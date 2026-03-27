<?php
require_once __DIR__ . '/config.php';

$email = 'endrit.hasani@gmail.com';
$password = password_hash('Noteria@2024', PASSWORD_ARGON2ID);

$sql = "INSERT INTO users (emri, mbiemri, email, password, roli, created_at) 
        VALUES ('Endrit', 'Hasani', ?, ?, 'noter', NOW())
        ON DUPLICATE KEY UPDATE password = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$email, $password, $password]);

echo "✅ Përdoruesi u krijua/përditësua!\n";
echo "Email: $email\n";
echo "Fjalëkalim: Noteria@2024\n";
echo "Roli: Noter\n";
