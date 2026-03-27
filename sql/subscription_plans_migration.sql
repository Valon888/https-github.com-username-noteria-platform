-- Tabela për pakete abonimesh të predefinuara
CREATE TABLE IF NOT EXISTS `subscription_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL UNIQUE,
  `slug` varchar(100) NOT NULL UNIQUE,
  `description` text,
  `currency` varchar(3) DEFAULT 'EUR',
  `monthly_price` decimal(10, 2) NOT NULL,
  `yearly_price` decimal(10, 2),
  `setup_fee` decimal(10, 2) DEFAULT 0,
  `billing_cycle` enum('monthly', 'yearly') DEFAULT 'monthly',
  `features` json,
  `max_documents` int(11) DEFAULT -1,
  `max_signatures` int(11) DEFAULT -1,
  `max_consultations` int(11) DEFAULT -1,
  `support_level` enum('email', 'priority', 'dedicated') DEFAULT 'email',
  `is_active` boolean DEFAULT TRUE,
  `trial_days` int(11) DEFAULT 0,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela për zbritje dhe promocione
CREATE TABLE IF NOT EXISTS `discounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL UNIQUE,
  `name` varchar(100) NOT NULL,
  `description` text,
  `discount_type` enum('percentage', 'fixed') DEFAULT 'percentage',
  `discount_value` decimal(10, 2) NOT NULL,
  `max_uses` int(11) DEFAULT -1,
  `used_count` int(11) DEFAULT 0,
  `valid_from` datetime NOT NULL,
  `valid_until` datetime NOT NULL,
  `applies_to` enum('all_plans', 'specific_plans') DEFAULT 'all_plans',
  `applicable_plans` json,
  `min_subscription_months` int(11) DEFAULT 1,
  `is_active` boolean DEFAULT TRUE,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela për historikun e zbritjeve të përdorura
CREATE TABLE IF NOT EXISTS `discount_usage` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `discount_id` int(11) NOT NULL,
  `subscription_id` int(11) NOT NULL,
  `zyra_id` int(11) NOT NULL,
  `discount_amount` decimal(10, 2) NOT NULL,
  `applied_date` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`discount_id`) REFERENCES `discounts` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`subscription_id`) REFERENCES `subscription` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`zyra_id`) REFERENCES `zyrat` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela për kujtesa të pagesave dhe emërime
CREATE TABLE IF NOT EXISTS `payment_reminders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subscription_id` int(11) NOT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `reminder_type` enum('3days_before', '1day_before', '1day_after', '7days_after', 'overdue') DEFAULT '3days_before',
  `scheduled_date` datetime NOT NULL,
  `sent_date` datetime DEFAULT NULL,
  `status` enum('scheduled', 'sent', 'failed', 'skipped') DEFAULT 'scheduled',
  `email_body` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`subscription_id`) REFERENCES `subscription` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela për vonesa pagese
CREATE TABLE IF NOT EXISTS `payment_delays` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11),
  `subscription_id` int(11) NOT NULL,
  `days_overdue` int(11) DEFAULT 0,
  `penalty_fee` decimal(10, 2) DEFAULT 0,
  `first_overdue_date` date,
  `last_reminder_date` datetime DEFAULT NULL,
  `payment_plan_amount` decimal(10, 2) DEFAULT NULL,
  `payment_plan_installments` int(11) DEFAULT NULL,
  `status` enum('active', 'resolved', 'written_off') DEFAULT 'active',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`subscription_id`) REFERENCES `subscription` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Përditëso tabelën subscription për të shtuar lidhjen me plan
ALTER TABLE `subscription` ADD COLUMN `plan_id` int(11) DEFAULT NULL;
ALTER TABLE `subscription` ADD FOREIGN KEY (`plan_id`) REFERENCES `subscription_plans` (`id`) ON DELETE SET NULL;
ALTER TABLE `subscription` ADD COLUMN `billing_cycle` enum('monthly', 'yearly') DEFAULT 'monthly';
ALTER TABLE `subscription` ADD COLUMN `discount_id` int(11) DEFAULT NULL;
ALTER TABLE `subscription` ADD FOREIGN KEY (`discount_id`) REFERENCES `discounts` (`id`) ON DELETE SET NULL;
ALTER TABLE `subscription` ADD COLUMN `next_billing_date` datetime DEFAULT NULL;

-- Përditëso tabelën invoices
ALTER TABLE `invoices` ADD COLUMN `subscription_id` int(11) DEFAULT NULL;
ALTER TABLE `invoices` ADD COLUMN `discount_id` int(11) DEFAULT NULL;
ALTER TABLE `invoices` ADD COLUMN `discount_amount` decimal(10, 2) DEFAULT 0;
ALTER TABLE `invoices` ADD COLUMN `recurring_invoice` boolean DEFAULT FALSE;
ALTER TABLE `invoices` ADD COLUMN `late_payment_penalty` decimal(10, 2) DEFAULT 0;
ALTER TABLE `invoices` ADD FOREIGN KEY (`subscription_id`) REFERENCES `subscription` (`id`) ON DELETE SET NULL;
ALTER TABLE `invoices` ADD FOREIGN KEY (`discount_id`) REFERENCES `discounts` (`id`) ON DELETE SET NULL;
