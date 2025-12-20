<?php

namespace PhpMcp\Client\Tests\Feature;

use Mockery;
use PhpMcp\Client\Contracts\TransportInterface;
use PhpMcp\Client\Enum\ConnectionStatus;
use PhpMcp\Client\Enum\TransportType;
use PhpMcp\Client\Exception\ConnectionException;
use PhpMcp\Client\Exception\RequestException;
use PhpMcp\Client\Exception\TimeoutException;
use PhpMcp\Client\Exception\TransportException;
use PhpMcp\Client\Exception\UnsupportedCapabilityException;
use PhpMcp\Client\Factory\MessageIdGenerator;
use PhpMcp\Client\JsonRpc\Error as JsonRpcError;
use PhpMcp\Client\JsonRpc\Response;
use PhpMcp\Client\Model\Content\EmbeddedResource;
use PhpMcp\Client\Model\Content\PromptMessage;
use PhpMcp\Client\Model\Content\TextContent;
use PhpMcp\Client\Model\Definitions\PromptDefinition;
use PhpMcp\Client\Model\Definitions\ResourceDefinition;
use PhpMcp\Client\Model\Definitions\ResourceTemplateDefinition;
use PhpMcp\Client\Model\Definitions\ToolDefinition;
use PhpMcp\Client\ServerConfig;
use React\EventLoop\Loop;
use React\Promise\Deferred;

const TEST_SERVER_NAME_STDIO_UNIT = 'stdio_unit_client';

beforeEach(function () {
    $this->loop = Loop::get();

    $this->serverConfig = new ServerConfig(
        name: TEST_SERVER_NAME_STDIO_UNIT,
        transport: TransportType::Stdio,
        command: 'dummy_stdio_cmd_for_test',
        timeout: 0.2
    );

    $this->mockTransport = Mockery::mock(TransportInterface::class);
    $this->messageListenerCallback = null;

    $this->mockTransport->shouldReceive('on')
        ->with('message', Mockery::capture($this->messageListenerCallback))
        ->atMost(1)
        ->andReturnUsing(function () {})
        ->byDefault();

    $this->mockTransport->shouldReceive('on')->with('error', Mockery::any())->byDefault();
    $this->mockTransport->shouldReceive('on')->with('close', Mockery::any())->byDefault();
    $this->mockTransport->shouldReceive('on')->with('stderr', Mockery::any())->byDefault();
    $this->mockTransport->shouldReceive('close')->byDefault();

    $this->idGenerator = Mockery::mock(MessageIdGenerator::class);

    $this->client = createClientForTesting(
        $this->serverConfig,
        $this->mockTransport,
        $this->loop,
        $this->idGenerator
    );
});

it('initializes successfully', function () {
    // Arrange
    $initRequestId = 'test-init-1';
    $this->idGenerator->shouldReceive('generate')->once()->andReturn($initRequestId);

    simulateSuccessfulHandshake(
        $initRequestId,
        $this->messageListenerCallback,
        $this->mockTransport,
        $this->loop
    );

    // Act
    $returnedClient = $this->client->initialize();

    // Assert
    expect($returnedClient)->toBe($this->client);
    expect($this->client->getStatus())->toBe(ConnectionStatus::Ready);
    expect($this->client->getServerName())->toBe('MockServer');

})->group('usesLoop');

it('throws connection exception if initialize transport connect fails', function () {
    // Arrange
    $connectDeferred = new Deferred;
    $exception = new TransportException('Process start failed');
    $this->mockTransport->shouldReceive('connect')->once()->andReturn($connectDeferred->promise());
    $this->loop->addTimer(0.01, fn () => $connectDeferred->reject($exception));

    // Act & Assert
    $this->client->initialize();

})->throws(ConnectionException::class, 'Connection failed: Process start failed');

it('throws connection exception if initialize handshake fails (server error response)', function () {
    // Arrange
    $initRequestId = 'test-init-fail';
    $this->idGenerator->shouldReceive('generate')->once()->andReturn($initRequestId);

    $connectDeferred = new Deferred;
    $this->mockTransport->shouldReceive('connect')->once()->andReturn($connectDeferred->promise());
    $this->loop->addTimer(0.001, fn () => $connectDeferred->resolve(null));

    simulateSuccessfulRequest(
        'initialize',
        $initRequestId,
        $this->mockTransport,
        $this->loop
    );

    $error = new JsonRpcError(-32000, 'Version Mismatch');
    $initErrorResponse = new Response($initRequestId, null, $error);

    simulateServerResponse($initErrorResponse, $this->loop, $this->messageListenerCallback, 0.003);

    // Act & Assert
    $this->client->initialize();

})->throws(ConnectionException::class, 'Connection failed: Version Mismatch');

it('can ping server synchronously', function () {
    // Arrange
    $initRequestId = 'init-ping-sync';
    $pingRequestId = 'ping-sync-1';

    $this->idGenerator->shouldReceive('generate')->twice()->andReturn($initRequestId, $pingRequestId);

    simulateSuccessfulHandshake($initRequestId, $this->messageListenerCallback, $this->mockTransport, $this->loop);

    $this->client->initialize();

    simulateSuccessfulRequest('ping', $pingRequestId, $this->mockTransport, $this->loop);

    $response = new Response($pingRequestId, new \stdClass);
    simulateServerResponse($response, $this->loop, $this->messageListenerCallback);

    // Act
    $this->client->ping();

    // Assert: No exception thrown means success
    expect(true)->toBeTrue();

})->group('usesLoop');

it('can list tools synchronously', function () {
    // Arrange
    $initRequestId = 'test-init-ok';
    $listToolsRequestId = 'test-listtools-ok';

    $this->idGenerator->shouldReceive('generate')->twice()->andReturn($initRequestId, $listToolsRequestId);

    simulateSuccessfulHandshake(
        $initRequestId, $this->messageListenerCallback, $this->mockTransport, $this->loop
    );

    $this->client->initialize();

    expect($this->client->getStatus())->toBe(ConnectionStatus::Ready);

    simulateSuccessfulRequest(
        'tools/list',
        $listToolsRequestId,
        $this->mockTransport,
        $this->loop
    );

    $listToolsResultData = [
        'tools' => [
            ['name' => 'toolA', 'description' => 'A', 'inputSchema' => ['type' => 'object']],
            ['name' => 'toolB', 'description' => 'B', 'inputSchema' => ['type' => 'object']],
        ],
    ];
    $listToolsResponse = new Response($listToolsRequestId, $listToolsResultData);
    simulateServerResponse($listToolsResponse, $this->loop, $this->messageListenerCallback);

    // Act
    $tools = $this->client->listTools(false);

    // Assert
    expect($tools)->toBeArray();
    expect($tools[0])->toBeInstanceOf(ToolDefinition::class);
    expect($tools[0]->name)->toBe('toolA');
    expect($tools[1]->name)->toBe('toolB');

})->group('usesLoop');

it('can call tool synchronously and receive result', function () {
    // Arrange
    $initRequestId = 'test-init-ok-call';
    $callToolRequestId = 'test-calltool-ok';

    $this->idGenerator->shouldReceive('generate')->twice()->andReturn($initRequestId, $callToolRequestId);

    simulateSuccessfulHandshake(
        $initRequestId, $this->messageListenerCallback, $this->mockTransport, $this->loop
    );

    $this->client->initialize();

    expect($this->client->getStatus())->toBe(ConnectionStatus::Ready);

    simulateSuccessfulRequest(
        'tools/call',
        $callToolRequestId,
        $this->mockTransport,
        $this->loop,
        function ($params) {
            return isset($params['name']) && $params['name'] === 'toolA'
                && isset($params['arguments']['p1']) && $params['arguments']['p1'] === 'v1';
        }
    );

    $callToolResultData = [
        'content' => [['type' => 'text', 'text' => 'Tool A Success!']],
        'isError' => false,
    ];

    $callToolResponse = new Response($callToolRequestId, $callToolResultData);
    simulateServerResponse($callToolResponse, $this->loop, $this->messageListenerCallback);

    // Act
    $result = $this->client->callTool('toolA', ['p1' => 'v1']);

    // Assert
    expect($result->isSuccess())->toBeTrue();
    expect($result->content[0]->text)->toBe('Tool A Success!');

})->group('usesLoop');

it('throws exception when calling methods before initialization', function () {
    // Act & Assert
    $this->client->listTools();
})->throws(ConnectionException::class, 'Client not initialized. Call initialize() first.');

it('handles server error synchronously when calling tool', function () {
    // Arrange
    $initRequestId = 'test-init-ok-reqerr';
    $callToolRequestId = 'test-calltool-reqerr';

    $this->idGenerator->shouldReceive('generate')->twice()->andReturn($initRequestId, $callToolRequestId);

    simulateSuccessfulHandshake(
        $initRequestId, $this->messageListenerCallback, $this->mockTransport, $this->loop
    );

    $this->client->initialize();

    simulateSuccessfulRequest('tools/call', $callToolRequestId, $this->mockTransport, $this->loop);

    $error = new JsonRpcError(-32602, 'Invalid Args');

    $callToolErrorResponse = new Response($callToolRequestId, null, $error);
    simulateServerResponse($callToolErrorResponse, $this->loop, $this->messageListenerCallback);

    // Act & Assert
    $this->client->callTool('toolA', []);

})->throws(RequestException::class, 'Invalid Args');

it('throws timeout exception if server does not respond to request', function () {
    // Arrange
    $initRequestId = 'test-init-ok-timeout';
    $listToolsRequestId = 'test-listtools-timeout';

    $this->idGenerator->shouldReceive('generate')->twice()->andReturn($initRequestId, $listToolsRequestId);

    simulateSuccessfulHandshake(
        $initRequestId, $this->messageListenerCallback, $this->mockTransport, $this->loop
    );

    $this->client->initialize();

    simulateSuccessfulRequest('tools/list', $listToolsRequestId, $this->mockTransport, $this->loop);

    // DO NOT simulate receiving a response

    // Act & Assert
    $this->client->listTools(); // Use the 0.2s timeout from beforeEach

})->throws(TimeoutException::class, "Request 'tools/list' (ID: test-listtools-timeout) timed out after 0.2 seconds")
    ->group('usesLoop');

it('handles timeout synchronously when calling tool', function () {
    // Arrange
    $initRequestId = 'test-init-ok-timeout-call';
    $callToolRequestId = 'test-calltool-timeout';

    $this->idGenerator->shouldReceive('generate')->twice()->andReturn($initRequestId, $callToolRequestId);

    simulateSuccessfulHandshake($initRequestId, $this->messageListenerCallback, $this->mockTransport, $this->loop);

    $this->client->initialize();

    simulateSuccessfulRequest('tools/call', $callToolRequestId, $this->mockTransport, $this->loop);
    // DO NOT simulate receiving a response
    // Act & Assert
    $this->client->callTool('toolA', []);
})->throws(TimeoutException::class, "Request 'tools/call' (ID: test-calltool-timeout) timed out after 0.2 seconds")
    ->group('usesLoop');

it('can list resources synchronously', function () {
    // Arrange
    $initRequestId = 'init-listres-sync';
    $listResRequestId = 'listres-sync-1';

    $this->idGenerator->shouldReceive('generate')->twice()->andReturn($initRequestId, $listResRequestId);

    simulateSuccessfulHandshake($initRequestId, $this->messageListenerCallback, $this->mockTransport, $this->loop);

    $this->client->initialize(); // Ensure ready

    simulateSuccessfulRequest('resources/list', $listResRequestId, $this->mockTransport, $this->loop);

    $resData = [['uri' => 'res://sync/1', 'name' => 'SyncRes1', 'mimeType' => null, 'size' => null, 'annotations' => []]];
    $response = new Response($listResRequestId, ['resources' => $resData]);
    simulateServerResponse($response, $this->loop, $this->messageListenerCallback);

    // Act
    $resources = $this->client->listResources(false); // Disable cache

    // Assert
    expect($resources)->toBeArray()->toHaveCount(1);
    expect($resources[0])->toBeInstanceOf(ResourceDefinition::class);
    expect($resources[0]->uri)->toBe('res://sync/1');
})->group('usesLoop');

it('can list resource templates synchronously', function () {
    // Arrange
    $initRequestId = 'init-listtmpl-sync';
    $listTmplRequestId = 'listtmpl-sync-1';

    $this->idGenerator->shouldReceive('generate')->twice()->andReturn($initRequestId, $listTmplRequestId);

    simulateSuccessfulHandshake($initRequestId, $this->messageListenerCallback, $this->mockTransport, $this->loop);

    $this->client->initialize();

    simulateSuccessfulRequest('resources/templates/list', $listTmplRequestId, $this->mockTransport, $this->loop);

    $tmplData = [['uriTemplate' => 'tmpl://sync/{id}', 'name' => 'SyncTmpl1', 'mimeType' => null, 'description' => null, 'annotations' => []]];
    $response = new Response($listTmplRequestId, ['resourceTemplates' => $tmplData]);
    simulateServerResponse($response, $this->loop, $this->messageListenerCallback);

    // Act
    $templates = $this->client->listResourceTemplates(false);

    // Assert
    expect($templates)->toBeArray()->toHaveCount(1);
    expect($templates[0])->toBeInstanceOf(ResourceTemplateDefinition::class);
    expect($templates[0]->uriTemplate)->toBe('tmpl://sync/{id}');
})->group('usesLoop');

it('can list prompts synchronously', function () {
    // Arrange
    $initRequestId = 'init-listprmpt-sync';
    $listPrmptRequestId = 'listprmpt-sync-1';

    $this->idGenerator->shouldReceive('generate')->twice()->andReturn($initRequestId, $listPrmptRequestId);

    simulateSuccessfulHandshake($initRequestId, $this->messageListenerCallback, $this->mockTransport, $this->loop);

    $this->client->initialize();

    simulateSuccessfulRequest('prompts/list', $listPrmptRequestId, $this->mockTransport, $this->loop);

    $prmptData = [['name' => 'SyncP1', 'description' => null, 'arguments' => []]];
    $response = new Response($listPrmptRequestId, ['prompts' => $prmptData]);
    simulateServerResponse($response, $this->loop, $this->messageListenerCallback);

    // Act
    $prompts = $this->client->listPrompts(false);

    // Assert
    expect($prompts)->toBeArray()->toHaveCount(1);
    expect($prompts[0])->toBeInstanceOf(PromptDefinition::class);
    expect($prompts[0]->name)->toBe('SyncP1');
})->group('usesLoop');

it('can read resource synchronously', function () {
    // Arrange
    $initRequestId = 'init-readres-sync';
    $readResRequestId = 'readres-sync-1';
    $resourceUri = 'sync://resource';

    $this->idGenerator->shouldReceive('generate')->twice()->andReturn($initRequestId, $readResRequestId);

    simulateSuccessfulHandshake($initRequestId, $this->messageListenerCallback, $this->mockTransport, $this->loop);

    $this->client->initialize();

    simulateSuccessfulRequest(
        'resources/read',
        $readResRequestId,
        $this->mockTransport,
        $this->loop,
        fn ($p) => $p['uri'] === $resourceUri
    );

    $contentData = ['uri' => $resourceUri, 'mimeType' => 'text/plain', 'text' => 'sync content'];
    $response = new Response($readResRequestId, ['contents' => [$contentData]]);
    simulateServerResponse($response, $this->loop, $this->messageListenerCallback);

    // Act
    $result = $this->client->readResource($resourceUri);

    // Assert
    expect($result->contents[0])->toBeInstanceOf(EmbeddedResource::class);
    expect($result->contents[0]->text)->toBe('sync content');
})->group('usesLoop');

it('can get prompt synchronously', function () {
    // Arrange
    $initRequestId = 'init-getprmpt-sync';
    $getPrmptRequestId = 'getprmpt-sync-1';
    $promptName = 'syncPrompt';
    $args = ['id' => 123];

    $this->idGenerator->shouldReceive('generate')->twice()->andReturn($initRequestId, $getPrmptRequestId);

    simulateSuccessfulHandshake($initRequestId, $this->messageListenerCallback, $this->mockTransport, $this->loop);

    $this->client->initialize();

    simulateSuccessfulRequest(
        'prompts/get',
        $getPrmptRequestId,
        $this->mockTransport,
        $this->loop,
        fn ($p) => $p['name'] === $promptName && $p['arguments'] == $args // Loose compare args
    );

    $messagesData = [['role' => 'user', 'content' => ['type' => 'text', 'text' => 'Sync Prompt Text']]];
    $response = new Response($getPrmptRequestId, ['messages' => $messagesData, 'description' => 'Sync Desc']);
    simulateServerResponse($response, $this->loop, $this->messageListenerCallback);

    // Act
    $result = $this->client->getPrompt($promptName, $args);

    // Assert
    expect($result->description)->toBe('Sync Desc');
    expect($result->messages[0])->toBeInstanceOf(PromptMessage::class);
    expect($result->messages[0]->content)->toBeInstanceOf(TextContent::class);

})->group('usesLoop');

it('can subscribe to resource synchronously', function () {
    // Arrange
    $initRequestId = 'init-sub-sync';
    $subRequestId = 'sub-sync-1';
    $resourceUri = 'res://sub/sync';
    $serverCaps = ['resources' => ['subscribe' => true]];

    $this->idGenerator->shouldReceive('generate')->twice()->andReturn($initRequestId, $subRequestId);

    simulateSuccessfulHandshake($initRequestId, $this->messageListenerCallback, $this->mockTransport, $this->loop, $serverCaps);

    $this->client->initialize();

    simulateSuccessfulRequest(
        'resources/subscribe',
        $subRequestId,
        $this->mockTransport,
        $this->loop,
        fn ($p) => $p['uri'] === $resourceUri
    );

    $response = new Response($subRequestId, new \stdClass);
    simulateServerResponse($response, $this->loop, $this->messageListenerCallback);

    // Act
    $this->client->subscribeResource($resourceUri);

    // Assert: No exception thrown means success
    expect(true)->toBeTrue();

})->group('usesLoop');

it('throws exception when subscribing if capability missing', function () {
    // Arrange
    $initRequestId = 'init-sub-sync-nocap';

    $this->idGenerator->shouldReceive('generate')->once()->andReturn($initRequestId);

    simulateSuccessfulHandshake($initRequestId, $this->messageListenerCallback, $this->mockTransport, $this->loop, []); // No caps
    $this->client->initialize();

    // Act & Assert
    $this->client->subscribeResource('res://no/sub/sync');

})->throws(UnsupportedCapabilityException::class);

it('can unsubscribe from resource synchronously', function () {
    // Arrange
    $initRequestId = 'init-unsub-sync';
    $unsubRequestId = 'unsub-sync-1';
    $resourceUri = 'res://unsub/sync';

    $this->idGenerator->shouldReceive('generate')->twice()->andReturn($initRequestId, $unsubRequestId);

    simulateSuccessfulHandshake($initRequestId, $this->messageListenerCallback, $this->mockTransport, $this->loop);

    $this->client->initialize();

    simulateSuccessfulRequest(
        'resources/unsubscribe',
        $unsubRequestId,
        $this->mockTransport,
        $this->loop,
        fn ($p) => $p['uri'] === $resourceUri
    );

    $response = new Response($unsubRequestId, new \stdClass);
    simulateServerResponse($response, $this->loop, $this->messageListenerCallback);

    // Act
    $this->client->unsubscribeResource($resourceUri);

    // Assert: No exception thrown means success
    expect(true)->toBeTrue();

})->group('usesLoop');

it('can set log level synchronously', function () {
    // Arrange
    $initRequestId = 'init-setlog-sync';
    $setLogRequestId = 'setlog-sync-1';
    $level = 'info';
    $serverCaps = ['logging' => []];

    $this->idGenerator->shouldReceive('generate')->twice()->andReturn($initRequestId, $setLogRequestId);

    simulateSuccessfulHandshake($initRequestId, $this->messageListenerCallback, $this->mockTransport, $this->loop, $serverCaps);

    $this->client->initialize();

    simulateSuccessfulRequest(
        'logging/setLevel',
        $setLogRequestId,
        $this->mockTransport,
        $this->loop,
        fn ($p) => $p['level'] === $level
    );

    $response = new Response($setLogRequestId, new \stdClass);
    simulateServerResponse($response, $this->loop, $this->messageListenerCallback);

    // Act
    $this->client->setLogLevel($level);

    // Assert: No exception thrown means success
    expect(true)->toBeTrue();

})->group('usesLoop');

it('throws exception when setting log level if capability missing', function () {
    // Arrange
    $initRequestId = 'init-setlog-sync-nocap';

    $this->idGenerator->shouldReceive('generate')->once()->andReturn($initRequestId);

    simulateSuccessfulHandshake($initRequestId, $this->messageListenerCallback, $this->mockTransport, $this->loop, []); // No caps

    $this->client->initialize();

    // Act & Assert
    $this->client->setLogLevel('debug');

})->throws(UnsupportedCapabilityException::class);

it('disconnects successfully after initialization', function () {
    // Arrange
    $initRequestId = 'test-init-disc';

    $this->idGenerator->shouldReceive('generate')->once()->andReturn($initRequestId);

    simulateSuccessfulHandshake(
        $initRequestId, $this->messageListenerCallback, $this->mockTransport, $this->loop
    );

    $this->client->initialize();

    expect($this->client->getStatus())->toBe(ConnectionStatus::Ready);

    $closeListenerCallback = null;
    $this->mockTransport->shouldReceive('once')
        ->with('close', Mockery::capture($closeListenerCallback))
        ->once()->ordered();

    $this->mockTransport->shouldReceive('close')
        ->once()->ordered()
        ->andReturnUsing(function () use (&$closeListenerCallback) {
            $this->loop->addTimer(0.01, function () use (&$closeListenerCallback) {
                if (is_callable($closeListenerCallback)) {
                    $closeListenerCallback('Client initiated close.');
                } else {
                    throw new \RuntimeException('Disconnect test: Close callback was not captured!');
                }
            });
        });

    // Act
    $this->client->disconnect(); // Call blocking disconnect

    // Assert
    expect($this->client->getStatus())->toBe(ConnectionStatus::Closed);
    // Mockery verifies the calls/order

})->group('usesLoop');
