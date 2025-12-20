#!/usr/bin/env php
<?php
// scripts/dump_discovered_tools.php
// Low-memory compatibility wrapper that prints discovered MCP tools as JSON.

declare(strict_types=1);

chdir(__DIR__ . '/..');

ini_set('memory_limit', '256M');
set_time_limit(15);

function outJson(array $arr): void {
    echo json_encode($arr, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

$tools = [];

$handlersDir = __DIR__ . '/../src/Handlers';
if (is_dir($handlersDir)) {
    foreach (glob($handlersDir . '/*.php') as $file) {
        $txt = @file_get_contents($file);
        if ($txt === false) continue;

        if (preg_match('/class\s+([A-Za-z0-9_]+)/', $txt, $m)) $class = $m[1];
        else $class = basename($file, '.php');

        // Match McpTool attribute with multi-line support (use DOTALL to match newlines)
        if (preg_match_all('/#\[\s*McpTool\s*\((.+?)\)\s*\]/s', $txt, $mAttrs, PREG_OFFSET_CAPTURE)) {
            foreach ($mAttrs[1] as $idx => $attrData) {
                $attrBody = $attrData[0];
                $attrPos = $mAttrs[0][$idx][1] ?? null;
                $name = null;
                $description = null;

                // Extract name parameter
                if (preg_match('/name\s*[:=]\s*["\']([^"\']+)["\']/i', $attrBody, $m2)) {
                    $name = $m2[1];
                }
                if (!$name && preg_match('/^[\s\'\"]*([A-Za-z0-9_\-\/\.]+)[\s\'\"]*/', trim($attrBody), $m3)) {
                    $name = $m3[1];
                }
                if (!$name) continue;

                // Extract description parameter (handle multi-line strings)
                if (preg_match('/description\s*[:=]\s*["\'](.+?)["\']\s*(?:,|\)|$)/s', $attrBody, $mDesc)) {
                    $description = trim(preg_replace('/\s+/', ' ', $mDesc[1]));
                }

                // find method after attribute
                $method = null;
                if ($attrPos !== null) {
                    $after = substr($txt, $attrPos);
                    if (preg_match('/function\s+([A-Za-z0-9_]+)/', $after, $mfn)) {
                        $method = $mfn[1];
                    }
                }

                $handler = '\\App\\Handlers\\' . $class . ($method ? ('::' . $method) : '');

                // Use description from attribute if available, otherwise fallback
                if (!$description) {
                    $description = 'Handler ' . $class . ($method ? ('::' . $method) : '');
                }

                $tools[] = [
                    'id' => $name,
                    'name' => $name,
                    'title' => $name,
                    'description' => $description,
                    'source' => 'handlers',
                    'mcp' => 'php-mcp',
                    'handler' => $handler,
                ];
            }
        }
    }
}

$toolsRoot = __DIR__ . '/../tools';
if (is_dir($toolsRoot)) {
    foreach (glob($toolsRoot . '/*', GLOB_ONLYDIR) as $dir) {
        $serverJson = $dir . '/server.json';
        if (!is_file($serverJson)) continue;
        $s = @json_decode(@file_get_contents($serverJson), true) ?: [];
        if (!empty($s['tools']) && is_array($s['tools'])) {
            foreach ($s['tools'] as $raw) {
                $n = is_array($raw) ? ($raw['name'] ?? null) : (is_string($raw) ? $raw : null);
                if (!$n) continue;
                $tools[] = [
                    'id' => $n,
                    'name' => $n,
                    'title' => is_array($raw) ? ($raw['title'] ?? $n) : $n,
                    'description' => is_array($raw) ? ($raw['description'] ?? null) : null,
                    'source' => 'tools-folder',
                    'mcp' => basename($dir),
                    'handler' => null,
                ];
            }
        }
    }
}

foreach ($tools as &$t) {
    $mcp = $t['mcp'] ?? '';
    if (stripos($mcp, 'groq') !== false) $t['mcp'] = 'groq';
    elseif (stripos($mcp, 'github') !== false) $t['mcp'] = 'github';
    else $t['mcp'] = 'php-mcp';
}

outJson($tools);
