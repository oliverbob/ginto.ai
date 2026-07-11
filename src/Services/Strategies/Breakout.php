<?php
namespace Ginto\Services\Strategies;

use Ginto\Support\Env;

/**
 * Breakout — enter a coin pressing its 24h high (within 0.6%) with real volume;
 * wider take-profit, stop just under the breakout level.
 */
class Breakout implements GtbTemplate
{
    private float $sl;   // 2.0%
    private float $tp;   // 4.0%
    private float $minG;

    public function __construct()
    {
        $this->sl   = ((float) (Env::get('GTB_BREAKOUT_SL_PCT', '2.0') ?? 2.0)) / 100.0;
        $this->tp   = ((float) (Env::get('GTB_BREAKOUT_TP_PCT', '4.0') ?? 4.0)) / 100.0;
        $this->minG = (float) (Env::get('GTB_MIN_GAINER_PCT', '3') ?? 3);
    }

    public function key(): string { return 'breakout'; }
    public function name(): string { return 'Breakout'; }
    public function description(): string { return 'Coin pressing its 24h high on volume; stop under the level.'; }

    public function entryCandidate(array $tickers, array $held): ?array
    {
        $best = null;
        foreach ($tickers as $t) {
            if (in_array($t['symbol'], $held, true)) continue;
            if ($t['changePct'] < $this->minG) continue;
            $high = (float) ($t['high'] ?? 0);
            if ($high <= 0) continue;
            $nearHigh = $t['price'] >= $high * 0.994;  // within ~0.6% of the 24h high
            if (!$nearHigh) continue;
            // prefer the most liquid breakout (real volume behind it)
            if ($best === null || $t['quoteVol'] > $best['quoteVol']) {
                $best = ['symbol' => $t['symbol'], 'price' => $t['price'], 'changePct' => $t['changePct'],
                         'quoteVol' => $t['quoteVol'], 'reason' => 'pressing 24h high on volume'];
            }
        }
        if ($best) unset($best['quoteVol']);
        return $best;
    }

    public function stops(float $entry): array
    {
        return ['stop_loss' => $entry * (1 - $this->sl), 'take_profit' => $entry * (1 + $this->tp), 'trail_pct' => null];
    }

    public function meta(): array
    {
        return ['min_gain' => round($this->tp * 100, 2), 'max_gain' => round($this->tp * 100, 2), 'min_hold_min' => 0, 'max_hold_min' => 0];
    }

    public function manage(array $pos, float $price): ?array
    {
        if ($price <= (float) $pos['stop_loss'])  return ['close' => 'STOP-LOSS'];
        if ($price >= (float) $pos['take_profit']) return ['close' => 'TAKE-PROFIT'];
        return null;
    }
}
