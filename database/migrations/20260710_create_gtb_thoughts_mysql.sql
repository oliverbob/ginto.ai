-- Ginto Trading Bot — the bot's self-reflection / decision log ("chat with itself").
-- Idempotent.
CREATE TABLE IF NOT EXISTS gtb_thoughts (
    id         BIGINT UNSIGNED                 NOT NULL AUTO_INCREMENT PRIMARY KEY,
    role       ENUM('bot','claude','system')   NOT NULL DEFAULT 'claude',
    phase      VARCHAR(24)                     NOT NULL DEFAULT 'reflect',  -- reflect|decision|trade|error
    symbol     VARCHAR(20)                     NULL,
    decision   VARCHAR(24)                     NULL,                        -- BUY <SYM> | HOLD | SKIP
    message    TEXT                            NOT NULL,
    meta       JSON                            NULL,
    created_at DATETIME                        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_gtb_thoughts_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
