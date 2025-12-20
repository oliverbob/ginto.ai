<?php

use PhpMcp\Client\Enum\TransportType;
use PhpMcp\Client\Exception\ConfigurationException;
use PhpMcp\Client\ServerConfig;

it('creates a valid stdio config', function () {
    // Arrange
    $name = 'my-stdio-server';
    $command = 'php'; // Command is now string
    $args = ['server.php', '--verbose']; // Args is array
    $timeout = 15.0;
    $cwd = '/app';
    $env = ['APP_ENV' => 'production'];

    // Act
    $config = new ServerConfig(
        name: $name,
        transport: TransportType::Stdio,
        timeout: $timeout,
        command: $command, // Pass command string
        args: $args,       // Pass args array
        workingDir: $cwd,
        env: $env
    );

    // Assert
    expect($config->name)->toBe($name);
    expect($config->transport)->toBe(TransportType::Stdio);
    expect($config->timeout)->toBe($timeout);
    expect($config->command)->toBe($command);
    expect($config->args)->toBe($args); // Assert args
    expect($config->workingDir)->toBe($cwd);
    expect($config->env)->toBe($env);
    expect($config->url)->toBeNull();
    expect($config->headers)->toBeNull();
    expect($config->sessionId)->toBeNull();
});

it('creates a valid stdio config with empty args', function () {
    // Arrange
    $name = 'stdio-no-args';
    $command = '/usr/bin/my_server';

    // Act
    $config = new ServerConfig(
        name: $name,
        transport: TransportType::Stdio,
        command: $command
        // args defaults to []
    );

    // Assert
    expect($config->command)->toBe($command);
    expect($config->args)->toBe([]);
});

// Test successful creation for Http
it('creates a valid http config', function () {
    // Arrange
    $name = 'my-http-server';
    $url = 'http://localhost:8080/mcp';
    $timeout = 45.0;
    $headers = ['Authorization' => 'Bearer token'];
    $sessionId = 'sess_123';

    // Act
    $config = new ServerConfig(
        name: $name,
        transport: TransportType::Http,
        timeout: $timeout,
        url: $url,
        headers: $headers,
        sessionId: $sessionId
    );

    // Assert
    expect($config->name)->toBe($name);
    expect($config->transport)->toBe(TransportType::Http);
    expect($config->timeout)->toBe($timeout);
    expect($config->url)->toBe($url);
    expect($config->headers)->toBe($headers);
    expect($config->sessionId)->toBe($sessionId);
    // Assert Stdio fields are null/default
    expect($config->command)->toBeNull();
    expect($config->args)->toBe([]); // Args defaults to empty array
    expect($config->workingDir)->toBeNull();
    expect($config->env)->toBeNull();
});

// Test default timeout
it('uses default timeout if not provided', function () {
    // Arrange & Act
    $config = new ServerConfig(
        name: 'default-timeout',
        transport: TransportType::Http,
        url: 'http://example.com'
    );

    // Assert
    expect($config->timeout)->toBe(30.0);
});

// Test validation failures

it('throws exception for negative timeout', function () {
    // Arrange & Act & Assert
    new ServerConfig(
        name: 'test',
        transport: TransportType::Stdio,
        command: 'cmd',
        timeout: -5
    );
})->throws(ConfigurationException::class, 'Server timeout must be positive');

it('throws exception for missing command in stdio config', function () {
    // Arrange & Act & Assert
    new ServerConfig(name: 'test', transport: TransportType::Stdio);
})->throws(ConfigurationException::class, "The 'command' property is required for the Stdio transport");

it('throws exception for url provided in stdio config', function () {
    // Arrange & Act & Assert
    new ServerConfig(name: 'test', transport: TransportType::Stdio, command: 'cmd', url: 'http://wrong.com');
})->throws(ConfigurationException::class, "The 'url' property is not applicable for the Stdio transport");

it('throws exception for missing url in http config', function () {
    // Arrange & Act & Assert
    new ServerConfig(name: 'test', transport: TransportType::Http);
})->throws(ConfigurationException::class, "The 'url' property is required for the Http transport");

it('throws exception for invalid url in http config', function () {
    // Arrange & Act & Assert
    new ServerConfig(name: 'test', transport: TransportType::Http, url: 'invalid-url');
})->throws(ConfigurationException::class, "The 'url' property must be a valid URL for server 'test'");

it('throws exception for command provided in http config', function () {
    // Arrange & Act & Assert
    new ServerConfig(name: 'test', transport: TransportType::Http, url: 'http://valid.com', command: 'cmd');
})->throws(ConfigurationException::class, "The 'command' property is not applicable for the Http transport");

it('creates valid stdio config from array', function () {
    // Arrange
    $name = 'stdio-from-array';
    $data = [
        'transport' => 'stdio',
        'command' => 'php',
        'args' => ['server.php', '-v'],
        'timeout' => 20,
        'workingDir' => '/tmp',
        'env' => ['CACHE_DIR' => '/tmp/cache'],
    ];

    // Act
    $config = ServerConfig::fromArray($name, $data);

    // Assert
    expect($config->name)->toBe($name);
    expect($config->transport)->toBe(TransportType::Stdio);
    expect($config->timeout)->toBe(20.0);
    expect($config->command)->toBe('php');
    expect($config->args)->toBe(['server.php', '-v']);
    expect($config->workingDir)->toBe('/tmp');
    expect($config->env)->toBe(['CACHE_DIR' => '/tmp/cache']);
});

it('creates valid http config from array', function () {
    // Arrange
    $name = 'http-from-array';
    $data = [
        'transport' => 'http',
        'url' => 'https://mcp.example.com/api',
        'timeout' => 10.5,
        'headers' => ['X-Api-Key' => 'abc'],
        'sessionId' => 'sess_abc',
    ];

    // Act
    $config = ServerConfig::fromArray($name, $data);

    // Assert
    expect($config->name)->toBe($name);
    expect($config->transport)->toBe(TransportType::Http);
    expect($config->timeout)->toBe(10.5);
    expect($config->url)->toBe('https://mcp.example.com/api');
    expect($config->headers)->toBe(['X-Api-Key' => 'abc']);
    expect($config->sessionId)->toBe('sess_abc');
});

it('infers stdio transport type from array', function () {
    $config = ServerConfig::fromArray('infer-stdio', ['command' => 'go', 'args' => ['run', 'main.go']]);
    expect($config->transport)->toBe(TransportType::Stdio);
});

it('infers http transport type from array', function () {
    $config = ServerConfig::fromArray('infer-http', ['url' => 'http://api.test']);
    expect($config->transport)->toBe(TransportType::Http);
});

it('throws from array if transport ambiguous', function () {
    ServerConfig::fromArray('ambiguous', []);
})->throws(ConfigurationException::class, 'Missing or ambiguous transport type');

it('throws from array if transport invalid', function () {
    ServerConfig::fromArray('invalid-transport', ['transport' => 'websocket']);
})->throws(ConfigurationException::class, "Invalid transport type 'websocket'");

it('throws from array if stdio command missing', function () {
    ServerConfig::fromArray('stdio-no-cmd', ['transport' => 'stdio']);
})->throws(ConfigurationException::class, "Missing or invalid 'command'");

it('throws from array if stdio command not string', function () {
    ServerConfig::fromArray('stdio-bad-cmd', ['transport' => 'stdio', 'command' => ['a']]);
})->throws(ConfigurationException::class, "Missing or invalid 'command'");

it('throws from array if stdio args not array', function () {
    ServerConfig::fromArray('stdio-bad-args', ['transport' => 'stdio', 'command' => 'c', 'args' => 'not-an-array']);
})->throws(ConfigurationException::class, "Invalid 'args' format");

it('throws from array if stdio args not string list', function () {
    ServerConfig::fromArray('stdio-bad-args-type', ['transport' => 'stdio', 'command' => 'c', 'args' => ['a', 123]]);
})->throws(ConfigurationException::class, "Invalid 'args' format for stdio server 'stdio-bad-args-type'. Expected array of strings");

it('throws from array if http url missing', function () {
    ServerConfig::fromArray('http-no-url', ['transport' => 'http']);
})->throws(ConfigurationException::class, "Missing or invalid 'url'");

it('throws from array if http headers not map', function () {
    ServerConfig::fromArray('http-bad-headers', ['transport' => 'http', 'url' => 'http://a', 'headers' => ['a', 'b']]);
})->throws(ConfigurationException::class, "Invalid 'headers' format");
