-- GTB: session start timestamp for time-boxed trading sessions (MariaDB idempotent).
ALTER TABLE gtb_bot_state ADD COLUMN IF NOT EXISTS session_started_at DATETIME NULL AFTER arm_live;
