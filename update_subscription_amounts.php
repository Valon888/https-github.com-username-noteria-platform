<?php
// update_subscription_amounts.php
// Përditëson të gjitha pagesat e abonimit në 150 euro

require_once 'config.php';

try {
    $sql = "UPDATE subscription_payments SET amount = 150.00";
    $affected = $pdo->exec($sql);
    echo "U përditësuan $affected pagesa në 150 euro.";
} catch (PDOException $e) {
    echo "Gabim: " . $e->getMessage();
}
