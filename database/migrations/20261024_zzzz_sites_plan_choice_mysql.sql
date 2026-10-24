-- The plan a member picked (owner, 2026-10-24: "I have already chosen the lowest price within
-- the app, so it should be consistently chosen here unless changed"). Kept with their name, so
-- the feed card, the plans page and My Site all show the same choice.
ALTER TABLE sites_names ADD COLUMN plan_choice VARCHAR(12) NULL AFTER member_name;
