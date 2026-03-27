<?php
require_once 'confidb.php';

echo "<h2>Debug Info</h2>";

// 1. Check Notaret
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM notaret");
    $countNotaret = $stmt->fetchColumn();
    echo "Notaret count: $countNotaret<br>";
} catch (Exception $e) {
    echo "Error checking notaret: " . $e->getMessage() . "<br>";
}

// 2. Check Zyrat
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM zyrat");
    $countZyrat = $stmt->fetchColumn();
    echo "Zyrat count: $countZyrat<br>";
} catch (Exception $e) {
    echo "Error checking zyrat: " . $e->getMessage() . "<br>";
}

// 3. Try to insert one dummy office to see errors
if ($countZyrat == 0) {
    echo "<h3>Attempting test insert...</h3>";
    try {
        $sql = "INSERT INTO zyrat (
            emri, qyteti, adresa, email, telefoni, 
            numri_fiskal, numri_biznesit, numri_licences, 
            iban, emri_noterit, fjalekalimi, username, statusi
        ) VALUES (
            'Test Office', 'Prishtine', 'Rruga Test', 'test@test.com', '044123123', 
            '111111', '222222', '333333', 
            'XK050000000000000000', 'Test Noter', 'hash', 'testuser', 'aprovuar'
        )";
        $pdo->exec($sql);
        echo "Test insert successful.<br>";
    } catch (PDOException $e) {
        echo "Test insert failed: " . $e->getMessage() . "<br>";
    }
}
?>