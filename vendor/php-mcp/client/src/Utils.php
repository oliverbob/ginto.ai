<?php

declare(strict_types=1);

namespace PhpMcp\Client;

use PhpMcp\Client\Exception\TimeoutException;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Promise\Promise;
use React\Promise\PromiseInterface;

class Utils
{
    /**
     * Adds a timeout to a ReactPHP Promise.
     *
     * If the original promise doesn't settle within the specified timeout,
     * the promise returned by this function will be rejected with a TimeoutException.
     *
     * @template T
     *
     * @param  PromiseInterface<T>  $promise  The promise to apply the timeout to.
     * @param  float  $timeout  The timeout duration in seconds.
     * @param  LoopInterface  $loop  The event loop for scheduling the timer.
     * @param  string  $operationName  Optional name for the timeout exception message.
     * @return PromiseInterface<T> A new promise that resolves/rejects with the original, or rejects with TimeoutException.
     */
    public static function timeout(
        PromiseInterface $promise,
        float $timeout,
        LoopInterface $loop,
        string $operationName = 'Operation'
    ): PromiseInterface {
        $loop ??= Loop::get();
        $canceller = function () use (&$promise) {
            $promise->cancel();
            $promise = null;
        };

        return new Promise(function ($resolve, $reject) use ($loop, $promise, $timeout, $operationName) {
            $timer = null;
            $promise = $promise->then(function ($v) use (&$timer, $loop, $resolve) {
                if ($timer) {
                    $loop->cancelTimer($timer);
                }
                $timer = false;
                $resolve($v);
            }, function ($v) use (&$timer, $loop, $reject) {
                if ($timer) {
                    $loop->cancelTimer($timer);
                }
                $timer = false;
                $reject($v);
            });

            if ($timer === false) {
                return;
            }

            // start timeout timer which will cancel the input promise
            $timer = $loop->addTimer($timeout, function () use ($timeout, &$promise, $reject, $operationName) {
                $reject(new TimeoutException("{$operationName} timed out after {$timeout} seconds", $timeout));

                $promise->cancel();
                $promise = null;
            });
        }, $canceller);
    }
}
