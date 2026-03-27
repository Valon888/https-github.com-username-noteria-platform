<?php
/**
 * Check and Create User
 * Verifiko nëse ekziston përdoruesi dhe krijo nëse nevojitet
 */

require_once __DIR__ . '/config.php';

$email = 'endrit.hasani@gmail.com';

echo "🔍 Kontrollimi i përdoruesit: $email\n\n";

try {
    // Check if user exists
    $sql = "SELECT id, emri, mbiemri, email, roli FROM users WHERE email = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "✅ Përdoruesi EKZISTON:\n";
        echo "   ID: {$user['id']}\n";
        echo "   Emri: {$user['emri']} {$user['mbiemri']}\n";
        echo "   Email: {$user['email']}\n";
        echo "   Roli: {$user['roli']}\n";
        exit;
    }
    
    echo "❌ Përdoruesi NUK EKZISTON\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    echo "🔧 Zgjedhje për zgjidhjen:\n";
    echo "1. Krijo përdoruesin automatikisht\n";
    echo "2. Shfaq të gjithë përdoruesit në sistem\n";
    echo "3. Kontrollo tabelën 'users'\n\n";
    
    // Auto-create user if requested via CLI argument
    if (isset($argv[1]) && $argv[1] === 'create') {
        echo "🚀 Krijohet përdoruesi...\n\n";
        
        // Default password
        $password = 'Noteria@2024'; // Ndryshoje këtë!
        $hashedPassword = password_hash($password, PASSWORD_ARGON2ID);
        
        $sql = "INSERT INTO users (emri, mbiemri, email, password, roli, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'Endrit',
            'Hasani',
            $email,
            $hashedPassword,
            'noter' // notary role
        ]);
        
        $userId = $pdo->lastInsertId();
        
        echo "✅ Përdoruesi u krijua me sukses!\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "📋 Detalet e Hyrjes:\n";
        echo "   Email: $email\n";
        echo "   Fjalëkalim: $password\n";
        echo "   Roli: Noter (Notary)\n";
        echo "   ID: $userId\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "\n⚠️  RËNDËSI: Ndrysho fjalëkalimin pas hyrjes së parë!\n";
        
    } else {
        // Show all users
        echo "👥 Të Gjithë Përdoruesit në Sistem:\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        
        $sql = "SELECT id, emri, mbiemri, email, roli, created_at FROM users ORDER BY created_at DESC";
        $stmt = $pdo->query($sql);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($users)) {
            echo "⚠️  Nuk ka përdorues në sistem!\n";
        } else {
            foreach ($users as $u) {
                echo "\n#{$u['id']} - {$u['emri']} {$u['mbiemri']}\n";
                echo "   Email: {$u['email']}\n";
                echo "   Roli: {$u['roli']}\n";
                echo "   Krijuar: {$u['created_at']}\n";
            }
        }
        
        echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "\n🚀 Për të krijuar përdoruesin, ekzekuto:\n";
        echo "   php check_user.php create\n";
    }

} catch (Exception $e) {
    echo "❌ GABIM: " . $e->getMessage() . "\n";
    exit(1);
}
