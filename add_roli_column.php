<?php
// Add roli column to users table if it doesn't exist
require_once 'confidb.php';

try {
    // Kontrollo nëse kolona ekziston
    $stmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'roli'");
    $stmt->execute();
    $column = $stmt->fetch();
    
    if (!$column) {
        // Shto kolonën roli
        $pdo->exec("ALTER TABLE users ADD COLUMN roli VARCHAR(50) DEFAULT 'user' AFTER email");
        echo "✅ Kolona 'roli' u shtua me sukses në tabelën users<br>";
        
        // Shfaq të gjithë përdoruesit
        $stmt = $pdo->query("SELECT id, emri, mbiemri, email, roli FROM users");
        $users = $stmt->fetchAll();
        
        echo "<h3>Përdoruesit aktualë:</h3>";
        echo "<table border='1' cellpadding='10'>";
        echo "<tr><th>ID</th><th>Emri</th><th>Mbiemri</th><th>Email</th><th>Roli</th></tr>";
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>{$user['id']}</td>";
            echo "<td>{$user['emri']}</td>";
            echo "<td>{$user['mbiemri']}</td>";
            echo "<td>{$user['email']}</td>";
            echo "<td>{$user['roli']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
    } else {
        echo "ℹ️ Kolona 'roli' ekziston tashmë<br>";
        
        // Shfaq të gjithë përdoruesit me rolet e tyre
        $stmt = $pdo->query("SELECT id, emri, mbiemri, email, roli FROM users");
        $users = $stmt->fetchAll();
        
        echo "<h3>Përdoruesit aktualë me rolet:</h3>";
        echo "<table border='1' cellpadding='10'>";
        echo "<tr><th>ID</th><th>Emri</th><th>Mbiemri</th><th>Email</th><th>Roli</th></tr>";
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>{$user['id']}</td>";
            echo "<td>{$user['emri']}</td>";
            echo "<td>{$user['mbiemri']}</td>";
            echo "<td>{$user['email']}</td>";
            echo "<td><strong>{$user['roli']}</strong></td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "❌ Gabim: " . $e->getMessage();
}
?>
