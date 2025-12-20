<?php

use PhpMcp\Client\Exception\ConfigurationException;
use PhpMcp\Client\Exception\ConnectionException;
use PhpMcp\Client\Exception\McpClientException;
use PhpMcp\Client\Exception\RequestException;
use PhpMcp\Client\JsonRpc\Error as JsonRpcError;

it('can instantiate basic exceptions', function () {
    $base = new McpClientException('Base error');
    $config = new ConfigurationException('Config error');
    $conn = new ConnectionException('Conn error');

    expect($base)->toBeInstanceOf(RuntimeException::class);
    expect($config)->toBeInstanceOf(McpClientException::class);
    expect($conn)->toBeInstanceOf(McpClientException::class);
});

it('can instantiate request exception with rpc error', function () {
    $rpcError = new JsonRpcError(-32602, 'Invalid Params', ['details' => 'missing name']);
    $reqEx = new RequestException('Server error', $rpcError);

    expect($reqEx)->toBeInstanceOf(McpClientException::class);
    expect($reqEx->getMessage())->toContain('Server error');
    expect($reqEx->getRpcError())->toBe($rpcError);
    expect($reqEx->getRpcError()->code)->toBe(-32602);
    expect($reqEx->getRpcError()->data)->toBe(['details' => 'missing name']);
});
