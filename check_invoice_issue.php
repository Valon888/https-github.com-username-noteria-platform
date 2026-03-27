<?php
// Simple script to check invoice and reservation data mismatch
require_once 'config.php';

// Get the most recent payment with reservation
$stmt = $pdo->prepare("
    SELECT 
        p.id,
        p.amount,
        p.created_at,
        r.id as res_id,
        r.service,
        r.date,
        r.time,
        r.payment_status,
        u.emri,
        u.mbiemri,
        z.emri as zyra_emri
    FROM payments p
    LEFT JOIN reservations r ON p.reservation_id = r.id
    LEFT JOIN users u ON p.user_id = u.id
    LEFT JOIN zyrat z ON r.zyra_id = z.id
    ORDER BY p.id DESC
    LIMIT 10
");
$stmt->execute();
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>Recent Payments with Reservations</h2>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr>";
echo "<th>Payment ID</th>";
echo "<th>Reservation ID</th>";
echo "<th>Service (from reservation)</th>";
echo "<th>Customer</th>";
echo "<th>Office</th>";
echo "<th>Date</th>";
echo "<th>Amount</th>";
echo "</tr>";

foreach ($payments as $p) {
    echo "<tr>";
    echo "<td>" . ($p['id'] ?? '') . "</td>";
    echo "<td>" . ($p['res_id'] ?? 'No reservation') . "</td>";
    echo "<td><strong>" . ($p['service'] ?? 'NULL') . "</strong></td>";
    echo "<td>" . ($p['emri'] ?? '') . " " . ($p['mbiemri'] ?? '') . "</td>";
    echo "<td>" . ($p['zyra_emri'] ?? '') . "</td>";
    echo "<td>" . ($p['date'] ?? '') . "</td>";
    echo "<td>" . ($p['amount'] ?? '') . "€</td>";
    echo "</tr>";
}

echo "</table>";

// Check the specific payment the user mentioned
echo "<h2>Checking all reservations with 'Hartim të Testamentit'</h2>";
$stmt = $pdo->prepare("
    SELECT 
        r.id,
        r.service,
        r.date,
        r.time,
        r.user_id,
        r.zyra_id,
        r.punonjesi_id,
        r.payment_status,
        p.id as payment_id,
        p.amount
    FROM reservations r
    LEFT JOIN payments p ON p.reservation_id = r.id
    WHERE r.service LIKE '%Hartim%' OR r.service LIKE '%Testamenti%'
    ORDER BY r.id DESC
    LIMIT 5
");
$stmt->execute();
$testament_reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<p>Found " . count($testament_reservations) . " reservations with 'Hartim të Testamentit'</p>";
if (!empty($testament_reservations)) {
    foreach ($testament_reservations as $res) {
        echo "<pre>";
        echo "Reservation ID: " . $res['id'] . "\n";
        echo "Service: " . $res['service'] . "\n";
        echo "Date: " . $res['date'] . " " . $res['time'] . "\n";
        echo "Payment ID: " . ($res['payment_id'] ?? 'No payment yet') . "\n";
        echo "Amount: " . ($res['amount'] ?? 'N/A') . "€\n";
        echo "</pre>";
    }
}

// Check services in database
echo "<h2>All Services in Database</h2>";
$stmt = $pdo->query("SELECT DISTINCT service FROM reservations ORDER BY service");
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<ul>";
foreach ($services as $svc) {
    echo "<li>" . $svc['service'] . "</li>";
}
echo "</ul>";
?>
