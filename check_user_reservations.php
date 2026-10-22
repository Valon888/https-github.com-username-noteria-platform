<?php
// Check what reservations exist for the current or test user
require 'config.php';

try {
    // For demonstration, check user ID 2 (Valdrin Biçkaj - the main test user)
    $userId = 2;
    
    echo "<h2>Checking all reservations for User ID: {$userId}</h2>";
    
    $query = "
    SELECT 
        r.id as res_id,
        r.user_id,
        r.zyra_id,
        r.service,
        r.date,
        r.time,
        r.payment_method,
        r.payment_status,
        u.emri,
        u.mbiemri,
        z.emri as zyra_emri,
        p.id as payment_id,
        p.amount,
        p.uuid,
        p.created_at as payment_date
    FROM reservations r
    LEFT JOIN users u ON r.user_id = u.id
    LEFT JOIN zyrat z ON r.zyra_id = z.id
    LEFT JOIN payments p ON p.reservation_id = r.id
    WHERE r.user_id = ?
    ORDER BY r.created_at DESC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$userId]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($results)) {
        echo "<p>No reservations found for user {$userId}</p>";
    } else {
        echo "<p>Found " . count($results) . " reservations</p>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Res ID</th><th>Service</th><th>Date</th><th>Time</th><th>Payment ID</th><th>Status</th><th>Amount</th></tr>";
        
        foreach ($results as $row) {
            $service = $row['service'] ?? 'NULL';
            $payment_id = $row['payment_id'] ?? 'No payment';
            $status = $row['payment_status'] ?? 'pending';
            $amount = $row['amount'] ? '€' . number_format($row['amount'], 2) : 'N/A';
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['res_id']) . "</td>";
            echo "<td>" . htmlspecialchars($service) . "</td>";
            echo "<td>" . htmlspecialchars($row['date']) . "</td>";
            echo "<td>" . htmlspecialchars($row['time']) . "</td>";
            echo "<td>" . htmlspecialchars($payment_id) . "</td>";
            echo "<td>" . htmlspecialchars($status) . "</td>";
            echo "<td>" . $amount . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Also check if "Hartimi i testamentit" reservations exist for ANY user
    echo "<h2>Checking all reservations with 'Hartim' keyword across all users</h2>";
    $query2 = "SELECT id, user_id, service, date FROM reservations WHERE service LIKE ? ORDER BY id DESC";
    $stmt2 = $pdo->prepare($query2);
    $stmt2->execute(['%Hartim%']);
    $results2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($results2)) {
        echo "<p>No reservations with 'Hartim' found in entire database</p>";
    } else {
        echo "<p>Found " . count($results2) . " reservations with 'Hartim':</p>";
        echo "<pre>";
        print_r($results2);
        echo "</pre>";
    }
    
} catch (Exception $e) {
    echo "<p style='color:red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
