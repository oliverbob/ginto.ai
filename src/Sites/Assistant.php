<?php

declare(strict_types=1);

namespace Ginto\Sites;

/**
 * SilverQueen AI working on a member's own site (Premium part 3, owner 2026-10-24):
 *
 *  - edit(): "AI Assistant edits your site by chat". The member says what to
 *    change; the model returns the whole revised plan, which goes through the
 *    same Renderer::clean every build does, so nothing unchecked reaches a site.
 *    Photos stay where they are: words, colours and order only.
 *  - liveScript(): "AI live-selling scripts and reel ideas" (Gold and Crown): a
 *    30-second script for their next live and three reel ideas, from their
 *    own products.
 *
 * Both spend the member's monthly AI credits (Meter), like everything else.
 */
final class Assistant
{
    private const EDIT = 'You edit a small online shop website for its owner, a Filipino seller. '
        . 'You receive the site as JSON and the owner\'s request. Return ONLY the full revised JSON, same shape and keys. '
        . 'Change only what the request asks; keep everything else exactly. Never change any "photo" number, never add or remove products '
        . 'unless asked (removing is allowed; adding is not, there would be no photo). Theme colours are #rrggbb. '
        . 'Write naturally; never invent prices, awards, reviews, guarantees or claims about income, investment or reach. '
        . 'If the request is not about this website, return the JSON unchanged.';

    private const LIVE = 'You write live-selling material for a Filipino online seller on SilverQueen. '
        . 'Return JSON: {"script": "...", "reels": ["...", "...", "..."]}. The script is spoken in about 30 seconds, warm, '
        . 'Taglish is welcome, it greets viewers, shows two or three of the products by name, and ends by inviting them to the site address given. '
        . 'Each reel idea is one sentence a seller can film on a phone. Never invent prices, discounts, awards or promises of income.';

    public function __construct(private $db)
    {
    }

    /** @return array{ok:bool, changed?:list<string>, republished?:?bool, error?:string} */
    public function edit(int $uid, string $request): array
    {
        $request = trim($request);
        if (mb_strlen($request) < 4) {
            return ['ok' => false, 'error' => 'Tell SilverQueen AI what to change, in a sentence.'];
        }
        if ($this->plan($uid) === null) {
            // Until a plan is chosen the words can be edited by hand, not by AI (owner: "only limited edits until paid").
            return ['ok' => false, 'error' => 'Editing by chat comes with your plan. Until then, change the words in Review above.'];
        }
        $draft = $this->draft($uid);
        if (!$draft) {
            return ['ok' => false, 'error' => 'Build your site first.'];
        }
        $plan = $draft['site']['plan'];
        $photos = count(json_decode((string) $draft['photos'], true) ?: []);
        try {
            $r = (new Ai(new Meter($this->db)))->chat('assistant_edit', [
                ['role' => 'system', 'content' => self::EDIT],
                ['role' => 'user', 'content' => "Site JSON:\n" . json_encode($plan, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    . "\n\nOwner's request:\n" . mb_substr($request, 0, 600)],
            ], ['user_id' => $uid, 'plan' => $this->plan($uid), 'json' => true, 'preview_id' => (int) $draft['id']]);
        } catch (LimitReached $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'SilverQueen AI could not do that right now. Try again in a minute.'];
        }
        $new = Renderer::clean(json_decode((string) $r['content'], true), $photos);
        if ($new === null) {
            return ['ok' => false, 'error' => 'That change did not come out right. Try saying it another way.'];
        }
        // Photos never move: each product keeps the photo it had, by position.
        foreach ($new['products'] as $i => &$p) {
            if (isset($plan['products'][$i]['photo'])) {
                $p['photo'] = $plan['products'][$i]['photo'];
            }
        }
        unset($p);
        $changed = $this->diff($plan, $new);
        if ($changed === []) {
            return ['ok' => true, 'changed' => [], 'republished' => null];
        }
        $site = $draft['site'];
        $site['plan'] = $new;
        $this->db->update('sites_previews', ['site' => json_encode($site)], ['id' => (int) $draft['id']]);
        Subscriptions::log("AI EDIT user {$uid}: " . implode(', ', $changed));

        return ['ok' => true, 'changed' => $changed, 'republished' => $this->republish($uid)];
    }

    /** @return array{ok:bool, script?:string, reels?:list<string>, error?:string} */
    public function liveScript(int $uid): array
    {
        if (!in_array($this->plan($uid), ['gold', 'crown'], true)) {
            return ['ok' => false, 'error' => 'Live scripts and reel ideas come with Gold and Crown.'];
        }
        $draft = $this->draft($uid);
        $name = (new Names($this->db))->of($uid);
        if (!$draft || !$name) {
            return ['ok' => false, 'error' => 'Build your site first.'];
        }
        $plan = $draft['site']['plan'];
        $products = array_values(array_filter(array_map(static fn ($p) => is_array($p) ? trim((string) ($p['name'] ?? '')) : '', $plan['products'])));
        try {
            $r = (new Ai(new Meter($this->db)))->chat('live_script', [
                ['role' => 'system', 'content' => self::LIVE],
                ['role' => 'user', 'content' => 'Shop: ' . $plan['brand']['name'] . '. ' . ($plan['brand']['tagline'] ?? '')
                    . "\nProducts: " . implode('; ', array_slice($products, 0, 8))
                    . "\nSite address: " . $name['name'] . '.silverqueen.pro'],
            ], ['user_id' => $uid, 'plan' => $this->plan($uid), 'json' => true]);
        } catch (LimitReached $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'SilverQueen AI could not write it right now. Try again in a minute.'];
        }
        $j = json_decode((string) $r['content'], true);
        $script = is_array($j) ? mb_substr(trim((string) ($j['script'] ?? '')), 0, 1500) : '';
        $reels = is_array($j['reels'] ?? null) ? array_slice(array_values(array_filter(array_map(
            static fn ($x) => is_string($x) ? mb_substr(trim($x), 0, 240) : '', $j['reels']))), 0, 3) : [];
        if ($script === '') {
            return ['ok' => false, 'error' => 'That did not come out right. Try once more.'];
        }

        return ['ok' => true, 'script' => $script, 'reels' => $reels];
    }

    private function draft(int $uid): ?array
    {
        $n = (new Names($this->db))->of($uid);
        if (!$n || empty($n['preview_id'])) {
            return null;
        }
        $p = $this->db->get('sites_previews', ['id', 'site', 'photos'], ['id' => (int) $n['preview_id'], 'status' => 'ready']);
        $site = $p ? json_decode((string) $p['site'], true) : null;

        return is_array($site) && is_array($site['plan'] ?? null) ? ['id' => (int) $p['id'], 'site' => $site, 'photos' => $p['photos']] : null;
    }

    private function plan(int $uid): ?string
    {
        $s = (new Subscriptions($this->db))->current($uid);

        return $s && $s['status'] === 'active' ? (string) $s['plan'] : null;
    }

    /** The live site follows the edit: published when paid, on its trial when held. */
    private function republish(int $uid): ?bool
    {
        $n = (new Names($this->db))->of($uid);
        if (!$n) {
            return null;
        }
        $phase = Trial::phase($n, (new Subscriptions($this->db))->current($uid));

        return match ($phase) {
            'live'  => (new Publisher($this->db))->publish($uid)['ok'],
            'trial' => (new Publisher($this->db))->publishTrial($uid)['ok'],
            default => null,
        };
    }

    /** What changed, in words a member understands. @return list<string> */
    private function diff(array $a, array $b): array
    {
        $out = [];
        $map = ['brand.name' => 'shop name', 'brand.tagline' => 'tagline', 'hero.headline' => 'headline', 'hero.subheadline' => 'sub-headline',
            'hero.cta' => 'main button', 'about.title' => 'About title', 'about.body' => 'About text', 'closing.headline' => 'closing',
            'closing.cta' => 'closing button'];
        foreach ($map as $path => $label) {
            [$x, $y] = explode('.', $path);
            if (($a[$x][$y] ?? null) !== ($b[$x][$y] ?? null)) {
                $out[] = $label;
            }
        }
        if (($a['theme'] ?? []) != ($b['theme'] ?? [])) {
            $out[] = 'colours';
        }
        if (count($a['products']) !== count($b['products'])) {
            $out[] = 'products';
        } else {
            foreach ($b['products'] as $i => $p) {
                if (($p['name'] ?? '') !== ($a['products'][$i]['name'] ?? '') || ($p['description'] ?? '') !== ($a['products'][$i]['description'] ?? '')) {
                    $out[] = 'product ' . ($i + 1);
                }
            }
        }
        if (($a['highlights'] ?? []) != ($b['highlights'] ?? [])) {
            $out[] = 'highlights';
        }

        return $out;
    }
}
