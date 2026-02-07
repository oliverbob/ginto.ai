-- Migration: 011_create_friends_table_mysql.sql
-- Creates the `friends` table used by ContactsController and other features
-- Run: mysql -u <user> -p < 011_create_friends_table_mysql.sql
CREATE TABLE IF NOT EXISTS `friends` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `friend_id` BIGINT UNSIGNED NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `requested_at` DATETIME NULL DEFAULT NULL,
  `accepted_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pair` (`user_id`,`friend_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_friend` (`friend_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notes:
-- - The application expects rows where either (user_id,current_user) or (friend_id,current_user)
--   are used to find friendships. The UNIQUE pair prevents duplicate identical entries.
-- - Consider adding foreign key constraints to `users(id)` in production if desired.
