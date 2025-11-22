-- Migration: Create admins table
-- Krijo tabelën admins për ruajtjen e administratorëve me fjalëkalime të hashuar

-- ==========================================
-- KRIJO TABELËN ADMINS
-- ==========================================
CREATE TABLE IF NOT EXISTS admins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    emri VARCHAR(100) NOT NULL,
    mbiemri VARCHAR(100),
    telefoni VARCHAR(20),
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    roli ENUM('super_admin', 'admin', 'developer', 'moderator') DEFAULT 'admin',
    last_login DATETIME,
    login_ip VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT UNSIGNED,
    updated_by INT UNSIGNED,
    notes TEXT,
    
    INDEX idx_email (email),
    INDEX idx_status (status),
    INDEX idx_roli (roli),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- SHTO ADMIN-ET FILLESTARË (PASSWORD-AT JANË HASHAT PER admin123, dev123, support123)
-- ==========================================

-- Super Admin: admin@noteria.com / admin123
-- Password hash: $2y$10$ZC1sYGG2zAV1VFCu4iGKQuGXo/RhARRXVHvA8jG7XvHJh3W4Ox0Jm
INSERT IGNORE INTO admins (email, password, emri, mbiemri, roli, status)
VALUES ('admin@noteria.com', '$2y$10$ZC1sYGG2zAV1VFCu4iGKQuGXo/RhARRXVHvA8jG7XvHJh3W4Ox0Jm', 'Admin', 'System', 'super_admin', 'active');

-- Developer: developer@noteria.com / dev123  
-- Password hash: $2y$10$xKrVmGVADFg0g3K5L0m8.eW3cVVAMVZPj7VW5V2pBYvOXF2h6a8Iy
INSERT IGNORE INTO admins (email, password, emri, mbiemri, roli, status)
VALUES ('developer@noteria.com', '$2y$10$xKrVmGVADFg0g3K5L0m8.eW3cVVAMVZPj7VW5V2pBYvOXF2h6a8Iy', 'Developer', 'Panel', 'developer', 'active');

-- Support: support@noteria.com / support123
-- Password hash: $2y$10$IlBmPx8tJvMdJ7pF9dW2fOdJr5qP3E8C1lN4oL2mK6B9sH3cGq7nK
INSERT IGNORE INTO admins (email, password, emri, mbiemri, roli, status)
VALUES ('support@noteria.com', '$2y$10$IlBmPx8tJvMdJ7pF9dW2fOdJr5qP3E8C1lN4oL2mK6B9sH3cGq7nK', 'Support', 'Team', 'admin', 'active');
