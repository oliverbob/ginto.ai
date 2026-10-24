<?php

declare(strict_types=1);

namespace Ginto\Sites;

use Ginto\Support\Env;

/**
 * The one door Sites uses to reach Groq (OpenAI-compatible chat completions).
 *
 * Every call names its FEATURE, never a model: the model, the output cap and
 * the input cap come from Config::LIMITS, and the returned usage is priced and
 * written to sites_ai_usage by Meter. System prompts are passed in as constants
 * by the callers so they stay byte-identical (Groq caches identical prefixes at
 * half price).
 */
final class Groq
{
    private const URL = 'https://api.groq.com/openai/v1/chat/completions';

    public function __construct(private Meter $meter)
    {
    }

    public static function configured(): bool
    {
        return (string) Env::get('GROQ_API_KEY', '') !== '';
    }

    /**
     * @param list<array{role:string,content:mixed}> $messages
     * @param array{user_id?:?int, preview_id?:?int, plan?:?string, json?:bool, images?:int, model?:string} $ctx
     * @return array{content:string, usage:array<string,int>, model:string}
     */
    public function chat(string $feature, array $messages, array $ctx = []): array
    {
        [$role, $maxIn, $maxOut] = Config::LIMITS[$feature] ?? throw new \InvalidArgumentException('Unknown AI feature ' . $feature);
        $model = $ctx['model'] ?? Config::model($role);

        // Input size is checked before anything is sent: a friendly refusal, never a silent cut.
        $estimate = self::estimateTokens($messages) + (int) ($ctx['images'] ?? 0) * Config::IMAGE_TOKENS;
        if ($estimate > $maxIn) {
            throw new LimitReached('too_long', 'That is longer than this feature can take at once. Please shorten it and try again.');
        }

        $this->meter->allow($feature, $ctx, $estimate);

        $key = (string) Env::get('GROQ_API_KEY', '');
        if ($key === '') {
            throw new \RuntimeException('Groq is not configured (Admin → API Keys).');
        }

        $body = [
            'model'                 => $model,
            'messages'              => $messages,
            'max_completion_tokens' => $maxOut,
            'temperature'           => 0.4,
        ];
        if (!empty($ctx['json'])) {
            $body['response_format'] = ['type' => 'json_object'];
        }
        if (str_starts_with($model, 'openai/gpt-oss')) {
            $body['reasoning_effort'] = 'low';
            $body['include_reasoning'] = false;
        }

        [$status, $data] = $this->post($key, $body);
        if ($status === 400 && isset($body['reasoning_effort'])) {
            unset($body['reasoning_effort'], $body['include_reasoning']);
            [$status, $data] = $this->post($key, $body);
        }

        $usage = [
            'in'     => (int) ($data['usage']['prompt_tokens'] ?? 0),
            'out'    => (int) ($data['usage']['completion_tokens'] ?? 0),
            'cached' => (int) ($data['usage']['prompt_tokens_details']['cached_tokens'] ?? 0),
        ];
        $ok = $status === 200 && isset($data['choices'][0]['message']);
        $this->meter->record($feature, $model, $usage, $ctx, $ok);

        if (!$ok) {
            $why = (string) ($data['error']['message'] ?? ('HTTP ' . $status));
            throw new \RuntimeException('Groq refused (' . $status . '): ' . mb_substr($why, 0, 200));
        }

        return ['content' => (string) ($data['choices'][0]['message']['content'] ?? ''), 'usage' => $usage, 'model' => $model];
    }

    /** @return array{0:int,1:array<string,mixed>} */
    private function post(string $key, array $body): array
    {
        $ch = curl_init(self::URL);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $key, 'Content-Type: application/json'],
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
