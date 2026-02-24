-- Add token_encrypted column for retrievable API keys (encrypted at rest)

SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'api_tokens'
    AND COLUMN_NAME = 'token_encrypted'
);

SET @sql := IF(
  @col_exists = 0,
  'ALTER TABLE `api_tokens` ADD COLUMN `token_encrypted` TEXT NULL AFTER `token`',
  'SELECT "token_encrypted already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
