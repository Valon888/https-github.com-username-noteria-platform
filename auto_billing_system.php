<?php
/**
 * Sistema Automatike e Faturimit Mujor
 * Automatic Monthly Billing System for Notary Offices
 * 
 * Ky skedar duhet të thirret çdo ditë në ora 07:00 të mëngjesit
 * This file should be called every day at 07:00 AM
 * 
 * Cron Job Command:
 * 0 7 * * * /usr/bin/php /path/to/noteria/auto_billing_system.php
 */

// Konfigurimi i raportimit të gabimeve
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/billing_error.log');

require_once 'config.php';
require_once 'confidb.php';

// Log për të ndjekur ekzekutimet
function logBilling($message) {
    $logFile = __DIR__ . '/billing_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

logBilling("=== Fillim i procesit të faturimit automatik ===");

try {
    // Kontrollo nëse është dita e duhur për faturim (1 e muajit)
    $today = date('d');
    $currentMonth = date('m');
    $currentYear = date('Y');
    
    if ($today != '01') {
        logBilling("Sot nuk është dita e faturimit (1 e muajit). Dita aktuale: $today");
        exit();
    }
    
    logBilling("Sot është dita e faturimit. Duke filluar procesin...");
    
    // Merr të gjithë noterët aktivë që kanë kaluar të paktën një muaj prej regjistrimit
    $stmt = $pdo->prepare("
        SELECT 
            id, 
            emri, 
            mbiemri, 
            email, 
            telefoni,
            subscription_type,
            custom_price,
            data_regjistrimit,
            status
        FROM noteri 
        WHERE status = 'active' 
        AND DATE_ADD(data_regjistrimit, INTERVAL 1 MONTH) <= CURDATE()
        AND id NOT IN (
            SELECT noter_id 
            FROM subscription_payments 
            WHERE YEAR(payment_date) = ? 
            AND MONTH(payment_date) = ?
            AND status = 'completed'
        )
    ");
    
    $stmt->execute([$currentYear, $currentMonth]);
    $notersToCharge = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($notersToCharge)) {
        logBilling("Nuk ka noterë për t'u faturuar këtë muaj.");
        exit();
    }
    
    logBilling("U gjetën " . count($notersToCharge) . " noterë për faturim.");
    
    // Përcakto çmimin standard për të gjithë noterët
    $standardPrice = 150.00;
    
    $successfulCharges = 0;
    $failedCharges = 0;
    
    foreach ($notersToCharge as $noter) {
        try {
            // Përcakto çmimin - standard për të gjithë ose custom nëse është specifikuar
            $amount = $standardPrice;
            
            if (!empty($noter['custom_price'])) {
                $amount = floatval($noter['custom_price']);
            }
            
            // Gjenero një ID unik për transaksionin
            $transactionId = 'AUTO_' . date('Ymd') . '_' . $noter['id'] . '_' . uniqid();
            
            // Krijo regjistrimin e pagesës
            $insertStmt = $pdo->prepare("
                INSERT INTO subscription_payments (
                    noter_id,
                    amount,
                    currency,
                    payment_method,
                    transaction_id,
                    payment_date,
                    due_date,
                    status,
                    billing_period_start,
                    billing_period_end,
                    created_at,
                    payment_type,
                    notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)
            ");
            
            $billingStart = date('Y-m-01'); // Fillimi i muajit aktual
            $billingEnd = date('Y-m-t'); // Fundi i muajit aktual
            $dueDate = date('Y-m-d', strtotime('+7 days')); // 7 ditë për të paguar
            
            $insertStmt->execute([
                $noter['id'],
                $amount,
                'EUR',
                'auto_charge',
                $transactionId,
                date('Y-m-d H:i:s'),
                $dueDate,
                'pending', // Fillimisht pending, do të ndryshojë në 'completed' pas pagesës së suksesshme
                $billingStart,
                $billingEnd,
                'automatic',
                "Faturim automatik për muajin " . date('m/Y')
            ]);
            
            // Simulo procesin e pagesës (këtu do të integrohet sistemi i pagesave real)
            $paymentSuccess = processAutoPayment($noter, $amount, $transactionId);
            
            if ($paymentSuccess) {
                // Përditëso statusin në 'completed'
                $updateStmt = $pdo->prepare("
                    UPDATE subscription_payments 
                    SET status = 'completed', 
                        processed_at = NOW(),
                        notes = CONCAT(notes, ' - Paguar automatikisht më ', NOW())
                    WHERE transaction_id = ?
                ");
                $updateStmt->execute([$transactionId]);
                
                $successfulCharges++;
                logBilling("Faturim i suksesshëm për {$noter['emri']} {$noter['mbiemri']} (ID: {$noter['id']}) - Shuma: €{$amount}");
                
                // Dërgo email njoftimi (opsionale)
                sendBillingNotification($noter, $amount, $transactionId, 'success');
                
            } else {
                // Pagesa dështoi, përditëso statusin në 'failed'
                $updateStmt = $pdo->prepare("
                    UPDATE subscription_payments 
                    SET status = 'failed', 
                        processed_at = NOW(),
                        notes = CONCAT(notes, ' - Pagesa dështoi më ', NOW())
                    WHERE transaction_id = ?
                ");
                $updateStmt->execute([$transactionId]);
                
                $failedCharges++;
                logBilling("Faturimi dështoi për {$noter['emri']} {$noter['mbiemri']} (ID: {$noter['id']}) - Shuma: €{$amount}");
                
                // Dërgo email njoftimi për dështim
                sendBillingNotification($noter, $amount, $transactionId, 'failed');
            }
            
        } catch (Exception $e) {
            $failedCharges++;
            logBilling("Gabim gjatë faturimit të {$noter['emri']} {$noter['mbiemri']}: " . $e->getMessage());
        }
    }
    
    // Log rezultatet përfundimtare
    logBilling("Procesi i faturimit u përfundua:");
    logBilling("- Faturime të suksesshme: $successfulCharges");
    logBilling("- Faturime të dështuara: $failedCharges");
    logBilling("- Totali: " . count($notersToCharge));
    
    // Ruaj statistikat në databazë
    $statsStmt = $pdo->prepare("
        INSERT INTO billing_statistics (
            billing_date,
            total_noters_processed,
            successful_charges,
            failed_charges,
            total_amount_charged,
            created_at
        ) VALUES (?, ?, ?, ?, ?, NOW())
    ");
    
    // Llogarit shumën totale të faturuar
    $totalAmountStmt = $pdo->prepare("
        SELECT SUM(amount) 
        FROM subscription_payments 
        WHERE DATE(payment_date) = CURDATE() 
        AND payment_type = 'automatic' 
        AND status = 'completed'
    ");
    $totalAmountStmt->execute();
    $totalAmount = $totalAmountStmt->fetchColumn() ?: 0;
    
    $statsStmt->execute([
        date('Y-m-d'),
        count($notersToCharge),
        $successfulCharges,
        $failedCharges,
        $totalAmount
    ]);
    
} catch (PDOException $e) {
    logBilling("Gabim në databazë: " . $e->getMessage());
} catch (Exception $e) {
    logBilling("Gabim i përgjithshëm: " . $e->getMessage());
}

logBilling("=== Fundi i procesit të faturimit automatik ===\n");

/**
 * Simulo procesin e pagesës automatike
 * Në një implementim real, kjo funksion do të integrohej me një sistem pagese si Stripe, PayPal, etj.
 */
function processAutoPayment($noter, $amount, $transactionId) {
    // Simulo vonesën e procesimit
    usleep(500000); // 0.5 sekonda
    
    // Simulo suksesin e pagesës (90% mundësi suksesi)
    $success = (rand(1, 100) <= 90);
    
    logBilling("Procesim pagese për {$noter['emri']} {$noter['mbiemri']} - Transaksioni: $transactionId - Rezultati: " . ($success ? "Sukses" : "Dështim"));
    
    return $success;
}

/**
 * Dërgo njoftim email për faturimin
 */
function sendBillingNotification($noter, $amount, $transactionId, $status) {
    $to = $noter['email'];
    $subject = ($status === 'success') 
        ? "Faturim i Suksesshëm - Abonimi Mujor" 
        : "Njoftim - Problem me Faturimin";
    
    if ($status === 'success') {
        $message = "
        Përshëndetje {$noter['emri']} {$noter['mbiemri']},
        
        Faturimi juaj mujor për platformën Noteria është procesuar me sukses.
        
        Detajet e pagesës:
        - Shuma: €{$amount}
        - ID e transaksionit: {$transactionId}
        - Data: " . date('d.m.Y H:i') . "
        - Periudha: " . date('m/Y') . "
        
        Faleminderit që jeni pjesë e platformës sonë!
        
        Me respekt,
        Ekipi i Noteria
        ";
    } else {
        $message = "
        Përshëndetje {$noter['emri']} {$noter['mbiemri']},
        
        Fatkeqësisht, nuk arritëm të procesojmë faturimin tuaj mujor.
        
        Detajet:
        - Shuma: €{$amount}
        - ID e transaksionit: {$transactionId}
        - Data e tentimit: " . date('d.m.Y H:i') . "
        
        Ju lutemi kontaktoni me ne ose përditësoni të dhënat e pagesës në platformë.
        
        Me respekt,
        Ekipi i Noteria
        ";
    }
    
    $headers = "From: noreply@noteria.com\r\n";
    $headers .= "Reply-To: support@noteria.com\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    // Në një implementim real, përdorni një shërbim email profesional
    // mail($to, $subject, $message, $headers);
    
    logBilling("Email njoftimi u dërgua për {$noter['emri']} {$noter['mbiemri']} - Status: $status");
}
?>