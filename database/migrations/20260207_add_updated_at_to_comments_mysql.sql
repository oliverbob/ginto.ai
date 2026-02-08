-- Migration: Add updated_at column to comments table (MySQL)
-- Generated: 2026-02-07
-- Purpose: add an `updated_at` column so server can set/update timestamps when comments are edited.

SET @prev_sql_mode := @@sql_mode;
SET @prev_time_zone := @@time_zone;

-- Use a deterministic timezone for migration operations
SET time_zone = '+00:00';

-- Only add column if table exists and column missing
SET @table_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'comments'
);

IF @table_exists = 1 THEN
  SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'comments' AND COLUMN_NAME = 'updated_at'
  );

  IF @col_exists = 0 THEN
    ALTER TABLE `comments`
      ADD COLUMN `updated_at` DATETIME NULL DEFAULT NULL AFTER `created_at`;
  END IF;
END IF;

-- Restore session settings
SET sql_mode = @prev_sql_mode;
SET time_zone = @prev_time_zone;

-- End of migration
