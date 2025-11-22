<?php
// Script për të kontrolluar strukturën e tabelës payment_logs

// Konfigurimi i databazës
$db_host = 'localhost';
$db_name = 'noteria';
$db_user = 'root';
$db_pass = '';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== STRUKTURA E TABELËS payment_logs ===\n";
    $stmt = $pdo->query('DESCRIBE payment_logs');
    while ($row = $stmt->fetch()) {
        echo $row['Field'] . ' - ' . $row['Type'] . "\n";
    }
    
    echo "\n=== KONTROLL PËR KOLONËN user_email ===\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM payment_logs LIKE 'user_email'");
    if ($stmt->rowCount() > 0) {
        echo "✓ Kolona 'user_email' ekziston\n";
    } else {
        echo "❌ Kolona 'user_email' NUK ekziston\n";
        
        // Kontrollo për office_email
        $stmt = $pdo->query("SHOW COLUMNS FROM payment_logs LIKE 'office_email'");
        if ($stmt->rowCount() > 0) {
            echo "✓ Kolona 'office_email' ekziston\n";
            echo "💡 ZGJIDHJA: Duhet të ndryshojmë kodin për të përdorur 'office_email'\n";
        }
    }
    
} catch (Exception $e) {
    echo "Gabim: " . $e->getMessage() . "\n";
}
?>