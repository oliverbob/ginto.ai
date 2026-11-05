-- Migration: 20260331_add_checkout_processing_fee_mall_orders_mysql.sql
-- Adds checkout_processing_fee to mall_orders for processing fee tracking.

DROP PROCEDURE IF EXISTS _add_checkout_processing_fee_mall_orders;

DELIMITER //
CREATE PROCEDURE _add_checkout_processing_fee_mall_orders()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mall_orders' AND COLUMN_NAME = 'checkout_processing_fee'
    ) THEN
        ALTER TABLE `mall_orders`
            ADD COLUMN `checkout_processing_fee` DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER `processing_fee_amount`;
    END IF;
END //
DELIMITER ;

CALL _add_checkout_processing_fee_mall_orders();
DROP PROCEDURE IF EXISTS _add_checkout_processing_fee_mall_orders;
