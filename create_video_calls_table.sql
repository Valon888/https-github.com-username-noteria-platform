-- SQL to create video_calls table for Noteria
-- Run this in your MySQL/MariaDB console or via phpMyAdmin

CREATE TABLE IF NOT EXISTS `video_calls` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `call_id` VARCHAR(100) NOT NULL UNIQUE,
  `room` VARCHAR(255) DEFAULT NULL,
  `user_id` VARCHAR(100) DEFAULT NULL,
  `start_time` DATETIME DEFAULT NULL,
  `end_time` DATETIME DEFAULT NULL,
  `status` VARCHAR(50) DEFAULT 'scheduled',
  `metadata` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX (`room`),
  INDEX (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
