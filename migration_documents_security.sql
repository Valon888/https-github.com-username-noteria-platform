-- Migration: Update documents table and create download logs
-- Përditëso tabelën documents dhe krijo logging table

-- ==========================================
-- PËRDITËSO TABELËN DOCUMENTS
-- ==========================================

-- Shto kolona nëse nuk ekzistojnë
ALTER TABLE documents ADD file_size INT;
ALTER TABLE documents ADD file_type VARCHAR(255);
ALTER TABLE documents ADD created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE documents ADD updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Shto indexes për performance
ALTER TABLE documents ADD INDEX idx_user_id (user_id);
ALTER TABLE documents ADD INDEX idx_created_at (created_at);

-- ==========================================
-- KRIJO TABELËN DOCUMENT_DOWNLOAD_LOGS
-- ==========================================
CREATE TABLE IF NOT EXISTS document_download_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    user_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    downloaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    INDEX idx_document_id (document_id),
    INDEX idx_user_id (user_id),
    INDEX idx_downloaded_at (downloaded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
