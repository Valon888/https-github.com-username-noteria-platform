<?php
require_once __DIR__ . '/config.php';

// Clear output buffering
ob_clean();

$email = 'endrit.hasani@gmail.com';

// Check if user exists
$stmt = $pdo->prepare("SELECT id, emri, mbiemri, email, roli FROM users WHERE LOWER(email) = LOWER(?)");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    die("USER_EXISTS:" . json_encode($user));
} else {
    die("USER_NOT_FOUND");
}
