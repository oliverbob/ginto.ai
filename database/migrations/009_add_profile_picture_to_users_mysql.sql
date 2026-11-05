-- Migration: 009_add_profile_picture_to_users_mysql.sql
-- Adds `profile_picture` column to `users` for backward compatibility with controller queries
-- Run: mysql -u <user> -p < 009_add_profile_picture_to_users_mysql.sql

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `profile_picture` VARCHAR(255) NULL AFTER `avatar`;

-- Copy existing avatar values into profile_picture if present
UPDATE `users` SET profile_picture = avatar WHERE profile_picture IS NULL AND avatar IS NOT NULL;
