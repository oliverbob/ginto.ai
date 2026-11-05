-- GTB: template + trailing-stop fields for multi-position, multi-strategy trading (MariaDB idempotent).
ALTER TABLE gtb_trades ADD COLUMN IF NOT EXISTS template   VARCHAR(24)   NULL AFTER mode;
ALTER TABLE gtb_trades ADD COLUMN IF NOT EXISTS peak_price DECIMAL(20,8) NULL AFTER take_profit;
ALTER TABLE gtb_trades ADD COLUMN IF NOT EXISTS trail_pct  DECIMAL(8,4)  NULL AFTER peak_price;
