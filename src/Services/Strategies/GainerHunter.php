<?php
namespace Ginto\Services\Strategies;

use Ginto\Support\Env;

/**
 * Top-3 Gainer Hunter — chase the day's strongest movers and protect them with a
 * profit-locking OCO. It targets the top few gainers (prioritising #1) that are still
 * pressing their highs, then rides them: once price is up ~arm%, the stop ratchets ONTO
 * THE PLUS SIDE (locks ≥ lock%, default 1.5%) and trails upward while the OCO take-profit
 * caps the reach (5–8%). The goal is many small-but-locked wins — because top gainers can
 * drop fast, the downside stop becomes a *profit-taking* stop the moment we're in the money.
 *
 * Tunables (env): GTB_GAINER_MIN_PCT, GTB_GAINER_MAX_PCT, GTB_GAINER_SL_PCT,
 * GTB_GAINER_TP_PCT, GTB_GAINER_LOCK_PCT, GTB_GAINER_ARM_PCT, GTB_GAINER_TRAIL_PCT,
 * GTB_GAINER_CHASE_BAND_PCT, GTB_GAINER_TOP_N, GTB_GAINER_MIN_HOLD_MIN, GTB_GAINER_MAX_HOLD_MIN.
 */
class GainerHunter implements GtbTemplate
{
    private float $minG;
    private float $maxG;
    private float $sl;        // initial stop % (before we arm the profit-lock)
    private float $tp;        // OCO take-profit ceiling %
    private float $lock;      // profit % locked once armed (min win)
    private float $arm;       // price must be up this % before we start locking
    private float $trail;     // trail distance % below the peak once armed
    private float $chaseBand; // only enter within this % of the 24h high (still pressing up)
    private int   $topN;
    private int   $minHold;
    private int   $maxHold;

    public function __construct()
    {
        $this->minG      = (float) (Env::get('GTB_GAINER_MIN_PCT', '3') ?? 3);
        $this->maxG      = (float) (Env::get('GTB_GAINER_MAX_PCT', '60') ?? 60);
        $this->sl        = (float) (Env::get('GTB_GAINER_SL_PCT', '2.0') ?? 2.0);
        $this->tp        = (float) (Env::get('GTB_GAINER_TP_PCT', '8.0') ?? 8.0);
        $this->lock      = (float) (Env::get('GTB_GAINER_LOCK_PCT', '1.5') ?? 1.5);
        $this->arm       = (float) (Env::get('GTB_GAINER_ARM_PCT', '2.0') ?? 2.0);
        $this->trail     = (float) (Env::get('GTB_GAINER_TRAIL_PCT', '2.0') ?? 2.0);
        $this->chaseBand = (float) (Env::get('GTB_GAINER_CHASE_BAND_PCT', '4.0') ?? 4.0);
        $this->topN      = max(1, (int) (Env::get('GTB_GAINER_TOP_N', '3') ?? 3));
        $this->minHold   = max(0, (int) (Env::get('GTB_GAINER_MIN_HOLD_MIN', '15') ?? 15));
        $this->maxHold   = max(0, (int) (Env::get('GTB_GAINER_MAX_HOLD_MIN', '45') ?? 45));
    }

    public function key(): string { return 'gainers'; }
    public function name(): string { return 'Top-3 Gainer Hunter'; }
    public function description(): string { return 'Chase the top 3 gainers pressing their highs; profit-locking OCO (locks ≥' . rtrim(rtrim(number_format($this->lock, 1), '0'), '.') . '%, aims ' . rtrim(rtrim(number_format($this->tp, 1), '0'), '.') . '%).'; }

    /**
     * Pick the strongest gainer (then #2, #3) that is still within CHASE_BAND% of its 24h high —
     * i.e. still pressing up, not a spent rocket that already dumped.
     */
    public function entryCandidate(array $tickers, array $held): ?array
    {
        $band = [];
        foreach ($tickers as $t) {
            if (in_array($t['symbol'], $held, true)) continue;
            $chg = (float) ($t['changePct'] ?? 0);
            if ($chg < $this->minG || $chg > $this->maxG) continue;
            $band[] = $t;
        }
        if (!$band) return null;
        usort($band, static fn($a, $b) => (float) $b['changePct'] <=> (float) $a['changePct']);

        $ranked = array_slice($band, 0, $this->topN);
        foreach ($ranked as $i => $t) {
            $price = (float) ($t['price'] ?? 0);
            $high  = (float) ($t['high'] ?? 0);
            if ($price <= 0) continue;
            // Must still be pressing the high (chasing strength, not catching a collapse).
            if ($high > 0 && $price < $high * (1 - $this->chaseBand / 100.0)) continue;
            $offHigh = $high > 0 ? round(($high - $price) / $high * 100, 2) : 0.0;
            return [
                'symbol'    => $t['symbol'],
                'price'     => $price,
                'changePct' => (float) $t['changePct'],
                'reason'    => sprintf('#%d gainer +%s%%, %s the 24h high — chase with profit-locking OCO',
                    $i + 1, $t['changePct'], $offHigh <= 0.2 ? 'pressing' : ('~' . $offHigh . '% off')),
            ];
        }
        return null;
    }

    public function stops(float $entry): array
    {
        // OCO: fixed take-profit ceiling + an initial stop that reconcileLive/manage() ratchet
        // upward (trail_pct signals the profit-lock behaviour).
        return [
            'stop_loss'   => $entry * (1 - $this->sl / 100.0),
            'take_profit' => $entry * (1 + $this->tp / 100.0),
            'trail_pct'   => $this->trail,
        ];
    }

    /**
     * Paper-mode management mirrors the live profit-locking OCO: exit at the TP ceiling or the
     * (ratcheting) stop; once up ~arm%, pin the stop to the plus side (≥ lock%) and trail it up.
     */
    public function manage(array $pos, float $price): ?array
    {
        $entry = (float) $pos['price'];
        $tp    = (float) $pos['take_profit'];
        $stop  = (float) $pos['stop_loss'];
        if ($entry <= 0) return null;

        if ($tp > 0 && $price >= $tp) return ['close' => 'TAKE-PROFIT'];
        if ($price <= $stop)          return ['close' => $price >= $entry ? 'PROFIT-LOCK' : 'STOP-LOSS'];

        $prevPeak = (float) ($pos['peak_price'] ?: $entry);
        $peak     = max($prevPeak, $price);
        $armed    = $price >= $entry * (1 + $this->arm / 100.0);
        $newStop  = $stop;
        if ($armed) {
            $lockStop  = $entry * (1 + $this->lock / 100.0);
            $trailStop = $peak * (1 - $this->trail / 100.0);
            $newStop   = max($stop, $lockStop, $trailStop);
            $newStop   = min($newStop, $price * 0.999); // never at/above market
        }
        if ($newStop > $stop + 1e-12 || $peak > $prevPeak + 1e-12) {
            return ['trail' => ['stop_loss' => max($newStop, $stop), 'peak' => $peak]];
        }
        return null;
    }

    public function meta(): array
    {
        return [
            'min_gain'     => round($this->lock, 2),
            'max_gain'     => round($this->tp, 2),
            'min_hold_min' => $this->minHold,
            'max_hold_min' => $this->maxHold,
        ];
    }
}
