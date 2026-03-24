-- Migration: 20260325_create_barangays_mysql.sql
-- Creates the barangays table used for GPS-based geofencing in Ginto Mall.
-- Barangay locations are stored as centroid lat/lng with a coverage radius.
-- Pre-seeded with common Philippine barangays for the initial release.
-- Sellers declare which barangay(s) they can deliver to; buyers are matched
-- to their GPS-detected or manually-selected barangay.

DROP PROCEDURE IF EXISTS _barangay_migration;

DELIMITER //
CREATE PROCEDURE _barangay_migration()
BEGIN
    -- Create barangays table
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'barangays'
    ) THEN
        CREATE TABLE `barangays` (
            `id`           INT UNSIGNED       NOT NULL AUTO_INCREMENT,
            `psgc_code`    VARCHAR(20)        NULL UNIQUE COMMENT 'PSGC 10-digit code',
            `name`         VARCHAR(120)       NOT NULL,
            `city`         VARCHAR(120)       NOT NULL DEFAULT '',
            `province`     VARCHAR(120)       NOT NULL DEFAULT '',
            `region`       VARCHAR(60)        NOT NULL DEFAULT '',
            `lat`          DECIMAL(10,7)      NOT NULL DEFAULT 0,
            `lng`          DECIMAL(10,7)      NOT NULL DEFAULT 0,
            `radius_m`     INT UNSIGNED       NOT NULL DEFAULT 1500 COMMENT 'Coverage radius in metres',
            `is_active`    TINYINT(1)         NOT NULL DEFAULT 1,
            `created_at`   TIMESTAMP          NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_barangays_city`   (`city`),
            KEY `idx_barangays_province` (`province`),
            KEY `idx_barangays_latlon` (`lat`, `lng`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    END IF;

    -- Create seller_delivery_zones table
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seller_delivery_zones'
    ) THEN
        CREATE TABLE `seller_delivery_zones` (
            `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
            `seller_id`    INT UNSIGNED  NOT NULL,
            `barangay_id`  INT UNSIGNED  NOT NULL,
            `is_home`      TINYINT(1)    NOT NULL DEFAULT 0 COMMENT '1 = seller home barangay',
            `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_seller_barangay` (`seller_id`, `barangay_id`),
            KEY `idx_sdz_seller` (`seller_id`),
            KEY `idx_sdz_barangay` (`barangay_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    END IF;

    -- Create ginto_runners table
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ginto_runners'
    ) THEN
        CREATE TABLE `ginto_runners` (
            `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
            `user_id`      INT UNSIGNED  NOT NULL,
            `barangay_id`  INT UNSIGNED  NOT NULL,
            `status`       ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
            `bio`          TEXT          NULL,
            `has_vehicle`  TINYINT(1)    NOT NULL DEFAULT 0,
            `vehicle_type` VARCHAR(60)   NULL COMMENT 'bike, motorcycle, etc.',
            `rating`       DECIMAL(3,2)  NOT NULL DEFAULT 5.00,
            `deliveries`   INT UNSIGNED  NOT NULL DEFAULT 0,
            `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_runner_user` (`user_id`),
            KEY `idx_runner_barangay` (`barangay_id`),
            KEY `idx_runner_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    END IF;

    -- Add buyer_barangay_id to users table
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'buyer_barangay_id'
    ) THEN
        ALTER TABLE `users`
            ADD COLUMN `buyer_barangay_id` INT UNSIGNED NULL DEFAULT NULL
            COMMENT 'Pinned barangay for buyer GPS matching'
            AFTER `id`;
    END IF;

    -- Add delivery_rating to users table (seller delivery score)
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'delivery_rating'
    ) THEN
        ALTER TABLE `users`
            ADD COLUMN `delivery_rating` DECIMAL(3,2) NOT NULL DEFAULT 5.00
            AFTER `buyer_barangay_id`;
        ALTER TABLE `users`
            ADD COLUMN `delivery_strikes` TINYINT UNSIGNED NOT NULL DEFAULT 0
            AFTER `delivery_rating`;
    END IF;

    -- Seed barangays with well-known NCR barangays (expandable via admin panel)
    -- Metro Manila / NCR representative barangays
    INSERT IGNORE INTO `barangays` (`psgc_code`, `name`, `city`, `province`, `region`, `lat`, `lng`, `radius_m`) VALUES
    -- Quezon City
    ('137404001', 'Barangay Batasan Hills',         'Quezon City', 'Metro Manila', 'NCR', 14.6800, 121.1060, 2000),
    ('137404002', 'Barangay Commonwealth',          'Quezon City', 'Metro Manila', 'NCR', 14.7000, 121.0988, 2500),
    ('137404003', 'Barangay Fairview',              'Quezon City', 'Metro Manila', 'NCR', 14.7168, 121.0583, 2000),
    ('137404004', 'Barangay Novaliches Proper',     'Quezon City', 'Metro Manila', 'NCR', 14.7356, 121.0158, 1800),
    ('137404005', 'Barangay Tandang Sora',          'Quezon City', 'Metro Manila', 'NCR', 14.6952, 121.0405, 1500),
    ('137404006', 'Barangay Bagong Pag-asa',        'Quezon City', 'Metro Manila', 'NCR', 14.6585, 121.0267, 1200),
    ('137404007', 'Barangay Kamias',                'Quezon City', 'Metro Manila', 'NCR', 14.6448, 121.0524, 1000),
    ('137404008', 'Barangay Talipapa',              'Quezon City', 'Metro Manila', 'NCR', 14.7078, 121.0204, 1200),
    ('137404009', 'Barangay UP Village',             'Quezon City', 'Metro Manila', 'NCR', 14.6570, 121.0669, 1000),
    ('137404010', 'Barangay Sikatuna Village',       'Quezon City', 'Metro Manila', 'NCR', 14.6436, 121.0600, 900),
    -- Makati City
    ('137601001', 'Barangay Poblacion',             'Makati City', 'Metro Manila', 'NCR', 14.5658, 121.0355, 1000),
    ('137601002', 'Barangay Bel-Air',               'Makati City', 'Metro Manila', 'NCR', 14.5618, 121.0247, 1000),
    ('137601003', 'Barangay Forbes Park',           'Makati City', 'Metro Manila', 'NCR', 14.5509, 121.0155, 1200),
    ('137601004', 'Barangay San Lorenzo',           'Makati City', 'Metro Manila', 'NCR', 14.5630, 121.0170, 900),
    ('137601005', 'Barangay Urdaneta',              'Makati City', 'Metro Manila', 'NCR', 14.5533, 121.0178, 800),
    ('137601006', 'Barangay Bangkal',               'Makati City', 'Metro Manila', 'NCR', 14.5356, 121.0074, 1000),
    ('137601007', 'Barangay Palanan',               'Makati City', 'Metro Manila', 'NCR', 14.5462, 121.0068, 900),
    ('137601008', 'Barangay Guadalupe Nuevo',       'Makati City', 'Metro Manila', 'NCR', 14.5654, 121.0520, 1200),
    ('137601009', 'Barangay Comembo',               'Makati City', 'Metro Manila', 'NCR', 14.5626, 121.0508, 800),
    ('137601010', 'Barangay Pembo',                 'Makati City', 'Metro Manila', 'NCR', 14.5601, 121.0564, 1000),
    -- Pasig City
    ('137602001', 'Barangay Rosario',               'Pasig City',  'Metro Manila', 'NCR', 14.5836, 121.0680, 1200),
    ('137602002', 'Barangay Kapitolyo',             'Pasig City',  'Metro Manila', 'NCR', 14.5650, 121.0653, 800),
    ('137602003', 'Barangay Oranbo',                'Pasig City',  'Metro Manila', 'NCR', 14.5731, 121.0740, 900),
    ('137602004', 'Barangay San Miguel',            'Pasig City',  'Metro Manila', 'NCR', 14.5590, 121.0864, 1000),
    -- Taguig City
    ('137603001', 'Barangay BGC (Fort Bonifacio)',  'Taguig City', 'Metro Manila', 'NCR', 14.5505, 121.0483, 1500),
    ('137603002', 'Barangay Ususan',                'Taguig City', 'Metro Manila', 'NCR', 14.5194, 121.0676, 1500),
    ('137603003', 'Barangay Pinagsama',             'Taguig City', 'Metro Manila', 'NCR', 14.5214, 121.0538, 1200),
    -- Manila (City of Manila)
    ('137501001', 'Barangay Malate',                'Manila',      'Metro Manila', 'NCR', 14.5682, 120.9929, 1000),
    ('137501002', 'Barangay Paco',                  'Manila',      'Metro Manila', 'NCR', 14.5769, 121.0012, 1200),
    ('137501003', 'Barangay Pandacan',              'Manila',      'Metro Manila', 'NCR', 14.5919, 121.0044, 1000),
    ('137501004', 'Barangay Tondo',                 'Manila',      'Metro Manila', 'NCR', 14.6188, 120.9720, 2000),
    ('137501005', 'Barangay Binondo',               'Manila',      'Metro Manila', 'NCR', 14.5994, 120.9731, 800),
    ('137501006', 'Barangay Quiapo',                'Manila',      'Metro Manila', 'NCR', 14.5989, 120.9842, 700),
    ('137501007', 'Barangay Ermita',                'Manila',      'Metro Manila', 'NCR', 14.5754, 120.9796, 900),
    ('137501008', 'Barangay Sampaloc',              'Manila',      'Metro Manila', 'NCR', 14.6104, 121.0016, 1500),
    -- Mandaluyong City
    ('137604001', 'Barangay Addition Hills',        'Mandaluyong', 'Metro Manila', 'NCR', 14.5881, 121.0374, 1200),
    ('137604002', 'Barangay Barangka',              'Mandaluyong', 'Metro Manila', 'NCR', 14.5835, 121.0490, 1000),
    ('137604003', 'Barangay Hagdan Bato',           'Mandaluyong', 'Metro Manila', 'NCR', 14.5700, 121.0374, 1000),
    -- Marikina City
    ('137605001', 'Barangay Concepcion Uno',        'Marikina City','Metro Manila','NCR', 14.6590, 121.0970, 1500),
    ('137605002', 'Barangay Sto. Nino',             'Marikina City','Metro Manila','NCR', 14.6473, 121.1003, 1200),
    ('137605003', 'Barangay Parang',                'Marikina City','Metro Manila','NCR', 14.6265, 121.1061, 1500),
    -- Parañaque City
    ('137606001', 'Barangay BF Homes',              'Parañaque',   'Metro Manila', 'NCR', 14.4786, 121.0074, 2000),
    ('137606002', 'Barangay Don Galo',              'Parañaque',   'Metro Manila', 'NCR', 14.5113, 121.0142, 1200),
    ('137606003', 'Barangay La Huerta',             'Parañaque',   'Metro Manila', 'NCR', 14.4986, 121.0186, 1200),
    -- Las Piñas City
    ('137607001', 'Barangay Pamplona Uno',          'Las Piñas',   'Metro Manila', 'NCR', 14.4530, 120.9940, 1500),
    ('137607002', 'Barangay BF Almanza',            'Las Piñas',   'Metro Manila', 'NCR', 14.4623, 121.0046, 1500),
    -- Muntinlupa City
    ('137608001', 'Barangay Alabang',               'Muntinlupa',  'Metro Manila', 'NCR', 14.4211, 121.0422, 2000),
    ('137608002', 'Barangay Sucat',                 'Muntinlupa',  'Metro Manila', 'NCR', 14.4574, 121.0422, 1800),
    -- Valenzuela City
    ('137609001', 'Barangay Karuhatan',             'Valenzuela',  'Metro Manila', 'NCR', 14.6897, 120.9751, 1500),
    ('137609002', 'Barangay Malinta',               'Valenzuela',  'Metro Manila', 'NCR', 14.7060, 120.9724, 1500),
    -- Caloocan City
    ('137301001', 'Barangay 1 (Camarin)',           'Caloocan',    'Metro Manila', 'NCR', 14.7524, 121.0421, 2000),
    ('137301002', 'Barangay Bagong Silang',         'Caloocan',    'Metro Manila', 'NCR', 14.7483, 121.0540, 2500),
    -- Cebu City (Visayas)
    ('072217001', 'Barangay Lahug',                 'Cebu City',   'Cebu',         'Region VII', 10.3289, 123.9047, 1500),
    ('072217002', 'Barangay Mabolo',                'Cebu City',   'Cebu',         'Region VII', 10.3267, 123.9175, 1200),
    ('072217003', 'Barangay Banilad',               'Cebu City',   'Cebu',         'Region VII', 10.3430, 123.8990, 1200),
    ('072217004', 'Barangay Talamban',              'Cebu City',   'Cebu',         'Region VII', 10.3658, 123.9118, 1800),
    ('072217005', 'Barangay Guadalupe',             'Cebu City',   'Cebu',         'Region VII', 10.2940, 123.8900, 1200),
    -- Davao City (Mindanao)
    ('112402001', 'Barangay Poblacion (Davao)',     'Davao City',  'Davao del Sur','Region XI', 7.0736,  125.6123, 1500),
    ('112402002', 'Barangay Talomo',                'Davao City',  'Davao del Sur','Region XI', 7.0486,  125.5955, 2000),
    ('112402003', 'Barangay Buhangin',              'Davao City',  'Davao del Sur','Region XI', 7.1044,  125.6358, 2500),
    -- Iloilo City (Western Visayas)
    ('063001001', 'Barangay La Paz',                'Iloilo City', 'Iloilo',       'Region VI', 10.7193, 122.5481, 1500),
    ('063001002', 'Barangay Mandurriao',            'Iloilo City', 'Iloilo',       'Region VI', 10.7155, 122.5313, 1200),
    -- Cagayan de Oro (Northern Mindanao)
    ('100201001', 'Barangay Lapasan',               'Cagayan de Oro', 'Misamis Oriental', 'Region X', 8.4944, 124.6462, 1500),
    ('100201002', 'Barangay Bulua',                 'Cagayan de Oro', 'Misamis Oriental', 'Region X', 8.5103, 124.5980, 2000);

END //
DELIMITER ;

CALL _barangay_migration();
DROP PROCEDURE IF EXISTS _barangay_migration;
