-- SQL script për krijimin e tabelave të sistemit të verifikimit të pagesave
-- Ekzekutoni këto komanda në bazën tuaj të të dhënave

-- Tabela për log-un e pagesave
CREATE TABLE IF NOT EXISTS payment_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    office_email VARCHAR(255) NOT NULL,
    transaction_id VARCHAR(100) NOT NULL UNIQUE,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('bank_transfer', 'paypal', 'card') DEFAULT 'bank_transfer',
    status ENUM('pending', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    verification_attempts INT DEFAULT 0,
    api_response TEXT,
    payment_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    verified_at TIMESTAMP NULL,
    
    INDEX idx_email (office_email),
    INDEX idx_transaction (transaction_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
);

-- Shtimi i kolonave të reja në tabelën ekzistuese të zyrave
ALTER TABLE zyrat 
ADD COLUMN IF NOT EXISTS transaction_id VARCHAR(100) NULL,
ADD COLUMN IF NOT EXISTS payment_method ENUM('bank_transfer', 'paypal', 'card') DEFAULT 'bank_transfer',
ADD COLUMN IF NOT EXISTS payment_verified BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS payment_proof_path VARCHAR(500) NULL,
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Shtimi i indekseve për performancë të mirë
ALTER TABLE zyrat 
ADD INDEX IF NOT EXISTS idx_transaction_id (transaction_id),
ADD INDEX IF NOT EXISTS idx_payment_verified (payment_verified),
ADD INDEX IF NOT EXISTS idx_email (email);

-- Tabela për të dhënat e verifikimit të bankave
CREATE TABLE IF NOT EXISTS bank_verification_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bank_name VARCHAR(100) NOT NULL,
    api_endpoint VARCHAR(500) NOT NULL,
    api_key_encrypted TEXT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    verification_method ENUM('api', 'manual', 'webhook') DEFAULT 'api',
    timeout_seconds INT DEFAULT 30,
    retry_attempts INT DEFAULT 3,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_bank (bank_name)
);

-- Futja e konfigurimit të bankave të Kosovës
INSERT INTO bank_verification_config (bank_name, api_endpoint, api_key_encrypted, verification_method) VALUES
('Banka Ekonomike', 'https://api.bek.com.mk/verify', 'ENCRYPTED_KEY_HERE', 'api'),
('Banka për Biznes', 'https://api.bpb-bank.com/verify', 'ENCRYPTED_KEY_HERE', 'api'),
('Banka Kombëtare Tregtare (BKT)', 'https://api.bkt.com.mk/verify', 'ENCRYPTED_KEY_HERE', 'api'),
('ProCredit Bank', 'https://api.procreditbank.com.mk/verify', 'ENCRYPTED_KEY_HERE', 'api'),
('Raiffeisen Bank', 'https://api.raiffeisen.mk/verify', 'ENCRYPTED_KEY_HERE', 'api'),
('TEB Bank', 'https://api.tebbank.com/verify', 'ENCRYPTED_KEY_HERE', 'api');

-- Tabela për auditimin e aktiviteteve
CREATE TABLE IF NOT EXISTS payment_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id VARCHAR(100) NOT NULL,
    action ENUM('created', 'verified', 'failed', 'cancelled', 'refunded') NOT NULL,
    user_ip VARCHAR(45),
    user_agent TEXT,
    additional_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_transaction (transaction_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at)
);

-- Tabela për konfigurimin e sigurisë
CREATE TABLE IF NOT EXISTS security_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_name VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    description TEXT,
    is_encrypted BOOLEAN DEFAULT FALSE,
    updated_by VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Futja e konfigurimit të sigurisë
INSERT INTO security_settings (setting_name, setting_value, description) VALUES
('max_daily_transactions_per_email', '5', 'Numri maksimal i transaksioneve për email në ditë'),
('min_payment_amount', '10', 'Shuma minimale e pagesës në Euro'),
('max_payment_amount', '10000', 'Shuma maksimale e pagesës në Euro'),
('payment_verification_timeout', '300', 'Koha e timeout për verifikim në sekonda'),
('max_file_upload_size', '5242880', 'Madhësia maksimale e file në bytes (5MB)'),
('allowed_file_types', 'pdf,jpg,jpeg,png', 'Tipet e lejuara të file-ave'),
('require_payment_proof', 'true', 'A është e detyrueshme dëshmi e pagesës'),
('enable_duplicate_check', 'true', 'A kontrollohen pagesat duplikate'),
('duplicate_check_hours', '24', 'Orët për kontroll të duplikateve');

-- View për raporte të pagesave
CREATE VIEW payment_summary AS
SELECT 
    DATE(pl.created_at) as payment_date,
    pl.payment_method,
    pl.status,
    COUNT(*) as transaction_count,
    SUM(pl.amount) as total_amount,
    AVG(pl.amount) as average_amount,
    MIN(pl.amount) as min_amount,
    MAX(pl.amount) as max_amount
FROM payment_logs pl
GROUP BY DATE(pl.created_at), pl.payment_method, pl.status
ORDER BY payment_date DESC, pl.payment_method;

-- View për transaksionet e dyshimta
CREATE VIEW suspicious_transactions AS
SELECT 
    pl.*,
    z.emri as office_name,
    COUNT(*) OVER (PARTITION BY pl.office_email, DATE(pl.created_at)) as daily_count,
    LAG(pl.created_at) OVER (PARTITION BY pl.office_email ORDER BY pl.created_at) as previous_transaction
FROM payment_logs pl
LEFT JOIN zyrat z ON pl.office_email = z.email
WHERE pl.verification_attempts > 3 
   OR pl.amount > 5000 
   OR pl.status = 'failed'
ORDER BY pl.created_at DESC;

-- Trigger për auditim automatik
DELIMITER //
CREATE TRIGGER payment_audit_trigger 
AFTER INSERT ON payment_logs
FOR EACH ROW
BEGIN
    INSERT INTO payment_audit_log (transaction_id, action, additional_data)
    VALUES (NEW.transaction_id, 'created', JSON_OBJECT('amount', NEW.amount, 'method', NEW.payment_method));
END//

CREATE TRIGGER payment_verification_audit 
AFTER UPDATE ON payment_logs
FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO payment_audit_log (transaction_id, action, additional_data)
        VALUES (NEW.transaction_id, NEW.status, 
                JSON_OBJECT('old_status', OLD.status, 'new_status', NEW.status, 'attempts', NEW.verification_attempts));
    END IF;
END//
DELIMITER ;