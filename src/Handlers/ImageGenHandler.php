<?php

declare(strict_types=1);

namespace Ginto\Handlers;

use Ginto\Database;
use PhpMcp\Server\Attributes\McpTool;

/**
 * ImageGenHandler - FastSD CPU image generation
 * 
 * This handler uses the FastSD CPU backend for ultra-fast (~1 second) image generation.
 * Features:
 * - SSE streaming for real-time progress updates
 * - Direct API calls to FastSD CPU server
 * - Saves generated images to local storage
 * - MCP tool for AI agent access
 */
class ImageGenHandler
{
    private $db = null;
    
    /** @var string SDCPU API endpoint (FastSD CPU with OpenVINO - ~1 second generation!) */
    private const SDCPU_API_URL = 'http://127.0.0.1:8888/api/generate';

    private function isEnvEnabled(string $key): bool
    {
        return strtolower(trim((string)($_ENV[$key] ?? getenv($key) ?? 'false'))) === 'true';
    }

    private function envValue(string $key, string $default = ''): string
    {
        return strtolower(trim((string)($_ENV[$key] ?? getenv($key) ?? $default)));
    }

    private function envRaw(string $key): string
    {
        return trim((string)($_ENV[$key] ?? getenv($key) ?? ''));
    }

    private function envIntInRange(string $key, int $min, int $max): ?int
    {
        $raw = $this->envRaw($key);
        if ($raw === '' || !is_numeric($raw)) {
            return null;
        }
        $value = (int)$raw;
        if ($value < $min || $value > $max) {
            return null;
        }
        return $value;
    }

    private function envFloatInRange(string $key, float $min, float $max): ?float
    {
        $raw = $this->envRaw($key);
        if ($raw === '' || !is_numeric($raw)) {
            return null;
        }
        $value = (float)$raw;
        if ($value < $min || $value > $max) {
            return null;
        }
        return $value;
    }

    private function wantsMultiSubject(string $prompt): bool
    {
        $normalized = strtolower(trim($prompt));
        if ($normalized === '') {
            return false;
        }

        if (preg_match('/\b(\d+)\s+(people|persons|characters|subjects|figures)\b/', $normalized)) {
            return true;
        }

        $multiHints = [
            'two ',
            'three ',
            'four ',
            'five ',
            'group',
            'crowd',
            'multiple',
            'pair',
            'twins',
            'people',
            'characters',
            'subjects',
            'team',
            'family',
        ];

        foreach ($multiHints as $hint) {
            if (strpos($normalized, $hint) !== false) {
                return true;
            }
        }

        return false;
    }

    private function buildPromptPayload(string $prompt): array
    {
        if ($this->wantsMultiSubject($prompt)) {
            return ['prompt' => $prompt];
        }

        $singlePrompt = rtrim($prompt) . ', single subject, one character only, centered composition';
        return [
            'prompt' => $singlePrompt,
            'negative_prompt' => 'duplicate subject, multiple people, multiple characters, twins, clone, mirrored person, group photo',
        ];
    }

    private function resolveImageGenModelId(): ?string
    {
        $modelId = trim($this->envRaw('IMAGEGEN_MODEL_ID'));
        if ($modelId === '') {
            return null;
        }
        return $modelId;
    }

    private function resolveImageGenProfile(): array
    {
        $profile = $this->envValue('IMAGEGEN_PROFILE', 'balanced');
        switch ($profile) {
            case 'startup':
                $config = [
                    'profile' => 'startup',
                    'width' => 384,
                    'height' => 384,
                    'num_inference_steps' => 3,
                    'guidance_scale' => 0.8,
                ];
                break;
            case 'fast':
                $config = [
                    'profile' => 'fast',
                    'width' => 512,
                    'height' => 384,
                    'num_inference_steps' => 3,
                    'guidance_scale' => 0.9,
                ];
                break;
            case 'quality':
                $config = [
                    'profile' => 'quality',
                    'width' => 768,
                    'height' => 512,
                    'num_inference_steps' => 8,
                    'guidance_scale' => 1.5,
                ];
                break;
            case 'ultra':
                $config = [
                    'profile' => 'ultra',
                    'width' => 1024,
                    'height' => 576,
                    'num_inference_steps' => 12,
                    'guidance_scale' => 2.0,
                ];
                break;
            default:
                $config = [
                    'profile' => 'balanced',
                    'width' => 512,
                    'height' => 384,
                    'num_inference_steps' => 4,
                    'guidance_scale' => 1.0,
                ];
                break;
        }

        $stepsOverride = $this->envIntInRange('IMAGEGEN_STEPS', 1, 50);
        if ($stepsOverride !== null) {
            $config['num_inference_steps'] = $stepsOverride;
        }

        $guidanceOverride = $this->envFloatInRange('IMAGEGEN_GUIDANCE_SCALE', 0.1, 20.0);
        if ($guidanceOverride !== null) {
            $config['guidance_scale'] = $guidanceOverride;
        }

        $widthOverride = $this->envIntInRange('IMAGEGEN_WIDTH', 256, 1536);
        if ($widthOverride !== null) {
            $config['width'] = $widthOverride;
        }

        $heightOverride = $this->envIntInRange('IMAGEGEN_HEIGHT', 256, 1536);
        if ($heightOverride !== null) {
            $config['height'] = $heightOverride;
        }

        return $config;
    }

    private function resolveSdcpuApiUrl(): string
    {
        $sdcpuTunnelEnabled = $this->isEnvEnabled('SDCPU_TUNNEL');

        $requestHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        $requestHost = preg_replace('/:\d+$/', '', $requestHost);
        $isLocalRequest = in_array($requestHost, ['localhost', '127.0.0.1', '::1'], true);

        // SDCPU_TUNNEL=false is a hard local override.
        if (!$sdcpuTunnelEnabled && $isLocalRequest) {
            return $this->resolveLocalTunnelBaseUrl() . '/api/generate';
        }

        if (!$sdcpuTunnelEnabled) {
            return self::SDCPU_API_URL;
        }

        // On local host (PC2), use local tunnel relay so requests are parsed the same way as tunnel flow.
        if ($isLocalRequest) {
            return $this->resolveLocalTunnelBaseUrl() . '/api/generate';
        }

        $computeMode = $this->envValue('IMAGEGEN_COMPUTE_MODE', 'auto');
        if ($computeMode === 'gpu') {
            return 'https://vision.ginto.ai/api/generate';
        }
        if ($computeMode === 'cpu') {
            return self::SDCPU_API_URL;
        }

        $sdcpuActive = $this->isEnvEnabled('SDCPU_ACTIVE');
        $sdcpuTunnel = $sdcpuActive && $sdcpuTunnelEnabled;
        if ($sdcpuTunnel) {
            return 'https://vision.ginto.ai/api/generate';
        }
        return self::SDCPU_API_URL;
    }

    private function resolveLocalTunnelBaseUrl(): string
    {
        $localRelayPort = (int)($this->envRaw('TUNNEL_RELAY_LOCAL_PORT') ?: '18080');
        if ($localRelayPort < 1024 || $localRelayPort > 65535) {
            $localRelayPort = 18080;
        }

        return 'http://127.0.0.1:' . $localRelayPort;
    }

    private function consumeSseEventFromBuffer(string &$buffer): ?string
    {
        if (!preg_match('/\r\n\r\n|\n\n|\r\r/', $buffer, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $separator = $matches[0][0];
        $offset = $matches[0][1];
        $event = substr($buffer, 0, $offset);
        $buffer = substr($buffer, $offset + strlen($separator));

        return trim($event);
    }

    private function decodeSseEventPayload(string $event): ?array
    {
        $lines = preg_split('/\r\n|\n|\r/', trim($event)) ?: [];
        $dataLines = [];

        foreach ($lines as $line) {
            if (strpos($line, 'data:') === 0) {
                $dataLines[] = ltrim(substr($line, 5));
            }
        }

        if ($dataLines === []) {
            return null;
        }

        $json = implode("\n", $dataLines);
        $decoded = @json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function normalizeImageResult(?array $result): ?array
    {
        if (!is_array($result) || $result === []) {
            return null;
        }

        $imageValue = $result['image'] ?? null;

        if (!is_string($imageValue) || trim($imageValue) === '') {
            $images = $result['images'] ?? null;
            if (is_array($images) && isset($images[0])) {
                if (is_string($images[0])) {
                    $imageValue = $images[0];
                } elseif (is_array($images[0])) {
                    $imageValue = $images[0]['image'] ?? ($images[0]['b64_json'] ?? null);
                }
            }
        }

        if ((!is_string($imageValue) || trim($imageValue) === '') && isset($result['data'])) {
            $data = $result['data'];
            if (is_array($data)) {
                if (isset($data[0]) && is_array($data[0])) {
                    $imageValue = $data[0]['b64_json'] ?? ($data[0]['image'] ?? null);
                } elseif (isset($data['image']) && is_string($data['image'])) {
                    $imageValue = $data['image'];
                }
            }
        }

        if (!is_string($imageValue) || trim($imageValue) === '') {
            return null;
        }

        $imageValue = trim($imageValue);
        if (str_starts_with($imageValue, 'data:image/')) {
            $commaPos = strpos($imageValue, 'base64,');
            if ($commaPos !== false) {
                $imageValue = substr($imageValue, $commaPos + 7);
            }
        }

        if ($imageValue === '') {
            return null;
        }

        $result['image'] = $imageValue;
        return $result;
    }

    private function summarizeApiResultForDebug(?array $result): string
    {
        if (!is_array($result)) {
            return 'non-json response';
        }

        $keys = array_keys($result);
        $summary = 'keys=' . implode(',', array_slice($keys, 0, 8));

        if (!empty($result['detail']) && is_string($result['detail'])) {
            $summary .= ' detail=' . substr($result['detail'], 0, 180);
        } elseif (!empty($result['message']) && is_string($result['message'])) {
            $summary .= ' message=' . substr($result['message'], 0, 180);
        } elseif (!empty($result['error']) && is_string($result['error'])) {
            $summary .= ' error=' . substr($result['error'], 0, 180);
        }

        return $summary;
    }
    
    public function __construct($db = null)
    {
        // Database is optional - not needed for MCP tool invocation
        if ($db !== null) {
            $this->db = $db;
        } elseif (class_exists('Ginto\\Database')) {
            try {
                $this->db = Database::getInstance();
            } catch (\Throwable $e) {
                // Database not available, that's okay for MCP tools
            }
        }
        
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
    }
    
    // =========================================================================
    // MCP TOOL - For AI agent access
    // =========================================================================
    
    #[McpTool(
        name: 'generate_image',
        description: 'Generate an AI image from a text prompt using FastSD CPU. Supports text-to-image and image-to-image editing. Returns a generated image URL. Use this when the user asks you to create, generate, draw, or make an image, picture, artwork, or illustration.'
    )]
    public function generateImage(string $prompt, ?string $init_image = null, float $strength = 0.5): array
    {
        if (empty(trim($prompt))) {
            return ['success' => false, 'error' => 'Prompt is required'];
        }
        
        // Check if user has ImageGen subscription
        $userId = $_SESSION['user_id'] ?? null;
        $hasImageGenSubscription = false;
        
        if ($userId && $this->db) {
            $addon = $this->db->get('user_addons', ['status'], [
                'user_id' => $userId,
                'addon_type' => 'imagegen',
                'status' => 'active'
            ]);
            $hasImageGenSubscription = !empty($addon);
        }
        
        if (!$hasImageGenSubscription) {
            // Return upgrade required response
            return [
                'success' => false, 
                'error' => 'Image generation requires an ImageGen Pro subscription.',
                'upgrade_required' => true,
                'addon_type' => 'imagegen',
                'addon_name' => 'ImageGen Pro',
                'addon_price' => '$500.00/month',
                'features' => [
                    'Unlimited AI image generation',
                    'GPU-accelerated processing (10x faster)',
                    'Image-to-image editing',
                    'Inpainting and outpainting',
                    'Multiple style presets',
                    'Priority support',
                    'Dedicated GPU resources'
                ]
            ];
        }
        
        // User has subscription - but we're transferring to GPU server
        // For now, return a pending message
        return [
            'success' => false,
            'error' => 'processing imagegen request for your purchase',
            'subscription_active' => true,
            'pending_setup' => true
        ];
        
        /* Original code - commented out until GPU server is ready
        // Check if SDCPU server is available
        $healthCheck = @file_get_contents(str_replace('/api/generate', '/api/health', self::SDCPU_API_URL));
        if ($healthCheck === false) {
            return ['success' => false, 'error' => 'Image generation server is not available'];
        }
        
        $health = @json_decode($healthCheck, true);
        if (!($health['model_loaded'] ?? false)) {
            return ['success' => false, 'error' => 'Image model is not loaded'];
        }
        */
        
        // Generate the image synchronously (for MCP tool use)
        $generationConfig = $this->resolveImageGenProfile();
        $promptPayload = $this->buildPromptPayload($prompt);
        $requestData = [
            'prompt' => $promptPayload['prompt'],
            'width' => $generationConfig['width'],
            'height' => $generationConfig['height'],
            'num_inference_steps' => $generationConfig['num_inference_steps'],
            'guidance_scale' => $generationConfig['guidance_scale'],
            'num_images' => 1,
        ];
        $modelId = $this->resolveImageGenModelId();
        if ($modelId !== null) {
            $requestData['model'] = $modelId;
        }
        if (!empty($promptPayload['negative_prompt'])) {
            $requestData['negative_prompt'] = $promptPayload['negative_prompt'];
        }
        
        // Add img2img parameters if provided
        if ($init_image) {
            $requestData['init_image'] = $init_image;
            $requestData['strength'] = max(0.0, min(1.0, $strength));
        }
        
        $ch = curl_init($this->resolveSdcpuApiUrl());
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($requestData),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError || $httpCode !== 200) {
            return ['success' => false, 'error' => $curlError ?: "API returned HTTP {$httpCode}"];
        }
        
        $result = $this->normalizeImageResult(json_decode($response, true));
        if (!$result || !isset($result['image'])) {
            return ['success' => false, 'error' => 'Invalid response from image API (' . $this->summarizeApiResultForDebug(json_decode((string)$response, true)) . ')'];
        }
        
        // Save the base64 image to storage
        $storageDir = STORAGE_PATH . '/imagegen';
        if (!is_dir($storageDir)) {
            @mkdir($storageDir, 0755, true);
        }
        
        $mode = $result['mode'] ?? ($init_image ? 'img2img' : 'txt2img');
        $safePrompt = preg_replace('/[^a-zA-Z0-9]/', '_', substr($prompt, 0, 30));
        $outputFile = date('Y-m-d_His') . '_sdcpu_' . $mode . '_' . $safePrompt . '_' . substr(md5(uniqid()), 0, 8) . '.png';
        $outputPath = $storageDir . '/' . $outputFile;
        
        $imageData = base64_decode($result['image']);
        file_put_contents($outputPath, $imageData);
        
        $webUrl = '/storage/imagegen/' . $outputFile;
        
        return [
            'success' => true,
            'prompt' => $prompt,
            'mode' => $mode,
            'model' => 'Ginto AI ImageGen 1.0',
            'images' => [
                [
                    'url' => $webUrl,
                    'width' => $result['width'] ?? 512,
                    'height' => $result['height'] ?? 512,
                ]
            ],
            'generation_time_ms' => $result['generation_time_ms'] ?? null,
            'seed' => $result['seed'] ?? null,
        ];
    }
    
    /**
     * Handle the imagegen request (POST /imagegen)
     */
    public function handle(): void
    {
        // CSRF validation
        if (!$this->validateCsrf()) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['error' => 'Invalid or missing CSRF token']);
            exit;
        }
        
        // Get request data
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $prompt = $input['prompt'] ?? '';
        $initImage = $input['init_image'] ?? null;
        $strength = isset($input['strength']) ? floatval($input['strength']) : 0.5;
        
        if (empty($prompt)) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['error' => 'Prompt is required']);
            return;
        }
        
        // Start SSE stream
        $this->startStream();
        
        $startTime = microtime(true);
        $mode = $initImage ? 'img2img' : 'txt2img';
        $this->emit([
            'status' => 'started',
            'prompt' => $prompt,
            'mode' => $mode,
            'timestamp' => date('c'),
        ]);
        
        // Use FastSD CPU backend
        $this->handleSdcpuGeneration($prompt, $startTime, $initImage, $strength);
    }
    
    /**
     * Handle SDCPU API generation (FastSD CPU with OpenVINO)
     * 
     * Uses our local FastSD CPU server for ultra-fast (~1 second) image generation.
     * The API supports SSE streaming for progress updates.
     * Supports both txt2img and img2img modes.
     */
    private function handleSdcpuGeneration(string $prompt, float $startTime, ?string $initImage = null, float $strength = 0.5): void
    {
        $sdcpuApiUrl = $this->resolveSdcpuApiUrl();
        $sdcpuBaseUrl = preg_replace('#/api/generate$#', '', $sdcpuApiUrl) ?: 'http://127.0.0.1:8888';
        $mode = $initImage ? 'img2img' : 'txt2img';
        
        $this->emit([
            'phase' => 'connecting',
            'message' => $mode === 'img2img' 
                ? 'Connecting to FastSD CPU server (Image-to-Image mode)...' 
                : 'Connecting to FastSD CPU server...',
            'progress' => 0,
            'mode' => $mode,
            'elapsed_ms' => round((microtime(true) - $startTime) * 1000),
        ]);
        
        // Build the API request - use streaming endpoint for progress
        $generationConfig = $this->resolveImageGenProfile();
        $promptPayload = $this->buildPromptPayload($prompt);
        $requestData = [
            'prompt' => $promptPayload['prompt'],
            'width' => $generationConfig['width'],
            'height' => $generationConfig['height'],
            'num_inference_steps' => $generationConfig['num_inference_steps'],
            'guidance_scale' => $generationConfig['guidance_scale'],
            'num_images' => 1,
        ];
        $modelId = $this->resolveImageGenModelId();
        if ($modelId !== null) {
            $requestData['model'] = $modelId;
        }
        if (!empty($promptPayload['negative_prompt'])) {
            $requestData['negative_prompt'] = $promptPayload['negative_prompt'];
        }
        
        // Add img2img parameters if provided
        if ($initImage) {
            // Clean up base64 data - remove data URL prefix if present
            if (strpos($initImage, 'base64,') !== false) {
                $initImage = explode('base64,', $initImage)[1];
            }
            $requestData['init_image'] = $initImage;
            $requestData['strength'] = max(0.0, min(1.0, $strength));
        }
        
        // Use streaming endpoint if available, otherwise fallback to regular
        $streamUrl = $sdcpuBaseUrl . '/api/generate-stream';
        
        // First try the streaming endpoint
        $ch = curl_init($streamUrl);
        $responseBuffer = '';
        $self = $this;
        $lastProgress = 0;
        $finalResult = null;  // Store the final result with image
        $streamApiError = null;
        
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($requestData),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: text/event-stream'],
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_WRITEFUNCTION => function($ch, $data) use (&$responseBuffer, $self, $startTime, &$lastProgress, &$finalResult, &$streamApiError) {
                $responseBuffer .= $data;
                
                // Parse SSE events from buffer (supports both \n\n and \r\n\r\n separators)
                while (($event = $self->consumeSseEventFromBuffer($responseBuffer)) !== null) {
                    $eventData = $self->decodeSseEventPayload($event);
                    if (!$eventData) {
                        continue;
                    }

                    // Capture explicit API error events
                    if (!empty($eventData['error']) && is_string($eventData['error'])) {
                        $streamApiError = $eventData['error'];
                    }

                    // Check if this is the final result with image
                    $normalizedEvent = $self->normalizeImageResult($eventData);
                    if ($normalizedEvent !== null && isset($normalizedEvent['image'])) {
                        $finalResult = $normalizedEvent;
                    }

                    // Forward progress events to client
                    if (isset($eventData['progress'])) {
                        $progress = (int)$eventData['progress'];
                        if ($progress > $lastProgress) {
                            $lastProgress = $progress;
                            $self->emit([
                                'phase' => 'generating',
                                'message' => $eventData['message'] ?? "Generating... {$progress}%",
                                'progress' => $progress,
                                'step' => $eventData['step'] ?? null,
                                'total_steps' => $eventData['total_steps'] ?? null,
                                'elapsed_ms' => round((microtime(true) - $startTime) * 1000),
                            ]);
                        }
                    }
                }
                
                return strlen($data);
            },
        ]);
        
        $this->emit([
            'phase' => 'generating',
            'message' => $mode === 'img2img' 
                ? 'Transforming image with FastSD CPU...' 
                : 'Generating image with FastSD CPU...',
            'progress' => 5,
            'mode' => $mode,
            'elapsed_ms' => round((microtime(true) - $startTime) * 1000),
        ]);
        
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        // Use the final result captured during streaming
        $result = $finalResult;
        
        // If we have leftover buffer, try to parse trailing SSE or plain JSON.
        if (!$result && !empty($responseBuffer)) {
            $responseBuffer = trim($responseBuffer);
            $eventData = $this->decodeSseEventPayload($responseBuffer);
            $normalizedEvent = $this->normalizeImageResult($eventData);
            if ($normalizedEvent && isset($normalizedEvent['image'])) {
                $result = $normalizedEvent;
            } else {
                $plainJson = @json_decode($responseBuffer, true);
                $normalizedPlain = $this->normalizeImageResult(is_array($plainJson) ? $plainJson : null);
                if ($normalizedPlain && isset($normalizedPlain['image'])) {
                    $result = $normalizedPlain;
                }
            }
        }
        
        // Retry regular endpoint if streaming failed OR if stream returned no final image payload.
        $shouldRetry = $curlError !== '' || ($httpCode !== 200 && $httpCode !== 0) || !$result || !isset($result['image']);
        
        // If streaming failed or no image, try regular endpoint
        if ($shouldRetry) {
            $this->emit([
                'phase' => 'generating',
                'message' => 'Using standard API...',
                'progress' => 10,
                'elapsed_ms' => round((microtime(true) - $startTime) * 1000),
            ]);
            
            $ch = curl_init($sdcpuApiUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($requestData),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_CONNECTTIMEOUT => 10,
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            $decodedResponse = json_decode((string)$response, true);
            $result = $this->normalizeImageResult(is_array($decodedResponse) ? $decodedResponse : null);

            $needsCompatRetry = $curlError !== '' || $httpCode !== 200 || !$result || !isset($result['image']);
            if ($needsCompatRetry) {
                $compatRequestData = $requestData;
                unset($compatRequestData['negative_prompt'], $compatRequestData['model'], $compatRequestData['num_images']);

                $this->emit([
                    'phase' => 'generating',
                    'message' => 'Retrying with compatibility payload...',
                    'progress' => 15,
                    'elapsed_ms' => round((microtime(true) - $startTime) * 1000),
                ]);

                $compatCh = curl_init($sdcpuApiUrl);
                curl_setopt_array($compatCh, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode($compatRequestData),
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 60,
                    CURLOPT_CONNECTTIMEOUT => 10,
                ]);

                $compatResponse = curl_exec($compatCh);
                $compatHttpCode = curl_getinfo($compatCh, CURLINFO_HTTP_CODE);
                $compatCurlError = curl_error($compatCh);
                curl_close($compatCh);

                if ($compatCurlError === '' && $compatHttpCode === 200) {
                    $compatDecoded = json_decode((string)$compatResponse, true);
                    $compatResult = $this->normalizeImageResult(is_array($compatDecoded) ? $compatDecoded : null);
                    if ($compatResult && isset($compatResult['image'])) {
                        $result = $compatResult;
                        $curlError = '';
                        $httpCode = 200;
                        $decodedResponse = is_array($compatDecoded) ? $compatDecoded : null;
                    } else {
                        $decodedResponse = is_array($compatDecoded) ? $compatDecoded : $decodedResponse;
                    }
                } else {
                    $compatDecoded = json_decode((string)$compatResponse, true);
                    if (is_array($compatDecoded)) {
                        $decodedResponse = $compatDecoded;
                    }
                    $curlError = $compatCurlError !== '' ? $compatCurlError : $curlError;
                    $httpCode = $compatHttpCode > 0 ? $compatHttpCode : $httpCode;
                }
            }
        }
        
        if ($curlError || $httpCode !== 200) {
            $upstreamDetail = $this->summarizeApiResultForDebug(isset($decodedResponse) && is_array($decodedResponse) ? $decodedResponse : null);
            if ($streamApiError) {
                $upstreamDetail .= ' stream_error=' . substr($streamApiError, 0, 180);
            }
            $this->emit([
                'error' => true,
                'message' => ($curlError ?: "SDCPU API returned HTTP {$httpCode}") . ' (' . $upstreamDetail . ')',
                'progress' => 0,
                'elapsed_ms' => round((microtime(true) - $startTime) * 1000),
            ]);
            $this->emit(['final' => true, 'success' => false]);
            return;
        }

        if (!$result || !isset($result['image'])) {
            $debugSummary = $this->summarizeApiResultForDebug(is_array($result) ? $result : null);
            if ($streamApiError) {
                $debugSummary .= ' stream_error=' . substr($streamApiError, 0, 180);
            }
            $this->emit([
                'error' => true,
                'message' => 'Invalid response from SDCPU API (' . $debugSummary . ')',
                'progress' => 0,
                'elapsed_ms' => round((microtime(true) - $startTime) * 1000),
            ]);
            $this->emit(['final' => true, 'success' => false]);
            return;
        }
        
        // Save the base64 image to storage
        $storageDir = STORAGE_PATH . '/imagegen';
        if (!is_dir($storageDir)) {
            @mkdir($storageDir, 0755, true);
        }
        
        $safePrompt = preg_replace('/[^a-zA-Z0-9]/', '_', substr($prompt, 0, 30));
        $outputFile = date('Y-m-d_His') . '_sdcpu_' . $mode . '_' . $safePrompt . '_' . substr(md5(uniqid()), 0, 8) . '.png';
        $outputPath = $storageDir . '/' . $outputFile;
        
        // Decode and save the image
        $imageData = base64_decode($result['image']);
        file_put_contents($outputPath, $imageData);
        
        // Get web-accessible URL
        $webUrl = '/storage/imagegen/' . $outputFile;
        
        $generationTime = $result['generation_time_ms'] ?? round((microtime(true) - $startTime) * 1000);
        
        $this->emit([
            'image' => true,
            'url' => $webUrl,
            'prompt' => $prompt,
            'mode' => $mode,
            'strength' => $initImage ? $strength : null,
            'generation_time_ms' => $generationTime,
            'seed' => $result['seed'] ?? null,
            'width' => $result['width'] ?? 512,
            'height' => $result['height'] ?? 512,
            'elapsed_ms' => round((microtime(true) - $startTime) * 1000),
        ]);
        
        $this->emit([
            'final' => true,
            'success' => true,
            'mode' => $mode,
            'total_elapsed_ms' => round((microtime(true) - $startTime) * 1000),
        ]);
    }
    
    /**
     * Save image from URL to local storage
     */
    private function saveImageFromUrl(string $imageUrl, string $promptHint): ?string
    {
        // Handle data URLs
        if (str_starts_with($imageUrl, 'data:image/')) {
            return $this->saveBase64Image($imageUrl, $promptHint);
        }
        
        // Download the image
        $ch = curl_init($imageUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ]);
        
        $imageData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        
        if ($httpCode !== 200 || !$imageData) {
            return null;
        }
        
        // Determine extension
        $ext = 'png';
        if (str_contains($contentType, 'jpeg') || str_contains($contentType, 'jpg')) {
            $ext = 'jpg';
        } elseif (str_contains($contentType, 'webp')) {
            $ext = 'webp';
        }
        
        return $this->saveImageData($imageData, $ext, $promptHint);
    }
    
    /**
     * Save raw image data to storage
     */
    private function saveImageData(string $data, string $extension, string $promptHint): ?string
    {
        $storageDir = dirname(self::projectRoot()) . '/storage/imagegen';
        if (!is_dir($storageDir)) {
            @mkdir($storageDir, 0755, true);
        }
        
        $safePrompt = preg_replace('/[^a-zA-Z0-9]/', '_', substr($promptHint, 0, 30));
        $filename = date('Y-m-d_His') . '_' . $safePrompt . '_' . substr(md5(uniqid()), 0, 8) . '.' . $extension;
        $filepath = $storageDir . '/' . $filename;
        
        if (file_put_contents($filepath, $data) === false) {
            return null;
        }
        
        return '/storage/imagegen/' . $filename;
    }
    
    /**
     * Save base64 image data
     */
    private function saveBase64Image(string $base64Data, string $promptHint): ?string
    {
        if (!preg_match('/^data:image\/(\w+);base64,(.+)$/', $base64Data, $matches)) {
            return null;
        }
        
        $extension = $matches[1];
        $data = base64_decode($matches[2]);
        
        if ($data === false) {
            return null;
        }
        
        return $this->saveImageData($data, $extension, $promptHint);
    }
    
    /**
     * GET handler - returns the image generation page
     */
    public function info(): void
    {
        header('Content-Type: text/html; charset=utf-8');
        echo $this->getPage();
    }
    
    /**
     * Get project root directory
     */
    private static function projectRoot(): string
    {
        return defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2);
    }
    
    /**
     * Validate CSRF token
     */
    private function validateCsrf(): bool
    {
        $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        
        if (empty($token)) {
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $token = $input['csrf_token'] ?? '';
        }
        
        return !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Start SSE stream
     */
    private function startStream(): void
    {
        while (ob_get_level()) {
            ob_end_flush();
        }
        
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('X-Accel-Buffering: no');
        header('Connection: keep-alive');
        
        echo str_repeat(' ', 1024) . "\n";
        flush();
    }
    
    /**
     * Emit SSE event
     */
    private function emit(array $data): void
    {
        echo "data: " . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
        flush();
    }
    
    /**
     * Get the image generation page HTML
     */
    private function getPage(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        $csrfToken = $_SESSION['csrf_token'];
        
        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ImageGen - FastSD CPU AI Image Generation</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        html, body { height: 100%; margin: 0; }
        .stream-log { font-family: monospace; font-size: 11px; }
        .event-activity { color: #60a5fa; }
        .event-phase { color: #fbbf24; }
        .event-error { color: #f87171; }
        .event-image { color: #34d399; }
        .hidden { display: none !important; }
        
        /* Stop button override */
        #submitBtn.stop-mode {
            background: linear-gradient(to right, #dc2626, #b91c1c) !important;
            background-image: linear-gradient(to right, #dc2626, #b91c1c) !important;
        }
        
        /* Tab styles */
        .tab-btn { 
            transition: all 0.2s ease;
            border-bottom: 2px solid transparent;
        }
        .tab-btn.active {
            border-bottom-color: #3b82f6;
            color: #3b82f6;
        }
        .tab-btn:hover:not(.active) {
            color: #60a5fa;
        }
        
        /* Drop zone styles */
        .drop-zone {
            transition: all 0.3s ease;
            border: 2px dashed #4b5563;
        }
        .drop-zone:hover, .drop-zone.drag-over {
            border-color: #3b82f6;
            background: rgba(59, 130, 246, 0.1);
        }
        .drop-zone.has-image {
            border-style: solid;
            border-color: #22c55e;
        }
        
        /* Strength slider */
        .strength-slider {
            -webkit-appearance: none;
            appearance: none;
            height: 8px;
            border-radius: 4px;
            background: linear-gradient(to right, #22c55e, #eab308, #ef4444);
        }
        .strength-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: white;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        .strength-slider::-moz-range-thumb {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: white;
            cursor: pointer;
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        
        /* Image comparison */
        .comparison-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        .comparison-container.single {
            grid-template-columns: 1fr;
        }
        
        /* Blend mode specific */
        .blend-images-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        .blend-drop-zone {
            transition: all 0.3s ease;
            border: 2px dashed #4b5563;
            min-height: 180px;
        }
        .blend-drop-zone:hover, .blend-drop-zone.drag-over {
            border-color: #3b82f6;
            background: rgba(59, 130, 246, 0.1);
        }
        .blend-drop-zone.has-image {
            border-style: solid;
            border-color: #22c55e;
        }
        .blend-preview-container {
            background: linear-gradient(45deg, #1a1a1a 25%, transparent 25%),
                        linear-gradient(-45deg, #1a1a1a 25%, transparent 25%),
                        linear-gradient(45deg, transparent 75%, #1a1a1a 75%),
                        linear-gradient(-45deg, transparent 75%, #1a1a1a 75%);
            background-size: 20px 20px;
            background-position: 0 0, 0 10px, 10px -10px, -10px 0px;
            background-color: #2a2a2a;
        }
        .blend-mix-slider {
            -webkit-appearance: none;
            appearance: none;
            height: 8px;
            border-radius: 4px;
            background: linear-gradient(to right, #3b82f6, #8b5cf6, #ec4899);
        }
        .blend-mix-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: white;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        .blend-mix-slider::-moz-range-thumb {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: white;
            cursor: pointer;
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        
        /* Modal */
        .modal-backdrop {
            backdrop-filter: blur(4px);
        }
        
        /* Tooltip */
        .tooltip {
            position: relative;
        }
        .tooltip::after {
            content: attr(data-tip);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            padding: 4px 8px;
            background: #1f2937;
            color: white;
            font-size: 12px;
            border-radius: 4px;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s;
            z-index: 10;
        }
        .tooltip:hover::after {
            opacity: 1;
        }
    </style>
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen">
    <!-- Header -->
    <div class="bg-gray-800 border-b border-gray-700 px-4 py-3">
        <div class="max-w-6xl mx-auto flex items-center justify-between">
            <div class="flex items-center gap-4">
                <h1 class="text-xl font-bold flex items-center gap-2">
                    <span>🎨</span>
                    <span>ImageGen Pro</span>
                </h1>
                <span class="text-xs bg-blue-600/30 text-blue-400 px-2 py-1 rounded">FastSD CPU</span>
            </div>
            <div class="flex items-center gap-2 text-sm text-gray-400">
                <i class="fas fa-bolt text-yellow-400"></i>
                <span>~1 second generation</span>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="max-w-6xl mx-auto p-4">
        <!-- Mode Tabs -->
        <div class="flex border-b border-gray-700 mb-6">
            <button type="button" class="tab-btn active px-6 py-3 text-sm font-medium" data-mode="txt2img">
                <i class="fas fa-wand-magic-sparkles mr-2"></i>Text to Image
            </button>
            <button type="button" class="tab-btn px-6 py-3 text-sm font-medium" data-mode="img2img">
                <i class="fas fa-image mr-2"></i>Image to Image
            </button>
            <button type="button" class="tab-btn px-6 py-3 text-sm font-medium" data-mode="blend">
                <i class="fas fa-layer-group mr-2"></i>Blend Images
            </button>
        </div>
        
        <!-- Generation Form -->
        <form id="genForm" class="space-y-6">
            <input type="hidden" name="csrf_token" id="csrfToken" value="">
            
            <!-- Image Upload Section (img2img only) -->
            <div id="img2imgSection" class="hidden">
                <div class="grid md:grid-cols-2 gap-6">
                    <!-- Drop Zone -->
                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-2">
                            <i class="fas fa-upload mr-1"></i>Source Image
                        </label>
                        <div id="dropZone" class="drop-zone rounded-lg p-8 flex flex-col items-center justify-center min-h-[200px] cursor-pointer">
                            <div id="dropZoneContent" class="text-center">
                                <i class="fas fa-cloud-arrow-up text-4xl text-gray-500 mb-3"></i>
                                <p class="text-gray-400 mb-2">Drag & drop an image here</p>
                                <p class="text-xs text-gray-500">or click to browse • paste from clipboard</p>
                            </div>
                            <img id="sourcePreview" class="hidden max-w-full max-h-[300px] rounded-lg" alt="Source">
                        </div>
                        <input type="file" id="fileInput" accept="image/*" class="hidden">
                    </div>
                    
                    <!-- Strength Control -->
                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-2">
                            <i class="fas fa-sliders mr-1"></i>Transformation Strength
                        </label>
                        <div class="bg-gray-800 rounded-lg p-4 border border-gray-700">
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-2xl font-bold text-white" id="strengthValue">0.50</span>
                                <div class="flex gap-2">
                                    <button type="button" class="strength-preset px-3 py-1 text-xs bg-gray-700 hover:bg-gray-600 rounded" data-value="0.3">Light</button>
                                    <button type="button" class="strength-preset px-3 py-1 text-xs bg-gray-700 hover:bg-gray-600 rounded" data-value="0.5">Medium</button>
                                    <button type="button" class="strength-preset px-3 py-1 text-xs bg-gray-700 hover:bg-gray-600 rounded" data-value="0.7">Strong</button>
                                </div>
                            </div>
                            <input type="range" id="strengthSlider" class="strength-slider w-full" 
                                   min="0" max="1" step="0.05" value="0.5">
                            <div class="flex justify-between text-xs text-gray-500 mt-2">
                                <span>Keep Original</span>
                                <span>Complete Change</span>
                            </div>
                            <p class="text-xs text-gray-400 mt-3">
                                <i class="fas fa-info-circle mr-1"></i>
                                Lower values preserve more of the original image. Higher values allow more creative transformation.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Blend Mode Section -->
            <div id="blendSection" class="hidden">
                <div class="grid gap-6">
                    <!-- Two Image Drop Zones Side by Side -->
                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-2">
                            <i class="fas fa-images mr-1"></i>Select Two Images to Blend
                        </label>
                        <div class="blend-images-grid">
                            <!-- Image A -->
                            <div class="relative">
                                <p class="text-xs text-gray-500 mb-2 text-center">Image A (Left)</p>
                                <div id="blendDropZoneA" class="blend-drop-zone rounded-lg p-4 flex flex-col items-center justify-center cursor-pointer">
                                    <div id="blendDropZoneContentA" class="text-center">
                                        <i class="fas fa-cloud-arrow-up text-3xl text-gray-500 mb-2"></i>
                                        <p class="text-gray-400 text-sm">Drop image A</p>
                                        <p class="text-xs text-gray-500">or click to browse</p>
                                    </div>
                                    <img id="blendPreviewA" class="hidden max-w-full max-h-[200px] rounded-lg" alt="Image A">
                                </div>
                                <input type="file" id="blendFileInputA" accept="image/*" class="hidden">
                            </div>
                            
                            <!-- Image B -->
                            <div class="relative">
                                <p class="text-xs text-gray-500 mb-2 text-center">Image B (Right)</p>
                                <div id="blendDropZoneB" class="blend-drop-zone rounded-lg p-4 flex flex-col items-center justify-center cursor-pointer">
                                    <div id="blendDropZoneContentB" class="text-center">
                                        <i class="fas fa-cloud-arrow-up text-3xl text-gray-500 mb-2"></i>
                                        <p class="text-gray-400 text-sm">Drop image B</p>
                                        <p class="text-xs text-gray-500">or click to browse</p>
                                    </div>
                                    <img id="blendPreviewB" class="hidden max-w-full max-h-[200px] rounded-lg" alt="Image B">
                                </div>
                                <input type="file" id="blendFileInputB" accept="image/*" class="hidden">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Blend Controls -->
                    <div class="grid md:grid-cols-2 gap-6">
                        <!-- Blend Preview -->
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-2">
                                <i class="fas fa-eye mr-1"></i>Blend Preview
                            </label>
                            <div id="blendPreviewContainer" class="blend-preview-container rounded-lg min-h-[200px] flex items-center justify-center border border-gray-700">
                                <div id="blendPreviewPlaceholder" class="text-center text-gray-500">
                                    <i class="fas fa-layer-group text-3xl mb-2"></i>
                                    <p class="text-sm">Upload both images to see preview</p>
                                </div>
                                <canvas id="blendCanvas" class="hidden max-w-full max-h-[250px] rounded-lg"></canvas>
                            </div>
                        </div>
                        
                        <!-- Mix Ratio Control -->
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-2">
                                <i class="fas fa-sliders mr-1"></i>Blend Settings
                            </label>
                            <div class="bg-gray-800 rounded-lg p-4 border border-gray-700">
                                <div class="mb-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-sm text-gray-400">Mix Ratio</span>
                                        <span class="text-lg font-bold text-white" id="blendMixValue">50%</span>
                                    </div>
                                    <input type="range" id="blendMixSlider" class="blend-mix-slider w-full" 
                                           min="0" max="100" step="5" value="50">
                                    <div class="flex justify-between text-xs text-gray-500 mt-1">
                                        <span>100% Image A</span>
                                        <span>100% Image B</span>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-sm text-gray-400">AI Strength</span>
                                        <span class="text-lg font-bold text-white" id="blendStrengthValue">0.50</span>
                                    </div>
                                    <input type="range" id="blendStrengthSlider" class="strength-slider w-full" 
                                           min="0.1" max="0.9" step="0.05" value="0.5">
                                    <div class="flex justify-between text-xs text-gray-500 mt-1">
                                        <span>Keep Blend</span>
                                        <span>More Creative</span>
                                    </div>
                                </div>
                                
                                <p class="text-xs text-gray-400 mt-3">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    The AI will use the blended image as a base and transform it according to your prompt.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Prompt Input -->
            <div>
                <label class="block text-sm font-medium text-gray-400 mb-2">
                    <i class="fas fa-comment-dots mr-1"></i>
                    <span id="promptLabel">Describe the image you want to create</span>
                </label>
                <div class="flex gap-3">
                    <div class="flex-1 relative">
                        <input type="text" id="prompt" name="prompt" 
                               class="w-full bg-gray-800 border border-gray-600 rounded-lg px-4 py-3 text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-colors"
                               placeholder="A majestic dragon flying over a mountain at sunset..." 
                               autocomplete="off" required>
                        <button type="button" id="clearPrompt" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300 hidden">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <button type="submit" id="submitBtn"
                            class="px-8 py-3 bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 rounded-lg font-medium transition-all whitespace-nowrap flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="fas fa-bolt"></i>
                        <span>Generate</span>
                    </button>
                </div>
            </div>
            
            <!-- Progress Bar -->
            <div id="progressArea" class="hidden">
                <div class="flex items-center justify-between mb-2">
                    <span id="progressLabel" class="text-sm text-gray-400 flex items-center gap-2">
                        <i class="fas fa-spinner fa-spin"></i>
                        <span>Preparing...</span>
                    </span>
                    <span id="progressPercent" class="text-sm font-mono text-blue-400">0%</span>
                </div>
                <div class="w-full bg-gray-700 rounded-full h-2 overflow-hidden">
                    <div id="progressBar" class="bg-gradient-to-r from-blue-500 to-purple-500 h-2 rounded-full transition-all duration-150 ease-out" style="width: 0%"></div>
                </div>
            </div>
        </form>
        
        <!-- Results Section -->
        <div id="resultsSection" class="mt-8">
            <div class="grid lg:grid-cols-3 gap-6">
                <!-- Generated Image -->
                <div class="lg:col-span-2">
                    <div class="bg-gray-800 rounded-lg p-4 border border-gray-700">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-sm font-medium text-gray-400">
                                <i class="fas fa-image mr-1"></i>Result
                            </h3>
                            <div id="imageActions" class="hidden flex items-center gap-2">
                                <button type="button" id="downloadBtn" class="tooltip text-gray-400 hover:text-white p-2" data-tip="Download">
                                    <i class="fas fa-download"></i>
                                </button>
                                <button type="button" id="openBtn" class="tooltip text-gray-400 hover:text-white p-2" data-tip="Open in new tab">
                                    <i class="fas fa-external-link"></i>
                                </button>
                                <button type="button" id="useAsSourceBtn" class="tooltip text-gray-400 hover:text-white p-2" data-tip="Use as source for img2img">
                                    <i class="fas fa-recycle"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Comparison View -->
                        <div id="comparisonView" class="hidden">
                            <div class="comparison-container">
                                <div id="originalContainer" class="text-center">
                                    <p class="text-xs text-gray-500 mb-2">Original</p>
                                    <img id="originalImage" class="max-w-full max-h-[400px] rounded-lg mx-auto" alt="Original">
                                </div>
                                <div id="resultContainer" class="text-center">
                                    <p class="text-xs text-gray-500 mb-2">Generated</p>
                                    <img id="resultImage" class="max-w-full max-h-[400px] rounded-lg mx-auto cursor-pointer hover:ring-2 hover:ring-blue-500" alt="Generated">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Single Image View -->
                        <div id="singleImageView">
                            <div id="imageContainer" class="min-h-[300px] flex items-center justify-center border border-gray-600 rounded-lg bg-gray-900">
                                <div class="text-center text-gray-500">
                                    <i class="fas fa-image text-4xl mb-3"></i>
                                    <p class="text-sm">Your generated image will appear here</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Image Stats -->
                        <div id="imageStats" class="hidden mt-3 flex flex-wrap gap-4 text-xs text-gray-400">
                            <span><i class="fas fa-clock mr-1"></i><span id="statTime">-</span></span>
                            <span><i class="fas fa-expand mr-1"></i><span id="statSize">-</span></span>
                            <span><i class="fas fa-dice mr-1"></i>Seed: <span id="statSeed">-</span></span>
                            <span id="statModeContainer"><i class="fas fa-tag mr-1"></i><span id="statMode">-</span></span>
                        </div>
                    </div>
                </div>
                
                <!-- Activity Log -->
                <div>
                    <div class="bg-gray-800 rounded-lg p-4 border border-gray-700">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-sm font-medium text-gray-400">
                                <i class="fas fa-terminal mr-1"></i>Activity Log
                            </h3>
                            <button type="button" id="clearLog" class="text-xs text-gray-500 hover:text-gray-300">Clear</button>
                        </div>
                        <div id="log" class="stream-log h-[300px] overflow-y-auto bg-gray-900 rounded p-2"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Full Image Modal -->
    <div id="imageModal" class="fixed inset-0 z-50 hidden">
        <div class="modal-backdrop absolute inset-0 bg-black/80" id="modalBackdrop"></div>
        <div class="relative z-10 flex items-center justify-center min-h-screen p-4">
            <div class="relative max-w-5xl w-full">
                <img id="modalImage" class="w-full rounded-lg shadow-2xl" alt="Full size">
                <div class="absolute top-4 right-4 flex gap-2">
                    <a id="modalDownload" href="#" download class="bg-gray-800/80 hover:bg-gray-700 text-white px-4 py-2 rounded-lg flex items-center gap-2">
                        <i class="fas fa-download"></i>Download
                    </a>
                    <button type="button" id="closeModal" class="bg-gray-800/80 hover:bg-gray-700 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    (function() {
        // Elements
        const form = document.getElementById('genForm');
        const submitBtn = document.getElementById('submitBtn');
        const logEl = document.getElementById('log');
        const imageContainer = document.getElementById('imageContainer');
        const progressArea = document.getElementById('progressArea');
        const progressBar = document.getElementById('progressBar');
        const progressLabel = document.getElementById('progressLabel').querySelector('span');
        const progressPercent = document.getElementById('progressPercent');
        const img2imgSection = document.getElementById('img2imgSection');
        const blendSection = document.getElementById('blendSection');
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');
        const sourcePreview = document.getElementById('sourcePreview');
        const dropZoneContent = document.getElementById('dropZoneContent');
        const strengthSlider = document.getElementById('strengthSlider');
        const strengthValue = document.getElementById('strengthValue');
        const promptInput = document.getElementById('prompt');
        const clearPrompt = document.getElementById('clearPrompt');
        const imageActions = document.getElementById('imageActions');
        const imageStats = document.getElementById('imageStats');
        const comparisonView = document.getElementById('comparisonView');
        const singleImageView = document.getElementById('singleImageView');
        const imageModal = document.getElementById('imageModal');
        const modalImage = document.getElementById('modalImage');
        const modalDownload = document.getElementById('modalDownload');
        
        // Blend mode elements
        const blendDropZoneA = document.getElementById('blendDropZoneA');
        const blendDropZoneB = document.getElementById('blendDropZoneB');
        const blendFileInputA = document.getElementById('blendFileInputA');
        const blendFileInputB = document.getElementById('blendFileInputB');
        const blendPreviewA = document.getElementById('blendPreviewA');
        const blendPreviewB = document.getElementById('blendPreviewB');
        const blendDropZoneContentA = document.getElementById('blendDropZoneContentA');
        const blendDropZoneContentB = document.getElementById('blendDropZoneContentB');
        const blendCanvas = document.getElementById('blendCanvas');
        const blendPreviewPlaceholder = document.getElementById('blendPreviewPlaceholder');
        const blendMixSlider = document.getElementById('blendMixSlider');
        const blendMixValue = document.getElementById('blendMixValue');
        const blendStrengthSlider = document.getElementById('blendStrengthSlider');
        const blendStrengthValue = document.getElementById('blendStrengthValue');
        
        // State
        let currentMode = 'txt2img';
        let sourceImageData = null;
        let generatedImageUrl = null;
        let isGenerating = false;
        let abortController = null;
        let blendImageA = null;
        let blendImageB = null;
        let blendedImageData = null;
        const csrfToken = 'CSRF_TOKEN_PLACEHOLDER';
        
        // Set CSRF token
        document.getElementById('csrfToken').value = csrfToken;
        
        // Tab switching
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                currentMode = btn.dataset.mode;
                
                // Hide all mode sections
                img2imgSection.classList.add('hidden');
                blendSection.classList.add('hidden');
                
                if (currentMode === 'img2img') {
                    img2imgSection.classList.remove('hidden');
                    document.getElementById('promptLabel').textContent = 'Describe how to transform the image';
                    promptInput.placeholder = 'Transform into a watercolor painting style...';
                } else if (currentMode === 'blend') {
                    blendSection.classList.remove('hidden');
                    document.getElementById('promptLabel').textContent = 'Describe how to blend and transform the images';
                    promptInput.placeholder = 'Merge these images into a dreamlike fantasy scene...';
                } else {
                    document.getElementById('promptLabel').textContent = 'Describe the image you want to create';
                    promptInput.placeholder = 'A majestic dragon flying over a mountain at sunset...';
                }
            });
        });
        
        // Strength slider
        strengthSlider.addEventListener('input', () => {
            strengthValue.textContent = parseFloat(strengthSlider.value).toFixed(2);
        });
        
        // Strength presets
        document.querySelectorAll('.strength-preset').forEach(btn => {
            btn.addEventListener('click', () => {
                strengthSlider.value = btn.dataset.value;
                strengthValue.textContent = parseFloat(btn.dataset.value).toFixed(2);
            });
        });
        
        // Drop zone click
        dropZone.addEventListener('click', () => {
            if (!sourceImageData) fileInput.click();
        });
        
        // File input change
        fileInput.addEventListener('change', (e) => {
            if (e.target.files?.[0]) loadImage(e.target.files[0]);
        });
        
        // Drag and drop
        ['dragenter', 'dragover'].forEach(event => {
            dropZone.addEventListener(event, (e) => {
                e.preventDefault();
                dropZone.classList.add('drag-over');
            });
        });
        
        ['dragleave', 'drop'].forEach(event => {
            dropZone.addEventListener(event, () => {
                dropZone.classList.remove('drag-over');
            });
        });
        
        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            const file = e.dataTransfer?.files?.[0];
            if (file?.type.startsWith('image/')) loadImage(file);
        });
        
        // Paste from clipboard
        document.addEventListener('paste', (e) => {
            if (currentMode !== 'img2img') return;
            const items = e.clipboardData?.items;
            for (const item of items) {
                if (item.type.startsWith('image/')) {
                    loadImage(item.getAsFile());
                    break;
                }
            }
        });
        
        // Load image
        function loadImage(file) {
            const reader = new FileReader();
            reader.onload = (e) => {
                sourceImageData = e.target.result;
                sourcePreview.src = sourceImageData;
                sourcePreview.classList.remove('hidden');
                dropZoneContent.classList.add('hidden');
                dropZone.classList.add('has-image');
                
                // Add remove button
                if (!document.getElementById('removeSource')) {
                    const removeBtn = document.createElement('button');
                    removeBtn.type = 'button';
                    removeBtn.id = 'removeSource';
                    removeBtn.className = 'absolute top-2 right-2 bg-red-600 hover:bg-red-700 text-white rounded-full w-8 h-8 flex items-center justify-center';
                    removeBtn.innerHTML = '<i class="fas fa-times"></i>';
                    removeBtn.onclick = clearSource;
                    dropZone.style.position = 'relative';
                    dropZone.appendChild(removeBtn);
                }
            };
            reader.readAsDataURL(file);
        }
        
        // Clear source image
        function clearSource() {
            sourceImageData = null;
            sourcePreview.src = '';
            sourcePreview.classList.add('hidden');
            dropZoneContent.classList.remove('hidden');
            dropZone.classList.remove('has-image');
            document.getElementById('removeSource')?.remove();
            fileInput.value = '';
        }
        
        // ==========================================
        // BLEND MODE HANDLERS
        // ==========================================
        
        // Setup blend drop zones
        function setupBlendDropZone(dropZone, fileInput, preview, content, isA) {
            dropZone.addEventListener('click', () => {
                const hasImage = isA ? blendImageA : blendImageB;
                if (!hasImage) fileInput.click();
            });
            
            fileInput.addEventListener('change', (e) => {
                if (e.target.files?.[0]) loadBlendImage(e.target.files[0], isA);
            });
            
            ['dragenter', 'dragover'].forEach(event => {
                dropZone.addEventListener(event, (e) => {
                    e.preventDefault();
                    dropZone.classList.add('drag-over');
                });
            });
            
            ['dragleave', 'drop'].forEach(event => {
                dropZone.addEventListener(event, () => {
                    dropZone.classList.remove('drag-over');
                });
            });
            
            dropZone.addEventListener('drop', (e) => {
                e.preventDefault();
                const file = e.dataTransfer?.files?.[0];
                if (file?.type.startsWith('image/')) loadBlendImage(file, isA);
            });
        }
        
        setupBlendDropZone(blendDropZoneA, blendFileInputA, blendPreviewA, blendDropZoneContentA, true);
        setupBlendDropZone(blendDropZoneB, blendFileInputB, blendPreviewB, blendDropZoneContentB, false);
        
        // Load blend image
        function loadBlendImage(file, isA) {
            const reader = new FileReader();
            reader.onload = (e) => {
                const imageData = e.target.result;
                const preview = isA ? blendPreviewA : blendPreviewB;
                const content = isA ? blendDropZoneContentA : blendDropZoneContentB;
                const dropZone = isA ? blendDropZoneA : blendDropZoneB;
                
                if (isA) {
                    blendImageA = imageData;
                } else {
                    blendImageB = imageData;
                }
                
                preview.src = imageData;
                preview.classList.remove('hidden');
                content.classList.add('hidden');
                dropZone.classList.add('has-image');
                
                // Add remove button
                const removeId = isA ? 'removeBlendA' : 'removeBlendB';
                if (!document.getElementById(removeId)) {
                    const removeBtn = document.createElement('button');
                    removeBtn.type = 'button';
                    removeBtn.id = removeId;
                    removeBtn.className = 'absolute top-6 right-2 bg-red-600 hover:bg-red-700 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs z-10';
                    removeBtn.innerHTML = '<i class="fas fa-times"></i>';
                    removeBtn.onclick = () => clearBlendImage(isA);
                    dropZone.style.position = 'relative';
                    dropZone.appendChild(removeBtn);
                }
                
                // Update blend preview
                updateBlendPreview();
            };
            reader.readAsDataURL(file);
        }
        
        // Clear blend image
        function clearBlendImage(isA) {
            const preview = isA ? blendPreviewA : blendPreviewB;
            const content = isA ? blendDropZoneContentA : blendDropZoneContentB;
            const dropZone = isA ? blendDropZoneA : blendDropZoneB;
            const fileInput = isA ? blendFileInputA : blendFileInputB;
            const removeId = isA ? 'removeBlendA' : 'removeBlendB';
            
            if (isA) {
                blendImageA = null;
            } else {
                blendImageB = null;
            }
            
            preview.src = '';
            preview.classList.add('hidden');
            content.classList.remove('hidden');
            dropZone.classList.remove('has-image');
            document.getElementById(removeId)?.remove();
            fileInput.value = '';
            
            updateBlendPreview();
        }
        
        // Blend mix slider
        blendMixSlider.addEventListener('input', () => {
            blendMixValue.textContent = blendMixSlider.value + '%';
            updateBlendPreview();
        });
        
        // Blend strength slider
        blendStrengthSlider.addEventListener('input', () => {
            blendStrengthValue.textContent = parseFloat(blendStrengthSlider.value).toFixed(2);
        });
        
        // Update blend preview canvas
        function updateBlendPreview() {
            if (!blendImageA || !blendImageB) {
                blendCanvas.classList.add('hidden');
                blendPreviewPlaceholder.classList.remove('hidden');
                blendedImageData = null;
                return;
            }
            
            blendCanvas.classList.remove('hidden');
            blendPreviewPlaceholder.classList.add('hidden');
            
            const imgA = new Image();
            const imgB = new Image();
            let loadedCount = 0;
            
            function onBothLoaded() {
                loadedCount++;
                if (loadedCount < 2) return;
                
                // Use 512x384 as target size (matching generation size)
                const targetW = 512;
                const targetH = 384;
                
                blendCanvas.width = targetW;
                blendCanvas.height = targetH;
                const ctx = blendCanvas.getContext('2d');
                
                // Draw image A
                ctx.drawImage(imgA, 0, 0, targetW, targetH);
                
                // Blend image B on top with mix ratio
                const mixRatio = parseInt(blendMixSlider.value) / 100;
                ctx.globalAlpha = mixRatio;
                ctx.drawImage(imgB, 0, 0, targetW, targetH);
                ctx.globalAlpha = 1;
                
                // Store blended result
                blendedImageData = blendCanvas.toDataURL('image/png');
            }
            
            imgA.onload = onBothLoaded;
            imgB.onload = onBothLoaded;
            imgA.src = blendImageA;
            imgB.src = blendImageB;
        }
        
        // ==========================================
        // END BLEND MODE HANDLERS
        // ==========================================
        
        // Clear prompt button
        promptInput.addEventListener('input', () => {
            clearPrompt.classList.toggle('hidden', !promptInput.value);
        });
        clearPrompt.addEventListener('click', () => {
            promptInput.value = '';
            clearPrompt.classList.add('hidden');
        });
        
        // Clear log
        document.getElementById('clearLog').addEventListener('click', () => {
            logEl.innerHTML = '';
        });
        
        // Progress functions
        function updateProgress(percent, message) {
            progressArea.classList.remove('hidden');
            progressBar.style.width = percent + '%';
            progressPercent.textContent = percent + '%';
            if (message) progressLabel.textContent = message;
        }
        
        function hideProgress() {
            progressArea.classList.add('hidden');
            progressBar.style.width = '0%';
        }
        
        // Form submission
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            // Handle stop if already generating
            if (isGenerating && abortController) {
                abortController.abort();
                log('Generation cancelled by user', 'event-error');
                resetGenerateButton();
                hideProgress();
                return;
            }
            
            const prompt = promptInput.value.trim();
            if (!prompt) return;
            
            // Validate img2img has source
            if (currentMode === 'img2img' && !sourceImageData) {
                log('Please upload a source image first', 'event-error');
                return;
            }
            
            // Validate blend mode has both images
            if (currentMode === 'blend') {
                if (!blendImageA || !blendImageB) {
                    log('Please upload both images to blend', 'event-error');
                    return;
                }
                if (!blendedImageData) {
                    log('Blend preview not ready, please wait...', 'event-error');
                    return;
                }
            }
            
            // Setup abort controller
            abortController = new AbortController();
            isGenerating = true;
            
            // Change button to Stop - more direct approach
            console.log('Changing button to Stop');
            submitBtn.innerHTML = '<i class="fas fa-stop"></i><span>Stop</span>';
            submitBtn.style.background = 'linear-gradient(to right, #dc2626, #b91c1c)';
            submitBtn.style.backgroundImage = 'linear-gradient(to right, #dc2626, #b91c1c)';
            submitBtn.classList.add('stop-mode');
            
            logEl.innerHTML = '';
            imageActions.classList.add('hidden');
            imageStats.classList.add('hidden');
            
            // Reset views
            comparisonView.classList.add('hidden');
            singleImageView.classList.remove('hidden');
            imageContainer.innerHTML = '<div class="text-center text-gray-400"><i class="fas fa-spinner fa-spin text-3xl mb-2"></i><p class="text-sm">Generating image...</p></div>';
            
            try {
                const payload = { 
                    prompt,
                    csrf_token: csrfToken 
                };
                
                // Add img2img params
                if (currentMode === 'img2img' && sourceImageData) {
                    payload.init_image = sourceImageData;
                    payload.strength = parseFloat(strengthSlider.value);
                }
                
                // Add blend mode params (uses img2img with blended image)
                if (currentMode === 'blend' && blendedImageData) {
                    payload.init_image = blendedImageData;
                    payload.strength = parseFloat(blendStrengthSlider.value);
                }
                
                const response = await fetch('/imagegen', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify(payload),
                    signal: abortController.signal,
                });
                
                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';
                
                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;
                    
                    buffer += decoder.decode(value, { stream: true });
                    const lines = buffer.split('\n');
                    buffer = lines.pop() || '';
                    
                    for (const line of lines) {
                        if (line.startsWith('data: ')) {
                            try {
                                const data = JSON.parse(line.substring(6));
                                handleEvent(data);
                            } catch {}
                        }
                    }
                }
                
            } catch (error) {
                if (error.name !== 'AbortError') {
                    log('Error: ' + error.message, 'event-error');
                }
                hideProgress();
            } finally {
                resetGenerateButton();
            }
        });
        
        function resetGenerateButton() {
            isGenerating = false;
            abortController = null;
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-bolt"></i><span>Generate</span>';
            submitBtn.style.background = '';
            submitBtn.style.backgroundImage = '';
            submitBtn.classList.remove('stop-mode');
        }
        
        // Handle SSE events
        function handleEvent(data) {
            const time = data.elapsed_ms ? '[' + data.elapsed_ms + 'ms] ' : '';
            
            // Update progress
            if (typeof data.progress === 'number') {
                updateProgress(data.progress, data.message || data.phase || '');
            }
            
            // Image received
            if (data.image) {
                log(time + '✓ Image generated!', 'event-image');
                generatedImageUrl = data.url;
                updateProgress(100, 'Complete!');
                
                // Show comparison for img2img
                if (currentMode === 'img2img' && sourceImageData) {
                    singleImageView.classList.add('hidden');
                    comparisonView.classList.remove('hidden');
                    document.getElementById('originalImage').src = sourceImageData;
                    const resultImg = document.getElementById('resultImage');
                    resultImg.src = data.url;
                    resultImg.onclick = () => showModal(data.url);
                } else if (currentMode === 'blend' && blendedImageData) {
                    // Show blend comparison (blended input vs AI result)
                    singleImageView.classList.add('hidden');
                    comparisonView.classList.remove('hidden');
                    document.getElementById('originalImage').src = blendedImageData;
                    document.querySelector('#originalContainer p').textContent = 'Blended Input';
                    const resultImg = document.getElementById('resultImage');
                    resultImg.src = data.url;
                    resultImg.onclick = () => showModal(data.url);
                } else {
                    imageContainer.innerHTML = '<img src="' + data.url + '" alt="Generated" class="max-w-full max-h-[400px] rounded-lg cursor-pointer hover:ring-2 hover:ring-blue-500 mx-auto">';
                    imageContainer.querySelector('img').onclick = () => showModal(data.url);
                }
                
                // Show stats
                imageStats.classList.remove('hidden');
                document.getElementById('statTime').textContent = data.generation_time_ms + 'ms';
                document.getElementById('statSize').textContent = data.width + '×' + data.height;
                document.getElementById('statSeed').textContent = data.seed || '-';
                document.getElementById('statMode').textContent = data.mode || currentMode;
                
                // Show actions
                imageActions.classList.remove('hidden');

                setTimeout(hideProgress, 400);
            } else if (data.error) {
                log(time + '✗ ' + (data.message || data.error), 'event-error');
                updateProgress(100, 'Failed');
                setTimeout(hideProgress, 300);
                imageContainer.innerHTML = '<div class="text-center text-red-400"><i class="fas fa-exclamation-triangle text-3xl mb-2"></i><p class="text-sm">' + (data.message || 'Generation failed') + '</p></div>';
            } else if (data.phase) {
                log(time + data.message || data.phase, 'event-phase');
            } else if (data.step && data.total_steps) {
                log(time + 'Step ' + data.step + '/' + data.total_steps, 'event-activity');
            } else if (!data.final && !data.done && !data.status) {
                log(time + JSON.stringify(data), '');
            }
        }
        
        // Log function
        function log(message, className) {
            const div = document.createElement('div');
            div.className = className || '';
            div.textContent = message;
            logEl.appendChild(div);
            logEl.scrollTop = logEl.scrollHeight;
        }
        
        // Modal functions
        function showModal(url) {
            modalImage.src = url;
            modalDownload.href = url;
            imageModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
        
        document.getElementById('closeModal').addEventListener('click', closeModal);
        document.getElementById('modalBackdrop').addEventListener('click', closeModal);
        
        function closeModal() {
            imageModal.classList.add('hidden');
            document.body.style.overflow = '';
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeModal();
        });
        
        // Image actions
        document.getElementById('downloadBtn').addEventListener('click', () => {
            if (generatedImageUrl) {
                const a = document.createElement('a');
                a.href = generatedImageUrl;
                a.download = 'generated-image.png';
                a.click();
            }
        });
        
        document.getElementById('openBtn').addEventListener('click', () => {
            if (generatedImageUrl) window.open(generatedImageUrl, '_blank');
        });
        
        document.getElementById('useAsSourceBtn').addEventListener('click', () => {
            if (!generatedImageUrl) return;
            
            // Switch to img2img mode
            document.querySelector('[data-mode="img2img"]').click();
            
            // Load the generated image as source
            fetch(generatedImageUrl)
                .then(r => r.blob())
                .then(blob => loadImage(blob));
        });
    })();
    </script>
</body>
</html>
HTML;
        
        // Replace the CSRF token placeholder
        return str_replace('CSRF_TOKEN_PLACEHOLDER', $csrfToken, $html);
    }
}