<?php

declare(strict_types=1);

namespace Ginto\Sites;

use Ginto\Sites\Vision\Pipeline;

/**
 * "Start it now": photos and a description in, a free preview out.
 *
 * No account and no payment. One generation per device and per IP address in
 * 24 hours, paid from the free-preview budget. The page answers at once; the
 * build runs in a background process (bin/sites-build.php) and the start page
 * polls. A preview lives 72 hours, is never indexed, and is what a name is
 * claimed from.
 */
final class Previews
{
    public const MIN_DESCRIPTION = 20;
    public const MAX_DESCRIPTION = 2000;

    public function __construct(private $db)
    {
    }

    /**
     * @param list<string> $shas
     * @return array{id:int, token:string}
     */
    public function create(string $description, array $shas, string $deviceId, string $ip, ?int $userId, bool $exempt): array
    {
        $description = trim($description);
        if (mb_strlen($description) < self::MIN_DESCRIPTION) {
            throw new \InvalidArgumentException('Tell us a little more about your shop (a sentence or two).');
        }
        if (mb_strlen($description) > self::MAX_DESCRIPTION) {
            throw new \InvalidArgumentException('Please keep the description under 2,000 characters.');
        }
        if ($shas === []) {
            throw new \InvalidArgumentException('Add at least one product photo.');
        }
        $device = hash('sha256', 'sites-device:' . $deviceId);
        $ipHash = hash('sha256', 'sites-ip:' . $ip);
        if (!$exempt) {
            $since = date('Y-m-d H:i:s', time() - 86400);
            $q = $this->db->pdo->prepare('SELECT COUNT(*) FROM sites_previews WHERE (device_hash = ? OR ip_hash = ?) AND created_at >= ?');
            $q->execute([$device, $ipHash, $since]);
            if ((int) $q->fetchColumn() > 0) {
                throw new LimitReached('preview_daily', 'You already made a free preview today. Come back tomorrow for another, or claim the one you made.');
            }
        }

        $token = bin2hex(random_bytes(16));
        $this->db->insert('sites_previews', [
            'token' => $token, 'user_id' => $userId, 'device_hash' => $device, 'ip_hash' => $ipHash,
            'description' => $description, 'photos' => json_encode(array_values($shas)), 'status' => 'queued',
            'expires_at' => date('Y-m-d H:i:s', time() + Config::PREVIEW_HOURS * 3600),
        ]);

        return ['id' => (int) $this->db->id(), 'token' => $token];
    }

    /** Start the build in its own process, so the request returns now. */
    public static function spawn(int $id): void
    {
        $php = PHP_BINARY && !str_contains(PHP_BINARY, 'fpm') ? PHP_BINARY : 'php';
        $cmd = escapeshellcmd($php) . ' ' . escapeshellarg(ROOT_PATH . '/bin/sites-build.php') . ' ' . (int) $id
            . ' >> ' . escapeshellarg(STORAGE_PATH . '/logs/sites.log') . ' 2>&1 &';
        exec($cmd);
    }

    /** The build itself (run by bin/sites-build.php). */
    public function build(int $id): void
    {
        $row = $this->db->get('sites_previews', '*', ['id' => $id]);
        if (!$row || !in_array($row['status'], ['queued', 'failed'], true)) {
            return;
        }
        $this->db->update('sites_previews', ['status' => 'building', 'error' => null], ['id' => $id]);
        $ctx = ['preview_id' => $id, 'user_id' => $row['user_id'] !== null ? (int) $row['user_id'] : null];

        try {
            $meter = new Meter($this->db);
            $groq  = new Groq($meter);
            $shas  = json_decode((string) $row['photos'], true) ?: [];
            $facts = (new Pipeline($this->db, $groq))->run($shas, $ctx);
            $photos = array_map(static fn ($sha) => $facts[$sha] ?? ['status' => 'manual', 'vision' => null, 'palette' => []], $shas);

            try {
                $plan = (new Builder($groq))->build((string) $row['description'], $photos, $ctx);
            } catch (LimitReached $e) {
                throw $e;
            } catch (\Throwable $e) {
                // Groq down or refusing: the seller still sees a site, from their own words and the photos' colours.
                $plan = Renderer::fallback((string) $row['description'], $photos);
            }

            $q = $this->db->pdo->prepare('SELECT COALESCE(SUM(cost_usd),0) FROM sites_ai_usage WHERE preview_id = ?');
            $q->execute([$id]);
            $this->db->update('sites_previews', ['status' => 'ready', 'site' => json_encode(['plan' => $plan, 'photos' => $photos]),
                'cost_usd' => (float) $q->fetchColumn()], ['id' => $id]);
        } catch (\Throwable $e) {
            $this->db->update('sites_previews', ['status' => 'failed',
                'error' => mb_substr($e instanceof LimitReached ? $e->getMessage() : 'We could not build it this time. Please try again.', 0, 255)], ['id' => $id]);
            @file_put_contents(STORAGE_PATH . '/logs/sites.log', date('c') . " BUILD {$id} failed: " . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
        }
    }

    public function byToken(string $token): ?array
    {
        if (preg_match('/^[0-9a-f]{32}$/', $token) !== 1) {
            return null;
        }
        $row = $this->db->get('sites_previews', '*', ['token' => $token]);
        if (!$row || strtotime((string) $row['expires_at']) < time()) {
            return null;
        }

        return $row;
    }
}
