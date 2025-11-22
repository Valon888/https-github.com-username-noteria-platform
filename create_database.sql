-- Create the noteria database
CREATE DATABASE IF NOT EXISTS `noteria` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Use the database
USE `noteria`;

-- Create zyrat table (notary offices)
CREATE TABLE IF NOT EXISTS `zyrat` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `emri` VARCHAR(255) NOT NULL COMMENT 'Office name',
  `qyteti` VARCHAR(100) NOT NULL COMMENT 'City',
  `shteti` VARCHAR(100) NOT NULL COMMENT 'Country',
  `email` VARCHAR(255) UNIQUE COMMENT 'Office email',
  `telefoni` VARCHAR(20) COMMENT 'Office phone',
  `adresa` TEXT COMMENT 'Office address',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `emri` (`emri`),
  INDEX `qyteti` (`qyteti`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create noteret table (notaries/users)
CREATE TABLE IF NOT EXISTS `noteret` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `emri` VARCHAR(100) NOT NULL,
  `mbiemri` VARCHAR(100) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `telefoni` VARCHAR(20),
  `zyra_id` INT(11),
  `password` VARCHAR(255) NOT NULL,
  `status` ENUM('aktiv', 'i_pavlefshem', 'i_fshire') DEFAULT 'aktiv',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email_noteret` (`email`),
  KEY `zyra_id` (`zyra_id`),
  CONSTRAINT `noteret_ibfk_1` FOREIGN KEY (`zyra_id`) REFERENCES `zyrat` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create users table (clients)
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `emri` VARCHAR(100) NOT NULL,
  `mbiemri` VARCHAR(100) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `telefoni` VARCHAR(20),
  `password` VARCHAR(255) NOT NULL,
  `status` ENUM('aktiv', 'i_pavlefshem', 'i_fshire') DEFAULT 'aktiv',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email_users` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create reservations table
CREATE TABLE IF NOT EXISTS `reservations` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `noter_id` INT(11),
  `zyra_id` INT(11),
  `service` VARCHAR(100) NOT NULL COMMENT 'Service type (e.g., document verification)',
  `date` DATE NOT NULL,
  `time` TIME NOT NULL,
  `status` ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
  `payment_status` ENUM('pending', 'paid', 'failed') DEFAULT 'pending',
  `amount` DECIMAL(10,2),
  `payment_method` VARCHAR(50),
  `document_path` VARCHAR(255),
  `notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `noter_id` (`noter_id`),
  KEY `zyra_id` (`zyra_id`),
  KEY `date` (`date`),
  CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reservations_ibfk_2` FOREIGN KEY (`noter_id`) REFERENCES `noteret` (`id`) ON DELETE SET NULL,
  CONSTRAINT `reservations_ibfk_3` FOREIGN KEY (`zyra_id`) REFERENCES `zyrat` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create abonimet table (subscriptions)
CREATE TABLE IF NOT EXISTS `abonimet` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `emri` VARCHAR(100) NOT NULL,
  `pershkrim` TEXT,
  `cmimi` DECIMAL(10,2) NOT NULL,
  `kohezgjatja` INT(11) COMMENT 'Duration in days',
  `features` JSON,
  `status` ENUM('aktiv', 'i_pavlefshem') DEFAULT 'aktiv',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create noteri_abonimet table (user subscriptions)
CREATE TABLE IF NOT EXISTS `noteri_abonimet` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `noter_id` INT(11) NOT NULL,
  `abonim_id` INT(11) NOT NULL,
  `data_fillimit` DATE NOT NULL,
  `data_mbarimit` DATE NOT NULL,
  `status` ENUM('aktiv', 'skaduar', 'pezulluar', 'anuluar') DEFAULT 'aktiv',
  `payment_method` VARCHAR(50),
  `renewal_status` ENUM('automatic', 'manual') DEFAULT 'automatic',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `noter_id` (`noter_id`),
  KEY `abonim_id` (`abonim_id`),
  CONSTRAINT `noteri_abonimet_ibfk_1` FOREIGN KEY (`noter_id`) REFERENCES `noteret` (`id`) ON DELETE CASCADE,
  CONSTRAINT `noteri_abonimet_ibfk_2` FOREIGN KEY (`abonim_id`) REFERENCES `abonimet` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create transaksionet table (transactions)
CREATE TABLE IF NOT EXISTS `transaksionet` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `abonim_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `lloji` VARCHAR(50) NOT NULL COMMENT 'Transaction type',
  `amount` DECIMAL(10,2) NOT NULL,
  `payment_date` DATETIME NOT NULL,
  `payment_status` ENUM('sukses','deshtuar','ne_pritje') NOT NULL DEFAULT 'ne_pritje',
  `payment_provider` VARCHAR(50),
  `transaction_id` VARCHAR(255),
  `reference_id` VARCHAR(255),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `abonim_id` (`abonim_id`),
  KEY `user_id` (`user_id`),
  KEY `payment_date` (`payment_date`),
  CONSTRAINT `transaksionet_ibfk_1` FOREIGN KEY (`abonim_id`) REFERENCES `noteri_abonimet` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transaksionet_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `noteret` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create payment_logs table
CREATE TABLE IF NOT EXISTS `payment_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `zyra_id` INT(11),
  `payment_id` VARCHAR(255),
  `amount` DECIMAL(10,2) NOT NULL,
  `payment_method` VARCHAR(50),
  `payment_status` ENUM('pending','completed','failed','refunded') DEFAULT 'pending',
  `description` VARCHAR(255),
  `log_data` TEXT,
  `ip_address` VARCHAR(45),
  `user_agent` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `zyra_id` (`zyra_id`),
  KEY `payment_id` (`payment_id`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create admin_login_attempts table (for rate limiting)
CREATE TABLE IF NOT EXISTS `admin_login_attempts` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(255) NOT NULL,
  `ip_address` VARCHAR(45),
  `attempt_time` DATETIME NOT NULL,
  `success` BOOLEAN DEFAULT FALSE,
  PRIMARY KEY (`id`),
  KEY `email` (`email`),
  KEY `attempt_time` (`attempt_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create api_rate_limits table
CREATE TABLE IF NOT EXISTS `api_rate_limits` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `api_key` VARCHAR(255) NOT NULL,
  `ip_address` VARCHAR(45),
  `request_count` INT(11) DEFAULT 0,
  `request_time` DATETIME,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `api_key_ip` (`api_key`, `ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create rate_limit table
CREATE TABLE IF NOT EXISTS `rate_limit` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `identifier` VARCHAR(255) NOT NULL,
  `requests` INT(11) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `identifier` (`identifier`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create video_calls table
CREATE TABLE IF NOT EXISTS `video_calls` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `room_id` VARCHAR(255) NOT NULL,
  `user_id` INT(11),
  `noter_id` INT(11),
  `status` ENUM('pending','active','completed','cancelled') DEFAULT 'pending',
  `start_time` DATETIME,
  `end_time` DATETIME,
  `duration` INT(11) COMMENT 'Duration in seconds',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `room_id_unique` (`room_id`),
  KEY `user_id` (`user_id`),
  KEY `noter_id` (`noter_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample subscriptions
INSERT INTO `abonimet` (`emri`, `cmimi`, `kohezgjatja`) VALUES
('Aboniment Bazik', 29.99, 30),
('Aboniment Pro', 49.99, 30),
('Aboniment Premium', 99.99, 30);
