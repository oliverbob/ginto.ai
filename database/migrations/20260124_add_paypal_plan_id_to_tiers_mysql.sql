-- Add PayPal subscription plan IDs to tier_plans
-- Each tier can have a PayPal billing plan for recurring payments

ALTER TABLE tier_plans 
ADD COLUMN setup_fee DECIMAL(10,4) DEFAULT 0.0000 AFTER cost_amount,
ADD COLUMN recurring_amount DECIMAL(10,4) DEFAULT NULL AFTER setup_fee,
ADD COLUMN billing_interval VARCHAR(20) DEFAULT 'MONTH' AFTER recurring_amount,
ADD COLUMN paypal_plan_id VARCHAR(50) DEFAULT NULL AFTER billing_interval;

-- Update Starter tier with setup fee and recurring amount
-- ₱100 setup fee (promo) + ₱150/month recurring
UPDATE tier_plans SET 
    cost_amount = 150.0000,
    setup_fee = 100.0000,
    recurring_amount = 150.0000,
    billing_interval = 'MONTH'
WHERE id = 1;

-- Add paypal_subscription_id to users table for tracking active subscriptions
ALTER TABLE users 
ADD COLUMN paypal_subscription_id VARCHAR(50) DEFAULT NULL,
ADD COLUMN subscription_status VARCHAR(20) DEFAULT NULL,
ADD COLUMN subscription_start_date DATETIME DEFAULT NULL,
ADD COLUMN subscription_next_billing DATETIME DEFAULT NULL;
