<?php
namespace Ginto\Services;

/**
 * RFC 6238 TOTP enrollment and verification for authenticator applications.
 * Secrets are encrypted at rest; set TOTP_ENCRYPTION_KEY to a long random
 * deployment secret (APP_KEY or MasterKey is accepted for older deployments).
 */
class TotpService
{
    private $pdo;

    public function __construct($db)
    {
        $this->pdo = $db->pdo;
        $this->ensureTable();
    }

    public function isEnabled(int $userId): bool
    {
        $stmt = $this->pdo->prepare('SELECT enabled_at FROM user_totp WHERE user_id = ?');
        $stmt->execute([$userId]);
        return (bool) $stmt->fetchColumn();
    }

    public function createEnrollment(int $userId, string $label): array
    {
        $secret = $this->base32Encode(random_bytes(20));
        if ($this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
            $stmt = $this->pdo->prepare('INSERT INTO user_totp (user_id, secret_encrypted, enabled_at, created_at, updated_at)
                VALUES (?, ?, NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                ON DUPLICATE KEY UPDATE secret_encrypted = VALUES(secret_encrypted), enabled_at = NULL, updated_at = CURRENT_TIMESTAMP');
            $stmt->execute([$userId, $this->encrypt($secret)]);
        } else {
            $update = $this->pdo->prepare('UPDATE user_totp SET secret_encrypted = ?, enabled_at = NULL, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?');
            $update->execute([$this->encrypt($secret), $userId]);
            if ($update->rowCount() === 0) {
                $insert = $this->pdo->prepare('INSERT INTO user_totp (user_id, secret_encrypted, enabled_at, created_at, updated_at) VALUES (?, ?, NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)');
                $insert->execute([$userId, $this->encrypt($secret)]);
            }
        }
        $issuer = 'Ginto AI';
        $uri = 'otpauth://totp/' . rawurlencode($issuer . ':' . $label)
            . '?secret=' . rawurlencode($secret) . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=6&period=30';
        return ['secret' => $secret, 'uri' => $uri];
    }

    public function confirmEnrollment(int $userId, string $code): bool
    {
        $secret = $this->getSecret($userId);
        if ($secret === null || !$this->verifyCode($secret, $code)) return false;
        $stmt = $this->pdo->prepare('UPDATE user_totp SET enabled_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?');
        $stmt->execute([$userId]);
        return true;
    }

    public function verifyUserCode(int $userId, string $code): bool
    {
        if (!$this->isEnabled($userId)) return false;
        $secret = $this->getSecret($userId);
        return $secret !== null && $this->verifyCode($secret, $code);
    }

    public function disable(int $userId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM user_totp WHERE user_id = ?');
        $stmt->execute([$userId]);
    }

    private function getSecret(int $userId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT secret_encrypted FROM user_totp WHERE user_id = ?');
        $stmt->execute([$userId]);
        $value = $stmt->fetchColumn();
        return is_string($value) && $value !== '' ? $this->decrypt($value) : null;
    }

    private function verifyCode(string $secret, string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code);
        if (!preg_match('/^\d{6}$/', $code)) return false;
        $key = $this->base32Decode($secret);
        $step = (int) floor(time() / 30);
        // Permit one adjacent period for minor clock drift.
        for ($offset = -1; $offset <= 1; $offset++) {
            $counter = $step + $offset;
            $binary = pack('N*', 0, $counter);
            $hash = hash_hmac('sha1', $binary, $key, true);
            $index = ord($hash[19]) & 0x0f;
            $number = ((ord($hash[$index]) & 0x7f) << 24) | (ord($hash[$index + 1]) << 16) | (ord($hash[$index + 2]) << 8) | ord($hash[$index + 3]);
            if (hash_equals(str_pad((string) ($number % 1000000), 6, '0', STR_PAD_LEFT), $code)) return true;
        }
        return false;
    }

    private function encryptionKey(): string
    {
        $raw = $_ENV['TOTP_ENCRYPTION_KEY'] ?? getenv('TOTP_ENCRYPTION_KEY')
            ?: ($_ENV['APP_KEY'] ?? getenv('APP_KEY') ?: ($_ENV['MasterKey'] ?? getenv('MasterKey')));
        if (!is_string($raw) || $raw === '') throw new \RuntimeException('TOTP_ENCRYPTION_KEY must be configured before enabling two-factor authentication.');
        return hash('sha256', $raw, true);
    }

    private function encrypt(string $plaintext): string
    {
        $key = $this->encryptionKey();
        if (function_exists('sodium_crypto_secretbox')) {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            return 'sodium:' . base64_encode($nonce . sodium_crypto_secretbox($plaintext, $nonce, $key));
        }
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($ciphertext === false) throw new \RuntimeException('Unable to encrypt TOTP secret.');
        return 'gcm:' . base64_encode($iv . $tag . $ciphertext);
    }

    private function decrypt(string $stored): ?string
    {
        $key = $this->encryptionKey();
        if (strpos($stored, 'sodium:') === 0 && function_exists('sodium_crypto_secretbox_open')) {
            $raw = base64_decode(substr($stored, 7), true);
            $len = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
            return $raw === false ? null : (sodium_crypto_secretbox_open(substr($raw, $len), substr($raw, 0, $len), $key) ?: null);
        }
        if (strpos($stored, 'gcm:') === 0) {
            $raw = base64_decode(substr($stored, 4), true);
            if ($raw === false || strlen($raw) < 28) return null;
            return openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16)) ?: null;
        }
        return null;
    }

    private function ensureTable(): void
    {
        $driver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $sql = $driver === 'sqlite'
            ? 'CREATE TABLE IF NOT EXISTS user_totp (user_id INTEGER PRIMARY KEY, secret_encrypted TEXT NOT NULL, enabled_at DATETIME NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)'
            : 'CREATE TABLE IF NOT EXISTS user_totp (user_id INT UNSIGNED NOT NULL PRIMARY KEY, secret_encrypted TEXT NOT NULL, enabled_at DATETIME NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, CONSTRAINT fk_user_totp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
        $this->pdo->exec($sql);
    }

    private function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $bits = '';
        foreach (str_split($data) as $char) $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        $out = '';
        foreach (str_split($bits, 5) as $chunk) $out .= $alphabet[bindec(str_pad($chunk, 5, '0'))];
        return $out;
    }

    private function base32Decode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $bits = '';
        foreach (str_split(strtoupper(preg_replace('/[^A-Z2-7]/i', '', $data))) as $char) $bits .= str_pad(decbin(strpos($alphabet, $char)), 5, '0', STR_PAD_LEFT);
        $out = '';
        foreach (str_split($bits, 8) as $chunk) if (strlen($chunk) === 8) $out .= chr(bindec($chunk));
        return $out;
    }
}
