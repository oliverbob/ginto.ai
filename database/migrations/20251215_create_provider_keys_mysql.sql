-- Migration: Create provider_keys table for multiple API keys per provider
-- Date: 2025-12-15
-- Description: Stores API keys for LLM providers (Groq, Cerebras, etc.) with tier info
--              and automatic rotation when rate limits are hit.

CREATE TABLE IF NOT EXISTS provider_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider VARCHAR(50) NOT NULL,              -- 'groq', 'cerebras', 'openai', etc.
    api_key VARCHAR(255) NOT NULL,              -- The actual API key
    key_name VARCHAR(100) DEFAULT NULL,         -- Friendly name for the key
    tier ENUM('basic', 'production') NOT NULL DEFAULT 'basic',  -- Free tier or paid
    is_default TINYINT(1) NOT NULL DEFAULT 0,   -- Is this the default key for the provider?
    is_active TINYINT(1) NOT NULL DEFAULT 1,    -- Is this key currently active/enabled?
    last_used_at DATETIME DEFAULT NULL,         -- Last time this key was used
    last_error_at DATETIME DEFAULT NULL,        -- Last time an error occurred with this key
    error_count INT NOT NULL DEFAULT 0,         -- Number of consecutive errors
    rate_limit_reset_at DATETIME DEFAULT NULL,  -- When rate limit is expected to reset
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_provider (provider),
    INDEX idx_provider_active (provider, is_active),
    UNIQUE INDEX idx_provider_key (provider, api_key(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Note: Initial keys should be added via the admin panel or install process.
-- The system will use .env keys as fallback when no database keys are available.
