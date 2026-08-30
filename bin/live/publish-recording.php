#!/usr/bin/env php
<?php

/**
 * A finished broadcast, put where everybody can watch it.
 *
 * MediaMTX writes the live HLS and throws it away; the recording ffmpeg made
 * alongside it is the only lasting copy. This is what happens to that copy
 * once the encoder disconnects:
 *
 *     recording on disk  →  B2 (the same bucket the phone uploads to)
 *                        →  comchain, told where it landed
 *                        →  the newsfeed
 *
 * Both routes end in the same bucket on purpose. A recording made on the phone
 * and one made on the server are the same thing to everybody downstream — one
 * URL shape, one retention policy, one bill — and the only difference is who
 * did the encoding.
 *
 * Run by hook-unpublish.sh, detached, one per broadcast. It is deliberately a
 * separate process: uploading three gigabytes must not hold up MediaMTX's own
 * teardown, and a broadcast that has ended should read as ended immediately
 * whatever is still happening to its recording.
 *
 * Usage: publish-recording.php <stream_key> <path/to/recording.mp4>
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));

require ROOT_PATH . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(ROOT_PATH);
$dotenv->safeLoad();

use Ginto\Helpers\B2Helper;

/** Everything here goes to syslog: there is no terminal watching this. */
function say(string $message, bool $isError = false): void
{
    openlog('recording-publish', LOG_PID, LOG_DAEMON);
    syslog($isError ? LOG_ERR : LOG_INFO, $message);
    closelog();
}

$key  = $argv[1] ?? '';
$path = $argv[2] ?? '';

// The key names a file in a bucket and is signed into a webhook, so it is
// checked rather than trusted. It is minted by comchain as 64 hex characters
// and nothing else is a broadcast.
if (preg_match('/^[0-9a-f]{64}$/', $key) !== 1) {
    say("refusing a key that is not a broadcast key", true);
    exit(1);
}

if ($path === '' || !is_file($path)) {
    say("no recording on disk for {$key}", true);
    exit(1);
}

$bytes = (int) filesize($path);

// Under a megabyte is an encoder that connected and left. Publishing that
// would put an empty player in the feed under a title somebody chose.
if ($bytes < 1_048_576) {
    say("recording for {$key} was {$bytes} bytes; discarding");
    @unlink($path);
    exit(0);
}

if (!B2Helper::isEnabled()) {
    // Left on disk rather than deleted. Storage misconfigured is a problem to
    // fix, not a reason to destroy the only copy of somebody's broadcast.
    say("B2 is not configured; leaving {$key} on disk at {$path}", true);
    exit(1);
}

// The same prefix the phone's uploads use, so one lifecycle rule covers both.
$remote = 'recordings/' . $key . '.mp4';

try {
    $url = B2Helper::uploadFile($path, $remote, 'video/mp4');
} catch (Throwable $e) {
    say("upload failed for {$key}: " . $e->getMessage(), true);
    exit(1);
}

say("uploaded {$key} ({$bytes} bytes)");

// ── Tell comchain ────────────────────────────────────────────────────────────
//
// Signed the same way the publish and unpublish hooks are: HMAC over
// "<action>|<key>" with the shared secret. The URL travels in the body rather
// than being rebuilt on the far side, because the bucket and CDN base are
// configuration on *this* host and comchain has no business knowing them.
$secret = (string) ($_ENV['LIVE_HOOK_SECRET'] ?? getenv('LIVE_HOOK_SECRET') ?: '');
$hooks  = rtrim((string) ($_ENV['COMCHAIN_HOOKS'] ?? getenv('COMCHAIN_HOOKS')
    ?: 'https://comchain.silverqueen.pro/api/v1/live/hook'), '/');

if ($secret === '') {
    say("uploaded {$key} but LIVE_HOOK_SECRET is unset; comchain was not told", true);
    exit(1);
}

$seconds = 0;
$probe   = @shell_exec(sprintf(
    'ffprobe -v error -show_entries format=duration -of csv=p=0 %s 2>/dev/null',
    escapeshellarg($path),
));

if (is_string($probe) && trim($probe) !== '') {
    $seconds = (int) (float) trim($probe);
}

$body = http_build_query([
    'key'     => $key,
    'sig'     => hash_hmac('sha256', 'recording|' . $key, $secret),
    'url'     => $url,
    'bytes'   => $bytes,
    'seconds' => $seconds,
]);

$ch = curl_init($hooks . '/recording');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $body,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
]);

$answer = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($status < 200 || $status >= 300) {
    // The bytes are safely in B2; only the notification failed. Keeping the
    // local copy means this can simply be run again rather than the recording
    // being lost to a transient 502.
    say("comchain answered HTTP {$status} for {$key}; keeping the local copy", true);
    exit(1);
}

// Only now. The local file is the fallback for every step above it, and it
// stops being needed the moment comchain has both the bytes' address and a
// row pointing at it.
@unlink($path);

say("published {$key} → {$url}");
exit(0);
