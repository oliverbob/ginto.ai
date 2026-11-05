-- ============================================================
-- Delivery zones expansion, processing fees, saved buyer
-- addresses, per-product zone overrides, and minimum order.
-- Safe to run multiple times (idempotent).
-- ============================================================

DROP PROCEDURE IF EXISTS _zones_fees_upgrade;

DELIMITER //
CREATE PROCEDURE _zones_fees_upgrade()
BEGIN
    DECLARE _db VARCHAR(255) DEFAULT DATABASE();

    -- -------------------------------------------------------
    -- 1. mall_orders: processing_fee_amount for PayMongo/PayPal
    -- -------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = _db AND TABLE_NAME = 'mall_orders' AND COLUMN_NAME = 'processing_fee_amount'
    ) THEN
        ALTER TABLE `mall_orders`
            ADD COLUMN `processing_fee_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00
                COMMENT 'Gateway processing fee: P25 PayMongo, P50+7% PayPal'
                AFTER `platform_fee_amount`,
            ADD COLUMN `delivery_fee_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00
                COMMENT 'Grab-style delivery fee estimate (distance-based)'
                AFTER `processing_fee_amount`;
    END IF;

    -- -------------------------------------------------------
    -- 2. mall_orders: seller_payout_eta for payment wait info
    -- -------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = _db AND TABLE_NAME = 'mall_orders' AND COLUMN_NAME = 'seller_payout_eta'
    ) THEN
        ALTER TABLE `mall_orders`
            ADD COLUMN `seller_payout_eta` VARCHAR(50) DEFAULT NULL
                COMMENT 'Expected payout window e.g. 7-12 days after delivery'
                AFTER `delivered_at`;
    END IF;

    -- -------------------------------------------------------
    -- 3. seller_delivery_zones: add lat/lng for main zone
    --    distance calculation (haversine)
    -- -------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = _db AND TABLE_NAME = 'seller_delivery_zones' AND COLUMN_NAME = 'lat'
    ) THEN
        ALTER TABLE `seller_delivery_zones`
            ADD COLUMN `lat` DECIMAL(10,7) DEFAULT NULL AFTER `is_home`,
            ADD COLUMN `lng` DECIMAL(10,7) DEFAULT NULL AFTER `lat`;
    END IF;

    -- -------------------------------------------------------
    -- 4. product_delivery_zones: per-product zone overrides
    --    (optional; if empty, uses seller-level zones)
    -- -------------------------------------------------------
    CREATE TABLE IF NOT EXISTS `product_delivery_zones` (
        `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
        `product_id`   BIGINT UNSIGNED NOT NULL,
        `barangay_id`  INT UNSIGNED  NOT NULL,
        `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_product_barangay` (`product_id`, `barangay_id`),
        KEY `idx_pdz_product` (`product_id`),
        KEY `idx_pdz_barangay` (`barangay_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- -------------------------------------------------------
    -- 5. buyer_saved_addresses: remember address & payment
    -- -------------------------------------------------------
    CREATE TABLE IF NOT EXISTS `buyer_saved_addresses` (
        `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id`        INT UNSIGNED NOT NULL,
        `label`          VARCHAR(50)  NOT NULL DEFAULT 'Home'
            COMMENT 'e.g. Home, Office, etc.',
        `is_default`     TINYINT(1)   NOT NULL DEFAULT 0,
        `full_name`      VARCHAR(191) NOT NULL,
        `phone`          VARCHAR(30)  DEFAULT NULL,
        `address_line1`  VARCHAR(255) NOT NULL,
        `address_line2`  VARCHAR(255) DEFAULT NULL,
        `city`           VARCHAR(100) NOT NULL,
        `province`       VARCHAR(100) DEFAULT NULL,
        `postal_code`    VARCHAR(20)  DEFAULT NULL,
        `country`        CHAR(3)      NOT NULL DEFAULT 'PH',
        `barangay_id`    INT UNSIGNED DEFAULT NULL,
        `lat`            DECIMAL(10,7) DEFAULT NULL,
        `lng`            DECIMAL(10,7) DEFAULT NULL,
        `payment_method` VARCHAR(50)  DEFAULT NULL
            COMMENT 'Last used payment method',
        `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`     DATETIME     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_bsa_user` (`user_id`, `is_default`),
        CONSTRAINT `fk_bsa_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- -------------------------------------------------------
    -- 6. Increase seller zone limit from 10 to 50
    --    (no schema change needed — just a backend constant)
    -- -------------------------------------------------------

    -- -------------------------------------------------------
    -- 7. products: flag to use custom zones vs seller zones
    -- -------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = _db AND TABLE_NAME = 'products' AND COLUMN_NAME = 'use_custom_zones'
    ) THEN
        ALTER TABLE `products`
            ADD COLUMN `use_custom_zones` TINYINT(1) NOT NULL DEFAULT 0
                COMMENT '1 = use product_delivery_zones, 0 = inherit seller zones';
    END IF;

END //
DELIMITER ;

CALL _zones_fees_upgrade();
DROP PROCEDURE IF EXISTS _zones_fees_upgrade;
