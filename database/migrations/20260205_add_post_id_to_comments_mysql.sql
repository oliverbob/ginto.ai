-- Migration: 2026-02-05 - Add post_id to comments table and index (MySQL)
-- Safe to run multiple times. Uses IF NOT EXISTS where supported.
-- This file uses MySQL-compatible statements and is intended for the project's /admin/migrate runner.
START TRANSACTION;

-- Add the column if it does not exist (MySQL 8+ supports IF NOT EXISTS)
ALTER TABLE comments ADD COLUMN IF NOT EXISTS post_id INT(10) UNSIGNED DEFAULT NULL AFTER id;

-- Create index on post_id if it does not already exist.
SET @idx_count := (
  SELECT COUNT(1)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'comments'
    AND index_name = 'idx_comments_post_id'
);
SET @sql := IF(@idx_count = 0, 'ALTER TABLE comments ADD INDEX idx_comments_post_id (post_id)', 'SELECT "index_exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

COMMIT;

-- End migration
