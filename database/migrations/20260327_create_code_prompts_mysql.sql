-- Code Prompts Tracking Table
-- Tracks monthly AI prompt usage per user (or visitor IP) on the /code page
-- Used to enforce the 2-prompt monthly limit for non-upgraded users

CREATE TABLE IF NOT EXISTS `code_prompts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED DEFAULT NULL COMMENT 'NULL for guest/visitor',
    `visitor_ip` VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'Client IP (IPv4 or IPv6)',
    `month` DATE NOT NULL COMMENT 'First day of the month, e.g. 2026-03-01',
    `prompt_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY `idx_user_month` (`user_id`, `month`),
    UNIQUE KEY `idx_ip_month` (`visitor_ip`, `month`),
    KEY `idx_month` (`month`),

    CONSTRAINT `fk_code_prompts_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
