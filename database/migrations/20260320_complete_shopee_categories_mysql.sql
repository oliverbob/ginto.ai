/*
 * Migration: Complete ePower Mall categories to match Shopee PH taxonomy
 * Date: 2026-03-20
 *
 * Updates existing category names/slugs to match Shopee conventions and
 * inserts all missing Shopee top-level categories.
 * Safe to re-run: uses UPDATE ... WHERE slug = ... and INSERT IGNORE.
 */

-- -------------------------------------------------------
-- 1. Rename existing categories to match Shopee naming
-- -------------------------------------------------------
UPDATE `categories` SET `name` = 'Health & Beauty',       `slug` = 'health-beauty',          `sort_order` = 13  WHERE `slug` = 'beauty';
UPDATE `categories` SET `name` = 'Books & Stationery',    `slug` = 'books-stationery',        `sort_order` = 21  WHERE `slug` = 'books';
UPDATE `categories` SET `name` = 'Computers & Peripherals',`slug` = 'computers-peripherals',  `sort_order` = 11  WHERE `slug` = 'computers-it';
UPDATE `categories` SET `name` = 'Women\'s Apparel',      `slug` = 'womens-apparel',          `sort_order` = 1   WHERE `slug` = 'fashion';
UPDATE `categories` SET `name` = 'Food & Beverages',      `slug` = 'food-beverages',          `sort_order` = 19  WHERE `slug` = 'food-grocery';
UPDATE `categories` SET `name` = 'Tools & Home Improvement', `slug` = 'tools-home-improvement', `sort_order` = 26 WHERE `slug` = 'hardware';
UPDATE `categories` SET `name` = 'Home & Living',          `slug` = 'home-living',             `sort_order` = 12  WHERE `slug` = 'home-living';
UPDATE `categories` SET `name` = 'Kitchen & Dining',       `slug` = 'kitchen-dining',          `sort_order` = 13  WHERE `slug` = 'kitchenware';
UPDATE `categories` SET `name` = 'Sports & Outdoors',      `slug` = 'sports-outdoors',         `sort_order` = 16  WHERE `slug` = 'sports';
UPDATE `categories` SET `name` = 'Toys, Games & Collectibles', `slug` = 'toys-games-collectibles', `sort_order` = 15 WHERE `slug` = 'toys-hobbies';
UPDATE `categories` SET `name` = 'Health & Wellness',      `slug` = 'health-wellness',         `sort_order` = 13  WHERE `slug` = 'health-wellness';
UPDATE `categories` SET                                     `sort_order` = 9   WHERE `slug` = 'electronics';

-- -------------------------------------------------------
-- 2. Insert new Shopee-aligned top-level categories
-- -------------------------------------------------------
INSERT IGNORE INTO `categories` (`name`, `slug`, `description`, `sort_order`, `created_at`, `updated_at`) VALUES
    -- Apparel & Fashion
    ('Men\'s Apparel',             'mens-apparel',             'T-shirts, polo, jackets, jeans, shorts & men\'s clothing',         2,  NOW(), NOW()),
    ('Women\'s Bags',              'womens-bags',              'Totes, sling bags, backpacks, clutches & handbags for women',       3,  NOW(), NOW()),
    ('Men\'s Bags & Wallets',      'mens-bags-wallets',        'Backpacks, messenger bags, wallets & cardholders for men',         4,  NOW(), NOW()),
    ('Women\'s Shoes',             'womens-shoes',             'Heels, flats, sneakers, sandals & boots for women',                5,  NOW(), NOW()),
    ('Men\'s Shoes',               'mens-shoes',               'Sneakers, loafers, boots, sandals & formal shoes for men',         6,  NOW(), NOW()),
    ('Fashion Accessories',        'fashion-accessories',      'Belts, sunglasses, scarves, hats, caps & accessories',             7,  NOW(), NOW()),
    ('Watches',                    'watches',                  'Smartwatches, analog, digital & luxury timepieces',                8,  NOW(), NOW()),
    ('Jewellery & Accessories',    'jewellery-accessories',    'Rings, necklaces, bracelets, earrings & fine jewellery',           9,  NOW(), NOW()),

    -- Tech & Electronics
    ('Mobile & Gadgets',           'mobile-gadgets',           'Smartphones, tablets, powerbanks, earphones & mobile accessories', 10, NOW(), NOW()),
    ('TV & Home Appliances',       'tv-home-appliances',       'Televisions, refrigerators, washing machines & major appliances',  11, NOW(), NOW()),
    ('Cameras & Drones',           'cameras-drones',           'DSLR, mirrorless cameras, action cams, lenses & drones',           12, NOW(), NOW()),
    ('Gaming',                     'gaming',                   'Consoles, controllers, PC gaming, games & gaming accessories',     13, NOW(), NOW()),

    -- Home & Lifestyle
    ('Home & Kitchen',             'home-kitchen',             'Cookware, kitchen appliances, bakeware & kitchen tools',           14, NOW(), NOW()),
    ('Baby & Kids',                'baby-kids',                'Baby essentials, clothing, feeding, diapers & nursery items',      15, NOW(), NOW()),
    ('Pet Care & Supplies',        'pet-care-supplies',        'Pet food, grooming, accessories & supplies for all pets',          16, NOW(), NOW()),
    ('Travel & Luggage',           'travel-luggage',           'Luggage, travel bags, travel pillows, adapters & travel gear',     17, NOW(), NOW()),

    -- Food & Personal Care
    ('Groceries',                  'groceries',                'Rice, canned goods, condiments, snacks & everyday grocery items',  18, NOW(), NOW()),

    -- Automotive
    ('Automotive',                 'automotive',               'Car accessories, motorcycle parts, oil & automotive tools',        20, NOW(), NOW()),

    -- Hardware / Tools
    ('Tools & Home Improvement',   'tools-home-improvement',   'Hand tools, power tools, hardware, building & repair supplies',    26, NOW(), NOW()),

    -- Other
    ('Office & School Supplies',   'office-school-supplies',   'Pens, notebooks, printers, office chairs, desks & supplies',       22, NOW(), NOW()),
    ('Music & Media',              'music-media',              'Instruments, microphones, speakers, CDs, DVDs & media',            23, NOW(), NOW()),
    ('Collectibles & Memorabilia', 'collectibles-memorabilia', 'Action figures, trading cards, stamps, coins & collectibles',      24, NOW(), NOW()),
    ('Tickets, Vouchers & More',   'tickets-vouchers',         'Event tickets, gift vouchers, meal deals & experience packages',   25, NOW(), NOW());
