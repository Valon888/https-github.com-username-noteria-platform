<?php
// subscription_processor.php - Skript për procesimin automatik të pagesave të abonimit mujor për noterët
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

require_once 'config.php';
// We no longer need to include db.php as config.php already initializes $pdo

if (!isset($pdo) || !$pdo instanceof PDO) {
    error_log('Database connection is not established.');
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed.']);
    exit();
}
require_once __DIR__ . '/vendor/autoload.php'; // Use Composer autoload for PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Kontrollo për token nëse skripti thirret nga një CRON job ose një kërkesë e jashtme
$validToken = "YXV0b21hdGljX3N1YnNjcmlwdGlvbl90b2tlbg=="; // base64_encode("automatic_subscription_token")
$providedToken = $_GET['token'] ?? '';

$isAdmin = false;
$isAutomated = false;

// Kontrollo nëse është një kërkesë administrative ose një thirrje e automatizuar
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kontrollo nëse përdoruesi është admin ose tokeni është i vlefshëm
if (isset($_SESSION['admin_id'])) {
    $isAdmin = true;
} elseif ($providedToken === $validToken) {
    $isAutomated = true;
} else {
    // Kërkon autentifikim
    if (!isset($_GET['token'])) {
        header("Location: login.php");
        exit();
    } else {
        header('HTTP/1.1 401 Unauthorized');
        echo json_encode(['error' => 'Token i pavlefshëm']);
        exit();
    }
}

// Funksioni për të regjistruar çdo veprim
function logAction($message, $status = 'info', $noterId = null) {
    global $pdo;

    if (!isset($pdo) || !$pdo instanceof PDO) {
        error_log('Database connection is not established in logAction.');
        return;
    }

    $logType = 'subscription';
    $sql = "INSERT INTO activity_logs (log_type, user_id, status, message, created_at) 
            VALUES (:log_type, :user_id, :status, :message, NOW())";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'log_type' => $logType,
            'user_id' => $noterId,
            'status' => $status,
            'message' => $message
        ]);
    } catch (PDOException $e) {
        error_log('Failed to log action: ' . $e->getMessage());
    }
}
function processSubscriptions($testMode = false) {
    global $pdo;
    $results = [
        'success' => [],
        'failed' => [],
        'skipped' => [],
        'total_processed' => 0,
        'total_amount' => 0
    ];

    // Kontrollo që $pdo është i inicializuar dhe është objekt PDO
    if (!isset($pdo) || !$pdo instanceof PDO) {
        return [
            'status' => 'error',
            'message' => 'Database connection is not established in processSubscriptions.',
            'details' => $results
        ];
    }

    // Merr të dhënat e konfigurimit të abonimeve
    try {
        $configStmt = $pdo->query("SELECT subscription_price, payment_day FROM system_settings LIMIT 1");
        $config = $configStmt->fetch(PDO::FETCH_ASSOC);

        if (!$config) {
            throw new Exception("Nuk u gjetën konfigurimet e sistemit për abonimet");
        }

        $subscriptionPrice = $config['subscription_price'];
        $paymentDay = $config['payment_day'];

        // Kontrollo nëse është dita e duhur për pagesë (përveç nëse jemi në test mode)
        $today = date('j'); // Dita e muajit pa zero në fillim
        if ($today != $paymentDay && !$testMode) {
            return [
                'status' => 'skipped',
                'message' => "Nuk është dita e pagesës. Pagesat procesohen në ditën $paymentDay të çdo muaji. Sot është dita $today.",
                'details' => $results
            ];
        }

        // Merr të gjithë noterët aktivë me detajet e pagesës
        $noterStmt = $pdo->query("
            SELECT 
                n.id, n.emri, n.mbiemri, n.email, n.statusi,
                n.account_number, n.bank_name, n.subscription_status,
                COALESCE(n.custom_price, $subscriptionPrice) as price
            FROM 
                noteri n
            WHERE 
                n.statusi = 'active' 
                AND n.subscription_status = 'active'
                AND (n.account_number IS NOT NULL AND n.account_number != '')
        ");
        $noters = $noterStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($noters)) {
            return [
                'status' => 'skipped',
                'message' => "Nuk u gjetën noterë aktivë me informacion pagese të vlefshëm",
                'details' => $results
            ];
        }

        // Për secilin noter, proceso pagesën
        foreach ($noters as $noter) {
            try {
                // Kontrollo nëse pagesa për muajin aktual ekziston tashmë
                $currentMonth = date('m');
                $currentYear = date('Y');
                $checkStmt = $pdo->prepare("
                    SELECT id FROM subscription_payments 
                    WHERE noter_id = ? 
                    AND MONTH(payment_date) = ? 
                    AND YEAR(payment_date) = ?
                    AND status = 'completed'
                ");
                $checkStmt->execute([$noter['id'], $currentMonth, $currentYear]);

                if ($checkStmt->rowCount() > 0) {
                    $results['skipped'][] = [
                        'noter_id' => $noter['id'],
                        'name' => $noter['emri'] . ' ' . $noter['mbiemri'],
                        'reason' => "Pagesa për muajin aktual është kryer tashmë"
                    ];
                    continue;
                }

                // Krijo pagesën në tabelën e pagesave
                $paymentRef = generatePaymentReference($noter['id']);
                $paymentStmt = $pdo->prepare("
                    INSERT INTO subscription_payments 
                    (noter_id, amount, payment_date, status, reference, payment_method, description)
                    VALUES (?, ?, NOW(), ?, ?, ?, ?)
                ");

                // Nëse jemi në test mode, shëno pagesën si 'test', përndryshe 'pending'
                $paymentStatus = $testMode ? 'test' : 'pending';
                $paymentDescription = "Abonim mujor për " . date('F Y');

                $paymentStmt->execute([
                    $noter['id'], 
                    $noter['price'], 
                    $paymentStatus,
                    $paymentRef,
                    'automatic', 
                    $paymentDescription
                ]);

                $paymentId = $pdo->lastInsertId();

                if (!$testMode) {
                    // Këtu do të shtojmë logjikën për të procesuar pagesën reale
                    // Mund të përdorësh Paysera, PayPal ose një sistem tjetër pagese
                    $paymentProcessed = processActualPayment($noter, $noter['price'], $paymentRef);

                    if ($paymentProcessed['success']) {
                        // Përditëso statusin e pagesës në 'completed'
                        $updateStmt = $pdo->prepare("
                            UPDATE subscription_payments 
                            SET status = 'completed', transaction_id = ?
                            WHERE id = ?
                        ");
                        $updateStmt->execute([$paymentProcessed['transaction_id'], $paymentId]);

                        $results['success'][] = [
                            'noter_id' => $noter['id'],
                            'name' => $noter['emri'] . ' ' . $noter['mbiemri'],
                            'amount' => $noter['price'],
                            'payment_id' => $paymentId,
                            'transaction_id' => $paymentProcessed['transaction_id']
                        ];

                        $results['total_amount'] += $noter['price'];

                        // Dërgo email konfirmimi
                        sendPaymentConfirmationEmail($noter, $noter['price'], $paymentRef, $paymentId);

                        // Regjistro veprimin
                        logAction(
                            "Pagesa e abonimit u procesua me sukses për noterin {$noter['emri']} {$noter['mbiemri']}. Shuma: {$noter['price']} EUR. Ref: $paymentRef",
                            'success',
                            $noter['id']
                        );
                    } else {
                        // Përditëso statusin e pagesës në 'failed'
                        $updateStmt = $pdo->prepare("
                            UPDATE subscription_payments 
                            SET status = 'failed', notes = ?
                            WHERE id = ?
                        ");
                        $updateStmt->execute([$paymentProcessed['error'], $paymentId]);

                        $results['failed'][] = [
                            'noter_id' => $noter['id'],
                            'name' => $noter['emri'] . ' ' . $noter['mbiemri'],
                            'amount' => $noter['price'],
                            'error' => $paymentProcessed['error']
                        ];

                        // Dërgo njoftim për dështimin
                        sendPaymentFailureNotification($noter, $noter['price'], $paymentProcessed['error']);

                        // Regjistro veprimin
                        logAction(
                            "Pagesa e abonimit dështoi për noterin {$noter['emri']} {$noter['mbiemri']}. Arsyeja: {$paymentProcessed['error']}",
                            'error',
                            $noter['id']
                        );
                    }
                } else {
                    // Në test mode, shëno pagesën si të suksesshme
                    $results['success'][] = [
                        'noter_id' => $noter['id'],
                        'name' => $noter['emri'] . ' ' . $noter['mbiemri'],
                        'amount' => $noter['price'],
                        'payment_id' => $paymentId,
                        'transaction_id' => 'TEST-' . $paymentRef,
                        'test_mode' => true
                    ];

                    $results['total_amount'] += $noter['price'];

                    // Regjistro veprimin
                    logAction(
                        "[TEST] Pagesa e abonimit u simulua për noterin {$noter['emri']} {$noter['mbiemri']}. Shuma: {$noter['price']} EUR. Ref: $paymentRef",
                        'info',
                        $noter['id']
                    );
                }

                $results['total_processed']++;

            } catch (Exception $e) {
                $results['failed'][] = [
                    'noter_id' => $noter['id'],
                    'name' => $noter['emri'] . ' ' . $noter['mbiemri'],
                    'error' => $e->getMessage()
                ];

                logAction(
                    "Gabim në procesimin e pagesës për noterin {$noter['emri']} {$noter['mbiemri']}: " . $e->getMessage(),
                    'error',
                    $noter['id']
                );
            }
        }

        return [
            'status' => 'completed',
            'message' => "Procesi i abonimeve përfundoi me " . count($results['success']) . " pagesa të suksesshme dhe " . count($results['failed']) . " të dështuara.",
            'details' => $results
        ];

    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => "Gabim në procesimin e abonimeve: " . $e->getMessage(),
            'details' => $results
        ];
    }
}

// Funksion për të gjeneruar një referencë unike pagese
function generatePaymentReference($noterId) {
    $prefix = "SUB";
    $date = date('Ymd');
    $random = substr(str_shuffle("0123456789"), 0, 4);
    return $prefix . $date . $noterId . $random;
}

// Funksion për të procesuar pagesën aktuale (këtu duhet të integrohet me një sistem real pagese)
function processActualPayment($noter, $amount, $reference) {
    // Kjo është thjesht një simulim. Në një implementim real, do të komunikoje me një API pagese
    // si Paysera, PayPal, Stripe, etj.
    
    // Simulim i suksesit me probabilitet 90%
    $success = (rand(1, 100) <= 90);
    
    if ($success) {
        return [
            'success' => true,
            'transaction_id' => 'TRX-' . time() . '-' . $noter['id']
        ];
    } else {
        return [
            'success' => false,
            'error' => 'Simulim i dështimit të pagesës për testim'
        ];
    }
    
    // Shembull implementimi me Paysera (pseudokod):
    /*
    try {
        // Konfiguro klientin Paysera
        $paysera = new PayseraClient($apiKey, $projectId);
        
        // Krijo kërkesën e pagesës
        $payment = $paysera->createPayment([
            'amount' => $amount,
            'currency' => 'EUR',
            'description' => 'Abonimi mujor - ' . date('F Y'),
            'reference' => $reference,
            'account' => $noter['account_number']
        ]);
        
        // Proceso pagesën direkte
        $result = $paysera->processDirectPayment($payment);
        
        if ($result->isSuccess()) {
            return [
                'success' => true,
                'transaction_id' => $result->getTransactionId()
            ];
        } else {
            return [
                'success' => false,
                'error' => $result->getErrorMessage()
            ];
        }
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
    */
}

// Funksion për të dërguar email konfirmimi
function sendPaymentConfirmationEmail($noter, $amount, $reference, $paymentId) {
    global $config; // Access configuration settings
    
    $to = $noter['email'];
    $subject = "Konfirmim i pagesës së abonimit - " . date('F Y');
    
    $message = "
    <html>
    <head>
        <title>Konfirmim i pagesës së abonimit</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; }
            .container { width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #1a56db; color: white; padding: 15px; text-align: center; }
            .content { padding: 20px; border: 1px solid #ddd; }
            .footer { font-size: 12px; text-align: center; margin-top: 30px; color: #666; }
            .details { margin: 20px 0; padding: 15px; background-color: #f9f9f9; border-left: 4px solid #1a56db; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Konfirmim i pagesës së abonimit</h2>
            </div>
            <div class='content'>
                <p>I/E nderuar " . htmlspecialchars($noter['emri'] . ' ' . $noter['mbiemri']) . ",</p>
                <p>Ju konfirmojmë se pagesa e abonimit tuaj mujor është procesuar me sukses.</p>
                
                <div class='details'>
                    <p><strong>Shuma:</strong> " . htmlspecialchars(number_format($amount, 2)) . " EUR</p>
                    <p><strong>Data:</strong> " . date('d-m-Y H:i') . "</p>
                    <p><strong>Referenca:</strong> " . htmlspecialchars($reference) . "</p>
                    <p><strong>Përshkrimi:</strong> Abonim mujor për " . date('F Y') . "</p>
                </div>
                
                <p>Faleminderit për përdorimin e shërbimit tonë.</p>
                <p>Për çdo pyetje ose nevojë për asistencë, ju lutem na kontaktoni.</p>
                <p>Me respekt,<br>Ekipi i Noteria</p>
            </div>
            <div class='footer'>
                <p>Ky është një email automatik. Ju lutemi mos i përgjigjeni këtij emaili.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    try {
        // Properly instantiate and configure PHPMailer
        $mail = new PHPMailer(true); // true enables exceptions
        
        // Server settings
        $mail->isSMTP();
        $mail->Host       = $config['mail']['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['mail']['username'];
        $mail->Password   = $config['mail']['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Or PHPMailer::ENCRYPTION_SMTPS
        $mail->Port       = $config['mail']['port'];
        
        // Recipients
        $mail->setFrom($config['mail']['from_email'], $config['mail']['from_name']);
        $mail->addAddress($to);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $message;
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Error sending confirmation email: " . $e->getMessage());
        return false;
    }
}

// Funksion për të dërguar njoftim për dështim pagese
function sendPaymentFailureNotification($noter, $amount, $error) {
    global $config; // Access configuration settings
    
    $to = $noter['email'];
    $subject = "Njoftim për dështim të pagesës së abonimit - " . date('F Y');
    
    $message = "
    <html>
    <head>
        <title>Njoftim për dështim të pagesës së abonimit</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; }
            .container { width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #dc2626; color: white; padding: 15px; text-align: center; }
            .content { padding: 20px; border: 1px solid #ddd; }
            .footer { font-size: 12px; text-align: center; margin-top: 30px; color: #666; }
            .details { margin: 20px 0; padding: 15px; background-color: #fee2e2; border-left: 4px solid #dc2626; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Njoftim për dështim të pagesës së abonimit</h2>
            </div>
            <div class='content'>
                <p>I/E nderuar " . htmlspecialchars($noter['emri'] . ' ' . $noter['mbiemri']) . ",</p>
                <p>Ju njoftojmë se ka pasur një problem me procesimin e pagesës së abonimit tuaj mujor.</p>
                
                <div class='details'>
                    <p><strong>Shuma:</strong> " . htmlspecialchars(number_format($amount, 2)) . " EUR</p>
                    <p><strong>Data e tentimit:</strong> " . date('d-m-Y H:i') . "</p>
                    <p><strong>Arsyeja e dështimit:</strong> " . htmlspecialchars($error) . "</p>
                </div>
                
                <p>Ju lutemi të kontrolloni informacionin e pagesës në llogarinë tuaj dhe të kontaktoni me ne për asistencë të mëtejshme.</p>
                <p>Do të tentojmë të procesojmë pagesën përsëri në ditët në vijim.</p>
                <p>Me respekt,<br>Ekipi i Noteria</p>
            </div>
            <div class='footer'>
                <p>Ky është një email automatik. Ju lutemi mos i përgjigjeni këtij emaili.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    try {
        // Properly instantiate and configure PHPMailer for customer notification
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $config['mail']['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['mail']['username'];
        $mail->Password   = $config['mail']['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $config['mail']['port'];
        
        $mail->setFrom($config['mail']['from_email'], $config['mail']['from_name']);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $message;
        
        $mail->send();
        
        // Send admin notification with a new PHPMailer instance
        $adminMail = new PHPMailer(true);
        $adminMail->isSMTP();
        $adminMail->Host       = $config['mail']['host'];
        $adminMail->SMTPAuth   = true;
        $adminMail->Username   = $config['mail']['username'];
        $adminMail->Password   = $config['mail']['password'];
        $adminMail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $adminMail->Port       = $config['mail']['port'];
        
        $adminMail->setFrom($config['mail']['from_email'], $config['mail']['from_name']);
        $adminMail->addAddress($config['mail']['admin_email']);
        $adminMail->isHTML(true);
        $adminMail->Subject = "ALARM: Dështim pagese abonimit - " . $noter['emri'] . ' ' . $noter['mbiemri'];
        $adminMail->Body    = "Ka pasur një dështim në procesimin e pagesës së abonimit për noterin " . 
                             $noter['emri'] . ' ' . $noter['mbiemri'] . 
                             " (ID: " . $noter['id'] . "). Arsyeja: " . $error . 
                             ". Ju lutem kontrolloni sistemin.";
        
        $adminMail->send();
        return true;
    } catch (Exception $e) {
        error_log("Error sending failure notification: " . $e->getMessage());
        return false;
    }
}

// Determino nëse po ekzekutohet në test mode
$testMode = isset($_GET['test']) && $_GET['test'] === 'true';

// Proceso abonimet
$result = processSubscriptions($testMode);

// Përgatit përgjigjen bazuar në formatin e kërkesës
$isJsonRequest = isset($_GET['format']) && $_GET['format'] === 'json';

if ($isJsonRequest || $isAutomated) {
    // Ktheje përgjigjen si JSON për kërkesat automatike ose formati JSON
    header('Content-Type: application/json');
    echo json_encode($result);
    exit();
} else {
    // Shfaq rezultatet në një format të lexueshëm për administratorët
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Procesimi i Abonimeve | Noteria</title>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #1a56db;
            --primary-hover: #1e40af;
            --secondary-color: #6b7280;
            --light-bg: #f8fafc;
            --border-color: #e2e8f0;
            --text-color: #374151;
            --heading-color: #1e293b;
            --success-color: #16a34a;
            --warning-color: #f59e0b;
            --danger-color: #dc2626;
        }
        
        body {
            font-family: 'Montserrat', Arial, sans-serif;
            line-height: 1.6;
            color: var(--text-color);
            background-color: var(--light-bg);
            margin: 0;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .panel {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 25px;
            margin-bottom: 30px;
        }
        
        h1 {
            color: var(--primary-color);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
        }
        
        h1 i {
            margin-right: 12px;
        }
        
        h2 {
            color: #2563eb;
            font-size: 1.4rem;
            margin-top: 30px;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .message {
            padding: 15px;
            margin: 20px 0;
            border-radius: 6px;
            font-weight: 500;
        }
        
        .success {
            background-color: #dcfce7;
            color: #166534;
            border-left: 5px solid #16a34a;
        }
        
        .error {
            background-color: #fee2e2;
            color: #991b1b;
            border-left: 5px solid #dc2626;
        }
        
        .warning {
            background-color: #fef3c7;
            color: #92400e;
            border-left: 5px solid #f59e0b;
        }
        
        .info {
            background-color: #dbeafe;
            color: #1e40af;
            border-left: 5px solid #2563eb;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0 30px;
        }
        
        th, td {
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            text-align: left;
        }
        
        th {
            background-color: #f1f5f9;
            font-weight: 600;
            color: var(--text-color);
        }
        
        tr:nth-child(even) {
            background-color: #f8fafc;
        }
        
        tr:hover {
            background-color: #f1f5f9;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
            color: white;
        }
        
        .badge-success { background-color: var(--success-color); }
        .badge-warning { background-color: var(--warning-color); }
        .badge-danger { background-color: var(--danger-color); }
        .badge-info { background-color: var(--primary-color); }
        
        .button {
            display: inline-block;
            background-color: var(--primary-color);
            color: white;
            padding: 10px 15px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            margin-right: 10px;
            transition: background-color 0.2s;
        }
        
        .button:hover {
            background-color: var(--primary-hover);
        }
        
        .button i {
            margin-right: 6px;
        }
        
        .button-success {
            background-color: var(--success-color);
        }
        
        .button-success:hover {
            background-color: #15803d;
        }
        
        .button-warning {
            background-color: var(--warning-color);
        }
        
        .button-warning:hover {
            background-color: #d97706;
        }
        
        .summary-box {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            padding: 20px;
            flex: 1;
            min-width: 200px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .stat-label {
            color: var(--secondary-color);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .stat-success .stat-value { color: var(--success-color); }
        .stat-warning .stat-value { color: var(--warning-color); }
        .stat-danger .stat-value { color: var(--danger-color); }
        .stat-info .stat-value { color: var(--primary-color); }
        
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .test-mode {
            background-color: #fef3c7;
            border: 2px dashed #f59e0b;
            color: #92400e;
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="toolbar">
            <h1><i class="fas fa-sync-alt"></i> Procesimi i Abonimeve</h1>
            
            <div>
                <?php if ($testMode): ?>
                    <a href="subscription_processor.php" class="button">
                        <i class="fas fa-play"></i> Ekzekuto Real
                    </a>
                <?php else: ?>
                    <a href="subscription_processor.php?test=true" class="button button-warning">
                        <i class="fas fa-flask"></i> Ekzekuto Test
                    </a>
                <?php endif; ?>
                
                <a href="subscription_payments.php" class="button">
                    <i class="fas fa-arrow-left"></i> Kthehu
                </a>
            </div>
        </div>
        
        <?php if ($testMode): ?>
            <div class="test-mode">
                <i class="fas fa-exclamation-triangle"></i> MËNYRA TEST - Asnjë pagesë reale nuk do të procesohet
            </div>
        <?php endif; ?>
        
        <div class="panel">
            <div class="message <?php 
                if ($result['status'] === 'completed') echo 'success';
                elseif ($result['status'] === 'error') echo 'error';
                elseif ($result['status'] === 'skipped') echo 'warning';
                else echo 'info';
            ?>">
                <?php echo $result['message']; ?>
            </div>
            
            <?php if ($result['status'] === 'completed' || ($result['status'] === 'skipped' && !empty($result['details']['success']))): ?>
                <div class="summary-box">
                    <div class="stat-card stat-info">
                        <div class="stat-label">Totali i procesuar</div>
                        <div class="stat-value"><?php echo $result['details']['total_processed']; ?></div>
                    </div>
                    
                    <div class="stat-card stat-success">
                        <div class="stat-label">Pagesat e suksesshme</div>
                        <div class="stat-value"><?php echo count($result['details']['success']); ?></div>
                    </div>
                    
                    <div class="stat-card stat-danger">
                        <div class="stat-label">Pagesat e dështuara</div>
                        <div class="stat-value"><?php echo count($result['details']['failed']); ?></div>
                    </div>
                    
                    <div class="stat-card stat-warning">
                        <div class="stat-label">Pagesat e anashkaluara</div>
                        <div class="stat-value"><?php echo count($result['details']['skipped']); ?></div>
                    </div>
                    
                    <div class="stat-card stat-info">
                        <div class="stat-label">Shuma totale</div>
                        <div class="stat-value"><?php echo number_format($result['details']['total_amount'], 2); ?> €</div>
                    </div>
                </div>
                
                <?php if (!empty($result['details']['success'])): ?>
                    <h2>Pagesat e suksesshme</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>ID Noterit</th>
                                <th>Emri</th>
                                <th>Shuma</th>
                                <th>ID e pagesës</th>
                                <th>ID e transaksionit</th>
                                <?php if ($testMode): ?>
                                    <th>Statusi</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($result['details']['success'] as $payment): ?>
                                <tr>
                                    <td><?php echo $payment['noter_id']; ?></td>
                                    <td><?php echo htmlspecialchars($payment['name']); ?></td>
                                    <td><?php echo number_format($payment['amount'], 2); ?> €</td>
                                    <td><?php echo $payment['payment_id']; ?></td>
                                    <td><?php echo $payment['transaction_id']; ?></td>
                                    <?php if ($testMode): ?>
                                        <td>
                                            <span class="badge badge-warning">TEST</span>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                
                <?php if (!empty($result['details']['failed'])): ?>
                    <h2>Pagesat e dështuara</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>ID Noterit</th>
                                <th>Emri</th>
                                <th>Shuma</th>
                                <th>Arsyeja e dështimit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($result['details']['failed'] as $payment): ?>
                                <tr>
                                    <td><?php echo $payment['noter_id']; ?></td>
                                    <td><?php echo htmlspecialchars($payment['name']); ?></td>
                                    <td><?php echo isset($payment['amount']) ? number_format($payment['amount'], 2) . ' €' : 'N/A'; ?></td>
                                    <td><?php echo htmlspecialchars($payment['error']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                
                <?php if (!empty($result['details']['skipped'])): ?>
                    <h2>Pagesat e anashkaluara</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>ID Noterit</th>
                                <th>Emri</th>
                                <th>Arsyeja</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($result['details']['skipped'] as $payment): ?>
                                <tr>
                                    <td><?php echo $payment['noter_id']; ?></td>
                                    <td><?php echo htmlspecialchars($payment['name']); ?></td>
                                    <td><?php echo htmlspecialchars($payment['reason']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                
            <?php endif; ?>
            
            <div style="margin-top: 30px;">
                <a href="subscription_settings.php" class="button">
                    <i class="fas fa-cog"></i> Konfigurimet e abonimeve
                </a>
                
                <a href="subscription_reports.php" class="button">
                    <i class="fas fa-chart-bar"></i> Raportet e abonimeve
                </a>
                
                <?php if (!empty($result['details']['success']) || !empty($result['details']['failed'])): ?>
                    <a href="subscription_payments.php" class="button">
                        <i class="fas fa-list"></i> Të gjitha pagesat
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
<?php
}
?>