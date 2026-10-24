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
            'hold'  => $this->hold($db, $uid, (string) ($in['n'] ?? ''), $who),
            'state' => ['ok' => true] + $this->state($db, $uid, $who),
            'plan'  => $this->choose($db, $uid, (string) ($in['plan'] ?? '')) + $this->state($db, $uid, $who),
            // The feed's promotions: paid, live sites, never the viewer's own.
            'showcase' => ['ok' => true, 'sites' => $this->showcase($db, $uid, !empty($in['preview']))],
            default => ['ok' => false, 'error' => 'Unknown action.'],
        };
        echo json_encode($out);
    }

    private function hold($db, int $uid, string $name, string $who): array
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
