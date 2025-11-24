<?php
require 'confidb.php';

// Krijo përdorues test me role të ndryshme
$test_users = [
    ['emri' => 'Admin', 'mbiemri' => 'User', 'email' => 'admin@noteria.al', 'password' => 'Admin@2025', 'roli' => 'admin'],
    ['emri' => 'Notar', 'mbiemri' => 'User', 'email' => 'notary@noteria.al', 'password' => 'Notary@2025', 'roli' => 'notary'],
    ['emri' => 'User', 'mbiemri' => 'Normal', 'email' => 'user@noteria.al', 'password' => 'User@2025', 'roli' => 'user'],
];

try {
    $stmt = $pdo->prepare("INSERT INTO users (emri, mbiemri, email, password, roli) VALUES (?, ?, ?, ?, ?)");
    
    foreach ($test_users as $user) {
        $hashed_password = password_hash($user['password'], PASSWORD_BCRYPT);
        $stmt->execute([
            $user['emri'],
            $user['mbiemri'],
            $user['email'],
            $hashed_password,
            $user['roli']
        ]);
        echo "✅ Përdoruesi '{$user['email']}' u krijua me rolin '{$user['roli']}'<br>";
    }
    
    echo "<br><br><strong>Të dhënat e kyçjes:</strong><br>";
    foreach ($test_users as $user) {
        echo "📧 Email: <strong>{$user['email']}</strong> | 🔑 Password: <strong>{$user['password']}</strong> | 👤 Roli: <strong>{$user['roli']}</strong><br>";
    }
    
} catch (PDOException $e) {
    echo "❌ Gabim: " . $e->getMessage();
}
?>
