<?php
require_once 'db_connection.php';

echo '<h1>Update User Subscriptions</h1>';

try {
    // Check if table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'noteri_abonimet'");
    
    if ($tableCheck->num_rows > 0) {
        // Count existing records
        $countResult = $conn->query("SELECT COUNT(*) as total FROM noteri_abonimet");
        $countRow = $countResult->fetch_assoc();
        echo "<p>Found {$countRow['total']} subscription records to update.</p>";
        
        // Update monthly subscriptions to point to the new monthly plan
        $monthlyResult = $conn->query("
            UPDATE noteri_abonimet 
            SET abonim_id = 5
            WHERE abonim_id IN (1, 2, 3)
        ");
        
        echo "<p>Updated " . $conn->affected_rows . " monthly subscriptions to point to the new Monthly Plan (ID: 5).</p>";
        
        // Update yearly subscriptions to point to the new yearly plan
        $yearlyResult = $conn->query("
            UPDATE noteri_abonimet 
            SET abonim_id = 6
            WHERE abonim_id = 4
        ");
        
        echo "<p>Updated " . $conn->affected_rows . " yearly subscriptions to point to the new Yearly Plan (ID: 6).</p>";
        
        echo "<h2>Update Complete</h2>";
        echo "<p>All user subscriptions have been updated to point to the new simplified subscription plans.</p>";
        
        // Link to check the results
        echo "<p><a href='check_user_subscriptions.php'>Check Updated Subscriptions</a></p>";
    } else {
        echo '<p>Notary subscriptions table does not exist.</p>';
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?>