-- Link a PayMongo (or other gateway) subscription id to a user_subscriptions row (MariaDB idempotent).
ALTER TABLE user_subscriptions ADD COLUMN IF NOT EXISTS gateway_subscription_id VARCHAR(100) NULL AFTER paypal_plan_id;
