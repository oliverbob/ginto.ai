-- Migration: create fgbibledb_kjv and populate from existing Bible_Kjv dump
-- This migration expects the raw dump (which creates `Bible_Kjv`) to already be applied
-- If `Bible_Kjv` is present, this will transform rows into the app's expected schema.

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

CREATE TABLE IF NOT EXISTS `fgbibledb_kjv` (
  `BOOK` INT NOT NULL,
  `CHAPTER` INT NOT NULL,
  `VERSE` INT NOT NULL,
  `TEXT` TEXT,
  PRIMARY KEY (`BOOK`,`CHAPTER`,`VERSE`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Populate `fgbibledb_kjv` by mapping `Bible_Kjv`.`Chapter_Name` to BOOK index
-- Uses INSERT IGNORE to avoid duplicate-key failures if run more than once
-- Guarded population: build and execute the INSERT only when the
-- source table `Bible_Kjv` exists in the current database. This avoids
-- failing the migration when the raw dump/migration for `Bible_Kjv` is absent.
SET @kjv_exists = (
  SELECT COUNT(*)
  FROM information_schema.tables
  WHERE table_schema = DATABASE() AND table_name = 'Bible_Kjv'
);

SET @sql = IF(@kjv_exists > 0,
  CONCAT(
    'INSERT IGNORE INTO `fgbibledb_kjv` (`BOOK`,`CHAPTER`,`VERSE`,`TEXT`) ',
    'SELECT CASE TRIM(Chapter_Name) ',
      "WHEN 'Genesis' THEN 1 ",
      "WHEN 'Exodus' THEN 2 ",
      "WHEN 'Leviticus' THEN 3 ",
      "WHEN 'Numbers' THEN 4 ",
      "WHEN 'Deuteronomy' THEN 5 ",
      "WHEN 'Joshua' THEN 6 ",
      "WHEN 'Judges' THEN 7 ",
      "WHEN 'Ruth' THEN 8 ",
      "WHEN '1Samuel' THEN 9 ",
      "WHEN '2Samuel' THEN 10 ",
      "WHEN '1Kings' THEN 11 ",
      "WHEN '2Kings' THEN 12 ",
      "WHEN '1Chronicles' THEN 13 ",
      "WHEN '2Chronicles' THEN 14 ",
      "WHEN 'Ezra' THEN 15 ",
      "WHEN 'Nehemiah' THEN 16 ",
      "WHEN 'Esther' THEN 17 ",
      "WHEN 'Job' THEN 18 ",
      "WHEN 'Psalms' THEN 19 ",
      "WHEN 'Proverbs' THEN 20 ",
      "WHEN 'Ecclesiastes' THEN 21 ",
      "WHEN 'Song of Solomon' THEN 22 ",
      "WHEN 'Isaiah' THEN 23 ",
      "WHEN 'Jeremiah' THEN 24 ",
      "WHEN 'Lamentations' THEN 25 ",
      "WHEN 'Ezekiel' THEN 26 ",
      "WHEN 'Daniel' THEN 27 ",
      "WHEN 'Hosea' THEN 28 ",
      "WHEN 'Joel' THEN 29 ",
      "WHEN 'Amos' THEN 30 ",
      "WHEN 'Obadiah' THEN 31 ",
      "WHEN 'Jonah' THEN 32 ",
      "WHEN 'Micah' THEN 33 ",
      "WHEN 'Nahum' THEN 34 ",
      "WHEN 'Habakkuk' THEN 35 ",
      "WHEN 'Zephaniah' THEN 36 ",
      "WHEN 'Haggai' THEN 37 ",
      "WHEN 'Zechariah' THEN 38 ",
      "WHEN 'Malachi' THEN 39 ",
      "WHEN 'Matthew' THEN 40 ",
      "WHEN 'Mark' THEN 41 ",
      "WHEN 'Luke' THEN 42 ",
      "WHEN 'John' THEN 43 ",
      "WHEN 'Acts' THEN 44 ",
      "WHEN 'Romans' THEN 45 ",
      "WHEN '1Corinthians' THEN 46 ",
      "WHEN '2Corinthians' THEN 47 ",
      "WHEN 'Galatians' THEN 48 ",
      "WHEN 'Ephesians' THEN 49 ",
      "WHEN 'Philippians' THEN 50 ",
      "WHEN 'Colossians' THEN 51 ",
      "WHEN '1Thessalonians' THEN 52 ",
      "WHEN '2Thessalonians' THEN 53 ",
      "WHEN '1Timothy' THEN 54 ",
      "WHEN '2Timothy' THEN 55 ",
      "WHEN 'Titus' THEN 56 ",
      "WHEN 'Philemon' THEN 57 ",
      "WHEN 'Hebrews' THEN 58 ",
      "WHEN 'James' THEN 59 ",
      "WHEN '1Peter' THEN 60 ",
      "WHEN '2Peter' THEN 61 ",
      "WHEN '1John' THEN 62 ",
      "WHEN '2John' THEN 63 ",
      "WHEN '3John' THEN 64 ",
      "WHEN 'Jude' THEN 65 ",
      "WHEN 'Revelation' THEN 66 ",
      "ELSE 0 END AS BOOK, ",
      "CAST(Chapter_Id AS UNSIGNED) AS CHAPTER, CAST(Verse_Id AS UNSIGNED) AS VERSE, Verse_Text ",
      "FROM `Bible_Kjv` WHERE Chapter_Name IS NOT NULL AND Chapter_Id REGEXP '^[0-9]+' AND Verse_Id REGEXP '^[0-9]+'"
  ),
  'SELECT 0'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add an index to speed up lookups by BOOK/CHAPTER
CREATE INDEX IF NOT EXISTS idx_fgbible_book_chapter ON `fgbibledb_kjv` (`BOOK`,`CHAPTER`);
