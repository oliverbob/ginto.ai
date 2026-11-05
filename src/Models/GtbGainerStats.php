<?php
namespace Ginto\Models;

use Ginto\Core\Database;
use Ginto\Support\Env;
use Medoo\Medoo;

/**
 * Cross-session self-learning for the Gainer Hunter — PURE STATISTICS, no AI tokens.
 *
 * Every closed gainer trade is bucketed by (entry mode x 24h-change band) and its win/P&L is
 * accumulated across sessions. The strategy then deterministically AVOIDS buckets that have a
 * proven losing record (enough sample + sub-threshold win-rate + negative P&L). Winning buckets
 * are left alone to keep working. This compounds "which entries actually win" over days without
 * ever calling the model — and it saves tokens by skipping known-bad setups before the AI is asked.
 */
class GtbGainerStats
{
    private Medoo $db;
    private string $table = 'gtb_gainer_stats';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** Bucket key from entry mode ('chase'|'dip') and the coin's 24h change % at entry. */
    public static function bucket(string $mode, float $chg): string
    {
        $b = $chg < 25 ? '0-25' : ($chg < 50 ? '25-50' : ($chg < 70 ? '50-70'
           : ($chg < 100 ? '70-100' : ($chg < 200 ? '100-200' : '200+'))));
        return ($mode === 'chase' ? 'chase' : 'dip') . ':' . $b;
    }

    /** Record a closed gainer trade's outcome into its bucket. */
    public function record(string $bucket, float $pnl): void
    {
        try {
            $win = $pnl > 0 ? 1 : 0;
            $row = $this->db->get($this->table, '*', ['bucket_key' => $bucket]);
            if (is_array($row)) {
                $this->db->update($this->table, [
                    'trades'  => (int) $row['trades'] + 1,
                    'wins'    => (int) $row['wins'] + $win,
                    'pnl_sum' => (float) $row['pnl_sum'] + $pnl,
                ], ['bucket_key' => $bucket]);
            } else {
                $this->db->insert($this->table, ['bucket_key' => $bucket, 'trades' => 1, 'wins' => $win, 'pnl_sum' => $pnl]);
            }
        } catch (\Throwable $e) { /* table may be missing */ }
    }

    /**
     * Should we AVOID this (mode, change) setup based on its learned record? Only true once a
     * bucket has enough sample AND is clearly negative — so a couple of early losses can't lock
     * the bot out, and market-regime changes can be reset by clearing the table.
     */
    public function shouldAvoid(string $mode, float $chg): bool
    {
        $minSample  = (int) (Env::get('GTB_GAINER_LEARN_MIN_SAMPLE', '12') ?? 12);
        $minWinRate = (float) (Env::get('GTB_GAINER_LEARN_MIN_WINRATE', '0.35') ?? 0.35);
        try {
            $row = $this->db->get($this->table, '*', ['bucket_key' => self::bucket($mode, $chg)]);
            if (!is_array($row)) return false;
            $t = (int) $row['trades'];
            if ($t < $minSample) return false;
            $winRate = $t > 0 ? (int) $row['wins'] / $t : 0.0;
            return $winRate < $minWinRate && (float) $row['pnl_sum'] < 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** All buckets for display (win-rate + avg P&L), best first. */
    public function summary(): array
    {
        try {
            $rows = $this->db->select($this->table, '*', ['ORDER' => ['pnl_sum' => 'DESC']]);
            if (!is_array($rows)) return [];
            return array_map(static function ($r) {
                $t = max(1, (int) $r['trades']);
                return [
                    'bucket'  => $r['bucket_key'],
                    'trades'  => (int) $r['trades'],
                    'winRate' => round((int) $r['wins'] / $t * 100, 1),
                    'avgPnl'  => round((float) $r['pnl_sum'] / $t, 4),
                    'pnlSum'  => round((float) $r['pnl_sum'], 4),
                ];
            }, $rows);
        } catch (\Throwable $e) {
            return [];
        }
    }
}
