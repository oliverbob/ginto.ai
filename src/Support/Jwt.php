<?php
namespace Ginto\Support;

/**
 * Minimal HS256 JSON Web Token encoder/verifier.
 *
 * This exists so services that cannot hold a session cookie — SilverQueen on
 * sq.ginto.ai, reaching this host over HTTPS through the tunnel — can say which
 * ginto.ai user a request is for, in a way the receiving side can trust.
 *
 * What the token does and does not do is worth being explicit about, because it
 * is easy to assume otherwise: the payload is base64url, NOT ciphertext. Anyone
 * holding a token can read the username in it. What the signature buys is that
 * nobody can *change* it or mint one for a different user without the shared
 * secret, and that is the property that actually matters here — the username is
 * not a secret, the authority to claim it is. Confidentiality on the wire comes
 * from TLS, which already covers the whole request. If a payload ever needs to
 * be opaque at rest as well, encrypt it with Crypto instead of signing it here.
 *
 * Two verification details are deliberate rather than incidental:
 *
 *   - the algorithm is fixed at HS256 and the token's own `alg` header is only
 *     ever compared against it, never used to select the algorithm. Trusting
 *     that header is the classic JWT forgery: an attacker sets `alg: none` (or
 *     downgrades an RS256 key to HS256) and signs whatever they like.
 *   - the signature is compared with hash_equals, so a wrong signature takes
 *     the same time to reject as a nearly-right one and cannot be guessed byte
 *     by byte from timing.
 *
 * Tokens are bearer credentials: whoever holds one is that user until it
 * expires. Mint them short-lived (minutes) and give each a `jti` so the
 * receiving side can refuse a replay.
 */
class Jwt
{
    private const ALG = 'HS256';

    /** Seconds of clock skew tolerated on exp/nbf/iat, so a slightly fast sender is not rejected. */
    private const LEEWAY = 60;

    /**
     * Shortest shared secret accepted, in bytes — the HS256 block size.
     *
     * This is not arbitrary caution: firebase/php-jwt v7, which mints the tokens
     * on the SilverQueen side, throws on anything shorter. Enforcing the same
     * floor here keeps the two halves from disagreeing, so a too-short secret
     * fails loudly at both ends during setup rather than leaving this side
     * happily accepting a weak key that the other side cannot even sign with.
     */
    private const MIN_SECRET_BYTES = 32;

    /**
     * Sign a claim set and return the compact token.
     *
     * @param array<string,mixed> $claims
     */
    public static function encode(array $claims, string $secret): string
    {
        self::assertSecret($secret);
        $header  = self::b64(json_encode(['alg' => self::ALG, 'typ' => 'JWT'], JSON_UNESCAPED_SLASHES));
        $payload = self::b64(json_encode($claims, JSON_UNESCAPED_SLASHES));
        $signing = $header . '.' . $payload;

        return $signing . '.' . self::b64(hash_hmac('sha256', $signing, $secret, true));
    }

    /**
     * Verify a compact token and return its claims.
     *
     * Throws on anything that is not a valid, unexpired, correctly signed token
     * for this audience — callers should treat any exception as a flat 401 and
     * must not leak the reason to the client, since the distinction between
     * "bad signature" and "expired" is useful to an attacker and to nobody else.
     *
     * @return array<string,mixed>
     */
    public static function decode(string $token, string $secret, ?string $audience = null): array
    {
        self::assertSecret($secret);

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new \RuntimeException('Malformed token.');
        }
        [$h64, $p64, $s64] = $parts;

        $header = json_decode(self::unb64($h64), true);
        if (!is_array($header)) {
            throw new \RuntimeException('Malformed token header.');
        }
        // Compared against the constant, never used to pick the algorithm.
        if (($header['alg'] ?? '') !== self::ALG) {
            throw new \RuntimeException('Unsupported token algorithm.');
        }

        $expected = hash_hmac('sha256', $h64 . '.' . $p64, $secret, true);
        if (!hash_equals($expected, self::unb64($s64))) {
            throw new \RuntimeException('Bad token signature.');
        }

        $claims = json_decode(self::unb64($p64), true);
        if (!is_array($claims)) {
            throw new \RuntimeException('Malformed token payload.');
        }

        $now = time();
        if (isset($claims['nbf']) && $now + self::LEEWAY < (int) $claims['nbf']) {
            throw new \RuntimeException('Token is not valid yet.');
        }
        if (isset($claims['iat']) && $now + self::LEEWAY < (int) $claims['iat']) {
            throw new \RuntimeException('Token was issued in the future.');
        }
        // An expiry is required, not optional: a bearer token that never dies is
        // a permanent credential sitting in whatever log first captured it.
        if (!isset($claims['exp'])) {
            throw new \RuntimeException('Token has no expiry.');
        }
        if ($now - self::LEEWAY >= (int) $claims['exp']) {
            throw new \RuntimeException('Token has expired.');
        }
        if ($audience !== null && ($claims['aud'] ?? null) !== $audience) {
            throw new \RuntimeException('Token is for a different audience.');
        }

        return $claims;
    }

    private static function assertSecret(string $secret): void
    {
        if (strlen($secret) < self::MIN_SECRET_BYTES) {
            throw new \RuntimeException(sprintf(
                'The relay secret must be at least %d bytes; got %d. Generate one with: openssl rand -hex 32',
                self::MIN_SECRET_BYTES,
                strlen($secret)
            ));
        }
    }

    private static function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function unb64(string $b64): string
    {
        $raw = base64_decode(strtr($b64, '-_', '+/'), true);

        return $raw === false ? '' : $raw;
    }
}
