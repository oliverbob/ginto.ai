<?php
namespace Ginto\Services;

use Ginto\Models\GtbSettings;

/**
 * Minimal Binance Spot REST client (V1).
 *
 * - Market data (prices, klines) always uses the public MAINNET endpoint so the
 *   charts are real and rich regardless of the testnet toggle. No key required.
 * - Signed calls (account now, orders later) use the CONFIGURED endpoint:
 *   testnet.binance.vision when testnet is on, else api.binance.com.
 *
 * Every method returns ['ok' => bool, 'data' => mixed, 'error' => string, 'code' => int].
 */
class BinanceClient
{
    private string $marketBase = 'https://api.binance.com';
    private string $accountBase;
    private string $apiKey;
    private string $apiSecret;
    private bool $testnet;

    public function __construct(?GtbSettings $settings = null)
    {
        $settings          = $settings ?? new GtbSettings();
        $this->apiKey      = $settings->getApiKey();
        $this->apiSecret   = $settings->getApiSecret();
        $this->testnet     = $settings->isTestnet();
        $this->accountBase = $this->testnet ? 'https://testnet.binance.vision' : 'https://api.binance.com';
    }

    public function isConfigured(): bool { return $this->apiKey !== '' && $this->apiSecret !== ''; }
    public function accountEndpoint(): string { return $this->accountBase; }
    public function isTestnet(): bool { return $this->testnet; }

    /** 24h ticker for the given symbols in a single request. */
    public function ticker24hr(array $symbols): array
    {
        if (empty($symbols)) {
            return ['ok' => true, 'data' => [], 'code' => 200];
        }
        return $this->httpGet($this->marketBase . '/api/v3/ticker/24hr', [
            'symbols' => json_encode(array_values($symbols)),
        ]);
    }

    /** Hourly close prices for several symbols in parallel. Returns [symbol => [float,...]]. */
    public function klinesMulti(array $symbols, string $interval = '1h', int $limit = 24): array
    {
        $out = [];
        if (empty($symbols)) return $out;

        $mh = curl_multi_init();
        $handles = [];
        foreach ($symbols as $s) {
            $url = $this->marketBase . '/api/v3/klines?symbol=' . urlencode($s)
                 . '&interval=' . urlencode($interval) . '&limit=' . (int)$limit;
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_CONNECTTIMEOUT => 4,
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[$s] = $ch;
        }
        $running = null;
        do {
            curl_multi_exec($mh, $running);
            if ($running) curl_multi_select($mh, 1.0);
        } while ($running);

        foreach ($handles as $s => $ch) {
            $body = curl_multi_getcontent($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $closes = [];
            if ($code === 200) {
                $rows = json_decode($body, true);
                if (is_array($rows)) {
                    foreach ($rows as $r) {
                        if (isset($r[4])) $closes[] = (float) $r[4];  // kline close
                    }
                }
            }
            $out[$s] = $closes;
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);
        return $out;
    }

    /** OHLC candles for one symbol (for the candlestick chart). Public mainnet data. */
    public function klines(string $symbol, string $interval = '1h', int $limit = 300): array
    {
        $res = $this->httpGet($this->marketBase . '/api/v3/klines', [
            'symbol'   => strtoupper($symbol),
            'interval' => $interval,
            'limit'    => $limit,
        ]);
        if (empty($res['ok'])) return $res;
        $candles = [];
        foreach ($res['data'] as $r) {
            // [ openTime(ms), open, high, low, close, volume, closeTime, ... ]
            $candles[] = [
                'time'  => (int) floor(((float) $r[0]) / 1000),  // seconds, for lightweight-charts
                'open'  => (float) $r[1],
                'high'  => (float) $r[2],
                'low'   => (float) $r[3],
                'close' => (float) $r[4],
                'volume'=> (float) $r[5],
            ];
        }
        return ['ok' => true, 'data' => $candles];
    }

    /** 24h ticker for ALL symbols in one request (for hot/gainers/losers). Public mainnet. */
    public function allTickers24hr(): array
    {
        return $this->httpGet($this->marketBase . '/api/v3/ticker/24hr');
    }

    /** All symbol prices as [SYMBOL => price] for portfolio valuation. Public mainnet. */
    public function allPrices(): array
    {
        $res = $this->httpGet($this->marketBase . '/api/v3/ticker/price');
        if (empty($res['ok'])) return $res;
        $map = [];
        foreach ($res['data'] as $row) {
            if (isset($row['symbol'], $row['price'])) $map[$row['symbol']] = (float) $row['price'];
        }
        return ['ok' => true, 'data' => $map];
    }

    /** Last price for one symbol (public mainnet). */
    public function price(string $symbol): ?float
    {
        $r = $this->httpGet($this->marketBase . '/api/v3/ticker/price', ['symbol' => strtoupper($symbol)]);
        return (!empty($r['ok']) && isset($r['data']['price'])) ? (float) $r['data']['price'] : null;
    }

    /** @var array<string,array{step:float,tick:float,minNotional:float}> per-instance filter cache */
    private array $filterCache = [];

    /** LOT_SIZE step + PRICE_FILTER tick + min notional for a symbol. Cached per instance. */
    public function exchangeFilters(string $symbol): array
    {
        $symbol = strtoupper($symbol);
        if (isset($this->filterCache[$symbol])) return $this->filterCache[$symbol];
        $out = ['step' => 0.0, 'tick' => 0.0, 'minNotional' => 0.0];
        $r = $this->httpGet($this->accountBase . '/api/v3/exchangeInfo', ['symbol' => $symbol]);
        if (!empty($r['ok'])) {
            foreach (($r['data']['symbols'][0]['filters'] ?? []) as $f) {
                switch ($f['filterType'] ?? '') {
                    case 'LOT_SIZE':    $out['step'] = (float) ($f['stepSize'] ?? 0); break;
                    case 'PRICE_FILTER':$out['tick'] = (float) ($f['tickSize'] ?? 0); break;
                    case 'NOTIONAL':    $out['minNotional'] = (float) ($f['minNotional'] ?? 0); break;
                    case 'MIN_NOTIONAL':$out['minNotional'] = (float) ($f['minNotional'] ?? 0); break;
                }
            }
            $this->filterCache[$symbol] = $out;
        }
        return $out;
    }

    /** LOT_SIZE step for a symbol (to round sell quantities). */
    public function lotStep(string $symbol): ?float
    {
        $s = $this->exchangeFilters($symbol)['step'];
        return $s > 0 ? $s : null;
    }

    /** Number of decimal places implied by a step/tick size (e.g. 0.001 -> 3). */
    private function decimalsOf(float $step): int
    {
        if ($step <= 0) return 8;
        if ($step >= 1) return 0;
        // Binance precision is capped at 8 dp; %.8f avoids float-representation noise
        // (e.g. %.18f of 0.1 -> 0.100000000000000006, which mis-counts as 18 decimals).
        $s = rtrim(sprintf('%.8f', $step), '0');
        $dot = strpos($s, '.');
        return $dot === false ? 0 : strlen($s) - $dot - 1;
    }

    /** Floor (or ceil) a value to a step and format it at the step's precision — Binance-safe. */
    public function quantize(float $value, float $step, bool $up = false): string
    {
        if ($step <= 0) return rtrim(rtrim(sprintf('%.8f', $value), '0'), '.') ?: '0';
        $n = $up ? ceil($value / $step - 1e-9) : floor($value / $step + 1e-9);
        return number_format($n * $step, $this->decimalsOf($step), '.', '');
    }

    /**
     * Place a resting OCO SELL to protect a long: a take-profit limit above and a
     * stop-limit below, on the exchange. quantity/prices are quantized to the symbol
     * filters here. @return ['ok'=>bool,'data'=>['orderListId'=>...], 'error'=>...]
     */
    public function placeOcoSell(string $symbol, float $qty, float $tpPrice, float $stopPrice, float $stopLimitPrice): array
    {
        $f = $this->exchangeFilters($symbol);
        return $this->signedRequest('POST', '/api/v3/order/oco', [
            'symbol'                => strtoupper($symbol),
            'side'                  => 'SELL',
            'quantity'              => $this->quantize($qty, $f['step']),
            'price'                 => $this->quantize($tpPrice, $f['tick']),          // take-profit limit
            'stopPrice'             => $this->quantize($stopPrice, $f['tick']),        // stop trigger
            'stopLimitPrice'        => $this->quantize($stopLimitPrice, $f['tick']),   // stop execution limit
            'stopLimitTimeInForce'  => 'GTC',
        ]);
    }

    /** Place a resting STOP_LOSS_LIMIT SELL (for the trailing template; no fixed take-profit). */
    public function placeStopLossSell(string $symbol, float $qty, float $stopPrice, float $stopLimitPrice): array
    {
        $f = $this->exchangeFilters($symbol);
        return $this->signedRequest('POST', '/api/v3/order', [
            'symbol'      => strtoupper($symbol),
            'side'        => 'SELL',
            'type'        => 'STOP_LOSS_LIMIT',
            'timeInForce' => 'GTC',
            'quantity'    => $this->quantize($qty, $f['step']),
            'price'       => $this->quantize($stopLimitPrice, $f['tick']),
            'stopPrice'   => $this->quantize($stopPrice, $f['tick']),
        ]);
    }

    /** Cancel an OCO order list. */
    public function cancelOco(string $symbol, string $orderListId): array
    {
        return $this->signedRequest('DELETE', '/api/v3/orderList', [
            'symbol' => strtoupper($symbol), 'orderListId' => $orderListId,
        ]);
    }

    /** Cancel a single order. */
    public function cancelOrder(string $symbol, string $orderId): array
    {
        return $this->signedRequest('DELETE', '/api/v3/order', [
            'symbol' => strtoupper($symbol), 'orderId' => $orderId,
        ]);
    }

    /** Status of an OCO list: listOrderStatus (EXECUTING|ALL_DONE|REJECT) + its orders. */
    public function ocoStatus(string $orderListId): array
    {
        return $this->signedRequest('GET', '/api/v3/orderList', ['orderListId' => $orderListId]);
    }

    /** Status of a single order (status, executedQty, cummulativeQuoteQty, ...). */
    public function orderStatus(string $symbol, string $orderId): array
    {
        return $this->signedRequest('GET', '/api/v3/order', [
            'symbol' => strtoupper($symbol), 'orderId' => $orderId,
        ]);
    }

    /** LIVE market BUY spending a fixed quote amount (USDT). Uses the configured endpoint. */
    public function marketBuyQuote(string $symbol, float $quoteUsd): array
    {
        return $this->signedPost('/api/v3/order', [
            'symbol' => strtoupper($symbol), 'side' => 'BUY', 'type' => 'MARKET',
            'quoteOrderQty' => rtrim(rtrim(number_format($quoteUsd, 2, '.', ''), '0'), '.'),
        ]);
    }

    /** LIVE market SELL a base-asset quantity. Uses the configured endpoint. */
    public function marketSell(string $symbol, float $qty): array
    {
        return $this->signedPost('/api/v3/order', [
            'symbol' => strtoupper($symbol), 'side' => 'SELL', 'type' => 'MARKET',
            'quantity' => rtrim(rtrim(number_format($qty, 8, '.', ''), '0'), '.'),
        ]);
    }

    private function signedPost(string $path, array $params): array
    {
        return $this->signedRequest('POST', $path, $params);
    }

    /** Signed request (GET/POST/DELETE) to the configured account endpoint. */
    private function signedRequest(string $method, string $path, array $params): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'error' => 'API key/secret not set', 'code' => 0];
        }
        $params['timestamp']  = (int) round(microtime(true) * 1000);
        $params['recvWindow'] = 5000;
        $query = http_build_query($params);
        $sig   = hash_hmac('sha256', $query, $this->apiSecret);
        $url   = $this->accountBase . $path . '?' . $query . '&signature=' . $sig;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => ['X-MBX-APIKEY: ' . $this->apiKey],
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, '');
        }
        $body  = curl_exec($ch);
        $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);
        $err   = curl_error($ch);
        curl_close($ch);

        if ($body === false || $errno) {
            return ['ok' => false, 'error' => 'Network error: ' . $err, 'code' => 0];
        }
        $data = json_decode($body, true);
        if ($code < 200 || $code >= 300) {
            $msg = (is_array($data) && isset($data['msg'])) ? $data['msg'] : ('HTTP ' . $code);
            return ['ok' => false, 'error' => $msg, 'code' => $code];
        }
        return ['ok' => true, 'data' => $data, 'code' => $code];
    }

    /** Signed account info (balances, canTrade). Uses the configured endpoint. */
    public function account(): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'error' => 'API key/secret not set', 'code' => 0];
        }
        $query = 'timestamp=' . (int) round(microtime(true) * 1000) . '&recvWindow=5000';
        $sig   = hash_hmac('sha256', $query, $this->apiSecret);
        $url   = $this->accountBase . '/api/v3/account?' . $query . '&signature=' . $sig;
        return $this->httpGet($url, [], ['X-MBX-APIKEY: ' . $this->apiKey]);
    }

    private function httpGet(string $url, array $query = [], array $headers = []): array
    {
        if (!empty($query)) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($query);
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $body  = curl_exec($ch);
        $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);
        $err   = curl_error($ch);
        curl_close($ch);

        if ($body === false || $errno) {
            return ['ok' => false, 'error' => 'Network error: ' . $err, 'code' => 0];
        }
        $data = json_decode($body, true);
        if ($code < 200 || $code >= 300) {
            $msg = (is_array($data) && isset($data['msg'])) ? $data['msg'] : ('HTTP ' . $code);
            return ['ok' => false, 'error' => $msg, 'code' => $code];
        }
        return ['ok' => true, 'data' => $data, 'code' => $code];
    }
}
