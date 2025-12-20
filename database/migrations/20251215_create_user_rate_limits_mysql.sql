-- Migration: Create user_rate_limits table for per-user rate limit tracking
-- Date: 2025-12-15
-- Description: Tracks API usage per user/session for rate limiting.
--              Used by UserRateLimiter to enforce per-user limits based on tier.

CREATE TABLE IF NOT EXISTS `user_rate_limits` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `visitor_ip` VARCHAR(45) NULL,
    `provider` VARCHAR(50) NOT NULL DEFAULT 'cerebras',
    `date` DATE NOT NULL,
    `minute_bucket` DATETIME NULL,
    `requests_count` INT NOT NULL DEFAULT 0,
    `tokens_used` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_user_date` (`user_id`, `date`),
    INDEX `idx_visitor_date` (`visitor_ip`, `date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
