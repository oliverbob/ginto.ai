-- GTB: wind-down support — whether the bot may open NEW positions (Stop = wind down, keep managing). Idempotent.
ALTER TABLE gtb_bot_state ADD COLUMN IF NOT EXISTS open_new TINYINT(1) NOT NULL DEFAULT 1 AFTER enabled;
