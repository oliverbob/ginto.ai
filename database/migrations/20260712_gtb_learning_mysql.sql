-- GTB session self-learning: track gainers the bot considered, how far each actually ran,
-- and whether we entered — so it can assess misses and adapt (dip-wait -> chase).
CREATE TABLE IF NOT EXISTS `gtb_learning` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_at`  VARCHAR(32) NOT NULL,               -- session start key (or 'nosession')
  `symbol`      VARCHAR(32) NOT NULL,
  `first_price` DECIMAL(20,8) NOT NULL,             -- price when first considered
  `best_pct`    DECIMAL(10,4) NOT NULL DEFAULT 0,   -- best % move since first considered
  `entered`     TINYINT(1) NOT NULL DEFAULT 0,      -- did we actually buy it?
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_session_symbol` (`session_at`, `symbol`),
  KEY `idx_session` (`session_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
