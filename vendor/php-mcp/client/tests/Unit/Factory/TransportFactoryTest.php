<?php

use PhpMcp\Client\ClientConfig;
use PhpMcp\Client\Enum\TransportType;
use PhpMcp\Client\Exception\ConfigurationException;
use PhpMcp\Client\Factory\TransportFactory;
use PhpMcp\Client\Model\Capabilities;
use PhpMcp\Client\ServerConfig;
use PhpMcp\Client\Transport\Http\HttpClientTransport;
use PhpMcp\Client\Transport\Stdio\StdioClientTransport;
use React\EventLoop\LoopInterface;

beforeEach(function () {
    $this->loop = Mockery::mock(LoopInterface::class);
    $this->name = 'TestClient';
    $this->version = '1.0';
    $this->clientCaps = Capabilities::forClient();
    $this->clientConfig = new ClientConfig($this->name, $this->version, $this->clientCaps, loop: $this->loop);
    $this->factory = new TransportFactory($this->clientConfig);
});

it('creates stdio transport for stdio config', function () {
    // Arrange
    $config = new ServerConfig(
        name: 'stdio_test',
        transport: TransportType::Stdio,
        command: 'php',
        args: ['server.php'],
        timeout: 10
    );

    // Act
    $transport = $this->factory->create($config);

    // Assert
    expect($transport)->toBeInstanceOf(StdioClientTransport::class);
});

it('creates http transport for http config', function () {
    // Arrange
    $config = new ServerConfig(
        name: 'http_test',
        transport: TransportType::Http,
        url: 'http://localhost:8080',
        timeout: 20
    );

    // Act
    $transport = $this->factory->create($config);

    // Assert
    expect($transport)->toBeInstanceOf(HttpClientTransport::class);
    $loggerProp = new ReflectionProperty($transport, 'logger');
    $loggerProp->setAccessible(true);
    expect($loggerProp->getValue($transport))->toBe($this->clientConfig->logger);
});

it('throws exception for invalid stdio config during creation', function () {
    // Arrange
    $config = new ServerConfig(
        name: 'invalid_stdio',
        transport: TransportType::Stdio,
        command: null, // Missing command
        timeout: 10
    );

    // Act & Assert
    $this->factory->create($config);
})->throws(ConfigurationException::class, "'command' property is required for the Stdio transport for server 'invalid_stdio'.");

it('throws exception for invalid http config during creation', function () {
    // Arrange
    $config = new ServerConfig(
        name: 'invalid_http',
        transport: TransportType::Http,
        url: null, // Missing url
        timeout: 10
    );

    // Act & Assert
    $this->factory->create($config);
})->throws(ConfigurationException::class, "The 'url' property is required for the Http transport for server 'invalid_http'.");
