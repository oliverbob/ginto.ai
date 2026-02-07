-- Migration: Create clients table for guest user access
-- This table is accessible by the 'guest' MySQL user for the AI agent
-- Generated: 2024-12-05
-- 
-- Guest user permissions (already set up):
-- This project expects a dedicated 'clients' database for sandbox client data.
-- Ensure the guest user has the necessary rights on the clients database:
-- GRANT SELECT, INSERT, UPDATE, DELETE ON clients.* TO 'guest'@'localhost' IDENTIFIED BY 'guest_password';

CREATE TABLE IF NOT EXISTS `clients` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NULL,
    `phone` VARCHAR(50) NULL,
    `company` VARCHAR(255) NULL,
    `address` TEXT NULL,
    `city` VARCHAR(100) NULL,
    `country` VARCHAR(100) NULL,
    `status` ENUM('active', 'inactive', 'pending') NOT NULL DEFAULT 'active',
    `notes` TEXT NULL,
    `metadata` JSON NULL COMMENT 'Additional client data as JSON',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add indexes for common queries
CREATE INDEX idx_clients_email ON `clients` (`email`);
CREATE INDEX idx_clients_status ON `clients` (`status`);
CREATE INDEX idx_clients_company ON `clients` (`company`);

-- Insert sample data for testing
INSERT INTO `clients` (`name`, `email`, `phone`, `company`, `status`, `notes`) VALUES
('John Doe', 'john@example.com', '+1-555-0101', 'Acme Corp', 'active', 'VIP customer'),
('Jane Smith', 'jane@example.com', '+1-555-0102', 'Tech Solutions', 'active', NULL),
('Bob Wilson', 'bob@example.com', '+1-555-0103', 'Startup Inc', 'pending', 'Awaiting onboarding');
