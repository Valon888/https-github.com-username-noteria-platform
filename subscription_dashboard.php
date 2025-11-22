<?php
// Redirect to the new abonimet.php page
session_start();
header("Location: abonimet.php");
exit();

/*
// Old code below for reference
// subscription_dashboard.php - Paneli i monitorimit të sistemit të abonimeve
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

require_once 'config.php';

// Kontrollo autorizimin
session_start();
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
*/

// Procesi për skriptet e automatizuara
$message = '';
$messageType = '';

if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'test_process':
            // Ekzekuto skriptin e testimit të procesimit
            ob_start();
            $_GET['test'] = 'true';
            $_GET['token'] = 'YXV0b21hdGljX3N1YnNjcmlwdGlvbl90b2tlbg==';
            include 'subscription_processor.php';
            $result = ob_get_clean();
            
            $message = 'Simulimi i procesimit të abonimeve u ekzekutua me sukses.';
            $messageType = 'success';
            break;
            
        case 'real_process':
            // Ekzekuto skriptin real të procesimit (vetëm për admin)
            if (isset($_SESSION['admin_id'])) {
                ob_start();
                $_GET['token'] = 'YXV0b21hdGljX3N1YnNjcmlwdGlvbl90b2tlbg==';
                include 'subscription_processor.php';
                $result = ob_get_clean();
                
                $message = 'Procesimi real i abonimeve u ekzekutua me sukses.';
                $messageType = 'success';
            } else {
                $message = 'Nuk keni të drejta të mjaftueshme për këtë veprim.';
                $messageType = 'error';
            }
            break;
            
        case 'check_tables':
            // Kontrollo tabelat e sistemit
            ob_start();
            include 'check_subscription_tables.php';
            $result = ob_get_clean();
            
            $message = 'Kontrolli i tabelave u ekzekutua me sukses.';
            $messageType = 'info';
            break;
    }
}

// Merr të dhëna për tabelën e abonimeve
function getSubscriptionSummary($pdo) {
    $summary = [
        'total_payments' => 0,
        'completed_payments' => 0,
        'pending_payments' => 0,
        'failed_payments' => 0,
        'total_amount' => 0,
        'completed_amount' => 0,
        'current_month_payments' => 0,
        'current_month_amount' => 0
    ];
    
    try {
        // Numri total i pagesave
        $stmt = $pdo->query("SELECT COUNT(*) FROM subscription_payments");
        $summary['total_payments'] = $stmt->fetchColumn();
        
        // Pagesat e kompletuara
        $stmt = $pdo->query("SELECT COUNT(*) FROM subscription_payments WHERE status = 'completed'");
        $summary['completed_payments'] = $stmt->fetchColumn();
        
        // Pagesat në pritje
        $stmt = $pdo->query("SELECT COUNT(*) FROM subscription_payments WHERE status = 'pending'");
        $summary['pending_payments'] = $stmt->fetchColumn();
        
        // Pagesat e dështuara
        $stmt = $pdo->query("SELECT COUNT(*) FROM subscription_payments WHERE status = 'failed'");
        $summary['failed_payments'] = $stmt->fetchColumn();
        
        // Shuma totale e pagesave
        $stmt = $pdo->query("SELECT SUM(amount) FROM subscription_payments WHERE status = 'completed'");
        $summary['completed_amount'] = $stmt->fetchColumn() ?: 0;
        
        // Shuma totale e pagesave të planifikuara
        $stmt = $pdo->query("SELECT SUM(amount) FROM subscription_payments");
        $summary['total_amount'] = $stmt->fetchColumn() ?: 0;
        
        // Pagesat e muajit aktual
        $stmt = $pdo->query("SELECT COUNT(*) FROM subscription_payments WHERE MONTH(payment_date) = MONTH(CURRENT_DATE()) AND YEAR(payment_date) = YEAR(CURRENT_DATE())");
        $summary['current_month_payments'] = $stmt->fetchColumn();
        
        // Shuma e muajit aktual
        $stmt = $pdo->query("SELECT SUM(amount) FROM subscription_payments WHERE MONTH(payment_date) = MONTH(CURRENT_DATE()) AND YEAR(payment_date) = YEAR(CURRENT_DATE()) AND status = 'completed'");
        $summary['current_month_amount'] = $stmt->fetchColumn() ?: 0;
        
        return $summary;
    } catch (PDOException $e) {
        error_log("Gabim në marrjen e statistikave të abonimeve: " . $e->getMessage());
        return $summary;
    }
}

// Merr pagesat e fundit
function getRecentPayments($pdo, $limit = 10) {
    $payments = [];
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                sp.id, sp.noter_id, sp.amount, sp.payment_date, sp.status, sp.reference,
                n.emri, n.mbiemri, n.email
            FROM 
                subscription_payments sp
            JOIN 
                noteri n ON sp.noter_id = n.id
            ORDER BY 
                sp.payment_date DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $payments;
    } catch (PDOException $e) {
        error_log("Gabim në marrjen e pagesave të fundit: " . $e->getMessage());
        return $payments;
    }
}

// Merr të dhënat
$summary = getSubscriptionSummary($pdo);
$recentPayments = getRecentPayments($pdo, 5);

?><!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paneli i Abonimeve | Noteria</title>
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
            padding: 0;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        header {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 15px 0;
            margin-bottom: 30px;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .logo {
            color: var(--primary-color);
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
        }
        
        .logo i {
            margin-right: 10px;
        }
        
        .user-menu {
            display: flex;
            align-items: center;
        }
        
        .user-info {
            margin-right: 20px;
            font-size: 0.9rem;
            color: var(--secondary-color);
        }
        
        .panel {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 25px;
            margin-bottom: 30px;
        }
        
        h1, h2, h3 {
            color: var(--heading-color);
            margin-top: 0;
        }
        
        h1 {
            font-size: 1.8rem;
            margin-bottom: 20px;
            color: var(--primary-color);
        }
        
        h2 {
            font-size: 1.4rem;
            margin-bottom: 15px;
        }
        
        h3 {
            font-size: 1.2rem;
            margin-bottom: 10px;
        }
        
        .alert {
            padding: 15px;
            margin: 20px 0;
            border-radius: 6px;
            font-weight: 500;
        }
        
        .alert-success {
            background-color: #dcfce7;
            color: #166534;
            border-left: 5px solid #16a34a;
        }
        
        .alert-error {
            background-color: #fee2e2;
            color: #991b1b;
            border-left: 5px solid #dc2626;
        }
        
        .alert-info {
            background-color: #dbeafe;
            color: #1e40af;
            border-left: 5px solid #2563eb;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.07);
            padding: 20px;
            text-align: center;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin: 10px 0;
        }
        
        .stat-label {
            color: var(--secondary-color);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0;
        }
        
        .stat-success .stat-value { color: var(--success-color); }
        .stat-warning .stat-value { color: var(--warning-color); }
        .stat-danger .stat-value { color: var(--danger-color); }
        .stat-info .stat-value { color: var(--primary-color); }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 30px;
        }
        
        .main-content {
            grid-column: 1;
        }
        
        .sidebar {
            grid-column: 2;
        }
        
        .button {
            display: inline-block;
            background-color: var(--primary-color);
            color: white;
            padding: 10px 15px;
            border-radius: 6px;
            text-decoration: none;
            font-family: inherit;
            font-weight: 500;
            font-size: 0.9rem;
            cursor: pointer;
            margin-right: 10px;
            transition: background-color 0.2s;
            border: none;
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
        
        .button-danger {
            background-color: var(--danger-color);
        }
        
        .button-danger:hover {
            background-color: #b91c1c;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        th {
            font-weight: 600;
            color: var(--heading-color);
            background-color: #f9fafb;
        }
        
        tr:hover {
            background-color: #f9fafb;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-success {
            background-color: #dcfce7;
            color: #166534;
        }
        
        .badge-warning {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .badge-danger {
            background-color: #fee2e2;
            color: #991b1b;
        }
        
        .badge-info {
            background-color: #dbeafe;
            color: #1e40af;
        }
        
        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 20px;
        }
        
        .card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.07);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--heading-color);
        }
        
        .quick-links ul {
            list-style-type: none;
            padding: 0;
            margin: 0;
        }
        
        .quick-links li {
            margin-bottom: 10px;
        }
        
        .quick-links a {
            display: block;
            padding: 10px;
            border-radius: 6px;
            text-decoration: none;
            color: var(--text-color);
            transition: background-color 0.2s;
        }
        
        .quick-links a:hover {
            background-color: #f9fafb;
        }
        
        .quick-links a i {
            margin-right: 10px;
            color: var(--primary-color);
        }
        
        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .main-content, .sidebar {
                grid-column: 1;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }
            
            .header-content {
                flex-direction: column;
                text-align: center;
            }
            
            .user-menu {
                margin-top: 15px;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="header-content">
            <div class="logo">
                <i class="fas fa-file-invoice"></i>
                Paneli i Abonimeve
            </div>
            <div class="user-menu">
                <div class="user-info">
                    <strong>Admin</strong> | <?php echo date('d.m.Y H:i'); ?>
                </div>
                <a href="dashboard.php" class="button">
                    <i class="fas fa-arrow-left"></i> Kthehu te paneli kryesor
                </a>
            </div>
        </div>
    </header>
    
    <div class="container">
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <div class="dashboard-grid">
            <div class="main-content">
                <h1><i class="fas fa-chart-pie"></i> Përmbledhje e abonimeve</h1>
                <div class="alert alert-info" style="margin-bottom: 25px;">
                    <strong>Çmimi i abonimit mujor:</strong> <span style="color: #1a56db; font-weight: bold;">150 €</span> për çdo noter aktiv. Të gjitha pagesat mujore të abonimit janë të barabarta me këtë shumë.
                </div>
                
                <div class="stats-grid">
                    <div class="stat-card stat-info">
                        <div class="stat-label">Totali i pagesave</div>
                        <div class="stat-value"><?php echo $summary['total_payments']; ?></div>
                    </div>
                    
                    <div class="stat-card stat-success">
                        <div class="stat-label">Pagesat e kompletuara</div>
                        <div class="stat-value"><?php echo $summary['completed_payments']; ?></div>
                    </div>
                    
                    <div class="stat-card stat-warning">
                        <div class="stat-label">Pagesat në pritje</div>
                        <div class="stat-value"><?php echo $summary['pending_payments']; ?></div>
                    </div>
                    
                    <div class="stat-card stat-danger">
                        <div class="stat-label">Pagesat e dështuara</div>
                        <div class="stat-value"><?php echo $summary['failed_payments']; ?></div>
                    </div>
                    
                    <div class="stat-card stat-info">
                        <div class="stat-label">Shuma totale e pagesave</div>
                        <div class="stat-value"><?php echo number_format($summary['total_amount'], 2); ?> €</div>
                    </div>
                    
                    <div class="stat-card stat-success">
                        <div class="stat-label">Shuma e pagesave të kompletuara</div>
                        <div class="stat-value"><?php echo number_format($summary['completed_amount'], 2); ?> €</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-label">Pagesat e muajit aktual</div>
                        <div class="stat-value"><?php echo $summary['current_month_payments']; ?></div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-label">Shuma e muajit aktual</div>
                        <div class="stat-value"><?php echo number_format($summary['current_month_amount'], 2); ?> €</div>
                    </div>
                </div>
                
                <div class="panel">
                    <h2>Pagesat e fundit</h2>
                    
                    <?php if (!empty($recentPayments)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Noter</th>
                                    <th>Shuma</th>
                                    <th>Data</th>
                                    <th>Statusi</th>
                                    <th>Referenca</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentPayments as $payment): ?>
                                    <tr>
                                        <td><?php echo $payment['id']; ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($payment['emri'] . ' ' . $payment['mbiemri']); ?>
                                            <div style="font-size: 0.8rem; color: #6b7280;">
                                                <?php echo htmlspecialchars($payment['email']); ?>
                                            </div>
                                        </td>
                                        <td>150.00 €</td>
                                        <td><?php echo date('d.m.Y H:i', strtotime($payment['payment_date'])); ?></td>
                                        <td>
                                            <?php 
                                                $statusClass = '';
                                                switch ($payment['status']) {
                                                    case 'completed':
                                                        $statusClass = 'badge-success';
                                                        break;
                                                    case 'pending':
                                                        $statusClass = 'badge-warning';
                                                        break;
                                                    case 'failed':
                                                        $statusClass = 'badge-danger';
                                                        break;
                                                    case 'test':
                                                        $statusClass = 'badge-info';
                                                        break;
                                                }
                                            ?>
                                            <span class="badge <?php echo $statusClass; ?>">
                                                <?php echo htmlspecialchars($payment['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($payment['reference']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <a href="subscription_payments.php" class="button">
                            <i class="fas fa-list"></i> Shiko të gjitha pagesat
                        </a>
                    <?php else: ?>
                        <p>Nuk ka pagesa të regjistruara ende.</p>
                    <?php endif; ?>
                </div>
                
                <div class="panel">
                    <h2>Veprime të shpejta</h2>
                    <p>Këtu mund të ekzekutoni veprime të ndryshme për sistemin e abonimeve.</p>
                    
                    <div class="action-buttons">
                        <a href="?action=test_process" class="button button-warning">
                            <i class="fas fa-flask"></i> Simulim i procesimit të abonimeve
                        </a>
                        
                        <?php if (isset($_SESSION['admin_id'])): ?>
                            <a href="?action=real_process" class="button button-success">
                                <i class="fas fa-play"></i> Procesim real i abonimeve
                            </a>
                        <?php endif; ?>
                        
                        <a href="?action=check_tables" class="button">
                            <i class="fas fa-table"></i> Kontrollo tabelat e sistemit
                        </a>
                        
                        <a href="subscription_settings.php" class="button">
                            <i class="fas fa-cog"></i> Konfigurimet e abonimeve
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="sidebar">
                <div class="card">
                    <div class="card-title">
                        <i class="fas fa-info-circle"></i> Informacion
                    </div>
                    <p>Sistemi i abonimeve proceson pagesat automatike mujore për noterët. Pagesat procesohen automatikisht në ditën e konfiguruar të çdo muaji.</p>
                    <p>Statusi aktual:</p>
                    <ul>
                        <li>Dita e pagesës: <strong><?php echo $paymentDay ?? '1'; ?> e çdo muaji</strong></li>
                        <li>Abonimet aktive: <strong><?php echo $activeSubscriptions ?? 0; ?></strong></li>
                        <li>Ekzekutimi i fundit: <strong><?php echo $lastExecution ?? 'N/A'; ?></strong></li>
                    </ul>
                </div>
                
                <div class="card">
                    <div class="card-title">
                        <i class="fas fa-link"></i> Lidhje të shpejta
                    </div>
                    <div class="quick-links">
                        <ul>
                            <li>
                                <a href="subscription_settings.php">
                                    <i class="fas fa-cog"></i> Konfigurime abonimesh
                                </a>
                            </li>
                            <li>
                                <a href="subscription_payments.php">
                                    <i class="fas fa-list"></i> Lista e pagesave
                                </a>
                            </li>
                            <li>
                                <a href="subscription_reports.php">
                                    <i class="fas fa-chart-bar"></i> Raporte abonimesh
                                </a>
                            </li>
                            <li>
                                <a href="subscription_custom_prices.php">
                                    <i class="fas fa-tags"></i> Çmime të personalizuara
                                </a>
                            </li>
                            <li>
                                <a href="dashboard.php">
                                    <i class="fas fa-tachometer-alt"></i> Paneli kryesor
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-title">
                        <i class="fas fa-question-circle"></i> Ndihmë
                    </div>
                    <p>Për ndihmë me sistemin e abonimeve, ju lutemi kontaktoni:</p>
                    <ul>
                        <li>Email: <a href="mailto:support@noteria.com">support@noteria.com</a></li>
                        <li>Tel: +3834 567 890</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Kod për ndërveprim nëse nevojitet
            console.log('Paneli i abonimeve u ngarkua');
        });
    </script>
</body>
</html>