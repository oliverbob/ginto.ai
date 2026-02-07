-- Member messaging tables for Facebook-like messenger
-- Direct messages between platform members

-- Conversations table - tracks each unique 1-on-1 or group conversation
CREATE TABLE IF NOT EXISTS member_conversations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type ENUM('direct', 'group') DEFAULT 'direct',
    name VARCHAR(255) DEFAULT NULL COMMENT 'For group chats only',
    avatar_url VARCHAR(500) DEFAULT NULL COMMENT 'Group chat avatar',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_message_at DATETIME DEFAULT NULL COMMENT 'Timestamp of last message',
    
    INDEX idx_last_message (last_message_at DESC),
    INDEX idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Conversation participants - who is part of each conversation
CREATE TABLE IF NOT EXISTS member_conversation_participants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_read_at DATETIME DEFAULT NULL COMMENT 'When user last read messages',
    is_muted BOOLEAN DEFAULT FALSE,
    is_archived BOOLEAN DEFAULT FALSE,
    
    UNIQUE KEY uk_conversation_user (conversation_id, user_id),
    INDEX idx_user_id (user_id),
    INDEX idx_conversation_id (conversation_id),
    
    FOREIGN KEY (conversation_id) REFERENCES member_conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Messages table - individual messages in conversations
CREATE TABLE IF NOT EXISTS member_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT UNSIGNED NOT NULL,
    sender_id INT UNSIGNED NOT NULL,
    content TEXT NOT NULL,
    message_type ENUM('text', 'image', 'file', 'emoji', 'audio', 'video') DEFAULT 'text',
    attachment_url VARCHAR(500) DEFAULT NULL,
    attachment_name VARCHAR(255) DEFAULT NULL,
    attachment_size INT UNSIGNED DEFAULT NULL COMMENT 'File size in bytes',
    reply_to_id INT UNSIGNED DEFAULT NULL COMMENT 'For message replies',
    is_edited BOOLEAN DEFAULT FALSE,
    is_deleted BOOLEAN DEFAULT FALSE,
    deleted_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_conversation_id (conversation_id),
    INDEX idx_sender_id (sender_id),
    INDEX idx_created_at (created_at DESC),
    INDEX idx_reply_to (reply_to_id),
    
    FOREIGN KEY (conversation_id) REFERENCES member_conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reply_to_id) REFERENCES member_messages(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Message read receipts - track who has read each message
CREATE TABLE IF NOT EXISTS member_message_reads (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    message_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY uk_message_user (message_id, user_id),
    INDEX idx_message_id (message_id),
    INDEX idx_user_id (user_id),
    
    FOREIGN KEY (message_id) REFERENCES member_messages(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User online status and typing indicators (in-memory via Redis/WebSocket typically, but DB backup)
CREATE TABLE IF NOT EXISTS member_online_status (
    user_id INT UNSIGNED PRIMARY KEY,
    is_online BOOLEAN DEFAULT FALSE,
    last_seen_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    currently_typing_in INT UNSIGNED DEFAULT NULL COMMENT 'conversation_id if typing',
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (currently_typing_in) REFERENCES member_conversations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
