<?php

use PhpMcp\Client\ClientConfig;
use PhpMcp\Client\Factory\MessageIdGenerator;
use PhpMcp\Client\Model\Capabilities;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;
use React\EventLoop\LoopInterface;

beforeEach(function () {
    $this->name = 'TestClient';
    $this->version = '1.0';
    $this->capabilities = Capabilities::forClient();
    $this->loop = Mockery::mock(LoopInterface::class);
});

it('creates config with required properties and defaults', function () {
    // Arrange & Act
    $config = new ClientConfig(
        name: $this->name,
        version: $this->version,
        capabilities: $this->capabilities,
        loop: $this->loop
    );

    // Assert
    expect($config->name)->toBe($this->name);
    expect($config->version)->toBe($this->version);
    expect($config->capabilities)->toBe($this->capabilities);
    expect($config->loop)->toBe($this->loop);
    // Assert defaults
    expect($config->logger)->toBeInstanceOf(NullLogger::class);
    expect($config->cache)->toBeNull();
    expect($config->eventDispatcher)->toBeNull();
    expect($config->definitionCacheTtl)->toBe(3600);
    expect($config->idGenerator)->toBeInstanceOf(MessageIdGenerator::class);
});

it('creates config with all optional properties set', function () {
    // Arrange
    $logger = Mockery::mock(LoggerInterface::class);
    $cache = Mockery::mock(CacheInterface::class);
    $dispatcher = Mockery::mock(EventDispatcherInterface::class);
    $idGenerator = new MessageIdGenerator('custom-');
    $ttl = 600;

    // Act
    $config = new ClientConfig(
        name: $this->name,
        version: $this->version,
        capabilities: $this->capabilities,
        logger: $logger,
        cache: $cache,
        eventDispatcher: $dispatcher,
        loop: $this->loop,
        definitionCacheTtl: $ttl,
        idGenerator: $idGenerator,
    );

    // Assert
    expect($config->logger)->toBe($logger);
    expect($config->cache)->toBe($cache);
    expect($config->eventDispatcher)->toBe($dispatcher);
    expect($config->definitionCacheTtl)->toBe($ttl);
    expect($config->idGenerator)->toBe($idGenerator);
    expect($config->idGenerator->generate())->toStartWith('custom-');
});

it('uses default loop if not provided', function () {
    // Arrange & Act - don't pass loop to constructor
    $config = new ClientConfig(
        name: $this->name,
        version: $this->version,
        capabilities: $this->capabilities
    );

    // Assert
    expect($config->loop)->toBeInstanceOf(LoopInterface::class);
});
