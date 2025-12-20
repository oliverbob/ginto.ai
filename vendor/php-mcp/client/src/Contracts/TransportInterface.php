<?php

declare(strict_types=1);

namespace PhpMcp\Client\Contracts;

use Evenement\EventEmitterInterface;
use PhpMcp\Client\JsonRpc\Message;
use React\Promise\PromiseInterface;

/**
 * Interface for low-level MCP client transports.
 * Implementations handle the actual sending/receiving over stdio, http, etc.
 * They should emit 'message', 'error', and 'close' events.
 */
interface TransportInterface extends EventEmitterInterface
{
    /**
     * Establish the underlying connection (e.g., spawn process, open SSE connection).
     * MUST resolve the promise only when the transport is ready for the *initial*
     * MCP handshake message (e.g., process running, SSE endpoint URL received).
     *
     * @return PromiseInterface<void> Resolves on successful connection, rejects on failure.
     */
    public function connect(): PromiseInterface;

    /**
     * Send a JSON-RPC Message object over the transport.
     *
     * @param  Message  $message  The message to send.
     * @return PromiseInterface<void> Resolves when the message is successfully written/sent, rejects on error.
     */
    public function send(Message $message): PromiseInterface;

    /**
     * Close the transport connection gracefully.
     */
    public function close(): void;
}
