<?php
/**
 * Billing Automation API Controller
 */
header('Content-Type: application/json');
session_start();

// Kontrolloni aksesin admin
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../classes/BillingAutomation.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

$billing = new BillingAutomation($pdo);

try {
    switch ($action) {
        case 'generate_invoice':
            if ($method !== 'POST') throw new Exception('Method not allowed');
            
            $subscriptionId = intval($_POST['subscription_id'] ?? 0);
            $planId = intval($_POST['plan_id'] ?? 0);
            
            if (!$subscriptionId || !$planId) {
                throw new Exception('subscription_id dhe plan_id janë të detyrueshme');
            }
            
            $invoiceId = $billing->generateInvoice($subscriptionId, $planId);
            if ($invoiceId) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Fatura u gjeneru me sukses',
                    'invoice_id' => $invoiceId
                ]);
            } else {
                throw new Exception('Gabim gjatë gjenerimit të fatures');
            }
            break;

        case 'check_overdue':
            if ($method !== 'POST') throw new Exception('Method not allowed');
            
            $count = $billing->checkOverduePayments();
            echo json_encode([
                'success' => true,
                'message' => 'U kontrolluan pagesa vonuese',
                'overdue_count' => $count
            ]);
            break;

        case 'send_reminders':
            if ($method !== 'POST') throw new Exception('Method not allowed');
            
            $count = $billing->sendScheduledReminders();
            echo json_encode([
                'success' => true,
                'message' => 'U dërguan kujtesa të planifikuara',
                'reminders_sent' => $count
            ]);
            break;

        case 'send_overdue_reminders':
            if ($method !== 'POST') throw new Exception('Method not allowed');
            
            $count = $billing->sendOverdueReminders();
            echo json_encode([
                'success' => true,
                'message' => 'U dërguan kujtesa të vonuese',
                'reminders_sent' => $count
            ]);
            break;

        case 'check_expiring':
            if ($method !== 'POST') throw new Exception('Method not allowed');
            
            $days = intval($_POST['days'] ?? 14);
            $expiringSubscriptions = $billing->checkExpiringSubscriptions($days);
            
            echo json_encode([
                'success' => true,
                'message' => 'U kontrolluan abonimet e afërta të skadimit',
                'count' => count($expiringSubscriptions),
                'data' => $expiringSubscriptions
            ]);
            break;

        case 'update_expired':
            if ($method !== 'POST') throw new Exception('Method not allowed');
            
            $billing->updateExpiredSubscriptions();
            echo json_encode([
                'success' => true,
                'message' => 'Abonimet e skaduar u përditësuan'
            ]);
            break;

        case 'create_payment_plan':
            if ($method !== 'POST') throw new Exception('Method not allowed');
            
            $delayId = intval($_POST['delay_id'] ?? 0);
            $installments = intval($_POST['installments'] ?? 3);
            
            if (!$delayId) throw new Exception('delay_id është i detyrueshëm');
            
            if ($billing->createPaymentPlan($delayId, $installments)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Plan pagese u krijua'
                ]);
            } else {
                throw new Exception('Gabim gjatë krijimit të planit të pagesës');
            }
            break;

        case 'run_daily_tasks':
            if ($method !== 'POST') throw new Exception('Method not allowed');
            
            // Ekzekuto të gjitha detyrat ditore
            $billing->checkOverduePayments();
            $billing->sendScheduledReminders();
            $billing->sendOverdueReminders();
            $billing->updateExpiredSubscriptions();
            
            echo json_encode([
                'success' => true,
                'message' => 'Të gjitha detyrat ditore përfunduan'
            ]);
            break;

        default:
            throw new Exception('Aksion i panjohur: ' . $action);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
