<?php
// Konfigurimi i raportimit të gabimeve
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

session_start();
require_once 'config.php';

// Kontrollo nëse përdoruesi është i kyçur
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Kontrollo nëse është dërguar forma me POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST)) {
    echo "<h1>Konfirmimi i Pagesës</h1>";
    echo "<p style='color:#d32f2f;'>Nuk ka informacion mbi pagesën. Ju lutemi përdorni butonin 'Paguaj Online' nga rezervimi.</p>";
    exit();
}

// Mbrojtje CSRF (nëse e përdor në formë)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Merr të dhënat nga forma
$user_id     = $_SESSION['user_id'];
// Merr emrin dhe mbiemrin e përdoruesit
$stmt = $pdo->prepare('SELECT emri, mbiemri FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$emri = $user['emri'] ?? '';
$mbiemri = $user['mbiemri'] ?? '';
$amount      = $_POST['shuma'] ?? 0;
$tvsh_rate   = 0.12;
$prov_rate   = 0.02;
$tvsh        = round($amount * $tvsh_rate, 2);
$provizioni  = round($amount * $prov_rate, 2);
$pa_tvsh     = round($amount - $tvsh, 2);
$currency    = 'EUR';
$service     = $_POST['emri_bankes'] ?? '';
$description = $_POST['pershkrimi'] ?? '';
$iban        = $_POST['llogaria'] ?? '';

// Përgatit të dhënat për MCP server
$paymentData = [
    'user_id'     => $user_id,
    'amount'      => $amount,
    'currency'    => $currency,
    'service'     => $service,
    'description' => $description . ' | IBAN: ' . $iban,
    'return_url'  => 'https://noteria.com/dashboard.php?payment=success',
    'cancel_url'  => 'https://noteria.com/dashboard.php?payment=cancel'
];

// Parametrat e MCP
$apiUrl = 'https://mcp-server.example.com/api/payments'; // Ndrysho sipas dokumentacionit të MCP
$apiKey = 'KETU_SHENO_API_KEY';

// Simulo përgjigjen si pagesë e suksesshme (nuk dërgon kërkesë reale)
$success = true;

// Nëse do të përdorësh MCP real, zëvendëso këtë pjesë me cURL dhe kontrollo përgjigjen si më parë

?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagesa e Kryer me Sukses | Noteria</title>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700&display=swap" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(120deg, #e2eafc 0%, #f8fafc 100%);
            font-family: 'Montserrat', Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        .success-container {
            max-width: 480px;
            margin: 70px auto;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(44,108,223,0.10);
            padding: 44px 32px 38px 32px;
            text-align: center;
        }
        .success-icon {
            font-size: 3.5rem;
            color: #388e3c;
            margin-bottom: 18px;
            display: inline-block;
        }
        h1 {
            color: #388e3c;
            font-size: 2.1rem;
            margin-bottom: 14px;
            font-weight: 700;
        }
        .desc {
            color: #184fa3;
            font-size: 1.13rem;
            margin-bottom: 22px;
        }
        .details {
            background: #eafaf1;
            border-radius: 10px;
            padding: 18px 12px;
            color: #184fa3;
            margin-bottom: 18px;
            font-size: 1.05rem;
            text-align: left;
            display: inline-block;
            min-width: 220px;
        }
        .btn {
            background: #2d6cdf;
            color: #fff;
            border-radius: 8px;
            padding: 12px 28px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1.08rem;
            transition: background 0.18s;
            display: inline-block;
            margin-top: 10px;
        }
        .btn:hover {
            background: #184fa3;
        }
        @media (max-width: 600px) {
            .success-container { padding: 12px; }
            .details { font-size: 0.98rem; }
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-icon">&#10004;</div>
        <h1>Pagesa u Krye me Sukses!</h1>
        <div class="desc">
            Faleminderit për besimin tuaj.<br>
            Pagesa juaj është procesuar me sukses përmes sistemit të sigurt MCP Server.<br>
            Një konfirmim do të dërgohet edhe në numrin tuaj të telefonit.
        </div>
        <div class="details">
            <b>Emri dhe mbiemri:</b> <?php echo htmlspecialchars($emri . ' ' . $mbiemri); ?><br>
            <b>Shërbimi noterial:</b> <?php echo htmlspecialchars($service); ?><br>
            <b>Shuma totale:</b> <?php echo htmlspecialchars(number_format($amount, 2)); ?> EUR<br>
            <b>Shuma pa TVSH:</b> <?php echo htmlspecialchars(number_format($pa_tvsh, 2)); ?> EUR<br>
            <b>TVSH (12%):</b> <?php echo htmlspecialchars(number_format($tvsh, 2)); ?> EUR<br>
            <b>Provizion platforme (2%):</b> <?php echo htmlspecialchars(number_format($provizioni, 2)); ?> EUR<br>
            <b>Përshkrimi:</b> <?php echo htmlspecialchars($description); ?><br>
            <?php if ($iban): ?><b>IBAN:</b> <?php echo htmlspecialchars($iban); ?><br><?php endif; ?>
            <b>Data:</b> <?php echo date('d.m.Y H:i'); ?>
        </div>
        <a href="dashboard.php" class="btn">Kthehu në Panelin e Përdoruesit</a>
    </div>
</body>
</html>