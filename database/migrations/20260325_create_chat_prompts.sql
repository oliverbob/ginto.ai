CREATE TABLE IF NOT EXISTS `chat_prompts` (
    `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`      INT UNSIGNED NOT NULL,
    `day`          DATE NOT NULL,                      -- e.g. 2026-03-25
    `prompt_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`   DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY `idx_user_day` (`user_id`, `day`),
    KEY `idx_day` (`day`),

    CONSTRAINT `fk_chat_prompts_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
