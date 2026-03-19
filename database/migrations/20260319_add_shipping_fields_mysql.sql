-- ============================================================
-- Shipping fee fields for products and mall_orders.
-- Supports Shopee/Lazada-style chargeable weight calculation
-- (actual vs volumetric weight, zone-based rates, discrepancy
-- settlement) even before a logistics partner is configured.
-- Safe to run multiple times.
-- ============================================================

DROP PROCEDURE IF EXISTS _shipping_fields_upgrade;

DELIMITER //
CREATE PROCEDURE _shipping_fields_upgrade()
BEGIN
    DECLARE _db VARCHAR(255) DEFAULT DATABASE();

    -- -------------------------------------------------------
    -- products: weight & dimension fields
    -- Sellers must enter total packed weight (item + box + wrap).
    -- -------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = _db AND TABLE_NAME = 'products' AND COLUMN_NAME = 'weight_kg'
    ) THEN
        ALTER TABLE `products`
            ADD COLUMN `weight_kg`  DECIMAL(8,3)  DEFAULT NULL COMMENT 'Packed weight including packaging (kg)',
            ADD COLUMN `length_cm`  DECIMAL(8,2)  DEFAULT NULL COMMENT 'Packed parcel length (cm)',
            ADD COLUMN `width_cm`   DECIMAL(8,2)  DEFAULT NULL COMMENT 'Packed parcel width (cm)',
            ADD COLUMN `height_cm`  DECIMAL(8,2)  DEFAULT NULL COMMENT 'Packed parcel height (cm)';
    END IF;

    -- -------------------------------------------------------
    -- mall_orders: shipping fee tracking & settlement
    -- -------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = _db AND TABLE_NAME = 'mall_orders' AND COLUMN_NAME = 'shipping_fee_estimated'
    ) THEN
        ALTER TABLE `mall_orders`
            ADD COLUMN `shipping_fee_estimated`  DECIMAL(10,2) NOT NULL DEFAULT 0.00
                COMMENT 'Estimated shipping fee paid by buyer at checkout (ESF)',
            ADD COLUMN `shipping_fee_actual`     DECIMAL(10,2)          DEFAULT NULL
                COMMENT 'Actual fee measured by courier post-pickup (ASF); NULL until measured',
            ADD COLUMN `shipping_fee_discrepancy` DECIMAL(10,2) GENERATED ALWAYS AS (
                COALESCE(`shipping_fee_actual`, 0.00) - `shipping_fee_estimated`
            ) VIRTUAL COMMENT 'Positive = seller owes platform; negative = platform owes seller',
            ADD COLUMN `shipping_zone`           VARCHAR(50)            DEFAULT NULL
                COMMENT 'Zone code used for ESF calculation',
            ADD COLUMN `chargeable_weight_kg`    DECIMAL(8,3)           DEFAULT NULL
                COMMENT 'Chargeable weight used for ESF (max of actual vs volumetric)',
            ADD COLUMN `logistics_partner`       VARCHAR(100)           DEFAULT NULL
                COMMENT 'Courier / logistics partner slug (NULL = platform default rates)',
            ADD COLUMN `shipping_dimensions_json` JSON                  DEFAULT NULL
                COMMENT 'Snapshot of weight/dimension inputs used for ESF';

        -- Index for settlement queries (orders with discrepancy)
        ALTER TABLE `mall_orders`
            ADD INDEX `idx_mall_orders_logistics` (`logistics_partner`, `status`);
    END IF;

    -- -------------------------------------------------------
    -- mall_order_items: per-item weight snapshot for audit
    -- -------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = _db AND TABLE_NAME = 'mall_order_items' AND COLUMN_NAME = 'weight_kg_snapshot'
    ) THEN
        ALTER TABLE `mall_order_items`
            ADD COLUMN `weight_kg_snapshot`  DECIMAL(8,3) DEFAULT NULL
                COMMENT 'Weight at time of order (packed, kg)',
            ADD COLUMN `length_cm_snapshot`  DECIMAL(8,2) DEFAULT NULL,
            ADD COLUMN `width_cm_snapshot`   DECIMAL(8,2) DEFAULT NULL,
            ADD COLUMN `height_cm_snapshot`  DECIMAL(8,2) DEFAULT NULL;
    END IF;

END //
DELIMITER ;

CALL _shipping_fields_upgrade();
DROP PROCEDURE IF EXISTS _shipping_fields_upgrade;
