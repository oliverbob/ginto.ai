<?php

declare(strict_types=1);

namespace Ginto\Sites;

/**
 * Search engines and a member's site (owner, 2026-10-24).
 *
 * A paid site is open to search engines only when:
 *  - its owner leaves "Show on Google" on (sites_names.index_on, on by default);
 *  - SilverQueen AI checked the site's content as it is now and found nothing
 *    against the rules (index_status 'ok' for index_hash = the content's hash);
 *    the check is paid from the site's own plan (1 AI credit) and runs again
 *    whenever the content changes;
 *  - three days have passed since it passed, so a bad site is caught before
 *    search engines ever see it.
 * Trials and previews are never open to search engines. A lapsed site is taken
 * down, and search engines drop it.
 *
 * seo(): "more professional indexing" with the plan's AI (3 credits): a search
 * title and description written for the shop.
 */
final class Indexing
{
    public const WAIT_DAYS = 3;

    private const CHECK = 'You review a small online shop website before it is shown to search engines, for SilverQueen, a Philippine '
        . 'social platform. Return JSON only: {"ok": true|false, "issues": ["...", ...]} with at most 3 short issues written to the shop owner. '
        . 'Not allowed: illegal or prohibited goods (drugs, weapons, counterfeit, wildlife, gambling, adult or sexual content), '
        . 'investment, crypto-profit, income or "earn money" promises, pyramid or recruitment schemes, medical or health cure claims, '
        . 'impersonating a brand, celebrity or government body, hateful or violent content, other people\'s private data, '
        . 'and mentioning taxes or VAT or naming crypto exchanges. Ordinary products, services, prices and contact details are fine. '
        . 'When in doubt about something ordinary, it is ok.';

    private const SEO = 'You write the search-engine listing for a small Filipino online shop. Return JSON only: '
        . '{"title": "...", "description": "..."}. Title at most 60 characters: the shop name and what it sells. Description at most 155 '
        . 'characters: what it offers and why to visit, plain and specific, no prices, no promises, no emoji, no ALL CAPS.';

    public function __construct(private $db)
    {
    }

    /** The content search engines would read: its words and contact. */
    public static function hash(array $site): string
    {
        return substr(hash('sha256', json_encode([$site['plan'] ?? [], $site['contact'] ?? '', $site['seo'] ?? []])), 0, 16);
    }

    public function site(array $n): ?array
    {
        if (empty($n['preview_id'])) {
            return null;
        }
        $p = $this->db->get('sites_previews', ['site'], ['id' => (int) $n['preview_id']]);
        $s = $p ? json_decode((string) $p['site'], true) : null;

        return is_array($s) && is_array($s['plan'] ?? null) ? $s : null;
    }

    public function indexable(array $n): bool
    {
        $site = $this->site($n);

        return $site !== null && (int) $n['index_on'] === 1 && $n['status'] === 'active' && $n['index_status'] === 'ok'
            && $n['index_hash'] === self::hash($site) && !empty($n['index_after']) && strtotime((string) $n['index_after']) <= time();
    }

    /** What My Site says about it. @return array{on:bool, state:string, text:string} */
    public function describe(array $n): array
    {
        $site = $this->site($n);
        $on = (int) $n['index_on'] === 1;
        if (!$on) {
            return ['on' => false, 'state' => 'off', 'text' => 'Hidden from Google. Only people with your link can find your site.'];
        }
        if ($n['status'] !== 'active') {
            return ['on' => true, 'state' => 'wait', 'text' => 'Shown on Google once your plan is active.'];
        }
        if (!$site || $n['index_hash'] !== self::hash($site) || $n['index_status'] === null || $n['index_status'] === 'pending') {
            return ['on' => true, 'state' => 'wait', 'text' => 'SilverQueen AI is checking your site for Google. This takes a few minutes.'];
        }
        if ($n['index_status'] === 'blocked') {
            return ['on' => true, 'state' => 'bad', 'text' => 'Not shown on Google yet: ' . ($n['index_note'] ?: 'something on your site needs changing.') . ' Change it in Review above and it is checked again.'];
        }
        $at = strtotime((string) $n['index_after']);

        return $at > time()
            ? ['on' => true, 'state' => 'wait', 'text' => 'Approved. Your site opens to Google on ' . date('j F', $at) . '.']
            : ['on' => true, 'state' => 'ok', 'text' => 'Open to Google. New sites usually appear in search within a few days to weeks.'];
    }

    /** Every ten minutes: check changed sites, open or close them to search engines. @return list<string> */
    public function tick(): array
    {
        $out = [];
        foreach ($this->db->select('sites_names', '*', ['status' => 'active']) ?: [] as $n) {
            $site = $this->site($n);
            if (!$site) {
                continue;
            }
            if ((int) $n['index_on'] === 1 && $n['index_hash'] !== self::hash($site)) {
                $r = $this->check($n, $site);
                if ($r === null) {
                    continue; // AI not available now: tried again next run
                }
                $n = $this->db->get('sites_names', '*', ['name' => $n['name']]);
                $out[] = $n['name'] . ' ' . $n['index_status'];
            }
            $want = $this->indexable($n);
            if ((int) $n['index_live'] !== (int) $want) {
                $uid = (int) $n['user_id'];
                $this->db->update('sites_names', ['index_live' => $want ? 1 : 0], ['name' => $n['name']]);
                (new Publisher($this->db))->publish($uid);
                Subscriptions::log('INDEX ' . ($want ? 'OPEN ' : 'CLOSED ') . $n['name']);
                $out[] = $n['name'] . ($want ? ' opened' : ' closed');
            }
        }

        return $out;
    }

    /** @return ?string 'ok' | 'blocked', or null when the check could not run */
    private function check(array $n, array $site): ?string
    {
        $sub = (new Subscriptions($this->db))->current((int) $n['user_id']);
        if (!$sub || $sub['status'] !== 'active') {
            return null;
        }
        $plan = $site['plan'];
        $text = ['Shop: ' . ($plan['brand']['name'] ?? ''), 'Tagline: ' . ($plan['brand']['tagline'] ?? ''),
            'Headline: ' . ($plan['hero']['headline'] ?? ''), 'Sub-headline: ' . ($plan['hero']['subheadline'] ?? ''),
            'About: ' . mb_substr((string) ($plan['about']['body'] ?? ''), 0, 900), 'Contact: ' . ($site['contact'] ?? '')];
        foreach (array_slice($plan['products'] ?? [], 0, 12) as $p) {
            $text[] = 'Product: ' . ($p['name'] ?? '') . ' — ' . mb_substr((string) ($p['description'] ?? ''), 0, 240);
        }
        foreach ($plan['highlights'] ?? [] as $h) {
            $text[] = 'Highlight: ' . ($h['title'] ?? '') . ' — ' . ($h['body'] ?? '');
        }
        if (!empty($site['seo'])) {
            $text[] = 'Search title: ' . ($site['seo']['title'] ?? '') . '; description: ' . ($site['seo']['description'] ?? '');
        }
        try {
            $r = (new Ai(new Meter($this->db)))->chat('index_check', [
                ['role' => 'system', 'content' => self::CHECK],
                ['role' => 'user', 'content' => implode("\n", $text)],
            ], ['user_id' => (int) $n['user_id'], 'plan' => (string) $sub['plan'], 'json' => true]);
        } catch (\Throwable $e) {
            return null;
        }
        $j = json_decode((string) $r['content'], true);
        if (!is_array($j) || !isset($j['ok'])) {
            return null;
        }
        $ok = (bool) $j['ok'];
        $issues = array_slice(array_values(array_filter(array_map(static fn ($x) => is_string($x) ? trim($x) : '', (array) ($j['issues'] ?? [])))), 0, 3);
        $passedBefore = $n['index_status'] === 'ok' && !empty($n['index_after']);
        $this->db->update('sites_names', [
            'index_status' => $ok ? 'ok' : 'blocked',
            'index_note'   => $ok ? null : mb_substr(implode(' ', $issues), 0, 400),
            'index_hash'   => self::hash($site),
            // The three days start at the first pass; an edit that passes again keeps its date.
            'index_after'  => $ok ? ($passedBefore ? $n['index_after'] : date('Y-m-d H:i:s', time() + self::WAIT_DAYS * 86400)) : null,
        ], ['name' => $n['name']]);
        Subscriptions::log('INDEX CHECK ' . $n['name'] . ' ' . ($ok ? 'ok' : 'blocked: ' . implode(' | ', $issues)));

        return $ok ? 'ok' : 'blocked';
    }

    /** "Improve my Google listing with AI": a search title and description, from the plan's credits. */
    public function seo(int $uid): array
    {
        $n = (new Names($this->db))->of($uid);
        $sub = (new Subscriptions($this->db))->current($uid);
        $site = $n ? $this->site($n) : null;
        if (!$site || !$sub || $sub['status'] !== 'active') {
            return ['ok' => false, 'error' => 'This comes with your plan, once your site is built.'];
        }
        $plan = $site['plan'];
        $products = implode('; ', array_slice(array_filter(array_map(static fn ($p) => (string) ($p['name'] ?? ''), $plan['products'] ?? [])), 0, 8));
        try {
            $r = (new Ai(new Meter($this->db)))->chat('seo', [
                ['role' => 'system', 'content' => self::SEO],
                ['role' => 'user', 'content' => 'Shop: ' . ($plan['brand']['name'] ?? '') . '. ' . ($plan['brand']['tagline'] ?? '')
                    . "\nAbout: " . mb_substr((string) ($plan['about']['body'] ?? ''), 0, 500) . "\nProducts: " . $products],
            ], ['user_id' => $uid, 'plan' => (string) $sub['plan'], 'json' => true]);
        } catch (LimitReached $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'SilverQueen AI could not write it right now. Try again in a minute.'];
        }
        $j = json_decode((string) $r['content'], true);
        $t = static fn ($v, int $max): string => mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags((string) $v))), 0, $max);
        $seo = ['title' => $t($j['title'] ?? '', 70), 'description' => $t($j['description'] ?? '', 170)];
        if ($seo['title'] === '' || $seo['description'] === '') {
            return ['ok' => false, 'error' => 'That did not come out right. Try once more.'];
        }
        $site['seo'] = $seo;
        $this->db->update('sites_previews', ['site' => json_encode($site)], ['id' => (int) $n['preview_id']]);
        (new Publisher($this->db))->publish($uid);

        return ['ok' => true, 'seo' => $seo];
    }

    public function setOn(int $uid, bool $on): array
    {
        $n = (new Names($this->db))->of($uid);
        if (!$n) {
            return ['ok' => false, 'error' => 'Claim your name first.'];
        }
        $this->db->update('sites_names', ['index_on' => $on ? 1 : 0], ['name' => $n['name']]);
        $n = $this->db->get('sites_names', '*', ['name' => $n['name']]);
        $want = $this->indexable($n);
        if ((int) $n['index_live'] !== (int) $want) {
            $this->db->update('sites_names', ['index_live' => $want ? 1 : 0], ['name' => $n['name']]);
            (new Publisher($this->db))->publish($uid);
        }

        return ['ok' => true] + $this->describe($this->db->get('sites_names', '*', ['name' => $n['name']]));
    }
}
