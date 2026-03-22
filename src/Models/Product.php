<?php
namespace Ginto\Models;

use Ginto\Core\Database;
use Medoo\Medoo;

class Product
{
    private Medoo $db;
    private string $table = 'products';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Create a new product and return the inserted row
     * Accepts legacy fields and maps them to modern schema
     */
    public function create(array $data): ?array
    {
        $now = date('Y-m-d H:i:s');

        // Map legacy fields to modern schema
        $sellerId = $data['seller_id'] ?? $data['owner_id'] ?? null;
        $price = isset($data['price']) ? $data['price'] : ($data['price_amount'] ?? 0);
        $currency = $data['currency'] ?? ($data['price_currency'] ?? 'USD');
        $quantity = isset($data['quantity']) ? (int)$data['quantity'] : (isset($data['stock']) ? (int)$data['stock'] : 0);

        $images = $data['images'] ?? null;
        if (is_array($images)) {
            $imagesJson = json_encode(array_values($images));
        } elseif (!empty($data['image_path'])) {
            $imagesJson = json_encode([$data['image_path']]);
        } elseif (!empty($data['img'])) {
            $imagesJson = json_encode([$data['img']]);
        } else {
            $imagesJson = null;
        }

        $insert = [
            'seller_id' => $sellerId,
            'category_id' => $data['category_id'] ?? ($data['category'] ?? null),
            'title' => $data['title'] ?? '',
            'slug' => $this->uniqueSlug($data['slug'] ?? null, $data['title'] ?? ''),
            'short_description' => $data['short_description'] ?? ($data['excerpt'] ?? null),
            'description' => $data['description'] ?? null,
            'price' => $price,
            'currency' => $currency,
            'quantity' => $quantity,
            'images' => $imagesJson,
            'attributes' => !empty($data['attributes']) ? (is_array($data['attributes']) ? json_encode($data['attributes']) : $data['attributes']) : null,
            'status' => $data['status'] ?? 'draft',
            'is_visible' => isset($data['is_visible']) ? (int)$data['is_visible'] : 0,
            // Shipping dimensions — packed weight + outer box size (including packaging)
            'weight_kg' => isset($data['weight_kg']) && $data['weight_kg'] !== '' ? round((float)$data['weight_kg'], 3) : null,
            'length_cm' => isset($data['length_cm']) && $data['length_cm'] !== '' ? round((float)$data['length_cm'], 2) : null,
            'width_cm'  => isset($data['width_cm'])  && $data['width_cm']  !== '' ? round((float)$data['width_cm'],  2) : null,
            'height_cm' => isset($data['height_cm']) && $data['height_cm'] !== '' ? round((float)$data['height_cm'], 2) : null,
            'created_at' => $now,
            'updated_at' => $now
        ];

        $res = $this->db->insert($this->table, $insert);
        if (!$res || $res->rowCount() === 0) return null;
        $id = $this->db->id();
        return $this->find((int)$id);
    }

    /**
     * Generate a URL-safe slug from a string.
     */
    private function makeSlug(string $text): string
    {
        $slug = mb_strtolower(trim($text));
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        return trim($slug, '-') ?: 'product';
    }

    /**
     * Return a slug that does not yet exist in the products table.
     * Appends -2, -3, … until unique.
     */
    private function uniqueSlug(?string $provided, string $title): string
    {
        $base = $provided !== null && $provided !== '' ? $this->makeSlug($provided) : $this->makeSlug($title);
        $slug = $base;
        $counter = 2;
        while ($this->db->get($this->table, 'id', ['slug' => $slug]) !== null) {
            $slug = $base . '-' . $counter++;
        }
        return $slug;
    }

    /**
     * Find a product by id
     */
    public function find(int $id): ?array
    {
        return $this->db->get($this->table, '*', ['id' => $id]);
    }

    /**
     * Update a product by id
     */
    public function update(int $id, array $data): ?array
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        if (isset($data['images']) && is_array($data['images'])) {
            $data['images'] = json_encode(array_values($data['images']));
        }
        $this->db->update($this->table, $data, ['id' => $id]);
        return $this->find($id);
    }

    /**
     * List products with basic filters, pagination, and sorting
     * Default: only published & visible products (marketplace listing)
     */
    public function list(array $opts = []): array
    {
        $where = [];

        // If caller wants all statuses (e.g., admin or seller panel), pass status in opts
        if (!empty($opts['status'])) {
            $where['status'] = $opts['status'];
        } else {
            // Marketplace default
            $where['status'] = 'published';
            $where['is_visible'] = 1;
        }

        if (!empty($opts['category_id'])) $where['category_id'] = $opts['category_id'];
        if (!empty($opts['seller_id'])) $where['seller_id'] = (int)$opts['seller_id'];
        if (!empty($opts['search'])) $where['title[~]'] = $opts['search'];

        // Sorting
        $order = ['created_at' => 'DESC'];
        if (!empty($opts['sort'])) {
            if ($opts['sort'] === 'price_asc') $order = ['price' => 'ASC'];
            if ($opts['sort'] === 'price_desc') $order = ['price' => 'DESC'];
            if ($opts['sort'] === 'rating') $order = ['rating' => 'DESC'];
        }

        $limit = isset($opts['limit']) ? (int)$opts['limit'] : 24;
        $offset = isset($opts['offset']) ? (int)$opts['offset'] : 0;

        $where['ORDER'] = $order;
        $where['LIMIT'] = [$offset, $limit];

        return $this->db->select($this->table, '*', $where) ?: [];
    }

    /**
     * Delete a product (soft delete: mark status = 'deleted')
     */
    public function delete(int $id): bool
    {
        $this->db->update($this->table, ['status' => 'deleted', 'updated_at' => date('Y-m-d H:i:s')], ['id' => $id]);
        return true;
    }
}

