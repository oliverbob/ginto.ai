<?php

declare(strict_types=1);

// This example demonstrates configuring and interacting with multiple
// MCP servers (one stdio, one http)

require __DIR__.'/../vendor/autoload.php';

use PhpMcp\Client\Client;
use PhpMcp\Client\Enum\TransportType;
use PhpMcp\Client\Exception\McpClientException;
use PhpMcp\Client\Model\Capabilities as ClientCapabilities;
use PhpMcp\Client\Model\Content\TextContent;
use PhpMcp\Client\ServerConfig;
use PhpMcp\Client\StreamLogger;

// --- Configuration ---

$clientName = 'MultiServerClientDemo';
$clientVersion = '0.2.0';
$clientCapabilities = ClientCapabilities::forClient(supportsSampling: false);
$logger = new StreamLogger(__DIR__.'/client_multi.log');

$stdioServerConfig = new ServerConfig(
    name: 'local_stdio_server',
    transport: TransportType::Stdio,
    timeout: 10,
    command: 'php',
    args: [__DIR__.'/../../server/samples/php_stdio/server.php']
);

$httpServerConfig = new ServerConfig(
    name: 'http_sample_server',
    transport: TransportType::Http,
    timeout: 30,
    url: 'http://127.0.0.1:8080/mcp/sse'
);

// --- Instantiate Clients ---
$stdioClient = Client::make()
    ->withClientInfo($clientName, $clientVersion)
    ->withCapabilities($clientCapabilities)
    ->withLogger($logger)
    ->withServerConfig($stdioServerConfig)
    ->build();

$httpClient = Client::make()
    ->withClientInfo($clientName, $clientVersion)
    ->withCapabilities($clientCapabilities)
    ->withLogger($logger)
    ->withServerConfig($httpServerConfig)
    ->build();

try {
    // --- Initialize Connections (Blocking) ---
    echo "\nInitializing connection to '{$stdioServerConfig->name}'...\n";
    $stdioClient->initialize();
    echo "Stdio client Initialized.\n";

    echo "\nInitializing connection to '{$httpServerConfig->name}'...\n";
    $httpClient->initialize();
    echo "Http client Initialized.\n";

    // === Interact with STDIO Server ===
    echo "\n--- Interacting with '{$stdioServerConfig->name}' ---\n";
    echo "[1a] Listing Tools...\n";
    $stdioTools = $stdioClient->listTools();
    echo "   Available Tools on {$stdioServerConfig->name}: ".count($stdioTools)."\n";

    echo "[2a] Calling 'greeter' tool...\n";
    try {
        $stdioCallResult = $stdioClient->callTool('greeter', ['name' => 'Stdio User']);
        if ($stdioCallResult->isSuccess()) {
            /** @var TextContent|null $content */
            $content = $stdioCallResult->content[0] ?? null;
            echo "   Tool 'greeter' Result: ".($content->text ?? 'N/A')."\n";
        } else {
            echo "   Tool 'greeter' reported an error.\n";
        }
    } catch (McpClientException $e) {
        echo "   Error calling tool 'greeter': {$e->getMessage()}\n";
    }

    // === Interact with HTTP Server ===
    echo "\n--- Interacting with '{$httpServerConfig->name}' ---\n";
    echo "[1b] Listing Tools...\n";
    $httpTools = $httpClient->listTools();
    echo "   Available Tools on {$httpServerConfig->name}: ".count($httpTools)."\n";

    echo "[2b] Calling 'greet_user' tool...\n";
    try {
        $httpCallResult = $httpClient->callTool('greet_user', ['name' => 'HTTP User']);
        if ($httpCallResult->isSuccess()) {
            $content = $httpCallResult->content[0] ?? null;
            echo "   Tool 'greet_user' Result: ".($content->text ?? 'N/A')."\n";
        } else {
            echo "   Tool 'greet_user' reported an error.\n";
        }
    } catch (McpClientException $e) {
        echo "   Error calling tool 'greet_user': {$e->getMessage()}\n";
    }
} catch (McpClientException $e) {
    echo "\n MCP Client Error during setup or operation: ".get_class($e).' - '.$e->getMessage()."\n";
} catch (Throwable $e) {
    echo "\n Unexpected Error: ".get_class($e).' - '.$e->getMessage()."\n";
    echo $e->getTraceAsString()."\n";
} finally {
    // IMPORTANT: Disconnect all client instances
    echo "\nDisconnecting from '{$stdioServerConfig->name}'...\n";
    $stdioClient->disconnect();
    echo "Disconnected.\n";

    echo "\nDisconnecting from '{$httpServerConfig->name}'...\n";
    $httpClient->disconnect();
    echo "Disconnected.\n";
}
