-- SilverQueen Pages, Sites and Stores (owner, 2026-10-24).
-- Names, free previews, the per-photo vision cache, every AI request's cost,
-- and subscriptions. See src/Sites/.

CREATE TABLE IF NOT EXISTS sites_names (
    name        VARCHAR(32)  NOT NULL PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    status      ENUM('held','active','lapsed') NOT NULL DEFAULT 'held',
    preview_id  INT UNSIGNED NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sites_names_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sites_previews (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    token        CHAR(32)     NOT NULL,
    user_id      INT UNSIGNED NULL,
    device_hash  CHAR(64)     NOT NULL,
    ip_hash      CHAR(64)     NOT NULL,
    description  TEXT         NOT NULL,
    photos       JSON         NOT NULL,
    site         JSON         NULL,
    status       ENUM('queued','building','ready','failed') NOT NULL DEFAULT 'queued',
    error        VARCHAR(255) NULL,
    cost_usd     DECIMAL(10,6) NOT NULL DEFAULT 0,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at   DATETIME NOT NULL,
    UNIQUE KEY uq_sites_previews_token (token),
    KEY idx_sites_previews_device (device_hash, created_at),
    KEY idx_sites_previews_ip (ip_hash, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per distinct image (SHA-256 of its bytes): never analysed twice.
CREATE TABLE IF NOT EXISTS sites_photos (
    sha256      CHAR(64)     NOT NULL PRIMARY KEY,
    path        VARCHAR(255) NOT NULL,
    width       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    height      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    status      ENUM('pending','done','manual') NOT NULL DEFAULT 'pending',
    provider    VARCHAR(32)  NULL,
    vision      JSON         NULL,
    palette     JSON         NULL,
    attempts    TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every AI request: who, what for, which model, tokens, cost. Margin tracking and the ceilings read this.
CREATE TABLE IF NOT EXISTS sites_ai_usage (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED NULL,
    preview_id   INT UNSIGNED NULL,
    feature      VARCHAR(32)  NOT NULL,
    model        VARCHAR(64)  NOT NULL,
    in_tokens    INT UNSIGNED NOT NULL DEFAULT 0,
    out_tokens   INT UNSIGNED NOT NULL DEFAULT 0,
    cached_tokens INT UNSIGNED NOT NULL DEFAULT 0,
    cost_usd     DECIMAL(10,6) NOT NULL DEFAULT 0,
    credits      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    ok           TINYINT(1)   NOT NULL DEFAULT 1,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sites_ai_user (user_id, created_at),
    KEY idx_sites_ai_preview (preview_id),
    KEY idx_sites_ai_month (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sites_subscriptions (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NOT NULL,
    name          VARCHAR(32)  NOT NULL,
    plan          ENUM('silver','gold','crown') NOT NULL,
    price_php     INT UNSIGNED NOT NULL,
    founding      TINYINT(1)   NOT NULL DEFAULT 0,
    status        ENUM('pending','active','lapsed','cancelled') NOT NULL DEFAULT 'pending',
    period_start  DATETIME NULL,
    period_end    DATETIME NULL,
    payment_ref   VARCHAR(64)  NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_sites_subs_user (user_id),
    KEY idx_sites_subs_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
