<?php
// tinky_payment.php - Handler për pagesat Tinky
// Ky file do të përdoret si action për butonin "Paguaj me Tinky"

// 1. Lexo të dhënat e rezervimit dhe userit
// 2. Krijo kërkesën për pagesë në Tinky (API call)
// 3. Redirect ose shfaq linkun për pagesë
// 4. Pas suksesit, ruaj statusin në DB dhe shfaq konfirmimin

// DEMO: Ky është vetëm shembull bazik, duhet të zëvendësohet me integrimin real të Tinky


session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Verifiko CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    die('Veprimi i paautorizuar! CSRF token mungon ose është i pavlefshëm.');
}

$userId = $_SESSION['user_id'];
$reservationId = $_POST['reservation_id'] ?? null;
$amount = $_POST['amount'] ?? null;
$payer_name = trim($_POST['payer_name'] ?? '');
$payer_iban = trim($_POST['payer_iban'] ?? '');
$description = trim($_POST['description'] ?? '');

// Validim i plotë
$errors = [];
if (!$reservationId) $errors[] = 'Rezervimi nuk u gjet.';
if (!$amount || (float)$amount < 10) $errors[] = 'Shuma duhet të jetë minimum €10.';
if (strlen($payer_name) < 3) $errors[] = 'Emri duhet të ketë minimum 3 karaktere.';
if (!preg_match('/^[A-Z0-9]{15,34}$/', $payer_iban)) $errors[] = 'IBAN është i pavlefshëm.';
if (strlen($description) < 5) $errors[] = 'Përshkrimi duhet të ketë minimum 5 karaktere.';

if (count($errors) > 0) {
    $errorMsg = implode('<br>', array_map(function($e) { return '• ' . htmlspecialchars($e); }, $errors));
    ?><!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gabim në Validim</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;700&display=swap" rel="stylesheet">
    <style>
        body { background: linear-gradient(120deg, #f8fafc 0%, #ffe0e0 100%); font-family: 'Montserrat', Arial, sans-serif; min-height: 100vh; margin: 0; display: flex; align-items: center; justify-content: center; }
        .error-card {
            background: #fff;
            max-width: 450px;
            width: 90%;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(180,80,80,0.15);
            padding: 40px;
            text-align: center;
            border: 3px solid #ff3b3b;
        }
        .error-icon { font-size: 4em; margin-bottom: 16px; }
        .error-title { font-size: 1.5em; font-weight: 700; color: #b30000; margin-bottom: 16px; }
        .error-list { text-align: left; background: #fff3f3; padding: 16px; border-radius: 8px; margin: 20px 0; color: #555; font-size: 1em; line-height: 1.6; }
        .error-btn { display: inline-block; background: linear-gradient(90deg, #ff6600 0%, #ffb347 100%); color: #fff; font-weight: 600; border: none; border-radius: 8px; padding: 12px 32px; font-size: 1.1em; cursor: pointer; box-shadow: 0 2px 8px #ff660033; text-decoration: none; margin-top: 20px; transition: all 0.3s; }
        .error-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 20px #ff660044; }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="error-icon">⚠️</div>
        <div class="error-title">Gabim në Validim të Të Dhënave</div>
        <div class="error-list"><?php echo $errorMsg; ?></div>
        <div style="color: #555; font-size: 1em; margin-top: 16px;">Ju lutem kthehuni dhe korrigjoni të dhënat tuaja.</div>
        <a href="reservation.php" class="error-btn">← Kthehu te Rezervimi</a>
    </div>
</body>
</html>
<?php
    exit();
}

if (!$reservationId || !$amount) {
    ?>
    <!DOCTYPE html>
    <html lang="sq">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Gabim në Pagesë</title>
        <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;700&display=swap" rel="stylesheet">
        <style>
            body { background: linear-gradient(120deg, #f8fafc 0%, #ffe0e0 100%); font-family: 'Montserrat', Arial, sans-serif; min-height: 100vh; margin: 0; }
            .error-card {
                background: #fff;
                max-width: 420px;
                margin: 60px auto 0 auto;
                border-radius: 18px;
                box-shadow: 0 8px 32px rgba(180,80,80,0.10), 0 1.5px 6px rgba(0,0,0,0.04);
                padding: 36px 32px 32px 32px;
                text-align: center;
            }
            .error-icon {
                font-size: 3em;
                color: #ff3b3b;
                margin-bottom: 12px;
            }
            .error-title {
                font-size: 1.4em;
                font-weight: 700;
                color: #b30000;
                margin-bottom: 10px;
            }
            .error-desc {
                color: #444;
                font-size: 1.08em;
                margin-bottom: 18px;
            }
            .error-btn {
                display: inline-block;
                background: linear-gradient(90deg, #ff6600 0%, #ffb347 100%);
                color: #fff;
                font-weight: 600;
                border: none;
                border-radius: 8px;
                padding: 12px 32px;
                font-size: 1.1em;
                cursor: pointer;
                box-shadow: 0 2px 8px #ff660033;
                text-decoration: none;
                transition: background 0.2s;
            }
            .error-btn:hover {
                background: linear-gradient(90deg, #ffb347 0%, #ff6600 100%);
            }
        </style>
    </head>
    <body>
        <div class="error-card">
            <div class="error-icon">&#9888;</div>
            <div class="error-title">Të dhënat e pagesës mungojnë!</div>
            <div class="error-desc">Ju lutem kthehuni dhe plotësoni të gjitha fushat e kërkuara për të vazhduar me pagesën online.<br><br>Nëse problemi vazhdon, kontaktoni mbështetjen.</div>
            <a href="reservation.php" class="error-btn">Kthehu te Rezervimi</a>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Këtu do të bëhet thirrja reale në API-në e Tinky
$tinkyUrl = 'https://tinky.com/pay?demo=1&amount=' . urlencode($amount) . '&ref=' . urlencode($reservationId);

// Ruaj statusin "pending" në DB (opsionale)
$stmt = $pdo->prepare("UPDATE reservations SET payment_status = 'pending', payment_method = 'tinky' WHERE id = ? AND user_id = ?");
$stmt->execute([$reservationId, $userId]);

// UI profesionale para redirect
?><!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="refresh" content="3;url=<?php echo htmlspecialchars($tinkyUrl); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paguaj me Tinky Diaspora</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: linear-gradient(135deg, #f8fafc 0%, #e8f0ff 100%);
            font-family: 'Montserrat', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .tinky-card {
            background: #fff;
            max-width: 480px;
            width: 100%;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(45, 108, 223, 0.15), 0 1px 3px rgba(0,0,0,0.1);
            padding: 44px 36px;
            text-align: center;
            position: relative;
            animation: slideUp 0.5s ease-out;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .tinky-logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 24px auto;
            background: linear-gradient(135deg, #ff6600 0%, #ffb347 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3em;
            box-shadow: 0 8px 24px rgba(255, 102, 0, 0.2);
        }
        .tinky-title {
            font-size: 1.8em;
            font-weight: 700;
            color: #2d2d6a;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }
        .tinky-desc {
            color: #555;
            font-size: 1.05em;
            margin-bottom: 28px;
            line-height: 1.5;
        }
        .tinky-summary {
            background: linear-gradient(135deg, #f3f6ff 0%, #eef5ff 100%);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            font-size: 1em;
            color: #2d2d6a;
            text-align: left;
            border-left: 5px solid #ff6600;
            box-shadow: 0 4px 12px rgba(45, 108, 223, 0.08);
        }
        .tinky-summary div { margin: 10px 0; display: flex; justify-content: space-between; align-items: center; }
        .tinky-summary strong { color: #ff6600; font-weight: 700; }
        .tinky-value { color: #333; font-weight: 600; }
        .tinky-spinner {
            margin: 28px auto 0 auto;
            width: 56px;
            height: 56px;
            border: 5px solid #e0e7ff;
            border-top: 5px solid #ff6600;
            border-right: 5px solid #ffb347;
            border-radius: 50%;
            animation: spin 1.2s linear infinite;
            box-shadow: 0 0 20px rgba(255, 102, 0, 0.2);
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .tinky-redirect {
            margin-top: 24px;
            color: #666;
            font-size: 1.05em;
            line-height: 1.6;
        }
        .tinky-btn {
            display: inline-block;
            margin-top: 16px;
            background: linear-gradient(135deg, #ff6600 0%, #ffb347 100%);
            color: #fff;
            font-weight: 700;
            border: none;
            border-radius: 10px;
            padding: 14px 40px;
            font-size: 1.1em;
            cursor: pointer;
            box-shadow: 0 8px 20px rgba(255, 102, 0, 0.25);
            text-decoration: none;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .tinky-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(255, 102, 0, 0.35);
        }
        .tinky-btn:active {
            transform: translateY(-1px);
        }
        .security-badge {
            margin-top: 28px;
            padding: 12px;
            background: #f0f8ff;
            border-radius: 8px;
            font-size: 0.95em;
            color: #0c5460;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
    </style>
</head>
<body>
    <div class="tinky-card">
        <div class="tinky-logo">💳</div>
        <div class="tinky-title">Paguaj me Tinky Diaspora</div>
        <div class="tinky-desc">Kontrolloni të dhënat e pagesës tuaj. Pas ndryshjeve, do të ridrejtohemi automatikisht te platforma Tinky për përfundimin e sigurt të transaksionit.</div>
        <div class="tinky-summary">
            <div><strong>👤 Emri:</strong> <span class="tinky-value"><?php echo htmlspecialchars($payer_name); ?></span></div>
            <div><strong>🏦 IBAN:</strong> <span class="tinky-value">***<?php echo substr(htmlspecialchars($payer_iban), -4); ?></span></div>
            <div><strong>💵 Shuma:</strong> <span class="tinky-value" style="color:#ff6600; font-size:1.15em;">€<?php echo htmlspecialchars(number_format((float)$amount,2,'.',',')); ?></span></div>
            <div><strong>📝 Përshkrimi:</strong> <span class="tinky-value"><?php echo htmlspecialchars($description); ?></span></div>
            <div><strong>📌 Ref. Rezervimi:</strong> <span class="tinky-value">#<?php echo htmlspecialchars($reservationId); ?></span></div>
        </div>
        <div class="tinky-spinner"></div>
        <div class="tinky-redirect">
            <strong>🔄 Duke u përpunuar...</strong><br>
            <span style="font-size:0.95em; color:#777; margin-top:8px; display:block;">Do të ridrejtoheni te Tinky në 3 sekonda</span>
        </div>
        <div style="margin-top:24px;">
            <a href="<?php echo htmlspecialchars($tinkyUrl); ?>" class="tinky-btn">⚡ Vazhdo te Pagesa Tani</a>
        </div>
        <div class="security-badge">
            🔒 Pagesa e Sigurt - Enkriptuar & Sertifikuar
        </div>
    </div>
</body>
</html>
<?php
// Redirect pas 3 sekondash (fallback në meta refresh)
header('Refresh: 3;url=' . $tinkyUrl);
exit();
