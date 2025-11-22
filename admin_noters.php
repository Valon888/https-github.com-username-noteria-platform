<?php
// Redirect to the new noteret.php page
session_start();
header("Location: noteret.php");
exit();

/*
// Old code for reference
// admin_noters.php
// Paneli i administratorit për menaxhimin e noterëve
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

require_once 'config.php';

// Kontrollo autorizimin
session_start();
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['user_id'])) {
    header("Location: login.php?error=auth_required");
    exit();
}
*/

// Kontrollo nëse është user normal ose admin
$isAdmin = isset($_SESSION['admin_id']);
$userId = $isAdmin ? $_SESSION['admin_id'] : $_SESSION['user_id'];

// Inicializo variablat
$message = '';
$messageType = '';
$noteri = [];
$allNoters = [];

// Procesi i fshirjes
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    try {
        $noterId = $_GET['id'];
        
        // Kontrollo nëse noteri ekziston
        $stmt = $pdo->prepare("SELECT id, emri, mbiemri FROM noteri WHERE id = ?");
        $stmt->execute([$noterId]);
        $noterToDelete = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$noterToDelete) {
            $message = "Noteri i kërkuar nuk u gjet!";
            $messageType = 'error';
        } else {
            // Kontrollo nëse ka të dhëna të lidhura para fshirjes
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM subscription_payments WHERE noter_id = ?");
            $stmt->execute([$noterId]);
            $paymentCount = $stmt->fetchColumn();
            
            if ($paymentCount > 0) {
                $message = "Noteri ka pagesa të regjistruara dhe nuk mund të fshihet. Fshini pagesat fillimisht.";
                $messageType = 'error';
            } else {
                // Fshi noterin
                $stmt = $pdo->prepare("DELETE FROM noteri WHERE id = ?");
                $stmt->execute([$noterId]);
                
                $message = "Noteri '{$noterToDelete['emri']} {$noterToDelete['mbiemri']}' u fshi me sukses!";
                $messageType = 'success';
            }
        }
    } catch (PDOException $e) {
        $message = "Gabim gjatë fshirjes së noterit: " . $e->getMessage();
        $messageType = 'error';
        error_log("admin_noters.php - Gabim fshirje: " . $e->getMessage());
    }
}

// Procesi për statusin
if (isset($_GET['action']) && $_GET['action'] == 'toggle_status' && isset($_GET['id'])) {
    try {
        $noterId = $_GET['id'];
        
        // Merr statusin aktual
        $stmt = $pdo->prepare("SELECT id, emri, mbiemri, status FROM noteri WHERE id = ?");
        $stmt->execute([$noterId]);
        $noterToUpdate = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$noterToUpdate) {
            $message = "Noteri i kërkuar nuk u gjet!";
            $messageType = 'error';
        } else {
            // Ndrysho statusin
            $newStatus = ($noterToUpdate['status'] == 'active') ? 'inactive' : 'active';
            
            $stmt = $pdo->prepare("UPDATE noteri SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $noterId]);
            
            $statusText = ($newStatus == 'active') ? 'aktiv' : 'joaktiv';
            $message = "Statusi i noterit '{$noterToUpdate['emri']} {$noterToUpdate['mbiemri']}' u bë {$statusText}!";
            $messageType = 'success';
        }
    } catch (PDOException $e) {
        $message = "Gabim gjatë ndryshimit të statusit: " . $e->getMessage();
        $messageType = 'error';
        error_log("admin_noters.php - Gabim ndryshimi statusi: " . $e->getMessage());
    }
}

// Procesi i editimit/shikimit
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    try {
        $noterId = $_GET['id'];
        
        // Merr të dhënat e noterit
        $stmt = $pdo->prepare("
            SELECT 
                n.*, 
                (SELECT COUNT(*) FROM subscription_payments WHERE noter_id = n.id) as payment_count,
                (SELECT SUM(amount) FROM subscription_payments WHERE noter_id = n.id AND status = 'completed') as total_paid
            FROM 
                noteri n 
            WHERE 
                n.id = ?
        ");
        $stmt->execute([$noterId]);
        $noteri = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$noteri) {
            $message = "Noteri i kërkuar nuk u gjet!";
            $messageType = 'error';
        }
    } catch (PDOException $e) {
        $message = "Gabim gjatë leximit të të dhënave: " . $e->getMessage();
        $messageType = 'error';
        error_log("admin_noters.php - Gabim leximi: " . $e->getMessage());
    }
}

// Procesi i përditësimit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_noter'])) {
    try {
        $noterId = $_POST['noter_id'];
        $emri = trim($_POST['emri']);
        $mbiemri = trim($_POST['mbiemri']);
        $email = trim($_POST['email']);
        $telefoni = trim($_POST['telefoni']);
        $adresa = trim($_POST['adresa']);
        $qyteti = trim($_POST['qyteti']);
        $status = $_POST['status'];
        $subscription_type = $_POST['subscription_type'];
        $custom_price = isset($_POST['custom_price']) ? (float)$_POST['custom_price'] : null;
        
        // Validimi bazë
        if (empty($emri) || empty($mbiemri) || empty($email)) {
            $message = "Të gjitha fushat e detyrueshme duhet të plotësohen!";
            $messageType = 'error';
        } else {
            // Kontrollo email unike (përveç këtij noterit)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM noteri WHERE email = ? AND id != ?");
            $stmt->execute([$email, $noterId]);
            if ($stmt->fetchColumn() > 0) {
                $message = "Email-i është në përdorim nga një noter tjetër!";
                $messageType = 'error';
            } else {
                // Përditëso noterin
                $stmt = $pdo->prepare("
                    UPDATE noteri SET 
                    emri = ?, 
                    mbiemri = ?, 
                    email = ?,
                    telefoni = ?,
                    adresa = ?,
                    qyteti = ?,
                    status = ?,
                    subscription_type = ?,
                    custom_price = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $emri, $mbiemri, $email, $telefoni, $adresa, $qyteti, 
                    $status, $subscription_type, $custom_price, $noterId
                ]);
                
                $message = "Noteri '{$emri} {$mbiemri}' u përditësua me sukses!";
                $messageType = 'success';
                
                // Reset noteri array for a clean view after update
                $noteri = [];
            }
        }
    } catch (PDOException $e) {
        $message = "Gabim gjatë përditësimit të të dhënave: " . $e->getMessage();
        $messageType = 'error';
        error_log("admin_noters.php - Gabim përditësimi: " . $e->getMessage());
    }
}

// Merr të gjithë noterët
try {
    $searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
    $statusFilter = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';
    $sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'id';
    $sortDirection = isset($_GET['direction']) && $_GET['direction'] === 'desc' ? 'DESC' : 'ASC';
    
    // Ndërto query
    $query = "
        SELECT 
            n.*, 
            (SELECT COUNT(*) FROM subscription_payments WHERE noter_id = n.id) as payment_count
        FROM 
            noteri n 
        WHERE 1=1
    ";
    
    $params = [];
    
    // Apliko filtra
    if (!empty($searchTerm)) {
        $query .= " AND (n.emri LIKE ? OR n.mbiemri LIKE ? OR n.email LIKE ? OR n.qyteti LIKE ?)";
        $searchParam = "%$searchTerm%";
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
    }
    
    // Kontrollo nëse ekziston kolona 'status' në tabelën 'noteri'
    $columnExists = false;
    try {
        $checkStmt = $pdo->query("SHOW COLUMNS FROM noteri LIKE 'status'");
        $columnExists = ($checkStmt && $checkStmt->rowCount() > 0);
    } catch (PDOException $e) {
        // Kolona nuk ekziston, vazhdojmë pa filtrim statusi
        error_log("admin_noters.php - Kolona 'status' mungon: " . $e->getMessage());
    }
    
    // Filtro vetëm nëse kolona ekziston
    if ($columnExists) {
        if (!empty($statusFilter)) {
            if ($statusFilter == 'all') {
                // Nuk apliko asnjë filtër për të shfaqur të gjithë noterët
            } else {
                $query .= " AND n.status = ?";
                $params[] = $statusFilter;
            }
        } else {
            // Nëse nuk është specifikuar asnjë filtër për status, shfaq vetëm noterët aktivë
            $query .= " AND (n.status = 'active' OR n.status IS NULL)";
        }
    }
    
    // Renditja
    $allowedSortFields = ['id', 'emri', 'email', 'qyteti', 'status', 'subscription_type', 'data_regjistrimit', 'payment_count'];
    $sortBy = in_array($sortBy, $allowedSortFields) ? $sortBy : 'id';
    $query .= " ORDER BY $sortBy $sortDirection";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $allNoters = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $message = "Gabim gjatë marrjes së të dhënave: " . $e->getMessage();
    $messageType = 'error';
    error_log("admin_noters.php - Gabim query: " . $e->getMessage());
}

// Statistikat 
$stats = [
    'total' => 0,
    'active' => 0,
    'inactive' => 0
];

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM noteri");
    $stats['total'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM noteri WHERE status = 'active'");
    $stats['active'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM noteri WHERE status = 'inactive'");
    $stats['inactive'] = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("admin_noters.php - Gabim statistikat: " . $e->getMessage());
}

?><!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menaxhimi i Noterëve | Noteria</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        :root {
            --primary: #1a56db;
            --primary-dark: #1e429f;
            --primary-light: #e1effe;
            --secondary: #6b7280;
            --success: #16a34a;
            --danger: #dc2626;
            --warning: #f59e0b;
            --info: #0ea5e9;
            --light: #f9fafb;
            --dark: #1f2937;
            --body-bg: #f8fafc;
            --card-bg: #ffffff;
            --border: #e5e7eb;
            --text: #374151;
            --text-light: #6b7280;
            --text-dark: #1f2937;
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
        .stat-red { background-color: var(--danger); }
        
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
        
        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.5rem;
            align-items: center;
        }
        
        .search-form {
            flex: 1;
            min-width: 300px;
            position: relative;
        }
        
        .search-input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.75rem;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font-family: inherit;
            font-size: 0.95rem;
            transition: var(--transition);
            color: var(--text);
        }
        
        .search-input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--secondary);
        }
        
        .filter-dropdown {
            padding: 0.75rem 1rem;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font-family: inherit;
            font-size: 0.95rem;
            color: var(--text);
            background-color: var(--light);
            cursor: pointer;
            transition: var(--transition);
            min-width: 180px;
        }
        
        .filter-dropdown:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .btn {
            padding: 0.75rem 1.25rem;
            font-family: inherit;
            font-size: 0.95rem;
            font-weight: 500;
            color: white;
            background-color: var(--primary);
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-decoration: none;
        }
        
        .btn:hover {
            background-color: var(--primary-dark);
        }
        
        .btn-sm {
            padding: 0.5rem 0.85rem;
            font-size: 0.85rem;
        }
        
        .btn-success { background-color: var(--success); }
        .btn-success:hover { background-color: #15803d; }
        
        .btn-danger { background-color: var(--danger); }
        .btn-danger:hover { background-color: #b91c1c; }
        
        .btn-warning { background-color: var(--warning); }
        .btn-warning:hover { background-color: #d97706; }
        
        .btn-info { background-color: var(--info); }
        .btn-info:hover { background-color: #0284c7; }
        
        .btn-secondary { 
            background-color: var(--light); 
            color: var(--text);
            border: 1px solid var(--border);
        }
        .btn-secondary:hover { 
            background-color: #e5e7eb;
        }
        
        .btn-link {
            background: none;
            color: var(--primary);
            padding: 0.5rem;
            text-decoration: underline;
        }
        
        .btn-link:hover {
            background: none;
            color: var(--primary-dark);
        }
        
        .table-responsive {
            overflow-x: auto;
            margin-bottom: 1rem;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95rem;
        }
        
        th {
            background-color: var(--light);
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--heading);
            white-space: nowrap;
            border-bottom: 1px solid var(--border);
        }
        
        td {
            padding: 1rem;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }
        
        th a {
            color: inherit;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        tr:hover {
            background-color: rgba(243, 244, 246, 0.5);
        }
        
        .table-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
        }
        
        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .badge-success { 
            background-color: #dcfce7;
            color: #166534;
        }
        
        .badge-danger {
            background-color: #fee2e2;
            color: #991b1b;
        }
        
        .badge-warning {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .badge-info {
            background-color: #dbeafe;
            color: #1e40af;
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
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-dark);
        }
        
        .form-required::after {
            content: '*';
            color: var(--danger);
            margin-left: 0.25rem;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            font-family: inherit;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            color: var(--text);
            transition: var(--transition);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .form-text {
            font-size: 0.8rem;
            color: var(--text-light);
            margin-top: 0.5rem;
        }
        
        .form-select {
            width: 100%;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            font-family: inherit;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            color: var(--text);
            transition: var(--transition);
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.5rem center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
        }
        
        .form-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }
        
        .page-item {
            list-style: none;
        }
        
        .page-link {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            color: var(--text);
            font-weight: 500;
            text-decoration: none;
            transition: var(--transition);
        }
        
        .page-link:hover {
            background-color: var(--light);
        }
        
        .page-link.active {
            background-color: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .modal-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: none;
            align-items: center;
            justify-content: center;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            padding: 1rem;
        }
        
        .modal-container.active {
            display: flex;
        }
        
        .modal {
            width: 100%;
            max-width: 500px;
            background-color: var(--card-bg);
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
        }
        
        .modal-header {
            padding: 1.25rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border);
        }
        
        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--heading);
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-light);
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-close:hover {
            color: var(--text-dark);
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .modal-footer {
            padding: 1.25rem 1.5rem;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 2rem;
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--text-light);
            margin-bottom: 1rem;
        }
        
        .empty-state-title {
            font-size: 1.25rem;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .empty-state-text {
            color: var(--text-light);
        }
        
        /* Responsiveness */
        @media (max-width: 768px) {
            .admin-nav {
                display: none;
            }
            
            .admin-header .container {
                justify-content: center;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .filter-bar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .table-responsive {
                margin-left: -1.5rem;
                margin-right: -1.5rem;
                width: calc(100% + 3rem);
            }
        }
        
        /* Print styles */
        @media print {
            .admin-header, 
            .filter-bar, 
            .pagination, 
            .table-actions, 
            .btn {
                display: none !important;
            }
            
            .card {
                box-shadow: none;
                border: 1px solid #ddd;
            }
            
            body {
                background: white;
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
                <a href="admin_noters.php" class="admin-nav-item active">
                    <i class="fas fa-user-tie"></i> Noterët
                </a>
                <a href="subscription_dashboard.php" class="admin-nav-item">
                    <i class="fas fa-receipt"></i> Abonimet
                </a>
                <a href="reports.php" class="admin-nav-item">
                    <i class="fas fa-chart-bar"></i> Raportet
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
            <h1 class="page-title"><i class="fas fa-user-tie"></i> Menaxhimi i Noterëve</h1>
            
            <a href="zyrat_register.php" class="btn">
                <i class="fas fa-plus"></i> Shto Noter të Ri
            </a>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType === 'error' ? 'danger' : $messageType; ?>">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : ($messageType === 'error' ? 'exclamation-circle' : 'info-circle'); ?>"></i>
                <div><?php echo $message; ?></div>
            </div>
        <?php endif; ?>
        
        <?php
        // Kontrollo nëse mungojnë kolonat e nevojshme në databazë
        try {
            $statusCheck = $pdo->query("SHOW COLUMNS FROM noteri LIKE 'status'");
            if ($statusCheck->rowCount() == 0) {
                echo '<div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div><strong>Vëmendje!</strong> Struktura e databazës duhet përditësuar. 
                    <a href="fix_database_columns.php" class="alert-link">Kliko këtu për të korrigjuar strukturën e databazës</a>.</div>
                </div>';
            }
        } catch (PDOException $e) {
            echo '<div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <div><strong>Gabim!</strong> Problem me verifikimin e strukturës së databazës: ' . $e->getMessage() . '</div>
            </div>';
        }
        ?>

        <div class="stats-cards">
            <div class="stat-card">
                <div class="stat-icon stat-blue">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Noterë Gjithsej</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon stat-green">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $stats['active']; ?></div>
                    <div class="stat-label">Noterë Aktivë</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon stat-red">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $stats['inactive']; ?></div>
                    <div class="stat-label">Noterë Joaktivë</div>
                </div>
            </div>
        </div>

        <?php if (isset($noteri['id'])): ?>
            <!-- Edit Noter Form -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><i class="fas fa-edit"></i> Ndrysho të dhënat e noterit</h2>
                </div>
                
                <form action="admin_noters.php" method="POST">
                    <input type="hidden" name="noter_id" value="<?php echo $noteri['id']; ?>">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="emri" class="form-label form-required">Emri</label>
                            <input type="text" id="emri" name="emri" class="form-control" value="<?php echo htmlspecialchars($noteri['emri']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="mbiemri" class="form-label form-required">Mbiemri</label>
                            <input type="text" id="mbiemri" name="mbiemri" class="form-control" value="<?php echo htmlspecialchars($noteri['mbiemri']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email" class="form-label form-required">Email</label>
                            <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($noteri['email']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="telefoni" class="form-label">Telefoni</label>
                            <input type="text" id="telefoni" name="telefoni" class="form-control" value="<?php echo htmlspecialchars($noteri['telefoni'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="qyteti" class="form-label">Qyteti</label>
                            <input type="text" id="qyteti" name="qyteti" class="form-control" value="<?php echo htmlspecialchars($noteri['qyteti'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="status" class="form-label">Statusi</label>
                            <select id="status" name="status" class="form-select">
                                <option value="active" <?php echo ($noteri['status'] == 'active') ? 'selected' : ''; ?>>Aktiv</option>
                                <option value="inactive" <?php echo ($noteri['status'] == 'inactive') ? 'selected' : ''; ?>>Joaktiv</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="subscription_type" class="form-label">Lloji i Abonimit</label>
                            <select id="subscription_type" name="subscription_type" class="form-select">
                                <option value="standard" <?php echo ($noteri['subscription_type'] == 'standard') ? 'selected' : ''; ?>>Standard (150.00 €)</option>
                                <option value="premium" <?php echo ($noteri['subscription_type'] == 'premium') ? 'selected' : ''; ?>>Premium</option>
                                <option value="custom" <?php echo ($noteri['subscription_type'] == 'custom') ? 'selected' : ''; ?>>Personalizuar</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="custom_price" class="form-label">Çmim i Personalizuar (€)</label>
                            <input type="number" id="custom_price" name="custom_price" class="form-control" value="<?php echo htmlspecialchars($noteri['custom_price'] ?? 150.00); ?>" min="0" step="0.01">
                            <div class="form-text">Lini 150.00 për çmimin standard.</div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="adresa" class="form-label">Adresa</label>
                        <textarea id="adresa" name="adresa" class="form-control" rows="3"><?php echo htmlspecialchars($noteri['adresa'] ?? ''); ?></textarea>
                    </div>
                    
                    <div>
                        <h3 class="card-title" style="margin: 1.5rem 0 1rem;">Informacion shtesë</h3>
                        <div class="form-grid">
                            <div>
                                <p><strong>ID:</strong> <?php echo $noteri['id']; ?></p>
                                <p><strong>Data e regjistrimit:</strong> <?php echo date('d.m.Y H:i', strtotime($noteri['data_regjistrimit'])); ?></p>
                            </div>
                            <div>
                                <p><strong>Pagesat totale:</strong> <?php echo $noteri['payment_count'] ?? 0; ?></p>
                                <p><strong>Shuma totale e paguar:</strong> <?php echo number_format($noteri['total_paid'] ?? 0, 2); ?> €</p>
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin-top: 2rem; display: flex; justify-content: space-between;">
                        <a href="admin_noters.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Kthehu
                        </a>
                        
                        <div>
                            <a href="subscription_payments.php?noter_id=<?php echo $noteri['id']; ?>" class="btn btn-info">
                                <i class="fas fa-receipt"></i> Shiko pagesat
                            </a>
                            
                            <button type="submit" name="update_noter" class="btn btn-success">
                                <i class="fas fa-save"></i> Ruaj Ndryshimet
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <!-- List All Noters -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><i class="fas fa-list"></i> Lista e Noterëve</h2>
                    <div>
                        <button class="btn btn-sm btn-secondary" id="printBtn" onclick="window.print()">
                            <i class="fas fa-print"></i> Printo
                        </button>
                    </div>
                </div>
                
                <div class="filter-bar">
                    <form action="" method="GET" class="search-form">
                        <i class="fas fa-search search-icon"></i>
                        <input 
                            type="text" 
                            name="search" 
                            class="search-input" 
                            placeholder="Kërko sipas emrit, mbiemrit, email-it ose qytetit" 
                            value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
                        >
                    </form>
                    
                    <select name="filter_status" id="filter_status" class="filter-dropdown" onchange="this.form.submit()">
                        <option value="active" <?php echo (!isset($_GET['filter_status']) || $_GET['filter_status'] === 'active') ? 'selected' : ''; ?>>Aktivë</option>
                        <option value="inactive" <?php echo (isset($_GET['filter_status']) && $_GET['filter_status'] === 'inactive') ? 'selected' : ''; ?>>Joaktivë</option>
                        <option value="all" <?php echo (isset($_GET['filter_status']) && $_GET['filter_status'] === 'all') ? 'selected' : ''; ?>>Të gjithë</option>
                    </select>
                    
                    <button type="submit" class="btn">
                        <i class="fas fa-filter"></i> Filtro
                    </button>
                </div>
                
                <div class="table-responsive">
                    <?php if (count($allNoters) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>
                                        <a href="?sort=id&direction=<?php echo (isset($_GET['sort']) && $_GET['sort'] === 'id' && isset($_GET['direction']) && $_GET['direction'] === 'asc') ? 'desc' : 'asc'; ?>">
                                            ID
                                            <?php if (isset($_GET['sort']) && $_GET['sort'] === 'id'): ?>
                                                <i class="fas fa-sort-<?php echo (isset($_GET['direction']) && $_GET['direction'] === 'desc') ? 'down' : 'up'; ?>"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a href="?sort=emri&direction=<?php echo (isset($_GET['sort']) && $_GET['sort'] === 'emri' && isset($_GET['direction']) && $_GET['direction'] === 'asc') ? 'desc' : 'asc'; ?>">
                                            Noter
                                            <?php if (isset($_GET['sort']) && $_GET['sort'] === 'emri'): ?>
                                                <i class="fas fa-sort-<?php echo (isset($_GET['direction']) && $_GET['direction'] === 'desc') ? 'down' : 'up'; ?>"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a href="?sort=email&direction=<?php echo (isset($_GET['sort']) && $_GET['sort'] === 'email' && isset($_GET['direction']) && $_GET['direction'] === 'asc') ? 'desc' : 'asc'; ?>">
                                            Kontakti
                                            <?php if (isset($_GET['sort']) && $_GET['sort'] === 'email'): ?>
                                                <i class="fas fa-sort-<?php echo (isset($_GET['direction']) && $_GET['direction'] === 'desc') ? 'down' : 'up'; ?>"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a href="?sort=qyteti&direction=<?php echo (isset($_GET['sort']) && $_GET['sort'] === 'qyteti' && isset($_GET['direction']) && $_GET['direction'] === 'asc') ? 'desc' : 'asc'; ?>">
                                            Vendndodhja
                                            <?php if (isset($_GET['sort']) && $_GET['sort'] === 'qyteti'): ?>
                                                <i class="fas fa-sort-<?php echo (isset($_GET['direction']) && $_GET['direction'] === 'desc') ? 'down' : 'up'; ?>"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a href="?sort=subscription_type&direction=<?php echo (isset($_GET['sort']) && $_GET['sort'] === 'subscription_type' && isset($_GET['direction']) && $_GET['direction'] === 'asc') ? 'desc' : 'asc'; ?>">
                                            Abonimi
                                            <?php if (isset($_GET['sort']) && $_GET['sort'] === 'subscription_type'): ?>
                                                <i class="fas fa-sort-<?php echo (isset($_GET['direction']) && $_GET['direction'] === 'desc') ? 'down' : 'up'; ?>"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a href="?sort=status&direction=<?php echo (isset($_GET['sort']) && $_GET['sort'] === 'status' && isset($_GET['direction']) && $_GET['direction'] === 'asc') ? 'desc' : 'asc'; ?>">
                                            Statusi
                                            <?php if (isset($_GET['sort']) && $_GET['sort'] === 'status'): ?>
                                                <i class="fas fa-sort-<?php echo (isset($_GET['direction']) && $_GET['direction'] === 'desc') ? 'down' : 'up'; ?>"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a href="?sort=payment_count&direction=<?php echo (isset($_GET['sort']) && $_GET['sort'] === 'payment_count' && isset($_GET['direction']) && $_GET['direction'] === 'asc') ? 'desc' : 'asc'; ?>">
                                            Pagesat
                                            <?php if (isset($_GET['sort']) && $_GET['sort'] === 'payment_count'): ?>
                                                <i class="fas fa-sort-<?php echo (isset($_GET['direction']) && $_GET['direction'] === 'desc') ? 'down' : 'up'; ?>"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th>Veprime</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allNoters as $noter): ?>
                                    <tr>
                                        <td><?php echo $noter['id']; ?></td>
                                        <td>
                                            <div style="font-weight: 500;"><?php echo htmlspecialchars($noter['emri'] . ' ' . $noter['mbiemri']); ?></div>
                                            <div style="font-size: 0.8rem; color: var(--text-light);">Regj: <?php echo isset($noter['data_regjistrimit']) ? date('d.m.Y', strtotime($noter['data_regjistrimit'])) : 'N/A'; ?></div>
                                        </td>
                                        <td>
                                            <div><?php echo htmlspecialchars($noter['email']); ?></div>
                                            <?php if (!empty($noter['telefoni'])): ?>
                                                <div style="font-size: 0.85rem;"><?php echo htmlspecialchars($noter['telefoni']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($noter['qyteti'])): ?>
                                                <div><?php echo htmlspecialchars($noter['qyteti']); ?></div>
                                            <?php else: ?>
                                                <span style="color: var(--text-light);">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                                if (isset($noter['subscription_type'])) {
                                                    switch($noter['subscription_type']) {
                                                        case 'standard':
                                                            echo '<span class="badge badge-info">Standard</span><div style="font-size: 0.85rem; margin-top: 0.25rem;">150.00 €/muaj</div>';
                                                            break;
                                                        case 'premium':
                                                            echo '<span class="badge badge-success">Premium</span>';
                                                            break;
                                                        case 'custom':
                                                            echo '<span class="badge badge-warning">Personalizuar</span>';
                                                            if (!empty($noter['custom_price'])) {
                                                                echo '<div style="font-size: 0.85rem; margin-top: 0.25rem;">' . number_format($noter['custom_price'], 2) . ' €/muaj</div>';
                                                            }
                                                            break;
                                                        default:
                                                            echo '<span class="badge badge-secondary">Standard</span><div style="font-size: 0.85rem; margin-top: 0.25rem;">150.00 €/muaj</div>';
                                                    }
                                                } else {
                                                    echo '<span class="badge badge-secondary">Standard</span><div style="font-size: 0.85rem; margin-top: 0.25rem;">150.00 €/muaj</div>';
                                                }
                                            ?>
                                        </td>
                                        <td>
                                            <?php if (isset($noter['status']) && $noter['status'] == 'active'): ?>
                                                <span class="badge badge-success">Aktiv</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">Joaktiv</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo isset($noter['payment_count']) ? $noter['payment_count'] : '0'; ?> pagesa
                                        </td>
                                        <td>
                                            <div class="table-actions">
                                                <a href="?action=edit&id=<?php echo $noter['id']; ?>" class="btn btn-sm btn-info" title="Ndrysho">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="?action=toggle_status&id=<?php echo $noter['id']; ?>" class="btn btn-sm btn-warning" title="<?php echo (isset($noter['status']) && $noter['status'] == 'active') ? 'Bëj joaktiv' : 'Bëj aktiv'; ?>">
                                                    <i class="fas fa-<?php echo (isset($noter['status']) && $noter['status'] == 'active') ? 'toggle-off' : 'toggle-on'; ?>"></i>
                                                </a>
                                                <a href="javascript:confirmDelete(<?php echo $noter['id']; ?>, '<?php echo htmlspecialchars($noter['emri'] . ' ' . $noter['mbiemri']); ?>')" class="btn btn-sm btn-danger" title="Fshi">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-user-slash"></i>
                            <h3 class="empty-state-title">Nuk u gjetën noterë</h3>
                            <p class="empty-state-text">Nuk ka noterë që përputhen me kriteret e kërkimit. Ju lutemi provoni me një kërkim tjetër ose <a href="zyrat_register.php">shtoni një noter të ri</a>.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal-container" id="deleteModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Konfirmo fshirjen</h3>
                <button class="modal-close" id="closeDeleteModal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Jeni të sigurt që doni të fshini noterin <strong id="deleteName"></strong>?</p>
                <p>Ky veprim nuk mund të kthehet. Të gjitha të dhënat e këtij noteri do të hiqen përgjithmonë.</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelDelete">Anulo</button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Konfirmo fshirjen</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize flatpickr date pickers if needed
            if(document.querySelector('.date-picker')) {
                flatpickr('.date-picker', {
                    dateFormat: 'd.m.Y'
                });
            }
            
            // Form submission for filters
            const filterStatus = document.getElementById('filter_status');
            if (filterStatus) {
                filterStatus.addEventListener('change', function() {
                    const searchParams = new URLSearchParams(window.location.search);
                    searchParams.set('filter_status', this.value);
                    window.location.href = `${window.location.pathname}?${searchParams.toString()}`;
                });
            }
            
            // Delete modal functionality
            const deleteModal = document.getElementById('deleteModal');
            const closeBtns = document.querySelectorAll('#closeDeleteModal, #cancelDelete');
            
            closeBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    deleteModal.classList.remove('active');
                });
            });
            
            // Close modal when clicking outside of it
            window.addEventListener('click', function(e) {
                if (e.target === deleteModal) {
                    deleteModal.classList.remove('active');
                }
            });
        });
        
        // Confirmation dialog for delete
        function confirmDelete(id, name) {
            const deleteModal = document.getElementById('deleteModal');
            const deleteName = document.getElementById('deleteName');
            const confirmBtn = document.getElementById('confirmDeleteBtn');
            
            deleteName.textContent = name;
            confirmBtn.href = `?action=delete&id=${id}`;
            deleteModal.classList.add('active');
        }
    </script>
</body>
</html>
