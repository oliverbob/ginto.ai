-- MySQL/SQLite schema for API request tracking (rate limiting)
-- Run this migration to enable rate limiting with provider fallback

CREATE TABLE IF NOT EXISTS api_requests (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    user_id VARCHAR(64) DEFAULT NULL,              -- User ID or session ID for visitors
    user_role VARCHAR(20) DEFAULT 'visitor',       -- 'admin', 'user', 'visitor'
    provider VARCHAR(32) NOT NULL,                 -- 'groq', 'cerebras', etc.
    model VARCHAR(128) NOT NULL,                   -- Model name used
    tokens_input INT UNSIGNED DEFAULT 0,           -- Input/prompt tokens
    tokens_output INT UNSIGNED DEFAULT 0,          -- Output/completion tokens
    tokens_total INT UNSIGNED DEFAULT 0,           -- Total tokens (input + output)
    request_type VARCHAR(32) DEFAULT 'chat',       -- 'chat', 'vision', 'websearch', etc.
    response_status VARCHAR(16) DEFAULT 'success', -- 'success', 'error', 'rate_limited'
    fallback_used TINYINT(1) DEFAULT 0,            -- 1 if fallback provider was used
    latency_ms INT UNSIGNED DEFAULT 0,             -- Response time in milliseconds
    ip_address VARCHAR(45) DEFAULT NULL,           -- IPv4 or IPv6
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_provider (provider),
    INDEX idx_created_at (created_at),
    INDEX idx_user_created (user_id, created_at),
    INDEX idx_provider_created (provider, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rate limit configuration table (optional - can also use env vars)
CREATE TABLE IF NOT EXISTS rate_limit_config (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    provider VARCHAR(32) NOT NULL,
    model VARCHAR(128) NOT NULL,
    limit_type VARCHAR(16) NOT NULL,               -- 'rpm', 'rpd', 'tpm', 'tpd'
    limit_value INT UNSIGNED NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_provider_model_type (provider, model, limit_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default rate limits (Developer Plan)
-- Sources:
--   Groq: https://console.groq.com/docs/rate-limits (Developer Plan)
--   Cerebras: https://inference-docs.cerebras.ai/support/rate-limits (Developer Plan)

INSERT INTO rate_limit_config (provider, model, limit_type, limit_value) VALUES
-- Groq openai/gpt-oss-120b: RPM=1000, RPD=500000, TPM=250000, TPD=10000000
('groq', 'openai/gpt-oss-120b', 'rpm', 1000),
('groq', 'openai/gpt-oss-120b', 'rpd', 500000),
('groq', 'openai/gpt-oss-120b', 'tpm', 250000),
('groq', 'openai/gpt-oss-120b', 'tpd', 10000000),
-- Groq llama-3.3-70b-versatile: RPM=1000, RPD=500000, TPM=300000, TPD=10000000
('groq', 'llama-3.3-70b-versatile', 'rpm', 1000),
('groq', 'llama-3.3-70b-versatile', 'rpd', 500000),
('groq', 'llama-3.3-70b-versatile', 'tpm', 300000),
('groq', 'llama-3.3-70b-versatile', 'tpd', 10000000),
-- Cerebras gpt-oss-120b: RPM=30, RPD=14400, TPM=64000, TPD=1000000
('cerebras', 'gpt-oss-120b', 'rpm', 30),
('cerebras', 'gpt-oss-120b', 'rpd', 14400),
('cerebras', 'gpt-oss-120b', 'tpm', 64000),
('cerebras', 'gpt-oss-120b', 'tpd', 1000000)
ON DUPLICATE KEY UPDATE limit_value = VALUES(limit_value);
