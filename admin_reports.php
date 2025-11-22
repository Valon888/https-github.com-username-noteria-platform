<?php
// Konfigurimi i raportimit të gabimeve
ini_set('display_errors', 0); // Mos shfaq gabime në faqe
ini_set('log_errors', 1);     // Log gabimet në server
ini_set('error_log', __DIR__ . '/error.log'); // Ruaj gabimet në error.log në këtë folder

// Fillimi i sigurt i sesionit - PARA require_once
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 1 : 0);
    ini_set('session.use_strict_mode', 1);
    session_start();
}
require_once 'confidb.php';

// Kontrollo nëse përdoruesi është i kyçur dhe ka rolin e duhur
if (!isset($_SESSION['user_id']) || $_SESSION['roli'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Funksioni për gjenerimin e raporteve
function generateReport($pdo, $reportType, $startDate = null, $endDate = null, $zyraId = null) {
    $data = [];
    
    switch ($reportType) {
        case 'payments':
            $sql = "SELECT p.id, r.service, p.amount, p.created_at, p.status, 
                    u.emri, u.mbiemri, u.email,
                    z.emri AS zyra_emri
                    FROM payments p
                    JOIN reservations r ON p.reservation_id = r.id
                    JOIN users u ON r.user_id = u.id
                    JOIN zyrat z ON r.zyra_id = z.id
                    WHERE 1=1";
            
            if ($startDate) {
                $sql .= " AND p.created_at >= :startDate";
            }
            if ($endDate) {
                $sql .= " AND p.created_at <= :endDate";
            }
            if ($zyraId) {
                $sql .= " AND r.zyra_id = :zyraId";
            }
            
            $sql .= " ORDER BY p.created_at DESC";
            
            $stmt = $pdo->prepare($sql);
            
            if ($startDate) {
                $stmt->bindParam(':startDate', $startDate);
            }
            if ($endDate) {
                $stmt->bindParam(':endDate', $endDate);
            }
            if ($zyraId) {
                $stmt->bindParam(':zyraId', $zyraId);
            }
            
            $stmt->execute();
            $data = $stmt->fetchAll();
            
            // Llogarit totalin
            $total = 0;
            foreach ($data as $row) {
                $total += $row['amount'];
            }
            $summary = [
                'total' => $total,
                'count' => count($data)
            ];
            
            return ['data' => $data, 'summary' => $summary];
            
        case 'subscriptions':
            $sql = "SELECT s.*, z.emri AS zyra_emri 
                    FROM subscription s 
                    JOIN zyrat z ON s.zyra_id = z.id 
                    WHERE 1=1";
            
            if ($startDate) {
                $sql .= " AND s.start_date >= :startDate";
            }
            if ($endDate) {
                $sql .= " AND s.start_date <= :endDate";
            }
            if ($zyraId) {
                $sql .= " AND s.zyra_id = :zyraId";
            }
            
            $sql .= " ORDER BY s.start_date DESC";
            
            $stmt = $pdo->prepare($sql);
            
            if ($startDate) {
                $stmt->bindParam(':startDate', $startDate);
            }
            if ($endDate) {
                $stmt->bindParam(':endDate', $endDate);
            }
            if ($zyraId) {
                $stmt->bindParam(':zyraId', $zyraId);
            }
            
            $stmt->execute();
            $data = $stmt->fetchAll();
            
            // Llogarit totalin
            $total = 0;
            $active = 0;
            $expired = 0;
            foreach ($data as $row) {
                // Subscription table doesn't have an amount column
                // Set a default price or retrieve from another source if needed
                $price = isset($row['amount']) ? $row['amount'] : 0;
                $total += $price;
                if ($row['status'] === 'active' && strtotime($row['expiry_date']) >= time()) {
                    $active++;
                } else {
                    $expired++;
                }
            }
            $summary = [
                'total' => $total,
                'count' => count($data),
                'active' => $active,
                'expired' => $expired
            ];
            
            return ['data' => $data, 'summary' => $summary];
            
        case 'reservations':
            $sql = "SELECT r.id, r.service, r.date, r.time, r.created_at,
                    u.emri AS user_emri, u.mbiemri AS user_mbiemri, u.email AS user_email,
                    z.emri AS zyra_emri
                    FROM reservations r
                    JOIN users u ON r.user_id = u.id
                    JOIN zyrat z ON r.zyra_id = z.id
                    WHERE 1=1";
            
            if ($startDate) {
                $sql .= " AND r.date >= :startDate";
            }
            if ($endDate) {
                $sql .= " AND r.date <= :endDate";
            }
            if ($zyraId) {
                $sql .= " AND r.zyra_id = :zyraId";
            }
            
            $sql .= " ORDER BY r.date DESC, r.time DESC";
            
            $stmt = $pdo->prepare($sql);
            
            if ($startDate) {
                $stmt->bindParam(':startDate', $startDate);
            }
            if ($endDate) {
                $stmt->bindParam(':endDate', $endDate);
            }
            if ($zyraId) {
                $stmt->bindParam(':zyraId', $zyraId);
            }
            
            $stmt->execute();
            $data = $stmt->fetchAll();
            
            // Grupoj të dhënat sipas shërbimeve
            $services = [];
            foreach ($data as $row) {
                $service = $row['service'];
                if (!isset($services[$service])) {
                    $services[$service] = 0;
                }
                $services[$service]++;
            }
            
            $summary = [
                'count' => count($data),
                'services' => $services
            ];
            
            return ['data' => $data, 'summary' => $summary];
            
        case 'users':
            $sql = "SELECT u.id, u.emri, u.mbiemri, u.email, u.roli, u.busy AS aktiv, u.created_at,
                    z.emri AS zyra_emri
                    FROM users u
                    LEFT JOIN zyrat z ON u.zyra_id = z.id
                    WHERE 1=1";
            
            if ($startDate) {
                $sql .= " AND u.created_at >= :startDate";
            }
            if ($endDate) {
                $sql .= " AND u.created_at <= :endDate";
            }
            if ($zyraId) {
                $sql .= " AND u.zyra_id = :zyraId";
            }
            
            $sql .= " ORDER BY u.created_at DESC";
            
            $stmt = $pdo->prepare($sql);
            
            if ($startDate) {
                $stmt->bindParam(':startDate', $startDate);
            }
            if ($endDate) {
                $stmt->bindParam(':endDate', $endDate);
            }
            if ($zyraId) {
                $stmt->bindParam(':zyraId', $zyraId);
            }
            
            $stmt->execute();
            $data = $stmt->fetchAll();
            
            // Numëro përdoruesit sipas roleve
            $roles = [
                'admin' => 0,
                'zyra' => 0,
                'user' => 0
            ];
            
            foreach ($data as $row) {
                $roles[$row['roli']]++;
            }
            
            $summary = [
                'count' => count($data),
                'roles' => $roles
            ];
            
            return ['data' => $data, 'summary' => $summary];
    }
    
    return ['data' => [], 'summary' => []];
}

// Parametrat e raportit
$reportType = $_GET['type'] ?? 'payments';
$startDate = $_GET['start_date'] ?? date('Y-m-01'); // Fillimi i muajit aktual
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$zyraId = $_GET['zyra_id'] ?? null;

// Gjeneroj raportin
$report = generateReport($pdo, $reportType, $startDate, $endDate, $zyraId);

// Merr listën e zyrave për filtrim
$zyrat = $pdo->query("SELECT id, emri FROM zyrat ORDER BY emri")->fetchAll();
?>

<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Raportet | Admin Panel | Noteria</title>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #2d6cdf;
            --primary-dark: #184fa3;
            --secondary: #10b981;
            --secondary-dark: #047857;
            --warning: #f59e0b;
            --danger: #dc2626;
            --light: #f8fafc;
            --dark: #1e293b;
            --gray: #64748b;
            --border: #e2eafc;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Montserrat', Arial, sans-serif;
            background: linear-gradient(120deg, #e2eafc 0%, #f8fafc 100%);
            color: var(--dark);
            line-height: 1.6;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }
        
        .container {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 260px;
            background: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            padding: 24px 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 10;
        }
        
        .logo {
            padding: 0 24px 24px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 24px;
        }
        
        .logo h1 {
            color: var(--primary);
            font-size: 1.7rem;
            font-weight: 800;
        }
        
        .logo span {
            color: var(--secondary);
        }
        
        .menu-item {
            display: flex;
            align-items: center;
            padding: 12px 24px;
            color: var(--gray);
            text-decoration: none;
            transition: all 0.3s;
            font-weight: 600;
            position: relative;
        }
        
        .menu-item:hover, .menu-item.active {
            color: var(--primary);
            background: rgba(45, 108, 223, 0.05);
        }
        
        .menu-item.active {
            border-left: 4px solid var(--primary);
        }
        
        .menu-item i {
            margin-right: 12px;
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
        }
        
        .admin-info {
            padding: 24px;
            margin-top: auto;
            border-top: 1px solid var(--border);
        }
        
        .admin-name {
            font-weight: 700;
            margin-bottom: 4px;
        }
        
        .admin-role {
            color: var(--gray);
            font-size: 0.9rem;
        }
        
        .logout-btn {
            margin-top: 12px;
            padding: 8px 12px;
            background: var(--light);
            color: var(--primary);
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .logout-btn:hover {
            background: var(--border);
        }
        
        .main-content {
            flex: 1;
            padding: 32px;
            margin-left: 260px;
        }
        
        .dashboard-header {
            margin-bottom: 32px;
        }
        
        .dashboard-header h2 {
            font-size: 1.8rem;
            color: var(--dark);
            margin-bottom: 8px;
        }
        
        .breadcrumb {
            display: flex;
            gap: 8px;
            color: var(--gray);
            font-size: 0.9rem;
        }
        
        .breadcrumb span:not(:last-child):after {
            content: '/';
            margin-left: 8px;
        }
        
        .breadcrumb span:last-child {
            color: var(--primary);
            font-weight: 600;
        }
        
        .section {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
            margin-bottom: 32px;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border);
            flex-wrap: wrap;
            gap: 16px;
        }
        
        .section-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--dark);
        }
        
        .filter-form {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .form-label {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--dark);
        }
        
        .form-control {
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 0.95rem;
            transition: all 0.2s;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 2px rgba(45, 108, 223, 0.1);
        }
        
        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.95rem;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        
        .btn-light {
            background: var(--light);
            color: var(--dark);
        }
        
        .btn-light:hover {
            background: var(--border);
        }
        
        .btn-export {
            background: var(--secondary);
            color: white;
        }
        
        .btn-export:hover {
            background: var(--secondary-dark);
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .summary-card {
            background: var(--light);
            padding: 16px;
            border-radius: 8px;
        }
        
        .summary-value {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 4px;
        }
        
        .summary-label {
            color: var(--gray);
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead th {
            background: var(--light);
            text-align: left;
            padding: 12px 16px;
            font-weight: 600;
            color: var(--dark);
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
        }
        
        tbody td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            color: var(--gray);
        }
        
        tbody tr:hover {
            background: rgba(45, 108, 223, 0.02);
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
        }
        
        .badge-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--secondary);
        }
        
        .badge-warning {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }
        
        .badge-danger {
            background: rgba(220, 38, 38, 0.1);
            color: var(--danger);
        }
        
        .chart-container {
            height: 300px;
            margin-top: 24px;
            margin-bottom: 32px;
        }
        
        .tab-nav {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            overflow-x: auto;
            padding-bottom: 8px;
        }
        
        .tab-btn {
            padding: 8px 16px;
            background: var(--light);
            border: none;
            border-radius: 6px;
            font-weight: 600;
            color: var(--gray);
            cursor: pointer;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .tab-btn.active {
            background: var(--primary);
            color: white;
        }
        
        .tab-btn:hover:not(.active) {
            background: var(--border);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .no-data {
            text-align: center;
            padding: 24px;
            color: var(--gray);
        }
        
        .export-options {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
        }
        
        @media print {
            .sidebar, .filter-form, .export-options, .tab-nav {
                display: none !important;
            }
            
            .main-content {
                margin-left: 0;
                padding: 0;
            }
            
            .section {
                box-shadow: none;
                border: 1px solid #eee;
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 80px;
                padding: 16px 0;
            }
            
            .logo {
                padding: 0 8px 16px;
                text-align: center;
            }
            
            .logo h1 {
                font-size: 1.2rem;
            }
            
            .menu-item {
                flex-direction: column;
                padding: 12px 8px;
                text-align: center;
            }
            
            .menu-item i {
                margin: 0 0 8px 0;
                font-size: 1.2rem;
            }
            
            .menu-item span {
                font-size: 0.7rem;
            }
            
            .admin-info {
                display: none;
            }
            
            .main-content {
                margin-left: 80px;
                padding: 24px 16px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="logo">
                <h1>Noteria <span>Admin</span></h1>
            </div>
            
            <a href="admin_dashboard.php" class="menu-item">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            
            <a href="admin_reports.php" class="menu-item active">
                <i class="fas fa-chart-bar"></i>
                <span>Raportet</span>
            </a>
            
            <a href="admin_users.php" class="menu-item">
                <i class="fas fa-users"></i>
                <span>Përdoruesit</span>
            </a>
            
            <a href="admin_zyrat.php" class="menu-item">
                <i class="fas fa-building"></i>
                <span>Zyrat Noteriale</span>
            </a>
            
            <a href="admin_subscriptions.php" class="menu-item">
                <i class="fas fa-credit-card"></i>
                <span>Abonimet</span>
            </a>
            
            <a href="admin_settings.php" class="menu-item">
                <i class="fas fa-cog"></i>
                <span>Cilësimet</span>
            </a>
            
            <a href="admin_logs.php" class="menu-item">
                <i class="fas fa-history"></i>
                <span>Aktiviteti</span>
            </a>
            
            <div class="admin-info">
                <div class="admin-name"><?php echo $_SESSION['emri'] . ' ' . $_SESSION['mbiemri']; ?></div>
                <div class="admin-role">Administrator</div>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Shkyçu
                </a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="dashboard-header">
                <h2>Raportet dhe Statistikat</h2>
                <div class="breadcrumb">
                    <span>Noteria</span>
                    <span>Admin</span>
                    <span>Raportet</span>
                </div>
            </div>
            
            <div class="tab-nav">
                <a href="?type=payments" class="tab-btn <?php echo $reportType === 'payments' ? 'active' : ''; ?>">
                    <i class="fas fa-money-bill-wave"></i> Pagesat
                </a>
                <a href="?type=subscriptions" class="tab-btn <?php echo $reportType === 'subscriptions' ? 'active' : ''; ?>">
                    <i class="fas fa-credit-card"></i> Abonimet
                </a>
                <a href="?type=reservations" class="tab-btn <?php echo $reportType === 'reservations' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-check"></i> Rezervimet
                </a>
                <a href="?type=users" class="tab-btn <?php echo $reportType === 'users' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i> Përdoruesit
                </a>
            </div>
            
            <div class="section">
                <div class="section-header">
                    <h3 class="section-title">
                        <?php
                        switch ($reportType) {
                            case 'payments':
                                echo '<i class="fas fa-money-bill-wave"></i> Raporti i Pagesave';
                                break;
                            case 'subscriptions':
                                echo '<i class="fas fa-credit-card"></i> Raporti i Abonimeve';
                                break;
                            case 'reservations':
                                echo '<i class="fas fa-calendar-check"></i> Raporti i Rezervimeve';
                                break;
                            case 'users':
                                echo '<i class="fas fa-users"></i> Raporti i Përdoruesve';
                                break;
                        }
                        ?>
                    </h3>
                    
                    <form method="GET" class="filter-form">
                        <input type="hidden" name="type" value="<?php echo htmlspecialchars($reportType); ?>">
                        
                        <div class="form-group">
                            <label class="form-label">Data e fillimit</label>
                            <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($startDate); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Data e mbarimit</label>
                            <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($endDate); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Zyra</label>
                            <select name="zyra_id" class="form-control">
                                <option value="">Të gjitha zyrat</option>
                                <?php foreach ($zyrat as $zyra): ?>
                                <option value="<?php echo $zyra['id']; ?>" <?php echo $zyraId == $zyra['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($zyra['emri']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group" style="align-self: flex-end;">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Filtro
                            </button>
                        </div>
                    </form>
                </div>
                
                <div class="export-options">
                    <button class="btn btn-export" onclick="window.print()">
                        <i class="fas fa-print"></i> Printo
                    </button>
                    <button class="btn btn-export" onclick="exportToExcel()">
                        <i class="fas fa-file-excel"></i> Eksporto në Excel
                    </button>
                    <button class="btn btn-export" onclick="exportToCSV()">
                        <i class="fas fa-file-csv"></i> Eksporto në CSV
                    </button>
                </div>
                
                <!-- Përmbledhja -->
                <div class="summary-grid">
                    <?php if ($reportType === 'payments'): ?>
                        <div class="summary-card">
                            <div class="summary-value"><?php echo number_format($report['summary']['count']); ?></div>
                            <div class="summary-label">Totali i pagesave</div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-value"><?php echo number_format($report['summary']['total'], 2); ?>€</div>
                            <div class="summary-label">Shuma totale</div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-value">
                                <?php
                                $avgAmount = $report['summary']['count'] > 0 ? 
                                    $report['summary']['total'] / $report['summary']['count'] : 0;
                                echo number_format($avgAmount, 2);
                                ?>€
                            </div>
                            <div class="summary-label">Pagesa mesatare</div>
                        </div>
                    <?php elseif ($reportType === 'subscriptions'): ?>
                        <div class="summary-card">
                            <div class="summary-value"><?php echo number_format($report['summary']['count']); ?></div>
                            <div class="summary-label">Totali i abonimeve</div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-value"><?php echo number_format(isset($report['summary']['total']) ? $report['summary']['total'] : 0, 2); ?>€</div>
                            <div class="summary-label">Shuma totale</div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-value"><?php echo number_format($report['summary']['active']); ?></div>
                            <div class="summary-label">Abonimi aktive</div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-value"><?php echo number_format($report['summary']['expired']); ?></div>
                            <div class="summary-label">Abonimi të skaduara</div>
                        </div>
                    <?php elseif ($reportType === 'reservations'): ?>
                        <div class="summary-card">
                            <div class="summary-value"><?php echo number_format($report['summary']['count']); ?></div>
                            <div class="summary-label">Totali i rezervimeve</div>
                        </div>
                        <?php foreach ($report['summary']['services'] as $service => $count): ?>
                        <div class="summary-card">
                            <div class="summary-value"><?php echo number_format($count); ?></div>
                            <div class="summary-label"><?php echo htmlspecialchars($service); ?></div>
                        </div>
                        <?php endforeach; ?>
                    <?php elseif ($reportType === 'users'): ?>
                        <div class="summary-card">
                            <div class="summary-value"><?php echo number_format($report['summary']['count']); ?></div>
                            <div class="summary-label">Totali i përdoruesve</div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-value"><?php echo number_format($report['summary']['roles']['admin']); ?></div>
                            <div class="summary-label">Administratorë</div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-value"><?php echo number_format($report['summary']['roles']['zyra']); ?></div>
                            <div class="summary-label">Zyra Noteriale</div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-value"><?php echo number_format($report['summary']['roles']['user']); ?></div>
                            <div class="summary-label">Përdorues të thjeshtë</div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Chart -->
                <div class="chart-container">
                    <canvas id="reportChart"></canvas>
                </div>
                
                <!-- Data Table -->
                <?php if ($reportType === 'payments'): ?>
                    <?php if (count($report['data']) > 0): ?>
                    <div class="table-responsive">
                        <table id="reportTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Përdoruesi</th>
                                    <th>Shërbimi</th>
                                    <th>Zyra</th>
                                    <th>Shuma</th>
                                    <th>Data</th>
                                    <th>Statusi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report['data'] as $row): ?>
                                <tr>
                                    <td><?php echo $row['id']; ?></td>
                                    <td><?php echo htmlspecialchars($row['emri'] . ' ' . $row['mbiemri']); ?></td>
                                    <td><?php echo htmlspecialchars($row['service']); ?></td>
                                    <td><?php echo htmlspecialchars($row['zyra_emri']); ?></td>
                                    <td><?php echo number_format($row['amount'], 2); ?>€</td>
                                    <td><?php echo date('d.m.Y H:i', strtotime($row['created_at'])); ?></td>
                                    <td>
                                        <?php if ($row['status'] === 'completed'): ?>
                                        <span class="badge badge-success">Kompletuar</span>
                                        <?php elseif ($row['status'] === 'pending'): ?>
                                        <span class="badge badge-warning">Në Pritje</span>
                                        <?php else: ?>
                                        <span class="badge badge-danger">Dështuar</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="no-data">Nuk u gjetën të dhëna për periudhën e zgjedhur.</div>
                    <?php endif; ?>
                <?php elseif ($reportType === 'subscriptions'): ?>
                    <?php if (count($report['data']) > 0): ?>
                    <div class="table-responsive">
                        <table id="reportTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Zyra</th>
                                    <th>Data e Fillimit</th>
                                    <th>Data e Mbarimit</th>
                                    <th>Shuma</th>
                                    <th>Statusi</th>
                                    <th>Pagesa</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report['data'] as $row): ?>
                                <tr>
                                    <td><?php echo $row['id']; ?></td>
                                    <td><?php echo htmlspecialchars($row['zyra_emri']); ?></td>
                                    <td><?php echo date('d.m.Y', strtotime($row['start_date'])); ?></td>
                                    <td><?php echo date('d.m.Y', strtotime($row['expiry_date'])); ?></td>
                                    <td><?php echo number_format(isset($row['amount']) ? $row['amount'] : 0, 2); ?>€</td>
                                    <td>
                                        <?php 
                                        $status = 'expired';
                                        $statusClass = 'badge-danger';
                                        
                                        if ($row['status'] === 'active' && strtotime($row['expiry_date']) >= time()) {
                                            $status = 'active';
                                            $statusClass = 'badge-success';
                                        } elseif ($row['payment_status'] === 'pending') {
                                            $status = 'pending';
                                            $statusClass = 'badge-warning';
                                        }
                                        ?>
                                        <span class="badge <?php echo $statusClass; ?>"><?php echo ucfirst($status); ?></span>
                                    </td>
                                    <td><?php echo ucfirst($row['payment_status']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="no-data">Nuk u gjetën të dhëna për periudhën e zgjedhur.</div>
                    <?php endif; ?>
                <?php elseif ($reportType === 'reservations'): ?>
                    <?php if (count($report['data']) > 0): ?>
                    <div class="table-responsive">
                        <table id="reportTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Shërbimi</th>
                                    <th>Përdoruesi</th>
                                    <th>Zyra</th>
                                    <th>Data</th>
                                    <th>Ora</th>
                                    <th>Krijuar më</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report['data'] as $row): ?>
                                <tr>
                                    <td><?php echo $row['id']; ?></td>
                                    <td><?php echo htmlspecialchars($row['service']); ?></td>
                                    <td><?php echo htmlspecialchars($row['user_emri'] . ' ' . $row['user_mbiemri']); ?></td>
                                    <td><?php echo htmlspecialchars($row['zyra_emri']); ?></td>
                                    <td><?php echo date('d.m.Y', strtotime($row['date'])); ?></td>
                                    <td><?php echo $row['time']; ?></td>
                                    <td><?php echo date('d.m.Y', strtotime($row['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="no-data">Nuk u gjetën të dhëna për periudhën e zgjedhur.</div>
                    <?php endif; ?>
                <?php elseif ($reportType === 'users'): ?>
                    <?php if (count($report['data']) > 0): ?>
                    <div class="table-responsive">
                        <table id="reportTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Emri</th>
                                    <th>Email</th>
                                    <th>Roli</th>
                                    <th>Zyra</th>
                                    <th>Statusi</th>
                                    <th>Regjistruar më</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report['data'] as $row): ?>
                                <tr>
                                    <td><?php echo $row['id']; ?></td>
                                    <td><?php echo htmlspecialchars($row['emri'] . ' ' . $row['mbiemri']); ?></td>
                                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                                    <td>
                                        <?php if ($row['roli'] === 'admin'): ?>
                                        <span class="badge badge-danger">Admin</span>
                                        <?php elseif ($row['roli'] === 'zyra'): ?>
                                        <span class="badge badge-warning">Zyrë</span>
                                        <?php else: ?>
                                        <span class="badge badge-success">Përdorues</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $row['zyra_emri'] ? htmlspecialchars($row['zyra_emri']) : 'N/A'; ?></td>
                                    <td>
                                        <?php if (isset($row['aktiv']) && $row['aktiv']): ?>
                                        <span class="badge badge-success">Aktiv</span>
                                        <?php else: ?>
                                        <span class="badge badge-danger">Joaktiv</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d.m.Y', strtotime($row['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="no-data">Nuk u gjetën të dhëna për periudhën e zgjedhur.</div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Chart data preparation
        const ctx = document.getElementById('reportChart').getContext('2d');
        let chartData = {};
        let chartOptions = {};
        
        <?php if ($reportType === 'payments'): ?>
            // Group payments by date
            const paymentsByDate = {};
            const amounts = {};
            
            <?php foreach ($report['data'] as $row): ?>
                const date = '<?php echo date('Y-m-d', strtotime($row['created_at'])); ?>';
                if (!paymentsByDate[date]) {
                    paymentsByDate[date] = 0;
                    amounts[date] = 0;
                }
                paymentsByDate[date]++;
                amounts[date] += <?php echo $row['amount']; ?>;
            <?php endforeach; ?>
            
            const sortedDates = Object.keys(paymentsByDate).sort();
            
            chartData = {
                labels: sortedDates.map(date => {
                    const d = new Date(date);
                    return d.toLocaleDateString('en-GB');
                }),
                datasets: [
                    {
                        label: 'Numri i pagesave',
                        data: sortedDates.map(date => paymentsByDate[date]),
                        backgroundColor: 'rgba(45, 108, 223, 0.2)',
                        borderColor: 'rgba(45, 108, 223, 1)',
                        borderWidth: 2,
                        tension: 0.3,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Shuma e pagesave (€)',
                        data: sortedDates.map(date => amounts[date]),
                        backgroundColor: 'rgba(16, 185, 129, 0.2)',
                        borderColor: 'rgba(16, 185, 129, 1)',
                        borderWidth: 2,
                        tension: 0.3,
                        yAxisID: 'y1'
                    }
                ]
            };
            
            chartOptions = {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        position: 'left',
                        grid: {
                            display: false
                        },
                        title: {
                            display: true,
                            text: 'Numri i pagesave'
                        }
                    },
                    y1: {
                        beginAtZero: true,
                        position: 'right',
                        grid: {
                            display: false
                        },
                        title: {
                            display: true,
                            text: 'Shuma e pagesave (€)'
                        }
                    }
                }
            };
        <?php elseif ($reportType === 'subscriptions'): ?>
            // Group subscriptions by month
            const subscriptionsByMonth = {};
            const amountsByMonth = {};
            
            <?php foreach ($report['data'] as $row): ?>
                const startDate = '<?php echo date('Y-m', strtotime($row['start_date'])); ?>';
                if (!subscriptionsByMonth[startDate]) {
                    subscriptionsByMonth[startDate] = 0;
                    amountsByMonth[startDate] = 0;
                }
                subscriptionsByMonth[startDate]++;
                amountsByMonth[startDate] += <?php echo $row['amount']; ?>;
            <?php endforeach; ?>
            
            const sortedMonths = Object.keys(subscriptionsByMonth).sort();
            
            chartData = {
                labels: sortedMonths.map(month => {
                    const [year, monthNum] = month.split('-');
                    const date = new Date(year, monthNum - 1, 1);
                    return date.toLocaleDateString('en-GB', { month: 'long', year: 'numeric' });
                }),
                datasets: [
                    {
                        label: 'Numri i abonimeve',
                        data: sortedMonths.map(month => subscriptionsByMonth[month]),
                        backgroundColor: 'rgba(45, 108, 223, 0.2)',
                        borderColor: 'rgba(45, 108, 223, 1)',
                        borderWidth: 2,
                        tension: 0.3,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Të ardhurat nga abonimet (€)',
                        data: sortedMonths.map(month => amountsByMonth[month]),
                        backgroundColor: 'rgba(16, 185, 129, 0.2)',
                        borderColor: 'rgba(16, 185, 129, 1)',
                        borderWidth: 2,
                        tension: 0.3,
                        yAxisID: 'y1'
                    }
                ]
            };
            
            chartOptions = {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        position: 'left',
                        grid: {
                            display: false
                        },
                        title: {
                            display: true,
                            text: 'Numri i abonimeve'
                        }
                    },
                    y1: {
                        beginAtZero: true,
                        position: 'right',
                        grid: {
                            display: false
                        },
                        title: {
                            display: true,
                            text: 'Të ardhurat (€)'
                        }
                    }
                }
            };
        <?php elseif ($reportType === 'reservations'): ?>
            // Group reservations by service type
            const reservationsByService = {};
            
            <?php foreach ($report['data'] as $row): ?>
                const service = '<?php echo addslashes($row['service']); ?>';
                if (!reservationsByService[service]) {
                    reservationsByService[service] = 0;
                }
                reservationsByService[service]++;
            <?php endforeach; ?>
            
            const services = Object.keys(reservationsByService);
            
            chartData = {
                labels: services,
                datasets: [
                    {
                        data: services.map(service => reservationsByService[service]),
                        backgroundColor: [
                            'rgba(45, 108, 223, 0.7)',
                            'rgba(16, 185, 129, 0.7)',
                            'rgba(245, 158, 11, 0.7)',
                            'rgba(220, 38, 38, 0.7)',
                            'rgba(124, 58, 237, 0.7)',
                            'rgba(14, 165, 233, 0.7)'
                        ],
                        borderWidth: 1
                    }
                ]
            };
            
            chartOptions = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            };
        <?php elseif ($reportType === 'users'): ?>
            // Group users by role
            const usersByRole = {
                'admin': <?php echo $report['summary']['roles']['admin']; ?>,
                'zyra': <?php echo $report['summary']['roles']['zyra']; ?>,
                'user': <?php echo $report['summary']['roles']['user']; ?>
            };
            
            chartData = {
                labels: ['Administratorë', 'Zyra Noteriale', 'Përdorues të thjeshtë'],
                datasets: [
                    {
                        data: [usersByRole.admin, usersByRole.zyra, usersByRole.user],
                        backgroundColor: [
                            'rgba(220, 38, 38, 0.7)',
                            'rgba(245, 158, 11, 0.7)',
                            'rgba(16, 185, 129, 0.7)'
                        ],
                        borderWidth: 1
                    }
                ]
            };
            
            chartOptions = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            };
        <?php endif; ?>
        
        // Create chart
        <?php if ($reportType === 'payments' || $reportType === 'subscriptions'): ?>
            new Chart(ctx, {
                type: 'line',
                data: chartData,
                options: chartOptions
            });
        <?php elseif ($reportType === 'reservations'): ?>
            new Chart(ctx, {
                type: 'pie',
                data: chartData,
                options: chartOptions
            });
        <?php elseif ($reportType === 'users'): ?>
            new Chart(ctx, {
                type: 'doughnut',
                data: chartData,
                options: chartOptions
            });
        <?php endif; ?>
        
        // Export functions
        window.exportToExcel = function() {
            const table = document.getElementById('reportTable');
            if (!table) {
                alert('Nuk ka të dhëna për eksport!');
                return;
            }
            
            let html = '<table><thead>';
            
            // Table header
            const headers = table.querySelectorAll('thead th');
            html += '<tr>';
            headers.forEach(header => {
                html += `<th>${header.innerText}</th>`;
            });
            html += '</tr></thead><tbody>';
            
            // Table data
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                html += '<tr>';
                row.querySelectorAll('td').forEach(cell => {
                    html += `<td>${cell.innerText}</td>`;
                });
                html += '</tr>';
            });
            
            html += '</tbody></table>';
            
            // Create Excel file
            const uri = 'data:application/vnd.ms-excel;base64,';
            const template = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40"><head><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>{worksheet}</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--><meta http-equiv="content-type" content="text/plain; charset=UTF-8"/></head><body><table>{table}</table></body></html>';
            
            const base64 = function(s) {
                return window.btoa(unescape(encodeURIComponent(s)));
            };
            
            const format = function(s, c) {
                return s.replace(/{(\w+)}/g, function(m, p) {
                    return c[p];
                });
            };
            
            const ctx = {worksheet: 'Report', table: html};
            const link = document.createElement('a');
            link.download = `raport_${new Date().toISOString().split('T')[0]}.xls`;
            link.href = uri + base64(format(template, ctx));
            link.click();
        };
        
        window.exportToCSV = function() {
            const table = document.getElementById('reportTable');
            if (!table) {
                alert('Nuk ka të dhëna për eksport!');
                return;
            }
            
            let csv = [];
            
            // Table header
            const headers = table.querySelectorAll('thead th');
            let headerRow = [];
            headers.forEach(header => {
                headerRow.push('"' + header.innerText + '"');
            });
            csv.push(headerRow.join(','));
            
            // Table data
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                let rowData = [];
                row.querySelectorAll('td').forEach(cell => {
                    rowData.push('"' + cell.innerText.replace(/"/g, '""') + '"');
                });
                csv.push(rowData.join(','));
            });
            
            // Download CSV file
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], {type: 'text/csv;charset=utf-8;'});
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `raport_${new Date().toISOString().split('T')[0]}.csv`;
            link.click();
        };
    });
    </script>
</body>
</html>