-- Migration: Add editor_show_hidden column to users table
-- This stores the user's preference for showing hidden files (starting with .)

ALTER TABLE users ADD COLUMN IF NOT EXISTS editor_show_hidden TINYINT(1) DEFAULT 0;
