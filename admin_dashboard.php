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

// Merr të dhënat e administratorit
$admin_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT emri, mbiemri, email FROM users WHERE id = ? AND roli = 'admin'");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();

if (!$admin) {
    header("Location: logout.php");
    exit();
}

// Statistikat dhe përmbledhja
$stats = [
    'total_users' => 0,
    'total_zyra' => 0,
    'total_reservations' => 0,
    'total_payments' => 0,
    'revenue_month' => 0,
    'subscription_active' => 0,
    'subscription_expired' => 0
];

// Numri i përgjithshëm i përdoruesve
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE roli = 'user'");
$stats['total_users'] = $stmt->fetchColumn();

// Numri i zyrave noteriale
$stmt = $pdo->query("SELECT COUNT(*) FROM zyrat");
$stats['total_zyra'] = $stmt->fetchColumn();

// Numri i rezervimeve
$stmt = $pdo->query("SELECT COUNT(*) FROM reservations");
$stats['total_reservations'] = $stmt->fetchColumn();

// Numri i pagesave
$stmt = $pdo->query("SELECT COUNT(*) FROM payments");
$stats['total_payments'] = $stmt->fetchColumn();

// Të ardhurat e muajit aktual
$stmt = $pdo->query("SELECT SUM(amount) FROM payments WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
$stats['revenue_month'] = $stmt->fetchColumn() ?: 0;

// Abonimet aktive dhe të skaduara
$stmt = $pdo->query("SELECT COUNT(*) FROM subscription WHERE status = 'active' AND expiry_date >= CURRENT_DATE()");
$stats['subscription_active'] = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM subscription WHERE status = 'expired' OR expiry_date < CURRENT_DATE()");
$stats['subscription_expired'] = $stmt->fetchColumn();

// Menaxhimi i zyrave noteriale
$zyrat = [];
$stmt = $pdo->query("SELECT z.*, COUNT(u.id) as users_count 
                     FROM zyrat z 
                     LEFT JOIN users u ON u.zyra_id = z.id 
                     GROUP BY z.id
                     ORDER BY z.emri");
$zyrat = $stmt->fetchAll();

// Menaxhimi i përdoruesve
$error = $success = '';

// Fshi zyrë
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_zyra'])) {
    $zyra_id = $_POST['zyra_id'] ?? 0;
    
    if ($zyra_id) {
        // Kontrollo nëse ka përdorues të lidhur
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE zyra_id = ?");
        $stmt->execute([$zyra_id]);
        $has_users = $stmt->fetchColumn() > 0;
        
        if ($has_users) {
            $error = "Nuk mund të fshihet zyra sepse ka përdorues të lidhur me të!";
        } else {
            $stmt = $pdo->prepare("DELETE FROM zyrat WHERE id = ?");
            if ($stmt->execute([$zyra_id])) {
                $success = "Zyra u fshi me sukses!";
                // Refresh listën e zyrave
                $stmt = $pdo->query("SELECT z.*, COUNT(u.id) as users_count 
                                     FROM zyrat z 
                                     LEFT JOIN users u ON u.zyra_id = z.id 
                                     GROUP BY z.id
                                     ORDER BY z.emri");
                $zyrat = $stmt->fetchAll();
            } else {
                $error = "Gabim gjatë fshirjes së zyrës.";
            }
        }
    } else {
        $error = "ID e zyrës nuk është e vlefshme!";
    }
}

// Aktivizo/deaktivizo përdorues
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_user_status'])) {
    $user_id = $_POST['user_id'] ?? 0;
    $new_status = $_POST['new_status'] === '1' ? 1 : 0;
    
    if ($user_id) {
        $stmt = $pdo->prepare("UPDATE users SET aktiv = ? WHERE id = ?");
        if ($stmt->execute([$new_status, $user_id])) {
            $success = "Statusi i përdoruesit u ndryshua me sukses!";
        } else {
            $error = "Gabim gjatë ndryshimit të statusit.";
        }
    } else {
        $error = "ID e përdoruesit nuk është e vlefshme!";
    }
}
?>

<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Paneli i Administratorit | Noteria</title>
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
            max-width: 500px;
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
        
        .subscription-status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .subscription-active {
            background: var(--secondary);
            color: white;
        }
        
        .subscription-expired {
            background: var(--danger);
            color: white;
        }
        
        .subscription-pending {
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
        
        .action-icon-edit {
            background: var(--primary);
        }
        
        .action-icon-delete {
            background: var(--danger);
        }
        
        .action-icon:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        .user-status-toggle {
            cursor: pointer;
        }
        
        .chart-container {
            height: 300px;
            margin-top: 24px;
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
            
            <a href="#dashboard" class="menu-item active">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            
            <a href="#zyrat" class="menu-item">
                <i class="fas fa-building"></i>
                <span>Zyrat Noteriale</span>
            </a>
            
            <a href="#users" class="menu-item">
                <i class="fas fa-users"></i>
                <span>Përdoruesit</span>
            </a>
            
            <a href="#subscriptions" class="menu-item">
                <i class="fas fa-credit-card"></i>
                <span>Abonimet</span>
            </a>
            
            <a href="#payments" class="menu-item">
                <i class="fas fa-money-bill-wave"></i>
                <span>Pagesat</span>
            </a>
            
            <a href="#settings" class="menu-item">
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
                <h2>Paneli i Kontrollit</h2>
                <div class="breadcrumb">
                    <span>Noteria</span>
                    <span>Admin</span>
                    <span>Dashboard</span>
                </div>
            </div>
            
            <!-- Success/Error messages -->
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <!-- Stats Overview -->
            <div class="stats-grid" id="dashboard">
                <div class="stat-card">
                    <i class="fas fa-users stat-card-icon"></i>
                    <div class="stat-card-value"><?php echo number_format($stats['total_users']); ?></div>
                    <div class="stat-card-label">Përdorues</div>
                </div>
                
                <div class="stat-card">
                    <i class="fas fa-building stat-card-icon"></i>
                    <div class="stat-card-value"><?php echo number_format($stats['total_zyra']); ?></div>
                    <div class="stat-card-label">Zyra Noteriale</div>
                </div>
                
                <div class="stat-card">
                    <i class="fas fa-calendar-check stat-card-icon"></i>
                    <div class="stat-card-value"><?php echo number_format($stats['total_reservations']); ?></div>
                    <div class="stat-card-label">Rezervime</div>
                </div>
                
                <div class="stat-card">
                    <i class="fas fa-money-bill-wave stat-card-icon"></i>
                    <div class="stat-card-value"><?php echo number_format($stats['revenue_month'], 2); ?>€</div>
                    <div class="stat-card-label">Të Ardhurat (Muaji)</div>
                </div>
            </div>
            
            <!-- Zyrat Noteriale Section -->
            <div class="section" id="zyrat">
                <div class="section-header">
                    <h3 class="section-title">Zyrat Noteriale</h3>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Emri</th>
                            <th>Adresa</th>
                            <th>Telefoni</th>
                            <th>Email</th>
                            <th>Përdorues</th>
                            <th>Veprime</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($zyrat as $zyra): ?>
                        <tr>
                            <td><?php echo $zyra['id']; ?></td>
                            <td><?php echo htmlspecialchars($zyra['emri']); ?></td>
                            <td><?php echo htmlspecialchars($zyra['adresa']); ?></td>
                            <td><?php echo htmlspecialchars($zyra['telefoni']); ?></td>
                            <td><?php echo htmlspecialchars($zyra['email']); ?></td>
                            <td><?php echo $zyra['users_count']; ?></td>
                            <td>
                                <div class="action-icons">
                                    <a href="edit_zyra.php?id=<?php echo $zyra['id']; ?>" class="action-icon action-icon-edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Jeni të sigurt që doni të fshini këtë zyrë?');">
                                        <input type="hidden" name="zyra_id" value="<?php echo $zyra['id']; ?>">
                                        <button type="submit" name="delete_zyra" class="action-icon action-icon-delete">
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
            
            <!-- Përdoruesit Section -->
            <div class="section" id="users">
                <div class="section-header">
                    <h3 class="section-title">Përdoruesit</h3>
                    <div>
                        <button class="btn btn-light filter-btn active" data-role="all">Të Gjithë</button>
                        <button class="btn btn-light filter-btn" data-role="user">Përdoruesit</button>
                        <button class="btn btn-light filter-btn" data-role="zyra">Zyrat</button>
                        <button class="btn btn-light filter-btn" data-role="admin">Administratorët</button>
                    </div>
                </div>
                
                <table id="usersTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Emri</th>
                            <th>Email</th>
                            <th>Roli</th>
                            <th>Zyra</th>
                            <th>Statusi</th>
                            <th>Regjistruar</th>
                            <th>Veprime</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $pdo->query("SELECT u.id, u.emri, u.mbiemri, u.email, u.roli, u.aktiv, u.created_at, z.emri AS zyra_emri 
                                           FROM users u 
                                           LEFT JOIN zyrat z ON u.zyra_id = z.id 
                                           ORDER BY u.id DESC");
                        while ($user = $stmt->fetch()):
                        ?>
                        <tr data-role="<?php echo $user['roli']; ?>">
                            <td><?php echo $user['id']; ?></td>
                            <td><?php echo htmlspecialchars($user['emri'] . ' ' . $user['mbiemri']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <?php if ($user['roli'] === 'admin'): ?>
                                    <span class="badge badge-danger">Admin</span>
                                <?php elseif ($user['roli'] === 'zyra'): ?>
                                    <span class="badge badge-warning">Zyrë</span>
                                <?php else: ?>
                                    <span class="badge badge-success">Përdorues</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $user['zyra_emri'] ? htmlspecialchars($user['zyra_emri']) : 'N/A'; ?></td>
                            <td>
                                <?php if ($user['id'] != $admin_id): // Prevent disabling own account ?>
                                <form method="POST" class="user-status-form">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <input type="hidden" name="new_status" value="<?php echo $user['aktiv'] ? '0' : '1'; ?>">
                                    <button type="submit" name="toggle_user_status" class="user-status-toggle badge <?php echo $user['aktiv'] ? 'badge-success' : 'badge-danger'; ?>">
                                        <?php echo $user['aktiv'] ? 'Aktiv' : 'Joaktiv'; ?>
                                    </button>
                                </form>
                                <?php else: ?>
                                    <span class="badge badge-success">Aktiv</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('d.m.Y', strtotime($user['created_at'])); ?></td>
                            <td>
                                <div class="action-icons">
                                    <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="action-icon action-icon-edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if ($user['id'] != $admin_id): // Prevent deleting own account ?>
                                    <a href="delete_user.php?id=<?php echo $user['id']; ?>" class="action-icon action-icon-delete" onclick="return confirm('Jeni të sigurt që doni të fshini këtë përdorues?');">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Abonimet Section -->
            <div class="section" id="subscriptions">
                <div class="section-header">
                    <h3 class="section-title">Abonimet</h3>
                    <button class="btn btn-primary" id="addSubscriptionBtn">
                        <i class="fas fa-plus"></i> Shto Abonim
                    </button>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>Zyra</th>
                            <th>Data e Fillimit</th>
                            <th>Data e Mbarimit</th>
                            <th>Statusi</th>
                            <th>Pagesa</th>
                            <th>Shuma</th>
                            <th>Veprime</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $pdo->query("SELECT s.*, z.emri AS zyra_emri 
                                           FROM subscription s 
                                           JOIN zyrat z ON s.zyra_id = z.id 
                                           ORDER BY s.expiry_date DESC");
                        while ($sub = $stmt->fetch()):
                            // Calculate subscription status
                            $status = 'expired';
                            $statusClass = 'subscription-expired';
                            
                            if ($sub['status'] === 'active' && strtotime($sub['expiry_date']) >= time()) {
                                $status = 'active';
                                $statusClass = 'subscription-active';
                            } elseif ($sub['payment_status'] === 'pending') {
                                $status = 'pending';
                                $statusClass = 'subscription-pending';
                            }
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($sub['zyra_emri']); ?></td>
                            <td><?php echo date('d.m.Y', strtotime($sub['start_date'])); ?></td>
                            <td><?php echo date('d.m.Y', strtotime($sub['expiry_date'])); ?></td>
                            <td><span class="<?php echo $statusClass; ?>"><?php echo ucfirst($status); ?></span></td>
                            <td><?php echo ucfirst($sub['payment_status']); ?></td>
                            <td><?php echo isset($sub['amount']) ? number_format($sub['amount'], 2) : "0.00"; ?>€</td>
                            <td>
                                <div class="action-icons">
                                    <a href="edit_subscription.php?id=<?php echo $sub['id']; ?>" class="action-icon action-icon-edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="#" class="action-icon action-icon-delete" onclick="return confirm('Jeni të sigurt që doni të fshini këtë abonim?');">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagesat Section -->
            <div class="section" id="payments">
                <div class="section-header">
                    <h3 class="section-title">Pagesat</h3>
                    <div>
                        <button class="btn btn-light filter-btn active" data-period="all">Të Gjitha</button>
                        <button class="btn btn-light filter-btn" data-period="month">Këtë Muaj</button>
                        <button class="btn btn-light filter-btn" data-period="week">Këtë Javë</button>
                    </div>
                </div>
                
                <table id="paymentsTable">
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
                        <?php
                        $stmt = $pdo->query("SELECT p.id, p.reservation_id, p.client_name, p.amount, p.created_at, p.status, 
                                           u.emri, u.mbiemri, u.email,
                                           z.emri AS zyra_emri, r.service
                                           FROM payments p
                                           JOIN reservations r ON p.reservation_id = r.id
                                           JOIN users u ON r.user_id = u.id
                                           JOIN zyrat z ON r.zyra_id = z.id
                                           ORDER BY p.created_at DESC
                                           LIMIT 100");
                        while ($payment = $stmt->fetch()):
                            $createdDate = new DateTime($payment['created_at']);
                            $now = new DateTime();
                            $interval = $createdDate->diff($now);
                            $daysAgo = $interval->days;
                            
                            $period = 'older';
                            if ($daysAgo < 7) {
                                $period = 'week';
                            } elseif ($daysAgo < 30) {
                                $period = 'month';
                            }
                        ?>
                        <tr data-period="<?php echo $period; ?>">
                            <td><?php echo $payment['id']; ?></td>
                            <td><?php echo htmlspecialchars($payment['emri'] . ' ' . $payment['mbiemri']); ?></td>
                            <td><?php echo htmlspecialchars($payment['service'] ?? $payment['client_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($payment['zyra_emri']); ?></td>
                            <td><?php echo number_format($payment['amount'], 2); ?>€</td>
                            <td><?php echo date('d.m.Y H:i', strtotime($payment['created_at'])); ?></td>
                            <td>
                                <?php if ($payment['status'] === 'completed'): ?>
                                <span class="badge badge-success">Kompletuar</span>
                                <?php elseif ($payment['status'] === 'pending'): ?>
                                <span class="badge badge-warning">Në Pritje</span>
                                <?php else: ?>
                                <span class="badge badge-danger">Dështuar</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Add Subscription Modal -->
    <div id="addSubscriptionModal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h3 class="modal-title">Shto Abonim të Ri</h3>
            <form method="POST" action="add_subscription.php">
                <div class="form-group">
                    <label for="zyra_id" class="form-label">Zyra</label>
                    <select id="zyra_id" name="zyra_id" class="form-control" required>
                        <option value="">Zgjidhni një zyrë...</option>
                        <?php foreach ($zyrat as $zyra): ?>
                        <option value="<?php echo $zyra['id']; ?>"><?php echo htmlspecialchars($zyra['emri']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="start_date" class="form-label">Data e Fillimit</label>
                    <input type="date" id="start_date" name="start_date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="expiry_date" class="form-label">Data e Mbarimit</label>
                    <input type="date" id="expiry_date" name="expiry_date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="amount" class="form-label">Shuma (€)</label>
                    <input type="number" id="amount" name="amount" step="0.01" value="150.00" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="status" class="form-label">Statusi</label>
                    <select id="status" name="status" class="form-control" required>
                        <option value="active">Aktiv</option>
                        <option value="expired">Skaduar</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="payment_status" class="form-label">Statusi i Pagesës</label>
                    <select id="payment_status" name="payment_status" class="form-control" required>
                        <option value="paid">Paguar</option>
                        <option value="pending">Në Pritje</option>
                        <option value="failed">Dështuar</option>
                    </select>
                </div>
                <button type="submit" name="add_subscription" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Shto Abonim
                </button>
            </form>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Modal functionality
            const addSubscriptionBtn = document.getElementById('addSubscriptionBtn');
            const addSubscriptionModal = document.getElementById('addSubscriptionModal');
            const closeButtons = document.querySelectorAll('.close-modal');
            
            addSubscriptionBtn.addEventListener('click', function() {
                addSubscriptionModal.style.display = 'flex';
            });
            
            closeButtons.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    addSubscriptionModal.style.display = 'none';
                });
            });
            
            window.addEventListener('click', function(event) {
                if (event.target === addSubscriptionModal) {
                    addSubscriptionModal.style.display = 'none';
                }
            });
            
            // Users table filtering
            const userFilterBtns = document.querySelectorAll('#users .filter-btn');
            const userRows = document.querySelectorAll('#usersTable tbody tr');
            
            userFilterBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const role = this.getAttribute('data-role');
                    
                    userFilterBtns.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');
                    
                    userRows.forEach(row => {
                        if (role === 'all' || row.getAttribute('data-role') === role) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                });
            });
            
            // Payments table filtering
            const paymentFilterBtns = document.querySelectorAll('#payments .filter-btn');
            const paymentRows = document.querySelectorAll('#paymentsTable tbody tr');
            
            paymentFilterBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const period = this.getAttribute('data-period');
                    
                    paymentFilterBtns.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');
                    
                    paymentRows.forEach(row => {
                        if (period === 'all' || row.getAttribute('data-period') === period) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                });
            });
            
            // Menu item highlighting
            const menuItems = document.querySelectorAll('.menu-item');
            const sections = document.querySelectorAll('.section');
            
            function setActiveMenuItem() {
                let current = '';
                
                sections.forEach(section => {
                    const sectionTop = section.offsetTop;
                    const sectionHeight = section.clientHeight;
                    
                    if (window.pageYOffset >= sectionTop - 200) {
                        current = section.getAttribute('id');
                    }
                });
                
                menuItems.forEach(item => {
                    item.classList.remove('active');
                    if (current && item.getAttribute('href') === '#' + current) {
                        item.classList.add('active');
                    }
                });
            }
            
            window.addEventListener('scroll', setActiveMenuItem);
            
            // Smooth scroll for menu items
            menuItems.forEach(item => {
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const targetId = this.getAttribute('href').substring(1);
                    const targetSection = document.getElementById(targetId);
                    
                    if (targetSection) {
                        window.scrollTo({
                            top: targetSection.offsetTop - 20,
                            behavior: 'smooth'
                        });
                    }
                });
            });
            
            // Set min date for date inputs to today
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('start_date').min = today;
            document.getElementById('expiry_date').min = today;
            
            // Auto calculate expiry date (30 days after start date)
            document.getElementById('start_date').addEventListener('change', function() {
                const startDate = new Date(this.value);
                const expiryDate = new Date(startDate);
                expiryDate.setDate(startDate.getDate() + 30);
                
                document.getElementById('expiry_date').value = expiryDate.toISOString().split('T')[0];
            });
        });
    </script>
</body>
</html>