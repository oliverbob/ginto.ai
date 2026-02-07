-- Migration: 012_create_posts_table_mysql.sql
-- Creates (or documents) the `posts` table schema expected by HomeController
-- Run: mysql -u <user> -p < 012_create_posts_table_mysql.sql
CREATE TABLE IF NOT EXISTS `posts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `profile_user_id` INT UNSIGNED NULL,
  `title` VARCHAR(200) NULL,
  `slug` VARCHAR(200) NULL,
  `content` TEXT NULL,
  `excerpt` TEXT NULL,
  `image` VARCHAR(255) NULL,
  `cloud_file_id` BIGINT UNSIGNED NULL,
  `visibility` VARCHAR(20) NOT NULL DEFAULT 'public',
  `post_type` VARCHAR(20) NOT NULL DEFAULT 'text',
  `shared_post_id` INT UNSIGNED NULL,
  `code_language` VARCHAR(20) NULL,
  `is_live_stream` TINYINT(1) NOT NULL DEFAULT 0,
  `stream_playback_uid` VARCHAR(255) NULL,
  `original_prompt` TEXT NULL,
  `location_name` VARCHAR(255) NULL,
  `link_url` VARCHAR(2048) NULL,
  `link_title` VARCHAR(255) NULL,
  `link_description` TEXT NULL,
  `link_domain` VARCHAR(255) NULL,
  `link_image_url` VARCHAR(2048) NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'draft',
  `author_id` INT UNSIGNED NULL,
  `category_id` INT NULL,
  `featured_image` VARCHAR(255) NULL,
  `views` INT NOT NULL DEFAULT 0,
  `meta_title` VARCHAR(200) NULL,
  `meta_description` TEXT NULL,
  `meta_keywords` TEXT NULL,
  `published_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_slug` (`slug`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_profile_user_id` (`profile_user_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notes:
-- - This schema includes columns added by recent controller logic (visibility, post_type,
--   link_* fields, etc.). Adjust sizes/defaults as needed for your deployment.
-- - The table includes legacy `author_id` preserved; application uses `user_id`.
-- - Optionally add FOREIGN KEY constraints to `users(id)` for `user_id`, `profile_user_id`, and `author_id`.
