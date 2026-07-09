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

        $apiKey = '';
        $secretSet = false;
        $testnet = false;
        $baseUrl = 'https://api.binance.com';
        $configured = false;
        try {
            $settings  = new GtbSettings();
            $apiKey    = $settings->getApiKey();
            $secretSet = $settings->getApiSecret() !== '';
            $testnet   = $settings->isTestnet();
            $baseUrl   = (string) (\Ginto\Support\Env::get('BINANCE_BASE_URL', 'https://api.binance.com') ?? 'https://api.binance.com');
            $configured = $settings->isConfigured();
        } catch (\Throwable $e) {
            // DB/env unavailable — render an empty form
        }

        View::view('gtb/gtb', [
            'title'            => 'GTB · API Settings',
            'page'             => 'settings',
            'isLoggedIn'       => !empty($_SESSION['user_id']),
            'isAdmin'          => $this->isAdmin(),
            'username'         => $_SESSION['username'] ?? null,
            'userId'           => $_SESSION['user_id'] ?? null,
            'userFullname'     => $_SESSION['fullname'] ?? $_SESSION['username'] ?? null,
            'apiConfigured'    => $configured,
            'binanceApiKey'    => $apiKey,
            'binanceSecretSet' => $secretSet,
            'binanceTestnet'   => $testnet,
            'binanceBaseUrl'   => $baseUrl,
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
            $apiKey  = trim((string) ($input['binance_api_key'] ?? ''));
            $secret  = trim((string) ($input['binance_api_secret'] ?? ''));
            $testnet = !empty($input['binance_testnet']);
            $baseUrl = trim((string) ($input['binance_base_url'] ?? ''));

            // Default base URL from the testnet flag when left blank
            if ($baseUrl === '') {
                $baseUrl = $testnet ? 'https://testnet.binance.vision' : 'https://api.binance.com';
            }
            if (!preg_match('#^https?://#i', $baseUrl)) {
                throw new \Exception('Base URL must start with http:// or https://');
            }

            $pairs = [
                'BINANCE_API_KEY'  => $apiKey,
                'BINANCE_TESTNET'  => $testnet ? 'true' : 'false',
                'BINANCE_BASE_URL' => $baseUrl,
            ];
            // Only overwrite the secret when a new value is supplied (blank = keep existing)
            if ($secret !== '') {
                $pairs['BINANCE_API_SECRET'] = $secret;
            }

            $this->updateEnvKeys($pairs);

            $secretPresent = $secret !== '';
            if (!$secretPresent) {
                try { $secretPresent = (new GtbSettings())->getApiSecret() !== ''; } catch (\Throwable $e) {}
            }

            echo json_encode([
                'success'    => true,
                'message'    => 'Binance settings saved to .env',
                'configured' => ($apiKey !== '' && $secretPresent),
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
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
