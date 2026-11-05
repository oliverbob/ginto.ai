-- ============================================================
-- GeoNames-based global geographic places table
-- Source: https://download.geonames.org/export/dump/
-- Enables worldwide zone search (42,000+ PH barangays + all countries)
-- ============================================================

DROP PROCEDURE IF EXISTS `_geo_places_migration`;
DELIMITER $$
CREATE PROCEDURE `_geo_places_migration`()
BEGIN
    -- Main geo_places table
    IF NOT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'geo_places') THEN
        CREATE TABLE `geo_places` (
            `geoname_id`    INT UNSIGNED NOT NULL,
            `name`          VARCHAR(200) NOT NULL,
            `ascii_name`    VARCHAR(200) NOT NULL DEFAULT '',
            `latitude`      DECIMAL(10,7) NOT NULL DEFAULT 0,
            `longitude`     DECIMAL(10,7) NOT NULL DEFAULT 0,
            `feature_class` CHAR(1) NOT NULL DEFAULT '',
            `feature_code`  VARCHAR(10) NOT NULL DEFAULT '',
            `country_code`  CHAR(2) NOT NULL DEFAULT '',
            `admin1_code`   VARCHAR(20) NOT NULL DEFAULT '',
            `admin2_code`   VARCHAR(80) NOT NULL DEFAULT '',
            `admin3_code`   VARCHAR(20) NOT NULL DEFAULT '',
            `admin4_code`   VARCHAR(20) NOT NULL DEFAULT '',
            `population`    BIGINT UNSIGNED NOT NULL DEFAULT 0,
            `timezone`      VARCHAR(40) NOT NULL DEFAULT '',
            PRIMARY KEY (`geoname_id`),
            KEY `idx_geo_country` (`country_code`),
            KEY `idx_geo_feature` (`feature_class`, `feature_code`),
            KEY `idx_geo_latlon` (`latitude`, `longitude`),
            KEY `idx_geo_admin` (`country_code`, `admin1_code`, `admin2_code`),
            FULLTEXT KEY `ft_geo_name` (`name`, `ascii_name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    END IF;

    -- Admin1 codes table (regions/states) for human-readable hierarchy
    IF NOT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'geo_admin1') THEN
        CREATE TABLE `geo_admin1` (
            `code`       VARCHAR(20) NOT NULL,
            `name`       VARCHAR(200) NOT NULL,
            `ascii_name` VARCHAR(200) NOT NULL DEFAULT '',
            `geoname_id` INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    END IF;

    -- Link barangays table to geo_places via geoname_id
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'barangays' AND column_name = 'geoname_id'
    ) THEN
        ALTER TABLE `barangays` ADD COLUMN `geoname_id` INT UNSIGNED NULL AFTER `id`;
        ALTER TABLE `barangays` ADD KEY `idx_brgy_geoname` (`geoname_id`);
    END IF;

    -- Track import status per country
    IF NOT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'geo_import_log') THEN
        CREATE TABLE `geo_import_log` (
            `country_code` CHAR(2) NOT NULL,
            `records`      INT UNSIGNED NOT NULL DEFAULT 0,
            `imported_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`country_code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    END IF;
END$$
DELIMITER ;

CALL `_geo_places_migration`();
DROP PROCEDURE IF EXISTS `_geo_places_migration`;
