<?php
require 'confidb.php';

echo "<h2>🔍 Kontrollo Përdoruesit në Database</h2>";

try {
    // Shfaq të gjithë përdoruesit
    $stmt = $pdo->query("SELECT id, emri, mbiemri, email, roli, password FROM users");
    $users = $stmt->fetchAll();
    
    echo "<table border='1' cellpadding='10' cellspacing='0' style='border-collapse: collapse;'>";
    echo "<tr style='background: #667eea; color: white;'>";
    echo "<th>ID</th><th>Emri</th><th>Mbiemri</th><th>Email</th><th>Roli</th><th>Password Hash (first 20 chars)</th>";
    echo "</tr>";
    
    foreach ($users as $u) {
        $pw_preview = substr($u['password'], 0, 20) . "...";
        echo "<tr>";
        echo "<td>{$u['id']}</td>";
        echo "<td>{$u['emri']}</td>";
        echo "<td>{$u['mbiemri']}</td>";
        echo "<td>{$u['email']}</td>";
        echo "<td><strong>{$u['roli']}</strong></td>";
        echo "<td><code>$pw_preview</code></td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<hr>";
    echo "<h3>🔐 Test Password Verification</h3>";
    
    // Testo admin@noteria.al
    $stmt = $pdo->prepare("SELECT password FROM users WHERE email = ?");
    $stmt->execute(['admin@noteria.al']);
    $admin = $stmt->fetch();
    
    if ($admin) {
        $test_password = 'Admin@2025';
        $is_correct = password_verify($test_password, $admin['password']);
        
        echo "📧 Email: <strong>admin@noteria.al</strong><br>";
        echo "🔑 Test Password: <strong>$test_password</strong><br>";
        echo "✓ Password Match: <strong>" . ($is_correct ? "✅ YES" : "❌ NO") . "</strong><br>";
        
        if (!$is_correct) {
            echo "<br><strong style='color: red;'>⚠️ Fjalëkalimi nuk përputhet!</strong>";
        }
    } else {
        echo "❌ admin@noteria.al nuk gjendet në database!";
    }
    
} catch (Exception $e) {
    echo "❌ Gabim: " . $e->getMessage();
}
?>
