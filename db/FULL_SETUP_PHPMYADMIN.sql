-- =================================================================
-- KONFIGURIMI PLOTË I SISTEMIT NOTERIA
-- =================================================================
-- Përgatit bazën e të dhënave dhe vendos orarit e punës
-- për datën 19 Mars 2026 (pas Festa e Fitër Bajramit)
-- 
-- PËRDORIMI:
-- 1. Hap phpMyAdmin: http://localhost/phpmyadmin
-- 2. Zgjidh bazën "noteria" (ose krijo nëse nuk ekziston)
-- 3. Shko në skedën "SQL"
-- 4. Kopjo këtë fajl dhe pastezo në SQL editor
-- 5. Kliko "Execute"
-- =================================================================

-- Zgjidhja e bagimit të karaktereve
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
SET COLLATION_CONNECTION = 'utf8mb4_unicode_ci';

-- =================================================================
-- TABELA 1: ZYRA NOTERIALE
-- =================================================================
CREATE TABLE IF NOT EXISTS `zyra_noteriale` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `emri` varchar(255) NOT NULL,
  `adresa` text NOT NULL,
  `qyteti` varchar(100) NOT NULL,
  `rrethi` varchar(100) NOT NULL,
  `kodi_postar` varchar(20) NOT NULL,
  `telefoni` varchar(50) DEFAULT NULL,
  `email` varchar(255) NOT NULL UNIQUE,
  `website` varchar(255) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `licensimi` varchar(100) DEFAULT NULL,
  `data_licensimit` date DEFAULT NULL,
  `statusi` enum('aktive','pezulluar','mbyllur') NOT NULL DEFAULT 'aktive',
  `krijuar_me` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `perditesuar_me` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `qyteti_rrethi_idx` (`qyteti`, `rrethi`),
  KEY `statusi_idx` (`statusi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =================================================================
-- TABELA 2: PUNONJEDTË
-- =================================================================
CREATE TABLE IF NOT EXISTS `punonjesit` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `zyra_id` int(11) NOT NULL,
  `emri` varchar(100) NOT NULL,
  `mbiemri` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL UNIQUE,
  `telefoni` varchar(50) DEFAULT NULL,
  `adresa` text DEFAULT NULL,
  `pozicioni` varchar(100) NOT NULL,
  `departamenti` varchar(100) DEFAULT NULL,
  `data_fillimit` date NOT NULL,
  `data_mbarimit` date DEFAULT NULL,
  `nr_personal` varchar(50) DEFAULT NULL UNIQUE,
  `foto` varchar(255) DEFAULT NULL,
  `oret_ditore` decimal(4,2) NOT NULL DEFAULT '8.00',
  `pushim_javor` set('E Hënë','E Martë','E Mërkurë','E Enjte','E Premte','E Shtunë','E Dielë') NOT NULL DEFAULT 'E Shtunë,E Dielë',
  `statusi` enum('aktiv','pushim','pezulluar','larguar') NOT NULL DEFAULT 'aktiv',
  `krijuar_me` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `perditesuar_me` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `nr_personal` (`nr_personal`),
  KEY `zyra_idx` (`zyra_id`),
  KEY `statusi_idx` (`statusi`),
  CONSTRAINT `punonjesit_zyra_fk` FOREIGN KEY (`zyra_id`) REFERENCES `zyra_noteriale` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =================================================================
-- TABELA 3: ORARIT E PUNËS
-- =================================================================
CREATE TABLE IF NOT EXISTS `oraret` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `punonjes_id` int(11) NOT NULL,
  `data_fillimit` date NOT NULL COMMENT 'Data nga e cila fillon ky orar',
  `data_mbarimit` date DEFAULT NULL COMMENT 'Data deri kur aplikohet ky orar',
  `hene_fillim` time DEFAULT NULL,
  `hene_mbarim` time DEFAULT NULL,
  `marte_fillim` time DEFAULT NULL,
  `marte_mbarim` time DEFAULT NULL,
  `merkure_fillim` time DEFAULT NULL,
  `merkure_mbarim` time DEFAULT NULL,
  `enjte_fillim` time DEFAULT NULL,
  `enjte_mbarim` time DEFAULT NULL,
  `premte_fillim` time DEFAULT NULL,
  `premte_mbarim` time DEFAULT NULL,
  `shtune_fillim` time DEFAULT NULL,
  `shtune_mbarim` time DEFAULT NULL,
  `diele_fillim` time DEFAULT NULL,
  `diele_mbarim` time DEFAULT NULL,
  `pershkrimi` varchar(255) DEFAULT NULL,
  `krijuar_nga` int(11) NOT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT '1',
  `krijuar_me` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `perditesuar_me` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `punonjes_id` (`punonjes_id`),
  KEY `data_fillimit_idx` (`data_fillimit`),
  KEY `data_mbarimit_idx` (`data_mbarimit`),
  KEY `aktiv_idx` (`aktiv`),
  CONSTRAINT `oraret_punonjes_fk` FOREIGN KEY (`punonjes_id`) REFERENCES `punonjesit` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =================================================================
-- TABELA 4: HYRJE - DALJE (CHECK-IN/CHECK-OUT)
-- =================================================================
CREATE TABLE IF NOT EXISTS `hyrje_dalje` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `punonjes_id` int(11) NOT NULL,
  `data` date NOT NULL,
  `koha_hyrjes` time DEFAULT NULL,
  `koha_daljes` time DEFAULT NULL,
  `komente_hyrje` text DEFAULT NULL,
  `komente_dalje` text DEFAULT NULL,
  `statusi` enum('normal','vonese','mungese','justifikuar','pezulluar') NOT NULL DEFAULT 'normal',
  `krijuar_me` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `perditesuar_me` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `punonjes_data_idx` (`punonjes_id`, `data`),
  KEY `data_idx` (`data`),
  KEY `statusi_idx` (`statusi`),
  CONSTRAINT `hyrje_dalje_punonjes_fk` FOREIGN KEY (`punonjes_id`) REFERENCES `punonjesit` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =================================================================
-- TABELAT E TJERA (Opsionale për full setup)
-- =================================================================

CREATE TABLE IF NOT EXISTS `lejet` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `punonjes_id` int(11) NOT NULL,
  `lloji` enum('vjetore','semundje','lindje','pa page','studimi','tjeter') NOT NULL,
  `data_fillimit` date NOT NULL,
  `data_mbarimit` date NOT NULL,
  `dite_totale` int(11) GENERATED ALWAYS AS (DATEDIFF(data_mbarimit, data_fillimit) + 1) STORED,
  `arsyeja` text DEFAULT NULL,
  `dokumenti` varchar(255) DEFAULT NULL,
  `aprovuar_nga` int(11) DEFAULT NULL,
  `statusi` enum('kerkuar','aprovuar','refuzuar','anuluar') NOT NULL DEFAULT 'kerkuar',
  `data_aprovimit` datetime DEFAULT NULL,
  `komente_aprovimi` text DEFAULT NULL,
  `krijuar_me` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `perditesuar_me` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `punonjes_id` (`punonjes_id`),
  KEY `aprovuar_nga` (`aprovuar_nga`),
  KEY `data_fillimit_idx` (`data_fillimit`),
  KEY `data_mbarimit_idx` (`data_mbarimit`),
  KEY `statusi_idx` (`statusi`),
  CONSTRAINT `lejet_punonjes_fk` FOREIGN KEY (`punonjes_id`) REFERENCES `punonjesit` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =================================================================
-- VENDOSJA E ORAREVE PËR MARS 19, 2026
-- =================================================================
-- Orari: 08:00 - 16:00
-- Ditë pune: Hënë - Premte
-- Ditë pushimi: Shtunë - Diele
-- =================================================================

-- Deaktivizo orarit e vjetër (opsionale)
UPDATE `oraret` 
SET `aktiv` = 0 
WHERE `data_mbarimit` IS NULL 
  AND `data_fillimit` < '2026-03-19';

-- Krijo orarit e reja për të gjithë punonjesit aktivë
INSERT INTO `oraret` (
  `punonjes_id`,
  `data_fillimit`,
  `data_mbarimit`,
  `hene_fillim`,
  `hene_mbarim`,
  `marte_fillim`,
  `marte_mbarim`,
  `merkure_fillim`,
  `merkure_mbarim`,
  `enjte_fillim`,
  `enjte_mbarim`,
  `premte_fillim`,
  `premte_mbarim`,
  `shtune_fillim`,
  `shtune_mbarim`,
  `diele_fillim`,
  `diele_mbarim`,
  `pershkrimi`,
  `krijuar_nga`,
  `aktiv`
)
SELECT
  `id`,
  '2026-03-19' AS `data_fillimit`,
  NULL AS `data_mbarimit`,
  '08:00:00' AS `hene_fillim`,
  '16:00:00' AS `hene_mbarim`,
  '08:00:00' AS `marte_fillim`,
  '16:00:00' AS `marte_mbarim`,
  '08:00:00' AS `merkure_fillim`,
  '16:00:00' AS `merkure_mbarim`,
  '08:00:00' AS `enjte_fillim`,
  '16:00:00' AS `enjte_mbarim`,
  '08:00:00' AS `premte_fillim`,
  '16:00:00' AS `premte_mbarim`,
  NULL AS `shtune_fillim`,
  NULL AS `shtune_mbarim`,
  NULL AS `diele_fillim`,
  NULL AS `diele_mbarim`,
  'Orari i rregullt 08:00-16:00 - Festa e Fitër Bajramit' AS `pershkrimi`,
  1 AS `krijuar_nga`,
  1 AS `aktiv`
FROM `punonjesit`
WHERE `statusi` = 'aktiv'
  AND (`data_mbarimit` IS NULL OR `data_mbarimit` >= '2026-03-19');

-- =================================================================
-- VERIFIKIMI I VENDOSJES
-- =================================================================
SELECT 
  p.id,
  p.emri,
  p.mbiemri,
  o.data_fillimit,
  CONCAT(TIME_FORMAT(o.hene_fillim, '%H:%i'), ' - ', TIME_FORMAT(o.hene_mbarim, '%H:%i')) AS 'Orari',
  CASE WHEN o.shtune_fillim IS NULL THEN 'Pushim' ELSE 'Work' END AS 'Shtunë',
  CASE WHEN o.diele_fillim IS NULL THEN 'Pushim' ELSE 'Work' END AS 'Diele',
  o.pershkrimi
FROM `oraret` o
JOIN `punonjesit` p ON o.punonjes_id = p.id
WHERE o.data_fillimit = '2026-03-19'
  AND o.aktiv = 1
ORDER BY p.emri, p.mbiemri;

-- =================================================================
-- PËRFUNDIM
-- =================================================================
-- Nëse në shikimin e verifikimit nuk shkon rreshta, mund të jenë:
-- 1. Nuk ka punonjës në tabelën "punonjesit"
-- 2. Të gjithë punonjesit kanë status = 'larguar' ose 'pezulluar'
-- 
-- Për të testuar, mund të shto një punonjës test të pari:
-- 
-- INSERT INTO `zyra_noteriale` 
-- (`emri`, `adresa`, `qyteti`, `rrethi`, `kodi_postar`, `email`, `statusi`)
-- VALUES 
-- ('Zyra Test', 'Adresa Test', 'Prishtinë', 'Prishtinë', '10000', 'test@noteria.com', 'aktive');
-- 
-- INSERT INTO `punonjesit` 
-- (`zyra_id`, `emri`, `mbiemri`, `email`, `pozicioni`, `data_fillimit`, `statusi`)
-- VALUES 
-- (1, 'Test', 'User', 'test@punonjis.com', 'Noterit', '2024-01-01', 'aktiv');
-- =================================================================
