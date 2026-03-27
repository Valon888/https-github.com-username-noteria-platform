<?php
/**
 * Setup script for Advertising System Database Tables
 * Run this once to create all necessary advertising tables
 * Access: http://localhost/noteria/setup_ads_database.php
 */

require_once 'confidb.php';

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
    
    // Create advertisers table
    $pdo->exec("CREATE TABLE IF NOT EXISTS advertisers (
        id INT PRIMARY KEY AUTO_INCREMENT,
        company_name VARCHAR(255) NOT NULL UNIQUE,
        contact_email VARCHAR(255) NOT NULL,
        contact_phone VARCHAR(20),
        logo_url VARCHAR(500),
        website_url VARCHAR(500),
        subscription_status ENUM('active', 'inactive', 'expired') DEFAULT 'active',
        subscription_start DATE,
        subscription_end DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // Create advertisements table
    $pdo->exec("CREATE TABLE IF NOT EXISTS advertisements (
        id INT PRIMARY KEY AUTO_INCREMENT,
        advertiser_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        image_url VARCHAR(500),
        cta_url VARCHAR(500) NOT NULL,
        ad_type ENUM('banner', 'card', 'modal') DEFAULT 'card',
        status ENUM('active', 'paused', 'draft') DEFAULT 'draft',
        start_date DATE NOT NULL,
        end_date DATE,
        total_impressions INT DEFAULT 0,
        total_clicks INT DEFAULT 0,
        budget_monthly DECIMAL(10, 2),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (advertiser_id) REFERENCES advertisers(id) ON DELETE CASCADE,
        INDEX (status),
        INDEX (start_date),
        INDEX (end_date)
    )");
    
    // Create ad_placements table
    $pdo->exec("CREATE TABLE IF NOT EXISTS ad_placements (
        id INT PRIMARY KEY AUTO_INCREMENT,
        ad_id INT NOT NULL,
        placement_location VARCHAR(100) NOT NULL,
        target_role ENUM('all', 'user', 'noter', 'admin') DEFAULT 'all',
        order_priority INT DEFAULT 0,
        enabled TINYINT DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (ad_id) REFERENCES advertisements(id) ON DELETE CASCADE,
        KEY (placement_location),
        KEY (enabled)
    )");
    
    // Create ad_impressions table
    $pdo->exec("CREATE TABLE IF NOT EXISTS ad_impressions (
        id INT PRIMARY KEY AUTO_INCREMENT,
        ad_id INT NOT NULL,
        user_id INT,
        placement_location VARCHAR(100),
        ip_address VARCHAR(45),
        user_agent VARCHAR(500),
        click_through TINYINT DEFAULT 0,
        click_time DATETIME,
        impression_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (ad_id) REFERENCES advertisements(id) ON DELETE CASCADE,
        KEY (ad_id),
        KEY (user_id),
        KEY (impression_time)
    )");
    
    echo "<div style='background:#e8f5e9;color:#2e7d32;padding:20px;border-radius:8px;margin:20px;font-weight:600;font-size:1.1rem;'>";
    echo "✅ Tabelat e reklamave u krijuan me sukses!<br>";
    echo "<small style='font-weight:400;margin-top:10px;display:block;'>";
    echo "Tabelat e krijuara: advertisers, advertisements, ad_placements, ad_impressions<br>";
    echo "Tani mund të hyni në <a href='admin_ads.php' style='color:#1565c0;text-decoration:underline;'>admin_ads.php</a> për të menaxhuar reklamat.";
    echo "</small>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div style='background:#ffebee;color:#c62828;padding:20px;border-radius:8px;margin:20px;font-weight:600;'>";
    echo "❌ Gabim: " . htmlspecialchars($e->getMessage());
    echo "</div>";
}
?>
