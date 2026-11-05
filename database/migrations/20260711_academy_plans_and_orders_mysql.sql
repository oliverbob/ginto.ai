-- Ginto Trading Academy: its own subscription plan type + seeded plans + a PayMongo order table.

-- 1) Allow an 'academy' plan type alongside the existing course/masterclass types.
ALTER TABLE subscription_plans MODIFY COLUMN plan_type ENUM('courses','masterclass','academy') NOT NULL DEFAULT 'courses';

-- 2) Seed the Academy membership tiers (idempotent by name).
INSERT INTO subscription_plans (name, plan_type, display_name, price_monthly, price_currency, description, has_ai_tutor, has_certificates, has_priority_support, badge_color, sort_order, is_active, created_at, updated_at)
SELECT 'academy_trader', 'academy', 'Trader', 499.00, 'PHP', 'Full trading curriculum, live AI bot walkthroughs, and risk-first strategy.', 1, 0, 0, '#6366f1', 1, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM subscription_plans WHERE name = 'academy_trader');

INSERT INTO subscription_plans (name, plan_type, display_name, price_monthly, price_currency, description, has_ai_tutor, has_certificates, has_priority_support, badge_color, sort_order, is_active, created_at, updated_at)
SELECT 'academy_pro', 'academy', 'Pro Trader', 1499.00, 'PHP', 'Everything in Trader plus PineScript strategy reviews, certificate, cohort sessions, and priority support.', 1, 1, 1, '#8b5cf6', 2, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM subscription_plans WHERE name = 'academy_pro');

-- 3) Pending PayMongo checkout orders for the Academy (correlated by checkout_session_id on the webhook).
CREATE TABLE IF NOT EXISTS academy_orders (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  plan_id INT NULL,
  checkout_session_id VARCHAR(100) NOT NULL,
  amount DECIMAL(10,2) NULL,
  currency VARCHAR(10) NOT NULL DEFAULT 'PHP',
  status ENUM('pending','completed','cancelled') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_cs (checkout_session_id),
  KEY idx_user (user_id),
  KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
