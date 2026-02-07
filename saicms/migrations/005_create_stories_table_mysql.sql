-- Migration: 005_create_stories_table_mysql.sql
-- Creates `stories` table used by StoriesController
-- Run: mysql -u <user> -p ginto < 005_create_stories_table_mysql.sql

CREATE TABLE IF NOT EXISTS `stories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `cloud_file_id` BIGINT UNSIGNED DEFAULT NULL,
  `content_type` VARCHAR(50) NOT NULL DEFAULT 'text_only',
  `media_url_override` VARCHAR(2048) DEFAULT NULL,
  `text_overlay` TEXT DEFAULT NULL,
  `code_content` TEXT DEFAULT NULL,
  `code_language` VARCHAR(50) DEFAULT NULL,
  `link_url` VARCHAR(2048) DEFAULT NULL,
  `link_preview_data` JSON DEFAULT NULL,
  `background_color` VARCHAR(32) DEFAULT NULL,
  `font_family` VARCHAR(100) DEFAULT NULL,
  `theme_category` VARCHAR(100) DEFAULT NULL,
  `duration_seconds` INT NOT NULL DEFAULT 15,
  `visibility` VARCHAR(20) NOT NULL DEFAULT 'public',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_stories_user` (`user_id`),
  INDEX `idx_stories_cloud_file` (`cloud_file_id`),
  INDEX `idx_stories_expires` (`expires_at`),
  INDEX `idx_stories_visibility` (`visibility`),
  CONSTRAINT `stories_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stories_ibfk_2` FOREIGN KEY (`cloud_file_id`) REFERENCES `cloud_files` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
