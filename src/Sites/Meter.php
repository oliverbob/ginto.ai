<?php

declare(strict_types=1);

namespace Ginto\Sites;

/**
 * Who may spend AI, and what they spent.
 *
 * Read before every AI call (allow) and written after it (record), from the
 * one table sites_ai_usage. Order of the checks is the order of the owner's
 * sheet: the global budget breaker first (it protects everyone), then the
 * shared rate (customer chat keeps its 30%), then the free-preview budget or
 * the member's own credits, daily share, per-minute burst and money ceiling.
 * A refusal is a LimitReached with words for the member; nothing here ever
 * touches a published site.
 */
final class Meter
{
    private const ORG_TPM = 250000;

    public function __construct(private $db)
    {
    }

    public function allow(string $feature, array $ctx, int $estimate): void
    {
        $model = Config::model(Config::LIMITS[$feature][0]);

        // 2. The shared per-model rate. Customer chat may use all of it; everything else stops at 70% on
        //    the fast model (its 30% is chat's), and the low-priority work steps aside at 80% anywhere.
        $tpm = $this->sum('in_tokens + out_tokens', 'model = ? AND created_at >= ?', [$model, date('Y-m-d H:i:s', time() - 60)]) + $estimate;
        if ($feature !== 'chat_reply') {
            if ($model === Config::model('fast') && $tpm > 0.70 * self::ORG_TPM) {
                throw new LimitReached('busy', 'SilverQueen AI is busy this minute. Please try again shortly.');
            }
            if ((Config::PRIORITY[$feature] ?? 9) >= 4 && $tpm > 0.80 * self::ORG_TPM) {
                throw new LimitReached('busy', 'SilverQueen AI is busy this minute. Please try again shortly.');
            }
        }

        $userId = (int) ($ctx['user_id'] ?? 0);
        $plan   = isset($ctx['plan']) ? Config::plan((string) $ctx['plan']) : null;

        // 3. Free previews spend their own monthly budget, never a subscriber's quota.
        if ($plan === null) {
            $previewPhp = $this->sum('cost_usd', 'preview_id IS NOT NULL AND created_at >= ?', [$this->monthStart()]) * Config::usdPhp();
            if ($previewPhp >= Config::previewBudgetPhp()) {
                throw new LimitReached('preview_budget', 'Preview queue full, try again tomorrow.');
            }

            return;
        }

        // 4. A member on a plan.
        if ($this->count('user_id = ? AND created_at >= ?', [$userId, date('Y-m-d H:i:s', time() - 60)]) >= Config::PER_MINUTE) {
            throw new LimitReached('burst', 'That is a lot at once. Please wait a minute and try again.');
        }
        $since   = $this->periodStart($userId);
        $credits = $this->sum('credits', 'user_id = ? AND created_at >= ?', [$userId, $since]);
        $cost    = (int) (Config::CREDITS[$feature] ?? 1) * max(1, (int) ($ctx['photos'] ?? 1));
        if ($credits + $cost > $plan['credits']) {
            throw new LimitReached('credits', 'You have used this month\'s AI credits. Upgrade your plan for more; your site keeps working.');
        }
        $today = $this->sum('credits', 'user_id = ? AND created_at >= ?', [$userId, date('Y-m-d 00:00:00')]);
        if ($today + $cost > max(5, intdiv($plan['credits'], 20))) {
            throw new LimitReached('daily', 'You have reached today\'s share of AI credits. They refill tomorrow.');
        }
        $php = $this->sum('cost_usd', 'user_id = ? AND created_at >= ?', [$userId, $since]) * Config::usdPhp();
        if ($php >= $plan['ceiling_php']) {
            throw new LimitReached('ceiling', 'You have reached this month\'s AI limit. Your site keeps working; AI returns on your billing date.');
        }
    }

    public function record(string $feature, string $provider, string $model, array $usage, array $ctx, bool $ok): float
    {
        // What the provider that answered charges: nothing on a free tier.
        $usd = \Ginto\Support\AiSpend::costUsd($provider, $model, max(0, $usage['in'] - intdiv($usage['cached'], 2)), $usage['out']);

        $this->db->insert('sites_ai_usage', [
            'user_id'       => isset($ctx['user_id']) ? (int) $ctx['user_id'] : null,
            'preview_id'    => isset($ctx['preview_id']) ? (int) $ctx['preview_id'] : null,
            'feature'       => $feature,
            'provider'      => $provider,
            'model'         => $model,
            'in_tokens'     => $usage['in'],
            'out_tokens'    => $usage['out'],
            'cached_tokens' => $usage['cached'],
            'cost_usd'      => round($usd, 6),
            'credits'       => $ok && isset($ctx['plan']) ? (int) (Config::CREDITS[$feature] ?? 1) * max(1, (int) ($ctx['photos'] ?? 1)) : 0,
            'ok'            => $ok ? 1 : 0,
        ]);

        return $usd;
    }

    /** For the member's dashboard: credits only, never tokens or pesos. */
    public function status(int $userId, string $planId): array
    {
        $plan = Config::plan($planId) ?? Config::PLANS['silver'];
        $used = (int) $this->sum('credits', 'user_id = ? AND created_at >= ?', [$userId, $this->periodStart($userId)]);

        return ['used' => $used, 'quota' => $plan['credits'], 'warn' => $used >= 0.8 * $plan['credits'], 'blocked' => $used >= $plan['credits']];
    }

    private function periodStart(int $userId): string
    {
        $sub = $this->db->get('sites_subscriptions', ['period_start'], ['user_id' => $userId, 'status' => 'active', 'ORDER' => ['id' => 'DESC']]);

        return !empty($sub['period_start']) ? (string) $sub['period_start'] : $this->monthStart();
    }

    private function monthStart(): string
    {
        return date('Y-m-01 00:00:00');
    }

    private function sum(string $expr, string $where, array $args): float
    {
        $q = $this->db->pdo->prepare('SELECT COALESCE(SUM(' . $expr . '), 0) FROM sites_ai_usage WHERE ' . $where);
        $q->execute($args);

        return (float) $q->fetchColumn();
    }

    private function count(string $where, array $args): int
    {
        $q = $this->db->pdo->prepare('SELECT COUNT(*) FROM sites_ai_usage WHERE ' . $where);
        $q->execute($args);

        return (int) $q->fetchColumn();
    }
}
