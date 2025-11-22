<?php
session_start();
require_once 'confidb.php';

// Kontrollo nëse përdoruesi është i kyçur dhe ka rolin e duhur
if (!isset($_SESSION['user_id']) || $_SESSION['roli'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Merr të dhënat e administratorit
$admin_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT emri, mbiemri, email FROM users WHERE id = ? AND roli = 'admin'");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();

if (!$admin) {
    header("Location: logout.php");
    exit();
}

// Handle alert operations
$message = '';
$messageType = '';

// Mark alert as processed
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_alert'])) {
    $alert_id = (int) $_POST['alert_id'];
    
    try {
        $stmt = $pdo->prepare("UPDATE security_alerts SET processed = 1, processed_by = ? WHERE id = ?");
        $result = $stmt->execute([$admin_id, $alert_id]);
        
        if ($result) {
            $message = "Alarmi u shënua si i procesuar!";
            $messageType = "success";
        } else {
            $message = "Ka ndodhur një gabim gjatë procesimit të alarmit.";
            $messageType = "error";
        }
    } catch (PDOException $e) {
        $message = "Gabim databaze: " . $e->getMessage();
        $messageType = "error";
    }
}

// Delete alert
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_alert'])) {
    $alert_id = (int) $_POST['alert_id'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM security_alerts WHERE id = ?");
        $result = $stmt->execute([$alert_id]);
        
        if ($result) {
            $message = "Alarmi u fshi me sukses!";
            $messageType = "success";
        } else {
            $message = "Ka ndodhur një gabim gjatë fshirjes së alarmit.";
            $messageType = "error";
        }
    } catch (PDOException $e) {
        $message = "Gabim databaze: " . $e->getMessage();
        $messageType = "error";
    }
}

// Filter parameters
$filter_camera = isset($_GET['camera']) ? (int) $_GET['camera'] : null;
$filter_type = isset($_GET['type']) ? $_GET['type'] : null;
$filter_level = isset($_GET['level']) ? $_GET['level'] : null;
$filter_processed = isset($_GET['processed']) ? (int) $_GET['processed'] : null;

// Build query based on filters
$query = "SELECT a.*, c.name AS camera_name, u.emri, u.mbiemri
          FROM security_alerts a
          LEFT JOIN security_cameras c ON a.camera_id = c.id
          LEFT JOIN users u ON a.processed_by = u.id";

$params = [];
$conditions = [];

if ($filter_camera) {
    $conditions[] = "a.camera_id = ?";
    $params[] = $filter_camera;
}

if ($filter_type) {
    $conditions[] = "a.alert_type = ?";
    $params[] = $filter_type;
}

if ($filter_level) {
    $conditions[] = "a.alert_level = ?";
    $params[] = $filter_level;
}

if ($filter_processed !== null) {
    $conditions[] = "a.processed = ?";
    $params[] = $filter_processed;
}

if (count($conditions) > 0) {
    $query .= " WHERE " . implode(' AND ', $conditions);
}

$query .= " ORDER BY a.alert_time DESC";

// Get all alerts
try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "Gabim gjatë marrjes së alarmeve: " . $e->getMessage();
    $messageType = "error";
    $alerts = [];
}

// Get all cameras for filter dropdown
try {
    $stmt = $pdo->query("SELECT id, name FROM security_cameras ORDER BY name");
    $cameras = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $cameras = [];
}

// Generate sample alerts data (only if there's no data)
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM security_alerts");
    $alertCount = $stmt->fetchColumn();
    
    if ($alertCount == 0 && count($cameras) > 0) {
        $alertTypes = ['motion', 'person', 'vehicle', 'animal', 'offline'];
        $alertLevels = ['low', 'medium', 'high', 'critical'];
        
        // Current timestamp
        $currentTimestamp = time();
        
        // Insert 10 sample alerts from past 24 hours
        $stmt = $pdo->prepare("INSERT INTO security_alerts 
                (camera_id, alert_time, alert_type, alert_level, image_path, processed, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        for ($i = 0; $i < 10; $i++) {
            $randomCamera = $cameras[array_rand($cameras)];
            $randomType = $alertTypes[array_rand($alertTypes)];
            $randomLevel = $alertLevels[array_rand($alertLevels)];
            
            // Random timestamp from past 24 hours
            $randomTime = $currentTimestamp - rand(0, 86400); // 86400 seconds = 24 hours
            $alertTime = date('Y-m-d H:i:s', $randomTime);
            
            // 70% chance of being unprocessed
            $processed = (rand(1, 10) > 7) ? 1 : 0;
            
            // Placeholder image path
            $imagePath = 'security/alerts/alert_' . $i . '.jpg';
            
            $stmt->execute([
                $randomCamera['id'],
                $alertTime,
                $randomType,
                $randomLevel,
                $imagePath,
                $processed,
                $alertTime
            ]);
        }
        
        // Get the alerts again
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    // Ignore errors
}
?>

<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Alarmet e Sigurisë | Noteria</title>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        }
        
        .section-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--dark);
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
        
        .btn-secondary {
            background: var(--secondary);
            color: white;
        }
        
        .btn-secondary:hover {
            background: var(--secondary-dark);
        }
        
        .btn-warning {
            background: var(--warning);
            color: white;
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-light {
            background: var(--light);
            color: var(--dark);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        
        thead th {
            background: var(--light);
            text-align: left;
            padding: 12px 16px;
            font-weight: 600;
            color: var(--dark);
            border-bottom: 1px solid var(--border);
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
        
        .badge-info {
            background: rgba(45, 108, 223, 0.1);
            color: var(--primary);
        }
        
        .action-icons {
            display: flex;
            gap: 8px;
        }
        
        .action-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            color: white;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .action-icon-view {
            background: var(--primary);
        }
        
        .action-icon-process {
            background: var(--secondary);
        }
        
        .action-icon-delete {
            background: var(--danger);
        }
        
        .action-icon:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        .filter-panel {
            background: var(--light);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
        }
        
        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            align-items: flex-end;
        }
        
        .filter-group {
            flex: 1;
            min-width: 180px;
        }
        
        .filter-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .filter-select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-weight: 600;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--secondary);
        }
        
        .alert-danger {
            background: rgba(220, 38, 38, 0.1);
            color: var(--danger);
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 100;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            overflow: auto;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 32px;
            width: 100%;
            max-width: 700px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            position: relative;
        }
        
        .close-modal {
            position: absolute;
            top: 16px;
            right: 16px;
            font-size: 1.5rem;
            color: var(--gray);
            cursor: pointer;
            transition: color 0.2s;
        }
        
        .close-modal:hover {
            color: var(--dark);
        }
        
        .alert-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }
        
        .alert-image {
            width: 100%;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .alert-image img {
            width: 100%;
            height: auto;
            display: block;
        }
        
        .alert-info {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .alert-info-item {
            display: flex;
            justify-content: space-between;
            border-bottom: 1px solid var(--border);
            padding-bottom: 8px;
        }
        
        .alert-info-label {
            font-weight: 600;
            color: var(--dark);
        }
        
        .alert-info-value {
            color: var(--gray);
        }
        
        .alert-actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 80px;
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
            
            .alert-details {
                grid-template-columns: 1fr;
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
            
            <a href="admin_dashboard.php#zyrat" class="menu-item">
                <i class="fas fa-building"></i>
                <span>Zyrat Noteriale</span>
            </a>
            
            <a href="admin_dashboard.php#users" class="menu-item">
                <i class="fas fa-users"></i>
                <span>Përdoruesit</span>
            </a>
            
            <a href="admin_dashboard.php#subscriptions" class="menu-item">
                <i class="fas fa-credit-card"></i>
                <span>Abonimet</span>
            </a>
            
            <a href="admin_dashboard.php#payments" class="menu-item">
                <i class="fas fa-money-bill-wave"></i>
                <span>Pagesat</span>
            </a>
            
            <a href="admin_security.php" class="menu-item">
                <i class="fas fa-video"></i>
                <span>Siguria</span>
            </a>
            
            <a href="admin_security_alerts.php" class="menu-item active">
                <i class="fas fa-bell"></i>
                <span>Alarmet</span>
            </a>
            
            <a href="admin_dashboard.php#settings" class="menu-item">
                <i class="fas fa-cog"></i>
                <span>Cilësimet</span>
            </a>
            
            <div class="admin-info">
                <div class="admin-name"><?php echo htmlspecialchars($admin['emri'] . ' ' . $admin['mbiemri']); ?></div>
                <div class="admin-role">Administrator</div>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Shkyçu
                </a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="dashboard-header">
                <h2>Alarmet e Sigurisë</h2>
                <div class="breadcrumb">
                    <span>Noteria</span>
                    <span>Admin</span>
                    <span>Alarmet</span>
                </div>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?>">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i> 
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Filter Panel -->
            <div class="filter-panel">
                <form class="filter-form" method="GET">
                    <div class="filter-group">
                        <label class="filter-label">Kamera</label>
                        <select name="camera" class="filter-select">
                            <option value="">Të gjitha kamerat</option>
                            <?php foreach ($cameras as $camera): ?>
                                <option value="<?php echo $camera['id']; ?>" <?php echo $filter_camera == $camera['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($camera['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Lloji i alarmit</label>
                        <select name="type" class="filter-select">
                            <option value="">Të gjitha</option>
                            <option value="motion" <?php echo $filter_type === 'motion' ? 'selected' : ''; ?>>Lëvizje</option>
                            <option value="person" <?php echo $filter_type === 'person' ? 'selected' : ''; ?>>Person</option>
                            <option value="vehicle" <?php echo $filter_type === 'vehicle' ? 'selected' : ''; ?>>Automjet</option>
                            <option value="animal" <?php echo $filter_type === 'animal' ? 'selected' : ''; ?>>Kafshë</option>
                            <option value="offline" <?php echo $filter_type === 'offline' ? 'selected' : ''; ?>>Offline</option>
                            <option value="custom" <?php echo $filter_type === 'custom' ? 'selected' : ''; ?>>Tjera</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Niveli i alarmit</label>
                        <select name="level" class="filter-select">
                            <option value="">Të gjitha</option>
                            <option value="low" <?php echo $filter_level === 'low' ? 'selected' : ''; ?>>I ulët</option>
                            <option value="medium" <?php echo $filter_level === 'medium' ? 'selected' : ''; ?>>Mesatar</option>
                            <option value="high" <?php echo $filter_level === 'high' ? 'selected' : ''; ?>>I lartë</option>
                            <option value="critical" <?php echo $filter_level === 'critical' ? 'selected' : ''; ?>>Kritik</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Statusi</label>
                        <select name="processed" class="filter-select">
                            <option value="">Të gjitha</option>
                            <option value="0" <?php echo $filter_processed === 0 ? 'selected' : ''; ?>>Pa procesuar</option>
                            <option value="1" <?php echo $filter_processed === 1 ? 'selected' : ''; ?>>Të procesuara</option>
                        </select>
                    </div>
                    
                    <div class="filter-group" style="flex: 0 0 auto;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Filtro
                        </button>
                        <a href="admin_security_alerts.php" class="btn btn-light">
                            <i class="fas fa-redo"></i> Reseto
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Alerts List -->
            <div class="section">
                <div class="section-header">
                    <h3 class="section-title">Lista e Alarmeve</h3>
                    <span><?php echo count($alerts); ?> alarme</span>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Kamera</th>
                            <th>Koha</th>
                            <th>Lloji</th>
                            <th>Niveli</th>
                            <th>Statusi</th>
                            <th>Procesuar nga</th>
                            <th>Veprime</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($alerts) > 0): ?>
                            <?php foreach ($alerts as $alert): ?>
                                <tr>
                                    <td><?php echo $alert['id']; ?></td>
                                    <td><?php echo htmlspecialchars($alert['camera_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo date('d.m.Y H:i', strtotime($alert['alert_time'])); ?></td>
                                    <td>
                                        <?php 
                                            $alertTypeClass = '';
                                            $alertTypeName = '';
                                            
                                            switch($alert['alert_type']) {
                                                case 'motion':
                                                    $alertTypeClass = 'badge-info';
                                                    $alertTypeName = 'Lëvizje';
                                                    break;
                                                case 'person':
                                                    $alertTypeClass = 'badge-warning';
                                                    $alertTypeName = 'Person';
                                                    break;
                                                case 'vehicle':
                                                    $alertTypeClass = 'badge-info';
                                                    $alertTypeName = 'Automjet';
                                                    break;
                                                case 'animal':
                                                    $alertTypeClass = 'badge-info';
                                                    $alertTypeName = 'Kafshë';
                                                    break;
                                                case 'offline':
                                                    $alertTypeClass = 'badge-danger';
                                                    $alertTypeName = 'Offline';
                                                    break;
                                                default:
                                                    $alertTypeClass = 'badge-info';
                                                    $alertTypeName = 'Tjetër';
                                            }
                                        ?>
                                        <span class="badge <?php echo $alertTypeClass; ?>"><?php echo $alertTypeName; ?></span>
                                    </td>
                                    <td>
                                        <?php 
                                            $alertLevelClass = '';
                                            $alertLevelName = '';
                                            
                                            switch($alert['alert_level']) {
                                                case 'low':
                                                    $alertLevelClass = 'badge-info';
                                                    $alertLevelName = 'I ulët';
                                                    break;
                                                case 'medium':
                                                    $alertLevelClass = 'badge-warning';
                                                    $alertLevelName = 'Mesatar';
                                                    break;
                                                case 'high':
                                                    $alertLevelClass = 'badge-danger';
                                                    $alertLevelName = 'I lartë';
                                                    break;
                                                case 'critical':
                                                    $alertLevelClass = 'badge-danger';
                                                    $alertLevelName = 'Kritik';
                                                    break;
                                            }
                                        ?>
                                        <span class="badge <?php echo $alertLevelClass; ?>"><?php echo $alertLevelName; ?></span>
                                    </td>
                                    <td>
                                        <?php if ($alert['processed']): ?>
                                            <span class="badge badge-success">Procesuar</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Pa procesuar</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                            if ($alert['processed'] && isset($alert['emri'])) {
                                                echo htmlspecialchars($alert['emri'] . ' ' . $alert['mbiemri']);
                                            } else {
                                                echo 'N/A';
                                            }
                                        ?>
                                    </td>
                                    <td>
                                        <div class="action-icons">
                                            <a href="#" class="action-icon action-icon-view view-alert" data-alert-id="<?php echo $alert['id']; ?>">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            <?php if (!$alert['processed']): ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="alert_id" value="<?php echo $alert['id']; ?>">
                                                    <button type="submit" name="process_alert" class="action-icon action-icon-process">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Jeni të sigurt që doni të fshini këtë alarm?');">
                                                <input type="hidden" name="alert_id" value="<?php echo $alert['id']; ?>">
                                                <button type="submit" name="delete_alert" class="action-icon action-icon-delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 30px;">Nuk u gjet asnjë alarm.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- View Alert Modal -->
    <div id="viewAlertModal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h3 class="modal-title">Detajet e Alarmit</h3>
            
            <div class="alert-details">
                <div class="alert-image">
                    <img src="" alt="Alert Image" id="alertImage">
                </div>
                
                <div class="alert-info">
                    <!-- This will be populated with JavaScript -->
                    <div id="alertInfo"></div>
                    
                    <div class="alert-actions">
                        <button id="processAlertBtn" class="btn btn-secondary">
                            <i class="fas fa-check"></i> Shëno si të procesuar
                        </button>
                        <button id="deleteAlertBtn" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Fshi alarmin
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Modal functionality
            const viewAlertModal = document.getElementById('viewAlertModal');
            const closeButtons = document.querySelectorAll('.close-modal');
            
            closeButtons.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    viewAlertModal.style.display = 'none';
                });
            });
            
            window.addEventListener('click', function(event) {
                if (event.target === viewAlertModal) {
                    viewAlertModal.style.display = 'none';
                }
            });
            
            // View alert details
            const viewAlertButtons = document.querySelectorAll('.view-alert');
            const processAlertBtn = document.getElementById('processAlertBtn');
            const deleteAlertBtn = document.getElementById('deleteAlertBtn');
            
            viewAlertButtons.forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const alertId = this.getAttribute('data-alert-id');
                    
                    // Get alert data - in a real application, you would fetch this from server
                    // For this demo, we'll just get it from the table row
                    const row = this.closest('tr');
                    const cells = row.querySelectorAll('td');
                    
                    const alertData = {
                        id: cells[0].textContent,
                        camera: cells[1].textContent,
                        time: cells[2].textContent,
                        type: cells[3].querySelector('.badge').textContent,
                        level: cells[4].querySelector('.badge').textContent,
                        processed: cells[5].querySelector('.badge').textContent === 'Procesuar',
                        processedBy: cells[6].textContent
                    };
                    
                    // Update modal with alert data
                    document.getElementById('alertImage').src = `https://picsum.photos/800/600?random=${alertId}`;
                    
                    let infoHtml = `
                        <div class="alert-info-item">
                            <div class="alert-info-label">ID:</div>
                            <div class="alert-info-value">${alertData.id}</div>
                        </div>
                        <div class="alert-info-item">
                            <div class="alert-info-label">Kamera:</div>
                            <div class="alert-info-value">${alertData.camera}</div>
                        </div>
                        <div class="alert-info-item">
                            <div class="alert-info-label">Koha e alarmit:</div>
                            <div class="alert-info-value">${alertData.time}</div>
                        </div>
                        <div class="alert-info-item">
                            <div class="alert-info-label">Lloji i alarmit:</div>
                            <div class="alert-info-value">${alertData.type}</div>
                        </div>
                        <div class="alert-info-item">
                            <div class="alert-info-label">Niveli i alarmit:</div>
                            <div class="alert-info-value">${alertData.level}</div>
                        </div>
                        <div class="alert-info-item">
                            <div class="alert-info-label">Statusi:</div>
                            <div class="alert-info-value">${alertData.processed ? 'Procesuar' : 'Pa procesuar'}</div>
                        </div>
                    `;
                    
                    if (alertData.processed && alertData.processedBy.trim() !== 'N/A') {
                        infoHtml += `
                            <div class="alert-info-item">
                                <div class="alert-info-label">Procesuar nga:</div>
                                <div class="alert-info-value">${alertData.processedBy}</div>
                            </div>
                        `;
                    }
                    
                    document.getElementById('alertInfo').innerHTML = infoHtml;
                    
                    // Show/hide process button based on alert status
                    if (alertData.processed) {
                        processAlertBtn.style.display = 'none';
                    } else {
                        processAlertBtn.style.display = 'inline-flex';
                        // Update form action
                        processAlertBtn.onclick = function() {
                            const form = document.createElement('form');
                            form.method = 'POST';
                            form.innerHTML = `<input type="hidden" name="alert_id" value="${alertData.id}"><input type="hidden" name="process_alert" value="1">`;
                            document.body.appendChild(form);
                            form.submit();
                        };
                    }
                    
                    // Update delete button action
                    deleteAlertBtn.onclick = function() {
                        if (confirm('Jeni të sigurt që doni të fshini këtë alarm?')) {
                            const form = document.createElement('form');
                            form.method = 'POST';
                            form.innerHTML = `<input type="hidden" name="alert_id" value="${alertData.id}"><input type="hidden" name="delete_alert" value="1">`;
                            document.body.appendChild(form);
                            form.submit();
                        }
                    };
                    
                    // Show modal
                    viewAlertModal.style.display = 'flex';
                });
            });
        });
    </script>
</body>
</html>