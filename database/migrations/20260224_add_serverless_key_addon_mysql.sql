-- Migration: Add Serverless Subscription addon plan (per-key slot)
-- Created: 2026-02-24

INSERT INTO `addon_plans` (`addon_type`, `name`, `description`, `amount_usd`, `features`, `is_active`)
VALUES (
  'serverless_key',
  'Serverless Subscription',
  'Additional web server tunnel key slot (1 key per active subscription)',
  105.00,
  JSON_ARRAY(
    'Create additional web server tunnel keys beyond the free limit',
    'Each active subscription adds 1 extra unrevoked key slot',
    'Instant activation after PayPal approval',
    'Use for hosting dashboards, web apps, and tools on your subdomain',
    'Cancel anytime'
  ),
  1
)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `description` = VALUES(`description`),
  `amount_usd` = VALUES(`amount_usd`),
  `features` = VALUES(`features`),
  `is_active` = VALUES(`is_active`);
