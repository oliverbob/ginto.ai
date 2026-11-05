<?php
namespace Ginto\Models;

use Ginto\Core\Database;
use Medoo\Medoo;

/**
 * Session self-learning for the Gainer Hunter. Each session it records the gainers the bot
 * considered, tracks how far each actually ran afterwards, and whether we entered. From that it
 * counts "misses" (coins we passed on that then ran ≥ threshold) so the strategy can adapt:
 * be patient (wait for a slight dip) at first, then switch to chasing breakouts once it's clear
 * the day's movers keep running away. All methods are defensive (no-op if the table is missing).
 */
class GtbLearning
{
    private Medoo $db;
    private string $table = 'gtb_learning';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** First time we consider a symbol this session, record its price as the baseline. */
    public function observe(string $sessionAt, string $symbol, float $price): void
    {
        if ($price <= 0 || $symbol === '') return;
        try {
            if (!is_array($this->db->get($this->table, ['id'], ['session_at' => $sessionAt, 'symbol' => $symbol]))) {
                $this->db->insert($this->table, [
                    'session_at'  => $sessionAt,
                    'symbol'      => $symbol,
                    'first_price' => $price,
                    'best_pct'    => 0,
                    'entered'     => 0,
                    'created_at'  => date('Y-m-d H:i:s'),
                ]);
            }
        } catch (\Throwable $e) { /* table may not exist yet */ }
    }

    /** Update the best % move for every tracked symbol this session using current prices. */
    public function updateBest(string $sessionAt, array $priceMap): void
    {
        try {
            $rows = $this->db->select($this->table, ['id', 'symbol', 'first_price', 'best_pct'], ['session_at' => $sessionAt]);
            if (!is_array($rows)) return;
            foreach ($rows as $r) {
                $sym = $r['symbol'] ?? '';
                if (!isset($priceMap[$sym])) continue;
                $first = (float) $r['first_price'];
                if ($first <= 0) continue;
                $pct = ((float) $priceMap[$sym] - $first) / $first * 100.0;
                if ($pct > (float) $r['best_pct']) {
                    $this->db->update($this->table, ['best_pct' => round($pct, 4)], ['id' => $r['id']]);
                }
            }
        } catch (\Throwable $e) { /* ignore */ }
    }

    /** Mark a symbol as entered (a win/attempt, not a miss). */
    public function markEntered(string $sessionAt, string $symbol): void
    {
        try { $this->db->update($this->table, ['entered' => 1], ['session_at' => $sessionAt, 'symbol' => $symbol]); }
        catch (\Throwable $e) { /* ignore */ }
    }

    /** How many symbols we considered-but-skipped this session then ran ≥ $threshold %. */
    public function missCount(string $sessionAt, float $threshold): int
    {
        try {
            return (int) $this->db->count($this->table, [
                'session_at' => $sessionAt, 'entered' => 0, 'best_pct[>=]' => $threshold,
            ]);
        } catch (\Throwable $e) { return 0; }
    }

    /** Biggest missed movers this session, for the log. */
    public function topMisses(string $sessionAt, float $threshold, int $limit = 5): array
    {
        try {
            $r = $this->db->select($this->table, ['symbol', 'best_pct'], [
                'session_at' => $sessionAt, 'entered' => 0, 'best_pct[>=]' => $threshold,
                'ORDER' => ['best_pct' => 'DESC'], 'LIMIT' => $limit,
            ]);
            return is_array($r) ? $r : [];
        } catch (\Throwable $e) { return []; }
    }
}
