-- Migration: Add Office Payment Routing Support
-- Date: 2026-01-05
-- Purpose: Enable direct payment routing to notary office bank accounts

-- Add zyra_id column to payments table to track which office received payment
ALTER TABLE payments 
ADD COLUMN IF NOT EXISTS zyra_id INT(11) DEFAULT NULL AFTER reservation_id,
ADD COLUMN IF NOT EXISTS office_bank VARCHAR(100) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS office_iban VARCHAR(34) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS routed_at TIMESTAMP NULL DEFAULT NULL;

-- Add index on zyra_id for faster lookups
ALTER TABLE payments 
ADD INDEX IF NOT EXISTS idx_zyra_id (zyra_id);

-- Ensure zyrat table has all necessary banking fields
ALTER TABLE zyrat
ADD COLUMN IF NOT EXISTS banka VARCHAR(100) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS iban VARCHAR(34) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS llogaria VARCHAR(30) DEFAULT NULL;

-- Add indexes on zyrat banking fields
ALTER TABLE zyrat
ADD INDEX IF NOT EXISTS idx_iban (iban),
ADD INDEX IF NOT EXISTS idx_email (email);

-- Update payment_logs to better track office payments
ALTER TABLE payment_logs
ADD COLUMN IF NOT EXISTS zyra_id INT(11) DEFAULT NULL;
