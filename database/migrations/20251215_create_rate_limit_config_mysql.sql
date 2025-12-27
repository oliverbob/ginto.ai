-- Migration: Create rate_limit_config table with preset values
-- This stores provider/model rate limits (rpm, rpd, tpm, tpd)

CREATE TABLE IF NOT EXISTS rate_limit_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider VARCHAR(50) NOT NULL,
    model VARCHAR(100) NOT NULL,
    limit_type ENUM('rpm', 'rpd', 'tpm', 'tpd') NOT NULL,
    limit_value BIGINT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_provider_model_type (provider, model, limit_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert preset values for groq openai/gpt-oss-120b
INSERT INTO rate_limit_config (provider, model, limit_type, limit_value) VALUES
    ('groq', 'openai/gpt-oss-120b', 'rpm', 1000),
    ('groq', 'openai/gpt-oss-120b', 'rpd', 1440000),
    ('groq', 'openai/gpt-oss-120b', 'tpm', 1000000),
    ('groq', 'openai/gpt-oss-120b', 'tpd', 2000000000)
ON DUPLICATE KEY UPDATE limit_value = VALUES(limit_value);

-- Insert preset values for cerebras gpt-oss-120b
INSERT INTO rate_limit_config (provider, model, limit_type, limit_value) VALUES
    ('cerebras', 'gpt-oss-120b', 'rpm', 30),
    ('cerebras', 'gpt-oss-120b', 'rpd', 14400),
    ('cerebras', 'gpt-oss-120b', 'tpm', 64000),
    ('cerebras', 'gpt-oss-120b', 'tpd', 1000000)
ON DUPLICATE KEY UPDATE limit_value = VALUES(limit_value);

-- Insert preset values for groq llama-3.3-70b-versatile
INSERT INTO rate_limit_config (provider, model, limit_type, limit_value) VALUES
    ('groq', 'llama-3.3-70b-versatile', 'rpm', 1000),
    ('groq', 'llama-3.3-70b-versatile', 'rpd', 500000),
    ('groq', 'llama-3.3-70b-versatile', 'tpm', 300000),
    ('groq', 'llama-3.3-70b-versatile', 'tpd', 10000000)
ON DUPLICATE KEY UPDATE limit_value = VALUES(limit_value);
