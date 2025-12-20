<?php

declare(strict_types=1);

namespace PhpMcp\Client;

use PhpMcp\Client\Cache\DefinitionCache;
use PhpMcp\Client\Contracts\TransportInterface;
use PhpMcp\Client\Enum\ConnectionStatus;
use PhpMcp\Client\Exception\ConfigurationException;
use PhpMcp\Client\Exception\ConnectionException;
use PhpMcp\Client\Exception\McpClientException;
use PhpMcp\Client\Exception\ProtocolException;
use PhpMcp\Client\Exception\RequestException;
use PhpMcp\Client\Exception\TimeoutException;
use PhpMcp\Client\Exception\TransportException;
use PhpMcp\Client\Exception\UnsupportedCapabilityException;
use PhpMcp\Client\Factory\MessageIdGenerator;
use PhpMcp\Client\Factory\TransportFactory;
use PhpMcp\Client\JsonRpc\Message;
use PhpMcp\Client\JsonRpc\Notification;
use PhpMcp\Client\JsonRpc\Params\InitializeParams;
use PhpMcp\Client\JsonRpc\Request;
use PhpMcp\Client\JsonRpc\Response;
use PhpMcp\Client\JsonRpc\Results\CallToolResult;
use PhpMcp\Client\JsonRpc\Results\GetPromptResult;
use PhpMcp\Client\JsonRpc\Results\InitializeResult;
use PhpMcp\Client\JsonRpc\Results\ListPromptsResult;
use PhpMcp\Client\JsonRpc\Results\ListResourcesResult;
use PhpMcp\Client\JsonRpc\Results\ListResourceTemplatesResult;
use PhpMcp\Client\JsonRpc\Results\ListToolsResult;
use PhpMcp\Client\JsonRpc\Results\ReadResourceResult;
use PhpMcp\Client\Model\Capabilities;
use PhpMcp\Client\Model\Definitions\ResourceDefinition;
use PhpMcp\Client\Model\Definitions\ToolDefinition;
use PhpMcp\Client\Transport\Stdio\StdioClientTransport;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Throwable;

use function React\Async\await;
use function React\Promise\reject;
use function React\Promise\resolve;

/**
 * Main user-facing class for interacting with a single MCP server.
 * Provides both synchronous-blocking and asynchronous-promise based methods.
 *
 * An explicit initialize() or initializeAsync() call is required before making requests.
 */
class Client
{
    protected readonly LoggerInterface $logger;

    protected readonly LoopInterface $loop;

    protected readonly MessageIdGenerator $idGenerator;

    protected readonly TransportFactory $transportFactory;

    protected readonly ?DefinitionCache $definitionCache;

    protected ConnectionStatus $status = ConnectionStatus::Disconnected;

    protected ?TransportInterface $transport = null;

    protected ?PromiseInterface $connectPromise = null;

    protected ?Deferred $connectRequestDeferred = null;

    /** @var array<string|int, Deferred> Request ID => Deferred mapping */
    protected array $pendingRequests = [];

    protected ?string $serverName = null;

    protected ?string $serverVersion = null;

    protected ?Capabilities $serverCapabilities = null;

    protected ?string $negotiatedProtocolVersion = null;

    protected string $preferredProtocolVersion = '2024-11-05';

    /**
     * @internal Use ClientBuilder::make()->...->build() instead.
     */
    public function __construct(
        public readonly ServerConfig $serverConfig,
        public readonly ClientConfig $clientConfig,
        ?TransportFactory $transportFactory = null,
    ) {
        $this->logger = $this->clientConfig->logger;
        $this->loop = $this->clientConfig->loop;
        $this->idGenerator = $this->clientConfig->idGenerator;
        $this->transportFactory = $transportFactory ?? new TransportFactory($this->clientConfig);

        $this->definitionCache = $this->clientConfig->cache
            ? new DefinitionCache($this->clientConfig->cache, $this->clientConfig->definitionCacheTtl, $this->logger)
            : null;
    }

    public static function make(): ClientBuilder
    {
        return ClientBuilder::make();
    }

    // --- Getters for Connection State ---

    public function getStatus(): ConnectionStatus
    {
        return $this->status;
    }

    public function getServerName(): ?string
    {
        return $this->serverName;
    }

    public function getServerVersion(): ?string
    {
        return $this->serverVersion;
    }

    public function getNegotiatedCapabilities(): ?Capabilities
    {
        return $this->serverCapabilities;
    }

    public function getNegotiatedProtocolVersion(): ?string
    {
        return $this->negotiatedProtocolVersion;
    }

    public function getConnectionStatus(): ConnectionStatus
    {
        return $this->status;
    }

    public function isReady(): bool
    {
        return $this->status === ConnectionStatus::Ready;
    }

    /** For advanced users needing async or event loop access */
    public function getLoop(): LoopInterface
    {
        return $this->loop;
    }

    // --- Connection / Initialization Methods ---

    /**
     * Initiates the asynchronous connection and handshake process.
     * Returns a promise that resolves with $this when ready, or rejects on error.
     * This is the underlying async operation for initialize().
     */
    public function initializeAsync(): PromiseInterface
    {
        if ($this->connectPromise !== null) {
            return $this->connectPromise;
        }

        if ($this->status !== ConnectionStatus::Disconnected && $this->status !== ConnectionStatus::Closed && $this->status !== ConnectionStatus::Error) {
            if ($this->status === ConnectionStatus::Ready) {
                return resolve($this);
            }

            return reject(new ConnectionException("Cannot initialize, client is in unexpected status: {$this->status->value}"));
        }

        $this->logger->info("Initializing connection to server '{$this->getServerName()}'...", ['transport' => $this->serverConfig->transport->value]);

        $this->connectRequestDeferred = new Deferred(function ($_, $reject) {
            $this->logger->info("Initialization attempt for '{$this->getServerName()}' cancelled.");
            $this->handleConnectionFailure(new ConnectionException('Initialization attempt cancelled.'), false);
            if (isset($this->transport) && ($this->status === ConnectionStatus::Connecting || $this->status === ConnectionStatus::Handshaking)) {
                $this->transport->close();
            }
        });

        $this->status = ConnectionStatus::Connecting;

        try {
            $this->transport = $this->transportFactory->create($this->serverConfig);
        } catch (Throwable $e) {
            $this->handleConnectionFailure(new ConfigurationException("Failed to create transport: {$e->getMessage()}", 0, $e));

            return reject($e);
        }

        $this->transport->on('message', $this->handleTransportMessage(...));
        $this->transport->on('error', $this->handleTransportError(...));
        $this->transport->on('close', $this->handleTransportClose(...));
        if ($this->transport instanceof StdioClientTransport) {
            $this->transport->on('stderr', function (string $data) {
                $this->logger->warning("Server '{$this->getServerName()}' STDERR: ".trim($data));
            });
        }

        // --- Define the connection and handshake sequence ---
        $this->transport->connect()->then(
            function () {
                if ($this->status !== ConnectionStatus::Connecting) {
                    throw new ConnectionException("Internal state error: Status was {$this->status->value} after transport connect resolved.");
                }

                $this->logger->info("Transport connected for '{$this->getServerName()}', initiating handshake...");
                $this->status = ConnectionStatus::Handshaking;

                return $this->performHandshake();
            }
        )->then(
            function () {
                // Check status again in case of rapid failure during handshake
                if ($this->status !== ConnectionStatus::Handshaking) {
                    throw new ConnectionException("Connection status changed unexpectedly ({$this->status->value}) during handshake.");
                }

                $this->status = ConnectionStatus::Ready;
                $this->logger->info("Server '{$this->getServerName()}' connection ready.", [
                    'protocol' => $this->negotiatedProtocolVersion,
                    'server' => $this->serverName,
                    'version' => $this->serverVersion,
                ]);

                return $this;
            }
        )->catch(
            function (Throwable $error) {
                $this->logger->error("Connection/Handshake failed for '{$this->getServerName()}': {$error->getMessage()}", ['exception' => $error]);
                $this->handleConnectionFailure($error);
            }
        )->then(
            fn ($connection) => $this->connectRequestDeferred?->resolve($connection),
            fn (Throwable $error) => $this->connectRequestDeferred?->reject($error)
        )->finally(function () {
            $this->connectRequestDeferred = null;
        });

        $this->connectPromise = $this->connectRequestDeferred->promise();

        return $this->connectPromise;
    }

    /**
     * Connects and performs handshake, blocking until ready or failed.
     *
     * @throws ConnectionException|TimeoutException|ConfigurationException|Throwable
     */
    public function initialize(): self
    {
        if ($this->status === ConnectionStatus::Ready) {
            return $this;
        }

        if ($this->connectPromise !== null) {
            $this->logger->debug("Waiting for existing async initialization to complete for '{$this->getServerName()}'...");
        }

        $promise = $this->connectPromise ?? $this->initializeAsync();

        try {
            await($promise);

            if ($this->status !== ConnectionStatus::Ready) {
                throw new ConnectionException("Initialization completed but client status is not Ready ({$this->status->value}).");
            }

            return $this;
        } catch (Throwable $e) {
            if ($this->status !== ConnectionStatus::Error && $this->status !== ConnectionStatus::Closed) {
                $this->logger->warning("Forcing error status after initialize() await failed for '{$this->getServerName()}'.");
                $this->handleConnectionFailure($e, false);
            }
            throw $e;
        }
    }

    /**
     * Disconnects the client asynchronously.
     */
    public function disconnectAsync(): PromiseInterface
    {
        if (! isset($this->transport)) {
            $this->status = ConnectionStatus::Closed;

            return resolve(null);
        }

        if ($this->status === ConnectionStatus::Closing || $this->status === ConnectionStatus::Closed) {
            return resolve(null);
        }

        if ($this->connectRequestDeferred) {
            $this->connectRequestDeferred->reject(new ConnectionException('Disconnect called during initialization.'));
        }

        $this->logger->info("Disconnecting from server '{$this->getServerName()}'...");
        $this->status = ConnectionStatus::Closing;

        $deferred = new Deferred;

        $this->rejectPendingRequests(new ConnectionException('Connection closing.'));

        $listener = function ($reason = null) use ($deferred) {
            $this->logger->info("Transport closed for server  '{$this->getServerName()}'.", ['reason' => $reason]);

            if ($this->status !== ConnectionStatus::Closed) {
                $this->status = ConnectionStatus::Closed;
                $this->cleanupTransport();
                $deferred->resolve(null);
            }
        };

        $this->transport->once('close', $listener);

        $this->transport->close();

        $closeTimeout = 5.0;
        $operationName = "Disconnect from '{$this->getServerName()}'";

        return Utils::timeout($deferred->promise(), $closeTimeout, $this->loop, $operationName)
            ->catch(function (Throwable $error) {
                if ($this->status !== ConnectionStatus::Closed && $this->status !== ConnectionStatus::Error) {
                    $this->handleConnectionFailure($error, false);
                }

                throw $error;
            });
    }

    /**
     * Disconnects the client, blocking until complete or timeout.
     */
    public function disconnect(): void
    {
        if ($this->status === ConnectionStatus::Disconnected || $this->status === ConnectionStatus::Closed) {
            return;
        }

        $promise = $this->disconnectAsync();

        try {
            await($promise);
        } catch (Throwable $e) {
            $this->logger->error("Error during disconnect for '{$this->getServerName()}': {$e->getMessage()}", ['exception' => $e]);
            if ($this->status !== ConnectionStatus::Closed && $this->status !== ConnectionStatus::Error) {
                $this->handleConnectionFailure($e, false);
            }
        } finally {
            if ($this->status !== ConnectionStatus::Closed && $this->status !== ConnectionStatus::Error) {
                $this->status = ConnectionStatus::Closed;
                $this->cleanupTransport();
            }
        }
    }

    // --- Synchronous Facade Methods ---

    /**
     * Synchronous ping method.
     *
     * @throws ConnectionException|TimeoutException|RequestException|ProtocolException|McpClientException|Throwable
     */
    public function ping(): void
    {
        $this->ensureReady();

        $request = new Request(
            id: $this->idGenerator->generate(),
            method: 'ping',
            params: []
        );

        // Send and wait
        $promise = $this->sendAsyncInternal($request);
        $response = await($promise);

        if ($response->isError()) {
            throw RequestException::fromError('Ping failed: Server Error', $response->error);
        }
        // Success is lack of error
    }

    /**
     * Synchronously lists all tools available on the server.
     *
     * @param  bool  $useCache  Whether to use cached tool definitions.
     * @return array<ToolDefinition>
     *
     * @throws ConnectionException|TimeoutException|RequestException|ProtocolException|McpClientException|Throwable
     */
    public function listTools(bool $useCache = true): array
    {
        $this->ensureReady();

        if ($useCache && $this->definitionCache && ($cached = $this->definitionCache->getTools($this->getServerName())) !== null) {
            return $cached;
        }

        $request = new Request($this->idGenerator->generate(), 'tools/list');
        $promise = $this->sendAsyncInternal($request);
        $response = await($promise);

        if ($response->isError()) {
            throw RequestException::fromError('Failed to list tools', $response->error);
        }

        if (! is_array($response->result)) {
            throw new ProtocolException('Invalid result format for tools/list.');
        }

        $listResult = ListToolsResult::fromArray($response->result);

        if ($this->definitionCache && $listResult->nextCursor === null) {
            $this->definitionCache->setTools($this->getServerName(), $listResult->tools);
        }

        return $listResult->tools;
    }

    /**
     * Synchronously calls a tool on the server.
     *
     * @param  string  $toolName  The name of the tool to call.
     * @param  array  $arguments  The arguments to pass to the tool.
     *
     * @throws ConnectionException|TimeoutException|RequestException|ProtocolException|McpClientException|Throwable
     */
    public function callTool(string $toolName, array $arguments = []): CallToolResult
    {
        $this->ensureReady();

        $request = new Request(
            $this->idGenerator->generate(),
            'tools/call',
            ['name' => $toolName, 'arguments' => empty($arguments) ? new \stdClass : $arguments]
        );

        $promise = $this->sendAsyncInternal($request);
        $response = await($promise);

        if ($response->isError()) {
            throw RequestException::fromError("Failed to call tool '{$toolName}'", $response->error);
        }

        if (! is_array($response->result)) {
            throw new ProtocolException('Invalid result format for tools/call.');
        }

        return CallToolResult::fromArray($response->result);
    }

    /**
     * Synchronously lists all resources available on the server.
     *
     * @param  bool  $useCache  Whether to use cached resource definitions.
     * @return array<ResourceDefinition>
     *
     * @throws ConnectionException|TimeoutException|RequestException|ProtocolException|McpClientException|Throwable
     */
    public function listResources(bool $useCache = true): array
    {
        $this->ensureReady();

        if ($useCache && $this->definitionCache && ($cached = $this->definitionCache->getResources($this->getServerName())) !== null) {
            $this->clientConfig->logger->debug("Cache hit for listResources on '{$this->getServerName()}'.");

            return $cached;
        }

        $this->clientConfig->logger->debug("Cache miss for listResources on '{$this->getServerName()}'.");

        $request = new Request($this->idGenerator->generate(), 'resources/list');
        $promise = $this->sendAsyncInternal($request);
        $response = await($promise);

        if ($response->isError()) {
            throw RequestException::fromError('Failed to list resources', $response->error);
        }

        if (! is_array($response->result)) {
            throw new ProtocolException('Invalid result format for resources/list.');
        }

        $listResult = ListResourcesResult::fromArray($response->result);

        if ($this->definitionCache && $listResult->nextCursor === null) {
            $this->definitionCache->setResources($this->getServerName(), $listResult->resources);
        }

        return $listResult->resources;
    }

    /**
     * Synchronously lists all resource templates available on the server.
     *
     * @param  bool  $useCache  Whether to use cached resource template definitions.
     * @return array<ResourceTemplateDefinition>
     *
     * @throws ConnectionException|TimeoutException|RequestException|ProtocolException|McpClientException|Throwable
     */
    public function listResourceTemplates(bool $useCache = true): array
    {
        $this->ensureReady();

        if ($useCache && $this->definitionCache && ($cached = $this->definitionCache->getResourceTemplates($this->getServerName())) !== null) {
            $this->clientConfig->logger->debug("Cache hit for listResourceTemplates on '{$this->getServerName()}'.");

            return $cached;
        }

        $this->clientConfig->logger->debug("Cache miss for listResourceTemplates on '{$this->getServerName()}'.");

        $request = new Request($this->idGenerator->generate(), 'resources/templates/list');
        $promise = $this->sendAsyncInternal($request);
        $response = await($promise);

        if ($response->isError()) {
            throw RequestException::fromError('Failed to list resource templates', $response->error);
        }

        if (! is_array($response->result)) {
            throw new ProtocolException('Invalid result format for resources/templates/list.');
        }

        $listResult = ListResourceTemplatesResult::fromArray($response->result);

        if ($this->definitionCache && $listResult->nextCursor === null) {
            $this->definitionCache->setResourceTemplates($this->getServerName(), $listResult->resourceTemplates);
        }

        return $listResult->resourceTemplates;
    }

    /**
     * Synchronously lists all prompts available on the server.
     *
     * @param  bool  $useCache  Whether to use cached prompt definitions.
     * @return array<PromptDefinition>
     *
     * @throws ConnectionException|TimeoutException|RequestException|ProtocolException|McpClientException|Throwable
     */
    public function listPrompts(bool $useCache = true): array
    {
        $this->ensureReady();

        if ($useCache && $this->definitionCache && ($cached = $this->definitionCache->getPrompts($this->getServerName())) !== null) {
            $this->clientConfig->logger->debug("Cache hit for listPrompts on '{$this->getServerName()}'.");

            return $cached;
        }

        $this->clientConfig->logger->debug("Cache miss for listPrompts on '{$this->getServerName()}'.");

        $request = new Request($this->idGenerator->generate(), 'prompts/list');
        $promise = $this->sendAsyncInternal($request);
        $response = await($promise);

        if ($response->isError()) {
            throw RequestException::fromError('Failed to list prompts', $response->error);
        }
        if (! is_array($response->result)) {
            throw new ProtocolException('Invalid result format for prompts/list.');
        }

        $listResult = ListPromptsResult::fromArray($response->result);

        if ($this->definitionCache && $listResult->nextCursor === null) {
            $this->definitionCache->setPrompts($this->getServerName(), $listResult->prompts);
        }

        return $listResult->prompts;
    }

    /**
     * Synchronously reads a resource from the server.
     *
     * @param  string  $uri  The URI of the resource to read.
     *
     * @throws ConnectionException|TimeoutException|RequestException|ProtocolException|McpClientException|Throwable
     */
    public function readResource(string $uri): ReadResourceResult
    {
        $this->ensureReady();

        $request = new Request(
            id: $this->idGenerator->generate(),
            method: 'resources/read',
            params: ['uri' => $uri]
        );

        $promise = $this->sendAsyncInternal($request);
        $response = await($promise);

        if ($response->isError()) {
            throw RequestException::fromError("Failed to read resource '{$uri}'", $response->error);
        }

        if (! is_array($response->result)) {
            throw new ProtocolException('Invalid result format for resources/read.');
        }

        return ReadResourceResult::fromArray($response->result);
    }

    /**
     * Synchronously gets a prompt from the server.
     *
     * @param  string  $promptName  The name of the prompt to get.
     * @param  array<string, mixed>  $arguments  The arguments to pass to the prompt.
     *
     * @throws ConnectionException|TimeoutException|RequestException|ProtocolException|McpClientException|Throwable
     */
    public function getPrompt(string $promptName, array $arguments = []): GetPromptResult
    {
        $this->ensureReady();

        $request = new Request(
            id: $this->idGenerator->generate(),
            method: 'prompts/get',
            params: [
                'name' => $promptName,
                'arguments' => empty($arguments) ? new \stdClass : $arguments,
            ]
        );

        $promise = $this->sendAsyncInternal($request);
        $response = await($promise);

        if ($response->isError()) {
            throw RequestException::fromError("Failed to get prompt '{$promptName}'", $response->error);
        }

        if (! is_array($response->result)) {
            throw new ProtocolException('Invalid result format for prompts/get.');
        }

        return GetPromptResult::fromArray($response->result);
    }

    /**
     * Synchronously subscribes to a resource on the server.
     *
     * @param  string  $uri  The URI of the resource to subscribe to.
     *
     * @throws ConnectionException|TimeoutException|RequestException|UnsupportedCapabilityException|McpClientException|Throwable
     */
    public function subscribeResource(string $uri): void
    {
        $this->ensureReady();

        if (! $this->getNegotiatedCapabilities()?->serverSupportsResourceSubscription()) {
            throw new UnsupportedCapabilityException("Server '{$this->getServerName()}' does not support resource subscription.");
        }

        $request = new Request(
            id: $this->idGenerator->generate(),
            method: 'resources/subscribe',
            params: ['uri' => $uri]
        );

        $promise = $this->sendAsyncInternal($request);
        $response = await($promise);

        if ($response->isError()) {
            throw RequestException::fromError("Failed to subscribe to resource '{$uri}'", $response->error);
        }
        // Success is empty result or specific ack? Assume empty for now.
    }

    /**
     * Synchronously unsubscribes from a resource on the server.
     *
     * @param  string  $uri  The URI of the resource to unsubscribe from.
     *
     * @throws ConnectionException|TimeoutException|RequestException|McpClientException|Throwable
     */
    public function unsubscribeResource(string $uri): void
    {
        $this->ensureReady();

        $request = new Request(
            id: $this->idGenerator->generate(),
            method: 'resources/unsubscribe',
            params: ['uri' => $uri]
        );

        $promise = $this->sendAsyncInternal($request);
        $response = await($promise);

        if ($response->isError()) {
            throw RequestException::fromError("Failed to unsubscribe from resource '{$uri}'", $response->error);
        }
    }

    /**
     * Synchronously sets the log level for the server.
     *
     * @param  string  $level  The log level to set.
     *
     * @throws ConnectionException|TimeoutException|RequestException|UnsupportedCapabilityException|McpClientException|Throwable
     */
    public function setLogLevel(string $level): void
    {
        $this->ensureReady();

        if (! $this->getNegotiatedCapabilities()?->serverSupportsLogging()) {
            throw new UnsupportedCapabilityException("Server '{$this->getServerName()}' does not support logging.");
        }

        $request = new Request(
            id: $this->idGenerator->generate(),
            method: 'logging/setLevel',
            params: ['level' => strtolower($level)]
        );

        $promise = $this->sendAsyncInternal($request);
        $response = await($promise);

        if ($response->isError()) {
            throw RequestException::fromError("Failed to set log level to '{$level}'", $response->error);
        }
    }

    // --- Asynchronous API Methods ---

    /**
     * Asynchronously pings the server.
     *
     * @return PromiseInterface<void>
     */
    public function pingAsync(): PromiseInterface
    {
        return $this->sendRequestAndParseResultAsync('ping', [], null);
    }

    /**
     * Asynchronously lists all tools available on the server.
     *
     * @return PromiseInterface<array<ToolDefinition>>
     */
    public function listToolsAsync(): PromiseInterface
    {
        return $this->sendRequestAndParseResultAsync('tools/list', [], ListToolsResult::class)
            ->then(fn (ListToolsResult $result) => $result->tools);
    }

    /**
     * Asynchronously calls a tool on the server.
     *
     * @param  string  $toolName  The name of the tool to call.
     * @param  array<string, mixed>  $arguments  The arguments to pass to the tool.
     * @return PromiseInterface<CallToolResult>
     */
    public function callToolAsync(string $toolName, array $arguments = []): PromiseInterface
    {
        $params = ['name' => $toolName, 'arguments' => empty($arguments) ? new \stdClass : $arguments];

        return $this->sendRequestAndParseResultAsync('tools/call', $params, CallToolResult::class);
    }

    /**
     * Asynchronously lists all resources available on the server.
     *
     * @return PromiseInterface<array<ResourceDefinition>>
     */
    public function listResourcesAsync(): PromiseInterface
    {
        return $this->sendRequestAndParseResultAsync('resources/list', [], ListResourcesResult::class)
            ->then(fn (ListResourcesResult $result) => $result->resources);
    }

    /**
     * Asynchronously lists all resource templates available on the server.
     *
     * @return PromiseInterface<array<ResourceTemplateDefinition>>
     */
    public function listResourceTemplatesAsync(): PromiseInterface
    {
        return $this->sendRequestAndParseResultAsync('resources/templates/list', [], ListResourceTemplatesResult::class)
            ->then(fn (ListResourceTemplatesResult $result) => $result->resourceTemplates);
    }

    /**
     * Asynchronously lists all prompts available on the server.
     *
     * @return PromiseInterface<array<PromptDefinition>>
     */
    public function listPromptsAsync(): PromiseInterface
    {
        return $this->sendRequestAndParseResultAsync('prompts/list', [], ListPromptsResult::class)
            ->then(fn (ListPromptsResult $result) => $result->prompts);
    }

    /**
     * Asynchronously reads a resource from the server.
     *
     * @param  string  $uri  The URI of the resource to read.
     * @return PromiseInterface<ReadResourceResult>
     */
    public function readResourceAsync(string $uri): PromiseInterface
    {
        $params = ['uri' => $uri];

        return $this->sendRequestAndParseResultAsync('resources/read', $params, ReadResourceResult::class);
    }

    /**
     * Asynchronously gets a prompt from the server.
     *
     * @param  string  $promptName  The name of the prompt to get.
     * @param  array<string, mixed>  $arguments  The arguments to pass to the prompt.
     * @return PromiseInterface<GetPromptResult>
     */
    public function getPromptAsync(string $promptName, array $arguments = []): PromiseInterface
    {
        $params = ['name' => $promptName, 'arguments' => empty($arguments) ? new \stdClass : $arguments];

        return $this->sendRequestAndParseResultAsync('prompts/get', $params, GetPromptResult::class);
    }

    /**
     * Asynchronously subscribes to a resource on the server.
     *
     * @param  string  $uri  The URI of the resource to subscribe to.
     * @return PromiseInterface<void>
     */
    public function subscribeResourceAsync(string $uri): PromiseInterface
    {
        if (! $this->getNegotiatedCapabilities()?->serverSupportsResourceSubscription()) {
            return reject(new UnsupportedCapabilityException("Server '{$this->getServerName()}' does not support resource subscription."));
        }

        return $this->sendRequestAndParseResultAsync('resources/subscribe', ['uri' => $uri], null);
    }

    /**
     * Asynchronously unsubscribes from a resource on the server.
     *
     * @param  string  $uri  The URI of the resource to unsubscribe from.
     * @return PromiseInterface<void>
     */
    public function unsubscribeResourceAsync(string $uri): PromiseInterface
    {
        return $this->sendRequestAndParseResultAsync('resources/unsubscribe', ['uri' => $uri], null);
    }

    /**
     * Asynchronously sets the log level for the server.
     *
     * @param  string  $level  The log level to set.
     * @return PromiseInterface<void>
     */
    public function setLogLevelAsync(string $level): PromiseInterface
    {
        if (! $this->getNegotiatedCapabilities()?->serverSupportsLogging()) {
            return reject(new UnsupportedCapabilityException("Server '{$this->getServerName()}' does not support logging."));
        }

        return $this->sendRequestAndParseResultAsync('logging/setLevel', ['level' => strtolower($level)], null);
    }

    // --- Internal Helper Methods ---

    /** Checks if client is ready, throws if not */
    protected function ensureReady(): void
    {
        if ($this->status !== ConnectionStatus::Ready) {
            // Attempt to initialize implicitly? Or just throw? Let's throw for clarity.
            // Call $this->initialize() here if implicit connection is desired.
            throw new ConnectionException('Client not initialized. Call initialize() first.');
        }
    }

    /**
     * Performs the MCP initialize handshake. Returns a promise.
     *
     * @return PromiseInterface<void>
     */
    private function performHandshake(): PromiseInterface
    {
        $initParams = new InitializeParams(
            clientName: $this->clientConfig->name,
            clientVersion: $this->clientConfig->version,
            protocolVersion: $this->preferredProtocolVersion,
            capabilities: $this->clientConfig->capabilities,
        );

        $request = new Request(
            id: $this->clientConfig->idGenerator->generate(),
            method: 'initialize',
            params: $initParams->toArray()
        );

        return $this->sendAsyncInternal($request)->then(
            function (Response $response) {
                if ($response->isError()) {
                    throw RequestException::fromError('Initialize failed', $response->error);
                }

                if (! is_array($response->result)) {
                    throw new ConnectionException('Invalid initialize result format.');
                }

                $initResult = InitializeResult::fromArray($response->result);

                // Version Negotiation
                $serverVersion = $initResult->protocolVersion;
                if ($serverVersion !== $this->preferredProtocolVersion) {
                    $this->logger->warning("Version mismatch: Server returned {$serverVersion}, expected {$this->preferredProtocolVersion}.");

                    if (! is_string($serverVersion) || empty($serverVersion)) {
                        throw new ConnectionException('Server returned invalid protocol version.');
                    }
                }
                $this->negotiatedProtocolVersion = $serverVersion;
                $this->serverName = $initResult->serverName;
                $this->serverVersion = $initResult->serverVersion;
                $this->serverCapabilities = $initResult->capabilities;

                $this->logger->debug("Sending 'initialized' notification to '{$this->getServerName()}'.");

                return $this->transport->send(new Notification('notifications/initialized'));
            }
        );
    }

    /**
     * Internal async request sender
     *
     * Allows sending requests even if the client is not ready. Callers must check the status
     * and ensure the client is ready before calling this method.
     *
     * @return PromiseInterface<Response>
     */
    protected function sendAsyncInternal(Request $request): PromiseInterface
    {
        if ($this->status === ConnectionStatus::Closed || $this->status === ConnectionStatus::Closing || $this->status === ConnectionStatus::Error) {
            return reject(new ConnectionException("Cannot send request, connection is closed or in error state ({$this->status->value})."));
        }

        if (! isset($this->transport)) {
            return reject(new ConnectionException('Cannot send request, transport not available.'));
        }

        if (! isset($request->id)) {
            return reject(new McpClientException('Cannot use sendAsyncInternal for notifications.'));
        }

        $deferred = new Deferred(function ($_, $reject) use ($request) {
            if (isset($this->pendingRequests[$request->id])) {
                unset($this->pendingRequests[$request->id]);
                $reject(new McpClientException("Request '{$request->method}' (ID: {$request->id}) cancelled by user."));
            }
        });

        $this->pendingRequests[$request->id] = $deferred;

        $this->transport->send($request)
            ->catch(
                function (Throwable $e) use ($deferred, $request) {
                    if (isset($this->pendingRequests[$request->id])) {
                        $sendFailMsg = "Failed to send request '{$request->method}' (ID: {$request->id}): {$e->getMessage()}";
                        $this->logger->error($sendFailMsg, ['exception' => $e]);
                        unset($this->pendingRequests[$request->id]);
                        $deferred->reject(new TransportException($sendFailMsg, 0, $e));
                    }
                }
            );

        $timeout = $this->serverConfig->timeout;
        $operationName = "Request '{$request->method}' (ID: {$request->id})";

        return Utils::timeout($deferred->promise(), $timeout, $this->loop, $operationName)
            ->catch(function (Throwable $e) use ($request) {
                if (isset($this->pendingRequests[$request->id])) {
                    unset($this->pendingRequests[$request->id]);
                }
                throw $e;
            });
    }

    /**
     * Helper for async methods that parses the result
     *
     * @param  string  $method  The method to call.
     * @param  array<string, mixed>  $params  The parameters to pass to the method.
     * @param  string|null  $resultClass  The class of the result to parse.
     * @return PromiseInterface<mixed>
     */
    private function sendRequestAndParseResultAsync(string $method, array $params, ?string $resultClass): PromiseInterface
    {
        if ($this->status !== ConnectionStatus::Ready) {
            return reject(new ConnectionException("Client not ready (Status: {$this->status->value})."));
        }

        $request = new Request($this->idGenerator->generate(), $method, $params);

        return $this->sendAsyncInternal($request)
            ->then(function (Response $response) use ($resultClass, $method) {
                if ($response->isError()) {
                    throw RequestException::fromError("Request '{$method}' failed", $response->error);
                }

                if ($resultClass === null) {
                    return null;
                }

                if (! is_array($response->result)) {
                    throw new ProtocolException("Invalid result format for {$method}. Expected array.");
                }

                if (! method_exists($resultClass, 'fromArray')) {
                    throw new \LogicException("Result class {$resultClass} missing fromArray method.");
                }

                return $resultClass::fromArray($response->result);
            });
    }

    /**
     * Rejects all currently pending requests
     *
     * @param  Throwable  $reason  The reason to reject the requests.
     */
    private function rejectPendingRequests(Throwable $reason): void
    {
        if (empty($this->pendingRequests)) {
            return;
        }

        $count = count($this->pendingRequests);
        $this->logger->warning("Rejecting {$count} pending requests.", ['reason' => $reason->getMessage()]);

        $pendingToReject = $this->pendingRequests;
        $this->pendingRequests = [];

        $this->loop->futureTick(function () use ($pendingToReject, $reason) {
            $this->logger->debug('Executing scheduled promise rejections for connection failure/close.');
            foreach ($pendingToReject as $deferred) {
                $deferred->reject($reason);
            }
        });
    }

    /**
     * Handler for incoming messages from transport
     *
     * @param  Message  $message  The message to handle.
     */
    private function handleTransportMessage(Message $message): void
    {
        if ($message instanceof Response) {
            $this->handleResponseMessage($message);
        } elseif ($message instanceof Notification) {
            $this->handleNotificationMessage($message);
        } else {
            $this->logger->warning('Received unknown message type', ['type' => get_class($message)]);
        }
    }

    /**
     * Routes response to the correct pending promise
     *
     * @param  Response  $response  The response to handle.
     */
    private function handleResponseMessage(Response $response): void
    {
        $id = $response->id;
        if ($id === null) {
            $this->logger->warning('Received Response message with null ID, ignoring.', ['response' => $response->toArray()]);

            return;
        }

        if (! isset($this->pendingRequests[$id])) {
            // This is common if a request timed out before the response arrived
            $this->logger->debug('Received response for unknown or timed out request ID', ['id' => $id]);

            return;
        }

        $deferred = $this->pendingRequests[$id];
        unset($this->pendingRequests[$id]);

        // Resolve/reject the deferred directly
        if ($response->isError()) {
            $this->logger->warning("Received error response for request ID {$id}", ['error' => $response->error->toArray()]);
            $exception = new RequestException(
                $response->error->message,
                $response->error,
                $response->error->code
            );
            $deferred->reject($exception);
        } else {
            $this->logger->debug("Received successful response for request ID {$id}");
            $deferred->resolve($response);
        }
    }

    /**
     * Dispatches notification events
     *
     * @param  Notification  $notification  The notification to handle.
     */
    private function handleNotificationMessage(Notification $notification): void
    {
        if (! $this->clientConfig->eventDispatcher) {
            $this->logger->debug('Received notification but no event dispatcher configured.', ['method' => $notification->method]);

            return;
        }

        $event = match ($notification->method) {
            'notifications/tools/listChanged' => new Event\ToolsListChanged($this->getServerName()),
            'notifications/resources/listChanged' => new Event\ResourcesListChanged($this->getServerName()),
            'notifications/prompts/listChanged' => new Event\PromptsListChanged($this->getServerName()),
            'notifications/resources/didChange' => isset($notification->params['uri']) && is_string($notification->params['uri'])
                ? new Event\ResourceChanged($this->getServerName(), $notification->params['uri'])
                : null,
            'notifications/logging/log' => is_array($notification->params)
                ? new Event\LogReceived($this->getServerName(), $notification->params)
                : null,
            'sampling/createMessage' => is_array($notification->params)
                ? new Event\SamplingRequestReceived($this->getServerName(), $notification->params)
                : null,
            default => null
        };

        if ($event) {
            $this->logger->debug('Dispatching event', ['event' => get_class($event), 'server' => $this->getServerName()]);

            try {
                $this->clientConfig->eventDispatcher->dispatch($event);
            } catch (Throwable $e) {
                $this->logger->error('Error during application event dispatch', ['exception' => $e, 'event' => get_class($event)]);
            }
        } else {
            // Log only if the method wasn't matched AND params were invalid for matched cases
            if ($notification->method === 'notifications/resources/didChange' && ! isset($notification->params['uri'])) {
                $this->logger->warning("Received 'resource/didChange' notification with missing/invalid 'uri' param.", ['params' => $notification->params]);
            } elseif ($notification->method === 'notifications/logging/log' && ! is_array($notification->params)) {
                $this->logger->warning("Received 'logging/log' notification with invalid 'params'.", ['params' => $notification->params]);
            } elseif ($notification->method === 'sampling/createMessage' && ! is_array($notification->params)) {
                $this->logger->warning("Received 'sampling/createMessage' notification with invalid 'params'.", ['params' => $notification->params]);
            } elseif (! in_array($notification->method, ['notifications/tools/listChanged', 'notifications/resources/listChanged', 'notifications/prompts/listChanged'])) {
                // Avoid logging warnings for valid but unmapped notifications if no dispatcher exists
                $this->logger->warning('Received unhandled MCP notification method', ['method' => $notification->method, 'server' => $this->getServerName()]);
            }
        }
    }

    /**
     * Handles transport errors
     *
     * @param  Throwable  $error  The error to handle.
     */
    private function handleTransportError(Throwable $error): void
    {
        if ($this->status === ConnectionStatus::Closing || $this->status === ConnectionStatus::Closed || $this->status === ConnectionStatus::Error) {
            $this->logger->debug('Ignoring transport error in terminal state.', ['status' => $this->status->value, 'error' => $error->getMessage()]);

            return;
        }

        $this->logger->error("Transport error for '{$this->getServerName()}': {$error->getMessage()}", ['exception' => $error]);

        $exceptionToPropagate = $error instanceof McpClientException ? $error : new ConnectionException("Transport layer error: {$error->getMessage()}", 0, $error);

        $this->handleConnectionFailure($exceptionToPropagate);
    }

    /**
     * Handles unexpected transport close
     *
     * @param  mixed  $reason  The reason for the close.
     */
    private function handleTransportClose(mixed $reason = null): void
    {
        if ($this->status === ConnectionStatus::Closing || $this->status === ConnectionStatus::Closed) {
            $this->logger->debug('Ignoring transport close in terminal state.', ['status' => $this->status->value]);

            return;
        }

        $message = "Transport closed unexpectedly for '{$this->getServerName()}'.".(is_string($reason) && $reason !== '' ? ' Reason: '.$reason : '');

        $this->logger->warning($message);

        $this->handleConnectionFailure(new ConnectionException($message));
    }

    /**
     * Central failure handler
     *
     * @param  Throwable  $error  The error to handle.
     * @param  bool  $rejectConnectDeferred  Whether to reject the connect deferred.
     */
    public function handleConnectionFailure(Throwable $error, bool $rejectConnectDeferred = true): void
    {
        if ($this->status === ConnectionStatus::Closed || $this->status === ConnectionStatus::Error) {
            return;
        }

        $this->status = ConnectionStatus::Error;

        $exception = match (true) {
            $error instanceof ConnectionException,
            $error instanceof TimeoutException,
            $error instanceof ProtocolException => $error,
            default => new ConnectionException("Connection failed: {$error->getMessage()}", 0, $error),
        };

        if ($rejectConnectDeferred) {
            $this->connectRequestDeferred?->reject($exception);
        }

        $this->rejectPendingRequests($exception);

        $this->transport?->close();
        $this->cleanupTransport();

        $this->logger->info("Connection failure handled for '{$this->getServerName()}'. Status set to {$this->status->value}.");
    }

    /** Cleans up transport resources */
    private function cleanupTransport(): void
    {
        if (isset($this->transport)) {
            $this->transport->removeAllListeners();
            $this->transport = null;
        }
        $this->pendingRequests = [];
        $this->serverName = null;
        $this->serverVersion = null;
        $this->serverCapabilities = null;
        $this->negotiatedProtocolVersion = null;
        $this->connectPromise = null;
        $this->connectRequestDeferred = null;
    }
}
