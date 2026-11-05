-- ============================================================
-- Mall commerce schema: storefronts, wallet, checkout sessions,
-- orders, delivery workflow, and product pricing model fields.
-- Safe to run multiple times.
-- ============================================================

DROP PROCEDURE IF EXISTS _mall_commerce_upgrade;

DELIMITER //
CREATE PROCEDURE _mall_commerce_upgrade()
BEGIN
    DECLARE _db VARCHAR(255) DEFAULT DATABASE();

    CREATE TABLE IF NOT EXISTS `seller_storefronts` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` INT UNSIGNED NOT NULL,
        `slug` VARCHAR(64) NOT NULL,
        `display_name` VARCHAR(191) NOT NULL,
        `description` TEXT DEFAULT NULL,
        `banner_image` VARCHAR(255) DEFAULT NULL,
        `logo_image` VARCHAR(255) DEFAULT NULL,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_seller_storefronts_user` (`user_id`),
        UNIQUE KEY `uniq_seller_storefronts_slug` (`slug`),
        CONSTRAINT `fk_seller_storefronts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS `wallet_accounts` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` INT UNSIGNED NOT NULL,
        `currency` CHAR(3) NOT NULL DEFAULT 'PHP',
        `balance` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        `status` ENUM('active','locked','closed') NOT NULL DEFAULT 'active',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_wallet_accounts_user` (`user_id`),
        CONSTRAINT `fk_wallet_accounts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS `wallet_transactions` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `wallet_account_id` BIGINT UNSIGNED NOT NULL,
        `user_id` INT UNSIGNED NOT NULL,
        `type` ENUM('topup','purchase','sale_credit','refund','adjustment') NOT NULL,
        `direction` ENUM('credit','debit') NOT NULL,
        `status` ENUM('pending','completed','failed','cancelled') NOT NULL DEFAULT 'completed',
        `currency` CHAR(3) NOT NULL DEFAULT 'PHP',
        `amount` DECIMAL(14,2) NOT NULL,
        `balance_after` DECIMAL(14,2) DEFAULT NULL,
        `reference_type` VARCHAR(50) DEFAULT NULL,
        `reference_id` VARCHAR(100) DEFAULT NULL,
        `description` VARCHAR(255) DEFAULT NULL,
        `metadata` JSON DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_wallet_transactions_user` (`user_id`, `created_at`),
        KEY `idx_wallet_transactions_reference` (`reference_type`, `reference_id`),
        CONSTRAINT `fk_wallet_transactions_account` FOREIGN KEY (`wallet_account_id`) REFERENCES `wallet_accounts` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_wallet_transactions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS `mall_payment_sessions` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `session_ref` VARCHAR(64) NOT NULL,
        `buyer_id` INT UNSIGNED NOT NULL,
        `purpose` ENUM('order_checkout','wallet_topup') NOT NULL,
        `payment_method` ENUM('ginto_pay_qr','ginto_pay_card','paypal','wallet') NOT NULL,
        `gateway` VARCHAR(30) DEFAULT NULL,
        `currency` CHAR(3) NOT NULL DEFAULT 'PHP',
        `amount_total` DECIMAL(14,2) NOT NULL,
        `status` ENUM('pending','processing','completed','failed','cancelled') NOT NULL DEFAULT 'pending',
        `gateway_reference` VARCHAR(191) DEFAULT NULL,
        `gateway_payment_id` VARCHAR(191) DEFAULT NULL,
        `order_ids_json` JSON DEFAULT NULL,
        `payload_json` JSON DEFAULT NULL,
        `gateway_payload_json` JSON DEFAULT NULL,
        `expires_at` DATETIME DEFAULT NULL,
        `completed_at` DATETIME DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_mall_payment_sessions_ref` (`session_ref`),
        KEY `idx_mall_payment_sessions_buyer` (`buyer_id`, `created_at`),
        KEY `idx_mall_payment_sessions_gateway_ref` (`gateway_reference`),
        CONSTRAINT `fk_mall_payment_sessions_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS `mall_orders` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `order_code` VARCHAR(40) NOT NULL,
        `checkout_ref` VARCHAR(64) DEFAULT NULL,
        `buyer_id` INT UNSIGNED NOT NULL,
        `seller_id` INT UNSIGNED NOT NULL,
        `storefront_id` BIGINT UNSIGNED DEFAULT NULL,
        `delivery_assignee_user_id` INT UNSIGNED DEFAULT NULL,
        `currency` CHAR(3) NOT NULL DEFAULT 'PHP',
        `subtotal_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        `platform_fee_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        `seller_net_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        `buyer_total_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        `payment_status` ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
        `status` ENUM('pending_payment','paid','processing','ready_for_pickup','in_transit','delivered','completed','cancelled','refunded') NOT NULL DEFAULT 'pending_payment',
        `payment_method` VARCHAR(50) DEFAULT NULL,
        `payment_reference` VARCHAR(191) DEFAULT NULL,
        `shipping_address_json` JSON DEFAULT NULL,
        `buyer_notes` TEXT DEFAULT NULL,
        `seller_notes` TEXT DEFAULT NULL,
        `delivery_notes` TEXT DEFAULT NULL,
        `paid_at` DATETIME DEFAULT NULL,
        `delivered_at` DATETIME DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_mall_orders_code` (`order_code`),
        KEY `idx_mall_orders_buyer` (`buyer_id`, `created_at`),
        KEY `idx_mall_orders_seller` (`seller_id`, `created_at`),
        KEY `idx_mall_orders_delivery` (`delivery_assignee_user_id`, `status`),
        KEY `idx_mall_orders_checkout` (`checkout_ref`),
        CONSTRAINT `fk_mall_orders_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_mall_orders_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_mall_orders_storefront` FOREIGN KEY (`storefront_id`) REFERENCES `seller_storefronts` (`id`) ON DELETE SET NULL,
        CONSTRAINT `fk_mall_orders_delivery_user` FOREIGN KEY (`delivery_assignee_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS `mall_order_items` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `order_id` BIGINT UNSIGNED NOT NULL,
        `product_id` BIGINT UNSIGNED NOT NULL,
        `seller_id` INT UNSIGNED NOT NULL,
        `title_snapshot` VARCHAR(255) NOT NULL,
        `quantity` INT NOT NULL DEFAULT 1,
        `currency` CHAR(3) NOT NULL DEFAULT 'PHP',
        `base_unit_price` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        `charged_unit_price` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        `line_subtotal` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        `platform_fee_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        `seller_net_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        `pricing_model` ENUM('hands_off','active_discovery','full_service','markup') NOT NULL DEFAULT 'hands_off',
        `pricing_rate` DECIMAL(5,2) NOT NULL DEFAULT 12.00,
        `markup_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
        `image_url` VARCHAR(255) DEFAULT NULL,
        `metadata` JSON DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_mall_order_items_order` (`order_id`),
        KEY `idx_mall_order_items_product` (`product_id`),
        CONSTRAINT `fk_mall_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `mall_orders` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_mall_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_mall_order_items_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS `mall_order_status_history` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `order_id` BIGINT UNSIGNED NOT NULL,
        `actor_user_id` INT UNSIGNED DEFAULT NULL,
        `actor_type` ENUM('system','buyer','seller','delivery','admin') NOT NULL DEFAULT 'system',
        `from_status` VARCHAR(50) DEFAULT NULL,
        `to_status` VARCHAR(50) NOT NULL,
        `message` VARCHAR(255) DEFAULT NULL,
        `metadata` JSON DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_mall_order_status_history_order` (`order_id`, `created_at`),
        CONSTRAINT `fk_mall_order_status_history_order` FOREIGN KEY (`order_id`) REFERENCES `mall_orders` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_mall_order_status_history_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = _db AND TABLE_NAME = 'products' AND COLUMN_NAME = 'pricing_model') THEN
        ALTER TABLE `products`
            ADD COLUMN `pricing_model` ENUM('hands_off','active_discovery','full_service','markup') NOT NULL DEFAULT 'hands_off' AFTER `price`,
            ADD COLUMN `pricing_rate` DECIMAL(5,2) NOT NULL DEFAULT 12.00 AFTER `pricing_model`,
            ADD COLUMN `markup_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER `pricing_rate`;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = _db AND TABLE_NAME = 'kyc_profiles' AND COLUMN_NAME = 'delivery_service_area') THEN
        ALTER TABLE `kyc_profiles`
            ADD COLUMN `delivery_service_area` VARCHAR(191) DEFAULT NULL AFTER `business_reg`;
    END IF;
END //
DELIMITER ;

CALL _mall_commerce_upgrade();
DROP PROCEDURE IF EXISTS _mall_commerce_upgrade;