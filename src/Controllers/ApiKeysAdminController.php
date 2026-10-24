<?php

namespace Ginto\Controllers;

use Ginto\Core\Database;
use Ginto\Services\ApiKeys;
use Ginto\Services\TotpService;

/**
 * Admin → API Keys (owner, 2026-10-24: "a page in admin ... that says: API Keys.
 * Then a section that says internal (our own apis), or external, the apis of
 * 3rd party, like groq").
 *
 * Every change needs the admin's authenticator code when 2FA is on: a key here
 * spends money at the provider, and a stolen session must not be enough.
 */
class ApiKeysAdminController extends \Core\Controller
{
    private $db;
    private int $me;

    public function __construct($db = null)
    {
        $this->db = $db ?? Database::getInstance();
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $this->me = (int) ($_SESSION['user_id'] ?? 0);
        if ($this->me <= 0) {
            header('Location: /login');
            exit;
        }
        $user = $this->db->get('users', ['role_id'], ['id' => $this->me]);
        if (!$user || !in_array((int) $user['role_id'], [1, 2], true)) {
            http_response_code(403);
            echo '<h1>403 Forbidden</h1>';
            exit;
        }
    }

    public function index()
    {
        $keys = new ApiKeys($this->db);
        $this->view('admin/api-keys/index', [
            'title'      => 'API Keys',
            'external'   => $keys->external(),
            'internal'   => $keys->internal(),
            'needs_code' => (new TotpService($this->db))->isEnabled($this->me),
            'csrf_token' => generateCsrfToken(),
        ]);
    }

    /** POST /admin/api-keys/test {provider, key} -- does the provider accept it? Changes nothing. */
    public function test()
    {
        $in = $this->input();
        $this->json((new ApiKeys($this->db))->test((string) ($in['provider'] ?? ''), trim((string) ($in['key'] ?? ''))));
    }

    /** POST /admin/api-keys/save {provider, key, tier, code} */
    public function save()
    {
        $in = $this->input();
        if (!$this->codeOk($in)) {
            return;
        }
        $keys     = new ApiKeys($this->db);
        $provider = (string) ($in['provider'] ?? '');
        $key      = trim((string) ($in['key'] ?? ''));
        $check    = $keys->test($provider, $key);
        if (!$check['ok'] && empty($in['force'])) {
            $this->json(['success' => false, 'error' => $check['error'] ?? 'The provider refused this key.', 'untested' => true], 422);
            return;
        }
        try {
            $keys->save($provider, $key, (string) ($in['tier'] ?? 'basic'), $this->me);
        } catch (\InvalidArgumentException $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 422);
            return;
        }
        $this->json(['success' => true, 'mask' => ApiKeys::mask($key), 'tested' => $check['ok']]);
    }

    /** POST /admin/api-keys/remove {provider, id, code} */
    public function remove()
    {
        $in = $this->input();
        if (!$this->codeOk($in)) {
            return;
        }
        try {
            (new ApiKeys($this->db))->remove((string) ($in['provider'] ?? ''), (int) ($in['id'] ?? 0), $this->me);
        } catch (\InvalidArgumentException $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 422);
            return;
        }
        $this->json(['success' => true]);
    }

    /** POST /admin/api-keys/revoke {id, code} -- one of our own issued tokens. */
    public function revoke()
    {
        $in = $this->input();
        if (!$this->codeOk($in)) {
            return;
        }
        (new ApiKeys($this->db))->revokeInternal((int) ($in['id'] ?? 0), $this->me);
        $this->json(['success' => true]);
    }

    private function codeOk(array $in): bool
    {
        $totp = new TotpService($this->db);
        if (!$totp->isEnabled($this->me)) {
            return true;
        }
        if ($totp->verifyUserCode($this->me, preg_replace('/\D/', '', (string) ($in['code'] ?? '')))) {
            return true;
        }
        $this->json(['success' => false, 'error' => 'That code did not match. Use the current code from your authenticator.', 'code' => true], 403);

        return false;
    }

    private function input(): array
    {
        $raw = json_decode((string) file_get_contents('php://input'), true);

        return is_array($raw) ? $raw : $_POST;
    }

    private function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data);
    }
}
