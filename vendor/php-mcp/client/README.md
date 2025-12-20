# PHP MCP Client

[![Latest Version on Packagist](https://img.shields.io/packagist/v/php-mcp/client.svg?style=flat-square)](https://packagist.org/packages/php-mcp/client)
[![Total Downloads](https://img.shields.io/packagist/dt/php-mcp/client.svg?style=flat-square)](https://packagist.org/packages/php-mcp/client)
[![Tests](https://img.shields.io/github/actions/workflow/status/php-mcp/client/tests.yml?branch=main&style=flat-square)](https://github.com/php-mcp/client/actions/workflows/tests.yml)
[![License](https://img.shields.io/packagist/l/php-mcp/client.svg?style=flat-square)](LICENSE)

**PHP MCP Client is a PHP library for interacting with servers that implement the Model Context Protocol (MCP).**

It provides a developer-friendly interface to connect to individual MCP servers using different transports (`stdio`, `http+sse`), manage the connection lifecycle, discover server capabilities (Tools, Resources, Prompts), and execute requests like calling tools or reading resources.

While utilizing asynchronous I/O internally via ReactPHP for robustness and handling features like server-sent events, the library offers **both** a straightforward **synchronous (blocking) API** for common use cases and an **asynchronous (Promise-based) API** for advanced control and concurrency.

This library aligns with the MCP specification's model where one client instance manages a stateful connection to one server.

## Introduction to MCP

The [Model Context Protocol (MCP)](https://modelcontextprotocol.io/introduction) is an open standard designed to standardize how AI assistants and applications connect to external data sources, APIs, and tools (like codebases, databases, web browsers). It acts as a communication layer, enabling AI models (like Claude, or models integrated via frameworks like OpenAI's) to securely access and interact with context provided by different servers.

This client library allows your PHP application (acting as the "Host" in MCP terminology) to *consume* capabilities offered by one or more MCP servers.

## Features

*   **Client-per-Server Model:** Each `Client` instance manages a stateful connection to a single configured MCP server, aligning with the MCP specification.
*   **Fluent Configuration:** Easy setup for each client instance using a `Client::make()->with...()` builder pattern.
*   **Dual API:**
    *   **Synchronous Facade:** Interact with the server using straightforward, blocking methods (e.g., `$client->listTools()`, `$client->callTool(...)`) for simple integration.
    *   **Asynchronous API:** Access underlying Promise-based methods (e.g., `$client->listToolsAsync()`, `$client->callToolAsync(...)`) for concurrency and integration with async PHP applications.
*   **Multiple Transports:** Built-in support for:
    *   `stdio`: Communicating with server processes via Standard Input/Output.
    *   `http`: Communicating with servers via HTTP POST and Server-Sent Events (SSE).
*   **Explicit Connection Lifecycle:** Requires `->initialize()` or `->initializeAsync()` to connect and perform the handshake before making requests. Provides `disconnect()` / `disconnectAsync()`.
*   **Tool/Resource/Prompt Interaction:** Provides comprehensive methods (sync & async) to list available elements and execute requests like `tools/call`, `resources/read`, `prompts/get`.
*   **PSR Compliance:** Integrates with standard PHP interfaces:
    *   `PSR-3` (LoggerInterface): Integrate your application's logger.
    *   `PSR-16` (SimpleCacheInterface): Optional caching for server definitions.
    *   `PSR-14` (EventDispatcherInterface): Optional handling of server-sent notifications via events (requires async handling).
*   **Robust Error Handling:** Specific exceptions for different failure modes.
*   **Asynchronous Core:** Utilizes ReactPHP internally for non-blocking I/O.

## Requirements

*   PHP >= 8.1
*   Composer
*   *(For Stdio Transport)*: Ability to execute the server command.
*   *(For Http Transport)*: Network access to the MCP server URL.

## Installation

Install the package via Composer:

```bash
composer require php-mcp/client
```

The necessary ReactPHP dependencies (`event-loop`, `promise`, `stream`, `child-process`, `http`) should be installed automatically.

## Quick Start: Simple Synchronous Usage (Stdio)

This example connects to a local filesystem server running via `npx`.

```php
<?php

require 'vendor/autoload.php';

use PhpMcp\Client\Client;
use PhpMcp\Client\Enum\TransportType;
use PhpMcp\Client\Model\Capabilities as ClientCapabilities;
use PhpMcp\Client\ServerConfig;
use PhpMcp\Client\Exception\McpClientException;

$clientCapabilities = ClientCapabilities::forClient(); // Default client caps

$userHome = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? getcwd();
$fsServerConfig = new ServerConfig(
    name: 'local_filesystem',
    transport: TransportType::Stdio,
    timeout: 15,
    command: 'npx',
    args: [
        '-y',
        '@modelcontextprotocol/server-filesystem',
        $userHome . '/Documents',
    ],
    workingDir: $userHome
);

$fsClient = Client::make()
    ->withClientInfo('MyFileSystemApp', '1.0')
    ->withCapabilities($clientCapabilities)
    // ->withLogger(new MyPsrLogger()) // Optional
    ->withServerConfig($fsServerConfig)
    ->build();

try {
    // Initialize Connection (BLOCKING)
    $fsClient->initialize();

    // Interact (Synchronously)
    $tools = $fsClient->listTools(); // Blocking call
    foreach ($tools as $tool) {
        echo "- Tool: {$tool->name}\n";
    }

    // ... Call other methods like $fsClient->callTool(...) ...

} catch (McpClientException $e) {
    echo "[MCP ERROR] " . get_class($e) . ": " . $e->getMessage() . "\n";
    // Check $e->getPrevious() for underlying transport/process errors
} catch (\Throwable $e) {
    echo "[UNEXPECTED ERROR] " . $e->getMessage() . "\n";
} finally {
    // Disconnect (BLOCKING)
    if (isset($fsClient)) {
        $fsClient->disconnect();
    }
}
```

## Configuration

Configuration involves setting up:

1.  **Client Identity:** Your application's name and version, passed directly to the builder.
2.  **Client Capabilities:** Features your client supports using `ClientCapabilities`.
3.  **Server Connection:** Details for the *single server* this client instance will connect to, using `ServerConfig`.
4.  **(Optional) Dependencies:** Logger, Cache, Event Dispatcher, Event Loop.

### `ClientCapabilities`

Declares features your client supports. Use the static factory method.

```php
use PhpMcp\Client\Model\Capabilities as ClientCapabilities;

// Client supports sampling requests from the server
$clientCapabilities = ClientCapabilities::forClient(supportsSampling: true);

// Client does NOT support sampling
$clientCapabilities = ClientCapabilities::forClient(supportsSampling: false);

// TODO: Add support for declaring 'roots' capability if needed
```

### `ServerConfig`

Defines how to connect to a *single* MCP server.

```php
use PhpMcp\Client\Enum\TransportType;
use PhpMcp\Client\ServerConfig;

// Example: Stdio Server
$stdioConfig = new ServerConfig(
    name: 'local_file_server',       // Required: Unique ID for this config
    transport: TransportType::Stdio, // Required: Transport type
    timeout: 15.0,                   // Optional: Request timeout (seconds)
    command: 'npx',                  // Required for Stdio: Executable
    args: [                          // Optional for Stdio: Arguments array
        '-y',
        '@modelcontextprotocol/server-filesystem',
        '/path/to/project'
    ],
    workingDir: '/path/to/project',  // Optional for Stdio: Working directory
    env: ['DEBUG' => 'mcp*']         // Optional for Stdio: Environment variables
);

// Example: HTTP Server
$httpConfig = new ServerConfig(
    name: 'remote_web_agent',        // Required: Unique ID
    transport: TransportType::Http,  // Required: Transport type
    timeout: 45.0,                   // Optional: Request timeout
    url: 'http://localhost:8080',    // Required for Http: Base URL (NO /sse)
    headers: [                       // Optional for Http: Auth/Custom headers
        'Authorization' => 'Bearer xyz789'
    ],
    // sessionId: 'sess_abcdef'      // Optional for Http: External session ID
);
```

### Loading Config from Array/JSON

You can easily parse configurations stored in arrays (e.g., from JSON files or framework config).

```php
use PhpMcp\Client\ServerConfig;
use PhpMcp\Client\Exception\ConfigurationException;

$jsonConfig = '{
    "mcpServers": {
        "stdio_files": {
            "command": "php",
            "args": ["/app/mcp/file_server.php"],
            "timeout": 10
        },
        "http_api": {
            "url": "https://api.example.com/mcp",
            "transport": "http",
            "headers": {"X-API-Key": "secret"}
        }
    }
}';

$decodedConfig = json_decode($jsonConfig, true)['mcpServers'] ?? [];

$serverConfigs = [];
foreach ($decodedConfig as $name => $data) {
    try {
        $serverConfigs[$name] = ServerConfig::fromArray($name, $data);
    } catch (ConfigurationException $e) {
        echo "Error parsing config for '{$name}': {$e->getMessage()}\n";
    }
}

// Now $serverConfigs['stdio_files'] and $serverConfigs['http_api']
// contain ServerConfig objects.
```

### `ClientBuilder`

Use the builder to assemble the `Client` instance:

```php
use PhpMcp\Client\Client;
// ... other use statements for Config, Logger etc...

$client = Client::make()
    ->withClientInfo($clientName, $clientVersion) // Required
    ->withCapabilities($clientCapabilities)       // Optional (defaults provided)
    ->withServerConfig($stdioConfig)              // Required: Config for THE server
    ->withLogger($myLogger)                       // Optional
    ->withCache($myCache, 3600)                   // Optional (cache + TTL)
    ->withEventDispatcher($myDispatcher)          // Optional
    ->withIdGenerator($myIdGenerator)             // Optional
    ->withLoop($myEventLoop)                      // Optional (defaults to Loop::get())
    ->build();
```

## Usage

Once you have a configured `Client` instance for a specific server:

**1. Initialize the Connection:**

You *must* call `initialize()` or `initializeAsync()` before making requests.

```php
// Synchronous (Blocking)
try {
    $client->initialize(); // Connects, performs handshake, waits until ready
    echo "Connection Ready!";
} catch (Throwable $e) {
    echo "Initialization failed: " . $e->getMessage();
    // Handle error... client is likely in Error state
}

// Asynchronous (Promise-based)
$client->initializeAsync()->then(
    function(Client $readyClient) { /* Ready */ },
    function(Throwable $error) { /* Handle init failure */ }
);
// Requires running the event loop ($client->getLoop()->run())
```

**2. Making Requests:**

Use the client methods. They operate on the single connection established by `initialize()`.

*   **Synchronous API (Recommended for simple scripts/frameworks):**
    *   Methods like `listTools()`, `callTool()`, `readResource()` block execution until a response is received or a timeout occurs.
    *   They return the parsed result object (e.g., `array<ToolDefinition>`, `CallToolResult`) or throw an exception (`TimeoutException`, `RequestException`, `ConnectionException`, etc.).

    ```php
    try {
        if ($client->isReady()) { // Check status
            $tools = $client->listTools();
            $result = $client->callTool('myTool', ['param' => 'value']);
        }
    } catch (Throwable $e) { /* Handle errors */ }
    ```

*   **Asynchronous API (For async applications or concurrent requests):**
    *   Methods like `listToolsAsync()`, `callToolAsync()`, `readResourceAsync()` return a `React\Promise\PromiseInterface`.
    *   You need to use promise methods (`then`, `catch`, `finally`) or `React\Async\await` (in a Fiber context) to handle the results.
    *   Requires the event loop to be running.

    ```php
    use function React\Promise\all;

    if ($client->isReady()) {
        $p1 = $client->listToolsAsync();
        $p2 = $client->readResourceAsync('config://settings');

        all([$p1, $p2])->then(
            function(array $results) {
                [$tools, $readResult] = $results;
                // Process async results...
            },
            function(Throwable $error) {
                // Handle async error...
            }
        );
        // $client->getLoop()->run(); // Need to run the loop
    }
    ```

**3. Disconnecting:**

Always disconnect when you are finished interacting with a server to release resources (especially for `stdio` transports).

```php
// Synchronous
$client->disconnect(); // Blocks until closed or timeout

// Asynchronous
$client->disconnectAsync()->then(function() { echo "Disconnected async"; });
// $loop->run();
```

## Available Client Methods

The `Client` class provides methods for interacting with the connected MCP server. Most methods have both a synchronous (blocking) and an asynchronous (Promise-returning) variant.

**Connection & Lifecycle:**

*   **(Sync)** `initialize(): self`
    Connects to the server and performs the MCP handshake. Blocks until ready or throws an exception. Returns the client instance.
*   **(Async)** `initializeAsync(): PromiseInterface<Client>`
    Initiates connection and handshake asynchronously. Returns a promise resolving with the client instance when ready, or rejecting on failure.
*   **(Sync)** `disconnect(): void`
    Closes the connection gracefully. Blocks until disconnection is complete or times out.
*   **(Async)** `disconnectAsync(): PromiseInterface<void>`
    Initiates graceful disconnection asynchronously. Returns a promise resolving when disconnection is complete.
*   `getStatus(): ConnectionStatus`
    Returns the current connection status enum (`Disconnected`, `Connecting`, `Handshaking`, `Ready`, `Closing`, `Closed`, `Error`).
*   `isReady(): bool`
    Helper method, returns `true` if status is `Ready`.
*   `getServerName(): ?string`
    Returns the name of the server (available after successful initialization).
*   `getServerVersion(): ?string`
    Returns the version of the server (available after successful initialization).
*   `getNegotiatedCapabilities(): ?Capabilities`
    Returns the capabilities negotiated with the server (available after successful initialization).
*   `getNegotiatedProtocolVersion(): ?string`
    Returns the protocol version agreed upon with the server (available after successful initialization).

**MCP Operations (Sync):**

*(These methods require the client to be initialized first and will block)*

*   `ping(): void`
*   `listTools(bool $useCache = true): array<ToolDefinition>`
*   `listResources(bool $useCache = true): array<ResourceDefinition>`
*   `listPrompts(bool $useCache = true): array<PromptDefinition>`
*   `listResourceTemplates(bool $useCache = true): array<ResourceTemplateDefinition>`
*   `callTool(string $toolName, array $arguments = []): CallToolResult`
*   `readResource(string $uri): ReadResourceResult`
*   `getPrompt(string $promptName, array $arguments = []): GetPromptResult`
*   `subscribeResource(string $uri): void`
*   `unsubscribeResource(string $uri): void`
*   `setLogLevel(string $level): void`

**MCP Operations (Async):**

*(These methods require the client to be initialized first and return `React\Promise\PromiseInterface`)*

*   `pingAsync(): PromiseInterface<void>`
*   `listToolsAsync(): PromiseInterface<array<ToolDefinition>>`
*   `listResourcesAsync(): PromiseInterface<array<ResourceDefinition>>`
*   `listPromptsAsync(): PromiseInterface<array<PromptDefinition>>`
*   `listResourceTemplatesAsync(): PromiseInterface<array<ResourceTemplateDefinition>>`
*   `callToolAsync(string $toolName, array $arguments = []): PromiseInterface<CallToolResult>`
*   `readResourceAsync(string $uri): PromiseInterface<ReadResourceResult>`
*   `getPromptAsync(string $promptName, array $arguments = []): PromiseInterface<GetPromptResult>`
*   `subscribeResourceAsync(string $uri): PromiseInterface<void>`
*   `unsubscribeResourceAsync(string $uri): PromiseInterface<void>`
*   `setLogLevelAsync(string $level): PromiseInterface<void>`

**Advanced:**

*   `getLoop(): LoopInterface`
    Access the underlying ReactPHP event loop instance.

## Handling Server Notifications (Asynchronous Only)

MCP servers can send notifications (e.g., `resources/didChange`). To receive these:

1.  Configure the client with a PSR-14 `EventDispatcherInterface` using `->withEventDispatcher(...)`.
2.  Add listeners to your dispatcher for events like `PhpMcp\Client\Event\ResourceChanged`.
3.  Use the **asynchronous API** (`initializeAsync`, potentially other `*Async` methods).
4.  **Run the event loop continuously** (`$client->getLoop()->run()`). Notifications arrive via the underlying transport (usually SSE) only while the loop is active.

See `examples/04-handling-notifications.php` for a conceptual guide.

## Error Handling

The client uses specific exceptions inheriting from `PhpMcp\Client\Exception\McpClientException`. Catching these allows for targeted error handling:

*   **`ConfigurationException`**: Thrown during `ClientBuilder::build()` or `ServerConfig::fromArray()` if the provided configuration is invalid or missing required fields (e.g., missing `command` for stdio, invalid `url` for http).
*   **`ConnectionException`**: Thrown by `initialize()` or `initializeAsync()` if the underlying transport connection fails (e.g., stdio process cannot start, TCP connection refused for HTTP, invalid initial response). Also thrown by request methods if called when the client is not in a `Ready` state or if the connection drops unexpectedly during an operation. Check `$e->getPrevious()` for lower-level transport or system errors.
*   **`HandshakeException` (Subclass of `ConnectionException`)**: Thrown specifically by `initialize()` or `initializeAsync()` if the MCP handshake phase fails after the transport connection is established (e.g., server returns an error to the `initialize` request, version mismatch, invalid capabilities received). May contain the server's `JsonRpc\Error` via `getRequestException()->getRpcError()`.
*   **`TransportException`**: Indicates a low-level error during communication *after* connection (e.g., failure to write to stdio stdin, SSE stream error, unexpected data format received from transport). Often wrapped by `ConnectionException`.
*   **`TimeoutException`**: Thrown by synchronous methods (`initialize`, `listTools`, `callTool`, etc.) or rejects asynchronous promises if the server does not respond within the configured `timeout` for the `ServerConfig`. Access timeout value via `$e->getTimeout()`.
*   **`RequestException`**: Thrown by synchronous methods or rejects asynchronous promises when the MCP server successfully processed the request but returned a JSON-RPC error payload (e.g., method not found on server, invalid parameters for a tool, tool execution failed on server). Access the `JsonRpc\Error` object via `$e->getRpcError()` to get the code, message, and optional data from the server.
*   **`UnsupportedCapabilityException`**: Thrown by methods like `subscribeResource()` or `setLogLevel()` if the connected server did not declare support for the required capability during the initial handshake.
*   **`DefinitionException`**: Thrown if there's an error fetching, caching, or parsing server definitions (Tools, Resources, Prompts), often related to cache issues or invalid data structures.
*   **`ProtocolException`**: Indicates a violation of the JSON-RPC 2.0 or MCP structure in messages received from the server (e.g., missing required fields, invalid types).

Always wrap client interactions in `try...catch` blocks to handle these potential failures gracefully.

## Examples

See the [`examples/`](./examples/) directory for working code:

*   `01-simple-stdio-sync.php`: Demonstrates basic synchronous interaction with a `stdio` server.
*   `02-simple-http-sync.php`: Demonstrates basic synchronous interaction with an `http+sse` server.
*   `03-multiple-servers-sync.php`: Shows how to instantiate and use multiple `Client` objects for different servers within the same script (sequentially).
*   `04-multiple-servers-async.php`: Demonstrates asynchronous interaction with multiple servers using Promises for concurrency. Requires running the event loop.
*   `05-openai-php-integration-sync`: Full example integrating with `openai-php` for tool usage using the synchronous client API, including its own `composer.json` setup.

## Testing

```bash
composer install --dev

composer test
```

## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) or open an issue/pull request.

## License

The MIT License (MIT). See [LICENSE](LICENSE).