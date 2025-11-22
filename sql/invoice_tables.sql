-- Create invoices table
CREATE TABLE IF NOT EXISTS `invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(50) NOT NULL,
  `zyra_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `vat` decimal(10,2) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `description` varchar(255) NOT NULL,
  `date_issued` date NOT NULL,
  `due_date` date NOT NULL,
  `service_period_start` date DEFAULT NULL,
  `service_period_end` date DEFAULT NULL,
  `status` enum('draft','issued','paid','cancelled','overdue') NOT NULL DEFAULT 'draft',
  `payment_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `zyra_id` (`zyra_id`),
  CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`zyra_id`) REFERENCES `zyrat` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create automatic_payments table
CREATE TABLE IF NOT EXISTS `automatic_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `zyra_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_type` varchar(50) NOT NULL,
  `reference_id` varchar(100) DEFAULT NULL,
  `payment_date` datetime NOT NULL,
  `status` enum('pending','completed','failed') NOT NULL DEFAULT 'completed',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `zyra_id` (`zyra_id`),
  CONSTRAINT `automatic_payments_ibfk_1` FOREIGN KEY (`zyra_id`) REFERENCES `zyrat` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;