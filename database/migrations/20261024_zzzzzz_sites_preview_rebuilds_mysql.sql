-- An unfinished preview is continued, not refused (owner, 2026-10-24: Eleanor "kept skipping and
-- when she wanted to finish it said: You already made a preview today ... Just let her continue
-- with the one she made"). How often a day's preview was rebuilt in place; capped.
ALTER TABLE sites_previews ADD COLUMN rebuilds TINYINT UNSIGNED NOT NULL DEFAULT 0;
