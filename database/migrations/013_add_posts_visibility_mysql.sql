-- Migration: 013_add_posts_visibility_mysql.sql
-- Adds a `visibility` column to `posts` table for social feed visibility rules.
-- Note: uses `IF NOT EXISTS` which requires MySQL 8.0+. If your server is older,
-- run the ALTER TABLE manually after reviewing.

SET @OLD_FOREIGN_KEY_CHECKS = @@FOREIGN_KEY_CHECKS;
SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE `posts`
  ADD COLUMN IF NOT EXISTS `visibility` VARCHAR(20) NOT NULL DEFAULT 'public' AFTER `content`;

SET FOREIGN_KEY_CHECKS = @OLD_FOREIGN_KEY_CHECKS;

-- Backfill: ensure existing rows have a value (this is redundant because of DEFAULT,
-- but included for safety if the server/version ignores IF NOT EXISTS semantics).
UPDATE `posts` SET `visibility` = 'public' WHERE `visibility` IS NULL OR `visibility` = '';
