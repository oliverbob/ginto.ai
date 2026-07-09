<?php
namespace Ginto\Models;

use Ginto\Core\Database;
use Medoo\Medoo;

/**
 * Recorded Binance Spot orders / fills.
 */
class GtbTrade
{
    private Medoo $db;
    private string $table = 'gtb_trades';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** Most recent trades, newest first. Empty if table missing. */
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

    /** Sum of realized P&L across all recorded trades. */
    public function totalRealizedPnl(): float
    {
        try {
            $sum = $this->db->sum($this->table, 'realized_pnl');
            return $sum !== null ? (float)$sum : 0.0;
        } catch (\Throwable $e) {
            return 0.0;
        }
    }
}
