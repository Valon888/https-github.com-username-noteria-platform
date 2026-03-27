<?php
require_once 'confidb.php';

try {
    // 1. Drop tables if they exist (to ensure clean slate)
    $pdo->exec("DROP TABLE IF EXISTS `ad_interactions`");
    $pdo->exec("DROP TABLE IF EXISTS `ads`");
    echo "Tabelat e vjetra u fshinë (nëse ekzistonin).<br>";

    // 2. Create 'ads' table with CORRECT columns for admin_advertisements.php
    $sqlAds = "CREATE TABLE `ads` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `business_name` varchar(255) NOT NULL,
      `business_email` varchar(255) DEFAULT NULL,
      `business_contact` varchar(50) DEFAULT NULL,
      `ad_title` varchar(255) NOT NULL,
      `ad_description` text,
      `ad_link` varchar(255) NOT NULL,
      `ad_type` varchar(50) DEFAULT 'card',
      `video_url` varchar(255) DEFAULT NULL,
      `image_url` varchar(255) DEFAULT NULL,
      `impressions` int(11) DEFAULT 0,
      `clicks` int(11) DEFAULT 0,
      `is_active` tinyint(1) DEFAULT 1,
      `start_date` datetime DEFAULT NULL,
      `end_date` datetime DEFAULT NULL,
      `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $pdo->exec($sqlAds);
    echo "Tabela 'ads' u krijua me sukses (me kolonat 'is_active' dhe 'impressions').<br>";

    // 3. Create 'ad_interactions' table
    $sqlInteractions = "CREATE TABLE `ad_interactions` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `ad_id` int(11) NOT NULL,
      `interaction_type` enum('view', 'click') NOT NULL,
      `user_ip` varchar(45) DEFAULT NULL,
      `user_agent` text,
      `referer` text,
      `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `ad_id` (`ad_id`),
      CONSTRAINT `fk_ad_interactions_ad` FOREIGN KEY (`ad_id`) REFERENCES `ads` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $pdo->exec($sqlInteractions);
    echo "Tabela 'ad_interactions' u krijua me sukses.<br>";

    echo "<br><strong>Procesi përfundoi! Tani mund të përdorni admin_advertisements.php</strong>";

} catch (PDOException $e) {
    echo "Gabim: " . $e->getMessage();
}
?>