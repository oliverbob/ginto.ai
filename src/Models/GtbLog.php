<?php
namespace Ginto\Models;

use Ginto\Core\Database;
use Medoo\Medoo;

/**
 * Trading / activity log.
 */
class GtbLog
{
    private Medoo $db;
    private string $table = 'gtb_logs';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** Append a log line. Never throws — logging must not break the app. */
    public function add(string $message, string $level = 'info'): void
    {
        try {
            $this->db->insert($this->table, ['level' => $level, 'message' => $message]);
        } catch (\Throwable $e) {
            // swallow
        }
    }

    /** Most recent log lines, newest first. Empty if table missing. */
    public function recent(int $limit = 20): array
    {
        try {
            $rows = $this->db->select($this->table, '*', [
                'ORDER' => ['created_at' => 'DESC', 'id' => 'DESC'],
                'LIMIT' => $limit,
            ]);
            return is_array($rows) ? $rows : [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
