<?php
/**
 * Faqja e Setup - Guida për Instalimin
 * Shfaq udhëzimet hap pas hapi
 */

session_start();

// Kontrollimi i autentifikimit - duhet të jeni admin ose të mos jeni logyrë
$is_admin = isset($_SESSION['roli']) && $_SESSION['roli'] === 'admin';
$is_logged_in = isset($_SESSION['user_id']);

if ($is_logged_in && !$is_admin) {
    echo "<div style='text-align:center; padding:40px; background:#ffebee; color:#c62828; border-radius:8px;'>";
    echo "<h2>❌ Akses i Refuzuar</h2>";
    echo "<p>Vetëm adminët mund të aksesojnë këtë faqe.</p>";
    echo "<a href='dashboard.php' style='color:#2d6cdf;'>Kthehu në Dashboard</a>";
    echo "</div>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup - Sistemi i Reklamave | Noteria</title>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Montserrat', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .setup-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 900px;
            padding: 50px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 50px;
        }
        
        h1 {
            color: #2d6cdf;
            margin-bottom: 10px;
            font-size: 2.5rem;
        }
        
        .subtitle {
            color: #666;
            font-size: 1.1rem;
        }
        
        .steps {
            display: grid;
            gap: 30px;
        }
        
        .step {
            padding: 25px;
            background: linear-gradient(135deg, #f0f4ff 0%, #e8f0ff 100%);
            border-left: 5px solid #2d6cdf;
            border-radius: 8px;
            position: relative;
        }
        
        .step-number {
            position: absolute;
            top: -15px;
            left: 20px;
            background: #2d6cdf;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.2rem;
        }
        
        .step-title {
            color: #184fa3;
            font-size: 1.3rem;
            margin-bottom: 15px;
            margin-top: 5px;
            font-weight: 700;
        }
        
        .step-description {
            color: #555;
            line-height: 1.8;
            margin-bottom: 15px;
            font-size: 1rem;
        }
        
        .step-action {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #2d6cdf;
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            font-size: 1rem;
        }
        
        .btn:hover {
            background: #184fa3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(45, 108, 223, 0.4);
        }
        
        .btn-success {
            background: #388e3c;
        }
        
        .btn-success:hover {
            background: #2e7d32;
        }
        
        .btn-secondary {
            background: #888;
        }
        
        .btn-secondary:hover {
            background: #666;
        }
        
        .code-block {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 6px;
            border-left: 4px solid #2d6cdf;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            overflow-x: auto;
            margin: 10px 0;
            color: #333;
        }
        
        .feature-list {
            list-style: none;
            padding: 0;
            margin: 15px 0;
        }
        
        .feature-list li {
            padding: 8px 0;
            color: #555;
        }
        
        .feature-list li:before {
            content: "✅ ";
            color: #388e3c;
            font-weight: bold;
            margin-right: 10px;
        }
        
        .warning {
            background: #fff3cd;
            border-left: 5px solid #ffc107;
            padding: 15px;
            border-radius: 6px;
            color: #856404;
            margin: 15px 0;
        }
        
        .success-checkmark {
            color: #388e3c;
            font-size: 1.5rem;
            margin-right: 10px;
        }
        
        .completed {
            background: #e8f5e9;
            border-left-color: #388e3c;
        }
        
        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #e2eafc;
            color: #666;
        }
        
        .footer a {
            color: #2d6cdf;
            text-decoration: none;
            font-weight: 600;
        }
        
        .footer a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .setup-container {
                padding: 30px 20px;
            }
            
            h1 {
                font-size: 1.8rem;
            }
            
            .step {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="header">
            <h1>🎯 Setup - Sistemi i Reklamave</h1>
            <p class="subtitle">Përcaktoni platformën e reklamave në 3 hapa të thjeshtë</p>
        </div>
        
        <div class="steps">
            <!-- STEP 1 -->
            <div class="step">
                <div class="step-number">1</div>
                <div class="step-title">Krijoni Tabelat e Database</div>
                <p class="step-description">
                    Sistemi juaj duhet të ketë tabelat e ndahuara për reklamat. Klikoni butonin më poshtë për të krijuar automatikisht:
                </p>
                
                <ul class="feature-list">
                    <li><code>advertisers</code> - Bizneset reklamues</li>
                    <li><code>advertisements</code> - Reklamat aktuale</li>
                    <li><code>ad_placements</code> - Vendndodhjet</li>
                    <li><code>ad_impressions</code> - Statistika të shikimeve</li>
                </ul>
                
                <div class="warning">
                    ⚠️ Kini sigurt se keni bektap të bazës të dhënave përpara se të ekzekutoni këtë!
                </div>
                
                <div class="step-action">
                    <a href="setup_ads_database.php" class="btn btn-success" target="_blank">
                        🚀 Krijo Tabelat
                    </a>
                    <span style="align-self: center; color: #666;">
                        Kjo faqe do të thirret në një tab të ri
                    </span>
                </div>
            </div>
            
            <!-- STEP 2 -->
            <div class="step">
                <div class="step-number">2</div>
                <div class="step-title">Hyni në Admin Panel</div>
                <p class="step-description">
                    Pasi tabelat të jenë krijuar, hyni në admin panel për të shtuar biznese dhe reklama:
                </p>
                
                <ul class="feature-list">
                    <li>Shtim biznese reklamuesve të ri</li>
                    <li>Krijoni reklama të reja</li>
                    <li>Menaxhoni vendndodhjet e shfaqjes</li>
                    <li>Shikoni statistika të reklamave</li>
                </ul>
                
                <div class="code-block">
                    http://localhost/noteria/admin_ads.php
                </div>
                
                <div class="step-action">
                    <a href="admin_ads.php" class="btn" target="_blank">
                        📊 Hap Admin Panel
                    </a>
                </div>
            </div>
            
            <!-- STEP 3 -->
            <div class="step">
                <div class="step-number">3</div>
                <div class="step-title">Vizitoni Marketplace</div>
                <p class="step-description">
                    Përdoruesit mund të shikojnë të gjitha reklamat aktive në marketplace:
                </p>
                
                <ul class="feature-list">
                    <li>Filtrimi i reklamave sipas tipit</li>
                    <li>Shikimi i të dhënave të reklamuesit</li>
                    <li>Klikimi për vizita në faqen e partnerit</li>
                </ul>
                
                <div class="code-block">
                    http://localhost/noteria/marketplace.php
                </div>
                
                <div class="step-action">
                    <a href="marketplace.php" class="btn" target="_blank">
                        🛍️ Hap Marketplace
                    </a>
                </div>
            </div>
        </div>
        
        <!-- QUICK START GUIDE -->
        <div style="background: #e8f5e9; border-radius: 8px; padding: 25px; margin-top: 40px;">
            <h2 style="color: #2e7d32; margin-bottom: 15px;">⚡ Quick Start</h2>
            <ol style="color: #555; line-height: 2;">
                <li><strong>Logohuni si admin</strong> nëse nuk jeni të loguar</li>
                <li><strong>Klikoni "Krijo Tabelat"</strong> në hapin 1</li>
                <li><strong>Hyni në Admin Panel</strong> dhe shtoni biznese</li>
                <li><strong>Krijoni reklamat e para</strong> të reja</li>
                <li><strong>Shikoni në marketplace</strong> resultat</li>
            </ol>
        </div>
        
        <!-- DOCUMENTATION -->
        <div style="background: #f0f4ff; border-radius: 8px; padding: 25px; margin-top: 20px;">
            <h2 style="color: #184fa3; margin-bottom: 15px;">📚 Dokumentacioni</h2>
            <p style="color: #555; margin-bottom: 15px;">
                Për dokumentacion më të detajuar, lexoni guidën e plotë:
            </p>
            <a href="ADS_DOCUMENTATION.md" class="btn btn-secondary" target="_blank">
                📖 Lexo Dokumentacionin
            </a>
        </div>
        
        <div class="footer">
            <p>
                Keni pyetje? 
                <a href="dashboard.php">Kthehu në Dashboard</a>
            </p>
            <small style="display: block; margin-top: 15px; color: #999;">
                Noteria Advertising System v1.0
            </small>
        </div>
    </div>
</body>
</html>
