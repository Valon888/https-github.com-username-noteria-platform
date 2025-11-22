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

// Merr të dhënat për statistikat
try {
    // Numri total i noterëve
    $stmt = $pdo->query("SHOW TABLES LIKE 'noteret'");
    $noteret_table_exists = ($stmt->rowCount() > 0);
    
    if ($noteret_table_exists) {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM noteret");
        $numri_notereve = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as aktiv FROM noteret WHERE statusi = 'aktiv'");
        $notere_aktiv = $stmt->fetch(PDO::FETCH_ASSOC)['aktiv'];
    } else {
        $numri_notereve = 0;
        $notere_aktiv = 0;
    }
    
    // Kontrollo nëse ekziston tabela e rezervimeve dhe krijo nëse nuk ekziston
    $stmt = $pdo->query("SHOW TABLES LIKE 'reservations'");
    $reservations_table_exists = ($stmt->rowCount() > 0);
    
    if (!$reservations_table_exists) {
        $pdo->exec("CREATE TABLE `reservations` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `noter_id` INT(11) NOT NULL,
            `client_name` VARCHAR(100) NOT NULL,
            `client_email` VARCHAR(100) NOT NULL,
            `client_phone` VARCHAR(20),
            `reservation_date` DATE NOT NULL,
            `reservation_time` TIME NOT NULL,
            `service_type` VARCHAR(100) NOT NULL,
            `status` ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
            `notes` TEXT,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        
        // Shto disa të dhëna demo
        $reservationDemoData = [
            [1, 'Agim Berisha', 'agim.berisha@example.com', '044123456', '2025-09-25', '10:00:00', 'Vërtetim Dokumenti', 'confirmed', 'Klienti ka kërkuar shërbim urgjent'],
            [2, 'Vjollca Krasniqi', 'vjollca.k@example.com', '045789123', '2025-09-26', '11:30:00', 'Kontratë Shitblerje', 'completed', ''],
            [3, 'Burim Hoxha', 'burim.h@example.com', '049567890', '2025-09-26', '14:00:00', 'Autorizim', 'pending', ''],
            [1, 'Drita Gashi', 'drita.g@example.com', '044345678', '2025-09-27', '09:30:00', 'Testament', 'confirmed', 'Klienti kërkon diskrecion të plotë'],
            [3, 'Fatmir Shala', 'fatmir.s@example.com', '045123789', '2025-09-27', '13:00:00', 'Vërtetim Kopje', 'pending', ''],
            [2, 'Mimoza Rexhepi', 'mimoza.r@example.com', '044678912', '2025-09-28', '10:30:00', 'Deklaratë Noteriale', 'cancelled', 'Klienti anuloi për arsye personale'],
            [1, 'Blerim Morina', 'blerim.m@example.com', '049234567', '2025-09-28', '15:00:00', 'Kontratë Qiraje', 'confirmed', ''],
            [3, 'Teuta Hyseni', 'teuta.h@example.com', '044567123', '2025-09-29', '11:00:00', 'Prokurë', 'pending', ''],
            [2, 'Arben Kelmendi', 'arben.k@example.com', '045345678', '2025-09-29', '16:30:00', 'Kontratë Pune', 'confirmed', ''],
            [1, 'Shpresa Ahmeti', 'shpresa.a@example.com', '049678912', '2025-09-30', '09:00:00', 'Marrëveshje Bashkëpunimi', 'pending', 'Klienti ka kërkuar konsultë paraprake']
        ];
        
        $insertStmt = $pdo->prepare("INSERT INTO reservations (noter_id, client_name, client_email, client_phone, reservation_date, reservation_time, service_type, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($reservationDemoData as $reservation) {
            $insertStmt->execute($reservation);
        }
    }
    
    // Numri i rezervimeve
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM reservations");
    $numri_rezervimeve = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as confirmed FROM reservations WHERE status = 'confirmed'");
    $rezervime_konfirmuara = $stmt->fetch(PDO::FETCH_ASSOC)['confirmed'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as pending FROM reservations WHERE status = 'pending'");
    $rezervime_pritje = $stmt->fetch(PDO::FETCH_ASSOC)['pending'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as completed FROM reservations WHERE status = 'completed'");
    $rezervime_perfunduara = $stmt->fetch(PDO::FETCH_ASSOC)['completed'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as cancelled FROM reservations WHERE status = 'cancelled'");
    $rezervime_anuluara = $stmt->fetch(PDO::FETCH_ASSOC)['cancelled'];
    
    // Kontrollo nëse ekziston tabela e pagesave dhe krijo nëse nuk ekziston
    $stmt = $pdo->query("SHOW TABLES LIKE 'payments'");
    $payments_table_exists = ($stmt->rowCount() > 0);
    
    if (!$payments_table_exists) {
        $pdo->exec("CREATE TABLE `payments` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `reservation_id` INT(11),
            `client_name` VARCHAR(100) NOT NULL,
            `amount` DECIMAL(10,2) NOT NULL,
            `payment_method` ENUM('card', 'bank_transfer', 'cash') NOT NULL,
            `status` ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
            `transaction_id` VARCHAR(100),
            `payment_date` DATETIME,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        
        // Shto disa të dhëna demo
        $paymentDemoData = [
            [1, 'Agim Berisha', 45.00, 'card', 'completed', 'TR-12345', '2025-09-25 10:30:00'],
            [2, 'Vjollca Krasniqi', 120.00, 'bank_transfer', 'completed', 'TR-12346', '2025-09-26 12:00:00'],
            [3, 'Burim Hoxha', 30.00, 'cash', 'completed', 'TR-12347', '2025-09-26 14:30:00'],
            [4, 'Drita Gashi', 100.00, 'card', 'pending', 'TR-12348', NULL],
            [5, 'Fatmir Shala', 25.00, 'cash', 'completed', 'TR-12349', '2025-09-27 13:15:00'],
            [6, 'Mimoza Rexhepi', 80.00, 'card', 'refunded', 'TR-12350', '2025-09-28 11:00:00'],
            [7, 'Blerim Morina', 90.00, 'bank_transfer', 'completed', 'TR-12351', '2025-09-28 15:45:00'],
            [8, 'Teuta Hyseni', 50.00, 'cash', 'pending', 'TR-12352', NULL],
            [9, 'Arben Kelmendi', 75.00, 'card', 'completed', 'TR-12353', '2025-09-29 17:00:00'],
            [10, 'Shpresa Ahmeti', 110.00, 'bank_transfer', 'pending', 'TR-12354', NULL]
        ];
        
        $insertStmt = $pdo->prepare("INSERT INTO payments (reservation_id, client_name, amount, payment_method, status, transaction_id, payment_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($paymentDemoData as $payment) {
            $insertStmt->execute($payment);
        }
    }
    
    // Të ardhurat totale
    $stmt = $pdo->query("SELECT SUM(amount) as total FROM payments WHERE status = 'completed'");
    $te_ardhurat_totale = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM payments");
    $numri_pagesave = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as completed FROM payments WHERE status = 'completed'");
    $pagesa_perfunduara = $stmt->fetch(PDO::FETCH_ASSOC)['completed'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as pending FROM payments WHERE status = 'pending'");
    $pagesa_pritje = $stmt->fetch(PDO::FETCH_ASSOC)['pending'];
    
    // Kontrollo nëse ekziston tabela e përdoruesve dhe krijo nëse nuk ekziston
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    $users_table_exists = ($stmt->rowCount() > 0);
    
    if (!$users_table_exists) {
        $pdo->exec("CREATE TABLE `users` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `emri` VARCHAR(100) NOT NULL,
            `mbiemri` VARCHAR(100) NOT NULL,
            `email` VARCHAR(100) NOT NULL,
            `password` VARCHAR(255) NOT NULL,
            `roli` ENUM('admin', 'noter', 'klient') NOT NULL DEFAULT 'klient',
            `status` ENUM('aktiv', 'joaktiv', 'pezulluar') DEFAULT 'aktiv',
            `last_login` DATETIME,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        
        // Shto disa të dhëna demo
        $userDemoData = [
            ['Admin', 'Sistemi', 'admin@noteria.al', password_hash('admin123', PASSWORD_DEFAULT), 'admin', 'aktiv', '2025-09-27 08:30:00'],
            ['Noter', 'Shembull', 'noter@noteria.al', password_hash('noter123', PASSWORD_DEFAULT), 'noter', 'aktiv', '2025-09-26 15:45:00'],
            ['Klient', 'Test', 'klient@example.com', password_hash('klient123', PASSWORD_DEFAULT), 'klient', 'aktiv', '2025-09-25 11:20:00'],
            ['Agjent', 'Shitje', 'agjent@noteria.al', password_hash('agjent123', PASSWORD_DEFAULT), 'admin', 'aktiv', '2025-09-24 14:10:00'],
            ['Demo', 'Përdorues', 'demo@example.com', password_hash('demo123', PASSWORD_DEFAULT), 'klient', 'joaktiv', '2025-09-20 09:15:00']
        ];
        
        $insertStmt = $pdo->prepare("INSERT INTO users (emri, mbiemri, email, password, roli, status, last_login) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($userDemoData as $user) {
            $insertStmt->execute($user);
        }
    }
    
    // Numri i përdoruesve
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $numri_perdoruesve = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Kontrollo nëse kolona 'status' ekziston në tabelën users
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'status'");
        if ($stmt->rowCount() > 0) {
            // Nëse kolona ekziston, numëro përdoruesit aktivë
            $stmt = $pdo->query("SELECT COUNT(*) as active FROM users WHERE status = 'aktiv'");
            $perdorues_aktiv = $stmt->fetch(PDO::FETCH_ASSOC)['active'];
        } else {
            // Nëse kolona nuk ekziston, supozojmë që të gjithë përdoruesit janë aktivë
            $perdorues_aktiv = $numri_perdoruesve;
        }
    } catch (PDOException $e) {
        // Nëse ndodh ndonjë gabim, supozojmë që të gjithë përdoruesit janë aktivë
        $perdorues_aktiv = $numri_perdoruesve;
    }
    
    // Statistikat mujore për rezervimet - 6 muajt e fundit
    $muajt = [];
    $rezervime_mujore = [];
    
    for ($i = 5; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $month_name = date('M Y', strtotime("-$i months"));
        $muajt[] = $month_name;
        
        $month_start = $month . '-01';
        $month_end = date('Y-m-t', strtotime($month_start));
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM reservations WHERE reservation_date BETWEEN ? AND ?");
        $stmt->execute([$month_start, $month_end]);
        $rezervime_mujore[] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }
    
    // Statistikat për metodat e pagesave
    $stmt = $pdo->query("SELECT payment_method, COUNT(*) as total FROM payments GROUP BY payment_method");
    $payment_methods = [];
    $payment_counts = [];
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $method = $row['payment_method'];
        switch ($method) {
            case 'card':
                $method = 'Kartelë';
                break;
            case 'bank_transfer':
                $method = 'Transfertë Bankare';
                break;
            case 'cash':
                $method = 'Para në Dorë';
                break;
        }
        $payment_methods[] = $method;
        $payment_counts[] = $row['total'];
    }
    
    // Statistikat për tipet e shërbimeve
    $stmt = $pdo->query("SELECT service_type, COUNT(*) as total FROM reservations GROUP BY service_type ORDER BY total DESC LIMIT 5");
    $service_types = [];
    $service_counts = [];
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $service_types[] = $row['service_type'];
        $service_counts[] = $row['total'];
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
    <title>Statistikat - Noteria Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background-color: var(--card-bg);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            transition: var(--transition);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
        }
        
        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }
        
        .stat-title {
            font-size: 1rem;
            font-weight: 500;
            color: var(--text-light);
        }
        
        .stat-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background-color: rgba(37, 99, 235, 0.1);
            border-radius: 50%;
            color: var(--primary);
            font-size: 1.25rem;
        }
        
        .stat-value {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--heading);
            margin-bottom: 0.5rem;
        }
        
        .stat-subtitle {
            font-size: 0.85rem;
            color: var(--text-light);
        }
        
        .stat-footer {
            margin-top: auto;
            padding-top: 1rem;
            display: flex;
            align-items: center;
            font-size: 0.85rem;
        }
        
        .stat-change {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .stat-change.positive {
            color: var(--success);
        }
        
        .stat-change.negative {
            color: var(--danger);
        }
        
        .chart-container {
            background-color: var(--card-bg);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .chart-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--heading);
        }
        
        .chart-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .chart-wrapper {
            position: relative;
            height: 300px;
        }
        
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 1.5rem;
        }
        
        .small-chart {
            height: 250px;
        }
        
        @media (max-width: 768px) {
            .admin-nav {
                display: none;
            }
            
            .charts-grid {
                grid-template-columns: 1fr;
            }
            
            .chart-wrapper {
                height: 250px;
            }
        }
        
        /* Additional colors for charts */
        .chart-colors {
            --color-1: #3b82f6;
            --color-2: #10b981;
            --color-3: #f59e0b;
            --color-4: #ef4444;
            --color-5: #8b5cf6;
            --color-6: #ec4899;
        }
        
        .filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        
        .filter-item {
            padding: 0.5rem 1rem;
            background-color: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: var(--transition);
            font-weight: 500;
        }
        
        .filter-item:hover {
            background-color: var(--light);
        }
        
        .filter-item.active {
            background-color: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .date-range {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            margin-left: auto;
        }
        
        .date-range input {
            padding: 0.5rem;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
        }
        
        .btn-export {
            padding: 0.5rem 1rem;
            background-color: var(--light);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-export:hover {
            background-color: var(--border);
        }
    </style>
</head>
<body>
    <header class="admin-header">
        <div class="container">
            <a href="#" class="admin-logo">
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
                <a href="statistikat.php" class="admin-nav-item active">
                    <i class="fas fa-chart-line"></i> Statistikat
                </a>
                <a href="abonimet.php" class="admin-nav-item">
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
            <h1 class="page-title"><i class="fas fa-chart-line"></i> Statistikat e Sistemit</h1>
            
            <div class="date-range">
                <button class="btn-export">
                    <i class="fas fa-download"></i> Eksporto Raport
                </button>
            </div>
        </div>
        
        <div class="filters">
            <div class="filter-item active">Të gjitha kohët</div>
            <div class="filter-item">Muaji aktual</div>
            <div class="filter-item">Java e fundit</div>
            <div class="filter-item">24 orët e fundit</div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Noterë</div>
                    <div class="stat-icon"><i class="fas fa-user-tie"></i></div>
                </div>
                <div class="stat-value"><?php echo $numri_notereve; ?></div>
                <div class="stat-subtitle"><?php echo $notere_aktiv; ?> noterë aktivë</div>
                <div class="stat-footer">
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i> 12% nga muaji i kaluar
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Rezervime</div>
                    <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                </div>
                <div class="stat-value"><?php echo $numri_rezervimeve; ?></div>
                <div class="stat-subtitle"><?php echo $rezervime_konfirmuara; ?> të konfirmuara, <?php echo $rezervime_pritje; ?> në pritje</div>
                <div class="stat-footer">
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i> 8% nga muaji i kaluar
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Të Ardhura</div>
                    <div class="stat-icon"><i class="fas fa-euro-sign"></i></div>
                </div>
                <div class="stat-value"><?php echo number_format($te_ardhurat_totale, 2); ?> €</div>
                <div class="stat-subtitle"><?php echo $pagesa_perfunduara; ?> pagesa të përfunduara</div>
                <div class="stat-footer">
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i> 15% nga muaji i kaluar
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Përdorues</div>
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                </div>
                <div class="stat-value"><?php echo $numri_perdoruesve; ?></div>
                <div class="stat-subtitle"><?php echo $perdorues_aktiv; ?> përdorues aktivë</div>
                <div class="stat-footer">
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i> 5% nga muaji i kaluar
                    </div>
                </div>
            </div>
        </div>
        
        <div class="chart-container">
            <div class="chart-header">
                <div class="chart-title">Rezervimet sipas Muajve</div>
                <div class="chart-actions">
                    <select id="chartType">
                        <option value="line">Linjë</option>
                        <option value="bar">Shtylla</option>
                    </select>
                </div>
            </div>
            <div class="chart-wrapper">
                <canvas id="monthlyReservationsChart"></canvas>
            </div>
        </div>
        
        <div class="charts-grid">
            <div class="chart-container">
                <div class="chart-header">
                    <div class="chart-title">Metodat e Pagesave</div>
                </div>
                <div class="chart-wrapper small-chart">
                    <canvas id="paymentMethodsChart"></canvas>
                </div>
            </div>
            
            <div class="chart-container">
                <div class="chart-header">
                    <div class="chart-title">Shërbimet më të Kërkuara</div>
                </div>
                <div class="chart-wrapper small-chart">
                    <canvas id="topServicesChart"></canvas>
                </div>
            </div>
            
            <div class="chart-container">
                <div class="chart-header">
                    <div class="chart-title">Statusi i Rezervimeve</div>
                </div>
                <div class="chart-wrapper small-chart">
                    <canvas id="reservationStatusChart"></canvas>
                </div>
            </div>
            
            <div class="chart-container">
                <div class="chart-header">
                    <div class="chart-title">Statusi i Pagesave</div>
                </div>
                <div class="chart-wrapper small-chart">
                    <canvas id="paymentStatusChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Të dhënat për grafikët
        const monthlyReservationsData = {
            labels: <?php echo json_encode($muajt); ?>,
            datasets: [{
                label: 'Rezervime',
                data: <?php echo json_encode($rezervime_mujore); ?>,
                backgroundColor: 'rgba(59, 130, 246, 0.2)',
                borderColor: 'rgba(59, 130, 246, 1)',
                borderWidth: 2,
                tension: 0.4,
                fill: true,
                pointBackgroundColor: 'rgba(59, 130, 246, 1)',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7
            }]
        };
        
        const paymentMethodsData = {
            labels: <?php echo json_encode($payment_methods); ?>,
            datasets: [{
                data: <?php echo json_encode($payment_counts); ?>,
                backgroundColor: [
                    'rgba(59, 130, 246, 0.8)',
                    'rgba(16, 185, 129, 0.8)',
                    'rgba(245, 158, 11, 0.8)'
                ],
                borderColor: [
                    'rgba(59, 130, 246, 1)',
                    'rgba(16, 185, 129, 1)',
                    'rgba(245, 158, 11, 1)'
                ],
                borderWidth: 1,
                hoverOffset: 10
            }]
        };
        
        const topServicesData = {
            labels: <?php echo json_encode($service_types); ?>,
            datasets: [{
                label: 'Numri i rezervimeve',
                data: <?php echo json_encode($service_counts); ?>,
                backgroundColor: [
                    'rgba(59, 130, 246, 0.8)',
                    'rgba(16, 185, 129, 0.8)',
                    'rgba(245, 158, 11, 0.8)',
                    'rgba(239, 68, 68, 0.8)',
                    'rgba(139, 92, 246, 0.8)'
                ],
                borderColor: [
                    'rgba(59, 130, 246, 1)',
                    'rgba(16, 185, 129, 1)',
                    'rgba(245, 158, 11, 1)',
                    'rgba(239, 68, 68, 1)',
                    'rgba(139, 92, 246, 1)'
                ],
                borderWidth: 1
            }]
        };
        
        const reservationStatusData = {
            labels: ['Konfirmuara', 'Në Pritje', 'Përfunduara', 'Anuluara'],
            datasets: [{
                data: [<?php echo $rezervime_konfirmuara; ?>, <?php echo $rezervime_pritje; ?>, <?php echo $rezervime_perfunduara; ?>, <?php echo $rezervime_anuluara; ?>],
                backgroundColor: [
                    'rgba(59, 130, 246, 0.8)',
                    'rgba(245, 158, 11, 0.8)',
                    'rgba(16, 185, 129, 0.8)',
                    'rgba(239, 68, 68, 0.8)'
                ],
                borderColor: [
                    'rgba(59, 130, 246, 1)',
                    'rgba(245, 158, 11, 1)',
                    'rgba(16, 185, 129, 1)',
                    'rgba(239, 68, 68, 1)'
                ],
                borderWidth: 1,
                hoverOffset: 10
            }]
        };
        
        const paymentStatusData = {
            labels: ['Përfunduara', 'Në Pritje', 'Dështuara', 'Rimbursime'],
            datasets: [{
                data: [<?php echo $pagesa_perfunduara; ?>, <?php echo $pagesa_pritje; ?>, 1, 1],
                backgroundColor: [
                    'rgba(16, 185, 129, 0.8)',
                    'rgba(245, 158, 11, 0.8)',
                    'rgba(239, 68, 68, 0.8)',
                    'rgba(139, 92, 246, 0.8)'
                ],
                borderColor: [
                    'rgba(16, 185, 129, 1)',
                    'rgba(245, 158, 11, 1)',
                    'rgba(239, 68, 68, 1)',
                    'rgba(139, 92, 246, 1)'
                ],
                borderWidth: 1,
                hoverOffset: 10
            }]
        };
        
        // Opsionet e përbashkëta për grafikët
        const commonOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        font: {
                            family: "'Montserrat', sans-serif",
                            size: 12
                        },
                        padding: 20
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(31, 41, 55, 0.9)',
                    titleFont: {
                        family: "'Montserrat', sans-serif",
                        size: 14,
                        weight: 'bold'
                    },
                    bodyFont: {
                        family: "'Montserrat', sans-serif",
                        size: 13
                    },
                    padding: 15,
                    cornerRadius: 8,
                    displayColors: false
                }
            }
        };
        
        // Inicializimi i grafikëve
        document.addEventListener('DOMContentLoaded', function() {
            // Grafiku i rezervimeve mujore
            const monthlyReservationsChart = new Chart(
                document.getElementById('monthlyReservationsChart'),
                {
                    type: 'line',
                    data: monthlyReservationsData,
                    options: {
                        ...commonOptions,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    font: {
                                        family: "'Montserrat', sans-serif"
                                    },
                                    precision: 0
                                },
                                grid: {
                                    drawBorder: false,
                                    color: 'rgba(229, 231, 235, 0.5)'
                                }
                            },
                            x: {
                                ticks: {
                                    font: {
                                        family: "'Montserrat', sans-serif"
                                    }
                                },
                                grid: {
                                    display: false
                                }
                            }
                        },
                        plugins: {
                            ...commonOptions.plugins,
                            legend: {
                                display: false
                            }
                        }
                    }
                }
            );
            
            // Grafiku i metodave të pagesave
            const paymentMethodsChart = new Chart(
                document.getElementById('paymentMethodsChart'),
                {
                    type: 'pie',
                    data: paymentMethodsData,
                    options: {
                        ...commonOptions,
                        cutout: '30%'
                    }
                }
            );
            
            // Grafiku i shërbimeve më të kërkuara
            const topServicesChart = new Chart(
                document.getElementById('topServicesChart'),
                {
                    type: 'bar',
                    data: topServicesData,
                    options: {
                        ...commonOptions,
                        indexAxis: 'y',
                        scales: {
                            x: {
                                beginAtZero: true,
                                ticks: {
                                    precision: 0,
                                    font: {
                                        family: "'Montserrat', sans-serif"
                                    }
                                },
                                grid: {
                                    drawBorder: false,
                                    color: 'rgba(229, 231, 235, 0.5)'
                                }
                            },
                            y: {
                                ticks: {
                                    font: {
                                        family: "'Montserrat', sans-serif"
                                    }
                                },
                                grid: {
                                    display: false
                                }
                            }
                        },
                        plugins: {
                            ...commonOptions.plugins,
                            legend: {
                                display: false
                            }
                        }
                    }
                }
            );
            
            // Grafiku i statusit të rezervimeve
            const reservationStatusChart = new Chart(
                document.getElementById('reservationStatusChart'),
                {
                    type: 'doughnut',
                    data: reservationStatusData,
                    options: {
                        ...commonOptions,
                        cutout: '50%'
                    }
                }
            );
            
            // Grafiku i statusit të pagesave
            const paymentStatusChart = new Chart(
                document.getElementById('paymentStatusChart'),
                {
                    type: 'doughnut',
                    data: paymentStatusData,
                    options: {
                        ...commonOptions,
                        cutout: '50%'
                    }
                }
            );
            
            // Ndërrimi i tipit të grafikut për rezervimet mujore
            document.getElementById('chartType').addEventListener('change', function() {
                monthlyReservationsChart.destroy();
                const newType = this.value;
                
                const newChart = new Chart(
                    document.getElementById('monthlyReservationsChart'),
                    {
                        type: newType,
                        data: monthlyReservationsData,
                        options: {
                            ...commonOptions,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        font: {
                                            family: "'Montserrat', sans-serif"
                                        },
                                        precision: 0
                                    },
                                    grid: {
                                        drawBorder: false,
                                        color: 'rgba(229, 231, 235, 0.5)'
                                    }
                                },
                                x: {
                                    ticks: {
                                        font: {
                                            family: "'Montserrat', sans-serif"
                                        }
                                    },
                                    grid: {
                                        display: false
                                    }
                                }
                            },
                            plugins: {
                                ...commonOptions.plugins,
                                legend: {
                                    display: false
                                }
                            }
                        }
                    }
                );
            });
            
            // Filtrimet
            const filterItems = document.querySelectorAll('.filter-item');
            filterItems.forEach(item => {
                item.addEventListener('click', function() {
                    filterItems.forEach(i => i.classList.remove('active'));
                    this.classList.add('active');
                });
            });
        });
    </script>
</body>
</html>