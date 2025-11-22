<?php
// Shtimi i kolonave për verifikimin e telefonit në payment_logs
// filepath: d:\xampp\htdocs\noteria\add_phone_columns_to_payment_logs.php

require_once 'config.php';

try {
    echo "<h2>📱 Shtimi i kolonave për verifikimin e telefonit</h2>\n";
    
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Kontrollo nëse kolonat ekzistojnë tashmë
    $stmt = $pdo->query("SHOW COLUMNS FROM payment_logs LIKE 'phone_verified'");
    if ($stmt->rowCount() == 0) {
        echo "<p>🔧 Shtoj kolonën phone_verified...</p>\n";
        $pdo->exec("ALTER TABLE payment_logs ADD COLUMN phone_verified BOOLEAN DEFAULT FALSE AFTER verification_status");
    } else {
        echo "<p>✅ Kolona phone_verified ekziston tashmë</p>\n";
    }
    
    $stmt = $pdo->query("SHOW COLUMNS FROM payment_logs LIKE 'phone_verified_at'");
    if ($stmt->rowCount() == 0) {
        echo "<p>🔧 Shtoj kolonën phone_verified_at...</p>\n";
        $pdo->exec("ALTER TABLE payment_logs ADD COLUMN phone_verified_at TIMESTAMP NULL AFTER phone_verified");
    } else {
        echo "<p>✅ Kolona phone_verified_at ekziston tashmë</p>\n";
    }
    
    $stmt = $pdo->query("SHOW COLUMNS FROM payment_logs LIKE 'phone_number'");
    if ($stmt->rowCount() == 0) {
        echo "<p>🔧 Shtoj kolonën phone_number...</p>\n";
        $pdo->exec("ALTER TABLE payment_logs ADD COLUMN phone_number VARCHAR(20) AFTER office_email");
    } else {
        echo "<p>✅ Kolona phone_number ekziston tashmë</p>\n";
    }
    
    // Krijo një index për performancë të mirë
    try {
        $pdo->exec("CREATE INDEX idx_phone_verified ON payment_logs(phone_verified, phone_verified_at)");
        echo "<p>✅ Index u krijua për performancë të mirë</p>\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "<p>✅ Index ekziston tashmë</p>\n";
        } else {
            echo "<p>⚠️ Warning: " . $e->getMessage() . "</p>\n";
        }
    }
    
    // Testo strukturën e re
    echo "<h3>📋 Struktura e përditësuar e payment_logs:</h3>\n";
    $stmt = $pdo->query("DESCRIBE payment_logs");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr><th>Kolona</th><th>Tipi</th><th>Default</th><th>Shtesë</th></tr>\n";
    foreach ($columns as $col) {
        $highlight = in_array($col['Field'], ['phone_verified', 'phone_verified_at', 'phone_number']) ? 
                    'style="background: #e8f5e8;"' : '';
        echo "<tr $highlight>";
        echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Default']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Extra']) . "</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    echo "<hr>\n";
    echo "<h3>🎉 Sistemi i verifikimit të telefonit është gati!</h3>\n";
    echo "<p><strong>Veçoritë e reja në payment_logs:</strong></p>\n";
    echo "<ul>\n";
    echo "<li>📱 <strong>phone_number:</strong> Ruhet numri i telefonit</li>\n";
    echo "<li>✅ <strong>phone_verified:</strong> Status i verifikimit përmes SMS</li>\n";
    echo "<li>⏰ <strong>phone_verified_at:</strong> Koha e verifikimit</li>\n";
    echo "</ul>\n";
    
    echo "<p><strong>📊 Integrimi me sistemin 3-minutësh:</strong></p>\n";
    echo "<ul>\n";
    echo "<li>🚀 Verifikim simultant i pagesës dhe telefonit</li>\n";
    echo "<li>📱 SMS automatik pas regjistrimit</li>\n";
    echo "<li>⚡ Konfirmim brenda 3 minutave</li>\n";
    echo "<li>📧 Email konfirmimi pas verifikimit të plotë</li>\n";
    echo "</ul>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Gabim: " . $e->getMessage() . "</p>\n";
}
?>