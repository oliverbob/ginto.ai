-- Mall Delivery Features: quantity thresholds, delivery proofs, ratings, shipment management
-- Created: 2026-03-26

-- 1. Product quantity threshold per buyer
ALTER TABLE products
    ADD COLUMN max_qty_per_buyer INT UNSIGNED DEFAULT NULL AFTER quantity,
    ADD COLUMN request_more_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER max_qty_per_buyer;

-- 2. Delivery proofs (photos from buyer AND seller/courier)
CREATE TABLE IF NOT EXISTS delivery_proofs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    shipment_id BIGINT UNSIGNED DEFAULT NULL,
    uploaded_by_user_id BIGINT UNSIGNED NOT NULL,
    role ENUM('buyer','seller','rider','admin') NOT NULL DEFAULT 'buyer',
    photo_url VARCHAR(1024) NOT NULL,
    photo_type ENUM('product_arrival','selfie_with_customer','product_photo','damage_report','other') NOT NULL DEFAULT 'product_arrival',
    condition_rating ENUM('good','minor_damage','major_damage','wrong_item','missing_parts') DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    lat DECIMAL(10,7) DEFAULT NULL,
    lng DECIMAL(10,7) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_dp_order (order_id),
    INDEX idx_dp_shipment (shipment_id),
    INDEX idx_dp_user (uploaded_by_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Product ratings
CREATE TABLE IF NOT EXISTS product_ratings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT UNSIGNED NOT NULL,
    order_id BIGINT UNSIGNED NOT NULL,
    buyer_id BIGINT UNSIGNED NOT NULL,
    seller_id BIGINT UNSIGNED NOT NULL,
    product_rating TINYINT UNSIGNED NOT NULL DEFAULT 5 COMMENT '1-5 stars',
    seller_rating TINYINT UNSIGNED NOT NULL DEFAULT 5 COMMENT '1-5 stars',
    review_text TEXT DEFAULT NULL,
    photo_urls JSON DEFAULT NULL,
    is_anonymous TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_pr_product (product_id),
    INDEX idx_pr_order (order_id),
    INDEX idx_pr_seller (seller_id),
    UNIQUE KEY uq_rating_order_product (order_id, product_id, buyer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Request more product queue (when buyer exceeds max_qty_per_buyer)
CREATE TABLE IF NOT EXISTS product_quantity_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT UNSIGNED NOT NULL,
    buyer_id BIGINT UNSIGNED NOT NULL,
    seller_id BIGINT UNSIGNED NOT NULL,
    requested_qty INT UNSIGNED NOT NULL,
    status ENUM('pending','approved','denied') NOT NULL DEFAULT 'pending',
    seller_notes TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME DEFAULT NULL,
    INDEX idx_pqr_product (product_id),
    INDEX idx_pqr_buyer (buyer_id),
    INDEX idx_pqr_seller (seller_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Admin activity log for ledgering between sellers and buyers
CREATE TABLE IF NOT EXISTS mall_admin_activity_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(64) NOT NULL,
    actor_user_id BIGINT UNSIGNED DEFAULT NULL,
    target_user_id BIGINT UNSIGNED DEFAULT NULL,
    order_id BIGINT UNSIGNED DEFAULT NULL,
    shipment_id BIGINT UNSIGNED DEFAULT NULL,
    details JSON DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_aal_event (event_type),
    INDEX idx_aal_order (order_id),
    INDEX idx_aal_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
