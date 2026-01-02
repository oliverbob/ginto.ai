-- Migration: Unify subscription_payments table for all payment types
-- Date: 2025-12-18
-- Description: Adds type column and bank transfer fields to subscription_payments
--              to handle registration, course, masterclass payments in one place
-- Compatibility: MySQL 5.7+, MariaDB 10.2+

-- =====================================================
-- TABLE STRUCTURE NOTE:
-- =====================================================
-- subscription_payments.user_id -> REFERENCES users.id
-- This links each payment directly to the user who made it.
-- For bank transfers, notes JSON stores original_filename for admin reference.
-- receipt_filename is a random secure filename (no PII exposed).

-- =====================================================
-- STEP 1: Add transaction_id for public-facing reference
-- =====================================================
-- Alphanumeric ID shown to users instead of sequential numeric ID
-- Format: GNT-XXXXXXXX (8 random alphanumeric chars)
ALTER TABLE subscription_payments 
  ADD COLUMN transaction_id VARCHAR(20) NULL UNIQUE COMMENT 'Public alphanumeric transaction ID' AFTER id;

-- =====================================================
-- STEP 2: Add type column for payment categorization
-- =====================================================
-- Types: registration (membership), course, masterclass, other
ALTER TABLE subscription_payments 
  ADD COLUMN type ENUM('registration', 'course', 'masterclass', 'other') DEFAULT 'registration' AFTER plan_id;

-- =====================================================
-- STEP 2: Add receipt fields for bank transfers
-- =====================================================
-- receipt_filename: Random secure filename (e.g., a1b2c3d4e5f6...png)
-- receipt_path: Full server path to file (outside webroot for security)
-- Original filename stored in notes JSON for admin reference
ALTER TABLE subscription_payments 
  ADD COLUMN receipt_filename VARCHAR(255) NULL COMMENT 'Random secure filename' AFTER notes,
  ADD COLUMN receipt_path VARCHAR(500) NULL COMMENT 'Full path to receipt file' AFTER receipt_filename;

-- =====================================================
-- STEP 3: Add verification workflow fields
-- =====================================================
-- verified_by -> REFERENCES users.id (admin who verified)
ALTER TABLE subscription_payments 
  ADD COLUMN verified_by INT UNSIGNED NULL COMMENT 'Admin user_id who verified' AFTER receipt_path,
  ADD COLUMN verified_at DATETIME NULL AFTER verified_by,
  ADD COLUMN rejection_reason TEXT NULL COMMENT 'Reason if payment rejected' AFTER verified_at;

-- =====================================================
-- STEP 4: Add IP tracking for audit
-- =====================================================
ALTER TABLE subscription_payments 
  ADD COLUMN ip_address VARCHAR(45) NULL AFTER rejection_reason;

-- =====================================================
-- STEP 5: Add comprehensive audit trail fields
-- =====================================================
-- Standard fraud prevention and compliance tracking
ALTER TABLE subscription_payments 
  ADD COLUMN user_agent VARCHAR(500) NULL COMMENT 'Browser/device user agent' AFTER ip_address,
  ADD COLUMN device_info JSON NULL COMMENT 'Device fingerprint: browser, OS, device type, referrer' AFTER user_agent,
  ADD COLUMN geo_country VARCHAR(2) NULL COMMENT 'ISO country code from IP geolocation' AFTER device_info,
  ADD COLUMN geo_city VARCHAR(100) NULL COMMENT 'City from IP geolocation' AFTER geo_country,
  ADD COLUMN session_id VARCHAR(128) NULL COMMENT 'PHP session ID for tracking' AFTER geo_city;

-- =====================================================
-- STEP 6: Expand status enum for all payment states
-- =====================================================
ALTER TABLE subscription_payments 
  MODIFY COLUMN status ENUM('pending', 'completed', 'verified', 'failed', 'refunded', 'rejected', 'cancelled') DEFAULT 'pending';

-- =====================================================
-- STEP 7: Add indexes
-- =====================================================
ALTER TABLE subscription_payments ADD INDEX idx_type (type);

-- =====================================================
-- STEP 8: Add updated_at for edit tracking
-- =====================================================
-- Auto-updates when row is modified (status change, verification, etc.)
ALTER TABLE subscription_payments 
  ADD COLUMN updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last modification time' AFTER created_at;

-- =====================================================
-- STEP 9: Add payment_status to users table
-- =====================================================
-- Note: MySQL 8.0+ supports IF NOT EXISTS, older versions will error if exists (safe to ignore)
ALTER TABLE users 
  ADD COLUMN payment_status ENUM('paid', 'pending', 'free') DEFAULT 'free' 
  COMMENT 'Payment verification status';

-- =====================================================
-- STEP 10: Add admin review request fields
-- =====================================================
-- For users to request manual admin review of pending payments
ALTER TABLE subscription_payments 
  ADD COLUMN admin_review_requested TINYINT(1) DEFAULT 0 COMMENT 'User requested admin review' AFTER rejection_reason,
  ADD COLUMN admin_review_requested_at DATETIME NULL COMMENT 'When admin review was requested' AFTER admin_review_requested;
