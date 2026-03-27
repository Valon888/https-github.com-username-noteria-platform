<?php
require 'config.php';

$stmt = $pdo->query('SELECT COUNT(*) as cnt FROM reservations');
$row = $stmt->fetch();
echo 'Total reservations in database: ' . $row['cnt'] . PHP_EOL;

$stmt2 = $pdo->query('SELECT DISTINCT service FROM reservations ORDER BY service');
$services = $stmt2->fetchAll(PDO::FETCH_COLUMN);
if (!empty($services)) {
    echo 'Services in database:' . PHP_EOL;
    foreach ($services as $service) {
        echo '  - ' . $service . PHP_EOL;
    }
} else {
    echo 'No services found (reservations table may be empty)' . PHP_EOL;
}
?>
