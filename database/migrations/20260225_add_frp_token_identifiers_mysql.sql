-- Migration: Add stable FRP token/domain identifiers for secure session references

ALTER TABLE `frp_tokens`
    ADD COLUMN `token_identifier` VARCHAR(64) NULL AFTER `token`;

CREATE UNIQUE INDEX `idx_token_identifier` ON `frp_tokens` (`token_identifier`);

CREATE TABLE IF NOT EXISTS `frp_token_domains` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `token_id` INT UNSIGNED NOT NULL,
    `token_identifier` VARCHAR(64) NOT NULL,
    `subdomain` VARCHAR(63) NOT NULL,
    `domain_identifier` VARCHAR(64) NOT NULL,
    `revoked` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `revoked_at` DATETIME DEFAULT NULL,

    UNIQUE KEY `idx_user_subdomain` (`user_id`, `subdomain`),
    UNIQUE KEY `idx_domain_identifier` (`domain_identifier`),
    KEY `idx_token_id` (`token_id`),

    CONSTRAINT `fk_frp_token_domains_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_frp_token_domains_token`
        FOREIGN KEY (`token_id`) REFERENCES `frp_tokens` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
