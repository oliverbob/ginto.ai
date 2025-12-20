<?php

use PhpMcp\Client\Enum\ConnectionStatus;
use PhpMcp\Client\Enum\TransportType;

it('has correct transport type values', function () {
    expect(TransportType::Stdio->value)->toBe('stdio');
    expect(TransportType::Http->value)->toBe('http');
});

it('has correct connection status values', function () {
    expect(ConnectionStatus::Disconnected->value)->toBe('disconnected');
    expect(ConnectionStatus::Connecting->value)->toBe('connecting');
    expect(ConnectionStatus::Handshaking->value)->toBe('handshaking');
    expect(ConnectionStatus::Ready->value)->toBe('ready');
    expect(ConnectionStatus::Closing->value)->toBe('closing');
    expect(ConnectionStatus::Closed->value)->toBe('closed');
    expect(ConnectionStatus::Error->value)->toBe('error');
});
