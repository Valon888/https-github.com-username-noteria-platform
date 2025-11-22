<?php
// Kontrollojmë nëse sesioni është i hapur para se të fillojmë një të ri
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Kontrollo nëse përdoruesi është i autentikuar dhe ka rolin admin
if (!isset($_SESSION["auth_test"]) && !isset($_SESSION["admin_id"]) && !isset($_SESSION["zyra_id"])) {
    header("Location: test_login_easy.php");
    exit();
}

// Set flag if user is already registered
$isUserRegistered = isset($_SESSION["zyra_id"]);

// Lidhja me databazën
require_once 'config.php';

// Kontrollo nëse tabela abonimet ekziston
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'abonimet'");
    $abonimet_table_exists = ($stmt->rowCount() > 0);
    
    if (!$abonimet_table_exists) {
        // Krijo tabelën abonimet
        $sql = "CREATE TABLE `abonimet` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `emri` VARCHAR(255) NOT NULL,
            `cmimi` DECIMAL(10,2) NOT NULL,
            `kohezgjatja` INT(11) NOT NULL COMMENT 'Në muaj',
            `pershkrimi` TEXT,
            `karakteristikat` TEXT,
            `status` ENUM('aktiv', 'joaktiv') NOT NULL DEFAULT 'aktiv',
            `krijuar_me` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `perditesuar_me` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $pdo->exec($sql);
        
        // Shto të dhëna demo
        $demo_data = [
            ['Abonim Mujor', '150.00', 1, 'Abonim mujor me qasje të plotë në platformë', '["Qasje e plotë në platformë", "Dokumente të pakufizuara", "Mbështetje prioritare 24/7", "Të gjitha shërbimet e platformës", "Mjete të avancuara për noterë"]', 'aktiv'],
            ['Abonim Vjetor', '1500.00', 12, 'Abonim vjetor me qasje të plotë në platformë', '["Qasje e plotë në platformë", "Dokumente të pakufizuara", "Mbështetje prioritare 24/7", "Të gjitha shërbimet e platformës", "Mjete të avancuara për noterë", "Kurseni 300€ me pagesën vjetore", "Trajnime personale", "Këshillime ligjore mujore të përfshira"]', 'aktiv']
        ];
        
        $insert_stmt = $pdo->prepare("INSERT INTO abonimet (emri, cmimi, kohezgjatja, pershkrimi, karakteristikat, status) VALUES (?, ?, ?, ?, ?, ?)");
        
        foreach ($demo_data as $abonim) {
            $insert_stmt->execute($abonim);
        }
    }
    
    // Kontrollo nëse tabela noteri_abonimet ekziston
    $stmt = $pdo->query("SHOW TABLES LIKE 'noteri_abonimet'");
    $noteri_abonimet_table_exists = ($stmt->rowCount() > 0);
    
    if (!$noteri_abonimet_table_exists) {
        // Krijo tabelën noteri_abonimet
        $sql = "CREATE TABLE `noteri_abonimet` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `noter_id` INT(11) NOT NULL,
            `abonim_id` INT(11) NOT NULL,
            `data_fillimit` DATE NOT NULL,
            `data_mbarimit` DATE NOT NULL,
            `status` ENUM('aktiv', 'skaduar', 'anuluar') NOT NULL DEFAULT 'aktiv',
            `paguar` DECIMAL(10,2) NOT NULL,
            `menyra_pageses` VARCHAR(50) DEFAULT NULL,
            `transaksion_id` VARCHAR(255) DEFAULT NULL,
            `krijuar_me` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `noter_id` (`noter_id`),
            KEY `abonim_id` (`abonim_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $pdo->exec($sql);
        
        // Kontrollo nëse tabela noteret ekziston
        $stmt = $pdo->query("SHOW TABLES LIKE 'noteret'");
        if ($stmt->rowCount() > 0) {
            // Merr disa ID noterësh për të krijuar të dhëna demo
            $stmt = $pdo->query("SELECT id FROM noteret LIMIT 5");
            $noterIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (count($noterIds) > 0) {
                // Shto abonime demo për noterët
                $insert_stmt = $pdo->prepare("INSERT INTO noteri_abonimet (noter_id, abonim_id, data_fillimit, data_mbarimit, status, paguar, menyra_pageses, transaksion_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                
                foreach ($noterIds as $index => $noterId) {
                    $abonim_id = ($index % 4) + 1; // Alternoj mes 1-4 për abonime
                    $data_fillimit = date('Y-m-d', strtotime('-' . (rand(1, 30)) . ' days'));
                    $data_mbarimit = date('Y-m-d', strtotime('+' . (rand(30, 360)) . ' days'));
                    $status = (strtotime($data_mbarimit) > time()) ? 'aktiv' : 'skaduar';
                    $paguar = ($abonim_id == 1 || $abonim_id == 2 || $abonim_id == 3) ? 150.00 : 1800.00;
                    $menyra_pageses = ['Kartelë krediti', 'PayPal', 'Transfertë bankare'][rand(0, 2)];
                    $transaksion_id = 'TRX' . date('Ymd') . rand(1000, 9999);
                    
                    $insert_stmt->execute([$noterId, $abonim_id, $data_fillimit, $data_mbarimit, $status, $paguar, $menyra_pageses, $transaksion_id]);
                }
            }
        }
    }
    
    // Merr të dhënat për abonimet
    $abonimet = [];
    if ($abonimet_table_exists) {
        $stmt = $pdo->query("SELECT * FROM abonimet ORDER BY cmimi ASC");
        $abonimet = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Merr statistikat e abonimeve
    $statistikat = [
        'totali_abonimeve' => 0,
        'abonime_aktive' => 0,
        'te_ardhura_mujore' => 0,
        'te_ardhura_vjetore' => 0
    ];
    
    if ($noteri_abonimet_table_exists) {
        // Totali i abonimeve
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM noteri_abonimet");
        $statistikat['totali_abonimeve'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Abonime aktive
        $stmt = $pdo->query("SELECT COUNT(*) as active FROM noteri_abonimet WHERE status = 'aktiv'");
        $statistikat['abonime_aktive'] = $stmt->fetch(PDO::FETCH_ASSOC)['active'];
        
        // Të ardhurat mujore dhe vjetore
        $stmt = $pdo->query("SELECT 
            SUM(CASE WHEN na.status = 'aktiv' THEN a.cmimi ELSE 0 END) as mujore,
            SUM(CASE WHEN na.status = 'aktiv' THEN a.cmimi * 12 / a.kohezgjatja ELSE 0 END) as vjetore
            FROM noteri_abonimet na
            JOIN abonimet a ON na.abonim_id = a.id");
        $te_ardhurat = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $statistikat['te_ardhura_mujore'] = $te_ardhurat['mujore'] ?? 0;
        $statistikat['te_ardhura_vjetore'] = $te_ardhurat['vjetore'] ?? 0;
    }
    
    // Merr noterët me abonim aktiv
    $noteret_abonimet = [];
    
    if ($noteri_abonimet_table_exists) {
        $stmt = $pdo->prepare("
            SELECT 
                n.id as noter_id,
                n.emri,
                n.mbiemri,
                n.email,
                a.emri as abonim_emri,
                na.data_fillimit,
                na.data_mbarimit,
                na.status,
                na.paguar,
                na.menyra_pageses
            FROM noteri_abonimet na
            JOIN noteret n ON na.noter_id = n.id
            JOIN abonimet a ON na.abonim_id = a.id
            WHERE na.status = 'aktiv'
            ORDER BY na.data_mbarimit ASC
            LIMIT 10
        ");
        $stmt->execute();
        $noteret_abonimet = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (PDOException $e) {
    $error_message = "Gabim në lidhjen me databazën: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menaxhimi i Abonimeve - Noteria Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-light: #4895ef;
            --primary-dark: #3a0ca3;
            --secondary: #6c63ff;
            --success: #10b981;
            --success-light: #34d399;
            --info: #3db9dc;
            --warning: #f7b84b;
            --danger: #ef4444;
            --danger-light: #f87171;
            --light: #f8f9fa;
            --dark: #1f2937;
            --body-bg: #f8fafc;
            --card-bg: #ffffff;
            --text: #4b5563;
            --text-light: #6b7280;
            --text-dark: #1e293b;
            --border: #e5e7eb;
            --heading: #0f172a;
            --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.04);
            --shadow: 0 4px 6px rgba(50, 50, 93, 0.11), 0 1px 3px rgba(0, 0, 0, 0.08);
            --shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.08), 0 10px 10px -5px rgba(0, 0, 0, 0.02);
            --shadow-hover: 0 25px 50px -12px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            --radius: 0.5rem;
            --radius-sm: 0.25rem;
            --radius-lg: 0.75rem;
            --radius-full: 9999px;
            --gradient-primary: linear-gradient(135deg, #4361ee 0%, #4895ef 100%);
            --gradient-secondary: linear-gradient(135deg, #6c63ff 0%, #8e8aff 100%);
            --gradient-success: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            --gradient-warning: linear-gradient(135deg, #f7b84b 0%, #ffcb80 100%);
            --gradient-danger: linear-gradient(135deg, #ef4444 0%, #f87171 100%);
            --blur-sm: blur(4px);
            --blur: blur(8px);
            --blur-lg: blur(16px);
            --scrollbar-thumb: #c5c8d0;
            --scrollbar-track: #f1f1f1;
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
            overflow-x: hidden;
        }
        
        .container {
            width: 100%;
            max-width: 1320px;
            margin: 0 auto;
            padding: 0 1rem;
        }
        
        .admin-header {
            background-color: var(--card-bg);
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.05);
            padding: 1.25rem 0;
            margin-bottom: 3rem;
            position: sticky;
            top: 0;
            z-index: 100;
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            background-color: rgba(255, 255, 255, 0.95);
            border-bottom: 1px solid rgba(0, 0, 0, 0.03);
        }
        
        .admin-header .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1400px;
        }
        
        .admin-logo {
            display: flex;
            align-items: center;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            text-decoration: none;
            letter-spacing: -0.02em;
            position: relative;
            padding: 0.25rem 0;
        }
        
        .admin-logo::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 3px;
            background-color: var(--primary);
            border-radius: 2px;
            transition: width 0.3s ease;
        }
        
        .admin-logo:hover::after {
            width: 100%;
        }
        
        .admin-logo i {
            margin-right: 0.75rem;
            font-size: 1.75rem;
            background: rgba(59, 93, 231, 0.1);
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
        }
        
        .admin-nav {
            display: flex;
            align-items: center;
        }
        
        .admin-nav-item {
            margin-left: 2rem;
            color: var(--text-dark);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
            display: flex;
            align-items: center;
            position: relative;
            padding: 0.5rem 0;
        }
        
        .admin-nav-item i {
            margin-right: 0.5rem;
            font-size: 1.1rem;
            transition: transform 0.3s ease;
        }
        
        .admin-nav-item:hover {
            color: var(--primary);
        }
        
        .admin-nav-item:hover i {
            transform: translateY(-2px);
        }
        
        .admin-nav-item.active {
            color: var(--primary);
            position: relative;
        }
        
        .admin-nav-item.active::after {
            content: '';
            position: absolute;
            bottom: -0.5rem;
            left: 0;
            width: 100%;
            height: 3px;
            background: var(--gradient-primary);
            border-radius: 5px;
            box-shadow: 0 2px 6px rgba(59, 93, 231, 0.25);
        }
        
        .page-header {
            margin-bottom: 2.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            position: relative;
            padding-bottom: 1.5rem;
        }
        
        .page-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100px;
            height: 4px;
            background: var(--gradient-primary);
            border-radius: 2px;
        }
        
        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--heading);
            display: flex;
            align-items: center;
            gap: 1rem;
            letter-spacing: -0.025em;
        }
        
        .page-title i {
            color: var(--primary);
            background: rgba(59, 93, 231, 0.1);
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            font-size: 1.5rem;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.85rem 1.75rem;
            font-size: 0.95rem;
            font-weight: 600;
            text-align: center;
            white-space: nowrap;
            vertical-align: middle;
            cursor: pointer;
            border: 1px solid transparent;
            border-radius: 50px;
            transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
            text-decoration: none;
            letter-spacing: 0.025em;
            box-shadow: 0 4px 6px rgba(50, 50, 93, 0.11), 0 1px 3px rgba(0, 0, 0, 0.08);
            position: relative;
            overflow: hidden;
            z-index: 1;
        }
        
        .btn::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.15);
            z-index: -1;
            transform: scale(0);
            transition: transform 0.5s ease;
            border-radius: 50px;
        }
        
        .btn:hover::after {
            transform: scale(2);
        }
        
        .btn-primary {
            color: white;
            background: var(--gradient-primary);
            border: none;
        }
        
        .btn-primary:hover {
            box-shadow: 0 7px 14px rgba(50, 50, 93, 0.15), 0 3px 6px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }
        
        .btn-primary:active {
            box-shadow: 0 3px 6px rgba(50, 50, 93, 0.1), 0 1px 3px rgba(0, 0, 0, 0.05);
            transform: translateY(1px);
        }
        
        .btn-success {
            color: white;
            background: var(--gradient-success);
            border: none;
        }
        
        .btn-success:hover {
            box-shadow: 0 7px 14px rgba(16, 185, 129, 0.2), 0 3px 6px rgba(0, 0, 0, 0.08);
            transform: translateY(-2px);
        }
        
        .btn-danger {
            color: white;
            background: var(--gradient-danger);
            border: none;
        }
        
        .btn-danger:hover {
            box-shadow: 0 7px 14px rgba(239, 68, 68, 0.2), 0 3px 6px rgba(0, 0, 0, 0.08);
            transform: translateY(-2px);
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            border-radius: 30px;
        }
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.75rem;
            margin-bottom: 3rem;
        }
        
        .stat-card {
            background-color: var(--card-bg);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            padding: 2rem;
            transition: all 0.5s cubic-bezier(0.165, 0.84, 0.44, 1);
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(0, 0, 0, 0.03);
        }
        
        .stat-card::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background-color: rgba(59, 93, 231, 0.03);
            z-index: 0;
        }
        
        .stat-card:hover {
            transform: translateY(-7px);
            box-shadow: var(--shadow-lg);
        }
        
        .stat-card:nth-child(1) .stat-icon {
            background: linear-gradient(135deg, rgba(59, 93, 231, 0.2) 0%, rgba(94, 128, 255, 0.2) 100%);
            color: var(--primary);
        }
        
        .stat-card:nth-child(2) .stat-icon {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.2) 0%, rgba(52, 211, 153, 0.2) 100%);
            color: var(--success);
        }
        
        .stat-card:nth-child(3) .stat-icon {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.2) 0%, rgba(103, 232, 249, 0.2) 100%);
            color: var(--info);
        }
        
        .stat-card:nth-child(4) .stat-icon {
            background: linear-gradient(135deg, rgba(247, 184, 75, 0.2) 0%, rgba(255, 203, 128, 0.2) 100%);
            color: var(--warning);
        }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            position: relative;
            z-index: 1;
        }
        
        .stat-title {
            color: var(--text-light);
            font-weight: 600;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }
        
        .stat-value {
            font-size: 2.25rem;
            font-weight: 700;
            color: var(--heading);
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
            letter-spacing: -0.025em;
        }
        
        .stat-subtitle {
            color: var(--text-light);
            font-size: 0.9rem;
            font-weight: 500;
            position: relative;
            z-index: 1;
        }
        
        .pricing-plans {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
            perspective: 1000px;
        }
        
        .pricing-card {
            background-color: var(--card-bg);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            padding: 2.5rem 2rem;
            transition: all 0.5s cubic-bezier(0.165, 0.84, 0.44, 1);
            display: flex;
            flex-direction: column;
            position: relative;
            z-index: 1;
            overflow: hidden;
            transform-style: preserve-3d;
            backface-visibility: hidden;
        }
        
        .pricing-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 100%);
            z-index: -1;
            opacity: 0;
            transition: opacity 0.5s ease;
        }
        
        .pricing-card::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: var(--gradient-primary);
            z-index: -2;
            transform: scale(0.98);
            opacity: 0;
            border-radius: var(--radius-lg);
            transition: all 0.5s ease;
        }
        
        .pricing-card:hover {
            transform: translateY(-15px) rotateX(5deg);
            box-shadow: var(--shadow-lg);
        }
        
        .pricing-card:hover::after {
            opacity: 1;
        }
        
        .pricing-card:hover::before {
            opacity: 0.05;
            transform: scale(1);
        }
        
        .pricing-card.popular {
            transform: translateY(-10px) scale(1.03);
            box-shadow: var(--shadow-lg);
            border: none;
            z-index: 2;
        }
        
        .pricing-card.popular::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: var(--gradient-primary);
            z-index: -2;
            opacity: 0.04;
            transform: scale(1);
            border-radius: var(--radius-lg);
        }
        
        .pricing-card.popular:hover {
            transform: translateY(-20px) rotateX(5deg) scale(1.03);
        }
        
        .pricing-card.popular .popular-badge {
            position: absolute;
            top: 12px;
            right: -32px;
            background: var(--gradient-primary);
            color: white;
            padding: 0.5rem 3rem;
            transform: rotate(45deg);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            z-index: 10;
        }
        
        .pricing-header {
            padding-bottom: 1.5rem;
            margin-bottom: 1.75rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            text-align: center;
            position: relative;
        }
        
        .pricing-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--heading);
            margin-bottom: 1rem;
            letter-spacing: -0.02em;
        }
        
        .pricing-price {
            font-size: 3.25rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 0.5rem;
            line-height: 1;
            letter-spacing: -0.03em;
            position: relative;
            display: inline-block;
        }
        
        .pricing-price span {
            font-size: 1.25rem;
            font-weight: 500;
            color: var(--text-light);
            position: relative;
            top: -1.5rem;
            margin-left: 0.2rem;
        }
        
        .pricing-duration {
            color: var(--text-light);
            font-size: 1rem;
            margin-top: 0.5rem;
            display: block;
            font-weight: 500;
        }
        
        .pricing-features {
            margin-bottom: 2rem;
            flex-grow: 1;
            position: relative;
        }
        
        .pricing-features::before {
            content: '';
            position: absolute;
            top: -20px;
            left: 50%;
            transform: translateX(-50%);
            width: 40px;
            height: 3px;
            background-color: var(--primary-light);
            border-radius: 5px;
            opacity: 0.5;
        }
        
        .feature-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 1rem;
            color: var(--text-dark);
            padding-left: 0.5rem;
        }
        
        .feature-item i {
            color: var(--primary);
            margin-right: 0.75rem;
            font-size: 1rem;
            margin-top: 0.2rem;
            background: rgba(59, 93, 231, 0.1);
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        
        .pricing-footer {
            margin-top: auto;
        }
        
        .btn-block {
            display: block;
            width: 100%;
        }
        
        .table-container {
            background-color: var(--card-bg);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            margin-bottom: 3rem;
            border: 1px solid rgba(0, 0, 0, 0.03);
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
        }
        
        .table-container:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-5px);
        }
        
        .table-header {
            padding: 1.75rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            background: linear-gradient(to right, rgba(59, 93, 231, 0.02), rgba(255, 255, 255, 0));
        }
        
        .table-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--heading);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            letter-spacing: -0.01em;
        }
        
        .table-title i {
            color: var(--primary);
            background: rgba(59, 93, 231, 0.1);
            width: 38px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            font-size: 1.2rem;
        }
        
        .table-responsive {
            overflow-x: auto;
            padding: 0.5rem 0;
        }
        
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        th {
            background-color: rgba(243, 244, 246, 0.5);
            font-weight: 600;
            text-align: left;
            color: var(--text-dark);
            padding: 1.25rem 1.75rem;
            white-space: nowrap;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            position: relative;
        }
        
        th:first-child {
            border-top-left-radius: 8px;
            border-bottom-left-radius: 8px;
        }
        
        th:last-child {
            border-top-right-radius: 8px;
            border-bottom-right-radius: 8px;
        }
        
        td {
            padding: 1.25rem 1.75rem;
            border-top: 1px solid rgba(0, 0, 0, 0.03);
            color: var(--text-dark);
            font-size: 0.95rem;
            vertical-align: middle;
            transition: background-color 0.2s ease;
        }
        
        tbody tr {
            transition: all 0.3s ease;
        }
        
        tbody tr:hover {
            background-color: rgba(243, 244, 246, 0.5);
            transform: scale(1.005);
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.03);
            z-index: 5;
            position: relative;
        }
        
        tbody tr:hover td {
            color: var(--primary-dark);
        }
        
        .table-footer {
            padding: 1.25rem 1.75rem;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(to right, rgba(59, 93, 231, 0.02), rgba(255, 255, 255, 0));
        }
        
        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.4em 0.8em;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 50px;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            letter-spacing: 0.025em;
        }
        
        .badge i {
            margin-right: 0.3rem;
            font-size: 0.7rem;
        }
        
        .badge-success {
            background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            color: white;
        }
        
        .badge-warning {
            background: linear-gradient(135deg, #f7b84b 0%, #ffcb80 100%);
            color: #7c2d12;
        }
        
        .badge-danger {
            background: linear-gradient(135deg, #ef4444 0%, #f87171 100%);
            color: white;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(15, 23, 42, 0.65);
            z-index: 1000;
            overflow-y: auto;
            padding: 2rem;
            backdrop-filter: var(--blur);
            -webkit-backdrop-filter: var(--blur);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            opacity: 0;
            visibility: hidden;
        }
        
        .modal.show {
            opacity: 1;
            visibility: visible;
        }
        
        .modal-content {
            background-color: var(--card-bg);
            border-radius: var(--radius-lg);
            max-width: 550px;
            margin: 3rem auto;
            box-shadow: var(--shadow-lg);
            position: relative;
            transform: translateY(-30px) scale(0.97);
            transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
            border: 1px solid rgba(0, 0, 0, 0.03);
            overflow: hidden;
        }
        
        .modal.show .modal-content {
            transform: translateY(0) scale(1);
        }
        
        .modal-header {
            padding: 1.75rem 2rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(to right, rgba(67, 97, 238, 0.04), rgba(255, 255, 255, 0));
        }
        
        .modal-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--heading);
            letter-spacing: -0.02em;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .modal-title i {
            color: var(--primary);
            background: rgba(59, 93, 231, 0.1);
            width: 38px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            font-size: 1.2rem;
        }
        
        .modal-close {
            background: rgba(0, 0, 0, 0.05);
            border: none;
            color: var(--text-dark);
            font-size: 1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            transition: all 0.3s ease;
        }
        
        .modal-close:hover {
            background-color: var(--light);
            color: var(--danger);
            transform: rotate(90deg);
        }
        
        .modal-body {
            padding: 2rem;
        }
        
        .modal-footer {
            padding: 1.5rem 2rem;
            background: rgba(243, 244, 246, 0.5);
            border-top: 1px solid rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.75rem;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-label i {
            color: var(--primary);
            font-size: 0.9rem;
        }
        
        .form-control {
            display: block;
            width: 100%;
            padding: 0.85rem 1.25rem;
            font-size: 0.95rem;
            font-weight: 500;
            line-height: 1.5;
            color: var(--text-dark);
            background-color: #fff;
            background-clip: padding-box;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            transition: all 0.3s ease;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
        }
        
        .form-control:focus {
            border-color: var(--primary-light);
            outline: 0;
            box-shadow: 0 0 0 4px rgba(59, 93, 231, 0.15);
        }
        
        .form-control::placeholder {
            color: var(--text-light);
            opacity: 0.7;
        }
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        .alert {
            padding: 1.25rem 1.75rem;
            border-radius: var(--radius);
            margin-bottom: 1.75rem;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        .alert-danger {
            background: linear-gradient(to right, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.05));
            color: #991b1b;
            border-left: 4px solid var(--danger);
        }
        
        .alert i {
            font-size: 1.25rem;
            margin-top: 0.1rem;
        }
        
        .form-hint {
            font-size: 0.85rem;
            color: var(--text-light);
            margin-top: 0.5rem;
        }
        
        .admin-footer {
            background: var(--light);
            padding: 2rem 0;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
            margin-top: 4rem;
        }
        
        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .footer-copyright {
            color: var(--text-light);
            font-size: 0.9rem;
        }
        
        .footer-links {
            display: flex;
            gap: 1.5rem;
        }
        
        .footer-link {
            color: var(--text);
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .footer-link:hover {
            color: var(--primary);
        }
        
        @media (max-width: 768px) {
            .admin-nav {
                display: none;
            }
            
            .stats-cards, .pricing-plans {
                grid-template-columns: 1fr;
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
                <a href="abonimet.php" class="admin-nav-item active">
                    <i class="fas fa-receipt"></i> Abonimet
                </a>
                <a href="raportet.php" class="admin-nav-item">
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
                <i class="fas fa-receipt"></i> Menaxhimi i Abonimeve
            </h1>
            
            <button id="btnAddPlan" class="btn btn-primary">
                <i class="fas fa-plus"></i> Shto Plan të Ri
            </button>
        </div>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <div><?php echo $error_message; ?></div>
            </div>
        <?php endif; ?>
        
        <!-- Statistikat e abonimeve -->
        <div class="stats-cards">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Totali i Abonimeve</div>
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                </div>
                <div class="stat-value"><?php echo number_format($statistikat['totali_abonimeve']); ?></div>
                <div class="stat-subtitle">Abonimi mesatar: 3 muaj</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Abonime Aktive</div>
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                </div>
                <div class="stat-value"><?php echo number_format($statistikat['abonime_aktive']); ?></div>
                <div class="stat-subtitle"><?php echo ($statistikat['totali_abonimeve'] > 0) ? round($statistikat['abonime_aktive'] / $statistikat['totali_abonimeve'] * 100) : 0; ?>% e totalit</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Të Ardhura Mujore</div>
                    <div class="stat-icon"><i class="fas fa-euro-sign"></i></div>
                </div>
                <div class="stat-value"><?php echo number_format($statistikat['te_ardhura_mujore'], 2); ?> €</div>
                <div class="stat-subtitle">Nga abonimet aktive</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Të Ardhura Vjetore</div>
                    <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
                </div>
                <div class="stat-value"><?php echo number_format($statistikat['te_ardhura_vjetore'], 2); ?> €</div>
                <div class="stat-subtitle">Parashikim në bazë të abonimeve aktuale</div>
            </div>
        </div>
        
        <!-- Planet e abonimit -->
        <div class="table-container">
            <div class="table-header">
                <h2 class="table-title"><i class="fas fa-tags"></i> Planet e Abonimit</h2>
            </div>
            
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Emri</th>
                            <th>Çmimi</th>
                            <th>Kohëzgjatja</th>
                            <th>Statusi</th>
                            <th>Veprime</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($abonimet)): ?>
                            <?php foreach ($abonimet as $abonim): ?>
                                <tr>
                                    <td><?php echo $abonim['id']; ?></td>
                                    <td><?php echo htmlspecialchars($abonim['emri']); ?></td>
                                    <td><?php echo number_format($abonim['cmimi'], 2); ?> €</td>
                                    <td><?php echo $abonim['kohezgjatja']; ?> muaj</td>
                                    <td>
                                        <?php if ($abonim['status'] == 'aktiv'): ?>
                                            <span class="badge badge-success">Aktiv</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Joaktiv</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-primary btn-sm edit-plan" data-id="<?php echo $abonim['id']; ?>">
                                            <i class="fas fa-edit"></i> Edito
                                        </button>
                                        
                                        <?php if ($abonim['status'] == 'aktiv'): ?>
                                            <button class="btn btn-danger btn-sm deactivate-plan" data-id="<?php echo $abonim['id']; ?>">
                                                <i class="fas fa-ban"></i> Çaktivizo
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-success btn-sm activate-plan" data-id="<?php echo $abonim['id']; ?>">
                                                <i class="fas fa-check"></i> Aktivizo
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 2rem;">Nuk u gjetën plane abonimesh. Shtoni një plan të ri.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Kartat e çmimeve për përdoruesit -->
        <div class="pricing-section">
            <div class="section-header" style="text-align: center; margin-bottom: 3rem;">
                <span class="section-badge" style="background: rgba(59, 93, 231, 0.1); color: var(--primary); padding: 0.5rem 1.25rem; border-radius: 50px; font-weight: 600; font-size: 0.9rem; margin-bottom: 1rem; display: inline-block;">
                    <i class="fas fa-sparkles" style="margin-right: 0.5rem;"></i>Zgjidhni Planin Tuaj
                </span>
                <h2 style="margin-bottom: 1rem; font-size: 2.5rem; font-weight: 700; color: var(--heading); letter-spacing: -0.03em;">
                    Planet e Abonimit për Klientët
                </h2>
                <p style="color: var(--text-light); font-size: 1.1rem; max-width: 600px; margin: 0 auto;">
                    Zgjidhni planin që përshtatet më mirë me nevojat tuaja profesionale për të aksesuar të gjitha shërbimet e platformës Noteria
                </p>
            </div>
            
            <div class="pricing-plans">
                <?php
                // Set which plan should be marked as popular
                $is_yearly = false;
                $i = 0;
                
                foreach ($abonimet as $abonim):
                    if ($abonim['status'] != 'aktiv') continue; // Shfaq vetëm planet aktive
                    
                    // Mark yearly plan as popular
                    $is_popular = ($abonim['kohezgjatja'] == 12);
                    $i++;
                    
                    // Parse karakteristikat nga JSON
                    $features = [];
                    if (!empty($abonim['karakteristikat'])) {
                        $features = json_decode($abonim['karakteristikat'], true) ?: [];
                    }
                    
                    // Calculate monthly equivalent for yearly plans
                    $monthly_price = $abonim['cmimi'];
                    $savings = 0;
                    if ($abonim['kohezgjatja'] == 12) {
                        $monthly_price = $abonim['cmimi'] / 12;
                        $savings = (150 * 12) - $abonim['cmimi']; // 150 is the monthly price
                    }
                ?>
                    <div class="pricing-card <?php echo $is_popular ? 'popular' : ''; ?>">
                        <?php if ($is_popular): ?>
                            <div class="popular-badge">Më e Favorshme</div>
                        <?php endif; ?>
                        
                        <div class="pricing-header">
                            <div class="pricing-title"><?php echo htmlspecialchars($abonim['emri']); ?></div>
                            <div class="pricing-price">
                                <?php echo number_format($abonim['cmimi'], 0); ?><span>€</span>
                            </div>
                            <div class="pricing-duration">
                                <?php 
                                    if ($abonim['kohezgjatja'] == 1) {
                                        echo 'për muaj';
                                    } elseif ($abonim['kohezgjatja'] == 12) {
                                        echo 'për vit <span style="display: block; margin-top: 0.5rem; font-size: 0.85rem; color: var(--success);"><i class="fas fa-check-circle"></i> vetëm ' . number_format($monthly_price, 0) . '€ në muaj</span>';
                                    } else {
                                        echo "për {$abonim['kohezgjatja']} muaj";
                                    }
                                ?>
                            </div>
                        </div>
                        
                        <div class="pricing-features">
                            <?php if (!empty($features)): ?>
                                <?php foreach ($features as $index => $feature): ?>
                                    <div class="feature-item">
                                        <i class="fas fa-check"></i>
                                        <div><?php echo htmlspecialchars($feature); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="feature-item">
                                    <i class="fas fa-check"></i>
                                    <div>Qasje bazë në sistem</div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($abonim['kohezgjatja'] == 12 && $savings > 0): ?>
                                <div class="feature-item" style="margin-top: 1rem; background: rgba(16, 185, 129, 0.1); padding: 0.75rem; border-radius: 8px;">
                                    <i class="fas fa-piggy-bank" style="color: var(--success);"></i>
                                    <div style="font-weight: 600; color: var(--success);">Kurseni <?php echo number_format($savings, 0); ?>€ me pagesën vjetore</div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="pricing-footer">
                            <button class="btn btn-primary btn-block plan-select-btn" data-plan-id="<?php echo $abonim['id']; ?>" data-plan-name="<?php echo htmlspecialchars($abonim['emri']); ?>" data-plan-price="<?php echo $abonim['cmimi']; ?>" data-plan-duration="<?php echo $abonim['kohezgjatja']; ?>">
                                <?php if ($abonim['kohezgjatja'] == 1): ?>
                                    <i class="fas fa-credit-card"></i> Abonohu Tani
                                <?php else: ?>
                                    <i class="fas fa-check-circle"></i> Zgjedh Këtë Plan
                                <?php endif; ?>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if (count($abonimet) == 0): ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 3rem; background: var(--card-bg); border-radius: var(--radius-lg); border: 1px dashed rgba(59, 93, 231, 0.2); box-shadow: var(--shadow);">
                        <div style="width: 80px; height: 80px; background: rgba(59, 93, 231, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem;">
                            <i class="fas fa-info-circle" style="font-size: 2rem; color: var(--primary);"></i>
                        </div>
                        <h3 style="margin-bottom: 1rem; font-size: 1.5rem; font-weight: 700; color: var(--heading);">Nuk ka plane abonimesh aktualisht</h3>
                        <p style="color: var(--text-light); margin-bottom: 1.5rem;">Shtoni plane të reja abonimesh që të shfaqen këtu për klientët tuaj.</p>
                        <button class="btn btn-primary" id="btnAddPlanEmpty">
                            <i class="fas fa-plus"></i> Shto Plan të Ri
                        </button>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="pricing-faq" style="max-width: 800px; margin: 3rem auto 0; text-align: center;">
                <p style="font-size: 1.1rem; color: var(--text-dark); margin-bottom: 1.5rem; font-weight: 500;">
                    Të gjitha planet përfshijnë mbështetje teknike prioritare dhe përditësime të rregullta
                </p>
                <a href="#" class="btn btn-sm" style="background-color: var(--light); color: var(--text-dark); border: 1px solid var(--border);">
                    <i class="fas fa-question-circle"></i> Shiko Pyetjet e Shpeshta
                </a>
            </div>
        </div>
        
        <!-- Lista e Noterëve me Abonime Aktive -->
        <div class="table-container">
            <div class="table-header">
                <h2 class="table-title"><i class="fas fa-user-tie"></i> Noterët me Abonime Aktive</h2>
            </div>
            
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Noter</th>
                            <th>Email</th>
                            <th>Plani</th>
                            <th>Fillimi</th>
                            <th>Mbarimi</th>
                            <th>Pagesa</th>
                            <th>Metoda</th>
                            <th>Veprime</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($noteret_abonimet)): ?>
                            <?php foreach ($noteret_abonimet as $na): ?>
                                <?php 
                                    // Llogarit ditët e mbetura
                                    $now = time();
                                    $end_date = strtotime($na['data_mbarimit']);
                                    $days_left = ceil(($end_date - $now) / (60 * 60 * 24));
                                    
                                    $badge_class = 'badge-success';
                                    if ($days_left <= 7) {
                                        $badge_class = 'badge-warning';
                                    } elseif ($days_left <= 0) {
                                        $badge_class = 'badge-danger';
                                    }
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($na['emri'] . ' ' . $na['mbiemri']); ?></td>
                                    <td><?php echo htmlspecialchars($na['email']); ?></td>
                                    <td><?php echo htmlspecialchars($na['abonim_emri']); ?></td>
                                    <td><?php echo date('d.m.Y', strtotime($na['data_fillimit'])); ?></td>
                                    <td>
                                        <?php echo date('d.m.Y', strtotime($na['data_mbarimit'])); ?>
                                        <span class="badge <?php echo $badge_class; ?>">
                                            <?php 
                                                if ($days_left > 0) {
                                                    echo "$days_left ditë të mbetura";
                                                } else {
                                                    echo "Skaduar";
                                                }
                                            ?>
                                        </span>
                                    </td>
                                    <td><?php echo number_format($na['paguar'], 2); ?> €</td>
                                    <td><?php echo htmlspecialchars($na['menyra_pageses']); ?></td>
                                    <td>
                                        <button class="btn btn-primary btn-sm">
                                            <i class="fas fa-sync-alt"></i> Rinovimi
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 2rem;">Nuk u gjetën noterë me abonime aktive.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (!empty($noteret_abonimet)): ?>
                <div class="table-footer">
                    <div>Shfaqur 1-<?php echo min(10, count($noteret_abonimet)); ?> nga <?php echo count($noteret_abonimet); ?></div>
                    <div>
                        <button class="btn btn-sm btn-primary">Shfaq të gjitha</button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="admin-footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-copyright">
                    &copy; <?php echo date('Y'); ?> Noteria. Të gjitha të drejtat e rezervuara.
                </div>
                <div class="footer-links">
                    <a href="#" class="footer-link"><i class="fas fa-shield-alt"></i> Privatësia</a>
                    <a href="#" class="footer-link"><i class="fas fa-file-alt"></i> Kushtet</a>
                    <a href="#" class="footer-link"><i class="fas fa-question-circle"></i> Ndihmë</a>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Modalet -->
    <!-- Modali për shtimin e planit të ri -->
    <div id="addPlanModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-plus-circle"></i> Shto Plan Abonimesh të Ri</h3>
                <button class="modal-close"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <form id="addPlanForm">
                    <div class="form-group">
                        <label class="form-label" for="emri"><i class="fas fa-tag"></i> Emri i Planit</label>
                        <input type="text" class="form-control" id="emri" name="emri" placeholder="p.sh. Abonim Mujor" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="cmimi"><i class="fas fa-euro-sign"></i> Çmimi (€)</label>
                        <input type="number" class="form-control" id="cmimi" name="cmimi" min="0" step="0.01" placeholder="p.sh. 150.00" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="kohezgjatja"><i class="fas fa-calendar-alt"></i> Kohëzgjatja (muaj)</label>
                        <input type="number" class="form-control" id="kohezgjatja" name="kohezgjatja" min="1" placeholder="p.sh. 1 ose 12" required>
                        <div class="form-hint">Vendosni 1 për plane mujore, 12 për plane vjetore</div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="pershkrimi"><i class="fas fa-align-left"></i> Përshkrimi</label>
                        <textarea class="form-control" id="pershkrimi" name="pershkrimi" placeholder="Përshkrim i shkurtër i planit të abonimit"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="karakteristikat"><i class="fas fa-list-check"></i> Karakteristikat (një për rresht)</label>
                        <textarea class="form-control" id="karakteristikat" name="karakteristikat" rows="5" placeholder="Qasje në dokumentet bazë&#10;Deri në 10 dokumente në muaj&#10;Mbështetje me email"></textarea>
                        <div class="form-hint">Çdo rresht do të shfaqet si një pikë e veçantë në listën e karakteristikave</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" id="savePlan"><i class="fas fa-save"></i> Ruaj Planin</button>
                <button class="btn btn-secondary modal-close-btn" style="background-color: var(--light); color: var(--text-dark); border: 1px solid var(--border);"><i class="fas fa-times"></i> Anulo</button>
            </div>
        </div>
    </div>
    
    <!-- Modali i pagesës -->
    <div id="paymentModal" class="modal">
        <div class="modal-content" style="max-width: 650px;">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-credit-card"></i> Pagesa për Abonimin</h3>
                <button class="modal-close"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div id="selectedPlanInfo" class="selected-plan-info">
                    <div class="plan-badge"><i class="fas fa-tag"></i> <span id="planName"></span></div>
                    <div class="plan-price"><span id="planPrice"></span> €</div>
                    <div class="plan-duration"><span id="planDuration"></span></div>
                </div>
                
                <div class="payment-tabs">
                    <div class="tab-headers">
                        <div class="tab-header active" data-tab="card"><i class="fas fa-credit-card"></i> Kartelë Krediti</div>
                        <div class="tab-header" data-tab="bank"><i class="fas fa-university"></i> Transfertë Bankare</div>
                    </div>
                    
                    <div class="tab-content">
                        <div class="tab-pane active" id="cardTab">
                            <form id="cardPaymentForm">
                                <div class="form-group">
                                    <label class="form-label" for="cardHolder"><i class="fas fa-user"></i> Emri i mbajtësit</label>
                                    <input type="text" class="form-control" id="cardHolder" name="cardHolder" placeholder="Emri i plotë siç paraqitet në kartelë" required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="cardNumber"><i class="fas fa-credit-card"></i> Numri i kartelës</label>
                                    <input type="text" class="form-control" id="cardNumber" name="cardNumber" placeholder="1234 5678 9012 3456" required>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group half">
                                        <label class="form-label" for="expiryDate"><i class="fas fa-calendar-alt"></i> Data e skadimit</label>
                                        <input type="text" class="form-control" id="expiryDate" name="expiryDate" placeholder="MM/YY" required>
                                    </div>
                                    
                                    <div class="form-group half">
                                        <label class="form-label" for="cvv"><i class="fas fa-lock"></i> CVV/CVC</label>
                                        <input type="password" class="form-control" id="cvv" name="cvv" placeholder="123" required>
                                    </div>
                                </div>
                                
                                <div class="payment-security">
                                    <i class="fas fa-shield-alt"></i>
                                    <span>Pagesa e sigurtë me enkriptim SSL</span>
                                </div>
                            </form>
                        </div>
                        
                        <div class="tab-pane" id="bankTab">
                            <div class="bank-transfer-info">
                                <h4><i class="fas fa-info-circle"></i> Detajet e transfertës bankare</h4>
                                
                                <div class="bank-details">
                                    <div class="detail-row">
                                        <span class="detail-label">Përfituesi:</span>
                                        <span class="detail-value">Noteria SHPK</span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Banka:</span>
                                        <span class="detail-value">Raiffeisen Bank Kosova</span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">IBAN:</span>
                                        <span class="detail-value">XK051212012345678906</span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">SWIFT/BIC:</span>
                                        <span class="detail-value">RBKOXKPR</span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Shuma:</span>
                                        <span class="detail-value"><span id="bankTransferAmount"></span> €</span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Përshkrimi:</span>
                                        <span class="detail-value">Abonim Noteria - <span id="bankTransferPlanName"></span></span>
                                    </div>
                                </div>
                                
                                <div class="bank-transfer-note">
                                    <p><i class="fas fa-exclamation-circle"></i> Pas kryerjes së pagesës, ju lutemi ngarkoni faturën e pagesës për të përshpejtuar aktivizimin e llogarisë suaj.</p>
                                </div>
                                
                                <form id="paymentProofForm">
                                    <div class="form-group">
                                        <label class="form-label" for="paymentProof"><i class="fas fa-file-upload"></i> Ngarkoni dëshminë e pagesës</label>
                                        <input type="file" class="form-control" id="paymentProof" name="paymentProof" accept=".jpg,.jpeg,.png,.pdf">
                                        <div class="form-hint">Formate të pranueshme: JPG, PNG, PDF. Madhësia maksimale: 5MB</div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <input type="hidden" id="selectedPlanId" name="plan_id" value="">
                <input type="hidden" id="paymentMethod" name="payment_method" value="card">
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" id="processPayment">
                    <i class="fas fa-lock"></i> Kryej Pagesën
                </button>
                <button class="btn btn-secondary modal-close-btn" style="background-color: var(--light); color: var(--text-dark); border: 1px solid var(--border);">
                    <i class="fas fa-times"></i> Anulo
                </button>
            </div>
        </div>
    </div>
    
    <!-- Modal i konfirmimit të pagesës -->
    <div id="paymentConfirmationModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-check-circle"></i> Pagesa u Pranua</h3>
                <button class="modal-close"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div class="payment-success">
                    <div class="success-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h3>Faleminderit për pagesën tuaj!</h3>
                    <p>Pagesa juaj për <span id="confirmPlanName"></span> u pranua me sukses. Abonimi juaj është tani aktiv.</p>
                    
                    <div class="payment-details">
                        <div class="detail-row">
                            <span class="detail-label">ID e Transaksionit:</span>
                            <span class="detail-value" id="transactionId">TXN_20251004_123456</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Shuma:</span>
                            <span class="detail-value"><span id="confirmAmount"></span> €</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Data:</span>
                            <span class="detail-value" id="paymentDate"></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Metoda:</span>
                            <span class="detail-value" id="confirmPaymentMethod">Kartelë krediti</span>
                        </div>
                    </div>
                    
                    <p>Një konfirmim i detajuar është dërguar në adresën tuaj të emailit.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" id="continueButton">
                    <i class="fas fa-arrow-right"></i> <?php echo isset($_SESSION["zyra_id"]) ? 'Vazhdo në Dashboard' : 'Vazhdo me Regjistrim'; ?>
                </button>
            </div>
        </div>
    </div>
    
    <script>
        // Animacioni për kartat e planeve
        document.addEventListener('DOMContentLoaded', function() {
            // Animo statistikat dhe kartat e çmimeve me vonesë
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 100 * index);
            });
            
            const pricingCards = document.querySelectorAll('.pricing-card');
            pricingCards.forEach((card, index) => {
                setTimeout(() => {
                    card.classList.add('animated');
                }, 200 * index);
            });
        });
        
        // Modalet me animacion
        const modals = document.querySelectorAll('.modal');
        const modalOpeners = document.querySelectorAll('[id^="btn"]');
        const modalClosers = document.querySelectorAll('.modal-close, .modal-close-btn');
        
        // Funksion për hapjen e modalit me animacion
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'block';
                // Shtojmë klasën për animacion pas 10ms
                setTimeout(() => {
                    modal.classList.add('show');
                }, 10);
            }
        }
        
        // Funksion për mbylljen e modalit me animacion
        function closeModal(modal) {
            if (modal) {
                modal.classList.remove('show');
                // Mbyllim modalin pas përfundimit të animacionit
                setTimeout(() => {
                    modal.style.display = 'none';
                }, 300);
            }
        }
        
        modalOpeners.forEach(opener => {
            opener.addEventListener('click', () => {
                const modalId = opener.id.replace('btn', '').toLowerCase() + 'Modal';
                openModal(modalId);
            });
        });
        
        modalClosers.forEach(closer => {
            closer.addEventListener('click', () => {
                const modal = closer.closest('.modal');
                closeModal(modal);
            });
        });
        
        window.addEventListener('click', (e) => {
            modals.forEach(modal => {
                if (e.target === modal) {
                    closeModal(modal);
                }
            });
        });
        
        // Ruaj planin e ri
        document.getElementById('savePlan').addEventListener('click', () => {
            // Validimi i formularit
            const form = document.getElementById('addPlanForm');
            const emri = document.getElementById('emri').value.trim();
            const cmimi = document.getElementById('cmimi').value.trim();
            const kohezgjatja = document.getElementById('kohezgjatja').value.trim();
            
            if (emri === '' || cmimi === '' || kohezgjatja === '') {
                // Shto një mesazh gabimi
                const modalBody = document.querySelector('.modal-body');
                
                // Kontrollo nëse ekziston një mesazh gabimi dhe fshije
                const existingAlert = modalBody.querySelector('.alert');
                if (existingAlert) {
                    existingAlert.remove();
                }
                
                // Shto një mesazh të ri gabimi
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-danger';
                alertDiv.innerHTML = `
                    <i class="fas fa-exclamation-circle"></i>
                    <div>
                        <strong>Gabim!</strong> Ju lutemi plotësoni të gjitha fushat e detyrueshme.
                    </div>
                `;
                
                modalBody.insertBefore(alertDiv, form);
                
                // Tregoni mesazhin për 3 sekonda
                setTimeout(() => {
                    alertDiv.style.opacity = '0';
                    setTimeout(() => {
                        alertDiv.remove();
                    }, 300);
                }, 3000);
                
                return;
            }
            
            // Këtu do të bëhej ruajtja e planit në databazë
            // Për demonstrim, thjesht shfaqim një notifikim dhe mbyllim modalin
            
            // Mbyll modalin
            closeModal(document.getElementById('addPlanModal'));
            
            // Shfaq notifikim
            const notification = document.createElement('div');
            notification.className = 'notification';
            notification.innerHTML = `
                <div class="notification-content success">
                    <i class="fas fa-check-circle"></i>
                    <div>Plani u ruajt me sukses!</div>
                </div>
            `;
            document.body.appendChild(notification);
            
            // Fshij notifikimin pas 3 sekondash
            setTimeout(() => {
                notification.classList.add('hide');
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 3000);
        });
        
        // Butoni i editimit
        const editButtons = document.querySelectorAll('.edit-plan');
        editButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                const planId = button.getAttribute('data-id');
                
                // Këtu do të bëhet kërkesa për të marrë të dhënat e planit dhe të mbushet formulari i modalit
                openModal('addPlanModal');
                
                // Ndryshojmë titullin e modalit
                document.querySelector('.modal-title').innerHTML = '<i class="fas fa-edit"></i> Edito Planin e Abonimit';
                
                // Mbushim formularin me të dhëna ekzistuese (demo)
                document.getElementById('emri').value = 'Abonim Mujor';
                document.getElementById('cmimi').value = '150.00';
                document.getElementById('kohezgjatja').value = '1';
                document.getElementById('pershkrimi').value = 'Abonim mujor me qasje të plotë në platformë';
                document.getElementById('karakteristikat').value = 'Qasje e plotë në platformë\nDokumente të pakufizuara\nMbështetje prioritare 24/7\nTë gjitha shërbimet e platformës\nMjete të avancuara për noterë';
            });
        });
        
        // Butoni i aktivizimit/çaktivizimit
        const statusButtons = document.querySelectorAll('.activate-plan, .deactivate-plan');
        statusButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                const planId = button.getAttribute('data-id');
                const action = button.classList.contains('activate-plan') ? 'aktivizuar' : 'çaktivizuar';
                
                // Konfirmo veprimin (mund të shtohet një modal konfirmimi)
                if (confirm(`A jeni të sigurt që dëshironi të ${action === 'aktivizuar' ? 'aktivizoni' : 'çaktivizoni'} këtë plan abonimesh?`)) {
                    // Këtu do të bëhej kërkesa për ndryshimin e statusit në databazë
                    
                    // Për demonstrim, thjesht shfaqim një notifikim
                    const notification = document.createElement('div');
                    notification.className = 'notification';
                    notification.innerHTML = `
                        <div class="notification-content ${action === 'aktivizuar' ? 'success' : 'warning'}">
                            <i class="fas fa-${action === 'aktivizuar' ? 'check' : 'ban'}-circle"></i>
                            <div>Plani u ${action} me sukses!</div>
                        </div>
                    `;
                    document.body.appendChild(notification);
                    
                    // Fshij notifikimin pas 3 sekondash
                    setTimeout(() => {
                        notification.classList.add('hide');
                        setTimeout(() => {
                            notification.remove();
                        }, 300);
                    }, 3000);
                }
            });
        });
        
        // Stilizim për notifikimet
        const style = document.createElement('style');
        style.textContent = `
            .notification {
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 2000;
                transition: all 0.3s ease;
                transform: translateX(0);
                opacity: 1;
            }
            
            .notification.hide {
                transform: translateX(100%);
                opacity: 0;
            }
            
            .notification-content {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 1rem 1.5rem;
                background: white;
                border-radius: 8px;
                box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
                min-width: 300px;
                border-left: 4px solid;
            }
            
            .notification-content.success {
                border-color: var(--success);
            }
            
            .notification-content.success i {
                color: var(--success);
            }
            
            .notification-content.warning {
                border-color: var(--warning);
            }
            
            .notification-content.warning i {
                color: var(--warning);
            }
            
            .notification-content i {
                font-size: 1.5rem;
            }
            
            .pricing-card {
                opacity: 0;
                transform: translateY(20px);
                transition: all 0.5s cubic-bezier(0.165, 0.84, 0.44, 1);
            }
            
            .pricing-card.animated {
                opacity: 1;
                transform: translateY(0);
            }
            
            /* Footer styling */
            .admin-footer {
                background-color: white;
                border-top: 1px solid var(--border);
                padding: 1.5rem 0;
                margin-top: 5rem;
                box-shadow: 0 -2px 10px rgba(0,0,0,0.03);
            }
            
            .footer-content {
                display: flex;
                justify-content: space-between;
                align-items: center;
                flex-wrap: wrap;
                gap: 1.25rem;
            }
            
            .footer-copyright {
                color: var(--text-light);
                font-size: 0.95rem;
            }
            
            .footer-links {
                display: flex;
                gap: 1.5rem;
            }
            
            .footer-link {
                color: var(--text-light);
                text-decoration: none;
                font-size: 0.95rem;
                display: flex;
                align-items: center;
                gap: 0.5rem;
                transition: color 0.2s ease;
            }
            
            .footer-link:hover {
                color: var(--primary);
            }
            
            .footer-link i {
                font-size: 1rem;
                opacity: 0.8;
            }
            
            /* Scroll to top button */
            .scroll-top {
                position: fixed;
                bottom: 2rem;
                right: 2rem;
                width: 45px;
                height: 45px;
                background: var(--gradient-primary);
                color: white;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                opacity: 0;
                visibility: hidden;
                transition: all 0.3s ease;
                box-shadow: var(--shadow-md);
                z-index: 99;
                border: none;
            }
            
            .scroll-top.active {
                opacity: 1;
                visibility: visible;
            }
            
            .scroll-top:hover {
                transform: translateY(-5px);
                box-shadow: var(--shadow-lg);
            }
            
            /* Payment Modal Styling */
            .selected-plan-info {
                background: var(--light);
                border-radius: var(--radius);
                padding: 1.5rem;
                margin-bottom: 2rem;
                text-align: center;
                border: 1px solid rgba(0,0,0,0.05);
                position: relative;
                overflow: hidden;
            }
            
            .selected-plan-info::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 4px;
                background: var(--gradient-primary);
            }
            
            .plan-badge {
                display: inline-block;
                background: rgba(59, 93, 231, 0.1);
                color: var(--primary);
                padding: 0.5rem 1rem;
                border-radius: 50px;
                font-weight: 600;
                margin-bottom: 1rem;
            }
            
            .plan-badge i {
                margin-right: 0.5rem;
            }
            
            .plan-price {
                font-size: 2rem;
                font-weight: 700;
                color: var(--heading);
                margin-bottom: 0.5rem;
            }
            
            .plan-duration {
                color: var(--text-light);
                font-size: 1rem;
            }
            
            .payment-tabs {
                margin-bottom: 1.5rem;
            }
            
            .tab-headers {
                display: flex;
                gap: 1rem;
                margin-bottom: 1.5rem;
                border-bottom: 1px solid var(--border);
            }
            
            .tab-header {
                padding: 0.75rem 1.25rem;
                font-weight: 600;
                color: var(--text-light);
                cursor: pointer;
                position: relative;
                transition: all 0.3s ease;
            }
            
            .tab-header i {
                margin-right: 0.5rem;
            }
            
            .tab-header.active {
                color: var(--primary);
            }
            
            .tab-header.active::after {
                content: '';
                position: absolute;
                bottom: -1px;
                left: 0;
                width: 100%;
                height: 3px;
                background: var(--primary);
            }
            
            .tab-pane {
                display: none;
                animation: fadeIn 0.3s ease;
            }
            
            .tab-pane.active {
                display: block;
            }
            
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(10px); }
                to { opacity: 1; transform: translateY(0); }
            }
            
            .form-row {
                display: flex;
                gap: 1rem;
                margin-bottom: 1.5rem;
            }
            
            .form-group.half {
                flex: 1;
                margin-bottom: 0;
            }
            
            .payment-security {
                background-color: rgba(16, 185, 129, 0.1);
                color: var(--success);
                padding: 0.75rem;
                border-radius: var(--radius);
                display: flex;
                align-items: center;
                gap: 0.5rem;
                margin-top: 1rem;
                font-size: 0.9rem;
                font-weight: 500;
            }
            
            .bank-transfer-info {
                background: var(--light);
                border-radius: var(--radius);
                padding: 1.5rem;
            }
            
            .bank-transfer-info h4 {
                margin-bottom: 1rem;
                color: var(--heading);
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }
            
            .bank-details {
                background: white;
                border-radius: var(--radius);
                padding: 1rem;
                margin-bottom: 1.5rem;
                border: 1px solid var(--border);
            }
            
            .detail-row {
                display: flex;
                justify-content: space-between;
                padding: 0.75rem 0;
                border-bottom: 1px solid rgba(0,0,0,0.05);
            }
            
            .detail-row:last-child {
                border-bottom: none;
            }
            
            .detail-label {
                font-weight: 600;
                color: var(--text-dark);
            }
            
            .detail-value {
                color: var(--text);
            }
            
            .bank-transfer-note {
                background-color: rgba(247, 184, 75, 0.1);
                color: #92400e;
                padding: 1rem;
                border-radius: var(--radius);
                margin-bottom: 1.5rem;
                border-left: 3px solid var(--warning);
            }
            
            .bank-transfer-note p {
                display: flex;
                align-items: flex-start;
                gap: 0.5rem;
                margin: 0;
                font-size: 0.95rem;
            }
            
            .bank-transfer-note i {
                margin-top: 0.2rem;
                color: var(--warning);
            }
            
            /* Payment Success Modal */
            .payment-success {
                text-align: center;
                padding: 1rem 0;
            }
            
            .success-icon {
                width: 80px;
                height: 80px;
                background: rgba(16, 185, 129, 0.1);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 1.5rem;
            }
            
            .success-icon i {
                font-size: 2.5rem;
                color: var(--success);
            }
            
            .payment-success h3 {
                color: var(--heading);
                margin-bottom: 1rem;
                font-size: 1.5rem;
            }
            
            .payment-success p {
                color: var(--text);
                margin-bottom: 1.5rem;
            }
            
            .payment-details {
                background: var(--light);
                border-radius: var(--radius);
                padding: 1rem;
                margin: 1.5rem 0;
                text-align: left;
            }
            
            /* Table responsive improvements */
            @media (max-width: 768px) {
                .table-responsive {
                    overflow-x: auto;
                    -webkit-overflow-scrolling: touch;
                }
                
                .footer-content {
                    flex-direction: column;
                    text-align: center;
                }
                
                .tab-headers {
                    flex-direction: column;
                    gap: 0.5rem;
                }
                
                .tab-header {
                    text-align: center;
                }
                
                .tab-header.active::after {
                    width: 50%;
                    left: 25%;
                }
                
                .form-row {
                    flex-direction: column;
                    gap: 1.5rem;
                }
            }
        `;
        document.head.appendChild(style);
        
        // Inicion butoni i shtimit të planit nga seksioni bosh
        if (document.getElementById('btnAddPlanEmpty')) {
            document.getElementById('btnAddPlanEmpty').addEventListener('click', () => {
                openModal('addPlanModal');
            });
        }
        
        // Scroll to top button
        const scrollTopBtn = document.createElement('button');
        scrollTopBtn.className = 'scroll-top';
        scrollTopBtn.innerHTML = '<i class="fas fa-chevron-up"></i>';
        document.body.appendChild(scrollTopBtn);
        
        // Show/hide scroll to top button based on scroll position
        window.addEventListener('scroll', () => {
            if (window.pageYOffset > 300) {
                scrollTopBtn.classList.add('active');
            } else {
                scrollTopBtn.classList.remove('active');
            }
        });
        
        // Scroll to top when button is clicked
        scrollTopBtn.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
        
        // Payment Modal Functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Plan selection buttons
            const planButtons = document.querySelectorAll('.plan-select-btn');
            planButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const planId = this.getAttribute('data-plan-id');
                    const planName = this.getAttribute('data-plan-name');
                    const planPrice = this.getAttribute('data-plan-price');
                    const planDuration = this.getAttribute('data-plan-duration');
                    
                    // Fill the payment modal with plan details
                    document.getElementById('selectedPlanId').value = planId;
                    document.getElementById('planName').textContent = planName;
                    document.getElementById('planPrice').textContent = planPrice;
                    document.getElementById('bankTransferAmount').textContent = planPrice;
                    document.getElementById('bankTransferPlanName').textContent = planName;
                    
                    // Set the plan duration text
                    let durationText = '';
                    if (planDuration == 1) {
                        durationText = 'Abonim Mujor';
                    } else if (planDuration == 12) {
                        durationText = 'Abonim Vjetor';
                    } else {
                        durationText = `Abonim për ${planDuration} muaj`;
                    }
                    document.getElementById('planDuration').textContent = durationText;
                    
                    // Open payment modal
                    openModal('paymentModal');
                });
            });
            
            // Payment method tabs
            const tabHeaders = document.querySelectorAll('.tab-header');
            tabHeaders.forEach(tab => {
                tab.addEventListener('click', function() {
                    // Remove active class from all tabs
                    tabHeaders.forEach(t => t.classList.remove('active'));
                    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
                    
                    // Add active class to clicked tab
                    this.classList.add('active');
                    const tabId = this.getAttribute('data-tab');
                    document.getElementById(tabId + 'Tab').classList.add('active');
                    
                    // Set payment method
                    document.getElementById('paymentMethod').value = tabId;
                });
            });
            
            // Process payment button
            document.getElementById('processPayment').addEventListener('click', function() {
                const paymentMethod = document.getElementById('paymentMethod').value;
                const planId = document.getElementById('selectedPlanId').value;
                const planName = document.getElementById('planName').textContent;
                const planPrice = document.getElementById('planPrice').textContent;
                
                // Save selection in session using AJAX
                const saveSessionData = async () => {
                    try {
                        const formData = new FormData();
                        formData.append('action', 'save_abonim');
                        formData.append('abonim_id', planId);
                        formData.append('abonim_price', planPrice);
                        formData.append('payment_method', paymentMethod);
                        
                        const response = await fetch('save_abonim_session.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        return await response.json();
                    } catch (error) {
                        console.error('Error saving session data:', error);
                        return { success: false, error: 'Network error' };
                    }
                };
                
                // Validate form based on payment method
                let isValid = true;
                
                if (paymentMethod === 'card') {
                    const cardForm = document.getElementById('cardPaymentForm');
                    const requiredFields = cardForm.querySelectorAll('[required]');
                    
                    requiredFields.forEach(field => {
                        if (!field.value.trim()) {
                            isValid = false;
                            field.classList.add('error-field');
                        }
                    });
                    
                    if (!isValid) {
                        alert('Ju lutemi plotësoni të gjitha fushat e kërkuara për pagesën me kartelë.');
                        return;
                    }
                    
                    // Simulate card payment processing
                    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Duke procesuar pagesën...';
                    this.disabled = true;
                    
                    // First save the selection in session, then show confirmation
                    const formData = new FormData();
                    formData.append('action', 'save_abonim');
                    formData.append('abonim_id', planId);
                    formData.append('abonim_price', planPrice);
                    formData.append('abonim_name', planName);
                    formData.append('payment_method', paymentMethod);
                    
                    saveSessionData().then(result => {
                        setTimeout(() => {
                            closeModal(document.getElementById('paymentModal'));
                            
                            // Prepare confirmation modal
                            document.getElementById('confirmPlanName').textContent = planName;
                            document.getElementById('confirmAmount').textContent = planPrice;
                            document.getElementById('transactionId').textContent = 'TXN_' + Date.now().toString().substr(-8);
                            document.getElementById('paymentDate').textContent = new Date().toLocaleDateString('sq-AL');
                            document.getElementById('confirmPaymentMethod').textContent = 'Kartelë krediti';
                            
                            // Open confirmation modal
                            openModal('paymentConfirmationModal');
                            
                            // Reset button
                            this.innerHTML = '<i class="fas fa-lock"></i> Kryej Pagesën';
                            this.disabled = false;
                        }, 2000);
                    });
                    
                } else if (paymentMethod === 'bank') {
                    // For bank transfer, we don't need strict validation
                    // Just check if file is uploaded (optional)
                    const fileInput = document.getElementById('paymentProof');
                    
                    // Simulate bank transfer processing
                    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Duke regjistruar pagesën...';
                    this.disabled = true;
                    
                    // First save the selection in session, then show confirmation
                    const formData = new FormData();
                    formData.append('action', 'save_abonim');
                    formData.append('abonim_id', planId);
                    formData.append('abonim_price', planPrice);
                    formData.append('abonim_name', planName);
                    formData.append('payment_method', paymentMethod);
                    
                    saveSessionData().then(result => {
                        setTimeout(() => {
                            closeModal(document.getElementById('paymentModal'));
                            
                            // Prepare confirmation modal
                            document.getElementById('confirmPlanName').textContent = planName;
                            document.getElementById('confirmAmount').textContent = planPrice;
                            document.getElementById('transactionId').textContent = 'TXN_' + Date.now().toString().substr(-8);
                            document.getElementById('paymentDate').textContent = new Date().toLocaleDateString('sq-AL');
                            document.getElementById('confirmPaymentMethod').textContent = 'Transfertë bankare';
                            
                            // Open confirmation modal
                            openModal('paymentConfirmationModal');
                            
                            // Reset button
                            this.innerHTML = '<i class="fas fa-lock"></i> Kryej Pagesën';
                            this.disabled = false;
                        }, 1500);
                    });
                }
                
                // In a real implementation, you would send an AJAX request to the server
                // to process the payment and save the transaction in the database
                
                // Example AJAX request:
                /*
                const formData = new FormData();
                formData.append('plan_id', planId);
                formData.append('payment_method', paymentMethod);
                
                // Add other fields based on payment method
                if (paymentMethod === 'card') {
                    formData.append('card_holder', document.getElementById('cardHolder').value);
                    formData.append('card_number', document.getElementById('cardNumber').value);
                    // etc.
                } else if (paymentMethod === 'bank') {
                    if (fileInput.files[0]) {
                        formData.append('payment_proof', fileInput.files[0]);
                    }
                }
                
                fetch('process_payment.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show confirmation modal
                    } else {
                        // Show error message
                    }
                })
                .catch(error => {
                    console.error('Payment error:', error);
                    alert('Ndodhi një gabim gjatë procesimit të pagesës. Ju lutemi provoni përsëri.');
                });
                */
            });
            
            // Continue button in confirmation modal
            document.getElementById('continueButton').addEventListener('click', function() {
                // Check if user is already registered
                if (typeof isUserRegistered !== 'undefined' && isUserRegistered) {
                    // Redirect to dashboard for registered users
                    window.location.href = 'dashboard.php';
                } else {
                    // Redirect to registration page for new users
                    window.location.href = 'zyrat_register.php';
                }
            });
            
            // Card input formatting
            const cardNumberInput = document.getElementById('cardNumber');
            if (cardNumberInput) {
                cardNumberInput.addEventListener('input', function(e) {
                    // Remove all non-digit characters
                    let value = this.value.replace(/\D/g, '');
                    
                    // Add a space after every 4 digits
                    value = value.replace(/(\d{4})(?=\d)/g, '$1 ');
                    
                    // Limit to 19 characters (16 digits + 3 spaces)
                    if (value.length > 19) {
                        value = value.substr(0, 19);
                    }
                    
                    this.value = value;
                });
            }
            
            const expiryDateInput = document.getElementById('expiryDate');
            if (expiryDateInput) {
                expiryDateInput.addEventListener('input', function(e) {
                    // Remove all non-digit characters
                    let value = this.value.replace(/\D/g, '');
                    
                    // Add a slash after 2 digits (MM/YY format)
                    if (value.length > 2) {
                        value = value.substr(0, 2) + '/' + value.substr(2);
                    }
                    
                    // Limit to 5 characters (MM/YY)
                    if (value.length > 5) {
                        value = value.substr(0, 5);
                    }
                    
                    this.value = value;
                });
            }
            
            const cvvInput = document.getElementById('cvv');
            if (cvvInput) {
                cvvInput.addEventListener('input', function(e) {
                    // Remove all non-digit characters
                    let value = this.value.replace(/\D/g, '');
                    
                    // Limit to 3 or 4 digits
                    if (value.length > 4) {
                        value = value.substr(0, 4);
                    }
                    
                    this.value = value;
                });
            }
            
            // Function to handle form validation styles
            function handleFormValidation() {
                const inputFields = document.querySelectorAll('.form-control');
                inputFields.forEach(input => {
                    input.addEventListener('input', function() {
                        this.classList.remove('error-field');
                    });
                });
            }
            
            handleFormValidation();
        });
    </script>
</body>
</html>