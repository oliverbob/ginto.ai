<?php

namespace PhpMcp\Client\Tests\Feature;

use Mockery;
use PhpMcp\Client\Contracts\TransportInterface;
use PhpMcp\Client\Enum\TransportType;
use PhpMcp\Client\Event\LogReceived;
use PhpMcp\Client\Event\PromptsListChanged;
use PhpMcp\Client\Event\ResourceChanged;
use PhpMcp\Client\Event\ResourcesListChanged;
use PhpMcp\Client\Event\ToolsListChanged;
use PhpMcp\Client\Factory\MessageIdGenerator;
use PhpMcp\Client\JsonRpc\Notification;
use PhpMcp\Client\JsonRpc\Response;
use PhpMcp\Client\ServerConfig;
use Psr\EventDispatcher\EventDispatcherInterface;
use React\EventLoop\Factory;

use function React\Async\await;

const TEST_SERVER_NAME_NOTIFY = 'notify_feature_test';

beforeEach(function () {
    $this->loop = Factory::create();

    $this->serverConfig = new ServerConfig(
        name: TEST_SERVER_NAME_NOTIFY,
        transport: TransportType::Http,
        url: 'http://notify.test',
        timeout: 1.0
    );

    $this->mockTransport = Mockery::mock(TransportInterface::class);
    $this->messageListenerCallback = null;

    $this->mockTransport->shouldReceive('on')
        ->with('message', Mockery::capture($this->messageListenerCallback))
        ->once()
        ->andReturnUsing(function () {});

    $this->mockTransport->shouldReceive('on')->with('error', Mockery::any())->byDefault();
    $this->mockTransport->shouldReceive('on')->with('close', Mockery::any())->byDefault();
    $this->mockTransport->shouldReceive('close')->byDefault();

    $this->mockDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $this->idGenerator = Mockery::mock(MessageIdGenerator::class);

    $this->client = createClientForTesting(
        $this->serverConfig,
        $this->mockTransport,
        $this->loop,
        $this->idGenerator,
        ['dispatcher' => $this->mockDispatcher]
    );
});

it('dispatches events when notifications are received', function () {
    // Arrange
    $initRequestId = 'test-notify-init-1';
    $pingRequestId = 'test-notify-ping-1';

    $this->idGenerator->shouldReceive('generate')->once()->andReturn($initRequestId);

    simulateSuccessfulHandshake(
        $initRequestId,
        $this->messageListenerCallback,
        $this->mockTransport,
        $this->loop,
        ['resources' => ['subscribe' => true]]
    );

    await($this->client->initializeAsync());

    $this->idGenerator->shouldReceive('generate')->once()->andReturn($pingRequestId);

    simulateSuccessfulRequest(
        'ping',
        $pingRequestId,
        $this->mockTransport,
        $this->loop
    );

    $pingResponse = new Response($pingRequestId, new \stdClass);

    simulateServerResponse($pingResponse, $this->loop, $this->messageListenerCallback, 0.03);

    $this->client->ping(TEST_SERVER_NAME_NOTIFY);

    expect($this->messageListenerCallback)->toBeCallable();

    $this->mockDispatcher->shouldReceive('dispatch')
        ->with(Mockery::type(ToolsListChanged::class))
        ->once()
        ->andReturnUsing(function ($event) {
            expect($event->serverName)->toBe(TEST_SERVER_NAME_NOTIFY);

            return $event;
        });

    $this->mockDispatcher->shouldReceive('dispatch')
        ->with(Mockery::on(function (ResourceChanged $event) {
            return $event->serverName === 'MockServer'
                   && $event->uri === 'file:///updated.txt';
        }))
        ->once()
        ->andReturnUsing(fn ($event) => $event);

    $this->mockDispatcher->shouldReceive('dispatch')
        ->with(Mockery::type(ResourcesListChanged::class))
        ->once()->andReturnUsing(fn ($e) => $e);

    $this->mockDispatcher->shouldReceive('dispatch')
        ->with(Mockery::type(PromptsListChanged::class))
        ->once()->andReturnUsing(fn ($e) => $e);

    $this->mockDispatcher->shouldReceive('dispatch')
        ->with(Mockery::type(LogReceived::class))
        ->once()->andReturnUsing(fn ($e) => $e);

    // Act
    $notification1 = new Notification('notifications/tools/listChanged');
    $notification2 = new Notification('notifications/resources/didChange', ['uri' => 'file:///updated.txt']);
    $notification3 = new Notification('notifications/resources/listChanged');
    $notification4 = new Notification('notifications/prompts/listChanged');
    $notification5 = new Notification('notifications/logging/log', ['level' => 'info', 'message' => 'Server says hi']);
    $notificationIgnored = new Notification('other/stuff');

    ($this->messageListenerCallback)($notification1);
    ($this->messageListenerCallback)($notification2);
    ($this->messageListenerCallback)($notification3);
    ($this->messageListenerCallback)($notification4);
    ($this->messageListenerCallback)($notification5);
    ($this->messageListenerCallback)($notificationIgnored);

    // Assert
    expect(true)->toBeTrue();

})->group('usesLoop');
