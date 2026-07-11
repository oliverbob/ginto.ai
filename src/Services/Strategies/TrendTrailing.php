<?php
namespace Ginto\Services\Strategies;

use Ginto\Support\Env;

/**
 * Trend Trailing — enter momentum, then ride it with a trailing stop that ratchets
 * up as the price makes new highs. No fixed take-profit; a hard initial stop plus a
 * trailing stop lock in gains while letting winners run.
 */
class TrendTrailing implements GtbTemplate
{
    private float $sl;       // initial hard stop, 2.0%
    private float $trail;    // trail distance %, 1.5%
    private float $minG;
    private float $maxG;

    public function __construct()
    {
        $this->sl    = ((float) (Env::get('GTB_TREND_SL_PCT', '2.0') ?? 2.0)) / 100.0;
        $this->trail = (float) (Env::get('GTB_TREND_TRAIL_PCT', '1.5') ?? 1.5);
        $this->minG  = (float) (Env::get('GTB_MIN_GAINER_PCT', '3') ?? 3);
        $this->maxG  = (float) (Env::get('GTB_MAX_GAINER_PCT', '40') ?? 40);
    }

    public function key(): string { return 'trend'; }
    public function name(): string { return 'Trend Trailing'; }
    public function description(): string { return 'Ride momentum with an ATR-style trailing stop; no fixed target.'; }

    public function entryCandidate(array $tickers, array $held): ?array
    {
        // Second-strongest gainer in band (diversify from Scalp which takes the top one).
        $inBand = [];
        foreach ($tickers as $t) {
            if (in_array($t['symbol'], $held, true)) continue;
            if ($t['changePct'] < $this->minG || $t['changePct'] > $this->maxG) continue;
            $inBand[] = $t;
        }
        if (!$inBand) return null;
        usort($inBand, static fn($a, $b) => $b['changePct'] <=> $a['changePct']);
        $t = $inBand[count($inBand) > 1 ? 1 : 0];
        return ['symbol' => $t['symbol'], 'price' => $t['price'], 'changePct' => $t['changePct'],
                'reason' => "trending +{$t['changePct']}%, ride with trailing stop"];
    }

    public function stops(float $entry): array
    {
        return ['stop_loss' => $entry * (1 - $this->sl), 'take_profit' => null, 'trail_pct' => $this->trail];
    }

    public function meta(): array
    {
        // Trailing: no fixed floor, no cap — it rides and gives back ~trail% from the peak.
        return ['min_gain' => 0.0, 'max_gain' => 0.0, 'min_hold_min' => 0, 'max_hold_min' => 0];
    }

    public function manage(array $pos, float $price): ?array
    {
        $prevPeak  = (float) ($pos['peak_price'] ?: $pos['price']);
        $peak      = max($prevPeak, $price);
        $trailPct  = (float) ($pos['trail_pct'] ?: $this->trail);
        $hardStop  = (float) $pos['stop_loss'];               // ratchets up, never down
        $trailStop = $peak * (1 - $trailPct / 100.0);
        $effStop   = max($hardStop, $trailStop);

        if ($price <= $effStop) return ['close' => 'TRAIL-STOP'];
        if ($effStop > $hardStop + 1e-12 || $peak > $prevPeak + 1e-12) {
            return ['trail' => ['stop_loss' => $effStop, 'peak' => $peak]];
        }
        return null;
    }
}
