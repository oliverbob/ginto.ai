-- Migration: Add doc_types column to kyc_profiles
-- Date: 2026-03-17
-- Purpose: Store which document categories the seller uploaded (JSON array)

DROP PROCEDURE IF EXISTS _kyc_add_doc_types;
DELIMITER $$
CREATE PROCEDURE _kyc_add_doc_types()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'kyc_profiles'
          AND COLUMN_NAME  = 'doc_types'
    ) THEN
        ALTER TABLE `kyc_profiles`
            ADD COLUMN `doc_types` text DEFAULT NULL
                COMMENT 'JSON array of document category keys uploaded by seller'
                AFTER `documents`;
    END IF;
END$$
DELIMITER ;
CALL _kyc_add_doc_types();
DROP PROCEDURE IF EXISTS _kyc_add_doc_types;
