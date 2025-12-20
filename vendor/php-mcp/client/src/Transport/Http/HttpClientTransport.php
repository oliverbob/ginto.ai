<?php

declare(strict_types=1);

namespace PhpMcp\Client\Transport\Http;

use Evenement\EventEmitterTrait;
use JsonException;
use PhpMcp\Client\Contracts\TransportInterface;
use PhpMcp\Client\Exception\TransportException;
use PhpMcp\Client\JsonRpc\Message;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use React\EventLoop\LoopInterface;
use React\Http\Browser;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use React\Socket\Connector;
use React\Stream\ReadableStreamInterface;
use Throwable;

class HttpClientTransport implements LoggerAwareInterface, TransportInterface
{
    use EventEmitterTrait;
    use LoggerAwareTrait;

    protected Browser $httpClient;

    protected ?string $postEndpointUrl = null;

    protected ?string $sessionId = null;

    protected bool $closing = false;

    protected ?PromiseInterface $connectPromise = null;

    protected bool $connectPromiseSettled = false;

    protected ?ReadableStreamInterface $sseStream = null;

    protected ?Deferred $connectRequestDeferred = null;

    public function __construct(
        protected readonly string $url,
        protected readonly LoopInterface $loop,
        protected readonly ?array $headers = null,
        ?string $sessionId = null,
        ?Browser $httpClient = null,
    ) {
        $this->httpClient = $httpClient ?? new Browser(new Connector($this->loop), $this->loop);
        $this->sessionId = $sessionId;
        $this->logger = new NullLogger;
    }

    public function connect(): PromiseInterface
    {
        if ($this->connectPromise !== null) {
            return $this->connectPromise;
        }

        $this->connectRequestDeferred = new Deferred(function ($_, $reject) {
            $this->close();
            $reject(new TransportException('Connection attempt cancelled.'));
        });

        $this->connectPromise = $this->connectRequestDeferred->promise();
        $this->connectPromiseSettled = false;
        $this->closing = false;

        $this->connectPromise->then(
            fn () => $this->connectPromiseSettled = true,
            fn () => $this->connectPromiseSettled = true
        );

        $headers = $this->headers ?? [];
        if ($this->sessionId) {
            $headers['Mcp-Session-Id'] = $this->sessionId;
        }

        $this->logger->debug('SSE: Connecting...', ['url' => $this->url]);
        $requestPromise = $this->httpClient->requestStreaming('GET', $this->url, $headers);

        $requestPromise->then(
            function (PsrResponseInterface $response) {
                if ($this->connectPromiseSettled) {
                    return;
                }

                $statusCode = $response->getStatusCode();
                $this->logger->debug('SSE: Connection established', ['status' => $statusCode]);

                if ($statusCode !== 200) {
                    $body = (string) $response->getBody();
                    $this->connectRequestDeferred->reject(new TransportException("SSE connection failed: Status {$statusCode} - ".($body ?: '(no body)')));
                    $this->connectRequestDeferred = null;
                    $this->cleanup();

                    return;
                }

                if (! str_contains(strtolower($response->getHeaderLine('Content-Type')), 'text/event-stream')) {
                    $this->connectRequestDeferred->reject(new TransportException("Invalid SSE response Content-Type: {$response->getHeaderLine('Content-Type')}"));
                    $this->connectRequestDeferred = null;
                    $this->cleanup();

                    return;
                }

                if (! $this->sessionId && $response->hasHeader('Mcp-Session-Id')) {
                    $this->sessionId = $response->getHeaderLine('Mcp-Session-Id');
                    $this->logger->info('SSE: Received session ID from server', ['session_id' => $this->sessionId]);
                    $this->emit('session_id_received', [$this->sessionId]);
                }

                $body = $response->getBody();
                if ($body instanceof ReadableStreamInterface) {
                    $this->sseStream = $body;
                } else {
                    $this->sseStream = new Psr7StreamAdapter($body, $this->loop);
                }

                $buffer = '';

                $this->sseStream->on('data', function ($chunk) use (&$buffer) {
                    $buffer .= $chunk;
                    while (($pos = strpos($buffer, "\n\n")) !== false) {
                        $eventBlock = substr($buffer, 0, $pos);
                        $buffer = substr($buffer, $pos + 2);

                        $event = 'message';
                        $data = '';
                        $id = null;

                        foreach (explode("\n", $eventBlock) as $line) {
                            if (str_starts_with($line, 'event:')) {
                                $event = trim(substr($line, 6));
                            } elseif (str_starts_with($line, 'data:')) {
                                // TODO: Handle multi-line data
                                $data .= trim(substr($line, 5));
                            } elseif (str_starts_with($line, 'id:')) {
                                $id = trim(substr($line, 3));
                            }
                        }

                        $this->handleSseEvent($event, $data, $id);
                    }
                });

                $this->sseStream->on('error', function (Throwable $error) {
                    if ($this->closing) {
                        return;
                    }
                    $this->logger->error('SSE: Stream error', ['error' => $error->getMessage()]);
                    $this->emit('error', [new TransportException("SSE stream error: {$error->getMessage()}", 0, $error)]);
                    $this->close();
                });

                $this->sseStream->on('close', function () {
                    if ($this->closing) {
                        return;
                    }
                    $this->logger->info('SSE: Stream closed by server.');
                    $reason = 'SSE stream closed by server.';
                    $this->emit('close', [$reason]);
                    $this->cleanup();
                });

                // Don't resolve deferred here yet, wait for 'endpoint' event

            },
            function (Throwable $error) {
                // Handle connection error (DNS, TCP, TLS etc.)
                $this->logger->error('SSE: Connection failed', ['error' => $error->getMessage()]);
                if (! $this->connectPromiseSettled) {
                    $this->connectRequestDeferred->reject(new TransportException('HTTP connection failed: '.$error->getMessage(), 0, $error));
                    $this->connectRequestDeferred = null;
                }
                $this->cleanup();
            }
        );

        return $this->connectPromise;
    }

    public function handleSseEvent(string $event, string $data, ?string $id): void
    {
        $this->logger->debug('SSE: Received event', ['event' => $event, 'id' => $id, 'data' => $data]);

        if ($this->closing) {
            return;
        }

        switch ($event) {
            case 'endpoint':
                try {
                    $resolvedUrl = $this->resolveEndpointUrl($this->url, $data);
                    if ($resolvedUrl === null) {
                        throw new \InvalidArgumentException("Failed to resolve relative path '{$data}' against base URL '{$this->url}'");
                    }

                    $this->postEndpointUrl = $resolvedUrl;

                    // Now we are truly ready for the MCP handshake
                    if ($this->connectPromise && ! $this->connectPromiseSettled) {
                        $this->connectRequestDeferred->resolve(null);
                        $this->connectRequestDeferred = null;
                    }
                } catch (\Throwable $e) {
                    $this->logger->error('SSE: Failed to resolve endpoint URL', ['error' => $e->getMessage(), 'base_url' => $this->url, 'relative_path' => $data]);
                    $this->emit('error', [new TransportException("Failed to resolve endpoint URL: {$e->getMessage()}", 0, $e)]);

                    if ($this->connectRequestDeferred && ! $this->connectPromiseSettled) {
                        $this->connectRequestDeferred->reject(new TransportException("Failed to resolve endpoint URL: {$e->getMessage()}", 0, $e));
                        $this->connectRequestDeferred = null;
                    }

                    $this->close();
                }
                break;

            case 'message':
                try {
                    $messageData = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
                    $message = $this->parseMessageData($messageData); // Use same parser as Stdio
                    if ($message) {
                        $this->emit('message', [$message]);
                    } else {
                        $this->logger->warning('SSE: Received unrecognized message structure', ['data' => $data]);
                    }
                } catch (JsonException $e) {
                    $this->emit('error', [new TransportException("SSE: Failed to decode JSON: {$e->getMessage()}", 0, $e)]);
                } catch (Throwable $e) {
                    $this->emit('error', [new TransportException("SSE: Error parsing message: {$e->getMessage()}", 0, $e)]);
                }
                break;

            case 'error': // Some servers might send explicit error events
                $this->logger->error('SSE: Received error event from server', ['data' => $data]);
                $this->emit('error', [new TransportException('Received error event from server: '.$data)]);
                break;

            case 'ping': // Handle keep-alive pings if necessary
                $this->logger->debug('SSE: Received ping event');
                break;

                // Ignore other events for now
        }
    }

    // Copied from StdioTransport - refactor to a trait or base class?
    private function parseMessageData(array $data): ?Message
    {
        // ... same implementation as in StdioClientTransport ...
        if (! isset($data['jsonrpc']) || $data['jsonrpc'] !== '2.0') {
            return null;
        }
        // ... rest of parsing logic ...
        // TEMPORARY Placeholder:
        if (isset($data['method'])) {
            return isset($data['id']) ? \PhpMcp\Client\JsonRpc\Request::fromArray($data) : \PhpMcp\Client\JsonRpc\Notification::fromArray($data);
        } elseif (isset($data['id'])) {
            return \PhpMcp\Client\JsonRpc\Response::fromArray($data);
        }

        return null;
    }

    /**
     * Resolves the relative endpoint URL against the base URL.
     * Does not perform full RFC3986 normalization (e.g., '../' handling).
     */
    private function resolveEndpointUrl(string $base, string $relative): ?string
    {
        if (str_contains($relative, '://') || str_starts_with($relative, '//')) {
            return $relative;
        }

        $baseParts = parse_url($base);
        if ($baseParts === false) {
            return null; // Base URL is invalid
        }

        // Build the authority part (scheme://[user:pass@]host:port)
        $authority = '';
        if (isset($baseParts['scheme'])) {
            $authority .= $baseParts['scheme'].':';
        }
        $authority .= '//';
        if (isset($baseParts['user'])) {
            $authority .= $baseParts['user'];
            if (isset($baseParts['pass'])) {
                $authority .= ':'.$baseParts['pass'];
            }
            $authority .= '@';
        }
        if (isset($baseParts['host'])) {
            $authority .= $baseParts['host'];
        }
        if (isset($baseParts['port'])) {
            $authority .= ':'.$baseParts['port'];
        }

        if (str_starts_with($relative, '/')) {
            // Relative path starts with '/', treat as relative to authority
            return $authority.$relative;
        } else {
            // Relative path does not start with '/', resolve relative to base path's directory
            $basePath = $baseParts['path'] ?? '/';

            // Find the last '/' to get the directory
            $lastSlashPos = strrpos($basePath, '/');
            if ($lastSlashPos === false) {
                // Base path is just 'filename' or empty, directory is '/'
                $baseDir = '/';
            } elseif ($lastSlashPos === 0) {
                // Base path is '/filename', directory is '/'
                $baseDir = '/';
            } else {
                // Base path is '/path/to/filename', directory is '/path/to/'
                $baseDir = substr($basePath, 0, $lastSlashPos + 1); // Include trailing slash
            }

            // Combine directory and relative path
            return $authority.$baseDir.$relative;
        }
    }

    public function send(Message $message): PromiseInterface
    {
        if ($this->closing) {
            return \React\Promise\reject(new TransportException('Transport is closing.'));
        }
        if ($this->postEndpointUrl === null) {
            // Should not happen if connect() succeeded, but good check
            return \React\Promise\reject(new TransportException('Cannot send message: POST endpoint not received yet.'));
        }

        try {
            $json = json_encode($message->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $headers = $this->headers ?? [];
            $headers['Content-Type'] = 'application/json';
            if ($this->sessionId) {
                $headers['Mcp-Session-Id'] = $this->sessionId;
            }

            $this->logger->debug('HTTP: Sending POST request', ['url' => $this->postEndpointUrl, 'id' => $message instanceof \PhpMcp\Client\JsonRpc\Request ? $message->id : '(notification)']);

            // Send the POST request, ignore the response body (usually 202)
            // The real MCP Response comes via SSE
            return $this->httpClient->post($this->postEndpointUrl, $headers, $json)
                ->then(
                    function (PsrResponseInterface $response) {
                        $statusCode = $response->getStatusCode();
                        if ($statusCode < 200 || $statusCode >= 300) {
                            $body = (string) $response->getBody();
                            $this->logger->warning('HTTP: POST request returned non-2xx status', ['status' => $statusCode, 'body' => $body]);
                            // Decide if this should reject the send promise
                            // throw new TransportException("POST request failed with status {$statusCode}");
                        }
                        // Resolve void promise on success
                    },
                    function (Throwable $error) {
                        $this->logger->error('HTTP: POST request failed', ['error' => $error->getMessage()]);
                        throw new TransportException("Failed to send POST request: {$error->getMessage()}", 0, $error);
                    }
                );

        } catch (JsonException $e) {
            return \React\Promise\reject(new TransportException("Failed to encode message to JSON: {$e->getMessage()}", 0, $e));
        } catch (Throwable $e) {
            return \React\Promise\reject(new TransportException("Error preparing POST request: {$e->getMessage()}", 0, $e));
        }
    }

    public function close(): void
    {
        if ($this->closing) {
            return;
        }
        $this->closing = true;
        $reason = 'Client initiated close.';

        $this->logger->info('HTTP: Closing connection.', ['session_id' => $this->sessionId]);

        // Cancel pending connection attempt if any
        $this->connectRequestDeferred?->reject(new TransportException($reason));

        // Close the SSE stream body if it exists
        $this->sseStream?->close();

        $this->emit('close', [$reason]);
        $this->cleanup();

    }

    private function cleanup(): void
    {
        $this->removeAllListeners();
        $this->postEndpointUrl = null;
        $this->connectRequestDeferred = null;
        $this->connectPromise = null;
        $this->connectPromiseSettled = false;
        $this->sseStream = null;
        $this->sessionId = null;
        $this->closing = true;
    }
}
