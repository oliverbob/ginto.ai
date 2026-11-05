-- SilverQueen Sites: which picked photos are videos (index -> video URL), and the
-- chosen template, kept with the preview (owner, 2026-10-24: "Include also videos";
-- "12 templates to choose").
ALTER TABLE sites_previews
    ADD COLUMN media    JSON        NULL AFTER photos,
    ADD COLUMN template VARCHAR(24) NULL AFTER media;
