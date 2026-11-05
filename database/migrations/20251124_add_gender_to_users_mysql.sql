-- Migration: Add gender column to users table
-- Check if column exists before adding (MySQL doesn't have ADD COLUMN IF NOT EXISTS)
SET @columnExists = (SELECT COUNT(*) FROM information_schema.columns 
    WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'gender');
SET @sql = IF(@columnExists = 0, 
    'ALTER TABLE users ADD COLUMN gender VARCHAR(10) NULL AFTER lastname',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;