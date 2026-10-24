<?php

declare(strict_types=1);

namespace Ginto\Support;

/**
 * Groq and Cerebras, consolidated (owner, 2026-10-24).
 *
 * The two serve the same open models under different names, so a request for
 * a model FAMILY can go to either: free providers first, paid ones only when
 * the free one refuses for capacity (a rate limit, a too-large request, an
 * overload). Which is free and which is paid is AiSpend::tier().
 */
final class ProviderRouter
{
    /** family => provider => model id */
    private const MODELS = [
        'gpt-oss-120b' => ['groq' => 'openai/gpt-oss-120b', 'cerebras' => 'gpt-oss-120b'],
        // Cerebras has no 20b: the 120b answers there (dearer per token, still cents).
        'gpt-oss-20b'  => ['groq' => 'openai/gpt-oss-20b', 'cerebras' => 'gpt-oss-120b'],
        'qwen3.8-27b'  => ['groq' => 'qwen/qwen3.8-27b', 'cerebras' => 'qwen-3.8-27b'],
    ];
    private const ENV_KEYS = ['groq' => 'GROQ_API_KEY', 'cerebras' => 'CEREBRAS_API_KEY'];

    /** The family a model id belongs to, or null when only its own provider serves it. */
    public static function family(string $model): ?string
    {
        $m = strtolower($model);
        $m = preg_replace('#^(openai|qwen|meta-llama)/#', '', $m);
        $m = str_replace('qwen-3', 'qwen3', (string) $m);

        return isset(self::MODELS[$m]) ? $m : null;
    }

    public static function modelFor(string $provider, string $family): ?string
    {
        return self::MODELS[$family][$provider] ?? null;
    }

    /** @return list<string> providers with a key, free ones first */
    public static function order(): array
    {
        $out = [];
        foreach (['free', 'paid'] as $tier) {
            foreach (array_keys(self::ENV_KEYS) as $p) {
                if (AiSpend::tier($p) === $tier && self::key($p) !== '') {
                    $out[] = $p;
                }
            }
        }

        return $out;
    }

    public static function key(string $provider): string
    {
        return (string) (Env::get(self::ENV_KEYS[$provider] ?? '', '') ?? '');
    }

    /** Did the provider refuse for capacity (so the next one should be asked), rather than for the request itself? */
    public static function isOverflow(string $error, int $status = 0): bool
    {
        return in_array($status, [413, 429, 498, 503], true)
            || preg_match('/rate.?limit|too many requests|tokens per (minute|day)|requests per (minute|day)|request too large|over ?capacity|overloaded|quota|\b(413|429|503)\b/i', $error) === 1;
    }
}
