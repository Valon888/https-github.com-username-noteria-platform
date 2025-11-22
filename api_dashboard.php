<?php
// api_dashboard.php - Paneli kryesor i API për administratorët
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

require_once 'config.php';
session_start();

// Kontrollo nëse përdoruesi është i autentifikuar si admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Dashboard | Noteria</title>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #1a56db;
            --primary-hover: #1e40af;
            --secondary-color: #6b7280;
            --light-bg: #f8fafc;
            --border-color: #e2e8f0;
            --text-color: #374151;
            --heading-color: #1e293b;
            --success-color: #16a34a;
            --warning-color: #f59e0b;
            --danger-color: #dc2626;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Montserrat', Arial, sans-serif;
            line-height: 1.6;
            color: var(--text-color);
            background-color: var(--light-bg);
            min-height: 100vh;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        h1 {
            color: var(--primary-color);
            margin-bottom: 25px;
            font-size: 2rem;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 15px;
        }
        
        .welcome-box {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 30px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .welcome-icon {
            font-size: 4rem;
            color: var(--primary-color);
            margin-bottom: 20px;
        }
        
        .welcome-title {
            font-size: 1.8rem;
            color: var(--heading-color);
            margin-bottom: 15px;
        }
        
        .welcome-text {
            font-size: 1.1rem;
            color: var(--secondary-color);
            max-width: 800px;
            margin: 0 auto 30px;
        }
        
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 25px;
            transition: transform 0.2s, box-shadow 0.2s;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
        }
        
        .card-icon {
            font-size: 3rem;
            margin-bottom: 20px;
        }
        
        .card-title {
            font-size: 1.4rem;
            color: var(--heading-color);
            margin-bottom: 15px;
        }
        
        .card-text {
            color: var(--secondary-color);
            margin-bottom: 20px;
        }
        
        .btn {
            background-color: var(--primary-color);
            color: white;
            padding: 12px 25px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            transition: background-color 0.2s;
            margin-top: auto;
        }
        
        .btn:hover {
            background-color: var(--primary-hover);
        }
        
        .btn i {
            margin-right: 10px;
        }
        
        .info-section {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 1.4rem;
            color: var(--heading-color);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .quick-links {
            margin-top: 50px;
            text-align: center;
        }
        
        .quick-links h2 {
            margin-bottom: 25px;
            font-size: 1.6rem;
            color: var(--heading-color);
        }
        
        .quick-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .quick-link {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            padding: 15px;
            text-decoration: none;
            color: var(--text-color);
            display: flex;
            align-items: center;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .quick-link:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.12);
        }
        
        .quick-link i {
            font-size: 1.5rem;
            margin-right: 15px;
            color: var(--primary-color);
        }
        
        /* Ngjyra specifike për çdo kartë */
        .card-primary .card-icon { color: var(--primary-color); }
        .card-success .card-icon { color: var(--success-color); }
        .card-warning .card-icon { color: var(--warning-color); }
        .card-danger .card-icon { color: var(--danger-color); }
    </style>
</head>
<body>
    <div class="container">
        <h1>API Dashboard</h1>
        
        <div class="welcome-box">
            <div class="welcome-icon">
                <i class="fas fa-code"></i>
            </div>
            <h2 class="welcome-title">Mirë se vini në Panelin e API-t</h2>
            <p class="welcome-text">
                Ky panel ju ofron mjete për menaxhimin e API-t, gjenerimin e token-ave, monitorimin e trafikut, dhe testimin e pikave fundore. 
                Përdorni kartat e mëposhtme për të aksesuar funksionalitetet e ndryshme të API-t.
            </p>
        </div>
        
        <div class="grid">
            <div class="card card-primary">
                <div class="card-icon">
                    <i class="fas fa-key"></i>
                </div>
                <h3 class="card-title">Token Generator</h3>
                <p class="card-text">
                    Krijoni dhe menaxhoni token-at API për autentifikimin e kërkesave. Vendosni data skadimi dhe përshkrime për token-at tuaj.
                </p>
                <a href="token_generator.php" class="btn">
                    <i class="fas fa-arrow-right"></i> Gjeneroni token-a
                </a>
            </div>
            
            <div class="card card-success">
                <div class="card-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h3 class="card-title">Monitorimi i API-t</h3>
                <p class="card-text">
                    Shikoni statistikat e përdorimit të API-t, trafikun, kohën e përgjigjes dhe shpërndarjen e kërkesave.
                </p>
                <a href="api_monitor.php" class="btn">
                    <i class="fas fa-arrow-right"></i> Monitoroni trafikun
                </a>
            </div>
            
            <div class="card card-warning">
                <div class="card-icon">
                    <i class="fas fa-flask"></i>
                </div>
                <h3 class="card-title">Test Client</h3>
                <p class="card-text">
                    Testoni pikat fundore të API-t me një ndërfaqe të thjeshtë. Provoni kërkesat GET, POST, PUT dhe DELETE.
                </p>
                <a href="api_client_test.php" class="btn">
                    <i class="fas fa-arrow-right"></i> Testoni API-n
                </a>
            </div>
            
            <div class="card card-danger">
                <div class="card-icon">
                    <i class="fas fa-bug"></i>
                </div>
                <h3 class="card-title">Debug Tool</h3>
                <p class="card-text">
                    Diagnostifikoni probleme me API-n dhe kryeni teste të automatizuara të të gjitha pikave fundore.
                </p>
                <a href="api_debug.php" class="btn">
                    <i class="fas fa-arrow-right"></i> Hapni mjetin
                </a>
            </div>
        </div>
        
        <div class="info-section">
            <h3 class="section-title">Dokumentim për zhvilluesit</h3>
            <p>
                API i Noteria ofron një sërë pikash fundore për të aksesuar dhe menaxhuar të dhënat e aplikacionit.
                Për një dokumentim të plotë të API-t, përfshirë shembuj kërkesash dhe përgjigjeshe, vizitoni
                <a href="api_docs.php">dokumentimin e API-t</a>.
            </p>
            <p>
                Nëse dëshironi të instaloni ose përditësoni tabelat e nevojshme për API-n, vizitoni
                <a href="api_install.php">faqen e instalimit</a>.
            </p>
        </div>
        
        <div class="quick-links">
            <h2>Lidhje të shpejta</h2>
            <div class="quick-grid">
                <a href="api_docs.php" class="quick-link">
                    <i class="fas fa-book"></i>
                    <span>Dokumentimi i API-t</span>
                </a>
                <a href="api_install.php" class="quick-link">
                    <i class="fas fa-wrench"></i>
                    <span>Instalimi i API-t</span>
                </a>
                <a href="mcp_api_new.php?endpoint=info" class="quick-link">
                    <i class="fas fa-info-circle"></i>
                    <span>Info Endpoint</span>
                </a>
                <a href="api_index.php" class="quick-link">
                    <i class="fas fa-home"></i>
                    <span>API Home</span>
                </a>
            </div>
        </div>
    </div>
</body>
</html>