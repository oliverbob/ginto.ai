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

    /** The current open position (newest), or null. */
    public function openPosition(): ?array
    {
        try {
            $row = $this->db->get($this->table, '*', ['status' => 'OPEN', 'ORDER' => ['id' => 'DESC']]);
            return is_array($row) ? $row : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Open a new position. Returns inserted id or null. */
    public function openTrade(array $data): ?int
    {
        try {
            $this->db->insert($this->table, [
                'symbol'           => $data['symbol'],
                'side'             => 'BUY',
                'type'             => 'MARKET',
                'mode'             => $data['mode'] ?? 'paper',
                'price'            => $data['price'],
                'qty'              => $data['qty'],
                'quote_qty'        => $data['quote_qty'] ?? null,
                'stop_loss'        => $data['stop_loss'] ?? null,
                'take_profit'      => $data['take_profit'] ?? null,
                'status'           => 'OPEN',
                'binance_order_id' => $data['binance_order_id'] ?? null,
            ]);
            return (int) $this->db->id();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Close an open position with an exit price + realized P&L. */
    public function closeTrade(int $id, float $exitPrice, float $realizedPnl): void
    {
        try {
            $this->db->update($this->table, [
                'status'       => 'CLOSED',
                'exit_price'   => $exitPrice,
                'realized_pnl' => $realizedPnl,
                'closed_at'    => date('Y-m-d H:i:s'),
            ], ['id' => $id]);
        } catch (\Throwable $e) {}
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
