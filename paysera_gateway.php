<?php
session_start();
require_once 'confidb.php';
// Merr të dhënat nga POST ose SESSION
$service = $_POST['service'] ?? ($_SESSION['pay_service'] ?? null);
$date = $_POST['date'] ?? ($_SESSION['pay_date'] ?? null);
$time = $_POST['time'] ?? ($_SESSION['pay_time'] ?? null);
$zyra_id = $_POST['zyra_id'] ?? ($_SESSION['pay_zyra_id'] ?? null);

// Ruaj në session për rifreskim
if ($service && $date && $time && $zyra_id) {
    $_SESSION['pay_service'] = $service;
    $_SESSION['pay_date'] = $date;
    $_SESSION['pay_time'] = $time;
    $_SESSION['pay_zyra_id'] = $zyra_id;
}

// Merr të dhënat e paguesit (user)
$payer_name = $payer_email = $payer_phone = '';
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare('SELECT emri, mbiemri, email, telefoni FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    if ($u = $stmt->fetch()) {
        $payer_name = $u['emri'] . ' ' . $u['mbiemri'];
        $payer_email = $u['email'];
        $payer_phone = $u['telefoni'] ?? '';
    }
}

// Merr të dhënat e zyrës noteriale
$zyra_emri = $zyra_adresa = $zyra_noteri = '';
if ($zyra_id) {
    $stmt = $pdo->prepare('SELECT z.emri, z.adresa, u.emri AS noteri_emri, u.mbiemri AS noteri_mbiemri FROM zyrat z LEFT JOIN users u ON u.zyra_id = z.id AND u.roli = "zyra" WHERE z.id = ? LIMIT 1');
    $stmt->execute([$zyra_id]);
    if ($z = $stmt->fetch()) {
        $zyra_emri = $z['emri'];
        $zyra_adresa = $z['adresa'] ?? '';
        $zyra_noteri = trim(($z['noteri_emri'] ?? '') . ' ' . ($z['noteri_mbiemri'] ?? ''));
    }
}

// Simulo pagesën me Paysera (në praktikë këtu do të integrohej API e Paysera)
$success = true; // Simulo suksesin
$transaction_id = strtoupper(uniqid('PAYSR'));
$service_prices = [
    // Çmimet sipas Udhëzimit Administrativ dhe Ligjit për Noterinë në Kosovë (shembuj, përshtat sipas listës zyrtare)
    'Legalizimi i dokumentit' => 10.00,
    'Përpilimi i testamentit' => 30.00,
    'Përpilimi i kontratës së shitjes' => 50.00,
    'Përpilimi i kontratës së dhurimit' => 40.00,
    'Përpilimi i deklaratës' => 15.00,
    'Legalizimi i nënshkrimit' => 5.00,
    'Përkthim i dokumentit' => 8.00,
    // Shto shërbime të tjera sipas listës zyrtare
];

// Vendos çmimin sipas shërbimit të zgjedhur
$amount = isset($service_prices[$service]) ? $service_prices[$service] : 20.00; // 20.00 default nëse nuk gjendet
$currency = 'EUR';

if (!$service || !$date || !$time || !$zyra_id) {
    echo '<h2 style="color:red;text-align:center;margin-top:40px;">Të dhënat mungojnë!</h2>';
    echo '<div style="text-align:center;margin-top:16px;"><a href="dashboard.php">Kthehu në panel</a></div>';
    exit();
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <title>Rezultati i Pagesës | Noteria</title>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700&display=swap" rel="stylesheet">
    <style>
        body { background: #f8fafc; font-family: 'Montserrat', Arial, sans-serif; }
        .pay-container { max-width: 480px; margin: 60px auto; background: #fff; border-radius: 16px; box-shadow: 0 8px 24px rgba(44,108,223,0.10); padding: 36px 28px; text-align: center; }
        h2 { color: #2d6cdf; margin-bottom: 18px; font-size: 1.6rem; font-weight: 800; }
        .summary { text-align:left; margin-bottom: 24px; }
        .summary label { color: #184fa3; font-weight: 600; display:block; margin-bottom:2px; }
        .summary div { margin-bottom: 10px; }
        .success { color: #388e3c; background: #eafaf1; border-radius: 10px; padding: 14px; margin-bottom: 22px; font-size: 1.08rem; text-align: center; border-left: 5px solid #388e3c; }
        .fail { color: #d32f2f; background: #ffeaea; border-radius: 10px; padding: 14px; margin-bottom: 22px; font-size: 1.08rem; text-align: center; border-left: 5px solid #d32f2f; }
        .txid { font-size:0.98em; color:#888; margin-top:8px; }
        .back-link { margin-top:18px; display:block; }
        .back-link a { color:#2d6cdf; text-decoration:none; font-weight:600; }
        .back-link a:hover { text-decoration:underline; }
        .section-title { color:#184fa3; font-size:1.08em; font-weight:700; margin:18px 0 8px 0; }
        .info-block { background:#f8fafc; border-radius:8px; padding:10px 14px; margin-bottom:12px; }
    </style>
</head>
<body>
    <div class="pay-container">
        <?php if ($success): ?>
            <div class="success"><i class="fas fa-check-circle"></i> Pagesa u krye me sukses!</div>
            <h2>Detajet e Pagesës</h2>
            <div class="summary">
                <div><label>Zyra:</label> <?= htmlspecialchars($zyra_emri) ?></div>
                <div><label>Noteri/ja:</label> <?= htmlspecialchars($zyra_noteri) ?></div>
                <div><label>Lokacioni:</label> <?= htmlspecialchars($zyra_adresa) ?></div>
                <div><label>Shërbimi:</label> <?= htmlspecialchars($service) ?></div>
                <div><label>Data:</label> <?= htmlspecialchars($date) ?></div>
                <div><label>Ora:</label> <?= htmlspecialchars($time) ?></div>
                <div><label>Shuma:</label> <?= number_format($amount,2) . ' ' . $currency ?>
                    <?php if (!isset($service_prices[$service])): ?>
                        <span style="color:#d32f2f;font-size:0.95em;">(Çmimi i përafërt, shërbimi nuk u gjet në listën zyrtare)</span>
                    <?php endif; ?>
                </div>
                <div class="txid">ID e transaksionit: <b><?= $transaction_id ?></b></div>
            </div>
            <div class="section-title">Të dhënat e Paguesit</div>
            <div class="info-block">
                <div><b>Emri:</b> <?= htmlspecialchars($payer_name) ?></div>
                <div><b>Email:</b> <?= htmlspecialchars($payer_email) ?></div>
                <div><b>Telefoni:</b> <?= htmlspecialchars($payer_phone) ?></div>
            </div>
        <?php else: ?>
            <div class="fail"><i class="fas fa-times-circle"></i> Pagesa dështoi. Ju lutemi provoni përsëri.</div>
        <?php endif; ?>
        <div class="back-link"><a href="dashboard.php">Kthehu në panel</a></div>
    </div>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</body>
</html>
