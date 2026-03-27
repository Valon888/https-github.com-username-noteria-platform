<?php
require_once 'config.php';

try {
    $stmt = $pdo->query("SELECT * FROM abonimet");
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($plans)) {
        echo "No plans found in the database.\n";
    } else {
        echo "Current Plans in Database:\n";
        echo "--------------------------\n";
        foreach ($plans as $plan) {
            echo "ID: " . $plan['id'] . "\n";
            echo "Name: " . $plan['emri'] . "\n";
            echo "Price: " . $plan['cmimi'] . " EUR\n";
            echo "Duration: " . $plan['kohezgjatja'] . " months\n";
            echo "Status: " . $plan['status'] . "\n";
            echo "--------------------------\n";
        }
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>