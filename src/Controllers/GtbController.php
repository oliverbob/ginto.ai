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
        $anthropicKeySet    = (string) (\Ginto\Support\Env::get('ANTHROPIC_API_KEY', '') ?? '') !== '';
        $anthropicModel     = (string) (\Ginto\Support\Env::get('ANTHROPIC_MODEL', 'claude-opus-4-8') ?? 'claude-opus-4-8');
        $anthropicScanModel = (string) (\Ginto\Support\Env::get('ANTHROPIC_SCAN_MODEL', 'claude-haiku-4-5') ?? 'claude-haiku-4-5');
        $gtbTemplates = array_filter(array_map('trim', explode(',', (string) (\Ginto\Support\Env::get('GTB_TEMPLATES', 'scalp,breakout,trend,pullback') ?? ''))));
        $gtbMemory    = \Ginto\Support\Env::bool('GTB_MEMORY_ENABLED', false);

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
            'anthropicKeySet'   => $anthropicKeySet,
            'anthropicModel'    => $anthropicModel,
            'anthropicScanModel'=> $anthropicScanModel,
            'gtbTemplates'      => $gtbTemplates,
            'gtbMemory'         => $gtbMemory,
            'csrf_token'        => $this->csrfToken(),
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

            // AI brain key (write-only, like the Binance secrets)
            $anthropicKey = trim((string) ($input['anthropic_api_key'] ?? ''));
            if ($anthropicKey !== '') $pairs['ANTHROPIC_API_KEY'] = $anthropicKey;

            // AI brain model selection (validated allowlist)
            $allowedModels = ['claude-opus-4-8', 'claude-sonnet-5', 'claude-haiku-4-5'];
            $decModel  = (string) ($input['decision_model'] ?? '');
            $scanModel = (string) ($input['scan_model'] ?? '');
            if (in_array($decModel, $allowedModels, true))  $pairs['ANTHROPIC_MODEL'] = $decModel;
            if (in_array($scanModel, $allowedModels, true)) $pairs['ANTHROPIC_SCAN_MODEL'] = $scanModel;

            // Strategy templates enable/disable (at least one required)
            $allowedTpls = ['scalp', 'breakout', 'trend', 'pullback'];
            $tpls = array_values(array_intersect($allowedTpls, (array) ($input['templates'] ?? [])));
            if ($tpls) $pairs['GTB_TEMPLATES'] = implode(',', $tpls);

            // Memory (opt-in; increases tokens per AI decision)
            $pairs['GTB_MEMORY_ENABLED'] = !empty($input['memory_enabled']) ? 'true' : 'false';

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

    /** GET /gtb/markets — categorized USDT markets: hot (volume), gainers, losers */
    public function markets(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $this->requireAdmin(true);

        // Stablecoin / fiat bases to exclude (X/USDT where X≈1 isn't interesting).
        $stable = ['USDC','BUSD','TUSD','FDUSD','DAI','USDP','USTC','EUR','GBP','AEUR','USD1','EURI','XUSD'];

        try {
            $client = new \Ginto\Services\BinanceClient();
            $res = $client->allTickers24hr();
            if (empty($res['ok']) || !is_array($res['data'])) {
                http_response_code(502);
                echo json_encode(['ok' => false, 'error' => $res['error'] ?? 'ticker failed']);
                exit;
            }

            $items = [];
            foreach ($res['data'] as $r) {
                $sym = $r['symbol'] ?? '';
                if (!str_ends_with($sym, 'USDT')) continue;
                $base = substr($sym, 0, -4);
                if ($base === '') continue;
                if (preg_match('/(UP|DOWN|BULL|BEAR)$/', $base)) continue; // leveraged tokens
                if (in_array($base, $stable, true)) continue;
                $items[] = [
                    'symbol'      => $sym,
                    'base'        => $base,
                    'price'       => (float) ($r['lastPrice'] ?? 0),
                    'changePct'   => (float) ($r['priceChangePercent'] ?? 0),
                    'high'        => (float) ($r['highPrice'] ?? 0),
                    'low'         => (float) ($r['lowPrice'] ?? 0),
                    'quoteVolume' => (float) ($r['quoteVolume'] ?? 0),
                ];
            }

            // Liquidity floor for gainers/losers so micro-cap pumps don't dominate.
            $minVol = 5000000.0;
            $liquid = array_values(array_filter($items, static fn($i) => $i['quoteVolume'] >= $minVol));

            $hot = $items;
            usort($hot, static fn($a, $b) => $b['quoteVolume'] <=> $a['quoteVolume']);
            $hot = array_slice($hot, 0, 24);

            $gainers = $liquid;
            usort($gainers, static fn($a, $b) => $b['changePct'] <=> $a['changePct']);
            $gainers = array_slice($gainers, 0, 24);

            $losers = $liquid;
            usort($losers, static fn($a, $b) => $a['changePct'] <=> $b['changePct']);
            $losers = array_slice($losers, 0, 24);

            echo json_encode([
                'ok'      => true,
                'hot'     => $hot,
                'gainers' => $gainers,
                'losers'  => $losers,
                'source'  => 'mainnet public',
            ]);
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
            $earnUsdt = 0.0;
            foreach (($acct['balances'] ?? []) as $b) {
                $free = (float) $b['free'];
                $locked = (float) $b['locked'];
                $total = $free + $locked;
                if ($total <= 0) continue;
                $asset = $b['asset'];

                // Per-unit USDT price. Simple Earn Flexible positions appear as
                // LD-prefixed tokens (LDUSDT ~ USDT, LDBTC ~ BTC). We only strip the
                // LD prefix when the token has no pair of its own, so real tokens
                // like LDO (which has LDOUSDT) are still valued directly.
                $isEarn = false;
                if ($asset === 'USDT') {
                    $unit = 1.0;
                } elseif (isset($priceMap[$asset . 'USDT'])) {
                    $unit = $priceMap[$asset . 'USDT'];
                } elseif (str_starts_with($asset, 'LD')) {
                    $u = substr($asset, 2);
                    $unit = $u === 'USDT' ? 1.0 : ($priceMap[$u . 'USDT'] ?? 0.0);
                    $isEarn = $unit > 0.0;
                } else {
                    $unit = 0.0;
                }
                $usdt = $unit * $total;

                if ($asset === 'USDT') $freeUsdt = $free;   // free USDT available for spot trading
                if ($isEarn) $earnUsdt += $usdt;            // value parked in Flexible Earn

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
                'earnUsdt'      => $earnUsdt,
                'holdingsCount' => count($balances),
                'balances'      => array_slice($balances, 0, 25),
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** GET /gtb/thoughts — the bot's reflection stream + capital/brain status */
    public function thoughts(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        $this->requireAdmin(true);

        $thoughts = [];
        $capital = null;
        $brainReady = false;
        $spend = ['total' => 0.0, 'count' => 0];
        try {
            $tModel = new \Ginto\Models\GtbThought();
            $thoughts = $tModel->recent(40);
            $spend = $tModel->spend();
            $realized = (new GtbTrade())->totalRealizedPnl();
            $capital = (new \Ginto\Services\GtbCapital())->summary($realized);
            $brainReady = (new \Ginto\Services\GtbBrain())->isConfigured();
        } catch (\Throwable $e) {}

        echo json_encode(['ok' => true, 'thoughts' => $thoughts, 'capital' => $capital, 'brainReady' => $brainReady, 'spend' => $spend]);
        exit;
    }

    /** POST /gtb/bot/reflect — ask the AI brain to reflect on the market (advisory, no orders) */
    public function reflect(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        $this->requireAdmin(true);

        // CSRF (reuse the same scheme as settings save)
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $input['csrf_token'] ?? '';
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        if (empty($token) || empty($sessionToken) || !hash_equals($sessionToken, $token)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }

        try {
            $settings = new GtbSettings();
            $brain    = new \Ginto\Services\GtbBrain();
            $thoughts = new \Ginto\Models\GtbThought();

            if (!$brain->isConfigured()) {
                echo json_encode(['ok' => false, 'error' => 'No Anthropic API key set. Add it on the API Settings page.']);
                exit;
            }

            $realized = (new GtbTrade())->totalRealizedPnl();
            $capital  = (new \Ginto\Services\GtbCapital())->summary($realized);
            $context  = [
                'env'      => $settings->isTestnet() ? 'testnet' : 'mainnet',
                'capital'  => $capital,
                'movers'   => $this->marketSnapshot(),
                'note'     => 'Advisory reflection only — no order will be placed.',
            ];
            if (\Ginto\Support\Env::bool('GTB_MEMORY_ENABLED', false)) {
                $closed = (new GtbTrade())->recentClosed(8);
                $context['memory'] = array_map(static fn($c) => [
                    'symbol' => $c['symbol'], 'template' => $c['template'] ?? '?',
                    'pnl' => round((float) $c['realized_pnl'], 4),
                ], $closed);
            }

            $res = $brain->reflect($context);
            if (empty($res['ok'])) {
                $thoughts->add('Reflection failed: ' . ($res['error'] ?? 'unknown'), 'system', 'error');
                echo json_encode(['ok' => false, 'error' => $res['error'] ?? 'Reflection failed']);
                exit;
            }

            $meta = array_merge(['model' => $res['model'] ?? $brain->model()], $res['usage'] ?? []);
            $thoughts->add($res['text'], 'claude', 'reflect', null, $res['decision'] ?? null, $meta);
            echo json_encode(['ok' => true, 'text' => $res['text'], 'decision' => $res['decision'] ?? null, 'usage' => $res['usage'] ?? null]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** POST /gtb/bot/step — run one strategy cycle (paper on testnet, live on mainnet) */
    public function step(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        $this->requireAdmin(true);

        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $input['csrf_token'] ?? '';
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        if (empty($token) || empty($sessionToken) || !hash_equals($sessionToken, $token)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }
        try {
            $armLive = !empty($input['arm_live']);
            echo json_encode((new \Ginto\Services\GtbStrategy())->step($armLive));
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** GET /gtb/bot/positions — open positions + portfolio + persisted bot state */
    public function positions(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        $this->requireAdmin(true);
        try {
            $state = (new \Ginto\Services\GtbStrategy())->openPositionsState();
            $state['bot'] = (new \Ginto\Models\GtbBotState())->status();
            echo json_encode($state);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** POST /gtb/bot/control — persist bot on/off (+ live arm). The server runner acts on it. */
    public function control(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        $this->requireAdmin(true);
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        if (!$this->checkCsrf($input)) exit;
        try {
            $enabled = !empty($input['enabled']);
            $armLive = !empty($input['arm_live']);
            $state = new \Ginto\Models\GtbBotState();
            $state->set($enabled, $armLive);
            echo json_encode(['ok' => true, 'bot' => $state->status()]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** POST /gtb/bot/close — manually close one open position */
    public function closePosition(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        $this->requireAdmin(true);
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        if (!$this->checkCsrf($input)) exit;
        try {
            $id = (int) ($input['id'] ?? 0);
            echo json_encode((new \Ginto\Services\GtbStrategy())->closePosition($id));
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    private function checkCsrf(array $input): bool
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $input['csrf_token'] ?? '';
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        if (empty($token) || empty($sessionToken) || !hash_equals($sessionToken, $token)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
            return false;
        }
        return true;
    }

    /** Compact top-movers snapshot for the brain (top gainers/losers among liquid USDT pairs). */
    private function marketSnapshot(): array
    {
        try {
            $client = new \Ginto\Services\BinanceClient();
            $res = $client->allTickers24hr();
            if (empty($res['ok']) || !is_array($res['data'])) return [];
            $stable = ['USDC','BUSD','TUSD','FDUSD','DAI','USDP','USTC','EUR','GBP','AEUR','USD1'];
            $items = [];
            foreach ($res['data'] as $r) {
                $sym = $r['symbol'] ?? '';
                if (!str_ends_with($sym, 'USDT')) continue;
                $base = substr($sym, 0, -4);
                if ($base === '' || preg_match('/(UP|DOWN|BULL|BEAR)$/', $base) || in_array($base, $stable, true)) continue;
                $vol = (float) ($r['quoteVolume'] ?? 0);
                if ($vol < 5000000.0) continue;
                $items[] = [
                    'symbol'    => $sym,
                    'changePct' => round((float) ($r['priceChangePercent'] ?? 0), 2),
                    'price'     => (float) ($r['lastPrice'] ?? 0),
                    'quoteVol'  => (int) $vol,
                ];
            }
            $g = $items; usort($g, static fn($a, $b) => $b['changePct'] <=> $a['changePct']);
            $l = $items; usort($l, static fn($a, $b) => $a['changePct'] <=> $b['changePct']);
            return ['topGainers' => array_slice($g, 0, 6), 'topLosers' => array_slice($l, 0, 4)];
        } catch (\Throwable $e) {
            return [];
        }
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
