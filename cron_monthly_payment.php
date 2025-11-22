<?php
function dergoEmailPagesa($to, $emri, $shuma, $status, $data) {
    $subject = "Njoftim për pagesën e abonimit | Noteria";
    $message = "Përshëndetje $emri,\n\n" .
        "Pagesa juaj për abonimin në Noteria është: $status.\n" .
        "Shuma: €$shuma\n" .
        "Data: $data\n\n" .
        "Ju faleminderit që përdorni platformën Noteria!";
    $headers = "From: Noteria <noreply@noteria.com>\r\n" .
        "Content-Type: text/plain; charset=UTF-8";
    @mail($to, $subject, $message, $headers);
}
// Skript automatik për pagesa mujore/vjetore me Stripe dhe Tink
// Run this file via cron çdo ditë

require 'vendor/autoload.php';
require_once 'db_connect.php'; // lidhet me $pdo

// Stripe setup
$stripeApiKey = getenv('STRIPE_API_KEY');
use Stripe\StripeClient;
$stripe = new StripeClient($stripeApiKey);

// Tink setup
$tinkAccessToken = getenv('TINK_ACCESS_TOKEN');
use GuzzleHttp\Client;
$tinkClient = new Client(['base_uri' => 'https://api.tink.com']);

/*
 * Minimal helper classes to provide the missing types used later in the script.
 * These are small wrappers that return a consistent structure so the rest of the
 * script can log results without causing "undefined class" errors.
 */
class StripeAutoPayment {
    private $client;
    public function __construct($apiKey) {
        $this->client = new \Stripe\StripeClient($apiKey);
    }
    /**
     * Attempt to find an active subscription for the customer and return a simple result array.
     * Returns ['success' => bool, 'id' => string|null, 'details' => mixed]
     */
    public function chargeRecurring($customerId) {
        try {
            $subs = $this->client->subscriptions->all(['customer' => $customerId, 'status' => 'active']);
            foreach ($subs as $sub) {
                return ['success' => ($sub->status === 'active'), 'id' => $sub->id, 'details' => $sub];
            }
            return ['success' => false, 'message' => 'No active subscription found'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}

class TinkAutoPayment {
    private $accessToken;
    private $client;
    public function __construct($accessToken) {
        $this->accessToken = $accessToken;
        $this->client = new \GuzzleHttp\Client(['base_uri' => 'https://api.tink.com']);
    }
    /**
     * Attempt to create a SEPA payment and return a simple result array.
     * Returns ['success' => bool, 'id' => string|null, 'details' => mixed]
     */
    public function chargeRecurring($mandateId, $amount = null) {
        try {
            $payload = ['mandate_id' => $mandateId];
            if ($amount !== null) {
                $payload['amount'] = $amount;
                $payload['currency'] = 'EUR';
            }
            $response = $this->client->post('/api/v1/sepa/payments', [
                'headers' => ['Authorization' => 'Bearer ' . $this->accessToken],
                'json' => $payload
            ]);
            $result = json_decode($response->getBody(), true);
            return ['success' => (($result['status'] ?? '') === 'EXECUTED'), 'id' => $result['id'] ?? null, 'details' => $result];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}

// Merr abonimet që duhet të paguhen sot
$stmt = $pdo->query("SELECT * FROM abonimet WHERE next_payment <= CURDATE() AND status = 'aktiv'");
$abonimet = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($abonimet as $abonim) {
    $success = false;
    $details = '';
    $transaction_id = '';
    $user_email = '';
    $user_emri = '';
    // Merr emailin dhe emrin e përdoruesit
    $stmtUser = $pdo->prepare("SELECT email, emri FROM users WHERE id = ?");
    $stmtUser->execute([$abonim['user_id']]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $user_email = $user['email'];
        $user_emri = $user['emri'];
    }
    if ($abonim['payment_method'] == 'stripe' && !empty($abonim['stripe_customer_id'])) {
        // Stripe: pagesa automatike
        try {
            // Stripe subscriptions janë automatike, por mund të kontrolloni statusin
            $subscriptions = $stripe->subscriptions->all(['customer' => $abonim['stripe_customer_id'], 'status' => 'active']);
            foreach ($subscriptions as $sub) {
                $transaction_id = $sub->id;
                $details = json_encode($sub);
                $success = ($sub->status === 'active');
            }
        } catch (Exception $e) {
            $details = $e->getMessage();
        }
    } elseif ($abonim['payment_method'] == 'tink' && !empty($abonim['tink_user_id'])) {
        // Tink: pagesa automatike SEPA
        try {
            $response = $tinkClient->post('/api/v1/sepa/payments', [
                'headers' => ['Authorization' => 'Bearer ' . $tinkAccessToken],
                'json' => [
                    'mandate_id' => $abonim['tink_user_id'],
                    'amount' => $abonim['cmimi'],
                    'currency' => 'EUR'
                ]
            ]);
            $result = json_decode($response->getBody(), true);
            $transaction_id = $result['id'] ?? '';
            $details = json_encode($result);
            $success = ($result['status'] ?? '') === 'EXECUTED';
        } catch (Exception $e) {
            $details = $e->getMessage();
        }
    }

    // Logo transaksionin
    $pdo->prepare("INSERT INTO transaksionet (abonim_id, user_id, amount, payment_date, payment_status, payment_provider, transaction_id, details) VALUES (?, ?, ?, NOW(), ?, ?, ?, ?)")
        ->execute([
            $abonim['id'],
            $abonim['user_id'],
            $abonim['cmimi'],
            $success ? 'sukses' : 'deshtuar',
            $abonim['payment_method'],
            $transaction_id,
            $details
        ]);
    // Dërgo email njoftimi
    if ($user_email && $user_emri) {
        dergoEmailPagesa($user_email, $user_emri, $abonim['cmimi'], $success ? 'Sukses' : 'Deshtuar', date('Y-m-d H:i:s'));
    }

    // Përditëso datën e pagesës së ardhshme në abonime
    if ($success) {
        $next = ($abonim['lloji'] == 'mujor') ? date('Y-m-d', strtotime('+1 month')) : date('Y-m-d', strtotime('+1 year'));
        $pdo->prepare("UPDATE abonimet SET last_payment = NOW(), next_payment = ?, status = 'aktiv' WHERE id = ?")
            ->execute([$next, $abonim['id']]);
    } else {
        $pdo->prepare("UPDATE abonimet SET status = 'pezulluar' WHERE id = ?")
            ->execute([$abonim['id']]);
    }
}

echo "Automated payment cron finished at " . date('Y-m-d H:i:s');
?>
<?php
// cron_monthly_payment.php
// Ky script duhet të ekzekutohet çdo muaj (cron job)
require_once 'config.php';

// Merr të gjitha zyrat me abonim aktiv
$stmt = $pdo->query("SELECT id, emri FROM zyrat WHERE abonim_aktiv = 1");
$zyrat = $stmt->fetchAll();

foreach ($zyrat as $zyra) {
    $zyra_id = $zyra['id'];
    $emri_zyres = $zyra['emri'];
    $shuma = 130;
    $banka = 'Automatik';
    $data_fature = date('Y-m-d H:i:s');
    $status_fature = 'Paguar';
    // Krijo faturën mujore
    try {
        $stmtF = $pdo->prepare('INSERT INTO faturat (zyra_id, banka, shuma, data, status) VALUES (?, ?, ?, ?, ?)');
        $stmtF->execute([$zyra_id, $banka, $shuma, $data_fature, $status_fature]);
        echo "Fatura mujore për zyrën $emri_zyres u krijua!<br>";
    } catch (Exception $ex) {
        echo "Gabim për zyrën $emri_zyres: " . $ex->getMessage() . "<br>";
    }
}

// ...lidhja me databazën...
// Merr abonimet që duhet të paguhen sot
$abonimet = $pdo->query("SELECT * FROM abonimet WHERE next_payment <= CURDATE() AND status = 'aktiv'");
foreach ($abonimet as $abonim) {
    if ($abonim['payment_method'] == 'stripe') {
        $stripe = new StripeAutoPayment($stripeApiKey);
        $result = $stripe->chargeRecurring($abonim['stripe_customer_id']);
        // Logo rezultatin në transaksionet
    } else if ($abonim['payment_method'] == 'tink') {
        $tink = new TinkAutoPayment($tinkAccessToken);
        $result = $tink->chargeRecurring($abonim['tink_user_id'], $abonim['cmimi']);
        // Logo rezultatin në transaksionet
    }
    // Përditëso datën e pagesës së ardhshme
}
?>
