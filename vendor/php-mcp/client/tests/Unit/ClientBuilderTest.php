<?php

use PhpMcp\Client\Client;
use PhpMcp\Client\ClientConfig;
use PhpMcp\Client\Enum\TransportType;
use PhpMcp\Client\Exception\ConfigurationException;
use PhpMcp\Client\Factory\MessageIdGenerator;
use PhpMcp\Client\Model\Capabilities;
use PhpMcp\Client\ServerConfig;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use React\EventLoop\LoopInterface;

it('builds client with minimal configuration', function () {
    // Arrange

    // Act
    $client = Client::make()
        ->withName('MinClient')
        ->withVersion('0.1')
        ->withServerConfig(new ServerConfig(name: 's1', transport: TransportType::Stdio, command: 'c'))
        ->build();

    // Assert
    expect($client)->toBeInstanceOf(Client::class);

    $reflector = new ReflectionClass($client);
    $configProp = $reflector->getProperty('clientConfig');
    $configProp->setAccessible(true);
    $internalConfig = $configProp->getValue($client);

    expect($internalConfig)->toBeInstanceOf(ClientConfig::class);
    expect($internalConfig->name)->toBe('MinClient');
    expect($internalConfig->version)->toBe('0.1');
    expect($internalConfig->capabilities)->toBeInstanceOf(Capabilities::class);
    expect($internalConfig->logger)->toBeInstanceOf(\Psr\Log\NullLogger::class);
    expect($internalConfig->cache)->toBeNull();
    expect($internalConfig->eventDispatcher)->toBeNull();
    expect($internalConfig->loop)->toBeInstanceOf(LoopInterface::class);
});

it('builds client with all configurations', function () {
    // Arrange
    $caps = Capabilities::forClient(supportsSampling: false, supportsRootListChanged: true);
    $logger = Mockery::mock(LoggerInterface::class);
    $cache = Mockery::mock(CacheInterface::class);
    $dispatcher = Mockery::mock(EventDispatcherInterface::class);
    $loop = Mockery::mock(LoopInterface::class);
    $idGen = new MessageIdGenerator('test-');
    $server = new ServerConfig(name: 's1', transport: TransportType::Stdio, command: 'c');

    // Act
    $client = Client::make()
        ->withName('FullClient')
        ->withVersion('1.1')
        ->withCapabilities($caps)
        ->withLogger($logger)
        ->withCache($cache, 900)
        ->withEventDispatcher($dispatcher)
        ->withLoop($loop)
        ->withIdGenerator($idGen)
        ->withServerConfig($server)
        ->build();

    // Assert
    expect($client)->toBeInstanceOf(Client::class);
    $reflector = new ReflectionClass($client);
    $configProp = $reflector->getProperty('clientConfig');
    $configProp->setAccessible(true);
    $internalConfig = $configProp->getValue($client);

    expect($internalConfig->name)->toBe('FullClient');
    expect($internalConfig->version)->toBe('1.1');
    expect($internalConfig->capabilities)->toBe($caps);
    expect($internalConfig->logger)->toBe($logger);
    expect($internalConfig->cache)->toBe($cache);
    expect($internalConfig->definitionCacheTtl)->toBe(900);
    expect($internalConfig->eventDispatcher)->toBe($dispatcher);
    expect($internalConfig->loop)->toBe($loop);
});

it('builds client with client info', function () {
    $server = new ServerConfig(name: 's1', transport: TransportType::Stdio, command: 'c');

    // Arrange
    $client = Client::make()
        ->withClientInfo('TestClient', '1.0')
        ->withServerConfig($server)
        ->build();

    // Assert
    expect($client)->toBeInstanceOf(Client::class);
    $reflector = new ReflectionClass($client);
    $configProp = $reflector->getProperty('clientConfig');
    $configProp->setAccessible(true);
    $internalConfig = $configProp->getValue($client);

    expect($internalConfig->name)->toBe('TestClient');
    expect($internalConfig->version)->toBe('1.0');
});

it('throws exception if client name not provided', function () {
    Client::make()->build();
})->throws(ConfigurationException::class, 'Name must be provided using withName().');

it('throws exception if client version not provided', function () {
    Client::make()->withName('TestClient')->build();
})->throws(ConfigurationException::class, 'Version must be provided using withVersion().');
