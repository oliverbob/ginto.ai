# Changelog

All notable changes to `php-mcp/client` will be documented in this file.

## v1.0.0 - 2025-05-06

### v1.0.0 - Initial Release

🚀 **Introducing `php-mcp/client` v1.0.0!**

I'm thrilled to announce the first stable release of the PHP client library for the Model Context Protocol (MCP). Following the release of [`php-mcp/server`](https://github.com/php-mcp/server), this package provides the other essential piece, enabling your PHP applications to easily connect to and interact with any MCP-compliant server.

It offers a robust, flexible, and developer-friendly solution designed specifically for the PHP ecosystem.

#### ✨ Key Features

* **Client-per-Server Architecture:** Aligns with the MCP specification's core model where each `Client` instance manages a dedicated, stateful connection to a single configured MCP server.
* **Fluent Configuration Builder:** Set up client instances easily using the `Client::make()->with...()->build()` pattern, configuring client identity, capabilities, and the specific server connection details (`ServerConfig`).
* **Dual API for Flexibility:**
    * **Synchronous Facade:** Interact with MCP servers using straightforward, blocking methods (e.g., `$client->initialize()`, `$client->listTools()`, `$client->callTool(...)`) for simple integration into traditional PHP scripts and frameworks. The underlying asynchronous complexity is handled internally.
    * **Asynchronous API:** Access Promise-based methods (e.g., `$client->initializeAsync()`, `$client->listToolsAsync()`, `$client->callToolAsync(...)`) for advanced use cases, concurrent operations using `React\Promise\all`, or integration into asynchronous PHP applications (ReactPHP, Amp, Swoole).
    
* **Multiple Transport Support:**
    * **`stdio`:** Seamlessly connect to and manage local MCP server processes via standard input/output, ideal for command-line tools or embedded servers. Uses `react/child-process` internally.
    * **`http`:** Connect to remote or local MCP servers over HTTP, handling POST requests for client messages and Server-Sent Events (SSE) for server messages and notifications. Uses `react/http` internally.
    
* **Explicit Connection Lifecycle:** Clear methods (`initialize`/`initializeAsync`, `disconnect`/`disconnectAsync`) to manage the stateful connection and MCP handshake process.
* **Full MCP Core Feature Support:** Implements core MCP operations:
    * Capability Negotiation (`initialize`)
    * Listing Tools, Resources, Prompts, Resource Templates
    * Calling Tools (`tools/call`)
    * Reading Resources (`resources/read`)
    * Getting Prompts (`prompts/get`)
    * Resource Subscriptions (`resources/subscribe`, `resources/unsubscribe`) *(Requires server capability)*
    * Server Log Level Control (`logging/setLevel`) *(Requires server capability)*
    * Connectivity Check (`ping`)
    
* **PSR Compliance:**
    * Integrates with `PSR-3 (LoggerInterface)` for flexible logging.
    * Supports optional `PSR-16 (SimpleCacheInterface)` for caching server definitions (tools, resources, etc.) via `DefinitionCache`.
    * Supports optional `PSR-14 (EventDispatcherInterface)` for handling server-sent notifications (e.g., `ResourceChanged`, `ToolsListChanged`) asynchronously.
    
* **Robust Error Handling:** Provides a hierarchy of specific exceptions (`ConfigurationException`, `ConnectionException`, `HandshakeException`, `RequestException`, `TimeoutException`, `TransportException`, `UnsupportedCapabilityException`, etc.) for predictable error management.
* **Asynchronous Core:** Built on ReactPHP's event loop and promises for efficient, non-blocking I/O handling crucial for `stdio` and `http+sse` transports.

#### 🚀 Getting Started

1. **Install:**
    
    ```bash
    composer require php-mcp/client
    
    ```
2. **Configure:** Define your server connection using `ServerConfig` and build a client instance.
    
    ```php
    use PhpMcp\Client\Client;
    use PhpMcp\Client\Enum\TransportType;
    use PhpMcp\Client\Model\ClientInfo;
    use PhpMcp\Client\ServerConfig;
    
    $serverConfig = new ServerConfig(
        name: 'my_stdio_server',
        transport: TransportType::Stdio,
        command: ['php', '/path/to/your/mcp_server.php'],
        timeout: 15
    );
    
    $clientInfo = new ClientInfo('MyPHPApp', '1.0');
    
    $client = Client::make()
        ->withClientName($clientInfo->name) // Pass name/version directly
        ->withClientVersion($clientInfo->version)
        ->withServerConfig($serverConfig)
        // ->withLogger(new MyLogger()) // Optional
        ->build();
    
    ```
3. **Initialize & Interact (Sync Example):**
    
    ```php
    try {
        $client->initialize(); // Connect & Handshake (blocks)
        $tools = $client->listTools(); // Make request (blocks)
        print_r($tools);
        // ... other interactions ...
    } catch (\Throwable $e) {
        echo "Error: " . $e->getMessage();
    } finally {
        $client->disconnect(); // Disconnect (blocks)
    }
    
    ```

#### Documentation & Examples

Please refer to the [README.md](README.md) for detailed usage instructions, configuration options, explanations of the sync vs. async APIs, error handling, and more.

Working examples can be found in the [`examples/`](./examples/) directory, covering:

* Basic synchronous stdio (`01-simple-stdio-sync.php`)
* Basic synchronous http (`02-simple-http-sync.php`)
* Using multiple client instances (`03-multiple-servers-sync.php`)
* Asynchronous operations with promises (`04-multiple-servers-async.php`)
* Integration with `openai-php` for tool usage (`05-openai-php-integration-sync`)

### Looking Ahead

This initial release provides a solid foundation for MCP client interactions in PHP. Future development may include:

* Adding support for more advanced MCP capabilities (e.g., richer client-side `roots` declaration).
* Enhanced session management helpers for the HTTP transport.
* A shared `php-mcp/core` package for common definitions.

Feedback, bug reports, and contributions are duly welcomed! Thank you for trying `php-mcp/client`.
