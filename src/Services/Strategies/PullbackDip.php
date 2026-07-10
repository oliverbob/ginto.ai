<?php
namespace Ginto\Services\Strategies;

use Ginto\Support\Env;

/**
 * Pullback Dip — the contrarian of the set. Instead of chasing raw strength
 * (Scalp/Trend) or buying at the high (Breakout), it buys a coin that is still
 * in an uptrend on the day but has pulled back off its 24h high — a healthy
 * retracement, not a collapse. Entry filter: positive 24h change, price sitting
 * in the upper half of the day's range but at least a couple percent below the
 * high (the dip). Tight stop under, modest bounce target.
 */
class PullbackDip implements GtbTemplate
{
    private float $sl;      // hard stop, 1.8%
    private float $tp;      // bounce target, 3.0%
    private float $minG;    // still net-up on the day
    private float $minDip;  // must be at least this far off the 24h high (%)

    public function __construct()
    {
        $this->sl     = ((float) (Env::get('GTB_PULLBACK_SL_PCT', '1.8') ?? 1.8)) / 100.0;
        $this->tp     = ((float) (Env::get('GTB_PULLBACK_TP_PCT', '3.0') ?? 3.0)) / 100.0;
        $this->minG   = (float) (Env::get('GTB_PULLBACK_MIN_GAINER_PCT', '2') ?? 2);
        $this->minDip = (float) (Env::get('GTB_PULLBACK_MIN_DIP_PCT', '2.0') ?? 2.0);
    }

    public function key(): string { return 'pullback'; }
    public function name(): string { return 'Pullback Dip'; }
    public function description(): string { return 'Buy an uptrend that pulled back off its 24h high (not a collapse); tight stop, quick bounce target.'; }

    public function entryCandidate(array $tickers, array $held): ?array
    {
        $best = null;
        foreach ($tickers as $t) {
            if (in_array($t['symbol'], $held, true)) continue;
            if ($t['changePct'] < $this->minG) continue;          // still up on the day
            $high = (float) ($t['high'] ?? 0);
            $low  = (float) ($t['low'] ?? 0);
            $px   = (float) $t['price'];
            if ($high <= 0 || $low <= 0 || $high <= $low || $px <= 0) continue;

            $offHigh = ($high - $px) / $high * 100.0;              // how far it dipped from the high
            if ($offHigh < $this->minDip) continue;               // not a real pullback -> skip (Breakout handles the high)
            $rangePos = ($px - $low) / ($high - $low);            // 0 = at low, 1 = at high
            if ($rangePos < 0.5) continue;                        // still in the upper half = uptrend intact, not a dump

            // Prefer the strongest trend that gave the deepest healthy dip (best risk/reward bounce).
            $score = $t['changePct'] + $offHigh;
            if ($best === null || $score > $best['_score']) {
                $best = ['symbol' => $t['symbol'], 'price' => $px, 'changePct' => $t['changePct'],
                         '_score' => $score,
                         'reason' => sprintf('uptrend +%.1f%%, pulled back %.1f%% off high — buying the dip', $t['changePct'], $offHigh)];
            }
        }
        if ($best) unset($best['_score']);
        return $best;
    }

    public function stops(float $entry): array
    {
        return ['stop_loss' => $entry * (1 - $this->sl), 'take_profit' => $entry * (1 + $this->tp), 'trail_pct' => null];
    }

    public function manage(array $pos, float $price): ?array
    {
        if ($price <= (float) $pos['stop_loss'])   return ['close' => 'STOP-LOSS'];
        if ($price >= (float) $pos['take_profit']) return ['close' => 'TAKE-PROFIT'];
        return null;
    }
}
