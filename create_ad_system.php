<?php
require 'confidb.php';

echo "=== Creating Advertising System Tables ===\n\n";

// Krijo advertisers table
echo "Creating advertisers table...\n";
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS advertisers (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        company_name VARCHAR(255) NOT NULL,
        contact_person VARCHAR(100),
        email VARCHAR(255) UNIQUE,
        phone VARCHAR(20),
        website VARCHAR(255),
        logo_url VARCHAR(255),
        subscription_status ENUM('active', 'inactive', 'expired', 'pending') DEFAULT 'pending',
        subscription_start DATETIME,
        subscription_end DATETIME,
        budget DECIMAL(10,2) DEFAULT 0,
        budget_used DECIMAL(10,2) DEFAULT 0,
        impressions_limit INT(11) DEFAULT 0,
        impressions_count INT(11) DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL,
        INDEX (subscription_status),
        INDEX (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "  ✓ advertisers table created\n";
} catch (Exception $e) {
    echo "  ✗ Error: " . $e->getMessage() . "\n";
}

// Krijo advertisements table
echo "Creating advertisements table...\n";
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS advertisements (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        advertiser_id INT(11) NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        image_url VARCHAR(255),
        video_url VARCHAR(255),
        cta_text VARCHAR(100) DEFAULT 'Vizito',
        cta_url VARCHAR(255),
        ad_type ENUM('banner', 'video', 'popup', 'native', 'sidebar') DEFAULT 'banner',
        status ENUM('draft', 'active', 'paused', 'expired', 'rejected') DEFAULT 'draft',
        start_date DATETIME,
        end_date DATETIME,
        daily_budget DECIMAL(10,2) DEFAULT 0,
        cost_per_impression DECIMAL(10,4) DEFAULT 0.01,
        cost_per_click DECIMAL(10,4) DEFAULT 0.05,
        total_impressions INT(11) DEFAULT 0,
        total_clicks INT(11) DEFAULT 0,
        total_cost DECIMAL(10,2) DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL,
        FOREIGN KEY (advertiser_id) REFERENCES advertisers(id) ON DELETE CASCADE,
        INDEX (advertiser_id),
        INDEX (status),
        INDEX (ad_type),
        INDEX (start_date),
        INDEX (end_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "  ✓ advertisements table created\n";
} catch (Exception $e) {
    echo "  ✗ Error: " . $e->getMessage() . "\n";
}

// Krijo ad_placements table
echo "Creating ad_placements table...\n";
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS ad_placements (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        ad_id INT(11) NOT NULL,
        placement_location VARCHAR(100) NOT NULL,
        placement_type VARCHAR(50),
        target_role ENUM('admin', 'notary', 'user', 'all') DEFAULT 'all',
        enabled TINYINT(1) DEFAULT 1,
        order_priority INT(11) DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (ad_id) REFERENCES advertisements(id) ON DELETE CASCADE,
        INDEX (placement_location),
        INDEX (target_role),
        INDEX (enabled),
        UNIQUE KEY unique_placement (ad_id, placement_location)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "  ✓ ad_placements table created\n";
} catch (Exception $e) {
    echo "  ✗ Error: " . $e->getMessage() . "\n";
}

// Krijo ad_impressions table
echo "Creating ad_impressions table...\n";
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS ad_impressions (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        ad_id INT(11) NOT NULL,
        user_id INT(11),
        placement_location VARCHAR(100),
        ip_address VARCHAR(50),
        user_agent VARCHAR(255),
        click_through TINYINT(1) DEFAULT 0,
        impression_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        click_time DATETIME NULL,
        FOREIGN KEY (ad_id) REFERENCES advertisements(id) ON DELETE CASCADE,
        INDEX (ad_id),
        INDEX (user_id),
        INDEX (impression_time),
        INDEX (click_through)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "  ✓ ad_impressions table created\n";
} catch (Exception $e) {
    echo "  ✗ Error: " . $e->getMessage() . "\n";
}

// Krijo ad_payments table
echo "Creating ad_payments table...\n";
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS ad_payments (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        advertiser_id INT(11) NOT NULL,
        invoice_number VARCHAR(100) UNIQUE,
        amount DECIMAL(10,2) NOT NULL,
        payment_method VARCHAR(50),
        status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
        period_start DATETIME,
        period_end DATETIME,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        paid_at DATETIME NULL,
        FOREIGN KEY (advertiser_id) REFERENCES advertisers(id) ON DELETE CASCADE,
        INDEX (advertiser_id),
        INDEX (status),
        INDEX (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "  ✓ ad_payments table created\n";
} catch (Exception $e) {
    echo "  ✗ Error: " . $e->getMessage() . "\n";
}

echo "\n=== All advertising tables created ===\n";

// Verify tables
echo "\nVerifying tables:\n";
$tables = ['advertisers', 'advertisements', 'ad_placements', 'ad_impressions', 'ad_payments'];
foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
        $count = $stmt->fetchColumn();
        echo "  ✓ $table\n";
    } catch (Exception $e) {
        echo "  ✗ $table\n";
    }
}
