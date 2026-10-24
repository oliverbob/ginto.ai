<?php

declare(strict_types=1);

namespace Ginto\Sites\Vision;

use Ginto\Sites\Config;
use Ginto\Sites\Ai;
use Ginto\Sites\LimitReached;
use Ginto\Sites\Photos;

/**
 * Photos in, product facts out -- once per photo, ever.
 *
 * Groq's Qwen first (a preview model), then the alternate slot if one is set,
 * then no AI at all: the colours come from the photo itself and the seller
 * types the rest ("needs manual input"). Each provider gets one retry on an
 * answer that does not fit the schema. Nothing here can stop a site from being
 * built: the worst outcome is a photo with colours and empty words.
 */
final class Pipeline
{
    public function __construct(private $db, private Ai $groq)
    {
    }

    /**
     * @param list<string> $shas
     * @return array<string,array<string,mixed>> sha => the answer (status done|manual)
     */
    public function run(array $shas, array $ctx): array
    {
        $out = [];
        $todo = [];
        foreach (array_unique($shas) as $sha) {
            $row = $this->db->get('sites_photos', ['status', 'vision', 'palette'], ['sha256' => $sha]);
            if ($row && $row['status'] !== 'pending') {
                $out[$sha] = $this->shape($row);
            } else {
                $todo[] = $sha;
            }
        }

        $providers = [new OpenAiCompatible('groq', $this->groq), new OpenAiCompatible('alt')];
        foreach (array_chunk($todo, Config::VISION_MAX_IMAGES) as $batch) {
            $answers = [];
            foreach ($providers as $p) {
                if (!$p->ready()) {
                    continue;
                }
                $answers = $this->tryProvider($p, $batch, $ctx);
                if ($answers !== null) {
                    foreach ($batch as $i => $sha) {
                        $this->db->update('sites_photos', ['status' => 'done', 'provider' => $p->name(), 'vision' => json_encode($answers[$i]),
                            'palette' => json_encode(Photos::palette($sha))], ['sha256' => $sha]);
                    }
                    break;
                }
            }
            if ($answers === null || $answers === []) {
                foreach ($batch as $sha) {
                    $this->db->update('sites_photos', ['status' => 'manual', 'provider' => 'local', 'palette' => json_encode(Photos::palette($sha))], ['sha256' => $sha]);
                }
            }
            foreach ($batch as $sha) {
                $out[$sha] = $this->shape($this->db->get('sites_photos', ['status', 'vision', 'palette'], ['sha256' => $sha]));
            }
        }

        return $out;
    }

    /** @return list<array<string,mixed>>|null one clean answer per photo, or null to fall through */
    private function tryProvider(Provider $p, array $batch, array $ctx): ?array
    {
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $raw = $p->analyze($batch, $ctx);
            } catch (LimitReached $e) {
                return null;
            } catch (\Throwable $e) {
                $this->log($p->name() . ' failed: ' . $e->getMessage());

                return null;
            }
            $clean = array_map([Schema::class, 'clean'], array_slice($raw, 0, count($batch)));
            if (count($clean) === count($batch) && !in_array(null, $clean, true)) {
                return $clean;
            }
            $this->db->update('sites_photos', ['attempts[+]' => 1], ['sha256' => $batch]);
            $this->log($p->name() . ' gave an answer outside the schema (attempt ' . ($attempt + 1) . ')');
        }

        return null;
    }

    private function shape(?array $row): array
    {
        $vision = $row && $row['vision'] ? json_decode((string) $row['vision'], true) : null;
        $palette = $row && $row['palette'] ? json_decode((string) $row['palette'], true) : [];

        return [
            'status'  => $row['status'] ?? 'manual',
            'vision'  => is_array($vision) ? $vision : null,
            'palette' => is_array($palette) ? $palette : [],
        ];
    }

    private function log(string $line): void
    {
        @file_put_contents(STORAGE_PATH . '/logs/sites.log', date('c') . ' VISION ' . $line . "\n", FILE_APPEND | LOCK_EX);
    }
}
