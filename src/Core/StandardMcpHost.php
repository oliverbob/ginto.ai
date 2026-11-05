<?php
namespace App\Core;

use PhpMcp\Client\Client;
use PhpMcp\Client\ServerConfig;
use GuzzleHttp\Client as HttpClient;
use App\Core\LLM\LLMProviderFactory;
use App\Core\LLM\LLMProviderInterface;
use App\Core\LLM\UnifiedMcpClient;

/**
 * MCP Host that bridges LLM providers with MCP tool servers.
 * 
 * This class now uses the unified LLM provider architecture which supports:
 * - OpenAI-compatible APIs (OpenAI, Groq, Together, Fireworks)
 * - Anthropic Claude API
 * 
 * Configuration via environment:
 * - LLM_PROVIDER: openai, groq, anthropic, together, fireworks (auto-detected if not set)
 * - LLM_MODEL: Model to use (defaults based on provider)
 * - GROQ_API_KEY, OPENAI_API_KEY, ANTHROPIC_API_KEY, etc.
 * 
 * @deprecated Use UnifiedMcpClient directly for new code
 */
class StandardMcpHost
{
    private array $mcpClients = [];
    private ?UnifiedMcpClient $unifiedClient = null;
    private array $conversationHistory = [];
    private string $model = 'llama-3.3-70b-versatile';
    private string $projectPath;

    // Legacy properties for backward compatibility
    private ?HttpClient $groqClient = null;
    private ?string $groqApiKey = null;

    public function __construct(?string $projectPath = null, array $initialHistory = [])
    {
        $this->projectPath = $projectPath ?? (defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2));
        $this->conversationHistory = is_array($initialHistory) ? $initialHistory : [];

        // Initialize the unified client with auto-detected provider
        try {
            $this->unifiedClient = new UnifiedMcpClient(
                provider: null, // Auto-detect
                projectPath: $this->projectPath,
                initialHistory: $this->conversationHistory
            );
            
            // Sync model from provider
            $this->model = $this->unifiedClient->getProvider()->getDefaultModel();
        } catch (\Throwable $e) {
            error_log('[StandardMcpHost] UnifiedMcpClient init error: ' . $e->getMessage());
            // Fall back to legacy Groq initialization
            $this->initializeLegacyGroq();
        }

        $this->initializeMcpServers($this->projectPath);
    }

    private function initializeMcpServers(string $projectPath): void
    {
        // Prefer HTTP transport servers configured via env; otherwise rely on
        // locally installed php-mcp clients/stdio servers if present.
        try {
            // Filesystem MCP Server via HTTP if configured
            $mcpUrl = getenv('MCP_SERVER_URL') ?: getenv('MCP_TARGET_URL') ?: null;
            if ($mcpUrl) {
                $sc = ServerConfig::fromArray('filesystem', ['transport' => 'http', 'url' => $mcpUrl, 'timeout' => 30]);
                $client = Client::make()->withClientInfo('ginto', '1.0')->withServerConfig($sc)->build();
                try { $client->initialize(); } catch (\Throwable $_) { }
                if ($client->isReady()) $this->mcpClients['remote'] = $client;
            }

            // Try to initialize a local php-mcp using a simple HTTP/stdio fallback
            // If project installs local MCP servers (e.g. npx modelcontextprotocol/server-filesystem)
            // the client may connect via stdio or http depending on ServerConfig usage.
            // Here we attempt a local http URL first (127.0.0.1:9010) as a common default.
            $localUrl = 'http://127.0.0.1:9010';
            $scLocal = ServerConfig::fromArray('local', ['transport' => 'http', 'url' => $localUrl, 'timeout' => 10]);
            $clientLocal = Client::make()->withClientInfo('ginto', '1.0')->withServerConfig($scLocal)->build();
            try { $clientLocal->initialize(); } catch (\Throwable $_) { }
            if ($clientLocal->isReady()) $this->mcpClients['local'] = $clientLocal;

        } catch (\Throwable $e) {
            // If php-mcp classes are not available or initialization fails,
            // we simply leave mcpClients empty and continue.
            error_log('[StandardMcpHost] initializeMcpServers error: ' . $e->getMessage());
        }
    }

    public function getAllTools(): array
    {
        $allTools = [];
        
        // Add local handler tools (Ginto\Handlers with #[McpTool] attributes)
        $root = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2);
        foreach (glob($root . '/src/Handlers/*.php') as $f) {
            require_once $f;
        }
        foreach (get_declared_classes() as $class) {
            if (!str_starts_with($class, 'Ginto\\Handlers\\') && !str_starts_with($class, 'App\\Handlers\\')) continue;
            try {
                $rc = new \ReflectionClass($class);
                foreach ($rc->getMethods(\ReflectionMethod::IS_PUBLIC) as $m) {
                    if ($m->getDeclaringClass()->getName() !== $class) continue;
                    $attrs = $m->getAttributes('PhpMcp\\Server\\Attributes\\McpTool');
                    foreach ($attrs as $a) {
                        $inst = $a->newInstance();
                        $name = $inst->name ?? null;
                        if (!$name) continue;
                        // Build parameter schema from method signature
                        $props = [];
                        $required = [];
                        foreach ($m->getParameters() as $p) {
                            $ptype = 'string';
                            if ($p->hasType()) {
                                $t = $p->getType();
                                if ($t instanceof \ReflectionNamedType) {
                                    $ptype = match($t->getName()) {
                                        'int', 'integer' => 'integer',
                                        'float', 'double' => 'number',
                                        'bool', 'boolean' => 'boolean',
                                        'array' => 'array',
                                        default => 'string'
                                    };
                                }
                            }
                            $props[$p->getName()] = ['type' => $ptype];
                            if (!$p->isDefaultValueAvailable()) $required[] = $p->getName();
                        }
                        $allTools[] = [
                            'type' => 'function',
                            'function' => [
                                'name' => $name,
                                'description' => $inst->description ?? '',
                                'parameters' => [
                                    'type' => 'object',
                                    'properties' => $props ?: new \stdClass(),
                                    'required' => $required
                                ]
                            ]
                        ];
                    }
                }
            } catch (\Throwable $_) { continue; }
        }

        foreach ($this->mcpClients as $serverName => $client) {
            try {
                $tools = $client->listTools();
                foreach ($tools as $t) {
                    // Tool definitions may be objects or arrays depending on client
                    $name = is_array($t) ? ($t['name'] ?? null) : (is_object($t) ? ($t->name ?? ($t->name() ?? null)) : null);
                    $desc = is_array($t) ? ($t['description'] ?? '') : (is_object($t) ? ($t->description ?? '') : '');
                    $inputSchema = [];
                    if (is_array($t) && isset($t['inputSchema'])) $inputSchema = $t['inputSchema'];
                    elseif (is_object($t) && property_exists($t, 'inputSchema')) $inputSchema = $t->inputSchema;

                    if ($name) {
                        $allTools[] = [
                            'type' => 'function',
                            'function' => [
                                'name' => $name,
                                'description' => ($desc ?? '') . " [Server: $serverName]",
                                'parameters' => is_array($inputSchema) && !empty($inputSchema) ? $inputSchema : [
                                    'type' => 'object',
                                    'properties' => new \stdClass()
                                ]
                            ]
                        ];
                    }
                }
            } catch (\Throwable $e) {
                error_log("[StandardMcpHost] Failed to list tools from $serverName: " . $e->getMessage());
            }
        }
        return $allTools;
    }

    private function executeTool(string $toolName, array $arguments): array
    {
        foreach ($this->mcpClients as $serverName => $client) {
            try {
                $tools = $client->listTools();
                $toolExists = false;
                foreach ($tools as $t) {
                    $tname = is_array($t) ? ($t['name'] ?? null) : (is_object($t) ? ($t->name ?? null) : null);
                    if ($tname === $toolName) { $toolExists = true; break; }
                }
                if ($toolExists) {
                    $result = $client->callTool($toolName, $arguments);
                    return [ 'success' => true, 'server' => $serverName, 'result' => $result ];
                }
            } catch (\Throwable $e) {
                error_log("[StandardMcpHost] Tool execution error on $serverName: " . $e->getMessage());
            }
        }
        return [ 'success' => false, 'error' => "Tool '$toolName' not found" ];
    }

    /**
     * Initialize legacy Groq client for backward compatibility.
     */
    private function initializeLegacyGroq(): void
    {
        $this->groqApiKey = getenv('GROQ_API_KEY') ?: ($_ENV['GROQ_API_KEY'] ?? null);
        if (!$this->groqApiKey) {
            return;
        }

        $this->groqClient = new HttpClient([
            'base_uri' => 'https://api.groq.com/openai/v1/',
            'headers' => [
                'Authorization' => 'Bearer ' . $this->groqApiKey,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 60,
        ]);
    }

    /**
     * Send a chat message and get a response.
     * 
     * Uses the unified provider architecture when available,
     * falls back to legacy Groq implementation otherwise.
     */
    public function chat(string $userMessage, int $maxIterations = 10): string
    {
        // Prefer unified client if available
        if ($this->unifiedClient) {
            try {
                $this->unifiedClient->setHistory($this->conversationHistory);
                $result = $this->unifiedClient->chat($userMessage);
                $this->conversationHistory = $this->unifiedClient->getHistory();
                return $result;
            } catch (\Throwable $e) {
                error_log('[StandardMcpHost] UnifiedMcpClient error, falling back: ' . $e->getMessage());
            }
        }

        // Legacy implementation
        return $this->chatLegacy($userMessage, $maxIterations);
    }

    /**
     * Legacy chat implementation using direct Groq API calls.
     */
    private function chatLegacy(string $userMessage, int $maxIterations = 10): string
    {
        $this->conversationHistory[] = ['role' => 'user', 'content' => $userMessage];

        $tools = $this->getAllTools();
        $iteration = 0;

        while ($iteration < $maxIterations) {
            $iteration++;
            $response = $this->callGroq($tools);
            if (!$response || !is_array($response) || empty($response['choices'][0]['message'])) {
                return 'Error communicating with Groq API or no response';
            }

            $message = $response['choices'][0]['message'];
            // normalize assistant message
            $this->conversationHistory[] = $message;

            // Check for tool calls (standardized via 'tool_calls' or tool invocation markers)
            if (!empty($message['tool_calls']) && is_array($message['tool_calls'])) {
                foreach ($message['tool_calls'] as $toolCall) {
                    $toolName = $toolCall['function']['name'] ?? ($toolCall['name'] ?? null);
                    $argsJson = $toolCall['function']['arguments'] ?? ($toolCall['arguments'] ?? '{}');
                    $arguments = is_string($argsJson) ? json_decode($argsJson, true) : (is_array($argsJson) ? $argsJson : []);

                    error_log('[StandardMcpHost] Executing tool: ' . $toolName . ' with args: ' . json_encode($arguments));
                    // Use runTool() here so we attempt remote MCP clients first,
                    // and then fall back to local handler methods (#[McpTool]) if
                    // the tool isn't found remotely. This fixes chat-based tool
                    // calls that previously failed when the remote MCP didn't
                    // expose the handler but the app did.
                    $result = $this->runTool($toolName, $arguments);
                    error_log('[StandardMcpHost] Tool result: ' . json_encode($result));
                    $this->conversationHistory[] = [
                        'role' => 'tool',
                        'tool_call_id' => $toolCall['id'] ?? null,
                        'content' => json_encode($result)
                    ];
                }
                // Continue the loop to let LLM process tool results
                continue;
            }

            // If no explicit tool_calls, also check for an instruction to use a specific tool
            // e.g., the assistant message might contain JSON describing a call; skip advanced parsing here.

            // No more tool calls — return assistant content
            return $message['content'] ?? (is_string($message) ? $message : json_encode($message));
        }

        return 'Request too complex; exceeded tool loop iterations.';
    }

    private function callGroq(array $tools): ?array
    {
        if (empty($this->groqApiKey) || !$this->groqClient) return null;

        try {
            $payload = [
                'model' => $this->model,
                'messages' => $this->conversationHistory,
                'tools' => $tools,
                'tool_choice' => 'auto',
                'temperature' => 0.2,
                'max_tokens' => 2000
            ];

            $resp = $this->groqClient->post('chat/completions', ['json' => $payload]);
            $body = (string)$resp->getBody();
            $j = json_decode($body, true);
            return is_array($j) ? $j : null;
        } catch (\Throwable $e) {
            error_log('[StandardMcpHost] Groq API error: ' . $e->getMessage());
            return null;
        }
    }

    public function listResources(): array
    {
        $allResources = [];
        foreach ($this->mcpClients as $serverName => $client) {
            try {
                if (method_exists($client, 'listResources')) {
                    $allResources[$serverName] = $client->listResources();
                } else {
                    $allResources[$serverName] = [];
                }
            } catch (\Throwable $e) {
                error_log("[StandardMcpHost] Failed to list resources from $serverName: " . $e->getMessage());
            }
        }
        return $allResources;
    }

    public function reset(): void
    {
        $this->conversationHistory = [];
    }

    public function getHistory(): array
    {
        return $this->conversationHistory;
    }

    public function setHistory(array $h): void
    {
        $this->conversationHistory = $h;
    }

    public function addToHistory(string $role, string $content): void
    {
        $this->conversationHistory[] = ['role' => $role, 'content' => $content];
    }

    /**
     * Public wrapper to execute a named tool with arguments.
     * Returns the same array shape as executeTool.
     */
    public function runTool(string $toolName, array $arguments): array
    {
        $res = $this->executeTool($toolName, $arguments);
        if (isset($res['success']) && $res['success']) return $res;

        // Fallback: try to invoke local handler-based tools (defined via #[McpTool])
        try {
            $u = new \App\Core\McpUnifier();
            $dis = $u->getAllTools(false);
            $tools = $dis['tools'] ?? [];
            foreach ($tools as $t) {
                $name = $t['name'] ?? $t['id'] ?? null;
                $source = $t['source'] ?? null;
                $handler = $t['handler'] ?? null;
                if ($name === $toolName && $source === 'handlers' && $handler) {
                    // handler expected as '\\Fqcn\\Class::method'
                    if (is_string($handler) && strpos($handler, '::') !== false) {
                        list($class, $method) = explode('::', $handler, 2);
                        if (class_exists($class) && method_exists($class, $method)) {
                            $inst = new $class();
                            // Ensure arguments are passed positionally where possible
                            // If arguments is an associative array, attempt to pass as named via reflection
                            try {
                                $ref = new \ReflectionMethod($class, $method);
                                $params = $ref->getParameters();
                                $args = [];
                                $isSequential = array_keys($arguments) === range(0, max(0, count($arguments) - 1));
                                if ($params && count($params) > 0) {
                                    // match by name where possible
                                    foreach ($params as $p) {
                                        $pname = $p->getName();
                                        if (is_array($arguments) && array_key_exists($pname, $arguments)) {
                                            $args[] = $arguments[$pname];
                                        } elseif ($isSequential && !empty($arguments)) {
                                            $args[] = array_shift($arguments);
                                        } elseif ($p->isDefaultValueAvailable()) {
                                            $args[] = $p->getDefaultValue();
                                        } else {
                                            $args[] = null;
                                        }
                                    }
                                }
                                $result = $ref->invokeArgs($inst, $args);
                            } catch (\ReflectionException $_) {
                                // fallback to call_user_func_array
                                $result = call_user_func_array([$inst, $method], $arguments);
                            }
                            return ['success' => true, 'server' => 'app', 'result' => $result];
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('[StandardMcpHost] local handler fallback error: ' . $e->getMessage());
        }

        // Final fallback: try McpInvoker directly
        try {
            $result = \App\Core\McpInvoker::invoke($toolName, $arguments);
            return ['success' => true, 'server' => 'invoker', 'result' => $result];
        } catch (\Throwable $e) {
            error_log('[StandardMcpHost] McpInvoker fallback error: ' . $e->getMessage());
        }

        return $res;
    }

    /**
     * Get the unified MCP client (if available).
     */
    public function getUnifiedClient(): ?UnifiedMcpClient
    {
        return $this->unifiedClient;
    }

    /**
     * Get the current LLM provider.
     */
    public function getProvider(): ?LLMProviderInterface
    {
        return $this->unifiedClient?->getProvider();
    }

    /**
     * Set the LLM provider.
     */
    public function setProvider(LLMProviderInterface $provider): void
    {
        if ($this->unifiedClient) {
            $this->unifiedClient->setProvider($provider);
            $this->model = $provider->getDefaultModel();
        }
    }

    /**
     * Get provider information for debugging.
     */
    public function getProviderInfo(): array
    {
        if ($this->unifiedClient) {
            return $this->unifiedClient->getProviderInfo();
        }

        // Legacy info
        return [
            'name' => 'groq',
            'style' => 'openai',
            'configured' => !empty($this->groqApiKey),
            'default_model' => $this->model,
            'available_models' => [],
        ];
    }

    /**
     * Get list of configured providers.
     */
    public static function getConfiguredProviders(): array
    {
        return LLMProviderFactory::getConfiguredProviders();
    }
}
