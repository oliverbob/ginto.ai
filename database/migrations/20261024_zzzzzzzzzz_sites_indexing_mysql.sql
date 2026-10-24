-- Search engines (owner, 2026-10-24: "use ai tied to their subscription when they would like to get
-- more professional indexing, but also since they are paying, allocate indexing tasks for ai to ensure
-- its not violating"). A paid site is shown to search engines only when its owner allows it, SilverQueen
-- AI found nothing that breaks the rules in the content as it is now, and three days have passed since.
ALTER TABLE sites_names
  ADD COLUMN index_on TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN index_status VARCHAR(12) NULL,
  ADD COLUMN index_note VARCHAR(400) NULL,
  ADD COLUMN index_hash CHAR(16) NULL,
  ADD COLUMN index_after DATETIME NULL,
  ADD COLUMN index_live TINYINT(1) NOT NULL DEFAULT 0;
