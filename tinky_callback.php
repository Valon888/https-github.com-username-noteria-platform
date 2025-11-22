<?php
// tinky_callback.php - Përpunon përgjigjen nga Tinky pas pagesës
// Ky endpoint duhet të konfigurohet si webhook ose return/callback URL në Tinky

require_once 'config.php';

// Lexo parametrat nga Tinky (shembull: ?reservation_id=123&status=success)
$reservation_id = $_GET['reservation_id'] ?? null;
$status = $_GET['status'] ?? null;

if (!$reservation_id || !$status) {
    die('Parametra të paplota.');
}

// Përkthe statusin e Tinky në status të brendshëm
$status_map = [
    'success' => 'paid',
    'failed' => 'failed',
    'cancelled' => 'cancelled',
];
$internal_status = $status_map[$status] ?? 'unknown';


// Përditëso statusin në databazë
$stmt = $pdo->prepare("UPDATE reservations SET payment_status = ? WHERE id = ?");
$stmt->execute([$internal_status, $reservation_id]);

// Nëse pagesa është e suksesshme, dërgo njoftim me email përdoruesit
if ($internal_status === 'paid') {
    // Merr emailin e përdoruesit
    $stmtUser = $pdo->prepare("SELECT u.email, u.emri FROM users u JOIN reservations r ON r.user_id = u.id WHERE r.id = ?");
    $stmtUser->execute([$reservation_id]);
    $user = $stmtUser->fetch();
    if ($user && !empty($user['email'])) {
        $to = $user['email'];
        $name = $user['emri'];
        $subject = "Konfirmim Pagesë - Noteria";
        $body = "Përshëndetje $name,<br><br>Pagesa juaj për rezervimin #$reservation_id u krye me sukses përmes Tinky.<br>Rezervimi juaj është aktiv.<br><br>Ju faleminderit që përdorët Noteria!";
        if (file_exists('Phpmailer.php')) {
            require_once 'Phpmailer.php';
            if (function_exists('sendMail')) {
                sendMail($to, $subject, $body);
            } else {
                // Fallback në mail() nëse sendMail nuk ekziston
                $headers  = "MIME-Version: 1.0\r\n";
                $headers .= "Content-type: text/html; charset=UTF-8\r\n";
                $headers .= "From: Noteria <no-reply@noteria.local>\r\n";
                mail($to, $subject, $body, $headers);
            }
        }
    }
}

// Shfaq mesazh për përdoruesin
if ($internal_status === 'paid') {
    echo '<h2>Pagesa u krye me sukses! Rezervimi juaj është aktiv.</h2>';
} elseif ($internal_status === 'failed') {
    echo '<h2>Pagesa dështoi. Ju lutemi provoni përsëri.</h2>';
} elseif ($internal_status === 'cancelled') {
    echo '<h2>Pagesa u anulua.</h2>';
} else {
    echo '<h2>Status i panjohur nga Tinky.</h2>';
}
