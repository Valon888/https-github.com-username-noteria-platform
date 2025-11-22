<?php
// Konfigurimi i raportimit të gabimeve
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

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

// Parametrat për veprimet
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;
$error = $success = '';

// Merr të gjithë shfrytëzuesit
$users = [];
$stmt = $pdo->query("SELECT u.id, u.emri, u.mbiemri, u.email, u.roli, u.aktiv, u.created_at, z.emri AS zyra_emri 
                     FROM users u 
                     LEFT JOIN zyrat z ON u.zyra_id = z.id 
                     ORDER BY u.created_at DESC");
$users = $stmt->fetchAll();

// Menaxhimi i filtrimit të përdoruesve
$filter_role = $_GET['role'] ?? 'all';
$filter_status = $_GET['status'] ?? 'all';
$search_term = $_GET['search'] ?? '';

// Fshirja e përdoruesit
if ($action === 'delete' && $id > 0) {
    // Sigurohemi që nuk po fshijmë veten
    if ($id == $_SESSION['user_id']) {
        $error = "Nuk mund të fshini llogarinë tuaj!";
    } else {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        if ($stmt->execute([$id])) {
            $success = "Përdoruesi u fshi me sukses!";
            // Rifreskoj listën e përdoruesve
            $stmt = $pdo->query("SELECT u.id, u.emri, u.mbiemri, u.email, u.roli, u.aktiv, u.created_at, z.emri AS zyra_emri 
                               FROM users u 
                               LEFT JOIN zyrat z ON u.zyra_id = z.id 
                               ORDER BY u.created_at DESC");
            $users = $stmt->fetchAll();
        } else {
            $error = "Gabim gjatë fshirjes së përdoruesit!";
        }
    }
}

// Aktivizimi/Deaktivizimi i përdoruesit
if ($action === 'toggle_status' && $id > 0) {
    // Sigurohemi që nuk po modifikojmë veten
    if ($id == $_SESSION['user_id']) {
        $error = "Nuk mund të ndryshoni statusin e llogarisë tuaj!";
    } else {
        $stmt = $pdo->prepare("SELECT aktiv FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $current_status = $stmt->fetchColumn();
        
        $new_status = $current_status ? 0 : 1;
        
        $stmt = $pdo->prepare("UPDATE users SET aktiv = ?, busy = ? WHERE id = ?");
        if ($stmt->execute([$new_status, $new_status, $id])) {
            $status_text = $new_status ? "aktivizua" : "u çaktivizua";
            $success = "Përdoruesi u " . $status_text . " me sukses!";
            // Rifreskoj listën e përdoruesve
            $stmt = $pdo->query("SELECT u.id, u.emri, u.mbiemri, u.email, u.roli, u.aktiv, u.created_at, z.emri AS zyra_emri 
                               FROM users u 
                               LEFT JOIN zyrat z ON u.zyra_id = z.id 
                               ORDER BY u.created_at DESC");
            $users = $stmt->fetchAll();
        } else {
            $error = "Gabim gjatë ndryshimit të statusit të përdoruesit!";
        }
    }
}

// Shto përdorues të ri
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $emri = trim($_POST['emri'] ?? '');
    $mbiemri = trim($_POST['mbiemri'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $roli = $_POST['roli'] ?? 'user';
    $zyra_id = $_POST['zyra_id'] ?? null;
    
    if ($emri && $mbiemri && $email && $password) {
        // Kontrollo nëse ekziston email
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            $error = "Ky email është i regjistruar tashmë!";
        } else {
            // Kripto fjalëkalimin
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Shto përdoruesin
            $stmt = $pdo->prepare("INSERT INTO users (emri, mbiemri, email, password, roli, zyra_id, aktiv, busy) VALUES (?, ?, ?, ?, ?, ?, 1, 1)");
            if ($stmt->execute([$emri, $mbiemri, $email, $hashed_password, $roli, $zyra_id])) {
                $success = "Përdoruesi u shtua me sukses!";
                // Rifreskoj listën e përdoruesve
                $stmt = $pdo->query("SELECT u.id, u.emri, u.mbiemri, u.email, u.roli, u.aktiv, u.created_at, z.emri AS zyra_emri 
                                   FROM users u 
                                   LEFT JOIN zyrat z ON u.zyra_id = z.id 
                                   ORDER BY u.created_at DESC");
                $users = $stmt->fetchAll();
            } else {
                $error = "Gabim gjatë shtimit të përdoruesit!";
            }
        }
    } else {
        $error = "Ju lutemi plotësoni të gjitha fushat e detyrueshme!";
    }
}

// Ndrysho rolin e përdoruesit
if ($action === 'change_role' && $id > 0 && isset($_GET['new_role'])) {
    $new_role = $_GET['new_role'];
    $valid_roles = ['admin', 'zyra', 'user'];
    
    // Sigurohemi që nuk po ndryshojmë rolin e vetes
    if ($id == $_SESSION['user_id']) {
        $error = "Nuk mund të ndryshoni rolin e llogarisë tuaj!";
    } elseif (!in_array($new_role, $valid_roles)) {
        $error = "Roli i zgjedhur nuk është i vlefshëm!";
    } else {
        $stmt = $pdo->prepare("UPDATE users SET roli = ? WHERE id = ?");
        if ($stmt->execute([$new_role, $id])) {
            $success = "Roli i përdoruesit u ndryshua në '$new_role' me sukses!";
            // Rifreskoj listën e përdoruesve
            $stmt = $pdo->query("SELECT u.id, u.emri, u.mbiemri, u.email, u.roli, u.aktiv, u.created_at, z.emri AS zyra_emri 
                               FROM users u 
                               LEFT JOIN zyrat z ON u.zyra_id = z.id 
                               ORDER BY u.created_at DESC");
            $users = $stmt->fetchAll();
        } else {
            $error = "Gabim gjatë ndryshimit të rolit të përdoruesit!";
        }
    }
}

// Merr listën e zyrave për zgjedhjen e zyra_id
$zyrat = $pdo->query("SELECT id, emri FROM zyrat ORDER BY emri")->fetchAll();

// Filtro përdoruesit sipas kritereve të zgjedhura
$filtered_users = array_filter($users, function($user) use ($filter_role, $filter_status, $search_term) {
    $role_match = $filter_role === 'all' || $user['roli'] === $filter_role;
    $status_match = $filter_status === 'all' || 
                   ($filter_status === 'active' && $user['aktiv'] == 1) || 
                   ($filter_status === 'inactive' && $user['aktiv'] == 0);
    
    $search_match = empty($search_term) || 
                   stripos($user['emri'], $search_term) !== false || 
                   stripos($user['mbiemri'], $search_term) !== false || 
                   stripos($user['email'], $search_term) !== false;
    
    return $role_match && $status_match && $search_match;
});
?>

<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Menaxhimi i Përdoruesve | Admin Panel | Noteria</title>
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
        
        .filter-bar {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }
        
        .search-bar {
            flex: 1;
            display: flex;
            position: relative;
            min-width: 200px;
        }
        
        .search-bar input {
            flex: 1;
            padding: 10px 12px 10px 40px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 0.95rem;
        }
        
        .search-bar i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
        }
        
        .filter-dropdown {
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 0.95rem;
            background: white;
            color: var(--dark);
        }
        
        .btn {
            padding: 10px 16px;
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
        
        .btn-success {
            background: var(--secondary);
            color: white;
        }
        
        .btn-success:hover {
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
        
        .table-responsive {
            overflow-x: auto;
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
        
        .badge-primary {
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
            font-size: 0.95rem;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 12px;
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
        
        .form-text {
            margin-top: 4px;
            font-size: 0.85rem;
            color: var(--gray);
        }
        
        .role-selector {
            display: flex;
            gap: 8px;
            margin-top: 8px;
        }
        
        .role-option {
            flex: 1;
            text-align: center;
            padding: 8px;
            border: 1px solid var(--border);
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .role-option.active {
            background: rgba(45, 108, 223, 0.1);
            border-color: var(--primary);
        }
        
        .role-option:hover:not(.active) {
            background: var(--light);
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
        
        .pagination {
            display: flex;
            gap: 8px;
            margin-top: 24px;
            justify-content: center;
        }
        
        .pagination-item {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 6px;
            background: white;
            color: var(--gray);
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
            border: 1px solid var(--border);
        }
        
        .pagination-item.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .pagination-item:hover:not(.active) {
            background: var(--light);
            color: var(--primary);
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
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }
            
            .filter-bar {
                flex-direction: column;
                width: 100%;
            }
            
            .search-bar {
                width: 100%;
            }
            
            .table-responsive {
                font-size: 0.9rem;
            }
            
            .action-icons {
                flex-wrap: wrap;
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
            
            <a href="admin_reports.php" class="menu-item">
                <i class="fas fa-chart-bar"></i>
                <span>Raportet</span>
            </a>
            
            <a href="admin_users.php" class="menu-item active">
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
                <h2>Menaxhimi i Përdoruesve</h2>
                <div class="breadcrumb">
                    <span>Noteria</span>
                    <span>Admin</span>
                    <span>Përdoruesit</span>
                </div>
            </div>
            
            <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
            <?php endif; ?>
            
            <div class="section">
                <div class="section-header">
                    <h3 class="section-title"><i class="fas fa-users"></i> Lista e Përdoruesve</h3>
                    <button class="btn btn-primary" id="addUserBtn">
                        <i class="fas fa-user-plus"></i> Shto Përdorues
                    </button>
                </div>
                
                <div class="filter-bar">
                    <div class="search-bar">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" placeholder="Kërko përdorues..." value="<?php echo htmlspecialchars($search_term); ?>">
                    </div>
                    
                    <select class="filter-dropdown" id="roleFilter">
                        <option value="all" <?php echo $filter_role === 'all' ? 'selected' : ''; ?>>Të gjitha rolet</option>
                        <option value="admin" <?php echo $filter_role === 'admin' ? 'selected' : ''; ?>>Administratorët</option>
                        <option value="zyra" <?php echo $filter_role === 'zyra' ? 'selected' : ''; ?>>Zyrat Noteriale</option>
                        <option value="user" <?php echo $filter_role === 'user' ? 'selected' : ''; ?>>Përdoruesit e thjeshtë</option>
                    </select>
                    
                    <select class="filter-dropdown" id="statusFilter">
                        <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>Të gjitha statuset</option>
                        <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Aktiv</option>
                        <option value="inactive" <?php echo $filter_status === 'inactive' ? 'selected' : ''; ?>>Joaktiv</option>
                    </select>
                    
                    <button class="btn btn-primary" id="applyFilters">
                        <i class="fas fa-filter"></i> Filtro
                    </button>
                </div>
                
                <div class="table-responsive">
                    <table>
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
                            <?php foreach ($filtered_users as $user): ?>
                            <tr>
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
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <a href="?action=toggle_status&id=<?php echo $user['id']; ?>" class="badge <?php echo $user['aktiv'] ? 'badge-success' : 'badge-danger'; ?>">
                                        <?php echo $user['aktiv'] ? 'Aktiv' : 'Joaktiv'; ?>
                                    </a>
                                    <?php else: ?>
                                    <span class="badge badge-success">Aktiv</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d.m.Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <div class="action-icons">
                                        <a href="admin_edit_user.php?id=<?php echo $user['id']; ?>" class="action-icon action-icon-edit" title="Modifiko">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <a href="#" class="action-icon action-icon-edit role-change-btn" title="Ndrysho rolin" data-id="<?php echo $user['id']; ?>" data-role="<?php echo $user['roli']; ?>">
                                            <i class="fas fa-user-tag"></i>
                                        </a>
                                        
                                        <a href="?action=delete&id=<?php echo $user['id']; ?>" class="action-icon action-icon-delete" title="Fshi" onclick="return confirm('Jeni të sigurt që doni të fshini këtë përdorues?');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <?php if (empty($filtered_users)): ?>
                    <div style="text-align: center; padding: 24px; color: var(--gray);">
                        Nuk u gjetën përdorues që përputhen me kriteret e zgjedhura.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add User Modal -->
    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h3 class="modal-title">Shto Përdorues të Ri</h3>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="emri" class="form-label">Emri</label>
                    <input type="text" id="emri" name="emri" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="mbiemri" class="form-label">Mbiemri</label>
                    <input type="text" id="mbiemri" name="mbiemri" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" id="email" name="email" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">Fjalëkalimi</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                    <div class="form-text">Minimumi 8 karaktere, përfshirë shkronja dhe numra.</div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Roli</label>
                    <div class="role-selector">
                        <div class="role-option" data-role="user">
                            <i class="fas fa-user"></i>
                            <div>Përdorues</div>
                        </div>
                        <div class="role-option" data-role="zyra">
                            <i class="fas fa-building"></i>
                            <div>Zyrë Noteriale</div>
                        </div>
                        <div class="role-option" data-role="admin">
                            <i class="fas fa-user-shield"></i>
                            <div>Administrator</div>
                        </div>
                    </div>
                    <input type="hidden" name="roli" id="selectedRole" value="user">
                </div>
                
                <div class="form-group" id="zyraGroup" style="display: none;">
                    <label for="zyra_id" class="form-label">Zyra Noteriale</label>
                    <select id="zyra_id" name="zyra_id" class="form-control">
                        <option value="">Zgjidhni një zyrë...</option>
                        <?php foreach ($zyrat as $zyra): ?>
                        <option value="<?php echo $zyra['id']; ?>"><?php echo htmlspecialchars($zyra['emri']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Lidhni përdoruesin me një zyrë noteriale.</div>
                </div>
                
                <button type="submit" name="add_user" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> Shto Përdorues
                </button>
            </form>
        </div>
    </div>
    
    <!-- Change Role Modal -->
    <div id="changeRoleModal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h3 class="modal-title">Ndrysho Rolin e Përdoruesit</h3>
            <form id="changeRoleForm" action="" method="GET">
                <input type="hidden" name="action" value="change_role">
                <input type="hidden" name="id" id="changeRoleUserId" value="">
                
                <div class="form-group">
                    <label class="form-label">Zgjidhni Rolin e Ri</label>
                    <div class="role-selector">
                        <div class="role-option" data-role="user">
                            <i class="fas fa-user"></i>
                            <div>Përdorues</div>
                        </div>
                        <div class="role-option" data-role="zyra">
                            <i class="fas fa-building"></i>
                            <div>Zyrë Noteriale</div>
                        </div>
                        <div class="role-option" data-role="admin">
                            <i class="fas fa-user-shield"></i>
                            <div>Administrator</div>
                        </div>
                    </div>
                    <input type="hidden" name="new_role" id="newRole" value="">
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Ruaj Ndryshimet
                </button>
            </form>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add User Modal
            const addUserModal = document.getElementById('addUserModal');
            const addUserBtn = document.getElementById('addUserBtn');
            const closeModalBtns = document.querySelectorAll('.close-modal');
            
            addUserBtn.addEventListener('click', function() {
                addUserModal.style.display = 'flex';
            });
            
            // Role Selection in Add User Modal
            const roleOptions = document.querySelectorAll('#addUserModal .role-option');
            const selectedRoleInput = document.getElementById('selectedRole');
            const zyraGroup = document.getElementById('zyraGroup');
            
            roleOptions.forEach(option => {
                option.addEventListener('click', function() {
                    roleOptions.forEach(opt => opt.classList.remove('active'));
                    this.classList.add('active');
                    
                    const role = this.getAttribute('data-role');
                    selectedRoleInput.value = role;
                    
                    if (role === 'zyra') {
                        zyraGroup.style.display = 'block';
                    } else {
                        zyraGroup.style.display = 'none';
                    }
                });
            });
            
            // Change Role Modal
            const changeRoleModal = document.getElementById('changeRoleModal');
            const changeRoleBtns = document.querySelectorAll('.role-change-btn');
            const changeRoleUserId = document.getElementById('changeRoleUserId');
            const changeRoleOptions = document.querySelectorAll('#changeRoleModal .role-option');
            const newRoleInput = document.getElementById('newRole');
            
            changeRoleBtns.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const userId = this.getAttribute('data-id');
                    const currentRole = this.getAttribute('data-role');
                    
                    changeRoleUserId.value = userId;
                    
                    // Set current role as active
                    changeRoleOptions.forEach(opt => {
                        opt.classList.remove('active');
                        if (opt.getAttribute('data-role') === currentRole) {
                            opt.classList.add('active');
                            newRoleInput.value = currentRole;
                        }
                    });
                    
                    changeRoleModal.style.display = 'flex';
                });
            });
            
            // Role Selection in Change Role Modal
            changeRoleOptions.forEach(option => {
                option.addEventListener('click', function() {
                    changeRoleOptions.forEach(opt => opt.classList.remove('active'));
                    this.classList.add('active');
                    
                    const role = this.getAttribute('data-role');
                    newRoleInput.value = role;
                });
            });
            
            // Close Modals
            closeModalBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    addUserModal.style.display = 'none';
                    changeRoleModal.style.display = 'none';
                });
            });
            
            window.addEventListener('click', function(event) {
                if (event.target === addUserModal) {
                    addUserModal.style.display = 'none';
                }
                if (event.target === changeRoleModal) {
                    changeRoleModal.style.display = 'none';
                }
            });
            
            // Filter functionality
            const searchInput = document.getElementById('searchInput');
            const roleFilter = document.getElementById('roleFilter');
            const statusFilter = document.getElementById('statusFilter');
            const applyFiltersBtn = document.getElementById('applyFilters');
            
            applyFiltersBtn.addEventListener('click', function() {
                const searchValue = searchInput.value.trim();
                const roleValue = roleFilter.value;
                const statusValue = statusFilter.value;
                
                window.location.href = `admin_users.php?search=${encodeURIComponent(searchValue)}&role=${roleValue}&status=${statusValue}`;
            });
        });
    </script>
</body>
</html>