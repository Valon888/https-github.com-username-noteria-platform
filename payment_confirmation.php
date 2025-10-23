<?php
session_start();
require_once('config.php');

// Prano vetëm POST për pagesë të re
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
    $paymentMethod = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : '';

    // Validim bazik
    if ($amount > 0 && !empty($paymentMethod)) {
        // Këtu mund të integrohet me API të pagesave reale
        $paymentStatus = 'Sukses';
    } else {
        $paymentStatus = 'Gabim';
    }

    // Ruaj të dhënat në sesion
    $_SESSION['payment'] = [
        'amount' => number_format($amount, 2, '.', ''),
        'method' => htmlspecialchars($paymentMethod),
        'status' => $paymentStatus
    ];

    // Redirect për të shmangur ri-dërgimin e formës
    header('Location: payment_confirmation.php');
    exit();
}

// Merr të dhënat nga sesioni
$payment = $_SESSION['payment'] ?? null;
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmimi i Pagesës</title>
    <style>
        body { background: #f8fafc; font-family: 'Segoe UI', Arial, sans-serif; margin: 0; padding: 0; }
        .container { max-width: 420px; margin: 60px auto; background: #fff; border-radius: 16px; box-shadow: 0 8px 24px rgba(0,0,0,0.08); padding: 36px 28px; text-align: center; }
        h1 { color: #2d6cdf; margin-bottom: 24px; }
        .success { color: #388e3c; background: #eafaf1; border-radius: 8px; padding: 12px; margin-bottom: 18px; font-size: 1.08rem; }
        .error { color: #d32f2f; background: #ffeaea; border-radius: 8px; padding: 12px; margin-bottom: 18px; font-size: 1.08rem; }
        .details { margin-top: 18px; font-size: 1.05rem; }
        .details span { display: block; margin-bottom: 6px; }
        a { color: #2d6cdf; text-decoration: none; font-weight: 600; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Konfirmimi i Pagesës</h1>
        <?php if ($payment): ?>
            <?php if ($payment['status'] === 'Sukses'): ?>
                <div class="success">Pagesa u krye me sukses!</div>
                <div class="details">
                    <span><b>Shuma:</b> <?php echo $payment['amount']; ?> €</span>
                    <span><b>Metoda:</b> <?php echo $payment['method']; ?></span>
                </div>
            <?php else: ?>
                <div class="success">Pagesa juaj është kryer me sukses.</div>
            <a href="reservation.php">Kthehu te rezervimet</a>
            <?php endif; ?>
            <a href="reservation.php">Kthehu te rezervimet</a>
            <?php unset($_SESSION['payment']); ?>
        <?php else: ?>
            <div class="success">Pagesa juaj është kryer me sukses.</div>
            <a href="reservation.php">Kthehu te rezervimet</a>
        <?php endif; ?>
    </div>
    <footer style="text-align:center; margin-top:40px; color:#888; font-size:1rem;">
    <a href="Privacy_policy.php" style="color:#2d6cdf; text-decoration:underline;">Politika e Privatësisë</a>
</footer>
</body>
</html>