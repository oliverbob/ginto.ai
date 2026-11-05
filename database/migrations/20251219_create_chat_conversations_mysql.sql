-- Chat conversations table for logged-in users
-- Each conversation expires 24 hours after creation

CREATE TABLE IF NOT EXISTS chat_conversations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    convo_id VARCHAR(64) NOT NULL COMMENT 'Client-side conversation ID (e.g., c_1734567890123)',
    title VARCHAR(255) DEFAULT 'New chat',
    messages JSON NOT NULL COMMENT 'Array of message objects [{role, content, ts, ...}]',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL COMMENT '24 hours after created_at',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY uk_user_convo (user_id, convo_id),
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
