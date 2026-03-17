-- Migration: Add seller_tos_agreed_at to users table
-- Date: 2026-03-17
-- Purpose: Track when a seller agreed to the ePower Mall Seller Terms of Service and Privacy Act disclosure

SET @db = DATABASE();

DROP PROCEDURE IF EXISTS _migrate_seller_tos;
DELIMITER $$
CREATE PROCEDURE _migrate_seller_tos()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'users'
          AND COLUMN_NAME  = 'seller_tos_agreed_at'
    ) THEN
        ALTER TABLE `users`
            ADD COLUMN `seller_tos_agreed_at` datetime DEFAULT NULL
                COMMENT 'Timestamp when seller agreed to ePower Mall Seller ToS & Privacy Act notice';
    END IF;
END$$
DELIMITER ;
CALL _migrate_seller_tos();
DROP PROCEDURE IF EXISTS _migrate_seller_tos;
