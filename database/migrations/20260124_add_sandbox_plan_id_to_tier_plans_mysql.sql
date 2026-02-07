-- Add sandbox PayPal plan ID column to tier_plans
-- Allows separate plan IDs for sandbox vs live environments

-- Only add if column doesn't exist (idempotent)
SET @column_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'tier_plans' 
    AND COLUMN_NAME = 'paypal_plan_id_sandbox');

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE tier_plans ADD COLUMN paypal_plan_id_sandbox VARCHAR(50) DEFAULT NULL AFTER paypal_plan_id',
    'SELECT "Column already exists"');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
