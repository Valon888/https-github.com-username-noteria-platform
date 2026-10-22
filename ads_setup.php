<?php
session_start();
require_once 'config.php';

// Kontrollo nëse përdoruesi është admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    echo "<script>alert('Duhet të jesh i loguar si admin'); window.location.href='login.php';</script>";
    exit();
}

// Shembuj të reklamave për testing
$sample_ads = [
    [
        'business_name' => 'Tech Solutions Ltd',
        'business_contact' => '+383 45 123 456',
        'business_email' => 'info@techsolutions.com',
        'ad_title' => 'Shërbime IT Profesionale',
        'ad_description' => 'Zgjidhjet më të avancuara në teknologji për bizneset tuaj. Konsultim, instalim dhe support 24/7.',
        'ad_link' => 'https://techsolutions.example.com',
        'ad_type' => 'banner'
    ],
    [
        'business_name' => 'Clean Pro',
        'business_contact' => '+383 49 555 777',
        'business_email' => 'contact@cleanpro.com',
        'ad_title' => 'Pastrimi Profesional i Zyrës',
        'ad_description' => 'Zyrë të pastër dhe të shëndetshme! Përdorim produkteve ekologo dhe procedurash standarde botërore.',
        'ad_link' => 'https://cleanpro.example.com',
        'ad_type' => 'card'
    ],
    [
        'business_name' => 'Marketing Plus',
        'business_contact' => '+383 47 888 999',
        'business_email' => 'hello@marketingplus.com',
        'ad_title' => 'Zhvillim Marketingu Dixhital',
        'ad_description' => 'Rritet biznesi juaj në internet me strategjitë më efikase të SEO, Social Media dhe Advertising.',
        'ad_link' => 'https://marketingplus.example.com',
        'ad_type' => 'card'
    ]
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'load_samples') {
        try {
            foreach ($sample_ads as $ad) {
                $stmt = $pdo->prepare("
                    INSERT INTO ads (business_name, business_contact, business_email, ad_title, ad_description, ad_link, ad_type, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([
                    $ad['business_name'],
                    $ad['business_contact'],
                    $ad['business_email'],
                    $ad['ad_title'],
                    $ad['ad_description'],
                    $ad['ad_link'],
                    $ad['ad_type']
                ]);
            }
            $message = "✓ 3 reklama shembulli u ngarkuan me sukses!";
        } catch (Exception $e) {
            $error = "✗ Gabim: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup i Reklamave | Noteria</title>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,500,600,700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 700px;
            width: 100%;
            padding: 40px;
        }

        h1 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 2rem;
        }

        .subtitle {
            color: #6b7280;
            margin-bottom: 30px;
            font-size: 1rem;
        }

        .info-box {
            background: #eff6ff;
            border-left: 4px solid #3b82f6;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 30px;
            color: #1e40af;
        }

        .feature-list {
            list-style: none;
            margin-bottom: 30px;
        }

        .feature-list li {
            padding: 12px 0;
            border-bottom: 1px solid #f3f4f6;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #1f2937;
        }

        .feature-list li:last-child {
            border-bottom: none;
        }

        .feature-list li:before {
            content: "✓";
            color: #10b981;
            font-weight: 700;
            font-size: 1.2rem;
            min-width: 24px;
        }

        .message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        .buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-top: 30px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.95rem;
            text-decoration: none;
            text-align: center;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
        }

        .btn-secondary:hover {
            background: #f9fafb;
        }

        .divider {
            border-top: 2px solid #f3f4f6;
            margin: 30px 0;
            position: relative;
        }

        .divider:after {
            content: "ose";
            position: absolute;
            top: -12px;
            left: 50%;
            transform: translateX(-50%);
            background: white;
            padding: 0 12px;
            color: #6b7280;
            font-size: 0.9rem;
        }

        .steps {
            background: #f9fafb;
            padding: 20px;
            border-radius: 8px;
            margin-top: 30px;
        }

        .step {
            margin-bottom: 16px;
            padding-bottom: 16px;
            border-bottom: 1px solid #e5e7eb;
        }

        .step:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .step-num {
            background: #667eea;
            color: white;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            margin-bottom: 8px;
        }

        @media (max-width: 600px) {
            .container {
                padding: 20px;
            }

            h1 {
                font-size: 1.5rem;
            }

            .buttons {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📢 Sistemi i Reklamave</h1>
        <p class="subtitle">Menaxhimi i reklamave të bizneseve në platformën Noteria</p>

        <?php if (isset($message)): ?>
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="info-box">
            <strong>🎯 Karakteristikat e sistemit:</strong>
            <ul class="feature-list">
                <li>Menaxhimi i plotë i reklamave</li>
                <li>Trackimi i shikimeve dhe klikov</li>
                <li>4 lloje reklamash (Banner, Card, Popup, Sidebar)</li>
                <li>100% responsive design</li>
                <li>Estatistika dhe raporte</li>
            </ul>
        </div>

        <div class="steps">
            <h3 style="margin-bottom: 16px; color: #667eea;">📋 Hapat për të filluar:</h3>
            
            <div class="step">
                <div class="step-num">1</div>
                <strong>Krijo tabelat e bazës së të dhënave</strong>
                <p style="margin-top: 6px; color: #6b7280; font-size: 0.95rem;">Kliko butonin më poshtë për të krijuar tabelat</p>
            </div>

            <div class="step">
                <div class="step-num">2</div>
                <strong>Shto reklamat shembulli</strong>
                <p style="margin-top: 6px; color: #6b7280; font-size: 0.95rem;">Ngarko disa reklama shembulli për të testuar</p>
            </div>

            <div class="step">
                <div class="step-num">3</div>
                <strong>Menaxho reklamat tuaja</strong>
                <p style="margin-top: 6px; color: #6b7280; font-size: 0.95rem;">Shto, ndrysho ose fshi reklamat në panelin admin</p>
            </div>

            <div class="step">
                <div class="step-num">4</div>
                <strong>Monitoroni performancën</strong>
                <p style="margin-top: 6px; color: #6b7280; font-size: 0.95rem;">Shihni statistikat e klikov dhe shikimeve</p>
            </div>
        </div>

        <div class="buttons">
            <a href="create_ads_table.php" class="btn btn-primary">🗂️ Krijo Tabelat</a>
            
            <form method="POST" style="display: contents;">
                <input type="hidden" name="action" value="load_samples">
                <button type="submit" class="btn btn-primary">📥 Ngarko Shembujt</button>
            </form>

            <a href="admin_ads_manager.php" class="btn btn-secondary">⚙️ Panel Admin</a>
            
            <a href="dashboard.php" class="btn btn-secondary">← Kthehu</a>
        </div>
    </div>
</body>
</html>
