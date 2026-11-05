-- Simplified CMS Setup for Existing Ginto Database
-- Commission Rates Table
CREATE TABLE IF NOT EXISTS commission_rates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    level INT UNSIGNED NOT NULL,
    rate DECIMAL(6,4) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Insert default commission rates
INSERT INTO commission_rates (level, rate) VALUES
    (1, 0.0500),
    (2, 0.0400),
    (3, 0.0300),
    (4, 0.0200),
    (5, 0.0100),
    (6, 0.0050),
    (7, 0.0025),
    (8, 0.0025),
    (9, 0.0000);
-- This works with the existing users table structure

-- Roles Table
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    permissions TEXT,
    is_system BOOLEAN DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default roles
INSERT IGNORE INTO roles (name, display_name, description, permissions, is_system) VALUES
('super_admin', 'Super Administrator', 'Full system access', '["*"]', 1),
('admin', 'Administrator', 'Site administration access', '["admin.*", "content.*", "users.*", "themes.*", "plugins.*"]', 1),
('editor', 'Editor', 'Content management access', '["content.*", "media.*"]', 1),
('author', 'Author', 'Content creation access', '["content.create", "content.edit.own", "media.upload"]', 1),
('user', 'User', 'Basic user access', '["dashboard.view", "profile.edit"]', 1);

-- Add CMS fields to existing users table (if they don't exist)

-- Users Table (with all latest fields)
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    referrer_id INT UNSIGNED NULL,
    fullname VARCHAR(100) NOT NULL,
    firstname VARCHAR(50) NULL,
    middlename VARCHAR(50) NULL,
    lastname VARCHAR(50) NULL,
    gender VARCHAR(10) NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    country VARCHAR(5) NULL,
    phone VARCHAR(20) NULL,
    password_hash VARCHAR(255) NOT NULL,
    ginto_level INT UNSIGNED NOT NULL DEFAULT 0,
    wallet_address VARCHAR(100) UNIQUE NULL COMMENT 'User wallet for crypto payouts',
    current_level_id INT UNSIGNED NOT NULL DEFAULT 1,
    total_balance DECIMAL(18,8) NOT NULL DEFAULT 0.00000000,
    is_admin BOOLEAN NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    role_id INT DEFAULT 5,
    status VARCHAR(20) DEFAULT 'active',
    public_id VARCHAR(16),
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    avatar VARCHAR(255),
    bio TEXT,
    last_login DATETIME,
    email_verified_at DATETIME,
    two_factor_enabled BOOLEAN DEFAULT 0,
    preferences TEXT,
    package VARCHAR(50),
    package_amount INT,
    package_currency VARCHAR(10),
    pay_method VARCHAR(20),
    playground_use_sandbox BOOLEAN DEFAULT 0,
    -- Foreign Keys
    FOREIGN KEY (referrer_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (current_level_id) REFERENCES levels(id)
);

-- Update existing users to have proper first_name and last_name from existing fields
UPDATE users SET 
    first_name = COALESCE(firstname, SUBSTRING_INDEX(fullname, ' ', 1)),
    last_name = COALESCE(lastname, SUBSTRING_INDEX(fullname, ' ', -1))
WHERE first_name IS NULL OR last_name IS NULL;

-- Populate public_id for existing users if missing using UUID-derived hex string
UPDATE users SET public_id = LEFT(REPLACE(UUID(), '-', ''), 16) WHERE public_id IS NULL OR public_id = '';

-- Add unique index for public_id if not exists (safe for older MySQL versions)
SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'idx_users_public_id') = 0,
    'ALTER TABLE users ADD UNIQUE INDEX idx_users_public_id (public_id(16))',
    'SELECT "Index idx_users_public_id already exists" AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ensure package/payment columns exist for older installs (one-time ALTER checks)
SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'package') = 0,
    'ALTER TABLE users ADD COLUMN package VARCHAR(50)',
    'SELECT "Column package already exists" AS message'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'package_amount') = 0,
    'ALTER TABLE users ADD COLUMN package_amount DECIMAL(18,8)',
    'SELECT "Column package_amount already exists" AS message'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'package_currency') = 0,
    'ALTER TABLE users ADD COLUMN package_currency VARCHAR(10)',
    'SELECT "Column package_currency already exists" AS message'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'pay_method') = 0,
    'ALTER TABLE users ADD COLUMN pay_method VARCHAR(20)',
    'SELECT "Column pay_method already exists" AS message'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Categories Table
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    parent_id INT,
    sort_order INT DEFAULT 0,
    meta_title VARCHAR(200),
    meta_description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tags Table
CREATE TABLE IF NOT EXISTS tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    color VARCHAR(7),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Pages Table
CREATE TABLE IF NOT EXISTS pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    content TEXT,
    excerpt TEXT,
    template VARCHAR(100) DEFAULT 'default',
    status VARCHAR(20) DEFAULT 'draft',
    author_id INT NOT NULL,
    parent_id INT,
    sort_order INT DEFAULT 0,
    featured_image VARCHAR(255),
    meta_title VARCHAR(200),
    meta_description TEXT,
    meta_keywords TEXT,
    custom_css TEXT,
    custom_js TEXT,
    published_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Posts Table
CREATE TABLE IF NOT EXISTS posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    content TEXT,
    excerpt TEXT,
    status VARCHAR(20) DEFAULT 'draft',
    author_id INT NOT NULL,
    category_id INT,
    featured_image VARCHAR(255),
    views INT DEFAULT 0,
    meta_title VARCHAR(200),
    meta_description TEXT,
    meta_keywords TEXT,
    published_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Post Tags
CREATE TABLE IF NOT EXISTS post_tags (
    post_id INT NOT NULL,
    tag_id INT NOT NULL,
    PRIMARY KEY (post_id, tag_id)
);

-- Media Library
CREATE TABLE IF NOT EXISTS media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    path VARCHAR(500) NOT NULL,
    url VARCHAR(500) NOT NULL,
    mime_type VARCHAR(100),
    size INT,
    width INT,
    height INT,
    alt_text VARCHAR(255),
    caption TEXT,
    uploaded_by INT NOT NULL,
    folder VARCHAR(255) DEFAULT '/',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Settings Table
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(100) NOT NULL UNIQUE,
    value TEXT,
    type VARCHAR(20) DEFAULT 'string',
    group_name VARCHAR(50) DEFAULT 'general',
    description TEXT,
    is_public BOOLEAN DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default settings
INSERT IGNORE INTO settings (`key`, value, type, group_name, description, is_public) VALUES
('site_name', 'Ginto CMS', 'string', 'general', 'Website name', 1),
('site_description', 'A modern content management system', 'string', 'general', 'Website description', 1),
('site_url', 'http://localhost:8000', 'string', 'general', 'Website URL', 1),
('admin_email', 'admin@example.com', 'string', 'general', 'Administrator email', 0),
('timezone', 'UTC', 'string', 'general', 'Default timezone', 1),
('posts_per_page', '10', 'integer', 'content', 'Posts per page', 1),
('maintenance_mode', '0', 'boolean', 'system', 'Maintenance mode enabled', 0);

-- Activity Logs
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    model_type VARCHAR(100),
    model_id INT,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Client sandboxes mapping table (per-user playground sandboxes)
CREATE TABLE IF NOT EXISTS client_sandboxes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    public_id VARCHAR(16) NULL,
    sandbox_id VARCHAR(12) NOT NULL UNIQUE,
    quota_bytes BIGINT NOT NULL DEFAULT 104857600,
    used_bytes BIGINT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Index for quick sandbox lookups
-- Create index if it does not exist (MySQL does not support CREATE INDEX IF NOT EXISTS)
SELECT COUNT(*) INTO @idx_exists
FROM information_schema.statistics
WHERE table_schema = DATABASE()
    AND table_name = 'client_sandboxes'
    AND index_name = 'idx_client_sandbox_id';

SET @sql = IF(@idx_exists = 0,
    'CREATE INDEX idx_client_sandbox_id ON client_sandboxes (sandbox_id)',
    'SELECT 1');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Create some basic indexes
CREATE INDEX idx_users_role ON users(role_id);
CREATE INDEX idx_users_status ON users(status);
CREATE INDEX idx_pages_slug ON pages(slug);
CREATE INDEX idx_pages_status ON pages(status);
CREATE INDEX idx_posts_slug ON posts(slug);
CREATE INDEX idx_posts_status ON posts(status);

-- Orders table (ensure presence for recording package purchases)
CREATE TABLE IF NOT EXISTS orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    package VARCHAR(100) NOT NULL DEFAULT 'Gold',
    amount DECIMAL(18,8) NOT NULL DEFAULT 0.00000000,
    currency VARCHAR(10) NOT NULL DEFAULT 'PHP',
    status ENUM('pending','completed','cancelled') NOT NULL DEFAULT 'completed',
    metadata JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- =====================================================
-- Rate Limiting & API Request Tracking Tables
-- =====================================================

-- API Requests table - tracks all LLM API requests for rate limiting
CREATE TABLE IF NOT EXISTS api_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
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
    INDEX idx_api_user_id (user_id),
    INDEX idx_api_provider (provider),
    INDEX idx_api_created_at (created_at),
    INDEX idx_api_user_created (user_id, created_at),
    INDEX idx_api_provider_created (provider, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rate limit configuration table
CREATE TABLE IF NOT EXISTS rate_limit_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
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
('cerebras', 'gpt-oss-120b', 'tpd', 1000000),
-- PlayAI-TTS (via Groq): RPM=250, RPD=100000 (characters per day limit 250K)
-- Source: Groq TTS rate limits, conservative estimates for Developer tier
('groq', 'playai-tts', 'rpm', 250),
('groq', 'playai-tts', 'rpd', 100000),
('groq', 'playai-tts-turbo', 'rpm', 250),
('groq', 'playai-tts-turbo', 'rpd', 100000),
-- gpt-4o-mini-tts (OpenAI compatible via Groq)
('groq', 'gpt-4o-mini-tts', 'rpm', 250),
('groq', 'gpt-4o-mini-tts', 'rpd', 100000)
ON DUPLICATE KEY UPDATE limit_value = VALUES(limit_value);

-- =====================================================
-- Provider API Keys Table (Multi-Key Rotation)
-- =====================================================
-- Stores multiple API keys per provider with tier info.
-- Keys are rotated automatically when rate limits are hit.
-- Usage order: .env keys first, then DB entries by ID order.

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

-- =====================================================
-- User Rate Limits Table (Per-User Usage Tracking)
-- =====================================================
-- Tracks API usage per user/session for rate limiting.
-- Used by UserRateLimiter to enforce per-user limits based on tier.

CREATE TABLE IF NOT EXISTS user_rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,                            -- User ID (null for visitors)
    visitor_ip VARCHAR(45) NULL,                 -- IP address for visitors (IPv6 compatible)
    provider VARCHAR(50) NOT NULL DEFAULT 'cerebras',  -- 'groq', 'cerebras', etc.
    date DATE NOT NULL,                          -- Date for daily tracking
    minute_bucket DATETIME NULL,                 -- Minute bucket for per-minute tracking
    requests_count INT NOT NULL DEFAULT 0,       -- Number of requests in this period
    tokens_used INT NOT NULL DEFAULT 0,          -- Total tokens used in this period
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_user_date (user_id, date),
    INDEX idx_visitor_date (visitor_ip, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;