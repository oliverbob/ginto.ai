-- Update Starter tier to ₱250 (includes ₱100 one-time promo fee + ₱150 base)
-- Recurring will be ₱150/month after initial payment

UPDATE tier_plans SET cost_amount = 250.0000 WHERE id = 1 AND name = 'Starter';

-- Also update tier names to match the UI
UPDATE tier_plans SET name = 'Professional' WHERE id = 2 AND name = 'Basic';
UPDATE tier_plans SET name = 'Executive' WHERE id = 3 AND name = 'Silver';
