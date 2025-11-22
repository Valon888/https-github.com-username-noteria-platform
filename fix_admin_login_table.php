<?php
require 'confidb.php';

try {
    // Fix admin_login_attempts table
    $sql = "ALTER TABLE admin_login_attempts MODIFY COLUMN attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
    $pdo->exec($sql);
    echo "✓ Fixed admin_login_attempts table\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
