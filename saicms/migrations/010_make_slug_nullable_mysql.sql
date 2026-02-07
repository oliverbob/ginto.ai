-- Migration: 010_make_slug_nullable_mysql.sql
-- Make `posts.slug` nullable and remove UNIQUE constraint so blank slugs don't cause duplicate errors.
-- Run: mysql -u <user> -p < 010_make_slug_nullable_mysql.sql

-- 1) Drop the UNIQUE index on slug (index name observed as `slug`)
ALTER TABLE `posts` DROP INDEX `slug`;

-- 2) Modify column to allow NULL
ALTER TABLE `posts` MODIFY `slug` VARCHAR(200) NULL;

-- 3) Convert existing empty-string slugs to NULL
UPDATE `posts` SET `slug` = NULL WHERE `slug` = '';
