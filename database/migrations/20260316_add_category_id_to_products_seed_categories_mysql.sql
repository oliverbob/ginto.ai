/*
 * Migration: Add category_id column to products + seed default mall categories
 * Date: 2026-03-16 (rev 2 — removed FK constraint to avoid type/engine conflicts)
 *
 * Adds category_id (INT, matching categories.id int(11)) to products table,
 * adds an index, then seeds the 8 standard ePower Mall categories.
 * Safe to re-run: uses INFORMATION_SCHEMA guards and INSERT IGNORE.
 * NOTE: No FK constraint is added — the application resolves by value.
 */

-- -------------------------------------------------------
-- 1. Add category_id to products if missing
-- -------------------------------------------------------
SET @has_col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'category_id');

SET @sql = IF(@has_col = 0,
    'ALTER TABLE `products` ADD COLUMN `category_id` INT DEFAULT NULL',
    'SELECT 1');
PREPARE _s FROM @sql; EXECUTE _s; DEALLOCATE PREPARE _s;

-- -------------------------------------------------------
-- 2. Add index on products.category_id if missing
-- -------------------------------------------------------
SET @has_idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND INDEX_NAME = 'products_category_idx');

SET @sql2 = IF(@has_idx = 0,
    'ALTER TABLE `products` ADD INDEX `products_category_idx` (`category_id`)',
    'SELECT 1');
PREPARE _s2 FROM @sql2; EXECUTE _s2; DEALLOCATE PREPARE _s2;

-- -------------------------------------------------------
-- 3. Ensure sort_order column exists on categories
-- -------------------------------------------------------
SET @has_sort = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'categories' AND COLUMN_NAME = 'sort_order');

SET @sql3 = IF(@has_sort = 0,
    'ALTER TABLE `categories` ADD COLUMN `sort_order` INT NOT NULL DEFAULT 0',
    'SELECT 1');
PREPARE _s3 FROM @sql3; EXECUTE _s3; DEALLOCATE PREPARE _s3;

-- -------------------------------------------------------
-- 4. Seed default ePower Mall categories (idempotent via INSERT IGNORE)
-- -------------------------------------------------------
INSERT IGNORE INTO `categories` (`name`, `slug`, `description`, `sort_order`, `created_at`, `updated_at`) VALUES
    ('Electronics',    'electronics',   'Phones, computers, gadgets & accessories',  1, NOW(), NOW()),
    ('Fashion',        'fashion',       'Clothing, shoes & accessories',             2, NOW(), NOW()),
    ('Home & Living',  'home-living',   'Furniture, decor & household essentials',   3, NOW(), NOW()),
    ('Sports',         'sports',        'Sporting goods, fitness & outdoor gear',    4, NOW(), NOW()),
    ('Beauty',         'beauty',        'Skincare, makeup, hair care & wellness',    5, NOW(), NOW()),
    ('Books',          'books',         'Books, education & learning materials',     6, NOW(), NOW()),
    ('Food & Grocery', 'food-grocery',  'Fresh produce, snacks & packaged goods',   7, NOW(), NOW()),
    ('Toys & Hobbies', 'toys-hobbies',  'Toys, board games & hobby supplies',       8, NOW(), NOW());
