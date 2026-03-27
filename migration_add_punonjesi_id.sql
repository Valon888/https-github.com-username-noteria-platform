-- =================================================================
-- MIGRATION: Add Employee (punonjesi) Tracking to Reservations
-- This migration supports both database schemas
-- =================================================================

-- Step 1: Check if reservations table exists and add punonjesi_id column
-- (Only if the column doesn't already exist)
ALTER TABLE `reservations` 
  ADD COLUMN `punonjesi_id` INT(11) DEFAULT NULL AFTER `zyra_id`;

-- Step 2: Add foreign key constraint using the new schema (punonjesit table)
-- This assumes your database uses: zyra_noteriale and punonjesit tables
ALTER TABLE `reservations` 
  ADD CONSTRAINT `fk_reservations_punonjesit` 
  FOREIGN KEY (`punonjesi_id`) REFERENCES `punonjesit`(`id`) ON DELETE SET NULL;

-- ===== ALTERNATIVE: If you use the older schema with 'punetoret' table, use this instead:
-- ALTER TABLE `reservations` ADD PRIMARY KEY (`id`);
-- ALTER TABLE `reservations` ADD CONSTRAINT `fk_reservations_punonjesi` 
--   FOREIGN KEY (`punonjesi_id`) REFERENCES `punetoret`(`id`) ON DELETE SET NULL;
