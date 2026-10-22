<?php
/**
 * Sistemi për Ruajtjen e Metodave të Pagesës
 * Payment Method Storage System
 *
 * Lejon zyrave noteriale të ruajnë metoda pagese për faturim automatik
 */

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/payment_method_error.log');

require_once 'vendor/autoload.php';
require_once __DIR__ . '/env_loader.php';
require_once 'confidb.php';
require_once 'security.php';
require_once __DIR__ . '/session_helper.php';
initializeSecureSession();

\Stripe\Stripe::setApiKey(getenv('STRIPE_SECRET_KEY') ?: 'sk_test_...');

class PaymentMethodManager {
    private $pdo;
    private $stripe;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->stripe = new \Stripe\StripeClient(getenv('STRIPE_SECRET_KEY') ?: 'sk_test_...');
    }

    /**
     * Ruaj metodë pagese të re për zyrën
     */
    public function savePaymentMethod($officeId, $paymentMethodId, $userId = null) {
        try {
            // Merr detajet e payment method nga Stripe
            $paymentMethod = $this->stripe->paymentMethods->retrieve($paymentMethodId);

            // Krijo ose merr customer-in nga Stripe
            $customer = $this->getOrCreateStripeCustomer($officeId);

            // Attach payment method to customer
            $this->stripe->paymentMethods->attach($paymentMethodId, [
                'customer' => $customer->id
            ]);

            // Set as default payment method
            $this->stripe->customers->update($customer->id, [
                'invoice_settings' => [
                    'default_payment_method' => $paymentMethodId
                ]
            ]);

            // Ruaj në databazë
            $stmt = $this->pdo->prepare("
                INSERT INTO stored_payment_methods (
                    office_id, stripe_customer_id, stripe_payment_method_id,
                    card_last4, card_brand, is_default, status
                ) VALUES (?, ?, ?, ?, ?, 1, 'active')
                ON DUPLICATE KEY UPDATE
                    stripe_payment_method_id = VALUES(stripe_payment_method_id),
                    card_last4 = VALUES(card_last4),
                    card_brand = VALUES(card_brand),
                    updated_at = NOW()
            ");

            $stmt->execute([
                $officeId,
                $customer->id,
                $paymentMethodId,
                $paymentMethod->card->last4,
                $paymentMethod->card->brand
            ]);

            // Log activity
            $this->logActivity($userId, $officeId, 'payment_method_saved', "U ruajt metoda e pagesës: {$paymentMethod->card->brand} ****{$paymentMethod->card->last4}");

            return [
                'success' => true,
                'message' => 'Metoda e pagesës u ruajt me sukses.',
                'customer_id' => $customer->id
            ];

        } catch (Exception $e) {
            $this->logActivity($userId, $officeId, 'payment_method_error', "Gabim në ruajtjen e metodës së pagesës: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Gabim në ruajtjen e metodës së pagesës: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Merr ose krijo customer në Stripe për zyrën
     */
    private function getOrCreateStripeCustomer($officeId) {
        // Merr detajet e zyrës
        $stmt = $this->pdo->prepare("
            SELECT z.*, u.email, u.emri, u.mbiemri
            FROM zyrat z
            JOIN users u ON u.zyra_id = z.id
            WHERE z.id = ?
        ");
        $stmt->execute([$officeId]);
        $office = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$office) {
            throw new Exception("Zyra noteriale nuk u gjet.");
        }

        // Kontrollo nëse ka customer ekzistues
        $stmt = $this->pdo->prepare("SELECT stripe_customer_id FROM stored_payment_methods WHERE office_id = ? LIMIT 1");
        $stmt->execute([$officeId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing && $existing['stripe_customer_id']) {
            try {
                return $this->stripe->customers->retrieve($existing['stripe_customer_id']);
            } catch (Exception $e) {
                // Customer nuk ekziston më, krijo të ri
            }
        }

        // Krijo customer të ri
        $customer = $this->stripe->customers->create([
            'email' => $office['email'],
            'name' => $office['emri'] . ' ' . $office['mbiemri'],
            'metadata' => [
                'office_id' => $officeId,
                'office_name' => $office['emri']
            ]
        ]);

        return $customer;
    }

    /**
     * Merr metodat e pagesës për zyrën
     */
    public function getPaymentMethods($officeId) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM stored_payment_methods
            WHERE office_id = ? AND status = 'active'
            ORDER BY is_default DESC, created_at DESC
        ");
        $stmt->execute([$officeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fshi metodë pagese
     */
    public function deletePaymentMethod($methodId, $officeId, $userId = null) {
        try {
            // Merr detajet para fshirjes
            $stmt = $this->pdo->prepare("SELECT * FROM stored_payment_methods WHERE id = ? AND office_id = ?");
            $stmt->execute([$methodId, $officeId]);
            $method = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$method) {
                return ['success' => false, 'message' => 'Metoda e pagesës nuk u gjet.'];
            }

            // Detach nga Stripe
            $this->stripe->paymentMethods->detach($method['stripe_payment_method_id']);

            // Fshi nga databaza
            $stmt = $this->pdo->prepare("DELETE FROM stored_payment_methods WHERE id = ? AND office_id = ?");
            $stmt->execute([$methodId, $officeId]);

            $this->logActivity($userId, $officeId, 'payment_method_deleted', "U fshi metoda e pagesës: {$method['card_brand']} ****{$method['card_last4']}");

            return ['success' => true, 'message' => 'Metoda e pagesës u fshi me sukses.'];

        } catch (Exception $e) {
            $this->logActivity($userId, $officeId, 'payment_method_delete_error', "Gabim në fshirjen e metodës së pagesës: " . $e->getMessage());
            return ['success' => false, 'message' => 'Gabim në fshirjen e metodës së pagesës.'];
        }
    }

    /**
     * Vendos metodë pagese si default
     */
    public function setDefaultPaymentMethod($methodId, $officeId, $userId = null) {
        try {
            // Merr detajet e metodës
            $stmt = $this->pdo->prepare("SELECT * FROM stored_payment_methods WHERE id = ? AND office_id = ?");
            $stmt->execute([$methodId, $officeId]);
            $method = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$method) {
                return ['success' => false, 'message' => 'Metoda e pagesës nuk u gjet.'];
            }

            // Update Stripe customer
            $this->stripe->customers->update($method['stripe_customer_id'], [
                'invoice_settings' => [
                    'default_payment_method' => $method['stripe_payment_method_id']
                ]
            ]);

            // Update database
            $this->pdo->beginTransaction();

            // Hiq default nga të tjerat
            $stmt = $this->pdo->prepare("UPDATE stored_payment_methods SET is_default = 0 WHERE office_id = ?");
            $stmt->execute([$officeId]);

            // Vendos këtë si default
            $stmt = $this->pdo->prepare("UPDATE stored_payment_methods SET is_default = 1 WHERE id = ?");
            $stmt->execute([$methodId]);

            $this->pdo->commit();

            $this->logActivity($userId, $officeId, 'payment_method_default', "U vendos si default metoda e pagesës: {$method['card_brand']} ****{$method['card_last4']}");

            return ['success' => true, 'message' => 'Metoda e pagesës u vendos si default.'];

        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->logActivity($userId, $officeId, 'payment_method_default_error', "Gabim në vendosjen e default: " . $e->getMessage());
            return ['success' => false, 'message' => 'Gabim në vendosjen e metodës së pagesës si default.'];
        }
    }

    /**
     * Log activity
     */
    private function logActivity($userId, $officeId, $action, $message) {
        $stmt = $this->pdo->prepare("
            INSERT INTO activity_logs (log_type, user_id, status, message, created_at)
            VALUES (?, ?, 'info', ?, NOW())
        ");
        $stmt->execute([$action, $userId, "Office ID {$officeId}: {$message}"]);
    }
}

// API endpoint për ruajtjen e metodës së pagesës
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    $manager = new PaymentMethodManager($pdo);

    // Kontrollo autentifikimin
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Ju lutemi kyçuni.']);
        exit;
    }

    $userId = $_SESSION['user_id'];
    $officeId = $_POST['office_id'] ?? null;

    // Merr office_id nga user nëse nuk është specifikuar
    if (!$officeId) {
        $stmt = $pdo->prepare("SELECT zyra_id FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $officeId = $user['zyra_id'];
    }

    switch ($_POST['action']) {
        case 'save_payment_method':
            $paymentMethodId = $_POST['payment_method_id'] ?? '';
            if (empty($paymentMethodId)) {
                echo json_encode(['success' => false, 'message' => 'ID e metodës së pagesës mungon.']);
                exit;
            }
            $result = $manager->savePaymentMethod($officeId, $paymentMethodId, $userId);
            echo json_encode($result);
            break;

        case 'delete_payment_method':
            $methodId = $_POST['method_id'] ?? '';
            $result = $manager->deletePaymentMethod($methodId, $officeId, $userId);
            echo json_encode($result);
            break;

        case 'set_default_payment_method':
            $methodId = $_POST['method_id'] ?? '';
            $result = $manager->setDefaultPaymentMethod($methodId, $officeId, $userId);
            echo json_encode($result);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Veprim i pavlefshëm.']);
    }
    exit;
}
?>