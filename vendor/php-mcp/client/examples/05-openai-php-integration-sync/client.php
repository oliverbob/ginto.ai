<?php

declare(strict_types=1);

// Example: Interactive CLI chat using OpenAI functions/tools powered by MCP servers.

require __DIR__.'/vendor/autoload.php';

use Dotenv\Dotenv;
use PhpMcp\Client\Client;
use PhpMcp\Client\Enum\TransportType;
use PhpMcp\Client\Exception\McpClientException;
use PhpMcp\Client\Exception\RequestException;
use PhpMcp\Client\Model\Capabilities as ClientCapabilities;
use PhpMcp\Client\Model\Content\TextContent;
use PhpMcp\Client\ServerConfig;
use PhpMcp\Client\StreamLogger;
use Psr\Log\LoggerInterface;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$openaiApiKey = $_ENV['OPENAI_API_KEY'] ?? null;
if (! $openaiApiKey) {
    echo "Error: OPENAI_API_KEY not found in .env file.\n";
    exit(1);
}

$logger = new StreamLogger(__DIR__.'/openai_client.log');

$clientName = 'OpenAI-MCP-Demo';
$clientVersion = '1.0';
$clientCapabilities = ClientCapabilities::forClient();

$pathToStdioServerScript = __DIR__.'/../../../server/samples/php_stdio/server.php';
$httpServerUrl = 'http://127.0.0.1:8080/mcp/sse';

$stdioServerConfig = new ServerConfig(
    name: 'local_stdio',
    transport: TransportType::Stdio,
    timeout: 15,
    command: 'php',
    args: [$pathToStdioServerScript]
);

$httpServerConfig = new ServerConfig(
    name: 'http_web',
    transport: TransportType::Http,
    timeout: 45,
    url: $httpServerUrl
);

$firecrawlMcpServerConfig = new ServerConfig(
    name: 'firecrawl',
    transport: TransportType::Stdio,
    timeout: 45,
    command: 'env',
    args: [
        'FIRECRAWL_API_KEY=fc-f6bc6f23c9554cecb64a3feecc802d26',
        'npx',
        '-y',
        'firecrawl-mcp',
    ]
);

$serversToConfigure = [
    'stdio' => $stdioServerConfig,
    'http' => $httpServerConfig,
    'firecrawl' => $firecrawlMcpServerConfig,
];

$mcpClients = [];
$mcpClientInitStatus = [];

echo "Building MCP clients...\n";
foreach ($serversToConfigure as $serverName => $serverConfig) {
    try {
        $mcpClients[$serverName] = Client::make()
            ->withClientInfo($clientName, $clientVersion)
            ->withCapabilities($clientCapabilities)
            ->withLogger($logger)
            ->withServerConfig($serverConfig)
            ->build();

        $mcpClientInitStatus[$serverName] = false;
    } catch (Throwable $e) {
        echo "[Error] Failed to build client for '{$serverName}': {$e->getMessage()}\n";
        $logger->error("Failed to build MCP client for '{$serverName}'", ['exception' => $e]);
    }
}

echo "\nInitializing MCP client connections...\n";
foreach ($mcpClients as $serverName => $mcpClient) {
    try {
        $mcpClient->initialize();
        $mcpClientInitStatus[$serverName] = true;
    } catch (Throwable $e) {
        $logger->error("Failed to initialize MCP client for '{$serverConfig->name}'", ['exception' => $e]);
    }
}
echo "Initialization complete.\n";

$openaiClient = OpenAI::client($openaiApiKey);

// --- Helper Functions ---

/**
 * Fetches tools from *initialized* MCP clients and formats for OpenAI.
 *
 * @param  array<string, Client>  $initializedMcpClients  Map of server name => initialized Client instance
 */
function getOpenAiTools(array $initializedMcpClients, LoggerInterface $logger): array
{
    $openAiTools = [];
    $toolToServerMap = []; // Maps unique OpenAI tool name -> server name

    foreach ($initializedMcpClients as $serverName => $mcpClient) {
        try {
            $logger->info("Fetching tools from MCP server: {$serverName}");
            $mcpTools = $mcpClient->listTools();
            $logger->info('Found '.count($mcpTools)." tools on {$serverName}.");

            foreach ($mcpTools as $tool) {
                $uniqueToolName = $serverName.'__'.$tool->name; // Unique name format
                $openAiTools[] = [
                    'type' => 'function',
                    'function' => [
                        'name' => $uniqueToolName,
                        'description' => $tool->description ?? 'No description provided.',
                        'parameters' => $tool->inputSchema,
                    ],
                ];

                $toolToServerMap[$uniqueToolName] = $serverName;
            }
        } catch (McpClientException $e) {
            $logger->error("Failed to get tools from MCP server '{$serverName}'", ['exception' => $e]);
            echo "\n[Warning] Could not fetch tools from server '{$serverName}': {$e->getMessage()}\n";
        } catch (Throwable $e) {
            $logger->error("Unexpected error fetching tools from '{$serverName}'", ['exception' => $e]);
            echo "\n[Warning] Unexpected error fetching tools from '{$serverName}': {$e->getMessage()}\n";
        }
    }

    return [$openAiTools, $toolToServerMap];
}

/**
 * Executes requested tool calls via the appropriate MCP client instance.
 *
 * @param  array<string, Client>  $initializedMcpClients  Map of server name => initialized Client instance
 * @param  array  $toolCalls  Tool calls from OpenAI response
 * @param  array  $toolToServerMap  Map from unique OpenAI name to MCP server name
 */
function executeMcpToolCalls(array $initializedMcpClients, array $toolCalls, array $toolToServerMap, LoggerInterface $logger): array
{
    $toolResults = [];

    foreach ($toolCalls as $toolCall) {
        $uniqueToolName = $toolCall->function->name;
        $mcpServerName = $toolToServerMap[$uniqueToolName] ?? null;
        $mcpToolName = $mcpServerName ? substr($uniqueToolName, strlen($mcpServerName) + 2) : null;
        $arguments = json_decode($toolCall->function->arguments, true);
        $toolCallId = $toolCall->id;

        if (! $mcpServerName || ! $mcpToolName) {
            $logger->error('Could not map OpenAI tool call to MCP server/tool', ['openai_name' => $uniqueToolName]);
            $errorContent = json_encode(['error' => "Client configuration error: Tool mapping failed for '{$uniqueToolName}'."]);
            $toolResults[] = ['tool_call_id' => $toolCallId, 'role' => 'tool', 'name' => $uniqueToolName, 'content' => $errorContent];

            continue;
        }

        $mcpClient = $initializedMcpClients[$mcpServerName];

        echo "\n   -> Executing MCP Tool: [{$mcpServerName}] {$mcpToolName} with args: ".json_encode($arguments)."\n";
        $logger->info('Executing MCP tool call', ['server' => $mcpServerName, 'tool' => $mcpToolName, 'args' => $arguments]);

        try {
            $mcpResult = $mcpClient->callTool($mcpToolName, $arguments);

            $resultString = 'Tool execution successful.';
            if (! empty($mcpResult->content)) {
                $firstContent = $mcpResult->content[0];
                $isMcpError = $mcpResult->isError;

                if ($firstContent instanceof TextContent) {
                    $resultString = $firstContent->text;
                } else {
                    $resultString = json_encode($firstContent->toArray());
                }

                if ($isMcpError) {
                    $logger->warning('MCP tool reported an error', ['server' => $mcpServerName, 'tool' => $mcpToolName, 'result' => $resultString]);
                    $resultString = json_encode(['mcp_tool_error' => $resultString]);
                }
            }

            echo '   <- MCP Result: '.(strlen($resultString) > 80 ? substr($resultString, 0, 80).'...' : $resultString)."\n";
            $logger->info('MCP tool call successful', ['server' => $mcpServerName, 'tool' => $mcpToolName, 'result_preview' => substr($resultString, 0, 100)]);
            $toolResults[] = ['tool_call_id' => $toolCallId, 'role' => 'tool', 'name' => $uniqueToolName, 'content' => $resultString];

        } catch (RequestException $e) {
            $errorData = $e->getRpcError();
            $errMsg = $errorData ? "Server Error Code {$errorData->code} - {$errorData->message}" : $e->getMessage();
            echo "   <- MCP Error: {$errMsg}\n";
            $logger->error('MCP request failed', ['server' => $mcpServerName, 'tool' => $mcpToolName, 'error' => $errMsg, 'exception' => $e]);
            $toolResults[] = ['tool_call_id' => $toolCallId, 'role' => 'tool', 'name' => $uniqueToolName, 'content' => json_encode(['error' => "MCP Request Failed: {$errMsg}"])];
        } catch (Throwable $e) {
            echo '   <- MCP Error: '.get_class($e)." - {$e->getMessage()}\n";
            $logger->error('MCP client error during tool call', ['server' => $mcpServerName, 'tool' => $mcpToolName, 'exception' => $e]);
            $toolResults[] = ['tool_call_id' => $toolCallId, 'role' => 'tool', 'name' => $uniqueToolName, 'content' => json_encode(['error' => "Client execution failed: {$e->getMessage()}"])];
        }
    }

    return $toolResults;
}

// --- Main Interactive Loop ---

echo "\nWelcome to the MCP + OpenAI Chat Demo!\n";
echo "Enter your prompt below or type 'quit' to exit.\n";

$messages = [
    // ['role' => 'system', 'content' => 'You are a helpful assistant that can use tools.'],
];

$initializedClients = array_filter(
    $mcpClients,
    fn ($client) => $mcpClientInitStatus[$client->serverConfig->name] ?? false,
);

echo "\nFetching initial tools...\n";
[$initialOpenAiTools, $initialToolToServerMap] = getOpenAiTools($initializedClients, $logger);
$availableTools = $initialOpenAiTools;
$toolMap = $initialToolToServerMap;
echo count($availableTools)." tools ready.\n";

while (true) {
    $input = readline("\nPrompt: ");

    if ($input === false || strtolower($input) === 'quit' || strtolower($input) === 'exit') {
        break;
    }

    if (empty(trim($input))) {
        continue;
    }

    $messages[] = ['role' => 'user', 'content' => $input];

    // Keep track of tool calls within this turn
    $currentTurnToolCalls = [];

    try {
        $maxToolLoops = 5;

        for ($toolLoop = 0; $toolLoop < $maxToolLoops; $toolLoop++) {
            $params = [
                'model' => 'gpt-4o-mini',
                'messages' => $messages,
                'stream' => true,
            ];

            // Refresh tools on each loop? Or use cached?
            // [$availableTools, $toolMap] = getOpenAiTools($initializedClients, $logger);
            if (! empty($availableTools)) {
                $params['tools'] = $availableTools;
                $params['tool_choice'] = 'auto';
            } else {
                echo "[No MCP tools available for this turn]\n";
            }

            echo "\nAssistant: ";
            $stream = $openaiClient->chat()->createStreamed($params);

            $fullResponse = '';
            /** @var array<int, array{id: string, type: string, function: array{name: string, arguments: string}}> */
            $currentToolCalls = [];
            $lastToolCallIndex = -1;

            foreach ($stream as $response) {
                /** @var \OpenAI\Responses\Chat\CreateStreamedResponse $response */
                $delta = $response->choices[0]->delta;

                // Accumulate Text Content
                if ($delta->content) {
                    echo $delta->content;
                    $fullResponse .= $delta->content;
                }

                // Accumulate Tool Calls based on ID presence
                if (! empty($delta->toolCalls)) {
                    foreach ($delta->toolCalls as $toolCallChunk) {
                        // Check if this chunk signifies a NEW tool call (has an ID)
                        if ($toolCallChunk->id !== null) {
                            // Ensure it has the necessary function info too
                            if ($toolCallChunk->type === 'function' && $toolCallChunk->function->name !== null) {
                                $currentToolCalls[] = [
                                    'id' => $toolCallChunk->id,
                                    'type' => $toolCallChunk->type,
                                    'function' => [
                                        'name' => $toolCallChunk->function->name,
                                        'arguments' => $toolCallChunk->function->arguments ?? '', // Start arguments
                                    ],
                                ];

                                $lastToolCallIndex = count($currentToolCalls) - 1; // Update last index
                            } else {
                                echo "\n[WARN: Received tool call chunk with ID but missing function details]\n";
                            }
                        }
                        // Else, if ID is null, append arguments to the last known tool call
                        elseif ($lastToolCallIndex >= 0) {
                            $currentToolCalls[$lastToolCallIndex]['function']['arguments'] .= $toolCallChunk->function->arguments;
                        }
                    }
                }

                // Check if stream finished
                if ($response->choices[0]->finishReason !== null) {
                    break;
                }
            }
            echo "\n"; // Newline after assistant response

            // Add the assistant's final message
            $messages[] = [
                'role' => 'assistant',
                'content' => $fullResponse ?: null,
                'tool_calls' => ! empty($currentToolCalls) ? $currentToolCalls : null,
            ];

            // If no tool calls were received, break out of the loop
            if (empty($currentToolCalls)) {
                break;
            }

            // Convert to objects for executeMcpToolCalls if needed by that function
            $mcpToolCallRequests = array_map(function ($call) {
                return (object) [
                    'id' => $call['id'],
                    'type' => $call['type'],
                    'function' => (object) [
                        'name' => $call['function']['name'],
                        'arguments' => $call['function']['arguments'],
                    ],
                ];
            }, $currentToolCalls);

            $toolResults = executeMcpToolCalls($mcpClient, $mcpToolCallRequests, $toolMap, $logger);

            // Add tool results to messages for the next OpenAI call
            foreach ($toolResults as $result) {
                $messages[] = $result;
            }
        } // End tool loop

        if ($toolLoop >= $maxToolLoops) {
            echo "\n[Warning] Reached maximum tool execution loops ($maxToolLoops).\n";
        }

    } catch (McpClientException $e) {
        echo "\n[MCP Client Error] ".get_class($e).' - '.$e->getMessage()."\n";
        $logger->error('MCP Client Error in main loop', ['exception' => $e]);
    } catch (Throwable $e) {
        echo "\n[Unexpected Error] ".get_class($e).' - '.$e->getMessage()."\n";
        $logger->critical('Unexpected error in main loop', ['exception' => $e]);
        // echo $e->getTraceAsString() . "\n";
    }
} // End while loop

// --- Cleanup ---
echo "\nExiting. Disconnecting MCP servers...\n";
foreach ($mcpClients as $mcpClient) {
    $mcpClient->disconnect();
}
echo "Disconnected.\n";
