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
    // USD per 1M tokens: [input, output]. Fallback = Opus (conservative overestimate).
    private const PRICING = [
        'claude-opus-4-8'  => [5.0, 25.0],
        'claude-opus-4-7'  => [5.0, 25.0],
        'claude-opus-4-6'  => [5.0, 25.0],
        'claude-sonnet-5'  => [3.0, 15.0],
        'claude-haiku-4-5' => [1.0, 5.0],
    ];

    private string $apiKey;
    private string $decisionModel;
    private string $scanModel;

    public function __construct()
    {
        $this->apiKey        = (string) (Env::get('ANTHROPIC_API_KEY', '') ?? '');
        $this->decisionModel = (string) (Env::get('ANTHROPIC_MODEL', 'claude-opus-4-8') ?? 'claude-opus-4-8') ?: 'claude-opus-4-8';
        $this->scanModel     = (string) (Env::get('ANTHROPIC_SCAN_MODEL', 'claude-haiku-4-5') ?? 'claude-haiku-4-5') ?: 'claude-haiku-4-5';
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /** Model for a tier: 'scan' (cheap, high-frequency) or 'decision' (higher quality). */
    public function model(string $tier = 'decision'): string
    {
        return $tier === 'scan' ? $this->scanModel : $this->decisionModel;
    }

    /** Estimated USD cost of a call from token usage. */
    public static function costUsd(string $model, int $inputTokens, int $outputTokens): float
    {
        [$in, $out] = self::PRICING[$model] ?? self::PRICING['claude-opus-4-8'];
        return ($inputTokens / 1_000_000) * $in + ($outputTokens / 1_000_000) * $out;
    }

    /**
     * Reflect on the current market + capital state.
     * @return array{ok:bool, text?:string, decision?:?string, error?:string}
     */
    public function reflect(array $context, string $tier = 'decision'): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'error' => 'No Anthropic API key set. Add it on the API Settings page.'];
        }
        $model = $this->model($tier);

        $env = $context['env'] ?? 'testnet';
        $system = "You are GTB, an autonomous Binance Spot trading bot thinking out loud before you act. "
            . "You are trading on {$env}. Your edge is catching the fastest short-term momentum movers and exiting "
            . "quickly with a tight stop-loss. Hard rules you must respect and never rationalize away: only risk the "
            . "configured capital (perTradeSize); at most one open position per unlocked slot; every entry has a stop-loss. "
            . "Reason like a disciplined risk-first trader — momentum can reverse and chasing a coin that already pumped is "
            . "how you buy the top. Be concise: 3-5 sentences of genuine reasoning. "
            . "Finish with exactly one line in this format: 'DECISION: BUY <SYMBOL> | HOLD | SKIP — <the single biggest risk you're watching>'.";

        $user = "Snapshot (JSON):\n" . json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            . "\n\nGiven the capital rules and current movers, is there a momentum trade worth taking right now? Reflect, then decide.";

        $body = [
            'model'      => $model,
            'max_tokens' => 600,
            'system'     => $system,
            'messages'   => [['role' => 'user', 'content' => $user]],
        ];

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 45,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01',
                'content-type: application/json',
            ],
            CURLOPT_POSTFIELDS     => json_encode($body),
        ]);
        $resp  = curl_exec($ch);
        $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);
        $err   = curl_error($ch);
        curl_close($ch);

        if ($resp === false || $errno) {
            return ['ok' => false, 'error' => 'Network error contacting Claude: ' . $err];
        }
        $data = json_decode($resp, true);
        if ($code < 200 || $code >= 300) {
            $msg = $data['error']['message'] ?? ('HTTP ' . $code);
            return ['ok' => false, 'error' => $msg];
        }
        if (($data['stop_reason'] ?? '') === 'refusal') {
            return ['ok' => false, 'error' => 'Claude declined to respond to this prompt.'];
        }

        $text = '';
        foreach (($data['content'] ?? []) as $b) {
            if (($b['type'] ?? '') === 'text') {
                $text .= $b['text'];
            }
        }
        $text = trim($text);

        $decision = null;
        if (preg_match('/DECISION:\s*(BUY\s+[A-Z0-9]+|HOLD|SKIP)/i', $text, $m)) {
            $decision = strtoupper(trim($m[1]));
        }

        $usageIn  = (int) ($data['usage']['input_tokens'] ?? 0);
        $usageOut = (int) ($data['usage']['output_tokens'] ?? 0);
        $cost     = self::costUsd($model, $usageIn, $usageOut);

        return [
            'ok'       => true,
            'text'     => $text,
            'decision' => $decision,
            'model'    => $model,
            'usage'    => ['input_tokens' => $usageIn, 'output_tokens' => $usageOut, 'cost_usd' => round($cost, 6)],
        ];
    }
}
