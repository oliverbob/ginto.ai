-- Migration: Add Fragrance category
-- Date: 2026-03-22
INSERT IGNORE INTO categories (name, slug, description, sort_order, created_at, updated_at)
VALUES ('Fragrance', 'fragrance', 'Perfumes, colognes, body sprays, essential oils & scented products', 15, NOW(), NOW());
