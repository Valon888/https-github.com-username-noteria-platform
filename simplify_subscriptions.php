<?php
// Script to simplify subscription plans to just monthly and yearly options
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

require_once 'config.php';

echo "<h1>Updating Subscription Plans</h1>";

try {
    // 1. First check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'abonimet'");
    if ($stmt->rowCount() == 0) {
        die("<p style='color: red;'>Error: Table 'abonimet' doesn't exist!</p>");
    }
    
    echo "<p>Table 'abonimet' exists. Proceeding with updates.</p>";
    
    // 2. Display current plans before update
    $stmt = $pdo->query("SELECT id, emri, cmimi, kohezgjatja FROM abonimet");
    $abonimet = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Current Plans:</h2>";
    echo "<ul>";
    foreach ($abonimet as $abonim) {
        echo "<li>{$abonim['emri']} (ID: {$abonim['id']}): {$abonim['cmimi']} € for {$abonim['kohezgjatja']} months</li>";
    }
    echo "</ul>";
    
    // 3. Delete all existing plans
    $pdo->beginTransaction();
    
    // Store which plans existed before
    $had_monthly = false;
    $had_yearly = false;
    
    foreach ($abonimet as $abonim) {
        if ($abonim['kohezgjatja'] == 1) {
            $had_monthly = true;
        } elseif ($abonim['kohezgjatja'] == 12) {
            $had_yearly = true;
        }
    }
    
    // Delete all existing plans
    $stmt = $pdo->prepare("DELETE FROM abonimet");
    $stmt->execute();
    $deleted_count = $stmt->rowCount();
    
    // 4. Insert new simplified plans
    $features_monthly = json_encode([
        "Qasje e plotë në platformë", 
        "Dokumente të pakufizuara", 
        "Mbështetje prioritare 24/7", 
        "Të gjitha shërbimet e platformës", 
        "Mjete të avancuara për noterë"
    ]);
    
    $features_yearly = json_encode([
        "Qasje e plotë në platformë", 
        "Dokumente të pakufizuara", 
        "Mbështetje prioritare 24/7", 
        "Të gjitha shërbimet e platformës", 
        "Mjete të avancuara për noterë", 
        "Kurseni 300€ me pagesën vjetore", 
        "Trajnime personale", 
        "Këshillime ligjore mujore të përfshira"
    ]);
    
    // Insert monthly plan
    $stmt = $pdo->prepare("INSERT INTO abonimet (emri, cmimi, kohezgjatja, pershkrimi, karakteristikat, status) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute(['Abonim Mujor', 150.00, 1, 'Abonim mujor me qasje të plotë në platformë', $features_monthly, 'aktiv']);
    $monthly_id = $pdo->lastInsertId();
    
    // Insert yearly plan
    $stmt->execute(['Abonim Vjetor', 1500.00, 12, 'Abonim vjetor me qasje të plotë në platformë', $features_yearly, 'aktiv']);
    $yearly_id = $pdo->lastInsertId();
    
    // 5. Update any existing subscriptions to point to new plans
    if ($had_monthly) {
        $stmt = $pdo->prepare("
            UPDATE noteri_abonimet 
            SET abonim_id = ?, paguar = 150.00
            WHERE abonim_id IN (SELECT id FROM abonimet WHERE kohezgjatja = 1 AND id != ?)
        ");
        $stmt->execute([$monthly_id, $monthly_id]);
        $monthly_updated = $stmt->rowCount();
    } else {
        $monthly_updated = 0;
    }
    
    if ($had_yearly) {
        $stmt = $pdo->prepare("
            UPDATE noteri_abonimet 
            SET abonim_id = ?, paguar = 1500.00
            WHERE abonim_id IN (SELECT id FROM abonimet WHERE kohezgjatja = 12 AND id != ?)
        ");
        $stmt->execute([$yearly_id, $yearly_id]);
        $yearly_updated = $stmt->rowCount();
    } else {
        $yearly_updated = 0;
    }
    
    $pdo->commit();
    
    // 6. Display updated plans
    $stmt = $pdo->query("SELECT id, emri, cmimi, kohezgjatja FROM abonimet");
    $updated_abonimet = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>New Simplified Plans:</h2>";
    echo "<ul>";
    foreach ($updated_abonimet as $abonim) {
        echo "<li>{$abonim['emri']} (ID: {$abonim['id']}): {$abonim['cmimi']} € for {$abonim['kohezgjatja']} months</li>";
    }
    echo "</ul>";
    
    echo "<h2>Summary:</h2>";
    echo "<p>Successfully updated:</p>";
    echo "<ul>";
    echo "<li>Removed {$deleted_count} old subscription plans</li>";
    echo "<li>Created 2 new subscription plans (Monthly and Yearly)</li>";
    echo "<li>Updated {$monthly_updated} monthly subscription records</li>";
    echo "<li>Updated {$yearly_updated} yearly subscription records</li>";
    echo "</ul>";
    
    echo "<p><a href='abonimet.php'>Go back to Subscriptions Page</a></p>";
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
    error_log("Error updating subscription plans: " . $e->getMessage());
}
?>