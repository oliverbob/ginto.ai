<?php

declare(strict_types=1);

namespace PhpMcp\Client\Cache;

use PhpMcp\Client\Exception\DefinitionException;
use PhpMcp\Client\Model\Definitions\PromptDefinition;
use PhpMcp\Client\Model\Definitions\ResourceDefinition;
use PhpMcp\Client\Model\Definitions\ResourceTemplateDefinition;
use PhpMcp\Client\Model\Definitions\ToolDefinition;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Throwable;

/**
 * Handles caching of definitions (tools, resources, prompts) received from servers.
 * Uses a PSR-16 cache implementation provided via ClientConfig.
 *
 * @internal
 */
final class DefinitionCache
{
    private const CACHE_KEY_PREFIX = 'mcp_client_defs_';

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly int $ttl, // Default TTL in seconds
        private readonly LoggerInterface $logger
    ) {}

    /**
     * @return array<ToolDefinition>|null Null if not found or error.
     */
    public function getTools(string $serverName): ?array
    {
        return $this->get($serverName, 'tools', ToolDefinition::class);
    }

    /**
     * @param  array<ToolDefinition>  $tools
     */
    public function setTools(string $serverName, array $tools): void
    {
        $this->set($serverName, 'tools', $tools);
    }

    /**
     * @return array<ResourceDefinition>|null
     */
    public function getResources(string $serverName): ?array
    {
        return $this->get($serverName, 'resources', ResourceDefinition::class);
    }

    /**
     * @param  array<ResourceDefinition>  $resources
     */
    public function setResources(string $serverName, array $resources): void
    {
        $this->set($serverName, 'resources', $resources);
    }

    /**
     * @return array<PromptDefinition>|null
     */
    public function getPrompts(string $serverName): ?array
    {
        return $this->get($serverName, 'prompts', PromptDefinition::class);
    }

    /**
     * @param  array<PromptDefinition>  $prompts
     */
    public function setPrompts(string $serverName, array $prompts): void
    {
        $this->set($serverName, 'prompts', $prompts);
    }

    /**
     * @return array<ResourceTemplateDefinition>|null
     */
    public function getResourceTemplates(string $serverName): ?array
    {
        return $this->get($serverName, 'res_templates', ResourceTemplateDefinition::class);
    }

    /**
     * @param  array<ResourceTemplateDefinition>  $templates
     */
    public function setResourceTemplates(string $serverName, array $templates): void
    {
        $this->set($serverName, 'res_templates', $templates);
    }

    /**
     * Generic get method.
     *
     * @template T of ToolDefinition|ResourceDefinition|PromptDefinition|ResourceTemplateDefinition
     *
     * @param  class-string<T>  $expectedClass  FQCN of the definition class
     * @return array<T>|null
     */
    private function get(string $serverName, string $type, string $expectedClass): ?array
    {
        $key = $this->generateCacheKey($serverName, $type);
        try {
            $cachedData = $this->cache->get($key);

            if ($cachedData === null) {
                return null; // Cache miss
            }

            if (! is_array($cachedData)) {
                $this->logger->warning("Invalid data type found in cache for {$key}. Expected array.", ['type' => gettype($cachedData)]);
                $this->cache->delete($key); // Clear invalid cache entry

                return null;
            }

            $definitions = [];
            foreach ($cachedData as $itemData) {
                if (! is_array($itemData)) {
                    $this->logger->warning("Invalid item data type found in cached array for {$key}. Expected array.", ['type' => gettype($itemData)]);
                    $this->cache->delete($key); // Clear invalid cache entry

                    return null;
                }
                // Re-hydrate from array using the static fromArray method
                if (! method_exists($expectedClass, 'fromArray')) {
                    throw new DefinitionException("Definition class {$expectedClass} is missing the required fromArray method.");
                }

                $definitions[] = call_user_func([$expectedClass, 'fromArray'], $itemData);
            }

            return $definitions;

        } catch (Throwable $e) {
            // Catch PSR-16 exceptions or hydration errors
            $this->logger->error("Error getting definitions from cache for key '{$key}': {$e->getMessage()}", ['exception' => $e]);

            return null; // Return null on error
        }
    }

    /**
     * Generic set method.
     *
     * @param  array<ToolDefinition|ResourceDefinition|PromptDefinition|ResourceTemplateDefinition>  $definitions
     */
    private function set(string $serverName, string $type, array $definitions): void
    {
        $key = $this->generateCacheKey($serverName, $type);
        try {
            // Convert definition objects back to arrays for caching
            $dataToCache = array_map(function ($definition) {
                if (! method_exists($definition, 'toArray')) {
                    throw new DefinitionException('Definition class '.get_class($definition).' is missing the required toArray method for caching.');
                }

                return $definition->toArray(); // Assuming a toArray exists for caching
            }, $definitions);

            $this->cache->set($key, $dataToCache, $this->ttl);
        } catch (Throwable $e) {
            // Catch PSR-16 exceptions or serialization errors
            $this->logger->error("Error setting definitions to cache for key '{$key}': {$e->getMessage()}", ['exception' => $e]);
        }
    }

    private function generateCacheKey(string $serverName, string $type): string
    {
        // Sanitize server name for cache key safety
        $safeServerName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $serverName);

        return self::CACHE_KEY_PREFIX.$safeServerName.'_'.$type;
    }

    /** Invalidate cache for a specific server */
    public function invalidateServerCache(string $serverName): void
    {
        $keys = [
            $this->generateCacheKey($serverName, 'tools'),
            $this->generateCacheKey($serverName, 'resources'),
            $this->generateCacheKey($serverName, 'prompts'),
            $this->generateCacheKey($serverName, 'res_templates'),
        ];
        try {
            $this->cache->deleteMultiple($keys);
            $this->logger->info("Invalidated definition cache for server '{$serverName}'.");
        } catch (Throwable $e) {
            $this->logger->error("Error invalidating cache for server '{$serverName}': {$e->getMessage()}", ['exception' => $e]);
        }
    }
}
