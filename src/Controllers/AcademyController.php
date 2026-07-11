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

    /** True if the user has a current, non-expired active subscription. */
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

            $amount = (int) round(((float) $plan['price_monthly']) * 100); // centavos
            $base   = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'ginto.ai');

            $pm  = new \Ginto\Handlers\PayMongoHandler();
            $res = $pm->createCheckoutSession(
                $amount,
                'Ginto Trading Academy — ' . ($plan['display_name'] ?? 'Membership'),
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
                'amount'              => $plan['price_monthly'],
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

    /** Record the paid order and grant/refresh the Academy membership (on-site QRPh path). */
    private function activateMembership(int $userId, array $plan, string $ref, ?string $gatewayPaymentId, float $amount, string $currency): void
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
            'payment_method'     => 'paymongo_qrph',
            'payment_reference'  => $ref,
            'gateway_payment_id' => $gatewayPaymentId,
            'amount_paid'        => $amount,
            'currency'           => $currency,
            'auto_renew'         => 0,
            'created_at'         => $now,
            'updated_at'         => $now,
        ]);
        error_log("Academy membership activated on-site: user={$userId} plan={$plan['id']} pi={$ref}");
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

            $amount  = (int) round((float) $plan['price_monthly']); // whole pesos
            $handler = new \Ginto\Handlers\PayMongoHandler();
            $res     = $handler->initQrph($amount, (string) $payEmail, (string) $payName, '', 'Ginto Trading Academy — ' . ($plan['display_name'] ?? 'Membership'));
            if (empty($res['success'])) { echo json_encode(['success' => false, 'message' => $res['message'] ?? 'Could not start the payment.']); exit; }

            $_SESSION['paymongo_pi_id'] = $res['pi_id']; // let the shared status poller accept this PI
            $_SESSION['academy_pay']    = [
                'pi_id'    => $res['pi_id'], 'plan_id' => $plan['id'], 'amount' => $amount,
                'currency' => $plan['price_currency'] ?? 'PHP', 'user_id' => $userId ? (int) $userId : null, 'guest' => $guest,
            ];
            echo json_encode(['success' => true, 'pi_id' => $res['pi_id'], 'qr_image' => $res['qr_image'] ?? null, 'qr_string' => $res['qr_string'] ?? null, 'amount' => $amount]);
            exit;
        } catch (\Throwable $e) {
            error_log('Academy qrphInit error: ' . $e->getMessage());
            http_response_code(500); echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']); exit;
        }
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

            $this->activateMembership((int) $userId, $plan, $piId, $st['payment_id'] ?? null, (float) $ctx['amount'], (string) ($ctx['currency'] ?? 'PHP'));
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
