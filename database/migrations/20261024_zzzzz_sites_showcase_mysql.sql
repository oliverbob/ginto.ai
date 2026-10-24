-- The feed's promotions take turns (owner, 2026-10-24: "there may be plenty of them soon, so
-- distribute fairly"). How often each paid site has been handed to a feed, and when last;
-- the least shown go first.
CREATE TABLE IF NOT EXISTS sites_showcase (
  name VARCHAR(32) NOT NULL PRIMARY KEY,
  shown INT UNSIGNED NOT NULL DEFAULT 0,
  last_shown DATETIME NULL,
  KEY shown_turn (shown, last_shown)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
