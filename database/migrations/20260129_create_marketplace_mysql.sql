/*
 * Migration: Create marketplace / KYC / sellers / products tables
 * File: database/migrations/20260129_create_marketplace.sql
 * Date: 2026-01-29
 *
 * This migration creates tables for KYC, seller subscriptions/payments, products, categories,
 * CSRF tokens, and triggers that prevent a product from being published unless the seller
 * has an approved KYC and an active marketplace subscription.
 *
 * Notes:
 * - Assumes `users` table exists with primary key `id` (unsigned bigint).
 * - Requires MySQL 5.7+/8.0+ for SIGNAL and FULLTEXT on InnoDB.
 */

SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE;
SET SQL_MODE='STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION';

-- -----------------------------
-- Table: marketplace_settings
-- -----------------------------
CREATE TABLE IF NOT EXISTS marketplace_settings (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `value` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO marketplace_settings (`name`, `value`) VALUES
  ('marketplace_fee_amount', '25.00'),
  ('marketplace_fee_currency', 'USD'),
  ('marketplace_paypal_plan_id', ''),
  ('marketplace_fee_description', 'Monthly marketplace subscription fee');

-- -----------------------------
-- Table: kyc_profiles
-- -----------------------------
CREATE TABLE IF NOT EXISTS kyc_profiles (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `status` enum('pending','review','approved','rejected') NOT NULL DEFAULT 'pending',
  `first_name` varchar(191) DEFAULT NULL,
  `last_name` varchar(191) DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `country` char(2) DEFAULT NULL,
  `identifier` varchar(191) DEFAULT NULL,
  `documents` json DEFAULT NULL,
  `submitted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at` datetime DEFAULT NULL,
  `reviewer_id` int unsigned DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kyc_user_unique` (`user_id`),
  KEY `kyc_status_idx` (`status`),
  CONSTRAINT `kyc_profiles_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------
-- Table: seller_subscriptions
-- -----------------------------
CREATE TABLE IF NOT EXISTS seller_subscriptions (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `paypal_subscription_id` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive','cancelled','past_due') NOT NULL DEFAULT 'inactive',
  `amount` decimal(10,2) NOT NULL DEFAULT 25.00,
  `currency` char(3) NOT NULL DEFAULT 'USD',
  `started_at` datetime DEFAULT NULL,
  `next_billing_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `seller_sub_user_idx` (`user_id`),
  UNIQUE KEY `seller_sub_paypal_sub` (`paypal_subscription_id`),
  CONSTRAINT `seller_sub_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------
-- Table: seller_payments
-- -----------------------------
CREATE TABLE IF NOT EXISTS seller_payments (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `subscription_id` bigint unsigned DEFAULT NULL,
  `paypal_txn_id` varchar(255) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'USD',
  `status` enum('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending',
  `metadata` json DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `seller_pay_user_idx` (`user_id`),
  KEY `seller_pay_subscription_idx` (`subscription_id`),
  CONSTRAINT `seller_pay_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `seller_pay_sub_fk` FOREIGN KEY (`subscription_id`) REFERENCES `seller_subscriptions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------
-- Table: categories
-- -----------------------------
CREATE TABLE IF NOT EXISTS categories (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` bigint unsigned DEFAULT NULL,
  `name` varchar(191) NOT NULL,
  `slug` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `categories_parent_idx` (`parent_id`),
  UNIQUE KEY `categories_slug_unique` (`slug`),
  CONSTRAINT `categories_parent_fk` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------
-- Table: products
-- -----------------------------
CREATE TABLE IF NOT EXISTS products (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `seller_id` int unsigned NOT NULL,
  `category_id` bigint unsigned DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `short_description` varchar(500) DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `currency` char(3) NOT NULL DEFAULT 'USD',
  `quantity` int NOT NULL DEFAULT 0,
  `images` json DEFAULT NULL,
  `attributes` json DEFAULT NULL,
  `status` enum('draft','pending','published','archived','deleted') NOT NULL DEFAULT 'draft',
  `is_visible` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `products_seller_idx` (`seller_id`),
  KEY `products_category_idx` (`category_id`),
  KEY `products_price_idx` (`price`),
  UNIQUE KEY `products_slug_unique` (`slug`),
  CONSTRAINT `products_seller_fk` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `products_category_fk` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----- Backfill & normalize legacy products table columns (idempotent) -----
-- This handles older installs that had columns like owner_id, price_amount, price_currency,
-- image_path, stock, etc. We add missing modern columns and map values where possible.

-- Add seller_id if missing; map from owner_id when present
SET @has_seller_id = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'seller_id');
SET @sql = IF(@has_seller_id = 0, 'ALTER TABLE products ADD COLUMN `seller_id` INT UNSIGNED DEFAULT NULL', 'SELECT "skip_seller_id"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @has_owner = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'owner_id');
SET @has_seller_id = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'seller_id');
SET @sql = IF(@has_owner = 1 AND @has_seller_id = 1, 'UPDATE products SET seller_id = owner_id WHERE owner_id IS NOT NULL', 'SELECT "skip_owner_map"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add price and currency columns if missing; map from price_amount/price_currency when present
SET @has_price = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'price');
SET @has_price_amount = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'price_amount');
SET @has_price_currency = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'price_currency');
SET @sql = IF(@has_price = 0, 'ALTER TABLE products ADD COLUMN `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00, ADD COLUMN `currency` CHAR(3) NOT NULL DEFAULT "USD"', 'SELECT "skip_price"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @has_price = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'price');
SET @has_price_amount = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'price_amount');
SET @has_price_currency = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'price_currency');
SET @sql = IF(@has_price = 1 AND @has_price_amount = 1, 'UPDATE products SET price = price_amount WHERE price_amount IS NOT NULL', 'SELECT "skip_price_map"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(@has_price = 1 AND @has_price_currency = 1, 'UPDATE products SET currency = price_currency WHERE price_currency IS NOT NULL', 'SELECT "skip_currency_map"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add quantity and map from stock if present
SET @has_quantity = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'quantity');
SET @has_stock = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'stock');
SET @sql = IF(@has_quantity = 0, 'ALTER TABLE products ADD COLUMN `quantity` INT NOT NULL DEFAULT 0', 'SELECT "skip_quantity"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @has_quantity = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'quantity');
SET @sql = IF(@has_quantity = 1 AND @has_stock = 1, 'UPDATE products SET quantity = stock WHERE stock IS NOT NULL', 'SELECT "skip_stock_map"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add images JSON column and map from image_path when present
SET @has_images = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'images');
SET @has_image_path = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'image_path');
SET @sql = IF(@has_images = 0, 'ALTER TABLE products ADD COLUMN `images` JSON DEFAULT NULL', 'SELECT "skip_images"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @has_images = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'images');
SET @sql = IF(@has_images = 1 AND @has_image_path = 1, 'UPDATE products SET images = JSON_ARRAY(image_path) WHERE image_path IS NOT NULL AND image_path <> ""', 'SELECT "skip_images_map"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add short_description if missing
SET @has_short_desc = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'short_description');
SET @sql = IF(@has_short_desc = 0, 'ALTER TABLE products ADD COLUMN `short_description` VARCHAR(500) DEFAULT NULL', 'SELECT "skip_short_desc"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add is_visible (used by triggers) if missing
SET @has_is_visible = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'is_visible');
SET @sql = IF(@has_is_visible = 0, 'ALTER TABLE products ADD COLUMN `is_visible` TINYINT(1) NOT NULL DEFAULT 0', 'SELECT "skip_is_visible"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add attributes JSON column if missing
SET @has_attributes = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'attributes');
SET @sql = IF(@has_attributes = 0, 'ALTER TABLE products ADD COLUMN `attributes` JSON DEFAULT NULL', 'SELECT "skip_attributes"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add polite UNIQUE index on slug if it doesn't exist (ignore errors if not supported)
SET @has_slug_index = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND INDEX_NAME = 'products_slug_unique');
SET @sql = IF(@has_slug_index = 0, 'ALTER TABLE products ADD UNIQUE INDEX `products_slug_unique` (`slug`(191))', 'SELECT "skip_slug_index"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add FULLTEXT index only if the required columns exist (guard against partial/previous runs)
SET @has_cols = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME IN ('title','short_description','description'));
SET @sql = IF(@has_cols = 3, 'ALTER TABLE products ADD FULLTEXT KEY `products_fulltext_title_desc` (`title`, `short_description`, `description`)', 'SELECT "skip_fulltext"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------
-- Table: csrf_tokens (server-side helper)
-- -----------------------------
CREATE TABLE IF NOT EXISTS csrf_tokens (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned DEFAULT NULL,
  `token` varchar(128) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `csrf_user_idx` (`user_id`),
  UNIQUE KEY `csrf_token_unique` (`token`),
  CONSTRAINT `csrf_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------
-- View: sellers eligible to publish
-- -----------------------------
CREATE OR REPLACE VIEW view_sellers_eligible AS
SELECT u.id AS user_id, IFNULL(k.status, 'pending') AS kyc_status, IFNULL(s.status, 'inactive') AS subscription_status, s.next_billing_at
FROM users u
LEFT JOIN kyc_profiles k ON k.user_id = u.id
LEFT JOIN seller_subscriptions s ON s.user_id = u.id;

-- -----------------------------
-- Trigger: Prevent publication unless KYC approved and subscription active
-- -----------------------------
DROP TRIGGER IF EXISTS products_before_insert_publish_check;
DELIMITER $$
CREATE TRIGGER products_before_insert_publish_check
BEFORE INSERT ON products
FOR EACH ROW
BEGIN
  DECLARE kyc_stat VARCHAR(32);
  DECLARE sub_stat VARCHAR(32);

  IF NEW.status = 'published' OR NEW.is_visible = 1 THEN
    SELECT k.status INTO kyc_stat FROM kyc_profiles k WHERE k.user_id = NEW.seller_id LIMIT 1;
    SELECT s.status INTO sub_stat FROM seller_subscriptions s WHERE s.user_id = NEW.seller_id LIMIT 1;

    IF kyc_stat IS NULL OR kyc_stat <> 'approved' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot publish product: KYC not approved for seller';
    END IF;

    IF sub_stat IS NULL OR sub_stat <> 'active' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot publish product: marketplace subscription not active for seller';
    END IF;
  END IF;
END$$

CREATE TRIGGER products_before_update_publish_check
BEFORE UPDATE ON products
FOR EACH ROW
BEGIN
  DECLARE kyc_stat VARCHAR(32);
  DECLARE sub_stat VARCHAR(32);

  IF (NEW.status = 'published' OR NEW.is_visible = 1) AND (OLD.status <> 'published' OR OLD.is_visible = 0) THEN
    SELECT k.status INTO kyc_stat FROM kyc_profiles k WHERE k.user_id = NEW.seller_id LIMIT 1;
    SELECT s.status INTO sub_stat FROM seller_subscriptions s WHERE s.user_id = NEW.seller_id LIMIT 1;

    IF kyc_stat IS NULL OR kyc_stat <> 'approved' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot publish product: KYC not approved for seller';
    END IF;

    IF sub_stat IS NULL OR sub_stat <> 'active' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot publish product: marketplace subscription not active for seller';
    END IF;
  END IF;
END$$
DELIMITER ;

-- -----------------------------
-- Stored PROC: upsert seller subscription (webhook helper)
-- -----------------------------
DROP PROCEDURE IF EXISTS sp_upsert_seller_subscription;
DELIMITER $$
CREATE PROCEDURE sp_upsert_seller_subscription (
  IN in_user_id INT,
  IN in_paypal_subscription_id VARCHAR(255),
  IN in_status VARCHAR(32),
  IN in_started_at DATETIME,
  IN in_next_billing_at DATETIME
)
BEGIN
  INSERT INTO seller_subscriptions (user_id, paypal_subscription_id, status, started_at, next_billing_at, amount, currency, created_at)
  VALUES (in_user_id, in_paypal_subscription_id, in_status, in_started_at, in_next_billing_at, (SELECT CAST(value AS DECIMAL(10,2)) FROM marketplace_settings WHERE name='marketplace_fee_amount' LIMIT 1), (SELECT value FROM marketplace_settings WHERE name='marketplace_fee_currency' LIMIT 1), NOW())
  ON DUPLICATE KEY UPDATE
    paypal_subscription_id = VALUES(paypal_subscription_id),
    status = VALUES(status),
    started_at = VALUES(started_at),
    next_billing_at = VALUES(next_billing_at),
    updated_at = NOW();
END$$
DELIMITER ;

-- Re-enable checks
SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;

-- End migration
