<?php
/**
 * Fix Database Schema
 * Додава недостасушки колони во табелата advertisers
 */

require_once 'confidb.php';

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
    echo "<pre style='background: #f4f4f4; padding: 20px; font-family: monospace;'>";
    echo "🔧 ПОПРАВКА НА БАЗА НА ПОДАТОЦИ\n";
    echo str_repeat("=", 80) . "\n\n";
    
    // Get existing columns
    $columns = $pdo->query("DESC advertisers")->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'Field');
    
    echo "📊 Постоечки колони:\n";
    foreach ($columnNames as $col) {
        echo "  ✓ $col\n";
    }
    echo "\n";
    
    // Check for missing columns and add them
    $alterStatements = [];
    
    if (!in_array('category', $columnNames)) {
        echo "❌ Колона 'category' е недостасна. Додавам...\n";
        $pdo->exec("ALTER TABLE advertisers ADD COLUMN category VARCHAR(100) AFTER description");
        echo "✅ Колона 'category' е додадена успешно.\n";
    } else {
        echo "✅ Колона 'category' веќе постои.\n";
    }
    
    if (!in_array('subscription_status', $columnNames)) {
        echo "❌ Колона 'subscription_status' е недостасна. Додавам...\n";
        $pdo->exec("ALTER TABLE advertisers ADD COLUMN subscription_status VARCHAR(50) DEFAULT 'pending' AFTER business_registration");
        echo "✅ Колона 'subscription_status' е додадена успешно.\n";
    } else {
        echo "✅ Колона 'subscription_status' веќе постои.\n";
    }
    
    // Refresh columns
    $columns = $pdo->query("DESC advertisers")->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'Field');
    
    echo "\n" . str_repeat("=", 80) . "\n";
    echo "📊 НОВА СТРУКТУРА НА ТАБЕЛА:\n";
    echo str_repeat("-", 80) . "\n";
    
    foreach ($columns as $col) {
        echo str_pad($col['Field'], 25) . " | " . str_pad($col['Type'], 20) . " | " . str_pad($col['Null'], 5) . " | " . $col['Key'] . "\n";
    }
    
    echo "\n✅ Поправката е комплетна!\n";
    echo "🔗 Сега ви можете да глави форма во: http://localhost/noteria/become-advertiser.php\n";
    echo "</pre>";
    
} catch (PDOException $e) {
    echo "<pre style='background: #ffcccc; padding: 20px; font-family: monospace;'>";
    echo "❌ Грешка: " . $e->getMessage() . "\n";
    echo "Код: " . $e->getCode() . "\n";
    echo "</pre>";
}
?>
