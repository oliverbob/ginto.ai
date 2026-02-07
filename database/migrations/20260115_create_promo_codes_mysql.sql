-- Promo codes table for promotional discounts and referral codes
-- Used for validating promo codes during registration/checkout
-- NOTE: All datetime fields use Asia/Manila timezone. PHP code handles timezone conversion.

CREATE TABLE IF NOT EXISTS promo_codes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL COMMENT 'The promo code string (case-insensitive lookup)',
    description VARCHAR(255) DEFAULT NULL COMMENT 'Internal description of the promo',
    discount_type ENUM('percentage', 'fixed') NOT NULL DEFAULT 'percentage' COMMENT 'Type of discount',
    discount_value DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Discount amount (percentage or fixed PHP)',
    min_package_amount DECIMAL(10,2) DEFAULT NULL COMMENT 'Minimum package amount required to use this code',
    max_uses INT UNSIGNED DEFAULT NULL COMMENT 'Maximum total uses allowed (NULL = unlimited)',
    used_count INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Current number of times used',
    max_uses_per_user INT UNSIGNED DEFAULT 1 COMMENT 'Max uses per user (NULL = unlimited)',
    valid_from DATETIME DEFAULT NULL COMMENT 'Start date in Asia/Manila timezone (NULL = immediately valid)',
    valid_until DATETIME DEFAULT NULL COMMENT 'Expiry date in Asia/Manila timezone (NULL = never expires)',
    applicable_packages JSON DEFAULT NULL COMMENT 'Array of package names this code applies to (NULL = all packages)',
    created_by INT UNSIGNED DEFAULT NULL COMMENT 'Admin user who created this code',
    is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = active, 0 = disabled',
    created_at DATETIME NOT NULL COMMENT 'Record creation time in Asia/Manila timezone (set by PHP)',
    modified_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last modification time (auto-updates)',
    
    UNIQUE KEY uk_code (code),
    INDEX idx_is_active (is_active),
    INDEX idx_valid_dates (valid_from, valid_until),
    INDEX idx_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Promo code usage tracking table
-- Records each use of a promo code for audit and per-user limits

CREATE TABLE IF NOT EXISTS promo_code_usages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    promo_code_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    order_id INT UNSIGNED DEFAULT NULL COMMENT 'Associated order if applicable',
    discount_applied DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Actual discount amount applied',
    used_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_promo_code_id (promo_code_id),
    INDEX idx_user_id (user_id),
    INDEX idx_order_id (order_id),
    
    FOREIGN KEY (promo_code_id) REFERENCES promo_codes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert some sample promo codes (optional - remove in production if not needed)
-- INSERT INTO promo_codes (code, description, discount_type, discount_value, max_uses, valid_until, is_active) VALUES
-- ('WELCOME10', 'Welcome discount - 10% off', 'percentage', 10.00, 1000, '2026-12-31 23:59:59', 1),
-- ('STARTER50', 'Starter package - P50 off', 'fixed', 50.00, 500, '2026-06-30 23:59:59', 1);
