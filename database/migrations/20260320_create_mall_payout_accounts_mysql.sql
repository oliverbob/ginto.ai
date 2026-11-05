-- Mall Payout Accounts: where users/sellers receive scheduled payouts
CREATE TABLE IF NOT EXISTS `mall_payout_accounts` (
    `id`                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`             INT UNSIGNED NOT NULL,
    `account_type`        ENUM('bank', 'ewallet') NOT NULL DEFAULT 'bank',
    `institution_name`    VARCHAR(255) NOT NULL,
    `account_holder_name` VARCHAR(255) NOT NULL,
    `account_number`      VARCHAR(100) NOT NULL,
    `is_primary`          TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_mall_payout_accounts_user` (`user_id`),
    CONSTRAINT `fk_mall_payout_accounts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mall Seller Payouts: pending / sent payout records (auto-scheduled, not manual withdrawal)
CREATE TABLE IF NOT EXISTS `mall_seller_payouts` (
    `id`                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`            INT UNSIGNED NOT NULL,
    `payout_account_id`  INT UNSIGNED NULL,
    `source_type`        ENUM('sales', 'commission') NOT NULL DEFAULT 'sales',
    `amount`             DECIMAL(14,2) NOT NULL,
    `status`             ENUM('pending', 'processing', 'sent', 'failed') NOT NULL DEFAULT 'pending',
    `scheduled_at`       DATETIME NULL,
    `sent_at`            DATETIME NULL,
    `reference`          VARCHAR(100) NULL,
    `note`               TEXT NULL,
    `created_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_mall_seller_payouts_user` (`user_id`, `status`),
    CONSTRAINT `fk_mall_seller_payouts_user`    FOREIGN KEY (`user_id`)           REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_mall_seller_payouts_account` FOREIGN KEY (`payout_account_id`) REFERENCES `mall_payout_accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
