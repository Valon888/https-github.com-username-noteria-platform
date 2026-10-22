<?php
require_once 'confidb.php';

try {
    $stmt = $pdo->query('SELECT id, emri, banka, iban, llogaria, email FROM zyrat LIMIT 5');
    $offices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo 'Sample offices with banking info:' . PHP_EOL;
    foreach ($offices as $office) {
        echo 'ID: ' . $office['id'] . ' - ' . $office['emri'] . ' - Bank: ' . ($office['banka'] ?: 'N/A') . ' - IBAN: ' . ($office['iban'] ?: 'N/A') . PHP_EOL;
    }

    echo PHP_EOL . 'Checking reservations with zyra_id:' . PHP_EOL;
    $stmt = $pdo->query('SELECT id, user_id, zyra_id, service, status FROM reservations WHERE zyra_id IS NOT NULL AND zyra_id > 0 LIMIT 5');
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($reservations as $res) {
        echo 'Reservation ID: ' . $res['id'] . ' - Office ID: ' . $res['zyra_id'] . ' - Service: ' . $res['service'] . PHP_EOL;
    }

} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
?>