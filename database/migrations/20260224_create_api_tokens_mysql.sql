-- Create api_tokens table (MySQL)
-- Stores hashed tokens (sha256) for per-user API access.

CREATE TABLE IF NOT EXISTS `api_tokens` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(64) NOT NULL,
  `token` CHAR(64) NOT NULL,
  `expires_at` DATETIME NULL,
  `revoked` TINYINT(1) NOT NULL DEFAULT 0,
  `revoked_at` DATETIME NULL,
  `last_used_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_api_tokens_user_name` (`user_id`, `name`),
  KEY `idx_api_tokens_token` (`token`),
  KEY `idx_api_tokens_revoked` (`revoked`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
