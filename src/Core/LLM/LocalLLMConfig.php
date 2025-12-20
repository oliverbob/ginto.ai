<?php

declare(strict_types=1);

namespace App\Core\LLM;

/**
 * Local LLM Configuration and Helper
 * 
 * This class handles configuration for local LLM servers (llama.cpp, ollama, etc.)
 * These are fundamentally different from cloud providers:
 * 
 * KEY DIFFERENCES FROM CLOUD PROVIDERS:
 * =====================================
 * 1. NO API KEY REQUIRED - Local servers run on your machine
 * 2. NO RATE LIMITS - You control the hardware, no external throttling
 * 3. NO COSTS - Free to use (only electricity/hardware costs)
 * 4. PRIVACY - Data never leaves your machine
 * 5. OFFLINE CAPABLE - Works without internet
 * 6. CUSTOM MODELS - Run any GGUF model you want
 * 
 * SUPPORTED SERVERS:
 * ==================
 * - llama.cpp (llama-server) - Recommended, lightweight
 * - Ollama - Easy to use, model management built-in
 * - LM Studio - GUI-based, good for beginners
 * - text-generation-webui - Feature-rich, many model formats
 * 
 * CONFIGURATION (.env):
 * =====================
 * LOCAL_LLM_URL=http://127.0.0.1:8034/v1     # API endpoint (OpenAI-compatible)
 * LOCAL_LLM_MODEL=local                        # Model name (or auto-detect)
 * LOCAL_LLM_PRIMARY=true                       # Use as primary provider (skip cloud)
 * 
 * VISION MODEL (optional):
 * ========================
 * VISION_MODEL_URL=http://127.0.0.1:8033/v1  # Separate server for vision
 * VISION_MODEL_NAME=SmolVLM2                  # Vision model name
 * VISION_MAX_TOKENS=512                       # Max tokens for vision responses
 * 
 * HUGGINGFACE MODELS (for auto-download):
 * =======================================
 * REASONING_HF_MODEL=lm-kit/qwen-3-0.6b-instruct-gguf
 * VISION_HF_MODEL=ggml-org/SmolVLM2-500M-Video-Instruct-GGUF
 */
class LocalLLMConfig
{
    private static ?self $instance = null;
    
    private string $reasoningUrl;
    private string $reasoningModel;
    private string $visionUrl;
    private string $visionModel;
    private int $visionMaxTokens;
    private bool $isPrimary;
    private bool $isEnabled;
    
    private function __construct()
    {
        $this->reasoningUrl = $this->getEnv('LOCAL_LLM_URL', 'http://127.0.0.1:8034/v1');
        $this->reasoningModel = $this->getEnv('LOCAL_LLM_MODEL', 'local');
        $this->visionUrl = $this->getEnv('VISION_MODEL_URL', 'http://127.0.0.1:8033/v1');
        $this->visionModel = $this->getEnv('VISION_MODEL_NAME', 'SmolVLM2');
        $this->visionMaxTokens = (int) $this->getEnv('VISION_MAX_TOKENS', '512');
        $this->isPrimary = $this->toBool($this->getEnv('LOCAL_LLM_PRIMARY', 'false'));
        $this->isEnabled = !empty($this->reasoningUrl);
    }
    
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get environment variable with fallback
     */
    private function getEnv(string $key, string $default = ''): string
    {
        return getenv($key) ?: ($_ENV[$key] ?? $default);
    }
    
    /**
     * Convert string to boolean
     */
    private function toBool(string $value): bool
    {
        return in_array(strtolower($value), ['true', '1', 'yes', 'on'], true);
    }
    
    // =========================================================================
    // CONFIGURATION GETTERS
    // =========================================================================
    
    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }
    
    public function isPrimary(): bool
    {
        return $this->isPrimary;
    }
    
    public function getReasoningUrl(): string
    {
        return $this->reasoningUrl;
    }
    
    public function getReasoningModel(): string
    {
        return $this->reasoningModel;
    }
    
    public function getVisionUrl(): string
    {
        return $this->visionUrl;
    }
    
    public function getVisionModel(): string
    {
        return $this->visionModel;
    }
    
    public function getVisionMaxTokens(): int
    {
        return $this->visionMaxTokens;
    }
    
    // =========================================================================
    // SERVER HEALTH CHECKS
    // =========================================================================
    
    /**
     * Check if the reasoning model server is running
     */
    public function isReasoningServerHealthy(): bool
    {
        return $this->checkServerHealth($this->reasoningUrl);
    }
    
    /**
     * Check if the vision model server is running
     */
    public function isVisionServerHealthy(): bool
    {
        return $this->checkServerHealth($this->visionUrl);
    }
    
    /**
     * Check server health by calling /models endpoint
     */
    private function checkServerHealth(string $baseUrl): bool
    {
        try {
            $url = rtrim($baseUrl, '/') . '/models';
            $ctx = stream_context_create([
                'http' => [
                    'timeout' => 2,
                    'method' => 'GET',
                ]
            ]);
            $response = @file_get_contents($url, false, $ctx);
            return $response !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }
    
    /**
     * Get list of models from the reasoning server
     */
    public function getAvailableModels(): array
    {
        try {
            $url = rtrim($this->reasoningUrl, '/') . '/models';
            $response = @file_get_contents($url);
            if ($response === false) {
                return [];
            }
            $data = json_decode($response, true);
            $models = [];
            
            // Handle both OpenAI format and llama.cpp format
            if (isset($data['data'])) {
                foreach ($data['data'] as $model) {
                    $models[] = $model['id'] ?? $model['name'] ?? 'unknown';
                }
            } elseif (isset($data['models'])) {
                foreach ($data['models'] as $model) {
                    $models[] = $model['name'] ?? $model['model'] ?? 'unknown';
                }
            }
            
            return $models;
        } catch (\Throwable $e) {
            return [];
        }
    }
    
    // =========================================================================
    // PROVIDER CREATION
    // =========================================================================
    
    /**
     * Create provider configuration for reasoning (text) requests
     */
    public function getReasoningProviderConfig(): array
    {
        return [
            'provider' => 'local',
            'api_key' => 'local', // Not required, but needed for provider initialization
            'model' => $this->reasoningModel,
            'base_url' => $this->reasoningUrl,
        ];
    }
    
    /**
     * Create provider configuration for vision requests
     */
    public function getVisionProviderConfig(): array
    {
        return [
            'provider' => 'local',
            'api_key' => 'local',
            'model' => $this->visionModel,
            'base_url' => $this->visionUrl,
            'max_tokens' => $this->visionMaxTokens,
        ];
    }
    
    // =========================================================================
    // STATUS REPORTING
    // =========================================================================
    
    /**
     * Get comprehensive status of local LLM configuration
     */
    public function getStatus(): array
    {
        $reasoningHealthy = $this->isReasoningServerHealthy();
        $visionHealthy = $this->isVisionServerHealthy();
        
        return [
            'enabled' => $this->isEnabled,
            'primary' => $this->isPrimary,
            'reasoning' => [
                'url' => $this->reasoningUrl,
                'model' => $this->reasoningModel,
                'healthy' => $reasoningHealthy,
                'available_models' => $reasoningHealthy ? $this->getAvailableModels() : [],
            ],
            'vision' => [
                'url' => $this->visionUrl,
                'model' => $this->visionModel,
                'max_tokens' => $this->visionMaxTokens,
                'healthy' => $visionHealthy,
            ],
        ];
    }
}
