-- Migration: Restore ImageGen addon plan (idempotent)
-- Created: 2026-01-25
-- NOTE: This migration inserts the `imagegen` row into `addon_plans` if it does not exist.
-- Backup your database before applying.

INSERT INTO `addon_plans` (`addon_type`, `name`, `description`, `amount_usd`, `features`, `is_active`, `created_at`, `updated_at`)
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
    1,
    NOW(),
    NOW()
) 
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `description` = VALUES(`description`),
    `amount_usd` = VALUES(`amount_usd`),
    `features` = VALUES(`features`),
    `is_active` = VALUES(`is_active`),
    `updated_at` = NOW();

-- Manual rollback (if needed):
-- DELETE FROM `addon_plans` WHERE `addon_type` = 'imagegen';
