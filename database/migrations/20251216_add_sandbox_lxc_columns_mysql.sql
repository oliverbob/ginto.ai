-- Add LXC container and terms tracking columns to client_sandboxes
-- MySQL specific version - Run this migration after the initial client_sandboxes table is created

-- Check and add terms_accepted_at column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'client_sandboxes' AND COLUMN_NAME = 'terms_accepted_at');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE client_sandboxes ADD COLUMN terms_accepted_at DATETIME NULL DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add container_created_at column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'client_sandboxes' AND COLUMN_NAME = 'container_created_at');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE client_sandboxes ADD COLUMN container_created_at DATETIME NULL DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add container_name column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'client_sandboxes' AND COLUMN_NAME = 'container_name');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE client_sandboxes ADD COLUMN container_name VARCHAR(64) NULL DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add container_status column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'client_sandboxes' AND COLUMN_NAME = 'container_status');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE client_sandboxes ADD COLUMN container_status ENUM(\'not_created\', \'stopped\', \'running\', \'error\') DEFAULT \'not_created\'', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add last_accessed_at column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'client_sandboxes' AND COLUMN_NAME = 'last_accessed_at');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE client_sandboxes ADD COLUMN last_accessed_at DATETIME NULL DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
