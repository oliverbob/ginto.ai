-- Add LXC container and terms tracking columns to client_sandboxes
-- Run this migration after the initial client_sandboxes table is created

-- Add terms_accepted_at column to track when user accepted sandbox terms
ALTER TABLE client_sandboxes 
ADD COLUMN IF NOT EXISTS terms_accepted_at DATETIME NULL DEFAULT NULL AFTER updated_at;

-- Add container_created_at column to track when LXC container was created
ALTER TABLE client_sandboxes 
ADD COLUMN IF NOT EXISTS container_created_at DATETIME NULL DEFAULT NULL AFTER terms_accepted_at;

-- Add container_name column to store the LXC container name (sandbox-{id})
ALTER TABLE client_sandboxes 
ADD COLUMN IF NOT EXISTS container_name VARCHAR(64) NULL DEFAULT NULL AFTER container_created_at;

-- Add container_status column to cache last known container status
ALTER TABLE client_sandboxes 
ADD COLUMN IF NOT EXISTS container_status ENUM('not_created', 'stopped', 'running', 'error') DEFAULT 'not_created' AFTER container_name;

-- Add last_accessed_at column to track sandbox usage for cleanup policies
ALTER TABLE client_sandboxes 
ADD COLUMN IF NOT EXISTS last_accessed_at DATETIME NULL DEFAULT NULL AFTER container_status;
