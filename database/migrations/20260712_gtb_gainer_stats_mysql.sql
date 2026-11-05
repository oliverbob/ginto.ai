-- Cross-session learning for the Gainer Hunter (token-free, pure statistics).
-- Record each gainer trade's entry conditions, then aggregate win-rate / P&L per
-- (entry-mode x 24h-change bucket) so the strategy can avoid proven-losing combos.
ALTER TABLE `gtb_trades` ADD COLUMN IF NOT EXISTS `entry_chg`  DECIMAL(10,2) NULL AFTER `profile`;
ALTER TABLE `gtb_trades` ADD COLUMN IF NOT EXISTS `entry_mode` VARCHAR(8)    NULL AFTER `entry_chg`;

CREATE TABLE IF NOT EXISTS `gtb_gainer_stats` (
  `bucket_key` VARCHAR(24) NOT NULL,           -- e.g. "chase:70-100" or "dip:25-50"
  `trades`     INT UNSIGNED NOT NULL DEFAULT 0,
  `wins`       INT UNSIGNED NOT NULL DEFAULT 0,
  `pnl_sum`    DECIMAL(18,6) NOT NULL DEFAULT 0,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`bucket_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
