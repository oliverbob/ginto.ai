/*
 * Migration: Add category_id column to products + seed default mall categories
 * Date: 2026-03-16
 *
 * The original products table was created before the modern schema and is missing
 * the category_id (integer FK) column; it only has a legacy `category` varchar.
 * This migration adds the column, an index, and a FK constraint, then seeds the
 * standard ePower Mall categories used by the storefront.
 */

-- -------------------------------------------------------
-- 1. Add category_id to products (idempotent)
-- -------------------------------------------------------
SET @has_col = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'products'
      AND COLUMN_NAME  = 'category_id'
);
SET @sql = IF(
    @has_col = 0,
    'ALTER TABLE `products` ADD COLUMN `category_id` INT DEFAULT NULL',
    'SELECT "products.category_id already exists" AS note'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -------------------------------------------------------
-- 2. Add index on products.category_id (idempotent)
-- -------------------------------------------------------
SET @has_idx = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'products'
      AND INDEX_NAME   = 'products_category_idx'
);
SET @sql = IF(
    @has_idx = 0,
    'ALTER TABLE `products` ADD INDEX `products_category_idx` (`category_id`)',
    'SELECT "index already exists" AS note'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -------------------------------------------------------
-- 3. Add FK from products.category_id -> categories.id (idempotent)
-- -------------------------------------------------------
SET @has_fk = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA   = DATABASE()
      AND TABLE_NAME     = 'products'
      AND CONSTRAINT_NAME = 'products_category_fk'
);

-- Only add FK if categories table exists and the FK is missing
SET @cats_exist = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'categories'
);

SET @sql = IF(
    @has_fk = 0 AND @cats_exist = 1,
    'ALTER TABLE `products` ADD CONSTRAINT `products_category_fk` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT "fk already exists or categories table missing" AS note'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -------------------------------------------------------
-- 4. Ensure categories table sort_order column exists
-- -------------------------------------------------------
SET @has_sort = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'categories'
      AND COLUMN_NAME  = 'sort_order'
);
SET @sql = IF(
    @has_sort = 0,
    'ALTER TABLE `categories` ADD COLUMN `sort_order` INT DEFAULT 0',
    'SELECT "sort_order already exists" AS note'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -------------------------------------------------------
-- 5. Seed default ePower Mall categories (idempotent via IGNORE)
-- -------------------------------------------------------
INSERT IGNORE INTO `categories` (`name`, `slug`, `description`, `sort_order`, `created_at`, `updated_at`) VALUES
    ('Electronics',    'electronics',   'Phones, computers, gadgets & accessories',          1, NOW(), NOW()),
    ('Fashion',        'fashion',       'Clothing, shoes & accessories',                     2, NOW(), NOW()),
    ('Home & Living',  'home-living',   'Furniture, decor & household essentials',           3, NOW(), NOW()),
    ('Sports',         'sports',        'Sporting goods, fitness & outdoor gear',            4, NOW(), NOW()),
    ('Beauty',         'beauty',        'Skincare, makeup, hair care & wellness',            5, NOW(), NOW()),
    ('Books',          'books',         'Books, education & learning materials',             6, NOW(), NOW()),
    ('Food & Grocery', 'food-grocery',  'Fresh produce, snacks & packaged goods',           7, NOW(), NOW()),
    ('Toys & Hobbies', 'toys-hobbies',  'Toys, board games & hobby supplies',               8, NOW(), NOW());
