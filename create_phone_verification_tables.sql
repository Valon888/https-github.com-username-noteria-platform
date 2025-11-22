-- Tabela për verifikimin e telefonave - Sistemi 3-minutësh
-- filepath: d:\xampp\htdocs\noteria\create_phone_verification_tables.sql

-- Tabela për kodet e verifikimit të telefonave
CREATE TABLE IF NOT EXISTS phone_verification_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone_number VARCHAR(20) NOT NULL,
    verification_code VARCHAR(10) NOT NULL,
    transaction_id VARCHAR(100),
    expires_at TIMESTAMP NOT NULL,
    attempts INT DEFAULT 0,
    is_verified BOOLEAN DEFAULT FALSE,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_phone_transaction (phone_number, transaction_id),
    INDEX idx_expires_at (expires_at),
    INDEX idx_verification_code (verification_code),
    UNIQUE KEY unique_phone_transaction (phone_number, transaction_id)
);

-- Tabela për logjet e verifikimit të telefonave
CREATE TABLE IF NOT EXISTS phone_verification_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone_number VARCHAR(20) NOT NULL,
    action ENUM('code_sent', 'verified_success', 'wrong_code', 'expired', 'max_attempts', 'error') NOT NULL,
    transaction_id VARCHAR(100),
    verification_code VARCHAR(10),
    error_message TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_phone_number (phone_number),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at),
    INDEX idx_transaction_id (transaction_id)
);

-- Tabela për konfigurimin e provider-ëve SMS
CREATE TABLE IF NOT EXISTS sms_provider_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider_name VARCHAR(50) NOT NULL UNIQUE,
    api_endpoint VARCHAR(500) NOT NULL,
    api_key_encrypted TEXT NOT NULL,
    sender_name VARCHAR(20) DEFAULT 'NOTERIA',
    is_active BOOLEAN DEFAULT TRUE,
    priority_order INT DEFAULT 1,
    timeout_seconds INT DEFAULT 30,
    rate_limit_per_minute INT DEFAULT 60,
    success_rate DECIMAL(5,2) DEFAULT 100.00,
    last_used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_priority (priority_order),
    INDEX idx_active (is_active)
);

-- Tabela për statistikat e SMS-ve
CREATE TABLE IF NOT EXISTS sms_statistics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider_name VARCHAR(50) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    message_type ENUM('verification', 'confirmation', 'notification') NOT NULL,
    status ENUM('sent', 'delivered', 'failed', 'pending') NOT NULL,
    cost_cents INT DEFAULT 0,
    delivery_time_seconds INT,
    error_code VARCHAR(20),
    error_message TEXT,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    delivered_at TIMESTAMP NULL,
    
    INDEX idx_provider (provider_name),
    INDEX idx_status (status),
    INDEX idx_sent_at (sent_at),
    INDEX idx_phone_number (phone_number)
);

-- Tabela për blacklist të numrave të telefonit
CREATE TABLE IF NOT EXISTS phone_blacklist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone_number VARCHAR(20) NOT NULL UNIQUE,
    reason ENUM('spam', 'fraud', 'abuse', 'invalid', 'opt_out') NOT NULL,
    added_by VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    
    INDEX idx_phone_number (phone_number),
    INDEX idx_reason (reason),
    INDEX idx_expires_at (expires_at)
);

-- Shtimi i konfigurimit për provider-ët e SMS-ve
INSERT INTO sms_provider_config (provider_name, api_endpoint, api_key_encrypted, sender_name, priority_order) VALUES
('ipko', 'https://sms.ipko.com/api/send', 'ENCRYPTED_IPKO_KEY_HERE', 'NOTERIA', 1),
('infobip', 'https://api.infobip.com/sms/2/text/advanced', 'ENCRYPTED_INFOBIP_KEY_HERE', 'Noteria', 2),
('twilio', 'https://api.twilio.com/2010-04-01/Accounts/', 'ENCRYPTED_TWILIO_KEY_HERE', 'Noteria', 3)
ON DUPLICATE KEY UPDATE 
    api_endpoint = VALUES(api_endpoint),
    priority_order = VALUES(priority_order);

-- Shtimi i indexeve për performance të mirë
CREATE INDEX IF NOT EXISTS idx_phone_verification_created_at ON phone_verification_codes(created_at);
CREATE INDEX IF NOT EXISTS idx_phone_verification_status ON phone_verification_codes(is_verified, expires_at);
CREATE INDEX IF NOT EXISTS idx_phone_logs_date ON phone_verification_logs(DATE(created_at));

-- Trigger për pastrimin automatik të kodeve të skaduara
DELIMITER $$
CREATE EVENT IF NOT EXISTS cleanup_expired_phone_codes
ON SCHEDULE EVERY 30 MINUTE
DO
BEGIN
    -- Fshij kodet e skaduara më të vjetra se 1 orë
    DELETE FROM phone_verification_codes 
    WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 HOUR);
    
    -- Fshij logjet më të vjetra se 30 ditë
    DELETE FROM phone_verification_logs 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
    
    -- Fshij statistikat më të vjetra se 90 ditë
    DELETE FROM sms_statistics 
    WHERE sent_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
END$$
DELIMITER ;

-- Aktivizo event scheduler nëse nuk është aktiv
-- SET GLOBAL event_scheduler = ON;