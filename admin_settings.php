<?php
/**
 * Noteria Settings Panel
 * 
 * A comprehensive settings management page for configuring 
 * the Noteria system, including user interface, security,
 * payment gateways, and notification settings.
 * 
 * @version 1.0
 * @date September 2025
 */

require_once 'config.php';

// Fillimi i sigurt i sesionit - PARA require_once
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 1 : 0);
    ini_set('session.use_strict_mode', 1);
    session_start();
}
if (!isset($_SESSION['admin_id'])) {
    header("Location: test_login.php");
    exit();
}

// Ridrejto tek settings.php
header("Location: settings.php");
exit();

// Përcakto kategorinë aktive të cilësimeve
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'system';

// Lista e kategorive të cilësimeve
$settingCategories = [
    'system' => [
        'title' => 'Cilësimet e Sistemit',
        'icon' => 'fa-server',
        'description' => 'Konfiguro parametrat bazë të sistemit, duke përfshirë emrin e aplikacionit, URL-të dhe informatat e kontaktit.'
    ],
    'interface' => [
        'title' => 'Ndërfaqja e Përdoruesit',
        'icon' => 'fa-palette',
        'description' => 'Personalizo pamjen dhe ndjesinë e sistemit, përfshirë ngjyrat, logon dhe elementet e ndërfaqes.'
    ],
    'security' => [
        'title' => 'Siguria',
        'icon' => 'fa-shield-alt',
        'description' => 'Konfiguro cilësimet e sigurisë, politikat e fjalëkalimeve, dhe opsionet e vërtetimit.'
    ],
    'notifications' => [
        'title' => 'Njoftimet',
        'icon' => 'fa-bell',
        'description' => 'Menaxho konfigurimet e njoftimeve dhe emaileve për përdoruesit dhe administratorët.'
    ],
    'payment' => [
        'title' => 'Portat e Pagesave',
        'icon' => 'fa-credit-card',
        'description' => 'Konfiguro integrimet e pagesave dhe metodat e pranimit të pagesave.'
    ],
    'maintenance' => [
        'title' => 'Mirëmbajtja',
        'icon' => 'fa-tools',
        'description' => 'Opsione për mirëmbajtjen e sistemit, backup-et dhe performancën.'
    ]
];

// Merr vlerat e cilësimeve aktuale nga databaza
try {
    // Kontrollo nëse ekziston tabela e cilësimeve
    $stmt = $pdo->query("SHOW TABLES LIKE 'settings'");
    $tableExists = ($stmt->rowCount() > 0);
    
    if (!$tableExists) {
        // Krijo tabelën e cilësimeve nëse nuk ekziston
        $pdo->exec("CREATE TABLE `settings` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `category` VARCHAR(50) NOT NULL,
            `setting_key` VARCHAR(100) NOT NULL,
            `setting_value` TEXT,
            `type` VARCHAR(20) NOT NULL DEFAULT 'text',
            `options` TEXT,
            `description` TEXT,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `updated_by` INT(11),
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_setting` (`category`, `setting_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        
        // Shto cilësimet fillestare
        $defaultSettings = [
            // Cilësimet e sistemit
            ['system', 'app_name', 'Noteria', 'text', NULL, 'Emri i aplikacionit që shfaqet në tituj dhe interfaqe'],
            ['system', 'site_url', 'https://noteria.al', 'text', NULL, 'URL-ja kryesore e faqes'],
            ['system', 'admin_email', 'admin@noteria.al', 'email', NULL, 'Email-i i administratorit kryesor për njoftimet e sistemit'],
            ['system', 'support_email', 'support@noteria.al', 'email', NULL, 'Email-i i mbështetjes teknike për përdoruesit'],
            ['system', 'default_language', 'sq', 'select', 'sq:Shqip,en:English', 'Gjuha e parazgjedhur e sistemit'],
            ['system', 'timezone', 'Europe/Tirane', 'text', NULL, 'Zona kohore e sistemit (format PHP)'],
            
            // Cilësimet e ndërfaqes
            ['interface', 'primary_color', '#2563eb', 'color', NULL, 'Ngjyra primare e temës'],
            ['interface', 'secondary_color', '#64748b', 'color', NULL, 'Ngjyra sekondare e temës'],
            ['interface', 'logo_path', 'images/logo.png', 'file', NULL, 'Shtegu i logos së faqes'],
            ['interface', 'favicon_path', 'images/favicon.ico', 'file', NULL, 'Shtegu i ikonës favicon'],
            ['interface', 'show_footer', '1', 'boolean', NULL, 'Shfaq footer-in në fund të faqeve'],
            ['interface', 'items_per_page', '20', 'number', NULL, 'Numri i elementëve për faqe në lista'],
            
            // Cilësimet e sigurisë
            ['security', 'session_timeout', '30', 'number', NULL, 'Koha e skadimit të sesionit në minuta'],
            ['security', 'password_min_length', '8', 'number', NULL, 'Gjatësia minimale e fjalëkalimeve'],
            ['security', 'password_complexity', 'medium', 'select', 'low:E ulët,medium:Mesatare,high:E lartë', 'Niveli i kompleksitetit të kërkuar për fjalëkalimet'],
            ['security', 'max_login_attempts', '5', 'number', NULL, 'Numri maksimal i përpjekjeve të hyrjes para bllokimit'],
            ['security', 'account_lockout_time', '15', 'number', NULL, 'Koha e bllokimit të llogarisë pas shumë përpjekjeve të dështuara (minuta)'],
            ['security', 'force_password_change', '90', 'number', NULL, 'Ditët pas të cilave kërkohet ndryshimi i fjalëkalimit (0 për asnjëherë)'],
            
            // Cilësimet e njoftimeve
            ['notifications', 'enable_email', '1', 'boolean', NULL, 'Aktivizo njoftimet me email'],
            ['notifications', 'email_from_name', 'Noteria System', 'text', NULL, 'Emri i dërguesit për emailet'],
            ['notifications', 'smtp_host', 'smtp.mailtrap.io', 'text', NULL, 'Host-i SMTP për dërgimin e email-eve'],
            ['notifications', 'smtp_port', '2525', 'number', NULL, 'Porti SMTP'],
            ['notifications', 'smtp_username', '', 'text', NULL, 'Username për lidhjen SMTP'],
            ['notifications', 'smtp_password', '', 'password', NULL, 'Fjalëkalimi për lidhjen SMTP'],
            ['notifications', 'smtp_encryption', 'tls', 'select', 'none:None,tls:TLS,ssl:SSL', 'Lloji i enkriptimit për lidhjen SMTP'],
            
            // Cilësimet e pagesave
            ['payment', 'currency', 'EUR', 'text', NULL, 'Monedha e parazgjedhur për pagesat'],
            ['payment', 'vat_percentage', '20', 'number', NULL, 'Përqindja e TVSH-së për faturat'],
            ['payment', 'paysera_enabled', '1', 'boolean', NULL, 'Aktivizo pagesat me Paysera'],
            ['payment', 'paysera_project_id', '12345', 'text', NULL, 'ID e projektit në Paysera'],
            ['payment', 'paysera_test_mode', '1', 'boolean', NULL, 'Përdor mjedisin test të Paysera'],
            ['payment', 'bank_transfer_enabled', '1', 'boolean', NULL, 'Aktivizo pagesat me transfertë bankare'],
            ['payment', 'bank_account_details', 'Bank: Example Bank\nIBAN: AL00 0000 0000 0000 0000 0000 0000\nSWIFT: EXAMPLEAL', 'textarea', NULL, 'Detajet e llogarisë bankare për transferta'],
            
            // Cilësimet e mirëmbajtjes
            ['maintenance', 'maintenance_mode', '0', 'boolean', NULL, 'Aktivizo mënyrën e mirëmbajtjes (faqja nuk do të jetë e disponueshme për përdoruesit)'],
            ['maintenance', 'maintenance_message', 'Sistemi është aktualisht në mirëmbajtje. Ju lutemi provoni sërish më vonë.', 'textarea', NULL, 'Mesazhi që shfaqet gjatë mënyrës së mirëmbajtjes'],
            ['maintenance', 'debug_mode', '0', 'boolean', NULL, 'Aktivizo mënyrën debug për zhvilluesit'],
            ['maintenance', 'log_level', 'error', 'select', 'debug:Debug,info:Info,warning:Warning,error:Error', 'Niveli i logimit të gabimeve'],
            ['maintenance', 'auto_backup', '1', 'boolean', NULL, 'Aktivizo backup-et automatike të databazës'],
            ['maintenance', 'backup_frequency', 'daily', 'select', 'daily:Çdo ditë,weekly:Çdo javë,monthly:Çdo muaj', 'Shpeshtësia e backup-eve automatike']
        ];
        
        $insertStmt = $pdo->prepare("INSERT INTO settings (category, setting_key, setting_value, type, options, description) VALUES (?, ?, ?, ?, ?, ?)");
        
        foreach ($defaultSettings as $setting) {
            $insertStmt->execute($setting);
        }
    }
    
    // Merr të gjitha cilësimet nga databaza
    $stmt = $pdo->query("SELECT * FROM settings ORDER BY category, id");
    $allSettings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organizo cilësimet sipas kategorive për përdorim të lehtë
    $settings = [];
    foreach ($allSettings as $setting) {
        $settings[$setting['category']][$setting['setting_key']] = $setting;
    }
    
    // Procesi i ruajtjes së cilësimeve
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
        $category = $_POST['category'];
        $updateStmt = $pdo->prepare("UPDATE settings SET setting_value = ?, updated_by = ? WHERE category = ? AND setting_key = ?");
        
        foreach ($_POST as $key => $value) {
            // Ignoro çelësat speciale
            if (in_array($key, ['save_settings', 'category'])) {
                continue;
            }
            
            // Pastro vlerat nga HTML (përveç textarea ku lejohen formatime specifike)
            if (isset($settings[$category][$key]) && $settings[$category][$key]['type'] !== 'textarea') {
                $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            }
            
            $updateStmt->execute([$value, $adminId, $category, $key]);
        }
        
        // Trajtimi i skedarëve të ngarkuar
        if (!empty($_FILES)) {
            foreach ($_FILES as $fileKey => $fileInfo) {
                if ($fileInfo['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = 'uploads/';
                    $fileName = basename($fileInfo['name']);
                    $targetFile = $uploadDir . time() . '_' . $fileName;
                    
                    if (move_uploaded_file($fileInfo['tmp_name'], $targetFile)) {
                        $updateStmt->execute([$targetFile, $adminId, $category, $fileKey]);
                    }
                }
            }
        }
        
        // Rifresho cilësimet
        $stmt = $pdo->query("SELECT * FROM settings ORDER BY category, id");
        $allSettings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $settings = [];
        foreach ($allSettings as $setting) {
            $settings[$setting['category']][$setting['setting_key']] = $setting;
        }
        
        $successMessage = "Cilësimet u ruajtën me sukses!";
    }
} catch (PDOException $e) {
    $error = "Gabim në lidhjen me databazën: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cilësimet e Sistemit - Noteria Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
        
        .tabs-container {
            display: flex;
            border-bottom: 1px solid var(--border);
            margin-bottom: 2rem;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .tab-button {
            padding: 1rem 1.5rem;
            color: var(--text);
            background: transparent;
            border: none;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
            position: relative;
            white-space: nowrap;
        }
        
        .tab-button:hover {
            color: var(--primary);
        }
        
        .tab-button.active {
            color: var(--primary);
        }
        
        .tab-button.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 100%;
            height: 3px;
            background-color: var(--primary);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
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
        
        .card-description {
            margin-bottom: 1.5rem;
            color: var(--text-light);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-dark);
        }
        
        .form-description {
            font-size: 0.85rem;
            color: var(--text-light);
            margin-top: 0.25rem;
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
            background-color: white;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-select {
            width: 100%;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            font-family: inherit;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            color: var(--text);
            transition: var(--transition);
            background-color: white;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%236b7280'%3E%3Cpath fill-rule='evenodd' d='M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z' clip-rule='evenodd' /%3E%3C/svg%3E");
            background-position: right 1rem center;
            background-repeat: no-repeat;
            background-size: 1.25rem;
            padding-right: 2.5rem;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
        }
        
        .form-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .form-check {
            display: flex;
            align-items: center;
        }
        
        .form-check-input {
            width: 1.25rem;
            height: 1.25rem;
            margin-right: 0.5rem;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            transition: var(--transition);
            cursor: pointer;
        }
        
        .form-check-input:checked {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .form-check-label {
            font-weight: 500;
            color: var(--text-dark);
            cursor: pointer;
        }
        
        .color-preview {
            display: inline-block;
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            vertical-align: middle;
            margin-left: 0.5rem;
            border: 1px solid var(--border);
        }
        
        .form-file {
            display: flex;
            flex-direction: column;
        }
        
        .file-preview {
            margin-top: 0.75rem;
        }
        
        .file-preview img {
            max-width: 100%;
            max-height: 150px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
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
        
        .btn-success {
            color: white;
            background-color: var(--success);
            border-color: var(--success);
        }
        
        .btn-success:hover {
            background-color: #0d9488;
            border-color: #0d9488;
        }
        
        .btn-danger {
            color: white;
            background-color: var(--danger);
            border-color: var(--danger);
        }
        
        .btn-danger:hover {
            background-color: #b91c1c;
            border-color: #b91c1c;
        }
        
        .btn-lg {
            padding: 0.875rem 1.75rem;
            font-size: 1rem;
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border);
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
        
        @media (max-width: 768px) {
            .admin-nav {
                display: none;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .tabs-container {
                width: 100%;
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
                <a href="statistikat.php" class="admin-nav-item">
                    <i class="fas fa-chart-line"></i> Statistikat
                </a>
                <a href="subscription_dashboard.php" class="admin-nav-item">
                    <i class="fas fa-receipt"></i> Abonimet
                </a>
                <a href="reports.php" class="admin-nav-item">
                    <i class="fas fa-file-alt"></i> Raportet
                </a>
                <a href="settings.php" class="admin-nav-item active">
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
            <h1 class="page-title"><i class="fas fa-cog"></i> Cilësimet e Sistemit</h1>
        </div>
        
        <?php if (isset($successMessage)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <div><?php echo $successMessage; ?></div>
        </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <div><?php echo $error; ?></div>
        </div>
        <?php endif; ?>
        
        <div class="tabs-container">
            <?php foreach ($settingCategories as $key => $category): ?>
                <a href="?tab=<?php echo $key; ?>" class="tab-button <?php echo ($activeTab === $key) ? 'active' : ''; ?>">
                    <i class="fas <?php echo $category['icon']; ?>"></i> <?php echo $category['title']; ?>
                </a>
            <?php endforeach; ?>
        </div>
        
        <?php foreach ($settingCategories as $key => $category): ?>
            <div class="tab-content <?php echo ($activeTab === $key) ? 'active' : ''; ?>" id="<?php echo $key; ?>-settings">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title"><i class="fas <?php echo $category['icon']; ?>"></i> <?php echo $category['title']; ?></h2>
                    </div>
                    
                    <div class="card-description">
                        <?php echo $category['description']; ?>
                    </div>
                    
                    <form action="settings.php?tab=<?php echo $key; ?>" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="category" value="<?php echo $key; ?>">
                        
                        <div class="form-grid">
                            <?php if (isset($settings[$key])): ?>
                                <?php foreach ($settings[$key] as $setting): ?>
                                    <div class="form-group <?php echo ($setting['type'] === 'textarea') ? 'full-width' : ''; ?>">
                                        <label class="form-label" for="<?php echo $setting['setting_key']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $setting['setting_key'])); ?>
                                        </label>
                                        
                                        <?php if ($setting['type'] === 'text' || $setting['type'] === 'email' || $setting['type'] === 'number'): ?>
                                            <input 
                                                type="<?php echo $setting['type']; ?>" 
                                                class="form-control" 
                                                id="<?php echo $setting['setting_key']; ?>" 
                                                name="<?php echo $setting['setting_key']; ?>" 
                                                value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                                <?php echo ($setting['type'] === 'number') ? 'step="any"' : ''; ?>
                                            >
                                        <?php elseif ($setting['type'] === 'password'): ?>
                                            <input 
                                                type="password" 
                                                class="form-control" 
                                                id="<?php echo $setting['setting_key']; ?>" 
                                                name="<?php echo $setting['setting_key']; ?>" 
                                                value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                                autocomplete="new-password"
                                            >
                                        <?php elseif ($setting['type'] === 'textarea'): ?>
                                            <textarea 
                                                class="form-control" 
                                                id="<?php echo $setting['setting_key']; ?>" 
                                                name="<?php echo $setting['setting_key']; ?>"
                                                rows="4"
                                            ><?php echo htmlspecialchars($setting['setting_value']); ?></textarea>
                                        <?php elseif ($setting['type'] === 'boolean'): ?>
                                            <div class="form-check">
                                                <input 
                                                    type="checkbox" 
                                                    class="form-check-input" 
                                                    id="<?php echo $setting['setting_key']; ?>" 
                                                    name="<?php echo $setting['setting_key']; ?>" 
                                                    value="1" 
                                                    <?php echo ($setting['setting_value'] == 1) ? 'checked' : ''; ?>
                                                >
                                                <label class="form-check-label" for="<?php echo $setting['setting_key']; ?>">
                                                    Aktivizo
                                                </label>
                                            </div>
                                        <?php elseif ($setting['type'] === 'select' && !empty($setting['options'])): ?>
                                            <select 
                                                class="form-select" 
                                                id="<?php echo $setting['setting_key']; ?>" 
                                                name="<?php echo $setting['setting_key']; ?>"
                                            >
                                                <?php 
                                                $options = explode(',', $setting['options']);
                                                foreach ($options as $option): 
                                                    list($value, $label) = explode(':', $option);
                                                ?>
                                                    <option 
                                                        value="<?php echo $value; ?>" 
                                                        <?php echo ($setting['setting_value'] === $value) ? 'selected' : ''; ?>
                                                    >
                                                        <?php echo $label; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php elseif ($setting['type'] === 'color'): ?>
                                            <div style="display: flex; align-items: center;">
                                                <input 
                                                    type="color" 
                                                    class="form-control" 
                                                    id="<?php echo $setting['setting_key']; ?>" 
                                                    name="<?php echo $setting['setting_key']; ?>" 
                                                    value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                                    style="width: 80px; height: 40px; padding: 0.25rem;"
                                                >
                                                <input 
                                                    type="text" 
                                                    class="form-control" 
                                                    value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                                    style="width: 120px; margin-left: 0.5rem;"
                                                    onchange="document.getElementById('<?php echo $setting['setting_key']; ?>').value = this.value"
                                                >
                                            </div>
                                        <?php elseif ($setting['type'] === 'file'): ?>
                                            <div class="form-file">
                                                <input 
                                                    type="file" 
                                                    class="form-control" 
                                                    id="<?php echo $setting['setting_key']; ?>" 
                                                    name="<?php echo $setting['setting_key']; ?>"
                                                >
                                                <?php if (!empty($setting['setting_value']) && file_exists($setting['setting_value'])): ?>
                                                <div class="file-preview">
                                                    <img src="<?php echo $setting['setting_value']; ?>" alt="Preview">
                                                    <input type="hidden" name="<?php echo $setting['setting_key']; ?>_current" value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($setting['description'])): ?>
                                            <div class="form-description"><?php echo $setting['description']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="alert alert-info" style="grid-column: 1 / -1;">
                                    <i class="fas fa-info-circle"></i>
                                    <div>Nuk u gjetën cilësime për këtë kategori.</div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" onclick="window.location.reload();">
                                <i class="fas fa-sync-alt"></i> Anulo
                            </button>
                            <button type="submit" name="save_settings" class="btn btn-primary">
                                <i class="fas fa-save"></i> Ruaj Cilësimet
                            </button>
                        </div>
                    </form>
                </div>
                
                <?php if ($key === 'maintenance'): ?>
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title"><i class="fas fa-database"></i> Operacionet e Databazës</h2>
                    </div>
                    
                    <div class="card-description">
                        Këto veprime mund të ndikojnë në performancën e sistemit. Përdorni me kujdes.
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem; margin-top: 1rem;">
                        <div style="padding: 1.5rem; border: 1px solid var(--border); border-radius: var(--radius); background-color: var(--light);">
                            <h3 style="font-size: 1.1rem; margin-bottom: 1rem; color: var(--heading);">
                                <i class="fas fa-download" style="color: var(--primary); margin-right: 0.5rem;"></i> 
                                Backup i Databazës
                            </h3>
                            <p style="margin-bottom: 1.5rem;">Krijon një backup të plotë të të gjitha të dhënave në databazë.</p>
                            <a href="backup_database.php" class="btn btn-primary btn-sm">
                                <i class="fas fa-download"></i> Krijo Backup
                            </a>
                        </div>
                        
                        <div style="padding: 1.5rem; border: 1px solid var(--border); border-radius: var(--radius); background-color: var(--light);">
                            <h3 style="font-size: 1.1rem; margin-bottom: 1rem; color: var(--heading);">
                                <i class="fas fa-broom" style="color: var(--warning); margin-right: 0.5rem;"></i> 
                                Pastro Cache
                            </h3>
                            <p style="margin-bottom: 1.5rem;">Pastron të dhënat e përkohshme dhe cache të sistemit.</p>
                            <a href="clear_cache.php" class="btn btn-primary btn-sm">
                                <i class="fas fa-broom"></i> Pastro Cache
                            </a>
                        </div>
                        
                        <div style="padding: 1.5rem; border: 1px solid var(--border); border-radius: var(--radius); background-color: var(--light);">
                            <h3 style="font-size: 1.1rem; margin-bottom: 1rem; color: var(--heading);">
                                <i class="fas fa-file-export" style="color: var(--info); margin-right: 0.5rem;"></i> 
                                Eksporto Log-et
                            </h3>
                            <p style="margin-bottom: 1.5rem;">Eksporton file-t e log-eve të sistemit për analizë.</p>
                            <a href="export_logs.php" class="btn btn-primary btn-sm">
                                <i class="fas fa-file-export"></i> Eksporto Log-et
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Update color text field when color picker changes
            document.querySelectorAll('input[type="color"]').forEach(function(colorPicker) {
                colorPicker.addEventListener('input', function() {
                    const textField = this.nextElementSibling;
                    textField.value = this.value;
                });
            });
            
            // Image preview for file uploads
            document.querySelectorAll('input[type="file"]').forEach(function(fileInput) {
                fileInput.addEventListener('change', function() {
                    const preview = this.parentElement.querySelector('.file-preview img');
                    if (preview && this.files && this.files[0]) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            preview.src = e.target.result;
                        }
                        reader.readAsDataURL(this.files[0]);
                    }
                });
            });
        });
    </script>
</body>
</html>
