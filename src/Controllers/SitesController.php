<?php

namespace Ginto\Controllers;

use Ginto\Core\Database;
use Ginto\Sites\Config;
use Ginto\Sites\LimitReached;
use Ginto\Sites\Meter;
use Ginto\Sites\Publisher;
use Ginto\Sites\Subscriptions;
use Ginto\Sites\Members;
use Ginto\Sites\Trial;
use Ginto\Sites\Templates;
use Ginto\Sites\Ai;
use Ginto\Sites\Names;
use Ginto\Sites\Photos;
use Ginto\Sites\Previews;
use Ginto\Sites\Renderer;

/**
 * SilverQueen Premium -- the public side (owner, 2026-10-24).
 *
 *   /sites               the Directory and the founding slots (also sites.silverqueen.pro)
 *   /sites/plans         Silver, Gold, Crown, and the terms in brief
 *   /sites/start         "Start it now": photos + description -> free preview
 *   /sites/p/{token}     the preview itself (72 h, noindex)
 *   /sites/name, /claim  is a name free, and holding it
 *
 * Until SITES_PUBLIC=true only admins can build a preview (and spend AI);
 * everybody else sees the plans and "opening soon".
 */
class SitesController extends \Core\Controller
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? Database::getInstance();
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
    }

    public function directory()
    {
        $this->page('sites/directory', ['title' => 'SilverQueen Premium Directory'] + $this->founding() + [
            'sites' => $this->db->select('sites_names', ['name', 'updated_at'], ['status' => 'active', 'ORDER' => ['updated_at' => 'DESC'], 'LIMIT' => 200]) ?: [],
        ]);
    }

    public function plans()
    {
        $this->rememberPlan();
        // "Start with Gold" goes where this person's next step is, carrying the choice.
        $uid = $this->userId();
        $n = $uid ? (new Names($this->db))->of($uid) : null;
        $next = $n && Trial::phase($n, (new Subscriptions($this->db))->current($uid)) !== 'none' && $n['preview_id'] ? '/sites/me' : '/sites/start';
        $this->page('sites/plans', ['title' => 'Plans · SilverQueen Premium', 'next' => $next] + $this->founding());
    }

    public function start()
    {
        $this->rememberPlan();
        $uid = $this->userId();
        $n = $uid ? (new Names($this->db))->of($uid) : null;
        $held = $n && Trial::phase($n, (new Subscriptions($this->db))->current($uid)) !== 'none' ? $n : null;
        $this->page('sites/start', ['title' => 'Start it now · SilverQueen Premium', 'open' => $this->canBuild(),
            'held' => $held, 'suggest' => $uid && Members::isMember($uid) ? Members::photos($uid) : [], 'plan' => Config::plan((string) ($_SESSION['sites_plan'] ?? '')) ? (string) $_SESSION['sites_plan'] : null,
            'csrf_token' => generateCsrfToken(true)] + $this->founding());
    }

    /** A member the owner lets build previews without the one-a-day limit (SITES_EXEMPT_IDENTITIES, e.g. "10" for himself). */
    /**
     * Signed in and nothing published yet: no daily limit (owner, 2026-10-24: "You
     * should only do that when they have actually published something"). Only a
     * visitor who is not signed in is held to one free preview a day.
     */
    private function stillBuilding(): bool
    {
        $uid = $this->userId();
        if (!$uid) {
            return false;
        }
        $n = (new Names($this->db))->of($uid);

        return !$n || !Publisher::isLive((string) $n['name']);
    }

    private function exemptMember(): bool
    {
        $identity = Members::identity($this->userId());
        $list = array_filter(array_map('intval', explode(',', (string) (\Ginto\Support\Env::get('SITES_EXEMPT_IDENTITIES', '') ?? ''))));

        return $identity !== null && (in_array($identity, $list, true) || isset(Config::COMPLIMENTARY[$identity]));
    }

    /**
     * Datu Gyud himself: the member who is comchain identity 10, or his own
     * silverqueen.pro accounts (1 admin, 2 oliverbob) when no member link is in use.
     * Only he gets the payment simulation (owner, 2026-10-24).
     */
    private function isOwner(): bool
    {
        $uid = $this->userId();
        if (Members::isMember($uid)) {
            return Members::identity($uid) === 10;
        }

        return in_array($uid, [1, 2], true);
    }

    /**
     * Who may press "Simulate payment success": the owner, and the members he
     * named (Config::SIMULATORS). They see the real QR like anyone; the button
     * only plays the paid moment, nothing is saved.
     */
    private function canSimulate(): bool
    {
        $uid = $this->userId();

        return $this->isOwner() || (Members::isMember($uid) && in_array(Members::identity($uid), Config::SIMULATORS, true));
    }

    /** GET /sites/me/sim.js -- the payment simulation; only those who may simulate are ever sent it. */
    public function simScript()
    {
        if (!$this->canSimulate()) {
            http_response_code(404);

            return;
        }
        header('Content-Type: application/javascript; charset=utf-8');
        header('Cache-Control: private, no-store');
        readfile(ROOT_PATH . '/src/Views/sites/owner-sim.js');
    }

    /** The plan someone picked on the card or the Plans page, kept until checkout so they never pick twice. */
    private function rememberPlan(): void
    {
        $p = (string) ($_GET['plan'] ?? '');
        if (Config::plan($p) !== null) {
            $_SESSION['sites_plan'] = $p;
            if ($this->userId()) {
                $this->db->update('sites_names', ['plan_choice' => $p], ['user_id' => $this->userId()]);
            }
        }
    }

    /** POST /sites/start (multipart: photos[], description, csrf_token) */
    public function create()
    {
        if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
            return $this->json(['ok' => false, 'error' => 'This page has expired. Reload it and try again.'], 419);
        }
        if (!$this->canBuild()) {
            return $this->json(['ok' => false, 'error' => 'SilverQueen Premium opens soon.'], 403);
        }
        $held = $this->userId() ? (new Names($this->db))->of($this->userId()) : null;
        if (!Trial::canAdd($held, $this->userId() ? (new Subscriptions($this->db))->current($this->userId()) : null)) {
            return $this->json(['ok' => false, 'error' => 'Your site is waiting for its plan. Choose one to add photos or rebuild; until then you can edit its words.'], 402);
        }
        $files = $this->uploaded('photos');
        // Picks from their own SilverQueen photos: only URLs that are in their own list.
        $picked = array_values(array_unique(array_filter((array) ($_POST['picks'] ?? []), 'is_string')));
        $own = $picked && Members::isMember($this->userId()) ? Members::photos($this->userId()) : [];
        $picked = array_values(array_intersect($picked, array_column($own, 'url')));
        // Which picks are videos: their place in the site -> the video to play.
        $videoOf = array_column(array_filter($own, static fn ($o) => !empty($o['video'])), 'video', 'url');
        $media = [];
        foreach ($picked as $i => $u) {
            if (isset($videoOf[$u])) {
                $media[$i] = $videoOf[$u];
            }
        }
        if (count($files) + count($picked) > Photos::MAX_PER_SITE) {
            return $this->json(['ok' => false, 'error' => 'Up to ' . Photos::MAX_PER_SITE . ' photos for a preview.'], 422);
        }
        try {
            $photos = new Photos($this->db);
            $shas = array_merge(array_map(static fn ($u) => $photos->ingestUrl($u), $picked), array_map(static fn ($f) => $photos->ingest($f), $files));
            $p = (new Previews($this->db))->create((string) ($_POST['description'] ?? ''), $shas, $this->deviceId(), $this->clientIp(),
                $this->userId() ?: null, $this->isAdmin() || $this->exemptMember() || $this->stillBuilding(), $media, (string) ($_POST['template'] ?? ''));
        } catch (LimitReached | \InvalidArgumentException $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
        Previews::spawn($p['id']);
        // A held name's site goes to the dashboard to be reviewed and paid; anyone else sees the open preview.
        $next = $held && Trial::phase($held, null) !== 'none' ? '/sites/me' : '/sites/p/' . $p['token'];

        return $this->json(['ok' => true, 'token' => $p['token'], 'url' => $next]);
    }

    public function status($token)
    {
        $row = (new Previews($this->db))->byToken((string) $token);
        if (!$row) {
            return $this->json(['ok' => false, 'status' => 'gone'], 404);
        }

        return $this->json(['ok' => true, 'status' => $row['status'], 'error' => $row['error']]);
    }

    public function preview($token)
    {
        $row = (new Previews($this->db))->byToken((string) $token);
        header('X-Robots-Tag: noindex, nofollow');
        if (!$row) {
            http_response_code(404);

            return $this->page('sites/gone', ['title' => 'Preview ended']);
        }
        if ($row['status'] !== 'ready') {
            return $this->page('sites/building', ['title' => 'Building your site…', 'token' => $row['token'], 'status' => $row['status'], 'error' => $row['error']]);
        }
        $site  = json_decode((string) $row['site'], true);
        $shas  = json_decode((string) $row['photos'], true) ?: [];
        $hours = max(1, (int) ceil((strtotime((string) $row['expires_at']) - time()) / 3600));
        header('Content-Type: text/html; charset=utf-8');
        echo Renderer::html($site['plan'], array_map(static fn ($s) => '/sites/photo/' . $s, $shas),
            ['preview_hours' => $hours, 'claim_url' => '/sites/start?claim=' . $row['token'] . '#claim',
             'template' => $row['template'] ?? null, 'videos' => json_decode((string) ($row['media'] ?? ''), true) ?: []]);
    }

    public function photo($sha)
    {
        $sha = (string) $sha;
        if (preg_match('/^[0-9a-f]{64}$/', $sha) !== 1 || !is_file(Photos::path($sha))) {
            http_response_code(404);

            return;
        }
        header('Content-Type: image/jpeg');
        header('Cache-Control: public, max-age=31536000, immutable');
        readfile(Photos::path($sha));
    }

    /** GET /sites/name?n= */
    public function name()
    {
        return $this->json((new Names($this->db))->check((string) ($_GET['n'] ?? ''), $this->userId()));
    }

    /** POST /sites/claim {name, token, csrf_token} -- needs an account; the plan and payment come next. */
    public function claim()
    {
        $in = json_decode((string) file_get_contents('php://input'), true) ?: $_POST;
        if (!validateCsrfToken($in['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) {
            return $this->json(['ok' => false, 'reason' => 'This page has expired. Reload it and try again.'], 419);
        }
        if (!$this->userId()) {
            return $this->json(['ok' => false, 'login' => true, 'reason' => 'Sign in or create your SilverQueen account to claim it.'], 401);
        }
        $prev = !empty($in['token']) ? (new Previews($this->db))->byToken((string) $in['token']) : null;
        $r = (new Names($this->db))->claim((string) ($in['name'] ?? ''), $this->userId(), $prev ? (int) $prev['id'] : null);
        if (!empty($r['ok']) && empty($r['mine'])) {
            (new Trial($this->db))->start($this->userId(), (string) ($_SESSION['sites_member_name'] ?? ''));
            (new Subscriptions($this->db))->complimentary($this->userId());
        }

        return $this->json($r + ['next' => $r['ok'] ? '/sites/me' : null], $r['ok'] ? 200 : 422);
    }

    /** GET /sites/me -- the seller's own: review, plan and pay, renewal, credits. */
    public function me()
    {
        $this->rememberPlan();
        $uid = $this->userId();
        if (!$uid) {
            header('Location: /login?next=' . rawurlencode('/sites/me'));

            return;
        }
        (new Subscriptions($this->db))->complimentary($uid);
        $name  = (new Names($this->db))->of($uid);
        $draft = $this->draft($uid);
        $sub   = (new Subscriptions($this->db))->current($uid);
        // The owner's simulation of a paid checkout: the page as it will be, drawn
        // from the open checkout in memory only. Nothing is saved, nothing is published.
        $sim = $this->canSimulate() && isset($_GET['sim']) && $sub && $sub['status'] === 'pending';
        if ($sim) {
            $sub  = ['status' => 'active', 'period_start' => date('Y-m-d H:i:s'), 'period_end' => date('Y-m-d H:i:s', strtotime('+1 month'))] + $sub;
            $name = ['status' => 'active'] + (array) $name;
        }
        $this->page('sites/me', ['title' => 'My Site · SilverQueen Premium', 'nav' => 'me', 'name' => $name, 'draft' => $draft,
            'owner' => $this->canSimulate(), 'simulated' => $sim,
            'test_php' => Config::testPrice(Members::identity($uid)),
            'phase' => Trial::phase($name, $sub),
            'pending' => $sub && $sub['status'] === 'pending' ? $sub : null,
            'just_paid' => isset($_GET['paid']),
            'plan_pick' => (string) (($name['plan_choice'] ?? null) ?: ($_SESSION['sites_plan'] ?? '')),
            'sub' => $sub, 'credits' => $sub && $sub['status'] === 'active' ? (new Meter($this->db))->status($uid, (string) $sub['plan']) : null,
            'live' => $name && Publisher::isLive((string) $name['name']), 'csrf_token' => generateCsrfToken()] + $this->founding());
    }

    /** POST /sites/me/review -- the seller's own words over the AI's, required before anything goes live. */
    public function review()
    {
        $uid = $this->userId();
        $in  = json_decode((string) file_get_contents('php://input'), true) ?: [];
        if (!$uid || !validateCsrfToken($in['csrf_token'] ?? '')) {
            return $this->json(['ok' => false, 'error' => 'Sign in again and retry.'], 403);
        }
        $draft = $this->draft($uid);
        if (!$draft) {
            return $this->json(['ok' => false, 'error' => 'Build a preview and claim your name first.'], 422);
        }
        $site = $draft['site'];
        $t = static fn ($v, int $max): string => mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $v)), 0, $max);
        $site['plan']['brand']['name']    = $t($in['brand'] ?? $site['plan']['brand']['name'], 60) ?: $site['plan']['brand']['name'];
        $site['plan']['brand']['tagline'] = $t($in['tagline'] ?? '', 120);
        $site['plan']['hero']['headline'] = $t($in['headline'] ?? $site['plan']['hero']['headline'], 90);
        $site['plan']['about']['body']    = mb_substr(trim((string) ($in['about'] ?? $site['plan']['about']['body'])), 0, 900);
        foreach ((array) ($in['products'] ?? []) as $i => $p) {
            if (isset($site['plan']['products'][$i]) && is_array($p)) {
                $site['plan']['products'][$i]['name'] = $t($p['name'] ?? '', 80);
                $site['plan']['products'][$i]['description'] = $t($p['description'] ?? '', 320);
            }
        }
        $contact = trim((string) ($in['contact'] ?? ''));
        if ($contact !== '' && preg_match('#^(https://\S+|tel:\+?[0-9 ]{6,20}|mailto:\S+@\S+)$#i', $contact) !== 1) {
            return $this->json(['ok' => false, 'error' => 'The contact must start with https://, tel: or mailto:'], 422);
        }
        $site['contact'] = $contact;
        $site['reviewed_at'] = date('c');
        $this->db->update('sites_previews', ['site' => json_encode($site)], ['id' => $draft['id']]);

        $live = (new Subscriptions($this->db))->current($uid);
        $republished = null;
        if ($live && $live['status'] === 'active') {
            $republished = (new Publisher($this->db))->publish($uid)['ok'];
        }

        return $this->json(['ok' => true, 'republished' => $republished]);
    }

    /**
     * GET /sites/me/qr.png -- the QR they are paying with, served from here so it
     * shows and downloads on any phone (owner, 2026-10-24: "always, at all times
     * show a download qr button"). ?download=1 saves it as a file. 410 once paid.
     */
    public function qr()
    {
        $uid = $this->userId();
        $sub = $uid ? $this->db->get('sites_subscriptions', '*', ['user_id' => $uid, 'status' => 'pending', 'ORDER' => ['id' => 'DESC']]) : null;
        if (!$sub) {
            http_response_code(404);

            return;
        }
        $st = (new \Ginto\Handlers\PayMongoHandler())->getPaymentIntentStatus((string) $sub['payment_ref']);
        if (($st['status'] ?? '') === 'succeeded') {
            http_response_code(410);

            return;
        }
        $img = (string) ($st['qr_image'] ?? '');
        $bytes = null;
        if (preg_match('#^data:image/(png|jpeg);base64,(.+)$#s', $img, $m) === 1) {
            $bytes = base64_decode($m[2], true);
        } elseif (str_starts_with($img, 'https://')) {
            $ch = curl_init($img);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_FOLLOWLOCATION => false]);
            $bytes = curl_exec($ch);
            $ok = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200;
            curl_close($ch);
            $bytes = $ok ? $bytes : null;
        }
        if (!is_string($bytes) || $bytes === '' || @getimagesizefromstring($bytes) === false) {
            http_response_code(502);

            return;
        }
        $info = getimagesizefromstring($bytes);
        header('Content-Type: ' . $info['mime']);
        header('Cache-Control: private, no-store');
        if (isset($_GET['download'])) {
            header('Content-Disposition: attachment; filename="SilverQueen-Premium-' . preg_replace('/[^a-z0-9-]/', '', (string) $sub['name'])
                . '-QR-PHP' . (int) $sub['price_php'] . '.' . ($info['mime'] === 'image/png' ? 'png' : 'jpg') . '"');
        }
        echo $bytes;
    }

    /** POST /sites/me/plan {plan} -- the plan they picked, kept with their name (the same one everywhere). */
    public function planChoice()
    {
        $uid = $this->userId();
        $in = json_decode((string) file_get_contents('php://input'), true) ?: [];
        $plan = (string) ($in['plan'] ?? '');
        if (!$uid || !validateCsrfToken($in['csrf_token'] ?? '') || Config::plan($plan) === null) {
            return $this->json(['ok' => false], 422);
        }
        $_SESSION['sites_plan'] = $plan;
        $this->db->update('sites_names', ['plan_choice' => $plan], ['user_id' => $uid]);

        return $this->json(['ok' => true]);
    }

    /** POST /sites/me/checkout {plan} -- a QR Ph code for this month. */
    public function checkout()
    {
        $uid = $this->userId();
        $in  = json_decode((string) file_get_contents('php://input'), true) ?: [];
        if (!$uid || !validateCsrfToken($in['csrf_token'] ?? '')) {
            return $this->json(['ok' => false, 'error' => 'Sign in again and retry.'], 403);
        }
        // The same door as building: open to the public, an admin, or a member who came in from the app's card.
        if (!$this->canBuild()) {
            return $this->json(['ok' => false, 'error' => 'SilverQueen Premium opens soon.'], 403);
        }
        $draft = $this->draft($uid);
        if (!$draft || empty($draft['site']['reviewed_at'])) {
            return $this->json(['ok' => false, 'error' => 'Review your site first: nothing goes live until you have.'], 422);
        }
        $u = Members::isMember($uid)
            ? ['email' => 'member' . Members::identity($uid) . '@silverqueen.pro', 'firstname' => (string) ($_SESSION['sites_member_name'] ?? 'SilverQueen member')]
            : ($this->db->get('users', ['email', 'firstname', 'lastname', 'username'], ['id' => $uid]) ?: []);
        $r = (new Subscriptions($this->db))->checkout($uid, (string) ($in['plan'] ?? ''), (string) ($u['email'] ?? ''),
            trim(($u['firstname'] ?? '') . ' ' . ($u['lastname'] ?? '')) ?: (string) ($u['username'] ?? ''));

        return $this->json($r, $r['ok'] ? 200 : 422);
    }

    /** GET /sites/me/checkout -- paid yet? (polled by the QR screen) */
    public function checkoutStatus()
    {
        $uid = $this->userId();
        if (!$uid) {
            return $this->json(['status' => 'none'], 401);
        }

        return $this->json((new Subscriptions($this->db))->confirm($uid));
    }

    /**
     * POST /sites/surprise -- SilverQueen AI picks the posts that look like
     * products (owner, 2026-10-24: "auto-generation of choices or 'surprise me'
     * button but instruct the user that it is auto-generated with AI").
     * Answers indexes into the member's own suggestions.
     */
    public function surprise()
    {
        $uid = $this->userId();
        $own = Members::isMember($uid) ? Members::photos($uid) : [];
        if ($own === []) {
            return $this->json(['ok' => false, 'error' => 'There are no SilverQueen photos to choose from yet.'], 422);
        }
        $lines = [];
        foreach ($own as $i => $o) {
            $lines[] = $i . ' [' . $o['kind'] . '] ' . ($o['caption'] !== '' ? mb_substr($o['caption'], 0, 120) : '(no caption)');
        }
        $pick = [];
        try {
            $r = (new Ai(new Meter($this->db)))->chat('description', [
                ['role' => 'system', 'content' => self::SURPRISE_PROMPT],
                ['role' => 'user', 'content' => implode("\n", $lines)],
            ], ['user_id' => $uid, 'json' => true]);
            $j = json_decode($r['content'], true);
            $pick = array_values(array_filter(array_map('intval', (array) ($j['pick'] ?? [])), static fn ($i) => isset($own[$i])));
        } catch (\Throwable $e) {
            $pick = [];
        }
        $byAi = $pick !== [];
        if (!$byAi) {
            // AI unavailable: the most-loved ones with words, which is the order they came in.
            foreach ($own as $i => $o) {
                if ($o['caption'] !== '' && count($pick) < 6) {
                    $pick[] = $i;
                }
            }
        }

        return $this->json(['ok' => true, 'pick' => array_slice(array_unique($pick), 0, 8), 'by_ai' => $byAi]);
    }

    /**
     * POST /sites/describe {picks, name} -- the first AI step: what was picked,
     * summarised into the shop description the builder works from (owner,
     * 2026-10-24: "enable auto-generated prompt with AI"). The member edits it.
     */
    public function describe()
    {
        $uid = $this->userId();
        $in = json_decode((string) file_get_contents('php://input'), true) ?: [];
        $own = Members::isMember($uid) ? Members::photos($uid) : [];
        $want = array_flip(array_filter((array) ($in['picks'] ?? []), 'is_string'));
        $caps = [];
        foreach ($own as $o) {
            if (isset($want[$o['url']]) && $o['caption'] !== '') {
                $caps[] = '- [' . $o['kind'] . '] ' . $o['caption'];
            }
        }
        $name = mb_substr(trim((string) ($in['name'] ?? '')), 0, 60);
        if ($caps === [] && $name === '') {
            return $this->json(['ok' => false, 'error' => 'Pick a few photos or videos first.'], 422);
        }
        try {
            $r = (new Ai(new Meter($this->db)))->chat('description', [
                ['role' => 'system', 'content' => self::DESCRIBE_PROMPT],
                ['role' => 'user', 'content' => 'Shop name: ' . ($name ?: '(not given)') . "\nThe seller's own posts:\n" . implode("\n", array_slice($caps, 0, 12))],
            ], ['user_id' => $uid]);
            $text = trim(preg_replace('/\s+/u', ' ', strip_tags($r['content'])));
        } catch (\Throwable $e) {
            return $this->json(['ok' => false, 'error' => 'SilverQueen AI is busy right now. Your own draft is still there; try again shortly.'], 503);
        }

        return $this->json(['ok' => $text !== '', 'text' => mb_substr($text, 0, 1200)]);
    }

    /** POST /sites/me/template {id} -- the same site, drawn in another of the twelve. Free: no AI. */
    public function template()
    {
        $uid = $this->userId();
        $in = json_decode((string) file_get_contents('php://input'), true) ?: [];
        if (!$uid || !validateCsrfToken($in['csrf_token'] ?? '')) {
            return $this->json(['ok' => false, 'error' => 'Sign in again and retry.'], 403);
        }
        $draft = $this->draft($uid);
        if (!$draft) {
            return $this->json(['ok' => false, 'error' => 'Build your site first.'], 422);
        }
        $id = Templates::get((string) ($in['id'] ?? ''))['id'];
        $this->db->update('sites_previews', ['template' => $id], ['id' => $draft['id']]);
        $n = (new Names($this->db))->of($uid);
        $sub = (new Subscriptions($this->db))->current($uid);
        $phase = Trial::phase($n, $sub);
        $pub = $phase === 'live' ? (new Publisher($this->db))->publish($uid) : ($phase === 'trial' ? (new Publisher($this->db))->publishTrial($uid) : ['ok' => false]);

        return $this->json(['ok' => true, 'template' => $id, 'name' => Templates::ALL[$id]['name'], 'republished' => !empty($pub['ok'])]);
    }

    private const SURPRISE_PROMPT = 'You help a SilverQueen member pick which of their own posts to show on their online shop. '
        . 'Each line is: index [kind] caption. Choose up to 8 that most likely show a product, a service, their work, or their '
        . 'business; skip greetings, memes, quotes and personal life. Answer ONLY with JSON: {"pick":[indexes]}.';

    private const DESCRIBE_PROMPT = 'You write the shop description a site builder will work from, for a Filipino seller on '
        . 'SilverQueen, using ONLY what their own posts say. In 60 to 110 words say: what they sell or offer, who it is for, '
        . 'what makes it theirs, and the tone of the shop. Write in the language the posts mostly use. Never invent prices, '
        . 'stock, awards, reviews or promises. Plain text, no lists, no headings.';

    /** The account's claimed preview, as its draft site. */
    private function draft(int $uid): ?array
    {
        $n = (new Names($this->db))->of($uid);
        if (!$n || !$n['preview_id']) {
            return null;
        }
        $p = $this->db->get('sites_previews', ['id', 'site', 'photos', 'status', 'template'], ['id' => (int) $n['preview_id'], 'user_id' => $uid]);
        if (!$p || $p['status'] !== 'ready') {
            return null;
        }
        $site = json_decode((string) $p['site'], true);

        return is_array($site) ? ['id' => (int) $p['id'], 'site' => $site, 'photos' => json_decode((string) $p['photos'], true) ?: [],
            'template' => Templates::get($p['template'] ?? null)['id']] : null;
    }

    // ---------------------------------------------------------------------

    private function founding(): array
    {
        $filled = (int) $this->db->count('sites_subscriptions', ['founding' => 1, 'status' => 'active']);

        return ['founding_filled' => $filled, 'founding_cap' => Config::foundingCap()];
    }

    private function canBuild(): bool
    {
        return Config::isPublic() || $this->isAdmin() || Members::isMember($this->userId());
    }

    /**
     * Who this is for Sites. A SilverQueen member who came in by the feed card's
     * link comes FIRST: the link is the most recent and most explicit "this is
     * me", and the same phone's browser is often still signed in to a
     * silverqueen.pro account, which would otherwise take over (owner's phone,
     * 2026-10-24: the held name vanished and the page said "Opening soon").
     */
    private function userId(): int
    {
        return (int) ($_SESSION['sites_uid'] ?? 0) ?: (int) ($_SESSION['user_id'] ?? 0);
    }

    /** GET /sites/enter?t= -- the feed card's one-time way in, for the Sites dashboard only. */
    public function enter()
    {
        $m = Members::consume((string) ($_GET['t'] ?? ''));
        if (!$m) {
            return $this->page('sites/gone', ['title' => 'That link has expired']);
        }
        session_regenerate_id(true);
        $_SESSION['sites_uid'] = $m['uid'];
        $_SESSION['sites_member_name'] = $m['name'];
        $this->rememberPlan();
        header('Location: ' . $m['to']);
    }

    private function isAdmin(): bool
    {
        if (!$this->userId()) {
            return false;
        }
        $u = $this->db->get('users', ['role_id'], ['id' => $this->userId()]);

        return $u && in_array((int) $u['role_id'], [1, 2], true);
    }

    /** One per browser, kept a year: the "device" half of one free preview a day. */
    private function deviceId(): string
    {
        $id = (string) ($_COOKIE['sq_dev'] ?? '');
        if (preg_match('/^[0-9a-f]{32}$/', $id) !== 1) {
            $id = bin2hex(random_bytes(16));
            setcookie('sq_dev', $id, ['expires' => time() + 31536000, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
        }

        return $id;
    }

    /**
     * The visitor's address. Behind Caddy every request comes from 127.0.0.1, so
     * the forwarded address is used -- and only when the request did come from
     * the proxy on this machine.
     */
    private function clientIp(): string
    {
        $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        if (in_array($remote, ['127.0.0.1', '::1'], true) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $hops = array_map('trim', explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']));

            return (string) end($hops);
        }

        return $remote;
    }

    /** @return list<string> temp paths of the files sent as name[] */
    private function uploaded(string $field): array
    {
        $f = $_FILES[$field] ?? null;
        if (!$f || !is_array($f['tmp_name'])) {
            return [];
        }
        $out = [];
        foreach ($f['tmp_name'] as $i => $tmp) {
            if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && is_uploaded_file($tmp)) {
                $out[] = $tmp;
            }
        }

        return $out;
    }

    private function page(string $view, array $data)
    {
        $this->view($view, $data);
    }

    private function json(array $data, int $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data);
    }
}
