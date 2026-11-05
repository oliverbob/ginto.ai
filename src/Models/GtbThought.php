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

    /**
     * Operational trade log: entries, exits, and errors (not the verbose AI reasoning),
     * shaped as {message, level, created_at} for the Trading Log panel.
     */
    public function tradeLog(int $limit = 20): array
    {
        try {
            $rows = $this->db->select($this->table, ['id', 'message', 'phase', 'role', 'created_at'], [
                'OR' => ['phase' => ['trade', 'error'], 'role' => 'system'],
                'ORDER' => ['id' => 'DESC'],
                'LIMIT' => $limit,
            ]);
            if (!is_array($rows)) return [];
            return array_map(static fn($r) => [
                'id'         => (int) ($r['id'] ?? 0),
                'message'    => $r['message'] ?? '',
                'level'      => (($r['role'] ?? '') === 'system' || ($r['phase'] ?? '') === 'error') ? 'error' : 'trade',
                'created_at' => self::manilaTime($r['created_at'] ?? ''),
            ], $rows);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Format a stored (UTC) timestamp to human-readable Asia/Manila time, e.g. "Jul 11, 8:55 AM". */
    public static function manilaTime(string $ts): string
    {
        if ($ts === '') return '';
        try {
            $d = new \DateTime($ts, new \DateTimeZone('UTC'));
            $d->setTimezone(new \DateTimeZone('Asia/Manila'));
            return $d->format('M j, g:i A');
        } catch (\Throwable $e) {
            return $ts;
        }
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

    /** Total estimated AI spend (USD) and reflection count, from stored meta. */
    public function spend(): array
    {
        try {
            $stmt = $this->db->query(
                "SELECT COALESCE(SUM(CAST(JSON_EXTRACT(meta, '$.cost_usd') AS DECIMAL(14,6))), 0) AS total, "
                . "SUM(CASE WHEN role = 'claude' THEN 1 ELSE 0 END) AS cnt FROM {$this->table}"
            );
            $row = $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : null;
            return [
                'total' => round((float) ($row['total'] ?? 0), 6),
                'count' => (int) ($row['cnt'] ?? 0),
            ];
        } catch (\Throwable $e) {
            return ['total' => 0.0, 'count' => 0];
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
