<?php
/**
 * Create admins table in Noteria database
 * Table contains administrator data with strong encryption
 */

require_once 'config.php';

try {
    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'admins'");
    
    if ($stmt->rowCount() > 0) {
        echo "[OK] Table 'admins' already exists in Noteria database.\n";
        
        // Display table structure
        echo "\nCurrent structure of 'admins' table:\n";
        $columns = $pdo->query("DESCRIBE admins")->fetchAll();
        foreach ($columns as $col) {
            echo "  - {$col['Field']}: {$col['Type']}\n";
        }
        exit(0);
    }
    
    // If table doesn't exist, create it
    echo "[PENDING] Creating admins table...\n";
    
    $sql = "CREATE TABLE IF NOT EXISTS admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        emri VARCHAR(100) NOT NULL,
        status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
        role ENUM('super_admin', 'admin', 'moderator') DEFAULT 'admin',
        phone VARCHAR(20),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        last_login TIMESTAMP NULL,
        is_2fa_enabled BOOLEAN DEFAULT FALSE,
        INDEX idx_email (email),
        INDEX idx_status (status),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
    
    echo "[SUCCESS] Table admins created successfully!\n\n";
    
    // Display new structure
    echo "Structure of admins table:\n";
    $columns = $pdo->query("DESCRIBE admins")->fetchAll();
    foreach ($columns as $col) {
        echo "  - {$col['Field']}: {$col['Type']}\n";
    }
    
    echo "\n[OK] Table admins is ready for use!\n";
    
    echo "\nExample of inserting an administrator:\n";
    echo "<?php\n";
    echo "  \$email = 'admin@noteria.al';\n";
    echo "  \$password = password_hash('password123', PASSWORD_BCRYPT);\n";
    echo "  \$emri = 'Admin Administrator';\n";
    echo "\n";
    echo "  \$stmt = \$pdo->prepare(\"INSERT INTO admins (email, password, emri, status, role) VALUES (?, ?, ?, 'active', 'super_admin')\");\n";
    echo "  \$stmt->execute([\$email, \$password, \$emri]);\n";
    echo "  echo 'Administrator added with ID: ' . \$pdo->lastInsertId();\n";
    echo "?>\n";
    
} catch (PDOException $e) {
    echo "[ERROR] Database error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "[ERROR] Error: " . $e->getMessage() . "\n";
    exit(1);
}
