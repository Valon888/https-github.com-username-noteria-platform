<?php
/**
 * Verify User Creation
 * Kontrolloje nëse përdoruesi u krijua saktë
 */

require_once __DIR__ . '/config.php';

$email = 'endrit.hasani@gmail.com';

echo "🔍 Kontrollimi i përdoruesit...\n\n";

// Check if exists
$sql = "SELECT id, emri, mbiemri, email, roli, is_active FROM users WHERE LOWER(email) = LOWER(?)";
$stmt = $pdo->prepare($sql);
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    echo "✅ PËRDORUESI EKZISTON:\n";
    echo "   ID: {$user['id']}\n";
    echo "   Emri: {$user['emri']} {$user['mbiemri']}\n";
    echo "   Email: {$user['email']}\n";
    echo "   Rolo: {$user['roli']}\n";
    echo "   Aktiv: " . ($user['is_active'] ? 'PO' : 'JO') . "\n";
} else {
    echo "❌ PËRDORUESI NUK EKZISTON\n";
    echo "\n🔧 Krijohet automatikisht...\n";
    
    $password = password_hash('Noteria@2024', PASSWORD_ARGON2ID);
    
    $sql = "INSERT INTO users (emri, mbiemri, email, password, roli, is_active) 
            VALUES (?, ?, ?, ?, ?, 1)";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        'Endrit',
        'Hasani',
        $email,
        $password,
        'noter'
    ]);
    
    if ($result) {
        $id = $pdo->lastInsertId();
        echo "✅ Përdoruesi u krijua:\n";
        echo "   ID: $id\n";
        echo "   Email: $email\n";
        echo "   Rolo: noter\n";
    } else {
        echo "❌ Gabim gjatë krijimit\n";
    }
}

// Show all users
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "👥 TË GJITHË PËRDORUESIT:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$sql = "SELECT id, emri, mbiemri, email, roli FROM users ORDER BY id";
$stmt = $pdo->query($sql);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($users as $u) {
    echo "\n#{$u['id']} - {$u['emri']} {$u['mbiemri']}\n";
    echo "     Email: {$u['email']}\n";
    echo "     Rolo: {$u['roli']}\n";
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\n📝 Për hyrje:\n";
echo "   Email: endrit.hasani@gmail.com\n";
echo "   Fjalëkalim: Noteria@2024\n";
echo "   Rolo: Noter\n";
