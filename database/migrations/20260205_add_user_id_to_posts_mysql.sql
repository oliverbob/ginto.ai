-- Migration: add `user_id` column to `posts` (MySQL/MariaDB)
-- File: database/migrations/20260205_add_user_id_to_posts_mysql.sql
-- Up/Down in same file. Safe checks used so re-running is idempotent.

-- ===================== UP =====================
-- Adds nullable unsigned `user_id` column after `updated_at` and an index.
SET @DB := DATABASE();

-- Prepare statement to add column if it does not exist
SET @add_col_stmt = (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @DB AND table_name = 'posts' AND column_name = 'user_id') = 0,
    'ALTER TABLE `posts` ADD COLUMN `user_id` INT(10) UNSIGNED DEFAULT NULL AFTER `updated_at`;',
    'SELECT "column_exists" AS msg;'
  )
);
PREPARE stmt_add_col FROM @add_col_stmt;
EXECUTE stmt_add_col;
DEALLOCATE PREPARE stmt_add_col;

-- Prepare statement to add index if it does not exist
SET @add_idx_stmt = (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = @DB AND table_name = 'posts' AND index_name = 'idx_posts_user') = 0,
    'CREATE INDEX `idx_posts_user` ON `posts` (`user_id`);',
    'SELECT "index_exists" AS msg;'
  )
);
PREPARE stmt_add_idx FROM @add_idx_stmt;
EXECUTE stmt_add_idx;
DEALLOCATE PREPARE stmt_add_idx;

-- ===================== DOWN =====================
-- Drops the index and column if they exist (safe/idempotent).
SET @DB := DATABASE();

SET @drop_idx_stmt = (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = @DB AND table_name = 'posts' AND index_name = 'idx_posts_user') > 0,
    'DROP INDEX `idx_posts_user` ON `posts`;',
    'SELECT "index_missing" AS msg;'
  )
);
PREPARE stmt_drop_idx FROM @drop_idx_stmt;
EXECUTE stmt_drop_idx;
DEALLOCATE PREPARE stmt_drop_idx;

SET @drop_col_stmt = (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @DB AND table_name = 'posts' AND column_name = 'user_id') > 0,
    'ALTER TABLE `posts` DROP COLUMN `user_id`;',
    'SELECT "column_missing" AS msg;'
  )
);
PREPARE stmt_drop_col FROM @drop_col_stmt;
EXECUTE stmt_drop_col;
DEALLOCATE PREPARE stmt_drop_col;

-- Notes:
-- 1) Verify with:
--    SELECT column_name, column_type, is_nullable FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='posts' AND column_name='user_id';
-- 2) This migration intentionally does NOT add a foreign key to `users` to match the box schema.
-- 3) To rollback, execute the DOWN section (or run this file in a MySQL client and re-run appropriate statements).
