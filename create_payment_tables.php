<?php
require_once 'db_connection.php';

$tables = [
    // Payments table
    "CREATE TABLE IF NOT EXISTS payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        intent_id VARCHAR(255) UNIQUE,
        stripe_payment_id VARCHAR(255),
        amount DECIMAL(10, 2),
        currency VARCHAR(3),
        payment_method VARCHAR(50),
        status VARCHAR(50),
        service_type VARCHAR(50),
        service_id INT,
        description TEXT,
        metadata JSON,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX (status),
        INDEX (created_at)
    )",
    
    // Payment intents table
    "CREATE TABLE IF NOT EXISTS payment_intents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        payment_id INT NOT NULL,
        stripe_intent_id VARCHAR(255) UNIQUE,
        client_secret VARCHAR(255),
        status VARCHAR(50),
        amount DECIMAL(10, 2),
        currency VARCHAR(3),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE,
        INDEX (stripe_intent_id)
    )",
    
    // Refunds table
    "CREATE TABLE IF NOT EXISTS refunds (
        id INT AUTO_INCREMENT PRIMARY KEY,
        payment_id INT NOT NULL,
        user_id INT NOT NULL,
        amount DECIMAL(10, 2),
        stripe_refund_id VARCHAR(255),
        reason VARCHAR(255),
        status VARCHAR(50),
        metadata JSON,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX (status)
    )",
    
    // Bank transfers table
    "CREATE TABLE IF NOT EXISTS bank_transfers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        payment_id INT NOT NULL,
        user_id INT NOT NULL,
        iban VARCHAR(34),
        amount DECIMAL(10, 2),
        currency VARCHAR(3),
        account_holder VARCHAR(255),
        reference_code VARCHAR(50),
        status VARCHAR(50),
        bank_reference VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX (reference_code),
        INDEX (status)
    )",
    
    // Payment audit log
    "CREATE TABLE IF NOT EXISTS payment_audit_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        payment_id INT,
        user_id INT NOT NULL,
        action VARCHAR(100),
        details JSON,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX (action),
        INDEX (created_at)
    )"
];

$errors = [];
foreach ($tables as $sql) {
    if (!$conn->query($sql)) {
        $errors[] = $conn->error;
    }
}

if (empty($errors)) {
    echo "✓ Payment tables created successfully!\n";
    echo "
    Tables created:
    - payments: Main payment records
    - payment_intents: Stripe payment intents
    - refunds: Refund records
    - bank_transfers: Bank transfer details
    - payment_audit_log: Payment transaction audit log
    ";
} else {
    echo "Errors occurred:\n";
    foreach ($errors as $error) {
        echo "- " . $error . "\n";
    }
}

$conn->close();
?>
