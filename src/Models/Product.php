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

        $sql = "SELECT * FROM {$this->table} WHERE 1=1";
        $params = [];

        // If caller specifies status, honor it. Otherwise use marketplace defaults.
        if (!empty($opts['status'])) {
            $sql .= " AND status = :status";
            $params[':status'] = (string)$opts['status'];
        } else {
            if ($this->hasColumn('status')) {
                $sql .= " AND status = 'published'";
            }
            // Include legacy published rows where is_visible may be NULL.
            if ($this->hasColumn('is_visible')) {
                $sql .= " AND (is_visible = 1 OR is_visible IS NULL)";
            }
        }

        if (!empty($opts['category_id'])) {
            $sql .= " AND category_id = :category_id";
            $params[':category_id'] = (int)$opts['category_id'];
        }

        if (!empty($opts['seller_id'])) {
            $sql .= " AND seller_id = :seller_id";
            $params[':seller_id'] = (int)$opts['seller_id'];
        }

        // Tokenized depth search across key textual fields.
        if (!empty($opts['search'])) {
            $search = trim((string)$opts['search']);
            if ($search !== '') {
                $tokens = preg_split('/\s+/', mb_strtolower($search), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                $tokens = array_slice($tokens, 0, 6); // Guard against pathological long queries.
                $searchCols = ['title'];
                if ($this->hasColumn('slug')) $searchCols[] = 'slug';
                if ($this->hasColumn('short_description')) $searchCols[] = 'short_description';
                if ($this->hasColumn('description')) $searchCols[] = 'description';

                foreach ($tokens as $i => $token) {
                    $key = ':q' . $i;
                    $clauses = [];
                    foreach ($searchCols as $col) {
                        $clauses[] = "LOWER({$col}) LIKE {$key}";
                    }
                    $sql .= " AND (" . implode(' OR ', $clauses) . ")";
                    $params[$key] = '%' . $token . '%';
                }
            }
        }

        // Sorting
        $sort = (string)($opts['sort'] ?? 'default');
        $orderBy = 'created_at DESC';
        if ($sort === 'price_asc') {
            $orderBy = 'price ASC';
        } elseif ($sort === 'price_desc') {
            $orderBy = 'price DESC';
        } elseif ($sort === 'rating') {
            $orderBy = 'rating DESC';
        }
        $sql .= " ORDER BY {$orderBy} LIMIT :offset, :limit";

        $stmt = $this->db->pdo()->prepare($sql);
        foreach ($params as $k => $v) {
            $type = is_int($v) ? \PDO::PARAM_INT : \PDO::PARAM_STR;
            $stmt->bindValue($k, $v, $type);
        }
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    private function hasColumn(string $column): bool
    {
        if (array_key_exists($column, $this->columnExistsCache)) {
            return $this->columnExistsCache[$column];
        }

        try {
            $stmt = $this->db->pdo()->prepare("SHOW COLUMNS FROM {$this->table} LIKE :col");
            $stmt->bindValue(':col', $column, \PDO::PARAM_STR);
            $stmt->execute();
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

