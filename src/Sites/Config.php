<?php

declare(strict_types=1);

namespace Ginto\Sites;

use Ginto\Support\Env;

/**
 * Everything SilverQueen Premium decides by number, in one place.
 *
 * No feature names a model, a price or a limit: it asks here. Models and the
 * money ceilings can be moved from .env (SITES_*), everything else is the
 * owner's plan sheet of 2026-10-24 written down once.
 */
final class Config
{
    public const USD_PHP = 58.0; // for the ₱ ceilings; SITES_USD_PHP overrides

    /** role => model id (env-overridable) */
    public static function models(): array
    {
        return [
            'vision'     => self::env('SITES_VISION_MODEL', 'qwen/qwen3.8-27b'),
            'builder'    => self::env('SITES_BUILDER_MODEL', 'openai/gpt-oss-120b'),
            'fast'       => self::env('SITES_FAST_MODEL', 'openai/gpt-oss-20b'),
            'moderation' => self::env('SITES_MODERATION_MODEL', 'openai/gpt-oss-safeguard-20b'),
            'guard'      => self::env('SITES_GUARD_MODEL', 'meta-llama/llama-prompt-guard-2-86m'),
            'stt'        => self::env('SITES_STT_MODEL', 'whisper-large-v3-turbo'),
        ];
    }

    public static function model(string $role): string
    {
        return self::models()[$role] ?? throw new \InvalidArgumentException('No model role ' . $role);
    }

    /** USD per 1M tokens [in, out]. Unknown models are priced like the builder, never as free. */
    public static function price(string $model): array
    {
        $prices = [
            'qwen/qwen3.8-27b'                    => [0.80, 4.00],
            'openai/gpt-oss-120b'                 => [0.15, 0.60],
            'openai/gpt-oss-20b'                  => [0.075, 0.30],
            'openai/gpt-oss-safeguard-20b'        => [0.075, 0.30],
            'meta-llama/llama-prompt-guard-2-86m' => [0.04, 0.04],
        ];

        // SITES_PRICES={"model":[in,out],...} moves a price without a deploy.
        $over = json_decode((string) self::env('SITES_PRICES', '{}'), true);
        if (is_array($over) && isset($over[$model][0], $over[$model][1])) {
            return [(float) $over[$model][0], (float) $over[$model][1]];
        }

        return $prices[$model] ?? [0.80, 4.00];
    }

    /** Every image counts as this many input tokens. */
    public const IMAGE_TOKENS = 2048;

    public static function usdPhp(): float
    {
        return (float) self::env('SITES_USD_PHP', (string) self::USD_PHP);
    }

    // ---- plans (monthly only) ------------------------------------------------

    public const PLANS = [
        'silver' => ['name' => 'Silver', 'php' => 749, 'storage_gb' => 2, 'listings' => 50, 'render_min' => 60, 'live_hours' => 10,
            'queue' => 'Standard', 'directory' => 'Listed', 'domain' => false, 'credits' => 300, 'ceiling_php' => 75,
            'chat' => false, 'live_ai' => false, 'insights' => false, 'beta' => false, 'regens' => 5, 'chat_day' => 0],
        'gold'   => ['name' => 'Gold', 'php' => 999, 'storage_gb' => 5, 'listings' => 200, 'render_min' => 180, 'live_hours' => 30,
            'queue' => 'Priority', 'directory' => 'Priority', 'domain' => true, 'credits' => 1000, 'ceiling_php' => 150,
            'chat' => true, 'live_ai' => true, 'insights' => false, 'beta' => false, 'regens' => 15, 'chat_day' => 100],
        'crown'  => ['name' => 'Crown', 'php' => 1499, 'storage_gb' => 10, 'listings' => null, 'render_min' => 500, 'live_hours' => 60,
            'queue' => 'Top priority', 'directory' => 'Featured', 'domain' => true, 'credits' => 3000, 'ceiling_php' => 300,
            'chat' => true, 'live_ai' => true, 'insights' => true, 'beta' => true, 'regens' => 40, 'chat_day' => 300],
    ];

    public static function plan(string $id): ?array
    {
        return self::PLANS[$id] ?? null;
    }

    /** Weighted AI credits per feature (what counts against 300 / 1,000 / 3,000). */
    public const CREDITS = [
        'caption' => 1, 'description' => 1, 'chat_reply' => 1, 'live_script' => 1,
        'vision' => 1, 'assistant_edit' => 2, 'insights' => 5, 'site_generation' => 10,
    ];

    /** feature => [model role, max input tokens, max output tokens] */
    public const LIMITS = [
        'vision'          => ['vision', 3 * 2048 + 800, 400],
        // Sized to Groq's free minute (8K tokens, prompt and answer together); bigger work overflows to Cerebras.
        'site_generation' => ['builder', 4500, 3500],
        'assistant_edit'  => ['builder', 4000, 2000],
        'description'     => ['fast', 1500, 300],
        'caption'         => ['fast', 1500, 300],
        'chat_reply'      => ['fast', 3000, 300],
        'live_script'     => ['fast', 2000, 800],
        'insights'        => ['builder', 16000, 2000],
    ];

    /** Lower number = served first when the shared AI queue is busy. */
    public const PRIORITY = [
        'chat_reply' => 1, 'assistant_edit' => 2, 'site_generation' => 3, 'vision' => 4,
        'description' => 5, 'caption' => 5, 'live_script' => 5, 'insights' => 6,
    ];

    /** Paused first when the monthly budget runs hot (80%); at 95% everything but chat. */
    public const NON_ESSENTIAL = ['caption', 'live_script', 'insights'];

    public const PER_MINUTE = 10;           // AI requests per user per minute
    public const BUYER_PER_MINUTE = 10;     // customer-chat messages per buyer IP per minute
    public const BUYER_SESSION_MAX = 30;    // messages per buyer session

    public const VISION_MAX_EDGE = 1024;
    public const VISION_MAX_IMAGES = 3;
    public const PREVIEW_HOURS = 72;

    /** Free previews come out of their own monthly budget, never a subscriber's quota. */
    public static function previewBudgetPhp(): float
    {
        return (float) self::env('SITES_PREVIEW_BUDGET_PHP', '1000');
    }

    public static function foundingCap(): int
    {
        return max(1, (int) self::env('SITES_FOUNDING_CAP', '20'));
    }

    /** Before the owner opens it, only admins may build a preview (and spend). */
    public static function isPublic(): bool
    {
        return in_array(strtolower((string) self::env('SITES_PUBLIC', 'false')), ['1', 'true', 'yes'], true);
    }

    // ---- names -----------------------------------------------------------------

    /**
     * Members (comchain identities) who may simulate a payment and preview the
     * feed's promotions with their own site: the owner and Eleanor Rojas (owner, 2026-10-24).
     */
    public const SIMULATORS = [10, 20];

    /**
     * Complimentary plans, never paid (owner, 2026-10-24: "just let eleanor and
     * myself have the free ... give her only until the 2nd best tier, but to me
     * give me the crown"). Comchain identity => plan.
     */
    public const COMPLIMENTARY = [10 => 'crown', 20 => 'gold'];

    public const NAME_RE = '/^[a-z0-9]([a-z0-9-]{1,30}[a-z0-9])$/';

    public const RESERVED = [
        'www', 'mail', 'api', 'admin', 'app', 'gk', 'pay', 'wallet', 'login', 'auth', 'support', 'help', 'status',
        'cdn', 'static', 'dev', 'staging', 'sites', 'silverqueen', 'ginto', 'goldking', 'official',
        // ours, in use or kept
        'sfu', 'webmail', 'smtp', 'imap', 'pop', 'ftp', 'ns1', 'ns2', 'pma', 'chat', 'comchain', 'mall', 'store',
        'shop', 'pages', 'site', 'superadmin', 'billing', 'secure', 'account', 'accounts', 'live', 'directory',
    ];

    /** Brand lookalikes: anything that reads as one of ours once digits and hyphens are folded. */
    public const LOOKALIKE_ROOTS = ['silverqueen', 'silverqueeen', 'silverquen', 'sqofficial', 'goldking', 'ginto', 'gintoai', 'datugyud'];

    private static function env(string $key, string $default): string
    {
        $v = Env::get($key, null);

        return $v === null || $v === '' ? $default : (string) $v;
    }
}
