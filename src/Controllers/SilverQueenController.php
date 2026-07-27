<?php
namespace Ginto\Controllers;

use Ginto\Core\View;
use Ginto\Core\Database;
use Ginto\Services\SilverQueenEngine;

/**
 * SilverQueen (/silverqueen) — the tiered membership + SQB cloud-resource console.
 *
 * The whole route tree is hidden behind a hard guard: unless you are logged in AND
 * either hold an active Pro Trader membership or are an admin, every endpoint here
 * answers 404. Not 401, not 403 — 404, so the route's existence isn't discoverable
 * by probing.
 *
 * All money maths lives in \Ginto\Services\SilverQueenEngine; this controller is
 * guard + transport only.
 */
class SilverQueenController
{
    /** Plans that count as "Pro Trader" for access purposes. */
    private const PRO_PLANS = ['academy_pro'];

    private ?SilverQueenEngine $engine = null;

    private function engine(): SilverQueenEngine
    {
        return $this->engine ??= new SilverQueenEngine();
    }

    // ------------------------------------------------------------------ pages

    /** GET /silverqueen — the dashboard. */
    public function index(): void
    {
        $userId = $this->guard();
        $this->captureRef();

        $engine = $this->engine();
        $engine->enroll($userId, $this->pendingSponsorCode());
        $engine->sync($userId);   // catch up any 24h boundaries crossed since the last visit

        $elevated = $this->isElevated($userId);
        $snapshot = $engine->memberSnapshot($userId);

        View::view('silverqueen/dashboard', [
            'title'      => 'SilverQueen — Resource Allocation Console',
            'userId'     => $userId,
            'username'   => $_SESSION['username'] ?? '',
            'isElevated' => $elevated,
            'products'   => $engine->products(),
            'snapshot'   => $snapshot,
            'admin'      => $elevated ? $engine->adminSnapshot() : null,
            'inviteBase' => $this->baseUrl() . '/silverqueen?sqref=',
            'csrf_token' => function_exists('generateCsrfToken') ? generateCsrfToken(true) : ($_SESSION['csrf_token'] ?? ''),
        ]);
    }

    // ------------------------------------------------------------------- APIs

    /**
     * GET /silverqueen/data — the live snapshot the tracker polls. Runs a sync first,
     * so the numbers on screen are always settled to the current 24h boundary.
     */
    public function data(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $userId = $this->guardJson();

        try {
            $engine = $this->engine();
            $engine->sync($userId);
            $payload = ['ok' => true, 'snapshot' => $engine->memberSnapshot($userId), 'server_time' => date('c')];
            if ($this->isElevated($userId)) $payload['admin'] = $engine->adminSnapshot();
            echo json_encode($payload);
        } catch (\Throwable $e) {
            error_log('SilverQueen data: ' . $e->getMessage());
            echo json_encode(['ok' => false, 'error' => 'Could not load the console.']);
        }
        exit;
    }

    /** POST /silverqueen/purchase — buy a membership card or N SQB engine units. */
    public function purchase(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $userId = $this->guardJson();
        $input  = $this->jsonInput();
        $this->requireCsrf($input);

        $code  = preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($input['code'] ?? '')));
        $units = (int) ($input['units'] ?? 1);
        if ($code === '') { echo json_encode(['ok' => false, 'error' => 'Pick a product first.']); exit; }

        try {
            $engine = $this->engine();
            $engine->enroll($userId, $this->pendingSponsorCode());
            $result = $engine->purchase($userId, $code, $units);
            if (!empty($result['ok'])) $result['snapshot'] = $engine->memberSnapshot($userId);
            echo json_encode($result);
        } catch (\Throwable $e) {
            error_log('SilverQueen purchase: ' . $e->getMessage());
            echo json_encode(['ok' => false, 'error' => 'Could not complete the purchase.']);
        }
        exit;
    }

    /** POST /silverqueen/claim — Transfer to Wallet: accrued yield → internal wallet. */
    public function claim(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $userId = $this->guardJson();
        $this->requireCsrf($this->jsonInput());

        try {
            $engine = $this->engine();
            $engine->sync($userId);              // settle first, so nothing due is left behind
            $result = $engine->claim($userId);
            if (!empty($result['ok'])) $result['snapshot'] = $engine->memberSnapshot($userId);
            echo json_encode($result);
        } catch (\Throwable $e) {
            error_log('SilverQueen claim: ' . $e->getMessage());
            echo json_encode(['ok' => false, 'error' => 'Could not transfer the yield.']);
        }
        exit;
    }

    /**
     * POST /silverqueen/enroll — join the AntFun tree under an invite code. Only has
     * an effect the first time; sponsorship is immutable once set.
     */
    public function enroll(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $userId = $this->guardJson();
        $input  = $this->jsonInput();
        $this->requireCsrf($input);

        $code = trim((string) ($input['sponsor'] ?? ''));
        try {
            $engine  = $this->engine();
            $before  = $engine->referralRow($userId);
            $row     = $engine->enroll($userId, $code);
            $changed = (int) ($before['sponsor_id'] ?? 0) !== (int) ($row['sponsor_id'] ?? 0);
            echo json_encode([
                'ok'       => true,
                'changed'  => $changed,
                'referral' => $row,
                'message'  => $changed ? 'Sponsor linked.' : 'You are already placed in the AntFun tree.',
            ]);
        } catch (\Throwable $e) {
            error_log('SilverQueen enroll: ' . $e->getMessage());
            echo json_encode(['ok' => false, 'error' => 'Could not join the tree.']);
        }
        exit;
    }

    /**
     * POST /silverqueen/admin/run — force a platform-wide accrual + compounding pass.
     * Admin only; the same work the cron worker does, on demand.
     */
    public function adminRun(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $userId = $this->guardJson();
        if (!$this->isElevated($userId)) { http_response_code(404); echo json_encode(['ok' => false, 'error' => 'Not found']); exit; }
        $this->requireCsrf($this->jsonInput());

        try {
            $engine = $this->engine();
            $accrual  = $engine->accrueAll();
            $compound = $engine->compoundAllWallets();
            $engine->stampWorkerRun();
            echo json_encode(['ok' => true, 'accrual' => $accrual, 'compound' => $compound, 'admin' => $engine->adminSnapshot()]);
        } catch (\Throwable $e) {
            error_log('SilverQueen adminRun: ' . $e->getMessage());
            echo json_encode(['ok' => false, 'error' => 'The pass could not be completed.']);
        }
        exit;
    }

    // ------------------------------------------------------------------ guard

    /**
     * The route guard. Anyone who isn't a logged-in Pro Trader (or an admin) gets a
     * 404 page and the request ends here — the route stays invisible to everyone else.
     */
    private function guard(): int
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId <= 0 || !$this->hasAccess($userId)) $this->notFound();
        return $userId;
    }

    /** The same guard for JSON endpoints — still a 404, just machine-readable. */
    private function guardJson(): int
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId <= 0 || !$this->hasAccess($userId)) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Not found']);
            exit;
        }
        return $userId;
    }

    /**
     * Active Pro Trader membership, or elevated privileges. Nothing else opens this
     * door. Elevation implies access — whoever can see the system ledger must be able
     * to reach the page, even without a Pro subscription of their own.
     */
    private function hasAccess(int $userId): bool
    {
        return $this->isElevated($userId) || $this->isProTrader($userId);
    }

    /**
     * Elevated visibility: the deep-metrics view. Admins, plus the `oliverbob` operator
     * account which owns the pool and needs the raw ledger regardless of role flags.
     */
    private function isElevated(int $userId): bool
    {
        if ($this->isAdmin()) return true;
        $username = strtolower(trim((string) ($_SESSION['username'] ?? '')));
        if ($username === 'oliverbob') return true;
        try {
            $u = Database::getInstance()->get('users', 'username', ['id' => $userId]);
            return strtolower(trim((string) $u)) === 'oliverbob';
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * An active, unexpired Pro Trader subscription. Mirrors AcademyController's plan
     * lookup so the two products agree on what "Pro" means.
     */
    private function isProTrader(int $userId): bool
    {
        try {
            $db  = Database::getInstance();
            $sub = $db->get('user_subscriptions', ['plan_id', 'expires_at'], [
                'user_id' => $userId, 'status' => 'active', 'ORDER' => ['id' => 'DESC'],
            ]);
            if (!is_array($sub)) return false;

            $expires = $sub['expires_at'] ?? null;
            if (!empty($expires) && strtotime((string) $expires) <= time()) return false;

            $plan = $db->get('subscription_plans', ['name', 'display_name'], ['id' => $sub['plan_id']]);
            if (!is_array($plan)) return false;

            return in_array((string) ($plan['name'] ?? ''), self::PRO_PLANS, true)
                || strcasecmp(trim((string) ($plan['display_name'] ?? '')), 'Pro Trader') === 0;
        } catch (\Throwable $e) {
            error_log('SilverQueen isProTrader: ' . $e->getMessage());
            return false;
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

    /** Render the app's 404 and stop. */
    private function notFound(): void
    {
        http_response_code(404);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
           . '<meta name="viewport" content="width=device-width, initial-scale=1">'
           . '<title>404 Not Found</title>'
           . '<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;'
           . 'font-family:system-ui,-apple-system,"Segoe UI",sans-serif;background:#0b1020;color:#e5e7eb}'
           . 'div{text-align:center;padding:2rem}h1{font-size:4rem;margin:0;font-weight:800;letter-spacing:-.03em}'
           . 'p{color:#9ca3af;margin:.5rem 0 1.5rem}a{color:#6366f1;text-decoration:none;font-weight:600}</style>'
           . '</head><body><div><h1>404</h1><p>The page you requested could not be found.</p>'
           . '<a href="/">Return home</a></div></body></html>';
        exit;
    }

    // ----------------------------------------------------------------- inputs

    /** Body of a JSON (or form-encoded) POST, reusing whatever CSRF middleware cached. */
    private function jsonInput(): array
    {
        if (is_array($GLOBALS['_JSON_BODY'] ?? null)) return $GLOBALS['_JSON_BODY'];
        $raw = $GLOBALS['_RAW_BODY'] ?? file_get_contents('php://input');
        $decoded = json_decode((string) $raw, true);
        return is_array($decoded) ? $decoded : ($_POST ?: []);
    }

    /**
     * Every mutating endpoint here moves money, so each one validates CSRF itself
     * rather than relying on the global filter (which only covers /admin).
     */
    private function requireCsrf(array $input): void
    {
        $token = $input['csrf_token'] ?? $input['_csrf'] ?? $_POST['csrf_token'] ?? $_POST['_csrf']
              ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!function_exists('validateCsrfToken') || !validateCsrfToken((string) $token)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Your session expired — reload the page and try again.']);
            exit;
        }
    }

    /** Remember an AntFun sponsor from ?sqref= for the rest of the visit. */
    private function captureRef(): void
    {
        $ref = trim((string) ($_GET['sqref'] ?? ''));
        if ($ref !== '') $_SESSION['sq_ref'] = $ref;
    }

    private function pendingSponsorCode(): ?string
    {
        $code = trim((string) ($_SESSION['sq_ref'] ?? $_GET['sqref'] ?? ''));
        return $code !== '' ? $code : null;
    }

    private function baseUrl(): string
    {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
               || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        return ($secure ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'ginto.ai');
    }
}
