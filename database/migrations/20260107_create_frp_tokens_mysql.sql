-- FRP Tunnel Tokens Table
-- Stores authentication tokens for FRP tunnel clients

CREATE TABLE IF NOT EXISTS `frp_tokens` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `token` VARCHAR(64) NOT NULL,
    `name` VARCHAR(100) DEFAULT NULL COMMENT 'Optional friendly name for the token',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME DEFAULT NULL,
    `last_used_at` DATETIME DEFAULT NULL,
    `revoked` TINYINT(1) NOT NULL DEFAULT 0,
    `revoked_at` DATETIME DEFAULT NULL,
    
    UNIQUE KEY `idx_token` (`token`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_user_active` (`user_id`, `revoked`),
    
    CONSTRAINT `fk_frp_tokens_user` 
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) 
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- FRP Tunnel Sessions Table (optional - for tracking active tunnels)
CREATE TABLE IF NOT EXISTS `frp_sessions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `token_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `subdomain` VARCHAR(63) NOT NULL,
    `proxy_name` VARCHAR(100) NOT NULL,
    `proxy_type` ENUM('http', 'https', 'tcp', 'udp', 'stcp', 'xtcp') NOT NULL DEFAULT 'http',
    `local_port` INT UNSIGNED DEFAULT NULL,
    `remote_port` INT UNSIGNED DEFAULT NULL,
    `started_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `ended_at` DATETIME DEFAULT NULL,
    `bytes_in` BIGINT UNSIGNED DEFAULT 0,
    `bytes_out` BIGINT UNSIGNED DEFAULT 0,
    `last_heartbeat` DATETIME DEFAULT NULL,
    
    KEY `idx_user_id` (`user_id`),
    KEY `idx_token_id` (`token_id`),
    KEY `idx_subdomain` (`subdomain`),
    KEY `idx_active` (`ended_at`),
    
    CONSTRAINT `fk_frp_sessions_token` 
        FOREIGN KEY (`token_id`) REFERENCES `frp_tokens` (`id`) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_frp_sessions_user` 
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) 
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reserved Subdomains Table
CREATE TABLE IF NOT EXISTS `frp_reserved_subdomains` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `subdomain` VARCHAR(63) NOT NULL,
    `user_id` INT UNSIGNED DEFAULT NULL COMMENT 'NULL = system reserved',
    `reason` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY `idx_subdomain` (`subdomain`),
    KEY `idx_user_id` (`user_id`),
    
    CONSTRAINT `fk_frp_reserved_user` 
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) 
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert system-reserved subdomains
INSERT IGNORE INTO `frp_reserved_subdomains` (`subdomain`, `user_id`, `reason`) VALUES
('www', NULL, 'System reserved'),
('api', NULL, 'System reserved'),
('admin', NULL, 'System reserved'),
('mail', NULL, 'System reserved'),
('ftp', NULL, 'System reserved'),
('ssh', NULL, 'System reserved'),
('tunnel', NULL, 'System reserved'),
('app', NULL, 'System reserved'),
('dev', NULL, 'System reserved'),
('test', NULL, 'System reserved'),
('staging', NULL, 'System reserved'),
('ginto', NULL, 'System reserved'),
('oi', NULL, 'System reserved'),
('dashboard', NULL, 'System reserved'),
('console', NULL, 'System reserved'),
('panel', NULL, 'System reserved'),
('status', NULL, 'System reserved'),
('docs', NULL, 'System reserved'),
('help', NULL, 'System reserved'),
('support', NULL, 'System reserved');
