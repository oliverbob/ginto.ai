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
        $this->captureRef();

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
            'currentPlan'  => $hasAccess ? $this->planName((int) $userId) : '',
            'plans'        => $plans,
            'referralLink' => $hasAccess ? $this->referralLink((int) $userId) : '',
            'showSilverQueen' => $this->showSilverQueen($userId),
            'csrf_token'   => function_exists('generateCsrfToken') ? generateCsrfToken(true) : ($_SESSION['csrf_token'] ?? ''),
        ]);
    }

    /**
     * Remember a sponsor from ?ref= for the rest of the visit, exactly like /register does
     * (same session key), so the sponsor survives the whole funnel: landing → sign-up → pay.
     */
    private function captureRef(): void
    {
        if (isset($_GET['ref']) && is_string($_GET['ref']) && trim($_GET['ref']) !== '') {
            $_SESSION['referral_code'] = trim($_GET['ref']);
        }
    }

    /**
     * Resolve the remembered sponsor to a user id. Mirrors UserController::registerAction:
     * accepts a numeric id, a username, or a public_id, and falls back to user 2 so an
     * Academy sign-up is never left dangling outside the network.
     */
    private function resolveReferrerId(): int
    {
        $raw = trim((string) ($_SESSION['referral_code'] ?? $_GET['ref'] ?? ''));
        if ($raw === '') return 2;
        try {
            $db = Database::getInstance();
            $id = ctype_digit($raw)
                ? $db->get('users', 'id', ['id' => (int) $raw])
                : ($db->get('users', 'id', ['username' => $raw]) ?: $db->get('users', 'id', ['public_id' => $raw]));
            return $id ? (int) $id : 2;
        } catch (\Throwable $e) {
            return 2;
        }
    }

    /**
     * The member's shareable referral link — the short root form https://silverqueen.pro/?ref=<public_id>.
     * Landing anywhere with this ?ref (root, /register, or /academy) stores the same
     * $_SESSION['referral_code'], so an Academy sign-up is attributed to this member either way —
     * without forcing the invitee onto /register first.
     */
    private function referralLink(int $userId): string
    {
        try {
            $publicId = Database::getInstance()->get('users', 'public_id', ['id' => $userId]);
            if (empty($publicId)) return '';
            return $this->baseUrl() . '/?ref=' . rawurlencode((string) $publicId);
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function baseUrl(): string
    {
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'silverqueen.pro');
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
            'botInterval' => (int) $this->botSettings((int) $userId)['bot_interval_sec'],
            'showSilverQueen' => $this->showSilverQueen($userId),
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
        if (!in_array($interval, ['1s', '1m', '5m', '15m', '30m', '1h', '2h', '4h', '1d', '1w', '1M'], true)) $interval = '15m';
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
        $input  = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $out    = $this->runAnalysis($userId, (string) ($input['symbol'] ?? 'BTCUSDT'), (string) ($input['scope'] ?? 'coin'));
        if (!empty($out['rate_limited'])) {
            http_response_code(429);
        }
        unset($out['rate_limited']);
        echo json_encode($out);
        exit;
    }

    /**
     * Run one AI analysis and return it.
     *
     * Session-free like placePaperBuy and closePaperPositions, so the relay
     * reaches the same brain under the same cooldown. That cooldown is why this
     * belongs in one place: it rations a shared AI budget, and a second entry
     * point carrying its own copy of it rations nothing.
     *
     * @return array<string,mixed>
     */
    public function runAnalysis(int $userId, string $symbolIn = 'BTCUSDT', string $scopeIn = 'coin'): array
    {

        // Per-user cooldown (seconds) — one analysis at a time, protects the AI budget.
        $cool = 12;
        $stamp = (defined('STORAGE_PATH') ? STORAGE_PATH : sys_get_temp_dir()) . '/academy_analyze_' . $userId . '.txt';
        if (is_file($stamp)) {
            $wait = $cool - (time() - (int) filemtime($stamp));
            if ($wait > 0) return ['ok' => false, 'rate_limited' => true, 'error' => 'Hold on ' . $wait . 's — one analysis at a time.'];
        }

        $scope  = $scopeIn === 'market' ? 'market' : 'coin';
        $symbol = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $symbolIn)) ?: 'BTCUSDT';
        if (!str_ends_with($symbol, 'USDT')) $symbol .= 'USDT';
        $base = substr($symbol, 0, -4);

        try {
            $brain = new \Ginto\Services\GtbBrain();
            if (!$brain->isConfigured()) return ['ok' => false, 'error' => 'The AI brain is not configured yet. Try again later.'];
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
            if (empty($res['ok'])) return ['ok' => false, 'error' => $res['error'] ?? 'Analysis failed'];
            // Pro + bot ON: a BUY verdict auto-executes a paper trade for THIS user only.
            $auto = $this->autoBuyFromDecision($userId, $scope, $symbol, $res);
            return [
                'ok' => true, 'scope' => $scope, 'symbol' => $symbol, 'base' => $base,
                'text' => $res['text'], 'decision' => $res['decision'] ?? null, 'auto' => $auto,
            ];
        } catch (\Throwable $e) {
            error_log('Academy runAnalysis: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'The analysis engine is busy — try again in a moment.'];
        }
    }

    /** GET /academy/learn — the branded lessons facility (preview lessons open; rest gated). */
    public function learn(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        $userId    = $_SESSION['user_id'] ?? null;
        $hasAccess = !empty($userId) && $this->hasActiveSubscription((int) $userId);
        View::view('academy/learn', [
            'title'        => 'Learn — Ginto Trading Academy',
            'isLoggedIn'   => !empty($userId),
            'hasAccess'    => $hasAccess,
            'lessons'      => $this->publishedLessons(),
            'referralLink' => $hasAccess ? $this->referralLink((int) $userId) : '',
            'showSilverQueen' => $this->showSilverQueen($userId),
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
            'showSilverQueen' => $this->showSilverQueen($userId),
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

    /** GET /academy/admin/subscriptions — admin tool: manually grant a paid subscription. */
    public function adminGrantPage(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        if (!$this->isAdmin()) { $this->redirect('/academy'); return; }
        $db = Database::getInstance();
        $recent = [];
        try {
            $recent = $db->pdo->query(
                "SELECT us.expires_at, us.amount_paid, us.currency, us.created_at, us.payment_method,
                        u.email, u.username, u.fullname, sp.display_name AS plan
                 FROM user_subscriptions us
                 JOIN users u ON u.id = us.user_id
                 LEFT JOIN subscription_plans sp ON sp.id = us.plan_id
                 WHERE us.payment_method = 'manual' AND us.status = 'active'
                 ORDER BY us.id DESC LIMIT 25"
            )->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) { error_log('Academy adminGrantPage: ' . $e->getMessage()); }
        View::view('academy/admin_grant', [
            'title'      => 'Manual subscription grant',
            'plans'      => $this->subscriptionPlans(),
            'recent'     => $recent,
            'csrf_token' => function_exists('generateCsrfToken') ? generateCsrfToken(true) : ($_SESSION['csrf_token'] ?? ''),
        ]);
    }

    /** POST /academy/admin/grant — admin: grant/refresh a membership for a personally-paid customer. */
    public function grantSubscription(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        if (!$this->isAdmin()) { http_response_code(403); echo json_encode(['ok' => false, 'error' => 'Admins only.']); exit; }
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $input['csrf_token'] ?? '';
        if (empty($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            http_response_code(403); echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']); exit;
        }
        $email    = strtolower(trim((string) ($input['email'] ?? '')));
        $name     = trim((string) ($input['name'] ?? ''));
        $planName = (string) ($input['plan'] ?? 'academy_pro');
        $months   = max(1, min(36, (int) ($input['months'] ?? 1)));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { echo json_encode(['ok' => false, 'error' => 'Enter a valid email.']); exit; }
        try {
            $db   = Database::getInstance();
            $plan = $db->get('subscription_plans', '*', ['name' => $planName, 'plan_type' => 'academy']);
            if (!$plan) { echo json_encode(['ok' => false, 'error' => 'Unknown plan.']); exit; }
            $amount = ($input['amount'] ?? '') !== '' ? (float) $input['amount'] : (float) ($plan['price_monthly'] ?? 0);

            $u = $db->get('users', ['id', 'username'], ['email' => $email]);
            $created = false; $temp = null; $username = '';
            if (is_array($u)) {
                $userId = (int) $u['id']; $username = (string) $u['username'];
            } else {
                if ($name === '') { echo json_encode(['ok' => false, 'error' => 'No account with that email — add a full name to create one.']); exit; }
                $temp   = bin2hex(random_bytes(4));
                $userId = $this->insertLearner($name, $email, password_hash($temp, PASSWORD_DEFAULT));
                if (!$userId) { echo json_encode(['ok' => false, 'error' => 'Could not create the account.']); exit; }
                $created = true;
                $row = $db->get('users', ['username'], ['id' => $userId]); $username = is_array($row) ? (string) $row['username'] : '';
            }

            $now = date('Y-m-d H:i:s'); $expires = date('Y-m-d H:i:s', strtotime("+{$months} month"));
            $ref = 'MANUAL-' . date('Ymd-His');
            $db->insert('academy_orders', ['user_id' => $userId, 'plan_id' => $plan['id'], 'checkout_session_id' => $ref, 'amount' => $amount, 'currency' => 'PHP', 'status' => 'completed']);
            $db->update('user_subscriptions', ['status' => 'cancelled', 'cancelled_at' => $now, 'updated_at' => $now], ['user_id' => $userId, 'status' => 'active']);
            $db->insert('user_subscriptions', [
                'user_id' => $userId, 'plan_id' => $plan['id'], 'status' => 'active',
                'started_at' => $now, 'expires_at' => $expires, 'payment_method' => 'manual',
                'payment_reference' => $ref, 'amount_paid' => $amount, 'currency' => 'PHP',
                'auto_renew' => 0, 'created_at' => $now, 'updated_at' => $now,
            ]);
            error_log("Academy MANUAL grant by admin: user={$userId} plan={$plan['id']} amount={$amount} until {$expires}");
            echo json_encode([
                'ok' => true, 'user_id' => $userId, 'username' => $username, 'created' => $created,
                'temp_password' => $temp, 'plan' => $plan['display_name'] ?? $planName, 'expires' => $expires, 'amount' => $amount,
            ]);
        } catch (\Throwable $e) {
            error_log('Academy grantSubscription: ' . $e->getMessage());
            echo json_encode(['ok' => false, 'error' => 'Grant failed — check the server log.']);
        }
        exit;
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
        foreach ([
            'bot_enabled TINYINT NOT NULL DEFAULT 0', 'bot_since DATETIME NULL',
            'day_anchor DECIMAL(18,8) NULL',   // equity at the first poll of the current day
            'day_anchor_date DATE NULL',       // which day that anchor belongs to
            'halt_date DATE NULL',             // day the daily-loss circuit breaker last tripped
        ] as $col) {
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
            close_reason VARCHAR(16) NULL,
            opened_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            closed_at DATETIME NULL,
            INDEX idx_user_status (user_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        // Backfill on pre-existing installs.
        try { $db->pdo->exec("ALTER TABLE academy_positions ADD COLUMN IF NOT EXISTS close_reason VARCHAR(16) NULL"); } catch (\Throwable $e) {}
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
                'day_anchor' => isset($w['day_anchor']) && $w['day_anchor'] !== null ? (float) $w['day_anchor'] : null,
                'day_anchor_date' => $w['day_anchor_date'] ?? null,
                'halt_date' => $w['halt_date'] ?? null,
            ];
        } catch (\Throwable $e) {
            error_log('Academy walletFor: ' . $e->getMessage());
            return ['balance' => 10000.0, 'starting' => 10000.0, 'bot_enabled' => 0, 'bot_since' => null,
                    'day_anchor' => null, 'day_anchor_date' => null, 'halt_date' => null];
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
        $stopPct  = (float) $set['stop_loss_pct'];                    // per-trade stop guardrail
        $dailyPct = (float) $set['max_daily_loss_pct'];               // account guardrail
        $tpPct    = (float) $set['take_profit_pct'];                  // per-trade take-profit (0 = off)
        $enabledTpls = array_flip(array_filter(array_map('trim', explode(',', (string) $set['templates']))));
        $db = Database::getInstance();
        $client = new \Ginto\Services\BinanceClient();
        $balance = (float) $wallet['balance'];
        $enabled = !empty($wallet['bot_enabled']);
        $today   = date('Y-m-d');
        $halted  = (($wallet['halt_date'] ?? null) === $today);   // daily breaker already tripped today

        $mark = function (string $symbol, float $fallback) use ($client): float {
            $m = (float) ($client->price($symbol) ?? $fallback);
            return $m > 0 ? $m : $fallback;
        };
        // Close one open position at $exit; realize into balance. Returns proceeds.
        $close = function (array $u, float $exit, string $reason = '') use ($db, &$balance): void {
            if ($exit <= 0) $exit = (float) $u['entry'];
            $proceeds = (float) $u['qty'] * $exit; $balance += $proceeds;
            $db->update('academy_positions', [
                'status' => 'closed', 'exit_price' => $exit,
                'realized' => round($proceeds - (float) $u['spent'], 8),
                'close_reason' => $reason !== '' ? $reason : null, 'closed_at' => date('Y-m-d H:i:s'),
            ], ['id' => $u['id']]);
        };

        $botOpen = [];
        try { foreach ((new \Ginto\Models\GtbTrade())->openPositions('paper') as $p) { $botOpen[(int) $p['id']] = $p; } } catch (\Throwable $e) {}

        // 1) Close mirrors whose class-bot trade has closed — realize at the bot's exit (or live mark).
        $open = $db->select('academy_positions', '*', ['user_id' => $userId, 'status' => 'open']);
        if (!is_array($open)) $open = [];
        foreach ($open as $u) {
            $ref = $u['ref_trade_id'] !== null ? (int) $u['ref_trade_id'] : 0;
            if ($ref && !isset($botOpen[$ref])) {
                $bt = $db->get('gtb_trades', ['exit_price'], ['id' => $ref]);
                $exit = (is_array($bt) && $bt['exit_price'] !== null) ? (float) $bt['exit_price'] : $mark($u['symbol'], (float) $u['entry']);
                $close($u, $exit, 'bot_exit');
            }
        }

        // 2) GUARDRAIL A — per-trade stop-loss. Any open position (manual OR bot) down >= stop% from
        //    entry is auto-closed at the live mark. Applies to every member.
        $open = $db->select('academy_positions', '*', ['user_id' => $userId, 'status' => 'open']);
        if (!is_array($open)) $open = [];
        foreach ($open as $u) {
            $entry = (float) $u['entry']; if ($entry <= 0) continue;
            $m = $mark($u['symbol'], $entry);
            if (($m - $entry) / $entry * 100 <= -$stopPct) { $close($u, $m, 'stop_loss'); }
        }

        // 2b) TAKE-PROFIT — auto-close a MANUAL/AI trade once it's up >= take-profit% from entry.
        //     Bot-followed trades keep the class bot's own (trailing) exit, so they're left alone.
        if ($tpPct > 0) {
            $open = $db->select('academy_positions', '*', ['user_id' => $userId, 'status' => 'open', 'ref_trade_id' => null]);
            if (!is_array($open)) $open = [];
            foreach ($open as $u) {
                $entry = (float) $u['entry']; if ($entry <= 0) continue;
                $m = $mark($u['symbol'], $entry);
                if (($m - $entry) / $entry * 100 >= $tpPct) { $close($u, $m, 'take_profit'); }
            }
        }

        // 3) Open a mirror for each class-bot trade we don't hold yet — only if the learner turned their
        //    own bot on AND the daily breaker hasn't tripped today. Strictly per-user (scoped by user_id).
        if ($enabled && !$halted) {
            $open = $db->select('academy_positions', '*', ['user_id' => $userId, 'status' => 'open']);
            if (!is_array($open)) $open = [];
            $held = []; foreach ($open as $u) { if ($u['ref_trade_id'] !== null) $held[(int) $u['ref_trade_id']] = true; }
            $slots = $SLOTS - count($open);
            foreach ($botOpen as $id => $p) {
                if ($slots <= 0 || $balance < $UNIT) break;
                if (isset($held[$id])) continue;
                if ($enabledTpls && !isset($enabledTpls[$p['template'] ?? ''])) continue;
                $entry = (float) $p['price']; if ($entry <= 0) continue;
                // Honour the tighter of the class-bot's stop and the learner's own stop%.
                $userStop = $entry * (1 - $stopPct / 100);
                $botStop  = isset($p['stop_loss']) ? (float) $p['stop_loss'] : 0.0;
                $sl = $botStop > 0 ? max($botStop, $userStop) : $userStop;
                $db->insert('academy_positions', [
                    'user_id' => $userId, 'ref_trade_id' => $id, 'symbol' => $p['symbol'], 'base' => substr($p['symbol'], 0, -4),
                    'qty' => $UNIT / $entry, 'entry' => $entry, 'spent' => $UNIT,
                    'stop_loss' => $sl, 'take_profit' => $p['take_profit'] ?? null, 'template' => $p['template'] ?? null, 'status' => 'open',
                ]);
                $balance -= $UNIT; $slots--;
            }
        }

        // 4) Compute live equity from what's still open, then run GUARDRAIL B — account daily-loss halt.
        $loadOpen = function () use ($db, $userId, $mark) {
            $rows = $db->select('academy_positions', '*', ['user_id' => $userId, 'status' => 'open', 'ORDER' => ['id' => 'DESC']]);
            if (!is_array($rows)) $rows = [];
            $marks = 0.0;
            foreach ($rows as &$r) { $r['_mark'] = $mark($r['symbol'], (float) $r['entry']); $marks += $r['_mark'] * (float) $r['qty']; }
            return [$rows, $marks];
        };
        [$open, $marks] = $loadOpen();
        $equity = $balance + $marks;

        // Roll the day anchor forward on the first poll of a new day (a fresh day clears yesterday's halt).
        $anchor = $wallet['day_anchor'];
        $anchorDate = $wallet['day_anchor_date'] ?? null;
        $walletPatch = [];
        if ($anchorDate !== $today || $anchor === null || (float) $anchor <= 0) {
            $anchor = $equity; $anchorDate = $today;
            $walletPatch['day_anchor'] = round($anchor, 8);
            $walletPatch['day_anchor_date'] = $today;
            $halted = (($wallet['halt_date'] ?? null) === $today); // recompute (still false on a genuinely new day)
        }
        $justHalted = false;
        if (!$halted && (float) $anchor > 0 && $equity <= (float) $anchor * (1 - $dailyPct / 100)) {
            // Trip the breaker: flatten everything at the mark, pause the bot for the rest of the day.
            foreach ($open as $u) { $close($u, (float) $u['_mark'], 'daily_halt'); }
            $walletPatch['bot_enabled'] = 0;
            $walletPatch['halt_date'] = $today;
            $halted = true; $justHalted = true; $enabled = false;
            [$open, $marks] = $loadOpen();
            $equity = $balance + $marks;
        }

        if (abs($balance - (float) $wallet['balance']) > 1e-9) $walletPatch['balance'] = round($balance, 8);
        if ($walletPatch) $db->update('academy_wallets', $walletPatch, ['user_id' => $userId]);

        // 5) Live positions view. SL/TP reflect the CURRENT settings so the charts match the settings
        //    at a glance: SL from stop-loss%, TP from take-profit% (manual/AI) or the bot's own TP.
        $positions = []; $unreal = 0.0;
        foreach ($open as $u) {
            $entry = (float) $u['entry']; $qty = (float) $u['qty']; $m = (float) $u['_mark'];
            $isBot = $u['ref_trade_id'] !== null;
            $pnl = ($m - $entry) * $qty; $unreal += $pnl;
            $slPrice = $entry * (1 - $stopPct / 100);   // settings stop guardrail (applies to all)
            $tpPrice = $isBot
                ? ($u['take_profit'] !== null ? (float) $u['take_profit'] : null)   // bot's own target
                : ($tpPct > 0 ? $entry * (1 + $tpPct / 100) : null);                // settings target
            $positions[] = [
                'id' => (int) $u['id'], 'symbol' => $u['symbol'], 'base' => $u['base'], 'template' => $u['template'],
                'auto' => $isBot,   // true = bot-followed, false = manual
                'entry' => $entry, 'mark' => $m, 'qty' => $qty, 'spent' => (float) $u['spent'],
                'stop_loss' => round($slPrice, 10),
                'take_profit' => $tpPrice !== null ? round($tpPrice, 10) : null,
                // Dollar equivalents for one-glance reference on the chart.
                'sl_usd' => round(($slPrice - $entry) * $qty, 2),
                'tp_usd' => $tpPrice !== null ? round(($tpPrice - $entry) * $qty, 2) : null,
                'pnlPct' => $entry > 0 ? ($m - $entry) / $entry * 100 : 0, 'unrealized' => round($pnl, 4),
            ];
        }
        return [
            'balance' => round($balance, 8), 'positions' => $positions,
            'unrealized' => round($unreal, 4), 'equity' => round($equity, 4),
            'bot_enabled' => $enabled && !$halted,
            'halted' => $halted, 'just_halted' => $justHalted,
            'stop_loss_pct' => $stopPct, 'max_daily_loss_pct' => $dailyPct, 'take_profit_pct' => $tpPct,
            'bot_interval_sec' => (int) $set['bot_interval_sec'],
            'day_anchor' => round((float) $anchor, 4),
        ];
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
            'unrealized' => $r['unrealized'],
            // reconcile is authoritative: the breaker may have flipped the bot off this poll.
            'bot_enabled' => (bool) $r['bot_enabled'], 'positions' => $r['positions'],
            'halted' => (bool) $r['halted'], 'just_halted' => (bool) $r['just_halted'],
            'stop_loss_pct' => $r['stop_loss_pct'], 'max_daily_loss_pct' => $r['max_daily_loss_pct'],
            'take_profit_pct' => $r['take_profit_pct'], 'bot_interval_sec' => $r['bot_interval_sec'],
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
                // Daily-loss breaker: block re-enabling for the rest of the day it tripped.
                $w = $this->walletFor($userId);
                if (($w['halt_date'] ?? null) === date('Y-m-d')) {
                    echo json_encode(['ok' => false, 'halted' => true, 'error' => 'Daily loss limit hit — the bot is paused until tomorrow to protect your wallet.']); exit;
                }
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

    /** POST /academy/bot/activate — pick ONE strategy template and start the follow-bot (Pro). */
    public function botActivate(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $userId = $this->requireMemberJson();
        if (!$this->isPro($userId)) { echo json_encode(['ok' => false, 'upgrade' => true, 'error' => 'Automated trading is a Pro Trader feature.']); exit; }
        $this->ensureTradingSchema(); $this->ensureBotSettingsTable();
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $valid = array_keys(\Ginto\Services\GtbStrategy::catalog());
        $tpl   = (string) ($input['template'] ?? '');
        if (!in_array($tpl, $valid, true)) { echo json_encode(['ok' => false, 'error' => 'Unknown strategy.']); exit; }
        try {
            $db  = Database::getInstance();
            // Daily-loss breaker: no re-arming until the next day.
            $w = $this->walletFor($userId);
            if (($w['halt_date'] ?? null) === date('Y-m-d')) {
                echo json_encode(['ok' => false, 'halted' => true, 'error' => 'Daily loss limit hit — the bot is paused until tomorrow to protect your wallet.']); exit;
            }
            $row = ['templates' => $tpl, 'trade_size' => 200, 'max_slots' => 8];
            if (is_array($db->get('academy_bot_settings', ['user_id'], ['user_id' => $userId]))) {
                $db->update('academy_bot_settings', ['templates' => $tpl], ['user_id' => $userId]);
            } else {
                $db->insert('academy_bot_settings', array_merge(['user_id' => $userId], $row));
            }
            $db->update('academy_wallets', ['bot_enabled' => 1, 'bot_since' => date('Y-m-d H:i:s')], ['user_id' => $userId]);
            echo json_encode(['ok' => true, 'template' => $tpl, 'bot_enabled' => true]);
        } catch (\Throwable $e) {
            error_log('Academy botActivate: ' . $e->getMessage());
            echo json_encode(['ok' => false, 'error' => 'Could not activate the bot.']);
        }
        exit;
    }

    /**
     * Shared paper-buy: open a position at the live mark, size $amount, with the learner's stop-loss %
     * baked in. Used by the manual Buy ticket AND the AI auto-buy. Returns a JSON-ready result array;
     * never echoes. Enforces the daily-loss halt, min size, and balance.
     */
    /**
     * Open a paper position. Session-free and returns its result rather than
     * echoing it, so the relay can place the same trade under the same rules —
     * one implementation of what a buy means, not two that drift.
     */
    public function placePaperBuy(int $userId, string $symbol, float $amount, string $template = 'manual'): array
    {
        $this->ensureTradingSchema();
        $symbol = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $symbol));
        if ($symbol === '') return ['ok' => false, 'error' => 'Pick a coin first.'];
        if (!str_ends_with($symbol, 'USDT')) $symbol .= 'USDT';
        $base   = substr($symbol, 0, -4);
        $amount = round($amount, 2);
        $w = $this->walletFor($userId);
        if (($w['halt_date'] ?? null) === date('Y-m-d')) {
            return ['ok' => false, 'halted' => true, 'error' => 'Daily loss limit hit — trading is paused until tomorrow.'];
        }
        $balance = (float) $w['balance'];
        if ($amount < 10) return ['ok' => false, 'error' => 'Minimum trade is $10.'];
        if ($amount > $balance) return ['ok' => false, 'error' => 'Not enough paper balance ($' . number_format($balance, 2) . ').'];
        $price = (float) ((new \Ginto\Services\BinanceClient())->price($symbol) ?? 0);
        if ($price <= 0) return ['ok' => false, 'error' => 'Could not get a live price for ' . $base . '.'];
        $set = $this->botSettings($userId);
        $stopPct = (float) $set['stop_loss_pct'];
        $tpPct   = (float) $set['take_profit_pct'];
        $slPrice = $price * (1 - $stopPct / 100);
        $tpPrice = $tpPct > 0 ? $price * (1 + $tpPct / 100) : null;   // null = take-profit off (let it run)
        $db = Database::getInstance();
        $db->insert('academy_positions', [
            'user_id' => $userId, 'ref_trade_id' => null, 'symbol' => $symbol, 'base' => $base,
            'qty' => $amount / $price, 'entry' => $price, 'spent' => $amount,
            'stop_loss' => $slPrice, 'take_profit' => $tpPrice, 'template' => $template, 'status' => 'open',
        ]);
        $db->update('academy_wallets', ['balance' => round($balance - $amount, 8)], ['user_id' => $userId]);
        return ['ok' => true, 'symbol' => $symbol, 'base' => $base, 'entry' => $price, 'spent' => $amount,
                'stop_loss' => round($slPrice, 10), 'take_profit' => $tpPrice !== null ? round($tpPrice, 10) : null,
                'balance' => round($balance - $amount, 8)];
    }

    /**
     * Hands-free execution: when a Pro member has their bot ON and an AI analysis returns BUY, open a
     * paper position automatically. Strictly per-user — it only ever touches the calling user's wallet,
     * never anyone else's. Skips on daily halt, duplicate holding, or full slots. Returns a result the
     * analyze() response surfaces so the UI can say "auto-bought".
     */
    private function autoBuyFromDecision(int $userId, string $scope, string $focusSymbol, array $res): array
    {
        if (!$this->isPro($userId)) return ['executed' => false, 'reason' => 'not_pro'];
        $w = $this->walletFor($userId);
        if (empty($w['bot_enabled'])) return ['executed' => false, 'reason' => 'bot_off'];
        if (($w['halt_date'] ?? null) === date('Y-m-d')) return ['executed' => false, 'reason' => 'halted'];

        $blob = (string) ($res['decision'] ?? '') . ' ' . (string) ($res['text'] ?? '');
        if (!preg_match('/\bBUY\b/i', (string) ($res['decision'] ?? '')) && !preg_match('/DECISION:\s*BUY/i', $blob)) {
            return ['executed' => false, 'reason' => 'not_buy'];
        }
        // Resolve the coin: the focus coin for a single-coin analysis; parse it from the call for a market scan.
        $target = $focusSymbol;
        if ($scope === 'market') {
            if (preg_match('/BUY\s+([A-Z0-9]{2,15})/i', $blob, $m)) {
                $target = strtoupper($m[1]);
                if (!str_ends_with($target, 'USDT')) $target .= 'USDT';
            } else {
                return ['executed' => false, 'reason' => 'no_symbol'];
            }
        }
        try {
            $db  = Database::getInstance();
            $set = $this->botSettings($userId);
            // Don't stack: skip if we already hold this coin, or we're at the slot cap.
            if (is_array($db->get('academy_positions', ['id'], ['user_id' => $userId, 'status' => 'open', 'symbol' => $target]))) {
                return ['executed' => false, 'reason' => 'already_open', 'symbol' => $target];
            }
            $openCount = (int) $db->count('academy_positions', ['user_id' => $userId, 'status' => 'open']);
            if ($openCount >= max(1, min(20, (int) $set['max_slots']))) return ['executed' => false, 'reason' => 'slots_full'];

            $r = $this->placePaperBuy($userId, $target, max(10.0, min(2000.0, (float) $set['trade_size'])), 'ai');
            if (empty($r['ok'])) return ['executed' => false, 'reason' => $r['error'] ?? 'buy_failed', 'symbol' => $target];
            return ['executed' => true, 'symbol' => $r['symbol'], 'base' => $r['base'], 'entry' => $r['entry'], 'spent' => $r['spent'], 'balance' => $r['balance']];
        } catch (\Throwable $e) {
            error_log('Academy autoBuy: ' . $e->getMessage());
            return ['executed' => false, 'reason' => 'error'];
        }
    }

    /** POST /academy/trade/buy — open a MANUAL paper position on the charted coin (all members). */
    public function tradeBuy(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $userId = $this->requireMemberJson();
        $input  = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        try {
            echo json_encode($this->placePaperBuy($userId, (string) ($input['symbol'] ?? ''), (float) ($input['amount'] ?? 0), 'manual'));
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
        $input  = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        echo json_encode($this->closePaperPositions(
            $userId, (int) ($input['id'] ?? 0), (string) ($input['symbol'] ?? '')
        ));
        exit;
    }

    /**
     * Close manual paper positions at the live mark, by id or by symbol.
     *
     * Session-free and returns its result, for the same reason placePaperBuy is:
     * the relay closes trades through this too, so what "sell" means is defined
     * once. Bot-followed positions (ref_trade_id set) are never touched here —
     * the bot opened them and the bot closes them, and letting a member close
     * one by hand would leave the bot managing a position that no longer exists.
     *
     * @return array<string,mixed>
     */
    public function closePaperPositions(int $userId, int $id = 0, string $symbol = ''): array
    {
        $this->ensureTradingSchema();
        $symbol = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $symbol));
        if ($symbol !== '' && !str_ends_with($symbol, 'USDT')) $symbol .= 'USDT';

        try {
            $db = Database::getInstance();
            $client = new \Ginto\Services\BinanceClient();
            if ($id > 0) {
                $rows = ($p = $db->get('academy_positions', '*', ['id' => $id, 'user_id' => $userId, 'status' => 'open', 'ref_trade_id' => null])) && is_array($p) ? [$p] : [];
            } elseif ($symbol !== '') {
                $rows = $db->select('academy_positions', '*', ['user_id' => $userId, 'status' => 'open', 'ref_trade_id' => null, 'symbol' => $symbol]);
                if (!is_array($rows)) $rows = [];
            } else {
                $rows = [];
            }
            if (!$rows) return ['ok' => false, 'error' => 'No open manual position to close (bot trades are managed by the bot).'];

            $balance = (float) $this->walletFor($userId)['balance'];
            $realized = 0.0; $n = 0;
            foreach ($rows as $p) {
                $mark = (float) ($client->price($p['symbol']) ?? $p['entry']);
                if ($mark <= 0) $mark = (float) $p['entry'];
                $proceeds = (float) $p['qty'] * $mark; $r = $proceeds - (float) $p['spent'];
                $realized += $r; $balance += $proceeds; $n++;
                $db->update('academy_positions', ['status' => 'closed', 'exit_price' => $mark, 'realized' => round($r, 8), 'close_reason' => 'manual', 'closed_at' => date('Y-m-d H:i:s')], ['id' => (int) $p['id']]);
            }
            $db->update('academy_wallets', ['balance' => round($balance, 8)], ['user_id' => $userId]);

            return ['ok' => true, 'closed' => $n, 'realized' => round($realized, 4), 'balance' => round($balance, 8)];
        } catch (\Throwable $e) {
            error_log('Academy closePaperPositions: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Could not close the trade.'];
        }
    }

    /** GET /academy/history — this learner's paper-trading transaction history (full page). */
    public function history(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        $userId = $_SESSION['user_id'] ?? null;
        if (empty($userId)) { $this->redirect('/login?redirect=' . urlencode('/academy/history')); return; }
        if (!$this->hasActiveSubscription((int) $userId)) { $this->redirect('/academy#pricing'); return; }
        View::view('academy/history', [
            'title' => 'Trade History — Ginto Trading Academy', 'isLoggedIn' => true, 'hasAccess' => true,
            'showSilverQueen' => $this->showSilverQueen($userId),
        ]);
    }

    /**
     * GET /academy/history/data — JSON page of this learner's trades (closed + open), newest first.
     * Strictly scoped to the calling user. ?scope=closed|open|all & ?page=N.
     */
    public function historyData(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $userId = $this->requireMemberJson();
        $this->ensureTradingSchema();
        $scope = (string) ($_GET['scope'] ?? 'all');
        $page  = max(1, (int) ($_GET['page'] ?? 1));
        $per   = 25; $off = ($page - 1) * $per;
        $where = ['user_id' => $userId];
        if ($scope === 'closed') $where['status'] = 'closed';
        elseif ($scope === 'open') $where['status'] = 'open';
        try {
            $db = Database::getInstance();
            $total = (int) $db->count('academy_positions', $where);
            $rows  = $db->select('academy_positions', '*', array_merge($where, ['ORDER' => ['id' => 'DESC'], 'LIMIT' => [$off, $per]]));
            if (!is_array($rows)) $rows = [];
            $reasons = ['manual' => 'You closed it', 'stop_loss' => 'Stop-loss', 'take_profit' => 'Take-profit', 'daily_halt' => 'Daily-loss halt', 'bot_exit' => 'Bot exit'];
            $out = array_map(static function ($r) use ($reasons) {
                $entry = (float) $r['entry']; $exit = $r['exit_price'] !== null ? (float) $r['exit_price'] : null;
                $realized = $r['realized'] !== null ? (float) $r['realized'] : null;
                return [
                    'id' => (int) $r['id'], 'symbol' => $r['symbol'], 'base' => $r['base'],
                    'side' => 'BUY', 'status' => $r['status'], 'template' => $r['template'],
                    'auto' => $r['ref_trade_id'] !== null,
                    'qty' => (float) $r['qty'], 'entry' => $entry, 'exit' => $exit, 'spent' => (float) $r['spent'],
                    'realized' => $realized,
                    'pnlPct' => ($exit !== null && $entry > 0) ? ($exit - $entry) / $entry * 100 : null,
                    'reason' => $reasons[$r['close_reason'] ?? ''] ?? ($r['close_reason'] ?? null),
                    'opened_at' => $r['opened_at'] ?? null, 'closed_at' => $r['closed_at'] ?? null,
                ];
            }, $rows);
            echo json_encode(['ok' => true, 'page' => $page, 'per' => $per, 'total' => $total, 'rows' => $out]);
        } catch (\Throwable $e) {
            error_log('Academy historyData: ' . $e->getMessage());
            echo json_encode(['ok' => false, 'error' => 'Could not load history.']);
        }
        exit;
    }

    /** GET /academy/thoughts — full, paginated history of the class demo bot's reasoning (members). */
    public function thoughts(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        $userId = $_SESSION['user_id'] ?? null;
        if (empty($userId)) { $this->redirect('/login?redirect=' . urlencode('/academy/thoughts')); return; }
        if (!$this->hasActiveSubscription((int) $userId)) { $this->redirect('/academy#pricing'); return; }
        View::view('academy/thoughts', [
            'title' => "The Bot's Mind — Ginto Trading Academy", 'isLoggedIn' => true, 'hasAccess' => true,
            'showSilverQueen' => $this->showSilverQueen($userId),
        ]);
    }

    /** GET /academy/thoughts/data — JSON page of the shared class-bot thought stream. ?page=N. */
    public function thoughtsData(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $this->requireMemberJson();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $per  = 50; $off = ($page - 1) * $per;
        try {
            $db = Database::getInstance();
            $total = (int) $db->count('gtb_thoughts', []);
            $rows  = $db->select('gtb_thoughts', ['id', 'message', 'phase', 'role', 'created_at'],
                ['ORDER' => ['id' => 'DESC'], 'LIMIT' => [$off, $per]]);
            if (!is_array($rows)) $rows = [];
            echo json_encode(['ok' => true, 'page' => $page, 'per' => $per, 'total' => $total, 'rows' => $rows]);
        } catch (\Throwable $e) {
            error_log('Academy thoughtsData: ' . $e->getMessage());
            echo json_encode(['ok' => false, 'error' => 'Could not load the thought history.']);
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

    /**
     * Should the SilverQueen button appear in the Academy header for this visitor?
     * Delegates to the console's own guard, so the button shows only for members who
     * could actually open /silverqueen — Pro Trader (or elevated). Everyone else gets
     * no button and no mention of it anywhere on the page.
     */
    private function showSilverQueen($userId): bool
    {
        if (empty($userId)) return false;
        try {
            return \Ginto\Controllers\SilverQueenController::canAccess((int) $userId);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private const DEFAULT_TEMPLATES = 'gainers,scalp,breakout,trend,pullback';

    // Default risk guardrails (percent). Applied to every member, manual or bot.
    private const DEFAULT_STOP_LOSS_PCT = 1.0;    // per-trade: auto-close a position down this % from entry
    private const DEFAULT_DAILY_LOSS_PCT = 1.0;   // account: flatten + pause the bot if the day is down this %
    private const DEFAULT_TAKE_PROFIT_PCT = 2.0;  // per-trade: auto-close a manual/AI trade up this % from entry (0 = off)
    private const DEFAULT_BOT_INTERVAL_SEC = 15;  // per-user (Pro): how often the wallet syncs/mirrors/auto-buys

    private function ensureBotSettingsTable(): void
    {
        $db = Database::getInstance();
        $db->pdo->exec("CREATE TABLE IF NOT EXISTS academy_bot_settings (
            user_id INT PRIMARY KEY,
            templates VARCHAR(255) NOT NULL DEFAULT '" . self::DEFAULT_TEMPLATES . "',
            trade_size DECIMAL(18,8) NOT NULL DEFAULT 200,
            max_slots INT NOT NULL DEFAULT 8,
            stop_loss_pct DECIMAL(6,3) NOT NULL DEFAULT " . self::DEFAULT_STOP_LOSS_PCT . ",
            max_daily_loss_pct DECIMAL(6,3) NOT NULL DEFAULT " . self::DEFAULT_DAILY_LOSS_PCT . ",
            take_profit_pct DECIMAL(6,3) NOT NULL DEFAULT " . self::DEFAULT_TAKE_PROFIT_PCT . ",
            bot_interval_sec INT NOT NULL DEFAULT " . self::DEFAULT_BOT_INTERVAL_SEC . ",
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        // Backfill guardrail columns on pre-existing installs.
        foreach ([
            'stop_loss_pct DECIMAL(6,3) NOT NULL DEFAULT ' . self::DEFAULT_STOP_LOSS_PCT,
            'max_daily_loss_pct DECIMAL(6,3) NOT NULL DEFAULT ' . self::DEFAULT_DAILY_LOSS_PCT,
            'take_profit_pct DECIMAL(6,3) NOT NULL DEFAULT ' . self::DEFAULT_TAKE_PROFIT_PCT,
            'bot_interval_sec INT NOT NULL DEFAULT ' . self::DEFAULT_BOT_INTERVAL_SEC,
        ] as $col) {
            try { $db->pdo->exec("ALTER TABLE academy_bot_settings ADD COLUMN IF NOT EXISTS $col"); } catch (\Throwable $e) {}
        }
    }

    /** Per-user, DB-driven bot settings (not .env). Returns defaults if unset. */
    private function botSettings(int $userId): array
    {
        $defaults = [
            'templates' => self::DEFAULT_TEMPLATES, 'trade_size' => 200.0, 'max_slots' => 8,
            'stop_loss_pct' => self::DEFAULT_STOP_LOSS_PCT, 'max_daily_loss_pct' => self::DEFAULT_DAILY_LOSS_PCT,
            'take_profit_pct' => self::DEFAULT_TAKE_PROFIT_PCT, 'bot_interval_sec' => self::DEFAULT_BOT_INTERVAL_SEC,
        ];
        try {
            $this->ensureBotSettingsTable();
            $r = Database::getInstance()->get('academy_bot_settings', '*', ['user_id' => $userId]);
            if (!is_array($r)) return $defaults;
            return [
                'templates'  => (string) ($r['templates'] ?? $defaults['templates']),
                'trade_size' => (float) ($r['trade_size'] ?? 200),
                'max_slots'  => (int) ($r['max_slots'] ?? 8),
                // Clamp to a sane 0.1%–50% band; 0/invalid falls back to the default.
                'stop_loss_pct'      => $this->clampPct($r['stop_loss_pct'] ?? null, self::DEFAULT_STOP_LOSS_PCT),
                'max_daily_loss_pct' => $this->clampPct($r['max_daily_loss_pct'] ?? null, self::DEFAULT_DAILY_LOSS_PCT),
                // Take-profit: 0 = off (let winners run); otherwise clamped 0.1–50%.
                'take_profit_pct'    => $this->clampTp($r['take_profit_pct'] ?? null),
                'bot_interval_sec'   => $this->clampInterval($r['bot_interval_sec'] ?? null),
            ];
        } catch (\Throwable $e) { return $defaults; }
    }

    /** Clamp a user-supplied guardrail percent into [0.1, 50]; fall back to $default when empty/invalid. */
    private function clampPct($val, float $default): float
    {
        $v = (float) $val;
        if ($v <= 0) return $default;
        return max(0.1, min(50.0, $v));
    }

    /** Take-profit percent: empty/null → default; explicit 0 (or negative) → 0 (off); else clamp [0.1, 50]. */
    private function clampTp($val): float
    {
        if ($val === null || $val === '') return self::DEFAULT_TAKE_PROFIT_PCT;
        $v = (float) $val;
        if ($v <= 0) return 0.0;
        return max(0.1, min(50.0, $v));
    }

    /** Clamp the per-user follow interval into [5, 300] seconds; default when empty/invalid. */
    private function clampInterval($val): int
    {
        $v = (int) $val;
        if ($v <= 0) return self::DEFAULT_BOT_INTERVAL_SEC;
        return max(5, min(300, $v));
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
            'showSilverQueen' => $this->showSilverQueen($userId),
        ]);
    }

    /**
     * POST /academy/settings/save — persist per-user bot settings.
     * Risk guardrails (stop-loss %, daily-loss %) are saveable by EVERY member — they protect manual
     * trades too. Strategy templates / sizing / slots remain a Pro Trader feature.
     */
    public function settingsSave(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $userId = $this->requireMemberJson();
        $this->ensureBotSettingsTable();
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $isPro = $this->isPro($userId);

        // Everyone: risk guardrails + take-profit target.
        $row = [
            'stop_loss_pct'      => $this->clampPct($input['stop_loss_pct'] ?? null, self::DEFAULT_STOP_LOSS_PCT),
            'max_daily_loss_pct' => $this->clampPct($input['max_daily_loss_pct'] ?? null, self::DEFAULT_DAILY_LOSS_PCT),
            'take_profit_pct'    => $this->clampTp($input['take_profit_pct'] ?? null),
        ];
        // Pro only: strategy templates + sizing.
        if ($isPro) {
            $valid = array_keys(\Ginto\Services\GtbStrategy::catalog());
            $tpls  = array_values(array_intersect($valid, (array) ($input['templates'] ?? [])));
            if (!$tpls) $tpls = $valid;
            $row['templates']  = implode(',', $tpls);
            $row['trade_size'] = max(10.0, min(2000.0, (float) ($input['trade_size'] ?? 200)));
            $row['max_slots']  = max(1, min(20, (int) ($input['max_slots'] ?? 8)));
            $row['bot_interval_sec'] = $this->clampInterval($input['bot_interval_sec'] ?? null);
        }
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
            $base   = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'silverqueen.pro');

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
                'referrer_id'   => $this->resolveReferrerId(),
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
        $base  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'silverqueen.pro');

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
     * silverqueen.pro). Creates the intent, attaches the card, and returns any 3DS/OTP next-action URL
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

            // Name on the card (billing name) — prefer what the payer typed, else their account name.
            $cardName = trim((string) ($_POST['card_name'] ?? ''));
            if ($cardName !== '') $payName = mb_substr($cardName, 0, 100);

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
                $returnUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'silverqueen.pro') . '/academy/card/return';
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
