<?php
require 'confidb.php';

// Kontrolloje nëse kolona roli ekziston
try {
    $stmt = $pdo->query('SELECT id, emri, email, roli FROM users LIMIT 5');
    $results = $stmt->fetchAll();
    echo "<pre>";
    print_r($results);
    echo "</pre>";
} catch (PDOException $e) {
    echo "Gabim: " . $e->getMessage();
    
    // Nëse kolona nuk ekziston, krijoha
    echo "<br><br>Shtoj kolonën roli...";
    $pdo->exec("ALTER TABLE users ADD COLUMN roli VARCHAR(20) NOT NULL DEFAULT 'user'");
    echo "✅ Kolona u shtua!";
}

// Përditeso të gjithë përdoruesit me rolin 'user' si default
$pdo->exec("UPDATE users SET roli = 'user' WHERE roli IS NULL OR roli = ''");
echo "<br>✅ Përditesim i role-ve përfunduar!";
?>
