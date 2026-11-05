-- The SilverQueen post each site photo came from, in photo order (null for an upload), so a
-- visit to the site counts as a view of those posts (owner, 2026-10-24).
ALTER TABLE sites_previews ADD COLUMN posts TEXT NULL;
-- Visits already credited to the posts, per day ({"YYYY-MM-DD": n}, the last few days), so each
-- visit is credited once even as old days leave the counts.
ALTER TABLE sites_names ADD COLUMN visits_credited TEXT NULL;
-- The feed showings already reported in a weekly insight, so the next one tells the week's.
ALTER TABLE sites_showcase ADD COLUMN shown_reported INT UNSIGNED NOT NULL DEFAULT 0;
