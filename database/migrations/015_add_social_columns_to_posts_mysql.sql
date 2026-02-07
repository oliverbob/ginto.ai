-- Migration: 015_add_social_columns_to_posts_mysql.sql
-- Idempotent migration that adds social/post-related columns to `posts` without
-- relying on stored procedures or custom DELIMITER statements (compatible with
-- older MariaDB/MySQL clients). Uses conditional PREPARE/EXECUTE per-column.
-- Run: mysql -u <user> -p ginto < 015_add_social_columns_to_posts_mysql.sql

-- Add column if missing (pattern using information_schema + PREPARE)

SET @schema = DATABASE();

-- profile_user_id
SET @cnt = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @schema AND table_name = 'posts' AND column_name = 'profile_user_id');
SET @sql = IF(@cnt = 0, 'ALTER TABLE `posts` ADD COLUMN `profile_user_id` INT UNSIGNED NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- image
SET @cnt = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @schema AND table_name = 'posts' AND column_name = 'image');
SET @sql = IF(@cnt = 0, 'ALTER TABLE `posts` ADD COLUMN `image` VARCHAR(255) NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- cloud_file_id
SET @cnt = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @schema AND table_name = 'posts' AND column_name = 'cloud_file_id');
SET @sql = IF(@cnt = 0, 'ALTER TABLE `posts` ADD COLUMN `cloud_file_id` BIGINT UNSIGNED NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- post_type
SET @cnt = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @schema AND table_name = 'posts' AND column_name = 'post_type');
SET @sql = IF(@cnt = 0, "ALTER TABLE `posts` ADD COLUMN `post_type` VARCHAR(20) NOT NULL DEFAULT 'text'", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- shared_post_id
SET @cnt = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @schema AND table_name = 'posts' AND column_name = 'shared_post_id');
SET @sql = IF(@cnt = 0, 'ALTER TABLE `posts` ADD COLUMN `shared_post_id` INT UNSIGNED NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- code_language
SET @cnt = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @schema AND table_name = 'posts' AND column_name = 'code_language');
SET @sql = IF(@cnt = 0, 'ALTER TABLE `posts` ADD COLUMN `code_language` VARCHAR(20) NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- is_live_stream
SET @cnt = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @schema AND table_name = 'posts' AND column_name = 'is_live_stream');
SET @sql = IF(@cnt = 0, 'ALTER TABLE `posts` ADD COLUMN `is_live_stream` TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- stream_playback_uid
SET @cnt = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @schema AND table_name = 'posts' AND column_name = 'stream_playback_uid');
SET @sql = IF(@cnt = 0, 'ALTER TABLE `posts` ADD COLUMN `stream_playback_uid` VARCHAR(255) NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- original_prompt
SET @cnt = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @schema AND table_name = 'posts' AND column_name = 'original_prompt');
SET @sql = IF(@cnt = 0, 'ALTER TABLE `posts` ADD COLUMN `original_prompt` TEXT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- location_name
SET @cnt = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @schema AND table_name = 'posts' AND column_name = 'location_name');
SET @sql = IF(@cnt = 0, 'ALTER TABLE `posts` ADD COLUMN `location_name` VARCHAR(255) NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- link_url
SET @cnt = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @schema AND table_name = 'posts' AND column_name = 'link_url');
SET @sql = IF(@cnt = 0, 'ALTER TABLE `posts` ADD COLUMN `link_url` VARCHAR(2048) NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- link_title
SET @cnt = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @schema AND table_name = 'posts' AND column_name = 'link_title');
SET @sql = IF(@cnt = 0, 'ALTER TABLE `posts` ADD COLUMN `link_title` VARCHAR(255) NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- link_description
SET @cnt = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @schema AND table_name = 'posts' AND column_name = 'link_description');
SET @sql = IF(@cnt = 0, 'ALTER TABLE `posts` ADD COLUMN `link_description` TEXT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- link_domain
SET @cnt = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @schema AND table_name = 'posts' AND column_name = 'link_domain');
SET @sql = IF(@cnt = 0, 'ALTER TABLE `posts` ADD COLUMN `link_domain` VARCHAR(255) NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- link_image_url
SET @cnt = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @schema AND table_name = 'posts' AND column_name = 'link_image_url');
SET @sql = IF(@cnt = 0, 'ALTER TABLE `posts` ADD COLUMN `link_image_url` VARCHAR(2048) NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add index idx_profile_user_id if missing
SET @cnt = (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = @schema AND table_name = 'posts' AND index_name = 'idx_profile_user_id');
SET @sql = IF(@cnt = 0, 'ALTER TABLE `posts` ADD KEY `idx_profile_user_id` (`profile_user_id`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- End of migration
