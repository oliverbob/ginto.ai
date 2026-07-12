<?php
namespace Ginto\Services;

use Ginto\Support\Env;

/**
 * Capital rules for the Ginto Trading Bot (single-account).
 *
 *   - base    = the disciplined stake (default $7): the most that may be drawn from
 *               the wallet in "staked" mode.
 *   - unit    = growth step; each full unit of tradable unlocks one more concurrent
 *               trade slot (default $5). So $7 → 1 slot, $10 → 2, $15 → 3, ...
 *   - tradable = capital available to trade with, which depends on the CAPITAL MODE:
 *
 *       staked : base + realized P&L (never adds fresh wallet money; can shrink on losses).
 *       full   : the ENTIRE wallet (free USDT + value already deployed) — manual override.
 *       ai     : base + realized P&L, plus a slice of the remaining wallet that grows as
 *                the bot proves itself (performance-scaled) and pulls back on losses.
 *
 * Below the next $unit threshold, profit compounds into the existing position(s)
 * rather than opening new ones — perTradeSize = tradable / slots.
 */
class GtbCapital
{
    private float $base;
    private float $unit;
    private float $minNotional;
    private string $mode;      // staked | full | ai
    private float $aiScale;    // how fast "ai" mode unlocks the wallet as the base grows
    private float $wallet = 0.0;
    private float $aiPct = 0.0;

    public function __construct()
    {
        $this->base        = (float) (Env::get('GTB_BASE_CAPITAL', '7') ?? 7);
        $this->unit        = (float) (Env::get('GTB_GROWTH_UNIT', '5') ?? 5);
        $this->minNotional = (float) (Env::get('GTB_MIN_NOTIONAL', '5') ?? 5);
        $this->aiScale     = (float) (Env::get('GTB_AI_SCALE', '50') ?? 50);
        $mode = strtolower(trim((string) (Env::get('GTB_CAPITAL_MODE', 'staked') ?? 'staked')));
        $this->mode = in_array($mode, ['staked', 'full', 'ai'], true) ? $mode : 'staked';
        if ($this->unit <= 0) $this->unit = 5;
    }

    public function mode(): string { return $this->mode; }
    public function minNotional(): float { return $this->minNotional; }

    /** Total wallet available (free USDT + value already deployed). Only used by full/ai modes. */
    public function setWallet(float $walletUsd): self { $this->wallet = max(0.0, $walletUsd); return $this; }

    /** Fraction (0–100) of the remaining wallet the "ai" mode may deploy right now. */
    public function setAiPct(float $pct): self { $this->aiPct = max(0.0, min(100.0, $pct)); return $this; }

    /**
     * Performance-scaled allocation for "ai" mode: 0% at the start, rising with how much
     * the bot has grown the base (and falling on losses). Fully unlocked once the base
     * has roughly (100/aiScale)x'd in profit. Capital only scales up AFTER winning.
     */
    public function autoAiPct(float $realizedPnl): float
    {
        if ($this->base <= 0) return 0.0;
        $growthRatio = $realizedPnl / $this->base;      // 0 at start, 1.0 when profit == base
        return max(0.0, min(100.0, $growthRatio * $this->aiScale));
    }

    /** Capital available to trade with — depends on the mode (see class docblock). */
    public function tradable(float $realizedPnl): float
    {
        $staked = max(0.0, $this->base + $realizedPnl);
        if ($this->mode === 'full') {
            return max($staked, $this->wallet);
        }
        if ($this->mode === 'ai') {
            $headroom = max(0.0, $this->wallet - $staked);
            return $staked + $headroom * ($this->aiPct / 100.0);
        }
        return $staked;
    }

    /**
     * Concurrent trade slots unlocked: one per full $unit of tradable, but never so many
     * that a slot would fall below the min per-trade — so each open trade always clears
     * Binance's minimum (and setting a small unit can't silently stop the bot trading).
     */
    public function slots(float $realizedPnl): int
    {
        $t = $this->tradable($realizedPnl);
        if ($t < $this->minNotional) {
            return 0;
        }
        $byUnit     = (int) floor($t / $this->unit);
        $byNotional = (int) floor($t / $this->minNotional);   // cap so perTradeSize >= minNotional
        $slots      = max(1, min($byUnit, $byNotional));
        // Hard ceiling on concurrent trades so a large wallet can't spawn hundreds of positions
        // (and burn AI tokens on every new entry). 0 = no cap.
        $maxSlots   = (int) (Env::get('GTB_MAX_SLOTS', '0') ?? 0);
        if ($maxSlots > 0) $slots = min($slots, $maxSlots);
        return $slots;
    }

    /** Size per trade; profit compounds into open positions until the next slot unlocks. Capped by GTB_MAX_TRADE_USD. */
    public function perTradeSize(float $realizedPnl): float
    {
        $slots = $this->slots($realizedPnl);
        $size  = $slots > 0 ? $this->tradable($realizedPnl) / $slots : 0.0;
        $max   = (float) (Env::get('GTB_MAX_TRADE_USD', '0') ?? 0);
        if ($max > 0 && $size > $max) $size = $max;
        return $size;
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
            'mode'         => $this->mode,
            'wallet'       => round($this->wallet, 2),
            'aiPct'        => $this->mode === 'ai' ? round($this->aiPct, 1) : null,
            'realizedPnl'  => round($realizedPnl, 2),
            'tradable'     => round($this->tradable($realizedPnl), 2),
            'slots'        => $this->slots($realizedPnl),
            'perTradeSize' => round($this->perTradeSize($realizedPnl), 2),
            'canTrade'     => $this->canTrade($realizedPnl),
        ];
    }
}
