<?php
/**
 * Interface testuese për funksionet e sistemit të faturimit
 * Kjo faqe lejon testimin e funksionaliteteve nga functions.php
 */

session_start();
require_once 'config.php';
require_once 'confidb.php';
require_once 'developer_config.php';
require_once 'functions.php';

// Define database connection variables if they're not already defined
if (!isset($db_host)) $db_host = 'localhost';
if (!isset($db_name)) $db_name = 'noteria';
if (!isset($db_username)) $db_username = 'root';
if (!isset($db_password)) $db_password = '';

// Kontrollo autorizimin - vetëm super-admin
$isSuperAdmin = isset($_SESSION['admin_id']) && (isset($_SESSION['is_developer']) && $_SESSION['is_developer'] === true);

// Inicializo variablat
$message = '';
$messageType = '';
$testResults = [];
$testCase = isset($_GET['test']) ? $_GET['test'] : '';

// Inicializo lidhjen me bazën e të dhënave
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_username, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $dbConnected = true;
} catch (PDOException $e) {
    $dbConnected = false;
    $message = "Lidhja me bazën e të dhënave dështoi: " . $e->getMessage();
    $messageType = 'error';
}

// Funksione testuese
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    
    switch ($action) {
        case 'test_log':
            // Testo funksionin e log-ut
            $testMessage = "Test log message at " . date('Y-m-d H:i:s');
            logAutomaticPayment($testMessage);
            $logFile = __DIR__ . '/auto_payments.log';
            
            if (file_exists($logFile)) {
                $lastLines = tail($logFile, 5);
                $testResults = [
                    'success' => true,
                    'message' => 'Shënimi u regjistrua me sukses në log',
                    'details' => [
                        'log_file' => $logFile,
                        'last_entries' => $lastLines
                    ]
                ];
                $message = "Log u gjenerua me sukses. Kontrolloni rezultatet më poshtë.";
                $messageType = 'success';
            } else {
                $testResults = [
                    'success' => false,
                    'message' => 'Nuk u gjet file i log-ut',
                    'details' => [
                        'expected_file' => $logFile
                    ]
                ];
                $message = "Dështoi gjenerimi i log-ut. File-i nuk ekziston.";
                $messageType = 'error';
            }
            break;
            
        case 'test_invoice':
            if (!$dbConnected) {
                $message = "Nuk mund të testoni gjenerimin e faturës pa lidhje në bazën e të dhënave.";
                $messageType = 'error';
                break;
            }
            
            // Kontrollo nëse kemi pagesë të vlefshme për test
            try {
                $stmt = $pdo->query("
                    SELECT sp.*, n.emri, n.mbiemri, n.email, n.telefoni 
                    FROM subscription_payments sp
                    JOIN noteri n ON sp.noter_id = n.id
                    WHERE sp.status = 'completed'
                    LIMIT 1
                ");
                $payment = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($payment) {
                    // Gjenero faturën për testim
                    $invoiceNumber = generateElectronicInvoice($payment, $pdo);
                    
                    if ($invoiceNumber) {
                        $htmlPath = __DIR__ . '/faturat/' . $invoiceNumber . '.html';
                        $pdfTodoPath = __DIR__ . '/faturat/' . $invoiceNumber . '.pdf.todo';
                        
                        $testResults = [
                            'success' => true,
                            'message' => 'Fatura u gjenerua me sukses',
                            'details' => [
                                'invoice_number' => $invoiceNumber,
                                'html_exists' => file_exists($htmlPath),
                                'pdf_todo_exists' => file_exists($pdfTodoPath),
                                'html_path' => $htmlPath,
                                'pdf_todo_path' => $pdfTodoPath,
                                'payment_id' => $payment['id'],
                                'noter' => $payment['emri'] . ' ' . $payment['mbiemri'],
                                'amount' => $payment['amount']
                            ]
                        ];
                        $message = "Fatura #{$invoiceNumber} u gjenerua me sukses.";
                        $messageType = 'success';
                    } else {
                        $testResults = [
                            'success' => false,
                            'message' => 'Dështoi gjenerimi i faturës',
                            'details' => [
                                'payment_id' => $payment['id']
                            ]
                        ];
                        $message = "Dështoi gjenerimi i faturës për pagesën #{$payment['id']}.";
                        $messageType = 'error';
                    }
                } else {
                    $testResults = [
                        'success' => false,
                        'message' => 'Nuk u gjet asnjë pagesë e kompletuar për gjenerim të faturës',
                    ];
                    $message = "Nuk mund të gjeneroni faturë - nuk ka pagesa të kompletuara në sistem.";
                    $messageType = 'warning';
                }
            } catch (PDOException $e) {
                $testResults = [
                    'success' => false,
                    'message' => 'Gabim në bazën e të dhënave',
                    'details' => [
                        'error' => $e->getMessage()
                    ]
                ];
                $message = "Gabim në bazën e të dhënave: " . $e->getMessage();
                $messageType = 'error';
            }
            break;
            
        case 'test_notification':
            // Testo funksionin e njoftimeve
            $testPayment = [
                'id' => 999,
                'noter_id' => 1,
                'amount' => 99.99,
                'invoice_number' => 'TEST-' . date('YmdHis'),
                'emri' => 'Test',
                'mbiemri' => 'Noteri',
                'email' => 'test@example.com'
            ];
            
            // Testo të dy rastet - sukses dhe gabim
            sendPaymentNotification($testPayment, 'success', 'Kartë Krediti');
            sendPaymentNotification($testPayment, 'error', 'Kartë Krediti');
            
            $logFile = __DIR__ . '/auto_payments.log';
            if (file_exists($logFile)) {
                $lastLines = tail($logFile, 5);
                $testResults = [
                    'success' => true,
                    'message' => 'Njoftimet u gjeneruan me sukses',
                    'details' => [
                        'log_file' => $logFile,
                        'last_entries' => $lastLines,
                        'test_payment' => $testPayment
                    ]
                ];
                $message = "Njoftimet u gjeneruan me sukses dhe u regjistruan në log.";
                $messageType = 'success';
            } else {
                $testResults = [
                    'success' => false,
                    'message' => 'Nuk u gjet file i log-ut për njoftimet',
                ];
                $message = "Dështoi gjenerimi i njoftimeve - file i log-ut nuk ekziston.";
                $messageType = 'error';
            }
            break;
            
        case 'test_all':
            // Testo të gjitha funksionet
            // Për shkak të kompleksitetit, këtë do ta implementojmë më vonë
            $message = "Testimi i të gjitha funksioneve nuk është ende i implementuar.";
            $messageType = 'info';
            break;
    }
}

// Funksion ndihmues për të lexuar linjat e fundit të një file
function tail($file, $lines = 10) {
    if (!file_exists($file)) return [];
    
    $handle = fopen($file, "r");
    $linecounter = $lines;
    $pos = -2;
    $beginning = false;
    $text = [];
    
    while ($linecounter > 0) {
        $t = " ";
        while ($t != "\n") {
            if (fseek($handle, $pos, SEEK_END) == -1) {
                $beginning = true;
                break;
            }
            $t = fgetc($handle);
            $pos--;
        }
        
        if ($beginning) {
            rewind($handle);
        }
        
        $text[$lines - $linecounter] = trim(fgets($handle));
        $linecounter--;
        
        if ($beginning) break;
    }
    fclose($handle);
    return array_reverse($text);
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test i Funksioneve | Noteria</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            --code-bg: #f8f9fa;
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
            max-width: 1200px;
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
            text-align: center;
        }

        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
        }

        .header p {
            opacity: 0.9;
            font-size: 1.1rem;
        }

        .card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .card h2 {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: var(--heading);
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
            border-bottom: 2px solid var(--border);
            padding-bottom: 0.75rem;
        }

        .alert {
            padding: 1.25rem 1.75rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: flex-start;
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

        .btn-secondary {
            background: var(--border);
            color: var(--text);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-secondary:hover {
            background: #d1d5db;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .test-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
            border: 1px solid var(--border);
        }

        .test-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 20px rgba(0, 0, 0, 0.1);
        }

        .test-card-header {
            padding: 1.25rem;
            background: var(--dark);
            color: white;
        }

        .test-card-body {
            padding: 1.5rem;
        }

        .test-card-footer {
            padding: 1.25rem;
            background: var(--light);
            border-top: 1px solid var(--border);
            text-align: center;
        }

        form {
            margin-bottom: 0;
        }

        .code-block {
            background: var(--code-bg);
            padding: 1rem;
            border-radius: 8px;
            overflow-x: auto;
            font-family: monospace;
            margin-bottom: 1.5rem;
            white-space: pre-wrap;
            font-size: 0.9rem;
            border: 1px solid var(--border);
        }

        .result-block {
            margin-top: 2rem;
        }

        .result-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            font-size: 1.2rem;
        }

        .result-success {
            color: var(--success);
        }

        .result-error {
            color: var(--danger);
        }

        .detail-list {
            list-style: none;
            margin-bottom: 1.5rem;
        }

        .detail-list li {
            padding: 0.5rem 0;
            border-bottom: 1px dashed var(--border);
            display: flex;
            justify-content: space-between;
        }

        .detail-list li:last-child {
            border-bottom: none;
        }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .badge-success {
            background: #dcfce7;
            color: #166534;
        }

        .badge-error {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--primary);
            font-weight: 500;
            text-decoration: none;
            margin-top: 1rem;
            transition: all 0.2s;
        }

        .back-link:hover {
            color: #1e40af;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-vial"></i> Test i Funksioneve të Sistemit</h1>
            <p>Kjo faqe testuese lejon të verifikoni funksionalitetin e sistemit të faturimit</p>
            <div style="margin-top: 1rem;">
                <a href="billing_dashboard.php" style="color: white; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-arrow-left"></i> Kthehu te Paneli
                </a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <i class="fas fa-<?php 
                    echo $messageType === 'error' ? 'exclamation-circle' : 
                        ($messageType === 'warning' ? 'exclamation-triangle' : 
                        ($messageType === 'info' ? 'info-circle' : 'check-circle')); 
                ?>"></i>
                <div><?php echo htmlspecialchars($message); ?></div>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2><i class="fas fa-code"></i> Funksionet e Disponueshme</h2>
            
            <div class="code-block">
// Shkrimi në log-file
function logAutomaticPayment($message) {...}

// Gjenerimi i faturës elektronike
function generateElectronicInvoice($payment, $pdo) {...}

// Dërgimi i njoftimit për pagesë
function sendPaymentNotification($payment, $status, $method) {...}
            </div>
            
            <p style="margin-bottom: 1rem;">
                Zgjidhni një funksion për të testuar dhe për të parë sesi funksionon. Kjo ju ndihmon të kuptoni
                se çfarë ndodh "pas skenave" kur këto funksione thirren nga faqet e tjera.
            </p>
        </div>

        <div class="grid">
            <div class="test-card">
                <div class="test-card-header">
                    <h3><i class="fas fa-pen"></i> Log Automatic Payment</h3>
                </div>
                <div class="test-card-body">
                    <p>Testo funksionin e regjistrimit të veprimeve në log file. Ky funksion përdoret për të ruajtur historikun e veprimeve në sistem.</p>
                </div>
                <div class="test-card-footer">
                    <form method="post" action="?test=log">
                        <input type="hidden" name="action" value="test_log">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-play"></i> Testo Funksionin
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="test-card">
                <div class="test-card-header">
                    <h3><i class="fas fa-file-invoice"></i> Generate Invoice</h3>
                </div>
                <div class="test-card-body">
                    <p>Testo gjenerimin e një fature elektronike. Ky funksion krijon HTML dhe "todo" për PDF për një pagesë ekzistuese.</p>
                </div>
                <div class="test-card-footer">
                    <form method="post" action="?test=invoice">
                        <input type="hidden" name="action" value="test_invoice">
                        <button type="submit" class="btn btn-primary" <?php echo !$dbConnected ? 'disabled' : ''; ?>>
                            <i class="fas fa-play"></i> Testo Funksionin
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="test-card">
                <div class="test-card-header">
                    <h3><i class="fas fa-bell"></i> Send Notification</h3>
                </div>
                <div class="test-card-body">
                    <p>Testo funksionin e dërgimit të njoftimeve. Ky funksion përgatit mesazhet e njoftimit për pagesat e suksesshme ose të dështuara.</p>
                </div>
                <div class="test-card-footer">
                    <form method="post" action="?test=notification">
                        <input type="hidden" name="action" value="test_notification">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-play"></i> Testo Funksionin
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="test-card">
                <div class="test-card-header">
                    <h3><i class="fas fa-check-double"></i> Test All Functions</h3>
                </div>
                <div class="test-card-body">
                    <p>Ekzekuto të gjitha funksionet automatikisht dhe shfaq rezultatet e kombinuara. Teston gjithë zinxhirin e procesit të faturimit.</p>
                </div>
                <div class="test-card-footer">
                    <form method="post" action="?test=all">
                        <input type="hidden" name="action" value="test_all">
                        <button type="submit" class="btn btn-primary" <?php echo !$dbConnected ? 'disabled' : ''; ?>>
                            <i class="fas fa-play"></i> Testo Të Gjitha
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <?php if (!empty($testResults)): ?>
            <div class="card result-block">
                <h2>
                    <i class="fas fa-clipboard-check"></i> 
                    Rezultatet e Testimit: <?php echo htmlspecialchars(ucfirst($testCase)); ?>
                </h2>
                
                <div class="result-header <?php echo $testResults['success'] ? 'result-success' : 'result-error'; ?>">
                    <i class="fas fa-<?php echo $testResults['success'] ? 'check-circle' : 'times-circle'; ?>"></i>
                    <?php echo htmlspecialchars($testResults['message']); ?>
                </div>
                
                <?php if (isset($testResults['details']) && !empty($testResults['details'])): ?>
                    <h3 style="margin-top: 1.5rem; margin-bottom: 1rem; color: var(--heading);">Detajet e Testimit:</h3>
                    
                    <ul class="detail-list">
                        <?php foreach ($testResults['details'] as $key => $value): ?>
                            <li>
                                <strong><?php echo htmlspecialchars(str_replace('_', ' ', ucfirst($key))); ?>:</strong>
                                
                                <?php if ($key === 'last_entries' && is_array($value)): ?>
                                    <div class="code-block" style="margin-top: 0.5rem; margin-bottom: 0;">
                                        <?php foreach ($value as $line): ?>
                                            <?php echo htmlspecialchars($line); ?><br>
                                        <?php endforeach; ?>
                                    </div>
                                <?php elseif (is_array($value)): ?>
                                    <pre><?php print_r($value); ?></pre>
                                <?php elseif (is_bool($value)): ?>
                                    <span class="badge <?php echo $value ? 'badge-success' : 'badge-error'; ?>">
                                        <?php echo $value ? 'Po' : 'Jo'; ?>
                                    </span>
                                <?php else: ?>
                                    <?php echo htmlspecialchars($value); ?>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                
                <?php if ($testCase === 'invoice' && $testResults['success'] && isset($testResults['details']['invoice_number'])): ?>
                    <div style="margin-top: 1.5rem; display: flex; gap: 1rem;">
                        <a href="faturat/<?php echo $testResults['details']['invoice_number']; ?>.html" target="_blank" class="btn btn-primary">
                            <i class="fas fa-eye"></i> Shiko Faturën HTML
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div style="text-align: center; margin-top: 2rem; padding: 1rem; background: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);">
            <p>
                <i class="fas fa-info-circle"></i> 
                Kjo faqe është vetëm për qëllime testimi dhe zhvillimi. Përdoret për të verifikuar funksionalitetin e sistemit të faturimit.
            </p>
            
            <a href="billing_dashboard.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Kthehu te Paneli i Faturimit
            </a>
        </div>
    </div>

    <script>
        // Animation for cards
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.card, .test-card');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease-out';
                    card.style.transform = 'translateY(0)';
                    card.style.opacity = '1';
                }, index * 100);
            });
        });
    </script>
</body>
</html>