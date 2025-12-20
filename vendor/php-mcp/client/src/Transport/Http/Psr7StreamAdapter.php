<?php

declare(strict_types=1);

namespace PhpMcp\Client\Transport\Http;

use Evenement\EventEmitter;
use Psr\Http\Message\StreamInterface as Psr7StreamInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use React\Stream\ReadableStreamInterface;
use React\Stream\Util;

/**
 * Adapts a PSR-7 StreamInterface to a ReactPHP ReadableStreamInterface
 * by periodically reading from the PSR-7 stream using loop timers.
 *
 * NOTE: This is a basic implementation and might not be the most performant
 * way to handle streaming data compared to native async streams. It introduces
 * latency based on the polling interval.
 *
 * @internal
 */
class Psr7StreamAdapter extends EventEmitter implements ReadableStreamInterface
{
    private bool $closed = false;

    private ?TimerInterface $timer = null;

    private float $readInterval;

    private int $chunkSize;

    public function __construct(
        private Psr7StreamInterface $psrStream,
        private LoopInterface $loop,
        float $readInterval = 0.01, // Check every 10ms
        int $chunkSize = 8192 // Read up to 8KB at a time
    ) {
        if (! $psrStream->isReadable()) {
            // PSR stream must be readable
            $this->close(); // Mark as closed immediately
            throw new \InvalidArgumentException('PSR-7 stream provided is not readable.');
        }
        $this->readInterval = $readInterval;
        $this->chunkSize = $chunkSize;
        $this->resume(); // Start reading immediately
    }

    public function isReadable(): bool
    {
        return ! $this->closed && $this->psrStream->isReadable();
    }

    public function pause(): void
    {
        if ($this->timer) {
            $this->loop->cancelTimer($this->timer);
            $this->timer = null;
        }
    }

    public function resume(): void
    {
        if ($this->closed || $this->timer) {
            return; // Already running or closed
        }

        // Use a periodic timer to poll the PSR-7 stream
        $this->timer = $this->loop->addPeriodicTimer($this->readInterval, function () {
            if ($this->closed || ! $this->psrStream->isReadable()) {
                $this->close(); // Ensure closed state if stream becomes unreadable

                return;
            }

            try {
                // Check EOF *before* reading to avoid blocking read on empty stream end
                if ($this->psrStream->eof()) {
                    $this->emit('end');
                    $this->close();

                    return;
                }

                // Read a chunk - PSR-7 read() can block if no data available but not EOF
                // This is the main limitation of adapting sync streams.
                $data = $this->psrStream->read($this->chunkSize);

                if ($data === '') {
                    // If read returns empty string, check EOF again. If EOF, we're done.
                    // If not EOF, it might mean stream is waiting, keep polling.
                    if ($this->psrStream->eof()) {
                        $this->emit('end');
                        $this->close();
                    }
                    // Otherwise, loop continues polling
                } else {
                    // Emit the data chunk
                    $this->emit('data', [$data]);
                }
            } catch (\Throwable $e) {
                $this->emit('error', [$e]);
                $this->close();
            }
        });
    }

    public function pipe(\React\Stream\WritableStreamInterface $dest, array $options = []): \React\Stream\WritableStreamInterface
    {
        // Use ReactPHP's utility to handle piping events correctly
        Util::pipe($this, $dest, $options);

        return $dest;
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }
        $this->closed = true;

        if ($this->timer) {
            $this->loop->cancelTimer($this->timer);
            $this->timer = null;
        }

        // Close PSR-7 stream if it has a close method
        if (method_exists($this->psrStream, 'close')) {
            $this->psrStream->close();
        }

        $this->emit('close');
        $this->removeAllListeners();
    }
}
