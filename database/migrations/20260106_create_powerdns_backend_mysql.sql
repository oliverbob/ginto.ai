-- PowerDNS Native MySQL Backend Schema
-- Required for PowerDNS authoritative server with gmysql backend
-- See: https://doc.powerdns.com/authoritative/backends/generic-mysql.html
--
-- NOTE: These tables (domains, records, etc.) are used directly by PowerDNS.
-- The dns_zones/dns_records tables in 20260105 are for our app's internal use.
-- The HostingController syncs data FROM dns_zones/dns_records TO these PowerDNS tables.

-- Domains (zones) - PowerDNS native table
CREATE TABLE IF NOT EXISTS domains (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    master VARCHAR(128) DEFAULT NULL,
    last_check INT DEFAULT NULL,
    type VARCHAR(8) NOT NULL DEFAULT 'NATIVE',
    notified_serial INT UNSIGNED DEFAULT NULL,
    account VARCHAR(40) DEFAULT NULL,
    options VARCHAR(64000) DEFAULT NULL,
    catalog VARCHAR(255) DEFAULT NULL,
    UNIQUE INDEX name_index (name),
    INDEX catalog_idx (catalog)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- Records - PowerDNS native table
CREATE TABLE IF NOT EXISTS records (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    domain_id INT DEFAULT NULL,
    name VARCHAR(255) DEFAULT NULL,
    type VARCHAR(10) DEFAULT NULL,
    content VARCHAR(64000) DEFAULT NULL,
    ttl INT DEFAULT NULL,
    prio INT DEFAULT NULL,
    disabled TINYINT(1) DEFAULT 0,
    ordername VARCHAR(255) BINARY DEFAULT NULL,
    auth TINYINT(1) DEFAULT 1,
    INDEX nametype_index (name, type),
    INDEX domain_id (domain_id),
    INDEX ordername (ordername)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- Supermasters - for automatic zone provisioning from master servers
CREATE TABLE IF NOT EXISTS supermasters (
    ip VARCHAR(64) NOT NULL,
    nameserver VARCHAR(255) NOT NULL,
    account VARCHAR(40) NOT NULL,
    PRIMARY KEY (ip, nameserver)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- Comments on records
CREATE TABLE IF NOT EXISTS comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    domain_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(10) NOT NULL,
    modified_at INT NOT NULL,
    account VARCHAR(40) NOT NULL,
    comment TEXT NOT NULL,
    INDEX comments_domain_id_idx (domain_id),
    INDEX comments_name_type_idx (name, type),
    INDEX comments_order_idx (domain_id, modified_at)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- Domain metadata (DNSSEC keys, ALSO-NOTIFY, etc.)
CREATE TABLE IF NOT EXISTS domainmetadata (
    id INT AUTO_INCREMENT PRIMARY KEY,
    domain_id INT NOT NULL,
    kind VARCHAR(32),
    content TEXT,
    INDEX domainmetadata_idx (domain_id, kind)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- DNSSEC cryptographic keys
CREATE TABLE IF NOT EXISTS cryptokeys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    domain_id INT NOT NULL,
    flags INT NOT NULL,
    active BOOL,
    published BOOL DEFAULT 1,
    content TEXT,
    INDEX domainidindex (domain_id)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- TSIG keys for zone transfers
CREATE TABLE IF NOT EXISTS tsigkeys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255),
    algorithm VARCHAR(50),
    secret VARCHAR(255),
    UNIQUE INDEX namealgoindex (name, algorithm)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
