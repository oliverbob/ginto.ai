<?php

declare(strict_types=1);

namespace Ginto\Sites;

use Ginto\Support\Env;

/**
 * What a member's site did, and what it gives back (owner, 2026-10-24).
 *
 * Hourly, visits to a site count as views of the SilverQueen posts it shows
 * ("Site visits should constitute to new counts to displayed photos"): each
 * visit since the last run, on every post the site displays, through
 * comchain's sites-views (its shown counter only, never post_views).
 *
 * Weekly, a summary in the member's bell: visits and visitors against the week
 * before, how often the site was featured in members' feeds, the store's
 * listings; Crown adds one piece of advice from SilverQueen AI ("AI sales
 * insights" is a Crown feature).
 */
final class Insights
{
    public function __construct(private $db)
    {
    }

    /** @return array<string,int> name => views credited this run */
    public function creditVisits(?array $days = null): array
    {
        $days ??= (new Publisher($this->db))->stats();
        $out = [];
        foreach ($days as $name => $byDay) {
            $n = is_array($byDay) ? $this->db->get('sites_names', '*', ['name' => (string) $name]) : null;
            if (!$n || empty($n['preview_id'])) {
                continue;
            }
            $done = json_decode((string) ($n['visits_credited'] ?? ''), true) ?: [];
            $fresh = 0;
            $keep = [];
            foreach ($byDay as $day => $cell) {
                if ($day < date('Y-m-d', time() - 3 * 86400)) {
                    continue; // older days were settled when they were current
                }
                $views = (int) ($cell[0] ?? 0);
                $fresh += max(0, $views - (int) ($done[$day] ?? 0));
                $keep[$day] = max($views, (int) ($done[$day] ?? 0));
            }
            $this->db->update('sites_names', ['visits_credited' => json_encode($keep)], ['name' => (string) $name]);
            $posts = $fresh > 0 ? $this->shownPosts((int) $n['preview_id']) : [];
            if ($posts !== [] && self::credit((int) $n['user_id'], array_fill_keys($posts, $fresh))) {
                $out[(string) $name] = $fresh;
            }
        }

        return $out;
    }

    /** The posts a site actually displays: its products' photos, by their source post. @return list<int> */
    private function shownPosts(int $previewId): array
    {
        $p = $this->db->get('sites_previews', ['site', 'posts'], ['id' => $previewId]);
        $posts = json_decode((string) ($p['posts'] ?? ''), true) ?: [];
        $site = json_decode((string) ($p['site'] ?? ''), true) ?: [];
        $ids = [];
        foreach ($site['plan']['products'] ?? [] as $prod) {
            $i = is_array($prod) ? (int) ($prod['photo'] ?? -1) : -1;
            if (!empty($posts[$i])) {
                $ids[(int) $posts[$i]] = true;
            }
        }

        return array_keys($ids);
    }

    /** @param array<int,int> $credits post_id => views */
    private static function credit(int $uid, array $credits): bool
    {
        $r = self::comchain('sites-views', ['identity' => Members::identity($uid), 'credits' => $credits]);

        return !empty($r['ok']);
    }

    /** Monday morning: each active site's week, in its owner's bell. @return list<string> */
    public function weekly(): array
    {
        $days = (new Publisher($this->db))->stats();
        $out = [];
        foreach ($this->db->select('sites_subscriptions', '*', ['status' => 'active']) ?: [] as $s) {
            $name = (string) $s['name'];
            $n = $this->db->get('sites_names', '*', ['name' => $name, 'user_id' => (int) $s['user_id']]);
            if (!$n || !Members::isMember((int) $s['user_id'])) {
                continue;
            }
            [$v, $p] = self::sum($days[$name] ?? [], 0, 7);
            [$v0] = self::sum($days[$name] ?? [], 7, 14);
            $turn = $this->db->get('sites_showcase', '*', ['name' => $name]);
            $shown = $turn ? (int) $turn['shown'] - (int) $turn['shown_reported'] : 0;
            if ($turn) {
                $this->db->update('sites_showcase', ['shown_reported' => (int) $turn['shown']], ['name' => $name]);
            }
            $page = Members::page((int) $s['user_id']);
            $listed = count($page['shop']['items'] ?? []);

            $trend = $v0 > 0 ? ' (' . ($v >= $v0 ? '+' : '') . (int) round(($v - $v0) / $v0 * 100) . '% on the week before)' : '';
            $text = "Your week at {$name}.silverqueen.pro: {$v} visit" . ($v === 1 ? '' : 's') . " from {$p} visitor" . ($p === 1 ? '' : 's') . $trend . '. '
                . ($shown > 0 ? "It was featured {$shown} time" . ($shown === 1 ? '' : 's') . " in members' feeds. " : '')
                . ($listed > 0 ? "Your store has {$listed} listing" . ($listed === 1 ? '' : 's') . ' on the Mall. ' : 'Open your store in the Mall to sell from your site too. ')
                . 'Every visit also counts as a view of the posts your site shows.';
            if ($s['plan'] === 'crown') {
                $tip = $this->tip((int) $s['user_id'], $name, $v, $v0, $shown, $listed);
                if ($tip !== '') {
                    $text .= ' SilverQueen AI: ' . $tip;
                }
            }
            Trial::remind((int) $s['user_id'], $text);
            Subscriptions::log("INSIGHTS {$name} {$v}/{$p}");
            $out[] = $name;
        }

        return $out;
    }

    /** @return array{0:int,1:int} views and visitors over days [from, to) ago */
    private static function sum(array $byDay, int $from, int $to): array
    {
        $v = 0;
        $p = 0;
        for ($i = $from; $i < $to; $i++) {
            $cell = $byDay[date('Y-m-d', time() - $i * 86400)] ?? null;
            $v += (int) ($cell[0] ?? 0);
            $p += (int) ($cell[1] ?? 0);
        }

        return [$v, $p];
    }

    /** One short, concrete piece of advice for a Crown site. Never invents numbers. */
    private function tip(int $uid, string $name, int $v, int $v0, int $shown, int $listed): string
    {
        try {
            $r = (new Ai(new Meter($this->db)))->chat('insights', [
                ['role' => 'system', 'content' => 'You advise a small Filipino online seller about their SilverQueen website. '
                    . 'Give ONE practical suggestion for the coming week in at most two short sentences, warm and plain. '
                    . 'Use only the numbers given; never invent figures, prices, promises of reach or income.'],
                ['role' => 'user', 'content' => "Site {$name}.silverqueen.pro. Visits this week {$v}, last week {$v0}. "
                    . "Featured in members' feeds {$shown} times. Store listings {$listed}."],
            ], ['user_id' => $uid, 'plan' => 'crown']);

            return mb_substr(trim(preg_replace('~\s+~u', ' ', (string) $r['content'])), 0, 300);
        } catch (\Throwable $e) {
            return '';
        }
    }

    private static function comchain(string $what, array $data): array
    {
        $secret = (string) (Env::get('SITES_CARD_SECRET', '') ?? '');
        if ($secret === '' || empty($data['identity'])) {
            return [];
        }
        $body = (string) json_encode($data);
        $ts = (string) time();
        $ch = curl_init('https://comchain.silverqueen.pro/api/v1/internal/' . $what);
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-SQ-Timestamp: ' . $ts,
                'X-SQ-Signature: ' . hash_hmac('sha256', $ts . '.' . $body, $secret)],
            CURLOPT_POSTFIELDS => $body,
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
        $out = is_string($raw) ? json_decode($raw, true) : null;
        $out = is_array($out) && isset($out['data']) ? $out['data'] : $out;

        return is_array($out) ? $out : [];
    }
}
