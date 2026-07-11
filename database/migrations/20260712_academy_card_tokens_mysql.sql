-- Vaulted card tokens for Academy assisted auto-renew. One saved card per user; PayMongo keeps
-- the card, we only store its reference (customer + payment method) and display digits.
CREATE TABLE IF NOT EXISTS `academy_card_tokens` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`           INT UNSIGNED NOT NULL,
  `customer_id`       VARCHAR(64) NOT NULL,
  `payment_method_id` VARCHAR(64) NOT NULL,
  `brand`             VARCHAR(20) DEFAULT NULL,
  `last4`             VARCHAR(4)  DEFAULT NULL,
  `exp_month`         VARCHAR(2)  DEFAULT NULL,
  `exp_year`          VARCHAR(4)  DEFAULT NULL,
  `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
