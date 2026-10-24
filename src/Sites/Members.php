<?php

declare(strict_types=1);

namespace Ginto\Sites;

use Ginto\Support\Env;
use Ginto\Support\Jwt;

/**
 * SilverQueen members (the chat's identities) as Sites customers.
 *
 * Most people who meet the Sites ad on the SilverQueen feed have no
 * silverqueen.pro account, and must not need one: their name, preview and
 * subscription are kept under a member number in the Sites tables
 * (MEMBER_BASE + their chat identity), which can never collide with a
 * silverqueen.pro user id. They come in by a one-time link the card fetches,
 * which opens a session for the Sites dashboard only -- never the rest of
 * silverqueen.pro.
 */
final class Members
{
    public const MEMBER_BASE = 1_000_000_000;
    private const AUD = 'sites-enter';
    private const TTL = 900;

    public static function uid(int $identityId): int
    {
        return self::MEMBER_BASE + $identityId;
    }

    public static function isMember(int $uid): bool
    {
        return $uid > self::MEMBER_BASE;
    }

    public static function identity(int $uid): ?int
    {
        return self::isMember($uid) ? $uid - self::MEMBER_BASE : null;
    }

    /** A one-time link into the Sites dashboard for this member (15 minutes). */
    public static function enterLink(int $uid, string $displayName, string $to = '/sites/me'): string
    {
        $now = time();
        $token = Jwt::encode([
            'sub' => $uid, 'name' => mb_substr($displayName, 0, 120), 'to' => $to,
            'aud' => self::AUD, 'iat' => $now, 'exp' => $now + self::TTL, 'jti' => bin2hex(random_bytes(12)),
        ], self::key());

        return 'https://silverqueen.pro/sites/enter?t=' . rawurlencode($token);
    }

    /** @return array{uid:int, name:string, to:string}|null the link's member, once only */
    public static function consume(string $token): ?array
    {
        try {
            $c = Jwt::decode($token, self::key(), self::AUD);
        } catch (\Throwable $e) {
            return null;
        }
        $uid = (int) ($c['sub'] ?? 0);
        $jti = (string) ($c['jti'] ?? '');
        if (!self::isMember($uid) || preg_match('/^[0-9a-f]{24}$/', $jti) !== 1) {
            return null;
        }
        // Once only: a forwarded link opens nothing the second time.
        $dir = STORAGE_PATH . '/cache/sites-enter';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        $mark = $dir . '/' . $jti;
        if (is_file($mark)) {
            return null;
        }
        @file_put_contents($mark, (string) time());
        foreach (glob($dir . '/*') ?: [] as $old) {
            if (filemtime($old) < time() - 2 * self::TTL) {
                @unlink($old);
            }
        }
        $to = (string) ($c['to'] ?? '/sites/me');

        return ['uid' => $uid, 'name' => (string) ($c['name'] ?? ''), 'to' => str_starts_with($to, '/sites/') ? $to : '/sites/me'];
    }

    /**
     * The member's own SilverQueen photos, as suggestions for the builder (their
     * posts on the chat, never their phone). @return list<array{url:string, thumb:string}>
     */
    public static function photos(int $uid): array
    {
        $identity = self::identity($uid);
        $secret = (string) (Env::get('SITES_CARD_SECRET', '') ?? '');
        if ($identity === null || $secret === '') {
            return [];
        }
        $body = (string) json_encode(['identity' => $identity]);
        $ts = (string) time();
        $ch = curl_init('https://comchain.silverqueen.pro/api/v1/internal/sites-photos');
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-SQ-Timestamp: ' . $ts,
                'X-SQ-Signature: ' . hash_hmac('sha256', $ts . '.' . $body, $secret)],
            CURLOPT_POSTFIELDS => $body,
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
        $out = is_string($raw) ? json_decode($raw, true) : null;
        $out = is_array($out) && isset($out['data']) ? $out['data'] : $out;
        $list = is_array($out) && is_array($out['photos'] ?? null) ? $out['photos'] : [];

        return array_values(array_filter(array_map(static fn ($p) => is_array($p) && is_string($p['url'] ?? null) && str_starts_with($p['url'], 'https://')
            ? ['url' => $p['url'], 'thumb' => is_string($p['thumb'] ?? null) && str_starts_with($p['thumb'], 'https://') ? $p['thumb'] : $p['url']] : null, $list)));
    }

    private static function key(): string
    {
        $k = (string) (Env::get('APP_KEY', '') ?? '');
        if (strlen($k) < 32) {
            throw new \RuntimeException('APP_KEY is not set.');
        }

        return hash('sha256', 'sites-enter:' . $k);
    }
}
