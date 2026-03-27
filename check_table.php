<?php
require 'config.php';

// Check if table exists
$sql = "SELECT * FROM information_schema.TABLES WHERE TABLE_NAME = 'subscription_plans'";
$result = $pdo->query($sql);
$tableExists = $result->rowCount() > 0;

echo "Tabela 'subscription_plans' ekziston: " . ($tableExists ? "PO ✅\n" : "JO ❌\n");

if ($tableExists) {
    echo "\nStruktura e tabelës:\n";
    $result = $pdo->query('DESCRIBE subscription_plans');
    $columns = $result->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $col) {
        echo "  - {$col['Field']}: {$col['Type']}\n";
    }
}
