<?php
// Include database connection
require_once 'config.php';
session_start();

$payment_id = $_GET['payment_id'] ?? 1;  // Default to first payment for testing

if (!is_numeric($payment_id)) {
    $payment_id = 1;
}

echo "<h2>Debugging Invoice Data for Payment ID: $payment_id</h2>";

// Check if payment exists
echo "<h3>1. Payment Data:</h3>";
$stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ? LIMIT 1");
$stmt->execute([$payment_id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);
echo "<pre>";
echo "Payment ID: " . ($payment['id'] ?? 'NOT FOUND') . "\n";
echo "User ID: " . ($payment['user_id'] ?? 'null') . "\n";
echo "Reservation ID: " . ($payment['reservation_id'] ?? 'null') . "\n";
echo "Amount: " . ($payment['amount'] ?? 'null') . "\n";
echo "</pre>";

$reservation_id = $payment['reservation_id'] ?? null;

if ($reservation_id) {
    echo "<h3>2. Reservation Data:</h3>";
    $stmt = $pdo->prepare("SELECT * FROM reservations WHERE id = ? LIMIT 1");
    $stmt->execute([$reservation_id]);
    $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<pre>";
    foreach ($reservation as $key => $val) {
        echo "$key: $val\n";
    }
    echo "</pre>";
    
    echo "<h3>3. User Data:</h3>";
    $user_id = $payment['user_id'] ?? null;
    if ($user_id) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<pre>";
        foreach ($user as $key => $val) {
            echo "$key: " . ($val ?? 'null') . "\n";
        }
        echo "</pre>";
    }
    
    echo "<h3>4. Office (Zyra) Data:</h3>";
    $zyra_id = $reservation['zyra_id'] ?? null;
    if ($zyra_id) {
        $stmt = $pdo->prepare("SELECT * FROM zyrat WHERE id = ? LIMIT 1");
        $stmt->execute([$zyra_id]);
        $zyra = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<pre>";
        foreach ($zyra as $key => $val) {
            if ($key == 'staff_data') {
                echo "$key: " . substr($val, 0, 100) . "...\n";
            } else {
                echo "$key: " . ($val ?? 'null') . "\n";
            }
        }
        echo "</pre>";
    }
    
    echo "<h3>5. Employee (Punonjesi) Data:</h3>";
    $punonjesi_id = $reservation['punonjesi_id'] ?? null;
    if ($punonjesi_id && $punonjesi_id > 0) {
        echo "Looking for employee ID: $punonjesi_id<br>";
        
        // Try punonjesit table
        try {
            $stmt = $pdo->prepare("SELECT * FROM punonjesit WHERE id = ? LIMIT 1");
            $stmt->execute([$punonjesi_id]);
            $employee = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($employee) {
                echo "<strong>Found in punonjesit table:</strong><br>";
                echo "<pre>";
                foreach ($employee as $key => $val) {
                    echo "$key: " . ($val ?? 'null') . "\n";
                }
                echo "</pre>";
            } else {
                echo "Not found in punonjesit table<br>";
            }
        } catch (Exception $e) {
            echo "Error querying punonjesit: " . $e->getMessage() . "<br>";
        }
        
        // Try punetoret table
        try {
            $stmt = $pdo->prepare("SELECT * FROM punetoret WHERE id = ? LIMIT 1");
            $stmt->execute([$punonjesi_id]);
            $employee = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($employee) {
                echo "<strong>Found in punetoret table:</strong><br>";
                echo "<pre>";
                foreach ($employee as $key => $val) {
                    echo "$key: " . ($val ?? 'null') . "\n";
                }
                echo "</pre>";
            } else {
                echo "Not found in punetoret table<br>";
            }
        } catch (Exception $e) {
            echo "Error querying punetoret: " . $e->getMessage() . "<br>";
        }
    } else {
        echo "No employee ID in reservation or ID is 0/null<br>";
    }
}

echo "<h3>6. Test JOIN Query:</h3>";
try {
    $stmt = $pdo->prepare("
        SELECT 
            p.id as payment_id,
            p.reservation_id,
            r.service,
            r.date,
            r.time,
            r.punonjesi_id,
            u.emri as user_emri,
            u.mbiemri as user_mbiemri,
            z.emri as zyra_emri,
            pun.emri as pun_emri,
            pun.mbiemri as pun_mbiemri
        FROM payments p
        LEFT JOIN reservations r ON p.reservation_id = r.id
        LEFT JOIN users u ON p.user_id = u.id
        LEFT JOIN zyrat z ON r.zyra_id = z.id
        LEFT JOIN punonjesit pun ON r.punonjesi_id = pun.id
        WHERE p.id = ?
        LIMIT 1
    ");
    $stmt->execute([$payment_id]);
    $joined = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<pre>";
    foreach ($joined as $key => $val) {
        echo "$key: " . ($val ?? 'null') . "\n";
    }
    echo "</pre>";
} catch (Exception $e) {
    echo "JOIN Query Error: " . $e->getMessage() . "<br>";
}
?>
