-- 20260205_02_add_user_id_to_posts_mysql.sql
-- Adds nullable user_id column to posts and an index for MySQL

-- Safe: only run on MySQL. This file is selected by migration runner when DB_TYPE is mysql.

ALTER TABLE posts
  ADD COLUMN IF NOT EXISTS user_id INT(10) UNSIGNED DEFAULT NULL AFTER updated_at;

-- Create index if not present
CREATE INDEX IF NOT EXISTS idx_posts_user ON posts (user_id);
