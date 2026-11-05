-- Every AI call made with OUR keys (Groq, Cerebras), free or paid (owner, 2026-10-24:
-- "make sure that we earn more than we pay many times ... the apis themselves can
-- become auto billed"). The paid total for the month is what Ginto\Support\AiSpend caps.
CREATE TABLE IF NOT EXISTS ai_spend (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    provider    VARCHAR(24)  NOT NULL,
    model       VARCHAR(64)  NOT NULL,
    paid        TINYINT(1)   NOT NULL DEFAULT 0,
    feature     VARCHAR(32)  NOT NULL DEFAULT 'chat',
    user_id     INT UNSIGNED NULL,
    in_tokens   INT UNSIGNED NOT NULL DEFAULT 0,
    out_tokens  INT UNSIGNED NOT NULL DEFAULT 0,
    estimated   TINYINT(1)   NOT NULL DEFAULT 0,
    cost_usd    DECIMAL(12,6) NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ai_spend_month (paid, created_at),
    KEY idx_ai_spend_provider (provider, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE sites_ai_usage ADD COLUMN provider VARCHAR(24) NOT NULL DEFAULT 'groq' AFTER feature;
