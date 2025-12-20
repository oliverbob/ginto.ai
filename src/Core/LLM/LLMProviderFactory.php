<?php

declare(strict_types=1);

namespace App\Core\LLM;

use App\Core\LLM\Providers\OpenAICompatibleProvider;
use App\Core\LLM\Providers\AnthropicProvider;

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
    ];

    /**
     * Create a provider instance.
     *
     * @param string $provider Provider name or alias (openai, groq, anthropic, together, fireworks)
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
        return ['openai', 'groq', 'cerebras', 'anthropic', 'together', 'fireworks'];
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
