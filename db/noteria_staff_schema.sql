-- Krijimi i databazës për sistemin e menaxhimit të punonjësve të zyrave noteriale
-- Struktura e avancuar e databazës me relacione të plota dhe indekse

-- Tabela e zyrave noteriale
CREATE TABLE IF NOT EXISTS `zyra_noteriale` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `emri` varchar(255) NOT NULL,
  `adresa` text NOT NULL,
  `qyteti` varchar(100) NOT NULL,
  `rrethi` varchar(100) NOT NULL,
  `kodi_postar` varchar(20) NOT NULL,
  `telefoni` varchar(50) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
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

-- Tabela e punonjësve
CREATE TABLE IF NOT EXISTS `punonjesit` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `zyra_id` int(11) NOT NULL,
  `emri` varchar(100) NOT NULL,
  `mbiemri` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `telefoni` varchar(50) DEFAULT NULL,
  `adresa` text DEFAULT NULL,
  `pozicioni` varchar(100) NOT NULL,
  `departamenti` varchar(100) DEFAULT NULL,
  `data_fillimit` date NOT NULL,
  `data_mbarimit` date DEFAULT NULL,
  `nr_personal` varchar(50) DEFAULT NULL,
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
  KEY `pozicioni_idx` (`pozicioni`),
  KEY `statusi_idx` (`statusi`),
  CONSTRAINT `punonjesit_zyra_fk` FOREIGN KEY (`zyra_id`) REFERENCES `zyra_noteriale` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela e autentifikimit të përdoruesve
CREATE TABLE IF NOT EXISTS `perdoruesit` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `punonjes_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `roli` enum('admin','menaxher','punonjes','sekretar') NOT NULL DEFAULT 'punonjes',
  `token` varchar(255) DEFAULT NULL,
  `token_skadim` datetime DEFAULT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT '1',
  `aktivizuar_me` datetime DEFAULT NULL,
  `hyrja_fundit` datetime DEFAULT NULL,
  `dështime_hyrje` int(1) NOT NULL DEFAULT '0',
  `krijuar_me` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `perditesuar_me` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `punonjes_id` (`punonjes_id`),
  CONSTRAINT `perdoruesit_punonjes_fk` FOREIGN KEY (`punonjes_id`) REFERENCES `punonjesit` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela e regjistrimit të hyrje-daljeve (check-in/check-out)
CREATE TABLE IF NOT EXISTS `hyrje_dalje` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `punonjes_id` int(11) NOT NULL,
  `data` date NOT NULL,
  `koha_hyrjes` time DEFAULT NULL,
  `koha_daljes` time DEFAULT NULL,
  `komente_hyrje` text DEFAULT NULL,
  `komente_dalje` text DEFAULT NULL,
  `koordinatat_hyrje` point DEFAULT NULL COMMENT 'Koordinatat gjeografike të hyrjes',
  `koordinatat_dalje` point DEFAULT NULL COMMENT 'Koordinatat gjeografike të daljes',
  `pajisja_hyrje` varchar(255) DEFAULT NULL COMMENT 'Informacion për pajisjen e përdorur për hyrje',
  `pajisja_dalje` varchar(255) DEFAULT NULL COMMENT 'Informacion për pajisjen e përdorur për dalje',
  `ip_adresa_hyrje` varchar(45) DEFAULT NULL,
  `ip_adresa_dalje` varchar(45) DEFAULT NULL,
  `statusi` enum('normal','vonese','mungese','justifikuar','pezulluar') NOT NULL DEFAULT 'normal',
  `krijuar_me` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `perditesuar_me` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `punonjes_data_idx` (`punonjes_id`, `data`),
  KEY `data_idx` (`data`),
  KEY `statusi_idx` (`statusi`),
  CONSTRAINT `hyrje_dalje_punonjes_fk` FOREIGN KEY (`punonjes_id`) REFERENCES `punonjesit` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela e detyrave për punonjësit
CREATE TABLE IF NOT EXISTS `detyrat` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `zyra_id` int(11) NOT NULL,
  `krijuar_nga` int(11) NOT NULL COMMENT 'Referenca te tabela punonjesit',
  `titulli` varchar(255) NOT NULL,
  `pershkrimi` text DEFAULT NULL,
  `prioriteti` enum('i ulët','normal','i lartë','urgjent') NOT NULL DEFAULT 'normal',
  `statusi` enum('e re','në progres','pezulluar','përfunduar','anuluar') NOT NULL DEFAULT 'e re',
  `afati` datetime DEFAULT NULL,
  `kategoria` varchar(100) DEFAULT NULL,
  `krijuar_me` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `perditesuar_me` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `zyra_id` (`zyra_id`),
  KEY `krijuar_nga` (`krijuar_nga`),
  KEY `statusi_idx` (`statusi`),
  KEY `prioriteti_idx` (`prioriteti`),
  CONSTRAINT `detyrat_zyra_fk` FOREIGN KEY (`zyra_id`) REFERENCES `zyra_noteriale` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `detyrat_krijues_fk` FOREIGN KEY (`krijuar_nga`) REFERENCES `punonjesit` (`id`) ON DELETE NO ACTION ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela për caktimin e detyrave punonjësve
CREATE TABLE IF NOT EXISTS `caktim_detyre` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `detyre_id` int(11) NOT NULL,
  `punonjes_id` int(11) NOT NULL,
  `caktuar_nga` int(11) NOT NULL COMMENT 'Referenca te tabela punonjesit',
  `data_caktimit` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `afati_individual` datetime DEFAULT NULL COMMENT 'Nëse ndryshon nga afati i përgjithshëm i detyrës',
  `perfunduar` tinyint(1) NOT NULL DEFAULT '0',
  `data_perfundimit` datetime DEFAULT NULL,
  `komente` text DEFAULT NULL,
  `krijuar_me` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `perditesuar_me` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `detyre_punonjes_idx` (`detyre_id`, `punonjes_id`),
  KEY `punonjes_id` (`punonjes_id`),
  KEY `caktuar_nga` (`caktuar_nga`),
  KEY `perfunduar_idx` (`perfunduar`),
  CONSTRAINT `caktim_detyre_fk` FOREIGN KEY (`detyre_id`) REFERENCES `detyrat` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `caktim_punonjes_fk` FOREIGN KEY (`punonjes_id`) REFERENCES `punonjesit` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `caktim_caktues_fk` FOREIGN KEY (`caktuar_nga`) REFERENCES `punonjesit` (`id`) ON DELETE NO ACTION ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela e orareve për punonjësit
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
  `krijuar_nga` int(11) NOT NULL COMMENT 'Referenca te tabela punonjesit',
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

-- Tabela e lejeve
CREATE TABLE IF NOT EXISTS `lejet` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `punonjes_id` int(11) NOT NULL,
  `lloji` enum('vjetore','semundje','lindje','pa page','studimi','tjeter') NOT NULL,
  `data_fillimit` date NOT NULL,
  `data_mbarimit` date NOT NULL,
  `dite_totale` int(11) GENERATED ALWAYS AS (DATEDIFF(data_mbarimit, data_fillimit) + 1) STORED,
  `arsyeja` text DEFAULT NULL,
  `dokumenti` varchar(255) DEFAULT NULL COMMENT 'Path i dokumentit justifikues',
  `aprovuar_nga` int(11) DEFAULT NULL COMMENT 'Referenca te tabela punonjesit',
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
  CONSTRAINT `lejet_punonjes_fk` FOREIGN KEY (`punonjes_id`) REFERENCES `punonjesit` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `lejet_aprovues_fk` FOREIGN KEY (`aprovuar_nga`) REFERENCES `punonjesit` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela e njoftimeve
CREATE TABLE IF NOT EXISTS `njoftimet` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipi` enum('hyrje','dalje','vonese','mungese','detyre_e_re','afat_detyre','perfundim_detyre','leje_kerkuar','leje_aprovuar','leje_refuzuar','sistem') NOT NULL,
  `zyra_id` int(11) DEFAULT NULL,
  `punonjes_id` int(11) DEFAULT NULL COMMENT 'Punonjësi për të cilin është njoftimi',
  `marres_id` int(11) DEFAULT NULL COMMENT 'Punonjësi që duhet të marrë njoftimin',
  `titulli` varchar(255) NOT NULL,
  `permbajtja` text NOT NULL,
  `detyre_id` int(11) DEFAULT NULL,
  `leje_id` int(11) DEFAULT NULL,
  `hyrje_dalje_id` int(11) DEFAULT NULL,
  `lexuar` tinyint(1) NOT NULL DEFAULT '0',
  `lexuar_me` datetime DEFAULT NULL,
  `prioriteti` enum('i ulet','normal','i larte','urgjent') NOT NULL DEFAULT 'normal',
  `krijuar_me` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `perditesuar_me` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `zyra_id` (`zyra_id`),
  KEY `punonjes_id` (`punonjes_id`),
  KEY `marres_id` (`marres_id`),
  KEY `detyre_id` (`detyre_id`),
  KEY `leje_id` (`leje_id`),
  KEY `hyrje_dalje_id` (`hyrje_dalje_id`),
  KEY `tipi_idx` (`tipi`),
  KEY `lexuar_idx` (`lexuar`),
  CONSTRAINT `njoftimet_zyra_fk` FOREIGN KEY (`zyra_id`) REFERENCES `zyra_noteriale` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `njoftimet_punonjes_fk` FOREIGN KEY (`punonjes_id`) REFERENCES `punonjesit` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `njoftimet_marres_fk` FOREIGN KEY (`marres_id`) REFERENCES `punonjesit` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `njoftimet_detyre_fk` FOREIGN KEY (`detyre_id`) REFERENCES `detyrat` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `njoftimet_leje_fk` FOREIGN KEY (`leje_id`) REFERENCES `lejet` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `njoftimet_hyrje_dalje_fk` FOREIGN KEY (`hyrje_dalje_id`) REFERENCES `hyrje_dalje` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela për konfigurimin e sistemit të njoftimeve
CREATE TABLE IF NOT EXISTS `konfigurime_njoftimesh` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `zyra_id` int(11) NOT NULL,
  `tipi_njoftimit` enum('hyrje','dalje','vonese','mungese','detyre_e_re','afat_detyre','perfundim_detyre','leje_kerkuar','leje_aprovuar','leje_refuzuar','sistem') NOT NULL,
  `njofto_menaxherin` tinyint(1) NOT NULL DEFAULT '1',
  `njofto_administratorin` tinyint(1) NOT NULL DEFAULT '1',
  `njofto_hr` tinyint(1) NOT NULL DEFAULT '0',
  `njofto_email` tinyint(1) NOT NULL DEFAULT '1',
  `njofto_sistem` tinyint(1) NOT NULL DEFAULT '1',
  `njofto_sms` tinyint(1) NOT NULL DEFAULT '0',
  `mesazhi_template` text DEFAULT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT '1',
  `krijuar_me` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `perditesuar_me` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `zyra_tipi_idx` (`zyra_id`, `tipi_njoftimit`),
  KEY `tipi_njoftimit_idx` (`tipi_njoftimit`),
  KEY `aktiv_idx` (`aktiv`),
  CONSTRAINT `konfig_njoftimesh_zyra_fk` FOREIGN KEY (`zyra_id`) REFERENCES `zyra_noteriale` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela për historikun e veprimeve
CREATE TABLE IF NOT EXISTS `log_veprimesh` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `punonjes_id` int(11) DEFAULT NULL,
  `tipi` enum('hyrje_sistem','dalje_sistem','krijim','modifikim','fshirje','eksport','tjeter') NOT NULL,
  `tabela` varchar(100) DEFAULT NULL,
  `rresht_id` int(11) DEFAULT NULL,
  `pershkrimi` text NOT NULL,
  `te_dhenat_vjetra` text DEFAULT NULL,
  `te_dhenat_reja` text DEFAULT NULL,
  `ip_adresa` varchar(45) DEFAULT NULL,
  `shfletuesi` varchar(255) DEFAULT NULL,
  `krijuar_me` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `punonjes_id` (`punonjes_id`),
  KEY `tipi_idx` (`tipi`),
  KEY `tabela_idx` (`tabela`),
  KEY `krijuar_me_idx` (`krijuar_me`),
  CONSTRAINT `log_punonjes_fk` FOREIGN KEY (`punonjes_id`) REFERENCES `punonjesit` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela për statistikat e punonjësve (materialized view)
CREATE TABLE IF NOT EXISTS `statistika_punonjesish` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `punonjes_id` int(11) NOT NULL,
  `muaji` date NOT NULL COMMENT 'First day of month',
  `dite_pune` int(11) NOT NULL DEFAULT 0,
  `ore_pune` decimal(6,2) NOT NULL DEFAULT 0.00,
  `vonesa_totale_min` int(11) NOT NULL DEFAULT 0,
  `mungesa` int(11) NOT NULL DEFAULT 0,
  `detyra_perfunduara` int(11) NOT NULL DEFAULT 0,
  `detyra_vonuar` int(11) NOT NULL DEFAULT 0,
  `perditesuar_me` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `punonjes_muaji_idx` (`punonjes_id`, `muaji`),
  KEY `muaji_idx` (`muaji`),
  CONSTRAINT `statistika_punonjes_fk` FOREIGN KEY (`punonjes_id`) REFERENCES `punonjesit` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;