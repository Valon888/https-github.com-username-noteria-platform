-- SQL Schema për implementimin e WebRTC në platformën Noteria
-- Këto tabela janë të nevojshme për funksionimin e sistemit të video thirrjeve

-- Tabela për thirrjet video
CREATE TABLE IF NOT EXISTS `video_calls` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `call_id` varchar(50) NOT NULL COMMENT 'ID unike e thirrjes',
  `caller_id` int(11) NOT NULL COMMENT 'ID e përdoruesit që fillon thirrjen',
  `recipient_id` int(11) NOT NULL COMMENT 'ID e përdoruesit që merr thirrjen',
  `status` enum('pending','active','ended','missed','declined') NOT NULL DEFAULT 'pending' COMMENT 'Statusi i thirrjes',
  `created_at` datetime NOT NULL COMMENT 'Koha e krijimit të thirrjes',
  `start_time` datetime DEFAULT NULL COMMENT 'Koha kur thirrja është pranuar',
  `end_time` datetime DEFAULT NULL COMMENT 'Koha kur thirrja ka përfunduar',
  `duration` int(11) DEFAULT NULL COMMENT 'Kohëzgjatja e thirrjes në sekonda',
  `notification_id` int(11) DEFAULT NULL COMMENT 'ID e notifikimit të lidhur me thirrjen',
  PRIMARY KEY (`id`),
  UNIQUE KEY `call_id` (`call_id`),
  KEY `caller_id` (`caller_id`),
  KEY `recipient_id` (`recipient_id`),
  KEY `notification_id` (`notification_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Tabela për menaxhimin e thirrjeve video';

-- Tabela për historikun e thirrjeve
CREATE TABLE IF NOT EXISTS `call_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `call_id` varchar(50) NOT NULL COMMENT 'ID unike e thirrjes',
  `caller_id` int(11) NOT NULL COMMENT 'ID e përdoruesit që fillon thirrjen',
  `recipient_id` int(11) NOT NULL COMMENT 'ID e përdoruesit që merr thirrjen',
  `start_time` datetime NOT NULL COMMENT 'Koha kur thirrja ka filluar',
  `end_time` datetime DEFAULT NULL COMMENT 'Koha kur thirrja ka përfunduar',
  `duration` int(11) DEFAULT NULL COMMENT 'Kohëzgjatja e thirrjes në sekonda',
  `call_type` enum('audio','video') NOT NULL DEFAULT 'video' COMMENT 'Lloji i thirrjes',
  PRIMARY KEY (`id`),
  KEY `call_id` (`call_id`),
  KEY `caller_id` (`caller_id`),
  KEY `recipient_id` (`recipient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Historiku i thirrjeve';

-- Tabela për dhomat WebRTC
CREATE TABLE IF NOT EXISTS `webrtc_rooms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_id` varchar(50) NOT NULL COMMENT 'ID unike e dhomës',
  `created_at` datetime NOT NULL COMMENT 'Koha e krijimit',
  `updated_at` datetime NOT NULL COMMENT 'Koha e fundit e aktivitetit',
  PRIMARY KEY (`id`),
  UNIQUE KEY `room_id` (`room_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Dhomat për komunikim WebRTC';

-- Tabela për pjesëmarrësit në dhomat WebRTC
CREATE TABLE IF NOT EXISTS `webrtc_participants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_id` varchar(50) NOT NULL COMMENT 'ID e dhomës',
  `user_id` int(11) NOT NULL COMMENT 'ID e përdoruesit',
  `username` varchar(100) NOT NULL COMMENT 'Emri i përdoruesit',
  `joined_at` datetime NOT NULL COMMENT 'Koha e bashkimit',
  `last_seen` datetime NOT NULL COMMENT 'Aktiviteti i fundit',
  PRIMARY KEY (`id`),
  UNIQUE KEY `room_user` (`room_id`,`user_id`),
  KEY `room_id` (`room_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Pjesëmarrësit në dhomat WebRTC';

-- Tabela për mesazhet WebRTC (përdoret për sinjalizim)
CREATE TABLE IF NOT EXISTS `webrtc_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_id` varchar(50) NOT NULL COMMENT 'ID e dhomës',
  `target_user_id` int(11) NOT NULL COMMENT 'ID e përdoruesit të cilit i dërgohet mesazhi',
  `message_data` text NOT NULL COMMENT 'Të dhënat e mesazhit në JSON',
  `created_at` datetime NOT NULL COMMENT 'Koha e krijimit',
  `is_read` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'A është lexuar mesazhi',
  PRIMARY KEY (`id`),
  KEY `room_id` (`room_id`),
  KEY `target_user_id` (`target_user_id`),
  KEY `room_user` (`room_id`,`target_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Mesazhet për sinjalizim WebRTC';

-- Indekse dhe kufizime shtesë
ALTER TABLE `video_calls` 
  ADD CONSTRAINT `video_calls_ibfk_1` FOREIGN KEY (`caller_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `video_calls_ibfk_2` FOREIGN KEY (`recipient_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `video_calls_ibfk_3` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE SET NULL;

ALTER TABLE `call_history` 
  ADD CONSTRAINT `call_history_ibfk_1` FOREIGN KEY (`caller_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `call_history_ibfk_2` FOREIGN KEY (`recipient_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE;