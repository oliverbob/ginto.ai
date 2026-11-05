-- =============================================================================
-- Delivery system + push notifications for Ginto Mall
-- Creates: delivery_shipments, rider_profiles, rider_gps_updates,
--          push_subscriptions, delivery_tracking_tokens, cart_first_seller_log
-- Safe to run multiple times.
-- =============================================================================

DROP PROCEDURE IF EXISTS _delivery_push_upgrade;

DELIMITER //
CREATE PROCEDURE _delivery_push_upgrade()
BEGIN
    DECLARE _db VARCHAR(255) DEFAULT DATABASE();

    -- -------------------------------------------------------------------------
    -- Rider profiles (users with is_rider flag or records here are riders)
    -- -------------------------------------------------------------------------
    CREATE TABLE IF NOT EXISTS `rider_profiles` (
        `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id`       INT UNSIGNED NOT NULL,
        `vehicle_type`  ENUM('motorcycle','bicycle','car','van','tricycle','walk') NOT NULL DEFAULT 'motorcycle',
        `plate_number`  VARCHAR(20) DEFAULT NULL,
        `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
        `is_available`  TINYINT(1) NOT NULL DEFAULT 0,
        `last_lat`      DECIMAL(10,7) DEFAULT NULL,
        `last_lng`      DECIMAL(10,7) DEFAULT NULL,
        `last_seen_at`  DATETIME DEFAULT NULL,
        `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`    DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_rider_user` (`user_id`),
        CONSTRAINT `fk_rider_profiles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- -------------------------------------------------------------------------
    -- Delivery shipments — one record per mall_order
    -- -------------------------------------------------------------------------
    CREATE TABLE IF NOT EXISTS `delivery_shipments` (
        `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `order_id`         BIGINT UNSIGNED NOT NULL,
        `tracking_token`   VARCHAR(64) NOT NULL,
        `rider_id`         INT UNSIGNED DEFAULT NULL COMMENT 'users.id of assigned rider',
        `status`           ENUM(
                               'pending',
                               'ready_for_pickup',
                               'picked_up',
                               'in_transit',
                               'out_for_delivery',
                               'delivered',
                               'failed_delivery',
                               'returned'
                           ) NOT NULL DEFAULT 'pending',
        `pickup_address`   TEXT DEFAULT NULL,
        `delivery_address` TEXT DEFAULT NULL,
        `notes`            TEXT DEFAULT NULL,
        `estimated_delivery` DATETIME DEFAULT NULL,
        `actual_delivery`    DATETIME DEFAULT NULL,
        `claimed_at`         DATETIME DEFAULT NULL,
        `picked_up_at`       DATETIME DEFAULT NULL,
        `delivered_at`       DATETIME DEFAULT NULL,
        `created_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`         DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_delivery_order` (`order_id`),
        UNIQUE KEY `uniq_delivery_token` (`tracking_token`),
        KEY `idx_delivery_rider` (`rider_id`),
        KEY `idx_delivery_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- -------------------------------------------------------------------------
    -- Rider GPS updates — real-time location stream
    -- -------------------------------------------------------------------------
    CREATE TABLE IF NOT EXISTS `rider_gps_updates` (
        `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `rider_id`    INT UNSIGNED NOT NULL COMMENT 'users.id',
        `shipment_id` BIGINT UNSIGNED DEFAULT NULL,
        `lat`         DECIMAL(10,7) NOT NULL,
        `lng`         DECIMAL(10,7) NOT NULL,
        `accuracy`    FLOAT DEFAULT NULL COMMENT 'meters',
        `speed`       FLOAT DEFAULT NULL COMMENT 'm/s',
        `bearing`     FLOAT DEFAULT NULL COMMENT 'degrees',
        `recorded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_gps_rider_shipment` (`rider_id`, `shipment_id`),
        KEY `idx_gps_recorded` (`recorded_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- -------------------------------------------------------------------------
    -- Push subscriptions (Web Push API / VAPID)
    -- -------------------------------------------------------------------------
    CREATE TABLE IF NOT EXISTS `push_subscriptions` (
        `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id`      INT UNSIGNED DEFAULT NULL COMMENT 'NULL = guest/visitor',
        `endpoint`     TEXT NOT NULL,
        `p256dh_key`   TEXT NOT NULL,
        `auth_key`     VARCHAR(255) NOT NULL,
        `scope`        VARCHAR(50) NOT NULL DEFAULT 'mall' COMMENT 'mall|global',
        `device_hint`  VARCHAR(50) DEFAULT NULL COMMENT 'android|ios|desktop',
        `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`   DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_push_endpoint` (endpoint(200)),
        KEY `idx_push_user` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- We store VAPID keys in .env, but record generation time here
    CREATE TABLE IF NOT EXISTS `push_vapid_keys` (
        `id`          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `public_key`  TEXT NOT NULL,
        `private_key` TEXT NOT NULL,
        `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- -------------------------------------------------------------------------
    -- Cart first-seller log — persists which seller owns the referral
    -- This supplements the $_SESSION['mall_checkout_seller_id'] for cases
    -- where the visitor does not yet have a session (coming from app, etc.)
    -- -------------------------------------------------------------------------
    CREATE TABLE IF NOT EXISTS `cart_first_seller_log` (
        `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `visitor_id` VARCHAR(64) NOT NULL COMMENT 'session id or device id',
        `seller_id`  INT UNSIGNED NOT NULL,
        `product_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'first product added',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `expires_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_cart_visitor` (`visitor_id`),
        KEY `idx_cart_seller` (`seller_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- -------------------------------------------------------------------------
    -- Shipment status history log
    -- -------------------------------------------------------------------------
    CREATE TABLE IF NOT EXISTS `delivery_status_history` (
        `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `shipment_id` BIGINT UNSIGNED NOT NULL,
        `actor_id`    INT UNSIGNED DEFAULT NULL COMMENT 'user who changed status',
        `actor_role`  ENUM('seller','buyer','rider','admin','system') NOT NULL DEFAULT 'system',
        `old_status`  VARCHAR(30) DEFAULT NULL,
        `new_status`  VARCHAR(30) NOT NULL,
        `note`        TEXT DEFAULT NULL,
        `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_dsh_shipment` (`shipment_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- -------------------------------------------------------------------------
    -- Add is_rider column to users if not present
    -- -------------------------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = _db AND TABLE_NAME = 'users' AND COLUMN_NAME = 'is_rider'
    ) THEN
        ALTER TABLE `users` ADD COLUMN `is_rider` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_buyer`;
    END IF;

    -- -------------------------------------------------------------------------
    -- Add tracking_token to mall_orders if not present
    -- -------------------------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = _db AND TABLE_NAME = 'mall_orders' AND COLUMN_NAME = 'tracking_token'
    ) THEN
        ALTER TABLE `mall_orders` ADD COLUMN `tracking_token` VARCHAR(64) DEFAULT NULL AFTER `status`;
        ALTER TABLE `mall_orders` ADD UNIQUE KEY `uniq_mall_order_token` (`tracking_token`);
    END IF;

END //
DELIMITER ;

CALL _delivery_push_upgrade();
DROP PROCEDURE IF EXISTS _delivery_push_upgrade;
