-- Migration: 20260320_add_product_type_and_pricing_models.sql
-- Adds product_type (physical/digital/virtual/subscription) to products table.
-- Adds 'referral' and 'standard' to the pricing_model enum.
-- Safe to run multiple times.

DROP PROCEDURE IF EXISTS _product_type_upgrade;

DELIMITER //
CREATE PROCEDURE _product_type_upgrade()
BEGIN
    DECLARE _db VARCHAR(255) DEFAULT DATABASE();

    -- Add product_type column
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = _db AND TABLE_NAME = 'products' AND COLUMN_NAME = 'product_type'
    ) THEN
        ALTER TABLE `products`
            ADD COLUMN `product_type` ENUM('physical','digital','virtual','subscription')
                NOT NULL DEFAULT 'physical' AFTER `seller_id`;
    END IF;

    -- Extend pricing_model enum to include referral and standard alongside legacy values
    -- MySQL requires listing ALL current values when modifying an ENUM.
    -- We keep the old values for backward compatibility with existing products.
    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = _db AND TABLE_NAME = 'products' AND COLUMN_NAME = 'pricing_model'
    ) THEN
        ALTER TABLE `products`
            MODIFY COLUMN `pricing_model`
                ENUM('hands_off','active_discovery','full_service','markup','standard','referral')
                NOT NULL DEFAULT 'standard';
    END IF;

    -- Migrate legacy pricing models to new values for cleaner data
    UPDATE `products` SET `pricing_model` = 'standard'  WHERE `pricing_model` = 'hands_off';
    UPDATE `products` SET `pricing_model` = 'standard'  WHERE `pricing_model` = 'active_discovery';
    UPDATE `products` SET `pricing_model` = 'standard'  WHERE `pricing_model` = 'full_service';

    -- Set pricing_rate automatically based on new model (standard=10, referral=15)
    UPDATE `products` SET `pricing_rate` = 10.00 WHERE `pricing_model` = 'standard';
    UPDATE `products` SET `pricing_rate` = 15.00 WHERE `pricing_model` = 'referral';

END //
DELIMITER ;

CALL _product_type_upgrade();
DROP PROCEDURE IF EXISTS _product_type_upgrade;
