-- Migration: Rename levels table to tier_plans
-- This renames the membership tier table for clarity
-- Run on production: mysql -u root -p ginto < database/migrations/20251220_rename_levels_to_tier_plans_mysql.sql

-- Check if levels table exists and tier_plans doesn't, then rename
-- If levels exists, rename it
-- If tier_plans already exists, this is a no-op

-- Safe rename: Only runs if 'levels' exists and 'tier_plans' does not
SET @levels_exists = (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'levels');
SET @tier_plans_exists = (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'tier_plans');

-- Perform rename only if levels exists and tier_plans doesn't
SET @sql = IF(@levels_exists > 0 AND @tier_plans_exists = 0, 'RENAME TABLE levels TO tier_plans', 'SELECT "tier_plans already exists or levels not found - skipping rename" AS status');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Note: Code references have been updated to use 'tier_plans' table name
