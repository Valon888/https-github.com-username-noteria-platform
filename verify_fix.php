<?php
require_once 'confidb.php';

// Function to check the updated query
function checkQuery($pdo, $query) {
    try {
        echo "Testing query: " . $query . "\n\n";
        $stmt = $pdo->query($query);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Query successful! Retrieved " . count($rows) . " rows.\n";
        
        if (count($rows) > 0) {
            echo "Sample data from first row:\n";
            print_r($rows[0]);
        }
        
        return true;
    } catch (PDOException $e) {
        echo "Query failed: " . $e->getMessage() . "\n";
        return false;
    }
}

// Check the payment query
echo "======== CHECKING PAYMENT QUERY ========\n";
$paymentQuery = "SELECT p.id, p.reservation_id, p.client_name, p.amount, p.created_at, p.status, 
               u.emri, u.mbiemri, u.email,
               z.emri AS zyra_emri
               FROM payments p
               JOIN users u ON p.user_id = u.id
               JOIN zyrat z ON p.zyra_id = z.id
               ORDER BY p.created_at DESC
               LIMIT 5";
               
checkQuery($pdo, $paymentQuery);

// Check the subscription query
echo "\n\n======== CHECKING SUBSCRIPTION QUERY ========\n";
$subQuery = "SELECT s.*, z.emri AS zyra_emri 
             FROM subscription s 
             JOIN zyrat z ON s.zyra_id = z.id 
             ORDER BY s.expiry_date DESC
             LIMIT 5";
             
checkQuery($pdo, $subQuery);

echo "\nVerification complete!";
?>