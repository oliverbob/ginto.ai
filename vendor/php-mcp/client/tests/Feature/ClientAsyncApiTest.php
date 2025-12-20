<?php

namespace PhpMcp\Client\Tests\Feature;

use Mockery;
use PhpMcp\Client\Client;
use PhpMcp\Client\ClientConfig;
use PhpMcp\Client\Contracts\TransportInterface;
use PhpMcp\Client\Enum\ConnectionStatus;
use PhpMcp\Client\Enum\TransportType;
use PhpMcp\Client\Event\ResourceChanged;
use PhpMcp\Client\Event\ToolsListChanged;
use PhpMcp\Client\Exception\ConnectionException;
use PhpMcp\Client\Exception\RequestException;
use PhpMcp\Client\Exception\TimeoutException;
use PhpMcp\Client\Exception\TransportException;
use PhpMcp\Client\Exception\UnsupportedCapabilityException;
use PhpMcp\Client\Factory\MessageIdGenerator;
use PhpMcp\Client\Factory\TransportFactory;
use PhpMcp\Client\JsonRpc\Error as JsonRpcError;
use PhpMcp\Client\JsonRpc\Notification;
use PhpMcp\Client\JsonRpc\Request;
use PhpMcp\Client\JsonRpc\Response;
use PhpMcp\Client\JsonRpc\Results\CallToolResult as ResultsCallToolResult;
use PhpMcp\Client\JsonRpc\Results\GetPromptResult;
use PhpMcp\Client\JsonRpc\Results\ReadResourceResult;
use PhpMcp\Client\Model\Capabilities;
use PhpMcp\Client\Model\Content\EmbeddedResource;
use PhpMcp\Client\Model\Content\PromptMessage;
use PhpMcp\Client\Model\Content\TextContent;
use PhpMcp\Client\Model\Definitions\ResourceDefinition;
use PhpMcp\Client\Model\Definitions\ToolDefinition;
use PhpMcp\Client\ServerConfig;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\Loop;
use React\Promise\Deferred;

use function React\Async\await;
use function React\Promise\resolve;

const TEST_SERVER_NAME_STDIO_ASYNC = 'stdio_async_client';

beforeEach(function () {
    $this->loop = Loop::get();

    // --- Mocks ---
    $this->logger = Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing();
    $this->dispatcher = Mockery::mock(EventDispatcherInterface::class)->shouldIgnoreMissing();
    $this->idGenerator = Mockery::mock(MessageIdGenerator::class);
    $this->mockTransport = Mockery::mock(TransportInterface::class);
    $this->mockTransportFactory = Mockery::mock(TransportFactory::class);

    // --- Config ---
    $this->serverConfig = new ServerConfig(
        name: TEST_SERVER_NAME_STDIO_ASYNC,
        transport: TransportType::Stdio,
        command: 'dummy_async_cmd',
        timeout: 0.5
    );

    $this->clientConfig = new ClientConfig(
        name: 'AsyncTestClient',
        version: '1.0',
        capabilities: Capabilities::forClient(),
        logger: $this->logger,
        eventDispatcher: $this->dispatcher,
        loop: $this->loop,
        idGenerator: $this->idGenerator,
    );

    $this->mockTransportFactory->shouldReceive('create')
        ->with($this->serverConfig)
        ->andReturn($this->mockTransport);

    $this->client = new Client(
        $this->serverConfig,
        $this->clientConfig,
        $this->mockTransportFactory
    );

    $this->messageListenerCallback = null;
    $this->mockTransport->shouldReceive('on')
        ->with('message', Mockery::capture($this->messageListenerCallback))
        ->atMost(1)
        ->andReturnUsing(function () {})
        ->byDefault();

    // Allow other common listeners by default
    $this->mockTransport->shouldReceive('on')->with('error', Mockery::any())->byDefault()->andReturnUsing(function () {});
    $this->mockTransport->shouldReceive('on')->with('close', Mockery::any())->byDefault()->andReturnUsing(function () {});
    $this->mockTransport->shouldReceive('on')->with('stderr', Mockery::any())->byDefault()->andReturnUsing(function () {});
    $this->mockTransport->shouldReceive('once')->with('close', Mockery::any())->byDefault()->andReturnUsing(function () {});
    $this->mockTransport->shouldReceive('removeListener')->withAnyArgs()->byDefault();
    $this->mockTransport->shouldReceive('removeAllListeners')->withAnyArgs()->byDefault();
    $this->mockTransport->shouldReceive('close')->byDefault();

    $this->mockTransport->shouldReceive('connect')->withNoArgs()->andReturn(resolve(null))->byDefault();
    $this->mockTransport->shouldReceive('send')->with(Mockery::any())->andReturn(resolve(null))->byDefault();
});

// --- Async Initialization & Handshake Tests ---

it('can initialize connection asynchronously', function () {
    // Arrange
    $initRequestId = 'init-async-1';

    $connectDeferred = new Deferred;
    $initSentDeferred = new Deferred;
    $initializedSentDeferred = new Deferred;

    $this->mockTransport->shouldReceive('connect')->once()->ordered()->andReturn($connectDeferred->promise());

    $this->idGenerator->shouldReceive('generate')->once()->ordered()->andReturn($initRequestId);

    $this->mockTransport->shouldReceive('send')
        ->with(Mockery::on(fn (Request $req) => $req->method === 'initialize' && $req->id === $initRequestId))
        ->once()->ordered()
        ->andReturn($initSentDeferred->promise());

    $this->mockTransport->shouldReceive('send')
        ->with(Mockery::on(fn ($msg) => $msg instanceof Notification && $msg->method === 'notifications/initialized'))
        ->once()->ordered()
        ->andReturn($initializedSentDeferred->promise());

    // Act
    $initPromise = $this->client->initializeAsync();
    $resolvedClient = null;
    $rejectedReason = null;

    $initPromise->then(
        function ($client) use (&$resolvedClient) {
            $resolvedClient = $client;
        },
        function ($reason) use (&$rejectedReason) {
            $rejectedReason = $reason;
        }
    );

    expect($this->client->getStatus())->toBe(ConnectionStatus::Connecting);

    $this->loop->addTimer(0.01, fn () => $connectDeferred->resolve(null));
    $this->loop->addTimer(0.02, fn () => $initSentDeferred->resolve(null));
    $initResultData = [
        'protocolVersion' => '2024-11-05',
        'serverInfo' => ['name' => 'AsyncMockServer', 'version' => '2.0'],
        'capabilities' => ['tools' => new \stdClass],
    ];
    $initResponse = new Response(id: $initRequestId, result: $initResultData);

    $this->loop->addTimer(0.03, fn () => $this->messageListenerCallback ? ($this->messageListenerCallback)($initResponse) : null);
    $this->loop->addTimer(0.04, fn () => $initializedSentDeferred->resolve(null));

    $result = await($initPromise);

    // Assert
    expect($rejectedReason)->toBeNull();
    expect($resolvedClient)->toBe($this->client);
    expect($result)->toBe($this->client);
    expect($this->client->getStatus())->toBe(ConnectionStatus::Ready);
    expect($this->client->getServerName())->toBe('AsyncMockServer');
    expect($this->client->getServerVersion())->toBe('2.0');

})->group('usesLoop');

it('fails asynchronous initialization if transport connect fails', function () {
    // Arrange
    $connectDeferred = new Deferred;
    $exception = new TransportException('TCP Async Connection refused');
    $this->mockTransport->shouldReceive('connect')->once()->andReturn($connectDeferred->promise());

    // Act
    $initPromise = $this->client->initializeAsync();

    // Simulate failure
    $this->loop->addTimer(0.01, fn () => $connectDeferred->reject($exception));

    // Assert
    await($initPromise);

})->group('usesLoop')->throws(ConnectionException::class, 'Connection failed: TCP Async Connection refused');

it('fails asynchronous initialization if server sends error response', function () {
    // Arrange
    $initRequestId = 'init-async-fail-resp';

    $connectDeferred = new Deferred;
    $initSentDeferred = new Deferred;
    $this->mockTransport->shouldReceive('connect')->once()->andReturn($connectDeferred->promise());
    $this->idGenerator->shouldReceive('generate')->once()->andReturn($initRequestId);
    $this->mockTransport->shouldReceive('send')->with(Mockery::on(fn ($req) => $req->method === 'initialize'))->once()->andReturn($initSentDeferred->promise());

    // Act
    $initPromise = $this->client->initializeAsync();

    // Simulate connect & send success
    $this->loop->addTimer(0.01, fn () => $connectDeferred->resolve(null));
    $this->loop->addTimer(0.02, fn () => $initSentDeferred->resolve(null));

    // Simulate receiving error response
    $error = new JsonRpcError(-32000, 'Unsupported Version');
    $initErrorResponse = new Response(id: $initRequestId, error: $error);
    $this->loop->addTimer(0.03, fn () => $this->messageListenerCallback ? ($this->messageListenerCallback)($initErrorResponse) : null);

    // Assert
    await($initPromise);

})
    ->group('usesLoop')
    ->throws(ConnectionException::class, 'Connection failed: Unsupported Version');

it('can ping server asynchronously', function () {
    // Arrange
    $initRequestId = 'init-ping-async';
    $pingRequestId = 'ping-async-1';
    $this->idGenerator->shouldReceive('generate')->twice()->ordered()->andReturn($initRequestId, $pingRequestId);

    simulateSuccessfulHandshake($initRequestId, $this->messageListenerCallback, $this->mockTransport, $this->loop);

    await($this->client->initializeAsync());

    $response = new Response($pingRequestId, new \stdClass); // Ping has empty object result
    $sendDeferred = new Deferred;
    $this->mockTransport->shouldReceive('send')->with(Mockery::on(fn (Request $r) => $r->id === $pingRequestId && $r->method === 'ping'))->once()->andReturn($sendDeferred->promise());

    // Act
    $pingPromise = $this->client->pingAsync();

    // Simulate
    $this->loop->addTimer(0.01, fn () => $sendDeferred->resolve(null));
    $this->loop->addTimer(0.02, fn () => $this->messageListenerCallback ? ($this->messageListenerCallback)($response) : null);

    // Assert
    $resolvedResult = await($pingPromise);
    expect($resolvedResult)->toBeNull(); // Resolves void/null

})->group('usesLoop');

it('can list tools asynchronously', function () {
    // Arrange
    $initRequestId = 'init-listTools-async';
    $listToolsRequestId = 'listTools-async-1';

    $this->idGenerator->shouldReceive('generate')->twice()->ordered()->andReturn($initRequestId, $listToolsRequestId);

    simulateSuccessfulHandshake($initRequestId, $this->messageListenerCallback, $this->mockTransport, $this->loop);

    await($this->client->initializeAsync());

    $toolsData = [['name' => 'tool1', 'description' => null, 'inputSchema' => []]];
    $response = new Response($listToolsRequestId, ['tools' => $toolsData]);
    $sendDeferred = new Deferred;
    $this->mockTransport->shouldReceive('send')->with(Mockery::on(fn (Request $r) => $r->id === $listToolsRequestId))->once()->andReturn($sendDeferred->promise());

    // Act
    $listPromise = $this->client->listToolsAsync();

    // Simulate
    $this->loop->addTimer(0.01, fn () => $sendDeferred->resolve(null));
    $this->loop->addTimer(0.02, fn () => $this->messageListenerCallback ? ($this->messageListenerCallback)($response) : null);

    // Assert
    $resolvedResult = await($listPromise);
    expect($resolvedResult)->toBeArray()->toHaveCount(1);
    expect($resolvedResult[0])->toBeInstanceOf(ToolDefinition::class);
    expect($resolvedResult[0]->name)->toBe('tool1');

})->group('usesLoop');

it('can call tool asynchronously and receive result promise', function () {
    // Arrange
    $initRequestId = 'init-call-async';
    $callToolRequestId = 'call-async-1';

    $this->idGenerator->shouldReceive('generate')->twice()->ordered()->andReturn($initRequestId, $callToolRequestId);

    simulateSuccessfulHandshake($initRequestId, $this->messageListenerCallback, $this->mockTransport, $this->loop);

    await($this->client->initializeAsync());

    expect($this->client->getStatus())->toBe(ConnectionStatus::Ready);

    $toolName = 'testTool';
    $args = ['a' => 1];
    $expectedResultData = ['content' => [['type' => 'text', 'text' => 'OK']], 'isError' => false];
    $response = new Response($callToolRequestId, $expectedResultData);
    $sendDeferred = new Deferred;

    $this->mockTransport->shouldReceive('send')
        ->with(Mockery::on(fn (Request $req) => $req->id === $callToolRequestId && $req->method === 'tools/call'))
        ->once()->ordered()->andReturn($sendDeferred->promise());

    // Act
    $callPromise = $this->client->callToolAsync($toolName, $args);

    $this->loop->addTimer(0.01, fn () => $sendDeferred->resolve(null));
    $this->loop->addTimer(0.02, fn () => $this->messageListenerCallback ? ($this->messageListenerCallback)($response) : null);

    $resolvedResult = await($callPromise);

    // Assert
    expect($resolvedResult)->toBeInstanceOf(ResultsCallToolResult::class);
    expect($resolvedResult->isSuccess())->toBeTrue();
    expect($resolvedResult->content[0])->toBeInstanceOf(\PhpMcp\Client\Model\Content\TextContent::class);
    expect($resolvedResult->content[0]->text)->toBe('OK');
    expect(getPendingRequestCount($this->client))->toBe(0);

})->group('usesLoop');

it('handles server error asynchronously when calling tool', function () {
    // Arrange
    $initRequestId = 'init-call-async-err';
    $callToolRequestId = 'call-async-err-1';

    $this->idGenerator->shouldReceive('generate')->twice()->ordered()->andReturn($initRequestId, $callToolRequestId);

    simulateSuccessfulHandshake($initRequestId, $this->messageListenerCallback, $this->mockTransport, $this->loop);

    await($this->client->initializeAsync());

    expect($this->client->getStatus())->toBe(ConnectionStatus::Ready);

    $toolName = 'badTool';
    $sendDeferred = new Deferred;
    $this->mockTransport->shouldReceive('send')
        ->with(Mockery::on(fn (Request $req) => $req->id === $callToolRequestId))
        ->once()->ordered()->andReturn($sendDeferred->promise());

    $error = new JsonRpcError(-32001, 'Tool Failed');
    $response = new Response($callToolRequestId, null, $error);

    // Act
    $callPromise = $this->client->callToolAsync($toolName);

    $this->loop->addTimer(0.01, fn () => $sendDeferred->resolve(null));
    $this->loop->addTimer(0.02, fn () => $this->messageListenerCallback ? ($this->messageListenerCallback)($response) : null);

    // Assert by awaiting (it should throw)
    await($callPromise);

})->group('usesLoop')->throws(RequestException::class, 'Tool Failed');

it('handles timeout asynchronously when calling tool', function () {
    // Arrange
    $initRequestId = 'init-call-async-timeout';
    $callToolRequestId = 'call-async-timeout-1';

    $shortTimeoutConfig = new ServerConfig(
        name: TEST_SERVER_NAME_STDIO_ASYNC,
        transport: TransportType::Stdio,
        command: 'cmd',
        timeout: 0.05
    );
    $clientWithShortTimeout = createClientForTesting(
        $shortTimeoutConfig,
        $this->mockTransport,
        $this->loop,
        $this->idGenerator
    );
    $localMessageListenerCallback = null;
    $this->mockTransport->shouldReceive('on')
        ->with('message', Mockery::capture($localMessageListenerCallback))
        ->once()
        ->andReturnUsing(function () {});

    $this->idGenerator->shouldReceive('generate')->once()->andReturn($initRequestId);

    simulateSuccessfulHandshake($initRequestId, $localMessageListenerCallback, $this->mockTransport, $this->loop);

    await($clientWithShortTimeout->initializeAsync());

    expect($clientWithShortTimeout->getStatus())->toBe(ConnectionStatus::Ready);

    $this->idGenerator->shouldReceive('generate')->once()->andReturn($callToolRequestId);

    $toolName = 'timeoutTool';
    $sendDeferred = new Deferred;
    $this->mockTransport->shouldReceive('send')
        ->with(Mockery::on(fn (Request $req) => $req->id === $callToolRequestId))
        ->once()->ordered()->andReturn($sendDeferred->promise());

    // Act
    $callPromise = $clientWithShortTimeout->callToolAsync($toolName);

    $this->loop->addTimer(0.01, fn () => $sendDeferred->resolve(null));

    // DO NOT simulate response arrival

    // Assert
    await($callPromise);

})->group('usesLoop')->throws(TimeoutException::class, "Request 'tools/call' (ID: call-async-timeout-1) timed out after 0.05 seconds");

it('can list resources asynchronously', function () {
    // Arrange
    $initRequestId = 'init-listRes-async';
    $listResRequestId = 'listRes-async-1';

    $this->idGenerator->shouldReceive('generate')->twice()->ordered()->andReturn($initRequestId, $listResRequestId);

    simulateSuccessfulHandshake($initRequestId, $this->messageListenerCallback, $this->mockTransport, $this->loop);

    await($this->client->initializeAsync());

    $resData = [['uri' => 'res://1', 'name' => 'R1', 'mimeType' => null, 'size' => null, 'annotations' => []]];
    $response = new Response($listResRequestId, ['resources' => $resData]);
    $sendDeferred = new Deferred;
    $this->mockTransport->shouldReceive('send')->with(Mockery::on(fn (Request $r) => $r->id === $listResRequestId && $r->method === 'resources/list'))->once()->andReturn($sendDeferred->promise());

    // Act
    $listPromise = $this->client->listResourcesAsync();

    // Simulate
    $this->loop->addTimer(0.01, fn () => $sendDeferred->resolve(null));
    $this->loop->addTimer(0.02, fn () => $this->messageListenerCallback ? ($this->messageListenerCallback)($response) : null);

    // Assert
    $resolvedResult = await($listPromise);
    expect($resolvedResult)->toBeArray()->toHaveCount(1);
    expect($resolvedResult[0])->toBeInstanceOf(ResourceDefinition::class);
    expect($resolvedResult[0]->uri)->toBe('res://1');

})->group('usesLoop');

it('can list resource templates asynchronously', function () {
    // Arrange
    $initRequestId = 'init-listTmpl-async';
    $listTmplRequestId = 'listTmpl-async-1';

    $this->idGenerator->shouldReceive('generate')->twice()->ordered()->andReturn($initRequestId, $listTmplRequestId);

    simulateSuccessfulHandshake($initRequestId, $this->messageListenerCallback, $this->mockTransport, $this->loop);

    await($this->client->initializeAsync());

    $tmplData = [['uriTemplate' => 'tmpl://{id}', 'name' => 'T1', 'mimeType' => null, 'description' => null, 'annotations' => []]];
    $response = new Response($listTmplRequestId, ['resourceTemplates' => $tmplData]);
    $sendDeferred = new Deferred;
    $this->mockTransport->shouldReceive('send')->with(Mockery::on(fn (Request $r) => $r->id === $listTmplRequestId && $r->method === 'resources/templates/list'))->once()->andReturn($sendDeferred->promise());

    // Act
    $listPromise = $this->client->listResourceTemplatesAsync();

    // Simulate
    $this->loop->addTimer(0.01, fn () => $sendDeferred->resolve(null));
    $this->loop->addTimer(0.02, fn () => $this->messageListenerCallback ? ($this->messageListenerCallback)($response) : null);

    // Assert
    $resolvedResult = await($listPromise);
    expect($resolvedResult)->toBeArray()->toHaveCount(1);
    expect($resolvedResult[0])->toBeInstanceOf(\PhpMcp\Client\Model\Definitions\ResourceTemplateDefinition::class); // Use FQCN if needed
    expect($resolvedResult[0]->uriTemplate)->toBe('tmpl://{id}');

})->group('usesLoop');

it('can list prompts asynchronously', function () {
    // Arrange
    $initRequestId = 'init-listPrmpt-async';
    $listPrmptRequestId = 'listPrmpt-async-1';

    $this->idGenerator->shouldReceive('generate')->twice()->ordered()->andReturn($initRequestId, $listPrmptRequestId);

    simulateSuccessfulHandshake($initRequestId, $this->messageListenerCallback, $this->mockTransport, $this->loop);

    await($this->client->initializeAsync());

    $prmptData = [['name' => 'P1', 'description' => null, 'arguments' => []]];
    $response = new Response($listPrmptRequestId, ['prompts' => $prmptData]);
    $sendDeferred = new Deferred;
    $this->mockTransport->shouldReceive('send')->with(Mockery::on(fn (Request $r) => $r->id === $listPrmptRequestId && $r->method === 'prompts/list'))->once()->andReturn($sendDeferred->promise());

    // Act
    $listPromise = $this->client->listPromptsAsync();

    // Simulate
    $this->loop->addTimer(0.01, fn () => $sendDeferred->resolve(null));
    $this->loop->addTimer(0.02, fn () => $this->messageListenerCallback ? ($this->messageListenerCallback)($response) : null);

    // Assert
    $resolvedResult = await($listPromise);
    expect($resolvedResult)->toBeArray()->toHaveCount(1);
    expect($resolvedResult[0])->toBeInstanceOf(\PhpMcp\Client\Model\Definitions\PromptDefinition::class); // Use FQCN if needed
    expect($resolvedResult[0]->name)->toBe('P1');

})->group('usesLoop');

it('can read resource asynchronously', function () {
    // Arrange
    $initRequestId = 'init-readRes-async';
    $readResRequestId = 'readRes-async-1';
    $resourceUri = 'test://resource';

    $this->idGenerator->shouldReceive('generate')->twice()->ordered()->andReturn($initRequestId, $readResRequestId);

    simulateSuccessfulHandshake($initRequestId, $this->messageListenerCallback, $this->mockTransport, $this->loop);

    await($this->client->initializeAsync());

    $contentData = ['uri' => $resourceUri, 'mimeType' => 'text/plain', 'text' => 'content ok'];
    $response = new Response($readResRequestId, ['contents' => [$contentData]]);
    $sendDeferred = new Deferred;
    $this->mockTransport->shouldReceive('send')
        ->with(Mockery::on(fn (Request $r) => $r->id === $readResRequestId && $r->method === 'resources/read' && $r->params['uri'] === $resourceUri))
        ->once()->andReturn($sendDeferred->promise());

    // Act
    $readPromise = $this->client->readResourceAsync($resourceUri);

    // Simulate
    $this->loop->addTimer(0.01, fn () => $sendDeferred->resolve(null));
    $this->loop->addTimer(0.02, fn () => $this->messageListenerCallback ? ($this->messageListenerCallback)($response) : null);

    // Assert
    $resolvedResult = await($readPromise);
    expect($resolvedResult)->toBeInstanceOf(ReadResourceResult::class);
    expect($resolvedResult->contents[0])->toBeInstanceOf(EmbeddedResource::class);
    expect($resolvedResult->contents[0]->text)->toBe('content ok');

})->group('usesLoop');

it('can get prompt asynchronously', function () {
    // Arrange
    $initRequestId = 'init-getPrompt-async';
    $getPromptRequestId = 'getPrompt-async-1';
    $promptName = 'myPrompt';
    $args = ['topic' => 'test'];
    $this->idGenerator->shouldReceive('generate')->twice()->ordered()->andReturn($initRequestId, $getPromptRequestId);

    simulateSuccessfulHandshake($initRequestId, $this->messageListenerCallback, $this->mockTransport, $this->loop);

    await($this->client->initializeAsync());

    $messagesData = [['role' => 'user', 'content' => ['type' => 'text', 'text' => 'Prompt for test']]];
    $response = new Response($getPromptRequestId, ['messages' => $messagesData, 'description' => 'Test Desc']);
    $sendDeferred = new Deferred;
    $this->mockTransport->shouldReceive('send')
        ->with(Mockery::on(fn (Request $r) => $r->id === $getPromptRequestId && $r->method === 'prompts/get' && $r->params['name'] === $promptName))
        ->once()->andReturn($sendDeferred->promise());

    // Act
    $getPromise = $this->client->getPromptAsync($promptName, $args);

    // Simulate
    $this->loop->addTimer(0.01, fn () => $sendDeferred->resolve(null));
    $this->loop->addTimer(0.02, fn () => $this->messageListenerCallback ? ($this->messageListenerCallback)($response) : null);

    // Assert
    $resolvedResult = await($getPromise);
    expect($resolvedResult)->toBeInstanceOf(GetPromptResult::class);
    expect($resolvedResult->description)->toBe('Test Desc');
    expect($resolvedResult->messages[0])->toBeInstanceOf(PromptMessage::class);
    expect($resolvedResult->messages[0]->content)->toBeInstanceOf(TextContent::class);

})->group('usesLoop');

it('can subscribe to resource asynchronously', function () {
    // Arrange
    $initRequestId = 'init-sub-async';
    $subRequestId = 'sub-async-1';
    $resourceUri = 'res://subscribe/me';
    $serverCaps = ['resources' => ['subscribe' => true]]; // Enable subscribe capability

    $this->idGenerator->shouldReceive('generate')->twice()->ordered()->andReturn($initRequestId, $subRequestId);

    simulateSuccessfulHandshake($initRequestId, $this->messageListenerCallback, $this->mockTransport, $this->loop, $serverCaps);

    await($this->client->initializeAsync());

    $response = new Response($subRequestId, new \stdClass);
    $sendDeferred = new Deferred;
    $this->mockTransport->shouldReceive('send')
        ->with(Mockery::on(fn (Request $r) => $r->id === $subRequestId && $r->method === 'resources/subscribe' && $r->params['uri'] === $resourceUri))
        ->once()->andReturn($sendDeferred->promise());

    // Act
    $subPromise = $this->client->subscribeResourceAsync($resourceUri);

    // Simulate
    $this->loop->addTimer(0.01, fn () => $sendDeferred->resolve(null));
    $this->loop->addTimer(0.02, fn () => $this->messageListenerCallback ? ($this->messageListenerCallback)($response) : null);

    // Assert
    $resolvedResult = await($subPromise);
    expect($resolvedResult)->toBeNull();

})->group('usesLoop');

it('cannot subscribe to resource asynchronously if capability missing', function () {
    // Arrange
    $initRequestId = 'init-sub-nocap';
    $this->idGenerator->shouldReceive('generate')->once()->andReturn($initRequestId);

    simulateSuccessfulHandshake($initRequestId, $this->messageListenerCallback, $this->mockTransport, $this->loop);

    await($this->client->initializeAsync());

    // Act
    $subPromise = $this->client->subscribeResourceAsync('res://no/sub');

    // Assert
    await($subPromise);

})->group('usesLoop')->throws(UnsupportedCapabilityException::class);

it('can unsubscribe from resource asynchronously', function () {
    // Arrange
    $initRequestId = 'init-unsub-async';
    $unsubRequestId = 'unsub-async-1';
    $resourceUri = 'res://unsubscribe/me';

    $this->idGenerator->shouldReceive('generate')->twice()->ordered()->andReturn($initRequestId, $unsubRequestId);

    simulateSuccessfulHandshake($initRequestId, $this->messageListenerCallback, $this->mockTransport, $this->loop);

    await($this->client->initializeAsync());

    $response = new Response($unsubRequestId, new \stdClass);
    $sendDeferred = new Deferred;
    $this->mockTransport->shouldReceive('send')
        ->with(Mockery::on(fn (Request $r) => $r->id === $unsubRequestId && $r->method === 'resources/unsubscribe' && $r->params['uri'] === $resourceUri))
        ->once()->andReturn($sendDeferred->promise());

    // Act
    $unsubPromise = $this->client->unsubscribeResourceAsync($resourceUri);

    // Simulate
    $this->loop->addTimer(0.01, fn () => $sendDeferred->resolve(null));
    $this->loop->addTimer(0.02, fn () => $this->messageListenerCallback ? ($this->messageListenerCallback)($response) : null);

    // Assert
    $resolvedResult = await($unsubPromise);
    expect($resolvedResult)->toBeNull();

})->group('usesLoop');

it('can set log level asynchronously', function () {
    // Arrange
    $initRequestId = 'init-log-async';
    $logRequestId = 'log-async-1';
    $level = 'debug';
    $serverCaps = ['logging' => []];

    $this->idGenerator->shouldReceive('generate')->twice()->ordered()->andReturn($initRequestId, $logRequestId);

    simulateSuccessfulHandshake($initRequestId, $this->messageListenerCallback, $this->mockTransport, $this->loop, $serverCaps);

    await($this->client->initializeAsync());

    $response = new Response($logRequestId, new \stdClass);
    $sendDeferred = new Deferred;
    $this->mockTransport->shouldReceive('send')
        ->with(Mockery::on(fn (Request $r) => $r->id === $logRequestId && $r->method === 'logging/setLevel' && $r->params['level'] === $level))
        ->once()->andReturn($sendDeferred->promise());

    // Act
    $logPromise = $this->client->setLogLevelAsync($level);

    // Simulate
    $this->loop->addTimer(0.01, fn () => $sendDeferred->resolve(null));
    $this->loop->addTimer(0.02, fn () => $this->messageListenerCallback ? ($this->messageListenerCallback)($response) : null);

    // Assert
    $resolvedResult = await($logPromise);
    expect($resolvedResult)->toBeNull();

})->group('usesLoop');

it('cannot set log level asynchronously if capability missing', function () {
    // Arrange
    $initRequestId = 'init-log-nocap';

    $this->idGenerator->shouldReceive('generate')->once()->andReturn($initRequestId);

    simulateSuccessfulHandshake($initRequestId, $this->messageListenerCallback, $this->mockTransport, $this->loop);

    await($this->client->initializeAsync());

    // Act
    $logPromise = $this->client->setLogLevelAsync('info');

    // Assert
    await($logPromise);

})->group('usesLoop')->throws(UnsupportedCapabilityException::class);

it('rejects asynchronous requests if client is not ready', function () {
    // Arrange
    // Do NOT initialize client

    // Act
    $promise = $this->client->listToolsAsync(); // Call any async method

    // Assert
    await($promise);

})->group('usesLoop')->throws(ConnectionException::class, 'Client not ready (Status: disconnected)');

it('rejects asynchronous requests if transport fails to send', function () {
    // Arrange
    $initRequestId = 'init-sendfail-async';
    $listToolsRequestId = 'list-sendfail-async';
    $this->idGenerator->shouldReceive('generate')->twice()->ordered()->andReturn($initRequestId, $listToolsRequestId);

    simulateSuccessfulHandshake($initRequestId, $this->messageListenerCallback, $this->mockTransport, $this->loop);

    await($this->client->initializeAsync());

    $exception = new TransportException('Write failed');
    // Mock send to reject immediately
    $this->mockTransport->shouldReceive('send')
        ->with(Mockery::on(fn (Request $r) => $r->id === $listToolsRequestId))
        ->once()->ordered()->andReturn(\React\Promise\reject($exception));

    // Act
    $listPromise = $this->client->listToolsAsync();

    // Assert
    await($listPromise);

})->group('usesLoop')->throws(TransportException::class, "Failed to send request 'tools/list' (ID: list-sendfail-async): Write failed");

it('can disconnect asynchronously', function () {
    // Arrange
    $initRequestId = 'init-disc-async';

    $this->idGenerator->shouldReceive('generate')->once()->andReturn($initRequestId);

    simulateSuccessfulHandshake($initRequestId, $this->messageListenerCallback, $this->mockTransport, $this->loop);

    await($this->client->initializeAsync());

    expect($this->client->getStatus())->toBe(ConnectionStatus::Ready);

    $closeListener = null;
    $this->mockTransport->shouldReceive('once')
        ->with('close', Mockery::capture($closeListener))
        ->once()->ordered();

    // Act
    $disconnectPromise = $this->client->disconnectAsync();

    $this->loop->addTimer(0.01, fn () => $closeListener ? $closeListener('test reason async') : null);

    // Assert
    await($disconnectPromise);

    expect($this->client->getStatus())->toBe(ConnectionStatus::Closed);

})->group('usesLoop');

it('rejects pending asynchronous requests during disconnection', function () {
    // Arrange
    $initRequestId = 'init-disc-pending';

    $this->idGenerator->shouldReceive('generate')->once()->andReturn($initRequestId);

    simulateSuccessfulHandshake($initRequestId, $this->messageListenerCallback, $this->mockTransport, $this->loop);

    await($this->client->initializeAsync());

    $pendingRequestId = 'req-pending-async';
    $requestDeferred = new Deferred;

    addPendingRequest($this->client, $pendingRequestId, $requestDeferred);

    expect(getPendingRequestCount($this->client))->toBe(1);

    // Setup disconnect mocks
    $this->mockTransport->shouldReceive('once')->with('close', Mockery::any())->once()->ordered()->andReturnUsing(function ($ev, $cb) {
        $this->loop->addTimer(0.01, fn () => $cb('sim close'));
    });

    /** @var ConnectionException $rejectedReason */
    $rejectedReason = null;
    $requestDeferred->promise()->catch(function ($reason) use (&$rejectedReason) {
        $rejectedReason = $reason;
    });

    // Act
    $disconnectPromise = $this->client->disconnectAsync();

    // Run loop to allow rejection to propagate via futureTick
    $this->loop->run();

    // Assert pending request rejection happened
    expect($rejectedReason)->toBeInstanceOf(ConnectionException::class);
    expect($rejectedReason->getMessage())->toContain('Connection closing');
    expect(getPendingRequestCount($this->client))->toBe(0);

    $disconnectResult = await($disconnectPromise);
    expect($disconnectResult)->toBeNull();
    expect($this->client->getStatus())->toBe(ConnectionStatus::Closed);

})->group('usesLoop');

// --- Notification Handling Test ---

it('dispatches events correctly when notifications are received asynchronously', function () {
    // Arrange
    $initRequestId = 'init-notify-async';
    $this->idGenerator->shouldReceive('generate')->once()->andReturn($initRequestId);

    simulateSuccessfulHandshake($initRequestId, $this->messageListenerCallback, $this->mockTransport, $this->loop);

    await($this->client->initializeAsync());

    $this->dispatcher->shouldReceive('dispatch')
        ->with(Mockery::type(ToolsListChanged::class))
        ->once()->andReturnArg(0);
    $this->dispatcher->shouldReceive('dispatch')
        ->with(Mockery::on(fn (ResourceChanged $e) => $e->uri === 'file:///a.txt'))
        ->once()->andReturnArg(0);

    // Act
    $notification1 = new Notification('notifications/tools/listChanged');
    $notification2 = new Notification('notifications/resources/didChange', ['uri' => 'file:///a.txt']);
    $notificationIgnored = new Notification('unhandled/event');

    // Act
    expect($this->messageListenerCallback)->toBeCallable();
    ($this->messageListenerCallback)($notification1);
    ($this->messageListenerCallback)($notification2);
    ($this->messageListenerCallback)($notificationIgnored);

    // Assert
    expect(true)->toBeTrue();

});
