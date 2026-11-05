-- Migration: Add admin review columns to subscription_payments
-- Date: 2025-12-18
-- Purpose: Add columns for admin review request feature
-- Safe: Uses IF NOT EXISTS pattern to avoid errors on re-run

-- Step 1: Add admin_review_requested column (if not exists)
-- This column tracks if user has requested admin review for their payment
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'subscription_payments' 
    AND COLUMN_NAME = 'admin_review_requested'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE subscription_payments ADD COLUMN admin_review_requested TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''User requested admin review'' AFTER rejection_reason',
    'SELECT ''Column admin_review_requested already exists'' AS info'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 2: Add admin_review_requested_at column (if not exists)
-- This column tracks when the admin review was requested
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'subscription_payments' 
    AND COLUMN_NAME = 'admin_review_requested_at'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE subscription_payments ADD COLUMN admin_review_requested_at DATETIME NULL COMMENT ''When admin review was requested'' AFTER admin_review_requested',
    'SELECT ''Column admin_review_requested_at already exists'' AS info'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 3: Add index for faster admin review queries (if not exists)
SET @index_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'subscription_payments' 
    AND INDEX_NAME = 'idx_admin_review'
);

SET @sql = IF(@index_exists = 0,
    'ALTER TABLE subscription_payments ADD INDEX idx_admin_review (admin_review_requested, status)',
    'SELECT ''Index idx_admin_review already exists'' AS info'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verification: Show final table structure
SELECT 'Migration complete. Verifying columns:' AS status;
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'subscription_payments' 
AND COLUMN_NAME IN ('admin_review_requested', 'admin_review_requested_at')
ORDER BY ORDINAL_POSITION;
