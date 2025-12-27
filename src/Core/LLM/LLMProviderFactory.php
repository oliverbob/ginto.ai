<?php

declare(strict_types=1);

namespace App\Core\LLM;

use App\Core\LLM\Providers\OpenAICompatibleProvider;
use App\Core\LLM\Providers\AnthropicProvider;
use App\Core\LLM\Providers\OllamaProvider;

/**
 * Factory for creating LLM provider instances.
 * 
 * Supports automatic provider detection from environment variables
 * and manual provider selection.
 */
class LLMProviderFactory
{
    /**
     * Provider aliases for convenience.
     */
    protected static array $aliases = [
        'gpt' => 'openai',
        'gpt-4' => 'openai',
        'gpt-4o' => 'openai',
        'claude' => 'anthropic',
        'llama' => 'groq',
        'llama-3' => 'groq',
        'mixtral' => 'groq',
        'ollama-cloud' => 'ollama',
    ];

    /**
     * Create a provider instance.
     *
     * @param string $provider Provider name or alias (openai, groq, anthropic, together, fireworks, local, ginto)
     * @param array $config Optional configuration override
     * @return LLMProviderInterface
     */
    public static function create(string $provider, array $config = []): LLMProviderInterface
    {
        // Resolve aliases
        $provider = strtolower($provider);
        $provider = self::$aliases[$provider] ?? $provider;

        return match ($provider) {
            'anthropic', 'claude' => new AnthropicProvider($config),
            'openai' => new OpenAICompatibleProvider('openai', $config),
            'groq' => new OpenAICompatibleProvider('groq', $config),
            'cerebras' => new OpenAICompatibleProvider('cerebras', $config),
            'together' => new OpenAICompatibleProvider('together', $config),
            'fireworks' => new OpenAICompatibleProvider('fireworks', $config),
            'local', 'ginto' => new OpenAICompatibleProvider('local', $config),
            'ollama' => new OllamaProvider($config),
            default => throw new \InvalidArgumentException("Unknown provider: $provider"),
        };
    }

    /**
     * Auto-detect the best available provider based on configured API keys.
     *
     * Priority: Groq > OpenAI > Anthropic > Together > Fireworks
     *
     * @param array $config Optional configuration
     * @return LLMProviderInterface|null
     */
    public static function autoDetect(array $config = []): ?LLMProviderInterface
    {
        $priority = ['groq', 'openai', 'anthropic', 'together', 'fireworks'];

        foreach ($priority as $provider) {
            try {
                $instance = self::create($provider, $config);
                if ($instance->isConfigured()) {
                    return $instance;
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        return null;
    }

    /**
     * Create a provider from environment configuration.
     * 
     * Reads LLM_PROVIDER and LLM_MODEL from environment.
     *
     * @return LLMProviderInterface
     */
    public static function fromEnv(): LLMProviderInterface
    {
        $provider = getenv('LLM_PROVIDER') ?: ($_ENV['LLM_PROVIDER'] ?? null);
        $model = getenv('LLM_MODEL') ?: ($_ENV['LLM_MODEL'] ?? null);

        $config = [];
        if ($model) {
            $config['model'] = $model;
        }

        if ($provider) {
            return self::create($provider, $config);
        }

        // Auto-detect if no provider specified
        $detected = self::autoDetect($config);
        if (!$detected) {
            throw new \RuntimeException('No LLM provider configured. Set LLM_PROVIDER or configure an API key.');
        }

        return $detected;
    }

    /**
     * Get all available providers.
     *
     * @return array Provider names
     */
    public static function getAvailableProviders(): array
    {
        return ['local', 'openai', 'groq', 'cerebras', 'anthropic', 'together', 'fireworks', 'ollama'];
    }

    /**
     * Create a provider instance using database API key if available.
     * Falls back to environment variable if no database key exists.
     *
     * @param string $provider Provider name
     * @param \Medoo\Medoo|null $db Database connection
     * @return LLMProviderInterface
     */
    public static function createWithDb(string $provider, ?\Medoo\Medoo $db = null): LLMProviderInterface
    {
        $config = [];
        
        // Try to get API key from database first
        if ($db) {
            try {
                $keyManager = new \App\Core\ProviderKeyManager($db);
                $keyData = $keyManager->getAvailableKey($provider);
                if ($keyData && !empty($keyData['api_key'])) {
                    $config['api_key'] = $keyData['api_key'];
                }
            } catch (\Throwable $e) {
                // Database error, fall back to env
            }
        }
        
        return self::create($provider, $config);
    }

    /**
     * Check if a provider has any active keys in the database.
     *
     * @param string $provider Provider name
     * @param \Medoo\Medoo $db Database connection
     * @return bool
     */
    public static function hasDbKey(string $provider, \Medoo\Medoo $db): bool
    {
        try {
            return $db->has('provider_keys', [
                'provider' => $provider,
                'is_active' => 1,
            ]);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Check which providers are configured (have API keys).
     *
     * @return array Configured provider names
     */
    public static function getConfiguredProviders(): array
    {
        $configured = [];
        foreach (self::getAvailableProviders() as $provider) {
            try {
                $instance = self::create($provider);
                if ($instance->isConfigured()) {
                    $configured[] = $provider;
                }
            } catch (\Throwable $e) {
                continue;
            }
        }
        return $configured;
    }
}
