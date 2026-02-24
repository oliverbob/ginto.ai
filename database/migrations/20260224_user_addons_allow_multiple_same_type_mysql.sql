-- Migration: Allow multiple subscriptions for the same addon_type in user_addons
-- Created: 2026-02-24
-- Needed for per-key addons like serverless_key ($105/mo per key)

SET @idx := (
  SELECT INDEX_NAME
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'user_addons'
    AND INDEX_NAME = 'uk_user_addon_type'
  LIMIT 1
);

SET @sql := IF(
  @idx IS NULL,
  'SELECT 1',
  'ALTER TABLE `user_addons` DROP INDEX `uk_user_addon_type`'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
