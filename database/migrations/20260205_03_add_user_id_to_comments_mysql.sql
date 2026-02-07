-- Migration: 2026-02-05
-- Adds `user_id` and `content` columns to `comments` and creates helpful indexes.
-- Idempotent for MySQL 8+ (uses IF NOT EXISTS where supported).

SET @OLD_SQL_MODE=@@SQL_MODE;
SET SESSION SQL_MODE= CONCAT(@@SQL_MODE, ',NO_ZERO_IN_DATE,NO_ZERO_DATE');

-- Add user_id column (nullable to avoid breaking existing rows)
ALTER TABLE `comments`
  ADD COLUMN IF NOT EXISTS `user_id` INT(10) UNSIGNED DEFAULT NULL AFTER `post_id`;

-- Add content column (text body used by comment inserts)
ALTER TABLE `comments`
  ADD COLUMN IF NOT EXISTS `content` TEXT AFTER `user_id`;

-- Create indexes to speed common lookups
CREATE INDEX IF NOT EXISTS `idx_comments_user_id` ON `comments` (`user_id`);
CREATE INDEX IF NOT EXISTS `idx_comments_post_id` ON `comments` (`post_id`);

SET SESSION SQL_MODE=@OLD_SQL_MODE;
-- Migration: 20260205_03_add_user_id_to_comments_mysql.sql
-- Adds nullable user_id column and index to comments table if missing
-- This migration tries to be idempotent: ALTER TABLE ADD COLUMN IF NOT EXISTS and CREATE INDEX IF NOT EXISTS

ALTER TABLE `comments`
  ADD COLUMN IF NOT EXISTS `user_id` INT(10) UNSIGNED DEFAULT NULL AFTER `post_id`;

CREATE INDEX IF NOT EXISTS `idx_comments_user_id` ON `comments` (`user_id`);
