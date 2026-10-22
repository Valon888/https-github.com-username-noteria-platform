<?php
/**
 * Database Migration Script
 * Ekzekuto: php migrate_db.php
 */

require_once __DIR__ . '/config.php';

echo "🔄 Ekzekutimi i migrimit SQL...\n\n";

try {
    // Lexo SQL file
    $sqlFile = __DIR__ . '/sql/subscription_plans_migration.sql';
    
    if (!file_exists($sqlFile)) {
        throw new Exception("File nuk u gjet: {$sqlFile}");
    }

    $sql = file_get_contents($sqlFile);
    
    // Ndaji queries me ;
    $queries = array_filter(array_map('trim', explode(';', $sql)));
    
    $executed = 0;
    foreach ($queries as $query) {
        if (empty($query)) continue;
        
        try {
            $pdo->exec($query);
            $executed++;
            echo "✅ Query $executed ekzekutuar\n";
        } catch (PDOException $e) {
            // Disa queries mund të jenë duplika, ignoro
            if (strpos($e->getMessage(), 'already exists') === false) {
                echo "⚠️  {$e->getMessage()}\n";
            } else {
                echo "ℹ️  Tabela ekziston tashmë\n";
            }
        }
    }

    echo "\n✨ Migrimi përfundoi! Ekzekutuar $executed queries\n";

} catch (Exception $e) {
    echo "❌ GABIM: " . $e->getMessage() . "\n";
    exit(1);
}
