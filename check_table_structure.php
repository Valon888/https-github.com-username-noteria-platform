<?php
/**
 * Tabla Structure Check
 * Проверува точна структура на табелата advertisers
 */

require_once 'confidb.php';

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
    
    // Get table structure
    $columns = $pdo->query("DESC advertisers")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<pre style='background: #f4f4f4; padding: 20px; font-family: monospace;'>";
    echo "📊 СТРУКТУРА НА ТАБЕЛА: advertisers\n";
    echo str_repeat("=", 100) . "\n\n";
    
    foreach ($columns as $col) {
        echo "✓ " . str_pad($col['Field'], 25) . " | " . str_pad($col['Type'], 20) . " | " . str_pad($col['Null'], 5) . " | " . str_pad($col['Key'], 5) . " | " . $col['Extra'] . "\n";
    }
    
    echo "\n" . str_repeat("=", 100) . "\n";
    echo "JSON FORMAT:\n";
    echo json_encode($columns, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    echo "\n</pre>";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
