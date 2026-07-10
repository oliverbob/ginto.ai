<?php
namespace Ginto\Services;

use Ginto\Support\Env;
use Ginto\Models\GtbSettings;
use Ginto\Models\GtbTrade;
use Ginto\Models\GtbThought;
use Ginto\Services\Strategies\GtbTemplate;
use Ginto\Services\Strategies\ScalpMomentum;
use Ginto\Services\Strategies\Breakout;
use Ginto\Services\Strategies\TrendTrailing;

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

    public function __construct()
    {
        $all = ['scalp' => ScalpMomentum::class, 'breakout' => Breakout::class, 'trend' => TrendTrailing::class];
        $enabled = array_filter(array_map('trim', explode(',', (string) (Env::get('GTB_TEMPLATES', 'scalp,breakout,trend') ?? ''))));
        if (!$enabled) $enabled = array_keys($all);
        foreach ($enabled as $k) {
            if (isset($all[$k])) $this->templates[$k] = new $all[$k]();
        }
        if (!$this->templates) $this->templates['scalp'] = new ScalpMomentum();
    }

    public function step(bool $armLive = false): array
    {
        $settings = new GtbSettings();
        $mode     = $settings->isTestnet() ? 'paper' : 'live';
        $client   = new BinanceClient();
        $trades   = new GtbTrade();
        $thoughts = new GtbThought();
        $cap      = new GtbCapital();
        $realized = $trades->totalRealizedPnl();
        $tickers  = $this->liquidTickers($client);
        $priceOf  = function (string $sym) use ($tickers, $client) {
            return isset($tickers[$sym]) ? $tickers[$sym]['price'] : $client->price($sym);
        };

        // ---- 1) manage every open position ----
        foreach ($trades->openPositions() as $pos) {
            $tpl = $this->templates[$pos['template']] ?? ($this->templates['scalp'] ?? new ScalpMomentum());
            $price = $priceOf($pos['symbol']);
            if ($price === null || $price <= 0) continue;
            $m = $tpl->manage($pos, (float) $price);
            if (!$m) continue;
            if (isset($m['close'])) {
                if ($mode === 'live') {
                    $q = $this->floorToStep($client, $pos['symbol'], (float) $pos['qty']);
                    $client->marketSell($pos['symbol'], $q);
                }
                $pnl = ((float) $price - (float) $pos['price']) * (float) $pos['qty'];
                $trades->closeTrade((int) $pos['id'], (float) $price, $pnl);
                $realized += $pnl;
                $thoughts->add(sprintf('Closed %s at $%s (%s) [%s] · P&L %s$%.4f.',
                    $pos['symbol'], $this->fmt((float) $price), $m['close'], $pos['template'] ?? 'scalp',
                    $pnl >= 0 ? '+' : '-', abs($pnl)
                ), 'bot', 'trade', $pos['symbol'], $m['close'], ['mode' => $mode, 'pnl' => round($pnl, 6), 'template' => $pos['template'] ?? null]);
            } elseif (isset($m['trail'])) {
                $trades->updateStop((int) $pos['id'], (float) $m['trail']['stop_loss'], (float) $m['trail']['peak']);
            }
        }

        // ---- 2) open ONE new position if a slot is free ----
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
                $size = $cap->perTradeSize($realized);
                // template order: least-used first, to diversify
                $counts = array_count_values(array_map(static fn($p) => $p['template'] ?? 'scalp', $open));
                $order  = array_keys($this->templates);
                usort($order, static fn($a, $b) => ($counts[$a] ?? 0) <=> ($counts[$b] ?? 0));

                foreach ($order as $key) {
                    $tpl  = $this->templates[$key];
                    $cand = $tpl->entryCandidate(array_values($tickers), $held);
                    if (!$cand) continue;

                    $ctx = ['env' => $mode, 'template' => $tpl->name(), 'templateRule' => $tpl->description(),
                            'capital' => $cap->summary($realized), 'perTradeSize' => round($size, 2)];
                    $res = $brain->decide($cand, $ctx, 'decision');
                    if (empty($res['ok'])) {
                        $thoughts->add('Decision failed: ' . ($res['error'] ?? ''), 'system', 'error');
                        $action = 'brain error: ' . ($res['error'] ?? '');
                        break;
                    }
                    $meta = array_merge(['model' => $res['model'] ?? '', 'template' => $key], $res['usage'] ?? []);
                    $thoughts->add($res['text'], 'claude', 'decision', $cand['symbol'], $res['decision'] ?? null, $meta);

                    if (($res['decision'] ?? '') !== 'BUY') {
                        $action = '[' . $tpl->name() . '] skipped ' . $cand['symbol'];
                        break;
                    }
                    $price = $priceOf($cand['symbol']);
                    if ($price === null || $price <= 0) { $action = 'price unavailable at entry'; break; }
                    $fill = (float) $price; $qty = $size / $fill; $oid = null;
                    if ($mode === 'live') {
                        if (!$armLive) { $action = '[' . $tpl->name() . '] BUY ' . $cand['symbol'] . ' — live not armed'; break; }
                        $ord = $client->marketBuyQuote($cand['symbol'], $size);
                        if (empty($ord['ok'])) {
                            $thoughts->add('Live order failed: ' . ($ord['error'] ?? ''), 'system', 'error');
                            $action = 'live order failed: ' . ($ord['error'] ?? ''); break;
                        }
                        $d = $ord['data']; $ex = (float) ($d['executedQty'] ?? 0); $cum = (float) ($d['cummulativeQuoteQty'] ?? 0);
                        if ($ex > 0) { $qty = $ex; $fill = $cum / $ex; }
                        $oid = $d['orderId'] ?? null;
                    }
                    $st = $tpl->stops($fill);
                    $trades->openTrade([
                        'symbol' => $cand['symbol'], 'mode' => $mode, 'template' => $key, 'price' => $fill, 'qty' => $qty,
                        'quote_qty' => $size, 'stop_loss' => $st['stop_loss'], 'take_profit' => $st['take_profit'] ?? null,
                        'peak_price' => $fill, 'trail_pct' => $st['trail_pct'] ?? null, 'binance_order_id' => $oid,
                    ]);
                    $tpTxt = (!empty($st['take_profit'])) ? ' / TP $' . $this->fmt($st['take_profit']) : ' · trailing ' . ($st['trail_pct'] ?? '') . '%';
                    $thoughts->add(sprintf('Entered %s %s [%s] — $%.2f at $%s · SL $%s%s.',
                        $mode === 'paper' ? '(paper)' : '(LIVE)', $cand['symbol'], $tpl->name(), $size,
                        $this->fmt($fill), $this->fmt($st['stop_loss']), $tpTxt
                    ), 'bot', 'trade', $cand['symbol'], 'BUY', ['mode' => $mode, 'template' => $key]);
                    $action = 'entered ' . $cand['symbol'] . ' [' . $tpl->name() . ']';
                    break;
                }
            }
        }

        return $this->openPositionsState($client, $trades, $cap, $realized, $mode, $action, $tickers);
    }

    /** Live snapshot of all open positions + portfolio totals (used by the monitoring grid). */
    public function openPositionsState(
        ?BinanceClient $client = null, ?GtbTrade $trades = null, ?GtbCapital $cap = null,
        ?float $realized = null, ?string $mode = null, string $action = '', array $tickers = []
    ): array {
        $client = $client ?? new BinanceClient();
        $trades = $trades ?? new GtbTrade();
        $cap    = $cap ?? new GtbCapital();
        if ($realized === null) $realized = $trades->totalRealizedPnl();
        if ($mode === null)     $mode = (new GtbSettings())->isTestnet() ? 'paper' : 'live';

        $positions = [];
        $unrealTotal = 0.0;
        foreach ($trades->openPositions() as $p) {
            $sym   = $p['symbol'];
            $price = isset($tickers[$sym]) ? $tickers[$sym]['price'] : $client->price($sym);
            $entry = (float) $p['price'];
            $qty   = (float) $p['qty'];
            $mark  = ($price !== null && $price > 0) ? (float) $price : $entry;
            $un    = ($mark - $entry) * $qty;
            $unrealTotal += $un;
            $positions[] = [
                'id'          => (int) $p['id'],
                'symbol'      => $sym,
                'template'    => $p['template'] ?? 'scalp',
                'mode'        => $p['mode'] ?? $mode,
                'entry'       => $entry,
                'qty'         => $qty,
                'mark'        => $mark,
                'stop_loss'   => (float) $p['stop_loss'],
                'take_profit' => $p['take_profit'] !== null ? (float) $p['take_profit'] : null,
                'unrealized'  => round($un, 4),
                'pnlPct'      => $entry > 0 ? round(($mark - $entry) / $entry * 100, 2) : 0,
                'opened_at'   => $p['created_at'] ?? null,
            ];
        }
        $tmplList = [];
        foreach ($this->templates as $k => $t) $tmplList[] = ['key' => $k, 'name' => $t->name()];

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
        ];
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
