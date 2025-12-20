-- CMS Core Database Schema for MySQL
-- Commission Rates Table
CREATE TABLE IF NOT EXISTS commission_rates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    level INT UNSIGNED NOT NULL,
    rate DECIMAL(6,4) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Insert default commission rates (skip if exists)
INSERT IGNORE INTO commission_rates (level, rate) VALUES
    (1, 0.0500),
    (2, 0.0400),
    (3, 0.0300),
    (4, 0.0200),
    (5, 0.0100),
    (6, 0.0050),
    (7, 0.0025),
    (8, 0.0025),
    (9, 0.0000);

-- Version: 1.0
-- Created: 2025-11-13

-- NOTE: This migration uses CREATE TABLE IF NOT EXISTS to preserve existing data
-- Tables are only created if they don't exist - no data is destroyed

-- Levels Table (create first, referenced by users)
CREATE TABLE IF NOT EXISTS levels (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    cost_amount DECIMAL(10, 4) NOT NULL,
    cost_currency VARCHAR(10) NOT NULL,
    commission_rate_json JSON NOT NULL COMMENT 'e.g., {"L1": 0.50, "L2": 0.10}',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Initial Levels Data
-- Initial Levels Data
-- Note: default initial level rows removed to avoid inserting legacy tiers 1-3.
-- If you need to seed levels, add INSERT statements here for the desired default levels.

-- Roles Table
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    permissions TEXT, -- JSON array of permissions
    is_system BOOLEAN DEFAULT 0, -- System roles cannot be deleted
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

-- Create or update users table with CMS fields
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    -- Public alphanumeric identifier suitable for public URLs (e.g. profile/abc123...)
    public_id VARCHAR(16) NOT NULL UNIQUE,
    referrer_id INT UNSIGNED NULL,
    fullname VARCHAR(100) NOT NULL,
    firstname VARCHAR(50) NULL,
    middlename VARCHAR(50) NULL,
    lastname VARCHAR(50) NULL,
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
    role_id INT DEFAULT 5,
    status VARCHAR(20) DEFAULT 'active',
    avatar VARCHAR(255) NULL,
    bio TEXT NULL,
    last_login DATETIME NULL,
    email_verified_at DATETIME NULL,
    two_factor_enabled BOOLEAN DEFAULT 0,
    preferences TEXT NULL,
    -- Package/payment fields (membership package selected at registration)
    package VARCHAR(50) NULL,
    package_amount DECIMAL(18,8) NULL,
    package_currency VARCHAR(10) NULL,
    pay_method VARCHAR(20) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign Keys
    FOREIGN KEY (referrer_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (role_id) REFERENCES roles(id),
    FOREIGN KEY (current_level_id) REFERENCES levels(id)
);

-- Ensure public_id exists for existing installs: add column if missing and create trigger
SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'public_id') = 0,
    'ALTER TABLE users ADD COLUMN public_id VARCHAR(16)',
    'SELECT "Column public_id already exists" AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

DROP TRIGGER IF EXISTS users_before_insert_public_id;
CREATE TRIGGER users_before_insert_public_id BEFORE INSERT ON users
FOR EACH ROW
BEGIN
    IF NEW.public_id IS NULL OR NEW.public_id = '' THEN
        SET NEW.public_id = LEFT(REPLACE(UUID(), '-', ''), 16);
    END IF;
END;

-- If users table already exists, add CMS fields (MySQL compatible)
-- Note: Using separate statements since MySQL doesn't support IF NOT EXISTS for ALTER COLUMN

-- Add role_id column
SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'role_id') = 0,
    'ALTER TABLE users ADD COLUMN role_id INT DEFAULT 5',
    'SELECT "Column role_id already exists" AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add status column
SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'status') = 0,
    'ALTER TABLE users ADD COLUMN status VARCHAR(20) DEFAULT \'active\'',
    'SELECT "Column status already exists" AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add other CMS columns
SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'avatar') = 0,
    'ALTER TABLE users ADD COLUMN avatar VARCHAR(255)',
    'SELECT "Column avatar already exists" AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'bio') = 0,
    'ALTER TABLE users ADD COLUMN bio TEXT',
    'SELECT "Column bio already exists" AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add package/payment columns if missing (for older installs)
SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'package') = 0,
    'ALTER TABLE users ADD COLUMN package VARCHAR(50)',
    'SELECT "Column package already exists" AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'package_amount') = 0,
    'ALTER TABLE users ADD COLUMN package_amount DECIMAL(18,8)',
    'SELECT "Column package_amount already exists" AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'package_currency') = 0,
    'ALTER TABLE users ADD COLUMN package_currency VARCHAR(10)',
    'SELECT "Column package_currency already exists" AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'pay_method') = 0,
    'ALTER TABLE users ADD COLUMN pay_method VARCHAR(20)',
    'SELECT "Column pay_method already exists" AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add foreign key constraint if it doesn't exist
SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'role_id' AND REFERENCED_TABLE_NAME = 'roles') = 0,
    'ALTER TABLE users ADD FOREIGN KEY (role_id) REFERENCES roles(id)',
    'SELECT "Foreign key already exists" AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Transactions Table (unified for MLM and CMS)
CREATE TABLE IF NOT EXISTS transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL COMMENT 'The user affected (payer or recipient)',
    type ENUM('upgrade', 'commission', 'withdrawal', 'deposit', 'purchase', 'refund', 'bonus') NOT NULL,
    level_id INT UNSIGNED NULL COMMENT 'MLM level reference',
    source_user_id INT UNSIGNED NULL COMMENT 'The user who triggered the transaction (e.g., downline)',
    amount DECIMAL(18, 8) NOT NULL,
    currency VARCHAR(10) DEFAULT 'USD',
    balance_before DECIMAL(18,8) DEFAULT 0,
    balance_after DECIMAL(18,8) DEFAULT 0,
    status ENUM('pending', 'completed', 'failed') NOT NULL DEFAULT 'completed',
    tx_hash VARCHAR(100) UNIQUE NULL COMMENT 'Blockchain hash for on-chain audit',
    reference_id VARCHAR(100) NULL COMMENT 'External reference ID',
    reference_type VARCHAR(50) NULL COMMENT 'Type of reference (commission, payout, etc)',
    description TEXT NULL,
    metadata JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    -- Foreign Keys
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (level_id) REFERENCES levels(id) ON DELETE SET NULL,
    FOREIGN KEY (source_user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Referrals Table
CREATE TABLE IF NOT EXISTS referrals (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    referrer_id INT UNSIGNED NOT NULL COMMENT 'The user who referred (sponsor)',
    referred_id INT UNSIGNED NOT NULL COMMENT 'The user who was referred',
    level_joined INT UNSIGNED NOT NULL COMMENT 'Level at which the user joined',
    status ENUM('pending', 'active', 'inactive') NOT NULL DEFAULT 'pending',
    earnings_generated DECIMAL(18,8) NOT NULL DEFAULT 0.00000000 COMMENT 'Total commissions generated from this referral',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    activated_at DATETIME NULL COMMENT 'When the referral became active (paid)',
    
    -- Foreign Keys
    FOREIGN KEY (referrer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (referred_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (level_joined) REFERENCES levels(id),
    
    -- Ensure each user can only be referred once
    UNIQUE KEY unique_referred (referred_id),
    
    -- Index for quick lookups
    INDEX idx_referrer (referrer_id),
    INDEX idx_status (status)
);

-- Categories Table (for content organization)
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
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Tags Table
CREATE TABLE IF NOT EXISTS tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    color VARCHAR(7), -- hex color code
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Pages Table (static content)
CREATE TABLE IF NOT EXISTS pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    content TEXT,
    excerpt TEXT,
    template VARCHAR(100) DEFAULT 'default',
    status VARCHAR(20) DEFAULT 'draft', -- draft, published, archived
    author_id INT UNSIGNED NOT NULL,
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
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES pages(id) ON DELETE SET NULL
);

-- Posts Table (blog/news content)
CREATE TABLE IF NOT EXISTS posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    content TEXT,
    excerpt TEXT,
    status VARCHAR(20) DEFAULT 'draft', -- draft, published, archived
    author_id INT UNSIGNED NOT NULL,
    category_id INT,
    featured_image VARCHAR(255),
    views INT DEFAULT 0,
    meta_title VARCHAR(200),
    meta_description TEXT,
    meta_keywords TEXT,
    published_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Post Tags (many-to-many)
CREATE TABLE IF NOT EXISTS post_tags (
    post_id INT NOT NULL,
    tag_id INT NOT NULL,
    PRIMARY KEY (post_id, tag_id),
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
);

-- Media Library Table
CREATE TABLE IF NOT EXISTS media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    path VARCHAR(500) NOT NULL,
    url VARCHAR(500) NOT NULL,
    mime_type VARCHAR(100),
    size INT, -- file size in bytes
    width INT, -- for images
    height INT, -- for images
    alt_text VARCHAR(255),
    caption TEXT,
    uploaded_by INT UNSIGNED NOT NULL,
    folder VARCHAR(255) DEFAULT '/',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Menus Table
CREATE TABLE IF NOT EXISTS menus (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    location VARCHAR(100), -- header, footer, sidebar, etc.
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Menu Items Table
CREATE TABLE IF NOT EXISTS menu_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    menu_id INT NOT NULL,
    parent_id INT,
    title VARCHAR(100) NOT NULL,
    url VARCHAR(500),
    target VARCHAR(20) DEFAULT '_self',
    css_class VARCHAR(100),
    sort_order INT DEFAULT 0,
    page_id INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (menu_id) REFERENCES menus(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES menu_items(id) ON DELETE SET NULL,
    FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE SET NULL
);

-- Themes Table
CREATE TABLE IF NOT EXISTS themes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    version VARCHAR(20),
    author VARCHAR(100),
    screenshot VARCHAR(255),
    path VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT 0,
    settings TEXT, -- JSON for theme settings
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Plugins Table
CREATE TABLE IF NOT EXISTS plugins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    version VARCHAR(20),
    author VARCHAR(100),
    path VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT 0,
    settings TEXT, -- JSON for plugin settings
    dependencies TEXT, -- JSON array of required plugins
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Settings Table (system configuration)
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(100) NOT NULL UNIQUE, -- key is a reserved word in MySQL
    value TEXT,
    type VARCHAR(20) DEFAULT 'string', -- string, integer, boolean, json
    group_name VARCHAR(50) DEFAULT 'general',
    description TEXT,
    is_public BOOLEAN DEFAULT 0, -- can be accessed from frontend
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
('date_format', 'Y-m-d', 'string', 'general', 'Date display format', 1),
('time_format', 'H:i:s', 'string', 'general', 'Time display format', 1),
('posts_per_page', '10', 'integer', 'content', 'Posts per page', 1),
('comments_enabled', '1', 'boolean', 'content', 'Enable comments', 1),
('registration_enabled', '1', 'boolean', 'users', 'Allow user registration', 1),
('email_verification', '0', 'boolean', 'users', 'Require email verification', 0),
('maintenance_mode', '0', 'boolean', 'system', 'Maintenance mode enabled', 0),
('cache_enabled', '1', 'boolean', 'system', 'Enable caching', 0),
('debug_mode', '0', 'boolean', 'system', 'Debug mode enabled', 0);

-- Activity Logs Table (audit trail)
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED,
    action VARCHAR(100) NOT NULL,
    model_type VARCHAR(100), -- pages, posts, users, etc.
    model_id INT,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Revisions Table (version control)
CREATE TABLE IF NOT EXISTS revisions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    model_type VARCHAR(100) NOT NULL, -- pages, posts, etc.
    model_id INT NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    content TEXT, -- JSON snapshot of the model
    changes TEXT, -- JSON of what changed
    version INT DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Comments Table
CREATE TABLE IF NOT EXISTS comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    parent_id INT,
    author_name VARCHAR(100),
    author_email VARCHAR(255),
    author_url VARCHAR(255),
    user_id INT UNSIGNED,
    content TEXT NOT NULL,
    status VARCHAR(20) DEFAULT 'pending', -- pending, approved, spam, trash
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Widgets Table (for theme areas)
CREATE TABLE IF NOT EXISTS widgets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    title VARCHAR(100),
    content TEXT,
    type VARCHAR(50) NOT NULL, -- text, html, menu, recent_posts, etc.
    area VARCHAR(50), -- sidebar, footer, header
    position INT DEFAULT 0,
    settings TEXT, -- JSON for widget settings
    is_active BOOLEAN DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create indexes for performance
CREATE INDEX idx_users_role ON users(role_id);
CREATE INDEX idx_users_status ON users(status);
CREATE INDEX idx_pages_slug ON pages(slug);
CREATE INDEX idx_pages_status ON pages(status);
CREATE INDEX idx_pages_author ON pages(author_id);
CREATE INDEX idx_posts_slug ON posts(slug);
CREATE INDEX idx_posts_status ON posts(status);
CREATE INDEX idx_posts_author ON posts(author_id);
CREATE INDEX idx_posts_category ON posts(category_id);
CREATE INDEX idx_media_uploaded_by ON media(uploaded_by);
CREATE INDEX idx_comments_post ON comments(post_id);
CREATE INDEX idx_comments_status ON comments(status);
CREATE INDEX idx_activity_logs_user ON activity_logs(user_id);
CREATE INDEX idx_activity_logs_model ON activity_logs(model_type, model_id);

-- Commissions Table for MLM tracking
CREATE TABLE IF NOT EXISTS commissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    referrer_id INT UNSIGNED NULL,
    amount DECIMAL(18,8) NOT NULL,
    type ENUM('direct', 'indirect', 'bonus', 'override') DEFAULT 'direct',
    level TINYINT NOT NULL DEFAULT 1,
    transaction_id VARCHAR(100) NULL,
    description TEXT NULL,
    status ENUM('pending', 'paid', 'cancelled') DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (referrer_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Payouts Table for withdrawal requests
CREATE TABLE IF NOT EXISTS payouts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    amount DECIMAL(18,8) NOT NULL,
    wallet_address VARCHAR(255) NOT NULL,
    transaction_hash VARCHAR(255) NULL,
    status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    admin_notes TEXT NULL,
    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME NULL,
    completed_at DATETIME NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);



-- Financial Indexes
CREATE INDEX idx_commissions_user ON commissions(user_id);
CREATE INDEX idx_commissions_referrer ON commissions(referrer_id);
CREATE INDEX idx_commissions_status ON commissions(status);
CREATE INDEX idx_payouts_user ON payouts(user_id);
CREATE INDEX idx_payouts_status ON payouts(status);
CREATE INDEX idx_transactions_user ON transactions(user_id);
CREATE INDEX idx_transactions_type ON transactions(type);
CREATE INDEX idx_transactions_level ON transactions(level_id);
CREATE INDEX idx_transactions_source ON transactions(source_user_id);
CREATE INDEX idx_transactions_reference ON transactions(reference_type, reference_id);
CREATE INDEX idx_transactions_status ON transactions(status);
