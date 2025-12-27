<?php

declare(strict_types=1);

namespace App\Core\LLM;

use Medoo\Medoo;

/**
 * Unified Provider Registry - Central source of truth for all LLM provider configuration.
 * 
 * Responsibilities:
 * - Manage API keys (DB first, .env fallback)
 * - Fetch and cache live model lists from provider APIs
 * - Detect model capabilities (vision, reasoning/thinking)
 * - Create properly configured provider instances
 */
class ProviderRegistry
{
    private static ?self $instance = null;
    private ?Medoo $db = null;
    
    // Cache for fetched models (in-memory, persisted to file)
    private array $modelCache = [];
    private string $cacheDir;
    private int $cacheTtl = 3600; // 1 hour
    
    // Provider configurations
    private const PROVIDER_CONFIG = [
        'groq' => [
            'base_url' => 'https://api.groq.com/openai/v1/',
            'env_key' => 'GROQ_API_KEY',
            'display_name' => 'Groq',
            'supports_tools' => true,
        ],
        'cerebras' => [
            'base_url' => 'https://api.cerebras.ai/v1/',
            'env_key' => 'CEREBRAS_API_KEY',
            'display_name' => 'Cerebras',
            'supports_tools' => true,
        ],
        'openai' => [
            'base_url' => 'https://api.openai.com/v1/',
            'env_key' => 'OPENAI_API_KEY',
            'display_name' => 'OpenAI',
            'supports_tools' => true,
        ],
        'anthropic' => [
            'base_url' => 'https://api.anthropic.com/v1/',
            'env_key' => 'ANTHROPIC_API_KEY',
            'display_name' => 'Anthropic',
            'supports_tools' => true,
            'style' => 'anthropic', // Non-OpenAI compatible
        ],
        'together' => [
            'base_url' => 'https://api.together.xyz/v1/',
            'env_key' => 'TOGETHER_API_KEY',
            'display_name' => 'Together',
            'supports_tools' => true,
        ],
        'fireworks' => [
            'base_url' => 'https://api.fireworks.ai/inference/v1/',
            'env_key' => 'FIREWORKS_API_KEY',
            'display_name' => 'Fireworks',
            'supports_tools' => true,
        ],
        'ollama' => [
            'base_url' => 'http://localhost:11434/v1/',
            'env_key' => 'OLLAMA_API_KEY',
            'display_name' => 'Ollama',
            'supports_tools' => true,
            'no_auth' => true,
        ],
        'local' => [
            'base_url' => 'http://127.0.0.1:8034/v1/',
            'env_key' => 'LOCAL_LLM_API_KEY',
            'display_name' => 'Ginto AI Default - Llama.cpp',
            'supports_tools' => false,
            'no_auth' => true,
        ],
    ];
    
    // Known vision/multimodal models (pattern matching)
    private const VISION_PATTERNS = [
        'vision', 'vl', 'visual', 'image', 'multimodal', 'mm',
        'gpt-4o', 'gpt-4-turbo', 'claude-3', 'gemini', 'llava',
        'llama-3.2-11b-vision', 'llama-3.2-90b-vision',
        'llama-4-scout', 'llama-4-maverick', // Llama 4 multimodal models
        'smolvlm', 'qwen-vl', 'qwen2-vl', 'pixtral',
    ];
    
    // Known reasoning/thinking models (pattern matching)
    private const THINKING_PATTERNS = [
        'o1', 'o3', 'deepseek-r1', 'qwq', 'thinking', 'reason',
        'r1-distill', 'reasoner',
    ];
    
    // Known TTS (text-to-speech) models (pattern matching)
    private const TTS_PATTERNS = [
        'tts', 'speech', 'playai', 'orpheus', 'voice',
        'elevenlabs', 'bark', 'xtts', 'coqui',
    ];
    
    // Known STT (speech-to-text) models (pattern matching)
    private const STT_PATTERNS = [
        'whisper', 'stt', 'transcribe', 'speech-to-text',
    ];
    
    private function __construct()
    {
        $this->cacheDir = (defined('STORAGE_PATH') ? STORAGE_PATH : dirname(dirname(__DIR__, 3))) . '/cache';
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }
        $this->loadCacheFromDisk();
    }
    
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Set database connection for key management.
     */
    public function setDatabase(Medoo $db): self
    {
        $this->db = $db;
        return $this;
    }
    
    /**
     * Get all available provider names.
     */
    public function getProviderNames(): array
    {
        return array_keys(self::PROVIDER_CONFIG);
    }
    
    /**
     * Get provider configuration.
     */
    public function getProviderConfig(string $provider): ?array
    {
        return self::PROVIDER_CONFIG[$provider] ?? null;
    }
    
    /**
     * Get API key for a provider (DB first, then .env).
     */
    public function getApiKey(string $provider): ?string
    {
        // 1. Try database first
        if ($this->db) {
            try {
                $keyManager = new \App\Core\ProviderKeyManager($this->db);
                $keyData = $keyManager->getAvailableKey($provider);
                if ($keyData && !empty($keyData['api_key'])) {
                    return $keyData['api_key'];
                }
            } catch (\Throwable $e) {
                // DB error, fall through to env
            }
        }
        
        // 2. Fall back to environment variable
        $config = self::PROVIDER_CONFIG[$provider] ?? null;
        if ($config) {
            $envKey = $config['env_key'];
            $value = getenv($envKey) ?: ($_ENV[$envKey] ?? null);
            if ($value) {
                return $value;
            }
        }
        
        return null;
    }
    
    /**
     * Check if a provider has a database key.
     */
    public function hasDbKey(string $provider): bool
    {
        if (!$this->db) {
            return false;
        }
        
        try {
            return $this->db->has('provider_keys', [
                'provider' => $provider,
                'is_active' => 1,
            ]);
        } catch (\Throwable $e) {
            return false;
        }
    }
    
    /**
     * Check if a provider is configured (has API key).
     */
    public function isConfigured(string $provider): bool
    {
        $config = self::PROVIDER_CONFIG[$provider] ?? null;
        if (!$config) {
            return false;
        }
        
        // Local providers don't need API key
        if (!empty($config['no_auth'])) {
            if ($provider === 'local') {
                // Check if local LLM server is running
                try {
                    $localConfig = LocalLLMConfig::getInstance();
                    return $localConfig->isEnabled() && (
                        $localConfig->isReasoningServerHealthy() || 
                        $localConfig->isVisionServerHealthy()
                    );
                } catch (\Throwable $e) {
                    return false;
                }
            }
            if ($provider === 'ollama') {
                // Check if Ollama is running
                return $this->isOllamaRunning();
            }
            return true;
        }
        
        return $this->getApiKey($provider) !== null;
    }
    
    /**
     * Check if Ollama server is running.
     */
    private function isOllamaRunning(): bool
    {
        try {
            $ch = curl_init('http://localhost:11434/api/tags');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 2,
                CURLOPT_CONNECTTIMEOUT => 1,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return $httpCode === 200;
        } catch (\Throwable $e) {
            return false;
        }
    }
    
    /**
     * Fetch models from provider API (with caching).
     */
    public function getModels(string $provider, bool $forceRefresh = false): array
    {
        $cacheKey = "models_{$provider}";
        
        // Check cache first
        if (!$forceRefresh && isset($this->modelCache[$cacheKey])) {
            $cached = $this->modelCache[$cacheKey];
            if (time() - ($cached['fetched_at'] ?? 0) < $this->cacheTtl) {
                return $cached['models'] ?? [];
            }
        }
        
        // Fetch from API
        $models = $this->fetchModelsFromApi($provider);
        
        // Cache the result
        $this->modelCache[$cacheKey] = [
            'models' => $models,
            'fetched_at' => time(),
        ];
        $this->saveCacheToDisk();
        
        return $models;
    }
    
    /**
     * Fetch models from provider's /v1/models endpoint.
     */
    private function fetchModelsFromApi(string $provider): array
    {
        $config = self::PROVIDER_CONFIG[$provider] ?? null;
        if (!$config) {
            return [];
        }
        
        // Special handling for local provider
        if ($provider === 'local') {
            return $this->getLocalModels();
        }
        
        // Special handling for Ollama
        if ($provider === 'ollama') {
            return $this->fetchOllamaModels();
        }
        
        $apiKey = $this->getApiKey($provider);
        if (!$apiKey && empty($config['no_auth'])) {
            return [];
        }
        
        $baseUrl = $config['base_url'];
        $modelsUrl = rtrim($baseUrl, '/') . '/models';
        
        try {
            $headers = ['Content-Type: application/json'];
            if ($apiKey) {
                $headers[] = 'Authorization: Bearer ' . $apiKey;
            }
            
            $ch = curl_init($modelsUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_HTTPHEADER => $headers,
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200 || !$response) {
                return [];
            }
            
            $data = json_decode($response, true);
            if (!isset($data['data']) || !is_array($data['data'])) {
                return [];
            }
            
            // Parse models with capabilities
            $models = [];
            foreach ($data['data'] as $model) {
                $modelId = $model['id'] ?? '';
                if (!$modelId) continue;
                
                $models[] = [
                    'id' => $modelId,
                    'name' => $model['name'] ?? $modelId,
                    'owned_by' => $model['owned_by'] ?? $provider,
                    'created' => $model['created'] ?? null,
                    'capabilities' => $this->detectCapabilities($modelId),
                ];
            }
            
            // Sort by name
            usort($models, fn($a, $b) => strcasecmp($a['name'], $b['name']));
            
            return $models;
            
        } catch (\Throwable $e) {
            return [];
        }
    }
    
    /**
     * Get local Ginto AI models from llama.cpp servers.
     */
    private function getLocalModels(): array
    {
        $models = [];
        
        try {
            $localConfig = LocalLLMConfig::getInstance();
            
            // Fetch models from reasoning server (port 8034)
            if ($localConfig->isReasoningServerHealthy()) {
                $reasoningUrl = rtrim($localConfig->getReasoningUrl(), '/') . '/models';
                $reasoningModels = $this->fetchModelsFromUrl($reasoningUrl);
                foreach ($reasoningModels as $model) {
                    $modelId = $model['id'] ?? '';
                    if (!$modelId) continue;
                    $models[$modelId] = [
                        'id' => $modelId,
                        'name' => $model['id'],
                        'owned_by' => 'llama.cpp',
                        'server' => 'reasoning',
                        'capabilities' => $this->detectCapabilities($modelId),
                    ];
                }
            }
            
            // Fetch models from vision server (port 8035)
            if ($localConfig->isVisionServerHealthy()) {
                $visionUrl = rtrim($localConfig->getVisionUrl(), '/') . '/models';
                $visionModels = $this->fetchModelsFromUrl($visionUrl);
                foreach ($visionModels as $model) {
                    $modelId = $model['id'] ?? '';
                    if (!$modelId) continue;
                    // Mark as vision-capable
                    $caps = $this->detectCapabilities($modelId);
                    $caps['vision'] = true; // Vision server models always have vision
                    
                    if (isset($models[$modelId])) {
                        // Model exists on both servers, merge capabilities
                        $models[$modelId]['capabilities']['vision'] = true;
                        $models[$modelId]['server'] = 'both';
                    } else {
                        $models[$modelId] = [
                            'id' => $modelId,
                            'name' => $model['id'],
                            'owned_by' => 'llama.cpp',
                            'server' => 'vision',
                            'capabilities' => $caps,
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            // Fall back to default if servers unavailable
        }
        
        // Always include ginto-default as a unified option
        if (empty($models)) {
            $models['ginto-default'] = [
                'id' => 'ginto-default',
                'name' => 'Ginto AI - Default',
                'owned_by' => 'local',
                'capabilities' => [
                    'vision' => true,
                    'thinking' => false,
                    'tools' => false,
                ],
            ];
        }
        
        return array_values($models);
    }
    
    /**
     * Fetch models from a URL (helper for local servers).
     */
    private function fetchModelsFromUrl(string $url): array
    {
        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 3,
                CURLOPT_CONNECTTIMEOUT => 2,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200 || !$response) {
                return [];
            }
            
            $data = json_decode($response, true);
            return $data['data'] ?? [];
        } catch (\Throwable $e) {
            return [];
        }
    }
    
    /**
     * Fetch Ollama models.
     */
    private function fetchOllamaModels(): array
    {
        try {
            $ch = curl_init('http://localhost:11434/api/tags');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200 || !$response) {
                return [];
            }
            
            $data = json_decode($response, true);
            $models = [];
            
            foreach ($data['models'] ?? [] as $model) {
                $name = $model['name'] ?? '';
                if (!$name) continue;
                
                $models[] = [
                    'id' => $name,
                    'name' => $name,
                    'owned_by' => 'ollama',
                    'size' => $model['size'] ?? null,
                    'capabilities' => $this->detectCapabilities($name),
                ];
            }
            
            return $models;
            
        } catch (\Throwable $e) {
            return [];
        }
    }
    
    /**
     * Detect model capabilities from its name/ID.
     */
    public function detectCapabilities(string $modelId): array
    {
        $modelLower = strtolower($modelId);
        
        $hasVision = false;
        foreach (self::VISION_PATTERNS as $pattern) {
            if (str_contains($modelLower, strtolower($pattern))) {
                $hasVision = true;
                break;
            }
        }
        
        $hasThinking = false;
        foreach (self::THINKING_PATTERNS as $pattern) {
            if (str_contains($modelLower, strtolower($pattern))) {
                $hasThinking = true;
                break;
            }
        }
        
        $hasTts = false;
        foreach (self::TTS_PATTERNS as $pattern) {
            if (str_contains($modelLower, strtolower($pattern))) {
                $hasTts = true;
                break;
            }
        }
        
        $hasStt = false;
        foreach (self::STT_PATTERNS as $pattern) {
            if (str_contains($modelLower, strtolower($pattern))) {
                $hasStt = true;
                break;
            }
        }
        
        // Tool support - most modern models support it
        $hasTools = !$hasThinking; // Thinking models typically don't support tools well
        
        return [
            'vision' => $hasVision,
            'thinking' => $hasThinking,
            'tts' => $hasTts,
            'stt' => $hasStt,
            'tools' => $hasTools,
        ];
    }
    
    /**
     * Get a specific model's capabilities.
     */
    public function getModelCapabilities(string $provider, string $modelId): array
    {
        $models = $this->getModels($provider);
        
        foreach ($models as $model) {
            // Handle both array format and string format
            $id = is_array($model) ? ($model['id'] ?? '') : $model;
            if ($id === $modelId) {
                return is_array($model) && isset($model['capabilities']) 
                    ? $model['capabilities'] 
                    : $this->detectCapabilities($modelId);
            }
        }
        
        // Model not found in cache, detect from name
        return $this->detectCapabilities($modelId);
    }
    
    /**
     * Create a configured provider instance.
     */
    public function createProvider(string $provider, array $configOverride = []): LLMProviderInterface
    {
        $providerConfig = self::PROVIDER_CONFIG[$provider] ?? null;
        if (!$providerConfig) {
            throw new \InvalidArgumentException("Unknown provider: $provider");
        }
        
        $config = array_merge($configOverride, [
            'base_url' => $providerConfig['base_url'],
        ]);
        
        // Get API key if not provided
        if (!isset($config['api_key']) && empty($providerConfig['no_auth'])) {
            $config['api_key'] = $this->getApiKey($provider);
        }
        
        return LLMProviderFactory::create($provider, $config);
    }
    
    /**
     * Get all configured providers with their models.
     */
    public function getAllConfiguredProviders(): array
    {
        $result = [];
        
        foreach (self::PROVIDER_CONFIG as $name => $config) {
            if ($this->isConfigured($name)) {
                $models = $this->getModels($name);
                $result[$name] = [
                    'display_name' => $config['display_name'],
                    'configured' => true,
                    'models' => $models,
                    'supports_tools' => $config['supports_tools'] ?? true,
                ];
            }
        }
        
        return $result;
    }
    
    /**
     * Load cache from disk.
     */
    private function loadCacheFromDisk(): void
    {
        $cacheFile = $this->cacheDir . '/provider_models.json';
        if (file_exists($cacheFile)) {
            $data = @json_decode(file_get_contents($cacheFile), true);
            if (is_array($data)) {
                $this->modelCache = $data;
            }
        }
    }
    
    /**
     * Save cache to disk.
     */
    private function saveCacheToDisk(): void
    {
        $cacheFile = $this->cacheDir . '/provider_models.json';
        @file_put_contents($cacheFile, json_encode($this->modelCache, JSON_PRETTY_PRINT));
    }
    
    /**
     * Clear model cache.
     */
    public function clearCache(): void
    {
        $this->modelCache = [];
        $cacheFile = $this->cacheDir . '/provider_models.json';
        @unlink($cacheFile);
    }
    
    /**
     * Get provider priority order for UI display.
     * Local/offline providers first, then cloud APIs.
     */
    public static function getProviderPriority(): array
    {
        return ['local', 'ollama', 'cerebras', 'groq', 'openai', 'anthropic', 'together', 'fireworks'];
    }
}
