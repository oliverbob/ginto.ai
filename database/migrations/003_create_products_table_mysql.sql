-- Migration: Create products table (MySQL)
CREATE TABLE IF NOT EXISTS `products` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `owner_id` INT UNSIGNED DEFAULT NULL,
  `title` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) DEFAULT NULL,
  `description` TEXT,
  `price_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `price_currency` VARCHAR(8) NOT NULL DEFAULT 'PHP',
  `category` VARCHAR(128) DEFAULT NULL,
  `stock` INT DEFAULT 0,
  `image_path` VARCHAR(512) DEFAULT NULL,
  `badge` VARCHAR(64) DEFAULT NULL,
  `rating` DECIMAL(3,2) DEFAULT 0,
  `status` VARCHAR(32) NOT NULL DEFAULT 'published',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX (`owner_id`),
  INDEX (`category`),
  INDEX (`status`)
)
ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
