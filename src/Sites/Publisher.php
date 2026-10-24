<?php

declare(strict_types=1);

namespace Ginto\Sites;

use Ginto\Support\Env;

/**
 * A paid site, put live at name.silverqueen.pro.
 *
 * The page is rendered here once (Renderer), with the seller's reviewed plan,
 * and sent signed to the Sites slice on the production server (owner,
 * 2026-10-24: "use the actual slices that are available ... half the size of
 * the host"), which serves it as plain files. The marker file in
 * /var/lib/ginto/sites-live tells this host's Caddy to send name.silverqueen.pro
 * there instead of to the tunnels, and tells the certificate check to approve it.
 */
final class Publisher
{
    public const LIVE_DIR = '/var/lib/ginto/sites-live';
    private const HOST_URL = 'https://sq.silverqueen.pro/api/v1/internal/sites';

    public function __construct(private $db)
    {
    }

    public static function isLive(string $name): bool
    {
        return preg_match(Config::NAME_RE, $name) === 1 && is_file(self::LIVE_DIR . '/' . $name);
    }

    /** A paid site, live for good. @return array{ok:bool, url?:string, error?:string} */
    public function publish(int $userId): array
    {
        $n = $this->db->get('sites_names', '*', ['user_id' => $userId]);
        $sub = $this->db->get('sites_subscriptions', '*', ['user_id' => $userId, 'status' => 'active', 'ORDER' => ['id' => 'DESC']]);
        if (!$n || $n['status'] !== 'active' || !$sub) {
            return ['ok' => false, 'error' => 'There is no paid site to publish.'];
        }
        $plan = Config::plan((string) $sub['plan']) ?? Config::PLANS['silver'];

        return $this->push($n, (int) $plan['storage_gb'] * 1024, ['founding' => (bool) $sub['founding']]);
    }

    /**
     * The free trial: the held name live for its first hours, with a banner that
     * says so and counts down (owner, 2026-10-24: "they need to see the preview
     * (as 5 hour free trial)"). Nothing is published once the trial is over.
     */
    public function publishTrial(int $userId): array
    {
        $n = $this->db->get('sites_names', '*', ['user_id' => $userId]);
        if (!$n || $n['status'] !== 'held' || empty($n['trial_until']) || strtotime((string) $n['trial_until']) <= time()) {
            return ['ok' => false, 'error' => 'The free trial is over. Choose a plan to go live.'];
        }
        $left = max(1, (int) ceil((strtotime((string) $n['trial_until']) - time()) / 3600));

        return $this->push($n, 200, ['trial_hours' => $left, 'trial_until' => strtotime((string) $n['trial_until']),
            'claim_url' => 'https://silverqueen.pro/sites/me']);
    }

    /** Render the name's reviewed draft and send it to the Sites slice. */
    private function push(array $n, int $quotaMb, array $opts): array
    {
        $prev = $n['preview_id'] ? $this->db->get('sites_previews', '*', ['id' => (int) $n['preview_id']]) : null;
        $site = $prev && $prev['site'] ? json_decode((string) $prev['site'], true) : null;
        if (!is_array($site) || empty($site['plan'])) {
            return ['ok' => false, 'error' => 'Build a preview first, then publish it.'];
        }
        $shas = json_decode((string) $prev['photos'], true) ?: [];

        $files = [];
        $urls  = [];
        foreach ($shas as $i => $sha) {
            if (preg_match('/^[0-9a-f]{64}$/', (string) $sha) !== 1 || !is_file(Photos::path($sha))) {
                continue;
            }
            $path = 'p/' . substr($sha, 0, 16) . '.jpg';
            $files[$path] = base64_encode((string) file_get_contents(Photos::path($sha)));
            $urls[$i] = '/' . $path;
        }
        $contact = (string) ($site['contact'] ?? '');
        $html = Renderer::html($site['plan'], array_values(array_replace(array_fill(0, count($shas), ''), $urls)),
            $opts + ['contact_url' => $contact !== '' ? $contact : '#shop', 'template' => $prev['template'] ?? null,
                     'videos' => json_decode((string) ($prev['media'] ?? ''), true) ?: [],
                     // Their Page and their Store, carried by the site (owner, 2026-10-24).
                     'page' => Members::page((int) $n['user_id'])]);
        $files['index.html'] = base64_encode($html);
        $files['robots.txt'] = base64_encode(isset($opts['trial_hours']) ? "User-agent: *\nDisallow: /\n" : "User-agent: *\nAllow: /\n");

        $r = $this->call('publish', ['name' => $n['name'], 'files' => $files, 'quota_mb' => $quotaMb]);
        if (empty($r['ok'])) {
            Subscriptions::log('PUBLISH FAILED ' . $n['name'] . ': ' . ($r['error'] ?? '?'));

            return ['ok' => false, 'error' => (string) ($r['error'] ?? 'The site could not be published. We will retry.')];
        }
        if (!is_dir(self::LIVE_DIR)) {
            @mkdir(self::LIVE_DIR, 0755, true);
        }
        @file_put_contents(self::LIVE_DIR . '/' . $n['name'], date('c'));
        Subscriptions::log((isset($opts['trial_hours']) ? 'TRIAL ' : 'PUBLISHED ') . $n['name'] . ' (' . round(($r['bytes'] ?? 0) / 1048576, 1) . ' MB)');

        return ['ok' => true, 'url' => 'https://' . $n['name'] . '.silverqueen.pro'];
    }

    public function unpublish(string $name): void
    {
        if (preg_match(Config::NAME_RE, $name) !== 1) {
            return;
        }
        @unlink(self::LIVE_DIR . '/' . $name);
        $this->call('unpublish', ['name' => $name]);
        Subscriptions::log('UNPUBLISHED ' . $name);
    }

    /** Signed the way the production server checks every relay (its RelaySignature): HMAC of "ts.body" with SQ_RELAY_SECRET. */
    private function call(string $action, array $data): array
    {
        $secret = (string) (Env::get('SQ_RELAY_SECRET', '') ?? '');
        if ($secret === '') {
            return ['ok' => false, 'error' => 'SQ_RELAY_SECRET is not set.'];
        }
        $body  = (string) json_encode(['action' => $action] + $data, JSON_UNESCAPED_SLASHES);
        $ts    = (string) time();
        $sig   = hash_hmac('sha256', $ts . '.' . $body, $secret);
        $ch = curl_init(self::HOST_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-SQ-Timestamp: ' . $ts, 'X-SQ-Signature: ' . $sig],
            CURLOPT_POSTFIELDS => $body,
        ]);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $out = is_string($raw) ? json_decode($raw, true) : null;
        // The production API wraps every answer: {"success":..., "data":{...}, "error":{...}}.
        if (is_array($out) && array_key_exists('data', $out) && array_key_exists('success', $out)) {
            $out = is_array($out['data']) ? $out['data'] : ['ok' => false, 'error' => (string) ($out['error']['message'] ?? 'The Sites host refused.')];
        }

        return is_array($out) ? $out : ['ok' => false, 'error' => 'The Sites host did not answer (' . $status . ').'];
    }
}
