-- Add playground_use_sandbox column to users table
-- Migration: 20251207_add_sandbox_to_users
-- Check if column exists before adding (MySQL doesn't have ADD COLUMN IF NOT EXISTS)
SET @columnExists = (SELECT COUNT(*) FROM information_schema.columns 
    WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'playground_use_sandbox');
SET @sql = IF(@columnExists = 0, 
    'ALTER TABLE users ADD COLUMN playground_use_sandbox BOOLEAN DEFAULT 0',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;