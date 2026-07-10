-- GTB: persistent bot on/off state so the server-side runner survives restarts
-- and resumes where it left off. Single row (id=1).
CREATE TABLE IF NOT EXISTS gtb_bot_state (
    id          TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    enabled     TINYINT(1)       NOT NULL DEFAULT 0,
    arm_live    TINYINT(1)       NOT NULL DEFAULT 0,
    last_run_at DATETIME         NULL,
    last_action VARCHAR(180)     NULL,
    updated_at  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO gtb_bot_state (id, enabled, arm_live) VALUES (1, 0, 0);
