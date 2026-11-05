<?php
/**
 * Relay API test suite.
 *
 *   composer test:relay              # everything
 *   php bin/test_relay.php --unit    # crypto + replay only, no server or database
 *
 * The relay is the one door into this application that no browser session
 * guards, so the parts worth testing are the ones that decide whether a request
 * is refused. Four groups, in dependency order:
 *
 *   1. token   — the signature is the whole trust model, so each forgery it must
 *                withstand gets its own case, not one "invalid token" case.
 *   2. replay  — a jti is spendable once, including when copies of one token
 *                arrive together. That is a concurrency property and is tested
 *                by actually racing processes, since a check-then-write bug
 *                passes every sequential test there is.
 *   3. http    — the same rejections through the real endpoint, because a guard
 *                that works in isolation and is never reached is still a hole.
 *   4. access  — a member is served while subscribed and refused once expired.
 *
 * Groups 3 and 4 need the dev server and the database; they are skipped with a
 * note rather than failing when either is absent, so --unit stays useful in a
 * checkout with no services running.
 *
 * Group 4 creates a plan and a subscription and deletes both, on every exit path
 * including a fatal error. It refuses to run at all against a database that
 * already holds active subscriptions, so it can never be pointed at production
 * and quietly grant or revoke anything real.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
chdir($root);
if (!defined('ROOT_PATH'))    define('ROOT_PATH', $root);
if (!defined('STORAGE_PATH')) define('STORAGE_PATH', $root . '/storage');

require $root . '/vendor/autoload.php';
try {
    if (class_exists('Dotenv\\Dotenv')) {
        Dotenv\Dotenv::createImmutable($root)->safeLoad();
    }
} catch (\Throwable $e) {}

use Ginto\Support\Jwt;
use Ginto\Support\RelayAuth;

const AUD = RelayAuth::AUDIENCE;

$unitOnly = in_array('--unit', $argv, true);
$baseUrl  = getenv('RELAY_TEST_URL') ?: 'http://127.0.0.1:8000';

// A secret of this suite's own, so the tests never depend on how the deployment
// is configured and never print the real one.
$secret = bin2hex(random_bytes(32));

$pass = 0; $fail = 0; $skip = 0;

function section(string $t): void { printf("\n\033[1m%s\033[0m\n", $t); }
function ok(string $name, bool $cond, string $note = ''): void {
    global $pass, $fail;
    $cond ? $pass++ : $fail++;
    printf("  %s  %-52s %s\n", $cond ? "\033[32mPASS\033[0m" : "\033[31mFAIL\033[0m", $name, $note);
}
function skip(string $why): void { global $skip; $skip++; printf("  \033[33mSKIP\033[0m  %s\n", $why); }

/** Assert that $fn throws — the only correct outcome for every forgery below. */
function refuses(string $name, callable $fn): void {
    try { $fn(); ok($name, false, 'accepted it'); }
    catch (\Throwable $e) { ok($name, true, $e->getMessage()); }
}

/** @param array<string,mixed> $overrides */
function claims(array $overrides = []): array {
    $now = time();
    return $overrides + [
        'sub' => 'relay-test-user', 'aud' => AUD, 'iss' => 'test',
        'iat' => $now, 'exp' => $now + 300, 'jti' => bin2hex(random_bytes(16)),
    ];
}

function b64(string $raw): string { return rtrim(strtr(base64_encode($raw), '+/', '-_'), '='); }

// ── 1. token ─────────────────────────────────────────────────────────────────

section('Token — what the signature must withstand');

$token = Jwt::encode(claims(['sub' => 'oliverbob']), $secret);
$got   = Jwt::decode($token, $secret, AUD);
ok('a valid token decodes to its claims', ($got['sub'] ?? '') === 'oliverbob');

refuses('a different secret of equal length', fn() => Jwt::decode($token, bin2hex(random_bytes(32)), AUD));
refuses('a token minted for another audience', fn() => Jwt::decode(Jwt::encode(claims(['aud' => 'elsewhere']), $secret), $secret, AUD));

// Swap the username but keep the original signature — the forgery the whole
// scheme exists to stop.
[$h, $p, $s] = explode('.', $token);
$evil = json_decode(base64_decode(strtr($p, '-_', '+/')), true);
$evil['sub'] = 'someone-else';
refuses('a tampered sub carrying the old signature',
    fn() => Jwt::decode($h . '.' . b64(json_encode($evil)) . '.' . $s, $secret, AUD));

// alg:none, and an algorithm swap with a signature genuinely computed under it.
// Both pass if the verifier ever selects its algorithm from the token's header.
refuses('alg:none', fn() => Jwt::decode(
    b64(json_encode(['alg' => 'none', 'typ' => 'JWT'])) . '.' . $p . '.', $secret, AUD));
$h512 = b64(json_encode(['alg' => 'HS512', 'typ' => 'JWT']));
refuses('alg swapped to HS512 and re-signed', fn() => Jwt::decode(
    $h512 . '.' . $p . '.' . b64(hash_hmac('sha512', $h512 . '.' . $p, $GLOBALS['secret'], true)), $secret, AUD));

refuses('an expired token', fn() => Jwt::decode(Jwt::encode(claims(['exp' => time() - 3600]), $secret), $secret, AUD));
refuses('a token with no expiry at all', function () use ($secret) {
    $c = claims(); unset($c['exp']);
    Jwt::decode(Jwt::encode($c, $secret), $secret, AUD);
});
refuses('a token not valid yet (nbf ahead)', fn() => Jwt::decode(Jwt::encode(claims(['nbf' => time() + 3600]), $secret), $secret, AUD));
refuses('a token issued in the future', fn() => Jwt::decode(Jwt::encode(claims(['iat' => time() + 3600]), $secret), $secret, AUD));
refuses('garbage in place of a token', fn() => Jwt::decode('not.a.token', $secret, AUD));

// The floor exists because firebase/php-jwt v7 will not sign below it; a floor
// enforced on one side only leaves this side accepting what the caller cannot
// produce.
refuses('signing with a secret under 32 bytes', fn() => Jwt::encode(claims(), 'too-short'));
refuses('verifying with a secret under 32 bytes', fn() => Jwt::decode($token, 'too-short', AUD));

// Interop only runs where the caller's library is available. Both sides must
// agree byte for byte, so this is the case that would catch a divergence.
$callerAutoload = getenv('RELAY_TEST_CALLER') ?: dirname($root) . '/blockchain/vendor/autoload.php';
if (is_file($callerAutoload)) {
    require_once $callerAutoload;
    $fromCaller = Firebase\JWT\JWT::encode(claims(['sub' => 'oliverbob']), $secret, 'HS256');
    ok('a firebase/php-jwt token verifies here', (Jwt::decode($fromCaller, $secret, AUD)['sub'] ?? '') === 'oliverbob');
    $back = (array) Firebase\JWT\JWT::decode(Jwt::encode(claims(['sub' => 'oliverbob']), $secret), new Firebase\JWT\Key($secret, 'HS256'));
    ok('our token verifies in firebase/php-jwt', ($back['sub'] ?? '') === 'oliverbob');
} else {
    skip('firebase/php-jwt interop — no caller checkout at ' . $callerAutoload);
}

// ── 2. replay ────────────────────────────────────────────────────────────────

section('Replay — a jti is spendable exactly once');

$claimJti = (new ReflectionClass(RelayAuth::class))->getMethod('claimJti');
$claimJti->setAccessible(true);
$spend = fn(string $jti): bool => $claimJti->invoke(null, $jti);

$j = 'test-' . bin2hex(random_bytes(12));
ok('the first use is accepted', $spend($j) === true);
ok('the same jti is refused after', $spend($j) === false);
ok('an unrelated jti still works', $spend('test-' . bin2hex(random_bytes(12))) === true);

// Sequential tests cannot distinguish an atomic claim from check-then-write.
// Racing processes can, and that is the bug this guards against.
if (function_exists('pcntl_fork')) {
    $raced = 'test-race-' . bin2hex(random_bytes(12));
    $kids = [];
    for ($i = 0; $i < 12; $i++) {
        $pid = pcntl_fork();
        if ($pid === 0) { exit($spend($raced) ? 0 : 1); }
        $kids[] = $pid;
    }
    $winners = 0;
    foreach ($kids as $pid) { pcntl_waitpid($pid, $st); if (pcntl_wexitstatus($st) === 0) $winners++; }
    ok('12 processes race one jti, exactly one wins', $winners === 1, "winners: $winners");
} else {
    skip('the concurrency case — ext-pcntl is not available');
}

// ── 3 & 4. over HTTP ─────────────────────────────────────────────────────────

if ($unitOnly) {
    printf("\n%d passed, %d failed, %d skipped\n", $pass, $fail, $skip);
    exit($fail === 0 ? 0 : 1);
}

/** @return array{status:int,body:string} */
function call(string $baseUrl, ?string $token): array {
    $ch = curl_init($baseUrl . '/api/v1/relay/session');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => $token === null ? [] : ['Authorization: Bearer ' . $token],
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return ['status' => $code, 'body' => is_string($body) ? $body : ''];
}

section('HTTP — the same guards through the real endpoint');

if (call($baseUrl, null)['status'] === 0) {
    skip("no server at $baseUrl — run `composer serve` (or set RELAY_TEST_URL)");
    printf("\n%d passed, %d failed, %d skipped\n", $pass, $fail, $skip);
    exit($fail === 0 ? 0 : 1);
}

// The endpoint verifies against the deployment's secret, not this suite's, so
// borrow it for the cases that need a signature the relay will accept.
$live = (string) (Ginto\Support\Env::get('RELAY_JWT_SECRET', '') ?? '');
$mint = fn(array $c = []) => Jwt::encode(claims($c), $live);

$r = call($baseUrl, null);
ok('no Authorization header is refused', $r['status'] === 401, $r['body']);
$r = call($baseUrl, 'garbage');
ok('a malformed token is refused', $r['status'] === 401, $r['body']);

if ($live === '') {
    skip('the signed cases — RELAY_JWT_SECRET is not set in .env');
} else {
    $r = call($baseUrl, Jwt::encode(claims(), bin2hex(random_bytes(32))));
    ok('a token signed with another secret is refused', $r['status'] === 401, $r['body']);
    $r = call($baseUrl, $mint(['exp' => time() - 3600]));
    ok('an expired token is refused', $r['status'] === 401, $r['body']);
    $r = call($baseUrl, $mint(['aud' => 'elsewhere']));
    ok('another audience is refused', $r['status'] === 401, $r['body']);

    // Capped at the relay as well as the issuer: a compromised caller signing
    // itself a very long-lived token still gets nowhere.
    $r = call($baseUrl, $mint(['exp' => time() + 86400]));
    ok('a lifetime beyond the cap is refused', $r['status'] === 401, $r['body']);

    $c = claims(); unset($c['jti']);
    $r = call($baseUrl, Jwt::encode($c, $live));
    ok('a token with no jti is refused', $r['status'] === 401, $r['body']);

    $once = $mint(['sub' => 'relay-test-nobody']);
    $a = call($baseUrl, $once);
    $b = call($baseUrl, $once);
    ok('an unknown username is refused', $a['status'] === 403, $a['body']);
    ok('replaying that same token is refused', $b['status'] === 401, $b['body']);
}

// ── 4. access ────────────────────────────────────────────────────────────────

section('Access — served while subscribed, refused once expired');

$db = null;
try { $db = Ginto\Core\Database::getInstance(); } catch (\Throwable $e) {}

if ($db === null || $live === '') {
    skip('the subscription cases — no database connection or no secret');
    printf("\n%d passed, %d failed, %d skipped\n", $pass, $fail, $skip);
    exit($fail === 0 ? 0 : 1);
}

$planId = null; $subId = null; $userId = null;
$cleanup = function () use (&$planId, &$subId, &$userId, $db) {
    try {
        if ($subId)  $db->delete('user_subscriptions', ['id' => $subId]);
        if ($planId) $db->delete('subscription_plans', ['id' => $planId]);
        if ($userId) $db->delete('users', ['id' => $userId]);
    } catch (\Throwable $e) { fwrite(STDERR, "cleanup failed: " . $e->getMessage() . "\n"); }
};
register_shutdown_function($cleanup);

try {
    // Refuse to touch a database that has real subscriptions in it. This suite
    // writes and deletes rows; on production that is not a test, it is an
    // incident.
    $baseline = (int) $db->count('user_subscriptions', ['status' => 'active']);
    $existing = $baseline;
    if ($existing > 0) {
        skip("the subscription cases — $existing active subscription(s) present; refusing to write to a live database");
    } else {
        $username = 'relay-test-' . bin2hex(random_bytes(4));
        // A fixture account of its own rather than a real one, so a failed run
        // can only ever leave behind a row nobody was using. The password is
        // random and discarded: nothing should be able to log in as this.
        $db->insert('users', [
            'username' => $username, 'email' => $username . '@relay.test',
            'fullname' => 'Relay Test Fixture', 'status' => 'active',
            'password_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
        ]);
        $userId = (int) $db->id();

        $db->insert('subscription_plans', [
            'name' => 'academy_pro', 'plan_type' => 'courses',
            'display_name' => 'Pro Trader (relay test fixture)', 'price_monthly' => 0,
        ]);
        $planId = (int) $db->id();

        $db->insert('user_subscriptions', [
            'user_id' => $userId, 'plan_id' => $planId, 'status' => 'active',
            'started_at' => date('Y-m-d H:i:s'), 'expires_at' => date('Y-m-d H:i:s', time() + 86400),
        ]);
        $subId = (int) $db->id();

        $r = call($baseUrl, Jwt::encode(claims(['sub' => $username]), $live));
        $body = json_decode($r['body'], true) ?: [];
        ok('a subscribed member is served', $r['status'] === 200, $r['body']);
        ok('the response names that member', ($body['username'] ?? '') === $username, $body['username'] ?? '');
        ok('the plan is carried through', ($body['plan'] ?? '') === 'academy_pro' && ($body['is_pro'] ?? null) === true);

        $db->update('user_subscriptions', ['expires_at' => date('Y-m-d H:i:s', time() - 86400)], ['id' => $subId]);
        $r = call($baseUrl, Jwt::encode(claims(['sub' => $username]), $live));
        ok('the same member is refused once expired', $r['status'] === 403, $r['body']);

        $db->update('user_subscriptions', ['status' => 'cancelled', 'expires_at' => date('Y-m-d H:i:s', time() + 86400)], ['id' => $subId]);
        $r = call($baseUrl, Jwt::encode(claims(['sub' => $username]), $live));
        ok('and refused once cancelled', $r['status'] === 403, $r['body']);
    }
} catch (\Throwable $e) {
    ok('the subscription cases ran', false, $e->getMessage());
}

$cleanup();
$planId = $subId = $userId = null;   // the shutdown hook must not delete twice

// Compared against the count taken before the fixtures were created, not
// against zero: when the guard above skipped, rows this suite never touched are
// still there and finding them is correct, not a leak.
$left = (int) $db->count('user_subscriptions', ['status' => 'active']);
ok('no fixtures were left behind', $left === ($baseline ?? 0), "active subscriptions: {$left}, was " . ($baseline ?? 0));

printf("\n%d passed, %d failed, %d skipped\n", $pass, $fail, $skip);
exit($fail === 0 ? 0 : 1);
