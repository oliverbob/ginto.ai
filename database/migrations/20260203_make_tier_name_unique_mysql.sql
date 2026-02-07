-- Make tier_plans.name unique
-- Idempotent: only add the unique index if it doesn't already exist

SET @idx_exists = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'tier_plans'
    AND INDEX_NAME = 'ux_tier_plans_name'
);

SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE `tier_plans` ADD UNIQUE INDEX `ux_tier_plans_name` (`name`(191))',
  'SELECT "ux_tier_plans_name already exists"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
