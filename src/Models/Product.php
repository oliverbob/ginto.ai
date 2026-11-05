<?php
namespace Ginto\Models;

use Ginto\Core\Database;
use Medoo\Medoo;

class Product
{
    private Medoo $db;
    private string $table = 'products';
    private array $columnExistsCache = [];

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
            'colors'     => $data['colors'] ?? null,
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
     * Find a product by slug
     */
    public function findBySlug(string $slug): ?array
    {
        return $this->db->get($this->table, '*', ['slug' => $slug]) ?: null;
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
        $limit = isset($opts['limit']) ? max(1, (int)$opts['limit']) : 24;
        $offset = isset($opts['offset']) ? max(0, (int)$opts['offset']) : 0;

        // Sorting
        $sort = (string)($opts['sort'] ?? 'default');
        $order = ['created_at' => 'DESC'];
        if ($sort === 'price_asc') {
            $order = ['price' => 'ASC'];
        } elseif ($sort === 'price_desc') {
            $order = ['price' => 'DESC'];
        } elseif ($sort === 'rating') {
            $order = ['rating' => 'DESC'];
        }

        $where = [];

        // If caller specifies status, honor it. Otherwise use marketplace defaults.
        if (!empty($opts['status'])) {
            $where['status'] = (string)$opts['status'];
        } else {
            if ($this->hasColumn('status')) {
                $where['status'] = 'published';
            }
            if ($this->hasColumn('is_visible')) {
                $where['is_visible'] = 1;
            }
        }

        if (!empty($opts['category_id'])) {
            $where['category_id'] = (int)$opts['category_id'];
        }
        if (!empty($opts['seller_id'])) {
            $where['seller_id'] = (int)$opts['seller_id'];
        }

        // Barangay geofence filter: when provided, delegate to a JOIN-based query
        // because Medoo's where builder can't cleanly express the subselect.
        // Digital products are exempt from barangay gating (no delivery needed).
        if (!empty($opts['barangay_id'])) {
            return $this->listByBarangay($opts, $order, $offset, $limit);
        }

        if (!empty($opts['search'])) {
            $search = trim((string)$opts['search']);
            if ($search !== '') {
                $searchOr = ['title[~]' => $search];
                if ($this->hasColumn('slug')) $searchOr['slug[~]'] = $search;
                if ($this->hasColumn('short_description')) $searchOr['short_description[~]'] = $search;
                if ($this->hasColumn('description')) $searchOr['description[~]'] = $search;
                // Compose base filters with OR full-text-like matching fields.
                $where['OR'] = $searchOr;
            }
        }

        $where['ORDER'] = $order;
        $where['LIMIT'] = [$offset, $limit];

        try {
            $rows = $this->db->select($this->table, '*', $where) ?: [];

            // Fallback for environments with legacy publishing states/flags.
            if (empty($rows) && empty($opts['status'])) {
                $rows = $this->listLegacyFallback($opts, $order, $offset, $limit);
            }

            return $rows;
        } catch (\Throwable $e) {
            error_log('Product::list query error: ' . $e->getMessage());
            if (empty($opts['status'])) {
                return $this->listLegacyFallback($opts, $order, $offset, $limit);
            }
            return [];
        }
    }

    /**
     * List products from sellers who deliver to a specific barangay.
     * Digital/virtual/subscription products are always included (no delivery).
     * Physical products are only shown if the seller has declared the given
     * barangay in their seller_delivery_zones.
     */
    private function listByBarangay(array $opts, array $order, int $offset, int $limit): array
    {
        $barangayId = (int)$opts['barangay_id'];
        $params = [':barangay_id' => $barangayId, ':limit' => $limit, ':offset' => $offset];

        // Build dynamic WHERE fragments
        $extraWhere = "AND p.status = 'published' AND p.is_visible = 1";

        if (!empty($opts['category_id'])) {
            $extraWhere .= ' AND p.category_id = :category_id';
            $params[':category_id'] = (int)$opts['category_id'];
        }
        if (!empty($opts['seller_id'])) {
            $extraWhere .= ' AND p.seller_id = :seller_id';
            $params[':seller_id'] = (int)$opts['seller_id'];
        }
        if (!empty($opts['search'])) {
            $extraWhere .= ' AND (p.title LIKE :search OR p.short_description LIKE :search2)';
            $params[':search']  = '%' . $opts['search'] . '%';
            $params[':search2'] = '%' . $opts['search'] . '%';
        }

        // Sort clause (column whitelist – never interpolate raw user input)
        $orderSql = 'p.created_at DESC';
        $sortKey = array_key_first($order);
        $sortDir = reset($order);
        if ($sortKey === 'price'      && $sortDir === 'ASC')  $orderSql = 'p.price ASC';
        if ($sortKey === 'price'      && $sortDir === 'DESC') $orderSql = 'p.price DESC';
        if ($sortKey === 'rating'     && $sortDir === 'DESC') $orderSql = 'p.rating DESC';

        try {
            $stmt = $this->db->query("
                SELECT p.*
                FROM products p
                WHERE (
                    -- Non-physical products are exempt from barangay delivery zoning
                    (p.product_type IS NULL OR p.product_type = '' OR p.product_type IN ('digital', 'virtual', 'subscription', 'service', 'non-physical') OR p.product_type NOT IN ('physical', 'liquid'))
                    OR
                    -- Physical with custom zones: check product_delivery_zones
                    (p.use_custom_zones = 1 AND EXISTS (
                        SELECT 1 FROM product_delivery_zones pz
                        WHERE pz.product_id = p.id
                          AND pz.barangay_id = :barangay_id
                    ))
                    OR
                    -- Physical using seller zones: check seller_delivery_zones
                    (COALESCE(p.use_custom_zones, 0) = 0 AND EXISTS (
                        SELECT 1 FROM seller_delivery_zones z
                        WHERE z.seller_id = p.seller_id
                          AND z.barangay_id = :barangay_id2
                    ))
                )
                {$extraWhere}
                ORDER BY {$orderSql}
                LIMIT :offset, :limit
            ", array_merge($params, [':barangay_id2' => $barangayId]));

            return $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
        } catch (\Throwable $e) {
            error_log('Product::listByBarangay error: ' . $e->getMessage());
            // Graceful degradation: return unfiltered results so the app stays usable
            return $this->list(array_merge($opts, ['barangay_id' => null]));
        }
    }

    private function listLegacyFallback(array $opts, array $order, int $offset, int $limit): array
    {
        $where = [];

        // Legacy-safe public-ish fallback: exclude hard-deleted rows.
        if ($this->hasColumn('status')) {
            $where['status[!]'] = 'deleted';
        }

        if (!empty($opts['category_id'])) {
            $where['category_id'] = (int)$opts['category_id'];
        }
        if (!empty($opts['seller_id'])) {
            $where['seller_id'] = (int)$opts['seller_id'];
        }

        if (!empty($opts['search'])) {
            $search = trim((string)$opts['search']);
            if ($search !== '') {
                $searchOr = ['title[~]' => $search];
                if ($this->hasColumn('slug')) $searchOr['slug[~]'] = $search;
                if ($this->hasColumn('short_description')) $searchOr['short_description[~]'] = $search;
                if ($this->hasColumn('description')) $searchOr['description[~]'] = $search;
                $where['OR'] = $searchOr;
            }
        }

        $where['ORDER'] = $order;
        $where['LIMIT'] = [$offset, $limit];

        try {
            return $this->db->select($this->table, '*', $where) ?: [];
        } catch (\Throwable $e) {
            error_log('Product::list legacy fallback error: ' . $e->getMessage());
            return [];
        }
    }

    private function hasColumn(string $column): bool
    {
        if (array_key_exists($column, $this->columnExistsCache)) {
            return $this->columnExistsCache[$column];
        }

        try {
            $stmt = $this->db->query("SHOW COLUMNS FROM {$this->table} LIKE :col", [':col' => $column]);
            if (!$stmt) {
                $this->columnExistsCache[$column] = false;
                return false;
            }
            $exists = (bool)$stmt->fetch(\PDO::FETCH_ASSOC);
            $this->columnExistsCache[$column] = $exists;
            return $exists;
        } catch (\Throwable $e) {
            $this->columnExistsCache[$column] = false;
            return false;
        }
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

