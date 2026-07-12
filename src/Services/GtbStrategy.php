<?php
namespace Ginto\Services;

use Ginto\Support\Env;
use Ginto\Models\GtbSettings;
use Ginto\Models\GtbTrade;
use Ginto\Models\GtbThought;
use Ginto\Models\GtbBotState;
use Ginto\Services\Strategies\GtbTemplate;
use Ginto\Services\Strategies\ScalpMomentum;
use Ginto\Services\Strategies\Breakout;
use Ginto\Services\Strategies\TrendTrailing;
use Ginto\Services\Strategies\PullbackDip;
use Ginto\Services\Strategies\GainerHunter;
use Ginto\Services\Strategies\GtbProfiles;

/**
 * Multi-strategy, multi-position engine. One step():
 *   1) manage every open position via its template (deterministic exits / trailing),
 *   2) if a capital slot is free, open ONE new position — pick the least-used enabled
 *      template, get its candidate, have the AI brain confirm, then enter.
 *
 * Execution follows the testnet toggle: testnet = PAPER (simulated at live price),
 * mainnet = LIVE (real orders, gated behind an explicit arm flag). Exits are
 * deterministic; the AI is only consulted to confirm a NEW entry (cost control).
 */
class GtbStrategy
{
    /** @var array<string,GtbTemplate> */
    private array $templates = [];

    /** Free USDT available for a live buy this step (full/ai modes cap size to it); null = no cap. */
    private ?float $liveFreeUsd = null;

    /** Total wallet value (free + deployed) this step, when computed; used by the wallet-floor guard. */
    private ?float $walletUsd = null;

    public function __construct()
    {
        $all = ['gainers' => GainerHunter::class, 'scalp' => ScalpMomentum::class, 'breakout' => Breakout::class, 'trend' => TrendTrailing::class, 'pullback' => PullbackDip::class];
        $enabled = array_filter(array_map('trim', explode(',', (string) (Env::get('GTB_TEMPLATES', 'gainers,scalp,breakout,trend,pullback') ?? ''))));
        if (!$enabled) $enabled = array_keys($all);
        foreach ($enabled as $k) {
            if (isset($all[$k])) $this->templates[$k] = new $all[$k]();
        }
        if (!$this->templates) $this->templates['scalp'] = new ScalpMomentum();
    }

    /** All known templates with display info + testable metadata (min/max gain, hold window). */
    public static function catalog(): array
    {
        $classes = ['gainers' => GainerHunter::class, 'scalp' => ScalpMomentum::class, 'breakout' => Breakout::class, 'trend' => TrendTrailing::class, 'pullback' => PullbackDip::class];
        $out = [];
        foreach ($classes as $k => $cls) {
            try { $t = new $cls(); $out[$k] = ['name' => $t->name(), 'description' => $t->description(), 'meta' => $t->meta()]; }
            catch (\Throwable $e) {}
        }
        return $out;
    }

    public function step(bool $armLive = false): array
    {
        $settings = new GtbSettings();
        $mode     = $settings->isTestnet() ? 'paper' : 'live';
        $client   = new BinanceClient();
        $trades   = new GtbTrade();
        $thoughts = new GtbThought();
        $cap      = new GtbCapital();
        $realized = $trades->totalRealizedPnl($mode);
        $tickers  = $this->liquidTickers($client);
        $this->configureCapital($cap, $client, $trades, $mode, $realized, $tickers);

        // Time-boxing: max hold per trade + optional session window (both deterministic).
        $botState     = new GtbBotState();
        $openNew      = $botState->isOpeningNew();
        $maxHoldMin   = (int) (Env::get('GTB_MAX_HOLD_MIN', 0) ?? 0);
        $stallMin     = (int) (Env::get('GTB_STALL_MINUTES', 0) ?? 0);
        $stallMinGain = (float) (Env::get('GTB_STALL_MIN_GAIN_PCT', 0) ?? 0);
        $sessionHours = (float) (Env::get('GTB_SESSION_HOURS', 0) ?? 0);
        $sessionOver  = false;
        if ($sessionHours > 0) {
            $start = $botState->sessionStartedAt();
            if ($start) $sessionOver = (time() - strtotime($start)) >= $sessionHours * 3600;
        }

        $priceOf  = function (string $sym) use ($tickers, $client) {
            return isset($tickers[$sym]) ? $tickers[$sym]['price'] : $client->price($sym);
        };

        // ---- 1) manage every open position ----
        //   paper: deterministic exits are simulated here at the live mark price.
        //   live : the stop/target rest ON THE EXCHANGE (OCO / stop-limit). We only
        //          reconcile fills, ratchet the trailing stop, and re-protect if a
        //          protective order goes missing — the exchange enforces the stop
        //          even if this runner is down.
        foreach ($trades->openPositions() as $pos) {
            $price = $priceOf($pos['symbol']);
            if ($price === null || $price <= 0) continue;

            // Time-box first: session end or max-hold force an exit regardless of stop/target.
            if ($sessionOver) {
                $realized += $this->forceClose($client, $trades, $thoughts, $pos, (float) $price, $mode, 'SESSION-END');
                continue;
            }
            // Per-template time-box: a template may set its own max-hold / min-hold (e.g. the
            // Gainer Hunter holds 15–45m); fall back to the global setting otherwise.
            $tplMeta    = isset($this->templates[$pos['template'] ?? '']) ? $this->templates[$pos['template']]->meta() : [];
            $posMaxHold = ((int) ($tplMeta['max_hold_min'] ?? 0)) ?: $maxHoldMin;
            $posMinHold = (int) ($tplMeta['min_hold_min'] ?? 0);
            if ($posMaxHold > 0) {
                $age = $this->ageMinutes($pos['created_at'] ?? null);
                if ($age !== null && $age >= $posMaxHold) {
                    $realized += $this->forceClose($client, $trades, $thoughts, $pos, (float) $price, $mode, 'MAX-HOLD');
                    continue;
                }
            }
            // Stall rotation: after N minutes below the required follow-through gain, rotate out —
            // but never before the template's minimum hold (give a fresh runner room to work).
            if ($stallMin > 0) {
                $age  = $this->ageMinutes($pos['created_at'] ?? null);
                $gain = ((float) $price - (float) $pos['price']) / (float) $pos['price'] * 100.0;
                if ($age !== null && $age >= max($stallMin, $posMinHold) && $gain < $stallMinGain) {
                    $realized += $this->forceClose($client, $trades, $thoughts, $pos, (float) $price, $mode, 'STALL');
                    continue;
                }
            }

            if (($pos['mode'] ?? $mode) === 'live') {
                $realized += $this->reconcileLive($client, $trades, $thoughts, $pos, (float) $price);
                continue;
            }

            $tpl = $this->templates[$pos['template']] ?? ($this->templates['scalp'] ?? new ScalpMomentum());
            $m = $tpl->manage($pos, (float) $price);
            if (!$m) continue;
            if (isset($m['close'])) {
                $pnl = $this->netPnl((float) $pos["price"], (float) $price, (float) $pos["qty"]);
                $trades->closeTrade((int) $pos['id'], (float) $price, $pnl);
                $realized += $pnl;
                $thoughts->add(sprintf('Closed %s at $%s (%s) [%s] · P&L %s$%.4f.',
                    $pos['symbol'], $this->fmt((float) $price), $m['close'], $pos['template'] ?? 'scalp',
                    $pnl >= 0 ? '+' : '-', abs($pnl)
                ), 'bot', 'trade', $pos['symbol'], $m['close'], ['mode' => 'paper', 'pnl' => round($pnl, 6), 'template' => $pos['template'] ?? null]);
            } elseif (isset($m['trail'])) {
                $trades->updateStop((int) $pos['id'], (float) $m['trail']['stop_loss'], (float) $m['trail']['peak']);
            }
        }

        // ---- 2) open ONE new position if a slot is free ----
        // Session over: stay flat, take nothing new until the next Start resets the clock.
        if ($sessionOver) {
            return $this->openPositionsState($client, $trades, $cap, $realized, $mode,
                'session ended — flat, no new trades', $tickers);
        }
        // Wind-down (Stop pressed): keep managing open positions to a good exit, take
        // nothing new, and finalize to a full stop once everything is closed.
        if (!$openNew) {
            $open = $trades->openPositions();
            if (count($open) === 0) {
                $botState->stop();
                return $this->openPositionsState($client, $trades, $cap, $realized, $mode,
                    'wind-down complete — all positions closed, bot stopped', $tickers);
            }
            return $this->openPositionsState($client, $trades, $cap, $realized, $mode,
                'winding down — managing ' . count($open) . ' position(s), no new trades', $tickers);
        }
        // Circuit breaker: if the session has lost more than the limit, stop opening NEW trades
        // (open positions still ride their exchange stops). Bounds how bad a session can get.
        $maxLoss = (float) (Env::get('GTB_SESSION_MAX_LOSS', '0') ?? 0);
        if ($maxLoss > 0) {
            $sessStart = $botState->sessionStartedAt();
            $sessPnl = $sessStart ? $trades->realizedSince($mode, $sessStart) : $realized;
            if ($sessPnl <= -abs($maxLoss)) {
                return $this->openPositionsState($client, $trades, $cap, $realized, $mode,
                    sprintf('circuit breaker — session P&L $%.2f hit the -$%.2f loss limit; no new trades', $sessPnl, abs($maxLoss)), $tickers);
            }
        }

        // Wallet floor: stop SPENDING (opening new) once the wallet drops below the floor,
        // but keep managing open positions to their exits.
        $floor = (float) (Env::get('GTB_WALLET_FLOOR', '0') ?? 0);
        if ($floor > 0 && $this->walletUsd !== null && $this->walletUsd < $floor) {
            return $this->openPositionsState($client, $trades, $cap, $realized, $mode,
                sprintf('wallet $%.2f below floor $%.2f — holding, no new trades', $this->walletUsd, $floor), $tickers);
        }

        $open  = $trades->openPositions();
        $slots = $cap->slots($realized);
        $free  = $slots - count($open);
        $action = 'managing ' . count($open) . ' position(s)';

        if ($free < 1) {
            $action = 'all ' . $slots . ' slot(s) in use';
        } elseif (!$cap->canTrade($realized)) {
            $action = 'capital locked (below min notional)';
        } else {
            $brain = new GtbBrain();
            if (!$brain->isConfigured()) {
                $action = 'no AI key — cannot open new trades';
            } else {
                $held = array_column($open, 'symbol');
                // Re-entry cooldown: never rebuy a coin we just exited for N minutes. This is the
                // anti-churn guard — without it the bot rebuys the same top-gainer at a higher price
                // right after a stop/target fill, bleeding round-trip fees on a choppy move.
                $cooldownMin = (int) (Env::get('GTB_REENTRY_COOLDOWN_MIN', '20') ?? 20);
                $avoid = array_values(array_unique(array_merge(
                    $held, $trades->recentlyClosedSymbols($mode, $cooldownMin)
                )));

                // Session self-learning (Gainer Hunter): track how far the gainers we considered
                // actually ran, count the ones we skipped that then moved, and switch to "chase"
                // once we've missed enough runners — patient dip-buying first, chasing once burned.
                $learn      = new \Ginto\Models\GtbLearning();
                $gStats     = new \Ginto\Models\GtbGainerStats();
                $sessionKey = $botState->sessionStartedAt() ?: 'nosession';
                $priceMap   = [];
                foreach ($tickers as $tk) { if (isset($tk['symbol'], $tk['price'])) $priceMap[$tk['symbol']] = (float) $tk['price']; }
                $learn->updateBest($sessionKey, $priceMap);
                $missPct    = (float) (Env::get('GTB_GAINER_MISS_PCT', '2.5') ?? 2.5);
                $missTrig   = (int) (Env::get('GTB_GAINER_MISS_TRIGGER', '2') ?? 2);
                $sessMisses = $learn->missCount($sessionKey, $missPct);
                $chaseMode  = $sessMisses >= $missTrig;

                $size = $cap->perTradeSize($realized);
                // Live full/ai modes: never size a buy above the free USDT actually on hand.
                if ($mode === 'live' && $this->liveFreeUsd !== null) {
                    $size = min($size, $this->liveFreeUsd * 0.997);
                }
                if ($size < $cap->minNotional()) {
                    return $this->openPositionsState($client, $trades, $cap, $realized, $mode,
                        'insufficient free USDT for a new trade', $tickers);
                }
                $memory = Env::bool('GTB_MEMORY_ENABLED', false) ? $this->buildMemory($trades) : null;

                // Profile order: least-used first so the two bots share slots fairly.
                $profiles = GtbProfiles::enabled();
                $pCounts  = array_count_values(array_map(static fn($p) => $p['profile'] ?? 'conservative', $open));
                usort($profiles, static fn($a, $b) => ($pCounts[$a] ?? 0) <=> ($pCounts[$b] ?? 0));

                // (profile, template) attempts in priority order.
                $attempts = [];
                foreach ($profiles as $pk) {
                    $cfg = GtbProfiles::config($pk);
                    $tks = array_values(array_filter($cfg['templates'], fn($t) => isset($this->templates[$t])));
                    if (!$tks) $tks = array_keys($this->templates);   // fall back to whatever is enabled
                    foreach ($tks as $tk) $attempts[] = [$pk, $tk, $cfg];
                }

                $opened = false;
                foreach ($attempts as [$pk, $key, $cfg]) {
                    $tpl  = $this->templates[$key];
                    $isGainer = $tpl instanceof GainerHunter;
                    if ($isGainer) $tpl->setChase($chaseMode);
                    $cand = $tpl->entryCandidate(array_values($tickers), $avoid);
                    if (!$cand) continue;
                    if ($isGainer) {
                        $learn->observe($sessionKey, $cand['symbol'], (float) $cand['price']);
                        // Cross-session learning: skip setups with a proven losing record BEFORE
                        // spending an AI call on them (deterministic, token-free).
                        if ($gStats->shouldAvoid($chaseMode ? 'chase' : 'dip', (float) $cand['changePct'])) {
                            $thoughts->add(sprintf('Gainer Hunter passed on %s — learned that %s entries around +%s%% have been net losers.',
                                $cand['symbol'], $chaseMode ? 'chase' : 'dip', $cand['changePct']), 'bot', 'note', $cand['symbol'], 'SKIP', ['learned' => true]);
                            $action = 'skipped ' . $cand['symbol'] . ' (learned loser setup)';
                            continue;
                        }
                    }

                    $ctx = ['env' => $mode, 'profile' => $cfg['name'], 'posture' => $cfg['posture'],
                            'template' => $tpl->name(), 'templateRule' => $tpl->description(),
                            'capital' => $cap->summary($realized), 'perTradeSize' => round($size, 2)];
                    if ($memory) $ctx['memory'] = $memory;
                    if ($isGainer) { $ctx['sessionMisses'] = $sessMisses; $ctx['entryMode'] = $chaseMode ? 'chase (missed runners — bid strength)' : 'patient (prefer a slight dip)'; }
                    $res = $brain->decide($cand, $ctx, 'decision');
                    if (empty($res['ok'])) {
                        $thoughts->add('Decision failed: ' . ($res['error'] ?? ''), 'system', 'error');
                        $action = 'brain error: ' . ($res['error'] ?? '');
                        break;
                    }
                    $meta = array_merge(['model' => $res['model'] ?? '', 'template' => $key, 'profile' => $pk], $res['usage'] ?? []);
                    $thoughts->add($res['text'], 'claude', 'decision', $cand['symbol'], $res['decision'] ?? null, $meta);

                    if (($res['decision'] ?? '') !== 'BUY') {
                        // This bot passed on its best idea; let the next (profile,template) try.
                        $action = '[' . $cfg['name'] . '/' . $tpl->name() . '] skipped ' . $cand['symbol'];
                        continue;
                    }
                    $price = $priceOf($cand['symbol']);
                    if ($price === null || $price <= 0) { $action = 'price unavailable at entry'; continue; }
                    $fill = (float) $price; $qty = $size / $fill; $oid = null;
                    $protType = null; $protId = null;
                    if ($mode === 'live') {
                        if (!$armLive) { $action = '[' . $cfg['name'] . '] BUY ' . $cand['symbol'] . ' — live not armed'; break; }
                        $ord = $client->marketBuyQuote($cand['symbol'], $size);
                        if (empty($ord['ok'])) {
                            $thoughts->add('Live order failed: ' . ($ord['error'] ?? ''), 'system', 'error');
                            $action = 'live order failed: ' . ($ord['error'] ?? ''); break;
                        }
                        $d = $ord['data']; $ex = (float) ($d['executedQty'] ?? 0); $cum = (float) ($d['cummulativeQuoteQty'] ?? 0);
                        if ($ex > 0) { $qty = $ex; $fill = $cum / $ex; }
                        $oid = $d['orderId'] ?? null;
                    }
                    $st = $this->applyRisk($tpl->stops($fill), $fill, $cfg);
                    $st = $this->applyTargetOverride($st, $fill);
                    if ($mode === 'live') {
                        // Put the stop (and target) ON the exchange before we consider the position held.
                        $prot = $this->protectLive($client, $cand['symbol'], $qty, $st);
                        if (!empty($prot['error'])) {
                            // Never hold unprotected inventory — sell straight back out.
                            $client->marketSell($cand['symbol'], $this->floorToStep($client, $cand['symbol'], $qty * 0.999));
                            $thoughts->add('Aborted ' . $cand['symbol'] . ': could not place exchange stop (' . $prot['error'] . ') — sold back to stay protected.',
                                'system', 'error', $cand['symbol'], null, ['mode' => 'live']);
                            $action = 'aborted ' . $cand['symbol'] . ' — exchange stop rejected';
                            break;
                        }
                        $protType = $prot['type']; $protId = $prot['id'];
                    }
                    $trades->openTrade([
                        'symbol' => $cand['symbol'], 'mode' => $mode, 'template' => $key, 'profile' => $pk,
                        'entry_chg' => (float) ($cand['changePct'] ?? 0), 'entry_mode' => $isGainer ? ($chaseMode ? 'chase' : 'dip') : null,
                        'price' => $fill, 'qty' => $qty, 'quote_qty' => $size,
                        'stop_loss' => $st['stop_loss'], 'take_profit' => $st['take_profit'] ?? null,
                        'peak_price' => $fill, 'trail_pct' => $st['trail_pct'] ?? null, 'binance_order_id' => $oid,
                        'protect_type' => $protType, 'protect_id' => $protId,
                    ]);
                    if ($isGainer) $learn->markEntered($sessionKey, $cand['symbol']);
                    $tpTxt = (!empty($st['take_profit'])) ? ' / TP $' . $this->fmt($st['take_profit']) : ' · trailing ' . ($st['trail_pct'] ?? '') . '%';
                    $chaseTxt = ($isGainer && $chaseMode) ? ' · chase mode (' . $sessMisses . ' missed runner' . ($sessMisses === 1 ? '' : 's') . ')' : '';
                    $thoughts->add(sprintf('Entered %s %s [%s · %s] — $%.2f at $%s · SL $%s%s%s%s.',
                        $mode === 'paper' ? '(paper)' : '(LIVE)', $cand['symbol'], $cfg['name'], $tpl->name(), $size,
                        $this->fmt($fill), $this->fmt($st['stop_loss']), $tpTxt,
                        $mode === 'live' ? ' · exchange-protected' : '', $chaseTxt
                    ), 'bot', 'trade', $cand['symbol'], 'BUY', ['mode' => $mode, 'template' => $key, 'profile' => $pk]);
                    $action = 'entered ' . $cand['symbol'] . ' [' . $cfg['name'] . ']';
                    $opened = true;
                    break;
                }
                if (!$opened && $action === ('managing ' . count($open) . ' position(s)')) {
                    $action = 'no qualifying setup this cycle';
                }
            }
        }

        return $this->openPositionsState($client, $trades, $cap, $realized, $mode, $action, $tickers);
    }

    /** Wallet-aware capital summary for display / AI context (respects the capital mode). */
    public function capitalSummary(): array
    {
        $client   = new BinanceClient();
        $trades   = new GtbTrade();
        $mode     = (new GtbSettings())->isTestnet() ? 'paper' : 'live';
        $realized = $trades->totalRealizedPnl($mode);
        $cap      = new GtbCapital();
        $this->configureCapital($cap, $client, $trades, $mode, $realized);
        return $cap->summary($realized);
    }

    /** Sell every open position at market now (emergency stop). Returns count closed. */
    public function flattenAll(): array
    {
        $client   = new BinanceClient();
        $trades   = new GtbTrade();
        $thoughts = new GtbThought();
        $mode     = (new GtbSettings())->isTestnet() ? 'paper' : 'live';
        $n = 0;
        foreach ($trades->openPositions() as $pos) {
            $price = $client->price($pos['symbol']);
            if ($price === null || $price <= 0) $price = (float) $pos['price'];
            $this->forceClose($client, $trades, $thoughts, $pos, (float) $price, $mode, 'MANUAL-STOP');
            $n++;
        }
        return ['ok' => true, 'closed' => $n];
    }

    /** Manually close one open position at the current price (paper or live). */
    public function closePosition(int $id): array
    {
        $trades   = new GtbTrade();
        $client   = new BinanceClient();
        $thoughts = new GtbThought();
        $mode     = (new GtbSettings())->isTestnet() ? 'paper' : 'live';

        $pos = $trades->getOpen($id);
        if (!$pos) return ['ok' => false, 'error' => 'Position not found or already closed.'];
        $price = $client->price($pos['symbol']);
        if ($price === null || $price <= 0) return ['ok' => false, 'error' => 'Price unavailable — try again.'];

        if ($mode === 'live') {
            // Cancel the resting protective order first so it can't fire after we've sold.
            $type = $pos['protect_type'] ?? null; $pid = $pos['protect_id'] ?? null;
            if ($type === 'oco' && $pid)  $client->cancelOco($pos['symbol'], (string) $pid);
            elseif ($type === 'stop' && $pid) $client->cancelOrder($pos['symbol'], (string) $pid);
            $q = $this->floorToStep($client, $pos['symbol'], (float) $pos['qty'] * 0.999);
            $client->marketSell($pos['symbol'], $q);
        }
        $pnl = $this->netPnl((float) $pos["price"], (float) $price, (float) $pos["qty"]);
        $trades->closeTrade((int) $pos['id'], (float) $price, $pnl);
        $trades->setProtection((int) $pos['id'], null, null);
        $thoughts->add(sprintf('Manually closed %s at $%s [%s] · P&L %s$%.4f.',
            $pos['symbol'], $this->fmt((float) $price), $pos['template'] ?? 'scalp',
            $pnl >= 0 ? '+' : '-', abs($pnl)
        ), 'bot', 'trade', $pos['symbol'], 'MANUAL', ['mode' => $mode, 'pnl' => round($pnl, 6)]);
        return ['ok' => true, 'pnl' => round($pnl, 4)];
    }

    /** Live snapshot of all open positions + portfolio totals (used by the monitoring grid). */
    public function openPositionsState(
        ?BinanceClient $client = null, ?GtbTrade $trades = null, ?GtbCapital $cap = null,
        ?float $realized = null, ?string $mode = null, string $action = '', array $tickers = []
    ): array {
        $client = $client ?? new BinanceClient();
        $trades = $trades ?? new GtbTrade();
        if ($mode === null)     $mode = (new GtbSettings())->isTestnet() ? 'paper' : 'live';
        if ($realized === null) $realized = $trades->totalRealizedPnl($mode);
        // If called fresh (e.g. from the dashboard), teach a new capital object about the wallet.
        if ($cap === null) {
            $cap = new GtbCapital();
            $this->configureCapital($cap, $client, $trades, $mode, $realized, $tickers);
        }

        $positions = [];
        $unrealTotal = 0.0;
        foreach ($trades->openPositions() as $p) {
            $sym   = $p['symbol'];
            $price = isset($tickers[$sym]) ? $tickers[$sym]['price'] : $client->price($sym);
            $entry = (float) $p['price'];
            $qty   = (float) $p['qty'];
            $mark  = ($price !== null && $price > 0) ? (float) $price : $entry;
            $un    = $this->netPnl($entry, $mark, $qty);
            $unrealTotal += $un;
            $positions[] = [
                'id'          => (int) $p['id'],
                'symbol'      => $sym,
                'template'    => $p['template'] ?? 'scalp',
                'profile'     => $p['profile'] ?? 'conservative',
                'mode'        => $p['mode'] ?? $mode,
                'entry'       => $entry,
                'qty'         => $qty,
                'mark'        => $mark,
                'stop_loss'   => (float) $p['stop_loss'],
                'take_profit' => $p['take_profit'] !== null ? (float) $p['take_profit'] : null,
                'protected'   => (($p['mode'] ?? $mode) === 'live') ? !empty($p['protect_id']) : true,
                'unrealized'  => round($un, 4),
                'pnlPct'      => $entry > 0 ? round(($mark - $entry) / $entry * 100, 2) : 0,
                'opened_at'   => $p['created_at'] ?? null,
            ];
        }
        $tmplList = [];
        foreach ($this->templates as $k => $t) $tmplList[] = ['key' => $k, 'name' => $t->name()];
        $profList = [];
        foreach (GtbProfiles::enabled() as $pk) $profList[] = ['key' => $pk, 'name' => GtbProfiles::config($pk)['name']];

        return [
            'ok'        => true,
            'mode'      => $mode,
            'action'    => $action,
            'positions' => $positions,
            'portfolio' => [
                'open'       => count($positions),
                'slots'      => $cap->slots($realized),
                'unrealized' => round($unrealTotal, 4),
                'realized'   => round($realized, 4),
            ],
            'capital'   => $cap->summary($realized),
            'templates' => $tmplList,
            'profiles'  => $profList,
        ];
    }

    /**
     * Teach the capital object about the wallet for full/ai modes (staked mode needs
     * nothing extra). Wallet = free USDT + value already deployed in open positions
     * (live), or the configured paper wallet (paper). Also caches free USDT so a live
     * buy can be capped to what's actually on hand.
     */
    private function configureCapital(GtbCapital $cap, BinanceClient $client, GtbTrade $trades, string $mode, float $realized, array $tickers = []): void
    {
        $this->liveFreeUsd = null;
        $this->walletUsd   = null;

        // Compute the wallet if full/ai sizing needs it OR a wallet floor is set.
        $floor = (float) (Env::get('GTB_WALLET_FLOOR', '0') ?? 0);
        if ($cap->mode() === 'staked' && $floor <= 0) return;

        $invested = 0.0;
        foreach ($trades->openPositions() as $p) {
            if (($p['mode'] ?? $mode) !== $mode) continue;
            $sym = $p['symbol'];
            $px  = isset($tickers[$sym]) ? (float) $tickers[$sym]['price'] : (float) ($client->price($sym) ?? $p['price']);
            $invested += $px * (float) $p['qty'];
        }

        if ($mode === 'live') {
            $free = 0.0;
            $acct = $client->account();
            if (!empty($acct['ok'])) {
                foreach (($acct['data']['balances'] ?? []) as $b) {
                    if (($b['asset'] ?? '') === 'USDT') $free = (float) ($b['free'] ?? 0);
                }
            }
            $this->liveFreeUsd = $free;
            $this->walletUsd   = $free + $invested;
        } else {
            $this->walletUsd = (float) (Env::get('GTB_PAPER_WALLET_USD', '35') ?? 35);
        }

        // Only full/ai use the wallet for SIZING; staked keeps the $7 discipline.
        if ($cap->mode() !== 'staked') {
            $cap->setWallet($this->walletUsd);
            if ($cap->mode() === 'ai') $cap->setAiPct($cap->autoAiPct($realized));
        }
    }

    /** Liquid USDT tickers keyed by symbol (leveraged tokens + stablecoins excluded). */
    private function liquidTickers(BinanceClient $client): array
    {
        $res = $client->allTickers24hr();
        if (empty($res['ok']) || !is_array($res['data'])) return [];
        $stable = ['USDC','BUSD','TUSD','FDUSD','DAI','USDP','USTC','EUR','GBP','AEUR','USD1'];
        $out = [];
        foreach ($res['data'] as $r) {
            $sym = $r['symbol'] ?? '';
            if (!str_ends_with($sym, 'USDT')) continue;
            $base = substr($sym, 0, -4);
            if ($base === '' || preg_match('/(UP|DOWN|BULL|BEAR)$/', $base) || in_array($base, $stable, true)) continue;
            if ((float) ($r['quoteVolume'] ?? 0) < 5000000.0) continue;
            $out[$sym] = [
                'symbol'    => $sym,
                'base'      => $base,
                'changePct' => round((float) ($r['priceChangePercent'] ?? 0), 2),
                'price'     => (float) ($r['lastPrice'] ?? 0),
                'high'      => (float) ($r['highPrice'] ?? 0),
                'low'       => (float) ($r['lowPrice'] ?? 0),
                'quoteVol'  => (int) ($r['quoteVolume'] ?? 0),
            ];
        }
        return $out;
    }

    /** Compact trade memory: recent outcomes + per-template win/loss (kept small to bound tokens). */
    private function buildMemory(GtbTrade $trades): array
    {
        $closed = $trades->recentClosed(8);
        $recent = [];
        $byTpl  = [];
        foreach ($closed as $c) {
            $pnl = (float) $c['realized_pnl'];
            $recent[] = ['symbol' => $c['symbol'], 'template' => $c['template'] ?? '?',
                         'pnl' => round($pnl, 4), 'result' => $pnl >= 0 ? 'win' : 'loss'];
            $t = $c['template'] ?? '?';
            $byTpl[$t] = $byTpl[$t] ?? ['wins' => 0, 'losses' => 0, 'pnl' => 0.0];
            $byTpl[$t][$pnl >= 0 ? 'wins' : 'losses']++;
            $byTpl[$t]['pnl'] = round($byTpl[$t]['pnl'] + $pnl, 4);
        }
        return ['recentTrades' => $recent, 'byTemplate' => $byTpl];
    }

    /**
     * Place the resting protective order on the exchange for a live long.
     * OCO (target + stop) when there's a take-profit; a stop-limit for trailing-only.
     * @return array{type:string,id:string}|array{error:string}
     */
    private function protectLive(BinanceClient $client, string $symbol, float $qty, array $st): array
    {
        $fee     = (float) (Env::get('GTB_FEE_RATE', '0.001') ?? 0.001);          // haircut so we don't oversell after buy fee
        $bufPct  = (float) (Env::get('GTB_STOP_LIMIT_BUFFER_PCT', '0.5') ?? 0.5); // stop-limit sits this % under the trigger
        $sellQty = $qty * (1.0 - max(0.0, $fee));
        $stop    = (float) ($st['stop_loss'] ?? 0);
        if ($stop <= 0 || $sellQty <= 0) return ['error' => 'invalid stop/qty'];
        $stopLimit = $stop * (1.0 - $bufPct / 100.0);

        if (!empty($st['take_profit'])) {
            $r = $client->placeOcoSell($symbol, $sellQty, (float) $st['take_profit'], $stop, $stopLimit);
            if (!empty($r['ok'])) return ['type' => 'oco', 'id' => (string) ($r['data']['orderListId'] ?? '')];
            return ['error' => $r['error'] ?? 'OCO rejected'];
        }
        $r = $client->placeStopLossSell($symbol, $sellQty, $stop, $stopLimit);
        if (!empty($r['ok'])) return ['type' => 'stop', 'id' => (string) ($r['data']['orderId'] ?? '')];
        return ['error' => $r['error'] ?? 'stop order rejected'];
    }

    /**
     * Reconcile a LIVE position against its exchange-side protective order:
     * close the DB row when the exchange filled it, ratchet the trailing stop
     * (cancel + replace higher), or re-protect / flatten if protection is missing.
     * @return float realized P&L booked this cycle (0 if still open)
     */
    private function reconcileLive(BinanceClient $client, GtbTrade $trades, GtbThought $thoughts, array $pos, float $price): float
    {
        $sym  = $pos['symbol'];
        $id   = (int) $pos['id'];
        $type = $pos['protect_type'] ?? null;
        $pid  = $pos['protect_id'] ?? null;
        $entry = (float) $pos['price'];
        $qty   = (float) $pos['qty'];

        // No protection on record (legacy row or an aborted place) — try to protect now, else flatten.
        if (!$type || !$pid) {
            $prot = $this->protectLive($client, $sym, $qty, ['stop_loss' => $pos['stop_loss'], 'take_profit' => $pos['take_profit']]);
            if (!empty($prot['error'])) {
                $client->marketSell($sym, $this->floorToStep($client, $sym, $qty * 0.999));
                $pnl = $this->netPnl($entry, $price, $qty);
                $trades->closeTrade($id, $price, $pnl);
                $thoughts->add("$sym had no exchange stop and re-protect failed — flattened to stay safe.",
                    'system', 'error', $sym, null, ['mode' => 'live', 'pnl' => round($pnl, 6)]);
                return $pnl;
            }
            $trades->setProtection($id, $prot['type'], $prot['id']);
            return 0.0;
        }

        // OCO (target + stop).
        if ($type === 'oco') {
            $s = $client->ocoStatus((string) $pid);
            if (empty($s['ok'])) return 0.0; // transient; retry next cycle
            if (($s['data']['listOrderStatus'] ?? '') !== 'ALL_DONE') {
                // Still resting — for a profit-locking template (trail_pct set) ratchet the OCO stop
                // up onto the PLUS side as new highs print; cancel+replace so no orders overlap.
                return $this->ratchetOco($client, $trades, $thoughts, $pos, $price, (string) $pid);
            }
            [$exit, $fq] = $this->ocoFill($client, $sym, $s['data']);
            if ($exit > 0) {
                $pnl = $this->netPnl($entry, $exit, $fq);
                $trades->closeTrade($id, $exit, $pnl);
                $trades->setProtection($id, null, null);
                $thoughts->add(sprintf('Exchange closed %s at $%s (OCO) · P&L %s$%.4f.',
                    $sym, $this->fmt($exit), $pnl >= 0 ? '+' : '-', abs($pnl)),
                    'bot', 'trade', $sym, 'OCO', ['mode' => 'live', 'pnl' => round($pnl, 6)]);
                return $pnl;
            }
            // ALL_DONE with no fill = both legs canceled externally -> unprotected. Flatten.
            $client->marketSell($sym, $this->floorToStep($client, $sym, $qty * 0.999));
            $pnl = $this->netPnl($entry, $price, $qty);
            $trades->closeTrade($id, $price, $pnl);
            $trades->setProtection($id, null, null);
            $thoughts->add("$sym OCO was canceled on the exchange — flattened to stay safe.",
                'system', 'error', $sym, null, ['mode' => 'live', 'pnl' => round($pnl, 6)]);
            return $pnl;
        }

        // Single stop-limit (trailing template): fill -> close; alive -> maybe ratchet up.
        $o = $client->orderStatus($sym, (string) $pid);
        if (empty($o['ok'])) return 0.0;
        $status = $o['data']['status'] ?? '';
        if ($status === 'FILLED') {
            $exec = (float) ($o['data']['executedQty'] ?? 0);
            $cum  = (float) ($o['data']['cummulativeQuoteQty'] ?? 0);
            $exit = $exec > 0 ? $cum / $exec : $price;
            $pnl  = $this->netPnl($entry, $exit, $exec > 0 ? $exec : $qty);
            $trades->closeTrade($id, $exit, $pnl);
            $trades->setProtection($id, null, null);
            $thoughts->add(sprintf('Exchange stop hit %s at $%s · P&L %s$%.4f.',
                $sym, $this->fmt($exit), $pnl >= 0 ? '+' : '-', abs($pnl)),
                'bot', 'trade', $sym, 'STOP', ['mode' => 'live', 'pnl' => round($pnl, 6)]);
            return $pnl;
        }
        if (in_array($status, ['CANCELED', 'EXPIRED', 'REJECTED', 'PENDING_CANCEL'], true)) {
            $prot = $this->protectLive($client, $sym, $qty, ['stop_loss' => $pos['stop_loss']]);
            if (!empty($prot['error'])) {
                $client->marketSell($sym, $this->floorToStep($client, $sym, $qty * 0.999));
                $pnl = $this->netPnl($entry, $price, $qty);
                $trades->closeTrade($id, $price, $pnl);
                $trades->setProtection($id, null, null);
                $thoughts->add("$sym stop vanished and re-protect failed — flattened.",
                    'system', 'error', $sym, null, ['mode' => 'live', 'pnl' => round($pnl, 6)]);
                return $pnl;
            }
            $trades->setProtection($id, $prot['type'], $prot['id']);
            return 0.0;
        }
        // Still resting (NEW): ratchet the trailing stop upward if price made new highs.
        $trailPct = (float) ($pos['trail_pct'] ?: 0);
        if ($trailPct > 0) {
            $prevPeak = (float) ($pos['peak_price'] ?: $entry);
            $peak     = max($prevPeak, $price);
            $newStop  = $peak * (1.0 - $trailPct / 100.0);
            $curStop  = (float) $pos['stop_loss'];
            if ($newStop > $curStop * 1.001) {           // meaningful ratchet — cancel + replace higher
                $client->cancelOrder($sym, (string) $pid);
                $prot = $this->protectLive($client, $sym, $qty, ['stop_loss' => $newStop]);
                if (!empty($prot['error'])) {            // couldn't re-place — flatten rather than run naked
                    $client->marketSell($sym, $this->floorToStep($client, $sym, $qty * 0.999));
                    $pnl = $this->netPnl($entry, $price, $qty);
                    $trades->closeTrade($id, $price, $pnl);
                    $trades->setProtection($id, null, null);
                    $thoughts->add("$sym trailing replace failed — flattened to stay safe.",
                        'system', 'error', $sym, null, ['mode' => 'live', 'pnl' => round($pnl, 6)]);
                    return $pnl;
                }
                $trades->updateStop($id, $newStop, $peak);
                $trades->setProtection($id, $prot['type'], $prot['id']);
            } elseif ($peak > $prevPeak) {
                $trades->updateStop($id, $curStop, $peak);
            }
        }
        return 0.0;
    }

    /**
     * Profit-locking OCO ratchet for a resting live OCO (Gainer Hunter). Once price is up ~arm%,
     * raise the stop onto the plus side (lock ≥ lock%) and trail it under the peak, keeping the TP
     * ceiling. Cancels the old OCO before re-placing so the book never carries overlapping orders.
     * @return float realized P&L (0 unless a re-protect failure forced a flatten)
     */
    private function ratchetOco(BinanceClient $client, GtbTrade $trades, GtbThought $thoughts, array $pos, float $price, string $pid): float
    {
        $trailPct = (float) ($pos['trail_pct'] ?: 0);
        if ($trailPct <= 0) return 0.0;   // fixed-OCO template — leave it be
        $sym   = $pos['symbol'];
        $id    = (int) $pos['id'];
        $entry = (float) $pos['price'];
        $qty   = (float) $pos['qty'];
        $tp    = (float) ($pos['take_profit'] ?: 0);
        if ($tp <= 0 || $entry <= 0) return 0.0;

        $lockPct = (float) (Env::get('GTB_GAINER_LOCK_PCT', '1.5') ?? 1.5);
        $armPct  = (float) (Env::get('GTB_GAINER_ARM_PCT', '2.0') ?? 2.0);

        $prevPeak = (float) ($pos['peak_price'] ?: $entry);
        $peak     = max($prevPeak, $price);
        if ($price < $entry * (1 + $armPct / 100.0)) {   // not armed yet — just track the peak
            if ($peak > $prevPeak) $trades->updateStop($id, (float) $pos['stop_loss'], $peak);
            return 0.0;
        }

        $newStop = max((float) $pos['stop_loss'], $entry * (1 + $lockPct / 100.0), $peak * (1 - $trailPct / 100.0));
        $newStop = min($newStop, $price * 0.999);   // must rest below market
        $curStop = (float) $pos['stop_loss'];
        if ($newStop <= $curStop * 1.001) {         // nothing meaningful to raise
            if ($peak > $prevPeak) $trades->updateStop($id, $curStop, $peak);
            return 0.0;
        }

        $client->cancelOco($sym, $pid);   // cancel BEFORE re-placing — no overlapping orders
        $prot = $this->protectLive($client, $sym, $qty, ['stop_loss' => $newStop, 'take_profit' => $tp]);
        if (!empty($prot['error'])) {
            $client->marketSell($sym, $this->floorToStep($client, $sym, $qty * 0.999));
            $pnl = $this->netPnl($entry, $price, $qty);
            $trades->closeTrade($id, $price, $pnl);
            $trades->setProtection($id, null, null);
            $thoughts->add("$sym OCO re-lock failed — flattened to stay safe.",
                'system', 'error', $sym, null, ['mode' => 'live', 'pnl' => round($pnl, 6)]);
            return $pnl;
        }
        $trades->updateStop($id, $newStop, $peak);
        $trades->setProtection($id, $prot['type'], $prot['id']);
        $thoughts->add(sprintf('%s stop locked to $%s (≥ +%.2f%%) · TP $%s — trailing the OCO up.',
            $sym, $this->fmt($newStop), ($newStop / $entry - 1) * 100, $this->fmt($tp)),
            'bot', 'trade', $sym, 'LOCK', ['mode' => 'live']);
        return 0.0;
    }

    /** Force-close a position now (time-box / session end), paper or live. Returns realized P&L. */
    private function forceClose(BinanceClient $client, GtbTrade $trades, GtbThought $thoughts, array $pos, float $price, string $mode, string $reason): float
    {
        $sym = $pos['symbol'];
        $id  = (int) $pos['id'];
        if (($pos['mode'] ?? $mode) === 'live') {
            // Cancel the resting protective order before we market-sell.
            $type = $pos['protect_type'] ?? null; $pid = $pos['protect_id'] ?? null;
            if ($type === 'oco' && $pid)  $client->cancelOco($sym, (string) $pid);
            elseif ($type === 'stop' && $pid) $client->cancelOrder($sym, (string) $pid);
            $client->marketSell($sym, $this->floorToStep($client, $sym, (float) $pos['qty'] * 0.999));
        }
        $pnl = $this->netPnl((float) $pos["price"], $price, (float) $pos["qty"]);
        $trades->closeTrade($id, $price, $pnl);
        $trades->setProtection($id, null, null);
        $thoughts->add(sprintf('Closed %s at $%s (%s) [%s] · P&L %s$%.4f.',
            $sym, $this->fmt($price), $reason, $pos['template'] ?? 'scalp',
            $pnl >= 0 ? '+' : '-', abs($pnl)),
            'bot', 'trade', $sym, $reason, ['mode' => ($pos['mode'] ?? $mode), 'pnl' => round($pnl, 6)]);
        return $pnl;
    }

    /**
     * Realized P&L NET of Binance fees: gross move minus the taker fee on BOTH the
     * buy and the sell (~0.1% each). So a "win" is only booked once it actually
     * covers the round-trip charge — no phantom profit that fees would eat.
     */
    private function netPnl(float $entry, float $exit, float $qty): float
    {
        $fee = (float) (Env::get('GTB_FEE_RATE', '0.001') ?? 0.001);
        return ($exit - $entry) * $qty - $fee * $qty * ($entry + $exit);
    }

    /** Whole minutes a position has been open (from its created_at), or null. */
    private function ageMinutes(?string $createdAt): ?int
    {
        if (!$createdAt) return null;
        $ts = strtotime($createdAt);
        return $ts ? (int) floor((time() - $ts) / 60) : null;
    }

    /** Find the filled leg of a completed OCO and return [avgPrice, filledQty]. */
    private function ocoFill(BinanceClient $client, string $symbol, array $ocoData): array
    {
        foreach (($ocoData['orders'] ?? []) as $o) {
            $oid = (string) ($o['orderId'] ?? '');
            if ($oid === '') continue;
            $r = $client->orderStatus($symbol, $oid);
            if (empty($r['ok'])) continue;
            if (($r['data']['status'] ?? '') === 'FILLED') {
                $exec = (float) ($r['data']['executedQty'] ?? 0);
                $cum  = (float) ($r['data']['cummulativeQuoteQty'] ?? 0);
                if ($exec > 0) return [$cum / $exec, $exec];
            }
        }
        return [0.0, 0.0];
    }

    /**
     * Optional global override for a "small, frequent profit" style: if a fixed
     * take-profit % and/or stop-loss % are set, use them for EVERY entry (a fixed
     * small target instead of the template's larger one). Replaces trailing with a
     * fixed target so the small gain is actually banked.
     */
    private function applyTargetOverride(array $st, float $entry): array
    {
        $tp = (float) (Env::get('GTB_TP_OVERRIDE_PCT', '0') ?? 0);
        $sl = (float) (Env::get('GTB_SL_OVERRIDE_PCT', '0') ?? 0);
        if ($sl > 0) $st['stop_loss'] = $entry * (1 - $sl / 100.0);
        if ($tp > 0) { $st['take_profit'] = $entry * (1 + $tp / 100.0); $st['trail_pct'] = null; }
        return $st;
    }

    /** Scale a template's stop/target by the profile's risk multipliers (distance from entry). */
    private function applyRisk(array $st, float $entry, array $cfg): array
    {
        $slMult = (float) ($cfg['slMult'] ?? 1.0);
        $tpMult = (float) ($cfg['tpMult'] ?? 1.0);
        if (!empty($st['stop_loss'])) {
            $st['stop_loss'] = $entry - ($entry - (float) $st['stop_loss']) * $slMult;
        }
        if (!empty($st['take_profit'])) {
            $st['take_profit'] = $entry + ((float) $st['take_profit'] - $entry) * $tpMult;
        }
        if (!empty($st['trail_pct'])) {
            $st['trail_pct'] = (float) $st['trail_pct'] * $slMult;
        }
        return $st;
    }

    private function floorToStep(BinanceClient $client, string $symbol, float $qty): float
    {
        $step = $client->lotStep($symbol);
        if (!$step || $step <= 0) return $qty;
        return floor($qty / $step) * $step;
    }

    private function fmt(float $p): string
    {
        if ($p >= 1000) return number_format($p, 2);
        if ($p >= 1) return number_format($p, 4);
        return rtrim(rtrim(sprintf('%.8f', $p), '0'), '.');
    }
}
