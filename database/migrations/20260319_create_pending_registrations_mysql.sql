-- Migration: Create pending_registrations staging table for Ginto Pay webhook flow
-- Date: 2026-03-19
-- Description:
--   When a user initiates Ginto Pay (PayMongo card checkout), we cannot rely on
--   PHP sessions surviving through the webhook callback (server-to-server). This
--   table stores the registration form data keyed by checkout_session_id so the
--   webhook handler can create the user account without needing the original request.
--
--   Lifecycle:
--     1. INSERT on gintoPayInit()  -- status = 'pending'
--     2. UPDATE on successful webhook (checkout_session.payment.paid) -- status = 'completed'
--     3. UPDATE on failed/expired scenarios -- status = 'failed'/'expired'
--   Rows are kept for auditing; a cleanup job should purge expired rows periodically.

CREATE TABLE IF NOT EXISTS pending_registrations (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    checkout_session_id VARCHAR(80)  NOT NULL COMMENT 'PayMongo checkout session ID (cs_xxx)',
    reg_data            JSON         NOT NULL COMMENT 'Serialised registration fields (no plaintext password; stores password_hash)',
    amount              INT UNSIGNED NOT NULL COMMENT 'Amount in PHP pesos',
    duration            VARCHAR(5)   NOT NULL DEFAULT '1m' COMMENT '1m or 12m',
    status              ENUM('pending','completed','failed','expired') NOT NULL DEFAULT 'pending',
    user_id             INT UNSIGNED NULL      COMMENT 'Set after account is created via webhook',
    created_at          DATETIME     NOT NULL  DEFAULT CURRENT_TIMESTAMP,
    processed_at        DATETIME     NULL      COMMENT 'Timestamp when account was created',
    expires_at          DATETIME     NOT NULL  DEFAULT (CURRENT_TIMESTAMP + INTERVAL 2 HOUR) COMMENT 'Session expiry; uncompleted rows older than this are stale',

    PRIMARY KEY (id),
    UNIQUE  KEY uk_checkout_session_id (checkout_session_id),
    INDEX   idx_status_created (status, created_at),
    INDEX   idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
