<?php
namespace Ginto\Models;

use Ginto\Core\Database;
use Medoo\Medoo;

/**
 * The bot's self-reflection / decision stream (its "chat with itself").
 */
class GtbThought
{
    private Medoo $db;
    private string $table = 'gtb_thoughts';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function add(string $message, string $role = 'claude', string $phase = 'reflect', ?string $symbol = null, ?string $decision = null, ?array $meta = null): void
    {
        try {
            $this->db->insert($this->table, [
                'role'     => $role,
                'phase'    => $phase,
                'symbol'   => $symbol,
                'decision' => $decision,
                'message'  => $message,
                'meta'     => $meta !== null ? json_encode($meta) : null,
            ]);
        } catch (\Throwable $e) {
            // never let logging break the bot
        }
    }

    /** Most recent thoughts, oldest→newest for chat display. */
    public function recent(int $limit = 40): array
    {
        try {
            $rows = $this->db->select($this->table, '*', [
                'ORDER' => ['id' => 'DESC'],
                'LIMIT' => $limit,
            ]);
            $rows = is_array($rows) ? $rows : [];
            return array_reverse($rows);
        } catch (\Throwable $e) {
            return [];
        }
    }
}
