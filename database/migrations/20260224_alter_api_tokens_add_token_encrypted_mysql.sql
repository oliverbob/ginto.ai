-- Add token_encrypted column for retrievable API keys (encrypted at rest).
-- Guarded on BOTH table and column existence: this migration can sort before
-- 20260224_create_api_tokens (a<c), so it must be a no-op when the table is
-- absent (the create already defines token_encrypted) -- otherwise the ALTER
-- fails with "Table api_tokens doesn't exist" and stalls the whole run.

SET @tbl_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'api_tokens'
);

SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'api_tokens'
    AND COLUMN_NAME = 'token_encrypted'
);

SET @sql := IF(
  @tbl_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `api_tokens` ADD COLUMN `token_encrypted` TEXT NULL AFTER `token`',
  'SELECT "skip: api_tokens missing or token_encrypted already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
