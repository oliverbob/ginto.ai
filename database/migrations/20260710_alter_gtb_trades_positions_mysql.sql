-- Ginto Trading Bot — position fields on gtb_trades (paper + live execution).
-- MariaDB supports ADD COLUMN IF NOT EXISTS, so this is idempotent.
ALTER TABLE gtb_trades ADD COLUMN IF NOT EXISTS mode        VARCHAR(8)     NULL AFTER status;      -- paper | live
ALTER TABLE gtb_trades ADD COLUMN IF NOT EXISTS stop_loss   DECIMAL(20,8)  NULL AFTER mode;
ALTER TABLE gtb_trades ADD COLUMN IF NOT EXISTS take_profit DECIMAL(20,8)  NULL AFTER stop_loss;
ALTER TABLE gtb_trades ADD COLUMN IF NOT EXISTS exit_price  DECIMAL(20,8)  NULL AFTER take_profit;
ALTER TABLE gtb_trades ADD COLUMN IF NOT EXISTS closed_at   DATETIME       NULL AFTER exit_price;
