-- Script për të vendosur orarit e punës duke filluar nga 19 Mars 2026
-- Ora e punës: 08:00 - 16:00 (Hënë - Premte)
-- Ditë pushimi: Shtunë dhe Diele

-- INSTRUKSIONET:
-- 1. Hap phpMyAdmin ose MySQL client
-- 2. Zgjidh bazën e të dhënave "noteria"
-- 3. Kopjo dhe ekzekuto këtë script
-- 4. Kontrollo nëse ka punonjës pa orare

-- ============================================
-- KRIJONI ORARE TË REJA DUKE FILLUAR MARS 19
-- ============================================

-- Hapi 1: Deaktivizo orarit e vjetër (opsional - nëse dëshiron të mbash historinë)
UPDATE `oraret` 
SET `aktiv` = 0 
WHERE `data_mbarimit` IS NULL 
  AND `data_fillimit` < '2026-03-19';

-- Hapi 2: Shto orarit e reja për të gjithë punonjesit aktivë
-- Orari standard: 08:00 - 16:00 (Hënë - Premte); Shtunë-Diele = pushim
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
  'Orari i rregullt 08:00-16:00 duke filluar pas Festa e Fitër Bajramit' AS `pershkrimi`,
  1 AS `krijuar_nga`,
  1 AS `aktiv`
FROM `punonjesit`
WHERE `statusi` = 'aktiv'
  AND (`data_mbarimit` IS NULL OR `data_mbarimit` >= '2026-03-19');

-- Hapi 3: Verifikim - Shfaq orarit e reja të vendosura
SELECT 
  p.emri,
  p.mbiemri,
  o.data_fillimit,
  CONCAT(o.hene_fillim, ' - ', o.hene_mbarim) AS 'Orari (Hënë-Premte)',
  CASE 
    WHEN o.shtune_fillim IS NULL THEN 'Pushim'
    ELSE CONCAT(o.shtune_fillim, ' - ', o.shtune_mbarim)
  END AS 'Shtunë',
  CASE 
    WHEN o.diele_fillim IS NULL THEN 'Pushim'
    ELSE CONCAT(o.diele_fillim, ' - ', o.diele_mbarim)
  END AS 'Diele',
  o.pershkrimi
FROM `oraret` o
JOIN `punonjesit` p ON o.punonjes_id = p.id
WHERE o.data_fillimit = '2026-03-19'
  AND o.aktiv = 1
ORDER BY p.emri, p.mbiemri;

-- ============================================
-- KONTROLLI I PUNONJËSVE PA ORARE
-- ============================================
SELECT 
  p.id,
  p.emri,
  p.mbiemri,
  p.email,
  p.statusi,
  COUNT(o.id) AS 'Numri i orareve'
FROM `punonjesit` p
LEFT JOIN `oraret` o ON p.id = o.punonjes_id AND o.aktiv = 1
WHERE p.statusi = 'aktiv'
GROUP BY p.id
HAVING COUNT(o.id) = 0
ORDER BY p.emri;

-- ============================================
-- INFORMACION MBARIMIT
-- ============================================
-- Shërim: Skripti ka kryer këto vepra:
-- ✓ Deaktivizoi orarit e vjetër (nëse ka)
-- ✓ Vendosi orarit të reja për të gjithë punonjesit aktivë
-- ✓ Ora e punës: 08:00 - 16:00 (Hënë - Premte)
-- ✓ Ditë pushimi: Shtunë - Diele
-- ✓ Data e fillimit: 19 Mars 2026
-- ✓ Shfaq listat e punonjësve me orare të reja
-- ✓ Kontrollon nëse ka punonjës pa orare
