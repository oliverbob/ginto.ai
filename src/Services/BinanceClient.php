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
