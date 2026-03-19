-- Migration: Add gateway_payment_id for external payment charge tracking
-- Date: 2026-03-19
-- Description:
--   Adds `gateway_payment_id` to subscription_payments and user_subscriptions.
--   This stores the finalized charge/payment object ID from the payment gateway,
--   which is distinct from the payment intent / session reference already stored
--   in `payment_reference`.
--
--   PayMongo:  payment_reference = pi_xxxxxxxx (Payment Intent ID, created upfront)
--              gateway_payment_id = pay_xxxxxxxx (actual charge object, set on success)
--   PayPal:    payment_reference = paypal subscription ID
--              gateway_payment_id = PayPal capture/order ID
--   Internal:  transaction_id    = GNT-XXXXXXXX (our own alphanumeric reference)
--
-- Compatibility: MySQL 5.7+, MariaDB 10.2+

-- =====================================================
-- subscription_payments
-- =====================================================
ALTER TABLE subscription_payments
  ADD COLUMN gateway_payment_id VARCHAR(100) NULL
    COMMENT 'External charge/payment object ID from gateway (e.g. PayMongo pay_xxx)'
    AFTER payment_reference;

ALTER TABLE subscription_payments
  ADD INDEX idx_gateway_payment_id (gateway_payment_id);

-- =====================================================
-- user_subscriptions
-- =====================================================
ALTER TABLE user_subscriptions
  ADD COLUMN gateway_payment_id VARCHAR(100) NULL
    COMMENT 'External charge/payment object ID from gateway (e.g. PayMongo pay_xxx)'
    AFTER payment_reference;
