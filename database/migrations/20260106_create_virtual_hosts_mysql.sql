-- Virtual Hosts table for domain management with owner tracking
CREATE TABLE IF NOT EXISTS virtual_hosts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    domain VARCHAR(255) NOT NULL UNIQUE,
    document_root VARCHAR(512) DEFAULT NULL,
    
    -- Owner information
    owner_username VARCHAR(100) DEFAULT NULL,
    owner_fullname VARCHAR(255) DEFAULT NULL,
    user_id INT UNSIGNED DEFAULT NULL,
    
    -- Proxy target (for LXC/Docker)
    proxy_type ENUM('none', 'lxc', 'docker', 'external') DEFAULT 'none',
    proxy_target VARCHAR(255) DEFAULT NULL,  -- e.g., '10.185.95.52:80' or container name
    proxy_container_name VARCHAR(100) DEFAULT NULL,
    
    -- Configuration
    enable_php TINYINT(1) DEFAULT 1,
    enable_ssl TINYINT(1) DEFAULT 1,
    ssl_cert_path VARCHAR(512) DEFAULT NULL,
    ssl_key_path VARCHAR(512) DEFAULT NULL,
    
    -- Status
    is_enabled TINYINT(1) DEFAULT 1,
    last_ssl_check DATETIME DEFAULT NULL,
    ssl_expires_at DATETIME DEFAULT NULL,
    
    -- Timestamps
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_owner_username (owner_username),
    INDEX idx_user_id (user_id),
    INDEX idx_proxy_type (proxy_type),
    INDEX idx_is_enabled (is_enabled),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Import existing domains from Caddy configs (run once after migration)
-- This will be handled by the application
