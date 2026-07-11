<?php
namespace Ginto\Services\Strategies;

/**
 * A pluggable trading strategy template. Each template decides what to enter
 * (from a pre-filtered ticker list) and how to manage/exit an open position.
 * All price levels are absolute USDT prices.
 */
interface GtbTemplate
{
    public function key(): string;
    public function name(): string;
    public function description(): string;

    /**
     * Testable metadata about the template's target/behaviour. Keys:
     *   min_gain (float %)  — the minimum profit a win aims to bank (0 = none/rides).
     *   max_gain (float %)  — the highest gain it reaches for (0 = uncapped / trails).
     *   min_hold_min (int)  — don't rotate/stall out before this many minutes (0 = default).
     *   max_hold_min (int)  — force a time-box exit after this many minutes (0 = global default).
     */
    public function meta(): array;

    /**
     * Pick one entry candidate from liquid USDT tickers, excluding held symbols.
     * $tickers items: ['symbol','base','changePct','price','high','low','quoteVol'].
     * Return ['symbol','price','changePct','reason'] or null.
     */
    public function entryCandidate(array $tickers, array $held): ?array;

    /** Initial protective levels for a new entry at $entry. Keys: stop_loss, take_profit(?), trail_pct(?). */
    public function stops(float $entry): array;

    /**
     * Manage an open position at current $price.
     * Return ['close' => reason] to exit, ['trail' => ['stop_loss'=>x,'peak'=>y]] to ratchet, or null to hold.
     */
    public function manage(array $pos, float $price): ?array;
}
