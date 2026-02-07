-- Migration: 003_add_profile_fields_to_users_mysql.sql
-- Adds profile-related columns expected by controllers and migrates existing fullname
-- Run: mysql -u <user> -p ginto < 003_add_profile_fields_to_users_mysql.sql

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `full_name` VARCHAR(200) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `headline` VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `bio` TEXT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `profile_picture` VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `cover_photo` VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `work_place` VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `education` VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `current_city` VARCHAR(255) DEFAULT NULL;

-- If an older `fullname` column exists (no underscore), copy values to the new `full_name` column
UPDATE `users` SET `full_name` = `fullname` WHERE `full_name` IS NULL AND `fullname` IS NOT NULL;
