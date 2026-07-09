<?php
namespace Ginto\Models;

use Ginto\Core\Database;
use Ginto\Support\Crypto;
use Ginto\Support\Env;
use Medoo\Medoo;

/**
 * Single-row bot settings.
 *
 * Credentials resolve from .env by default (BINANCE_API_KEY / BINANCE_API_SECRET),
 * so the bot works out of the box like the rest of the codebase. A saved DB row
 * (with the secret encrypted at rest) overrides the .env values when present.
 */
class GtbSettings
{
    private Medoo $db;
    private string $table = 'gtb_settings';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** Raw DB settings row, or null if none / table missing. */
    public function get(): ?array
    {
        try {
            $row = $this->db->get($this->table, '*', ['ORDER' => ['id' => 'ASC']]);
            return is_array($row) ? $row : null;
        } catch (\Throwable $e) {
            return null; // table may not exist yet
        }
    }

    /** Effective API key: DB row overrides .env. */
    public function getApiKey(): string
    {
        $row = $this->get();
        if (!empty($row['binance_api_key'])) {
            return (string)$row['binance_api_key'];
        }
        return (string)(Env::get('BINANCE_API_KEY', '') ?? '');
    }

    /** Effective API secret (decrypted): DB row overrides .env. */
    public function getApiSecret(): string
    {
        $row = $this->get();
        if (!empty($row['binance_api_secret_enc'])) {
            return Crypto::decrypt($row['binance_api_secret_enc']);
        }
        return (string)(Env::get('BINANCE_API_SECRET', '') ?? '');
    }

    /** Testnet flag: DB row overrides .env (BINANCE_TESTNET). */
    public function isTestnet(): bool
    {
        $row = $this->get();
        if ($row !== null && isset($row['is_testnet'])) {
            return (int)$row['is_testnet'] === 1;
        }
        return Env::bool('BINANCE_TESTNET', false);
    }

    /** True when both an API key and secret are available from either source. */
    public function isConfigured(): bool
    {
        return $this->getApiKey() !== '' && $this->getApiSecret() !== '';
    }

    /** Insert/update the single settings row (secret encrypted at rest). */
    public function save(string $apiKey, string $apiSecret, bool $isTestnet = false): bool
    {
        try {
            $data = [
                'binance_api_key'        => $apiKey,
                'binance_api_secret_enc' => Crypto::encrypt($apiSecret),
                'is_testnet'             => $isTestnet ? 1 : 0,
            ];
            $existing = $this->get();
            if ($existing) {
                $this->db->update($this->table, $data, ['id' => $existing['id']]);
            } else {
                $this->db->insert($this->table, $data);
            }
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
