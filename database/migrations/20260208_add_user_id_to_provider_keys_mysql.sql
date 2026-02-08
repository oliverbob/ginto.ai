-- Migration: Add user_id to provider_keys so keys can be owned by users
-- Date: 2026-02-08
-- Description: Adds an optional `user_id` column to `provider_keys` so
--              individual users can register their own API keys. Adjust
--              uniqueness index to allow the same api_key across different users.

SET @table_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'provider_keys'
);

-- Only run if provider_keys table exists
IF @table_exists = 1 THEN

  -- Add user_id column if missing
  SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'provider_keys' AND COLUMN_NAME = 'user_id') = 0,
    'ALTER TABLE provider_keys ADD COLUMN user_id INT UNSIGNED NULL AFTER provider',
    'SELECT "Column user_id already exists"'
  );
  PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

  -- Add index on user_id for faster lookups
  SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'provider_keys' AND INDEX_NAME = 'idx_user_id') = 0,
    'ALTER TABLE provider_keys ADD INDEX idx_user_id (user_id)',
    'SELECT "Index idx_user_id already exists"'
  );
  PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

  -- Add foreign key to users (if not exists). Use SET NULL so keys remain if user deleted.
  SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS tc
                    JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
                      ON tc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
                    WHERE tc.TABLE_SCHEMA = DATABASE() AND tc.TABLE_NAME = 'provider_keys'
                      AND tc.CONSTRAINT_TYPE = 'FOREIGN KEY' AND kcu.COLUMN_NAME = 'user_id');

  IF @fk_exists = 0 THEN
    -- Only add FK if users table exists
    SET @users_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users');
    IF @users_exists = 1 THEN
      ALTER TABLE provider_keys
        ADD CONSTRAINT fk_provider_keys_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE;
    END IF;
  END IF;

  -- Replace the existing unique index on provider+api_key with a user-scoped unique index
  -- so different users can register the same provider/api_key independently.
  SET @has_old_idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'provider_keys' AND INDEX_NAME = 'idx_provider_key');
  IF @has_old_idx = 1 THEN
    ALTER TABLE provider_keys DROP INDEX idx_provider_key;
  END IF;

  SET @has_user_unique = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'provider_keys' AND INDEX_NAME = 'idx_provider_user_key');
  IF @has_user_unique = 0 THEN
    ALTER TABLE provider_keys ADD UNIQUE INDEX idx_provider_user_key (provider, user_id, api_key(100));
  END IF;

END IF;

-- Note: After running this migration, application code should start saving
-- `user_id` when users create API keys and use user-scoped selection logic.
