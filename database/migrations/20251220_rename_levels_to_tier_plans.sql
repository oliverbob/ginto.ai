-- Migration: Rename levels table to tier_plans (SQLite)
-- This renames the membership tier table for clarity

-- SQLite doesn't support RENAME TABLE directly, need to recreate
-- Step 1: Create new table with same structure
CREATE TABLE IF NOT EXISTS tier_plans AS SELECT * FROM levels;

-- Step 2: Drop old table
DROP TABLE IF EXISTS levels;

-- Note: Code references have been updated to use 'tier_plans' table name
