<?php
// Script to update all subscription prices to 150€
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

require_once 'config.php';

echo "<h1>Updating Subscription Prices</h1>";

try {
    // 1. First check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'abonimet'");
    if ($stmt->rowCount() == 0) {
        die("<p style='color: red;'>Error: Table 'abonimet' doesn't exist!</p>");
    }
    
    echo "<p>Table 'abonimet' exists. Proceeding with updates.</p>";
    
    // 2. Display current prices before update
    $stmt = $pdo->query("SELECT id, emri, cmimi, kohezgjatja FROM abonimet");
    $abonimet = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Current Prices:</h2>";
    echo "<ul>";
    foreach ($abonimet as $abonim) {
        echo "<li>{$abonim['emri']} (ID: {$abonim['id']}): {$abonim['cmimi']} € for {$abonim['kohezgjatja']} months</li>";
    }
    echo "</ul>";
    
    // 3. Update monthly subscription prices to 150€ and annual to 1800€
    $pdo->beginTransaction();
    
    // Update monthly subscriptions to 150€
    $stmt = $pdo->prepare("UPDATE abonimet SET cmimi = 150.00 WHERE kohezgjatja = 1");
    $stmt->execute();
    $monthly_updated = $stmt->rowCount();
    
    // Update annual subscriptions to 1800€
    $stmt = $pdo->prepare("UPDATE abonimet SET cmimi = 1800.00 WHERE kohezgjatja = 12");
    $stmt->execute();
    $annual_updated = $stmt->rowCount();
    
    // Update existing subscription payment records to match new prices
    $stmt = $pdo->prepare("
        UPDATE noteri_abonimet na
        JOIN abonimet a ON na.abonim_id = a.id
        SET na.paguar = CASE 
            WHEN a.kohezgjatja = 1 THEN 150.00
            WHEN a.kohezgjatja = 12 THEN 1800.00
            ELSE na.paguar
        END
    ");
    $stmt->execute();
    $payments_updated = $stmt->rowCount();
    
    $pdo->commit();
    
    // 4. Display updated prices
    $stmt = $pdo->query("SELECT id, emri, cmimi, kohezgjatja FROM abonimet");
    $updated_abonimet = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Updated Prices:</h2>";
    echo "<ul>";
    foreach ($updated_abonimet as $abonim) {
        echo "<li>{$abonim['emri']} (ID: {$abonim['id']}): {$abonim['cmimi']} € for {$abonim['kohezgjatja']} months</li>";
    }
    echo "</ul>";
    
    echo "<h2>Summary:</h2>";
    echo "<p>Successfully updated:</p>";
    echo "<ul>";
    echo "<li>$monthly_updated monthly subscription plans to 150€</li>";
    echo "<li>$annual_updated annual subscription plans to 1800€</li>";
    echo "<li>$payments_updated subscription payment records</li>";
    echo "</ul>";
    
    echo "<p><a href='abonimet.php'>Go back to Subscriptions Page</a></p>";
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
    error_log("Error updating subscription prices: " . $e->getMessage());
}
?>