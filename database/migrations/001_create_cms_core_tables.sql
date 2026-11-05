-- CMS Core Database Schema
-- Version: 1.0
-- Created: 2025-11-13

-- Enable foreign key constraints
PRAGMA foreign_keys = ON;

-- Roles Table
CREATE TABLE IF NOT EXISTS roles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    permissions TEXT, -- JSON array of permissions
    is_system BOOLEAN DEFAULT 0, -- System roles cannot be deleted
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Insert default roles
INSERT OR IGNORE INTO roles (name, display_name, description, permissions, is_system) VALUES
('super_admin', 'Super Administrator', 'Full system access', '["*"]', 1),
('admin', 'Administrator', 'Site administration access', '["admin.*", "content.*", "users.*", "themes.*", "plugins.*"]', 1),
('editor', 'Editor', 'Content management access', '["content.*", "media.*"]', 1),
('author', 'Author', 'Content creation access', '["content.create", "content.edit.own", "media.upload"]', 1),
('user', 'User', 'Basic user access', '["dashboard.view", "profile.edit"]', 1);

-- Update existing users table to add CMS fields
ALTER TABLE users ADD COLUMN role_id INTEGER DEFAULT 5 REFERENCES roles(id);
ALTER TABLE users ADD COLUMN status VARCHAR(20) DEFAULT 'active'; -- active, inactive, suspended
ALTER TABLE users ADD COLUMN avatar VARCHAR(255);
ALTER TABLE users ADD COLUMN bio TEXT;
ALTER TABLE users ADD COLUMN last_login DATETIME;
ALTER TABLE users ADD COLUMN email_verified_at DATETIME;
ALTER TABLE users ADD COLUMN two_factor_enabled BOOLEAN DEFAULT 0;
ALTER TABLE users ADD COLUMN preferences TEXT; -- JSON for user preferences

-- Categories Table (for content organization)
CREATE TABLE IF NOT EXISTS categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    parent_id INTEGER REFERENCES categories(id),
    sort_order INTEGER DEFAULT 0,
    meta_title VARCHAR(200),
    meta_description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Tags Table
CREATE TABLE IF NOT EXISTS tags (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    color VARCHAR(7), -- hex color code
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Pages Table (static content)
CREATE TABLE IF NOT EXISTS pages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    content TEXT,
    excerpt TEXT,
    template VARCHAR(100) DEFAULT 'default',
    status VARCHAR(20) DEFAULT 'draft', -- draft, published, archived
    author_id INTEGER NOT NULL REFERENCES users(id),
    parent_id INTEGER REFERENCES pages(id),
    sort_order INTEGER DEFAULT 0,
    featured_image VARCHAR(255),
    meta_title VARCHAR(200),
    meta_description TEXT,
    meta_keywords TEXT,
    custom_css TEXT,
    custom_js TEXT,
    published_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Levels Table (create first, referenced by users)
CREATE TABLE IF NOT EXISTS levels (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(50) NOT NULL,
    cost_amount DECIMAL(10, 4) NOT NULL,
    cost_currency VARCHAR(10) NOT NULL,
    commission_rate_json TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT (datetime('now'))
);

-- Note: No seed data for levels in the core DDL so installer can seed the appropriate production tiers.

-- Posts Table (blog/news content)
CREATE TABLE IF NOT EXISTS posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    content TEXT,
    excerpt TEXT,
    status VARCHAR(20) DEFAULT 'draft', -- draft, published, archived
    author_id INTEGER NOT NULL REFERENCES users(id),
    category_id INTEGER REFERENCES categories(id),
    featured_image VARCHAR(255),
    views INTEGER DEFAULT 0,
    meta_title VARCHAR(200),
    meta_description TEXT,
    meta_keywords TEXT,
    published_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Post Tags (many-to-many)
CREATE TABLE IF NOT EXISTS post_tags (
    post_id INTEGER NOT NULL REFERENCES posts(id) ON DELETE CASCADE,
    tag_id INTEGER NOT NULL REFERENCES tags(id) ON DELETE CASCADE,
    PRIMARY KEY (post_id, tag_id)
);

-- Media Library Table
CREATE TABLE IF NOT EXISTS media (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    path VARCHAR(500) NOT NULL,
    url VARCHAR(500) NOT NULL,
    mime_type VARCHAR(100),
    size INTEGER, -- file size in bytes
    width INTEGER, -- for images
    height INTEGER, -- for images
    alt_text VARCHAR(255),
    caption TEXT,
    uploaded_by INTEGER NOT NULL REFERENCES users(id),
    folder VARCHAR(255) DEFAULT '/',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Menus Table
CREATE TABLE IF NOT EXISTS menus (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    location VARCHAR(100), -- header, footer, sidebar, etc.
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Menu Items Table
CREATE TABLE IF NOT EXISTS menu_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    menu_id INTEGER NOT NULL REFERENCES menus(id) ON DELETE CASCADE,
    parent_id INTEGER REFERENCES menu_items(id),
    title VARCHAR(100) NOT NULL,
    url VARCHAR(500),
    target VARCHAR(20) DEFAULT '_self',
    css_class VARCHAR(100),
    sort_order INTEGER DEFAULT 0,
    page_id INTEGER REFERENCES pages(id),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Themes Table
CREATE TABLE IF NOT EXISTS themes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
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
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Plugins Table
CREATE TABLE IF NOT EXISTS plugins (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
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
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Settings Table (system configuration)
CREATE TABLE IF NOT EXISTS settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    key VARCHAR(100) NOT NULL UNIQUE,
    value TEXT,
    type VARCHAR(20) DEFAULT 'string', -- string, integer, boolean, json
    group_name VARCHAR(50) DEFAULT 'general',
    description TEXT,
    is_public BOOLEAN DEFAULT 0, -- can be accessed from frontend
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Insert default settings
INSERT OR IGNORE INTO settings (key, value, type, group_name, description, is_public) VALUES
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
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER REFERENCES users(id),
    action VARCHAR(100) NOT NULL,
    model_type VARCHAR(100), -- pages, posts, users, etc.
    model_id INTEGER,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Revisions Table (version control)
CREATE TABLE IF NOT EXISTS revisions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    model_type VARCHAR(100) NOT NULL, -- pages, posts, etc.
    model_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL REFERENCES users(id),
    content TEXT, -- JSON snapshot of the model
    changes TEXT, -- JSON of what changed
    version INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Comments Table
CREATE TABLE IF NOT EXISTS comments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id INTEGER NOT NULL REFERENCES posts(id) ON DELETE CASCADE,
    parent_id INTEGER REFERENCES comments(id),
    author_name VARCHAR(100),
    author_email VARCHAR(255),
    author_url VARCHAR(255),
    user_id INTEGER REFERENCES users(id),
    content TEXT NOT NULL,
    status VARCHAR(20) DEFAULT 'pending', -- pending, approved, spam, trash
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Widgets Table (for theme areas)
CREATE TABLE IF NOT EXISTS widgets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(100) NOT NULL,
    title VARCHAR(100),
    content TEXT,
    type VARCHAR(50) NOT NULL, -- text, html, menu, recent_posts, etc.
    area VARCHAR(50), -- sidebar, footer, header
    position INTEGER DEFAULT 0,
    settings TEXT, -- JSON for widget settings
    is_active BOOLEAN DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role_id);
CREATE INDEX IF NOT EXISTS idx_users_status ON users(status);
CREATE INDEX IF NOT EXISTS idx_pages_slug ON pages(slug);
CREATE INDEX IF NOT EXISTS idx_pages_status ON pages(status);
CREATE INDEX IF NOT EXISTS idx_pages_author ON pages(author_id);
CREATE INDEX IF NOT EXISTS idx_posts_slug ON posts(slug);
CREATE INDEX IF NOT EXISTS idx_posts_status ON posts(status);
CREATE INDEX IF NOT EXISTS idx_posts_author ON posts(author_id);
CREATE INDEX IF NOT EXISTS idx_posts_category ON posts(category_id);
CREATE INDEX IF NOT EXISTS idx_media_uploaded_by ON media(uploaded_by);
CREATE INDEX IF NOT EXISTS idx_comments_post ON comments(post_id);
CREATE INDEX IF NOT EXISTS idx_comments_status ON comments(status);
CREATE INDEX IF NOT EXISTS idx_activity_logs_user ON activity_logs(user_id);
CREATE INDEX IF NOT EXISTS idx_activity_logs_model ON activity_logs(model_type, model_id);