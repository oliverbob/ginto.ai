<?php
namespace Ginto\Services;

use Ginto\Support\Env;

/**
 * Capital rules for the Ginto Trading Bot (single-account).
 *
 *   - base    = the most that may ever be drawn from the wallet (default $7).
 *   - unit    = growth step; each full unit of tradable unlocks one more concurrent
 *               trade slot (default $5). So $7 → 1 slot, $10 → 2, $15 → 3, ...
 *   - tradable = base + realized P&L (never adds fresh wallet money; can shrink on losses).
 *
 * Below the next $unit threshold, profit compounds into the existing position(s)
 * rather than opening new ones — perTradeSize = tradable / slots.
 */
class GtbCapital
{
    private float $base;
    private float $unit;
    private float $minNotional;

    public function __construct()
    {
        $this->base        = (float) (Env::get('GTB_BASE_CAPITAL', '7') ?? 7);
        $this->unit        = (float) (Env::get('GTB_GROWTH_UNIT', '5') ?? 5);
        $this->minNotional = (float) (Env::get('GTB_MIN_NOTIONAL', '5') ?? 5);
        if ($this->unit <= 0) $this->unit = 5;
    }

    /** Capital available to trade with — base plus realized profit, floored at 0. */
    public function tradable(float $realizedPnl): float
    {
        return max(0.0, $this->base + $realizedPnl);
    }

    /** Concurrent trade slots unlocked: one per full $unit of tradable (min 1 once above min notional). */
    public function slots(float $realizedPnl): int
    {
        $t = $this->tradable($realizedPnl);
        if ($t < $this->minNotional) {
            return 0;
        }
        return max(1, (int) floor($t / $this->unit));
    }

    /** Size per trade; profit compounds into open positions until the next slot unlocks. */
    public function perTradeSize(float $realizedPnl): float
    {
        $slots = $this->slots($realizedPnl);
        return $slots > 0 ? $this->tradable($realizedPnl) / $slots : 0.0;
    }

    public function canTrade(float $realizedPnl): bool
    {
        return $this->perTradeSize($realizedPnl) >= $this->minNotional;
    }

    public function summary(float $realizedPnl): array
    {
        return [
            'base'         => $this->base,
            'unit'         => $this->unit,
            'minNotional'  => $this->minNotional,
            'realizedPnl'  => round($realizedPnl, 2),
            'tradable'     => round($this->tradable($realizedPnl), 2),
            'slots'        => $this->slots($realizedPnl),
            'perTradeSize' => round($this->perTradeSize($realizedPnl), 2),
            'canTrade'     => $this->canTrade($realizedPnl),
        ];
    }
}
