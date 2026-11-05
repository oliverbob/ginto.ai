-- Migration: Create user_addons table for addon subscriptions (ImageGen, etc.)
-- Created: 2026-01-24

-- Check if table exists before creating
CREATE TABLE IF NOT EXISTS `user_addons` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `addon_type` VARCHAR(50) NOT NULL COMMENT 'Addon identifier: imagegen, storage, etc.',
    `paypal_subscription_id` VARCHAR(50) NULL COMMENT 'PayPal subscription ID for this addon',
    `status` ENUM('pending', 'active', 'cancelled', 'suspended', 'expired') DEFAULT 'pending',
    `amount_usd` DECIMAL(10,2) NOT NULL COMMENT 'Monthly amount in USD',
    `billing_interval` VARCHAR(20) DEFAULT 'MONTH',
    `subscription_start_date` DATETIME NULL,
    `subscription_next_billing` DATETIME NULL,
    `metadata` JSON NULL COMMENT 'Additional addon-specific data',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_user_addons_user` (`user_id`),
    INDEX `idx_user_addons_type` (`addon_type`),
    INDEX `idx_user_addons_status` (`status`),
    INDEX `idx_user_addons_paypal` (`paypal_subscription_id`),
    UNIQUE KEY `uk_user_addon_type` (`user_id`, `addon_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create addon_plans table for storing PayPal plan IDs
CREATE TABLE IF NOT EXISTS `addon_plans` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `addon_type` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Addon identifier: imagegen, storage, etc.',
    `name` VARCHAR(100) NOT NULL COMMENT 'Display name',
    `description` TEXT NULL COMMENT 'Addon description',
    `amount_usd` DECIMAL(10,2) NOT NULL COMMENT 'Monthly amount in USD',
    `paypal_plan_id` VARCHAR(50) NULL COMMENT 'PayPal plan ID for production',
    `paypal_plan_id_sandbox` VARCHAR(50) NULL COMMENT 'PayPal plan ID for sandbox',
    `features` JSON NULL COMMENT 'List of features for display',
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert ImageGen addon plan
INSERT INTO `addon_plans` (`addon_type`, `name`, `description`, `amount_usd`, `features`, `is_active`)
VALUES (
    'imagegen',
    'ImageGen Pro',
    'Professional AI Image Generation with GPU acceleration',
    500.00,
    JSON_ARRAY(
        'Unlimited AI image generation',
        'GPU-accelerated processing (10x faster)',
        'Image-to-image editing',
        'Inpainting and outpainting',
        'Multiple style presets',
        'Priority support',
        'Dedicated GPU resources'
    ),
    1
) ON DUPLICATE KEY UPDATE 
    `name` = VALUES(`name`),
    `description` = VALUES(`description`),
    `amount_usd` = VALUES(`amount_usd`),
    `features` = VALUES(`features`);
