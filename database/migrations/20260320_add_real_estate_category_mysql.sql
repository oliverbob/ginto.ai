/*
 * Migration: Add Real Estate & Property top-level category with subcategories
 * Date: 2026-03-20
 *
 * Inserts a parent "Real Estate & Property" category and four subcategories:
 * House & Lot, Condominiums, Land, and Rentals (Short/Long-Term).
 * Safe to re-run: uses INSERT IGNORE for the parent and a stored procedure
 * pattern to look up the generated parent_id before inserting children.
 */

-- -------------------------------------------------------
-- 1. Insert parent category (idempotent via INSERT IGNORE)
-- -------------------------------------------------------
INSERT IGNORE INTO `categories` (`name`, `slug`, `description`, `sort_order`, `parent_id`, `created_at`, `updated_at`)
VALUES ('Real Estate & Property', 'real-estate-property', 'Houses, condos, land, and rental properties', 27, NULL, NOW(), NOW());

-- -------------------------------------------------------
-- 2. Insert subcategories linked to the parent
-- -------------------------------------------------------
INSERT IGNORE INTO `categories` (`name`, `slug`, `description`, `sort_order`, `parent_id`, `created_at`, `updated_at`)
SELECT 'House & Lot',                 'house-lot',                  'Single-family homes, townhouses & house-and-lot packages',    1, id, NOW(), NOW() FROM `categories` WHERE `slug` = 'real-estate-property' LIMIT 1;

INSERT IGNORE INTO `categories` (`name`, `slug`, `description`, `sort_order`, `parent_id`, `created_at`, `updated_at`)
SELECT 'Condominiums',                'condominiums',               'Studio, 1BR, 2BR & penthouse condo units for sale or resale',  2, id, NOW(), NOW() FROM `categories` WHERE `slug` = 'real-estate-property' LIMIT 1;

INSERT IGNORE INTO `categories` (`name`, `slug`, `description`, `sort_order`, `parent_id`, `created_at`, `updated_at`)
SELECT 'Land',                        'land',                       'Residential, commercial, agricultural & industrial lots',      3, id, NOW(), NOW() FROM `categories` WHERE `slug` = 'real-estate-property' LIMIT 1;

INSERT IGNORE INTO `categories` (`name`, `slug`, `description`, `sort_order`, `parent_id`, `created_at`, `updated_at`)
SELECT 'Rentals (Short/Long-Term)',   'rentals-short-long-term',    'Daily, monthly & long-term rentals — rooms, houses & units',   4, id, NOW(), NOW() FROM `categories` WHERE `slug` = 'real-estate-property' LIMIT 1;
