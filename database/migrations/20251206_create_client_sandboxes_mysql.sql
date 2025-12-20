-- Create client_sandboxes mapping table for per-user sandbox folders
-- MySQL specific migration

CREATE TABLE IF NOT EXISTS client_sandboxes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    public_id VARCHAR(16) NULL,
    sandbox_id VARCHAR(12) NOT NULL UNIQUE,
    quota_bytes BIGINT NOT NULL DEFAULT 104857600, -- default 100MB
    used_bytes BIGINT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Ensure sandbox_id is indexed for quick lookup
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
