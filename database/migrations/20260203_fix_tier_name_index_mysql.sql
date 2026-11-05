-- Ensure `tier_plans.name` is a VARCHAR(191) and add unique index `ux_tier_plans_name`.
-- Idempotent: will only alter the column if it's not varchar(<=191), and only add the index if missing.

-- Check if column is already varchar with length <= 191
SET @col_ok = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'tier_plans'
    AND COLUMN_NAME = 'name'
    AND DATA_TYPE = 'varchar'
    AND COALESCE(CHARACTER_MAXIMUM_LENGTH,0) <= 191
);

SET @sql = IF(@col_ok = 0,
  'ALTER TABLE `tier_plans` MODIFY `name` VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL',
  'SELECT "name column already varchar(<=191)"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Now add unique index if it doesn't exist
SET @idx_exists = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'tier_plans'
    AND INDEX_NAME = 'ux_tier_plans_name'
);

SET @sql2 = IF(@idx_exists = 0,
  'ALTER TABLE `tier_plans` ADD UNIQUE INDEX `ux_tier_plans_name` (`name`)',
  'SELECT "ux_tier_plans_name already exists"'
);
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;
