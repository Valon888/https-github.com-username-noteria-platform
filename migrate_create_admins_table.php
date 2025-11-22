<?php
/**
 * Migration: Create admins table
 * Krijo tabelën admins për ruajtjen e administratorëve me fjalëkalime të hashuar
 * 
 * Përdorimi: php migrate_create_admins_table.php
 */

require_once 'confidb.php';

try {
    // ==========================================
    // KRIJO TABELËN ADMINS
    // ==========================================
    $sql = "
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
    ";
    
    $pdo->exec($sql);
    echo "✅ Tabela 'admins' u krijua me sukses!\n";
    
    // ==========================================
    // SHTO ADMIN-ET FILLESTARË
    // ==========================================
    
    // Kontrollo nëse ekzistojnë admin-ë
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM admins");
    $result = $stmt->fetch();
    
    if ($result['count'] == 0) {
        echo "\n📝 Po shtojmë admin-ët fillestarë...\n";
        
        // Gjenero passwords (KËTO DUHET TË NDRYSHOHEN NGA ADMINISTRATORI!)
        $admins_to_insert = [
            [
                'email' => 'admin@noteria.com',
                'password' => 'Noteria@Admin#2025',  // DUHET TË NDRYSHOHET!
                'emri' => 'Admin',
                'mbiemri' => 'System',
                'roli' => 'super_admin'
            ],
            [
                'email' => 'developer@noteria.com',
                'password' => 'Dev@Noteria#2025',  // DUHET TË NDRYSHOHET!
                'emri' => 'Developer',
                'mbiemri' => 'Panel',
                'roli' => 'developer'
            ],
            [
                'email' => 'support@noteria.com',
                'password' => 'Support@Noteria#2025',  // DUHET TË NDRYSHOHET!
                'emri' => 'Support',
                'mbiemri' => 'Team',
                'roli' => 'admin'
            ]
        ];
        
        foreach ($admins_to_insert as $admin) {
            $password_hash = password_hash($admin['password'], PASSWORD_DEFAULT);
            
            $insert_stmt = $pdo->prepare("
                INSERT INTO admins (email, password, emri, mbiemri, roli, status)
                VALUES (?, ?, ?, ?, ?, 'active')
            ");
            
            $insert_stmt->execute([
                $admin['email'],
                $password_hash,
                $admin['emri'],
                $admin['mbiemri'],
                $admin['roli']
            ]);
            
            echo "  ✓ {$admin['email']} - Fjalëkalimi: {$admin['password']}\n";
        }
        
        echo "\n⚠️  SHUMË I RËNDËSISHËM:\n";
        echo "1. Shëno këto fjalëkalime në vend të sigurt\n";
        echo "2. Ndrysho fjalëkalimet menjëherë në login\n";
        echo "3. Fshi këtë fajll pasi të përfundoj migrimin\n";
        echo "4. Mos i commit-o këta fjalëkalime në version control!\n\n";
        
    } else {
        echo "ℹ️  Tabela 'admins' përmban tashmë {$result['count']} admin-ë.\n";
        echo "   Nuk do të shtohen admin-ë të rinj.\n\n";
    }
    
    // ==========================================
    // SHTO KOLONA ADMIN_ID NË USERS (NËSE NUK EKZISTON)
    // ==========================================
    
    // Kontrollo nëse tabela users ka kolona për admin references
    $columns = $pdo->query("DESCRIBE users")->fetchAll(PDO::FETCH_COLUMN, 0);
    
    if (!in_array('created_by_admin', $columns)) {
        $alter = "ALTER TABLE users ADD COLUMN created_by_admin INT UNSIGNED AFTER created_at";
        $pdo->exec($alter);
        echo "✅ Kolona 'created_by_admin' u shtua në tabelën 'users'\n";
    } else {
        echo "ℹ️  Kolona 'created_by_admin' ekziston tashmë\n";
    }
    
    echo "\n✨ Migration-i përfundoi me sukses!\n";
    
} catch (Exception $e) {
    echo "❌ Gabim gjatë migration-it: " . $e->getMessage() . "\n";
    exit(1);
}
?>
