<?php

namespace Ginto\Controllers;

use Ginto\Core\Database;
use Ginto\Sites\Members;
use Ginto\Sites\Names;
use Ginto\Sites\Subscriptions;
use Ginto\Sites\Trial;
use Ginto\Support\Env;

/**
 * POST /api/internal/sites-card -- the Sites ad on the SilverQueen feed, holding
 * names here, in the one registry (owner, 2026-10-24: "go straight to
 * silverqueen").
 *
 * The chat server signs every call with SITES_CARD_SECRET (HMAC of "ts.body",
 * 5-minute window) and names the member by their chat identity; this side
 * keeps them as Members::uid(). Actions:
 *   state {identity, display_name}       their name, its phase and clock, and a one-time link in
 *   check {identity, n}                  is this name free (the same rules as /sites/name)
 *   hold  {identity, n, display_name}    hold it and start the 5-hour trial
 */
class SitesCardController
{
    private const SKEW = 300;

    public function handle()
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        $raw = (string) file_get_contents('php://input');
        if (!$this->signed($raw)) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'error' => 'Not signed.']);

            return;
        }
        $in = json_decode($raw, true) ?: [];
        $identity = (int) ($in['identity'] ?? 0);
        if ($identity <= 0) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'No member.']);

            return;
        }
        $db = Database::getInstance();
        $uid = Members::uid($identity);
        $who = mb_substr(trim((string) ($in['display_name'] ?? '')), 0, 120);

        $out = match ((string) ($in['action'] ?? '')) {
            'check' => (new Names($db))->check((string) ($in['n'] ?? ''), $uid),
            'hold'  => $this->hold($db, $uid, (string) ($in['n'] ?? ''), $who, (string) ($in['template'] ?? '')),
            'state' => ['ok' => true] + $this->state($db, $uid, $who),
            'plan'  => $this->choose($db, $uid, (string) ($in['plan'] ?? '')) + $this->state($db, $uid, $who),
            // The feed's promotions: paid, live sites, never the viewer's own.
            'showcase' => ['ok' => true, 'sites' => $this->showcase($db, $uid, !empty($in['preview']))],
            // The Page's own words, edited in the app (Pages > Edit Page).
            'edit' => $this->editPage($db, $uid, $in),
            // Several live, paid sites by name: for the feed's site posts.
            'cards' => ['ok' => true, 'sites' => $this->cards($db, (array) ($in['names'] ?? []))],
            default => ['ok' => false, 'error' => 'Unknown action.'],
        };
        echo json_encode($out);
    }

    /** GET /api/internal/sites-templates -- the twelve ways a site can be drawn. */
    public function templates()
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        echo json_encode(['ok' => true, 'templates' => \Ginto\Sites\Templates::list()]);
    }

    private function hold($db, int $uid, string $name, string $who, string $template = ''): array
    {
        $names = new Names($db);
        $mine = $names->of($uid);
        if ($mine && Trial::phase($mine, (new Subscriptions($db))->current($uid)) !== 'none') {
            return ['ok' => false, 'reason' => 'You already hold ' . $mine['name'] . '.silverqueen.pro.'] + $this->state($db, $uid, $who);
        }
        // Their newest finished draft, if they have one, becomes this name's site.
        $draft = $db->get('sites_previews', ['id'], ['user_id' => $uid, 'status' => 'ready', 'ORDER' => ['id' => 'DESC']]);
        $r = $names->claim($name, $uid, $draft ? (int) $draft['id'] : null);
        if (empty($r['ok'])) {
            return $r + ['ok' => false];
        }
        /* The owner's drawing for a granted site (admin, 2026-10-29: "I have to
           chose the template for them"): the held name's site is built in it when
           the trial publishes from the draft it claimed. */
        if ($template !== '') {
            $heldNow = $names->of($uid);
            if (!empty($heldNow['preview_id'])) {
                $db->update('sites_previews', ['template' => \Ginto\Sites\Templates::get($template)['id']], ['id' => (int) $heldNow['preview_id']]);
            }
        }
        (new Trial($db))->start($uid, $who);
        $this->choose($db, $uid, (string) ($in['plan'] ?? ''));
        Subscriptions::log("HELD {$r['name']} by member " . Members::identity($uid) . ' from the feed card');

        return ['ok' => true, 'held' => true, 'name' => $r['name']] + $this->state($db, $uid, $who);
    }

    /** Keep the plan they picked with their name, so every page shows the same one. */
    /**
     * The feed's promotions. A simulator asking for a preview, while no paid site
     * exists, gets their own site so the single cards can be seen; never counted.
     */
    private function showcase($db, int $uid, bool $preview): array
    {
        $sc = new \Ginto\Sites\Showcase($db);
        $out = $sc->featured($uid);
        if ($out === [] && $preview && in_array(Members::identity($uid), \Ginto\Sites\Config::SIMULATORS, true)) {
            $n = (new Names($db))->of($uid);
            $own = $n ? $sc->of($n) : null;
            if ($own && $own['products'] !== []) {
                $out[] = $own + ['founding' => false, 'preview' => true];
            }
        }

        return $out;
    }

    /**
     * Edit the Page in the app (owner, 2026-10-24: "Editing the page is not
     * possible"): its name, tagline and about, the same words the site shows.
     * The live site follows at once; a held name's trial site too.
     */
    private function editPage($db, int $uid, array $in): array
    {
        $n = (new Names($db))->of($uid);
        if (!$n || empty($n['preview_id'])) {
            return ['ok' => false, 'error' => 'Build your site first.'];
        }
        $p = $db->get('sites_previews', ['id', 'site'], ['id' => (int) $n['preview_id']]);
        $site = $p ? json_decode((string) $p['site'], true) : null;
        if (!is_array($site) || !is_array($site['plan'] ?? null)) {
            return ['ok' => false, 'error' => 'Build your site first.'];
        }
        $t = static fn ($v, int $max): string => mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags((string) $v))), 0, $max);
        if (isset($in['brand']) && $t($in['brand'], 60) !== '') {
            $site['plan']['brand']['name'] = $t($in['brand'], 60);
        }
        if (isset($in['tagline'])) {
            $site['plan']['brand']['tagline'] = $t($in['tagline'], 120);
        }
        if (isset($in['about'])) {
            $site['plan']['about']['body'] = mb_substr(trim(strip_tags((string) $in['about'])), 0, 900);
        }
        $db->update('sites_previews', ['site' => json_encode($site)], ['id' => (int) $p['id']]);
        $phase = \Ginto\Sites\Trial::phase($n, (new Subscriptions($db))->current($uid));
        $pub = $phase === 'live' ? (new \Ginto\Sites\Publisher($db))->publish($uid)
            : ($phase === 'trial' ? (new \Ginto\Sites\Publisher($db))->publishTrial($uid) : ['ok' => null]);

        return ['ok' => true, 'republished' => $pub['ok'] ?? null, 'site' => (new \Ginto\Sites\Showcase($db))->of($db->get('sites_names', '*', ['name' => $n['name']]))];
    }

    /** @return array<string,array> name => card, for live paid sites only (never a trial or a preview) */
    private function cards($db, array $names): array
    {
        $names = array_slice(array_values(array_filter($names, static fn ($n) => is_string($n) && preg_match(\Ginto\Sites\Config::NAME_RE, $n) === 1)), 0, 30);
        if ($names === []) {
            return [];
        }
        $sc = new \Ginto\Sites\Showcase($db);
        $out = [];
        foreach ($db->select('sites_names', '*', ['name' => $names, 'status' => 'active']) ?: [] as $n) {
            if (!\Ginto\Sites\Publisher::isLive((string) $n['name'])) {
                continue;
            }
            $c = $sc->of($n);
            if ($c) {
                $out[(string) $n['name']] = $c;
            }
        }

        return $out;
    }

    private function choose($db, int $uid, string $plan): array
    {
        if (\Ginto\Sites\Config::plan($plan) === null) {
            return ['ok' => false];
        }
        $db->update('sites_names', ['plan_choice' => $plan], ['user_id' => $uid]);

        return ['ok' => true, 'plan_choice' => $plan];
    }

    private function state($db, int $uid, string $who): array
    {
        // The owner's complimentary plans take effect the moment there is a name.
        (new Subscriptions($db))->complimentary($uid);
        $n = (new Names($db))->of($uid);
        $sub = (new Subscriptions($db))->current($uid);
        $phase = Trial::phase($n, $sub);
        $hasDraft = (bool) $db->get('sites_previews', ['id'], ['user_id' => $uid, 'status' => 'ready']);
        $to = $phase === 'none' || (!$hasDraft && $phase !== 'live') ? '/sites/start' . ($n ? '?name=' . rawurlencode((string) $n['name']) : '') : '/sites/me';

        return [
            'mine'        => $n && $phase !== 'none' ? (string) $n['name'] : null,
            'plan_choice' => $n['plan_choice'] ?? null,
            'phase'       => $phase,
            'trial_left'  => $n && !empty($n['trial_until']) ? max(0, strtotime((string) $n['trial_until']) - time()) : 0,
            'release_left'=> $n && !empty($n['release_at']) ? max(0, strtotime((string) $n['release_at']) - time()) : 0,
            'has_draft'   => $hasDraft,
            'enter'       => Members::enterLink($uid, $who, $to),
            'filled'      => (new Subscriptions($db))->foundingTaken(),
            'cap'         => \Ginto\Sites\Config::foundingCap(),
            // Their own site, as the feed card shows it once they have one.
            'site'        => $n && $hasDraft && $phase !== 'none' ? (new \Ginto\Sites\Showcase($db))->of($n) : null,
            'founding'    => $sub && $sub['status'] === 'active' && !empty($sub['founding']),
            'plan'        => $sub && $sub['status'] === 'active' ? (string) $sub['plan'] : null,
            'complimentary' => Subscriptions::isComplimentary($sub),
        ];
    }

    private function signed(string $raw): bool
    {
        $secret = (string) (Env::get('SITES_CARD_SECRET', '') ?? '');
        $ts = (string) ($_SERVER['HTTP_X_SQ_TIMESTAMP'] ?? '');
        $sig = (string) ($_SERVER['HTTP_X_SQ_SIGNATURE'] ?? '');

        return $secret !== '' && ctype_digit($ts) && abs(time() - (int) $ts) <= self::SKEW
            && hash_equals(hash_hmac('sha256', $ts . '.' . $raw, $secret), $sig);
    }
}
