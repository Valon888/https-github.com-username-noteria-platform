-- Add reset_expires column to users table
ALTER TABLE users 
ADD reset_expires DATETIME DEFAULT NULL;