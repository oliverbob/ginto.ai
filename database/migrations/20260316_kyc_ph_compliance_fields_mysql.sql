-- ============================================================
-- Philippine KYC Compliance Fields (idempotent)
-- Adds fields mandated by RA 9160 (AMLA), BSP KYC guidelines,
-- BIR (RMC 60-2020), and DTI requirements for online sellers.
-- Safe to run multiple times — skips columns that already exist.
-- ============================================================

DROP PROCEDURE IF EXISTS _kyc_add_col;

DELIMITER //
CREATE PROCEDURE _kyc_add_col()
BEGIN
    DECLARE _db VARCHAR(255) DEFAULT DATABASE();

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=_db AND TABLE_NAME='kyc_profiles' AND COLUMN_NAME='middle_name') THEN
        ALTER TABLE `kyc_profiles` ADD COLUMN `middle_name` varchar(191) DEFAULT NULL AFTER `first_name`;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=_db AND TABLE_NAME='kyc_profiles' AND COLUMN_NAME='place_of_birth') THEN
        ALTER TABLE `kyc_profiles` ADD COLUMN `place_of_birth` varchar(191) DEFAULT NULL AFTER `dob`;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=_db AND TABLE_NAME='kyc_profiles' AND COLUMN_NAME='nationality') THEN
        ALTER TABLE `kyc_profiles` ADD COLUMN `nationality` varchar(100) DEFAULT NULL AFTER `place_of_birth`;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=_db AND TABLE_NAME='kyc_profiles' AND COLUMN_NAME='phone') THEN
        ALTER TABLE `kyc_profiles` ADD COLUMN `phone` varchar(30) DEFAULT NULL AFTER `nationality`;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=_db AND TABLE_NAME='kyc_profiles' AND COLUMN_NAME='address_street') THEN
        ALTER TABLE `kyc_profiles` ADD COLUMN `address_street` varchar(255) DEFAULT NULL AFTER `phone`;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=_db AND TABLE_NAME='kyc_profiles' AND COLUMN_NAME='address_city') THEN
        ALTER TABLE `kyc_profiles` ADD COLUMN `address_city` varchar(100) DEFAULT NULL AFTER `address_street`;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=_db AND TABLE_NAME='kyc_profiles' AND COLUMN_NAME='address_province') THEN
        ALTER TABLE `kyc_profiles` ADD COLUMN `address_province` varchar(100) DEFAULT NULL AFTER `address_city`;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=_db AND TABLE_NAME='kyc_profiles' AND COLUMN_NAME='address_zip') THEN
        ALTER TABLE `kyc_profiles` ADD COLUMN `address_zip` varchar(10) DEFAULT NULL AFTER `address_province`;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=_db AND TABLE_NAME='kyc_profiles' AND COLUMN_NAME='tin') THEN
        ALTER TABLE `kyc_profiles` ADD COLUMN `tin` varchar(25) DEFAULT NULL AFTER `address_zip`;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=_db AND TABLE_NAME='kyc_profiles' AND COLUMN_NAME='id_type') THEN
        ALTER TABLE `kyc_profiles` ADD COLUMN `id_type` varchar(80) DEFAULT NULL AFTER `tin`;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=_db AND TABLE_NAME='kyc_profiles' AND COLUMN_NAME='business_name') THEN
        ALTER TABLE `kyc_profiles` ADD COLUMN `business_name` varchar(191) DEFAULT NULL AFTER `documents`;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=_db AND TABLE_NAME='kyc_profiles' AND COLUMN_NAME='business_reg') THEN
        ALTER TABLE `kyc_profiles` ADD COLUMN `business_reg` varchar(100) DEFAULT NULL AFTER `business_name`;
    END IF;
END //
DELIMITER ;

CALL _kyc_add_col();
DROP PROCEDURE IF EXISTS _kyc_add_col;
