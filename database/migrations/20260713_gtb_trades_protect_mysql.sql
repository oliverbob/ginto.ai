-- GTB: exchange-side protective order reference (OCO list id or stop order id) per live position (MariaDB idempotent).
ALTER TABLE gtb_trades ADD COLUMN IF NOT EXISTS protect_type VARCHAR(8)  NULL AFTER binance_order_id;
ALTER TABLE gtb_trades ADD COLUMN IF NOT EXISTS protect_id   VARCHAR(40) NULL AFTER protect_type;
