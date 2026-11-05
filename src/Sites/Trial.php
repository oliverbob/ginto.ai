<?php

declare(strict_types=1);

namespace Ginto\Sites;

use Ginto\Support\Env;

/**
 * A held name's clock (owner, 2026-10-24): "they need to see the preview (as 5
 * hour free trial), if they don't do it, we remind them to pay it for another
 * 24 hours. After 24 hours, the domain will be available for others and all
 * records, work he made about it stays, but the subdomain is gone. He cannot
 * also publish it, nor can add anything or do only limited edits until that
 * is paid."
 *
 *   trial  0-5 h   name.silverqueen.pro is live, bannered "Free trial · Xh left"
 *   grace  5-29 h  the site is down; one reminder; the name is still theirs
 *   gone   29 h    the name is free for others; their draft and photos stay
 *
 * Paying at any point makes it a normal live site (Subscriptions::confirm).
 */
final class Trial
{
    public const TRIAL_HOURS = 5;
    public const GRACE_HOURS = 24;

    public function __construct(private $db)
    {
    }

    /** Start the clock on a freshly held name, and put the trial up if a preview is ready. */
    public function start(int $uid, string $memberName = ''): void
    {
        $now = time();
        $this->db->update('sites_names', [
            'held_at'     => date('Y-m-d H:i:s', $now),
            'trial_until' => date('Y-m-d H:i:s', $now + self::TRIAL_HOURS * 3600),
            'release_at'  => date('Y-m-d H:i:s', $now + (self::TRIAL_HOURS + self::GRACE_HOURS) * 3600),
            'member_name' => $memberName !== '' ? mb_substr($memberName, 0, 120) : null,
            'reminded'    => 0,
        ], ['user_id' => $uid, 'status' => 'held']);
        (new Publisher($this->db))->publishTrial($uid);
    }

    /** trial | grace | live | none */
    public static function phase(?array $n, ?array $sub = null): string
    {
        if (!$n) {
            return 'none';
        }
        if ($n['status'] === 'active' || ($sub && $sub['status'] === 'active')) {
            return 'live';
        }
        $now = time();
        if (!empty($n['trial_until']) && $now < strtotime((string) $n['trial_until'])) {
            return 'trial';
        }
        if (!empty($n['release_at']) && $now < strtotime((string) $n['release_at'])) {
            return 'grace';
        }

        return empty($n['held_at']) ? 'held' : 'none';
    }

    /** A paid name cannot be lost; an unpaid one may still be edited in words, nothing more. */
    public static function canAdd(?array $n, ?array $sub): bool
    {
        return self::phase($n, $sub) === 'live' || !$n || empty($n['preview_id']);
    }

    /** Hourly: end trials (one reminder), release names nobody paid for. @return array<string,list<string>> */
    public function tick(): array
    {
        $now = date('Y-m-d H:i:s');
        $out = ['ended' => [], 'released' => []];

        foreach ($this->db->select('sites_names', '*', ['status' => 'held', 'trial_until[<]' => $now, 'reminded' => 0]) ?: [] as $n) {
            (new Publisher($this->db))->unpublish((string) $n['name']);
            $this->db->update('sites_names', ['reminded' => 1], ['name' => $n['name']]);
            $hours = max(1, (int) round((strtotime((string) $n['release_at']) - time()) / 3600));
            self::remind((int) $n['user_id'], "Your free trial of {$n['name']}.silverqueen.pro has ended. "
                . "Choose a plan within {$hours} hours to keep the name and put your site back up. "
                . "After that the name is released, but your work stays with you.");
            $out['ended'][] = (string) $n['name'];
        }

        foreach ($this->db->select('sites_names', '*', ['status' => 'held', 'release_at[<]' => $now]) ?: [] as $n) {
            (new Publisher($this->db))->unpublish((string) $n['name']);
            // The work stays: the draft (preview) is kept a year, only the name goes.
            if ($n['preview_id']) {
                $this->db->update('sites_previews', ['expires_at' => date('Y-m-d H:i:s', time() + 365 * 86400)], ['id' => (int) $n['preview_id']]);
            }
            $this->db->delete('sites_names', ['name' => $n['name'], 'status' => 'held']);
            self::remind((int) $n['user_id'], "{$n['name']}.silverqueen.pro has been released. Your site and photos are still saved: "
                . 'claim a name and choose a plan whenever you are ready.');
            Subscriptions::log('RELEASED ' . $n['name']);
            $out['released'][] = (string) $n['name'];
        }

        return $out;
    }

    /** A notice in the member's SilverQueen bell (members only; a silverqueen.pro account has no bell there). */
    public static function remind(int $uid, string $text): void
    {
        $identity = Members::identity($uid);
        $secret = (string) (Env::get('SITES_CARD_SECRET', '') ?? '');
        if ($identity === null || $secret === '') {
            return;
        }
        $body = (string) json_encode(['identity' => $identity, 'text' => $text], JSON_UNESCAPED_UNICODE);
        $ts = (string) time();
        $ch = curl_init('https://comchain.silverqueen.pro/api/v1/internal/sites-notice');
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-SQ-Timestamp: ' . $ts,
                'X-SQ-Signature: ' . hash_hmac('sha256', $ts . '.' . $body, $secret)],
            CURLOPT_POSTFIELDS => $body,
        ]);
        curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($status !== 200) {
            Subscriptions::log("REMIND FAILED identity {$identity}: HTTP {$status}");
        }
    }
}
