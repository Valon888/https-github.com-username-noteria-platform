<?php
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

// Merr të dhënat e administratorit
$admin_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT emri, mbiemri, email FROM users WHERE id = ? AND roli = 'admin'");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();

if (!$admin) {
    header("Location: logout.php");
    exit();
}

// Handle camera operations
$message = '';
$messageType = '';

// Add new camera
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_camera'])) {
    $name = trim($_POST['name']);
    $location = trim($_POST['location']);
    $ip_address = trim($_POST['ip_address']);
    $model = trim($_POST['model']);
    $zyra_id = (int) $_POST['zyra_id'];
    $resolution = trim($_POST['resolution']);
    $feed_url = trim($_POST['feed_url']);
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    if ($name && $location && $ip_address && $feed_url) {
        try {
            $stmt = $pdo->prepare("INSERT INTO security_cameras (name, location, ip_address, model, zyra_id, resolution, feed_url, username, password, notes, installation_date) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_DATE)");
            $result = $stmt->execute([$name, $location, $ip_address, $model, $zyra_id, $resolution, $feed_url, $username, $password, $notes]);
            
            if ($result) {
                $message = "Kamera u shtua me sukses!";
                $messageType = "success";
            } else {
                $message = "Ka ndodhur një gabim gjatë shtimit të kamerës.";
                $messageType = "error";
            }
        } catch (PDOException $e) {
            $message = "Gabim databaze: " . $e->getMessage();
            $messageType = "error";
        }
    } else {
        $message = "Ju lutemi plotësoni të gjitha fushat e detyrueshme!";
        $messageType = "error";
    }
}

// Delete camera
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_camera'])) {
    $camera_id = (int) $_POST['camera_id'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM security_cameras WHERE id = ?");
        $result = $stmt->execute([$camera_id]);
        
        if ($result) {
            $message = "Kamera u fshi me sukses!";
            $messageType = "success";
        } else {
            $message = "Ka ndodhur një gabim gjatë fshirjes së kamerës.";
            $messageType = "error";
        }
    } catch (PDOException $e) {
        $message = "Gabim databaze: " . $e->getMessage();
        $messageType = "error";
    }
}

// Update camera status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $camera_id = (int) $_POST['camera_id'];
    $status = $_POST['status'];
    
    try {
        $stmt = $pdo->prepare("UPDATE security_cameras SET status = ? WHERE id = ?");
        $result = $stmt->execute([$status, $camera_id]);
        
        if ($result) {
            $message = "Statusi i kamerës u përditësua me sukses!";
            $messageType = "success";
        } else {
            $message = "Ka ndodhur një gabim gjatë përditësimit të statusit.";
            $messageType = "error";
        }
    } catch (PDOException $e) {
        $message = "Gabim databaze: " . $e->getMessage();
        $messageType = "error";
    }
}

// Get all cameras
try {
    $stmt = $pdo->query("SELECT c.*, z.emri AS zyra_name 
                        FROM security_cameras c
                        LEFT JOIN zyrat z ON c.zyra_id = z.id
                        ORDER BY c.status, c.name");
    $cameras = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "Gabim gjatë marrjes së kamerave: " . $e->getMessage();
    $messageType = "error";
    $cameras = [];
}

// Get all offices for dropdown
try {
    $stmt = $pdo->query("SELECT id, emri FROM zyrat ORDER BY emri");
    $offices = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "Gabim gjatë marrjes së zyrave: " . $e->getMessage();
    $messageType = "error";
    $offices = [];
}

// Get alerts count
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM security_alerts WHERE processed = 0");
    $alerts_count = $stmt->fetchColumn();
} catch (PDOException $e) {
    $alerts_count = 0;
}

// Get active cameras count
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM security_cameras WHERE status = 'active'");
    $active_cameras = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM security_cameras WHERE status != 'active'");
    $inactive_cameras = $stmt->fetchColumn();
} catch (PDOException $e) {
    $active_cameras = 0;
    $inactive_cameras = 0;
}
?>

<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sistemi i Sigurisë | Noteria</title>
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card-icon {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 2.2rem;
            opacity: 0.1;
            color: var(--primary);
        }
        
        .stat-card-value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 8px;
            margin-top: 16px;
        }
        
        .stat-card-label {
            color: var(--gray);
            font-weight: 600;
            font-size: 0.95rem;
            text-transform: uppercase;
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
            max-height: 90vh;
            overflow-y: auto;
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
        
        .modal-title {
            margin-bottom: 24px;
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--dark);
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
        }
        
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            outline: none;
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
        
        .camera-status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .status-active {
            background: var(--secondary);
            color: white;
        }
        
        .status-inactive {
            background: var(--danger);
            color: white;
        }
        
        .status-maintenance {
            background: var(--warning);
            color: white;
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
        
        .action-icon-edit {
            background: var(--warning);
        }
        
        .action-icon-delete {
            background: var(--danger);
        }
        
        .action-icon:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        .camera-feeds {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }
        
        .camera-feed {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
        }
        
        .camera-feed-header {
            padding: 12px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border);
        }
        
        .camera-feed-title {
            font-weight: 700;
            color: var(--dark);
        }
        
        .camera-feed-video {
            width: 100%;
            height: 0;
            padding-bottom: 56.25%; /* 16:9 aspect ratio */
            position: relative;
            background: #000;
        }
        
        .camera-feed-video img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .camera-feed-footer {
            padding: 12px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid var(--border);
            background: var(--light);
        }
        
        .camera-feed-location {
            font-size: 0.9rem;
            color: var(--gray);
        }
        
        .camera-feed-actions {
            display: flex;
            gap: 8px;
        }
        
        .camera-feed-actions button {
            border: none;
            background: none;
            color: var(--primary);
            cursor: pointer;
            transition: color 0.2s;
        }
        
        .camera-feed-actions button:hover {
            color: var(--primary-dark);
        }
        
        .form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
        }
        
        .form-row .form-group {
            flex: 1 1 45%;
            min-width: 200px;
        }
        
        /* Responsive */
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
            
            .stats-grid {
                grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
                gap: 16px;
            }
            
            .stat-card-value {
                font-size: 1.5rem;
            }
            
            .stat-card-label {
                font-size: 0.8rem;
            }
            
            .camera-feeds {
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
            
            <a href="admin_security.php" class="menu-item active">
                <i class="fas fa-video"></i>
                <span>Siguria</span>
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
                <h2>Sistemi i Sigurisë</h2>
                <div class="breadcrumb">
                    <span>Noteria</span>
                    <span>Admin</span>
                    <span>Siguria</span>
                </div>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?>">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i> 
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Stats Overview -->
            <div class="stats-grid">
                <div class="stat-card">
                    <i class="fas fa-camera stat-card-icon"></i>
                    <div class="stat-card-value"><?php echo $active_cameras; ?></div>
                    <div class="stat-card-label">Kamera Aktive</div>
                </div>
                
                <div class="stat-card">
                    <i class="fas fa-exclamation-triangle stat-card-icon"></i>
                    <div class="stat-card-value"><?php echo $inactive_cameras; ?></div>
                    <div class="stat-card-label">Kamera Jo-aktive</div>
                </div>
                
                <div class="stat-card">
                    <i class="fas fa-bell stat-card-icon"></i>
                    <div class="stat-card-value"><?php echo $alerts_count; ?></div>
                    <div class="stat-card-label">Alarme të reja</div>
                </div>
                
                <div class="stat-card">
                    <i class="fas fa-building stat-card-icon"></i>
                    <div class="stat-card-value"><?php echo count($offices); ?></div>
                    <div class="stat-card-label">Zyra me Kamera</div>
                </div>
            </div>
            
            <!-- Live Camera Feeds -->
            <?php if (count($cameras) > 0): ?>
            <div class="section" id="live-feeds">
                <div class="section-header">
                    <h3 class="section-title">Kamera Live</h3>
                    <div>
                        <button class="btn btn-light" id="refreshFeedsBtn">
                            <i class="fas fa-sync"></i> Rifresko
                        </button>
                        <button class="btn btn-primary" id="viewAllBtn">
                            <i class="fas fa-expand"></i> Pamje e Plotë
                        </button>
                    </div>
                </div>
                
                <div class="camera-feeds">
                    <?php foreach ($cameras as $camera): ?>
                    <?php if ($camera['status'] === 'active'): ?>
                    <div class="camera-feed" data-camera-id="<?php echo $camera['id']; ?>">
                        <div class="camera-feed-header">
                            <div class="camera-feed-title">
                                <?php echo htmlspecialchars($camera['name']); ?>
                            </div>
                            <div class="camera-status status-<?php echo $camera['status']; ?>">
                                <?php 
                                    switch($camera['status']) {
                                        case 'active':
                                            echo 'Aktive';
                                            break;
                                        case 'inactive':
                                            echo 'Joaktive';
                                            break;
                                        case 'maintenance':
                                            echo 'Në mirëmbajtje';
                                            break;
                                    }
                                ?>
                            </div>
                        </div>
                        <div class="camera-feed-video">
                            <!-- We use placeholders instead of actual RTSP streams for this demo -->
                            <img src="https://picsum.photos/800/450?random=<?php echo $camera['id']; ?>" alt="<?php echo htmlspecialchars($camera['name']); ?>" class="camera-placeholder">
                        </div>
                        <div class="camera-feed-footer">
                            <div class="camera-feed-location">
                                <i class="fas fa-map-marker-alt"></i> 
                                <?php echo htmlspecialchars($camera['location']); ?> 
                                (<?php echo htmlspecialchars($camera['zyra_name'] ?? 'N/A'); ?>)
                            </div>
                            <div class="camera-feed-actions">
                                <button class="view-recording" data-camera-id="<?php echo $camera['id']; ?>">
                                    <i class="fas fa-history"></i>
                                </button>
                                <button class="toggle-fullscreen" data-camera-id="<?php echo $camera['id']; ?>">
                                    <i class="fas fa-expand"></i>
                                </button>
                                <button class="take-snapshot" data-camera-id="<?php echo $camera['id']; ?>">
                                    <i class="fas fa-camera"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Camera Management -->
            <div class="section" id="cameras">
                <div class="section-header">
                    <h3 class="section-title">Menaxhimi i Kamerave</h3>
                    <button class="btn btn-primary" id="addCameraBtn">
                        <i class="fas fa-plus"></i> Shto Kamerë
                    </button>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Emri</th>
                            <th>Lokacioni</th>
                            <th>IP Adresa</th>
                            <th>Modeli</th>
                            <th>Zyra</th>
                            <th>Statusi</th>
                            <th>Instaluar</th>
                            <th>Veprime</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cameras as $camera): ?>
                        <tr>
                            <td><?php echo $camera['id']; ?></td>
                            <td><?php echo htmlspecialchars($camera['name']); ?></td>
                            <td><?php echo htmlspecialchars($camera['location']); ?></td>
                            <td><?php echo htmlspecialchars($camera['ip_address']); ?></td>
                            <td><?php echo htmlspecialchars($camera['model'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($camera['zyra_name'] ?? 'N/A'); ?></td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="camera_id" value="<?php echo $camera['id']; ?>">
                                    <select name="status" onchange="this.form.submit()" class="camera-status status-<?php echo $camera['status']; ?>">
                                        <option value="active" <?php echo $camera['status'] === 'active' ? 'selected' : ''; ?>>Aktive</option>
                                        <option value="inactive" <?php echo $camera['status'] === 'inactive' ? 'selected' : ''; ?>>Joaktive</option>
                                        <option value="maintenance" <?php echo $camera['status'] === 'maintenance' ? 'selected' : ''; ?>>Në mirëmbajtje</option>
                                    </select>
                                    <input type="hidden" name="update_status" value="1">
                                </form>
                            </td>
                            <td><?php echo $camera['installation_date'] ? date('d.m.Y', strtotime($camera['installation_date'])) : 'N/A'; ?></td>
                            <td>
                                <div class="action-icons">
                                    <a href="#" class="action-icon action-icon-view view-camera" data-camera-id="<?php echo $camera['id']; ?>">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="#" class="action-icon action-icon-edit edit-camera" data-camera-id="<?php echo $camera['id']; ?>">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Jeni të sigurt që doni të fshini këtë kamerë?');">
                                        <input type="hidden" name="camera_id" value="<?php echo $camera['id']; ?>">
                                        <button type="submit" name="delete_camera" class="action-icon action-icon-delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Recent Alerts -->
            <div class="section" id="alerts">
                <div class="section-header">
                    <h3 class="section-title">Alarmet e Fundit</h3>
                    <a href="admin_security_alerts.php" class="btn btn-primary">
                        <i class="fas fa-list"></i> Të Gjitha Alarmet
                    </a>
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
                            <th>Veprime</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- This would be populated from actual alerts; here we just show sample data -->
                        <tr>
                            <td>1</td>
                            <td>Kamera hyrëse</td>
                            <td>04.10.2025 14:25</td>
                            <td>Lëvizje</td>
                            <td><span class="badge badge-warning">Mesatar</span></td>
                            <td><span class="badge badge-danger">Pa Procesuar</span></td>
                            <td>
                                <div class="action-icons">
                                    <a href="#" class="action-icon action-icon-view">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="#" class="action-icon action-icon-edit">
                                        <i class="fas fa-check"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>2</td>
                            <td>Kamera e parkimit</td>
                            <td>04.10.2025 12:15</td>
                            <td>Person</td>
                            <td><span class="badge badge-danger">I Lartë</span></td>
                            <td><span class="badge badge-danger">Pa Procesuar</span></td>
                            <td>
                                <div class="action-icons">
                                    <a href="#" class="action-icon action-icon-view">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="#" class="action-icon action-icon-edit">
                                        <i class="fas fa-check"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Add Camera Modal -->
    <div id="addCameraModal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h3 class="modal-title">Shto Kamerë të Re</h3>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="name" class="form-label">Emri i Kamerës</label>
                        <input type="text" id="name" name="name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="location" class="form-label">Lokacioni</label>
                        <input type="text" id="location" name="location" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="ip_address" class="form-label">IP Adresa</label>
                        <input type="text" id="ip_address" name="ip_address" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="model" class="form-label">Modeli</label>
                        <input type="text" id="model" name="model" class="form-control">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="zyra_id" class="form-label">Zyra</label>
                        <select id="zyra_id" name="zyra_id" class="form-control" required>
                            <option value="">Zgjidhni zyrën...</option>
                            <?php foreach ($offices as $office): ?>
                            <option value="<?php echo $office['id']; ?>"><?php echo htmlspecialchars($office['emri']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="resolution" class="form-label">Rezolucioni</label>
                        <select id="resolution" name="resolution" class="form-control">
                            <option value="720p">HD (720p)</option>
                            <option value="1080p" selected>Full HD (1080p)</option>
                            <option value="2K">2K</option>
                            <option value="4K">4K</option>
                            <option value="Other">Tjetër</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="feed_url" class="form-label">URL e Feed (RTSP/HTTP)</label>
                    <input type="text" id="feed_url" name="feed_url" class="form-control" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="username" class="form-label">Përdoruesi (opsional)</label>
                        <input type="text" id="username" name="username" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="password" class="form-label">Fjalëkalimi (opsional)</label>
                        <input type="password" id="password" name="password" class="form-control">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="notes" class="form-label">Shënime</label>
                    <textarea id="notes" name="notes" class="form-control" rows="3"></textarea>
                </div>
                
                <button type="submit" name="add_camera" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Shto Kamerë
                </button>
            </form>
        </div>
    </div>
    
    <!-- View Camera Modal -->
    <div id="viewCameraModal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h3 class="modal-title">Shiko Kamerën</h3>
            <div class="camera-details">
                <div class="camera-feed-video" style="margin-bottom: 20px;">
                    <img src="" alt="Camera Feed" id="viewCameraImage">
                </div>
                
                <div class="camera-info">
                    <h4>Informacione për Kamerën</h4>
                    <div id="cameraDetails">
                        <!-- This will be populated with JavaScript -->
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Modal functionality
            const addCameraBtn = document.getElementById('addCameraBtn');
            const addCameraModal = document.getElementById('addCameraModal');
            const viewCameraModal = document.getElementById('viewCameraModal');
            const closeButtons = document.querySelectorAll('.close-modal');
            
            addCameraBtn.addEventListener('click', function() {
                addCameraModal.style.display = 'flex';
            });
            
            closeButtons.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    addCameraModal.style.display = 'none';
                    viewCameraModal.style.display = 'none';
                });
            });
            
            window.addEventListener('click', function(event) {
                if (event.target === addCameraModal) {
                    addCameraModal.style.display = 'none';
                }
                if (event.target === viewCameraModal) {
                    viewCameraModal.style.display = 'none';
                }
            });
            
            // View camera details
            const viewCameraButtons = document.querySelectorAll('.view-camera');
            viewCameraButtons.forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const cameraId = this.getAttribute('data-camera-id');
                    
                    // Get camera data - in a real application, you would fetch this from server
                    // For this demo, we'll just get it from the table row
                    const row = this.closest('tr');
                    const cells = row.querySelectorAll('td');
                    
                    const cameraData = {
                        id: cells[0].textContent,
                        name: cells[1].textContent,
                        location: cells[2].textContent,
                        ip: cells[3].textContent,
                        model: cells[4].textContent,
                        zyra: cells[5].textContent,
                        status: cells[6].querySelector('select').value,
                        installed: cells[7].textContent
                    };
                    
                    // Update modal with camera data
                    document.getElementById('viewCameraImage').src = `https://picsum.photos/800/450?random=${cameraId}`;
                    
                    let detailsHtml = `
                        <p><strong>ID:</strong> ${cameraData.id}</p>
                        <p><strong>Emri:</strong> ${cameraData.name}</p>
                        <p><strong>Lokacioni:</strong> ${cameraData.location}</p>
                        <p><strong>IP Adresa:</strong> ${cameraData.ip}</p>
                        <p><strong>Modeli:</strong> ${cameraData.model}</p>
                        <p><strong>Zyra:</strong> ${cameraData.zyra}</p>
                        <p><strong>Statusi:</strong> ${cameraData.status === 'active' ? 'Aktive' : (cameraData.status === 'inactive' ? 'Joaktive' : 'Në mirëmbajtje')}</p>
                        <p><strong>Instaluar më:</strong> ${cameraData.installed}</p>
                    `;
                    
                    document.getElementById('cameraDetails').innerHTML = detailsHtml;
                    
                    // Show modal
                    viewCameraModal.style.display = 'flex';
                });
            });
            
            // Refresh feeds button
            const refreshFeedsBtn = document.getElementById('refreshFeedsBtn');
            if (refreshFeedsBtn) {
                refreshFeedsBtn.addEventListener('click', function() {
                    // In a real application, this would refresh the camera feeds
                    // For this demo, we'll just reload the placeholder images
                    document.querySelectorAll('.camera-placeholder').forEach(function(img) {
                        const src = img.src.split('?')[0];
                        img.src = src + '?random=' + Math.random();
                    });
                    
                    // Show a message
                    alert('Pamjet e kamerave u rifreskuan!');
                });
            }
            
            // Handle camera feed actions
            document.querySelectorAll('.camera-feed-actions button').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const action = this.classList[0];
                    const cameraId = this.getAttribute('data-camera-id');
                    
                    switch(action) {
                        case 'view-recording':
                            alert('Shiko regjistrimet e mëparshme për kamerën ' + cameraId);
                            break;
                        case 'toggle-fullscreen':
                            alert('Hap pamjen me ekran të plotë për kamerën ' + cameraId);
                            break;
                        case 'take-snapshot':
                            alert('Fotografi e marrë nga kamera ' + cameraId);
                            break;
                    }
                });
            });
            
            // View all button
            const viewAllBtn = document.getElementById('viewAllBtn');
            if (viewAllBtn) {
                viewAllBtn.addEventListener('click', function() {
                    alert('Hap panelin me të gjitha pamjet e kamerave');
                });
            }
        });
    </script>
</body>
</html>