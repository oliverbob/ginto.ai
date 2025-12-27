<?php

declare(strict_types=1);

namespace App\Core;

/**
 * McpUnifier
 *
 * Discovers and normalizes MCP tools from multiple sources:
 * - static attribute-based handlers (scripts/dump_discovered_tools.php)
 * - tools/ folders containing server.json
 * - configured runtime MCP servers (composer.json config.mcp.default_server_url or config.mcp.servers)
 *
 * The unifier returns a normalized structure suitable for UI consumption.
 */
class McpUnifier
{
    private string $cacheFile;
    private int $ttl;

    public function __construct(?string $cacheFile = null, int $ttl = 60)
    {
        if ($cacheFile === null) {
            $storagePath = defined('STORAGE_PATH') ? STORAGE_PATH : dirname(__DIR__, 3) . '/storage';
            $cacheFile = $storagePath . '/mcp_unifier_cache.json';
        }
        $this->cacheFile = $cacheFile;
        $this->ttl = $ttl;
    }

    // Keep original constructor logic in init method for compatibility
    private function initLegacy(string $cacheFile = __DIR__ . '/../../storage/mcp_unifier_cache.json', int $ttl = 60)
    {
        $this->cacheFile = $cacheFile;
        $this->ttl = $ttl;
    }

    /**
     * Discover MCP tools declared via PHP attributes in `src/Handlers`.
     * This loads handler files and reflects for `PhpMcp\Server\Attributes\McpTool`.
     */
    private function discoverHandlersViaAttributes(): array
    {
        $root = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2);
        $hd = $root . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Handlers';
        if (!is_dir($hd)) return ['tools' => [], 'mcps' => []];

        $tools = [];
        foreach (scandir($hd) as $f) {
            if ($f === '.' || $f === '..') continue;
            if (!str_ends_with($f, '.php')) continue;
            $full = $hd . DIRECTORY_SEPARATOR . $f;
            // require the handler file to ensure classes/attributes are loaded
            try { @require_once $full; } catch (\Throwable $_) { continue; }
            $className = pathinfo($f, PATHINFO_FILENAME);
            $fqcn = '\\App\\Handlers\\' . $className;
            if (!class_exists($fqcn)) continue;
            try {
                $rc = new \ReflectionClass($fqcn);
                foreach ($rc->getMethods() as $m) {
                    $all = $m->getAttributes();
                    foreach ($all as $attr) {
                        $attrName = $attr->getName();
                        if (stripos($attrName, 'McpTool') === false) continue;
                        $args = $attr->getArguments();
                        $name = $args['name'] ?? ($args[0] ?? null);
                        if (!$name) continue;
                        // Use description from attribute if provided, otherwise fallback
                        $desc = $args['description'] ?? null;
                        if (!$desc) {
                            $desc = 'Handler ' . $className . '::' . $m->getName();
                        }
                        $tools[] = [
                            'id' => ($name),
                            'name' => $name,
                            'title' => $name,
                            'description' => $desc,
                            'input_schema' => null,
                            'source' => 'handlers',
                            'mcp' => 'app',
                            'handler' => $fqcn . '::' . $m->getName(),
                            'raw' => ['class' => $fqcn, 'method' => $m->getName(), 'attr' => $args],
                        ];
                    }
                }
            } catch (\Throwable $_) { continue; }
        }

        return ['tools' => $tools, 'mcps' => ['app']];
    }

    /**
     * Return unified discovery results.
     * If $force is true, bypass cache and re-discover.
     *
     * @return array{
     *   tools: array,
     *   mcps: array,
     *   counts: array,
     *   generated_at: string,
     *   errors: array
     * }
     */
    public function getAllTools(bool $force = false): array
    {
        if (!$force && $this->isCacheFresh()) {
            $data = @json_decode(@file_get_contents($this->cacheFile), true);
            if (is_array($data)) return $data;
        }

        $results = ['tools' => [], 'mcps' => [], 'errors' => []];

        // 1) Use existing static discovery script if present
        try {
            $static = $this->discoverStaticViaScript();
            $results = $this->mergeResults($results, $static);
        } catch (\Throwable $e) {
            $results['errors'][] = 'static:' . $e->getMessage();
        }

        // 1b) Discover handlers defined under src/Handlers via PHP attributes
        try {
            $handlerTools = $this->discoverHandlersViaAttributes();
            $results = $this->mergeResults($results, $handlerTools);
        } catch (\Throwable $e) {
            $results['errors'][] = 'handlers:' . $e->getMessage();
        }

        // 2) Tools folder discovery
        try {
            $tf = $this->discoverToolsFolders();
            $results = $this->mergeResults($results, $tf);
        } catch (\Throwable $e) {
            $results['errors'][] = 'tools-folder:' . $e->getMessage();
        }

        // 3) Runtime MCP servers
        try {
            $rt = $this->discoverRuntimeServers();
            $results = $this->mergeResults($results, $rt);
            // merge any discovery errors returned by the runtime discovery
            if (isset($rt['errors']) && is_array($rt['errors'])) {
                $results['errors'] = array_merge($results['errors'], $rt['errors']);
            }
        } catch (\Throwable $e) {
            $results['errors'][] = 'runtime:' . $e->getMessage();
        }

        $results['mcps'] = array_values(array_unique($results['mcps']));
        $results['counts'] = ['tools' => count($results['tools']), 'mcps' => count($results['mcps'])];
        $results['generated_at'] = date('c');

        @file_put_contents($this->cacheFile, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $results;
    }

    private function isCacheFresh(): bool
    {
        if (!file_exists($this->cacheFile)) return false;
        $age = time() - filemtime($this->cacheFile);
        return $age < $this->ttl;
    }

    private function mergeResults(array $base, array $add): array
    {
        if (!isset($add['tools']) || !is_array($add['tools'])) return $base;
        $existing = [];
        foreach ($base['tools'] as $t) {
            if (isset($t['id'])) $existing[$t['id']] = true;
        }
        foreach ($add['tools'] as $t) {
            $id = $t['id'] ?? ($t['name'] ?? null) ?? uniqid('tool_');
            if (isset($existing[$id])) continue;
            $t['id'] = $id;
            $base['tools'][] = $t;
            if (!empty($t['mcp'])) $base['mcps'][] = $t['mcp'];
        }
        return $base;
    }

    /**
     * Call the existing discovery script `scripts/dump_discovered_tools.php` if present
     * and return normalized tools.
     */
    private function discoverStaticViaScript(): array
    {
        $root = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2);
        $script = $root . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'dump_discovered_tools.php';
        if (!is_file($script)) return ['tools' => [], 'mcps' => []];

        $cmd = 'php ' . escapeshellarg($script) . ' 2>&1';
        $out = null; $code = 0;
        @exec($cmd, $lines, $code);
        $text = is_array($lines) ? implode("\n", $lines) : (string)$lines;

        $decoded = @json_decode($text, true);
        if (!is_array($decoded)) {
            // try to extract JSON substring
            $maybe = null; $len = strlen($text);
            for ($i = max(0, $len - 32768); $i < $len; $i++) {
                $ch = $text[$i] ?? '';
                if ($ch === '{' || $ch === '[') {
                    $cand = substr($text, $i);
                    $try = @json_decode($cand, true);
                    if (is_array($try)) { $maybe = $try; break; }
                }
            }
            if (is_array($maybe)) $decoded = $maybe;
        }

        $tools = [];
        if (is_array($decoded)) {
            foreach ($decoded as $raw) {
                $name = is_array($raw) ? ($raw['name'] ?? $raw['id'] ?? null) : (string)$raw;
                $tools[] = [
                    'id' => $name ?? uniqid('tool_'),
                    'name' => $name,
                    'title' => $raw['title'] ?? $raw['name'] ?? null,
                    'description' => $raw['description'] ?? null,
                    'input_schema' => $raw['schema'] ?? ($raw['inputSchema'] ?? ($raw['input'] ?? null)),
                    'source' => $raw['source'] ?? 'static',
                    'mcp' => $raw['mcp'] ?? 'app',
                    'handler' => $raw['handler'] ?? null,
                    'raw' => $raw,
                ];
            }
        }

        return ['tools' => $tools, 'mcps' => ['app']];
    }

    private function discoverToolsFolders(): array
    {
        $root = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2);
        $toolsRoot = $root . DIRECTORY_SEPARATOR . 'tools';
        $tools = [];
        $mcps = [];
        if (!is_dir($toolsRoot)) return ['tools' => [], 'mcps' => []];
        foreach (glob($toolsRoot . '/*', GLOB_ONLYDIR) as $dir) {
            $mcpName = basename($dir);
            $mcps[] = $mcpName;
            $serverJson = $dir . DIRECTORY_SEPARATOR . 'server.json';
            if (is_file($serverJson)) {
                $s = @json_decode(@file_get_contents($serverJson), true) ?: [];
                // Substitute ${ENV_VAR} placeholders with actual environment values
                $s = $this->substituteEnvVars($s);
                if (!empty($s['tools']) && is_array($s['tools'])) {
                    foreach ($s['tools'] as $raw) {
                        $n = is_array($raw) ? ($raw['name'] ?? null) : null;
                        $tools[] = [
                            'id' => $n ?? uniqid('tool_'),
                            'name' => $n,
                            'title' => $raw['title'] ?? $n,
                            'description' => $raw['description'] ?? null,
                            'input_schema' => $raw['schema'] ?? ($raw['inputSchema'] ?? null),
                            'source' => 'tools-folder',
                            'mcp' => $mcpName,
                            'handler' => null,
                            'raw' => $raw,
                        ];
                    }
                }
            }
        }
        return ['tools' => $tools, 'mcps' => $mcps];
    }

    /**
     * Recursively substitute ${ENV_VAR} placeholders in server.json arrays/strings
     * with the corresponding getenv() values when available.
     */
    private function substituteEnvVars($value)
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = $this->substituteEnvVars($v);
            }
            return $out;
        }
        if (!is_string($value)) return $value;

        return preg_replace_callback('/\\$\\{([A-Z0-9_]+)\\}/', function ($m) {
            $ev = getenv($m[1]);
            return ($ev === false) ? $m[0] : $ev;
        }, $value);
    }

    private function discoverRuntimeServers(): array
    {
        $servers = $this->loadConfiguredRuntimeServers();
        $tools = [];
        $mcps = [];
        $errors = [];
        foreach ($servers as $server) {
            $url = rtrim($server['url'] ?? ($server['endpoint'] ?? ''), '/');
            if (!$url) continue;
            $name = $server['name'] ?? $url;
            $mcps[] = $name;
            // Prefer using the php-mcp client library when available to list tools
            $used = false;
            if (class_exists(\PhpMcp\Client\Client::class) && class_exists(\PhpMcp\Client\ServerConfig::class)) {
                try {
                    $sc = \PhpMcp\Client\ServerConfig::fromArray($name, ['transport' => 'http', 'url' => $url, 'timeout' => 10]);
                    $client = \PhpMcp\Client\Client::make()->withServerConfig($sc)->build();
                    try { $client->initialize(); } catch (\Throwable $_) { /* ignore init errors */ }
                    if ($client->isReady()) {
                        $remoteTools = [];
                        try { $remoteTools = $client->listTools(); } catch (\Throwable $e) { $remoteTools = []; }
                        foreach ($remoteTools as $raw) {
                            // Tool definition objects vary; attempt to extract name/title/description/schema
                            $n = null; $title = null; $desc = null; $schema = null;
                            if (is_object($raw)) {
                                if (property_exists($raw, 'name')) $n = $raw->name;
                                elseif (method_exists($raw, 'name')) $n = $raw->name();
                                if (property_exists($raw, 'title')) $title = $raw->title;
                                if (property_exists($raw, 'description')) $desc = $raw->description;
                                if (property_exists($raw, 'schema')) $schema = $raw->schema;
                                if ($n === null) {
                                    $arr = (array)$raw;
                                    $n = $arr['name'] ?? $arr['id'] ?? null;
                                    $title = $title ?? ($arr['title'] ?? $n);
                                    $desc = $desc ?? ($arr['description'] ?? null);
                                }
                            } elseif (is_array($raw)) {
                                $n = $raw['name'] ?? $raw['id'] ?? null;
                                $title = $raw['title'] ?? $n;
                                $desc = $raw['description'] ?? null;
                                $schema = $raw['schema'] ?? ($raw['inputSchema'] ?? null);
                            } elseif (is_string($raw)) {
                                $n = $raw; $title = $raw;
                            }

                            $tools[] = [
                                'id' => $name . ':' . ($n ?? uniqid('r_')),
                                'name' => $n,
                                'title' => $title ?? $n,
                                'description' => $desc ?? null,
                                'input_schema' => $schema ?? null,
                                'source' => 'runtime',
                                'mcp' => $name,
                                'handler' => null,
                                'raw' => $raw,
                            ];
                        }
                        $used = true;
                    }
                } catch (\Throwable $e) {
                    $used = false;
                }
            }

            if ($used) continue;

            // Fallback: try a basic HTTP GET to /mcp/discover
            try {
                $json = $this->fetchJson($url . '/mcp/discover');
                if (!is_array($json)) continue;
                $remoteTools = $json['tools'] ?? $json['result'] ?? ($json['tools'] ?? []);
                if (!is_array($remoteTools)) continue;
                foreach ($remoteTools as $raw) {
                    $n = is_array($raw) ? ($raw['name'] ?? $raw['id'] ?? null) : (is_string($raw) ? $raw : null);
                    $tools[] = [
                        'id' => $name . ':' . ($n ?? uniqid('r_')),
                        'name' => $n,
                        'title' => is_array($raw) ? ($raw['title'] ?? $n) : $n,
                        'description' => is_array($raw) ? ($raw['description'] ?? null) : null,
                        'input_schema' => is_array($raw) ? ($raw['schema'] ?? ($raw['inputSchema'] ?? null)) : null,
                        'source' => 'runtime',
                        'mcp' => $name,
                        'handler' => null,
                        'raw' => $raw,
                    ];
                }
            } catch (\Throwable $e) {
                $errors[] = ['mcp' => $name, 'url' => $url, 'error' => $e->getMessage()];
            }
        }
        return ['tools' => $tools, 'mcps' => $mcps, 'errors' => $errors];
    }

    private function loadConfiguredRuntimeServers(): array
    {
        $root = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2);
        $composer = @json_decode(@file_get_contents($root . DIRECTORY_SEPARATOR . 'composer.json'), true) ?: [];
        $servers = [];
        if (isset($composer['config']['mcp']['servers']) && is_array($composer['config']['mcp']['servers'])) {
            $servers = $composer['config']['mcp']['servers'];
        } elseif (!empty($composer['config']['mcp']['default_server_url'])) {
            $servers[] = ['url' => $composer['config']['mcp']['default_server_url'], 'name' => 'default'];
        }
        return $servers;
    }

    private function fetchJson(string $url): array
    {
        $ctx = stream_context_create(['http' => ['timeout' => 5]]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) throw new \RuntimeException('Failed to fetch ' . $url);
        $j = @json_decode($raw, true);
        if (!is_array($j)) throw new \RuntimeException('Invalid JSON from ' . $url);
        return $j;
    }

    private function fetchRaw(string $url): string
    {
        $ctx = stream_context_create(['http' => ['timeout' => 5]]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) throw new \RuntimeException('Failed to fetch ' . $url);
        return (string)$raw;
    }
}
