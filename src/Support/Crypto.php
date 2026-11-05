<?php
namespace Ginto\Support;

/**
 * Minimal symmetric encryption for secrets at rest (e.g. the Binance API secret
 * stored in MySQL). AES-256-CBC with a key from .env (GTB_ENCRYPTION_KEY).
 * Deliberately small — hardening comes later per the V1 roadmap.
 */
class Crypto
{
    private const CIPHER = 'aes-256-cbc';

    private static function key(): string
    {
        $k = Env::get('GTB_ENCRYPTION_KEY', '');
        if ($k === null || $k === '') {
            throw new \RuntimeException('GTB_ENCRYPTION_KEY is not set in .env');
        }
        // Accept hex/base64/raw; derive a fixed 32-byte key deterministically.
        return hash('sha256', $k, true);
    }

    /** Encrypt plaintext; returns base64(iv . ciphertext), or null for empty input. */
    public static function encrypt(string $plaintext): ?string
    {
        if ($plaintext === '') {
            return null;
        }
        $ivLen = openssl_cipher_iv_length(self::CIPHER);
        $iv = random_bytes($ivLen);
        $ct = openssl_encrypt($plaintext, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv);
        if ($ct === false) {
            return null;
        }
        return base64_encode($iv . $ct);
    }

    /** Decrypt a value produced by encrypt(); returns '' on empty/failure. */
    public static function decrypt(?string $payload): string
    {
        if ($payload === null || $payload === '') {
            return '';
        }
        $raw = base64_decode($payload, true);
        if ($raw === false) {
            return '';
        }
        $ivLen = openssl_cipher_iv_length(self::CIPHER);
        if (strlen($raw) <= $ivLen) {
            return '';
        }
        $iv = substr($raw, 0, $ivLen);
        $ct = substr($raw, $ivLen);
        $pt = openssl_decrypt($ct, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv);
        return $pt === false ? '' : $pt;
    }
}
