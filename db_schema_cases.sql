-- Tabela: cases (lëndët)
CREATE TABLE IF NOT EXISTS cases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_number VARCHAR(50) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    client_id INT,
    opened_by INT,
    status ENUM('hapur','mbyllur','në proces','anuluar') DEFAULT 'hapur',
    opened_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    closed_at DATETIME,
    last_modified DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    archived TINYINT(1) DEFAULT 0,
    FOREIGN KEY (client_id) REFERENCES users(id),
    FOREIGN KEY (opened_by) REFERENCES users(id)
);

-- Tabela: case_documents (dokumentet e lëndës)
CREATE TABLE IF NOT EXISTS case_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    uploaded_by INT,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    doc_type VARCHAR(100),
    version INT DEFAULT 1,
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (case_id) REFERENCES cases(id),
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
);

-- Tabela: case_audit_trail (auditimi i veprimeve në lëndë)
CREATE TABLE IF NOT EXISTS case_audit_trail (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    action_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);
