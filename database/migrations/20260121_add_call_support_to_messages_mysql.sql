-- Add support for call messages in member_messages table
-- This migration is defensive: it will only apply changes when the `member_messages` table exists
-- and the specific enum/columns/index are not already present. This avoids ordering failures on fresh installs.

-- Only proceed if the table exists
SET @has_table = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'member_messages');

-- Add 'call' to the enum if not present
SET @enum_has_call = 0;
SET @enum_has_call = (SELECT IFNULL(LOCATE('call', COLUMN_TYPE), 0) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'member_messages' AND COLUMN_NAME = 'message_type' LIMIT 1);
SET @sql = IF(@has_table = 1 AND @enum_has_call = 0, 'ALTER TABLE member_messages MODIFY COLUMN message_type ENUM(''text'', ''image'', ''file'', ''emoji'', ''audio'', ''video'', ''call'') DEFAULT ''text''', 'SELECT "skip_enum"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add payload field for storing structured data (call duration, type, etc.) if missing
SET @has_payload = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'member_messages' AND COLUMN_NAME = 'payload');
SET @sql = IF(@has_table = 1 AND @has_payload = 0, 'ALTER TABLE member_messages ADD COLUMN payload JSON DEFAULT NULL COMMENT ''Structured data for special message types (e.g., call metadata)'' AFTER attachment_size', 'SELECT "skip_payload"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add index for message_type to optimize filtering if missing
SET @has_idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'member_messages' AND INDEX_NAME = 'idx_message_type');
SET @sql = IF(@has_table = 1 AND @has_idx = 0, 'ALTER TABLE member_messages ADD INDEX idx_message_type (message_type)', 'SELECT "skip_idx"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
