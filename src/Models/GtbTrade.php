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
    public function recent(int $limit = 20, ?string $mode = null): array
    {
        try {
            $where = $mode !== null ? ['mode' => $mode] : [];
            $where['ORDER'] = ['created_at' => 'DESC', 'id' => 'DESC'];
            $where['LIMIT'] = $limit;
            $rows = $this->db->select($this->table, '*', $where);
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

    /** All open positions (oldest first). */
    public function openPositions(?string $mode = null): array
    {
        try {
            $where = $mode !== null ? ['mode' => $mode] : [];
            $where['status'] = 'OPEN';
            $where['ORDER']  = ['id' => 'ASC'];
            $rows = $this->db->select($this->table, '*', $where);
            return is_array($rows) ? $rows : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** One open position by id, or null. */
    public function getOpen(int $id): ?array
    {
        try {
            $r = $this->db->get($this->table, '*', ['id' => $id, 'status' => 'OPEN']);
            return is_array($r) ? $r : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Ratchet a trailing stop (and record the running peak). */
    public function updateStop(int $id, float $stopLoss, float $peak): void
    {
        try {
            $this->db->update($this->table, ['stop_loss' => $stopLoss, 'peak_price' => $peak], ['id' => $id]);
        } catch (\Throwable $e) {}
    }

    /** Record (or clear) the exchange-side protective order reference. */
    public function setProtection(int $id, ?string $type, ?string $protectId): void
    {
        try {
            $this->db->update($this->table, ['protect_type' => $type, 'protect_id' => $protectId], ['id' => $id]);
        } catch (\Throwable $e) {}
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
                'template'         => $data['template'] ?? null,
                'profile'          => $data['profile'] ?? null,
                'entry_chg'        => $data['entry_chg'] ?? null,
                'entry_mode'       => $data['entry_mode'] ?? null,
                'price'            => $data['price'],
                'qty'              => $data['qty'],
                'quote_qty'        => $data['quote_qty'] ?? null,
                'stop_loss'        => $data['stop_loss'] ?? null,
                'take_profit'      => $data['take_profit'] ?? null,
                'peak_price'       => $data['peak_price'] ?? $data['price'],
                'trail_pct'        => $data['trail_pct'] ?? null,
                'status'           => 'OPEN',
                'binance_order_id' => $data['binance_order_id'] ?? null,
                'protect_type'     => $data['protect_type'] ?? null,
                'protect_id'       => $data['protect_id'] ?? null,
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
            // Cross-session learning (token-free): feed gainer outcomes into the bucket stats.
            $row = $this->db->get($this->table, ['template', 'entry_chg', 'entry_mode'], ['id' => $id]);
            if (is_array($row) && ($row['template'] ?? '') === 'gainers' && $row['entry_chg'] !== null && !empty($row['entry_mode'])) {
                (new GtbGainerStats())->record(GtbGainerStats::bucket((string) $row['entry_mode'], (float) $row['entry_chg']), $realizedPnl);
            }
        } catch (\Throwable $e) {}
    }

    /** Recent CLOSED trades (for memory). */
    public function recentClosed(int $limit = 8): array
    {
        try {
            $rows = $this->db->select($this->table,
                ['symbol', 'template', 'realized_pnl', 'created_at', 'closed_at'],
                ['status' => 'CLOSED', 'ORDER' => ['id' => 'DESC'], 'LIMIT' => $limit]
            );
            return is_array($rows) ? $rows : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Symbols closed within the last $minutes (for the re-entry cooldown). Prevents the bot from
     * immediately rebuying a coin it just exited — the main driver of fee-bleeding churn.
     */
    public function recentlyClosedSymbols(string $mode, int $minutes): array
    {
        if ($minutes <= 0) return [];
        try {
            $since = date('Y-m-d H:i:s', time() - $minutes * 60);
            $rows = $this->db->select($this->table, 'symbol', [
                'mode' => $mode, 'status' => 'CLOSED', 'closed_at[>=]' => $since, 'GROUP' => 'symbol',
            ]);
            return is_array($rows) ? array_values(array_unique(array_filter($rows))) : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Realized P&L for trades closed at/after $since (for the session loss-limit circuit breaker). */
    public function realizedSince(string $mode, string $since): float
    {
        try {
            $sum = $this->db->sum($this->table, 'realized_pnl', [
                'mode' => $mode, 'status' => 'CLOSED', 'closed_at[>=]' => $since,
            ]);
            return $sum !== null ? (float) $sum : 0.0;
        } catch (\Throwable $e) {
            return 0.0;
        }
    }

    /**
     * Sum of realized P&L. When $mode is given, counts only that mode's trades so
     * paper profits never inflate live capital sizing (and vice-versa).
     */
    public function totalRealizedPnl(?string $mode = null): float
    {
        try {
            $where = $mode !== null ? ['mode' => $mode] : [];
            $sum = $this->db->sum($this->table, 'realized_pnl', $where);
            return $sum !== null ? (float)$sum : 0.0;
        } catch (\Throwable $e) {
            return 0.0;
        }
    }
}
