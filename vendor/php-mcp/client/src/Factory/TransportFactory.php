<?php

declare(strict_types=1);

namespace PhpMcp\Client\Factory;

use PhpMcp\Client\ClientConfig;
use PhpMcp\Client\Contracts\TransportInterface;
use PhpMcp\Client\Enum\TransportType;
use PhpMcp\Client\ServerConfig;
use PhpMcp\Client\Transport\Http\HttpClientTransport;
use PhpMcp\Client\Transport\Stdio\StdioClientTransport;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;

class TransportFactory
{
    private readonly LoopInterface $loop;

    private readonly LoggerInterface $logger;

    public function __construct(private readonly ClientConfig $clientConfig)
    {
        $this->loop = $clientConfig->loop;
        $this->logger = $clientConfig->logger;
    }

    public function create(ServerConfig $config): TransportInterface
    {
        $transport = match ($config->transport) {
            TransportType::Stdio => $this->createStdioTransport($config),
            TransportType::Http => $this->createHttpTransport($config),
        };

        if ($transport instanceof LoggerAwareInterface) {
            $transport->setLogger($this->logger);
        }

        return $transport;
    }

    private function createStdioTransport(ServerConfig $config): TransportInterface
    {
        return new StdioClientTransport(
            $config->command,
            $config->args,
            $this->loop,
            $config->workingDir,
            $config->env
        );
    }

    private function createHttpTransport(ServerConfig $config): TransportInterface
    {
        return new HttpClientTransport(
            $config->url,
            $this->loop,
            $config->headers,
            $config->sessionId
        );
    }
}
