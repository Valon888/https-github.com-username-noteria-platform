<?php
/**
 * Script për gjenerimin e faturave elektronike për pagesat ekzistuese
 * Pa fatura elektronike të gjeneruara
 */

session_start();
require_once 'config.php';
require_once 'confidb.php';
require_once 'developer_config.php';
require_once 'functions.php'; // Include common functions

// Define database connection variables if they're not already defined
// These should normally come from config.php or confidb.php, but we're adding them here as a fallback
if (!isset($db_host)) $db_host = 'localhost';
if (!isset($db_name)) $db_name = 'noteria';
if (!isset($db_username)) $db_username = 'root';
if (!isset($db_password)) $db_password = '';

// Create database connection
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_username, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("ERROR: Could not connect to database. " . $e->getMessage());
}

// Kontrollo autorizimin - vetëm super-admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php?error=auth_required");
    exit();
}

// Kontrollo nëse është super-admin (zhvillues)
$isSuperAdmin = isDeveloper($_SESSION['admin_id'] ?? 0);

if (!$isSuperAdmin) {
    header("Location: billing_dashboard.php?error=developer_required");
    exit();
}

$message = '';
$messageType = '';
$generatedInvoices = [];

// Procesi fillon kur merret konfirmimi
if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    try {
        // Gjej pagesat e kompletuara pa fatura
        $stmt = $pdo->prepare("
            SELECT 
                sp.*,
                n.emri,
                n.mbiemri,
                n.email,
                n.telefoni
            FROM subscription_payments sp
            JOIN noteri n ON sp.noter_id = n.id
            WHERE sp.status = 'completed' 
            AND (sp.invoice_number IS NULL OR sp.invoice_number = '')
            ORDER BY sp.payment_date DESC
        ");
        $stmt->execute();
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($payments)) {
            $message = "Nuk u gjetën pagesa pa fatura elektronike. Të gjitha pagesat tashmë kanë fatura.";
            $messageType = 'info';
        } else {
            // Shto referencat në log
            logAutomaticPayment("=== GJENERIM I FATURAVE TË MUNGUARA - Fillim ===");
            logAutomaticPayment("U gjetën " . count($payments) . " pagesa që kërkojnë fatura elektronike");
            
            // Proceso çdo pagesë
            foreach ($payments as $payment) {
                try {
                    $invoiceNumber = generateElectronicInvoice($payment, $pdo);
                    
                    if ($invoiceNumber) {
                        $generatedInvoices[] = [
                            'id' => $payment['id'],
                            'noter' => $payment['emri'] . ' ' . $payment['mbiemri'],
                            'amount' => $payment['amount'],
                            'date' => $payment['payment_date'],
                            'invoice_number' => $invoiceNumber
                        ];
                        
                        logAutomaticPayment("GJENERUAR: Fatura #{$invoiceNumber} për Noter ID {$payment['noter_id']}, €{$payment['amount']}");
                    }
                } catch (Exception $e) {
                    logAutomaticPayment("ERROR: Dështoi gjenerimi për Pagesat ID {$payment['id']}: " . $e->getMessage());
                }
            }
            
            logAutomaticPayment("=== GJENERIM I FATURAVE TË MUNGUARA - Përfundim ===");
            
            $message = "U gjeneruan " . count($generatedInvoices) . " fatura elektronike nga " . count($payments) . " pagesat e përzgjedhura!";
            $messageType = 'success';
        }
    } catch (PDOException $e) {
        $message = "Gabim në bazën e të dhënave: " . $e->getMessage();
        $messageType = 'error';
    } catch (Exception $e) {
        $message = "Gabim gjatë procesimit: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Numëro pagesat pa fatura për statistika
try {
    $countStmt = $pdo->query("
        SELECT COUNT(*) FROM subscription_payments
        WHERE status = 'completed' AND (invoice_number IS NULL OR invoice_number = '')
    ");
    $paymentCount = $countStmt->fetchColumn();
} catch (PDOException $e) {
    $paymentCount = 0;
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gjenero Faturat e Munguara | Noteria</title>
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

        .card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
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

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-warning {
            background: var(--warning);
            color: white;
        }

        .btn-secondary {
            background: var(--border);
            color: var(--text);
        }

        .btn-secondary:hover {
            background: #d1d5db;
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
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }

        .stat-card {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.5rem;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .stat-content h3 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .result-card {
            padding: 1.5rem;
            border-radius: 12px;
            background: #f8fafc;
            margin-bottom: 1rem;
            border-left: 4px solid var(--success);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-file-invoice"></i> Gjenerim i Faturave të Munguara</h1>
            <p>Ky mjet gjeneron fatura elektronike për pagesat e kompletuara që nuk kanë fatura.</p>
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

        <?php if (!isset($_GET['confirm'])): ?>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-file-invoice"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $paymentCount; ?></h3>
                    <p>Pagesa të kompletuara pa fatura elektronike</p>
                </div>
            </div>

            <div class="card">
                <h2><i class="fas fa-exclamation-triangle"></i> Konfirmim i Gjenerimit</h2>
                
                <?php if ($paymentCount > 0): ?>
                    <p style="margin-bottom: 1.5rem;">
                        Ky proces do të gjenerojë fatura elektronike për të gjitha pagesat e kompletuara që nuk kanë fatura.
                        U gjetën <strong><?php echo $paymentCount; ?> pagesa</strong> pa fatura elektronike.
                    </p>
                    
                    <p style="margin-bottom: 1.5rem;">
                        <strong>Shënim:</strong> Ky proces do të:
                    </p>
                    
                    <ul style="margin-bottom: 1.5rem; margin-left: 1.5rem;">
                        <li>Gjenerojë faturë HTML për çdo pagesë</li>
                        <li>Krijojë një file .todo për gjenerimin e PDF-ve në të ardhmen</li>
                        <li>Përditësojë bazën e të dhënave me referencat e faturave</li>
                    </ul>
                    
                    <div class="action-buttons">
                        <a href="?confirm=yes" class="btn btn-primary">
                            <i class="fas fa-check"></i> Po, Gjenero Faturat
                        </a>
                        <a href="billing_dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Anulo
                        </a>
                    </div>
                <?php else: ?>
                    <p style="margin-bottom: 1.5rem;">
                        <strong>Nuk u gjetën pagesa pa fatura elektronike.</strong> 
                        Të gjitha pagesat e kompletuara tashmë kanë fatura elektronike të gjeneruara.
                    </p>
                    
                    <div class="action-buttons">
                        <a href="billing_dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Kthehu te Paneli
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php elseif (!empty($generatedInvoices)): ?>
            <div class="card">
                <h2><i class="fas fa-check-circle"></i> Faturat u Gjeneruan me Sukses</h2>
                
                <p style="margin-bottom: 1.5rem;">
                    U gjeneruan <strong><?php echo count($generatedInvoices); ?></strong> fatura elektronike nga 
                    <strong><?php echo $paymentCount; ?></strong> pagesa.
                </p>
                
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ID Pagesës</th>
                                <th>Noter</th>
                                <th>Shuma</th>
                                <th>Data e Pagesës</th>
                                <th>Numri i Faturës</th>
                                <th>Veprime</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($generatedInvoices as $invoice): ?>
                                <tr>
                                    <td><?php echo $invoice['id']; ?></td>
                                    <td><?php echo htmlspecialchars($invoice['noter']); ?></td>
                                    <td>€<?php echo number_format($invoice['amount'], 2); ?></td>
                                    <td><?php echo date('d.m.Y H:i', strtotime($invoice['date'])); ?></td>
                                    <td><?php echo $invoice['invoice_number']; ?></td>
                                    <td>
                                        <a href="faturat/<?php echo $invoice['invoice_number']; ?>.html" 
                                           target="_blank"
                                           style="color: var(--primary); margin-right: 10px;">
                                            <i class="fas fa-eye"></i> Shiko
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="action-buttons">
                    <a href="billing_dashboard.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Kthehu te Paneli
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <h2><i class="fas fa-info-circle"></i> Nuk u gjeneruan fatura të reja</h2>
                
                <p style="margin-bottom: 1.5rem;">
                    Asnjë faturë nuk u gjenerua. Mundësitë janë:
                </p>
                
                <ul style="margin-bottom: 1.5rem; margin-left: 1.5rem;">
                    <li>Nuk ka pagesa të kompletuara pa fatura</li>
                    <li>Ndodhi një problem gjatë gjenerimit të faturave</li>
                </ul>
                
                <div class="action-buttons">
                    <a href="billing_dashboard.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Kthehu te Paneli
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Simple animation for cards
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.card, .result-card, .stat-card');
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