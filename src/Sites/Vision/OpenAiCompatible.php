<?php

declare(strict_types=1);

namespace Ginto\Sites\Vision;

use Ginto\Sites\Config;
use Ginto\Sites\Ai;
use Ginto\Sites\Photos;
use Ginto\Support\Env;

/**
 * A vision provider on an OpenAI-style chat API.
 *
 * Two of them are wired: Groq (through Ginto\Sites\Ai, so it is metered like
 * everything else) and the alternate slot, which is empty until SITES_VISION_ALT_URL,
 * SITES_VISION_ALT_KEY and SITES_VISION_ALT_MODEL are set. The alternate is not
 * metered by Groq's prices; it records its tokens at the vision price as an
 * honest upper bound.
 */
final class OpenAiCompatible implements Provider
{
    public function __construct(private string $slot, private ?Ai $groq = null)
    {
    }

    public function name(): string
    {
        return $this->slot;
    }

    public function ready(): bool
    {
        return $this->slot === 'groq'
            ? \Ginto\Support\ProviderRouter::key('groq') !== ''
            : (string) Env::get('SITES_VISION_ALT_URL', '') !== '' && (string) Env::get('SITES_VISION_ALT_MODEL', '') !== '';
    }

    public function analyze(array $shas, array $ctx): array
    {
        $parts = [['type' => 'text', 'text' => 'Photos: ' . count($shas) . '.']];
        foreach ($shas as $sha) {
            $parts[] = ['type' => 'image_url', 'image_url' => ['url' => 'data:image/jpeg;base64,' . base64_encode((string) file_get_contents(Photos::path($sha)))]];
        }
        $messages = [['role' => 'system', 'content' => Schema::PROMPT], ['role' => 'user', 'content' => $parts]];

        if ($this->slot === 'groq') {
            $r = $this->groq->chat('vision', $messages, $ctx + ['json' => true, 'images' => count($shas), 'photos' => count($shas)]);
            $raw = $r['content'];
        } else {
            $raw = $this->alt($messages);
        }

        $data = json_decode($raw, true);
        $list = is_array($data) ? ($data['photos'] ?? (array_is_list($data) ? $data : [$data])) : [];

        return is_array($list) ? array_values($list) : [];
    }

    private function alt(array $messages): string
    {
        $ch = curl_init(rtrim((string) Env::get('SITES_VISION_ALT_URL', ''), '/') . '/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 90,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . Env::get('SITES_VISION_ALT_KEY', ''), 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => Env::get('SITES_VISION_ALT_MODEL', ''), 'messages' => $messages,
                'response_format' => ['type' => 'json_object'], 'max_tokens' => Config::LIMITS['vision'][2],
            ]),
        ]);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($status !== 200) {
            throw new \RuntimeException('Alternate vision provider answered ' . $status);
        }

        return (string) (json_decode((string) $raw, true)['choices'][0]['message']['content'] ?? '');
    }
}
