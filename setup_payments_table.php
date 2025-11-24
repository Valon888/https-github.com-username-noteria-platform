<?php
require 'confidb.php';

echo "=== Verifying Payments Table ===\n\n";

// Check if payments table exists and has correct structure
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'payments'");
    if ($stmt->rowCount() === 0) {
        echo "Creating payments table...\n";
        $pdo->exec("
            CREATE TABLE payments (
                id INT(11) AUTO_INCREMENT PRIMARY KEY,
                user_id INT(11) NOT NULL,
                emri_i_plot VARCHAR(100) NOT NULL,
                iban VARCHAR(50) NOT NULL,
                shuma DECIMAL(10,2) NOT NULL,
                pershkrimi TEXT NOT NULL,
                statusi ENUM('pending', 'completed', 'rejected', 'cancelled') DEFAULT 'pending',
                payment_method VARCHAR(50) DEFAULT 'bank_transfer',
                data_krijimit DATETIME DEFAULT CURRENT_TIMESTAMP,
                data_përfundimit DATETIME NULL,
                referenca VARCHAR(100) UNIQUE,
                shënime TEXT,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX(user_id),
                INDEX(statusi),
                INDEX(data_krijimit)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "✓ Payments table created\n";
    } else {
        echo "✓ Payments table exists\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Verify table structure
echo "\nTable structure:\n";
try {
    $stmt = $pdo->query("DESCRIBE payments");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo "  • " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n✅ Setup complete!\n";
?>
