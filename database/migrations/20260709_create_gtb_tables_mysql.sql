-- ============================================================================
-- Ginto Trading Bot (GTB) tables.
-- Balances / holdings are read live from Binance and NOT stored here.
-- Idempotent (CREATE TABLE IF NOT EXISTS) so it is safe to re-run.
-- ============================================================================

-- Single-row bot settings. Binance API secret is encrypted at rest.
CREATE TABLE IF NOT EXISTS gtb_settings (
    id                     INT UNSIGNED    NOT NULL AUTO_INCREMENT PRIMARY KEY,
    binance_api_key        VARCHAR(255)    NULL,
    binance_api_secret_enc TEXT            NULL,          -- base64(iv+ciphertext), AES-256-CBC
    is_testnet             TINYINT(1)      NOT NULL DEFAULT 1,
    updated_at             DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                    ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Recorded orders / fills.
CREATE TABLE IF NOT EXISTS gtb_trades (
    id               BIGINT UNSIGNED       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    symbol           VARCHAR(20)           NOT NULL,      -- e.g. BTCUSDT
    side             ENUM('BUY','SELL')    NOT NULL,
    type             ENUM('MARKET','LIMIT') NOT NULL,
    price            DECIMAL(20,8)         NULL,          -- null for market until filled
    qty              DECIMAL(20,8)         NOT NULL,      -- base asset quantity
    quote_qty        DECIMAL(20,8)         NULL,          -- quote spent/received
    status           VARCHAR(20)           NOT NULL DEFAULT 'NEW',
    binance_order_id VARCHAR(40)           NULL,
    realized_pnl     DECIMAL(20,8)         NULL,
    created_at       DATETIME              NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_gtb_trades_symbol  (symbol),
    INDEX idx_gtb_trades_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Activity / trading log.
CREATE TABLE IF NOT EXISTS gtb_logs (
    id         BIGINT UNSIGNED               NOT NULL AUTO_INCREMENT PRIMARY KEY,
    level      ENUM('info','error','trade')  NOT NULL DEFAULT 'info',
    message    TEXT                          NOT NULL,
    created_at DATETIME                      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_gtb_logs_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
