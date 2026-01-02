<?php

declare(strict_types=1);

namespace App\Core;

use Medoo\Medoo;

/**
 * Manages multiple API keys per provider with automatic rotation on rate limits.
 * 
 * Rotation Logic:
 * 1. .env keys are used first (not stored in DB)
 * 2. When .env key hits rate limit, rotate to DB entry 1
 * 3. When entry 1 exhausts, go to entry 2, etc.
 * 4. When all DB keys exhausted, go back to .env keys (limits should reset by then)
 * 
 * Features:
 * - Multiple API keys per provider (Groq, Cerebras, etc.)
 * - Tier-based rate limits (basic/production)
 * - Automatic key rotation when rate limits are hit
 * - Error tracking and temporary key disabling
 */
class ProviderKeyManager
{
    private Medoo $db;
    
    // Rate limits by provider and tier
    private const TIER_LIMITS = [
        'groq' => [
            'basic' => [
                'rpm' => 30,
                'rpd' => 14400,
                'tpm' => 6000,
                'tpd' => 500000,
            ],
            'production' => [
                'rpm' => 1000,
                'rpd' => 1440000,
                'tpm' => 1000000,
                'tpd' => 2000000000,
            ],
        ],
        'cerebras' => [
            'basic' => [
                'rpm' => 30,
                'rpd' => 1000,
                'tpm' => 60000,
                'tpd' => 1000000,
            ],
            'production' => [
                'rpm' => 30,
                'rpd' => 14400,
                'tpm' => 64000,
                'tpd' => 1000000,
            ],
        ],
    ];

    public function __construct(Medoo $db)
    {
        $this->db = $db;
    }

    /**
     * Get the first available API key from ALL providers.
     * 
     * Selection logic:
     * 1. Active keys only
     * 2. Not rate-limited (or rate limit has reset)
     * 3. Sorted by ID (first added = first used)
     * 4. Provider-agnostic - uses whichever key is available
     * 
     * @return array|null ['id', 'api_key', 'provider', 'tier', 'limits']
     */
    public function getFirstAvailableKey(): ?array
    {
        $now = date('Y-m-d H:i:s');
        
        // Get all active keys ordered by ID (first added first)
        $keys = $this->db->select('provider_keys', '*', [
            'is_active' => 1,
            'ORDER' => [
                'id' => 'ASC',
            ],
        ]);
        
        if (empty($keys)) {
            return null;
        }
        
        // Find first key that's not rate-limited
        foreach ($keys as $key) {
            if ($key['rate_limit_reset_at'] === null || $key['rate_limit_reset_at'] <= $now) {
                return $this->formatKeyResult($key);
            }
        }
        
        // All keys are rate-limited, return null to signal "use .env"
        return null;
    }

    /**
     * Get the next available key after current one is rate-limited.
     * Cycles through ALL keys regardless of provider.
     * 
     * @param int $currentKeyId Current key ID that hit rate limit
     * @return array|null Next available key or null (meaning go back to .env)
     */
    public function getNextAvailableKey(int $currentKeyId): ?array
    {
        $now = date('Y-m-d H:i:s');
        
        // Get all active keys with ID greater than current, ordered by ID
        $keys = $this->db->select('provider_keys', '*', [
            'is_active' => 1,
            'id[>]' => $currentKeyId,
            'ORDER' => [
                'id' => 'ASC',
            ],
        ]);
        
        if (empty($keys)) {
            // No more keys after current one - return null to signal "use .env"
            return null;
        }
        
        // Find first key that's not rate-limited
        foreach ($keys as $key) {
            if ($key['rate_limit_reset_at'] === null || $key['rate_limit_reset_at'] <= $now) {
                return $this->formatKeyResult($key);
            }
        }
        
        // All remaining keys rate-limited - return null to signal "use .env"
        return null;
    }

    /**
     * Get the best available API key for a specific provider (legacy method).
     * 
     * @param string $provider Provider name (groq, cerebras)
     * @return array|null ['id', 'api_key', 'tier', 'limits']
     */
    public function getAvailableKey(string $provider): ?array
    {
        $now = date('Y-m-d H:i:s');
        
        // Get all active keys for provider, ordered by ID
        $keys = $this->db->select('provider_keys', '*', [
            'provider' => $provider,
            'is_active' => 1,
            'ORDER' => [
                'id' => 'ASC',
            ],
        ]);
        
        if (empty($keys)) {
            return null;
        }
        
        // Find first key that's not rate-limited
        foreach ($keys as $key) {
            if ($key['rate_limit_reset_at'] === null || $key['rate_limit_reset_at'] <= $now) {
                return $this->formatKeyResult($key);
            }
        }
        
        // All keys rate-limited
        return null;
    }

    /**
     * Mark a key as rate-limited.
     * 
     * @param int $keyId Key ID
     * @param int $resetAfterSeconds Seconds until rate limit resets (default 60)
     */
    public function markKeyRateLimited(int $keyId, int $resetAfterSeconds = 60): void
    {
        $resetAt = date('Y-m-d H:i:s', time() + $resetAfterSeconds);
        
        $this->db->update('provider_keys', [
            'rate_limit_reset_at' => $resetAt,
            'last_error_at' => date('Y-m-d H:i:s'),
            'error_count[+]' => 1,
        ], ['id' => $keyId]);
    }

    /**
     * Mark a key as successfully used.
     */
    public function markKeyUsed(int $keyId): void
    {
        $this->db->update('provider_keys', [
            'last_used_at' => date('Y-m-d H:i:s'),
            'error_count' => 0, // Reset error count on success
        ], ['id' => $keyId]);
    }

    /**
     * Clear rate limit for a key (e.g., when limit has reset).
     */
    public function clearRateLimit(int $keyId): void
    {
        $this->db->update('provider_keys', [
            'rate_limit_reset_at' => null,
        ], ['id' => $keyId]);
    }

    /**
     * Get all keys for a provider (for admin UI).
     */
    public function getKeysForProvider(string $provider): array
    {
        return $this->db->select('provider_keys', [
            'id',
            'provider',
            'key_name',
            'api_key',
            'tier',
            'is_default',
            'is_active',
            'last_used_at',
            'last_error_at',
            'error_count',
            'rate_limit_reset_at',
            'created_at',
        ], [
            'provider' => $provider,
            'ORDER' => ['id' => 'ASC'],
        ]);
    }

    /**
     * Get all keys (for admin UI).
     */
    public function getAllKeys(): array
    {
        return $this->db->select('provider_keys', [
            'id',
            'provider',
            'key_name',
            'api_key',
            'tier',
            'is_default',
            'is_active',
            'last_used_at',
            'last_error_at',
            'error_count',
            'rate_limit_reset_at',
            'created_at',
        ], [
            'ORDER' => ['provider' => 'ASC', 'id' => 'ASC'],
        ]);
    }

    /**
     * Add a new API key.
     */
    public function addKey(array $data): int
    {
        // If this is marked as default, unset other defaults for this provider
        if (!empty($data['is_default'])) {
            $this->db->update('provider_keys', [
                'is_default' => 0,
            ], ['provider' => $data['provider']]);
        }
        
        $this->db->insert('provider_keys', [
            'provider' => $data['provider'],
            'api_key' => $data['api_key'],
            'key_name' => $data['key_name'] ?? null,
            'tier' => $data['tier'] ?? 'basic',
            'is_default' => !empty($data['is_default']) ? 1 : 0,
            'is_active' => isset($data['is_active']) ? ($data['is_active'] ? 1 : 0) : 1,
        ]);
        
        return (int) $this->db->id();
    }

    /**
     * Update an existing key.
     */
    public function updateKey(int $id, array $data): bool
    {
        $key = $this->db->get('provider_keys', ['provider'], ['id' => $id]);
        if (!$key) {
            return false;
        }
        
        // If this is marked as default, unset other defaults for this provider
        if (!empty($data['is_default'])) {
            $this->db->update('provider_keys', [
                'is_default' => 0,
            ], ['provider' => $key['provider']]);
        }
        
        $updateData = [];
        if (isset($data['api_key'])) $updateData['api_key'] = $data['api_key'];
        if (isset($data['key_name'])) $updateData['key_name'] = $data['key_name'];
        if (isset($data['tier'])) $updateData['tier'] = $data['tier'];
        if (isset($data['is_default'])) $updateData['is_default'] = $data['is_default'] ? 1 : 0;
        if (isset($data['is_active'])) $updateData['is_active'] = $data['is_active'] ? 1 : 0;
        
        if (empty($updateData)) {
            return false;
        }
        
        $result = $this->db->update('provider_keys', $updateData, ['id' => $id]);
        return $result->rowCount() > 0;
    }

    /**
     * Delete a key.
     */
    public function deleteKey(int $id): bool
    {
        $result = $this->db->delete('provider_keys', ['id' => $id]);
        return $result->rowCount() > 0;
    }

    /**
     * Get tier-based rate limits for a key.
     */
    public function getTierLimits(string $provider, string $tier): array
    {
        return self::TIER_LIMITS[$provider][$tier] ?? self::TIER_LIMITS['groq']['basic'];
    }

    /**
     * Format key result with limits.
     */
    private function formatKeyResult(array $key): array
    {
        return [
            'id' => (int) $key['id'],
            'provider' => $key['provider'],
            'api_key' => $key['api_key'],
            'key_name' => $key['key_name'],
            'tier' => $key['tier'],
            'is_default' => (bool) $key['is_default'],
            'limits' => $this->getTierLimits($key['provider'], $key['tier']),
        ];
    }

    /**
     * Mask API key for display (show first 8 and last 4 chars).
     */
    public static function maskKey(string $key): string
    {
        if (strlen($key) <= 12) {
            return str_repeat('*', strlen($key));
        }
        return substr($key, 0, 8) . str_repeat('*', strlen($key) - 12) . substr($key, -4);
    }
}
