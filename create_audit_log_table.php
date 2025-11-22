<?php
// Krijo tabelën audit_log nëse nuk ekziston
require_once 'confidb.php';

try {
    // Kontrollo nëse tabela ekziston
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'audit_log'");
    $stmt->execute();
    $table_exists = $stmt->fetch();
    
    if (!$table_exists) {
        echo "🔨 Duke krijuar tabelën audit_log...<br>";
        
        $sql = "CREATE TABLE audit_log (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT,
            action VARCHAR(255),
            details TEXT,
            ip_address VARCHAR(45),
            user_agent VARCHAR(500),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_action (action),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        echo "✅ Tabela audit_log u krijua me sukses!<br><br>";
    } else {
        echo "ℹ️ Tabela audit_log ekziston tashmë.<br><br>";
    }
    
    // Shfaq strukturën e tabelës
    echo "<h3>📋 Struktura e tabelës audit_log:</h3>";
    $stmt = $pdo->query("DESCRIBE audit_log");
    $columns = $stmt->fetchAll();
    
    echo "<table border='1' cellpadding='10' cellspacing='0' style='border-collapse: collapse;'>";
    echo "<tr style='background: #667eea; color: white;'>";
    echo "<th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th>";
    echo "</tr>";
    
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>{$col['Field']}</td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>{$col['Default']}</td>";
        echo "<td>{$col['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<hr><p><strong>✅ Tabela audit_log është gati!</strong> Tani mund të login pa probleme.</p>";
    
} catch (Exception $e) {
    echo "❌ Gabim: " . $e->getMessage();
}
?>
