-- SQL script to create payment-related tables for Noteria platform

-- Create payments table if it doesn't exist
CREATE TABLE IF NOT EXISTS payments (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    payment_id VARCHAR(255) NOT NULL COMMENT 'Unique payment identifier',
    user_id VARCHAR(255) NOT NULL COMMENT 'User who made the payment',
    amount DECIMAL(10, 2) NOT NULL COMMENT 'Payment amount',
    currency VARCHAR(10) NOT NULL COMMENT 'Payment currency',
    service_type VARCHAR(50) NOT NULL COMMENT 'Type of service being paid for',
    status VARCHAR(20) NOT NULL COMMENT 'Payment status (pending, completed, failed, cancelled)',
    payment_method VARCHAR(20) DEFAULT NULL COMMENT 'Payment method used (paysera, raiffeisen, bkt)',
    creation_date DATETIME NOT NULL COMMENT 'When payment was initiated',
    completion_date DATETIME DEFAULT NULL COMMENT 'When payment was completed',
    expiry_date DATETIME DEFAULT NULL COMMENT 'When service access expires',
    meta_data TEXT DEFAULT NULL COMMENT 'Additional JSON-encoded payment metadata',
    INDEX (payment_id),
    INDEX (user_id),
    INDEX (status),
    INDEX (service_type),
    INDEX (creation_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create payment_logs table for detailed payment logging
CREATE TABLE IF NOT EXISTS payment_logs (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    payment_id VARCHAR(255) NOT NULL,
    log_type VARCHAR(50) NOT NULL COMMENT 'Type of log entry (callback, verification, error, etc.)',
    log_data TEXT NOT NULL COMMENT 'JSON-encoded log data',
    ip_address VARCHAR(45) DEFAULT NULL COMMENT 'IP address that triggered the log',
    created_at DATETIME NOT NULL,
    INDEX (payment_id),
    INDEX (log_type),
    INDEX (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create video_consultations table to track video consultation sessions
CREATE TABLE IF NOT EXISTS video_consultations (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    room_id VARCHAR(255) NOT NULL COMMENT 'Unique room identifier',
    payment_id VARCHAR(255) DEFAULT NULL COMMENT 'Associated payment ID',
    user_id VARCHAR(255) NOT NULL COMMENT 'Client user ID',
    notary_id VARCHAR(255) DEFAULT NULL COMMENT 'Notary user ID',
    status VARCHAR(20) NOT NULL COMMENT 'Session status (pending, active, completed, cancelled)',
    start_time DATETIME DEFAULT NULL COMMENT 'When session actually started',
    end_time DATETIME DEFAULT NULL COMMENT 'When session ended',
    scheduled_duration INT(11) DEFAULT 30 COMMENT 'Scheduled duration in minutes',
    actual_duration INT(11) DEFAULT NULL COMMENT 'Actual duration in minutes',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX (room_id),
    INDEX (payment_id),
    INDEX (user_id),
    INDEX (notary_id),
    INDEX (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert some service types into a services table if it doesn't exist
CREATE TABLE IF NOT EXISTS services (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    service_code VARCHAR(50) NOT NULL COMMENT 'Unique service identifier',
    name VARCHAR(255) NOT NULL COMMENT 'Service name',
    description TEXT DEFAULT NULL COMMENT 'Service description',
    price DECIMAL(10, 2) NOT NULL COMMENT 'Service price',
    currency VARCHAR(10) NOT NULL DEFAULT 'EUR' COMMENT 'Price currency',
    duration INT(11) DEFAULT NULL COMMENT 'Service duration in minutes (if applicable)',
    is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Whether service is currently available',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX (service_code),
    INDEX (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default services
INSERT IGNORE INTO services (service_code, name, description, price, currency, duration, is_active, created_at, updated_at) VALUES
('video_consultation', 'Konsulencë video me noter', 'Konsulencë video 30 minutëshe me noter', 15.00, 'EUR', 30, 1, NOW(), NOW()),
('document_verification', 'Verifikim dokumentesh online', 'Verifikim i dokumenteve tuaja nga noteri', 10.00, 'EUR', NULL, 1, NOW(), NOW()),
('legal_advice', 'Këshillim ligjor me noter', 'Këshillim ligjor i specializuar me noter', 20.00, 'EUR', 45, 1, NOW(), NOW());