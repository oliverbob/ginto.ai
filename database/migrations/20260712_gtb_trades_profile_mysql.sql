-- GTB: trading profile (aggressive / conservative) per position — two bots share one wallet (MariaDB idempotent).
ALTER TABLE gtb_trades ADD COLUMN IF NOT EXISTS profile VARCHAR(24) NULL AFTER template;
