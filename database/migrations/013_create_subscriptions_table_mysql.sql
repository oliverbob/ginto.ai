-- Migration: 013_create_subscriptions_table_mysql.sql
-- Creates a minimal `subscriptions` table used by GameController::isPremiumUser
-- Run: mysql -u <user> -p < 013_create_subscriptions_table_mysql.sql
CREATE TABLE IF NOT EXISTS `subscriptions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `status` ENUM('active','cancelled','expired','pending') NOT NULL DEFAULT 'pending',
  `current_period_end` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
