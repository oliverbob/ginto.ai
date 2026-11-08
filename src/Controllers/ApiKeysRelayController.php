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
 * derived from SQ_RELAY_SECRET, which both hosts hold (the general relay secret,
 * narrowed by the 'sq-api-keys:' label to this one use). Checked: signature, a 5-minute clock window, and a
 * nonce that is never accepted twice.
 */
class ApiKeysRelayController
{
    private const WINDOW = 300;

    public function handle()
    {
        header('Cache-Control: no-store');
        $body = (string) file_get_contents('php://input');

        if (!$this->signed($body)) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'error' => 'Not signed.']);

            return;
        }

        $in = json_decode($body, true) ?: [];
        if (($in['action'] ?? '') === 'chat') {
            $this->streamChat($in['messages'] ?? []);
            return;
        }
        header('Content-Type: application/json; charset=utf-8');
        $keys = new ApiKeys(Database::getInstance());
        $by   = (int) ($in['by'] ?? 0);

        try {
            $out = match ((string) ($in['action'] ?? '')) {
                'status' => ['ok' => true, 'external' => $keys->external(), 'internal' => $keys->internal(),
                    'spend' => \Ginto\Support\AiSpend::summary(),
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
        echo json_encode($out + ['ok' => $out['ok'] ?? true]);
    }

    /** Stream a constrained text-only completion using the centrally managed provider keys. */
    public function streamChat(array $messages): void
    {
        if (!is_array($messages) || $messages === [] || count($messages) > 40) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => ['message' => 'A conversation with up to 40 messages is required.']]);
            return;
        }
        foreach ($messages as $message) {
            if (!is_array($message) || !in_array(($message['role'] ?? ''), ['system', 'user', 'assistant'], true)
                || !is_string($message['content'] ?? null) || strlen($message['content']) > 20000) {
                http_response_code(400);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => ['message' => 'The conversation contains an invalid message.']]);
                return;
            }
        }

        $db = Database::getInstance();
        $manager = new \App\Core\ProviderKeyManager($db);
        $attempts = [];
        foreach (['groq', 'cerebras'] as $provider) {
            $keyData = $manager->getAvailableKey($provider);
            $envName = $provider === 'groq' ? 'GROQ_API_KEY' : 'CEREBRAS_API_KEY';
            $envKey = (string) (\Ginto\Support\Env::get($envName, '') ?? '');
            if ($keyData) {
                $attempts[] = [$provider, $keyData['api_key'], (int) $keyData['id']];
            } elseif ($envKey !== '') {
                $rows = $manager->getKeysForProvider($provider);
                $mirroredAndLimited = false;
                foreach ($rows as $row) {
                    if (!empty($row['api_key']) && hash_equals((string) $row['api_key'], $envKey)) {
                        $mirroredAndLimited = true;
                        break;
                    }
                }
                if (!$mirroredAndLimited) $attempts[] = [$provider, $envKey, 0];
            }
        }
        if ($attempts === []) {
            http_response_code(503);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => ['message' => 'No active Groq or Cerebras provider key is available.']]);
            return;
        }

        while (ob_get_level() > 0) @ob_end_clean();
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-transform');
        header('X-Accel-Buffering: no');
        header('Connection: keep-alive');
        $lastError = '';
        foreach ($attempts as [$provider, $key, $keyId]) {
            $model = $provider === 'groq' ? 'openai/gpt-oss-20b' : 'gpt-oss-120b';
            $url = $provider === 'groq' ? 'https://api.groq.com/openai/v1/chat/completions' : 'https://api.cerebras.ai/v1/chat/completions';
            $payload = json_encode(['model' => $model, 'messages' => $messages, 'stream' => true, 'max_completion_tokens' => 4096], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $buffer = '';
            $started = false;
            $retry = false;
            $httpStatus = 0;
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 150,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: text/event-stream', 'Authorization: Bearer ' . $key],
                CURLOPT_WRITEFUNCTION => function ($curl, string $chunk) use (&$buffer, &$started, &$retry, &$httpStatus, &$lastError, $manager, $keyId): int {
                    $httpStatus = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
                    if ($httpStatus >= 400) {
                        $buffer .= $chunk;
                        if (strlen($buffer) > 8192) $buffer = substr($buffer, 0, 8192);
                        $decoded = json_decode($buffer, true);
                        $lastError = (string) ($decoded['error']['message'] ?? $decoded['message'] ?? 'Provider request failed.');
                        $retry = in_array($httpStatus, [401, 403, 408, 413, 429], true) || $httpStatus >= 500;
                        if ($keyId > 0 && in_array($httpStatus, [408, 413, 429], true)) $manager->markKeyRateLimited($keyId, 60);
                        return strlen($chunk);
                    }
                    $started = true;
                    echo $chunk;
                    if (ob_get_level() > 0) @ob_flush();
                    flush();
                    return strlen($chunk);
                },
            ]);
            curl_exec($ch);
            $httpStatus = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            if ($httpStatus >= 200 && $httpStatus < 300 && $started) {
                if ($keyId > 0) $manager->markKeyUsed($keyId);
                echo "data: [DONE]\n\n";
                flush();
                return;
            }
            if ($curlError !== '') { $lastError = 'The provider could not be reached.'; $retry = true; }
            if (!$retry || $started) break;
        }
        echo 'data: ' . json_encode(['error' => ['message' => $lastError ?: 'Both configured AI providers are unavailable.']]) . "\n\n";
        echo "data: [DONE]\n\n";
        flush();
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
        foreach (['SQ_RELAY_SECRET', 'SQ_RELAY_SECRET_PREVIOUS'] as $name) {
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
