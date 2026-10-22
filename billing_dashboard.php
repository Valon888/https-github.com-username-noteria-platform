<?php
/**
 * Dashboard për Menaxhimin e Sistemit të Faturimit dhe Pagesave Automatike
 * Enhanced Automatic Billing and Payment System Management Dashboard
 */

// Fillimi i sigurt i sesionit - PARA require_once
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 1 : 0);
    ini_set('session.use_strict_mode', 1);
    session_start();
}

require_once 'config.php';
require_once 'confidb.php';
require_once 'developer_config.php';

// ==========================================
// KONTROLLO AUTORIZIMIN DHE ROLIN
// ==========================================
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?error=auth_required");
    exit();
}

$user_roli = $_SESSION['roli'] ?? 'user';

// 🔒 VETËM ADMIN MUND TË HYJ NË KËTË FAQE
if ($user_roli !== 'admin') {
    // Përdoruesit e thjeshtë dhe notaret NUK kanë akses
    header("Location: index.php?error=unauthorized_access");
    exit();
}

$isAdmin = true;
$isUser = false;

// Kontrollo nëse është super-admin (zhvillues) - vetëm për admin
$isSuperAdmin = false;
if (isset($_SESSION['admin_id'])) {
    $isSuperAdmin = function_exists('isDeveloper') ? call_user_func('isDeveloper', $_SESSION['admin_id'] ?? 0) : false;
}

$message = '';
$messageType = '';

// Procesi pagesat automatikisht (vetëm për super-admin)
if (isset($_GET['action']) && $_GET['action'] === 'process_auto_payments' && $isSuperAdmin) {
    try {
        $pdo->beginTransaction();
        
        // Merr pagesat në pritje
        $pendingStmt = $pdo->prepare("
            SELECT sp.*, n.emri, n.mbiemri, n.email, n.telefoni
            FROM subscription_payments sp 
            JOIN noteri n ON sp.noter_id = n.id 
            WHERE sp.status = 'pending' 
            AND sp.payment_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY sp.payment_date DESC
        ");
        $pendingStmt->execute();
        $pendingPayments = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $processedCount = 0;
        $successCount = 0;
        
        foreach ($pendingPayments as $payment) {
            // Procesohet pagesa automatikisht
            $paymentSuccess = processAutomaticPayment($payment, $pdo);
            
            if ($paymentSuccess) {
                // Përditëso statusin në 'completed'
                $updateStmt = $pdo->prepare("
                    UPDATE subscription_payments 
                    SET status = 'completed', 
                        processed_at = NOW(),
                        payment_type = 'automatic',
                        notes = CONCAT(COALESCE(notes, ''), ' - Paguar automatikisht më ', NOW())
                    WHERE id = ?
                ");
                $updateStmt->execute([$payment['id']]);
                $successCount++;
                
                // Log suksesin
                logAutomaticPayment("SUCCESS: Pagesa u procesua për Noter ID {$payment['noter_id']}, Shuma: €{$payment['amount']}");
            } else {
                // Përditëso statusin në 'failed'
                $updateStmt = $pdo->prepare("
                    UPDATE subscription_payments 
                    SET status = 'failed', 
                        processed_at = NOW(),
                        notes = CONCAT(COALESCE(notes, ''), ' - Pagesa dështoi më ', NOW())
                    WHERE id = ?
                ");
                $updateStmt->execute([$payment['id']]);
                
                // Log dështimin
                logAutomaticPayment("FAILED: Pagesa dështoi për Noter ID {$payment['noter_id']}, Shuma: €{$payment['amount']}");
            }
            $processedCount++;
        }
        
        $pdo->commit();
        
        $message = "U procesuan $processedCount pagesa automatikisht. $successCount pagesa u kompletuan me sukses.";
        $messageType = 'success';
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Gabim gjatë procesimit të pagesave: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Përditëso konfigurimet
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_config'])) {
    try {
        $configs = [
            'billing_time' => $_POST['billing_time'],
            'billing_day' => $_POST['billing_day'],
            'standard_price' => $_POST['standard_price'],
            'due_days' => $_POST['due_days'],
            'email_notifications' => isset($_POST['email_notifications']) ? '1' : '0',
            'auto_billing_enabled' => isset($_POST['auto_billing_enabled']) ? '1' : '0',
            'auto_payment_enabled' => isset($_POST['auto_payment_enabled']) ? '1' : '0'
        ];
        
        foreach ($configs as $key => $value) {
            $updateStmt = $pdo->prepare("
                INSERT INTO billing_config (config_key, config_value, description) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE config_value = ?, updated_at = NOW()
            ");
            $description = [
                'billing_time' => 'Ora e faturimit automatik',
                'billing_day' => 'Dita e muajit për faturim',
                'standard_price' => 'Çmimi mujor në EUR',
                'due_days' => 'Ditët për të paguar pas faturimit',
                'email_notifications' => 'Dërgo njoftimet email',
                'auto_billing_enabled' => 'Faturimi automatik i aktivizuar',
                'auto_payment_enabled' => 'Pagesat automatike të aktivizuara'
            ][$key] ?? '';
            
            $updateStmt->execute([$key, $value, $description, $value]);
        }
        
        $message = "Konfigurimet u përditësuan me sukses! Pagesat automatike janë " . ($configs['auto_payment_enabled'] ? 'aktivë' : 'joaktivë') . ".";
        $messageType = 'success';
        
    } catch (PDOException $e) {
        $message = "Gabim gjatë përditësimit: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Ekzekuto faturimin manual (vetëm për super-admin)
if (isset($_GET['action']) && $_GET['action'] === 'manual_billing' && $isSuperAdmin) {
    try {
        // Krijo një version të modifikuar të sistemit të faturimit për testim manual
        $manualBillingResults = runManualBilling($pdo);
        
        if ($manualBillingResults['success']) {
            $message = "Faturimi manual u ekzekutua me sukses!\n\n";
            $message .= "📊 Rezultatet:\n";
            $message .= "• Noterë të procesuar: {$manualBillingResults['processed']}\n";
            $message .= "• Faturime të suksesshme: {$manualBillingResults['successful']}\n";
            $message .= "• Faturime të dështuara: {$manualBillingResults['failed']}\n";
            $message .= "• Shuma totale: €" . number_format($manualBillingResults['total_amount'], 2) . "\n\n";
            $message .= "📝 Kontrollo 'billing_log.txt' për detaje të plota.";
            $messageType = 'success';
        } else {
            $message = "Faturimi manual u ekzekutua por pa rezultate:\n\n";
            $message .= $manualBillingResults['message'];
            $messageType = 'warning';
        }
        
    } catch (Exception $e) {
        $message = "Gabim gjatë faturimit manual: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Shiko log files (vetëm për super-admin)
if (isset($_GET['action']) && $_GET['action'] === 'view_logs' && $isSuperAdmin) {
    $logContent = '';
    $logFiles = ['billing_log.txt', 'auto_payments.log', 'billing_error.log'];
    
    foreach ($logFiles as $logFile) {
        if (file_exists(__DIR__ . '/' . $logFile)) {
            $content = file_get_contents(__DIR__ . '/' . $logFile);
            $logContent .= "=== $logFile ===\n";
            $logContent .= $content ? $content : "(Log file është bosh)\n";
            $logContent .= "\n" . str_repeat("=", 50) . "\n\n";
        }
    }
    
    if (empty($logContent)) {
        $message = "Nuk u gjetën log files. Sistemin duhet të ekzekutohet të paktën një herë për të krijuar logs.";
        $messageType = 'warning';
    } else {
        $message = "📋 Log Files:\n\n" . $logContent;
        $messageType = 'info';
    }
}

// Pastro pagesat test (vetëm për super-admin)
if (isset($_GET['action']) && $_GET['action'] === 'cleanup_test_payments' && $isSuperAdmin) {
    try {
        $pdo->beginTransaction();
        
        // Fshi pagesat test (status = 'test' ose notes që përmbajnë "Test")
        $cleanupStmt = $pdo->prepare("
            DELETE FROM subscription_payments 
            WHERE status = 'test'
            OR notes LIKE '%Test%' 
            OR notes LIKE '%test%'
            OR notes = 'Test'
        ");
        $cleanupStmt->execute();
        $deletedCount = $cleanupStmt->rowCount();
        
        $pdo->commit();
        
        $message = "U fshinë $deletedCount pagesa test nga sistemi.";
        $messageType = 'success';
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Gabim gjatë pastrimit: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Merr konfigurimet aktuale
try {
    $configStmt = $pdo->query("SELECT config_key, config_value FROM billing_config");
    $configs = [];
    while ($row = $configStmt->fetch(PDO::FETCH_ASSOC)) {
        $configs[$row['config_key']] = $row['config_value'];
    }
} catch (PDOException $e) {
    $configs = [
        'billing_time' => '07:00:00',
        'billing_day' => '1',
        'standard_price' => '150.00',
        'due_days' => '7',
        'email_notifications' => '1',
        'auto_billing_enabled' => '1',
        'auto_payment_enabled' => '1'
    ];
}

// Statistikat e përgjithshme
try {
    $totalRevenue = $pdo->query("
        SELECT SUM(amount) FROM subscription_payments 
        WHERE status = 'completed'
    ")->fetchColumn() ?: 0;

    $monthlyRevenue = $pdo->query("
        SELECT SUM(amount) FROM subscription_payments 
        WHERE status = 'completed' 
        AND MONTH(payment_date) = MONTH(CURDATE()) 
        AND YEAR(payment_date) = YEAR(CURDATE())
    ")->fetchColumn() ?: 0;

    $pendingPayments = $pdo->query("
        SELECT COUNT(*) FROM subscription_payments 
        WHERE status = 'pending'
    ")->fetchColumn() ?: 0;

    $autoProcessedToday = $pdo->query("
        SELECT COUNT(*) FROM subscription_payments 
        WHERE payment_type = 'automatic' 
        AND DATE(processed_at) = CURDATE()
    ")->fetchColumn() ?: 0;

    $testPayments = $pdo->query("
        SELECT COUNT(*) FROM subscription_payments 
        WHERE status = 'test' OR notes LIKE '%Test%' OR notes LIKE '%test%' OR notes = 'Test'
    ")->fetchColumn() ?: 0;
} catch (PDOException $e) {
    $totalRevenue = $monthlyRevenue = $pendingPayments = $autoProcessedToday = $testPayments = 0;
}

// ========== ADVANCED ANALYTICS ENGINE ==========

// TREND ANALYSIS (30-ditësh)
try {
    $trendData = $pdo->query("
        SELECT 
            DATE(payment_date) as date,
            COUNT(*) as count,
            SUM(amount) as amount,
            SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as completed_amount
        FROM subscription_payments
        WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(payment_date)
        ORDER BY date ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    $trendDates = json_encode(array_column($trendData, 'date'));
    $trendAmounts = json_encode(array_column($trendData, 'amount'));
} catch (Exception $e) {
    $trendDates = json_encode([]);
    $trendAmounts = json_encode([]);
}

// CHURN RISK PREDICTION
try {
    $churnRiskNotaries = $pdo->query("
        SELECT n.id, n.emri, n.mbiemri, n.email,
               MAX(sp.payment_date) as last_payment,
               DATEDIFF(CURDATE(), MAX(sp.payment_date)) as days_since,
               COUNT(sp.id) as total_payments
        FROM noteri n
        LEFT JOIN subscription_payments sp ON n.id = sp.noter_id
        WHERE n.aktiv = 1
        GROUP BY n.id
        HAVING days_since > 45 OR (last_payment IS NULL AND n.created_at < DATE_SUB(NOW(), INTERVAL 30 DAY))
        ORDER BY days_since DESC
        LIMIT 25
    ")->fetchAll(PDO::FETCH_ASSOC);
    $churnCount = count($churnRiskNotaries);
} catch (Exception $e) {
    $churnRiskNotaries = [];
    $churnCount = 0;
}

// REVENUE FORECAST (Predictive Analytics)
try {
    $lastMonthRevenue = $pdo->query("
        SELECT SUM(amount) FROM subscription_payments 
        WHERE status = 'completed' 
        AND MONTH(payment_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
    ")->fetchColumn() ?: 0;
    
    $last3MonthsAvg = ($lastMonthRevenue * 3) / 3;
    $projectedRevenue = $last3MonthsAvg;
} catch (Exception $e) {
    $projectedRevenue = 0;
    $last3MonthsAvg = 0;
}

// PAYMENT METHOD DISTRIBUTION
try {
    $paymentMethods = $pdo->query("
        SELECT 
            payment_method,
            COUNT(*) as count,
            SUM(amount) as total,
            ROUND(COUNT(*) * 100 / (SELECT COUNT(*) FROM subscription_payments WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)), 1) as percentage
        FROM subscription_payments
        WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
        GROUP BY payment_method
        ORDER BY total DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $paymentMethods = [];
}

// ADVANCED FILTERING & SEARCH
$filterStatus = $_GET['filter_status'] ?? 'all';
$filterMonth = $_GET['filter_month'] ?? date('Y-m');
$filterMinAmount = $_GET['filter_min_amount'] ?? '';
$filterMaxAmount = $_GET['filter_max_amount'] ?? '';
$filterPaymentMethod = $_GET['filter_method'] ?? 'all';
$searchTerm = $_GET['search'] ?? '';
$sortBy = $_GET['sort'] ?? 'payment_date';
$sortOrder = $_GET['order'] ?? 'DESC';

$whereClause = "1=1";
$params = [];

if ($filterStatus !== 'all') {
    $whereClause .= " AND sp.status = ?";
    $params[] = $filterStatus;
}

if (!empty($filterMonth)) {
    $whereClause .= " AND DATE_FORMAT(sp.payment_date, '%Y-%m') = ?";
    $params[] = $filterMonth;
}

if (!empty($filterMinAmount)) {
    $whereClause .= " AND sp.amount >= ?";
    $params[] = (float)$filterMinAmount;
}

if (!empty($filterMaxAmount)) {
    $whereClause .= " AND sp.amount <= ?";
    $params[] = (float)$filterMaxAmount;
}

if ($filterPaymentMethod !== 'all') {
    $whereClause .= " AND sp.payment_method = ?";
    $params[] = $filterPaymentMethod;
}

if (!empty($searchTerm)) {
    $whereClause .= " AND (n.emri LIKE ? OR n.mbiemri LIKE ? OR n.email LIKE ? OR sp.transaction_id LIKE ? OR sp.invoice_number LIKE ?)";
    $searchLike = "%$searchTerm%";
    $params = array_merge($params, [$searchLike, $searchLike, $searchLike, $searchLike, $searchLike]);
}

// Validimi i sortimit
$validSortFields = ['payment_date', 'amount', 'status', 'emri'];
$sortBy = (in_array($sortBy, $validSortFields) ? $sortBy : 'payment_date');
$sortOrder = (in_array($sortOrder, ['ASC', 'DESC']) ? $sortOrder : 'DESC');

// Merr pagesat me filtrim të avancuar
try {
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = 20;
    $offset = ($page - 1) * $limit;
    
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) FROM subscription_payments sp
        JOIN noteri n ON sp.noter_id = n.id
        WHERE $whereClause
    ");
    $countStmt->execute($params);
    $totalPayments = $countStmt->fetchColumn();
    
    $totalPages = ceil($totalPayments / $limit);
    
    $recentPayments = $pdo->prepare("
        SELECT 
            sp.*,
            n.emri,
            n.mbiemri,
            n.email,
            n.zyra_emri,
            n.nipt
        FROM subscription_payments sp
        JOIN noteri n ON sp.noter_id = n.id
        WHERE $whereClause
        ORDER BY sp.$sortBy $sortOrder
        LIMIT ? OFFSET ?
    ");
    
    $recentPayments->execute(array_merge($params, [$limit, $offset]));
    $recentPayments = $recentPayments->fetchAll(PDO::FETCH_ASSOC);
    
    // Kalkulim shtesat për filtered results
    $filteredCountStmt = $pdo->prepare("
        SELECT 
            SUM(sp.amount) as total_filtered,
            COUNT(*) as count_filtered,
            SUM(CASE WHEN sp.status = 'completed' THEN sp.amount ELSE 0 END) as completed_filtered
        FROM subscription_payments sp
        JOIN noteri n ON sp.noter_id = n.id
        WHERE $whereClause
    ");
    $filteredCountStmt->execute($params);
    $filteredStats = $filteredCountStmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $recentPayments = [];
    $totalPages = 0;
    $filteredStats = ['total_filtered' => 0, 'count_filtered' => 0, 'completed_filtered' => 0];
}

/**
 * Funksioni për procesin e pagesave automatike
 */
function processAutomaticPayment($payment, $pdo) {
    // Simulo integrimin me një sistem pagese të vërtetë
    $paymentMethods = ['visa', 'mastercard', 'sepa', 'bank_transfer'];
    $selectedMethod = $paymentMethods[array_rand($paymentMethods)];
    
    // Gjeneroj ID-në e transaksionit
    $transactionId = 'PAY-' . date('Ymd') . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
    
    // Simulo vonesën e procesimit (0.2-0.8 sekonda)
    usleep(rand(200000, 800000));
    
    // Simulo suksesin e pagesës (90% mundësi suksesi për pagesat automatike)
    $success = (rand(1, 100) <= 90);
    
    if ($success) {
        // Përditëso informacionet e transaksionit
        $payment['payment_method'] = $selectedMethod;
        $payment['transaction_id'] = $transactionId;
        
        // Përditëso statusin e pagesës në bazën e të dhënave
        $updateStmt = $pdo->prepare("
            UPDATE subscription_payments 
            SET payment_status = 'completed',
                payment_date = NOW(),
                payment_method = ?,
                transaction_id = ? 
            WHERE id = ?
        ");
        $updateStmt->execute([$selectedMethod, $transactionId, $payment['id']]);
        
        // Gjenero faturën elektronike
        $invoiceNumber = generateElectronicInvoice($payment, $pdo);
        
        // Ruaj numrin e faturës në objektin e pagesës për referencë në njoftim
        $payment['invoice_number'] = $invoiceNumber;
        
        // Dërgo njoftimin
        sendPaymentNotification($payment, 'success', $selectedMethod);
    } else {
        sendPaymentNotification($payment, 'failed', $selectedMethod);
    }
    
    return $success;
}

/**
 * Log për pagesat automatike
 */
function logAutomaticPayment($message) {
    $logFile = __DIR__ . '/auto_payments.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

/**
 * Gjeneron faturë elektronike dhe e ruan në sistem
 */
function generateElectronicInvoice($payment, $pdo) {
    // Gjenero numrin unik të faturës
    $invoiceNumber = 'INV-' . date('Ymd') . '-' . str_pad($payment['id'], 5, '0', STR_PAD_LEFT);
    
    // Rrumbullako shumën e TVSH-së dhe totalin
    $subtotal = round($payment['amount'] / 1.18, 2); // Supozojmë TVSH 18%
    $vat = round($payment['amount'] - $subtotal, 2);
    $total = $payment['amount'];
    
    // Gjej të dhënat e noterit
    $noterStmt = $pdo->prepare("SELECT emri, mbiemri, adresa, email, telefoni, nipt, zyra_emri FROM noteri WHERE id = ?");
    $noterStmt->execute([$payment['noter_id']]);
    $noter = $noterStmt->fetch(PDO::FETCH_ASSOC);
    
    // Gjenero HTML për faturën
    $invoiceHtml = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Faturë Elektronike - ' . $invoiceNumber . '</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .invoice-container { max-width: 800px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; }
            .invoice-header { display: flex; justify-content: space-between; padding-bottom: 20px; border-bottom: 2px solid #2d6cdf; }
            .invoice-title { font-size: 28px; color: #2d6cdf; font-weight: bold; }
            .invoice-details { margin-top: 20px; display: flex; justify-content: space-between; }
            .invoice-details-left, .invoice-details-right { width: 48%; }
            .invoice-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            .invoice-table th, .invoice-table td { padding: 10px; border-bottom: 1px solid #ddd; text-align: left; }
            .invoice-table th { background-color: #f8f9fa; }
            .invoice-total { margin-top: 20px; display: flex; justify-content: flex-end; }
            .invoice-total-table { width: 300px; }
            .invoice-total-table td { padding: 5px; }
            .invoice-total-table .total { font-weight: bold; font-size: 18px; border-top: 2px solid #ddd; }
            .footer { margin-top: 30px; border-top: 1px solid #ddd; padding-top: 10px; font-size: 12px; text-align: center; color: #777; }
            .qr-code { text-align: right; margin-top: 20px; }
            .signature { margin-top: 40px; }
            .signature-line { width: 200px; border-bottom: 1px solid #333; margin-bottom: 5px; }
        </style>
    </head>
    <body>
        <div class="invoice-container">
            <div class="invoice-header">
                <div>
                    <div class="invoice-title">FATURË ELEKTRONIKE</div>
                    <div>Nr. ' . $invoiceNumber . '</div>
                </div>
                <div>
                    <img src="assets/logo.png" alt="Noteria Logo" style="max-height: 80px;">
                    <div>Platforma Noteriale e Kosovës</div>
                </div>
            </div>
            
            <div class="invoice-details">
                <div class="invoice-details-left">
                    <h3>Shitësi</h3>
                    <p>
                        <strong>Noteria Sh.p.k.</strong><br>
                        Adresa: Rr. "Gazmend Zajmi" Nr. 24<br>
                        10000 Prishtinë, Kosovë<br>
                        NIPT: K91234567A<br>
                        Tel: +383 44 123 456<br>
                        Email: finance@noteria.com
                    </p>
                </div>
                <div class="invoice-details-right">
                    <h3>Klienti</h3>
                    <p>
                        <strong>' . htmlspecialchars($noter['zyra_emri'] ?: ($noter['emri'] . ' ' . $noter['mbiemri'])) . '</strong><br>
                        Adresa: ' . htmlspecialchars($noter['adresa'] ?: 'N/A') . '<br>
                        NIPT: ' . htmlspecialchars($noter['nipt'] ?: 'N/A') . '<br>
                        Tel: ' . htmlspecialchars($noter['telefoni'] ?: 'N/A') . '<br>
                        Email: ' . htmlspecialchars($noter['email'] ?: 'N/A') . '
                    </p>
                </div>
            </div>
            
            <div class="invoice-details">
                <div class="invoice-details-left">
                    <h3>Të dhënat e faturës</h3>
                    <p>
                        Data e faturës: ' . date('d.m.Y') . '<br>
                        Data e pagesës: ' . date('d.m.Y', strtotime($payment['payment_date'])) . '<br>
                        Periudha e faturimit: ' . date('d.m.Y', strtotime($payment['billing_period_start'])) . ' - ' . 
                        date('d.m.Y', strtotime($payment['billing_period_end'])) . '<br>
                        Metoda e pagesës: ' . ucfirst($payment['payment_method']) . '<br>
                        ID Transaksioni: ' . htmlspecialchars($payment['transaction_id'])  . '
                    </p>
                </div>
                <div class="invoice-details-right">
                    <div class="qr-code">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=' . urlencode($invoiceNumber) . '" alt="QR Code">
                    </div>
                </div>
            </div>
            
            <table class="invoice-table">
                <thead>
                    <tr>
                        <th>Përshkrimi</th>
                        <th>Sasia</th>
                        <th>Çmimi</th>
                        <th>TVSH (18%)</th>
                        <th>Vlera</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Abonimi mujor në platformën Noteria</td>
                        <td>1</td>
                        <td>€' . number_format($subtotal, 2) . '</td>
                        <td>€' . number_format($vat, 2) . '</td>
                        <td>€' . number_format($total, 2) . '</td>
                    </tr>
                </tbody>
            </table>
            
            <div class="invoice-total">
                <table class="invoice-total-table">
                    <tr>
                        <td>Nëntotali:</td>
                        <td>€' . number_format($subtotal, 2) . '</td>
                    </tr>
                    <tr>
                        <td>TVSH (18%):</td>
                        <td>€' . number_format($vat, 2) . '</td>
                    </tr>
                    <tr class="total">
                        <td>Totali:</td>
                        <td>€' . number_format($total, 2) . '</td>
                    </tr>
                </table>
            </div>
            
            <div class="signature">
                <div class="signature-line"></div>
                <div>Nënshkrimi i autorizuar</div>
            </div>
            
            <div class="footer">
                <p>Kjo faturë është gjeneruar elektronikisht dhe është e vlefshme pa nënshkrim dhe vulë.</p>
                <p>Pagesa është procesuar automatikisht përmes sistemit të Noteria.</p>
                <p>&copy; ' . date('Y') . ' Noteria. Të gjitha të drejtat e rezervuara.</p>
            </div>
        </div>
    </body>
    </html>';
    
    // Krijo direktorinë për faturat nëse nuk ekziston
    $invoicesDir = __DIR__ . '/faturat';
    if (!is_dir($invoicesDir)) {
        mkdir($invoicesDir, 0777, true);
    }
    
    // Ruaj faturën në sistem
    $invoicePath = $invoicesDir . '/' . $invoiceNumber . '.html';
    file_put_contents($invoicePath, $invoiceHtml);
    
    // Ruaj PDF version gjithashtu (në një implementim të plotë do të përdorej një librari si TCPDF ose mPDF)
    // Për demonstrim, po simulojmë gjenerimin e PDF-së
    $pdfPath = $invoicesDir . '/' . $invoiceNumber . '.pdf';
    // Në implementimin e plotë: $pdf = new TCPDF(); $pdf->writeHTML($invoiceHtml); $pdf->Output($pdfPath, 'F');
    // Për tani thjesht sinjalizojmë se PDF duhet gjeneruar më vonë
    file_put_contents($pdfPath . '.todo', 'PDF to be generated');
    
    // Shto referencën e faturës në bazën e të dhënave
    try {
        $stmt = $pdo->prepare("
            UPDATE subscription_payments
            SET invoice_number = ?, invoice_path = ?, invoice_created_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$invoiceNumber, $invoicePath, $payment['id']]);
        
        logAutomaticPayment("FATURE ELEKTRONIKE: U gjenerua fatura #{$invoiceNumber} për Noterin #{$payment['noter_id']}");
        return $invoiceNumber;
    } catch (Exception $e) {
        logAutomaticPayment("ERROR: Dështoi gjenerimi i faturës për Noterin #{$payment['noter_id']}: " . $e->getMessage());
        return false;
    }
}

/**
 * Dërgo njoftim për pagesën
 */
function sendPaymentNotification($payment, $status, $method = '') {
    $invoiceInfo = '';
    
    // Shto informacionin për faturën elektronike nëse ekziston
    if ($status === 'success' && isset($payment['invoice_number']) && $payment['invoice_number']) {
        $invoiceInfo = "\n\nFatura elektronike #{$payment['invoice_number']} u gjenerua automatikisht dhe është gati për shkarkim në panelin tuaj.";
    }
    
    $message = $status === 'success' 
        ? "✅ Pagesa juaj prej €{$payment['amount']} u procesua me sukses via $method.$invoiceInfo"
        : "❌ Pagesa juaj prej €{$payment['amount']} dështoi. Ju lutemi kontaktoni me ne.";
    
    logAutomaticPayment("NOTIFICATION: $message sent to {$payment['emri']} {$payment['mbiemri']} ({$payment['email']})");
}

/**
 * Ekzekuto faturimin manual për testim
 */
function runManualBilling($pdo) {
    $results = [
        'success' => false,
        'processed' => 0,
        'successful' => 0,
        'failed' => 0,
        'total_amount' => 0,
        'message' => ''
    ];
    
    try {
        // Log fillimin
        logAutomaticPayment("=== FATURIM MANUAL - Fillim ===");
        
        $currentMonth = date('m');
        $currentYear = date('Y');
        
        // Merr noterët që nuk janë faturuar këtë muaj
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
                AND (status = 'completed' OR status = 'pending')
            )
        ");
        
        $stmt->execute([$currentYear, $currentMonth]);
        $notersToCharge = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($notersToCharge)) {
            $results['message'] = "ℹ️ Nuk ka noterë për t'u faturuar këtë muaj.\n\n";
            $results['message'] .= "Këto mund të jenë arsyet:\n";
            $results['message'] .= "• Të gjithë noterët janë faturuar tashmë për këtë muaj\n";
            $results['message'] .= "• Nuk ka noterë aktivë të regjistruar\n";
            $results['message'] .= "• Noterët e regjistruar nuk kanë kaluar 1 muaj ende\n\n";
            $results['message'] .= "📅 Muaji aktual: " . date('m/Y');
            
            logAutomaticPayment("MANUAL: Nuk ka noterë për faturim");
            return $results;
        }
        
        logAutomaticPayment("MANUAL: U gjetën " . count($notersToCharge) . " noterë për faturim");
        
        $standardPrice = 150.00;
        
        foreach ($notersToCharge as $noter) {
            try {
                // Përcakto çmimin
                $amount = $standardPrice;
                if (!empty($noter['custom_price'])) {
                    $amount = floatval($noter['custom_price']);
                }
                
                // Gjenero ID transaksioni
                $transactionId = 'MANUAL_' . date('Ymd_His') . '_' . $noter['id'] . '_' . uniqid();
                
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
                
                $billingStart = date('Y-m-01');
                $billingEnd = date('Y-m-t');
                $dueDate = date('Y-m-d', strtotime('+7 days'));
                $paymentDate = date('Y-m-d H:i:s');
                
                $insertStmt->execute([
                    $noter['id'],
                    $amount,
                    'EUR',
                    'manual_billing',
                    $transactionId,
                    $paymentDate,
                    $dueDate,
                    'pending',
                    $billingStart,
                    $billingEnd,
                    'manual',
                    "Faturim manual për muajin " . date('m/Y') . " - Ekzekutuar nga admin"
                ]);
                
                // Merr ID e pagesës së sapo krijuar
                $paymentId = $pdo->lastInsertId();
                
                // Gjenero faturë elektronike për pagesën manuale
                // Krijo një objekt pagese për funksionin generateElectronicInvoice
                $paymentInfo = [
                    'id' => $paymentId,
                    'noter_id' => $noter['id'],
                    'amount' => $amount,
                    'payment_date' => $paymentDate,
                    'payment_method' => 'manual_billing',
                    'transaction_id' => $transactionId,
                    'billing_period_start' => $billingStart,
                    'billing_period_end' => $billingEnd,
                    'emri' => $noter['emri'],
                    'mbiemri' => $noter['mbiemri'],
                    'email' => $noter['email']
                ];
                
                // Gjenero faturë vetëm nëse pagesa është automatikisht e suksesshme
                if (isset($_GET['auto_complete']) && $_GET['auto_complete'] == 'true') {
                    // Përditëso statusin e pagesës në 'completed'
                    $updateStmt = $pdo->prepare("
                        UPDATE subscription_payments 
                        SET status = 'completed' 
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$paymentId]);
                    
                    // Gjenero faturën elektronike
                    $invoiceNumber = generateElectronicInvoice($paymentInfo, $pdo);
                    
                    if ($invoiceNumber) {
                        logAutomaticPayment("MANUAL BILLING: Fatura #{$invoiceNumber} u gjenerua për Noter ID {$noter['id']}");
                    }
                }
                
                $results['processed']++;
                $results['successful']++;
                $results['total_amount'] += $amount;
                
                logAutomaticPayment("MANUAL SUCCESS: {$noter['emri']} {$noter['mbiemri']} - €{$amount} - {$transactionId}");
                
            } catch (Exception $e) {
                $results['failed']++;
                logAutomaticPayment("MANUAL FAILED: {$noter['emri']} {$noter['mbiemri']} - " . $e->getMessage());
            }
        }
        
        $results['success'] = true;
        logAutomaticPayment("=== FATURIM MANUAL - Përfundim: {$results['successful']} sukses, {$results['failed']} dështim ===");
        
        return $results;
        
    } catch (Exception $e) {
        logAutomaticPayment("MANUAL ERROR: " . $e->getMessage());
        $results['message'] = "Gabim gjatë faturimit: " . $e->getMessage();
        return $results;
    }
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistemi i Pagesave Automatike | Noteria</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/fontawesome/all.min.css">
    <style>
        :root {
            --primary: #1a56db;
            --success: #16a34a;
            --danger: #dc2626;
            --warning: #f59e0b;
            --info: #0ea5e9;
            --light: #f9fafb;
            --dark: #1f2937;
            --card-bg: #ffffff;
            --border: #e5e7eb;
            --text: #374151;
            --heading: #111827;
            --gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background: var(--light);
            color: var(--text);
            line-height: 1.6;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .header {
            background: var(--gradient);
            color: white;
            padding: 2rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .header p {
            opacity: 0.9;
            font-size: 1.1rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--card-bg);
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 1.5rem;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.15);
        }

        .stat-icon {
            width: 70px;
            height: 70px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: white;
        }

        .stat-icon.revenue { background: linear-gradient(135deg, #16a34a, #22c55e); }
        .stat-icon.pending { background: linear-gradient(135deg, #f59e0b, #fbbf24); }
        .stat-icon.auto { background: linear-gradient(135deg, #0ea5e9, #38bdf8); }
        .stat-icon.monthly { background: linear-gradient(135deg, #8b5cf6, #a78bfa); }

        .stat-content h3 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--heading);
            margin-bottom: 0.25rem;
        }

        .stat-content p {
            color: var(--text);
            font-weight: 500;
        }

        .card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .card h2 {
            color: var(--heading);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.5rem;
        }

        .auto-payment-section {
            background: var(--gradient);
            color: white;
            padding: 2.5rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .auto-payment-section::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
            animation: shine 3s infinite;
        }

        @keyframes shine {
            0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
        }

        .auto-payment-section h2 {
            color: white;
            font-size: 2rem;
            margin-bottom: 1rem;
        }

        .auto-payment-section p {
            font-size: 1.1rem;
            opacity: 0.95;
            margin-bottom: 2rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--heading);
        }

        .form-control {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid var(--border);
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.2s;
            background: #f8fafc;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(26, 86, 219, 0.1);
            background: white;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 12px;
            border: 2px solid var(--border);
            transition: all 0.2s;
        }

        .checkbox-group:hover {
            border-color: var(--primary);
        }

        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            accent-color: var(--primary);
        }

        .btn {
            padding: 0.875rem 1.75rem;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: #1e40af;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(26, 86, 219, 0.3);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-warning {
            background: var(--warning);
            color: white;
        }

        .btn-info {
            background: var(--info);
            color: white;
        }

        .alert {
            padding: 1.25rem 1.75rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            font-weight: 500;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border-left: 4px solid var(--success);
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid var(--danger);
        }

        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border-left: 4px solid var(--warning);
        }

        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border-left: 4px solid var(--info);
        }

        .alert pre {
            margin: 0;
            font-family: inherit;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        .table-responsive {
            overflow-x: auto;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95rem;
            background: white;
        }

        th, td {
            padding: 1.25rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        th {
            background: #f8fafc;
            font-weight: 600;
            color: var(--heading);
            position: sticky;
            top: 0;
        }

        tr:hover {
            background: #f8fafc;
        }

        .badge {
            padding: 0.375rem 0.875rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .badge-success { background: #dcfce7; color: #166534; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-info { background: #dbeafe; color: #1e40af; }

        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }

        .pulse {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }

        .live-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: rgba(22, 195, 86, 0.1);
            color: var(--success);
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .live-dot {
            width: 8px;
            height: 8px;
            background: var(--success);
            border-radius: 50%;
            animation: pulse 1s infinite;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <h1><i class="fas fa-robot"></i> Sistemi i Pagesave Automatike</h1>
                    <p>Menaxhimi dhe procesimi automatik i pagesave për zyrat noteriale</p>
                </div>
                <div style="text-align: right;">
                    <?php if ($isSuperAdmin): ?>
                        <div style="background: rgba(255,193,7,0.2); color: #856404; padding: 0.5rem 1rem; border-radius: 20px; margin-bottom: 0.5rem;">
                            <i class="fas fa-code"></i> <strong>ZHVILLUES</strong>
                        </div>
                    <?php else: ?>
                        <div style="background: rgba(25,135,84,0.2); color: #155724; padding: 0.5rem 1rem; border-radius: 20px; margin-bottom: 0.5rem;">
                            <i class="fas fa-user-shield"></i> <strong>ADMIN</strong>
                        </div>
                    <?php endif; ?>
                    <a href="admin_logout.php" style="background: rgba(220,38,38,0.1); color: #991b1b; padding: 0.5rem 1rem; border-radius: 20px; text-decoration: none; font-size: 0.9rem;">
                        <i class="fas fa-sign-out-alt"></i> Dil
                    </a>
                </div>
            </div>
            <?php if (($configs['auto_payment_enabled'] ?? '0') === '1'): ?>
                <div class="live-indicator">
                    <div class="live-dot"></div>
                    LIVE - Pagesat automatike aktivë
                </div>
            <?php endif; ?>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <i class="fas fa-<?php 
                    echo $messageType === 'error' ? 'exclamation-circle' : 
                        ($messageType === 'warning' ? 'exclamation-triangle' : 
                        ($messageType === 'info' ? 'info-circle' : 'check-circle')); 
                ?>"></i>
                <pre><?php echo htmlspecialchars($message); ?></pre>
            </div>
        <?php endif; ?>

        <!-- Auto Payment Status -->
        <?php if (($configs['auto_payment_enabled'] ?? '0') === '1'): ?>
            <div class="auto-payment-section">
                <h2><i class="fas fa-magic"></i> Pagesat Automatike Aktivë</h2>
                <p>Sistemi po proceson pagesat automatikisht. Të gjitha pagesat në pritje do të procesohen automatikisht.</p>
                <?php if ($pendingPayments > 0): ?>
                    <div class="action-buttons" style="justify-content: center;">
                        <a href="?action=process_auto_payments" class="btn" style="background: rgba(255,255,255,0.2); color: white; border: 2px solid rgba(255,255,255,0.3);"
                           onclick="return confirm('Procesoni <?php echo $pendingPayments; ?> pagesat në pritje tani?')">
                            <i class="fas fa-bolt"></i> Procesoni <?php echo $pendingPayments; ?> Pagesa Tani
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Statistikat -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon revenue">
                    <i class="fas fa-euro-sign"></i>
                </div>
                <div class="stat-content">
                    <h3>€<?php echo number_format($totalRevenue, 2); ?></h3>
                    <p>Të hyrat totale</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon monthly">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-content">
                    <h3>€<?php echo number_format($monthlyRevenue, 2); ?></h3>
                    <p>Këtë muaj</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon pending">
                    <i class="fas fa-hourglass-half"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $pendingPayments; ?></h3>
                    <p>Pagesat në pritje</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon auto">
                    <i class="fas fa-robot"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $autoProcessedToday; ?></h3>
                    <p>Procesuar sot</p>
                </div>
            </div>

            <!-- ADVANCED METRICS -->
            <div class="stat-card" style="background: linear-gradient(135deg, #c084fc 0%, #a78bfa 100%); color: white; border: none;">
                <div class="stat-icon" style="background: rgba(255,255,255,0.2); color: white;">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-content">
                    <h3>€<?php echo number_format($projectedRevenue, 2); ?></h3>
                    <p>Parashikimi mujor</p>
                </div>
            </div>

            <div class="stat-card" style="background: linear-gradient(135deg, #f87171 0%, #ef4444 100%); color: white; border: none;">
                <div class="stat-icon" style="background: rgba(255,255,255,0.2); color: white;">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $churnCount; ?></h3>
                    <p>Në rrezik (Churn)</p>
                </div>
            </div>
        </div>
                <div class="stat-content">
                    <h3>€<?php echo number_format($monthlyRevenue, 2); ?></h3>
                    <p>Të hyrat e muajit</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon pending">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $pendingPayments; ?></h3>
                    <p>Pagesa në pritje</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon auto">
                    <i class="fas fa-robot"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $autoProcessedToday; ?></h3>
                    <p>Pagesa automatike sot</p>
                </div>
            </div>

            <?php if ($testPayments > 0): ?>
            <div class="stat-card" style="border-left: 4px solid var(--warning);">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #fbbf24);">
                    <i class="fas fa-flask"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $testPayments; ?></h3>
                    <p>Pagesa test për t'u pastruar</p>
                </div>
            </div>
            <?php endif; ?>
        </div>

                    <p>Të hyrat e muajit</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon pending">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $pendingPayments; ?></h3>
                    <p>Pagesa në pritje</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon auto">
                    <i class="fas fa-robot"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $autoProcessedToday; ?></h3>
                    <p>Pagesa automatike sot</p>
                </div>
            </div>

            <?php if ($testPayments > 0): ?>
            <div class="stat-card" style="border-left: 4px solid var(--warning);">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #fbbf24);">
                    <i class="fas fa-flask"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $testPayments; ?></h3>
                    <p>Pagesa test për t'u pastruar</p>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- ADVANCED FEATURES SECTION -->

        <!-- Payment Method Distribution Chart -->
        <?php if (!empty($paymentMethods)): ?>
        <div class="card">
            <h2><i class="fas fa-credit-card"></i> Metodat e Pagesës (90 ditë)</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
                <?php foreach ($paymentMethods as $method): ?>
                <div style="background: #f8fafc; padding: 1.5rem; border-radius: 12px; border-left: 4px solid var(--primary);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                        <strong style="font-size: 1.1rem; text-transform: uppercase;"><?php echo ucfirst(str_replace('_', ' ', $method['payment_method'])); ?></strong>
                        <span style="background: var(--primary); color: white; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.85rem; font-weight: 600;">
                            <?php echo $method['percentage']; ?>%
                        </span>
                    </div>
                    <div style="display: flex; justify-content: space-between; color: #666; font-size: 0.9rem;">
                        <div>
                            <p style="margin: 0.25rem 0;">Transaksione: <strong><?php echo $method['transactions']; ?></strong></p>
                            <p style="margin: 0.25rem 0;">Mesatare: <strong>€<?php echo number_format($method['avg_amount'], 2); ?></strong></p>
                        </div>
                        <div style="text-align: right;">
                            <p style="margin: 0.25rem 0; color: var(--success); font-weight: 600;">€<?php echo number_format($method['total_amount'], 2); ?></p>
                            <p style="margin: 0.25rem 0; font-size: 0.85rem;">✓ <?php echo $method['successful']; ?> të suksesshme</p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Churn Risk Analysis -->
        <?php if ($churnCount > 0): ?>
        <div class="card" style="border-left: 4px solid var(--danger);">
            <h2 style="color: var(--danger);"><i class="fas fa-alert-circle"></i> Analiza e Rrezikut të Përjashtimit (Churn Risk)</h2>
            <p style="color: #666; margin-bottom: 1.5rem;">Këta noterë nuk kanë paguar në 45 ditë të fundit dhe janë në rrezik të përjashtimit:</p>
            
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr style="background: #fee2e2;">
                            <th>Noter</th>
                            <th>Email</th>
                            <th>Ditë pa pagim</th>
                            <th>Pagesa totale</th>
                            <th>Aksion</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($churnRiskNotaries, 0, 10) as $noter): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($noter['emri'] . ' ' . $noter['mbiemri']); ?></strong></td>
                            <td><small><?php echo htmlspecialchars($noter['email']); ?></small></td>
                            <td><span style="background: #fee2e2; color: #991b1b; padding: 0.25rem 0.75rem; border-radius: 12px; font-weight: 600;">
                                <?php echo $noter['days_since'] ?? 'N/A'; ?> ditë
                            </span></td>
                            <td><?php echo $noter['total_payments']; ?> pagesa</td>
                            <td>
                                <a href="mailto:<?php echo htmlspecialchars($noter['email']); ?>" class="btn" style="background: var(--warning); color: white; padding: 0.35rem 0.75rem; font-size: 0.85rem;">
                                    <i class="fas fa-envelope"></i> Dërgo njoftim
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- ADVANCED FILTERING AND SEARCH -->
                        <select name="billing_day" class="form-control" required>
                            <?php for ($i = 1; $i <= 28; $i++): ?>
                                <option value="<?php echo $i; ?>" 
                                    <?php echo ($configs['billing_day'] ?? '1') == $i ? 'selected' : ''; ?>>
                                    <?php echo $i; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Çmimi mujor (€)</label>
                        <input type="number" name="standard_price" class="form-control" step="0.01" min="0"
                               value="<?php echo $configs['standard_price'] ?? '150.00'; ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Ditët për të paguar</label>
                        <input type="number" name="due_days" class="form-control" min="1" max="30"
                               value="<?php echo $configs['due_days'] ?? '7'; ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" name="email_notifications" id="email_notifications"
                               <?php echo ($configs['email_notifications'] ?? '1') == '1' ? 'checked' : ''; ?>>
                        <label for="email_notifications" class="form-label">Dërgo njoftimet email</label>
                    </div>
                </div>

                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" name="auto_billing_enabled" id="auto_billing_enabled"
                               <?php echo ($configs['auto_billing_enabled'] ?? '1') == '1' ? 'checked' : ''; ?>>
                        <label for="auto_billing_enabled" class="form-label">Aktivizo faturimin automatik</label>
                    </div>
                </div>

                <div class="form-group">
                    <div class="checkbox-group" style="border-color: var(--success); background: rgba(22, 195, 86, 0.05);">
                        <input type="checkbox" name="auto_payment_enabled" id="auto_payment_enabled"
                               <?php echo ($configs['auto_payment_enabled'] ?? '1') == '1' ? 'checked' : ''; ?>>
                        <label for="auto_payment_enabled" class="form-label">
                            <i class="fas fa-magic"></i> Aktivizo pagesat automatike
                        </label>
                    </div>
                </div>

                <div class="action-buttons">
                    <button type="submit" name="update_config" class="btn btn-primary">
                        <i class="fas fa-save"></i> Ruaj Konfigurimet
                    </button>
                    
                    <!-- ADVANCED REPORTS -->
                    <a href="advanced_reports.php" class="btn" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white;">
                        <i class="fas fa-chart-bar"></i> Raportet e Avancuara
                    </a>
                    
                    <a href="export_payments.php" class="btn" style="background: var(--success); color: white;">
                        <i class="fas fa-file-csv"></i> Shkarko CSV
                    </a>
                    
                    <!-- Veprime për të gjithë administratorët -->
                    <a href="admin_noters.php" class="btn btn-success">
                        <i class="fas fa-users"></i> Menaxho Noterët
                    </a>
                    
                    <?php if ($isSuperAdmin): ?>
                        <!-- Veprime vetëm për zhvilluesit/super-administratorët -->
                        <div style="margin-left: 2rem; padding-left: 2rem; border-left: 3px solid var(--warning);">
                            <small style="color: var(--warning); font-weight: 600; display: block; margin-bottom: 1rem;">
                                <i class="fas fa-code"></i> ZONA E ZHVILLUESVE
                            </small>
                            
                            <a href="?action=manual_billing" class="btn btn-warning" 
                               onclick="return confirm('⚠️ ZHVILLUES: Ekzekutoni faturimin manual?')">
                                <i class="fas fa-file-invoice"></i> Faturim Manual
                            </a>
                            
                            <a href="?action=process_auto_payments" class="btn btn-info" 
                               onclick="return confirm('⚠️ ZHVILLUES: Procesoni të gjitha pagesat në pritje?')">
                                <i class="fas fa-bolt"></i> Procesoni Pagesat
                            </a>
                            
                            <a href="?action=cleanup_test_payments" class="btn" style="background: var(--danger); color: white;"
                               onclick="return confirm('⚠️ ZHVILLUES: Fshini të gjitha pagesat test? Kjo veprim nuk mund të kthehet!')">
                                <i class="fas fa-trash-alt"></i> Pastro Pagesat Test
                            </a>
                            
                            <a href="?action=view_logs" class="btn" style="background: var(--info); color: white;">
                                <i class="fas fa-file-alt"></i> Shiko Log Files
                            </a>
                            
                            <a href="generate_missing_invoices.php" class="btn" style="background: var(--success); color: white;">
                                <i class="fas fa-file-invoice"></i> Gjenero Faturat e Munguara
                            </a>
                            
                            <a href="download_invoices.php" class="btn" style="background: var(--primary); color: white;">
                                <i class="fas fa-file-download"></i> Shkarko të Gjitha Faturat
                            </a>
                            
                            <a href="convert_to_pdf.php" class="btn" style="background: var(--danger); color: white;">
                                <i class="fas fa-file-pdf"></i> Konverto HTML në PDF
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Pagesat e fundit -->
        <div class="card">
            <h2><i class="fas fa-history"></i> Pagesat e Fundit - Filtrat e Avancuara</h2>
            
            <!-- Advanced Filtering System -->
            <form method="GET" style="margin-bottom: 2rem;">
                <div class="form-grid" style="margin-bottom: 1.5rem;">
                    <!-- Search Box -->
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-search"></i> Kërko</label>
                        <input type="text" name="search" class="form-control" placeholder="Emri, email, transaction ID..." 
                               value="<?php echo htmlspecialchars($searchTerm); ?>">
                    </div>

                    <!-- Status Filter -->
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-filter"></i> Statusi</label>
                        <select name="filter_status" class="form-control">
                            <option value="all">Të gjitha</option>
                            <option value="completed" <?php echo $filterStatus === 'completed' ? 'selected' : ''; ?>>Të plotësuara</option>
                            <option value="pending" <?php echo $filterStatus === 'pending' ? 'selected' : ''; ?>>Në pritje</option>
                            <option value="failed" <?php echo $filterStatus === 'failed' ? 'selected' : ''; ?>>Të dështuara</option>
                        </select>
                    </div>

                    <!-- Payment Method Filter -->
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-credit-card"></i> Metoda e pagesës</label>
                        <select name="filter_method" class="form-control">
                            <option value="all">Të gjitha</option>
                            <option value="visa" <?php echo $filterPaymentMethod === 'visa' ? 'selected' : ''; ?>>Visa</option>
                            <option value="mastercard" <?php echo $filterPaymentMethod === 'mastercard' ? 'selected' : ''; ?>>Mastercard</option>
                            <option value="sepa" <?php echo $filterPaymentMethod === 'sepa' ? 'selected' : ''; ?>>SEPA</option>
                            <option value="bank_transfer" <?php echo $filterPaymentMethod === 'bank_transfer' ? 'selected' : ''; ?>>Lëvizje bancaire</option>
                        </select>
                    </div>

                    <!-- Month Filter -->
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-calendar"></i> Muaji</label>
                        <input type="month" name="filter_month" class="form-control" value="<?php echo $filterMonth; ?>">
                    </div>
                </div>

                <!-- Amount Range -->
                <div class="form-grid" style="margin-bottom: 1.5rem;">
                    <div class="form-group">
                        <label class="form-label">Shuma minimale (€)</label>
                        <input type="number" name="filter_min_amount" class="form-control" step="0.01" min="0" 
                               placeholder="0.00" value="<?php echo htmlspecialchars($filterMinAmount); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Shuma maksimale (€)</label>
                        <input type="number" name="filter_max_amount" class="form-control" step="0.01" min="0" 
                               placeholder="9999.99" value="<?php echo htmlspecialchars($filterMaxAmount); ?>">
                    </div>

                    <!-- Sort Options -->
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-sort"></i> Rendit sipas</label>
                        <select name="sort" class="form-control">
                            <option value="payment_date" <?php echo $sortBy === 'payment_date' ? 'selected' : ''; ?>>Data e pagesës</option>
                            <option value="amount" <?php echo $sortBy === 'amount' ? 'selected' : ''; ?>>Shuma</option>
                            <option value="status" <?php echo $sortBy === 'status' ? 'selected' : ''; ?>>Statusi</option>
                            <option value="emri" <?php echo $sortBy === 'emri' ? 'selected' : ''; ?>>Emri i noterit</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Drejtimi</label>
                        <select name="order" class="form-control">
                            <option value="DESC" <?php echo $sortOrder === 'DESC' ? 'selected' : ''; ?>>Zbritëse</option>
                            <option value="ASC" <?php echo $sortOrder === 'ASC' ? 'selected' : ''; ?>>Rritëse</option>
                        </select>
                    </div>
                </div>

                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Zbato Filtrat
                    </button>
                    <a href="?page=1" class="btn" style="background: var(--border); color: var(--text);">
                        <i class="fas fa-redo"></i> Riaktivizo
                    </a>
                </div>
            </form>

            <!-- Rezultatet e filtrimit -->
            <?php if ($filterStatus !== 'all' || !empty($searchTerm) || !empty($filterMonth) || !empty($filterMinAmount) || !empty($filterMaxAmount)): ?>
            <div style="background: #dbeafe; color: #1e40af; padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem; border-left: 4px solid #0ea5e9;">
                <strong>Rezultatet e filtrimit:</strong> 
                <?php echo $filteredStats['count_filtered']; ?> pagesa | Total: <strong>€<?php echo number_format($filteredStats['total_filtered'], 2); ?></strong> | 
                Të plotësuara: <strong>€<?php echo number_format($filteredStats['completed_filtered'], 2); ?></strong>
            </div>
            <?php endif; ?>
            
            <div class="table-responsive">
                <?php if (!empty($recentPayments)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Noter</th>
                                <th>Shuma</th>
                                <th>Statusi</th>
                                <th>Metoda</th>
                                <th>ID Transaksioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentPayments as $payment): ?>
                                <tr>
                                    <td><?php echo date('d.m.Y H:i', strtotime($payment['payment_date'])); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($payment['emri'] . ' ' . $payment['mbiemri']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($payment['email']); ?></small>
                                    </td>
                                    <td>
                                        <strong>€<?php echo number_format($payment['amount'], 2); ?></strong>
                                        <?php if (!empty($payment['invoice_number'])): ?>
                                            <div style="margin-top: 5px;">
                                                <a href="faturat/<?php echo $payment['invoice_number']; ?>.html" 
                                                   target="_blank" 
                                                   title="Shiko faturën elektronike"
                                                   style="display: inline-flex; align-items: center; gap: 3px; font-size: 0.75rem; 
                                                   background: rgba(22, 163, 74, 0.1); color: var(--success); 
                                                   padding: 2px 8px; border-radius: 12px; text-decoration: none;">
                                                    <i class="fas fa-file-invoice"></i> Faturë
                                                </a>
                                                
                                                <?php if (file_exists(__DIR__ . '/faturat/' . $payment['invoice_number'] . '.pdf')): ?>
                                                    <a href="faturat/<?php echo $payment['invoice_number']; ?>.pdf" 
                                                       target="_blank" 
                                                       title="Shkarko PDF"
                                                       style="display: inline-flex; align-items: center; gap: 3px; font-size: 0.75rem; 
                                                       background: rgba(220, 38, 38, 0.1); color: var(--danger); 
                                                       padding: 2px 8px; border-radius: 12px; text-decoration: none;">
                                                        <i class="fas fa-file-pdf"></i> PDF
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $statusClass = [
                                            'completed' => 'success',
                                            'pending' => 'warning',
                                            'failed' => 'danger',
                                            'cancelled' => 'info'
                                        ][$payment['status']] ?? 'info';
                                        ?>
                                        <span class="badge badge-<?php echo $statusClass; ?>">
                                            <?php echo ucfirst($payment['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php $type = $payment['payment_type'] ?? 'manual'; ?>
                                        <span style="color: <?php echo $type === 'automatic' ? 'var(--success)' : 'var(--text)'; ?>">
                                            <?php echo $type === 'automatic' ? '🤖 Automatik' : '👤 Manual'; ?>
                                        </span>
                                    </td>
                                    <td style="font-family: monospace; font-size: 0.8rem;">
                                        <?php 
                                        $txnId = $payment['transaction_id'] ?? 'N/A';
                                        echo $txnId === 'N/A' ? $txnId : htmlspecialchars(substr($txnId, 0, 15)) . '...';
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="text-align: center; padding: 2rem; color: var(--text);">
                        <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                        Nuk ka pagesa të regjistruara akoma.
                    </p>
                <?php endif; ?>
            </div>
            
            <?php if ($totalPages > 1): ?>
                <div style="display: flex; justify-content: center; margin-top: 2rem; gap: 0.5rem;">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page-1; ?>" class="btn" style="background: var(--border); color: var(--text);">
                            <i class="fas fa-chevron-left"></i> Mëparshëm
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page-2); $i <= min($totalPages, $page+2); $i++): ?>
                        <a href="?page=<?php echo $i; ?>" 
                           class="btn <?php echo $i === $page ? 'btn-primary' : ''; ?>" 
                           style="<?php echo $i === $page ? '' : 'background: var(--border); color: var(--text);'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page+1; ?>" class="btn" style="background: var(--border); color: var(--text);">
                            Tjetër <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
                
                <p style="text-align: center; margin-top: 1rem; color: var(--text);">
                    Faqja <?php echo $page; ?> nga <?php echo $totalPages; ?> 
                    (<?php echo $totalPayments; ?> pagesa gjithsej)
                </p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // ====== ADVANCED ANALYTICS & FEATURES ======
        
        // Real-time Payment Monitor
        <?php if (($configs['auto_payment_enabled'] ?? '0') === '1'): ?>
        let refreshInterval = setInterval(function() {
            <?php if ($pendingPayments > 0): ?>
                console.log('🔄 Real-time monitor: Kontrollo për pagesa të reja...');
                if (document.visibilityState === 'visible') {
                    setTimeout(() => {
                        window.location.reload();
                    }, 60000);
                }
            <?php endif; ?>
        }, 60000);
        <?php endif; ?>
        
        // ADVANCED FILTERING - Instant Search
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput) {
                searchInput.addEventListener('keyup', function(e) {
                    if (e.key === 'Enter') {
                        this.closest('form').submit();
                    }
                });
            }
        });
        
        // Export to CSV Function
        function exportTableToCSV(filename = 'pagesat.csv') {
            let csv = [];
            let rows = document.querySelectorAll("table tr");
            
            rows.forEach(function(row) {
                let csvRow = [];
                let cells = row.querySelectorAll("td, th");
                
                cells.forEach(function(cell) {
                    csvRow.push('"' + cell.innerText.replace(/"/g, '""') + '"');
                });
                
                csv.push(csvRow.join(","));
            });
            
            downloadCSV(csv.join("\n"), filename);
        }
        
        function downloadCSV(csv, filename) {
            let csvFile;
            let downloadLink;
            
            csvFile = new Blob([csv], {type: "text/csv"});
            downloadLink = document.createElement("a");
            downloadLink.setAttribute("href", URL.createObjectURL(csvFile));
            downloadLink.setAttribute("download", filename);
            downloadLink.click();
        }
        
        // Real-time Stats Update
        function updateStats() {
            fetch('billing_stats_api.php')
                .then(response => response.json())
                .then(data => {
                    // Update stat cards with new data
                    console.log('📊 Statistikat e përditësuara:', data);
                })
                .catch(error => console.error('Gabim:', error));
        }
        
        // Auto-update every 5 minutes
        setInterval(updateStats, 300000);
        
        // TABLE ROW INTERACTIONS
        document.querySelectorAll('table tbody tr').forEach(row => {
            row.addEventListener('mouseenter', function() {
                this.style.background = 'rgba(26, 86, 219, 0.05)';
            });
            
            row.addEventListener('mouseleave', function() {
                this.style.background = '';
            });
        });
        
        // FORM VALIDATION & ANIMATIONS
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                const inputs = this.querySelectorAll('input[required], select[required]');
                let isValid = true;
                
                inputs.forEach(input => {
                    if (!input.value) {
                        input.style.borderColor = '#ef4444';
                        input.style.background = '#fee2e2';
                        isValid = false;
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    alert('Ju lutem plotësoni të gjitha fushat e detyruara!');
                }
            });
        });
        
        // CHECKBOX ANIMATIONS
        document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const group = this.closest('.checkbox-group');
                if (group) {
                    group.style.transform = this.checked ? 'scale(1.02)' : 'scale(1)';
                    group.style.background = this.checked ? 'rgba(34, 197, 94, 0.1)' : '';
                    setTimeout(() => {
                        group.style.transform = 'scale(1)';
                    }, 150);
                }
            });
        });
        
        // TOOLTIPS
        document.querySelectorAll('[title]').forEach(el => {
            el.addEventListener('mouseenter', function() {
                const tooltip = document.createElement('div');
                tooltip.className = 'custom-tooltip';
                tooltip.textContent = this.getAttribute('title');
                tooltip.style.cssText = `
                    position: absolute;
                    background: #333;
                    color: white;
                    padding: 5px 10px;
                    border-radius: 4px;
                    font-size: 0.8rem;
                    z-index: 1000;
                    pointer-events: none;
                `;
                document.body.appendChild(tooltip);
                
                setTimeout(() => tooltip.remove(), 3000);
            });
        });
        
        // LOADING SPINNER
        function showLoadingSpinner() {
            const spinner = document.createElement('div');
            spinner.id = 'loading-spinner';
            spinner.style.cssText = `
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                z-index: 9999;
                background: rgba(0,0,0,0.7);
                padding: 30px;
                border-radius: 10px;
                color: white;
                text-align: center;
            `;
            spinner.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size: 2rem;"></i><p style="margin-top: 10px;">Mjesha për sekondat...</p>';
            document.body.appendChild(spinner);
        }
        
        // KEYBOARD SHORTCUTS
        document.addEventListener('keydown', function(e) {
            // Ctrl+Enter për të dërguar formin
            if (e.ctrlKey && e.key === 'Enter') {
                const form = document.querySelector('form');
                if (form) form.submit();
            }
        });
        
        // PAGE ANALYTICS TRACKING
        console.log('📈 Billing Dashboard Loaded');
        console.log('Active Features:');
        console.log('  ✓ Advanced Filtering');
        console.log('  ✓ Real-time Monitoring');
        console.log('  ✓ Churn Risk Analysis');
        console.log('  ✓ Revenue Forecasting');
        console.log('  ✓ Payment Method Analytics');
        console.log('  ✓ Export to CSV');
    </script>

    <!-- Footer -->
    <style>
        footer a:hover { color: white !important; }
    </style>
    <footer style="background: linear-gradient(135deg, #1a1f2e 0%, #24292e 100%); color: #d1d5da; padding: 40px 0 20px; margin-top: 40px; text-align: center; position: relative;">
        <div style="position: absolute; top: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, transparent, #cfa856, transparent);"></div>
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 mb-4">
                    <span style="font-weight: 800; font-size: 1.5rem; margin-bottom: 10px; display: block; background: linear-gradient(135deg, #ffffff, #cfa856); background-clip: text; -webkit-background-clip: text; -webkit-text-fill-color: transparent;">e-Noteria</span>
                    <p style="color: #8b949e; margin: 0; font-size: 0.9rem;">Platformë SaaS për zyrat noteriale në Kosovë</p>
                </div>
            </div>
            <div style="border-top: 1px solid #30363d; padding-top: 20px; margin-top: 20px;">
                <div style="margin-bottom: 15px;">
                    <a href="terms.php" style="color: #8b949e; text-decoration: none; margin: 0 15px; display: inline-block; transition: color 0.3s;">Kushtet e Përdorimit</a>
                    <a href="Privatesia.php" style="color: #8b949e; text-decoration: none; margin: 0 15px; display: inline-block; transition: color 0.3s;">Politika e Privatësisë</a>
                    <a href="ndihma.php" style="color: #8b949e; text-decoration: none; margin: 0 15px; display: inline-block; transition: color 0.3s;">Ndihma</a>
                </div>
                <p style="margin: 0; font-size: 0.8em; color: #8b949e;">
                    &copy; <?php echo date('Y'); ?> Platforma e-Noteria | Republika e Kosovës
                </p>
            </div>
        </div>
    </footer>
</body>
</html>
