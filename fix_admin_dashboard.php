<?php
require 'confidb.php';

echo "=== Creating Missing Tables for Admin Dashboard ===\n\n";

$tables_to_create = [];

// Check if payments table exists
try {
    $pdo->query("SELECT 1 FROM payments LIMIT 1");
    echo "✓ payments table exists\n";
} catch (Exception $e) {
    echo "✗ Creating payments table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS payments (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11),
        amount DECIMAL(10,2),
        payment_method VARCHAR(50),
        status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
        transaction_id VARCHAR(255),
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        INDEX (user_id),
        INDEX (status),
        INDEX (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "  ✓ payments table created\n";
}

// Check if subscription table exists
try {
    $pdo->query("SELECT 1 FROM subscription LIMIT 1");
    echo "✓ subscription table exists\n";
} catch (Exception $e) {
    echo "✗ Creating subscription table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS subscription (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11),
        plan_name VARCHAR(100),
        status ENUM('active', 'expired', 'cancelled', 'pending') DEFAULT 'pending',
        start_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        expiry_date DATETIME,
        price DECIMAL(10,2),
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        INDEX (user_id),
        INDEX (status),
        INDEX (expiry_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "  ✓ subscription table created\n";
}

// Check if zyrat table exists
try {
    $pdo->query("SELECT 1 FROM zyrat LIMIT 1");
    echo "✓ zyrat table exists\n";
} catch (Exception $e) {
    echo "✗ Creating zyrat table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS zyrat (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        emri VARCHAR(100) NOT NULL,
        adresa VARCHAR(255),
        telefoni VARCHAR(20),
        email VARCHAR(255),
        fax VARCHAR(20),
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL,
        INDEX (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "  ✓ zyrat table created\n";
}

// Check if fatura table exists
try {
    $pdo->query("SELECT 1 FROM fatura LIMIT 1");
    echo "✓ fatura table exists\n";
} catch (Exception $e) {
    echo "✗ Creating fatura table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS fatura (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11),
        invoice_number VARCHAR(100) UNIQUE,
        amount DECIMAL(10,2),
        status ENUM('draft', 'sent', 'paid', 'overdue', 'cancelled') DEFAULT 'draft',
        issue_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        due_date DATETIME,
        paid_date DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        INDEX (user_id),
        INDEX (status),
        INDEX (due_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "  ✓ fatura table created\n";
}

echo "\n=== All tables verified ===\n";

// List all tables
$stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'noteria' ORDER BY table_name");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "\nTotal tables: " . count($tables) . "\n";
foreach ($tables as $table) {
    echo "  - $table\n";
}
