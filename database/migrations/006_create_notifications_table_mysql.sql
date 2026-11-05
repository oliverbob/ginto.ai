-- Migration: 006_create_notifications_table_mysql.sql
-- Creates `notifications` table expected by controllers (ActivitiesController, FriendsController, etc.)
-- Run: mysqldump -u <user> -p ginto > backup.sql && mysql -u <user> -p ginto < 006_create_notifications_table_mysql.sql

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `actor_user_id` INT UNSIGNED DEFAULT NULL,
  `type` VARCHAR(100) NOT NULL,
  `message` TEXT NOT NULL,
  `context_json` JSON DEFAULT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_notifications_user` (`user_id`),
  INDEX `idx_notifications_actor` (`actor_user_id`),
  INDEX `idx_notifications_is_read` (`is_read`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_notifications_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notes:
-- - `context_json` uses JSON type; if your MySQL version doesn't support JSON, change to LONGTEXT.
-- - Back up your DB before running this migration.
