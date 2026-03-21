-- Add is_buyer flag to users table to distinguish mall-only buyers from full platform users
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_buyer TINYINT(1) NOT NULL DEFAULT 0 AFTER role_id;
