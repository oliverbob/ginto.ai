<?php

declare(strict_types=1);

// This example demonstrates basic synchronous-style usage
// with an HTTP+SSE server, using the Client-per-Server model.

require __DIR__.'/../vendor/autoload.php';

use PhpMcp\Client\Client;
use PhpMcp\Client\Enum\TransportType;
use PhpMcp\Client\Exception\McpClientException;
use PhpMcp\Client\Exception\RequestException;
use PhpMcp\Client\Model\Capabilities as ClientCapabilities;
use PhpMcp\Client\Model\Content\EmbeddedResource;
use PhpMcp\Client\ServerConfig;
use PhpMcp\Client\StreamLogger;

// --- Configuration ---

$clientName = 'MySimplePHPHttpClient';
$clientVersion = '0.1.1';
$clientCapabilities = ClientCapabilities::forClient(supportsSampling: false);
$logger = new StreamLogger(__DIR__.'/client_http.log');

$httpServerConfig = new ServerConfig(
    name: 'http_sample_server',
    transport: TransportType::Http,
    timeout: 30,
    url: 'http://127.0.0.1:8080/mcp/sse'
);

$httpClient = Client::make()
    ->withClientInfo($clientName, $clientVersion)
    ->withCapabilities($clientCapabilities)
    ->withLogger($logger)
    ->withServerConfig($httpServerConfig)
    ->build();

try {
    echo "Attempting to initialize connection to {$httpServerConfig->name} via HTTP+SSE...\n";
    echo "(Ensure server is running at: {$httpServerConfig->url})\n";

    $httpClient->initialize();

    echo "Connection to {$httpServerConfig->name} initialized successfully!\n";
    echo "Server: {$httpClient->getServerName()} v{$httpClient->getServerVersion()}, Protocol: {$httpClient->getNegotiatedProtocolVersion()}\n";

    // 1. List Tools
    echo "\n[1] Listing Tools...\n";

    $tools = $httpClient->listTools();

    if (empty($tools)) {
        echo "   No tools found on the server.\n";
    } else {
        echo "   Available Tools:\n";
        foreach ($tools as $tool) {
            echo "   - {$tool->name}".($tool->description ? " ({$tool->description})" : '')."\n";
        }
    }

    // 2. Call a Tool
    echo "\n[2] Calling 'greet_user' tool...\n";
    $toolName = 'greet_user';
    $arguments = ['name' => 'HTTP Client', 'count' => 1];
    try {
        $callResult = $httpClient->callTool($toolName, $arguments);

        if ($callResult->isSuccess()) {
            $textContent = $callResult->content[0] ?? null;
            echo "   Tool '{$toolName}' Result: ".$textContent->text."\n";
        } else {
            $errorContent = $callResult->content[0] ?? null;
            echo "   Tool '{$toolName}' reported an error: {$errorContent->text}\n";
        }
    } catch (RequestException $e) {
        if ($e->getRpcError()) {
            echo "   Error calling tool '{$toolName}': Server Error Code {$e->getRpcError()->code} - {$e->getRpcError()->message}\n";
        } else {
            echo "   Error calling tool '{$toolName}': {$e->getMessage()}\n";
        }
    } catch (McpClientException $e) {
        echo "   Error calling tool '{$toolName}': {$e->getMessage()}\n";
    }

    // 3. List Resources
    echo "\n[3] Listing Resources...\n";
    $resources = $httpClient->listResources();

    if (empty($resources)) {
        echo "   No resources found on the server.\n";
    } else {
        echo "   Available Resources:\n";
        foreach ($resources as $resource) {
            echo "   - {$resource->uri}".($resource->name ? " (Name: {$resource->name})" : '')."\n";
        }
    }

    // 4. Read a Resource
    $resourceUri = 'config://app/name';
    echo "\n[4] Reading resource '{$resourceUri}'...\n";

    try {
        $readResult = $httpClient->readResource($resourceUri);
        $resourceContent = $readResult->contents[0] ?? null;
        if ($resourceContent instanceof EmbeddedResource) {
            echo "   Resource MIME Type: {$resourceContent->mimeType}\n";
            echo '   Resource Content: '.($resourceContent->text ?? '[Binary Data]')."\n";
        } else {
            echo "   Resource '{$resourceUri}' not found, empty, or invalid format.\n";
        }
    } catch (RequestException $e) {
        if ($e->getRpcError()) {
            echo "   Error reading resource '{$resourceUri}': Server Error Code {$e->getRpcError()->code} - {$e->getRpcError()->message}\n";
        } else {
            echo "   Error reading resource '{$resourceUri}': {$e->getMessage()}\n";
        }
    } catch (McpClientException $e) {
        echo "   Error reading resource '{$resourceUri}': {$e->getMessage()}\n";
    }

    // 5. Ping the server
    echo "\n[5] Pinging server...\n";

    try {
        $httpClient->ping();
        echo "   Ping successful!\n";
    } catch (McpClientException $e) {
        echo "   Ping failed: {$e->getMessage()}\n";
    }

} catch (McpClientException $e) {
    echo "\n MCP Client Error: ".get_class($e).' - '.$e->getMessage()."\n";
    echo "   Check if the MCP HTTP server is running at {$httpServerConfig->url} and accessible.\n";
    echo "   Check the client_http.log file for more details.\n";
} catch (Throwable $e) {
    echo "\n Unexpected Error: ".get_class($e).' - '.$e->getMessage()."\n";
    echo $e->getTraceAsString()."\n";
} finally {
    // IMPORTANT: Always disconnect when finished
    if (isset($httpClient)) {
        echo "\nDisconnecting from server '{$httpServerConfig->name}'...\n";
        $httpClient->disconnect();
        echo "Disconnected.\n";
    }
}
