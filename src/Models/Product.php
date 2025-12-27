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
     */
    public function create(array $data): ?array
    {
        $now = date('Y-m-d H:i:s');
        $insert = [
            'owner_id' => $data['owner_id'] ?? null,
            'title' => $data['title'] ?? '',
            'slug' => $data['slug'] ?? null,
            'description' => $data['description'] ?? null,
            'price_amount' => $data['price_amount'] ?? ($data['price'] ?? 0),
            'price_currency' => $data['price_currency'] ?? ($data['currency'] ?? 'PHP'),
            'category' => $data['category'] ?? ($data['cat'] ?? null),
            'stock' => $data['stock'] ?? 0,
            'image_path' => $data['image_path'] ?? ($data['img'] ?? null),
            'badge' => $data['badge'] ?? null,
            'rating' => $data['rating'] ?? 0,
            'status' => $data['status'] ?? 'published',
            'created_at' => $now,
            'updated_at' => $now
        ];

        $res = $this->db->insert($this->table, $insert);
        if ($res->rowCount() === 0) return null;
        $id = $this->db->id();
        return $this->find((int)$id);
    }

    /**
     * Find a product by id
     */
    public function find(int $id): ?array
    {
        return $this->db->get($this->table, '*', ['id' => $id]);
    }

    /**
     * List products with basic filters, pagination, and sorting
     */
    public function list(array $opts = []): array
    {
        $where = [];
        if (!empty($opts['category'])) $where['category'] = $opts['category'];
        if (!empty($opts['status'])) $where['status'] = $opts['status'];
        if (!empty($opts['owner_id'])) $where['owner_id'] = (int)$opts['owner_id'];
        if (!empty($opts['search'])) $where['title[~]'] = $opts['search'];

        // Sorting
        $order = ['created_at' => 'DESC'];
        if (!empty($opts['sort'])) {
            if ($opts['sort'] === 'price_asc') $order = ['price_amount' => 'ASC'];
            if ($opts['sort'] === 'price_desc') $order = ['price_amount' => 'DESC'];
            if ($opts['sort'] === 'rating') $order = ['rating' => 'DESC'];
        }

        $limit = isset($opts['limit']) ? (int)$opts['limit'] : 24;
        $offset = isset($opts['offset']) ? (int)$opts['offset'] : 0;

        $where['ORDER'] = $order;
        $where['LIMIT'] = [$offset, $limit];

        return $this->db->select($this->table, '*', $where) ?: [];
    }
}
