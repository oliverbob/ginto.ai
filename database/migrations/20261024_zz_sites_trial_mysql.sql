-- SilverQueen Sites: a held name's free trial (owner, 2026-10-24): live for 5 hours,
-- then 24 hours to pay with a reminder, then the name is released (the work stays).
ALTER TABLE sites_names
    ADD COLUMN held_at     DATETIME NULL AFTER preview_id,
    ADD COLUMN trial_until DATETIME NULL AFTER held_at,
    ADD COLUMN release_at  DATETIME NULL AFTER trial_until,
    ADD COLUMN member_name VARCHAR(120) NULL AFTER release_at,
    ADD COLUMN reminded    TINYINT(1) NOT NULL DEFAULT 0 AFTER member_name;
