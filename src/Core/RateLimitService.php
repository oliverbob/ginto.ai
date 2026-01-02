<?php

declare(strict_types=1);

namespace App\Core;

use Ginto\Core\Database;
use Medoo\Medoo;

/**
 * Rate Limiting Service
 * 
 * Tracks API usage across users and providers, enforces tier-based rate limits,
 * and handles automatic fallback to alternative providers when limits are approached.
 * 
 * Rate Limits by Tier (% of organization limit):
 * - Admin: 50%
 * - User: 10%
 * - Visitor: 5%
 * 
 * Supported Providers:
 * - Groq: Primary provider (lower limits)
 * - Cerebras: Fallback provider (higher limits)
 */
class RateLimitService
{
    private Medoo $db;
    private array $config;
    
    // Default rate limits from environment or hardcoded
    // Sources: 
    // - Groq: https://console.groq.com/docs/rate-limits (Developer Plan)
    // - Cerebras: https://inference-docs.cerebras.ai/support/rate-limits (Developer Plan)
    private array $defaultLimits = [
        'groq' => [
            'openai/gpt-oss-120b' => [
                'rpm' => 1000,       // Requests per minute
                'rpd' => 500000,     // Requests per day
                'tpm' => 250000,     // Tokens per minute
                'tpd' => 10000000,   // Tokens per day (not specified, using high value)
            ],
            'llama-3.3-70b-versatile' => [
                'rpm' => 1000,       // Requests per minute
                'rpd' => 500000,     // Requests per day
                'tpm' => 300000,     // Tokens per minute
                'tpd' => 10000000,   // Tokens per day (not specified, using high value)
            ],
            // TTS models (PlayAI via Groq)
            'playai-tts' => [
                'rpm' => 250,        // Requests per minute
                'rpd' => 100000,     // Requests per day
            ],
            'playai-tts-turbo' => [
                'rpm' => 250,        // Requests per minute
                'rpd' => 100000,     // Requests per day
            ],
            'gpt-4o-mini-tts' => [
                'rpm' => 250,        // Requests per minute
                'rpd' => 100000,     // Requests per day
            ],
        ],
        'cerebras' => [
            'gpt-oss-120b' => [
                'rpm' => 30,         // Requests per minute
                'rpd' => 14400,      // Requests per day
                'tpm' => 64000,      // Tokens per minute
                'tpd' => 1000000,    // Tokens per day
            ],
        ],
    ];

    // Tier percentages (of organization limit)
    private array $tierPercentages = [
        'admin' => 50,
        'user' => 10,
        'visitor' => 5,
    ];

    // Minimum limits per tier (ensures usability even with low org limits)
    // These are the floor values - tier limits can never go below these
    private array $minimumLimits = [
        'admin' => [
            'rpm' => 20,      // At least 20 requests per minute
            'rpd' => 500,     // At least 500 requests per day
            'tpm' => 5000,    // At least 5000 tokens per minute
            'tpd' => 100000,  // At least 100k tokens per day
        ],
        'user' => [
            'rpm' => 10,      // At least 10 requests per minute
            'rpd' => 200,     // At least 200 requests per day
            'tpm' => 3000,    // At least 3000 tokens per minute
            'tpd' => 50000,   // At least 50k tokens per day
        ],
        'visitor' => [
            'rpm' => 5,       // At least 5 requests per minute
            'rpd' => 50,      // At least 50 requests per day
            'tpm' => 2000,    // At least 2000 tokens per minute
            'tpd' => 20000,   // At least 20k tokens per day
        ],
    ];

    public function __construct(?Medoo $db = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->loadConfig();
    }

    /**
     * Load configuration from environment variables
     */
    private function loadConfig(): void
    {
        $this->config = [
            'fallback_provider' => getenv('RATE_LIMIT_FALLBACK_PROVIDER') ?: 'cerebras',
            'fallback_threshold' => (int)(getenv('RATE_LIMIT_FALLBACK_THRESHOLD') ?: 80),
        ];

        // Override tier percentages from env if set
        if ($adminPercent = getenv('RATE_LIMIT_ADMIN_PERCENT')) {
            $this->tierPercentages['admin'] = (int)$adminPercent;
        }
        if ($userPercent = getenv('RATE_LIMIT_USER_PERCENT')) {
            $this->tierPercentages['user'] = (int)$userPercent;
        }
        if ($visitorPercent = getenv('RATE_LIMIT_VISITOR_PERCENT')) {
            $this->tierPercentages['visitor'] = (int)$visitorPercent;
        }
    }

    /**
     * Get rate limits for a provider/model from database or defaults
     */
    public function getRateLimits(string $provider, string $model): array
    {
        // Try to get from database first
        try {
            $limits = $this->db->select('rate_limit_config', ['limit_type', 'limit_value'], [
                'provider' => $provider,
                'model' => $model,
            ]);

            if (!empty($limits)) {
                $result = [];
                foreach ($limits as $limit) {
                    $result[$limit['limit_type']] = (int)$limit['limit_value'];
                }
                return $result;
            }
        } catch (\Throwable $e) {
            // Table might not exist yet, use defaults
        }

        // Return defaults
        return $this->defaultLimits[$provider][$model] ?? [
            'rpm' => 30,
            'rpd' => 1000,
            'tpm' => 10000,
            'tpd' => 100000,
        ];
    }

    /**
     * Get the effective rate limit for a user tier (with minimum floors applied)
     */
    public function getTierLimit(string $tier, string $limitType, string $provider, string $model): int
    {
        $tier = strtolower($tier);
        $orgLimit = $this->getRateLimits($provider, $model)[$limitType] ?? 0;
        $percentage = $this->tierPercentages[$tier] ?? 5;
        $mins = $this->minimumLimits[$tier] ?? $this->minimumLimits['visitor'];
        $minLimit = $mins[$limitType] ?? 1;
        
        return max($minLimit, (int)floor($orgLimit * ($percentage / 100)));
    }

    /**
     * Log an API request
     */
    public function logRequest(array $data): bool
    {
        try {
            $this->db->insert('api_requests', [
                'user_id' => $data['user_id'] ?? null,
                'user_role' => $data['user_role'] ?? 'visitor',
                'provider' => $data['provider'],
                'model' => $data['model'],
                'tokens_input' => $data['tokens_input'] ?? 0,
                'tokens_output' => $data['tokens_output'] ?? 0,
                'tokens_total' => ($data['tokens_input'] ?? 0) + ($data['tokens_output'] ?? 0),
                'request_type' => $data['request_type'] ?? 'chat',
                'response_status' => $data['response_status'] ?? 'success',
                'fallback_used' => $data['fallback_used'] ?? 0,
                'latency_ms' => $data['latency_ms'] ?? 0,
                'ip_address' => $data['ip_address'] ?? ($_SERVER['REMOTE_ADDR'] ?? null),
            ]);
            return true;
        } catch (\Throwable $e) {
            error_log("RateLimitService::logRequest error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get usage statistics for a user within a time window
     */
    public function getUsage(string $userId, string $provider, string $model, string $window = 'minute'): array
    {
        $windowSeconds = match($window) {
            'minute' => 60,
            'hour' => 3600,
            'day' => 86400,
            default => 60,
        };

        $since = date('Y-m-d H:i:s', time() - $windowSeconds);

        try {
            // Count requests
            $requestCount = $this->db->count('api_requests', [
                'user_id' => $userId,
                'provider' => $provider,
                'model' => $model,
                'created_at[>=]' => $since,
            ]);

            // Sum tokens
            $tokenSum = $this->db->sum('api_requests', 'tokens_total', [
                'user_id' => $userId,
                'provider' => $provider,
                'model' => $model,
                'created_at[>=]' => $since,
            ]);

            return [
                'requests' => (int)$requestCount,
                'tokens' => (int)($tokenSum ?? 0),
                'window' => $window,
                'since' => $since,
            ];
        } catch (\Throwable $e) {
            return ['requests' => 0, 'tokens' => 0, 'window' => $window, 'since' => $since];
        }
    }

    /**
     * Get organization-wide usage (all users combined)
     */
    public function getOrgUsage(string $provider, string $model, string $window = 'minute'): array
    {
        $windowSeconds = match($window) {
            'minute' => 60,
            'hour' => 3600,
            'day' => 86400,
            default => 60,
        };

        $since = date('Y-m-d H:i:s', time() - $windowSeconds);

        try {
            $requestCount = $this->db->count('api_requests', [
                'provider' => $provider,
                'model' => $model,
                'created_at[>=]' => $since,
            ]);

            $tokenSum = $this->db->sum('api_requests', 'tokens_total', [
                'provider' => $provider,
                'model' => $model,
                'created_at[>=]' => $since,
            ]);

            return [
                'requests' => (int)$requestCount,
                'tokens' => (int)($tokenSum ?? 0),
                'window' => $window,
                'since' => $since,
            ];
        } catch (\Throwable $e) {
            return ['requests' => 0, 'tokens' => 0, 'window' => $window, 'since' => $since];
        }
    }

    /**
     * Check if a user can make a request (within their tier limits)
     */
    public function canMakeRequest(string $userId, string $userRole, string $provider, string $model): array
    {
        $limits = $this->getRateLimits($provider, $model);
        $tier = strtolower($userRole);
        $percentage = $this->tierPercentages[$tier] ?? 5;
        $mins = $this->minimumLimits[$tier] ?? $this->minimumLimits['visitor'];

        // Check minute limits - apply minimum floors to ensure usability
        $minuteUsage = $this->getUsage($userId, $provider, $model, 'minute');
        $rpmLimit = max($mins['rpm'], (int)floor(($limits['rpm'] ?? 30) * ($percentage / 100)));
        $tpmLimit = max($mins['tpm'], (int)floor(($limits['tpm'] ?? 10000) * ($percentage / 100)));

        if ($minuteUsage['requests'] >= $rpmLimit) {
            return [
                'allowed' => false,
                'reason' => 'rpm_exceeded',
                'limit' => $rpmLimit,
                'current' => $minuteUsage['requests'],
                'retry_after' => 60,
            ];
        }

        if ($minuteUsage['tokens'] >= $tpmLimit) {
            return [
                'allowed' => false,
                'reason' => 'tpm_exceeded',
                'limit' => $tpmLimit,
                'current' => $minuteUsage['tokens'],
                'retry_after' => 60,
            ];
        }

        // Check daily limits - apply minimum floors
        $dayUsage = $this->getUsage($userId, $provider, $model, 'day');
        $rpdLimit = max($mins['rpd'], (int)floor(($limits['rpd'] ?? 1000) * ($percentage / 100)));
        $tpdLimit = max($mins['tpd'], (int)floor(($limits['tpd'] ?? 100000) * ($percentage / 100)));

        if ($dayUsage['requests'] >= $rpdLimit) {
            return [
                'allowed' => false,
                'reason' => 'rpd_exceeded',
                'limit' => $rpdLimit,
                'current' => $dayUsage['requests'],
                'retry_after' => 86400 - (time() % 86400),
            ];
        }

        if ($dayUsage['tokens'] >= $tpdLimit) {
            return [
                'allowed' => false,
                'reason' => 'tpd_exceeded',
                'limit' => $tpdLimit,
                'current' => $dayUsage['tokens'],
                'retry_after' => 86400 - (time() % 86400),
            ];
        }

        return [
            'allowed' => true,
            'usage' => [
                'rpm' => ['current' => $minuteUsage['requests'], 'limit' => $rpmLimit],
                'tpm' => ['current' => $minuteUsage['tokens'], 'limit' => $tpmLimit],
                'rpd' => ['current' => $dayUsage['requests'], 'limit' => $rpdLimit],
                'tpd' => ['current' => $dayUsage['tokens'], 'limit' => $tpdLimit],
            ],
        ];
    }

    /**
     * Check organization-level limits and determine if fallback should be used
     */
    public function shouldUseFallback(string $provider, string $model): array
    {
        $limits = $this->getRateLimits($provider, $model);
        $threshold = $this->config['fallback_threshold'];

        // Check minute usage (most likely to hit)
        $minuteUsage = $this->getOrgUsage($provider, $model, 'minute');
        $rpmPercent = ($limits['rpm'] > 0) ? ($minuteUsage['requests'] / $limits['rpm']) * 100 : 0;
        $tpmPercent = ($limits['tpm'] > 0) ? ($minuteUsage['tokens'] / $limits['tpm']) * 100 : 0;

        // Check daily usage
        $dayUsage = $this->getOrgUsage($provider, $model, 'day');
        $rpdPercent = ($limits['rpd'] > 0) ? ($dayUsage['requests'] / $limits['rpd']) * 100 : 0;
        $tpdPercent = ($limits['tpd'] > 0) ? ($dayUsage['tokens'] / $limits['tpd']) * 100 : 0;

        $maxPercent = max($rpmPercent, $tpmPercent, $rpdPercent, $tpdPercent);

        if ($maxPercent >= $threshold) {
            return [
                'use_fallback' => true,
                'reason' => "Usage at {$maxPercent}% of limit (threshold: {$threshold}%)",
                'fallback_provider' => $this->config['fallback_provider'],
                'usage_percent' => [
                    'rpm' => round($rpmPercent, 1),
                    'tpm' => round($tpmPercent, 1),
                    'rpd' => round($rpdPercent, 1),
                    'tpd' => round($tpdPercent, 1),
                ],
            ];
        }

        return [
            'use_fallback' => false,
            'usage_percent' => [
                'rpm' => round($rpmPercent, 1),
                'tpm' => round($tpmPercent, 1),
                'rpd' => round($rpdPercent, 1),
                'tpd' => round($tpdPercent, 1),
            ],
        ];
    }

    /**
     * Get the best provider to use (primary or fallback)
     * Supports bidirectional fallback: groq ↔ cerebras
     */
    public function selectProvider(string $primaryProvider, string $model): array
    {
        $fallbackCheck = $this->shouldUseFallback($primaryProvider, $model);
        
        if ($fallbackCheck['use_fallback']) {
            // Determine fallback provider (opposite of primary)
            $fallbackProvider = ($primaryProvider === 'cerebras') ? 'groq' : 
                               ($this->config['fallback_provider'] ?? 'cerebras');
            
            // Verify fallback provider is configured
            $fallbackApiKey = match($fallbackProvider) {
                'cerebras' => getenv('CEREBRAS_API_KEY'),
                'groq' => getenv('GROQ_API_KEY'),
                'openai' => getenv('OPENAI_API_KEY'),
                default => null,
            };

            if ($fallbackApiKey) {
                return [
                    'provider' => $fallbackProvider,
                    'model' => $model,
                    'is_fallback' => true,
                    'reason' => $fallbackCheck['reason'],
                    'primary_usage' => $fallbackCheck['usage_percent'],
                ];
            }
        }

        return [
            'provider' => $primaryProvider,
            'model' => $model,
            'is_fallback' => false,
            'usage' => $fallbackCheck['usage_percent'] ?? null,
        ];
    }

    /**
     * Get usage summary for a user
     */
    public function getUserSummary(string $userId, string $userRole): array
    {
        $providers = ['groq', 'cerebras'];
        $summary = [];

        foreach ($providers as $provider) {
            $models = $this->db->select('api_requests', 'model', [
                'user_id' => $userId,
                'provider' => $provider,
                'GROUP' => 'model',
            ]);

            foreach ($models as $model) {
                $key = "{$provider}/{$model}";
                $summary[$key] = [
                    'minute' => $this->getUsage($userId, $provider, $model, 'minute'),
                    'hour' => $this->getUsage($userId, $provider, $model, 'hour'),
                    'day' => $this->getUsage($userId, $provider, $model, 'day'),
                    'limits' => $this->getRateLimits($provider, $model),
                    'tier' => $userRole,
                    'tier_percentage' => $this->tierPercentages[strtolower($userRole)] ?? 5,
                ];
            }
        }

        return $summary;
    }

    /**
     * Clean up old request logs (keep last 30 days)
     */
    public function cleanup(int $daysToKeep = 30): int
    {
        $cutoff = date('Y-m-d H:i:s', time() - ($daysToKeep * 86400));
        
        try {
            $result = $this->db->delete('api_requests', [
                'created_at[<]' => $cutoff,
            ]);
            return $result->rowCount();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Check if TTS request is allowed based on rate limits.
     * 
     * Rate limits by role:
     * - Admin: 50% of organization TTS quota
     * - User: 30 requests per hour
     * - Visitor: 10 requests per session (tracked via session_id in api_requests)
     * 
     * Returns detailed info for showing appropriate messages when limit is hit.
     * 
     * @param string $model The TTS model (e.g., 'playai-tts', 'gpt-4o-mini-tts')
     * @param string $provider The provider (default 'groq')
     * @param string $userRole User role: 'admin', 'user', or 'visitor'
     * @param int|null $userId User ID (null for visitors)
     * @param string|null $sessionId Session ID for visitor tracking
     * @return array ['allowed' => bool, 'reason' => string|null, 'limit_type' => string, 'usage' => array]
     */
    public function canMakeTtsRequest(
        string $model, 
        string $provider = 'groq',
        string $userRole = 'visitor',
        ?int $userId = null,
        ?string $sessionId = null
    ): array {
        $userRole = strtolower($userRole);
        
        // =================================================================
        // Load TTS limits from environment (with defaults)
        // Check both getenv() and $_ENV for compatibility
        // =================================================================
        $adminThresholdPercent = ((int)(getenv('TTS_LIMIT_ADMIN_PERCENT') ?: ($_ENV['TTS_LIMIT_ADMIN_PERCENT'] ?? 50))) / 100;
        $userHourLimit = (int)(getenv('TTS_LIMIT_USER_HOURLY') ?: ($_ENV['TTS_LIMIT_USER_HOURLY'] ?? 30));
        $visitorSessionLimit = (int)(getenv('TTS_LIMIT_VISITOR_SESSION') ?: ($_ENV['TTS_LIMIT_VISITOR_SESSION'] ?? 10));
        // Org-wide silent stop threshold (when hit, TTS silently stops - no modal)
        $silentStopPercent = ((int)(getenv('TTS_SILENT_STOP_PERCENT') ?: ($_ENV['TTS_SILENT_STOP_PERCENT'] ?? 90))) / 100;
        
        // Get organization-wide limits for the TTS model
        $limits = $this->getRateLimits($provider, $model);
        
        // =================================================================
        // Check organization-wide limits first (applies to everyone)
        // BEHAVIOR: When org hits the silent threshold, TTS stops silently
        // (no error, no modal) to preserve quota for the system.
        // =================================================================
        $minuteUsage = $this->getOrgUsage($provider, $model, 'minute');
        $dayUsage = $this->getOrgUsage($provider, $model, 'day');
        
        // For admins, use configured percentage of org quota (50% default)
        // For users/visitors, use silent stop threshold (90% default - stops at 10% remaining)
        $thresholdPercent = ($userRole === 'admin') ? $adminThresholdPercent : $silentStopPercent;
        
        $rpmThreshold = (int)floor(($limits['rpm'] ?? 250) * $thresholdPercent);
        $rpdThreshold = (int)floor(($limits['rpd'] ?? 100000) * $thresholdPercent);
        
        // Check org-wide minute limit - SILENT stop (no modal, just disable TTS)
        if ($minuteUsage['requests'] >= $rpmThreshold) {
            return [
                'allowed' => false,
                'reason' => 'org_rpm_threshold',
                'limit_type' => 'organization',
                'silent' => true, // Key flag: don't show error/modal, just stop TTS
                'user_role' => $userRole,
                'usage' => [
                    'current' => $minuteUsage['requests'],
                    'limit' => $limits['rpm'] ?? 250,
                    'threshold' => $rpmThreshold,
                ],
            ];
        }
        
        // Check org-wide daily limit - SILENT stop (no modal, just disable TTS)
        if ($dayUsage['requests'] >= $rpdThreshold) {
            return [
                'allowed' => false,
                'reason' => 'org_rpd_threshold',
                'limit_type' => 'organization',
                'silent' => true, // Key flag: don't show error/modal, just stop TTS
                'user_role' => $userRole,
                'usage' => [
                    'current' => $dayUsage['requests'],
                    'limit' => $limits['rpd'] ?? 100000,
                    'threshold' => $rpdThreshold,
                ],
            ];
        }
        
        // =================================================================
        // Role-specific limits (all roles get modal when personal limit hit)
        // =================================================================
        
        if ($userRole === 'admin' && $userId !== null) {
            // Admins: higher hourly limit (100/hour by default, configurable)
            $adminHourLimit = (int)(getenv('TTS_LIMIT_ADMIN_HOURLY') ?: ($_ENV['TTS_LIMIT_ADMIN_HOURLY'] ?? 100));
            $hourAgo = date('Y-m-d H:i:s', time() - 3600);
            
            try {
                $adminHourCount = $this->db->count('api_requests', [
                    'user_id' => $userId,
                    'request_type' => 'tts',
                    'created_at[>=]' => $hourAgo,
                ]);
            } catch (\Throwable $e) {
                $adminHourCount = 0;
            }
            
            if ($adminHourCount >= $adminHourLimit) {
                return [
                    'allowed' => false,
                    'reason' => 'admin_hourly_limit',
                    'limit_type' => 'admin',
                    'silent' => false, // Show modal for admin too
                    'user_role' => $userRole,
                    'usage' => [
                        'current' => $adminHourCount,
                        'limit' => $adminHourLimit,
                        'window' => 'hour',
                    ],
                ];
            }
            
            return [
                'allowed' => true,
                'reason' => null,
                'limit_type' => 'admin',
                'user_role' => $userRole,
                'usage' => [
                    'current' => $adminHourCount,
                    'limit' => $adminHourLimit,
                    'remaining' => $adminHourLimit - $adminHourCount,
                ],
            ];
        }
        
        if ($userRole === 'user' && $userId !== null) {
            // Users: configurable requests per hour
            $hourAgo = date('Y-m-d H:i:s', time() - 3600);
            
            try {
                $userHourCount = $this->db->count('api_requests', [
                    'user_id' => $userId,
                    'request_type' => 'tts',
                    'created_at[>=]' => $hourAgo,
                ]);
            } catch (\Throwable $e) {
                $userHourCount = 0;
            }
            
            if ($userHourCount >= $userHourLimit) {
                return [
                    'allowed' => false,
                    'reason' => 'user_hourly_limit',
                    'limit_type' => 'user',
                    'user_role' => $userRole,
                    'usage' => [
                        'current' => $userHourCount,
                        'limit' => $userHourLimit,
                        'window' => 'hour',
                    ],
                ];
            }
            
            return [
                'allowed' => true,
                'reason' => null,
                'limit_type' => 'user',
                'user_role' => $userRole,
                'usage' => [
                    'current' => $userHourCount,
                    'limit' => $userHourLimit,
                    'remaining' => $userHourLimit - $userHourCount,
                ],
            ];
        }
        
        // Visitors: configurable requests per session
        if (!empty($sessionId)) {
            try {
                $sessionCount = $this->db->count('api_requests', [
                    'ip_address' => $sessionId, // We'll store session_id in ip_address for visitors
                    'request_type' => 'tts',
                    'user_id' => null, // Only count visitor requests
                ]);
            } catch (\Throwable $e) {
                $sessionCount = 0;
            }
            
            if ($sessionCount >= $visitorSessionLimit) {
                return [
                    'allowed' => false,
                    'reason' => 'visitor_session_limit',
                    'limit_type' => 'visitor',
                    'user_role' => $userRole,
                    'usage' => [
                        'current' => $sessionCount,
                        'limit' => $visitorSessionLimit,
                        'window' => 'session',
                    ],
                ];
            }
            
            return [
                'allowed' => true,
                'reason' => null,
                'limit_type' => 'visitor',
                'user_role' => $userRole,
                'usage' => [
                    'current' => $sessionCount,
                    'limit' => $visitorSessionLimit,
                    'remaining' => $visitorSessionLimit - $sessionCount,
                ],
            ];
        }
        
        // Fallback: allow if we can't track
        return [
            'allowed' => true,
            'reason' => null,
            'limit_type' => 'unknown',
            'user_role' => $userRole,
            'usage' => [],
        ];
    }

    /**
     * Log a TTS request for rate limiting purposes
     * 
     * @param string $model The TTS model used
     * @param string $provider The provider (default 'groq')
     * @param int|null $userId The user ID (null for guests)
     * @param string $userRole The user role
     * @param bool $success Whether the request was successful
     * @param string|null $sessionId Session ID for visitor tracking
     */
    public function logTtsRequest(
        string $model, 
        string $provider = 'groq', 
        ?int $userId = null, 
        string $userRole = 'visitor', 
        bool $success = true,
        ?string $sessionId = null
    ): bool {
        // For visitors, store session_id in ip_address field for tracking
        $ipAddress = $userId ? ($_SERVER['REMOTE_ADDR'] ?? null) : ($sessionId ?? ($_SERVER['REMOTE_ADDR'] ?? null));
        
        return $this->logRequest([
            'user_id' => $userId,
            'user_role' => $userRole,
            'provider' => $provider,
            'model' => $model,
            'tokens_input' => 0,
            'tokens_output' => 0,
            'request_type' => 'tts',
            'response_status' => $success ? 'success' : 'error',
            'fallback_used' => 0,
            'latency_ms' => 0,
            'ip_address' => $ipAddress,
        ]);
    }
}
