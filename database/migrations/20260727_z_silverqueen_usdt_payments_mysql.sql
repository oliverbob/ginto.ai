-- ============================================================================
-- SilverQueen: USDT (BEP20) becomes the only accepted payment method.
--
-- Purchases stop settling instantly. Every order is now an invoice that moves
-- pending -> awaiting_confirmation (buyer submitted a TxHash) -> completed
-- (an admin verified the transfer on-chain). Allocations and referral overrides
-- are created only on completion, so nothing is granted before funds land.
--
-- Idempotent: ADD COLUMN IF NOT EXISTS is supported on this MariaDB.
-- ============================================================================

-- Widen the lifecycle. 'completed' keeps its meaning (paid and verified), so any
-- pre-existing simulated rows stay valid.
ALTER TABLE sq_purchases
  MODIFY COLUMN status ENUM('pending','awaiting_confirmation','completed','cancelled','rejected')
  NOT NULL DEFAULT 'pending';

-- On-chain settlement details.
ALTER TABLE sq_purchases
  ADD COLUMN IF NOT EXISTS payment_method   VARCHAR(20)     NOT NULL DEFAULT 'usdt_bep20' AFTER source,
  ADD COLUMN IF NOT EXISTS wallet_address   VARCHAR(100)    NULL     AFTER payment_method,
  ADD COLUMN IF NOT EXISTS tx_hash          VARCHAR(100)    NULL     AFTER wallet_address,
  ADD COLUMN IF NOT EXISTS paid_at          DATETIME        NULL     AFTER tx_hash,
  ADD COLUMN IF NOT EXISTS confirmed_at     DATETIME        NULL     AFTER paid_at,
  ADD COLUMN IF NOT EXISTS confirmed_by     INT UNSIGNED    NULL     AFTER confirmed_at,
  ADD COLUMN IF NOT EXISTS rejection_reason VARCHAR(255)    NULL     AFTER confirmed_by;

-- One transfer can only ever pay for one order. This is the guard against a
-- buyer pasting the same TxHash into several invoices.
ALTER TABLE sq_purchases
  ADD UNIQUE KEY IF NOT EXISTS uq_sq_purchases_tx (tx_hash);

-- The admin verification queue reads by status, oldest first.
ALTER TABLE sq_purchases
  ADD KEY IF NOT EXISTS idx_sq_purchases_review (status, paid_at);

-- Price everything in USDT rather than USD: the buyer sends the ticket amount
-- 1:1 in USDT, so the displayed currency should be what they actually transfer.
UPDATE sq_products SET currency = 'USDT' WHERE currency = 'USD';
