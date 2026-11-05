CREATE TABLE IF NOT EXISTS user_totp (
    user_id INT UNSIGNED NOT NULL PRIMARY KEY,
    secret_encrypted TEXT NOT NULL,
    enabled_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_user_totp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
