<?php

declare(strict_types=1);

namespace Ginto\Services;

use Ginto\Support\EnvFile;

/**
 * The keys the platform holds, as the admin's API Keys page sees them.
 *
 * External: a third party's key we were given (Groq, Cerebras). Saving one
 * writes it to .env, where every existing caller already reads it, AND to
 * provider_keys, where ProviderKeyManager rotates to it on a rate limit.
 * Internal: keys we issue ourselves (api_tokens), listed and revocable here.
 *
 * A key is never sent back to the browser whole: the page sees the last four
 * characters and nothing else.
 */
final class ApiKeys
{
    /** provider => what the page needs to know about it */
    public const EXTERNAL = [
        'groq' => [
            'label'  => 'Groq',
            'env'    => 'GROQ_API_KEY',
            'prefix' => 'gsk_',
            'models' => 'https://api.groq.com/openai/v1/models',
            'site'   => 'https://console.groq.com/keys',
            'uses'   => 'SilverQueen Sites AI (photo recognition, site builder, captions, customer chat), Live chat, speech to text',
        ],
        'cerebras' => [
            'label'  => 'Cerebras',
            'env'    => 'CEREBRAS_API_KEY',
            'prefix' => 'csk-',
            'models' => 'https://api.cerebras.ai/v1/models',
            'site'   => 'https://cloud.cerebras.ai',
            'uses'   => 'Chat fallback when Groq is rate-limited',
        ],
    ];

    /** Our own secrets that live in .env: shown as set or missing, never edited here (the other side holds a copy). */
    private const INTERNAL_ENV = ['APP_KEY', 'JWT_SECRET', 'SQ_RELAY_SECRET', 'SQ_MALL_RELAY_SECRET', 'RELAY_SHARED_SECRET', 'WEBHOOK_SECRET'];

    public function __construct(private $db)
    {
    }

    public static function mask(string $key): string
    {
        return $key === '' ? '' : '••••' . substr($key, -4);
    }

    /** @return list<array<string,mixed>> */
    public function external(): array
    {
        $out = [];
        foreach (self::EXTERNAL as $id => $p) {
            $env  = (string) ($_ENV[$p['env']] ?? getenv($p['env']) ?: '');
            $rows = [];
            try {
                $rows = $this->db->select('provider_keys',
                    ['id', 'api_key', 'key_name', 'tier', 'is_default', 'is_active', 'last_used_at', 'error_count', 'created_at'],
                    ['provider' => $id, 'user_id' => null, 'ORDER' => ['id' => 'ASC']]) ?: [];
            } catch (\Throwable $e) {
                $rows = [];
            }
            $out[] = $p + [
                'id'   => $id,
                'tier' => \Ginto\Support\AiSpend::tier($id),
                'env_set'  => $env !== '',
                'env_mask' => self::mask($env),
                'rows' => array_map(static fn (array $r): array => [
                    'id' => (int) $r['id'], 'mask' => self::mask((string) $r['api_key']), 'name' => (string) ($r['key_name'] ?? ''),
                    'tier' => (string) $r['tier'], 'default' => (bool) $r['is_default'], 'active' => (bool) $r['is_active'],
                    'in_env' => $env !== '' && hash_equals($env, (string) $r['api_key']),
                    'last_used_at' => $r['last_used_at'], 'errors' => (int) $r['error_count'], 'created_at' => $r['created_at'],
                ], $rows),
            ];
        }

        return $out;
    }

    /** @return array{ok:bool, status:int, error?:string} Does the provider accept this key? (a read-only models list) */
    public function test(string $provider, string $key): array
    {
        $p = self::EXTERNAL[$provider] ?? null;
        if ($p === null || $key === '') {
            return ['ok' => false, 'status' => 0, 'error' => 'Unknown provider or empty key.'];
        }
        $ch = curl_init($p['models']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 12,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $key],
        ]);
        curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $status === 200
            ? ['ok' => true, 'status' => 200]
            : ['ok' => false, 'status' => $status, 'error' => $status === 401 ? 'The provider refused this key.' : 'The provider did not answer (' . $status . ').'];
    }

    /** Save a key: .env (what callers read) and provider_keys (what rotation reads), as the provider's default. */
    public function save(string $provider, string $key, string $tier, int $byUserId): void
    {
        $p = self::EXTERNAL[$provider] ?? null;
        if ($p === null) {
            throw new \InvalidArgumentException('Unknown provider.');
        }
        $key = trim($key);
        if (!str_starts_with($key, $p['prefix']) || strlen($key) < 20 || strlen($key) > 255 || preg_match('/\s/', $key) === 1) {
            throw new \InvalidArgumentException($p['label'] . ' keys start with ' . $p['prefix'] . ' and have no spaces.');
        }
        $tier = $tier === 'production' ? 'production' : 'basic';

        // The key and its tier together: the tier decides whether its calls cost money (AiSpend).
        EnvFile::set([$p['env'] => $key, strtoupper($provider) . '_TIER' => $tier === 'production' ? 'paid' : 'free']);

        $this->db->update('provider_keys', ['is_default' => 0], ['provider' => $provider, 'user_id' => null]);
        $have = $this->db->get('provider_keys', ['id'], ['provider' => $provider, 'user_id' => null, 'api_key' => $key]);
        $row  = ['key_name' => 'Set on API Keys', 'tier' => $tier, 'is_default' => 1, 'is_active' => 1, 'error_count' => 0, 'rate_limit_reset_at' => null];
        if ($have) {
            $this->db->update('provider_keys', $row, ['id' => (int) $have['id']]);
        } else {
            $this->db->insert('provider_keys', $row + ['provider' => $provider, 'api_key' => $key]);
        }
        $this->audit($byUserId, 'save', $provider, self::mask($key));
    }

    /** Remove one stored key; if it is also the one in .env, .env is emptied too. */
    public function remove(string $provider, int $rowId, int $byUserId): void
    {
        $p   = self::EXTERNAL[$provider] ?? null;
        $row = $p ? $this->db->get('provider_keys', ['id', 'api_key'], ['id' => $rowId, 'provider' => $provider, 'user_id' => null]) : null;
        if (!$row) {
            throw new \InvalidArgumentException('No such key.');
        }
        $env = (string) ($_ENV[$p['env']] ?? getenv($p['env']) ?: '');
        if ($env !== '' && hash_equals($env, (string) $row['api_key'])) {
            EnvFile::set([$p['env'] => '']);
        }
        $this->db->delete('provider_keys', ['id' => $rowId]);
        $this->audit($byUserId, 'remove', $provider, self::mask((string) $row['api_key']));
    }

    /** @return array{tokens: list<array<string,mixed>>, env: list<array{name:string,set:bool}>} */
    public function internal(): array
    {
        $tokens = [];
        try {
            $tokens = $this->db->select('api_tokens', ['[>]users' => ['user_id' => 'id']],
                ['api_tokens.id', 'api_tokens.name', 'api_tokens.last_used_at', 'api_tokens.created_at', 'api_tokens.expires_at', 'users.username'],
                ['api_tokens.revoked' => 0, 'ORDER' => ['api_tokens.id' => 'DESC'], 'LIMIT' => 200]) ?: [];
        } catch (\Throwable $e) {
            $tokens = [];
        }
        $env = array_map(static fn (string $k): array => ['name' => $k, 'set' => (string) ($_ENV[$k] ?? getenv($k) ?: '') !== ''], self::INTERNAL_ENV);

        return ['tokens' => $tokens, 'env' => $env];
    }

    public function revokeInternal(int $tokenId, int $byUserId): void
    {
        $this->db->update('api_tokens', ['revoked' => 1, 'revoked_at' => date('Y-m-d H:i:s')], ['id' => $tokenId]);
        $this->audit($byUserId, 'revoke', 'internal', '#' . $tokenId);
    }

    /** Who changed which key, when -- never the key itself. */
    private function audit(int $userId, string $what, string $provider, string $mask): void
    {
        $dir = STORAGE_PATH . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        @file_put_contents($dir . '/api-keys.log',
            date('c') . " user={$userId} {$what} {$provider} {$mask}\n", FILE_APPEND | LOCK_EX);
    }
}
