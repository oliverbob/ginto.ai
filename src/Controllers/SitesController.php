<?php

namespace Ginto\Controllers;

use Ginto\Core\Database;
use Ginto\Sites\Config;
use Ginto\Sites\LimitReached;
use Ginto\Sites\Names;
use Ginto\Sites\Photos;
use Ginto\Sites\Previews;
use Ginto\Sites\Renderer;

/**
 * SilverQueen Pages, Sites and Stores -- the public side (owner, 2026-10-24).
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
        $this->page('sites/directory', ['title' => 'SilverQueen Sites Directory'] + $this->founding() + [
            'sites' => $this->db->select('sites_names', ['name', 'updated_at'], ['status' => 'active', 'ORDER' => ['updated_at' => 'DESC'], 'LIMIT' => 200]) ?: [],
        ]);
    }

    public function plans()
    {
        $this->page('sites/plans', ['title' => 'Plans · SilverQueen Sites'] + $this->founding());
    }

    public function start()
    {
        $this->page('sites/start', ['title' => 'Start it now · SilverQueen Sites', 'open' => $this->canBuild(),
            'csrf_token' => generateCsrfToken(true)] + $this->founding());
    }

    /** POST /sites/start (multipart: photos[], description, csrf_token) */
    public function create()
    {
        if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
            return $this->json(['ok' => false, 'error' => 'This page has expired. Reload it and try again.'], 419);
        }
        if (!$this->canBuild()) {
            return $this->json(['ok' => false, 'error' => 'SilverQueen Sites opens soon.'], 403);
        }
        $files = $this->uploaded('photos');
        if (count($files) > Photos::MAX_PER_SITE) {
            return $this->json(['ok' => false, 'error' => 'Up to ' . Photos::MAX_PER_SITE . ' photos for a preview.'], 422);
        }
        try {
            $photos = new Photos($this->db);
            $shas = array_map(static fn ($f) => $photos->ingest($f), $files);
            $p = (new Previews($this->db))->create((string) ($_POST['description'] ?? ''), $shas, $this->deviceId(), $this->clientIp(),
                $this->userId() ?: null, $this->isAdmin());
        } catch (LimitReached | \InvalidArgumentException $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
        Previews::spawn($p['id']);

        return $this->json(['ok' => true, 'token' => $p['token'], 'url' => '/sites/p/' . $p['token']]);
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
            ['preview_hours' => $hours, 'claim_url' => '/sites/start?claim=' . $row['token'] . '#claim']);
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

        return $this->json($r + ['next' => $r['ok'] ? '/sites/plans?name=' . rawurlencode($r['name']) : null], $r['ok'] ? 200 : 422);
    }

    // ---------------------------------------------------------------------

    private function founding(): array
    {
        $filled = (int) $this->db->count('sites_subscriptions', ['founding' => 1, 'status' => 'active']);

        return ['founding_filled' => $filled, 'founding_cap' => Config::foundingCap()];
    }

    private function canBuild(): bool
    {
        return Config::isPublic() || $this->isAdmin();
    }

    private function userId(): int
    {
        return (int) ($_SESSION['user_id'] ?? 0);
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
