<?php
namespace Ginto\Services;

use Ginto\Support\Env;
use Ginto\Models\GtbSettings;
use Ginto\Models\GtbTrade;
use Ginto\Models\GtbThought;

/**
 * Momentum strategy engine. One step() = one decision cycle:
 *   - if a position is open, manage it (deterministic stop-loss / take-profit exit),
 *   - else deterministically pre-filter for the best momentum candidate, ask the AI
 *     brain to confirm the entry, and open a position sized by the capital rules.
 *
 * Execution follows the active environment (the testnet toggle):
 *   - testnet  → PAPER: fills simulated at live mainnet price (zero risk),
 *   - mainnet  → LIVE:  real Binance orders (gated behind an explicit "arm" flag).
 *
 * Exits are deterministic (no AI call). AI is only consulted to confirm an entry.
 */
class GtbStrategy
{
    private float $slPct;
    private float $tpPct;
    private float $minGainer;
    private float $maxGainer;

    public function __construct()
    {
        $this->slPct     = ((float) (Env::get('GTB_STOP_LOSS_PCT', '1.5') ?? 1.5)) / 100.0;
        $this->tpPct     = ((float) (Env::get('GTB_TAKE_PROFIT_PCT', '2.5') ?? 2.5)) / 100.0;
        $this->minGainer = (float) (Env::get('GTB_MIN_GAINER_PCT', '3') ?? 3);
        $this->maxGainer = (float) (Env::get('GTB_MAX_GAINER_PCT', '40') ?? 40);
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

        // ---- 1) manage an open position (deterministic, no AI) ----
        $open = $trades->openPosition();
        if ($open) {
            $price = $client->price($open['symbol']);
            if ($price === null) {
                return $this->state($mode, $open, $cap, $realized, 'price unavailable — holding');
            }
            $entry = (float) $open['price'];
            $qty   = (float) $open['qty'];
            $sl    = (float) $open['stop_loss'];
            $tp    = (float) $open['take_profit'];

            if ($price <= $sl || $price >= $tp) {
                $reason = $price <= $sl ? 'STOP-LOSS' : 'TAKE-PROFIT';
                if ($mode === 'live') {
                    $sellQty = $this->floorToStep($client, $open['symbol'], $qty);
                    $client->marketSell($open['symbol'], $sellQty);
                }
                $pnl = ($price - $entry) * $qty;
                $trades->closeTrade((int) $open['id'], $price, $pnl);
                $thoughts->add(sprintf(
                    'Closed %s at $%s (%s). Entry $%s → exit $%s · P&L %s$%.4f.',
                    $open['symbol'], $this->fmt($price), $reason, $this->fmt($entry), $this->fmt($price),
                    $pnl >= 0 ? '+' : '-', abs($pnl)
                ), 'bot', 'trade', $open['symbol'], $reason, ['mode' => $mode, 'pnl' => round($pnl, 6)]);
                return $this->state($mode, null, $cap, $realized + $pnl, "exited: {$reason}");
            }
            return $this->state($mode, $open, $cap, $realized, 'holding position', $price);
        }

        // ---- 2) no position: consider an entry ----
        if (!$cap->canTrade($realized)) {
            return $this->state($mode, null, $cap, $realized, 'capital locked (below min notional)');
        }

        $cand = $this->preFilter($client);
        if (!$cand) {
            return $this->state($mode, null, $cap, $realized, 'no momentum candidate right now');
        }

        $brain = new GtbBrain();
        if (!$brain->isConfigured()) {
            return $this->state($mode, null, $cap, $realized, 'no AI key — cannot decide');
        }

        $size = $cap->perTradeSize($realized);
        $ctx  = [
            'env'          => $mode,
            'capital'      => $cap->summary($realized),
            'perTradeSize' => round($size, 2),
            'stopLossPct'  => $this->slPct * 100,
            'takeProfitPct'=> $this->tpPct * 100,
        ];
        $res = $brain->decide($cand, $ctx, 'decision');
        if (empty($res['ok'])) {
            $thoughts->add('Decision failed: ' . ($res['error'] ?? 'unknown'), 'system', 'error');
            return $this->state($mode, null, $cap, $realized, 'brain error: ' . ($res['error'] ?? ''));
        }
        $meta = array_merge(['model' => $res['model'] ?? ''], $res['usage'] ?? []);
        $thoughts->add($res['text'], 'claude', 'decision', $cand['symbol'], $res['decision'] ?? null, $meta);

        if (($res['decision'] ?? '') !== 'BUY') {
            return $this->state($mode, null, $cap, $realized, 'skipped by AI');
        }

        // ---- 3) enter ----
        $price = $client->price($cand['symbol']);
        if ($price === null) {
            return $this->state($mode, null, $cap, $realized, 'price unavailable at entry');
        }
        $fillPrice = $price;
        $qty = $size / $price;

        if ($mode === 'live') {
            if (!$armLive) {
                return $this->state($mode, null, $cap, $realized, 'BUY signal — live trading not armed');
            }
            $ord = $client->marketBuyQuote($cand['symbol'], $size);
            if (empty($ord['ok'])) {
                $thoughts->add('Live order failed: ' . ($ord['error'] ?? ''), 'system', 'error');
                return $this->state($mode, null, $cap, $realized, 'live order failed: ' . ($ord['error'] ?? ''));
            }
            $d = $ord['data'];
            $exec = (float) ($d['executedQty'] ?? 0);
            $cum  = (float) ($d['cummulativeQuoteQty'] ?? 0);
            if ($exec > 0) { $qty = $exec; $fillPrice = $cum / $exec; }
            $oid = $d['orderId'] ?? null;
        } else {
            $oid = null;
        }

        $sl = $fillPrice * (1 - $this->slPct);
        $tp = $fillPrice * (1 + $this->tpPct);
        $trades->openTrade([
            'symbol' => $cand['symbol'], 'mode' => $mode, 'price' => $fillPrice, 'qty' => $qty,
            'quote_qty' => $size, 'stop_loss' => $sl, 'take_profit' => $tp, 'binance_order_id' => $oid,
        ]);
        $thoughts->add(sprintf(
            'Entered %s %s — $%.2f at $%s · SL $%s / TP $%s.',
            $mode === 'paper' ? '(paper)' : '(LIVE)', $cand['symbol'], $size,
            $this->fmt($fillPrice), $this->fmt($sl), $this->fmt($tp)
        ), 'bot', 'trade', $cand['symbol'], 'BUY', ['mode' => $mode]);

        return $this->state($mode, $trades->openPosition(), $cap, $realized, "entered {$cand['symbol']}", $fillPrice);
    }

    /** Deterministic momentum pre-filter: strongest 24h gainer in the sweet-spot band. */
    private function preFilter(BinanceClient $client): ?array
    {
        $res = $client->allTickers24hr();
        if (empty($res['ok']) || !is_array($res['data'])) return null;
        $stable = ['USDC','BUSD','TUSD','FDUSD','DAI','USDP','USTC','EUR','GBP','AEUR','USD1'];
        $best = null;
        foreach ($res['data'] as $r) {
            $sym = $r['symbol'] ?? '';
            if (!str_ends_with($sym, 'USDT')) continue;
            $base = substr($sym, 0, -4);
            if ($base === '' || preg_match('/(UP|DOWN|BULL|BEAR)$/', $base) || in_array($base, $stable, true)) continue;
            if ((float) ($r['quoteVolume'] ?? 0) < 5000000.0) continue;
            $chg = (float) ($r['priceChangePercent'] ?? 0);
            if ($chg < $this->minGainer || $chg > $this->maxGainer) continue; // positive but not parabolic
            if ($best === null || $chg > $best['changePct']) {
                $best = [
                    'symbol'    => $sym,
                    'base'      => $base,
                    'changePct' => round($chg, 2),
                    'price'     => (float) ($r['lastPrice'] ?? 0),
                    'quoteVol'  => (int) ($r['quoteVolume'] ?? 0),
                    'high'      => (float) ($r['highPrice'] ?? 0),
                    'low'       => (float) ($r['lowPrice'] ?? 0),
                ];
            }
        }
        return $best;
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

    private function state(string $mode, ?array $open, GtbCapital $cap, float $realized, string $action, ?float $mark = null): array
    {
        $pos = null;
        if ($open) {
            $entry = (float) $open['price'];
            $qty   = (float) $open['qty'];
            $mark  = $mark ?? $entry;
            $pos = [
                'symbol'      => $open['symbol'],
                'mode'        => $open['mode'] ?? $mode,
                'entry'       => $entry,
                'qty'         => $qty,
                'mark'        => $mark,
                'stop_loss'   => (float) $open['stop_loss'],
                'take_profit' => (float) $open['take_profit'],
                'unrealized'  => round(($mark - $entry) * $qty, 4),
            ];
        }
        return [
            'ok'       => true,
            'mode'     => $mode,
            'action'   => $action,
            'position' => $pos,
            'capital'  => $cap->summary($realized),
        ];
    }
}
