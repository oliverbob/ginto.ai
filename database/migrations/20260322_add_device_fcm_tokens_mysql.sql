-- Migration: device_fcm_tokens
-- Stores FCM device tokens for Android (and optionally iOS) push notifications.
-- Run once per environment: mysql -u <user> -p <database> < this_file.sql

CREATE TABLE IF NOT EXISTS device_fcm_tokens (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id     INT             NOT NULL,
    fcm_token   VARCHAR(512)    NOT NULL,
    device_type VARCHAR(20)     NOT NULL DEFAULT 'android',
    created_at  DATETIME        NOT NULL,
    updated_at  DATETIME        NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fcm_token (fcm_token(200)),
    KEY idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
