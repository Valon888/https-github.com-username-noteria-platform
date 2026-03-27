<?php
require_once 'config.php';

try {
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Searching for 'amount' column in all tables...\n";
    
    foreach ($tables as $table) {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE 'amount'");
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            echo "Found 'amount' column in table: $table\n";
            
            // Check triggers on zyrat to see if they reference this table
            $trigStmt = $pdo->prepare("SHOW TRIGGERS WHERE `Table` = 'zyrat'");
            $trigStmt->execute();
            $triggers = $trigStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach($triggers as $trig) {
                if (stripos($trig['Statement'], $table) !== false) {
                    echo "  -> POTENTIAL CULPRIT: Trigger '{$trig['Trigger']}' on 'zyrat' references '$table'\n";
                    echo "     Statement: {$trig['Statement']}\n";
                }
            }
        }
    }
    
    // Also just list triggers on zyrat
    echo "\nAll triggers on 'zyrat':\n";
    $stmt = $pdo->prepare("SHOW TRIGGERS WHERE `Table` = 'zyrat'");
    $stmt->execute();
    $triggers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($triggers as $trigger) {
        echo "Trigger: " . $trigger['Trigger'] . "\n";
        echo "Statement: " . $trigger['Statement'] . "\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>