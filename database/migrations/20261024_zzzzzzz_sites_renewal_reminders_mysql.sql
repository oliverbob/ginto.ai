-- Renewal reminders in the member's bell (owner's Premium part 3, 2026-10-24): which have gone
-- out for this month, as bits: 1 = three days before, 2 = the day before, 4 = after it ended.
ALTER TABLE sites_subscriptions ADD COLUMN reminded TINYINT UNSIGNED NOT NULL DEFAULT 0;
