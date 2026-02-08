-- Create provider_key_usage table to store local per-key usage snapshots
-- This is safe to run multiple times; it uses CREATE TABLE IF NOT EXISTS

CREATE TABLE IF NOT EXISTS `provider_key_usage` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `provider` VARCHAR(64) NOT NULL,
  `user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `date` DATE NOT NULL,
  `minute_bucket` DATETIME NULL DEFAULT NULL,
  `requests_count` INT NOT NULL DEFAULT 0,
  `tokens_used` BIGINT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_key_date` (`key_id`,`date`),
  KEY `idx_provider_date` (`provider`,`date`),
  KEY `idx_minute_bucket` (`minute_bucket`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Best-effort: attempt to add the table only if it does not exist already.
-- The migration runner should execute these SQL files in order. If your
-- MySQL version supports ALTER TABLE ... ADD COLUMN IF NOT EXISTS you may
-- add columns safely; the SQL above uses CREATE TABLE IF NOT EXISTS which
-- is broadly supported.
