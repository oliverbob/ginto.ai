<?php

declare(strict_types=1);

namespace Ginto\Security;

/**
 * A TOTP gate in front of phpMyAdmin.
 *
 * phpMyAdmin sits in the webroot at public/pma and is reachable from the open
 * internet, which makes it a permanent target: a database login form that every
 * scanner on the internet knows the shape of. This class stands in front of it
 * so that an unauthenticated request never reaches phpMyAdmin's code at all.
 * That ordering is the point — it protects against phpMyAdmin's own bugs, not
 * just against someone guessing the database password.
 *
 * Deliberately self-contained. It reads one value out of .env and touches no
 * database, because the times you most need phpMyAdmin are the times the
 * database is unwell, and a gate that needs the database to let you in is a
 * gate that locks you out exactly when it matters.
 *
 * IT FAILS CLOSED. With no PMA_TOTP_SECRET configured, every request is denied
 * rather than allowed. This codebase already carries one bug of the opposite
 * shape — AgentTools::dbQueryAdmin() compares a supplied key against an unset
 * environment variable, so an empty string matches an empty string and the
 * caller is handed a root database handle. An unconfigured secret must never
 * mean an open door.
 */
final class PmaGate
{
    /** Session key holding the proof of a passed challenge. */
    private const SESSION_KEY = 'pma_gate';

    /** Re-challenge after this long without a request, in seconds. */
    private const IDLE_TIMEOUT = 1800;

    /**
     * Re-challenge this long after the challenge was passed, however active the
     * session has been. The application's own sessions last ten years; database
     * administration should not inherit that.
     */
    private const ABSOLUTE_TIMEOUT = 43200;

    /** Failed attempts from one address before it is locked out. */
    private const MAX_ATTEMPTS = 5;

    /** How long that lockout lasts, in seconds. */
    private const LOCKOUT = 900;

    /** Accept a code from this many 30-second periods either side, for clock drift. */
    private const DRIFT_PERIODS = 1;

    /**
     * Stand in front of a phpMyAdmin request.
     *
     * Returns true when the caller may proceed. Otherwise it has already sent
     * the challenge page and the caller must stop: return false from the
     * built-in server's router, or exit.
     */
    public static function guard(): bool
    {
        self::startSession();

        if (self::passed()) {
            $_SESSION[self::SESSION_KEY]['seen'] = time();

            // Hand the session back before phpMyAdmin runs. It starts a session
            // of its own under its own name, and PHP refuses a second
            // session_start() while one is active — which shows up as
            // phpMyAdmin's "Error during session start" page rather than as
            // anything pointing at this gate. Writing and closing here persists
            // what the gate needs for the next request and leaves the request
            // with no active session, which is the state phpMyAdmin expects.
            session_write_close();

            return true;
        }

        $secret = self::secret();

        if ($secret === null) {
            self::render(
                'Two-factor authentication is not configured yet.',
                'Run bin/pma-2fa-setup on the server and put the secret it prints '
                . 'into PMA_TOTP_SECRET in .env. Until then this page stays shut.',
                false
            );

            return false;
        }

        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $error = self::attempt($secret);

            if ($error === null) {
                // A fresh identifier for the newly privileged session, so a
                // session id an attacker already knows cannot be upgraded by
                // watching someone else pass the challenge.
                session_regenerate_id(true);

                $_SESSION[self::SESSION_KEY] = [
                    'at'    => time(),
                    'seen'  => time(),
                    'ip'    => self::clientIp(),
                    'agent' => self::agentFingerprint(),
                ];

                // Redirect so a refresh does not repost a code that is now spent.
                header('Location: ' . self::currentPath(), true, 303);

                return false;
            }
        }

        self::render('Two-factor authentication', $error, true);

        return false;
    }

    /** Whether the current session already holds a live, valid challenge. */
    private static function passed(): bool
    {
        $state = $_SESSION[self::SESSION_KEY] ?? null;

        if (!is_array($state) || !isset($state['at'], $state['seen'])) {
            return false;
        }

        $now = time();

        if ($now - (int) $state['at'] > self::ABSOLUTE_TIMEOUT) {
            return false;
        }

        if ($now - (int) $state['seen'] > self::IDLE_TIMEOUT) {
            return false;
        }

        // Bind the proof to where it was issued. A stolen cookie replayed from
        // another address or another browser does not carry the gate with it.
        if (!hash_equals((string) ($state['ip'] ?? ''), self::clientIp())) {
            return false;
        }

        return hash_equals((string) ($state['agent'] ?? ''), self::agentFingerprint());
    }

    /**
     * Check one submitted code.
     *
     * @return string|null An error to show, or null when the code was accepted.
     */
    private static function attempt(string $secret): ?string
    {
        $remaining = self::lockoutRemaining();

        if ($remaining > 0) {
            return 'Too many attempts. Try again in ' . (int) ceil($remaining / 60) . ' minute(s).';
        }

        $token = (string) ($_POST['csrf'] ?? '');

        if (!hash_equals((string) ($_SESSION['pma_gate_csrf'] ?? ''), $token) || $token === '') {
            return 'This form expired. Try again.';
        }

        $code    = preg_replace('/\D/', '', (string) ($_POST['code'] ?? '')) ?? '';
        $counter = self::matchingCounter($secret, $code);

        if ($counter === null) {
            self::recordFailure();

            return 'That code is not right.';
        }

        // A code stays valid for its whole 30-second period, so without this a
        // code read over someone's shoulder — or lifted from a proxy log — can
        // be replayed within the window. Refuse anything not strictly newer
        // than the last code this secret accepted.
        if ($counter <= self::lastCounter()) {
            self::recordFailure();

            return 'That code has already been used.';
        }

        self::writeLastCounter($counter);
        self::clearFailures();

        return null;
    }

    /**
     * The TOTP counter a code matches, or null if it matches none.
     *
     * RFC 6238 with the parameters Google Authenticator uses: HMAC-SHA1 over a
     * 30-second counter, truncated to six digits.
     */
    private static function matchingCounter(string $secret, string $code): ?int
    {
        if (preg_match('/^\d{6}$/', $code) !== 1) {
            return null;
        }

        $key = self::base32Decode($secret);

        if ($key === '') {
            return null;
        }

        $step = (int) floor(time() / 30);

        for ($offset = -self::DRIFT_PERIODS; $offset <= self::DRIFT_PERIODS; $offset++) {
            $counter = $step + $offset;
            $hash    = hash_hmac('sha1', pack('N*', 0, $counter), $key, true);
            $index   = ord($hash[19]) & 0x0f;

            $number = ((ord($hash[$index]) & 0x7f) << 24)
                | (ord($hash[$index + 1]) << 16)
                | (ord($hash[$index + 2]) << 8)
                | ord($hash[$index + 3]);

            $candidate = str_pad((string) ($number % 1000000), 6, '0', STR_PAD_LEFT);

            if (hash_equals($candidate, $code)) {
                return $counter;
            }
        }

        return null;
    }

    /** The shared secret, or null when none is configured. */
    private static function secret(): ?string
    {
        $value = getenv('PMA_TOTP_SECRET');

        if (!is_string($value) || trim($value) === '') {
            $value = (string) ($_ENV['PMA_TOTP_SECRET'] ?? '');
        }

        if (trim($value) === '') {
            $value = self::fromEnvFile('PMA_TOTP_SECRET');
        }

        $value = trim($value, " \t\n\r\0\x0B\"'");

        return $value === '' ? null : $value;
    }

    /**
     * Read one key straight out of .env.
     *
     * The gate runs before the application bootstraps, so it cannot assume
     * Dotenv has populated the environment.
     */
    private static function fromEnvFile(string $key): string
    {
        $path = self::root() . '/.env';

        if (!is_readable($path)) {
            return '';
        }

        $handle = @fopen($path, 'r');

        if ($handle === false) {
            return '';
        }

        $found = '';

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);

            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);

            if (trim($name) === $key) {
                $found = trim($value);
                break;
            }
        }

        fclose($handle);

        return $found;
    }

    // -- Rate limiting -------------------------------------------------------
    //
    // Six digits is a million combinations, but a drift window of three periods
    // and no limit turns that into a feasible grind against a long-lived target.
    // State is per-address and on disk, so it survives the request and does not
    // depend on the attacker keeping a session.

    private static function lockoutRemaining(): int
    {
        $state = self::readJson(self::failurePath());

        if (($state['count'] ?? 0) < self::MAX_ATTEMPTS) {
            return 0;
        }

        $elapsed = time() - (int) ($state['at'] ?? 0);

        return $elapsed >= self::LOCKOUT ? 0 : self::LOCKOUT - $elapsed;
    }

    private static function recordFailure(): void
    {
        $state = self::readJson(self::failurePath());
        $count = (int) ($state['count'] ?? 0);

        // A lockout that has run its course starts the count again.
        if ($count >= self::MAX_ATTEMPTS && time() - (int) ($state['at'] ?? 0) >= self::LOCKOUT) {
            $count = 0;
        }

        self::writeJson(self::failurePath(), ['count' => $count + 1, 'at' => time()]);
    }

    private static function clearFailures(): void
    {
        @unlink(self::failurePath());
    }

    private static function failurePath(): string
    {
        return self::stateDir() . '/fail-' . hash('sha256', self::clientIp()) . '.json';
    }

    private static function lastCounter(): int
    {
        return (int) (self::readJson(self::stateDir() . '/last-counter.json')['counter'] ?? 0);
    }

    private static function writeLastCounter(int $counter): void
    {
        self::writeJson(self::stateDir() . '/last-counter.json', ['counter' => $counter]);
    }

    // -- Plumbing ------------------------------------------------------------

    private static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $sessions = self::root() . '/storage/sessions';

        if (is_dir($sessions)) {
            ini_set('session.save_path', $sessions);
        }

        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');

        if (self::isHttps()) {
            ini_set('session.cookie_secure', '1');
        }

        @session_start();
    }

    private static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }

        return ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }

    /**
     * The caller's address.
     *
     * X-Forwarded-For is honoured only for requests that actually arrived from
     * the local reverse proxy. Trusting the header unconditionally would let
     * anyone reset their own rate-limit counter by changing one header.
     */
    private static function clientIp(): string
    {
        $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

        if (in_array($remote, ['127.0.0.1', '::1'], true)) {
            $forwarded = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');

            if ($forwarded !== '') {
                $first = trim(explode(',', $forwarded)[0]);

                if (filter_var($first, FILTER_VALIDATE_IP) !== false) {
                    return $first;
                }
            }
        }

        return $remote;
    }

    private static function agentFingerprint(): string
    {
        return hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    }

    private static function currentPath(): string
    {
        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/pma/'), PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : '/pma/';
    }

    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    private static function stateDir(): string
    {
        $dir = self::root() . '/storage/pma-gate';

        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }

        return $dir;
    }

    /** @return array<string,mixed> */
    private static function readJson(string $path): array
    {
        if (!is_readable($path)) {
            return [];
        }

        $decoded = json_decode((string) @file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string,mixed> $data */
    private static function writeJson(string $path, array $data): void
    {
        @file_put_contents($path, json_encode($data), LOCK_EX);
        @chmod($path, 0600);
    }

    private static function base32Decode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $clean    = strtoupper((string) preg_replace('/[^A-Za-z2-7]/', '', $data));
        $bits     = '';

        foreach (str_split($clean) as $char) {
            $position = strpos($alphabet, $char);

            if ($position === false) {
                return '';
            }

            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $out = '';

        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $out .= chr((int) bindec($chunk));
            }
        }

        return $out;
    }

    /** Send the challenge page. */
    private static function render(string $title, ?string $message, bool $showForm): void
    {
        if (!headers_sent()) {
            http_response_code($showForm ? 401 : 503);
            header('Content-Type: text/html; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('X-Robots-Tag: noindex, nofollow');
            header('Referrer-Policy: no-referrer');
            header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'");
        }

        if ($showForm && empty($_SESSION['pma_gate_csrf'])) {
            $_SESSION['pma_gate_csrf'] = bin2hex(random_bytes(32));
        }

        $csrf   = (string) ($_SESSION['pma_gate_csrf'] ?? '');
        $safe   = static fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $action = $safe(self::currentPath());

        echo '<!doctype html><html lang="en"><head><meta charset="utf-8">';
        echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<title>', $safe($title), '</title><style>';
        echo ':root{color-scheme:light dark}';
        echo 'body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f6f7f9;color:#16181d;';
        echo 'font:15px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif}';
        echo '@media(prefers-color-scheme:dark){body{background:#0e1013;color:#e7e9ee}';
        echo '.card{background:#171a1f!important;border-color:#2a2f38!important}';
        echo 'input{background:#0e1013!important;color:#e7e9ee!important;border-color:#2a2f38!important}}';
        echo '.card{width:min(92vw,23rem);background:#fff;border:1px solid #e2e5ea;border-radius:12px;';
        echo 'padding:1.75rem;box-shadow:0 1px 3px rgba(0,0,0,.06)}';
        echo 'h1{margin:0 0 .5rem;font-size:1.05rem;letter-spacing:-.01em}';
        echo 'p{margin:0 0 1.25rem;color:#5b6472;font-size:.875rem}';
        echo '@media(prefers-color-scheme:dark){p{color:#9aa3b2}}';
        echo 'input{width:100%;box-sizing:border-box;padding:.7rem .85rem;font:1.35rem/1 ui-monospace,monospace;';
        echo 'letter-spacing:.4em;text-align:center;border:1px solid #cfd4dc;border-radius:8px;background:#fff;color:#16181d}';
        echo 'input:focus{outline:2px solid #4f7cff;outline-offset:1px;border-color:transparent}';
        echo 'button{width:100%;margin-top:.85rem;padding:.7rem;font:600 .9rem system-ui,sans-serif;color:#fff;';
        echo 'background:#2f6bff;border:0;border-radius:8px;cursor:pointer}';
        echo 'button:hover{background:#2559da}';
        echo '.err{margin:0 0 1rem;padding:.6rem .75rem;border-radius:8px;background:#fdecec;color:#a32020;font-size:.85rem}';
        echo '@media(prefers-color-scheme:dark){.err{background:#3a1c1c;color:#ff9d9d}}';
        echo '</style></head><body><div class="card">';
        echo '<h1>', $safe($title), '</h1>';

        if ($showForm) {
            echo '<p>Enter the six-digit code from your authenticator app.</p>';

            if ($message !== null) {
                echo '<p class="err">', $safe($message), '</p>';
            }

            echo '<form method="post" action="', $action, '">';
            echo '<input type="hidden" name="csrf" value="', $safe($csrf), '">';
            echo '<input name="code" inputmode="numeric" autocomplete="one-time-code" pattern="\d{6}" ';
            echo 'maxlength="6" required autofocus aria-label="Six-digit code">';
            echo '<button type="submit">Continue</button></form>';
        } else {
            echo '<p>', $safe($message), '</p>';
        }

        echo '</div></body></html>';
    }
}
