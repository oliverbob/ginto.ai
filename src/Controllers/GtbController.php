<?php
namespace Ginto\Controllers;

use Ginto\Core\Database;
use Ginto\Core\View;
use Ginto\Models\GtbSettings;
use Ginto\Models\GtbTrade;
use Ginto\Models\GtbLog;

/**
 * Ginto Trading Bot (GTB) controller.
 *
 * V1 foundation: renders the dashboard shell from DB data (empty states until
 * the schema is loaded and trades exist). No Binance calls / trading logic yet.
 */
class GtbController
{
    protected $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /** GET /gtb — dashboard */
    public function index(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $this->requireAdmin();

        // Gather dashboard data defensively: the page must render even if the
        // DB or the gtb_* tables are not available yet.
        $apiConfigured = false;
        $isTestnet     = false;
        $recentTrades  = [];
        $recentLogs    = [];
        $realizedPnl   = 0.0;

        try {
            $settings = new GtbSettings();
            $apiConfigured = $settings->isConfigured();
            $isTestnet     = $settings->isTestnet();

            $trades       = new GtbTrade();
            $recentTrades = $trades->recent(20);
            $realizedPnl  = $trades->totalRealizedPnl();

            $recentLogs = (new GtbLog())->recent(20);
        } catch (\Throwable $e) {
            // DB unavailable — render an empty dashboard rather than erroring.
        }

        $isAdmin = false;
        if (class_exists('\\Ginto\\Controllers\\UserController')) {
            try {
                $isAdmin = \Ginto\Controllers\UserController::isAdmin();
            } catch (\Throwable $e) {
                $isAdmin = false;
            }
        }

        View::view('gtb/gtb', [
            'title'         => 'Ginto Trading Bot',
            'isLoggedIn'    => !empty($_SESSION['user_id']),
            'isAdmin'       => $isAdmin,
            'username'      => $_SESSION['username'] ?? null,
            'userId'        => $_SESSION['user_id'] ?? null,
            'userFullname'  => $_SESSION['fullname'] ?? $_SESSION['username'] ?? null,
            'apiConfigured' => $apiConfigured,
            'isTestnet'     => $isTestnet,
            'binanceEndpoint' => $isTestnet ? 'https://testnet.binance.vision' : 'https://api.binance.com',
            'recentTrades'  => $recentTrades,
            'recentLogs'    => $recentLogs,
            'realizedPnl'   => $realizedPnl,
        ]);
    }

    /** GET /gtb-settings — Binance API configuration form */
    public function settings(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $this->requireAdmin();

        $testnet = false;
        $mainKey = ''; $mainSecretSet = false;
        $testKey = ''; $testSecretSet = false;
        $configured = false;
        try {
            $s = new GtbSettings();
            $testnet       = $s->isTestnet();
            $mainKey       = $s->mainnetApiKey();
            $mainSecretSet = $s->mainnetApiSecret() !== '';
            $testKey       = $s->testnetApiKey();
            $testSecretSet = $s->testnetApiSecret() !== '';
            $configured    = $s->isConfigured();
        } catch (\Throwable $e) {
            // env unavailable — render an empty form
        }
        $endpoint = $testnet ? 'https://testnet.binance.vision' : 'https://api.binance.com';

        View::view('gtb/gtb', [
            'title'            => 'GTB · API Settings',
            'page'             => 'settings',
            'isLoggedIn'       => !empty($_SESSION['user_id']),
            'isAdmin'          => $this->isAdmin(),
            'username'         => $_SESSION['username'] ?? null,
            'userId'           => $_SESSION['user_id'] ?? null,
            'userFullname'     => $_SESSION['fullname'] ?? $_SESSION['username'] ?? null,
            'apiConfigured'    => $configured,
            'mainnetApiKey'    => $mainKey,
            'mainnetSecretSet' => $mainSecretSet,
            'testnetApiKey'    => $testKey,
            'testnetSecretSet' => $testSecretSet,
            'binanceTestnet'   => $testnet,
            'binanceEndpoint'  => $endpoint,
            'csrf_token'       => $this->csrfToken(),
        ]);
    }

    /** POST /gtb-settings — persist Binance config to .env (mirrors /live save) */
    public function saveSettings(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

        // CSRF: header or body token vs session (same scheme as /live)
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $input['csrf_token'] ?? '';
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        if (empty($token) || empty($sessionToken) || !hash_equals($sessionToken, $token)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }

        // Admin only
        $this->requireAdmin(true);

        try {
            $mainKey    = trim((string) ($input['mainnet_api_key'] ?? ''));
            $mainSecret = trim((string) ($input['mainnet_api_secret'] ?? ''));
            $testKey    = trim((string) ($input['testnet_api_key'] ?? ''));
            $testSecret = trim((string) ($input['testnet_api_secret'] ?? ''));
            $testnet    = !empty($input['binance_testnet']);

            // The testnet toggle is authoritative for the endpoint so the flag and
            // the base URL can never disagree.
            $baseUrl = $testnet ? 'https://testnet.binance.vision' : 'https://api.binance.com';

            // Keys are shown in the form (editable), so write them as submitted.
            // Secrets are write-only, so a blank secret keeps the stored one.
            $pairs = [
                'BINANCE_API_KEY'         => $mainKey,
                'BINANCE_TESTNET_API_KEY' => $testKey,
                'BINANCE_TESTNET'         => $testnet ? 'true' : 'false',
                'BINANCE_BASE_URL'        => $baseUrl,
            ];
            if ($mainSecret !== '') $pairs['BINANCE_API_SECRET'] = $mainSecret;
            if ($testSecret !== '') $pairs['BINANCE_TESTNET_API_SECRET'] = $testSecret;

            $this->updateEnvKeys($pairs);

            // Re-read (fresh .env) to report whether the ACTIVE environment is ready.
            $active = new GtbSettings();
            echo json_encode([
                'success'    => true,
                'message'    => 'Binance settings saved to .env',
                'testnet'    => $testnet,
                'configured' => $active->isConfigured(),
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** GET /gtb/markets — live market data (public mainnet) for the dashboard charts */
    public function markets(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $this->requireAdmin(true);

        $symbols = ['BTCUSDT','ETHUSDT','BNBUSDT','SOLUSDT','XRPUSDT','ADAUSDT',
                    'DOGEUSDT','AVAXUSDT','LINKUSDT','DOTUSDT','LTCUSDT','TRXUSDT'];
        try {
            $client = new \Ginto\Services\BinanceClient();
            $ticker = $client->ticker24hr($symbols);
            if (empty($ticker['ok'])) {
                http_response_code(502);
                echo json_encode(['ok' => false, 'error' => $ticker['error'] ?? 'ticker failed']);
                exit;
            }
            $closesMap = $client->klinesMulti($symbols, '1h', 24);

            $bySymbol = [];
            foreach ($ticker['data'] as $row) {
                $bySymbol[$row['symbol']] = $row;
            }
            $markets = [];
            foreach ($symbols as $s) {
                if (!isset($bySymbol[$s])) continue;
                $r = $bySymbol[$s];
                $markets[] = [
                    'symbol'      => $s,
                    'base'        => str_replace('USDT', '', $s),
                    'price'       => (float) $r['lastPrice'],
                    'changePct'   => (float) $r['priceChangePercent'],
                    'high'        => (float) $r['highPrice'],
                    'low'         => (float) $r['lowPrice'],
                    'quoteVolume' => (float) $r['quoteVolume'],
                    'closes'      => $closesMap[$s] ?? [],
                ];
            }
            echo json_encode(['ok' => true, 'markets' => $markets, 'source' => 'mainnet public']);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** GET /gtb/klines?symbol=&interval= — OHLC candles for the candlestick chart */
    public function klines(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $this->requireAdmin(true);

        $symbol   = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($_GET['symbol'] ?? 'BTCUSDT')));
        $interval = preg_replace('/[^0-9a-zA-Z]/', '', (string) ($_GET['interval'] ?? '1h'));
        if (!in_array($interval, ['1m', '5m', '15m', '1h', '4h', '1d'], true)) {
            $interval = '1h';
        }
        if ($symbol === '') $symbol = 'BTCUSDT';

        try {
            $client = new \Ginto\Services\BinanceClient();
            $res = $client->klines($symbol, $interval, 300);
            if (empty($res['ok'])) {
                http_response_code(502);
                echo json_encode(['ok' => false, 'error' => $res['error'] ?? 'klines failed']);
                exit;
            }
            echo json_encode(['ok' => true, 'symbol' => $symbol, 'interval' => $interval, 'candles' => $res['data']]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** GET /gtb/account — signed account: portfolio value, free USDT, holdings, balances */
    public function account(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $this->requireAdmin(true);

        try {
            $client = new \Ginto\Services\BinanceClient();
            if (!$client->isConfigured()) {
                echo json_encode(['ok' => false, 'error' => 'No API key/secret saved yet.',
                                  'endpoint' => $client->accountEndpoint(), 'testnet' => $client->isTestnet()]);
                exit;
            }
            $res = $client->account();
            if (empty($res['ok'])) {
                echo json_encode(['ok' => false, 'error' => $res['error'] ?? 'Request failed',
                                  'endpoint' => $client->accountEndpoint(), 'testnet' => $client->isTestnet()]);
                exit;
            }
            $acct = $res['data'];

            // Price map for USDT valuation (public mainnet prices).
            $priceMap = [];
            $p = $client->allPrices();
            if (!empty($p['ok'])) $priceMap = $p['data'];

            $balances = [];
            $portfolioUsdt = 0.0;
            $freeUsdt = 0.0;
            foreach (($acct['balances'] ?? []) as $b) {
                $free = (float) $b['free'];
                $locked = (float) $b['locked'];
                $total = $free + $locked;
                if ($total <= 0) continue;
                $asset = $b['asset'];
                $usdt = $asset === 'USDT' ? $total : (($priceMap[$asset . 'USDT'] ?? 0.0) * $total);
                if ($asset === 'USDT') $freeUsdt = $free;
                $portfolioUsdt += $usdt;
                $balances[] = ['asset' => $asset, 'free' => $free, 'locked' => $locked, 'usdt' => $usdt];
            }
            usort($balances, static fn($a, $b) => $b['usdt'] <=> $a['usdt']);

            echo json_encode([
                'ok'            => true,
                'endpoint'      => $client->accountEndpoint(),
                'testnet'       => $client->isTestnet(),
                'canTrade'      => $acct['canTrade'] ?? null,
                'portfolioUsdt' => $portfolioUsdt,
                'freeUsdt'      => $freeUsdt,
                'holdingsCount' => count($balances),
                'balances'      => array_slice($balances, 0, 25),
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Restrict access to admins only. GTB controls real trading credentials, so
     * both the dashboard and the settings API are admin-gated (single-user bot).
     * Non-admins: redirect to /login (or /chat if logged in) for pages; 403 JSON
     * for API calls.
     */
    private function requireAdmin(bool $json = false): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if ($this->isAdmin()) {
            return;
        }

        if ($json) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'Admin access required']);
            exit;
        }

        if (empty($_SESSION['user_id'])) {
            $dest = $_SERVER['REQUEST_URI'] ?? '/gtb';
            header('Location: /login?redirect=' . rawurlencode($dest));
        } else {
            http_response_code(403);
            header('Location: /chat');
        }
        exit;
    }

    private function isAdmin(): bool
    {
        if (class_exists('\\Ginto\\Controllers\\UserController')) {
            try { return (bool) \Ginto\Controllers\UserController::isAdmin(); } catch (\Throwable $e) {}
        }
        return false;
    }

    private function csrfToken(): string
    {
        if (function_exists('generateCsrfToken')) {
            return generateCsrfToken();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Update specific keys in .env in place, preserving all other lines,
     * comments, and ordering. Backs up the current file first (like /live).
     */
    private function updateEnvKeys(array $pairs): void
    {
        $envPath = ROOT_PATH . '/.env';
        $contents = is_file($envPath) ? (string) file_get_contents($envPath) : '';

        // Back up current .env (same safety net as /live)
        if ($contents !== '') {
            $backupDir = (defined('STORAGE_PATH') ? STORAGE_PATH : dirname(ROOT_PATH) . '/storage') . '/backups';
            if (!is_dir($backupDir)) {
                @mkdir($backupDir, 0775, true);
            }
            @file_put_contents($backupDir . '/.env.' . date('Ymd_His'), $contents);
        }

        $lines = $contents === '' ? [] : preg_split("/\r\n|\n|\r/", $contents);
        $seen = [];
        foreach ($lines as $i => $line) {
            $trim = ltrim($line);
            if ($trim === '' || $trim[0] === '#') {
                continue;
            }
            $eq = strpos($line, '=');
            if ($eq === false) {
                continue;
            }
            $key = trim(substr($line, 0, $eq));
            if (array_key_exists($key, $pairs)) {
                $lines[$i] = $key . '=' . $this->envQuote((string) $pairs[$key]);
                $seen[$key] = true;
            }
        }
        // Append keys that were not already present
        foreach ($pairs as $key => $val) {
            if (empty($seen[$key])) {
                $lines[] = $key . '=' . $this->envQuote((string) $val);
            }
        }

        $new = rtrim(implode("\n", $lines), "\n") . "\n";
        if (file_put_contents($envPath, $new) === false) {
            throw new \Exception('Failed to write .env file');
        }
    }

    private function envQuote(string $v): string
    {
        if ($v !== '' && strpos($v, ' ') !== false && !str_starts_with($v, '"')) {
            return '"' . $v . '"';
        }
        return $v;
    }
}
