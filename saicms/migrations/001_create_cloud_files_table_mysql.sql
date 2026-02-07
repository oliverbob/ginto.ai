-- Migration: 001_create_cloud_files_table_mysql.sql
-- Creates `cloud_files` table used by upload, posts, stories, and related controllers
-- Run: mysql -u <user> -p ginto < 001_create_cloud_files_table_mysql.sql

CREATE TABLE IF NOT EXISTS `cloud_files` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `storage_provider` VARCHAR(100) NOT NULL,
  `provider_file_id` VARCHAR(255) DEFAULT NULL,
  `file_path_in_provider` VARCHAR(500) DEFAULT NULL,
  `container_name` VARCHAR(255) DEFAULT NULL,
  `container_id` VARCHAR(255) DEFAULT NULL,
  `original_filename` VARCHAR(255) DEFAULT NULL,
  `title` VARCHAR(255) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `tags` JSON DEFAULT NULL,
  `content_type` VARCHAR(100) DEFAULT NULL,
  `size_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `content_sha1` VARCHAR(64) DEFAULT NULL,
  `file_category` VARCHAR(50) DEFAULT NULL,
  `visibility` VARCHAR(20) NOT NULL DEFAULT 'private',
  `uploaded_at_provider` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_cloud_files_user` (`user_id`),
  INDEX `idx_cloud_files_provider` (`storage_provider`),
  INDEX `idx_cloud_files_provider_file_id` (`provider_file_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
