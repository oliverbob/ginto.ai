<?php

declare(strict_types=1);

namespace PhpMcp\Client\Transport\Stdio;

use Evenement\EventEmitterTrait;
use JsonException;
use PhpMcp\Client\Contracts\TransportInterface;
use PhpMcp\Client\Exception\TransportException;
use PhpMcp\Client\JsonRpc\Message;
use PhpMcp\Client\JsonRpc\Notification;
use PhpMcp\Client\JsonRpc\Request;
use PhpMcp\Client\JsonRpc\Response;
use React\ChildProcess\Process;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use React\Stream\ReadableStreamInterface;
use React\Stream\WritableStreamInterface;
use Throwable;

class StdioClientTransport implements TransportInterface
{
    use EventEmitterTrait;

    protected ?Process $process = null;

    protected ?ReadableStreamInterface $stdout = null;

    protected ?WritableStreamInterface $stdin = null;

    protected string $buffer = '';

    protected bool $closing = false;

    protected ?PromiseInterface $connectPromise = null;

    protected bool $connectPromiseSettled = false;

    public function __construct(
        protected readonly string $command,
        protected readonly array $args,
        protected readonly LoopInterface $loop,
        protected readonly ?string $cwd = null,
        protected readonly ?array $env = null
    ) {}

    public function connect(): PromiseInterface
    {
        if ($this->connectPromise !== null) {
            return $this->connectPromise;
        }

        $deferred = new Deferred(function ($_, $reject) {
            $this->close();
            $reject(new TransportException('Connection attempt cancelled.'));
        });

        $this->connectPromise = $deferred->promise();
        $this->connectPromiseSettled = false;
        $this->closing = false;

        $this->connectPromise->then(
            fn () => $this->connectPromiseSettled = true,
            fn () => $this->connectPromiseSettled = true
        );

        try {
            $this->process = $this->createProcess();
            $this->process->start($this->loop);

            if (! $this->process->stdin?->isWritable() || ! $this->process->stdout?->isReadable() || ! $this->process->stderr?->isReadable()) {
                throw new TransportException('Failed to get valid stdio/stderr streams from process.');
            }

            $this->stdin = $this->process->stdin;
            $this->stdout = $this->process->stdout;
            $stderr = $this->process->stderr;

            $this->stdout->on('data', $this->handleData(...));
            $stderr->on('data', $this->handleStderr(...));
            $this->process->on('exit', $this->handleExit(...));
            $this->stdout->on('error', $this->handleStreamError(...));
            $this->stdin->on('error', $this->handleStreamError(...));
            $this->stdout->on('close', $this->handleStreamClose(...));

            $deferred->resolve(null);

        } catch (Throwable $e) {
            if (! $this->connectPromiseSettled) {
                $deferred->reject(new TransportException("Failed to start stdio process: {$e->getMessage()}", 0, $e));
            }

        }

        return $this->connectPromise;
    }

    protected function createProcess(): Process
    {
        $commandParts = [];
        $commandParts[] = escapeshellarg($this->command);

        foreach ($this->args as $arg) {
            $commandParts[] = escapeshellarg((string) $arg);
        }

        $commandString = implode(' ', $commandParts);

        return new Process($commandString, $this->cwd, $this->env);
    }

    protected function handleData(string $chunk): void
    {
        $this->buffer .= $chunk;

        while (($pos = strpos($this->buffer, "\n")) !== false) {
            $line = trim(substr($this->buffer, 0, $pos));
            $this->buffer = substr($this->buffer, $pos + 1);

            if ($line === '') {
                continue;
            }

            try {
                $messageData = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                $message = $this->parseMessageData($messageData);

                if ($message) {
                    $this->emit('message', [$message]);
                } else {
                    $this->emit('error', [new TransportException("Unrecognized message structure: {$line}")]);
                }
            } catch (JsonException $e) {
                $this->emit('error', [new TransportException("Failed to decode message from server: '{$line}'. {$e->getMessage()}", 0, $e)]);
            } catch (Throwable $e) {
                $this->emit('error', [new TransportException("Error parsing message: {$e->getMessage()}", 0, $e)]);
            }
        }
    }

    protected function parseMessageData(array $data): ?Message
    {
        if (! isset($data['jsonrpc']) || $data['jsonrpc'] !== '2.0') {
            return null;
        }

        if (isset($data['method'])) {
            if (isset($data['id'])) {
                return Request::fromArray($data);
            } else {
                return Notification::fromArray($data);
            }
        } elseif (isset($data['id'])) {
            return Response::fromArray($data);
        }

        return null;
    }

    protected function handleStderr(string $chunk): void
    {
        $this->emit('stderr', [$chunk]);
    }

    protected function handleExit(?int $exitCode, ?int $termSignal): void
    {
        if ($this->closing) {
            return;
        }

        $reason = $termSignal !== null
            ? "Process terminated by signal {$termSignal}"
            : "Process exited with code {$exitCode}";

        $exception = new TransportException($reason, $exitCode ?? -1);

        if ($this->connectPromise !== null && ! $this->connectPromiseSettled) {
            // Process exited before connection fully established
            $this->emit('error', [$exception]);
            $this->emit('close', [$reason]);
            $this->cleanup();
        } else {
            // Process exited unexpectedly after connection was established
            $this->emit('error', [$exception]);
            $this->emit('close', [$reason]);
            $this->cleanup();
        }
    }

    protected function handleStreamError(Throwable $error): void
    {
        if ($this->closing) {
            return;
        }

        $exception = new TransportException("Stream error: {$error->getMessage()}", 0, $error);

        // Check if connectPromise exists and *hasn't been settled yet*
        if ($this->connectPromise !== null && ! $this->connectPromiseSettled) {
            $this->emit('error', [$exception]);

            $this->cleanup();
        } else {
            $this->emit('error', [$exception]);
        }

    }

    protected function handleStreamClose(): void
    {
        // If stdout closes unexpectedly, assume connection is lost
        if (! $this->closing && $this->process?->isRunning()) {
            $reason = 'Server stdout stream closed unexpectedly.';
            $this->emit('error', [new TransportException($reason)]);
            $this->emit('close', [$reason]);
            $this->close();
        }
    }

    public function send(Message $message): PromiseInterface
    {
        $deferred = new Deferred;
        $isDrainListenerAttached = false;

        if ($this->closing || ! $this->stdin || ! $this->stdin->isWritable()) {
            $deferred->reject(new TransportException('Stdio stdin stream is not writable or transport is closing.'));

            return $deferred->promise();
        }

        $drainListener = function () use ($deferred, &$isDrainListenerAttached) {
            $deferred->resolve(null);
            $isDrainListenerAttached = false;
        };

        $drainTimeout = 5.0;
        $drainTimer = $this->loop->addTimer($drainTimeout, function () use ($deferred, $drainListener, &$isDrainListenerAttached, $drainTimeout) {
            if ($isDrainListenerAttached) {
                $this->stdin?->removeListener('drain', $drainListener);
                $deferred->reject(new TransportException("Timeout waiting for stdin drain event after {$drainTimeout} seconds."));
                $isDrainListenerAttached = false;
            }
        });

        $deferred->promise()->finally(function () use ($drainTimer, $drainListener, &$isDrainListenerAttached) {
            $this->loop->cancelTimer($drainTimer);

            if ($isDrainListenerAttached) {
                $this->stdin?->removeListener('drain', $drainListener);
                $isDrainListenerAttached = false;
            }
        });

        try {
            $json = json_encode($message->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $written = $this->stdin->write($json."\n");

            if ($written) {
                $deferred->resolve(null);
            } else {
                $this->stdin->once('drain', $drainListener);
                $isDrainListenerAttached = true;
            }
        } catch (JsonException $e) {
            $deferred->reject(new TransportException("Failed to encode message to JSON: {$e->getMessage()}", 0, $e));
        } catch (Throwable $e) {
            $deferred->reject(new TransportException("Error writing to stdio stdin: {$e->getMessage()}", 0, $e));
        }

        return $deferred->promise();
    }

    public function close(): void
    {
        if ($this->closing || ! $this->process) {
            return;
        }
        $this->closing = true;

        // Attempt graceful shutdown
        if ($this->process->isRunning()) {
            $this->stdin?->end();
            $this->process->terminate(SIGTERM);

            $killTimer = $this->loop->addTimer(2, function () {
                if ($this->process && $this->process->isRunning()) {
                    $this->process->terminate(SIGKILL);
                    $this->emit('error', [new TransportException('Process did not exit gracefully, sent SIGKILL.')]);
                }
            });

            $this->process->on('exit', fn () => $this->loop->cancelTimer($killTimer));

        }

        $this->emit('close', ['Client initiated close.']);
        $this->cleanup();
    }

    private function cleanup(): void
    {
        $this->removeAllListeners();
        $this->stdin?->close();
        $this->stdout?->close();
        $this->process = null;
        $this->stdin = null;
        $this->stdout = null;
        $this->connectPromise = null;
        $this->connectPromiseSettled = false;
        $this->closing = true;
    }
}
