-- =========================================
-- NOTERIA - MULTI-COUNTRY MIGRATION
-- Kosovo Setup (XK - Kosovë)
-- Created: 2026-03-12
-- =========================================

-- 1. Add country columns to existing tables (handled by PHP script)
-- Columns will be checked in run_migration.php before adding

-- 2. Create countries table
CREATE TABLE IF NOT EXISTS `countries` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `code` VARCHAR(2) UNIQUE NOT NULL COMMENT 'ISO 3166-1 alpha-2 / XK for Kosovo',
  `name` VARCHAR(100) NOT NULL,
  `name_sq` VARCHAR(100) NOT NULL COMMENT 'Albanian name',
  `currency` VARCHAR(3) DEFAULT 'EUR',
  `language` VARCHAR(10) DEFAULT 'sq',
  `timezone` VARCHAR(50) DEFAULT 'Europe/Tirane',
  `active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Insert Kosovo
INSERT INTO `countries` (`code`, `name`, `name_sq`, `currency`, `language`, `timezone`, `active`)
VALUES ('XK', 'Kosovo', 'Kosovë', 'EUR', 'sq', 'Europe/Tirane', TRUE);

-- 4. Create pricing table (per country)
CREATE TABLE IF NOT EXISTS `country_pricing` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Kosovo Pricing - Standard Services
INSERT INTO `country_pricing` (`country_code`, `service_name`, `service_name_sq`, `base_price`, `currency`, `description`, `min_price`, `max_price`)
VALUES 
-- Legalization Services
('XK', 'Legalizim', 'Legalizim', 3.00, 'EUR', 'Legalizim i dokumenteve - Kosovë', 2.50, 5.00),
('XK', 'Legalizim i dy dokumenteve', 'Legalizim i dy dokumenteve', 5.50, 'EUR', 'Legalizim i dy dokumenteve - Kosovë', 5.00, 7.00),

-- Document Verification
('XK', 'Vertetim Dokumenti', 'Vertetim Dokumenti', 5.00, 'EUR', 'Vertetim i nënshkrimit dhe kopjeve - Kosovë', 4.00, 8.00),
('XK', 'Vertetim nënshkrimi', 'Vertetim nënshkrimi', 3.50, 'EUR', 'Vertetim nënshkrimi - Kosovë', 3.00, 5.00),
('XK', 'Vertetim kopjesh', 'Vertetim kopjesh', 2.00, 'EUR', 'Vertetim kopjesh - Kosovë', 1.50, 3.00),

-- Testament Services  
('XK', 'Hartim testamenti', 'Hartim testamenti', 80.00, 'EUR', 'Hartim testamenti i plotë - Kosovë', 70.00, 120.00),
('XK', 'Ndryshim testamenti', 'Ndryshim testamenti', 50.00, 'EUR', 'Ndryshim i testamentit - Kosovë', 40.00, 70.00),

-- Signature Services
('XK', 'Vërtetim nënshkrimi i disa dokumenteve', 'Vërtetim nënshkrimi i disa dokumenteve', 10.00, 'EUR', 'Vërtetim nënshkrimi i disa dokumenteve - Kosovë', 8.00, 15.00),

-- Other Services
('XK', 'Autorizim', 'Autorizim', 5.00, 'EUR', 'Autorizim përfaqësimi - Kosovë', 4.00, 8.00),
('XK', 'Dorëzim dokumenti', 'Dorëzim dokumenti', 15.00, 'EUR', 'Dorëzim zyrtar dokumenti - Kosovë', 12.00, 20.00);

-- 6. Create country regulations table
CREATE TABLE IF NOT EXISTS `country_regulations` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `country_code` VARCHAR(2) NOT NULL,
  `regulation_type` VARCHAR(100) NOT NULL COMMENT 'Document requirements, business rules, etc',
  `regulation_name_sq` VARCHAR(150),
  `description` TEXT,
  `active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`country_code`) REFERENCES `countries`(`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Kosovo Regulations
INSERT INTO `country_regulations` (`country_code`, `regulation_type`, `regulation_name_sq`, `description`)
VALUES 
('XK', 'ID_REQUIREMENT', 'ID Kombëtare Oblieguese', 'Dokumentet duhet të jetë me ID të vlefshëm personal'),
('XK', 'ORIGINAL_DOCUMENTS', 'Dokumentet Origjinale', 'Të gjitha dokumentet duhet të jenë origjinale ose kopje të vërtetuara'),
('XK', 'SIGNATURE_WITNESS', 'Dëshmit për nënshkrimin', 'Nënshkrim duhet të bëhet përpara notarit');

-- 8. Add indexes for performance
CREATE INDEX idx_country_code ON `reservations`(`country_code`);
CREATE INDEX idx_country_code_payments ON `payments`(`country_code`);
CREATE INDEX idx_country_code_users ON `users`(`country_code`);
CREATE INDEX idx_country_code_pricing ON `country_pricing`(`country_code`, `active`);

-- 9. Create logs table for country-specific changes
CREATE TABLE IF NOT EXISTS `country_change_log` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `country_code` VARCHAR(2),
  `change_type` VARCHAR(50),
  `description` TEXT,
  `changed_by` INT COMMENT 'admin_id',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`country_code`) REFERENCES `countries`(`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================
-- MIGRATION COMPLETE
-- =========================================
