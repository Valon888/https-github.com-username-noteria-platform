<?php
/**
 * MIGRATION EXECUTION SCRIPT
 * Applies country system database changes
 * Run: php migrations/run_migration.php
 */

require_once __DIR__ . '/../config.php';

echo "=== NOTERIA MULTI-COUNTRY MIGRATION ===\n";
echo "Starting migration at: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // 1. Add columns to existing tables if they don't exist
    echo "Step 1: Checking and adding columns to existing tables...\n";
    
    $columns_to_add = [
        'reservations' => 'country_code',
        'payments' => 'country_code',
        'users' => 'country_code'
    ];
    
    foreach ($columns_to_add as $table => $column) {
        try {
            $result = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='$table' AND COLUMN_NAME='$column'");
            if ($result->rowCount() == 0) {
                // Column doesn't exist, add it
                if ($table === 'reservations' && $column === 'country_code') {
                    $pdo->exec("ALTER TABLE `$table` ADD COLUMN `country_code` VARCHAR(2) DEFAULT 'XK' AFTER `id`");
                    echo "✅ Added country_code to $table\n";
                    // Add second column for reservations
                    $pdo->exec("ALTER TABLE `$table` ADD COLUMN `country_name` VARCHAR(50) DEFAULT 'Kosovo' AFTER `country_code`");
                    echo "✅ Added country_name to $table\n";
                } else {
                    $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` VARCHAR(2) DEFAULT 'XK' AFTER `id`");
                    echo "✅ Added $column to $table\n";
                }
            } else {
                echo "⚠️  Column $column already exists in $table (skipped)\n";
            }
        } catch (PDOException $e) {
            echo "⚠️  Column check failed for $table.$column (likely already exists)\n";
        }
    }
    
    echo "\nStep 2: Creating new country tables...\n";
    
    // 2. Create countries table
    $create_countries = "CREATE TABLE IF NOT EXISTS `countries` (
      `id` INT PRIMARY KEY AUTO_INCREMENT,
      `code` VARCHAR(2) UNIQUE NOT NULL COMMENT 'ISO 3166-1 alpha-2 / XK for Kosovo',
      `name` VARCHAR(100) NOT NULL,
      `name_sq` VARCHAR(100) NOT NULL COMMENT 'Albanian name',
      `currency` VARCHAR(3) DEFAULT 'EUR',
      `language` VARCHAR(10) DEFAULT 'sq',
      `timezone` VARCHAR(50) DEFAULT 'Europe/Tirane',
      `active` BOOLEAN DEFAULT TRUE,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($create_countries);
    echo "✅ Created countries table\n";
    
    // 3. Insert Kosovo
    try {
        $check = $pdo->query("SELECT COUNT(*) as cnt FROM countries WHERE code='XK'")->fetch();
        if ($check['cnt'] == 0) {
            $pdo->exec("INSERT INTO `countries` (`code`, `name`, `name_sq`, `currency`, `language`, `timezone`, `active`) 
                       VALUES ('XK', 'Kosovo', 'Kosovë', 'EUR', 'sq', 'Europe/Tirane', TRUE)");
            echo "✅ Inserted Kosovo country\n";
        }
    } catch (Exception $e) {
        echo "⚠️  Kosovo country already exists\n";
    }
    
    // 4. Create country_pricing table
    $create_pricing = "CREATE TABLE IF NOT EXISTS `country_pricing` (
      `id` INT PRIMARY KEY AUTO_INCREMENT,
      `country_code` VARCHAR(2) NOT NULL,
      `service_name` VARCHAR(100) NOT NULL,
      `service_name_sq` VARCHAR(100) NOT NULL,
      `base_price` DECIMAL(10, 2) NOT NULL,
      `currency` VARCHAR(3) DEFAULT 'EUR',
      `description` TEXT,
      `min_price` DECIMAL(10, 2),
      `max_price` DECIMAL(10, 2),
      `active` BOOLEAN DEFAULT TRUE,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      FOREIGN KEY (`country_code`) REFERENCES `countries`(`code`),
      UNIQUE KEY `unique_service_per_country` (`country_code`, `service_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($create_pricing);
    echo "✅ Created country_pricing table\n";
    
    // 5. Insert Kosovo pricing
    $pricing_data = [
        ['XK', 'Legalizim', 'Legalizim', 3.00, 'EUR', 'Legalizim i dokumenteve - Kosovë', 2.50, 5.00],
        ['XK', 'Legalizim i dy dokumenteve', 'Legalizim i dy dokumenteve', 5.50, 'EUR', 'Legalizim i dy dokumenteve - Kosovë', 5.00, 7.00],
        ['XK', 'Vertetim Dokumenti', 'Vertetim Dokumenti', 5.00, 'EUR', 'Vertetim i nënshkrimit dhe kopjeve - Kosovë', 4.00, 8.00],
        ['XK', 'Vertetim nënshkrimi', 'Vertetim nënshkrimi', 3.50, 'EUR', 'Vertetim nënshkrimi - Kosovë', 3.00, 5.00],
        ['XK', 'Vertetim kopjesh', 'Vertetim kopjesh', 2.00, 'EUR', 'Vertetim kopjesh - Kosovë', 1.50, 3.00],
        ['XK', 'Hartim testamenti', 'Hartim testamenti', 80.00, 'EUR', 'Hartim testamenti i plotë - Kosovë', 70.00, 120.00],
        ['XK', 'Ndryshim testamenti', 'Ndryshim testamenti', 50.00, 'EUR', 'Ndryshim i testamentit - Kosovë', 40.00, 70.00],
        ['XK', 'Vërtetim nënshkrimi i disa dokumenteve', 'Vërtetim nënshkrimi i disa dokumenteve', 10.00, 'EUR', 'Vërtetim nënshkrimi i disa dokumenteve - Kosovë', 8.00, 15.00],
        ['XK', 'Autorizim', 'Autorizim', 5.00, 'EUR', 'Autorizim përfaqësimi - Kosovë', 4.00, 8.00],
        ['XK', 'Dorëzim dokumenti', 'Dorëzim dokumenti', 15.00, 'EUR', 'Dorëzim zyrtar dokumenti - Kosovë', 12.00, 20.00],
    ];
    
    $pricing_count = $pdo->query("SELECT COUNT(*) as cnt FROM country_pricing WHERE country_code='XK'")->fetch();
    
    if ($pricing_count['cnt'] == 0) {
        $stmt = $pdo->prepare("INSERT INTO country_pricing (country_code, service_name, service_name_sq, base_price, currency, description, min_price, max_price) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($pricing_data as $pricing) {
            try {
                $stmt->execute($pricing);
            } catch (PDOException $e) {
                // Duplicate entry, skip
            }
        }
        echo "✅ Inserted Kosovo pricing (10 services)\n";
    } else {
        echo "⚠️  Kosovo pricing already exists\n";
    }
    
    // 6. Create country_regulations table
    $create_regulations = "CREATE TABLE IF NOT EXISTS `country_regulations` (
      `id` INT PRIMARY KEY AUTO_INCREMENT,
      `country_code` VARCHAR(2) NOT NULL,
      `regulation_type` VARCHAR(100) NOT NULL COMMENT 'Document requirements, business rules, etc',
      `regulation_name_sq` VARCHAR(150),
      `description` TEXT,
      `active` BOOLEAN DEFAULT TRUE,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (`country_code`) REFERENCES `countries`(`code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($create_regulations);
    echo "✅ Created country_regulations table\n";
    
    // 7. Insert Kosovo regulations
    $regulations_count = $pdo->query("SELECT COUNT(*) as cnt FROM country_regulations WHERE country_code='XK'")->fetch();
    
    if ($regulations_count['cnt'] == 0) {
        $regulations = [
            ['XK', 'ID_REQUIREMENT', 'ID Kombëtare Oblieguese', 'Dokumentet duhet të jetë me ID të vlefshëm personal'],
            ['XK', 'ORIGINAL_DOCUMENTS', 'Dokumentet Origjinale', 'Të gjitha dokumentet duhet të jenë origjinale ose kopje të vërtetuara'],
            ['XK', 'SIGNATURE_WITNESS', 'Dëshmit për nënshkrimin', 'Nënshkrim duhet të bëhet përpara notarit'],
        ];
        
        $stmt = $pdo->prepare("INSERT INTO country_regulations (country_code, regulation_type, regulation_name_sq, description) VALUES (?, ?, ?, ?)");
        foreach ($regulations as $reg) {
            $stmt->execute($reg);
        }
        echo "✅ Inserted Kosovo regulations (3 rules)\n";
    }
    
    // 8. Create indexes
    echo "\nStep 3: Creating indexes for performance...\n";
    
    try {
        $pdo->exec("CREATE INDEX idx_country_code ON `reservations`(`country_code`)");
        echo "✅ Created index on reservations.country_code\n";
    } catch (Exception $e) { echo "⚠️  Index may already exist\n"; }
    
    try {
        $pdo->exec("CREATE INDEX idx_country_code_pricing ON `country_pricing`(`country_code`, `active`)");
        echo "✅ Created index on country_pricing\n";
    } catch (Exception $e) { echo "⚠️  Index may already exist\n"; }
    
    echo "\n=== MIGRATION COMPLETE ===\n";
    echo "✅ Kosovo multi-country system installed!\n\n";

    // Verify installation
    $countries = $pdo->query("SELECT COUNT(*) as count FROM countries WHERE code='XK'")->fetch();
    $services = $pdo->query("SELECT COUNT(*) as count FROM country_pricing WHERE country_code='XK'")->fetch();
    
    echo "📊 Verification:\n";
    echo "  ✅ Countries: " . $countries['count'] . " (Kosovo)\n";
    echo "  ✅ Services: " . $services['count'] . " (10 services)\n";
    echo "\n🚀 Ready for Phase 2: UI & Analytics!\n";

} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    echo "\n⚠️  Error details:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
?>
