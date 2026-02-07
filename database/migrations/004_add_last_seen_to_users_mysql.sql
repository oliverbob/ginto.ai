-- Migration: 004_add_last_seen_to_users_mysql.sql
-- Adds `last_seen_at` column to `users` used by ContactsController activity/status calculations
-- Run: mysql -u <user> -p ginto < 004_add_last_seen_to_users_mysql.sql

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `last_seen_at` DATETIME DEFAULT NULL;

-- Optionally, initialize `last_seen_at` to the current timestamp for active users
-- UPDATE `users` SET `last_seen_at` = NOW() WHERE `last_seen_at` IS NULL AND `status` = 'active';
