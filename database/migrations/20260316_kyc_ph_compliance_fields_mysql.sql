-- ============================================================
-- Philippine KYC Compliance Fields
-- Adds fields mandated by RA 9160 (AMLA), BSP KYC guidelines,
-- BIR (RMC 60-2020), and DTI requirements for online sellers.
-- ============================================================

ALTER TABLE `kyc_profiles`
    ADD COLUMN `middle_name`      varchar(191) DEFAULT NULL AFTER `first_name`,
    ADD COLUMN `place_of_birth`   varchar(191) DEFAULT NULL AFTER `dob`,
    ADD COLUMN `nationality`      varchar(100) DEFAULT NULL AFTER `place_of_birth`,
    ADD COLUMN `phone`            varchar(30)  DEFAULT NULL AFTER `nationality`,
    ADD COLUMN `address_street`   varchar(255) DEFAULT NULL AFTER `phone`,
    ADD COLUMN `address_city`     varchar(100) DEFAULT NULL AFTER `address_street`,
    ADD COLUMN `address_province` varchar(100) DEFAULT NULL AFTER `address_city`,
    ADD COLUMN `address_zip`      varchar(10)  DEFAULT NULL AFTER `address_province`,
    ADD COLUMN `tin`              varchar(25)  DEFAULT NULL AFTER `address_zip`,
    ADD COLUMN `id_type`          varchar(80)  DEFAULT NULL AFTER `tin`,
    ADD COLUMN `business_name`    varchar(191) DEFAULT NULL AFTER `documents`,
    ADD COLUMN `business_reg`     varchar(100) DEFAULT NULL AFTER `business_name`;
