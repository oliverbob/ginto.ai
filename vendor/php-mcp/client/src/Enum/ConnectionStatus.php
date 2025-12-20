<?php

declare(strict_types=1);

namespace PhpMcp\Client\Enum;

/**
 * Represents the connection state of a ServerConnection.
 */
enum ConnectionStatus: string
{
    case Disconnected = 'disconnected'; // Initial state, or after clean close
    case Connecting = 'connecting';     // Transport connect() called, process starting/HTTP connecting
    case Handshaking = 'handshaking';   // Connected, MCP initialize sequence in progress
    case Ready = 'ready';               // Handshake complete, ready for requests
    case Closing = 'closing';         // Disconnect initiated
    case Closed = 'closed';           // Transport confirmed closed (process exited, SSE closed)
    case Error = 'error';             // Unrecoverable error state
}
