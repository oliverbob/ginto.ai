<?php

namespace Ginto\Controllers;

use Ginto\Core\Database;
use Ginto\Services\ApiKeys;
use Ginto\Support\Env;

/**
 * POST /api/internal/api-keys -- the SilverQueen /superadmin "API Keys" page,
 * reaching the keys that live on this host (owner, 2026-10-24: "don't put it
 * on admin but superadmin").
 *
 * There is no page here and no session: the wallet's superadmin controller has
 * already checked the owner and his 2FA code, and signs each request with a key
 * derived from the relay secret both hosts hold (RELAY_JWT_SECRET here,
 * GINTO_RELAY_SECRET there). Checked: signature, a 5-minute clock window, and a
 * nonce that is never accepted twice.
 */
class ApiKeysRelayController
{
    private const WINDOW = 300;

    public function handle()
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        $body = (string) file_get_contents('php://input');

        if (!$this->signed($body)) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'error' => 'Not signed.']);

            return;
        }

        $in   = json_decode($body, true) ?: [];
        $keys = new ApiKeys(Database::getInstance());
        $by   = (int) ($in['by'] ?? 0);

        try {
            $out = match ((string) ($in['action'] ?? '')) {
                'status' => ['ok' => true, 'external' => $keys->external(), 'internal' => $keys->internal(),
                    'models' => class_exists(\Ginto\Sites\Config::class) ? \Ginto\Sites\Config::models() : []],
                'test'   => $keys->test((string) ($in['provider'] ?? ''), trim((string) ($in['key'] ?? ''))),
                'save'   => $this->save($keys, $in, $by),
                'remove' => ($keys->remove((string) ($in['provider'] ?? ''), (int) ($in['id'] ?? 0), $by) ?? ['ok' => true]),
                'revoke' => ($keys->revokeInternal((int) ($in['id'] ?? 0), $by) ?? ['ok' => true]),
                default  => ['ok' => false, 'error' => 'Unknown action.'],
            };
        } catch (\InvalidArgumentException $e) {
            $out = ['ok' => false, 'error' => $e->getMessage()];
        }
        if (isset($out['external']) && $out['external'] === []) {
            $out['external'] = [];
        }
        echo json_encode($out + ['ok' => $out['ok'] ?? true]);
    }

    private function save(ApiKeys $keys, array $in, int $by): array
    {
        $provider = (string) ($in['provider'] ?? '');
        $key      = trim((string) ($in['key'] ?? ''));
        $check    = $keys->test($provider, $key);
        if (!$check['ok'] && empty($in['force'])) {
            return ['ok' => false, 'error' => $check['error'] ?? 'The provider refused this key.'];
        }
        $keys->save($provider, $key, (string) ($in['tier'] ?? 'basic'), $by);

        return ['ok' => true, 'mask' => ApiKeys::mask($key), 'tested' => $check['ok']];
    }

    private function signed(string $body): bool
    {
        $ts    = (int) ($_SERVER['HTTP_X_SQ_TS'] ?? 0);
        $nonce = (string) ($_SERVER['HTTP_X_SQ_NONCE'] ?? '');
        $sig   = (string) ($_SERVER['HTTP_X_SQ_SIG'] ?? '');
        if (abs(time() - $ts) > self::WINDOW || preg_match('/^[0-9a-f]{32}$/', $nonce) !== 1 || $sig === '') {
            return false;
        }
        $ok = false;
        foreach (['RELAY_JWT_SECRET', 'RELAY_JWT_SECRET_PREVIOUS'] as $name) {
            $secret = (string) (Env::get($name, '') ?? '');
            if ($secret !== '' && hash_equals(hash_hmac('sha256', $ts . '.' . $nonce . '.' . $body, hash('sha256', 'sq-api-keys:' . $secret, true)), $sig)) {
                $ok = true;
                break;
            }
        }

        return $ok && $this->fresh($nonce);
    }

    /** A nonce is good once; they are remembered for twice the clock window. */
    private function fresh(string $nonce): bool
    {
        $file = STORAGE_PATH . '/cache/api-keys-nonces.json';
        if (!is_dir(dirname($file))) {
            @mkdir(dirname($file), 0700, true);
        }
        $fh = fopen($file, 'c+');
        if (!$fh) {
            return false;
        }
        flock($fh, LOCK_EX);
        $seen = json_decode((string) stream_get_contents($fh), true) ?: [];
        $now  = time();
        $seen = array_filter($seen, static fn ($t) => $t > $now - 2 * self::WINDOW);
        $fresh = !isset($seen[$nonce]);
        $seen[$nonce] = $now;
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, json_encode($seen));
        flock($fh, LOCK_UN);
        fclose($fh);

        return $fresh;
    }
}
