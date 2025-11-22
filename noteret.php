<?php
session_start();

// Kontrollo nëse përdoruesi është i autentikuar dhe ka rolin admin
if (!isset($_SESSION["auth_test"]) && !isset($_SESSION["admin_id"])) {
    header("Location: test_login_easy.php");
    exit();
}

// Lidhja me databazën
require_once 'config.php';

// Kontrollo nëse tabela e noterëve ekziston
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'noteret'");
    $tableExists = ($stmt->rowCount() > 0);
    
    if (!$tableExists) {
        // Krijo tabelën e noterëve nëse nuk ekziston
        $pdo->exec("CREATE TABLE `noteret` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `emri` VARCHAR(100) NOT NULL,
            `mbiemri` VARCHAR(100) NOT NULL,
            `nr_personal` VARCHAR(50) NOT NULL,
            `nr_licences` VARCHAR(50) NOT NULL,
            `email` VARCHAR(100) NOT NULL,
            `telefoni` VARCHAR(20),
            `adresa` TEXT,
            `qyteti` VARCHAR(100),
            `shteti` VARCHAR(100) DEFAULT 'Kosovë',
            `gjinia` ENUM('M', 'F'),
            `data_lindjes` DATE,
            `data_licencimit` DATE,
            `foto` VARCHAR(255),
            `statusi` ENUM('aktiv', 'joaktiv', 'pezulluar') DEFAULT 'aktiv',
            `verejtje` TEXT,
            `data_regjistrimit` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `data_perditesimit` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY (`nr_personal`),
            UNIQUE KEY (`email`),
            UNIQUE KEY (`nr_licences`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        
        // Shto disa të dhëna demo
        $noterDemoData = [
            ['Arben', 'Krasniqi', '1234567890', 'LIC-2020-001', 'arben.krasniqi@noter.com', '044123456', 'Rr. Agim Ramadani nr. 23', 'Prishtinë', 'Kosovë', 'M', '1975-05-15', '2020-01-15', 'uploads/noter1.jpg', 'aktiv', ''],
            ['Vjosa', 'Berisha', '2345678901', 'LIC-2019-042', 'vjosa.berisha@noter.com', '045234567', 'Rr. Dardania nr. 5', 'Prizren', 'Kosovë', 'F', '1980-03-22', '2019-06-10', 'uploads/noter2.jpg', 'aktiv', ''],
            ['Driton', 'Hoxha', '3456789012', 'LIC-2018-118', 'driton.hoxha@noter.com', '049345678', 'Rr. Ilir Konushevci nr. 11', 'Pejë', 'Kosovë', 'M', '1972-11-08', '2018-11-20', 'uploads/noter3.jpg', 'aktiv', ''],
            ['Mimoza', 'Gashi', '4567890123', 'LIC-2021-033', 'mimoza.gashi@noter.com', '044456789', 'Rr. Qendra nr. 7', 'Gjakovë', 'Kosovë', 'F', '1983-07-19', '2021-03-05', 'uploads/noter4.jpg', 'aktiv', ''],
            ['Besnik', 'Rexhepi', '5678901234', 'LIC-2017-089', 'besnik.rexhepi@noter.com', '045567890', 'Rr. Lidhja e Prizrenit nr. 15', 'Ferizaj', 'Kosovë', 'M', '1970-02-28', '2017-09-12', 'uploads/noter5.jpg', 'joaktiv', 'Licenca e pezulluar përkohësisht']
        ];
        
        $insertStmt = $pdo->prepare("INSERT INTO noteret (emri, mbiemri, nr_personal, nr_licences, email, telefoni, adresa, qyteti, shteti, gjinia, data_lindjes, data_licencimit, foto, statusi, verejtje) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($noterDemoData as $noter) {
            $insertStmt->execute($noter);
        }
    }
    
    // Merr të gjithë noterët nga databaza
    $noteret = [];
    $stmt = $pdo->query("SELECT * FROM noteret ORDER BY emri, mbiemri");
    $noteret = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Procesi i filtrimit
    $filterBy = isset($_GET['filter']) ? $_GET['filter'] : '';
    $searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
    
    if (!empty($searchTerm)) {
        $searchStmt = $pdo->prepare("SELECT * FROM noteret WHERE 
            emri LIKE :term OR 
            mbiemri LIKE :term OR 
            email LIKE :term OR 
            qyteti LIKE :term OR
            nr_licences LIKE :term
            ORDER BY emri, mbiemri");
        $searchStmt->execute(['term' => "%$searchTerm%"]);
        $noteret = $searchStmt->fetchAll(PDO::FETCH_ASSOC);
    } else if (!empty($filterBy)) {
        switch ($filterBy) {
            case 'aktiv':
            case 'joaktiv':
            case 'pezulluar':
                $filterStmt = $pdo->prepare("SELECT * FROM noteret WHERE statusi = :status ORDER BY emri, mbiemri");
                $filterStmt->execute(['status' => $filterBy]);
                $noteret = $filterStmt->fetchAll(PDO::FETCH_ASSOC);
                break;
        }
    }
    
    // Procesi i shtimit ose përditësimit të noterit
    $successMessage = '';
    $errorMessage = '';
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        if ($_POST['action'] === 'add' || $_POST['action'] === 'edit') {
            $noterId = isset($_POST['noter_id']) ? $_POST['noter_id'] : null;
            $emri = $_POST['emri'];
            $mbiemri = $_POST['mbiemri'];
            $nrPersonal = $_POST['nr_personal'];
            $nrLicences = $_POST['nr_licences'];
            $email = $_POST['email'];
            $telefoni = $_POST['telefoni'];
            $adresa = $_POST['adresa'];
            $qyteti = $_POST['qyteti'];
            $shteti = $_POST['shteti'];
            $gjinia = $_POST['gjinia'];
            $dataLindjes = $_POST['data_lindjes'];
            $dataLicencimit = $_POST['data_licencimit'];
            $statusi = $_POST['statusi'];
            $verejtje = $_POST['verejtje'];
            
            // Kontrollo për foto të ngarkuar
            $foto = '';
            if (!empty($_FILES['foto']['name'])) {
                $targetDir = "uploads/";
                $fileName = time() . '_' . basename($_FILES['foto']['name']);
                $targetFile = $targetDir . $fileName;
                $uploadOk = 1;
                $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
                
                // Kontrollo nëse është imazh i vërtetë
                $check = getimagesize($_FILES['foto']['tmp_name']);
                if ($check === false) {
                    $errorMessage = "Skedari nuk është një imazh.";
                    $uploadOk = 0;
                }
                
                // Kontrollo madhësinë e skedarit
                if ($_FILES['foto']['size'] > 5000000) { // 5MB
                    $errorMessage = "Skedari është shumë i madh.";
                    $uploadOk = 0;
                }
                
                // Lejo vetëm disa formate
                if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg") {
                    $errorMessage = "Vetëm skedarët JPG, JPEG, PNG lejohen.";
                    $uploadOk = 0;
                }
                
                // Ngarko skedarin nëse është në rregull
                if ($uploadOk == 1) {
                    if (move_uploaded_file($_FILES['foto']['tmp_name'], $targetFile)) {
                        $foto = $targetFile;
                    } else {
                        $errorMessage = "Pati një problem me ngarkimin e fotos.";
                    }
                }
            }
            
            if (empty($errorMessage)) {
                try {
                    if ($_POST['action'] === 'add') {
                        $stmt = $pdo->prepare("INSERT INTO noteret (emri, mbiemri, nr_personal, nr_licences, email, telefoni, adresa, qyteti, shteti, gjinia, data_lindjes, data_licencimit, foto, statusi, verejtje) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $emri, $mbiemri, $nrPersonal, $nrLicences, $email, $telefoni, $adresa, $qyteti, $shteti, 
                            $gjinia, $dataLindjes, $dataLicencimit, $foto, $statusi, $verejtje
                        ]);
                        $successMessage = "Noteri u shtua me sukses!";
                    } else {
                        // Përditëso fotot vetëm nëse është ngarkuar një foto e re
                        if (!empty($foto)) {
                            $stmt = $pdo->prepare("UPDATE noteret SET emri = ?, mbiemri = ?, nr_personal = ?, nr_licences = ?, email = ?, telefoni = ?, adresa = ?, qyteti = ?, shteti = ?, gjinia = ?, data_lindjes = ?, data_licencimit = ?, foto = ?, statusi = ?, verejtje = ? WHERE id = ?");
                            $stmt->execute([
                                $emri, $mbiemri, $nrPersonal, $nrLicences, $email, $telefoni, $adresa, $qyteti, 
                                $shteti, $gjinia, $dataLindjes, $dataLicencimit, $foto, $statusi, $verejtje, $noterId
                            ]);
                        } else {
                            $stmt = $pdo->prepare("UPDATE noteret SET emri = ?, mbiemri = ?, nr_personal = ?, nr_licences = ?, email = ?, telefoni = ?, adresa = ?, qyteti = ?, shteti = ?, gjinia = ?, data_lindjes = ?, data_licencimit = ?, statusi = ?, verejtje = ? WHERE id = ?");
                            $stmt->execute([
                                $emri, $mbiemri, $nrPersonal, $nrLicences, $email, $telefoni, $adresa, $qyteti, 
                                $shteti, $gjinia, $dataLindjes, $dataLicencimit, $statusi, $verejtje, $noterId
                            ]);
                        }
                        $successMessage = "Të dhënat e noterit u përditësuan me sukses!";
                    }
                    
                    // Rifresho listën e noterëve
                    $stmt = $pdo->query("SELECT * FROM noteret ORDER BY emri, mbiemri");
                    $noteret = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) { // Duplicate entry
                        $errorMessage = "Gabim: Ky noter ekziston tashmë (email, numri personal ose numri i licencës duhet të jenë unikë).";
                    } else {
                        $errorMessage = "Gabim në databazë: " . $e->getMessage();
                    }
                }
            }
        } elseif ($_POST['action'] === 'delete' && isset($_POST['noter_id'])) {
            $noterId = $_POST['noter_id'];
            try {
                $stmt = $pdo->prepare("DELETE FROM noteret WHERE id = ?");
                $stmt->execute([$noterId]);
                $successMessage = "Noteri u fshi me sukses!";
                
                // Rifresho listën e noterëve
                $stmt = $pdo->query("SELECT * FROM noteret ORDER BY emri, mbiemri");
                $noteret = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $errorMessage = "Gabim në fshirjen e noterit: " . $e->getMessage();
            }
        }
    }
} catch (PDOException $e) {
    $errorMessage = "Gabim në lidhjen me databazën: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menaxhimi i Noterëve - Noteria Admin</title>
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
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }
        
        .card {
            background-color: var(--card-bg);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 1px solid var(--border);
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
        
        .table-container {
            overflow-x: auto;
            margin-bottom: 1.5rem;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
        }
        
        .table th, .table td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        
        .table th {
            background-color: var(--light);
            font-weight: 600;
            color: var(--text-dark);
            white-space: nowrap;
        }
        
        .table tbody tr:hover {
            background-color: rgba(243, 244, 246, 0.5);
        }
        
        .table td .actions {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
        }
        
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 9999px;
            text-transform: uppercase;
        }
        
        .badge-success {
            background-color: #dcfce7;
            color: #166534;
        }
        
        .badge-warning {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .badge-danger {
            background-color: #fee2e2;
            color: #991b1b;
        }
        
        .search-filter {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        
        .search-box {
            flex-grow: 1;
            position: relative;
        }
        
        .search-box input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 0.95rem;
            transition: var(--transition);
        }
        
        .search-box input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .search-box i {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
        }
        
        .filter-dropdown {
            position: relative;
        }
        
        .filter-dropdown select {
            padding: 0.75rem 2.5rem 0.75rem 1rem;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 0.95rem;
            transition: var(--transition);
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%236b7280'%3E%3Cpath fill-rule='evenodd' d='M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z' clip-rule='evenodd' /%3E%3C/svg%3E");
            background-position: right 0.75rem center;
            background-repeat: no-repeat;
            background-size: 1.25rem;
        }
        
        .filter-dropdown select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid white;
            box-shadow: var(--shadow-sm);
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            overflow-y: auto;
            padding: 1rem;
        }
        
        .modal-content {
            background-color: var(--card-bg);
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            max-width: 800px;
            margin: 2rem auto;
            animation: modalOpen 0.3s ease;
        }
        
        @keyframes modalOpen {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
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
        }
        
        .modal-close:hover {
            color: var(--danger);
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
        }
        
        .form-group {
            margin-bottom: 1.25rem;
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
        
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }
        
        .required-field::after {
            content: '*';
            color: var(--danger);
            margin-left: 0.25rem;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }
        
        .pagination-item {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 2.25rem;
            height: 2.25rem;
            padding: 0 0.5rem;
            font-size: 0.95rem;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            background-color: white;
            color: var(--text);
            text-decoration: none;
            transition: var(--transition);
        }
        
        .pagination-item:hover {
            background-color: var(--light);
        }
        
        .pagination-item.active {
            background-color: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
        }
        
        .empty-state-icon {
            font-size: 3rem;
            color: var(--text-light);
            margin-bottom: 1rem;
        }
        
        .empty-state-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--heading);
            margin-bottom: 0.5rem;
        }
        
        .empty-state-description {
            color: var(--text-light);
            margin-bottom: 1.5rem;
        }
        
        @media (max-width: 768px) {
            .admin-nav {
                display: none;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .table th, .table td {
                padding: 0.5rem;
            }
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
                <a href="noteret.php" class="admin-nav-item active">
                    <i class="fas fa-user-tie"></i> Noterët
                </a>
                <a href="statistikat.php" class="admin-nav-item">
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
            <h1 class="page-title"><i class="fas fa-user-tie"></i> Menaxhimi i Noterëve</h1>
            
            <button class="btn btn-primary" onclick="openModal('addNoterModal')">
                <i class="fas fa-plus"></i> Shto Noter
            </button>
        </div>
        
        <?php if (!empty($successMessage)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <div><?php echo $successMessage; ?></div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($errorMessage)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <div><?php echo $errorMessage; ?></div>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="search-filter">
                <form class="search-box" method="GET" action="">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Kërko noter..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                </form>
                
                <div class="filter-dropdown">
                    <select name="filter" onchange="this.form.submit()" form="filter-form">
                        <option value="">Të gjithë</option>
                        <option value="aktiv" <?php echo ($filterBy === 'aktiv') ? 'selected' : ''; ?>>Aktivë</option>
                        <option value="joaktiv" <?php echo ($filterBy === 'joaktiv') ? 'selected' : ''; ?>>Joaktivë</option>
                        <option value="pezulluar" <?php echo ($filterBy === 'pezulluar') ? 'selected' : ''; ?>>Të pezulluar</option>
                    </select>
                </div>
                
                <form id="filter-form" method="GET" action="">
                    <!-- Form për filtrimin -->
                </form>
            </div>
            
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 60px;"></th>
                            <th>Noteri</th>
                            <th>Kontakti</th>
                            <th>Nr. Licencës</th>
                            <th>Qyteti</th>
                            <th>Statusi</th>
                            <th style="width: 120px;">Veprimet</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($noteret)): ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <div class="empty-state-icon">
                                        <i class="fas fa-user-slash"></i>
                                    </div>
                                    <h3 class="empty-state-title">Nuk u gjetën noterë</h3>
                                    <p class="empty-state-description">Nuk ka noterë që përputhen me kriteret e kërkimit.</p>
                                    <a href="noteret.php" class="btn btn-primary">
                                        <i class="fas fa-sync-alt"></i> Rifresko
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($noteret as $noter): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($noter['foto']) && file_exists($noter['foto'])): ?>
                                        <img src="<?php echo $noter['foto']; ?>" alt="<?php echo $noter['emri']; ?>" class="avatar">
                                    <?php else: ?>
                                        <div class="avatar" style="background-color: #e5e7eb; display: flex; justify-content: center; align-items: center;">
                                            <i class="fas fa-user" style="color: #9ca3af;"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo $noter['emri'] . ' ' . $noter['mbiemri']; ?></strong><br>
                                    <small>ID: <?php echo $noter['nr_personal']; ?></small>
                                </td>
                                <td>
                                    <?php echo $noter['email']; ?><br>
                                    <?php echo $noter['telefoni']; ?>
                                </td>
                                <td><?php echo $noter['nr_licences']; ?></td>
                                <td><?php echo $noter['qyteti']; ?></td>
                                <td>
                                    <?php if ($noter['statusi'] === 'aktiv'): ?>
                                        <span class="badge badge-success">Aktiv</span>
                                    <?php elseif ($noter['statusi'] === 'joaktiv'): ?>
                                        <span class="badge badge-warning">Joaktiv</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Pezulluar</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="actions">
                                        <button class="btn btn-secondary btn-sm" onclick="editNoter(<?php echo htmlspecialchars(json_encode($noter)); ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-danger btn-sm" onclick="confirmDelete(<?php echo $noter['id']; ?>, '<?php echo $noter['emri'] . ' ' . $noter['mbiemri']; ?>')">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="pagination">
                <a href="#" class="pagination-item">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <a href="#" class="pagination-item active">1</a>
                <a href="#" class="pagination-item">2</a>
                <a href="#" class="pagination-item">3</a>
                <span class="pagination-item">...</span>
                <a href="#" class="pagination-item">10</a>
                <a href="#" class="pagination-item">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Modali për shtimin e noterit të ri -->
    <div id="addNoterModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title"><i class="fas fa-user-plus"></i> Shto Noter të Ri</h2>
                <button class="modal-close" onclick="closeModal('addNoterModal')">&times;</button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label required-field" for="emri">Emri</label>
                            <input type="text" class="form-control" id="emri" name="emri" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label required-field" for="mbiemri">Mbiemri</label>
                            <input type="text" class="form-control" id="mbiemri" name="mbiemri" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label required-field" for="nr_personal">Numri Personal</label>
                            <input type="text" class="form-control" id="nr_personal" name="nr_personal" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label required-field" for="nr_licences">Numri i Licencës</label>
                            <input type="text" class="form-control" id="nr_licences" name="nr_licences" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label required-field" for="email">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="telefoni">Telefoni</label>
                            <input type="tel" class="form-control" id="telefoni" name="telefoni">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="data_lindjes">Data e Lindjes</label>
                            <input type="date" class="form-control" id="data_lindjes" name="data_lindjes">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label required-field" for="data_licencimit">Data e Licencimit</label>
                            <input type="date" class="form-control" id="data_licencimit" name="data_licencimit" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="gjinia">Gjinia</label>
                            <select class="form-select" id="gjinia" name="gjinia">
                                <option value="">Zgjidhni gjininë</option>
                                <option value="M">Mashkull</option>
                                <option value="F">Femër</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label required-field" for="statusi">Statusi</label>
                            <select class="form-select" id="statusi" name="statusi" required>
                                <option value="aktiv">Aktiv</option>
                                <option value="joaktiv">Joaktiv</option>
                                <option value="pezulluar">Pezulluar</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="qyteti">Qyteti</label>
                            <input type="text" class="form-control" id="qyteti" name="qyteti">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="shteti">Shteti</label>
                            <input type="text" class="form-control" id="shteti" name="shteti" value="Kosovë">
                        </div>
                        
                        <div class="form-group full-width">
                            <label class="form-label" for="adresa">Adresa</label>
                            <textarea class="form-control" id="adresa" name="adresa" rows="2"></textarea>
                        </div>
                        
                        <div class="form-group full-width">
                            <label class="form-label" for="verejtje">Vërejtje</label>
                            <textarea class="form-control" id="verejtje" name="verejtje" rows="2"></textarea>
                        </div>
                        
                        <div class="form-group full-width">
                            <label class="form-label" for="foto">Foto</label>
                            <input type="file" class="form-control" id="foto" name="foto" accept="image/*">
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addNoterModal')">Anulo</button>
                    <button type="submit" class="btn btn-primary">Shto Noterin</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modali për editimin e noterit -->
    <div id="editNoterModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title"><i class="fas fa-user-edit"></i> Edito Noterin</h2>
                <button class="modal-close" onclick="closeModal('editNoterModal')">&times;</button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="noter_id" id="edit_noter_id">
                
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label required-field" for="edit_emri">Emri</label>
                            <input type="text" class="form-control" id="edit_emri" name="emri" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label required-field" for="edit_mbiemri">Mbiemri</label>
                            <input type="text" class="form-control" id="edit_mbiemri" name="mbiemri" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label required-field" for="edit_nr_personal">Numri Personal</label>
                            <input type="text" class="form-control" id="edit_nr_personal" name="nr_personal" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label required-field" for="edit_nr_licences">Numri i Licencës</label>
                            <input type="text" class="form-control" id="edit_nr_licences" name="nr_licences" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label required-field" for="edit_email">Email</label>
                            <input type="email" class="form-control" id="edit_email" name="email" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="edit_telefoni">Telefoni</label>
                            <input type="tel" class="form-control" id="edit_telefoni" name="telefoni">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="edit_data_lindjes">Data e Lindjes</label>
                            <input type="date" class="form-control" id="edit_data_lindjes" name="data_lindjes">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label required-field" for="edit_data_licencimit">Data e Licencimit</label>
                            <input type="date" class="form-control" id="edit_data_licencimit" name="data_licencimit" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="edit_gjinia">Gjinia</label>
                            <select class="form-select" id="edit_gjinia" name="gjinia">
                                <option value="">Zgjidhni gjininë</option>
                                <option value="M">Mashkull</option>
                                <option value="F">Femër</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label required-field" for="edit_statusi">Statusi</label>
                            <select class="form-select" id="edit_statusi" name="statusi" required>
                                <option value="aktiv">Aktiv</option>
                                <option value="joaktiv">Joaktiv</option>
                                <option value="pezulluar">Pezulluar</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="edit_qyteti">Qyteti</label>
                            <input type="text" class="form-control" id="edit_qyteti" name="qyteti">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="edit_shteti">Shteti</label>
                            <input type="text" class="form-control" id="edit_shteti" name="shteti" value="Kosovë">
                        </div>
                        
                        <div class="form-group full-width">
                            <label class="form-label" for="edit_adresa">Adresa</label>
                            <textarea class="form-control" id="edit_adresa" name="adresa" rows="2"></textarea>
                        </div>
                        
                        <div class="form-group full-width">
                            <label class="form-label" for="edit_verejtje">Vërejtje</label>
                            <textarea class="form-control" id="edit_verejtje" name="verejtje" rows="2"></textarea>
                        </div>
                        
                        <div class="form-group full-width">
                            <label class="form-label" for="edit_foto">Foto e Re</label>
                            <input type="file" class="form-control" id="edit_foto" name="foto" accept="image/*">
                            <div id="current_foto" style="margin-top: 0.5rem;"></div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editNoterModal')">Anulo</button>
                    <button type="submit" class="btn btn-primary">Ruaj Ndryshimet</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modali për konfirmimin e fshirjes -->
    <div id="deleteNoterModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h2 class="modal-title"><i class="fas fa-trash-alt"></i> Konfirmo Fshirjen</h2>
                <button class="modal-close" onclick="closeModal('deleteNoterModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p>A jeni i sigurt që dëshironi të fshini noterin <span id="delete_noter_name" style="font-weight: bold;"></span>?</p>
                <p style="color: var(--danger);">Ky veprim nuk mund të kthehet!</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('deleteNoterModal')">Anulo</button>
                <form method="POST" action="" id="delete-form">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="noter_id" id="delete_noter_id">
                    <button type="submit" class="btn btn-danger">Fshi</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Funksioni për hapjen e modalit
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        // Funksioni për mbylljen e modalit
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // Funksioni për editimin e noterit
        function editNoter(noter) {
            // Plotëso formën me të dhënat e noterit
            document.getElementById('edit_noter_id').value = noter.id;
            document.getElementById('edit_emri').value = noter.emri;
            document.getElementById('edit_mbiemri').value = noter.mbiemri;
            document.getElementById('edit_nr_personal').value = noter.nr_personal;
            document.getElementById('edit_nr_licences').value = noter.nr_licences;
            document.getElementById('edit_email').value = noter.email;
            document.getElementById('edit_telefoni').value = noter.telefoni || '';
            document.getElementById('edit_data_lindjes').value = noter.data_lindjes || '';
            document.getElementById('edit_data_licencimit').value = noter.data_licencimit || '';
            document.getElementById('edit_gjinia').value = noter.gjinia || '';
            document.getElementById('edit_statusi').value = noter.statusi;
            document.getElementById('edit_qyteti').value = noter.qyteti || '';
            document.getElementById('edit_shteti').value = noter.shteti || 'Kosovë';
            document.getElementById('edit_adresa').value = noter.adresa || '';
            document.getElementById('edit_verejtje').value = noter.verejtje || '';
            
            // Shfaq foton aktuale nëse ka
            const currentFotoDiv = document.getElementById('current_foto');
            if (noter.foto) {
                currentFotoDiv.innerHTML = `
                    <div style="margin-top: 0.5rem;">
                        <p>Foto aktuale:</p>
                        <img src="${noter.foto}" alt="${noter.emri}" style="max-height: 100px; max-width: 100px; border-radius: 4px;">
                    </div>
                `;
            } else {
                currentFotoDiv.innerHTML = '<p>Nuk ka foto.</p>';
            }
            
            // Hap modalin
            openModal('editNoterModal');
        }
        
        // Funksioni për konfirmimin e fshirjes
        function confirmDelete(noterId, noterName) {
            document.getElementById('delete_noter_id').value = noterId;
            document.getElementById('delete_noter_name').textContent = noterName;
            openModal('deleteNoterModal');
        }
        
        // Mbyll modalin kur klikohet jashtë
        window.addEventListener('click', function(event) {
            const modals = document.getElementsByClassName('modal');
            for (let i = 0; i < modals.length; i++) {
                if (event.target === modals[i]) {
                    closeModal(modals[i].id);
                }
            }
        });
    </script>
</body>
</html>