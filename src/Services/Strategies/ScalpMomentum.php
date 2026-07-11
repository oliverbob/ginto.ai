<?php
namespace Ginto\Services\Strategies;

use Ginto\Support\Env;

/** Scalp Momentum — strongest liquid gainer in a sweet-spot band, tight fixed SL/TP. */
class ScalpMomentum implements GtbTemplate
{
    private float $sl;
    private float $tp;
    private float $minG;
    private float $maxG;

    public function __construct()
    {
        $this->sl   = ((float) (Env::get('GTB_STOP_LOSS_PCT', '1.5') ?? 1.5)) / 100.0;
        $this->tp   = ((float) (Env::get('GTB_TAKE_PROFIT_PCT', '2.5') ?? 2.5)) / 100.0;
        $this->minG = (float) (Env::get('GTB_MIN_GAINER_PCT', '3') ?? 3);
        $this->maxG = (float) (Env::get('GTB_MAX_GAINER_PCT', '40') ?? 40);
    }

    public function key(): string { return 'scalp'; }
    public function name(): string { return 'Scalp Momentum'; }
    public function description(): string { return 'Strongest liquid gainer, tight take-profit and stop-loss.'; }

    public function entryCandidate(array $tickers, array $held): ?array
    {
        $best = null;
        foreach ($tickers as $t) {
            if (in_array($t['symbol'], $held, true)) continue;
            if ($t['changePct'] < $this->minG || $t['changePct'] > $this->maxG) continue;
            if ($best === null || $t['changePct'] > $best['changePct']) {
                $best = ['symbol' => $t['symbol'], 'price' => $t['price'], 'changePct' => $t['changePct'],
                         'reason' => "top gainer +{$t['changePct']}% in band"];
            }
        }
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
