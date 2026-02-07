-- Migration: Add updated_at column to comments table (MySQL)
-- Generated: 2026-02-07
-- Purpose: add an `updated_at` column so server can set/update timestamps when comments are edited.

SET @prev_sql_mode := @@sql_mode;
SET @prev_time_zone := @@time_zone;

-- Use a deterministic timezone for migration operations
SET time_zone = '+00:00';

ALTER TABLE `comments`
  ADD COLUMN `updated_at` DATETIME NULL DEFAULT NULL AFTER `created_at`;

-- Restore session settings
SET sql_mode = @prev_sql_mode;
SET time_zone = @prev_time_zone;

-- End of migration
