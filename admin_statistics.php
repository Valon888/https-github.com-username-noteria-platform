<?php
// Redirect to the new statistikat.php page
session_start();
header("Location: statistikat.php");
exit();

/*
 * Admin Statistics Dashboard
 * 
 * A comprehensive dashboard for viewing statistics and analytics
 * related to the notary system including user activities, payments,
 * and document management statistics.
 * 
 * @version 1.0
 * @date September 2025
 */

require_once 'config.php';

// Kontrollo autorizimin
session_start();
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['user_id'])) {
    header("Location: login.php?error=auth_required");
    exit();
}

// Kontrollo nëse është user normal ose admin
$isAdmin = isset($_SESSION['admin_id']);
$userId = $isAdmin ? $_SESSION['admin_id'] : $_SESSION['user_id'];

// Periudha e statistikave (default: muaji aktual)
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Kontrollo formatin e datave
if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $startDate)) $startDate = date('Y-m-01');
if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $endDate)) $endDate = date('Y-m-t');

// Merr statistikat e përgjithshme
try {
    // Numri total i noterëve
    $stmt = $pdo->query("SELECT 
        COUNT(*) as total_noters,
        SUM(CASE WHEN status = 'active' OR status IS NULL THEN 1 ELSE 0 END) as active_noters,
        SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_noters
        FROM noteri");
    $noteryStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Statistikat e pagesave
    $stmt = $pdo->prepare("SELECT 
        COUNT(*) as total_payments, 
        SUM(amount) as total_amount,
        AVG(amount) as avg_amount,
        MAX(amount) as max_amount
        FROM subscription_payments 
        WHERE payment_date BETWEEN ? AND ?");
    $stmt->execute([$startDate, $endDate]);
    $paymentStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Pagesat sipas muajve (për 6 muajt e fundit)
    $stmt = $pdo->query("SELECT 
        DATE_FORMAT(payment_date, '%Y-%m') as month,
        COUNT(*) as payment_count,
        SUM(amount) as monthly_amount
        FROM subscription_payments
        WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
        ORDER BY month");
    $monthlyPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top 5 noterët me më shumë pagesa
    $stmt = $pdo->prepare("SELECT 
        n.id, n.emri, n.mbiemri, n.email,
        COUNT(sp.id) as payment_count,
        SUM(sp.amount) as total_paid
        FROM noteri n
        LEFT JOIN subscription_payments sp ON n.id = sp.noter_id
        WHERE sp.payment_date BETWEEN ? AND ?
        GROUP BY n.id
        ORDER BY total_paid DESC
        LIMIT 5");
    $stmt->execute([$startDate, $endDate]);
    $topNoters = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Statistikat e sistemit - me kontrolle për ekzistencën e tabelave
    $systemStats = [
        'login_attempts' => 0,
        'payment_logs' => 0,
        'session_logs' => 0,
        'uploaded_files' => 0
    ];
    
    // Funksion për të kontrolluar nëse tabela ekziston
    function tableExists($pdo, $table) {
        try {
            $result = $pdo->query("SELECT 1 FROM $table LIMIT 1");
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    // Kontrollo dhe merr të dhënat për secilën tabelë nëse ekziston
    if (tableExists($pdo, 'login_attempts')) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM login_attempts");
        $systemStats['login_attempts'] = $stmt->fetchColumn();
    }
    
    if (tableExists($pdo, 'payment_logs')) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM payment_logs");
        $systemStats['payment_logs'] = $stmt->fetchColumn();
    }
    
    if (tableExists($pdo, 'session_logs')) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM session_logs");
        $systemStats['session_logs'] = $stmt->fetchColumn();
    }
    
    if (tableExists($pdo, 'uploaded_files')) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM uploaded_files");
        $systemStats['uploaded_files'] = $stmt->fetchColumn();
    }
    
    // Numërimi i regjistrimeve sipas muajve - me kontrolle për ekzistencën e kolonës
    $monthlySignups = [];
    
    // Kontrollo nëse kolona data_regjistrimit ekziston në tabelën noteri
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM noteri LIKE 'data_regjistrimit'");
        $columnExists = ($stmt->rowCount() > 0);
        
        if ($columnExists) {
            $stmt = $pdo->query("SELECT 
                DATE_FORMAT(data_regjistrimit, '%Y-%m') as month,
                COUNT(*) as signup_count
                FROM noteri
                WHERE data_regjistrimit IS NOT NULL
                GROUP BY DATE_FORMAT(data_regjistrimit, '%Y-%m')
                ORDER BY month");
            $monthlySignups = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Nëse kolona nuk ekziston, shto disa të dhëna demo
            $monthlySignups = [
                ['month' => date('Y-m', strtotime('-5 months')), 'signup_count' => 0],
                ['month' => date('Y-m', strtotime('-4 months')), 'signup_count' => 0],
                ['month' => date('Y-m', strtotime('-3 months')), 'signup_count' => 0],
                ['month' => date('Y-m', strtotime('-2 months')), 'signup_count' => 0],
                ['month' => date('Y-m', strtotime('-1 month')), 'signup_count' => 0],
                ['month' => date('Y-m'), 'signup_count' => 0]
            ];
        }
    } catch (PDOException $e) {
        // Nëse ndodh gabim, shto disa të dhëna demo
        $monthlySignups = [
            ['month' => date('Y-m', strtotime('-5 months')), 'signup_count' => 0],
            ['month' => date('Y-m', strtotime('-4 months')), 'signup_count' => 0],
            ['month' => date('Y-m', strtotime('-3 months')), 'signup_count' => 0],
            ['month' => date('Y-m', strtotime('-2 months')), 'signup_count' => 0],
            ['month' => date('Y-m', strtotime('-1 month')), 'signup_count' => 0],
            ['month' => date('Y-m'), 'signup_count' => 0]
        ];
        error_log("Error getting monthly signups: " . $e->getMessage());
    }
    
    // Statistikat e dokumentave - me kontrolle për ekzistencën e tabelës
    $documentStats = [
        'total_documents' => 0,
        'completed_documents' => 0,
        'pending_documents' => 0
    ];
    
    // Kontrollo nëse tabela uploaded_files ekziston
    if (tableExists($pdo, 'uploaded_files')) {
        try {
            $stmt = $pdo->prepare("SELECT 
                COUNT(*) as total_documents,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_documents,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_documents
                FROM uploaded_files
                WHERE upload_date BETWEEN ? AND ?");
            $stmt->execute([$startDate, $endDate]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $documentStats = $result;
            }
        } catch (PDOException $e) {
            // Handle errors with columns that might not exist
            error_log("Error getting document stats: " . $e->getMessage());
        }
    }
    
    // Kontrollo për gabime në databazë
    $dbErrors = [];
    
    // Kontrollo nëse kolonat e nevojshme ekzistojnë
    $requiredColumns = [
        'noteri' => ['status', 'subscription_type', 'data_regjistrimit'],
        'subscription_payments' => ['noter_id', 'amount', 'payment_date']
    ];
    
    foreach ($requiredColumns as $table => $columns) {
        foreach ($columns as $column) {
            try {
                $stmt = $pdo->query("SHOW COLUMNS FROM {$table} LIKE '{$column}'");
                if ($stmt->rowCount() == 0) {
                    $dbErrors[] = "Kolona '{$column}' mungon në tabelën '{$table}'";
                }
            } catch (PDOException $e) {
                $dbErrors[] = "Gabim gjatë kontrollimit të kolonës '{$column}' në tabelën '{$table}'";
            }
        }
    }
    
} catch (PDOException $e) {
    $error = "Gabim gjatë marrjes së statistikave: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistikat e Sistemit - Noteria Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #2563eb;
            --primary-light: #3b82f6;
            --primary-dark: #1d4ed8;
            --secondary: #64748b;
            --success: #10b981;
            --info: #06b6d4;
            --warning: #f59e0b;
            --danger: #ef4444;
            --light: #f3f4f6;
            --dark: #1f2937;
            --body-bg: #f9fafb;
            --card-bg: #ffffff;
            --text: #4b5563;
            --text-light: #6b7280;
            --text-dark: #374151;
            --border: #e5e7eb;
            --heading: #111827;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --transition: all 0.3s ease;
            --radius: 0.5rem;
            --radius-sm: 0.25rem;
            --radius-lg: 0.75rem;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Montserrat', sans-serif;
            font-size: 0.95rem;
            line-height: 1.6;
            color: var(--text);
            background-color: var(--body-bg);
        }
        
        .container {
            width: 100%;
            max-width: 1320px;
            margin: 0 auto;
            padding: 0 1rem;
        }
        
        .admin-header {
            background-color: var(--card-bg);
            box-shadow: var(--shadow-sm);
            padding: 1rem 0;
            margin-bottom: 2rem;
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .admin-header .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .admin-logo {
            display: flex;
            align-items: center;
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary);
            text-decoration: none;
        }
        
        .admin-logo i {
            margin-right: 0.5rem;
            font-size: 1.5rem;
        }
        
        .admin-nav {
            display: flex;
            align-items: center;
        }
        
        .admin-nav-item {
            margin-left: 1.5rem;
            color: var(--text);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            display: flex;
            align-items: center;
        }
        
        .admin-nav-item i {
            margin-right: 0.4rem;
        }
        
        .admin-nav-item:hover {
            color: var(--primary);
        }
        
        .admin-nav-item.active {
            color: var(--primary);
            position: relative;
        }
        
        .admin-nav-item.active::after {
            content: '';
            position: absolute;
            bottom: -1.5rem;
            left: 0;
            width: 100%;
            height: 3px;
            background-color: var(--primary);
        }
        
        .page-header {
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .page-title {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--heading);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .page-title i {
            color: var(--primary);
        }
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background-color: var(--card-bg);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            padding: 1.5rem;
            transition: var(--transition);
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            color: white;
            font-size: 1.5rem;
        }
        
        .stat-blue { background-color: var(--primary); }
        .stat-green { background-color: var(--success); }
        .stat-orange { background-color: var(--warning); }
        .stat-red { background-color: var(--danger); }
        .stat-teal { background-color: var(--info); }
        .stat-purple { background-color: #8b5cf6; }
        
        .stat-content {
            flex: 1;
        }
        
        .stat-value {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--heading);
            line-height: 1.2;
        }
        
        .stat-label {
            color: var(--text-light);
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .chart-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .card {
            background-color: var(--card-bg);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 1px solid var(--border);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid var(--border);
        }
        
        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--heading);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .card-title i {
            color: var(--primary);
        }
        
        .card-body {
            margin-bottom: 1rem;
        }
        
        .card-footer {
            display: flex;
            justify-content: flex-end;
            padding-top: 1rem;
            border-top: 1px solid var(--border);
        }
        
        .table-responsive {
            overflow-x: auto;
            margin-bottom: 1.5rem;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background-color: var(--light);
            font-weight: 600;
            text-align: left;
            color: var(--text-dark);
            padding: 0.75rem 1rem;
        }
        
        td {
            padding: 0.75rem 1rem;
            border-top: 1px solid var(--border);
            color: var(--text);
        }
        
        tbody tr:hover {
            background-color: var(--light);
        }
        
        .badge {
            display: inline-block;
            padding: 0.25em 0.6em;
            font-size: 0.75em;
            font-weight: 600;
            border-radius: 50rem;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .badge-primary { background-color: #dbeafe; color: #1e40af; }
        .badge-success { background-color: #d1fae5; color: #065f46; }
        .badge-warning { background-color: #fef3c7; color: #92400e; }
        .badge-danger { background-color: #fee2e2; color: #991b1b; }
        
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.5rem;
            background-color: var(--card-bg);
            padding: 1rem;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            align-items: flex-end;
        }
        
        .form-group {
            flex-grow: 1;
            min-width: 200px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-dark);
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            font-family: inherit;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            color: var(--text);
            transition: var(--transition);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            font-size: 0.95rem;
            font-weight: 500;
            text-align: center;
            white-space: nowrap;
            vertical-align: middle;
            cursor: pointer;
            border: 1px solid transparent;
            border-radius: var(--radius-sm);
            transition: var(--transition);
            text-decoration: none;
        }
        
        .btn-primary {
            color: white;
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        
        .btn-secondary {
            color: var(--text-dark);
            background-color: white;
            border-color: var(--border);
        }
        
        .btn-secondary:hover {
            background-color: var(--light);
        }
        
        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .alert i {
            font-size: 1.25rem;
        }
        
        .alert-success {
            background-color: #dcfce7;
            color: #166534;
            border-left: 4px solid #16a34a;
        }
        
        .alert-danger {
            background-color: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #dc2626;
        }
        
        .alert-warning {
            background-color: #fef3c7;
            color: #92400e;
            border-left: 4px solid #f59e0b;
        }
        
        .alert-info {
            background-color: #dbeafe;
            color: #1e40af;
            border-left: 4px solid #2563eb;
        }
        
        .progress {
            height: 0.75rem;
            background-color: var(--light);
            border-radius: 50rem;
            overflow: hidden;
            margin-top: 0.5rem;
        }
        
        .progress-bar {
            height: 100%;
            border-radius: 50rem;
            transition: width 0.6s ease;
        }
        
        .progress-primary { background-color: var(--primary); }
        .progress-success { background-color: var(--success); }
        .progress-warning { background-color: var(--warning); }
        .progress-danger { background-color: var(--danger); }
        
        .stat-trend {
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
            margin-top: 0.25rem;
        }
        
        .trend-up {
            color: #16a34a;
        }
        
        .trend-down {
            color: #dc2626;
        }
        
        @media (max-width: 991px) {
            .chart-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .admin-nav {
                display: none;
            }
            
            .stats-cards {
                grid-template-columns: repeat(auto-fill, minmax(100%, 1fr));
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <header class="admin-header">
        <div class="container">
            <a href="dashboard.php" class="admin-logo">
                <i class="fas fa-gavel"></i>
                Noteria Admin
            </a>
            
            <nav class="admin-nav">
                <a href="dashboard.php" class="admin-nav-item">
                    <i class="fas fa-tachometer-alt"></i> Paneli
                </a>
                <a href="admin_noters.php" class="admin-nav-item">
                    <i class="fas fa-user-tie"></i> Noterët
                </a>
                <a href="admin_statistics.php" class="admin-nav-item active">
                    <i class="fas fa-chart-line"></i> Statistikat
                </a>
                <a href="subscription_dashboard.php" class="admin-nav-item">
                    <i class="fas fa-receipt"></i> Abonimet
                </a>
                <a href="reports.php" class="admin-nav-item">
                    <i class="fas fa-file-alt"></i> Raportet
                </a>
                <a href="settings.php" class="admin-nav-item">
                    <i class="fas fa-cog"></i> Konfigurimet
                </a>
                <a href="logout.php" class="admin-nav-item">
                    <i class="fas fa-sign-out-alt"></i> Dil
                </a>
            </nav>
        </div>
    </header>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title"><i class="fas fa-chart-line"></i> Statistikat e Sistemit</h1>
            
            <div class="filter-form">
                <div class="form-group">
                    <label for="start_date" class="form-label">Nga data</label>
                    <input type="text" id="start_date" name="start_date" class="form-control date-picker" 
                        value="<?php echo htmlspecialchars($startDate); ?>">
                </div>
                
                <div class="form-group">
                    <label for="end_date" class="form-label">Deri më</label>
                    <input type="text" id="end_date" name="end_date" class="form-control date-picker" 
                        value="<?php echo htmlspecialchars($endDate); ?>">
                </div>
                
                <button type="button" id="apply-filter" class="btn btn-primary">
                    <i class="fas fa-filter"></i> Filtro
                </button>
                
                <button type="button" id="reset-filter" class="btn btn-secondary">
                    <i class="fas fa-sync-alt"></i> Rivendos
                </button>
            </div>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <div><?php echo $error; ?></div>
            </div>
        <?php endif; ?>
        
        <?php
        // Kontrollo për tabela që mungojnë
        $missingTables = [];
        $requiredTables = ['login_attempts', 'payment_logs', 'session_logs', 'uploaded_files'];
        
        foreach ($requiredTables as $table) {
            if (!tableExists($pdo, $table)) {
                $missingTables[] = $table;
            }
        }
        ?>
        
        <?php if (!empty($missingTables)): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>Kujdes!</strong> Disa tabela të nevojshme për statistikat mungojnë në databazë:
                    <ul style="margin-top: 0.5rem; margin-left: 1.5rem;">
                        <?php foreach ($missingTables as $table): ?>
                            <li>Tabela <code><?php echo $table; ?></code> nuk ekziston</li>
                        <?php endforeach; ?>
                    </ul>
                    <div style="margin-top: 0.5rem;">
                        <a href="create_database_tables.php" style="color: inherit; font-weight: 600;">Kliko këtu për të krijuar tabelat e nevojshme</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($dbErrors)): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>Kujdes!</strong> Janë detektuar probleme me strukturën e databazës:
                    <ul style="margin-top: 0.5rem; margin-left: 1.5rem;">
                        <?php foreach ($dbErrors as $dbError): ?>
                            <li><?php echo $dbError; ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <div style="margin-top: 0.5rem;">
                        <a href="fix_database_columns.php" style="color: inherit; font-weight: 600;">Kliko këtu për të korrigjuar problemet</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Kartat e statistikave të përgjithshme -->
        <div class="stats-cards">
            <div class="stat-card">
                <div class="stat-icon stat-blue">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($noteryStats['total_noters']); ?></div>
                    <div class="stat-label">Noterë gjithsej</div>
                    <div class="stat-trend trend-up">
                        <i class="fas fa-arrow-up"></i> 12% nga muaji i kaluar
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon stat-green">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($noteryStats['active_noters']); ?></div>
                    <div class="stat-label">Noterë aktivë</div>
                    <div class="stat-trend trend-up">
                        <i class="fas fa-arrow-up"></i> 8% nga muaji i kaluar
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon stat-orange">
                    <i class="fas fa-euro-sign"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($paymentStats['total_amount'], 2); ?> €</div>
                    <div class="stat-label">Të ardhura në periudhën e zgjedhur</div>
                    <div class="stat-trend trend-up">
                        <i class="fas fa-arrow-up"></i> 15% nga periudha e mëparshme
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon stat-teal">
                    <i class="fas fa-receipt"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($paymentStats['total_payments']); ?></div>
                    <div class="stat-label">Pagesa në periudhën e zgjedhur</div>
                    <div class="stat-trend trend-up">
                        <i class="fas fa-arrow-up"></i> 5% nga periudha e mëparshme
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Grafikët -->
        <div class="chart-grid">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><i class="fas fa-chart-bar"></i> Pagesat mujore</h2>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="monthlyPaymentsChart"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><i class="fas fa-chart-line"></i> Regjistrimet mujore të noterëve</h2>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="monthlySignupsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Statistikat e sistemit -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><i class="fas fa-server"></i> Statistikat e sistemit</h2>
            </div>
            <div class="card-body">
                <div class="stats-cards">
                    <div class="stat-card">
                        <div class="stat-icon stat-purple">
                            <i class="fas fa-sign-in-alt"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo number_format($systemStats['login_attempts']); ?></div>
                            <div class="stat-label">Përpjekje për hyrje</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon stat-teal">
                            <i class="fas fa-credit-card"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo number_format($systemStats['payment_logs']); ?></div>
                            <div class="stat-label">Log-e pagesash</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon stat-blue">
                            <i class="fas fa-user-clock"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo number_format($systemStats['session_logs']); ?></div>
                            <div class="stat-label">Seanca hyrje</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon stat-green">
                            <i class="fas fa-file-upload"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo number_format($systemStats['uploaded_files']); ?></div>
                            <div class="stat-label">Dokumente të ngarkuar</div>
                        </div>
                    </div>
                </div>
                
                <div style="margin-top: 2rem;">
                    <h3 style="margin-bottom: 1rem; font-size: 1.2rem;">Statistikat e dokumentave</h3>
                    
                    <div style="margin-bottom: 1.5rem;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                            <span>Dokumente të përfunduar</span>
                            <span><?php echo number_format($documentStats['completed_documents']); ?> nga <?php echo number_format($documentStats['total_documents']); ?></span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar progress-success" style="width: <?php echo $documentStats['total_documents'] > 0 ? ($documentStats['completed_documents'] / $documentStats['total_documents'] * 100) : 0; ?>%"></div>
                        </div>
                    </div>
                    
                    <div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                            <span>Dokumente në pritje</span>
                            <span><?php echo number_format($documentStats['pending_documents']); ?> nga <?php echo number_format($documentStats['total_documents']); ?></span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar progress-warning" style="width: <?php echo $documentStats['total_documents'] > 0 ? ($documentStats['pending_documents'] / $documentStats['total_documents'] * 100) : 0; ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Top 5 noterët -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><i class="fas fa-trophy"></i> Top 5 noterët sipas pagesave</h2>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Noter</th>
                                <th>Email</th>
                                <th>Nr. i pagesave</th>
                                <th>Shuma e paguar</th>
                                <th>Veprime</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($topNoters)): ?>
                                <?php foreach ($topNoters as $noter): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 500;"><?php echo htmlspecialchars($noter['emri'] . ' ' . $noter['mbiemri']); ?></div>
                                        </td>
                                        <td><?php echo htmlspecialchars($noter['email']); ?></td>
                                        <td><?php echo number_format($noter['payment_count']); ?></td>
                                        <td><?php echo number_format($noter['total_paid'], 2); ?> €</td>
                                        <td>
                                            <a href="admin_noters.php?action=edit&id=<?php echo $noter['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-eye"></i> Shiko
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 2rem 1rem;">Nuk u gjetën të dhëna për periudhën e zgjedhur</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Raporti i pagesave -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><i class="fas fa-file-invoice-dollar"></i> Raport i pagesave</h2>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
                    <div>
                        <div style="font-size: 0.9rem; color: var(--text-light); margin-bottom: 0.5rem;">Numri total i pagesave</div>
                        <div style="font-size: 1.5rem; font-weight: 600; color: var(--text-dark);">
                            <?php echo number_format($paymentStats['total_payments']); ?>
                        </div>
                    </div>
                    
                    <div>
                        <div style="font-size: 0.9rem; color: var(--text-light); margin-bottom: 0.5rem;">Shuma totale e arkëtuar</div>
                        <div style="font-size: 1.5rem; font-weight: 600; color: var(--text-dark);">
                            <?php echo number_format($paymentStats['total_amount'], 2); ?> €
                        </div>
                    </div>
                    
                    <div>
                        <div style="font-size: 0.9rem; color: var(--text-light); margin-bottom: 0.5rem;">Pagesa mesatare</div>
                        <div style="font-size: 1.5rem; font-weight: 600; color: var(--text-dark);">
                            <?php echo number_format($paymentStats['avg_amount'], 2); ?> €
                        </div>
                    </div>
                    
                    <div>
                        <div style="font-size: 0.9rem; color: var(--text-light); margin-bottom: 0.5rem;">Pagesa më e madhe</div>
                        <div style="font-size: 1.5rem; font-weight: 600; color: var(--text-dark);">
                            <?php echo number_format($paymentStats['max_amount'], 2); ?> €
                        </div>
                    </div>
                </div>
                
                <div class="card-footer">
                    <a href="reports.php?type=payment&start=<?php echo $startDate; ?>&end=<?php echo $endDate; ?>" class="btn btn-primary">
                        <i class="fas fa-download"></i> Gjenero raport të detajuar
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializo date picker
            flatpickr('.date-picker', {
                dateFormat: 'Y-m-d',
                altInput: true,
                altFormat: 'd.m.Y'
            });
            
            // Filtrimi
            document.getElementById('apply-filter').addEventListener('click', function() {
                const startDate = document.getElementById('start_date').value;
                const endDate = document.getElementById('end_date').value;
                
                window.location.href = `admin_statistics.php?start_date=${startDate}&end_date=${endDate}`;
            });
            
            // Rivendos filtrat
            document.getElementById('reset-filter').addEventListener('click', function() {
                window.location.href = 'admin_statistics.php';
            });
            
            // Grafikët
            const monthlyPaymentsLabels = <?php echo json_encode(array_column($monthlyPayments, 'month')); ?>;
            const monthlyPaymentsData = <?php echo json_encode(array_column($monthlyPayments, 'monthly_amount')); ?>;
            const monthlyPaymentsCounts = <?php echo json_encode(array_column($monthlyPayments, 'payment_count')); ?>;
            
            const monthlySignupsLabels = <?php echo json_encode(array_column($monthlySignups, 'month')); ?>;
            const monthlySignupsData = <?php echo json_encode(array_column($monthlySignups, 'signup_count')); ?>;
            
            // Grafiku i pagesave mujore
            const paymentsCtx = document.getElementById('monthlyPaymentsChart').getContext('2d');
            new Chart(paymentsCtx, {
                type: 'bar',
                data: {
                    labels: monthlyPaymentsLabels,
                    datasets: [{
                        label: 'Shuma e paguar (€)',
                        data: monthlyPaymentsData,
                        backgroundColor: 'rgba(37, 99, 235, 0.7)',
                        borderColor: 'rgba(37, 99, 235, 1)',
                        borderWidth: 1
                    }, {
                        label: 'Numri i pagesave',
                        data: monthlyPaymentsCounts,
                        type: 'line',
                        backgroundColor: 'rgba(16, 185, 129, 0.2)',
                        borderColor: 'rgba(16, 185, 129, 1)',
                        borderWidth: 2,
                        pointBackgroundColor: 'rgba(16, 185, 129, 1)',
                        tension: 0.4,
                        yAxisID: 'y1'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Shuma (€)'
                            }
                        },
                        y1: {
                            position: 'right',
                            beginAtZero: true,
                            grid: {
                                drawOnChartArea: false
                            },
                            title: {
                                display: true,
                                text: 'Numri i pagesave'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        title: {
                            display: false
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        }
                    }
                }
            });
            
            // Grafiku i regjistrimeve mujore
            const signupsCtx = document.getElementById('monthlySignupsChart').getContext('2d');
            new Chart(signupsCtx, {
                type: 'line',
                data: {
                    labels: monthlySignupsLabels,
                    datasets: [{
                        label: 'Noterë të regjistruar',
                        data: monthlySignupsData,
                        backgroundColor: 'rgba(245, 158, 11, 0.2)',
                        borderColor: 'rgba(245, 158, 11, 1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Numri i regjistrimeve'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>
