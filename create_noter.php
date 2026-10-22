<?php
/**
 * Create Noter (Notary) User
 * Krijo përdoruesin me rolin NOTER
 */

require_once __DIR__ . '/config.php';

$email = 'endrit.hasani@gmail.com';
$password = 'Noteria@2024';
$hashedPassword = password_hash($password, PASSWORD_ARGON2ID);

echo "🚀 Krijimi i përdoruesit NOTER...\n\n";

try {
    // Delete existing if any
    $pdo->prepare("DELETE FROM users WHERE email = ?")->execute([$email]);
    
    // Insert noter user
    $sql = "INSERT INTO users (emri, mbiemri, email, password, roli, created_at, is_active) 
            VALUES (?, ?, ?, ?, ?, NOW(), 1)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'Endrit',
        'Hasani',
        $email,
        $hashedPassword,
        'noter'
    ]);
    
    echo "✅ SUKSES! Përdoruesi NOTER u krijua:\n";
    echo "════════════════════════════════════════════\n";
    echo "📧 Email: $email\n";
    echo "🔐 Fjalëkalim: $password\n";
    echo "👤 Emri: Endrit Hasani\n";
    echo "🎯 Roli: NOTER (Noter)\n";
    echo "════════════════════════════════════════════\n";
    echo "\n💡 Mund të hyni në: http://localhost/login.php\n";
    echo "   Zgjedhni rolin: Noter\n";
    echo "\n⚠️  Ndrysho fjalëkalimin pasi të hysh në sistem!\n";

} catch (Exception $e) {
    echo "❌ GABIM: " . $e->getMessage() . "\n";
    exit(1);
}
