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
            $this->redirect('/login?redirect=' . urlencode('/academy/enter'));
            return;
        }
        if ($this->hasActiveSubscription((int) $userId)) {
            $this->redirect('/courses');           // active subscriber → the facility
            return;
        }
        $this->redirect('/subscribe');             // no subscription → paywall
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
        if (empty($userId)) { $this->redirect('/login?redirect=' . urlencode('/academy/pricing')); return; }

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
