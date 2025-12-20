<?php

declare(strict_types=1);

namespace PhpMcp\Client;

use Deprecated;
use PhpMcp\Client\Exception\ConfigurationException;
use PhpMcp\Client\Factory\MessageIdGenerator;
use PhpMcp\Client\Factory\TransportFactory; // Added use
use PhpMcp\Client\Model\Capabilities as ClientCapabilities;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;

/**
 * Builder class for creating Client instances, each configured for ONE server connection.
 *
 * This class provides a fluent interface for configuring and building Client instances.
 */
class ClientBuilder
{
    protected ?string $name = null;

    protected ?string $version = null;

    protected ?ClientCapabilities $capabilities = null;

    protected ?LoggerInterface $logger = null;

    protected ?CacheInterface $cache = null;

    protected ?EventDispatcherInterface $eventDispatcher = null;

    protected ?MessageIdGenerator $idGenerator = null;

    protected ?LoopInterface $loop = null;

    protected ?ServerConfig $serverConfig = null;

    protected int $definitionCacheTtl = 3600;

    protected ?TransportFactory $transportFactory = null;

    protected function __construct() {}

    public static function make(): self
    {
        return new self;
    }

    /** @deprecated 1.0.1 Use withClientInfo() instead. */
    public function withName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /** @deprecated 1.0.1 Use withClientInfo() instead. */
    public function withVersion(string $version): self
    {
        $this->version = $version;

        return $this;
    }

    public function withClientInfo(string $name, string $version): self
    {
        $this->name = $name;
        $this->version = $version;

        return $this;
    }

    public function withCapabilities(ClientCapabilities $capabilities): self
    {
        $this->capabilities = $capabilities;

        return $this;
    }

    public function withLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;

        return $this;
    }

    public function withCache(CacheInterface $cache, int $definitionCacheTtl = 3600): self
    {
        $this->cache = $cache;
        $this->definitionCacheTtl = $definitionCacheTtl > 0 ? $definitionCacheTtl : 3600;

        return $this;
    }

    public function withEventDispatcher(EventDispatcherInterface $eventDispatcher): self
    {
        $this->eventDispatcher = $eventDispatcher;

        return $this;
    }

    public function withIdGenerator(MessageIdGenerator $generator): self
    {
        $this->idGenerator = $generator;

        return $this;
    }

    public function withLoop(LoopInterface $loop): self
    {
        $this->loop = $loop;

        return $this;
    }

    /**
     * Sets the configuration for the server this client will connect to.
     * This is required before calling build().
     *
     * @param  ServerConfig  $config  The configuration object for the server.
     */
    public function withServerConfig(ServerConfig $config): self
    {
        $this->serverConfig = $config;

        return $this;
    }

    /**
     * [ADVANCED] Provide a custom TransportFactory instance.
     * Useful for testing or advanced transport customization.
     */
    public function withTransportFactory(TransportFactory $factory): self
    {
        $this->transportFactory = $factory;

        return $this;
    }

    /**
     * Builds the Client instance.
     *
     * @throws ConfigurationException If required configuration is missing.
     */
    public function build(): Client
    {
        if ($this->name === null) {
            throw new ConfigurationException('Name must be provided using withName().');
        }

        if ($this->version === null) {
            throw new ConfigurationException('Version must be provided using withVersion().');
        }

        if ($this->serverConfig === null) {
            throw new ConfigurationException('ServerConfig must be provided using withServerConfig().');
        }

        $capabilities = $this->capabilities ?? ClientCapabilities::forClient();
        $loop = $this->loop ?? Loop::get();

        $clientConfig = new ClientConfig(
            name: $this->name,
            version: $this->version,
            capabilities: $capabilities,
            logger: $this->logger,
            cache: $this->cache,
            eventDispatcher: $this->eventDispatcher,
            loop: $loop,
            definitionCacheTtl: $this->definitionCacheTtl,
            idGenerator: $this->idGenerator
        );

        return new Client(
            $this->serverConfig,
            $clientConfig,
            $this->transportFactory
        );
    }
}
