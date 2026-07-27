-- ============================================================================
-- SilverQueen (/silverqueen) — tiered membership cards, SQB engine units,
-- an independent 2-level referral tree, and the daily yield / compounding ledger.
--
-- Money columns are DECIMAL(20,8) to match the rest of the wallet/GTB schema.
-- Idempotent (CREATE TABLE IF NOT EXISTS + guarded seeds) so it is safe to re-run.
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 1) Product catalogue: the three membership cards + the SQB engine unit.
--    'card' products are one-per-user; 'engine' products are bought in units and
--    are what actually generate daily yield.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sq_products (
    id           INT UNSIGNED             NOT NULL AUTO_INCREMENT PRIMARY KEY,
    code         VARCHAR(40)              NOT NULL,           -- machine name, e.g. card_virtual
    name         VARCHAR(100)             NOT NULL,
    kind         ENUM('card','engine')    NOT NULL DEFAULT 'card',
    price        DECIMAL(20,8)            NOT NULL,           -- price per unit
    currency     VARCHAR(10)              NOT NULL DEFAULT 'USD',
    daily_rate   DECIMAL(10,6)            NOT NULL DEFAULT 0, -- fraction/day, e.g. 0.005000 = 0.5%
    term_days    INT UNSIGNED             NOT NULL DEFAULT 0, -- yield lifetime, e.g. 365
    max_per_user INT UNSIGNED             NOT NULL DEFAULT 0, -- 0 = unlimited
    description  VARCHAR(255)             NULL,
    sort_order   INT                      NOT NULL DEFAULT 0,
    is_active    TINYINT(1)               NOT NULL DEFAULT 1,
    created_at   DATETIME                 NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME                 NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                       ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sq_products_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- 2) Purchases. One row per checkout. Card ownership (and therefore the SQB
--    hardware qualification) is derived from the completed rows here.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sq_purchases (
    id          BIGINT UNSIGNED                          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED                             NOT NULL,
    product_id  INT UNSIGNED                             NOT NULL,
    product_code VARCHAR(40)                             NOT NULL,   -- denormalised for cheap reads
    units       INT UNSIGNED                             NOT NULL DEFAULT 1,
    unit_price  DECIMAL(20,8)                            NOT NULL,
    total       DECIMAL(20,8)                            NOT NULL,
    currency    VARCHAR(10)                              NOT NULL DEFAULT 'USD',
    status      ENUM('pending','completed','cancelled')  NOT NULL DEFAULT 'completed',
    source      VARCHAR(30)                              NOT NULL DEFAULT 'simulated',
    created_at  DATETIME                                 NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sq_purchases_user   (user_id, status),
    KEY idx_sq_purchases_code   (user_id, product_code, status),
    KEY idx_sq_purchases_created(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- 3) SQB engine allocations — the yield-bearing instrument. One row per engine
--    purchase; principal accrues daily_rate/day for term_days starting 24h after
--    start_at (Day 2 onwards).
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sq_allocations (
    id            BIGINT UNSIGNED               NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED                  NOT NULL,
    purchase_id   BIGINT UNSIGNED               NULL,
    units         INT UNSIGNED                  NOT NULL DEFAULT 1,
    principal     DECIMAL(20,8)                 NOT NULL,   -- units * unit_price
    daily_rate    DECIMAL(10,6)                 NOT NULL DEFAULT 0.005000,
    term_days     INT UNSIGNED                  NOT NULL DEFAULT 365,
    days_accrued  INT UNSIGNED                  NOT NULL DEFAULT 0,
    accrued       DECIMAL(20,8)                 NOT NULL DEFAULT 0,  -- earned, not yet transferred
    claimed_total DECIMAL(20,8)                 NOT NULL DEFAULT 0,  -- lifetime transferred to wallet
    status        ENUM('active','completed')    NOT NULL DEFAULT 'active',
    start_at      DATETIME                      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    matures_at    DATETIME                      NULL,
    created_at    DATETIME                      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sq_alloc_user   (user_id, status),
    KEY idx_sq_alloc_status (status, start_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- 4) Per-day accrual audit trail. The UNIQUE key is what makes the yield worker
--    idempotent: running it twice on the same day cannot pay twice.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sq_accruals (
    id            BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    allocation_id BIGINT UNSIGNED  NOT NULL,
    user_id       INT UNSIGNED     NOT NULL,
    day_index     INT UNSIGNED     NOT NULL,   -- 1 = first payout, 24h after start_at
    amount        DECIMAL(20,8)    NOT NULL,
    principal     DECIMAL(20,8)    NOT NULL,   -- base the rate was applied to
    accrued_for   DATETIME         NOT NULL,   -- the 24h boundary this row settles
    created_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sq_accruals_day (allocation_id, day_index),
    KEY idx_sq_accruals_user (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- 5) Internal platform wallet. Balance sitting here is re-rated every 24h
--    (compounding), so "transfer then leave it" is the compounding path.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sq_wallets (
    user_id          INT UNSIGNED   NOT NULL PRIMARY KEY,
    balance          DECIMAL(20,8)  NOT NULL DEFAULT 0,
    total_claimed    DECIMAL(20,8)  NOT NULL DEFAULT 0,  -- lifetime yield transferred in
    total_compounded DECIMAL(20,8)  NOT NULL DEFAULT 0,  -- lifetime compounding interest
    total_commission DECIMAL(20,8)  NOT NULL DEFAULT 0,  -- lifetime referral earnings
    last_compound_at DATETIME       NULL,                -- anchor of the 24h recalculation cycle
    created_at       DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP
                                              ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- 6) Wallet ledger. Every balance movement gets a row with the resulting balance
--    so the dashboard (and an auditor) can replay the account.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sq_wallet_txns (
    id            BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED     NOT NULL,
    type          ENUM('yield_claim','compound','commission','purchase','adjustment') NOT NULL,
    amount        DECIMAL(20,8)    NOT NULL,   -- signed: negative for purchases
    balance_after DECIMAL(20,8)    NOT NULL,
    note          VARCHAR(255)     NULL,
    ref_id        BIGINT UNSIGNED  NULL,       -- allocation / purchase / commission id
    created_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sq_txns_user (user_id, id),
    KEY idx_sq_txns_type (type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- 7) The AntFun tree — an INDEPENDENT hierarchy, deliberately not users.referrer_id.
--    sponsor_id is the direct upline (level 1); level 2 is that sponsor's sponsor.
--    materialised_path makes upline/downline walks a single indexed query.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sq_referrals (
    user_id     INT UNSIGNED   NOT NULL PRIMARY KEY,
    sponsor_id  INT UNSIGNED   NULL,             -- NULL = tree root
    depth       INT UNSIGNED   NOT NULL DEFAULT 0,
    path        VARCHAR(255)   NOT NULL DEFAULT '/',  -- e.g. /2/17/ (ancestors, root first)
    code        VARCHAR(32)    NOT NULL,         -- this member's own AntFun invite code
    created_at  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sq_referrals_code (code),
    KEY idx_sq_referrals_sponsor (sponsor_id),
    KEY idx_sq_referrals_path (path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- 8) Unilevel commissions earned off downline purchases (L1 15%, L2 5%).
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sq_commissions (
    id             BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    earner_id      INT UNSIGNED     NOT NULL,   -- upline who gets paid
    source_user_id INT UNSIGNED     NOT NULL,   -- downline who bought
    purchase_id    BIGINT UNSIGNED  NOT NULL,
    level          TINYINT UNSIGNED NOT NULL,   -- 1 or 2
    rate           DECIMAL(10,6)    NOT NULL,   -- 0.150000 / 0.050000
    amount         DECIMAL(20,8)    NOT NULL,
    created_at     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sq_comm_purchase_level (purchase_id, level),
    KEY idx_sq_comm_earner (earner_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- 9) Seed the catalogue (idempotent by code).
-- ---------------------------------------------------------------------------
INSERT INTO sq_products (code, name, kind, price, currency, daily_rate, term_days, max_per_user, description, sort_order, is_active)
SELECT 'card_virtual', 'Virtual Membership', 'card', 120.00000000, 'USD', 0, 0, 1,
       'Entry card. Unlocks the SilverQueen console and your AntFun invite code.', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM sq_products WHERE code = 'card_virtual');

INSERT INTO sq_products (code, name, kind, price, currency, daily_rate, term_days, max_per_user, description, sort_order, is_active)
SELECT 'card_physical', 'Physical Membership', 'card', 240.00000000, 'USD', 0, 0, 1,
       'Shipped hardware card. Required for physical rack attestation.', 2, 1
WHERE NOT EXISTS (SELECT 1 FROM sq_products WHERE code = 'card_physical');

INSERT INTO sq_products (code, name, kind, price, currency, daily_rate, term_days, max_per_user, description, sort_order, is_active)
SELECT 'card_nft', 'NFT Tracker Membership', 'card', 567.00000000, 'USD', 0, 0, 1,
       'On-chain tracker that binds your engine units to a verifiable identity.', 3, 1
WHERE NOT EXISTS (SELECT 1 FROM sq_products WHERE code = 'card_nft');

INSERT INTO sq_products (code, name, kind, price, currency, daily_rate, term_days, max_per_user, description, sort_order, is_active)
SELECT 'sqb_engine', 'SQB Engine Unit', 'engine', 100.00000000, 'USD', 0.005000, 365, 0,
       'One unit of allocated cloud compute. Yields 0.5% of its principal per day for 365 days.', 4, 1
WHERE NOT EXISTS (SELECT 1 FROM sq_products WHERE code = 'sqb_engine');
