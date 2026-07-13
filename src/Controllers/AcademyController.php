<?php
namespace Ginto\Controllers;

use Ginto\Core\View;
use Ginto\Core\Database;

/**
 * Ginto Trading Academy — public-facing front door for the crypto-trading education
 * product. It markets the academy, showcases the live GTB trading bot as the teaching
 * centrepiece, and funnels visitors into the EXISTING courses + subscription system.
 *
 * Access to the actual "facility" (lessons) is gated by an active subscription:
 * /academy/enter routes subscribers to /courses and everyone else to /subscribe.
 */
class AcademyController
{
    /** GET /academy — public landing page. */
    public function index(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();

        $userId     = $_SESSION['user_id'] ?? null;
        $isLoggedIn = !empty($userId);
        $hasAccess  = $isLoggedIn && $this->hasActiveSubscription((int) $userId);

        $plans = $this->subscriptionPlans();

        View::view('academy/academy', [
            'title'        => 'Ginto Trading Academy — Learn crypto trading with a live AI bot',
            'isLoggedIn'   => $isLoggedIn,
            'isAdmin'      => $this->isAdmin(),
            'username'     => $_SESSION['username'] ?? null,
            'userFullname' => $_SESSION['fullname'] ?? $_SESSION['username'] ?? null,
            'hasAccess'    => $hasAccess,
            'plans'        => $plans,
            'csrf_token'   => function_exists('generateCsrfToken') ? generateCsrfToken(true) : ($_SESSION['csrf_token'] ?? ''),
        ]);
    }

    /**
     * GET /academy/enter — the gate. Subscribers go into the facility (/courses);
     * everyone else is sent to subscribe. This enforces "no subscription, no access".
     */
    public function enter(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        $userId = $_SESSION['user_id'] ?? null;

        if (empty($userId)) {
            $this->redirect('/login?promo=GINTO-ACADEMY&redirect=' . urlencode('/academy/enter'));
            return;
        }
        if ($this->hasActiveSubscription((int) $userId)) {
            $this->redirect('/academy/learn');     // active subscriber → the branded facility
            return;
        }
        $this->redirect('/academy#pricing');       // no subscription → membership (on the landing)
    }

    /** GET /academy/bot — read-only "Live Bot Lab": members watch the testnet bot trade live. */
    public function bot(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        $userId = $_SESSION['user_id'] ?? null;
        if (empty($userId)) { $this->redirect('/login?redirect=' . urlencode('/academy/bot')); return; }
        if (!$this->hasActiveSubscription((int) $userId)) { $this->redirect('/academy#pricing'); return; }
        $csrf = function_exists('generateCsrfToken') ? generateCsrfToken(true) : ($_SESSION['csrf_token'] ?? '');
        View::view('academy/bot', [
            'title' => 'Live Bot Lab — Ginto Trading Academy', 'isLoggedIn' => true, 'hasAccess' => true, 'csrf_token' => $csrf,
            'isPro' => $this->isPro((int) $userId), 'catalog' => \Ginto\Services\GtbStrategy::catalog(),
        ]);
    }

    /**
     * GET /academy/bot/data — read-only snapshot of the TESTNET (paper) bot: open positions with
     * live marks + the AI reasoning stream. Members only. Shared 5s cache so many learners watching
     * at once don't each hit Binance.
     */
    public function botData(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        header('Content-Type: application/json; charset=utf-8');
        $userId = $_SESSION['user_id'] ?? null;
        if (empty($userId) || !$this->hasActiveSubscription((int) $userId)) {
            http_response_code(403); echo json_encode(['ok' => false, 'error' => 'members only']); exit;
        }
        $cf = (defined('STORAGE_PATH') ? STORAGE_PATH : sys_get_temp_dir()) . '/academy_botdata.json';
        if (is_file($cf) && (time() - filemtime($cf) < 5)) { echo (string) file_get_contents($cf); exit; }
        try {
            $trades = new \Ginto\Models\GtbTrade();
            $client = new \Ginto\Services\BinanceClient();
            $positions = []; $unreal = 0.0;
            foreach ($trades->openPositions('paper') as $p) {   // always PAPER for the learning lab
                $entry = (float) $p['price']; $qty = (float) $p['qty'];
                $mark  = (float) ($client->price($p['symbol']) ?? $entry);
                if ($mark <= 0) $mark = $entry;
                $pnl = ($mark - $entry) * $qty; $unreal += $pnl;
                $positions[] = [
                    'symbol' => $p['symbol'], 'base' => substr($p['symbol'], 0, -4),
                    'template' => $p['template'] ?? '', 'profile' => $p['profile'] ?? '',
                    'entry' => $entry, 'mark' => $mark, 'qty' => $qty,
                    'stop_loss' => (float) $p['stop_loss'],
                    'take_profit' => $p['take_profit'] !== null ? (float) $p['take_profit'] : null,
                    'pnlPct' => $entry > 0 ? ($mark - $entry) / $entry * 100 : 0,
                    'unrealized' => round($pnl, 4), 'opened_at' => $p['created_at'] ?? null,
                ];
            }
            $thoughts = (new \Ginto\Models\GtbThought())->recent(24);
            $running  = false; $lastRun = null; $lastAction = null;
            try {
                $bs = (new \Ginto\Models\GtbBotState())->status();
                $running = !empty($bs['enabled']); $lastRun = $bs['last_run_at'] ?? null; $lastAction = $bs['last_action'] ?? null;
            } catch (\Throwable $e) {}
            $out = json_encode([
                'ok' => true, 'mode' => 'paper', 'running' => $running,
                'last_run_at' => $lastRun, 'last_action' => $lastAction,
                'realized' => round($trades->totalRealizedPnl('paper'), 4), 'unrealized' => round($unreal, 4),
                'positions' => $positions, 'thoughts' => is_array($thoughts) ? $thoughts : [],
            ]);
            @file_put_contents($cf, $out);
            echo $out;
        } catch (\Throwable $e) {
            error_log('Academy botData: ' . $e->getMessage());
            echo json_encode(['ok' => false, 'error' => 'unavailable']);
        }
        exit;
    }

    /** True if the current session is a paid member (for the members-only market endpoints). */
    private function requireMemberJson(): int
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        $userId = $_SESSION['user_id'] ?? null;
        if (empty($userId) || !$this->hasActiveSubscription((int) $userId)) {
            http_response_code(403); echo json_encode(['ok' => false, 'error' => 'members only']); exit;
        }
        return (int) $userId;
    }

    /** GET /academy/markets — members: popular/gainers/losers pairs (same as /gtb, cached 30s). */
    public function markets(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $this->requireMemberJson();
        $cf = (defined('STORAGE_PATH') ? STORAGE_PATH : sys_get_temp_dir()) . '/academy_markets.json';
        if (is_file($cf) && (time() - filemtime($cf) < 30)) { echo (string) file_get_contents($cf); exit; }
        $stable = ['USDC','BUSD','TUSD','FDUSD','DAI','USDP','USTC','EUR','GBP','AEUR','USD1','EURI','XUSD'];
        try {
            $res = (new \Ginto\Services\BinanceClient())->allTickers24hr();
            if (empty($res['ok']) || !is_array($res['data'] ?? null)) { echo json_encode(['ok' => false, 'error' => 'ticker failed']); exit; }
            $items = [];
            foreach ($res['data'] as $r) {
                $sym = $r['symbol'] ?? '';
                if (!str_ends_with($sym, 'USDT')) continue;
                $base = substr($sym, 0, -4);
                if ($base === '' || preg_match('/(UP|DOWN|BULL|BEAR)$/', $base) || in_array($base, $stable, true)) continue;
                $items[] = ['symbol' => $sym, 'base' => $base, 'price' => (float) ($r['lastPrice'] ?? 0),
                    'changePct' => (float) ($r['priceChangePercent'] ?? 0), 'quoteVolume' => (float) ($r['quoteVolume'] ?? 0)];
            }
            $liquid = array_values(array_filter($items, static fn($i) => $i['quoteVolume'] >= 5000000.0));
            $hot = $items; usort($hot, static fn($a, $b) => $b['quoteVolume'] <=> $a['quoteVolume']); $hot = array_slice($hot, 0, 24);
            $gainers = $liquid; usort($gainers, static fn($a, $b) => $b['changePct'] <=> $a['changePct']); $gainers = array_slice($gainers, 0, 24);
            $losers = $liquid; usort($losers, static fn($a, $b) => $a['changePct'] <=> $b['changePct']); $losers = array_slice($losers, 0, 24);
            $out = json_encode(['ok' => true, 'hot' => $hot, 'gainers' => $gainers, 'losers' => $losers]);
            @file_put_contents($cf, $out); echo $out;
        } catch (\Throwable $e) { echo json_encode(['ok' => false, 'error' => 'unavailable']); }
        exit;
    }

    /** GET /academy/klines — members: OHLC candles for the TradingView chart (same as /gtb). */
    public function klines(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $this->requireMemberJson();
        $symbol   = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($_GET['symbol'] ?? 'BTCUSDT'))) ?: 'BTCUSDT';
        $interval = preg_replace('/[^0-9a-zA-Z]/', '', (string) ($_GET['interval'] ?? '15m'));
        if (!in_array($interval, ['1s', '1m', '5m', '15m', '1h', '4h', '1d'], true)) $interval = '15m';
        try {
            $res = (new \Ginto\Services\BinanceClient())->klines($symbol, $interval, 300);
            if (empty($res['ok'])) { echo json_encode(['ok' => false, 'error' => 'klines failed']); exit; }
            echo json_encode(['ok' => true, 'symbol' => $symbol, 'interval' => $interval, 'candles' => $res['data']]);
        } catch (\Throwable $e) { echo json_encode(['ok' => false, 'error' => 'unavailable']); }
        exit;
    }

    /**
     * POST /academy/bot/analyze — members: run the AI brain on a chosen coin and return its
     * reasoning + a BUY/HOLD/SKIP decision (advisory only, no orders — same brain as /gtb Reflect).
     * Per-user cooldown keeps token spend sane when many learners click.
     */
    public function analyze(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $userId = $this->requireMemberJson();

        // Per-user cooldown (seconds) — one analysis at a time, protects the AI budget.
        $cool = 12;
        $stamp = (defined('STORAGE_PATH') ? STORAGE_PATH : sys_get_temp_dir()) . '/academy_analyze_' . $userId . '.txt';
        if (is_file($stamp)) {
            $wait = $cool - (time() - (int) filemtime($stamp));
            if ($wait > 0) { http_response_code(429); echo json_encode(['ok' => false, 'error' => 'Hold on ' . $wait . 's — one analysis at a time.']); exit; }
        }

        $input  = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $scope  = ($input['scope'] ?? 'coin') === 'market' ? 'market' : 'coin';
        $symbol = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($input['symbol'] ?? 'BTCUSDT'))) ?: 'BTCUSDT';
        if (!str_ends_with($symbol, 'USDT')) $symbol .= 'USDT';
        $base = substr($symbol, 0, -4);

        try {
            $brain = new \Ginto\Services\GtbBrain();
            if (!$brain->isConfigured()) { echo json_encode(['ok' => false, 'error' => 'The AI brain is not configured yet. Try again later.']); exit; }
            @touch($stamp);   // start the cooldown now (before the slow AI call)

            $client = new \Ginto\Services\BinanceClient();
            // Movers snapshot for context (reuse the members cache if warm).
            $gainers = []; $losers = [];
            $mc = (defined('STORAGE_PATH') ? STORAGE_PATH : sys_get_temp_dir()) . '/academy_markets.json';
            if (is_file($mc)) {
                $md = json_decode((string) file_get_contents($mc), true);
                $pick = static fn($rows, $n) => array_map(static fn($g) => ['symbol' => $g['symbol'], 'changePct' => round((float) $g['changePct'], 2), 'price' => (float) $g['price']], array_slice($rows ?? [], 0, $n));
                $gainers = $pick($md['gainers'] ?? [], $scope === 'market' ? 12 : 6);
                $losers  = $pick($md['losers'] ?? [], $scope === 'market' ? 6 : 0);
            }

            if ($scope === 'market') {
                $context = [
                    'env' => 'testnet', 'top_gainers' => $gainers, 'top_losers' => $losers,
                    'note' => 'Scan the whole market. Name the single best momentum trade to take right now (or SKIP if nothing is clean), and explain why it beats the alternatives.',
                ];
            } else {
                // Recent price action for the focus coin (compact — closes only).
                $closes = []; $last = 0.0; $chg = 0.0;
                $k = $client->klines($symbol, '15m', 48);
                if (!empty($k['ok']) && is_array($k['data'] ?? null) && $k['data']) {
                    foreach ($k['data'] as $c) { $closes[] = round((float) $c['close'], 8); }
                    $first = (float) $k['data'][0]['close']; $last = (float) end($k['data'])['close'];
                    if ($first > 0) $chg = ($last - $first) / $first * 100;
                }
                $context = [
                    'env' => 'testnet',
                    'focus' => ['symbol' => $symbol, 'base' => $base, 'last' => $last, 'change_since_window' => round($chg, 2), 'recent_closes_15m' => $closes],
                    'movers' => $gainers,
                    'note' => "A student is studying {$base}/USDT. Analyze THIS coin's momentum specifically, then decide.",
                ];
            }

            $res = $brain->reflect($context);
            if (empty($res['ok'])) { echo json_encode(['ok' => false, 'error' => $res['error'] ?? 'Analysis failed']); exit; }
            echo json_encode(['ok' => true, 'scope' => $scope, 'symbol' => $symbol, 'base' => $base, 'text' => $res['text'], 'decision' => $res['decision'] ?? null]);
        } catch (\Throwable $e) {
            error_log('Academy analyze: ' . $e->getMessage());
            echo json_encode(['ok' => false, 'error' => 'The analysis engine is busy — try again in a moment.']);
        }
        exit;
    }

    /** GET /academy/learn — the branded lessons facility (preview lessons open; rest gated). */
    public function learn(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        $userId    = $_SESSION['user_id'] ?? null;
        $hasAccess = !empty($userId) && $this->hasActiveSubscription((int) $userId);
        View::view('academy/learn', [
            'title'      => 'Learn — Ginto Trading Academy',
            'isLoggedIn' => !empty($userId),
            'hasAccess'  => $hasAccess,
            'lessons'    => $this->publishedLessons(),
        ]);
    }

    /** GET /academy/lesson/{slug} — a lesson; non-preview lessons require an active membership. */
    public function lesson(string $slug = ''): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        $userId    = $_SESSION['user_id'] ?? null;
        $hasAccess = !empty($userId) && $this->hasActiveSubscription((int) $userId);

        $lesson = null;
        try {
            $lesson = Database::getInstance()->get('academy_lessons', '*', ['slug' => $slug, 'is_published' => 1]);
        } catch (\Throwable $e) {}
        if (!is_array($lesson)) { $this->redirect('/academy/learn'); return; }

        if (empty($lesson['is_preview']) && !$hasAccess) {
            $this->redirect('/academy#pricing');   // locked → membership (on the landing)
            return;
        }
        View::view('academy/lesson', [
            'title'      => ($lesson['title'] ?? 'Lesson') . ' — Ginto Trading Academy',
            'lesson'     => $lesson,
            'hasAccess'  => $hasAccess,
            'lessons'    => $this->publishedLessons(),
        ]);
    }

    /** GET /academy/admin — admin editor for plan prices + lessons. */
    public function admin(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        if (!$this->isAdmin()) { $this->redirect('/academy'); return; }
        $db = Database::getInstance();
        View::view('academy/admin', [
            'title'      => 'Academy Admin',
            'plans'      => $this->subscriptionPlans(),
            'lessons'    => (function () use ($db) { try { $r = $db->select('academy_lessons', '*', ['ORDER' => ['sort_order' => 'ASC']]); return is_array($r) ? $r : []; } catch (\Throwable $e) { return []; } })(),
            'editLesson' => (function () use ($db) { $id = (int) ($_GET['edit'] ?? 0); if (!$id) return null; try { $r = $db->get('academy_lessons', '*', ['id' => $id]); return is_array($r) ? $r : null; } catch (\Throwable $e) { return null; } })(),
            'csrf_token' => function_exists('generateCsrfToken') ? generateCsrfToken(true) : ($_SESSION['csrf_token'] ?? ''),
        ]);
    }

    /** POST /academy/admin/save — save plan prices or create/update a lesson (admin only). */
    public function adminSave(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        if (!$this->isAdmin()) { http_response_code(403); echo 'Forbidden'; exit; }
        $token = $_POST['csrf_token'] ?? '';
        if (empty($_SESSION['csrf_token']) || !hash_equals((string) $_SESSION['csrf_token'], (string) $token)) {
            http_response_code(403); echo 'Invalid CSRF'; exit;
        }
        $db = Database::getInstance();
        $action = (string) ($_POST['action'] ?? '');
        try {
            if ($action === 'plans') {
                foreach (['academy_trader', 'academy_pro'] as $name) {
                    $price = (float) ($_POST['price_' . $name] ?? 0);
                    $disp  = trim((string) ($_POST['display_' . $name] ?? ''));
                    $desc  = trim((string) ($_POST['desc_' . $name] ?? ''));
                    $upd = [];
                    if ($price > 0) $upd['price_monthly'] = $price;
                    if ($disp !== '') $upd['display_name'] = mb_substr($disp, 0, 100);
                    if ($desc !== '') $upd['description'] = $desc;
                    if ($upd) $db->update('subscription_plans', $upd, ['name' => $name, 'plan_type' => 'academy']);
                }
            } elseif ($action === 'lesson') {
                $data = [
                    'module'       => mb_substr(trim((string) ($_POST['module'] ?? 'Foundations')), 0, 80),
                    'title'        => mb_substr(trim((string) ($_POST['ltitle'] ?? '')), 0, 160),
                    'summary'      => mb_substr(trim((string) ($_POST['summary'] ?? '')), 0, 400),
                    'body'         => (string) ($_POST['body'] ?? ''),
                    'video_url'    => mb_substr(trim((string) ($_POST['video_url'] ?? '')), 0, 300),
                    'tier'         => in_array($_POST['tier'] ?? '', ['free', 'trader', 'pro'], true) ? $_POST['tier'] : 'trader',
                    'is_preview'   => !empty($_POST['is_preview']) ? 1 : 0,
                    'is_published' => !empty($_POST['is_published']) ? 1 : 0,
                    'sort_order'   => (int) ($_POST['sort_order'] ?? 0),
                ];
                if ($data['title'] === '') { $this->redirect('/academy/admin?err=title'); return; }
                $id = (int) ($_POST['id'] ?? 0);
                if ($id > 0) {
                    $db->update('academy_lessons', $data, ['id' => $id]);
                } else {
                    $base = preg_replace('/[^a-z0-9]+/', '-', strtolower($data['title']));
                    $data['slug'] = trim($base, '-') . '-' . substr((string) time(), -4);
                    $db->insert('academy_lessons', $data);
                }
            }
        } catch (\Throwable $e) {
            error_log('Academy adminSave error: ' . $e->getMessage());
        }
        $this->redirect('/academy/admin?saved=1');
    }

    /**
     * GET /api/academy/movers — top gainer, popular (BTC), top loser with recent closes,
     * fetched SERVER-SIDE (reliable) and cached ~60s. Powers the homepage/banner charts.
     */
    public function movers(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $cacheFile = (defined('STORAGE_PATH') ? STORAGE_PATH : sys_get_temp_dir()) . '/academy_movers.json';
        if (is_file($cacheFile) && (time() - filemtime($cacheFile) < 60)) {
            echo (string) file_get_contents($cacheFile); exit;
        }
        $out = [];
        try {
            $client = new \Ginto\Services\BinanceClient();
            $res = $client->allTickers24hr();
            if (!empty($res['ok']) && is_array($res['data'] ?? null)) {
                $stable = ['USDC','FDUSD','TUSD','BUSD','DAI','EUR','GBP','USD1','AEUR'];
                $rows = [];
                foreach ($res['data'] as $r) {
                    $sym = $r['symbol'] ?? '';
                    if (!str_ends_with($sym, 'USDT')) continue;
                    $base = substr($sym, 0, -4);
                    if ($base === '' || preg_match('/(UP|DOWN|BULL|BEAR)$/', $base) || in_array($base, $stable, true)) continue;
                    if ((float) ($r['quoteVolume'] ?? 0) < 30000000) continue;
                    $rows[] = ['symbol' => $sym, 'base' => $base, 'chg' => (float) ($r['priceChangePercent'] ?? 0)];
                }
                if (count($rows) >= 3) {
                    usort($rows, static fn($a, $b) => $b['chg'] <=> $a['chg']);
                    $popular = null;
                    foreach ($rows as $rr) { if ($rr['symbol'] === 'BTCUSDT') { $popular = $rr; break; } }
                    if (!$popular) $popular = $rows[intdiv(count($rows), 2)];
                    $picks = [
                        ['tag' => 'TOP GAINER'] + $rows[0],
                        ['tag' => 'POPULAR'] + $popular,
                        ['tag' => 'TOP LOSER'] + $rows[count($rows) - 1],
                    ];
                    $klines = $client->klinesMulti(array_column($picks, 'symbol'), '15m', 48);
                    foreach ($picks as $p) {
                        $out[] = [
                            'symbol' => $p['symbol'], 'base' => $p['base'], 'tag' => $p['tag'],
                            'chg'    => round($p['chg'], 2),
                            'closes' => array_map('floatval', $klines[$p['symbol']] ?? []),
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('Academy movers error: ' . $e->getMessage());
        }
        $json = json_encode(['ok' => !empty($out), 'movers' => $out]);
        if (!empty($out)) @file_put_contents($cacheFile, $json);
        echo $json;
        exit;
    }

    /** Published lessons for the facility, ordered. */
    private function publishedLessons(): array
    {
        try {
            $rows = Database::getInstance()->select('academy_lessons',
                ['id', 'module', 'title', 'slug', 'summary', 'tier', 'is_preview', 'sort_order'],
                ['is_published' => 1, 'ORDER' => ['sort_order' => 'ASC']]);
            return is_array($rows) ? $rows : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Ensure the per-user wallet + positions schema exists (idempotent). */
    private function ensureTradingSchema(): void
    {
        $db = Database::getInstance();
        $db->pdo->exec("CREATE TABLE IF NOT EXISTS academy_wallets (
            user_id INT PRIMARY KEY,
            balance DECIMAL(18,8) NOT NULL DEFAULT 10000,
            start_balance DECIMAL(18,8) NOT NULL DEFAULT 10000,
            bot_enabled TINYINT NOT NULL DEFAULT 0,
            bot_since DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        foreach (['bot_enabled TINYINT NOT NULL DEFAULT 0', 'bot_since DATETIME NULL'] as $col) {
            try { $db->pdo->exec("ALTER TABLE academy_wallets ADD COLUMN IF NOT EXISTS $col"); } catch (\Throwable $e) {}
        }
        $db->pdo->exec("CREATE TABLE IF NOT EXISTS academy_positions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            ref_trade_id INT NULL,
            symbol VARCHAR(32) NOT NULL,
            base VARCHAR(24) NOT NULL,
            qty DECIMAL(28,10) NOT NULL,
            entry DECIMAL(28,10) NOT NULL,
            spent DECIMAL(18,8) NOT NULL,
            stop_loss DECIMAL(28,10) NULL,
            take_profit DECIMAL(28,10) NULL,
            template VARCHAR(32) NULL,
            status VARCHAR(8) NOT NULL DEFAULT 'open',
            exit_price DECIMAL(28,10) NULL,
            realized DECIMAL(18,8) NULL,
            opened_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            closed_at DATETIME NULL,
            INDEX idx_user_status (user_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    /**
     * Each member gets their own $10,000 paper wallet (the practice balance). Lazily creates the
     * schema + the member's row on first touch.
     */
    private function walletFor(int $userId): array
    {
        try {
            $this->ensureTradingSchema();
            $db = Database::getInstance();
            $w = $db->get('academy_wallets', '*', ['user_id' => $userId]);
            if (!is_array($w)) {
                $db->insert('academy_wallets', ['user_id' => $userId, 'balance' => 10000, 'start_balance' => 10000]);
                $w = ['balance' => 10000, 'start_balance' => 10000, 'bot_enabled' => 0, 'bot_since' => null];
            }
            return [
                'balance' => (float) $w['balance'], 'starting' => (float) $w['start_balance'],
                'bot_enabled' => (int) ($w['bot_enabled'] ?? 0), 'bot_since' => $w['bot_since'] ?? null,
            ];
        } catch (\Throwable $e) {
            error_log('Academy walletFor: ' . $e->getMessage());
            return ['balance' => 10000.0, 'starting' => 10000.0, 'bot_enabled' => 0, 'bot_since' => null];
        }
    }

    /**
     * Follow-the-bot sync: mirror each of the class bot's open PAPER trades into this learner's wallet
     * (enter at the bot's entry, fixed $ size), and close a mirror when the bot closes — realizing P&L
     * into the wallet. Runs on each wallet poll; no per-user AI, so it stays token-free.
     */
    private function reconcileBot(int $userId, array $wallet): array
    {
        $set = $this->botSettings($userId);
        $UNIT = max(10.0, min(2000.0, (float) $set['trade_size']));   // paper $ per mirrored trade
        $SLOTS = max(1, min(20, (int) $set['max_slots']));            // max concurrent mirrors
        $enabledTpls = array_flip(array_filter(array_map('trim', explode(',', (string) $set['templates']))));
        $db = Database::getInstance();
        $client = new \Ginto\Services\BinanceClient();
        $balance = (float) $wallet['balance'];
        $enabled = !empty($wallet['bot_enabled']);

        $botOpen = [];
        try { foreach ((new \Ginto\Models\GtbTrade())->openPositions('paper') as $p) { $botOpen[(int) $p['id']] = $p; } } catch (\Throwable $e) {}

        // 1) Close mirrors whose bot trade has closed — realize at the bot's exit (or live mark).
        $open = $db->select('academy_positions', '*', ['user_id' => $userId, 'status' => 'open']);
        if (!is_array($open)) $open = [];
        foreach ($open as $u) {
            $ref = $u['ref_trade_id'] !== null ? (int) $u['ref_trade_id'] : 0;
            if ($ref && !isset($botOpen[$ref])) {
                $bt = $db->get('gtb_trades', ['exit_price'], ['id' => $ref]);
                $exit = (is_array($bt) && $bt['exit_price'] !== null) ? (float) $bt['exit_price'] : (float) ($client->price($u['symbol']) ?? $u['entry']);
                if ($exit <= 0) $exit = (float) $u['entry'];
                $proceeds = (float) $u['qty'] * $exit; $balance += $proceeds;
                $db->update('academy_positions', ['status' => 'closed', 'exit_price' => $exit, 'realized' => round($proceeds - (float) $u['spent'], 8), 'closed_at' => date('Y-m-d H:i:s')], ['id' => $u['id']]);
            }
        }

        // 2) Open a mirror for each bot trade we don't hold yet.
        if ($enabled) {
            $open = $db->select('academy_positions', '*', ['user_id' => $userId, 'status' => 'open']);
            if (!is_array($open)) $open = [];
            $held = []; foreach ($open as $u) { if ($u['ref_trade_id'] !== null) $held[(int) $u['ref_trade_id']] = true; }
            $slots = $SLOTS - count($open);
            foreach ($botOpen as $id => $p) {
                if ($slots <= 0 || $balance < $UNIT) break;
                if (isset($held[$id])) continue;
                // Only follow templates the learner enabled in their settings.
                if ($enabledTpls && !isset($enabledTpls[$p['template'] ?? ''])) continue;
                $entry = (float) $p['price']; if ($entry <= 0) continue;
                $db->insert('academy_positions', [
                    'user_id' => $userId, 'ref_trade_id' => $id, 'symbol' => $p['symbol'], 'base' => substr($p['symbol'], 0, -4),
                    'qty' => $UNIT / $entry, 'entry' => $entry, 'spent' => $UNIT,
                    'stop_loss' => $p['stop_loss'] ?? null, 'take_profit' => $p['take_profit'] ?? null, 'template' => $p['template'] ?? null, 'status' => 'open',
                ]);
                $balance -= $UNIT; $slots--;
            }
        }

        if (abs($balance - (float) $wallet['balance']) > 1e-9) {
            $db->update('academy_wallets', ['balance' => round($balance, 8)], ['user_id' => $userId]);
        }

        // 3) Live positions view.
        $open = $db->select('academy_positions', '*', ['user_id' => $userId, 'status' => 'open', 'ORDER' => ['id' => 'DESC']]);
        if (!is_array($open)) $open = [];
        $positions = []; $unreal = 0.0; $marks = 0.0;
        foreach ($open as $u) {
            $entry = (float) $u['entry']; $qty = (float) $u['qty'];
            $mark = (float) ($client->price($u['symbol']) ?? $entry); if ($mark <= 0) $mark = $entry;
            $pnl = ($mark - $entry) * $qty; $unreal += $pnl; $marks += $mark * $qty;
            $positions[] = [
                'id' => (int) $u['id'], 'symbol' => $u['symbol'], 'base' => $u['base'], 'template' => $u['template'],
                'auto' => $u['ref_trade_id'] !== null,   // true = bot-followed, false = manual
                'entry' => $entry, 'mark' => $mark, 'qty' => $qty, 'spent' => (float) $u['spent'],
                'stop_loss' => $u['stop_loss'] !== null ? (float) $u['stop_loss'] : null,
                'take_profit' => $u['take_profit'] !== null ? (float) $u['take_profit'] : null,
                'pnlPct' => $entry > 0 ? ($mark - $entry) / $entry * 100 : 0, 'unrealized' => round($pnl, 4),
            ];
        }
        return ['balance' => round($balance, 8), 'positions' => $positions, 'unrealized' => round($unreal, 4), 'equity' => round($balance + $marks, 4)];
    }

    /** GET /academy/wallet — this learner's own paper wallet + live follow-the-bot positions. */
    public function wallet(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $userId = $this->requireMemberJson();
        $w = $this->walletFor($userId);
        $r = $this->reconcileBot($userId, $w);
        echo json_encode([
            'ok' => true, 'balance' => $r['balance'], 'starting' => $w['starting'], 'equity' => $r['equity'],
            'unrealized' => $r['unrealized'], 'bot_enabled' => (bool) $w['bot_enabled'], 'positions' => $r['positions'],
        ]);
        exit;
    }

    /** POST /academy/bot/toggle — start/stop this learner's follow-the-bot trading. */
    public function botToggle(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $userId = $this->requireMemberJson();
        $this->ensureTradingSchema();
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $on = !empty($input['on']);
        $db = Database::getInstance();
        try {
            if ($on) {
                $db->update('academy_wallets', ['bot_enabled' => 1, 'bot_since' => date('Y-m-d H:i:s')], ['user_id' => $userId]);
            } else {
                // Stop = flatten every open mirror at the current mark, then disable.
                $client = new \Ginto\Services\BinanceClient();
                $w = $this->walletFor($userId); $balance = (float) $w['balance'];
                // Only flatten BOT-followed positions (ref_trade_id set). Manual trades stay open.
                $open = $db->select('academy_positions', '*', ['user_id' => $userId, 'status' => 'open', 'ref_trade_id[!]' => null]);
                if (is_array($open)) foreach ($open as $u) {
                    $mark = (float) ($client->price($u['symbol']) ?? $u['entry']); if ($mark <= 0) $mark = (float) $u['entry'];
                    $proceeds = (float) $u['qty'] * $mark; $balance += $proceeds;
                    $db->update('academy_positions', ['status' => 'closed', 'exit_price' => $mark, 'realized' => round($proceeds - (float) $u['spent'], 8), 'closed_at' => date('Y-m-d H:i:s')], ['id' => $u['id']]);
                }
                $db->update('academy_wallets', ['bot_enabled' => 0, 'balance' => round($balance, 8)], ['user_id' => $userId]);
            }
            echo json_encode(['ok' => true, 'bot_enabled' => $on]);
        } catch (\Throwable $e) {
            error_log('Academy botToggle: ' . $e->getMessage());
            echo json_encode(['ok' => false, 'error' => 'Could not update the bot.']);
        }
        exit;
    }

    /** POST /academy/trade/buy — open a MANUAL paper position on the charted coin (all members). */
    public function tradeBuy(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $userId = $this->requireMemberJson();
        $this->ensureTradingSchema();
        $input  = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $symbol = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($input['symbol'] ?? ''))) ?: '';
        if ($symbol === '') { echo json_encode(['ok' => false, 'error' => 'Pick a coin first.']); exit; }
        if (!str_ends_with($symbol, 'USDT')) $symbol .= 'USDT';
        $base   = substr($symbol, 0, -4);
        $amount = round((float) ($input['amount'] ?? 0), 2);
        try {
            $w = $this->walletFor($userId); $balance = (float) $w['balance'];
            if ($amount < 10) { echo json_encode(['ok' => false, 'error' => 'Minimum trade is $10.']); exit; }
            if ($amount > $balance) { echo json_encode(['ok' => false, 'error' => 'Not enough paper balance ($' . number_format($balance, 2) . ').']); exit; }
            $price = (float) ((new \Ginto\Services\BinanceClient())->price($symbol) ?? 0);
            if ($price <= 0) { echo json_encode(['ok' => false, 'error' => 'Could not get a live price for ' . $base . '.']); exit; }
            $db = Database::getInstance();
            $db->insert('academy_positions', [
                'user_id' => $userId, 'ref_trade_id' => null, 'symbol' => $symbol, 'base' => $base,
                'qty' => $amount / $price, 'entry' => $price, 'spent' => $amount, 'template' => 'manual', 'status' => 'open',
            ]);
            $db->update('academy_wallets', ['balance' => round($balance - $amount, 8)], ['user_id' => $userId]);
            echo json_encode(['ok' => true, 'symbol' => $symbol, 'base' => $base, 'entry' => $price, 'spent' => $amount, 'balance' => round($balance - $amount, 8)]);
        } catch (\Throwable $e) {
            error_log('Academy tradeBuy: ' . $e->getMessage());
            echo json_encode(['ok' => false, 'error' => 'Could not place the trade.']);
        }
        exit;
    }

    /** POST /academy/trade/sell — close a MANUAL paper position at the live mark (all members). */
    public function tradeSell(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $userId = $this->requireMemberJson();
        $this->ensureTradingSchema();
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $id    = (int) ($input['id'] ?? 0);
        try {
            $db = Database::getInstance();
            // Manual positions only (ref_trade_id NULL) — bot-followed trades are managed by the bot.
            $p = $db->get('academy_positions', '*', ['id' => $id, 'user_id' => $userId, 'status' => 'open', 'ref_trade_id' => null]);
            if (!is_array($p)) { echo json_encode(['ok' => false, 'error' => 'Trade not found (or it is bot-managed).']); exit; }
            $mark = (float) ((new \Ginto\Services\BinanceClient())->price($p['symbol']) ?? $p['entry']);
            if ($mark <= 0) $mark = (float) $p['entry'];
            $proceeds = (float) $p['qty'] * $mark; $realized = $proceeds - (float) $p['spent'];
            $w = $this->walletFor($userId);
            $db->update('academy_positions', ['status' => 'closed', 'exit_price' => $mark, 'realized' => round($realized, 8), 'closed_at' => date('Y-m-d H:i:s')], ['id' => $id]);
            $db->update('academy_wallets', ['balance' => round((float) $w['balance'] + $proceeds, 8)], ['user_id' => $userId]);
            echo json_encode(['ok' => true, 'realized' => round($realized, 4), 'balance' => round((float) $w['balance'] + $proceeds, 8)]);
        } catch (\Throwable $e) {
            error_log('Academy tradeSell: ' . $e->getMessage());
            echo json_encode(['ok' => false, 'error' => 'Could not close the trade.']);
        }
        exit;
    }

    /** The active plan's machine name for this user ('academy_pro', 'academy_trader', or ''). */
    private function planName(int $userId): string
    {
        try {
            $db  = Database::getInstance();
            $sub = $db->get('user_subscriptions', ['plan_id', 'expires_at'], ['user_id' => $userId, 'status' => 'active', 'ORDER' => ['id' => 'DESC']]);
            if (!is_array($sub)) return '';
            $exp = $sub['expires_at'] ?? null;
            if (!empty($exp) && strtotime((string) $exp) <= time()) return '';
            $plan = $db->get('subscription_plans', ['name'], ['id' => $sub['plan_id']]);
            return is_array($plan) ? (string) ($plan['name'] ?? '') : '';
        } catch (\Throwable $e) { return ''; }
    }

    private function isPro(int $userId): bool { return $this->planName($userId) === 'academy_pro'; }

    private const DEFAULT_TEMPLATES = 'gainers,scalp,breakout,trend,pullback';

    private function ensureBotSettingsTable(): void
    {
        Database::getInstance()->pdo->exec("CREATE TABLE IF NOT EXISTS academy_bot_settings (
            user_id INT PRIMARY KEY,
            templates VARCHAR(255) NOT NULL DEFAULT '" . self::DEFAULT_TEMPLATES . "',
            trade_size DECIMAL(18,8) NOT NULL DEFAULT 200,
            max_slots INT NOT NULL DEFAULT 8,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    /** Per-user, DB-driven bot settings (not .env). Returns defaults if unset. */
    private function botSettings(int $userId): array
    {
        $defaults = ['templates' => self::DEFAULT_TEMPLATES, 'trade_size' => 200.0, 'max_slots' => 8];
        try {
            $this->ensureBotSettingsTable();
            $r = Database::getInstance()->get('academy_bot_settings', '*', ['user_id' => $userId]);
            if (!is_array($r)) return $defaults;
            return [
                'templates'  => (string) ($r['templates'] ?? $defaults['templates']),
                'trade_size' => (float) ($r['trade_size'] ?? 200),
                'max_slots'  => (int) ($r['max_slots'] ?? 8),
            ];
        } catch (\Throwable $e) { return $defaults; }
    }

    /** GET /academy/settings — per-user, DB-driven bot settings (templates, size, slots). Pro-gated. */
    public function settings(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        $userId = $_SESSION['user_id'] ?? null;
        if (empty($userId)) { $this->redirect('/login?redirect=' . urlencode('/academy/settings')); return; }
        if (!$this->hasActiveSubscription((int) $userId)) { $this->redirect('/academy#pricing'); return; }
        $csrf = function_exists('generateCsrfToken') ? generateCsrfToken(true) : ($_SESSION['csrf_token'] ?? '');
        View::view('academy/settings', [
            'title'      => 'Bot Settings — Ginto Trading Academy',
            'isPro'      => $this->isPro((int) $userId),
            'catalog'    => \Ginto\Services\GtbStrategy::catalog(),
            'settings'   => $this->botSettings((int) $userId),
            'csrf_token' => $csrf,
        ]);
    }

    /** POST /academy/settings/save — persist per-user bot settings to the DB. Pro-gated. */
    public function settingsSave(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $userId = $this->requireMemberJson();
        if (!$this->isPro($userId)) { echo json_encode(['ok' => false, 'error' => 'Bot settings are a Pro Trader feature. Upgrade to configure your own strategy.']); exit; }
        $this->ensureBotSettingsTable();
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $valid = array_keys(\Ginto\Services\GtbStrategy::catalog());
        $tpls  = array_values(array_intersect($valid, (array) ($input['templates'] ?? [])));
        if (!$tpls) $tpls = $valid;
        $size  = max(10.0, min(2000.0, (float) ($input['trade_size'] ?? 200)));
        $slots = max(1, min(20, (int) ($input['max_slots'] ?? 8)));
        $row   = ['templates' => implode(',', $tpls), 'trade_size' => $size, 'max_slots' => $slots];
        try {
            $db = Database::getInstance();
            if (is_array($db->get('academy_bot_settings', ['user_id'], ['user_id' => $userId]))) {
                $db->update('academy_bot_settings', $row, ['user_id' => $userId]);
            } else {
                $db->insert('academy_bot_settings', array_merge(['user_id' => $userId], $row));
            }
            echo json_encode(['ok' => true, 'settings' => $row]);
        } catch (\Throwable $e) {
            error_log('Academy settingsSave: ' . $e->getMessage());
            echo json_encode(['ok' => false, 'error' => 'Could not save settings.']);
        }
        exit;
    }

    private function hasActiveSubscription(int $userId): bool
    {
        try {
            $db = Database::getInstance();
            // Mirrors CourseController::getUserSubscription (user_subscriptions + expiry).
            $sub = $db->get('user_subscriptions', ['status', 'expires_at'], [
                'user_id' => $userId,
                'status'  => 'active',
                'ORDER'   => ['id' => 'DESC'],
            ]);
            if (!is_array($sub)) return false;
            $exp = $sub['expires_at'] ?? null;
            return empty($exp) || strtotime((string) $exp) > time();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** GET /academy/pricing — retired. Membership now lives on the landing; keep the URL alive. */
    public function pricing(): void
    {
        $this->redirect('/academy#pricing');
    }

    /**
     * POST /academy/join — the standalone Academy sign-up + checkout. Guests create an account
     * right here (shared DB, no /register detour) and are logged in; logged-in users just get a
     * new order. Either way we open a PayMongo checkout for the chosen plan.
     */
    public function join(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();

        $planName = (string) ($_POST['plan'] ?? '');
        try {
            $db   = Database::getInstance();
            $plan = $db->get('subscription_plans', '*', ['name' => $planName, 'plan_type' => 'academy', 'is_active' => 1]);
            if (!$plan) { $this->redirect('/academy?err=plan#pricing'); return; }

            $userId = $_SESSION['user_id'] ?? null;

            // Guest: register-and-pay without ever leaving the Academy.
            if (empty($userId)) {
                $name  = trim((string) ($_POST['name'] ?? ''));
                $email = strtolower(trim((string) ($_POST['email'] ?? '')));
                $pass  = (string) ($_POST['password'] ?? '');
                if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 6) {
                    $this->redirect('/academy?err=form#pricing'); return;
                }
                if (is_array($db->get('users', ['id'], ['email' => $email]))) {
                    // Existing account — send them to log in, then straight back to checkout.
                    $this->redirect('/login?redirect=' . urlencode('/academy/subscribe?plan=' . $planName)); return;
                }
                $userId = $this->createLearner($name, $email, $pass);
                if (!$userId) { $this->redirect('/academy?err=signup#pricing'); return; }
                $created = $db->get('users', '*', ['id' => $userId]);
                if (is_array($created)) $this->loginSession($created);
            }

            $this->startCheckout((int) $userId, $plan);
        } catch (\Throwable $e) {
            error_log('Academy join error: ' . $e->getMessage());
            $this->redirect('/academy?err=1#pricing');
        }
    }

    /**
     * GET /academy/subscribe?plan=academy_pro — one-click checkout for a logged-in member
     * (used after login-then-return). Guests are sent to the on-page sign-up.
     */
    public function subscribe(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        $userId = $_SESSION['user_id'] ?? null;
        if (empty($userId)) { $this->redirect('/academy#pricing'); return; }

        $planName = (string) ($_GET['plan'] ?? '');
        try {
            $db   = Database::getInstance();
            $plan = $db->get('subscription_plans', '*', ['name' => $planName, 'plan_type' => 'academy', 'is_active' => 1]);
            if (!$plan) { $this->redirect('/academy#pricing'); return; }
            $this->startCheckout((int) $userId, $plan);
        } catch (\Throwable $e) {
            error_log('Academy subscribe error: ' . $e->getMessage());
            $this->redirect('/academy?err=1#pricing');
        }
    }

    /** Create + open a PayMongo hosted checkout for a user and record a pending academy_order. */
    private function startCheckout(int $userId, array $plan): void
    {
        try {
            $db    = Database::getInstance();
            $user  = $db->get('users', ['email', 'fullname', 'username'], ['id' => $userId]);
            $email = $user['email'] ?? '';
            $name  = ($user['fullname'] ?? '') !== '' ? $user['fullname'] : ($user['username'] ?? 'Ginto Learner');
            if ($email === '') { $this->redirect('/academy?err=email#pricing'); return; }

            $vat    = $this->vatBreakdown((float) $plan['price_monthly']);
            $amount = (int) round($vat['total'] * 100); // centavos, VAT-inclusive
            $base   = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'ginto.ai');

            $pm  = new \Ginto\Handlers\PayMongoHandler();
            $res = $pm->createCheckoutSession(
                $amount,
                'Ginto Trading Academy — ' . ($plan['display_name'] ?? 'Membership') . sprintf(' (₱%s + 12%% VAT ₱%s)', number_format($vat['base'], 0), number_format($vat['vat'], 0)),
                (string) $name,
                (string) $email,
                $base . '/academy/subscribe/success',
                $base . '/academy#pricing'
            );
            if (empty($res['success']) || empty($res['checkout_url'])) {
                error_log('Academy checkout create failed: ' . json_encode($res));
                $this->redirect('/academy?err=checkout#pricing'); return;
            }

            $db->insert('academy_orders', [
                'user_id'             => $userId,
                'plan_id'             => $plan['id'],
                'checkout_session_id' => $res['session_id'] ?? '',
                'amount'              => $vat['total'],
                'currency'            => $plan['price_currency'] ?? 'PHP',
                'status'              => 'pending',
            ]);
            $this->redirect($res['checkout_url']);
        } catch (\Throwable $e) {
            error_log('Academy startCheckout error: ' . $e->getMessage());
            $this->redirect('/academy?err=1#pricing');
        }
    }

    /** Create a new learner in the shared users table. Returns the new user id, or null on failure. */
    private function createLearner(string $name, string $email, string $pass): ?int
    {
        return $this->insertLearner($name, $email, password_hash($pass, PASSWORD_DEFAULT));
    }

    /** Insert a learner given an already-hashed password (used by the on-site payment finalize). */
    private function insertLearner(string $name, string $email, string $passwordHash): ?int
    {
        try {
            $db   = Database::getInstance();
            $seed = preg_replace('/[^a-z0-9]+/', '', strtolower(explode('@', $email)[0])) ?: 'trader';
            $username = substr($seed, 0, 18) . mt_rand(100, 9999);
            for ($i = 0; $i < 6 && is_array($db->get('users', ['id'], ['username' => $username])); $i++) {
                $username = substr($seed, 0, 14) . mt_rand(1000, 999999);
            }
            $db->insert('users', [
                'public_id'     => substr(md5(uniqid((string) mt_rand(), true)), 0, 12),
                'fullname'      => mb_substr($name, 0, 100),
                'username'      => $username,
                'email'         => $email,
                'password_hash' => $passwordHash,
                'status'        => 'active',
                'role_id'       => 5,
                'created_at'    => date('Y-m-d H:i:s'),
            ]);
            $id = $db->id();
            return $id ? (int) $id : null;
        } catch (\Throwable $e) {
            error_log('Academy insertLearner error: ' . $e->getMessage());
            return null;
        }
    }

    /** Record the paid order and grant/refresh the Academy membership (on-site QRPh / card path). */
    private function activateMembership(int $userId, array $plan, string $ref, ?string $gatewayPaymentId, float $amount, string $currency, string $method = 'paymongo_qrph', bool $autoRenew = false): void
    {
        $db      = Database::getInstance();
        $now     = date('Y-m-d H:i:s');
        $expires = date('Y-m-d H:i:s', strtotime('+1 month'));
        $db->insert('academy_orders', [
            'user_id'             => $userId,
            'plan_id'             => $plan['id'],
            'checkout_session_id' => $ref,
            'amount'              => $amount,
            'currency'            => $currency,
            'status'              => 'completed',
        ]);
        $db->update('user_subscriptions',
            ['status' => 'cancelled', 'cancelled_at' => $now, 'updated_at' => $now],
            ['user_id' => $userId, 'status' => 'active']);
        $db->insert('user_subscriptions', [
            'user_id'            => $userId,
            'plan_id'            => $plan['id'],
            'status'             => 'active',
            'started_at'         => $now,
            'expires_at'         => $expires,
            'payment_method'     => $method,
            'payment_reference'  => $ref,
            'gateway_payment_id' => $gatewayPaymentId,
            'amount_paid'        => $amount,
            'currency'           => $currency,
            'auto_renew'         => $autoRenew ? 1 : 0,
            'created_at'         => $now,
            'updated_at'         => $now,
        ]);
        error_log("Academy membership activated on-site: user={$userId} plan={$plan['id']} method={$method} pi={$ref}");

        // Payment is confirmed and access granted — send the welcome/receipt email. Never let a
        // mail failure break activation (the membership is already live either way).
        try { $this->sendMembershipEmail((int) $userId, $plan, (float) $amount, (string) $currency, $expires); }
        catch (\Throwable $e) { error_log('Academy membership email error: ' . $e->getMessage()); }
    }

    /** Send the post-payment confirmation / welcome email via the configured SMTP (MailHelper). */
    private function sendMembershipEmail(int $userId, array $plan, float $amount, string $currency, string $expires): void
    {
        $db   = Database::getInstance();
        $user = $db->get('users', ['email', 'fullname', 'username'], ['id' => $userId]);
        $email = $user['email'] ?? '';
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) return;
        $name  = ($user['fullname'] ?? '') !== '' ? $user['fullname'] : ($user['username'] ?? 'there');
        $plName = htmlspecialchars($plan['display_name'] ?? ($plan['name'] ?? 'Membership'));
        $amt   = htmlspecialchars($currency . ' ' . number_format($amount, 2));
        $exp   = htmlspecialchars(date('M j, Y', strtotime($expires)));
        $base  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'ginto.ai');

        $html = '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:520px;margin:auto;color:#1f2937;">'
              . '<div style="background:linear-gradient(135deg,#4f46e5,#8b5cf6);border-radius:14px 14px 0 0;padding:22px 24px;color:#fff;">'
              . '<div style="font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;opacity:.85;">🎓 Ginto Trading Academy</div>'
              . '<div style="font-size:20px;font-weight:800;margin-top:6px;">Payment confirmed — welcome aboard!</div></div>'
              . '<div style="border:1px solid #e5e7eb;border-top:none;border-radius:0 0 14px 14px;padding:22px 24px;">'
              . '<p>Hi ' . htmlspecialchars($name) . ',</p>'
              . '<p>Your <strong>' . $plName . '</strong> membership is now <strong>active</strong>. Thank you for joining!</p>'
              . '<table style="width:100%;border-collapse:collapse;font-size:14px;margin:16px 0;">'
              . '<tr><td style="padding:6px 0;color:#6b7280;">Plan</td><td style="padding:6px 0;text-align:right;font-weight:600;">' . $plName . '</td></tr>'
              . '<tr><td style="padding:6px 0;color:#6b7280;">Amount paid</td><td style="padding:6px 0;text-align:right;font-weight:600;">' . $amt . '</td></tr>'
              . '<tr><td style="padding:6px 0;color:#6b7280;">Access until</td><td style="padding:6px 0;text-align:right;font-weight:600;">' . $exp . '</td></tr>'
              . '</table>'
              . '<a href="' . $base . '/academy/learn" style="display:inline-block;background:#4f46e5;color:#fff;text-decoration:none;font-weight:700;padding:11px 20px;border-radius:10px;">Start learning →</a>'
              . '<p style="font-size:13px;color:#6b7280;margin-top:18px;">Log in any time at <a href="' . $base . '/login">' . htmlspecialchars(preg_replace('#^https?://#', '', $base)) . '/login</a> with this email. This is a one-time payment; it does not auto-renew.</p>'
              . '<p style="font-size:12px;color:#9ca3af;margin-top:16px;">Educational only — crypto trading carries real risk of loss.</p>'
              . '</div></div>';
        $text = "Hi {$name},\n\nYour {$plan['display_name']} membership is now active. Amount paid: {$amt}. Access until: {$exp}.\n\nStart learning: {$base}/academy/learn\nLog in with this email at {$base}/login.\n\nEducational only — crypto trading carries real risk of loss.";

        \Ginto\Helpers\MailHelper::send($email, 'Your Ginto Trading Academy membership is active', $html, $text);
    }

    /**
     * VAT breakdown for a plan's base price. Tax is added ON TOP (VAT-exclusive) at the rate in
     * ACADEMY_VAT_PCT (default 12%). Returns whole-peso base/vat/total for display + billing.
     */
    private function vatBreakdown(float $base): array
    {
        $rate  = (float) (\Ginto\Support\Env::get('ACADEMY_VAT_PCT', '12') ?? 12);
        $vat   = round($base * $rate / 100.0, 2);
        return [
            'base'  => round($base, 2),
            'vat'   => $vat,
            'total' => round($base + $vat, 2),
            'rate'  => $rate,
        ];
    }

    /**
     * POST /academy/qrph/init — start an ON-SITE QR Ph payment (no redirect to PayMongo).
     * Creates the payment intent + QR server-side and returns the QR image to render inline.
     * The amount is taken from the plan on the SERVER — never trusted from the client.
     */
    public function qrphInit(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        header('Content-Type: application/json; charset=utf-8');
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); echo json_encode(['success' => false, 'message' => 'Method not allowed']); exit; }
        if (empty($_POST['csrf_token']) || !function_exists('validateCsrfToken') || !validateCsrfToken($_POST['csrf_token'])) {
            http_response_code(403); echo json_encode(['success' => false, 'message' => 'Invalid session token — please refresh.']); exit;
        }
        try {
            $db   = Database::getInstance();
            $plan = $db->get('subscription_plans', '*', ['name' => (string) ($_POST['plan'] ?? ''), 'plan_type' => 'academy', 'is_active' => 1]);
            if (!$plan) { echo json_encode(['success' => false, 'message' => 'That plan is unavailable.']); exit; }

            $userId = $_SESSION['user_id'] ?? null;
            $guest  = null;
            if (empty($userId)) {
                $name  = trim((string) ($_POST['name'] ?? ''));
                $email = strtolower(trim((string) ($_POST['email'] ?? '')));
                $pass  = (string) ($_POST['password'] ?? '');
                if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 6) {
                    echo json_encode(['success' => false, 'message' => 'Enter your name, a valid email, and a 6+ character password.']); exit;
                }
                if (is_array($db->get('users', ['id'], ['email' => $email]))) {
                    echo json_encode(['success' => false, 'message' => 'That email already has an account — please log in.', 'login' => true]); exit;
                }
                $guest    = ['name' => mb_substr($name, 0, 100), 'email' => $email, 'password_hash' => password_hash($pass, PASSWORD_DEFAULT)];
                $payEmail = $email; $payName = $name;
            } else {
                $u = $db->get('users', ['email', 'fullname', 'username'], ['id' => $userId]);
                $payEmail = $u['email'] ?? '';
                $payName  = ($u['fullname'] ?? '') !== '' ? $u['fullname'] : ($u['username'] ?? 'Ginto Learner');
                if ($payEmail === '') { echo json_encode(['success' => false, 'message' => 'Your account has no email on file.']); exit; }
            }

            if (!\Ginto\Handlers\PayMongoHandler::isConfigured()) { echo json_encode(['success' => false, 'message' => 'Payments are not configured.']); exit; }

            $vat     = $this->vatBreakdown((float) $plan['price_monthly']);
            $amount  = (int) round($vat['total']); // whole pesos, VAT-inclusive total charged
            $handler = new \Ginto\Handlers\PayMongoHandler();
            $res     = $handler->initQrph($amount, (string) $payEmail, (string) $payName, '', 'Ginto Trading Academy — ' . ($plan['display_name'] ?? 'Membership') . ' (incl. 12% VAT)');
            if (empty($res['success'])) { echo json_encode(['success' => false, 'message' => $res['message'] ?? 'Could not start the payment.']); exit; }

            $_SESSION['paymongo_pi_id'] = $res['pi_id']; // let the shared status poller accept this PI
            $_SESSION['academy_pay']    = [
                'pi_id'    => $res['pi_id'], 'plan_id' => $plan['id'], 'amount' => $amount,
                'currency' => $plan['price_currency'] ?? 'PHP', 'user_id' => $userId ? (int) $userId : null, 'guest' => $guest,
            ];
            echo json_encode([
                'success' => true, 'pi_id' => $res['pi_id'],
                'qr_image' => $res['qr_image'] ?? null, 'qr_string' => $res['qr_string'] ?? null,
                'amount' => $amount, 'base' => $vat['base'], 'vat' => $vat['vat'], 'vat_rate' => $vat['rate'],
            ]);
            exit;
        } catch (\Throwable $e) {
            error_log('Academy qrphInit error: ' . $e->getMessage());
            http_response_code(500); echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']); exit;
        }
    }

    /**
     * POST /academy/card/init — start an ON-SITE credit/debit card payment (card entered on
     * ginto.ai). Creates the intent, attaches the card, and returns any 3DS/OTP next-action URL
     * to complete inline. Finalization reuses /academy/qrph/finalize (it verifies PI status).
     */
    public function cardInit(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        header('Content-Type: application/json; charset=utf-8');
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); echo json_encode(['success' => false, 'message' => 'Method not allowed']); exit; }
        if (empty($_POST['csrf_token']) || !function_exists('validateCsrfToken') || !validateCsrfToken($_POST['csrf_token'])) {
            http_response_code(403); echo json_encode(['success' => false, 'message' => 'Invalid session token — please refresh.']); exit;
        }
        try {
            $db   = Database::getInstance();
            $plan = $db->get('subscription_plans', '*', ['name' => (string) ($_POST['plan'] ?? ''), 'plan_type' => 'academy', 'is_active' => 1]);
            if (!$plan) { echo json_encode(['success' => false, 'message' => 'That plan is unavailable.']); exit; }

            $userId = $_SESSION['user_id'] ?? null;
            $guest  = null;
            if (empty($userId)) {
                $name  = trim((string) ($_POST['name'] ?? ''));
                $email = strtolower(trim((string) ($_POST['email'] ?? '')));
                $pass  = (string) ($_POST['password'] ?? '');
                if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 6) {
                    echo json_encode(['success' => false, 'message' => 'Enter your name, a valid email, and a 6+ character password.']); exit;
                }
                if (is_array($db->get('users', ['id'], ['email' => $email]))) {
                    echo json_encode(['success' => false, 'message' => 'That email already has an account — please log in.', 'login' => true]); exit;
                }
                $guest    = ['name' => mb_substr($name, 0, 100), 'email' => $email, 'password_hash' => password_hash($pass, PASSWORD_DEFAULT)];
                $payEmail = $email; $payName = $name;
            } else {
                $u = $db->get('users', ['email', 'fullname', 'username'], ['id' => $userId]);
                $payEmail = $u['email'] ?? '';
                $payName  = ($u['fullname'] ?? '') !== '' ? $u['fullname'] : ($u['username'] ?? 'Ginto Learner');
                if ($payEmail === '') { echo json_encode(['success' => false, 'message' => 'Your account has no email on file.']); exit; }
            }

            $card = [
                'number'    => preg_replace('/[^0-9]/', '', (string) ($_POST['card_number'] ?? '')),
                'exp_month' => preg_replace('/[^0-9]/', '', (string) ($_POST['exp_month'] ?? '')),
                'exp_year'  => preg_replace('/[^0-9]/', '', (string) ($_POST['exp_year'] ?? '')),
                'cvc'       => preg_replace('/[^0-9]/', '', (string) ($_POST['cvc'] ?? '')),
            ];
            if (strlen($card['number']) < 13 || strlen($card['number']) > 19) { echo json_encode(['success' => false, 'message' => 'Please enter a valid card number.']); exit; }
            if ((int) $card['exp_month'] < 1 || (int) $card['exp_month'] > 12 || strlen($card['exp_year']) < 2 || strlen($card['cvc']) < 3) {
                echo json_encode(['success' => false, 'message' => 'Please check the card expiry and CVC.']); exit;
            }

            if (!\Ginto\Handlers\PayMongoHandler::isConfigured()) { echo json_encode(['success' => false, 'message' => 'Payments are not configured.']); exit; }

            $vat       = $this->vatBreakdown((float) $plan['price_monthly']);
            $amount    = (int) round($vat['total']); // whole pesos, VAT-inclusive
            $autoRenew = !empty($_POST['auto_renew']);
            $desc      = 'Ginto Trading Academy — ' . ($plan['display_name'] ?? 'Membership') . ' (incl. 12% VAT)';
            $handler   = new \Ginto\Handlers\PayMongoHandler();

            // Try to vault the card for assisted renewal, but NEVER let that block the payment:
            // PayMongo card vaulting ("on_session") may be unavailable, in which case we fall back
            // to a plain one-off charge and just don't enable auto-renew.
            $customerId = null;
            $res = null;
            if ($autoRenew) {
                $parts = preg_split('/\s+/', trim((string) $payName), 2);
                $cust  = $handler->createCustomer($parts[0] ?? 'Ginto', $parts[1] ?? 'Learner', (string) $payEmail, (string) ($_POST['phone'] ?? ''));
                $cid   = $cust['customer_id'] ?? ($cust['existing_id'] ?? null);
                if ($cid) {
                    $vaultRes = $handler->initCardPaymentVault((float) $amount, (string) $payEmail, (string) $payName, '', $desc, $card, $cid, []);
                    if (!empty($vaultRes['success'])) { $res = $vaultRes; $customerId = $cid; }
                }
            }
            if ($res === null) {   // no vault (or vaulting unsupported) — charge one-off
                $scheme    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $returnUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'ginto.ai') . '/academy/card/return';
                $res = $handler->initCardPayment((float) $amount, (string) $payEmail, (string) $payName, '', $desc, $card, [], $returnUrl);
                $autoRenew = false;
            }
            if (empty($res['success'])) { echo json_encode(['success' => false, 'message' => $res['message'] ?? 'The card could not be processed.']); exit; }

            $_SESSION['paymongo_pi_id'] = $res['pi_id']; // shared status poller
            $_SESSION['academy_pay']    = [
                'pi_id'    => $res['pi_id'], 'plan_id' => $plan['id'], 'amount' => $amount,
                'currency' => $plan['price_currency'] ?? 'PHP', 'user_id' => $userId ? (int) $userId : null,
                'guest'    => $guest, 'method' => 'paymongo_card', 'auto_renew' => $autoRenew,
                'customer_id' => $customerId,
            ];
            $nextUrl = $res['next_action']['redirect']['url'] ?? ($res['next_action']['url'] ?? null);
            echo json_encode([
                'success' => true, 'pi_id' => $res['pi_id'], 'status' => $res['status'] ?? 'processing',
                'requires_action' => !empty($nextUrl), 'next_action_url' => $nextUrl,
                'amount' => $amount, 'base' => $vat['base'], 'vat' => $vat['vat'],
            ]);
            exit;
        } catch (\Throwable $e) {
            error_log('Academy cardInit error: ' . $e->getMessage());
            http_response_code(500); echo json_encode(['success' => false, 'message' => 'An error occurred processing the card.']); exit;
        }
    }

    /**
     * GET /academy/card/return — where PayMongo sends the browser back after 3-D Secure. This loads
     * inside the checkout's 3DS iframe; the parent page's status poller does the actual finalize +
     * grant, so this only needs to signal completion and stop the spinner.
     */
    public function cardReturn(): void
    {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>Verification complete</title><style>body{font-family:system-ui,-apple-system,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#0b1020;color:#e5e7eb}'
            . '.c{text-align:center;padding:24px}.d{width:44px;height:44px;border-radius:50%;background:#22c55e;color:#fff;display:flex;align-items:center;justify-content:center;font-size:24px;margin:0 auto 12px}</style></head>'
            . '<body><div class="c"><div class="d">&#10003;</div><div>Card verification complete.</div>'
            . '<div style="color:#94a3b8;font-size:13px;margin-top:6px">You can return to the checkout window — it updates automatically.</div></div>'
            . '<script>try{window.parent&&window.parent.postMessage({type:"gta-3ds-done"},"*");}catch(e){}</script></body></html>';
        exit;
    }

    /**
     * POST /academy/qrph/finalize — verify the payment SUCCEEDED server-side, then create the
     * account (guests) + grant the membership. Called by the poller when the QR is paid.
     */
    public function qrphFinalize(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        header('Content-Type: application/json; charset=utf-8');
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); echo json_encode(['success' => false, 'message' => 'Method not allowed']); exit; }
        if (empty($_POST['csrf_token']) || !function_exists('validateCsrfToken') || !validateCsrfToken($_POST['csrf_token'])) {
            http_response_code(403); echo json_encode(['success' => false, 'message' => 'Invalid session token.']); exit;
        }
        $ctx  = $_SESSION['academy_pay'] ?? null;
        $piId = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string) ($_POST['pi_id'] ?? ''));
        if (!is_array($ctx) || $piId === '' || ($ctx['pi_id'] ?? '') !== $piId) {
            http_response_code(403); echo json_encode(['success' => false, 'message' => 'Session mismatch — please retry.']); exit;
        }
        try {
            $handler = new \Ginto\Handlers\PayMongoHandler();
            $st      = $handler->getPaymentIntentStatus($piId);
            if (empty($st['success']) || ($st['status'] ?? '') !== 'succeeded') {
                echo json_encode(['success' => false, 'paid' => false, 'message' => 'Payment not confirmed yet.']); exit;
            }
            $db   = Database::getInstance();
            $plan = $db->get('subscription_plans', '*', ['id' => $ctx['plan_id'], 'plan_type' => 'academy']);
            if (!$plan) { echo json_encode(['success' => false, 'message' => 'Plan is missing.']); exit; }

            // Idempotent: this PI already granted a membership.
            if (is_array($db->get('academy_orders', ['id'], ['checkout_session_id' => $piId, 'status' => 'completed']))) {
                unset($_SESSION['academy_pay'], $_SESSION['paymongo_pi_id']);
                echo json_encode(['success' => true, 'redirect' => '/academy/learn']); exit;
            }

            $userId = $ctx['user_id'] ?? null;
            if (empty($userId)) {
                $g = $ctx['guest'] ?? null;
                if (!is_array($g)) { echo json_encode(['success' => false, 'message' => 'Missing account details.']); exit; }
                if (is_array($db->get('users', ['id'], ['email' => $g['email']]))) {
                    echo json_encode(['success' => false, 'message' => 'Email already registered — please log in.', 'login' => true]); exit;
                }
                $userId = $this->insertLearner($g['name'], $g['email'], $g['password_hash']);
                if (!$userId) { echo json_encode(['success' => false, 'message' => 'Could not create your account.']); exit; }
                $created = $db->get('users', '*', ['id' => $userId]);
                if (is_array($created)) $this->loginSession($created);
            }

            $this->activateMembership((int) $userId, $plan, $piId, $st['payment_id'] ?? null, (float) $ctx['amount'], (string) ($ctx['currency'] ?? 'PHP'), (string) ($ctx['method'] ?? 'paymongo_qrph'), !empty($ctx['auto_renew']));

            // Assisted auto-renew: store the vaulted card reference for next month's on-session charge.
            if (!empty($ctx['auto_renew']) && !empty($ctx['customer_id'])) {
                try {
                    $pm = $handler->customerPaymentMethods((string) $ctx['customer_id']);
                    if (!empty($pm['success']) && !empty($pm['cards'])) {
                        (new \Ginto\Models\AcademyCard())->save((int) $userId, (string) $ctx['customer_id'], $pm['cards'][0]);
                    }
                } catch (\Throwable $e) { error_log('Academy vault save error: ' . $e->getMessage()); }
            }

            unset($_SESSION['academy_pay'], $_SESSION['paymongo_pi_id']);
            echo json_encode(['success' => true, 'redirect' => '/academy/learn']);
            exit;
        } catch (\Throwable $e) {
            error_log('Academy qrphFinalize error: ' . $e->getMessage());
            http_response_code(500); echo json_encode(['success' => false, 'message' => 'An error occurred finalizing your membership.']); exit;
        }
    }

    /** Establish a login session for a freshly-created learner (mirrors AuthController). */
    private function loginSession(array $user): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        session_regenerate_id(true);
        $_SESSION['user_id']              = $user['id'];
        $_SESSION['username']             = $user['username'] ?? '';
        $_SESSION['fullname']             = $user['fullname'] ?? '';
        $_SESSION['user']                 = $user['email'] ?? ($user['username'] ?? '');
        $_SESSION['user_email']           = $user['email'] ?? '';
        $_SESSION['user_full_name']       = $user['fullname'] ?? 'User';
        $_SESSION['user_username']        = $user['username'] ?? '';
        $_SESSION['user_profile_picture'] = $user['avatar'] ?? null;
        $_SESSION['role_id']              = $user['role_id'] ?? 5;
        $_SESSION['role']                 = 'user';
    }

    /** GET /academy/subscribe/success — after PayMongo checkout; access reflects once the webhook lands. */
    public function success(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        View::view('academy/success', ['title' => 'Welcome to the Academy']);
    }

    /** Active Academy membership plans for the pricing section (defensive — empty on any error). */
    private function subscriptionPlans(): array
    {
        try {
            $db = Database::getInstance();
            $rows = $db->select('subscription_plans', '*', [
                'plan_type' => 'academy',
                'is_active' => 1,
                'ORDER'     => ['sort_order' => 'ASC'],
            ]);
            return is_array($rows) ? $rows : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function isAdmin(): bool
    {
        try {
            if (class_exists('\\Ginto\\Controllers\\UserController')) {
                return (bool) \Ginto\Controllers\UserController::isAdmin();
            }
        } catch (\Throwable $e) {}
        return false;
    }

    private function redirect(string $to): void
    {
        header('Location: ' . $to, true, 302);
        exit;
    }
}
