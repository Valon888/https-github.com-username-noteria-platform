CREATE TABLE IF NOT EXISTS `docusign_envelopes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `envelope_id` varchar(255) NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `signer_email` varchar(255) NOT NULL,
  `signer_name` varchar(255) NOT NULL,
  `status` varchar(50) DEFAULT 'sent',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
