<?php

declare(strict_types=1);

namespace Ginto\Sites;

/**
 * A site as the app's feed card shows it (owner, 2026-10-24: "show in the
 * newsfeed the same presentation, but now tailored to hold what was shown in
 * the page ... show the bought version as part of the newsfeed promotions").
 *
 * Only what the site itself shows the public: its brand, headline, colours and
 * products with their photos. Its owner's own card gets their site whatever its
 * phase; the promotions shown to everybody else are paid, live sites only.
 */
final class Showcase
{
    private const PHOTO_BASE = 'https://silverqueen.pro/sites/photo/';

    public function __construct(private $db)
    {
    }

    /** @param array<string,mixed> $name a sites_names row */
    public function of(array $name): ?array
    {
        if (empty($name['preview_id'])) {
            return null;
        }
        $p = $this->db->get('sites_previews', ['site', 'photos', 'template'], ['id' => (int) $name['preview_id'], 'status' => 'ready']);
        $site = $p ? json_decode((string) $p['site'], true) : null;
        if (!is_array($site) || !is_array($site['plan'] ?? null)) {
            return null;
        }
        $plan = $site['plan'];
        $shas = json_decode((string) $p['photos'], true) ?: [];
        $img = static fn ($i): string => is_int($i) && isset($shas[$i]) && preg_match('/^[0-9a-f]{64}$/', (string) $shas[$i]) === 1
            ? self::PHOTO_BASE . $shas[$i] : '';
        $text = static fn ($v, int $max): string => mb_substr(trim(strip_tags((string) (is_scalar($v) ? $v : ''))), 0, $max);

        $products = [];
        foreach (is_array($plan['products'] ?? null) ? $plan['products'] : [] as $prod) {
            $u = is_array($prod) ? $img(isset($prod['photo']) ? (int) $prod['photo'] : -1) : '';
            if ($u === '') {
                continue;
            }
            $products[] = ['name' => $text($prod['name'] ?? '', 60), 'text' => $text($prod['description'] ?? '', 140), 'img' => $u];
            if (count($products) === 8) {
                break;
            }
        }
        $theme = [];
        foreach (['primary', 'accent', 'background', 'text'] as $k) {
            $c = (string) ($plan['theme'][$k] ?? '');
            if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $c) === 1) {
                $theme[$k] = $c;
            }
        }

        return [
            'name'     => (string) $name['name'],
            'url'      => 'https://' . $name['name'] . '.silverqueen.pro',
            'brand'    => $text($plan['brand']['name'] ?? $name['name'], 60),
            'tagline'  => $text($plan['brand']['tagline'] ?? '', 120),
            'headline' => $text($plan['hero']['headline'] ?? '', 100),
            'sub'      => $text($plan['hero']['subheadline'] ?? '', 160),
            'theme'    => $theme,
            'look'     => Templates::get($p['template'] ?? null)['name'],
            'products' => $products,
        ];
    }

    /**
     * Paid, live sites for the feed's promotions, never the viewer's own, in
     * fair turns: the least shown first, then the longest unseen, ties at random.
     * Each one handed out counts as shown.
     *
     * @return list<array<string,mixed>>
     */
    public function featured(int $exceptUserId, int $limit = 3): array
    {
        $subs = $this->db->select('sites_subscriptions', ['user_id', 'founding'], ['status' => 'active',
            'period_end[>]' => date('Y-m-d H:i:s'), 'user_id[!]' => $exceptUserId, 'ORDER' => ['id' => 'DESC']]) ?: [];
        $founding = [];
        foreach ($subs as $s) {
            $founding[(int) $s['user_id']] = ($founding[(int) $s['user_id']] ?? false) || (bool) $s['founding'];
        }
        if ($founding === []) {
            return [];
        }
        $names = $this->db->select('sites_names', '*', ['user_id' => array_keys($founding), 'status' => 'active']) ?: [];
        $turns = [];
        foreach ($this->db->select('sites_showcase', '*', ['name' => array_column($names, 'name')]) ?: [] as $t) {
            $turns[$t['name']] = $t;
        }
        // Shuffled, then a stable sort by turns: ties stay in random order.
        shuffle($names);
        usort($names, static function ($a, $b) use ($turns) {
            $ta = $turns[$a['name']] ?? ['shown' => 0, 'last_shown' => ''];
            $tb = $turns[$b['name']] ?? ['shown' => 0, 'last_shown' => ''];

            return [(int) $ta['shown'], (string) $ta['last_shown']] <=> [(int) $tb['shown'], (string) $tb['last_shown']];
        });

        $out = [];
        foreach ($names as $n) {
            if (count($out) === $limit) {
                break;
            }
            $card = Publisher::isLive((string) $n['name']) ? $this->of($n) : null;
            if (!$card || $card['products'] === []) {
                continue;
            }
            $out[] = $card + ['founding' => $founding[(int) $n['user_id']]];
            $this->counted((string) $n['name']);
        }

        return $out;
    }

    private function counted(string $name): void
    {
        $this->db->query('INSERT INTO sites_showcase (name, shown, last_shown) VALUES (:n, 1, NOW())
            ON DUPLICATE KEY UPDATE shown = shown + 1, last_shown = NOW()', [':n' => $name]);
    }
}
