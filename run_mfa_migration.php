<?php
/**
 * Migration: Create MFA tables
 * Krijo tabelat e nevojshme për Multi-Factor Authentication
 */

require_once 'confidb.php';

try {
    // Krijo tabelën user_mfa
    $sql1 = "
    CREATE TABLE IF NOT EXISTS user_mfa (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        secret VARCHAR(32) NOT NULL,
        backup_codes TEXT,
        is_verified TINYINT(1) DEFAULT 0,
        verified_at DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        UNIQUE KEY unique_user_id (user_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_is_verified (is_verified)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $pdo->exec($sql1);
    echo "✅ Tabela 'user_mfa' u krijua me sukses!\n";
    
    // Krijo tabelën admin_mfa
    $sql2 = "
    CREATE TABLE IF NOT EXISTS admin_mfa (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        admin_id INT UNSIGNED NOT NULL,
        secret VARCHAR(32) NOT NULL,
        backup_codes TEXT,
        is_verified TINYINT(1) DEFAULT 1,
        verified_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        UNIQUE KEY unique_admin_id (admin_id),
        FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE,
        INDEX idx_is_verified (is_verified)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $pdo->exec($sql2);
    echo "✅ Tabela 'admin_mfa' u krijua me sukses!\n";
    
    echo "\n✨ Migration-i përfundoi me sukses!\n";
    
} catch (PDOException $e) {
    echo "❌ Gabim në migrimin e MFA: " . $e->getMessage() . "\n";
    exit(1);
}
