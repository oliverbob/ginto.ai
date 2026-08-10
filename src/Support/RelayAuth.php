<?php
namespace Ginto\Support;

use Ginto\Core\Database;

/**
 * Turns a relay bearer token into "which ginto.ai member is this, and what may
 * they see".
 *
 * The caller is a machine, not a person. SilverQueen sits behind the gntl tunnel
 * on sq.ginto.ai and reaches this host over HTTPS; nobody logs in on the way. It
 * names the member it is acting for by putting their `users.username` in the
 * `sub` claim of a token signed with a secret both sides hold, and this class is
 * the other half of that handshake.
 *
 * Three checks, in this order, because each one is cheaper than the next:
 *
 *   1. the signature and expiry (Jwt::decode) — no database work for a forgery;
 *   2. the jti has not been seen before, so a captured token cannot be replayed
 *      for the rest of its lifetime;
 *   3. the username resolves to a live account with an active subscription —
 *      the same gate AcademyController applies to /academy/bot, so a member who
 *      lapses loses the API at the same moment they lose the web page.
 *
 * The username is matched against `username` only, never email or phone. The
 * general-purpose User::findByCredentials() accepts all three and is right for a
 * login box where a human types whatever they remember; here it would mean a
 * token could name a user three different ways, and one account whose email
 * happens to equal another account's username would be an impersonation route.
 *
 * Worth being clear about the trust boundary: whoever holds the shared secret
 * can mint a token for ANY username. That is acceptable when the holder is your
 * own service, and it is why RELAY_ALLOWED_USERS exists — set it and a stolen
 * secret still only reaches the accounts you listed.
 */
class RelayAuth
{
    /** Rejects a token minted for some other service that happens to share the secret. */
    public const AUDIENCE = 'ginto-relay';

    /** Longest token lifetime accepted, and therefore how long a jti must be remembered. */
    private const MAX_TTL = 900;

    /**
     * Authenticate the current request.
     *
     * @return array{user:array<string,mixed>,username:string,plan:string,is_pro:bool,claims:array<string,mixed>}
     * @throws RelayAuthError
     */
    public static function authenticate(): array
    {
        $secrets = self::secrets();
        if ($secrets === []) {
            // Misconfiguration, not a client error — say so in the log, not the response.
            error_log('RelayAuth: RELAY_JWT_SECRET is not set; refusing all relay requests.');
            throw new RelayAuthError('Relay authentication is not configured.', 503);
        }

        $token = self::bearerToken();
        if ($token === null) {
            throw new RelayAuthError('Missing bearer token.', 401);
        }

        $claims = null;
        $last   = null;
        foreach ($secrets as $label => $secret) {
            try {
                $claims = Jwt::decode($token, $secret, self::AUDIENCE);
                if ($label === 'previous') {
                    // Rotation is only finished once this stops appearing. Leaving
                    // the old secret in place indefinitely doubles the number of
                    // keys that can mint a token, which is the thing rotation was
                    // supposed to reduce.
                    error_log('RelayAuth: accepted a token signed with RELAY_JWT_SECRET_PREVIOUS; '
                        . 'the caller has not picked up the new secret yet.');
                }
                break;
            } catch (\Throwable $e) {
                $last = $e;
            }
        }

        if ($claims === null) {
            // Deliberately one flat message: "expired" vs "bad signature" tells an
            // attacker which half of the token to keep working on.
            error_log('RelayAuth: token rejected — ' . ($last ? $last->getMessage() : 'unknown'));
            throw new RelayAuthError('Invalid token.', 401);
        }

        // Cap the lifetime here as well as at the issuer. The issuer is another
        // machine; if it is ever compromised, a self-signed thousand-year token
        // should still be refused at the door.
        if ((int) $claims['exp'] - time() > self::MAX_TTL) {
            throw new RelayAuthError('Token lifetime exceeds the relay maximum.', 401);
        }

        $jti = (string) ($claims['jti'] ?? '');
        if ($jti === '') {
            throw new RelayAuthError('Token has no jti.', 401);
        }
        if (!self::claimJti($jti)) {
            throw new RelayAuthError('Token has already been used.', 401);
        }

        $username = trim((string) ($claims['sub'] ?? ''));
        if ($username === '') {
            throw new RelayAuthError('Token names no user.', 401);
        }

        $allowed = self::allowlist();
        if ($allowed !== null && !in_array($username, $allowed, true)) {
            throw new RelayAuthError('That user is not permitted over the relay.', 403);
        }

        $user = self::findByUsername($username);
        if ($user === null) {
            // Same shape as the subscription failure below, so probing the relay
            // does not reveal which usernames exist.
            throw new RelayAuthError('No access for that account.', 403);
        }
        if (strtolower((string) ($user['status'] ?? 'active')) !== 'active') {
            throw new RelayAuthError('No access for that account.', 403);
        }

        $plan = self::planName((int) $user['id']);
        if ($plan === '') {
            throw new RelayAuthError('No access for that account.', 403);
        }

        return [
            'user'     => $user,
            'username' => (string) $user['username'],
            'plan'     => $plan,
            'is_pro'   => $plan === 'academy_pro',
            'claims'   => $claims,
        ];
    }

    /**
     * Signing secrets to try, current first.
     *
     * Two exist only during a rotation. The secret lives in a file on two
     * separate hosts, so it cannot change on both at the same instant; without
     * an overlap every request in the gap fails, and the gap is however long it
     * takes to edit the second file and deploy. Honouring the previous secret
     * for a window turns that into a rotation with no failed requests at all.
     *
     * Clear RELAY_JWT_SECRET_PREVIOUS as soon as the callers have been updated.
     * Until you do, both keys can mint a token.
     *
     * @return array<string,string>
     */
    private static function secrets(): array
    {
        $out     = [];
        $current = (string) (Env::get('RELAY_JWT_SECRET', '') ?? '');
        if ($current !== '') {
            $out['current'] = $current;
        }

        $previous = (string) (Env::get('RELAY_JWT_SECRET_PREVIOUS', '') ?? '');
        if ($previous !== '' && $previous !== $current) {
            $out['previous'] = $previous;
        }

        return $out;
    }

    /** The Authorization header, unwrapped. Falls back for servers that hide it from PHP. */
    private static function bearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';

        if ($header === '' && function_exists('apache_request_headers')) {
            foreach (apache_request_headers() as $k => $v) {
                if (strcasecmp($k, 'Authorization') === 0) { $header = (string) $v; break; }
            }
        }
        if (!preg_match('/^Bearer\s+(\S+)$/i', trim((string) $header), $m)) {
            return null;
        }

        return $m[1];
    }

    /**
     * Record a jti as spent, returning false if it was already spent.
     *
     * One file per jti under a dedicated directory, created with an exclusive
     * open — the atomicity of O_EXCL is what makes this safe when two copies of
     * the same replayed token arrive at once, which a lock-free "check then
     * write" would let through. Files outlive the longest token by a minute and
     * are swept opportunistically, so the directory stays small without a cron.
     */
    private static function claimJti(string $jti): bool
    {
        $dir = self::storage() . '/relay_jti';
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            // Cannot prove freshness. Fail closed: a replay window is worse than an outage.
            error_log('RelayAuth: cannot create the jti directory at ' . $dir);
            return false;
        }

        // Sweep ~1 request in 50 rather than on every call, so the common path is one open().
        if (random_int(1, 50) === 1) {
            $cutoff = time() - (self::MAX_TTL + 60);
            foreach ((array) @scandir($dir) as $f) {
                if ($f === '.' || $f === '..') continue;
                $p = $dir . '/' . $f;
                if (@filemtime($p) < $cutoff) @unlink($p);
            }
        }

        $path = $dir . '/' . hash('sha256', $jti);
        $fh   = @fopen($path, 'x');
        if ($fh === false) {
            return false;       // already present → replay
        }
        fclose($fh);

        return true;
    }

    /** Exact-username lookup. See the class note on why this is not findByCredentials(). */
    private static function findByUsername(string $username): ?array
    {
        try {
            $row = Database::getInstance()->get('users', ['id', 'username', 'email', 'fullname', 'status'], [
                'username' => $username,
            ]);

            return is_array($row) ? $row : null;
        } catch (\Throwable $e) {
            error_log('RelayAuth: user lookup failed — ' . $e->getMessage());
            return null;
        }
    }

    /**
     * The member's active plan name, or '' if they have none.
     *
     * Mirrors AcademyController::planName()/hasActiveSubscription() so the relay
     * and /academy/bot cannot drift apart on who counts as a member.
     */
    private static function planName(int $userId): string
    {
        try {
            $db  = Database::getInstance();
            $sub = $db->get('user_subscriptions', ['plan_id', 'expires_at'], [
                'user_id' => $userId,
                'status'  => 'active',
                'ORDER'   => ['id' => 'DESC'],
            ]);
            if (!is_array($sub)) return '';

            $exp = $sub['expires_at'] ?? null;
            if (!empty($exp) && strtotime((string) $exp) <= time()) return '';

            $plan = $db->get('subscription_plans', ['name'], ['id' => $sub['plan_id']]);

            return is_array($plan) ? (string) ($plan['name'] ?? '') : '';
        } catch (\Throwable $e) {
            error_log('RelayAuth: plan lookup failed — ' . $e->getMessage());
            return '';
        }
    }

    /** Usernames the relay may act for, or null when unrestricted. */
    private static function allowlist(): ?array
    {
        $raw = trim((string) (Env::get('RELAY_ALLOWED_USERS', '') ?? ''));
        if ($raw === '') return null;

        $names = array_values(array_filter(array_map('trim', explode(',', $raw)), static fn($n) => $n !== ''));

        return $names === [] ? null : $names;
    }

    private static function storage(): string
    {
        return defined('STORAGE_PATH') ? STORAGE_PATH : sys_get_temp_dir();
    }
}
