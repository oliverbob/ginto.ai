<?php
namespace Ginto\Models;

use Ginto\Support\Env;

/**
 * Binance credentials for the trading bot, resolved from .env.
 *
 * Stores BOTH a mainnet and a testnet key pair; the BINANCE_TESTNET flag selects
 * which pair is active, so switching environments needs no re-entry of keys.
 *
 *   BINANCE_TESTNET                   = true|false   (active environment)
 *   BINANCE_API_KEY / _SECRET                        (mainnet)
 *   BINANCE_TESTNET_API_KEY / _SECRET                (testnet)
 */
class GtbSettings
{
    public function isTestnet(): bool
    {
        return Env::bool('BINANCE_TESTNET', false);
    }

    /** Active API key (matches the testnet flag). */
    public function getApiKey(): string
    {
        return $this->isTestnet() ? $this->testnetApiKey() : $this->mainnetApiKey();
    }

    /** Active API secret (matches the testnet flag). */
    public function getApiSecret(): string
    {
        return $this->isTestnet() ? $this->testnetApiSecret() : $this->mainnetApiSecret();
    }

    /** True when the ACTIVE environment has both key and secret set. */
    public function isConfigured(): bool
    {
        return $this->getApiKey() !== '' && $this->getApiSecret() !== '';
    }

    // --- per-environment accessors (used by the settings form) --------------
    public function mainnetApiKey(): string    { return (string) (Env::get('BINANCE_API_KEY', '') ?? ''); }
    public function mainnetApiSecret(): string { return (string) (Env::get('BINANCE_API_SECRET', '') ?? ''); }
    public function testnetApiKey(): string    { return (string) (Env::get('BINANCE_TESTNET_API_KEY', '') ?? ''); }
    public function testnetApiSecret(): string { return (string) (Env::get('BINANCE_TESTNET_API_SECRET', '') ?? ''); }

    public function mainnetConfigured(): bool { return $this->mainnetApiKey() !== '' && $this->mainnetApiSecret() !== ''; }
    public function testnetConfigured(): bool { return $this->testnetApiKey() !== '' && $this->testnetApiSecret() !== ''; }
}
