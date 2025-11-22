<?php
// Kontrollojmë nëse sesioni është i hapur para se të fillojmë një të ri
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Kontrollo nëse përdoruesi është i autentikuar dhe ka rolin admin
if (!isset($_SESSION["auth_test"]) && !isset($_SESSION["admin_id"])) {
    header("Location: test_login_easy.php");
    exit();
}

// Lidhja me databazën
require_once 'config.php';

// Merr parametrat e raportit
$lloji_raportit = isset($_GET['lloji']) ? $_GET['lloji'] : 'perdoruesit';
$data_fillimit = isset($_GET['data_fillimit']) ? $_GET['data_fillimit'] : date('Y-m-01'); // Fillimi i muajit aktual
$data_mbarimit = isset($_GET['data_mbarimit']) ? $_GET['data_mbarimit'] : date('Y-m-d'); // Data e sotme

// Parametrat e eksportimit
$format = isset($_GET['format']) ? $_GET['format'] : 'web'; // web, pdf, excel

// Funksion për të kontrolluar nëse tabela ekziston
function tableExists($pdo, $table) {
    try {
        $result = $pdo->query("SELECT 1 FROM $table LIMIT 1");
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// Gjenerimi i raportit
try {
    switch ($lloji_raportit) {
        case 'perdoruesit':
            // Merr të dhënat e përdoruesve
            $usersTable = tableExists($pdo, 'users');
            $notaretTable = tableExists($pdo, 'noteret');
            
            // Përdoruesit
            $perdoruesit = [];
            $noteret = [];
            $loginStats = ['success' => 0, 'failed' => 0];
            
            if ($usersTable) {
                $stmt = $pdo->prepare("SELECT id, emri, mbiemri, email, roli, DATE_FORMAT(created_at, '%d.%m.%Y') as data_regjistrimit FROM users WHERE created_at BETWEEN ? AND ?");
                $stmt->execute([$data_fillimit, $data_mbarimit]);
                $perdoruesit = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            if ($notaretTable) {
                $stmt = $pdo->prepare("SELECT id, emri, mbiemri, email, 'noter' as roli, DATE_FORMAT(data_regjistrimit, '%d.%m.%Y') as data_regjistrimit FROM noteret WHERE data_regjistrimit BETWEEN ? AND ?");
                $stmt->execute([$data_fillimit, $data_mbarimit]);
                $noteret = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            // Merr statistikat e hyrjes nëse ekziston tabela login_attempts dhe kolona 'status'
            if (tableExists($pdo, 'login_attempts')) {
                $loginStatusColumn = false;
                try {
                    $checkLoginCol = $pdo->query("SHOW COLUMNS FROM login_attempts");
                    $loginCols = $checkLoginCol->fetchAll(PDO::FETCH_COLUMN, 0);
                    $loginStatusColumn = in_array('status', $loginCols);
                } catch (PDOException $e) {
                    $loginStatusColumn = false;
                }
                if ($loginStatusColumn) {
                    $stmt = $pdo->prepare("SELECT status, COUNT(*) as total FROM login_attempts WHERE attempt_time BETWEEN ? AND ? GROUP BY status");
                    $stmt->execute([$data_fillimit, $data_mbarimit]);
                    $loginData = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($loginData as $item) {
                        if ($item['status'] === 'success') {
                            $loginStats['success'] = $item['total'];
                        } elseif ($item['status'] === 'failed') {
                            $loginStats['failed'] = $item['total'];
                        }
                    }
                }
            }
            break;
            
        case 'pagesat':
            // Merr të dhënat e pagesave
            $pagesat = [];
            $statistikat = ['total' => 0, 'shuma_total' => 0, 'mesatare' => 0];
            
            if (tableExists($pdo, 'payment_logs')) {
                // Kontrollo kolonat ekzistuese
                $statusColumn = false;
                $noterIdColumn = false;
                $userIdColumn = false;
                try {
                    $checkColumnStmt = $pdo->query("SHOW COLUMNS FROM payment_logs");
                    $columns = $checkColumnStmt->fetchAll(PDO::FETCH_COLUMN, 0);
                    $statusColumn = in_array('status', $columns);
                    $noterIdColumn = in_array('noter_id', $columns);
                    $userIdColumn = in_array('user_id', $columns);
                } catch (PDOException $e) {
                    // Nëse ndodh gabim, asnjë kolonë nuk ekziston
                }

                // Ndërto query sipas kolonave ekzistuese
                if ($statusColumn) {
                    if ($noterIdColumn) {
                        $stmt = $pdo->prepare("SELECT 
                            pl.id, pl.noter_id, n.emri, n.mbiemri, 150 as shuma, 
                            pl.status, pl.payment_method as metoda, 
                            pl.transaction_id as transaksioni,
                            DATE_FORMAT(pl.created_at, '%d.%m.%Y %H:%i') as data
                        FROM payment_logs pl
                        LEFT JOIN noteret n ON pl.noter_id = n.id
                        WHERE pl.created_at BETWEEN ? AND ?
                        ORDER BY pl.created_at DESC");
                    } else if ($userIdColumn) {
                        $stmt = $pdo->prepare("SELECT 
                            pl.id, pl.user_id as noter_id, n.emri, n.mbiemri, 150 as shuma, 
                            pl.status, pl.payment_method as metoda, 
                            pl.transaction_id as transaksioni,
                            DATE_FORMAT(pl.created_at, '%d.%m.%Y %H:%i') as data
                        FROM payment_logs pl
                        LEFT JOIN noteret n ON pl.user_id = n.id
                        WHERE pl.created_at BETWEEN ? AND ?
                        ORDER BY pl.created_at DESC");
                    } else {
                        $stmt = $pdo->prepare("SELECT 
                            pl.id, NULL as noter_id, 'N/A' as emri, '' as mbiemri, 150 as shuma, 
                            pl.status, pl.payment_method as metoda, 
                            pl.transaction_id as transaksioni,
                            DATE_FORMAT(pl.created_at, '%d.%m.%Y %H:%i') as data
                        FROM payment_logs pl
                        WHERE pl.created_at BETWEEN ? AND ?
                        ORDER BY pl.created_at DESC");
                    }
                } else {
                    if ($noterIdColumn) {
                        $stmt = $pdo->prepare("SELECT 
                            pl.id, pl.noter_id, n.emri, n.mbiemri, 150 as shuma, 
                            'completed' as status, pl.payment_method as metoda, 
                            pl.transaction_id as transaksioni,
                            DATE_FORMAT(pl.created_at, '%d.%m.%Y %H:%i') as data
                        FROM payment_logs pl
                        LEFT JOIN noteret n ON pl.noter_id = n.id
                        WHERE pl.created_at BETWEEN ? AND ?
                        ORDER BY pl.created_at DESC");
                    } else if ($userIdColumn) {
                        $stmt = $pdo->prepare("SELECT 
                            pl.id, pl.user_id as noter_id, n.emri, n.mbiemri, 150 as shuma, 
                            'completed' as status, pl.payment_method as metoda, 
                            pl.transaction_id as transaksioni,
                            DATE_FORMAT(pl.created_at, '%d.%m.%Y %H:%i') as data
                        FROM payment_logs pl
                        LEFT JOIN noteret n ON pl.user_id = n.id
                        WHERE pl.created_at BETWEEN ? AND ?
                        ORDER BY pl.created_at DESC");
                    } else {
                        $stmt = $pdo->prepare("SELECT 
                            pl.id, NULL as noter_id, 'N/A' as emri, '' as mbiemri, 150 as shuma, 
                            'completed' as status, pl.payment_method as metoda, 
                            pl.transaction_id as transaksioni,
                            DATE_FORMAT(pl.created_at, '%d.%m.%Y %H:%i') as data
                        FROM payment_logs pl
                        WHERE pl.created_at BETWEEN ? AND ?
                        ORDER BY pl.created_at DESC");
                    }
                }

                $stmt->execute([$data_fillimit, $data_mbarimit]);
                $pagesat = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Statistikat e pagesave
                $stmt = $pdo->prepare("SELECT 
                    COUNT(*) as total, 
                    COUNT(*) * 150 as shuma_total, 
                    150 as mesatare
                FROM payment_logs 
                WHERE created_at BETWEEN ? AND ?");
                $stmt->execute([$data_fillimit, $data_mbarimit]);
                $statistikat = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            break;
            
        case 'aktiviteti':
            // Raporti i aktivitetit të sistemit
            $aktiviteti = [];
            $statistikat = ['session_count' => 0, 'avg_duration' => 0, 'uploads' => 0];
            
            if (tableExists($pdo, 'session_logs')) {
                $stmt = $pdo->prepare("SELECT 
                    sl.id, sl.user_id, sl.user_type, sl.ip_address,
                    DATE_FORMAT(sl.login_time, '%d.%m.%Y %H:%i') as login_time,
                    DATE_FORMAT(sl.logout_time, '%d.%m.%Y %H:%i') as logout_time,
                    TIMESTAMPDIFF(MINUTE, sl.login_time, IFNULL(sl.logout_time, NOW())) as duration_minutes
                FROM session_logs sl
                WHERE sl.login_time BETWEEN ? AND ?
                ORDER BY sl.login_time DESC");
                $stmt->execute([$data_fillimit, $data_mbarimit]);
                $aktiviteti = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Statistikat e seancave
                $stmt = $pdo->prepare("SELECT 
                    COUNT(*) as session_count,
                    AVG(TIMESTAMPDIFF(MINUTE, login_time, IFNULL(logout_time, NOW()))) as avg_duration
                FROM session_logs
                WHERE login_time BETWEEN ? AND ?");
                $stmt->execute([$data_fillimit, $data_mbarimit]);
                $sessionStats = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $statistikat['session_count'] = $sessionStats['session_count'] ?? 0;
                $statistikat['avg_duration'] = $sessionStats['avg_duration'] ?? 0;
            }
            
            // Numri i dokumentave të ngarkuara
            if (tableExists($pdo, 'uploaded_files')) {
                $stmt = $pdo->prepare("SELECT COUNT(*) as uploads FROM uploaded_files WHERE upload_date BETWEEN ? AND ?");
                $stmt->execute([$data_fillimit, $data_mbarimit]);
                $uploadStats = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $statistikat['uploads'] = $uploadStats['uploads'] ?? 0;
            }
            break;
            
        case 'abonimet':
            // Raporti i abonimeve
            $abonimet = [];
            $statistikat = ['total' => 0, 'aktive' => 0, 'te_ardhura' => 0];

            if (tableExists($pdo, 'noteri_abonimet') && tableExists($pdo, 'abonimet') && tableExists($pdo, 'noteret')) {
                // Kontrollo nëse ekziston kolona 'status' te noteri_abonimet
                $abonimStatusColumn = false;
                try {
                    $checkAbonimCol = $pdo->query("SHOW COLUMNS FROM noteri_abonimet");
                    $abonimCols = $checkAbonimCol->fetchAll(PDO::FETCH_COLUMN, 0);
                    $abonimStatusColumn = in_array('status', $abonimCols);
                } catch (PDOException $e) {
                    $abonimStatusColumn = false;
                }

                // Query për të marrë abonimet
                $selectFields = "na.id, n.emri, n.mbiemri, a.emri as plani, na.paguar as shuma, na.menyra_pageses as metoda, DATE_FORMAT(na.data_fillimit, '%d.%m.%Y') as data_fillimit, DATE_FORMAT(na.data_mbarimit, '%d.%m.%Y') as data_mbarimit";
                if ($abonimStatusColumn) {
                    $selectFields .= ", na.status";
                } else {
                    $selectFields .= ", 'aktiv' as status";
                }
                $stmt = $pdo->prepare("SELECT $selectFields FROM noteri_abonimet na JOIN noteret n ON na.noter_id = n.id JOIN abonimet a ON na.abonim_id = a.id WHERE na.data_fillimit BETWEEN ? AND ? ORDER BY na.data_fillimit DESC");
                $stmt->execute([$data_fillimit, $data_mbarimit]);
                $abonimet = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Statistikat e abonimeve
                if ($abonimStatusColumn) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'aktiv' THEN 1 ELSE 0 END) as aktive, SUM(paguar) as te_ardhura FROM noteri_abonimet WHERE data_fillimit BETWEEN ? AND ?");
                } else {
                    $stmt = $pdo->prepare("SELECT COUNT(*) as total, COUNT(*) as aktive, SUM(paguar) as te_ardhura FROM noteri_abonimet WHERE data_fillimit BETWEEN ? AND ?");
                }
                $stmt->execute([$data_fillimit, $data_mbarimit]);
                $statistikat = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            break;
    }
} catch (PDOException $e) {
    $error = "Gabim në gjenerimin e raportit: " . $e->getMessage();
}

// Funksioni për eksportimin në PDF (demo)
function eksportoNePDF($titulli, $te_dhenat) {
    // Këtu do të implementohet logjika e eksportimit në PDF
    // Për qëllime demo, thjesht kthejmë true
    return true;
}

// Funksioni për eksportimin në Excel (demo)
function eksortoNeExcel($titulli, $te_dhenat) {
    // Këtu do të implementohet logjika e eksportimit në Excel
    // Për qëllime demo, thjesht kthejmë true
    return true;
}

// Nëse kërkohet eksportimi, bëje atë
if ($format !== 'web') {
    $titulli = "Raporti i " . ucfirst($lloji_raportit) . " ({$data_fillimit} - {$data_mbarimit})";
    $te_dhenat = compact('perdoruesit', 'noteret', 'pagesat', 'aktiviteti', 'abonimet', 'statistikat');
    
    if ($format === 'pdf') {
        eksportoNePDF($titulli, $te_dhenat);
        header("Location: {$_SERVER['PHP_SELF']}?lloji={$lloji_raportit}&data_fillimit={$data_fillimit}&data_mbarimit={$data_mbarimit}&exported=pdf");
        exit();
    } elseif ($format === 'excel') {
        eksortoNeExcel($titulli, $te_dhenat);
        header("Location: {$_SERVER['PHP_SELF']}?lloji={$lloji_raportit}&data_fillimit={$data_fillimit}&data_mbarimit={$data_mbarimit}&exported=excel");
        exit();
    }
}

// Nëse kemi një mesazh për eksportim të suksesshëm
$exported = isset($_GET['exported']) ? $_GET['exported'] : null;
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raportet - Noteria Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
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
            margin-bottom: 2rem;
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
        
        .card {
            background-color: var(--card-bg);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            margin-bottom: 2rem;
            border: 1px solid var(--border);
            overflow: hidden;
        }
        
        .card-header {
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
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
            padding: 1.5rem;
        }
        
        .card-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .tabs {
            display: flex;
            border-bottom: 1px solid var(--border);
            margin-bottom: 2rem;
            overflow-x: auto;
            white-space: nowrap;
            -webkit-overflow-scrolling: touch;
        }
        
        .tab {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            color: var(--text);
            text-decoration: none;
            font-weight: 500;
            position: relative;
            margin-right: 0.5rem;
            transition: var(--transition);
        }
        
        .tab.active {
            color: var(--primary);
        }
        
        .tab.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 100%;
            height: 3px;
            background-color: var(--primary);
        }
        
        .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding: 1.25rem;
            background-color: var(--light);
            border-radius: var(--radius);
        }
        
        .form-group {
            flex: 1;
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
            background-color: white;
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
        
        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
        }
        
        .table-responsive {
            overflow-x: auto;
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
            white-space: nowrap;
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
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 50rem;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
        }
        
        .badge-success {
            background-color: #d1fae5;
            color: #065f46;
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
        
        .export-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        
        .export-btn {
            padding: 0.5rem;
            background-color: white;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            color: var(--text-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .export-btn:hover {
            background-color: var(--light);
            color: var(--primary);
        }
        
        .export-btn i {
            font-size: 1.25rem;
        }
        
        .stats-summary {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-item {
            background-color: var(--card-bg);
            padding: 1.25rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
        }
        
        .stat-label {
            color: var(--text-light);
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--heading);
        }
        
        .no-data {
            text-align: center;
            padding: 3rem 1.5rem;
            color: var(--text-light);
        }
        
        .no-data i {
            font-size: 3rem;
            color: var(--border);
            margin-bottom: 1rem;
        }
        
        .no-data p {
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }
        
        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
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
        
        .report-header {
            margin-bottom: 1.5rem;
            padding: 1.5rem;
            background-color: var(--card-bg);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
        }
        
        .report-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--heading);
            margin-bottom: 0.75rem;
        }
        
        .report-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 2rem;
            color: var(--text-light);
        }
        
        .report-meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .report-meta-item i {
            color: var(--primary);
        }
        
        @media (max-width: 768px) {
            .admin-nav {
                display: none;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .filters {
                flex-direction: column;
            }
            
            .stats-summary {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header class="admin-header">
        <div class="container">
            <a href="admin_settings_view.php" class="admin-logo">
                <i class="fas fa-gavel"></i>
                Noteria Admin
            </a>
            
            <nav class="admin-nav">
                <a href="admin_settings_view.php" class="admin-nav-item">
                    <i class="fas fa-tachometer-alt"></i> Paneli
                </a>
                <a href="noteret.php" class="admin-nav-item">
                    <i class="fas fa-user-tie"></i> Noterët
                </a>
                <a href="statistikat.php" class="admin-nav-item">
                    <i class="fas fa-chart-line"></i> Statistikat
                </a>
                <a href="abonimet.php" class="admin-nav-item">
                    <i class="fas fa-receipt"></i> Abonimet
                </a>
                <a href="raportet.php" class="admin-nav-item active">
                    <i class="fas fa-file-alt"></i> Raportet
                </a>
                <a href="admin_settings_view.php" class="admin-nav-item">
                    <i class="fas fa-cog"></i> Cilësimet
                </a>
                <a href="logout.php" class="admin-nav-item">
                    <i class="fas fa-sign-out-alt"></i> Dil
                </a>
            </nav>
        </div>
    </header>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-file-alt"></i> Raportet e Sistemit
            </h1>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <div><?php echo $error; ?></div>
            </div>
        <?php endif; ?>
        
        <?php if ($exported): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <div>Raporti u eksportua me sukses në formatin <?php echo strtoupper($exported); ?>!</div>
            </div>
        <?php endif; ?>
        
        <div class="tabs">
            <a href="?lloji=perdoruesit" class="tab <?php echo $lloji_raportit === 'perdoruesit' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i> Përdoruesit
            </a>
            <a href="?lloji=pagesat" class="tab <?php echo $lloji_raportit === 'pagesat' ? 'active' : ''; ?>">
                <i class="fas fa-credit-card"></i> Pagesat
            </a>
            <a href="?lloji=aktiviteti" class="tab <?php echo $lloji_raportit === 'aktiviteti' ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i> Aktiviteti
            </a>
            <a href="?lloji=abonimet" class="tab <?php echo $lloji_raportit === 'abonimet' ? 'active' : ''; ?>">
                <i class="fas fa-receipt"></i> Abonimet
            </a>
        </div>
        
        <!-- Filtrat e raportit -->
        <form class="filters" action="" method="get">
            <input type="hidden" name="lloji" value="<?php echo htmlspecialchars($lloji_raportit); ?>">
            
            <div class="form-group">
                <label class="form-label" for="data_fillimit">Nga data</label>
                <input type="text" class="form-control datepicker" id="data_fillimit" name="data_fillimit" value="<?php echo htmlspecialchars($data_fillimit); ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label" for="data_mbarimit">Deri më</label>
                <input type="text" class="form-control datepicker" id="data_mbarimit" name="data_mbarimit" value="<?php echo htmlspecialchars($data_mbarimit); ?>">
            </div>
            
            <div style="display: flex; align-items: flex-end;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i> Filtro
                </button>
            </div>
        </form>
        
        <!-- Koka e raportit -->
        <div class="report-header">
            <h2 class="report-title">
                <?php if ($lloji_raportit === 'perdoruesit'): ?>
                    Raporti i Përdoruesve
                <?php elseif ($lloji_raportit === 'pagesat'): ?>
                    Raporti i Pagesave
                <?php elseif ($lloji_raportit === 'aktiviteti'): ?>
                    Raporti i Aktivitetit
                <?php elseif ($lloji_raportit === 'abonimet'): ?>
                    Raporti i Abonimeve
                <?php endif; ?>
            </h2>
            
            <div class="report-meta">
                <div class="report-meta-item">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Periudha: <?php echo date('d.m.Y', strtotime($data_fillimit)); ?> - <?php echo date('d.m.Y', strtotime($data_mbarimit)); ?></span>
                </div>
                
                <div class="report-meta-item">
                    <i class="fas fa-clock"></i>
                    <span>Gjeneruar më: <?php echo date('d.m.Y H:i'); ?></span>
                </div>
                
                <div class="report-meta-item">
                    <i class="fas fa-user"></i>
                    <span>
                        Nga: 
                        <?php 
                            if (isset($_SESSION['emri']) && isset($_SESSION['mbiemri'])) {
                                echo htmlspecialchars($_SESSION['emri'] . ' ' . $_SESSION['mbiemri']);
                            } else {
                                echo 'Admin';
                            }
                        ?>
                    </span>
                </div>
            </div>
        </div>
        
        <?php if ($lloji_raportit === 'perdoruesit'): ?>
            <!-- Raporti i përdoruesve -->
            <div class="stats-summary">
                <div class="stat-item">
                    <div class="stat-label">Përdorues të rinj</div>
                    <div class="stat-value"><?php echo count($perdoruesit); ?></div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-label">Noterë të rinj</div>
                    <div class="stat-value"><?php echo count($noteret); ?></div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-label">Hyrje të suksesshme</div>
                    <div class="stat-value"><?php echo $loginStats['success']; ?></div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-label">Hyrje të dështuara</div>
                    <div class="stat-value"><?php echo $loginStats['failed']; ?></div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-users"></i> Përdoruesit e rinj</h3>
                    
                    <div class="export-actions">
                        <a href="?lloji=<?php echo $lloji_raportit; ?>&data_fillimit=<?php echo $data_fillimit; ?>&data_mbarimit=<?php echo $data_mbarimit; ?>&format=pdf" class="export-btn" title="Eksporto në PDF">
                            <i class="fas fa-file-pdf"></i>
                        </a>
                        <a href="?lloji=<?php echo $lloji_raportit; ?>&data_fillimit=<?php echo $data_fillimit; ?>&data_mbarimit=<?php echo $data_mbarimit; ?>&format=excel" class="export-btn" title="Eksporto në Excel">
                            <i class="fas fa-file-excel"></i>
                        </a>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <?php if (count($perdoruesit) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Emri</th>
                                    <th>Email</th>
                                    <th>Roli</th>
                                    <th>Data e regjistrimit</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($perdoruesit as $perdorues): ?>
                                    <tr>
                                        <td><?php echo $perdorues['id']; ?></td>
                                        <td><?php echo htmlspecialchars($perdorues['emri'] . ' ' . $perdorues['mbiemri']); ?></td>
                                        <td><?php echo htmlspecialchars($perdorues['email']); ?></td>
                                        <td>
                                            <span class="badge badge-info">
                                                <?php echo htmlspecialchars(ucfirst($perdorues['roli'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $perdorues['data_regjistrimit']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-users"></i>
                            <p>Nuk u gjetën përdorues për periudhën e zgjedhur</p>
                            <span>Provo të ndryshosh datat e filtrimit</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-user-tie"></i> Noterët e rinj</h3>
                </div>
                
                <div class="table-responsive">
                    <?php if (count($noteret) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Emri</th>
                                    <th>Email</th>
                                    <th>Data e regjistrimit</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($noteret as $noter): ?>
                                    <tr>
                                        <td><?php echo $noter['id']; ?></td>
                                        <td><?php echo htmlspecialchars($noter['emri'] . ' ' . $noter['mbiemri']); ?></td>
                                        <td><?php echo htmlspecialchars($noter['email']); ?></td>
                                        <td><?php echo $noter['data_regjistrimit']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-user-tie"></i>
                            <p>Nuk u gjetën noterë të rinj për periudhën e zgjedhur</p>
                            <span>Provo të ndryshosh datat e filtrimit</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
        <?php elseif ($lloji_raportit === 'pagesat'): ?>
            <!-- Raporti i pagesave -->
            <div class="stats-summary">
                <div class="stat-item">
                    <div class="stat-label">Numri i pagesave</div>
                    <div class="stat-value"><?php echo number_format($statistikat['total']); ?></div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-label">Shuma totale</div>
                    <div class="stat-value"><?php echo number_format($statistikat['shuma_total'], 2); ?> €</div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-label">Pagesa mesatare</div>
                    <div class="stat-value"><?php echo number_format($statistikat['mesatare'], 2); ?> €</div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-credit-card"></i> Lista e pagesave</h3>
                    
                    <div class="export-actions">
                        <a href="?lloji=<?php echo $lloji_raportit; ?>&data_fillimit=<?php echo $data_fillimit; ?>&data_mbarimit=<?php echo $data_mbarimit; ?>&format=pdf" class="export-btn" title="Eksporto në PDF">
                            <i class="fas fa-file-pdf"></i>
                        </a>
                        <a href="?lloji=<?php echo $lloji_raportit; ?>&data_fillimit=<?php echo $data_fillimit; ?>&data_mbarimit=<?php echo $data_mbarimit; ?>&format=excel" class="export-btn" title="Eksporto në Excel">
                            <i class="fas fa-file-excel"></i>
                        </a>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <?php if (!empty($pagesat)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Noter</th>
                                    <th>Shuma</th>
                                    <th>Statusi</th>
                                    <th>Metoda</th>
                                    <th>Transaksioni</th>
                                    <th>Data</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pagesat as $pagesa): ?>
                                    <tr>
                                        <td><?php echo $pagesa['id']; ?></td>
                                        <td>
                                            <?php 
                                                if (!empty($pagesa['emri']) && !empty($pagesa['mbiemri'])) {
                                                    echo htmlspecialchars($pagesa['emri'] . ' ' . $pagesa['mbiemri']);
                                                } else {
                                                    echo 'Noter ID: ' . $pagesa['noter_id'];
                                                }
                                            ?>
                                        </td>
                                        <td><?php echo number_format($pagesa['shuma'], 2); ?> €</td>
                                        <td>
                                            <?php
                                                $status = isset($pagesa['status']) ? $pagesa['status'] : 'completed';
                                                $badge_class = 'badge-info';
                                                if ($status === 'completed' || $status === 'success') {
                                                    $badge_class = 'badge-success';
                                                } elseif ($status === 'failed' || $status === 'error') {
                                                    $badge_class = 'badge-danger';
                                                } elseif ($status === 'pending') {
                                                    $badge_class = 'badge-warning';
                                                }
                                            ?>
                                            <span class="badge <?php echo $badge_class; ?>">
                                                <?php echo htmlspecialchars(ucfirst($status)); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($pagesa['metoda']); ?></td>
                                        <td><?php echo htmlspecialchars($pagesa['transaksioni']); ?></td>
                                        <td><?php echo $pagesa['data']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-credit-card"></i>
                            <p>Nuk u gjetën pagesa për periudhën e zgjedhur</p>
                            <span>Provo të ndryshosh datat e filtrimit</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
        <?php elseif ($lloji_raportit === 'aktiviteti'): ?>
            <!-- Raporti i aktivitetit -->
            <div class="stats-summary">
                <div class="stat-item">
                    <div class="stat-label">Seanca totale</div>
                    <div class="stat-value"><?php echo number_format($statistikat['session_count']); ?></div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-label">Kohëzgjatja mesatare</div>
                    <div class="stat-value"><?php echo number_format($statistikat['avg_duration']); ?> min</div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-label">Dokumenta të ngarkuara</div>
                    <div class="stat-value"><?php echo number_format($statistikat['uploads']); ?></div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-user-clock"></i> Seancat e përdoruesve</h3>
                    
                    <div class="export-actions">
                        <a href="?lloji=<?php echo $lloji_raportit; ?>&data_fillimit=<?php echo $data_fillimit; ?>&data_mbarimit=<?php echo $data_mbarimit; ?>&format=pdf" class="export-btn" title="Eksporto në PDF">
                            <i class="fas fa-file-pdf"></i>
                        </a>
                        <a href="?lloji=<?php echo $lloji_raportit; ?>&data_fillimit=<?php echo $data_fillimit; ?>&data_mbarimit=<?php echo $data_mbarimit; ?>&format=excel" class="export-btn" title="Eksporto në Excel">
                            <i class="fas fa-file-excel"></i>
                        </a>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <?php if (!empty($aktiviteti)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Përdoruesi</th>
                                    <th>Roli</th>
                                    <th>IP Adresa</th>
                                    <th>Hyrja</th>
                                    <th>Dalja</th>
                                    <th>Kohëzgjatja</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($aktiviteti as $seance): ?>
                                    <tr>
                                        <td><?php echo $seance['id']; ?></td>
                                        <td>
                                            <?php echo 'ID: ' . $seance['user_id']; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars(ucfirst($seance['user_type'] ?? 'Përdorues')); ?></td>
                                        <td><?php echo htmlspecialchars($seance['ip_address']); ?></td>
                                        <td><?php echo $seance['login_time']; ?></td>
                                        <td>
                                            <?php echo $seance['logout_time'] ? $seance['logout_time'] : 'Aktiv'; ?>
                                        </td>
                                        <td><?php echo $seance['duration_minutes']; ?> min</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-user-clock"></i>
                            <p>Nuk u gjetën të dhëna aktiviteti për periudhën e zgjedhur</p>
                            <span>Provo të ndryshosh datat e filtrimit</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
        <?php elseif ($lloji_raportit === 'abonimet'): ?>
            <!-- Raporti i abonimeve -->
            <div class="stats-summary">
                <div class="stat-item">
                    <div class="stat-label">Abonimi totale</div>
                    <div class="stat-value"><?php echo number_format($statistikat['total']); ?></div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-label">Abonime aktive</div>
                    <div class="stat-value"><?php echo number_format($statistikat['aktive']); ?></div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-label">Të ardhurat</div>
                    <div class="stat-value"><?php echo number_format($statistikat['te_ardhura'], 2); ?> €</div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-receipt"></i> Lista e abonimeve</h3>
                    
                    <div class="export-actions">
                        <a href="?lloji=<?php echo $lloji_raportit; ?>&data_fillimit=<?php echo $data_fillimit; ?>&data_mbarimit=<?php echo $data_mbarimit; ?>&format=pdf" class="export-btn" title="Eksporto në PDF">
                            <i class="fas fa-file-pdf"></i>
                        </a>
                        <a href="?lloji=<?php echo $lloji_raportit; ?>&data_fillimit=<?php echo $data_fillimit; ?>&data_mbarimit=<?php echo $data_mbarimit; ?>&format=excel" class="export-btn" title="Eksporto në Excel">
                            <i class="fas fa-file-excel"></i>
                        </a>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <?php if (!empty($abonimet)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Noter</th>
                                    <th>Plani</th>
                                    <th>Fillimi</th>
                                    <th>Mbarimi</th>
                                    <th>Statusi</th>
                                    <th>Paguar</th>
                                    <th>Metoda</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($abonimet as $abonim): ?>
                                    <tr>
                                        <td><?php echo $abonim['id']; ?></td>
                                        <td><?php echo htmlspecialchars($abonim['emri'] . ' ' . $abonim['mbiemri']); ?></td>
                                        <td><?php echo htmlspecialchars($abonim['plani']); ?></td>
                                        <td><?php echo $abonim['data_fillimit']; ?></td>
                                        <td><?php echo $abonim['data_mbarimit']; ?></td>
                                        <td>
                                            <?php
                                                $status = isset($abonim['status']) ? $abonim['status'] : 'aktiv';
                                                $badge_class = 'badge-info';
                                                if ($status === 'aktiv') {
                                                    $badge_class = 'badge-success';
                                                } elseif ($status === 'skaduar') {
                                                    $badge_class = 'badge-danger';
                                                } elseif ($status === 'anuluar') {
                                                    $badge_class = 'badge-warning';
                                                }
                                            ?>
                                            <span class="badge <?php echo $badge_class; ?>">
                                                <?php echo htmlspecialchars(ucfirst($status)); ?>
                                            </span>
                                        </td>
                                        <td><?php echo number_format($abonim['shuma'], 2); ?> €</td>
                                        <td><?php echo htmlspecialchars($abonim['metoda']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-receipt"></i>
                            <p>Nuk u gjetën abonime për periudhën e zgjedhur</p>
                            <span>Provo të ndryshosh datat e filtrimit</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializo datepicker
            flatpickr('.datepicker', {
                dateFormat: 'Y-m-d',
                altInput: true,
                altFormat: 'd.m.Y'
            });
        });
    </script>
</body>
</html>