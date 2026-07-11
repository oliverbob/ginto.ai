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
        $this->redirect('/academy/pricing');       // no subscription → membership
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
            $this->redirect('/academy/pricing');   // locked → membership
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

    /** GET /academy/pricing — the branded Academy membership page (its own, not /courses/pricing). */
    public function pricing(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        $userId = $_SESSION['user_id'] ?? null;
        View::view('academy/pricing', [
            'title'      => 'Membership — Ginto Trading Academy',
            'isLoggedIn' => !empty($userId),
            'hasAccess'  => !empty($userId) && $this->hasActiveSubscription((int) $userId),
            'plans'      => $this->subscriptionPlans(),
            'csrf_token' => function_exists('generateCsrfToken') ? generateCsrfToken(true) : ($_SESSION['csrf_token'] ?? ''),
        ]);
    }

    /**
     * GET /academy/subscribe?plan=academy_pro — create a PayMongo hosted checkout for a plan
     * and redirect to it. On payment, checkout_session.payment.paid activates the membership.
     */
    public function subscribe(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        $userId = $_SESSION['user_id'] ?? null;
        if (empty($userId)) { $this->redirect('/login?promo=GINTO-ACADEMY&redirect=' . urlencode('/academy/pricing')); return; }

        $planName = (string) ($_GET['plan'] ?? '');
        try {
            $db   = Database::getInstance();
            $plan = $db->get('subscription_plans', '*', ['name' => $planName, 'plan_type' => 'academy', 'is_active' => 1]);
            if (!$plan) { $this->redirect('/academy/pricing'); return; }

            $user  = $db->get('users', ['email', 'name', 'username', 'fullname'], ['id' => $userId]);
            $email = $user['email'] ?? '';
            $name  = $user['fullname'] ?? ($user['name'] ?? ($user['username'] ?? 'Ginto Learner'));
            if ($email === '') { $this->redirect('/academy/pricing?err=email'); return; }

            $amount = (int) round(((float) $plan['price_monthly']) * 100); // centavos
            $base   = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'ginto.ai');

            $pm = new \Ginto\Handlers\PayMongoHandler();
            $res = $pm->createCheckoutSession(
                $amount,
                'Ginto Trading Academy — ' . ($plan['display_name'] ?? 'Membership'),
                (string) $name,
                (string) $email,
                $base . '/academy/subscribe/success',
                $base . '/academy/pricing'
            );
            if (empty($res['success']) || empty($res['checkout_url'])) {
                error_log('Academy checkout create failed: ' . json_encode($res));
                $this->redirect('/academy/pricing?err=checkout'); return;
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
            error_log('Academy subscribe error: ' . $e->getMessage());
            $this->redirect('/academy/pricing?err=1');
        }
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
