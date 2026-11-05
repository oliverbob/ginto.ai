-- ============================================================================
-- SilverQueen: SQB engine units become variable-amount.
--
-- 100 USDT was never the price — it is the minimum. A buyer stakes whatever they
-- are willing to put in (and an admin grants whatever was actually paid or
-- negotiated), and the allocation's principal is that amount. Yield is unchanged:
-- 0.5%/day of principal for 365 days, whatever the principal happens to be.
--
-- Membership cards stay fixed-price.
-- Idempotent (ADD COLUMN IF NOT EXISTS) so it is safe to re-run.
-- ============================================================================

ALTER TABLE sq_products
  ADD COLUMN IF NOT EXISTS pricing_mode ENUM('fixed','variable') NOT NULL DEFAULT 'fixed' AFTER price,
  ADD COLUMN IF NOT EXISTS min_amount   DECIMAL(20,8)            NOT NULL DEFAULT 0       AFTER pricing_mode,
  ADD COLUMN IF NOT EXISTS max_amount   DECIMAL(20,8)            NOT NULL DEFAULT 0       AFTER min_amount;

-- The engine is buyer-priced, floored at 100 USDT. max_amount 0 = no ceiling
-- beyond the engine's own sanity cap.
-- Renamed too: it is no longer sold in "units", so the old name would mislead.
UPDATE sq_products
   SET name         = 'SQB Engine',
       pricing_mode = 'variable',
       min_amount   = 100.00000000,
       description  = 'Allocated cloud compute. Stake any amount from 100 USDT up — it yields 0.5% of whatever you put in, per day, for 365 days.'
 WHERE code = 'sqb_engine';

-- Cards are one-per-member at a set price; make that explicit rather than implied.
UPDATE sq_products
   SET pricing_mode = 'fixed', min_amount = 0
 WHERE kind = 'card';
