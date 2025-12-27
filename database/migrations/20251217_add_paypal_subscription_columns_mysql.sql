-- Migration: Add PayPal subscription columns
-- Date: 2024-12-17

-- Add PayPal-specific columns to user_subscriptions table
ALTER TABLE user_subscriptions 
ADD COLUMN paypal_subscription_id VARCHAR(50) NULL AFTER payment_reference,
ADD COLUMN paypal_plan_id VARCHAR(50) NULL AFTER paypal_subscription_id;

-- Add index for faster lookups by PayPal subscription ID
ALTER TABLE user_subscriptions 
ADD INDEX idx_paypal_subscription_id (paypal_subscription_id);

-- Add subscription_plan column to users table for quick plan access
ALTER TABLE users 
ADD COLUMN subscription_plan VARCHAR(20) DEFAULT 'free' AFTER email;
