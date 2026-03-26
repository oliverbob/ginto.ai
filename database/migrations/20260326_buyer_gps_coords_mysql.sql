-- Add buyer GPS coordinates to users table for precise location persistence
-- Safe to re-run: checks if columns exist before adding

SET @db = DATABASE();

SET @has_lat = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'buyer_lat');
SET @sql_lat = IF(@has_lat = 0, 'ALTER TABLE `users` ADD COLUMN `buyer_lat` DECIMAL(10,7) DEFAULT NULL AFTER `buyer_barangay_id`', 'SELECT 1');
PREPARE stmt FROM @sql_lat;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_lng = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'buyer_lng');
SET @sql_lng = IF(@has_lng = 0, 'ALTER TABLE `users` ADD COLUMN `buyer_lng` DECIMAL(10,7) DEFAULT NULL AFTER `buyer_lat`', 'SELECT 1');
PREPARE stmt FROM @sql_lng;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
