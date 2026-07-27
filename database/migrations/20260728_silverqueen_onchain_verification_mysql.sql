-- ============================================================================
-- SilverQueen: automatic on-chain verification of USDT (BEP20) payments.
--
-- Records what the BNB Smart Chain nodes actually reported for each submitted
-- TxHash, so a confirmation is backed by evidence rather than an admin's glance
-- at BscScan. Verification runs on submit and again from cron until the transfer
-- clears or is judged bad.
--
-- Idempotent (ADD COLUMN IF NOT EXISTS) so it is safe to re-run.
-- ============================================================================

ALTER TABLE sq_purchases
  ADD COLUMN IF NOT EXISTS chain_from        VARCHAR(64)    NULL AFTER tx_hash,
  ADD COLUMN IF NOT EXISTS chain_amount      DECIMAL(30,8)  NULL AFTER chain_from,
  ADD COLUMN IF NOT EXISTS confirmations     INT UNSIGNED   NULL AFTER chain_amount,
  ADD COLUMN IF NOT EXISTS verify_verdict    VARCHAR(20)    NULL AFTER confirmations,
  ADD COLUMN IF NOT EXISTS verify_note       VARCHAR(255)   NULL AFTER verify_verdict,
  ADD COLUMN IF NOT EXISTS verify_checked_at DATETIME       NULL AFTER verify_note;

-- The verifier sweep picks up submitted invoices, oldest check first.
ALTER TABLE sq_purchases
  ADD KEY IF NOT EXISTS idx_sq_purchases_verify (status, verify_checked_at);
