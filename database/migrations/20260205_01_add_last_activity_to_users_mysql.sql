-- Migration: Add `last_activity` column to `users` and index
-- Created: 2026-02-05

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `last_activity` DATETIME NULL DEFAULT NULL AFTER `updated_at`;

-- Create index if it does not already exist (uses information_schema check)
SELECT COUNT(1) INTO @idx_exists
  FROM information_schema.statistics
  WHERE table_schema = DATABASE() AND table_name = 'users' AND index_name = 'idx_users_last_activity';

SET @sql_stmt = IF(@idx_exists = 0,
    'CREATE INDEX idx_users_last_activity ON `users` (`last_activity`);',
    'SELECT "idx_exists";'
);

PREPARE stmt FROM @sql_stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
