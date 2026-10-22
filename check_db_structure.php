<?php
require_once 'confidb.php';

try {
    echo "Checking database structure...\n\n";

    // Check payments table
    $stmt = $pdo->query('DESCRIBE payments');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo 'Payments table columns:' . PHP_EOL;
    foreach ($columns as $col) {
        echo '  ' . $col['Field'] . ' (' . $col['Type'] . ')' . PHP_EOL;
    }
    echo PHP_EOL;

    // Check reservations table
    $stmt = $pdo->query('DESCRIBE reservations');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo 'Reservations table columns:' . PHP_EOL;
    foreach ($columns as $col) {
        echo '  ' . $col['Field'] . ' (' . $col['Type'] . ')' . PHP_EOL;
    }
    echo PHP_EOL;

    // Check zyrat table
    $stmt = $pdo->query('DESCRIBE zyrat');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo 'Zyrat table columns:' . PHP_EOL;
    foreach ($columns as $col) {
        echo '  ' . $col['Field'] . ' (' . $col['Type'] . ')' . PHP_EOL;
    }
    echo PHP_EOL;

    // Check if zyra_id column exists in payments
    $stmt = $pdo->query("SHOW COLUMNS FROM payments LIKE 'zyra_id'");
    $zyra_id_exists = $stmt->fetch(PDO::FETCH_ASSOC);
    echo 'zyra_id column in payments: ' . ($zyra_id_exists ? 'EXISTS' : 'MISSING') . PHP_EOL;

    // Check if zyrat has banking fields
    $stmt = $pdo->query("SHOW COLUMNS FROM zyrat LIKE 'iban'");
    $iban_exists = $stmt->fetch(PDO::FETCH_ASSOC);
    echo 'iban column in zyrat: ' . ($iban_exists ? 'EXISTS' : 'MISSING') . PHP_EOL;

    $stmt = $pdo->query("SHOW COLUMNS FROM zyrat LIKE 'banka'");
    $banka_exists = $stmt->fetch(PDO::FETCH_ASSOC);
    echo 'banka column in zyrat: ' . ($banka_exists ? 'EXISTS' : 'MISSING') . PHP_EOL;

} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
?>