-- Customer chat on members' sites (Premium part 3, owner 2026-10-24): one row per answered
-- question, for the per-visitor and per-site limits and for the owner's insights. The visitor is
-- a hash, never an address; the question and answer are kept short for review of abuse.
CREATE TABLE IF NOT EXISTS sites_chat_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(32) NOT NULL,
  visitor CHAR(16) NOT NULL,
  question VARCHAR(500) NOT NULL,
  answer VARCHAR(700) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY name_time (name, created_at),
  KEY visitor_time (visitor, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
