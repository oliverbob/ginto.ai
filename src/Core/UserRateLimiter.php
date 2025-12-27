<?php

declare(strict_types=1);

namespace App\Core;

use Medoo\Medoo;

/**
 * User-level rate limiter to protect against hitting provider limits.
 * 
 * Enforces per-user limits based on tier (admin/user/visitor) to distribute
 * the provider's global limits across all users. This prevents any single user
 * from exhausting the provider quota and triggering billing.
 * 
 * Limits are calculated as:
 * - Admin: Unlimited (or full provider limits)
 * - User: Provider limit / expected_users (e.g., 14400 RPD / 200 = 72 RPD per user)
 * - Visitor: 10% of user limit (to discourage abuse)
 */
class UserRateLimiter
{
    private Medoo $db;
    private string $provider;
    private int $expectedUsers;
    
    // Provider limits (Cerebras Production defaults)
    // Note: These are the ACCOUNT-WIDE limits. Per-user limits are calculated
    // by dividing by expected_users (but with minimums to ensure usability)
    private const PROVIDER_LIMITS = [
        'cerebras' => [
            'rpm' => 30,        // Requests per minute (account-wide)
            'rpd' => 14400,     // Requests per day
            'tpm' => 64000,     // Tokens per minute
            'tpd' => 1000000,   // Tokens per day
        ],
        'groq' => [
            'rpm' => 1000,           // Requests per minute (Production)
            'rpd' => 1440000,        // Requests per day (Production)
            'tpm' => 1000000,        // Tokens per minute (Production)
            'tpd' => 2000000000,     // Tokens per day (Production)
        ],
    ];
    
    // Minimum per-user limits (floor values to ensure usability)
    // These prevent limits from being too restrictive even with many expected users
    private const MIN_USER_LIMITS = [
        'rpm' => 5,     // At least 5 requests per minute
        'rpd' => 50,    // At least 50 requests per day
        'tpm' => 1000,  // At least 1000 tokens per minute
        'tpd' => 10000, // At least 10000 tokens per day
    ];
    
    // Tier multipliers (percentage of per-user allocation)
    private const TIER_MULTIPLIERS = [
        'admin' => 0,       // 0 = unlimited
        'user' => 100,      // 100% of per-user allocation
        'visitor' => 100,   // 100% of per-user allocation (same as user for chat)
    ];

    public function __construct(Medoo $db, string $provider = 'cerebras')
    {
        $this->db = $db;
        $this->provider = $provider;
        // Read expected users from environment, default to 200
        $this->expectedUsers = (int)(getenv('EXPECTED_USERS') ?: ($_ENV['EXPECTED_USERS'] ?? 200));
        if ($this->expectedUsers < 1) {
            $this->expectedUsers = 200;
        }
    }

    /**
     * Calculate per-user limits based on tier.
     * 
     * @param string $tier 'admin', 'user', or 'visitor'
     * @return array ['rpm' => int, 'rpd' => int, 'tpm' => int, 'tpd' => int]
     */
    public function getUserLimits(string $tier): array
    {
        $tier = strtolower($tier);
        $multiplier = self::TIER_MULTIPLIERS[$tier] ?? self::TIER_MULTIPLIERS['visitor'];
        
        // Admin gets unlimited
        if ($multiplier === 0) {
            return [
                'rpm' => PHP_INT_MAX,
                'rpd' => PHP_INT_MAX,
                'tpm' => PHP_INT_MAX,
                'tpd' => PHP_INT_MAX,
            ];
        }
        
        $providerLimits = self::PROVIDER_LIMITS[$this->provider] ?? self::PROVIDER_LIMITS['cerebras'];
        $minLimits = self::MIN_USER_LIMITS;
        
        // Calculate per-user share (with multiplier for RPM since not all users active same minute)
        $perUserShare = [
            'rpm' => max($minLimits['rpm'], (int)floor($providerLimits['rpm'] / $this->expectedUsers * 10)),
            'rpd' => max($minLimits['rpd'], (int)floor($providerLimits['rpd'] / $this->expectedUsers)),
            'tpm' => max($minLimits['tpm'], (int)floor($providerLimits['tpm'] / $this->expectedUsers * 10)),
            'tpd' => max($minLimits['tpd'], (int)floor($providerLimits['tpd'] / $this->expectedUsers)),
        ];
        
        // Apply tier multiplier (users get 100%, visitors get 100% - same as users for chat)
        return [
            'rpm' => max($minLimits['rpm'], (int)floor($perUserShare['rpm'] * $multiplier / 100)),
            'rpd' => max($minLimits['rpd'], (int)floor($perUserShare['rpd'] * $multiplier / 100)),
            'tpm' => max($minLimits['tpm'], (int)floor($perUserShare['tpm'] * $multiplier / 100)),
            'tpd' => max($minLimits['tpd'], (int)floor($perUserShare['tpd'] * $multiplier / 100)),
        ];
    }

    /**
     * Get current usage for a user.
     * 
     * @param int|null $userId User ID (null for visitors)
     * @param string|null $visitorIp IP address for visitors
     * @return array ['rpm' => int, 'rpd' => int, 'tpm' => int, 'tpd' => int]
     */
    public function getCurrentUsage(?int $userId, ?string $visitorIp = null): array
    {
        $today = date('Y-m-d');
        $currentMinute = date('Y-m-d H:i:00');
        
        // Build where clause
        if ($userId !== null) {
            $whereDaily = ['user_id' => $userId, 'date' => $today, 'provider' => $this->provider];
            $whereMinute = ['user_id' => $userId, 'minute_bucket' => $currentMinute, 'provider' => $this->provider];
        } else {
            $whereDaily = ['visitor_ip' => $visitorIp, 'date' => $today, 'provider' => $this->provider];
            $whereMinute = ['visitor_ip' => $visitorIp, 'minute_bucket' => $currentMinute, 'provider' => $this->provider];
        }
        
        // Get daily totals
        $dailyStats = $this->db->select('user_rate_limits', [
            'requests_count',
            'tokens_used',
        ], $whereDaily);
        
        $rpd = 0;
        $tpd = 0;
        foreach ($dailyStats as $row) {
            $rpd += (int)($row['requests_count'] ?? 0);
            $tpd += (int)($row['tokens_used'] ?? 0);
        }
        
        // Get current minute totals
        $minuteStats = $this->db->select('user_rate_limits', [
            'requests_count',
            'tokens_used',
        ], $whereMinute);
        
        $rpm = 0;
        $tpm = 0;
        foreach ($minuteStats as $row) {
            $rpm += (int)($row['requests_count'] ?? 0);
            $tpm += (int)($row['tokens_used'] ?? 0);
        }
        
        return [
            'rpm' => $rpm,
            'rpd' => $rpd,
            'tpm' => $tpm,
            'tpd' => $tpd,
        ];
    }

    /**
     * Check if user can make a request.
     * 
     * @param int|null $userId User ID (null for visitors)
     * @param string|null $visitorIp IP address for visitors
     * @param string $tier User tier ('admin', 'user', 'visitor')
     * @param int $estimatedTokens Estimated tokens for this request (optional)
     * @return array ['allowed' => bool, 'reason' => string|null, 'usage' => array, 'limits' => array]
     */
    public function checkLimit(?int $userId, ?string $visitorIp, string $tier, int $estimatedTokens = 0): array
    {
        $limits = $this->getUserLimits($tier);
        $usage = $this->getCurrentUsage($userId, $visitorIp);
        
        // Visitor-specific message suffix
        $isVisitor = ($tier === 'visitor');
        $visitorSuffix = $isVisitor 
            ? "\n\nTo continue using Ginto AI, please create an account. Go to /register, select any plan, and request admin approval. Admins can grant free plans if you ask nicely. Thank you!"
            : "";
        
        // Check RPM
        if ($usage['rpm'] >= $limits['rpm']) {
            $message = "You've reached your request limit for this minute. Please wait a moment and try again.";
            if ($isVisitor) {
                $message = "You've reached the visitor request limit." . $visitorSuffix;
            }
            return [
                'allowed' => false,
                'reason' => 'rate_limit_rpm',
                'message' => $message,
                'usage' => $usage,
                'limits' => $limits,
                'retry_after' => 60, // seconds
            ];
        }
        
        // Check RPD
        if ($usage['rpd'] >= $limits['rpd']) {
            $message = "You've reached your daily request limit. Your limit resets at midnight.";
            if ($isVisitor) {
                $message = "You've reached the visitor daily limit." . $visitorSuffix;
            }
            return [
                'allowed' => false,
                'reason' => 'rate_limit_rpd',
                'message' => $message,
                'usage' => $usage,
                'limits' => $limits,
                'retry_after' => $this->secondsUntilMidnight(),
            ];
        }
        
        // Check TPM (if we have an estimate)
        if ($estimatedTokens > 0 && ($usage['tpm'] + $estimatedTokens) > $limits['tpm']) {
            $message = "You've used too many tokens this minute. Please wait a moment and try again.";
            if ($isVisitor) {
                $message = "You've reached the visitor token limit." . $visitorSuffix;
            }
            return [
                'allowed' => false,
                'reason' => 'rate_limit_tpm',
                'message' => $message,
                'usage' => $usage,
                'limits' => $limits,
                'retry_after' => 60,
            ];
        }
        
        // Check TPD
        if ($usage['tpd'] >= $limits['tpd']) {
            $message = "You've reached your daily token limit. Your limit resets at midnight.";
            if ($isVisitor) {
                $message = "You've reached the visitor daily token limit." . $visitorSuffix;
            }
            return [
                'allowed' => false,
                'reason' => 'rate_limit_tpd',
                'message' => $message,
                'usage' => $usage,
                'limits' => $limits,
                'retry_after' => $this->secondsUntilMidnight(),
            ];
        }
        
        return [
            'allowed' => true,
            'reason' => null,
            'message' => null,
            'usage' => $usage,
            'limits' => $limits,
        ];
    }

    /**
     * Record a request and token usage.
     * 
     * @param int|null $userId User ID (null for visitors)
     * @param string|null $visitorIp IP address for visitors
     * @param int $tokensUsed Tokens used in this request
     */
    public function recordUsage(?int $userId, ?string $visitorIp, int $tokensUsed): void
    {
        $today = date('Y-m-d');
        $currentMinute = date('Y-m-d H:i:00');
        
        // Upsert daily record
        $this->upsertUsage($userId, $visitorIp, $today, null, 1, $tokensUsed);
        
        // Upsert minute record
        $this->upsertUsage($userId, $visitorIp, $today, $currentMinute, 1, $tokensUsed);
    }

    /**
     * Upsert a usage record.
     */
    private function upsertUsage(?int $userId, ?string $visitorIp, string $date, ?string $minuteBucket, int $requests, int $tokens): void
    {
        // Build where clause
        $where = [
            'date' => $date,
            'provider' => $this->provider,
        ];
        
        if ($minuteBucket !== null) {
            $where['minute_bucket'] = $minuteBucket;
        } else {
            $where['minute_bucket'] = null;
        }
        
        if ($userId !== null) {
            $where['user_id'] = $userId;
            $where['visitor_ip'] = null;
        } else {
            $where['user_id'] = null;
            $where['visitor_ip'] = $visitorIp;
        }
        
        // Check if record exists
        $existing = $this->db->get('user_rate_limits', ['id', 'requests_count', 'tokens_used'], $where);
        
        if ($existing) {
            // Update existing record
            $this->db->update('user_rate_limits', [
                'requests_count[+]' => $requests,
                'tokens_used[+]' => $tokens,
            ], ['id' => $existing['id']]);
        } else {
            // Insert new record
            $insertData = [
                'user_id' => $userId,
                'visitor_ip' => $visitorIp,
                'provider' => $this->provider,
                'date' => $date,
                'minute_bucket' => $minuteBucket,
                'requests_count' => $requests,
                'tokens_used' => $tokens,
            ];
            $this->db->insert('user_rate_limits', $insertData);
        }
    }

    /**
     * Calculate seconds until midnight.
     */
    private function secondsUntilMidnight(): int
    {
        $now = time();
        $midnight = strtotime('tomorrow midnight');
        return max(0, $midnight - $now);
    }

    /**
     * Clean up old records (call periodically).
     * Keeps records for 7 days for analytics.
     */
    public function cleanup(int $daysToKeep = 7): int
    {
        $cutoffDate = date('Y-m-d', strtotime("-{$daysToKeep} days"));
        $result = $this->db->delete('user_rate_limits', [
            'date[<]' => $cutoffDate,
        ]);
        return $result->rowCount();
    }

    /**
     * Get provider limits for display/debugging.
     */
    public function getProviderLimits(): array
    {
        return self::PROVIDER_LIMITS[$this->provider] ?? self::PROVIDER_LIMITS['cerebras'];
    }

    /**
     * Get expected users constant.
     */
    public function getExpectedUsers(): int
    {
        return $this->expectedUsers;
    }
}
