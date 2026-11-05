-- Migration: 2026-02-05
-- Adds `created_at` column to `comments` (some code expects com.created_at)

SET @OLD_SQL_MODE=@@SQL_MODE;
SET SESSION SQL_MODE= CONCAT(@@SQL_MODE, ',NO_ZERO_IN_DATE,NO_ZERO_DATE');

ALTER TABLE `comments`
  ADD COLUMN IF NOT EXISTS `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP AFTER `content`;

CREATE INDEX IF NOT EXISTS `idx_comments_created_at` ON `comments` (`created_at`);

SET SESSION SQL_MODE=@OLD_SQL_MODE;
