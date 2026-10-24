<?php

declare(strict_types=1);

namespace Ginto\Sites;

use Ginto\Support\AiSpend;
use Ginto\Support\ProviderRouter;

/**
 * The one door Sites uses to reach AI: Groq (free) first, Cerebras (paid) when
 * Groq refuses for capacity (owner, 2026-10-24: "consolidate them ... make
 * sure that we earn more than we pay many times").
 *
 * Every call names its FEATURE, never a model: the model family and the token
 * caps come from Config::LIMITS (sized to fit Groq's free minute), and each
 * provider is asked for that family under its own name (ProviderRouter).
 *
 * Paid capacity is used only:
 *   - by a member on a plan (a free preview never spends money), and
 *   - while AiSpend has room this month; near the cap the nice-to-haves stop
 *     first (80%) and everything but customer chat after (95%).
 * Usage goes to sites_ai_usage (the member's credits and ceiling) and to
 * ai_spend (the platform's money), each call priced by the provider that
 * answered: $0 on a free tier.
 */
final class Ai
{
    private const URLS = [
        'groq'     => 'https://api.groq.com/openai/v1/chat/completions',
        'cerebras' => 'https://api.cerebras.ai/v1/chat/completions',
    ];

    public function __construct(private Meter $meter)
    {
    }

    public static function configured(): bool
    {
        return ProviderRouter::order() !== [];
    }

    /**
     * @param list<array{role:string,content:mixed}> $messages
     * @param array{user_id?:?int, preview_id?:?int, plan?:?string, json?:bool, images?:int, photos?:int} $ctx
     * @return array{content:string, usage:array<string,int>, model:string, provider:string}
     */
    public function chat(string $feature, array $messages, array $ctx = []): array
    {
        [$role, $maxIn, $maxOut] = Config::LIMITS[$feature] ?? throw new \InvalidArgumentException('Unknown AI feature ' . $feature);
        $family = ProviderRouter::family(Config::model($role));

        // Input size is checked before anything is sent: a friendly refusal, never a silent cut.
        $estimate = self::estimateTokens($messages) + (int) ($ctx['images'] ?? 0) * Config::IMAGE_TOKENS;
        if ($estimate > $maxIn) {
            throw new LimitReached('too_long', 'That is longer than this feature can take at once. Please shorten it and try again.');
        }
        $this->meter->allow($feature, $ctx, $estimate);

        $plan = $family !== null ? ProviderRouter::order() : ['groq'];
        $why  = 'SilverQueen AI is busy right now. Please try again shortly.';

        foreach ($plan as $p) {
            $model = $family !== null ? ProviderRouter::modelFor($p, $family) : Config::model($role);
            if ($model === null || ProviderRouter::key($p) === '') {
                continue;
            }
            if (AiSpend::isPaid($p) && !$this->paidAllowed($feature, $ctx, $p, $model, $estimate, $maxOut)) {
                continue;
            }

            [$status, $data] = $this->post($p, $model, $messages, $maxOut, !empty($ctx['json']));
            $usage = [
                'in'     => (int) ($data['usage']['prompt_tokens'] ?? 0),
                'out'    => (int) ($data['usage']['completion_tokens'] ?? 0),
                'cached' => (int) ($data['usage']['prompt_tokens_details']['cached_tokens'] ?? 0),
            ];
            $ok = $status === 200 && isset($data['choices'][0]['message']);
            $this->meter->record($feature, $p, $model, $usage, $ctx, $ok);
            AiSpend::record($p, $model, $usage['in'], $usage['out'], 'sites:' . $feature, isset($ctx['user_id']) ? (int) $ctx['user_id'] : null);

            if ($ok) {
                return ['content' => (string) ($data['choices'][0]['message']['content'] ?? ''), 'usage' => $usage, 'model' => $model, 'provider' => $p];
            }
            $err = (string) ($data['error']['message'] ?? ('HTTP ' . $status));
            if (!ProviderRouter::isOverflow($err, $status)) {
                throw new \RuntimeException($p . ' refused (' . $status . '): ' . mb_substr($err, 0, 200));
            }
            // Capacity: ask the next provider.
        }

        throw new LimitReached('busy', $why);
    }

    /** A paid call: only for a member on a plan, only while the month's cap has room, the nice-to-haves first to go. */
    private function paidAllowed(string $feature, array $ctx, string $p, string $model, int $estimate, int $maxOut): bool
    {
        if (empty($ctx['plan'])) {
            return false; // a free preview never spends money
        }
        $cap   = AiSpend::capUsd();
        $spent = AiSpend::paidThisMonthUsd();
        $ratio = $spent / max(0.01, $cap);
        if ($ratio >= 0.95 && $feature !== 'chat_reply') {
            return false;
        }
        if ($ratio >= 0.80 && in_array($feature, Config::NON_ESSENTIAL, true)) {
            return false;
        }

        return AiSpend::paidRoom(AiSpend::costUsd($p, $model, $estimate, $maxOut));
    }

    /** @return array{0:int,1:array<string,mixed>} */
    private function post(string $provider, string $model, array $messages, int $maxOut, bool $json): array
    {
        $body = ['model' => $model, 'messages' => $messages, 'max_completion_tokens' => $maxOut, 'temperature' => 0.4];
        if ($json) {
            $body['response_format'] = ['type' => 'json_object'];
        }
        if (str_contains($model, 'gpt-oss')) {
            $body['reasoning_effort'] = 'low';
        }

        [$status, $data] = $this->send($provider, $body);
        if ($status === 400 && isset($body['reasoning_effort'])) {
            unset($body['reasoning_effort']);
            [$status, $data] = $this->send($provider, $body);
        }

        return [$status, $data];
    }

    /** @return array{0:int,1:array<string,mixed>} */
    private function send(string $provider, array $body): array
    {
        $ch = curl_init(self::URLS[$provider]);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . ProviderRouter::key($provider), 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
        $raw    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = is_string($raw) ? json_decode($raw, true) : null;

        return [$status, is_array($data) ? $data : []];
    }

    /** About four characters a token; images are counted by the caller. */
    public static function estimateTokens(array $messages): int
    {
        $chars = 0;
        foreach ($messages as $m) {
            if (is_string($m['content'] ?? null)) {
                $chars += mb_strlen($m['content']);
            } elseif (is_array($m['content'] ?? null)) {
                foreach ($m['content'] as $part) {
                    if (($part['type'] ?? '') === 'text') {
                        $chars += mb_strlen((string) $part['text']);
                    }
                }
            }
        }

        return (int) ceil($chars / 4);
    }
}
