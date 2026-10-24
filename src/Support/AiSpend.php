<?php

declare(strict_types=1);

namespace Ginto\Support;

use Ginto\Core\Database;

/**
 * What OUR AI keys cost, and the line paid AI may not cross.
 *
 * Owner, 2026-10-24: "Cerebras (paid tier), groq (free tier). You can now
 * consolidate them. And make sure that we earn more than we pay many times (on
 * these apis) because the apis themselves can become auto billed by both
 * companies."
 *
 * So, for every call made with one of our keys (a member's own key is theirs
 * and is never counted or capped here):
 *
 *   - Groq answers first, because it is free. Cerebras answers only when Groq
 *     refuses for a rate limit (ProviderRouter), and only while there is room.
 *   - Room is a monthly cap on PAID spend:
 *       cap = min(AI_PAID_HARD_MAX_USD, max(AI_PAID_FLOOR_USD, AI_PAID_REVENUE_SHARE x this month's Sites revenue))
 *     With the default share of 10%, AI can never cost more than a tenth of
 *     what Sites took in: we earn at least ten times what we pay. The floor is
 *     a few dollars so the owner can test before the first subscriber; the hard
 *     maximum holds even if revenue were misread.
 *   - Every call is written to ai_spend, free ones at $0, so the ledger shows
 *     how much free capacity is carrying the platform.
 *
 * A provider's tier comes from <PROVIDER>_TIER in .env ("free" or "paid"; set on
 * the superadmin API Keys page). Unset: Groq free, Cerebras paid.
 */
final class AiSpend
{
    /** USD per 1M tokens [in, out], by provider and model, as billed on a paid tier. */
    private const PRICES = [
        'cerebras' => ['gpt-oss-120b' => [0.35, 0.75], 'qwen-3.8-27b' => [0.99, 1.49]],
        'groq'     => [
            'openai/gpt-oss-120b' => [0.15, 0.60], 'openai/gpt-oss-20b' => [0.075, 0.30],
            'openai/gpt-oss-safeguard-20b' => [0.075, 0.30], 'qwen/qwen3.8-27b' => [0.80, 4.00],
            'meta-llama/llama-prompt-guard-2-86m' => [0.04, 0.04],
        ],
    ];
    private const ENV_KEYS = ['groq' => 'GROQ_API_KEY', 'cerebras' => 'CEREBRAS_API_KEY'];

    public static function tier(string $provider): string
    {
        $t = strtolower((string) (Env::get(strtoupper($provider) . '_TIER', '') ?? ''));
        if ($t === 'free' || $t === 'paid') {
            return $t;
        }

        return $provider === 'cerebras' ? 'paid' : 'free';
    }

    public static function isPaid(string $provider): bool
    {
        return self::tier($provider) === 'paid';
    }

    /** Is this call on one of our keys (and so ours to count and cap)? */
    public static function ours(string $provider, ?string $apiKey): bool
    {
        $env = (string) (Env::get(self::ENV_KEYS[$provider] ?? '', '') ?? '');

        return $env !== '' && $apiKey !== null && hash_equals($env, $apiKey);
    }

    public static function price(string $provider, string $model): array
    {
        return self::PRICES[$provider][$model] ?? [0.99, 1.49];
    }

    public static function costUsd(string $provider, string $model, int $in, int $out): float
    {
        if (!self::isPaid($provider)) {
            return 0.0;
        }
        [$pin, $pout] = self::price($provider, $model);

        return ($in * $pin + $out * $pout) / 1_000_000;
    }

    /** This month's paid cap in USD. */
    public static function capUsd(): float
    {
        $share = (float) (Env::get('AI_PAID_REVENUE_SHARE', '0.10') ?: '0.10');
        $floor = (float) (Env::get('AI_PAID_FLOOR_USD', '3') ?: '3');
        $max   = (float) (Env::get('AI_PAID_HARD_MAX_USD', '25') ?: '25');

        return round(min($max, max($floor, $share * self::revenueUsdThisMonth())), 2);
    }

    /** Sites subscriptions paid this month, in USD. */
    public static function revenueUsdThisMonth(): float
    {
        try {
            $q = Database::getInstance()->pdo->prepare(
                "SELECT COALESCE(SUM(price_php),0) FROM sites_subscriptions WHERE status IN ('active','lapsed','cancelled') AND period_start >= ?"
            );
            $q->execute([date('Y-m-01 00:00:00')]);
            $usdPhp = class_exists(\Ginto\Sites\Config::class) ? \Ginto\Sites\Config::usdPhp() : 58.0;

            return (float) $q->fetchColumn() / max(1.0, $usdPhp);
        } catch (\Throwable $e) {
            return 0.0;
        }
    }

    public static function paidThisMonthUsd(): float
    {
        try {
            $q = Database::getInstance()->pdo->prepare('SELECT COALESCE(SUM(cost_usd),0) FROM ai_spend WHERE paid = 1 AND created_at >= ?');
            $q->execute([date('Y-m-01 00:00:00')]);

            return (float) $q->fetchColumn();
        } catch (\Throwable $e) {
            // Without the ledger we cannot know: no room, rather than unknown spend.
            return INF;
        }
    }

    /** May one more paid call of about $estimateUsd go out? */
    public static function paidRoom(float $estimateUsd = 0.01): bool
    {
        $spent = self::paidThisMonthUsd();
        $cap   = self::capUsd();
        $ok    = $spent + $estimateUsd <= $cap;
        if ($spent >= 0.8 * $cap) {
            self::alert($spent >= $cap ? '100' : '80', $spent, $cap);
        }

        return $ok;
    }

    public static function record(string $provider, string $model, int $in, int $out, string $feature = 'chat', ?int $userId = null, bool $estimated = false): float
    {
        $usd = self::costUsd($provider, $model, $in, $out);
        try {
            Database::getInstance()->insert('ai_spend', [
                'provider' => $provider, 'model' => mb_substr($model, 0, 64), 'paid' => self::isPaid($provider) ? 1 : 0,
                'feature' => mb_substr($feature, 0, 32), 'user_id' => $userId, 'in_tokens' => max(0, $in), 'out_tokens' => max(0, $out),
                'estimated' => $estimated ? 1 : 0, 'cost_usd' => round($usd, 6),
            ]);
        } catch (\Throwable $e) {
            error_log('[AiSpend] could not record: ' . $e->getMessage());
        }

        return $usd;
    }

    /** For the superadmin page. */
    public static function summary(): array
    {
        $rows = [];
        try {
            $q = Database::getInstance()->pdo->prepare(
                'SELECT provider, paid, COUNT(*) calls, SUM(in_tokens) tin, SUM(out_tokens) tout, SUM(cost_usd) usd
                   FROM ai_spend WHERE created_at >= ? GROUP BY provider, paid ORDER BY provider'
            );
            $q->execute([date('Y-m-01 00:00:00')]);
            $rows = $q->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $rows = [];
        }

        return [
            'month'     => date('F Y'),
            'paid_usd'  => round(self::paidThisMonthUsd(), 4),
            'cap_usd'   => self::capUsd(),
            'revenue_usd' => round(self::revenueUsdThisMonth(), 2),
            'share'     => (float) (Env::get('AI_PAID_REVENUE_SHARE', '0.10') ?: '0.10'),
            'tiers'     => ['groq' => self::tier('groq'), 'cerebras' => self::tier('cerebras')],
            'by_provider' => array_map(static fn ($r) => [
                'provider' => $r['provider'], 'paid' => (bool) $r['paid'], 'calls' => (int) $r['calls'],
                'tokens' => (int) $r['tin'] + (int) $r['tout'], 'usd' => round((float) $r['usd'], 4),
            ], $rows),
        ];
    }

    private static function alert(string $level, float $spent, float $cap): void
    {
        $flag = STORAGE_PATH . '/logs/ai-paid-' . $level . '-' . date('Ym');
        if (is_file($flag)) {
            return;
        }
        @touch($flag);
        @file_put_contents(STORAGE_PATH . '/logs/ai-spend.log',
            date('c') . " PAID AI {$level}% of the month's cap: \$" . number_format($spent, 2) . ' of $' . number_format($cap, 2) . "\n", FILE_APPEND | LOCK_EX);
    }
}
