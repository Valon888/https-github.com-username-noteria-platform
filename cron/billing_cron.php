<?php
/**
 * BILLING AUTOMATION CRON JOB
 * Ky file duhet të ekzekutohet cdo ditë (përshembull në 2 të mëngjesit)
 * 
 * Konfigurimi në Linux/Server:
 * 0 2 * * * php /path/to/noteria/cron/billing_cron.php
 * 
 * Ose thirrej manualisht: curl https://noteria.com/cron/billing_cron.php?key=YOUR_SECRET_KEY
 */

// Kontrolloni çelësin e sigurimit (opsional, por rekomanduar)
$CRON_SECRET_KEY = 'YOUR_SECRET_CRON_KEY_HERE'; // Ndrysho këtë në një çelës të fuqishëm

if (php_sapi_name() !== 'cli' && php_sapi_name() !== 'cgi') {
    // Nëse thirret përmes HTTP, kontrolloni çelësin
    $key = $_GET['key'] ?? '';
    if ($key !== $CRON_SECRET_KEY) {
        http_response_code(403);
        echo json_encode(['error' => 'Accesso i ndaluar']);
        exit;
    }
}

// Mos shfaq errore në ekran (log ato në vend të asaj)
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/billing_cron.log');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/BillingAutomation.php';

$startTime = microtime(true);
$log = [];

try {
    $billing = new BillingAutomation($pdo);

    // 1. Kontrollo abonimet e afërta të skadimit (14 ditë përpara)
    $log[] = "[" . date('Y-m-d H:i:s') . "] - Başladı kontrol espirations...";
    $expiringCount = count($billing->checkExpiringSubscriptions(14));
    $log[] = "  → U gjetën {$expiringCount} abonimet e afërta të skadimit";

    // 2. Kontrollo pagesat vonuese
    $log[] = "[" . date('Y-m-d H:i:s') . "] - Başladı kontrol overdue payments...";
    $overdueCount = $billing->checkOverduePayments();
    $log[] = "  → U gjetën {$overdueCount} fatura të vonuesa të reja";

    // 3. Dërgo kujetsa të planifikuara (3 ditë përpara, 1 ditë përpara)
    $log[] = "[" . date('Y-m-d H:i:s') . "] - Başladı sending scheduled reminders...";
    $remindersSent = $billing->sendScheduledReminders();
    $log[] = "  → U dërguan {$remindersSent} kujtesa të planifikuara";

    // 4. Dërgo kujetsa për pagesat vonuese
    $log[] = "[" . date('Y-m-d H:i:s') . "] - Başladı sending overdue reminders...";
    $overdueRemindersSent = $billing->sendOverdueReminders();
    $log[] = "  → U dërguan {$overdueRemindersSent} kujtesa të vonuese";

    // 5. Përditëso abonimet e skaduar
    $log[] = "[" . date('Y-m-d H:i:s') . "] - Başladı updating expired subscriptions...";
    $billing->updateExpiredSubscriptions();
    $log[] = "  → Abonimet e skaduar u përditësuan";

    // 6. Gjenero fatura të reja për abonimet me recurrence
    $log[] = "[" . date('Y-m-d H:i:s') . "] - Başladı generating recurring invoices...";
    
    $sql = "SELECT s.id, s.plan_id 
            FROM subscription s
            WHERE s.status = 'active'
            AND (s.next_billing_date IS NULL OR s.next_billing_date <= NOW())
            LIMIT 100"; // Limito në 100 për të shmangur overload
    
    $stmt = $pdo->query($sql);
    $subscriptionsToInvoice = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $invoicesGenerated = 0;
    foreach ($subscriptionsToInvoice as $sub) {
        if ($billing->generateInvoice($sub['id'], $sub['plan_id'])) {
            $invoicesGenerated++;
        }
    }
    $log[] = "  → U gjeneriuan {$invoicesGenerated} fatura të reja";

    $duration = round((microtime(true) - $startTime) * 1000, 2);
    $log[] = "\n[" . date('Y-m-d H:i:s') . "] - CRON JOB PËRFUNDOI - Koha: {$duration}ms\n";

    // Log rezultatet
    $logMessage = implode("\n", $log);
    error_log($logMessage);

    // Përgjigje JSON
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'duration_ms' => $duration,
        'tasks' => [
            'expired_subscriptions_checked' => $expiringCount,
            'overdue_payments_detected' => $overdueCount,
            'scheduled_reminders_sent' => $remindersSent,
            'overdue_reminders_sent' => $overdueRemindersSent,
            'invoices_generated' => $invoicesGenerated
        ],
        'status' => 'COMPLETED'
    ]);

} catch (Exception $e) {
    $error = "GABIM: " . $e->getMessage() . " (" . $e->getFile() . ":" . $e->getLine() . ")";
    error_log($error);
    
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $error,
        'timestamp' => date('Y-m-d H:i:s'),
        'status' => 'FAILED'
    ]);
}
