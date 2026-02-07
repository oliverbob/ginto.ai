-- Migration: 014_create_user_hidden_suggestions_table_mysql.sql
-- Creates the `user_hidden_suggestions` table used by FriendsController->fetchFriendSuggestions
-- Run: mysql -u <user> -p < 014_create_user_hidden_suggestions_table_mysql.sql
CREATE TABLE IF NOT EXISTS `user_hidden_suggestions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `hidden_user_id` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_hidden` (`user_id`,`hidden_user_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_hidden_user` (`hidden_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
