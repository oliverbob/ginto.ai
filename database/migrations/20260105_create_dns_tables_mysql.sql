-- DNS Zone Management Tables
-- Compatible with PowerDNS schema for easy integration

-- DNS Zones (domains)
CREATE TABLE IF NOT EXISTS dns_zones (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    type ENUM('MASTER', 'SLAVE', 'NATIVE') DEFAULT 'NATIVE',
    master VARCHAR(255) NULL,
    notified_serial INT UNSIGNED DEFAULT 0,
    account VARCHAR(40) NULL,
    dnssec BOOLEAN DEFAULT FALSE,
    nsec3param VARCHAR(255) NULL,
    nsec3narrow BOOLEAN DEFAULT FALSE,
    presigned BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- DNS Records
CREATE TABLE IF NOT EXISTS dns_records (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    zone_id INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(10) NOT NULL,
    content TEXT NOT NULL,
    ttl INT UNSIGNED DEFAULT 3600,
    priority INT UNSIGNED DEFAULT 0,
    disabled BOOLEAN DEFAULT FALSE,
    ordername VARCHAR(255) NULL,
    auth BOOLEAN DEFAULT TRUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (zone_id) REFERENCES dns_zones(id) ON DELETE CASCADE,
    INDEX idx_zone (zone_id),
    INDEX idx_name_type (name, type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- SOA (Start of Authority) defaults for zones
CREATE TABLE IF NOT EXISTS dns_soa_defaults (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    primary_ns VARCHAR(255) DEFAULT 'ns1.ginto.ai',
    admin_email VARCHAR(255) DEFAULT 'admin.ginto.ai',
    refresh INT UNSIGNED DEFAULT 10800,
    retry INT UNSIGNED DEFAULT 3600,
    expire INT UNSIGNED DEFAULT 604800,
    minimum_ttl INT UNSIGNED DEFAULT 3600,
    default_ttl INT UNSIGNED DEFAULT 3600,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default SOA settings
INSERT INTO dns_soa_defaults (primary_ns, admin_email) VALUES ('ns1.ginto.ai', 'admin.ginto.ai');

-- DNS-over-HTTPS configuration
CREATE TABLE IF NOT EXISTS dns_doh_config (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    enabled BOOLEAN DEFAULT FALSE,
    endpoint VARCHAR(255) DEFAULT '/dns-query',
    upstream_servers TEXT,
    cache_ttl INT UNSIGNED DEFAULT 300,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default DoH config
INSERT INTO dns_doh_config (enabled, endpoint) VALUES (FALSE, '/dns-query');
