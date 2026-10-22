<?php
require_once 'config.php';

try {
    echo "Checking triggers on 'zyrat' table...\n";
    
    $stmt = $pdo->prepare("SHOW TRIGGERS LIKE 'zyrat'");
    $stmt->execute();
    $triggers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($triggers) > 0) {
        foreach ($triggers as $trigger) {
            echo "Trigger: " . $trigger['Trigger'] . "\n";
            echo "Event: " . $trigger['Event'] . "\n";
            echo "Timing: " . $trigger['Timing'] . "\n";
            echo "Statement: " . $trigger['Statement'] . "\n";
            echo "-----------------------------------\n";
        }
    } else {
        echo "No triggers found on 'zyrat' table.\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>