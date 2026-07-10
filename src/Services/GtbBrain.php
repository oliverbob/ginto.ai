<?php
namespace Ginto\Services;

use Ginto\Support\Env;

/**
 * The bot's reasoning layer. Sends a compact market + account snapshot to Claude
 * and gets back a short reflection with a DECISION line. This is ADVISORY only —
 * the deterministic strategy + hard risk rules make the actual trade calls; the
 * LLM never places or overrides orders.
 *
 * Uses a single raw HTTPS call to the Messages API (no SDK dependency, so it
 * can't destabilise the app's composer lock). Key comes from .env / the settings
 * page (ANTHROPIC_API_KEY — a paid developer key, not Claude Pro).
 */
class GtbBrain
{
    // USD per 1M tokens: [input, output]. Fallback per provider below.
    private const PRICING = [
        // Anthropic
        'claude-opus-4-8'  => [5.0, 25.0],
        'claude-opus-4-7'  => [5.0, 25.0],
        'claude-opus-4-6'  => [5.0, 25.0],
        'claude-sonnet-5'  => [3.0, 15.0],
        'claude-haiku-4-5' => [1.0, 5.0],
        // Groq (OpenAI-compatible) — far cheaper
        'llama-3.1-8b-instant'          => [0.05, 0.08],
        'llama-3.3-70b-versatile'       => [0.59, 0.79],
        'deepseek-r1-distill-llama-70b' => [0.75, 0.99],
        'openai/gpt-oss-120b'           => [0.15, 0.75],
        'openai/gpt-oss-20b'            => [0.10, 0.50],
        'qwen/qwen3-32b'                => [0.29, 0.59],
        'moonshotai/kimi-k2-instruct'   => [1.00, 3.00],
    ];

    private string $provider;
    private string $apiKey;
    private string $decisionModel;
    private string $scanModel;
    private string $operatorInstructions;

    public function __construct()
    {
        $this->provider = strtolower(trim((string) (Env::get('GTB_AI_PROVIDER', 'anthropic') ?? 'anthropic')));
        if (!in_array($this->provider, ['anthropic', 'groq'], true)) $this->provider = 'anthropic';

        if ($this->provider === 'groq') {
            $this->apiKey        = (string) (Env::get('GROQ_API_KEY', '') ?? '');
            $this->decisionModel = (string) (Env::get('GROQ_MODEL', 'llama-3.3-70b-versatile') ?? '') ?: 'llama-3.3-70b-versatile';
            $this->scanModel     = (string) (Env::get('GROQ_SCAN_MODEL', 'llama-3.1-8b-instant') ?? '') ?: 'llama-3.1-8b-instant';
        } else {
            $this->apiKey        = (string) (Env::get('ANTHROPIC_API_KEY', '') ?? '');
            $this->decisionModel = (string) (Env::get('ANTHROPIC_MODEL', 'claude-opus-4-8') ?? 'claude-opus-4-8') ?: 'claude-opus-4-8';
            $this->scanModel     = (string) (Env::get('ANTHROPIC_SCAN_MODEL', 'claude-haiku-4-5') ?? 'claude-haiku-4-5') ?: 'claude-haiku-4-5';
        }
        // Precedence: dashboard-injected prompt > /gtb-settings operator prompt >
        // built-in win-focused house default (so the AI is never left without a strategy).
        $active   = trim((string) (Env::get('GTB_ACTIVE_PROMPT', '') ?? ''));
        $fallback = trim((string) (Env::get('GTB_CUSTOM_INSTRUCTIONS', '') ?? ''));
        $this->operatorInstructions = $active !== '' ? $active
            : ($fallback !== '' ? $fallback : \Ginto\Services\Strategies\GtbPrompts::defaultText());
    }

    /**
     * Free-text steering from the account owner (settings page). Obeyed as long as it
     * doesn't loosen a hard risk rule — the risk rules always win. Empty string = none.
     */
    private function operatorBlock(): string
    {
        if ($this->operatorInstructions === '') return '';
        return "\n\nOPERATOR INSTRUCTIONS (from the account owner — follow these unless they would violate a hard risk "
            . "rule above; a hard rule always wins, and if they conflict say so in your reasoning): "
            . $this->operatorInstructions;
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    public function provider(): string { return $this->provider; }

    /** Model for a tier: 'scan' (cheap, high-frequency) or 'decision' (higher quality). */
    public function model(string $tier = 'decision'): string
    {
        return $tier === 'scan' ? $this->scanModel : $this->decisionModel;
    }

    /**
     * Decide whether to ENTER a specific candidate right now. Focused, cheaper prompt.
     * @return array{ok:bool, text?:string, decision?:?string(BUY|SKIP), model?:string, usage?:array, error?:string}
     */
    public function decide(array $candidate, array $context, string $tier = 'decision'): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'error' => 'No API key set for the active provider (' . $this->provider . ').'];
        }
        $model = $this->model($tier);
        $env = $context['env'] ?? 'paper';
        $sym = $candidate['symbol'] ?? '?';

        $system = "You are GTB, a disciplined Binance Spot momentum bot on {$env}. A deterministic pre-filter has "
            . "proposed ONE candidate to enter now, sized within the capital rules with a hard stop-loss already set. "
            . "Your job: a fast risk check. Enter only if the momentum looks real and continuation is plausible; skip if "
            . "it's already parabolic/overextended, thin, or clearly reversing (don't buy the top). If a 'memory' of "
            . "recent outcomes is provided, learn from it — avoid setups/templates that have been losing and lean into "
            . "what's worked. 2-3 sentences, then exactly one final line: 'DECISION: BUY {$sym}' or 'DECISION: SKIP — <reason>'.";
        if (!empty($context['posture'])) {
            $system .= "\n\nPROFILE POSTURE (the temperament you are trading with right now): " . $context['posture'];
        }
        $system .= $this->operatorBlock();
        $user = "Candidate + account (JSON):\n" . json_encode(['candidate' => $candidate] + $context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            . "\n\nEnter {$sym} now, or skip?";

        $res = $this->call($model, $system, $user, 350);
        if (empty($res['ok'])) return $res;

        $decision = null;
        if (preg_match('/DECISION:\s*(BUY|SKIP)/i', $res['text'], $m)) {
            $decision = strtoupper($m[1]);
        }
        $res['decision'] = $decision;
        return $res;
    }

    /** Estimated USD cost of a call from token usage (fallback depends on provider). */
    public static function costUsd(string $model, int $inputTokens, int $outputTokens, string $provider = 'anthropic'): float
    {
        $fallback = $provider === 'groq' ? [0.6, 0.8] : self::PRICING['claude-opus-4-8'];
        [$in, $out] = self::PRICING[$model] ?? $fallback;
        return ($inputTokens / 1_000_000) * $in + ($outputTokens / 1_000_000) * $out;
    }

    /**
     * Reflect on the current market + capital state.
     * @return array{ok:bool, text?:string, decision?:?string, error?:string}
     */
    public function reflect(array $context, string $tier = 'decision'): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'error' => 'No API key set for the active provider (' . $this->provider . '). Add it on the API Settings page.'];
        }
        $model = $this->model($tier);

        $env = $context['env'] ?? 'testnet';
        $system = "You are GTB, an autonomous Binance Spot trading bot thinking out loud before you act. "
            . "You are trading on {$env}. Your edge is catching the fastest short-term momentum movers and exiting "
            . "quickly with a tight stop-loss. Hard rules you must respect and never rationalize away: only risk the "
            . "configured capital (perTradeSize); at most one open position per unlocked slot; every entry has a stop-loss. "
            . "Reason like a disciplined risk-first trader — momentum can reverse and chasing a coin that already pumped is "
            . "how you buy the top. If a 'memory' of recent outcomes is provided, factor it into your reasoning. "
            . "Be concise: 3-5 sentences of genuine reasoning. "
            . "Finish with exactly one line in this format: 'DECISION: BUY <SYMBOL> | HOLD | SKIP — <the single biggest risk you're watching>'."
            . $this->operatorBlock();

        $user = "Snapshot (JSON):\n" . json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            . "\n\nGiven the capital rules and current movers, is there a momentum trade worth taking right now? Reflect, then decide.";

        $res = $this->call($model, $system, $user, 600);
        if (empty($res['ok'])) return $res;

        $decision = null;
        if (preg_match('/DECISION:\s*(BUY\s+[A-Z0-9]+|HOLD|SKIP)/i', $res['text'], $m)) {
            $decision = strtoupper(trim($m[1]));
        }
        $res['decision'] = $decision;
        return $res;
    }

    /** One LLM call, dispatched to the active provider. Normalizes to ok/text/model/usage. */
    private function call(string $model, string $system, string $user, int $maxTokens): array
    {
        $r = $this->provider === 'groq'
            ? $this->callGroq($model, $system, $user, $maxTokens)
            : $this->callAnthropic($model, $system, $user, $maxTokens);
        if (empty($r['ok'])) return $r;
        $in = (int) $r['in']; $out = (int) $r['out'];
        return [
            'ok'    => true,
            'text'  => $r['text'],
            'model' => $model,
            'usage' => ['input_tokens' => $in, 'output_tokens' => $out, 'cost_usd' => round(self::costUsd($model, $in, $out, $this->provider), 6)],
        ];
    }

    /** Anthropic Messages API. Returns ok/text/in/out or ok=false/error. */
    private function callAnthropic(string $model, string $system, string $user, int $maxTokens): array
    {
        $body = [
            'model'      => $model,
            'max_tokens' => $maxTokens,
            'system'     => $system,
            'messages'   => [['role' => 'user', 'content' => $user]],
        ];
        $resp = $this->httpJson('https://api.anthropic.com/v1/messages', [
            'x-api-key: ' . $this->apiKey,
            'anthropic-version: 2023-06-01',
            'content-type: application/json',
        ], $body);
        if (empty($resp['ok'])) return ['ok' => false, 'error' => $resp['error']];
        $data = $resp['data'];
        if (($data['stop_reason'] ?? '') === 'refusal') {
            return ['ok' => false, 'error' => 'The model declined to respond to this prompt.'];
        }
        $text = '';
        foreach (($data['content'] ?? []) as $b) {
            if (($b['type'] ?? '') === 'text') $text .= $b['text'];
        }
        return ['ok' => true, 'text' => trim($text),
                'in' => (int) ($data['usage']['input_tokens'] ?? 0), 'out' => (int) ($data['usage']['output_tokens'] ?? 0)];
    }

    /** Groq (OpenAI-compatible Chat Completions). Returns ok/text/in/out or ok=false/error. */
    private function callGroq(string $model, string $system, string $user, int $maxTokens): array
    {
        $body = [
            'model'       => $model,
            'max_tokens'  => $maxTokens,
            'temperature' => 0.4,
            'messages'    => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $user],
            ],
        ];
        $resp = $this->httpJson('https://api.groq.com/openai/v1/chat/completions', [
            'Authorization: Bearer ' . $this->apiKey,
            'content-type: application/json',
        ], $body);
        if (empty($resp['ok'])) return ['ok' => false, 'error' => $resp['error']];
        $data = $resp['data'];
        $text = (string) ($data['choices'][0]['message']['content'] ?? '');
        // Reasoning models (e.g. DeepSeek R1) may prefix <think>...</think>; strip it.
        $text = trim(preg_replace('/<think>.*?<\/think>/is', '', $text));
        return ['ok' => true, 'text' => $text,
                'in' => (int) ($data['usage']['prompt_tokens'] ?? 0), 'out' => (int) ($data['usage']['completion_tokens'] ?? 0)];
    }

    /** POST JSON, return ['ok'=>true,'data'=>...] or ['ok'=>false,'error'=>...]. */
    private function httpJson(string $url, array $headers, array $body): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 45,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POSTFIELDS     => json_encode($body),
        ]);
        $resp  = curl_exec($ch);
        $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);
        $err   = curl_error($ch);
        curl_close($ch);

        if ($resp === false || $errno) {
            return ['ok' => false, 'error' => 'Network error contacting the AI provider: ' . $err];
        }
        $data = json_decode($resp, true);
        if ($code < 200 || $code >= 300) {
            $msg = $data['error']['message'] ?? (is_string($data['error'] ?? null) ? $data['error'] : ('HTTP ' . $code));
            return ['ok' => false, 'error' => $msg];
        }
        return ['ok' => true, 'data' => is_array($data) ? $data : []];
    }
}
