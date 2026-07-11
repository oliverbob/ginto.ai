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

    /** Active course subscription plans for the pricing section (defensive — empty on any error). */
    private function subscriptionPlans(): array
    {
        try {
            $db = Database::getInstance();
            $rows = $db->select('subscription_plans', '*', [
                'plan_type' => 'courses',
                'is_active' => 1,
                'ORDER'     => ['price' => 'ASC'],
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
