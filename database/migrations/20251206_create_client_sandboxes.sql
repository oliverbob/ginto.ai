-- Create client_sandboxes mapping table for per-user sandbox folders

CREATE TABLE IF NOT EXISTS client_sandboxes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NULL,
    public_id VARCHAR(16) NULL,
    sandbox_id VARCHAR(12) NOT NULL UNIQUE,
    quota_bytes INTEGER NOT NULL DEFAULT 104857600,
    used_bytes INTEGER NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Create unique index if it does not exist (MySQL does not support CREATE INDEX IF NOT EXISTS)
SELECT COUNT(*) INTO @idx_exists
FROM information_schema.statistics
WHERE table_schema = DATABASE()
    AND table_name = 'client_sandboxes'
    AND index_name = 'idx_client_sandbox_id';

SET @sql = IF(@idx_exists = 0,
    'CREATE UNIQUE INDEX idx_client_sandbox_id ON client_sandboxes (sandbox_id)',
    'SELECT 1');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
