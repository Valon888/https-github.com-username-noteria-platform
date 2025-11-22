<?php
// Shto 3 përdorues me role të ndryshme
require_once 'confidb.php';

$users_to_add = [
    [
        'emri' => 'Admin',
        'mbiemri' => 'Noteria',
        'email' => 'admin@noteria.al',
        'password' => 'Admin@2025', // Për admin
        'roli' => 'admin'
    ],
    [
        'emri' => 'Notere',
        'mbiemri' => 'Kosovë',
        'email' => 'notary@noteria.al',
        'password' => 'Notary@2025', // Për notary
        'roli' => 'notary'
    ],
    [
        'emri' => 'Përdorues',
        'mbiemri' => 'Standard',
        'email' => 'user@noteria.al',
        'password' => 'User@2025', // Për user
        'roli' => 'user'
    ]
];

echo "<h2>Shtimi i Përdoruesve të Rinj me Role</h2>";

foreach ($users_to_add as $new_user) {
    try {
        // Kontrollo nëse përdoruesi ekziston
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$new_user['email']]);
        $exists = $check->fetch();
        
        if ($exists) {
            echo "ℹ️ Përdoruesi <strong>{$new_user['email']}</strong> ekziston tashmë<br>";
        } else {
            // Hash password
            $hashed_password = password_hash($new_user['password'], PASSWORD_BCRYPT, ['cost' => 12]);
            
            // Shto përdoruesin
            $stmt = $pdo->prepare("INSERT INTO users (emri, mbiemri, email, password, roli) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $new_user['emri'],
                $new_user['mbiemri'],
                $new_user['email'],
                $hashed_password,
                $new_user['roli']
            ]);
            
            echo "✅ Përdoruesi <strong>{$new_user['email']}</strong> u shtua me rol <strong>{$new_user['roli']}</strong><br>";
        }
    } catch (Exception $e) {
        echo "❌ Gabim për {$new_user['email']}: " . $e->getMessage() . "<br>";
    }
}

echo "<hr>";
echo "<h3>📋 Lista e Përdoruesve me Rolet:</h3>";

$stmt = $pdo->query("SELECT id, emri, mbiemri, email, roli FROM users ORDER BY roli DESC");
$users = $stmt->fetchAll();

echo "<table border='1' cellpadding='12' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background: #667eea; color: white;'>";
echo "<th>ID</th><th>Emri</th><th>Mbiemri</th><th>Email</th><th>Roli</th><th>Kredenciale</th>";
echo "</tr>";

$credentials = [
    'admin@noteria.al' => 'Admin@2025',
    'notary@noteria.al' => 'Notary@2025',
    'user@noteria.al' => 'User@2025',
    'test@noteria.com' => '(ekziston përparësisht)'
];

foreach ($users as $user) {
    $role_color = match($user['roli']) {
        'admin' => '#ff4444',
        'notary' => '#4488ff',
        'user' => '#44aa44',
        default => '#888888'
    };
    
    $password = $credentials[$user['email']] ?? 'N/A';
    
    echo "<tr>";
    echo "<td>{$user['id']}</td>";
    echo "<td>{$user['emri']}</td>";
    echo "<td>{$user['mbiemri']}</td>";
    echo "<td>{$user['email']}</td>";
    echo "<td style='background: $role_color; color: white; font-weight: bold;'>{$user['roli']}</td>";
    echo "<td>$password</td>";
    echo "</tr>";
}

echo "</table>";

echo "<h3 style='margin-top: 30px;'>🎯 Përshkrime të Roleve:</h3>";
echo "<ul style='font-size: 16px; line-height: 1.8;'>";
echo "<li><strong style='color: #ff4444;'>ADMIN</strong> - Sheh të gjithë dashboard-in (admin_dashboard.php), të dhënat, statistikat, përdoruesit</li>";
echo "<li><strong style='color: #4488ff;'>NOTARY</strong> - Notere - Sheh dashboard-in normal (dashboard.php) me të dhënat e zyrës</li>";
echo "<li><strong style='color: #44aa44;'>USER</strong> - Përdorues standard - Sheh vetëm billing_dashboard.php për shërbimet dhe pagesa</li>";
echo "</ul>";

?>
