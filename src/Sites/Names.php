<?php

declare(strict_types=1);

namespace Ginto\Sites;

/**
 * yourname: the Page at silverqueen.pro/yourname and the Site at
 * yourname.silverqueen.pro. One name per account; once claimed it is gone.
 *
 * A name is refused when it is malformed, kept for SilverQueen, reads like one
 * of our brands, is claimed by someone else, or is a live tunnel's subdomain
 * (tunnels share *.silverqueen.pro, and the tunnel would win).
 */
final class Names
{
    public function __construct(private $db)
    {
    }

    /** @return array{name:string, ok:bool, mine?:bool, reason?:string} */
    public function check(string $raw, int $userId = 0): array
    {
        $name = strtolower(trim($raw));
        if (preg_match(Config::NAME_RE, $name) !== 1 || str_contains($name, '--')) {
            return ['name' => $name, 'ok' => false, 'reason' => 'Use 3 to 32 letters, numbers or single hyphens.'];
        }
        if (in_array($name, Config::RESERVED, true)) {
            return ['name' => $name, 'ok' => false, 'reason' => 'That name is kept for SilverQueen.'];
        }
        $folded = self::fold($name);
        foreach (Config::LOOKALIKE_ROOTS as $root) {
            if (str_contains($folded, self::fold($root))) {
                return ['name' => $name, 'ok' => false, 'reason' => 'That name is too close to one of ours.'];
            }
        }
        $row = $this->db->get('sites_names', ['user_id'], ['name' => $name]);
        if ($row) {
            return (int) $row['user_id'] === $userId && $userId > 0
                ? ['name' => $name, 'ok' => true, 'mine' => true]
                : ['name' => $name, 'ok' => false, 'reason' => 'Someone has already claimed it.'];
        }
        if ($this->isTunnel($name)) {
            return ['name' => $name, 'ok' => false, 'reason' => 'That name is in use on SilverQueen.'];
        }

        return ['name' => $name, 'ok' => true, 'mine' => false];
    }

    /** Hold a free name for this account, replacing a name it held before (an active one is never replaced). */
    public function claim(string $raw, int $userId, ?int $previewId): array
    {
        $v = $this->check($raw, $userId);
        if (!$v['ok']) {
            return $v;
        }
        $mine = $this->db->get('sites_names', ['name', 'status'], ['user_id' => $userId]);
        if ($mine && $mine['status'] === 'active' && $mine['name'] !== $v['name']) {
            return ['name' => $v['name'], 'ok' => false, 'reason' => 'Your site is live as ' . $mine['name'] . '.silverqueen.pro.'];
        }
        try {
            $this->db->pdo->beginTransaction();
            $this->db->delete('sites_names', ['user_id' => $userId, 'status' => 'held']);
            $this->db->insert('sites_names', ['name' => $v['name'], 'user_id' => $userId, 'status' => 'held', 'preview_id' => $previewId]);
            // The preview becomes this account's draft: kept 30 days instead of 72 hours.
            if ($previewId) {
                $this->db->update('sites_previews', ['user_id' => $userId, 'expires_at' => date('Y-m-d H:i:s', time() + 30 * 86400)], ['id' => $previewId]);
            }
            $this->db->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->db->pdo->inTransaction()) {
                $this->db->pdo->rollBack();
            }

            return ['name' => $v['name'], 'ok' => false, 'reason' => 'Someone just claimed it. Try another name.'];
        }

        return $v + ['held' => true];
    }

    public function of(int $userId): ?array
    {
        // The whole row: the trial clock (held_at, trial_until, release_at) decides its phase.
        return $this->db->get('sites_names', '*', ['user_id' => $userId]) ?: null;
    }

    /** Letters that read alike become one: s1lverqueen, silverqu33n and 5ilverqueen all read as silverqueen. */
    private static function fold(string $s): string
    {
        $s = strtr(str_replace('-', '', $s), ['0' => 'o', '1' => 'i', 'l' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't', '8' => 'b', '9' => 'g']);

        return preg_replace('/(.)\\1+/', '$1', $s); // and doubled letters (silverqueeen) count once
    }

    private function isTunnel(string $name): bool
    {
        if (is_file('/tmp/tunnel-' . $name . '.pid')) {
            return true;
        }
        foreach (['/var/lib/ginto/tunnel-registry.json', '/tmp/ginto-tunnel-registry.json'] as $f) {
            if (is_file($f)) {
                $reg = json_decode((string) @file_get_contents($f), true);
                if (is_array($reg) && isset($reg[$name])) {
                    return true;
                }
            }
        }

        return false;
    }
}
