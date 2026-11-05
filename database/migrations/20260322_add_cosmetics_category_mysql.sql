/*
 * Migration: Add Cosmetics as a standalone category
 * Date: 2026-03-22
 *
 * Adds a dedicated "Cosmetics" category (makeup, lipstick, foundation, etc.)
 * separate from the broader "Health & Beauty" category.
 * Safe to re-run: uses INSERT IGNORE.
 */

INSERT IGNORE INTO `categories` (`name`, `slug`, `description`, `sort_order`, `created_at`, `updated_at`) VALUES
    ('Cosmetics', 'cosmetics', 'Makeup, lipstick, foundation, eyeshadow, skincare & beauty products', 14, NOW(), NOW());
