<?php
session_start();
require_once 'config.php';

$services = require_once 'services_pricing.php';
$lang = $_GET['lang'] ?? 'sq';
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $lang === 'sq' ? 'Çmimet e Shërbimeve' : ($lang === 'sr' ? 'Cene Usluga' : 'Service Prices'); ?> | e-Noteria</title>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Montserrat', Arial, sans-serif;
            background: linear-gradient(120deg, #e2eafc 0%, #f8fafc 100%);
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(44,108,223,0.08);
        }
        h1 {
            color: #2d6cdf;
            text-align: center;
            margin-bottom: 10px;
            font-size: 2.2rem;
        }
        .subtitle {
            color: #888;
            text-align: center;
            margin-bottom: 40px;
            font-size: 1.05rem;
        }
        .price-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }
        .price-card {
            background: linear-gradient(135deg, #f8fafc 0%, #fff 100%);
            border-radius: 12px;
            padding: 28px;
            border-left: 5px solid #2d6cdf;
            box-shadow: 0 4px 16px rgba(44,108,223,0.1);
            transition: all 0.3s ease;
        }
        .price-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 8px 28px rgba(44,108,223,0.15);
            border-left-color: #184fa3;
        }
        .service-name {
            font-size: 1.3rem;
            font-weight: 700;
            color: #2d6cdf;
            margin-bottom: 8px;
        }
        .service-description {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 16px;
            line-height: 1.6;
        }
        .price-breakdown {
            background: #f0f4ff;
            padding: 16px;
            border-radius: 8px;
            font-size: 0.95rem;
        }
        .price-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        .price-row:last-child {
            margin-bottom: 0;
            border-top: 2px solid #ddd;
            padding-top: 10px;
            font-weight: 700;
            color: #2d6cdf;
            font-size: 1.1rem;
        }
        .price-base {
            color: #666;
        }
        .price-vat {
            color: #888;
            font-size: 0.85rem;
        }
        .price-total {
            color: #2d6cdf;
            font-weight: 700;
        }
        .info-box {
            background: linear-gradient(135deg, #e8f5e9 0%, #f1f8f4 100%);
            border-left: 5px solid #388e3c;
            padding: 20px;
            border-radius: 8px;
            margin-top: 40px;
            color: #2d5a2d;
        }
        .info-box h3 {
            margin-top: 0;
            color: #1a7a4a;
        }
        .vat-notice {
            background: linear-gradient(135deg, #fff3cd 0%, #fffbea 100%);
            border-left: 5px solid #ff9800;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 40px;
            color: #5a4a00;
        }
        .vat-notice strong {
            color: #d97706;
        }
        .nav-links {
            text-align: center;
            margin-top: 30px;
        }
        .nav-links a {
            display: inline-block;
            margin: 0 10px;
            padding: 10px 20px;
            background: #2d6cdf;
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: background 0.3s;
        }
        .nav-links a:hover {
            background: #184fa3;
        }
        .language-select {
            position: absolute;
            top: 20px;
            right: 20px;
        }
    </style>
</head>
<body>
    <div class="language-select">
        <select onchange="location.href='?lang='+this.value" style="padding: 8px; border-radius: 6px; border: 1px solid #ddd; font-weight: 600;">
            <option value="sq" <?php echo $lang === 'sq' ? 'selected' : ''; ?>>Shqip 🇦🇱</option>
            <option value="en" <?php echo $lang === 'en' ? 'selected' : ''; ?>>English 🇬🇧</option>
            <option value="sr" <?php echo $lang === 'sr' ? 'selected' : ''; ?>>Српски 🇷🇸</option>
        </select>
    </div>

    <div class="container">
        <h1>💰 <?php echo $lang === 'sq' ? 'Çmimet e Shërbimeve Noteriale' : ($lang === 'sr' ? 'Cene Notarskih Usluga' : 'Notarial Services Pricing'); ?></h1>
        <p class="subtitle">
            <?php 
            if ($lang === 'sq') echo '📍 Platforma e-Noteria, Republika e Kosovës';
            elseif ($lang === 'sr') echo '📍 e-Noteria platforma, Republika Kosovska';
            else echo '📍 e-Noteria Platform, Republic of Kosovo';
            ?>
        </p>

        <div class="vat-notice">
            <strong><?php echo $lang === 'sq' ? '⚠️ Vënim i rëndësishëm:' : ($lang === 'sr' ? '⚠️ Važno napomenuti:' : '⚠️ Important Notice:'); ?></strong><br>
            <?php 
            if ($lang === 'sq') echo 'Të gjithë çmimet e mëposhtëm përfshijnë <strong>TVSH 18%</strong> (Tatim mbi Vlerën e Shtuar). Çmimet janë të vlefshme në të gjitha zyrat noteriale të platformës e-Noteria.';
            elseif ($lang === 'sr') echo 'Sve donje navedene cene uključuju <strong>PDV 18%</strong> (Porez na dodanu vrednost). Cene su važeće u svim notarskim kancelarijama na platformi e-Noteria.';
            else echo 'All prices below include <strong>VAT 18%</strong> (Value Added Tax). Prices are valid at all notarial offices on the e-Noteria platform.';
            ?>
        </div>

        <div class="price-grid">
            <?php foreach ($services as $key => $service): 
                $price = $service['price'];
                $vat = $price * 0.18;
                $total = $price + $vat;
                $name = $lang === 'sq' ? $service['name_sq'] : ($lang === 'sr' ? $service['name_sr'] : $service['name_en']);
                $desc = $lang === 'sq' ? $service['description_sq'] : $service['description_en'];
            ?>
            <div class="price-card">
                <div class="service-name">📋 <?php echo $name; ?></div>
                <div class="service-description"><?php echo $desc; ?></div>
                <div class="price-breakdown">
                    <div class="price-row">
                        <span class="price-base"><?php echo $lang === 'sq' ? 'Çmim bazë:' : ($lang === 'sr' ? 'Osnovna cena:' : 'Base price:'); ?></span>
                        <span><?php echo number_format($price, 2); ?>€</span>
                    </div>
                    <div class="price-row">
                        <span class="price-vat"><?php echo $lang === 'sq' ? 'TVSH 18%:' : ($lang === 'sr' ? 'PDV 18%:' : 'VAT 18%:'); ?></span>
                        <span class="price-vat">+<?php echo number_format($vat, 2); ?>€</span>
                    </div>
                    <div class="price-row">
                        <strong><?php echo $lang === 'sq' ? 'Çmim Final:' : ($lang === 'sr' ? 'Finalna cena:' : 'Final Price:'); ?></strong>
                        <strong class="price-total"><?php echo number_format($total, 2); ?>€</strong>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="info-box">
            <h3>ℹ️ <?php echo $lang === 'sq' ? 'Informacione të tjera' : ($lang === 'sr' ? 'Ostale informacije' : 'Additional Information'); ?></h3>
            <p>
                <?php 
                if ($lang === 'sq') {
                    echo 'Këto çmime janë të vlefshme për vitin 2026. Ato mund të ndryshojnë në përputhje me ndryshimet ligjore të Ministrisë së Drejtësisë të Republikës së Kosovës. ' .
                    'Për më shumë informacione ose pyetje, ju lutemi kontaktoni zyrën noteriale më të afërt ose platformën e-Noteria.';
                } elseif ($lang === 'sr') {
                    echo 'Ove cene važe za godinu 2026. One se mogu promeniti u skladu sa zakonskim promenama Ministarstva pravosuđa Republike Kosovo. ' .
                    'Za više informacija ili pitanja, molimo vas da kontaktirate najbližu notarsku kancelariju ili platformu e-Noteria.';
                } else {
                    echo 'These prices are valid for 2026. They may change in accordance with legal changes by the Ministry of Justice of the Republic of Kosovo. ' .
                    'For more information or inquiries, please contact the nearest notarial office or e-Noteria platform.';
                }
                ?>
            </p>
        </div>

        <div class="nav-links">
            <a href="dashboard.php">🏠 <?php echo $lang === 'sq' ? 'Kthehu në Dashboard' : ($lang === 'sr' ? 'Nazad na Kontrolnu Panel' : 'Back to Dashboard'); ?></a>
            <a href="reservation.php">📅 <?php echo $lang === 'sq' ? 'Rezervo Termin' : ($lang === 'sr' ? 'Rezerviši Termin' : 'Book Appointment'); ?></a>
        </div>
    </div>
</body>
</html>
