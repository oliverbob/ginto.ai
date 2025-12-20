<?php

use Mockery\MockInterface;
use PhpMcp\Client\Client;
use PhpMcp\Client\ClientConfig;
use PhpMcp\Client\Contracts\TransportInterface;
use PhpMcp\Client\Enum\ConnectionStatus;
use PhpMcp\Client\Factory\MessageIdGenerator;
use PhpMcp\Client\Factory\TransportFactory;
use PhpMcp\Client\JsonRpc\Notification;
use PhpMcp\Client\JsonRpc\Request;
use PhpMcp\Client\JsonRpc\Response;
use PhpMcp\Client\Model\Capabilities;
use PhpMcp\Client\ServerConfig;
use PhpMcp\Client\ServerConnection;
use Psr\Log\NullLogger;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;

uses(PhpMcp\Client\Tests\TestCase::class)->in('Feature', 'Unit');

/**
 * Helper function to create a Client instance configured for testing
 * with a mocked transport.
 *
 * @param  MockInterface&TransportInterface  $mockTransport  // The mocked transport instance
 * @param  array  $options  Optional overrides for ClientConfig
 * @return Client The configured Client instance
 */
function createClientForTesting(
    ServerConfig $serverConfig,
    MockInterface&TransportInterface $mockTransport,
    ?LoopInterface $loop = null,
    ?MessageIdGenerator $idGenerator = null,
    array $options = []
): Client {
    $transportFactory = Mockery::mock(TransportFactory::class);
    $transportFactory->shouldReceive('create')
        ->with(Mockery::on(fn ($arg) => $arg === $serverConfig))
        ->andReturn($mockTransport);

    $clientName = $options['clientName'] ?? 'FeatureTestClient';
    $clientVersion = $options['clientVersion'] ?? '1.0';
    $clientCaps = $options['capabilities'] ?? Capabilities::forClient();
    $logger = $options['logger'] ?? new NullLogger;
    $cache = $options['cache'] ?? null;
    $dispatcher = $options['dispatcher'] ?? null;

    $clientConfig = new ClientConfig(
        name: $clientName,
        version: $clientVersion,
        capabilities: $clientCaps,
        logger: $logger,
        cache: $cache,
        eventDispatcher: $dispatcher,
        loop: $loop,
        idGenerator: $idGenerator,
    );

    return new Client($serverConfig, $clientConfig, $transportFactory);
}

/**
 * Helper to simulate the standard successful handshake sequence on a mock transport.
 * Assumes connect() resolves, initialize response is success, initialized send succeeds.
 *
 * @param  string  $initRequestId  ID used internally for initialize promise mapping if needed
 */
function simulateSuccessfulHandshake(
    string $initRequestId,
    &$messageListenerCallback,
    MockInterface&TransportInterface $mockTransport,
    LoopInterface $loop,
    ?array $serverCaps = null
): void {
    $connectDeferred = new Deferred;
    $mockTransport->shouldReceive('connect')->once()->andReturn($connectDeferred->promise());
    $loop->addTimer(0.001, fn () => $connectDeferred->resolve(null));

    $initSendDeferred = new Deferred;
    $mockTransport->shouldReceive('send')
        ->with(Mockery::on(function ($msg) use ($initRequestId) {
            return $msg instanceof Request && $msg->method === 'initialize' && $msg->id === $initRequestId;
        }))
        ->once()->ordered()
        ->andReturn($initSendDeferred->promise());
    $loop->addTimer(0.002, fn () => $initSendDeferred->resolve(null));

    $initResponseDeferred = new Deferred;
    $initResultData = [
        'protocolVersion' => '2024-11-05',
        'serverInfo' => ['name' => 'MockServer', 'version' => '1.0'],
        'capabilities' => $serverCaps ?? ['tools' => new stdClass],
    ];
    $initResponse = new Response($initRequestId, $initResultData);
    $loop->addTimer(0.003, fn () => $initResponseDeferred->resolve(null));
    $initResponseDeferred->promise()->then(function () use (&$messageListenerCallback, $initResponse) {
        if (is_callable($messageListenerCallback)) {
            $messageListenerCallback($initResponse);
        } else {
            throw new \RuntimeException('Handshake simulation: Message callback was not captured!');
        }
    });

    $initializedSendDeferred = new Deferred;
    $mockTransport->shouldReceive('send')
        ->with(Mockery::on(function ($msg) {
            return $msg instanceof Notification && $msg->method === 'notifications/initialized';
        }))
        ->once()->ordered()
        ->andReturn($initializedSendDeferred->promise());
    $loop->addTimer(0.004, fn () => $initializedSendDeferred->resolve(null));

}

function simulateSuccessfulRequest(
    string $method,
    string $requestId,
    MockInterface&TransportInterface $mockTransport,
    LoopInterface $loop,
    ?\Closure $paramCheck = null
): void {
    $requestDeferred = new Deferred;
    $mockTransport->shouldReceive('send')
        ->with(Mockery::on(function ($msg) use ($requestId, $method, $paramCheck) {
            $match = $msg instanceof Request && $msg->id === $requestId && $msg->method === $method;
            if ($match && $paramCheck) {
                return $paramCheck($msg->params);
            }

            return $match;
        }))
        ->once()->ordered()
        ->andReturn($requestDeferred->promise());
    $loop->addTimer(0.005, fn () => $requestDeferred->resolve(null));
}

// Helper to simulate receiving a specific response for a request ID
function simulateServerResponse(
    Response|Notification $response,
    LoopInterface $loop,
    &$messageListenerCallback,
    float $delay = 0.01
): void {
    $responseDeferred = new Deferred;

    $loop->addTimer($delay, fn () => $responseDeferred->resolve(null));

    $responseDeferred->promise()->then(function () use (&$messageListenerCallback, $response) {
        if (is_callable($messageListenerCallback)) {
            $messageListenerCallback($response); // Simulate transport sending the response
        } else {
            throw new \RuntimeException('Message callback was not captured!');
        }
    });
}

function setConnectionStatus(ServerConnection $conn, ConnectionStatus $status)
{
    $reflector = new ReflectionClass($conn);
    $prop = $reflector->getProperty('status');
    $prop->setAccessible(true);
    $prop->setValue($conn, $status);
}

function setTransport(ServerConnection $conn, TransportInterface $transport): void
{
    $reflector = new ReflectionClass($conn);
    $prop = $reflector->getProperty('transport');
    $prop->setAccessible(true);
    $prop->setValue($conn, $transport);
}

function addPendingRequest(Client $client, string|int $id, Deferred $deferred): void
{
    $reflector = new ReflectionClass($client);
    $prop = $reflector->getProperty('pendingRequests');
    $prop->setAccessible(true);
    $requests = $prop->getValue($client);
    $requests[$id] = $deferred;
    $prop->setValue($client, $requests);
}

function getPendingRequestCount(Client $client): int
{
    $reflector = new ReflectionClass($client);
    $prop = $reflector->getProperty('pendingRequests');
    $prop->setAccessible(true);

    return count($prop->getValue($client));
}

function clearPendingRequests(Client $client): void
{
    $reflector = new ReflectionClass($client);
    $prop = $reflector->getProperty('pendingRequests');
    $prop->setAccessible(true);
    $prop->setValue($client, []);
}
