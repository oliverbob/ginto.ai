<?php

declare(strict_types=1);

namespace App\Handlers;

use PhpMcp\Server\Attributes\McpTool;

/**
 * Advanced development tools for VS Code-like agent capabilities.
 * 
 * These tools enable the AI agent to:
 * - Read and analyze files with line numbers
 * - Make surgical edits (replace specific text)
 * - Search for patterns across the codebase
 * - List directory contents
 * - Execute terminal commands
 * - Understand project structure
 * - Analyze code semantically
 * - Find usages and references
 */
final class DevTools
{
    private static function repoRoot(): string
    {
        return defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2);
    }

    // ...existing code...

    private function analyzeJsFile(string $content): array
    {
        $result = [
            'imports' => [],
            'exports' => [],
            'classes' => [],
            'functions' => [],
            'variables' => [],
        ];
        
        // Extract imports
        preg_match_all('/^\s*import\s+(.+?)\s+from\s+[\'"]([^\'"]+)[\'"]/m', $content, $imports, PREG_SET_ORDER);
        foreach ($imports as $imp) {
            $result['imports'][] = [
                'what' => trim($imp[1]),
                'from' => $imp[2],
            ];
        }
        
        // Extract require statements
        preg_match_all('/(?:const|let|var)\s+(\w+)\s*=\s*require\s*\([\'"]([^\'"]+)[\'"]\)/m', $content, $requires, PREG_SET_ORDER);
        foreach ($requires as $req) {
            $result['imports'][] = [
                'what' => $req[1],
                'from' => $req[2],
            ];
        }
        
        // Extract classes
        preg_match_all('/^\s*(?:export\s+)?class\s+(\w+)(?:\s+extends\s+(\w+))?/m', $content, $classes, PREG_SET_ORDER);
        foreach ($classes as $class) {
            $result['classes'][] = [
                'name' => $class[1],
                'extends' => $class[2] ?? null,
            ];
        }
        
        // Extract functions
        preg_match_all('/^\s*(?:export\s+)?(?:async\s+)?function\s+(\w+)\s*\(/m', $content, $functions);
        $result['functions'] = array_merge($result['functions'], $functions[1]);
        
        // Extract arrow functions assigned to const
        preg_match_all('/^\s*(?:export\s+)?const\s+(\w+)\s*=\s*(?:async\s+)?\([^)]*\)\s*=>/m', $content, $arrowFuncs);
        $result['functions'] = array_merge($result['functions'], $arrowFuncs[1]);
        
        // Extract exports
        preg_match_all('/^\s*export\s+(?:default\s+)?(?:const|let|var|function|class|async\s+function)\s+(\w+)/m', $content, $exports);
        $result['exports'] = $exports[1];
        
        return $result;
    }

    #[McpTool(
        name: 'find_usages',
        description: 'Find all usages/references of a symbol (function, class, variable, method) across the codebase. Essential for understanding impact of changes.'
    )]
    public function findUsages(string $symbol, string $path = '.'): array
    {
        $root = realpath(self::repoRoot());
        $target = self::resolvePath($path);
        
        $results = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($target, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        // Build patterns for different usage types
        $patterns = [
            'method_call' => '/->'.preg_quote($symbol, '/').'\s*\(/',
            'static_call' => '/::'.preg_quote($symbol, '/').'\s*\(/',
            'function_call' => '/\b'.preg_quote($symbol, '/').'\s*\(/',
            'new_instance' => '/new\s+'.preg_quote($symbol, '/').'\b/',
            'extends' => '/extends\s+'.preg_quote($symbol, '/').'\b/',
            'implements' => '/implements\s+[^{]*\b'.preg_quote($symbol, '/').'\b/',
            'use_trait' => '/use\s+[^;]*\b'.preg_quote($symbol, '/').'\b/',
            'type_hint' => '/:\s*\??' . preg_quote($symbol, '/') . '\b/',
            'instanceof' => '/instanceof\s+'.preg_quote($symbol, '/').'\b/',
            'variable' => '/\$'.preg_quote($symbol, '/').'\b/',
        ];
        
        foreach ($it as $file) {
            if (!$file->isFile()) continue;
            
            $filePath = $file->getPathname();
            $rel = substr($filePath, strlen($root) + 1);
            
            // Skip non-code files
            if (!preg_match('#\.(php|js|ts|jsx|tsx|vue)$#', $rel)) continue;
            if (preg_match('#^(vendor|node_modules|\.git)/#', $rel)) continue;
            
            $content = @file_get_contents($filePath);
            if ($content === false) continue;
            
            $lines = explode("\n", $content);
            foreach ($lines as $lineNum => $line) {
                $matchedTypes = [];
                foreach ($patterns as $type => $pattern) {
                    if (preg_match($pattern, $line)) {
                        $matchedTypes[] = $type;
                    }
                }
                
                if (!empty($matchedTypes)) {
                    $results[] = [
                        'file' => $rel,
                        'line' => $lineNum + 1,
                        'content' => trim($line),
                        'usageTypes' => $matchedTypes,
                    ];
                }
            }
        }
        
        // Group by file for easier reading
        $byFile = [];
        foreach ($results as $r) {
            $byFile[$r['file']][] = $r;
        }
        
        return [
            'symbol' => $symbol,
            'totalUsages' => count($results),
            'filesWithUsages' => count($byFile),
            'usages' => $results,
        ];
    }

    #[McpTool(
        name: 'get_file_symbols',
        description: 'Get a list of all symbols (classes, functions, methods, variables) defined in a file. Quick way to understand what a file provides.'
    )]
    public function getFileSymbols(string $path): array
    {
        $analysis = $this->analyzeFile($path);
        
        $symbols = [
            'path' => $path,
            'symbols' => [],
        ];
        
        // Add namespace
        if (!empty($analysis['namespace'])) {
            $symbols['namespace'] = $analysis['namespace'];
        }
        
        // Add classes with their members
        foreach ($analysis['classes'] ?? [] as $class) {
            $symbols['symbols'][] = [
                'type' => 'class',
                'name' => $class['name'],
                'extends' => $class['extends'],
            ];
            
            foreach ($class['methods'] ?? [] as $method) {
                $symbols['symbols'][] = [
                    'type' => 'method',
                    'name' => $class['name'] . '::' . $method['name'],
                    'visibility' => $method['visibility'],
                    'static' => $method['static'],
                ];
            }
        }
        
        // Add functions
        foreach ($analysis['functions'] ?? [] as $func) {
            $name = is_array($func) ? $func['name'] : $func;
            $symbols['symbols'][] = [
                'type' => 'function',
                'name' => $name,
            ];
        }
        
        // Add interfaces
        foreach ($analysis['interfaces'] ?? [] as $interface) {
            $symbols['symbols'][] = [
                'type' => 'interface',
                'name' => $interface['name'],
            ];
        }
        
        // Add traits
        foreach ($analysis['traits'] ?? [] as $trait) {
            $symbols['symbols'][] = [
                'type' => 'trait',
                'name' => $trait['name'],
            ];
        }
        
        return $symbols;
    }

    #[McpTool(
        name: 'get_dependencies',
        description: 'Get all dependencies (imports, requires, uses) of a file. Shows what external code a file relies on.'
    )]
    public function getDependencies(string $path): array
    {
        $analysis = $this->analyzeFile($path);
        
        $deps = [
            'path' => $path,
            'namespace' => $analysis['namespace'] ?? null,
            'imports' => $analysis['imports'] ?? [],
        ];
        
        // For PHP, also extract inline class references
        $target = self::resolvePath($path);
        $content = @file_get_contents($target);
        
        if ($content) {
            // Find type hints and return types
            preg_match_all('/(?::\s*\??|new\s+|extends\s+|implements\s+)([A-Z]\w+(?:\\\\\w+)*)/m', $content, $typeRefs);
            $deps['typeReferences'] = array_unique($typeRefs[1]);
        }
        
        return $deps;
    }

    #[McpTool(
        name: 'get_context',
        description: 'Get expanded context around a specific line in a file. Shows surrounding code to understand the context of a specific location.'
    )]
    public function getContext(string $path, int $line, int $contextLines = 10): array
    {
        $target = self::resolvePath($path);
        
        if (!is_file($target)) {
            throw new \RuntimeException("File not found: {$path}");
        }
        
        $lines = file($target, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new \RuntimeException("Unable to read file: {$path}");
        }
        
        $totalLines = count($lines);
        $startLine = max(1, $line - $contextLines);
        $endLine = min($totalLines, $line + $contextLines);
        
        $content = [];
        for ($i = $startLine - 1; $i < $endLine; $i++) {
            $lineNum = $i + 1;
            $marker = ($lineNum === $line) ? ' >>>' : '    ';
            $content[] = sprintf("%s%4d | %s", $marker, $lineNum, $lines[$i] ?? '');
        }
        
        return [
            'path' => $path,
            'targetLine' => $line,
            'startLine' => $startLine,
            'endLine' => $endLine,
            'content' => implode("\n", $content),
        ];
    }

    #[McpTool(
        name: 'explain_code',
        description: 'Get a structured explanation of what a code section does. Analyzes the code and provides summary information.'
    )]
    public function explainCode(string $path, int $startLine = 1, int $endLine = 0): array
    {
        $target = self::resolvePath($path);
        
        if (!is_file($target)) {
            throw new \RuntimeException("File not found: {$path}");
        }
        
        $lines = file($target, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new \RuntimeException("Unable to read file: {$path}");
        }
        
        $totalLines = count($lines);
        $startLine = max(1, $startLine);
        $endLine = $endLine > 0 ? min($endLine, $totalLines) : $totalLines;
        
        $codeSection = array_slice($lines, $startLine - 1, $endLine - $startLine + 1);
        $code = implode("\n", $codeSection);
        
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        
        $explanation = [
            'path' => $path,
            'lineRange' => [$startLine, $endLine],
            'structure' => [],
        ];
        
        // Identify key constructs
        if ($ext === 'php') {
            // Find classes
            preg_match_all('/^\s*(abstract\s+|final\s+)?(class|interface|trait)\s+(\w+)/m', $code, $classes);
            if (!empty($classes[3])) {
                $explanation['structure']['classes'] = $classes[3];
            }
            
            // Find functions/methods
            preg_match_all('/^\s*(public|private|protected)?\s*function\s+(\w+)/m', $code, $functions);
            if (!empty($functions[2])) {
                $explanation['structure']['functions'] = $functions[2];
            }
            
            // Find control flow
            $controlFlow = [];
            if (preg_match('/\bif\s*\(/', $code)) $controlFlow[] = 'conditionals';
            if (preg_match('/\b(for|foreach|while)\s*\(/', $code)) $controlFlow[] = 'loops';
            if (preg_match('/\btry\s*\{/', $code)) $controlFlow[] = 'exception_handling';
            if (preg_match('/\breturn\b/', $code)) $controlFlow[] = 'returns';
            $explanation['structure']['controlFlow'] = $controlFlow;
            
            // Find database/external calls
            $externalCalls = [];
            if (preg_match('/->(?:query|execute|prepare|fetch)/i', $code)) $externalCalls[] = 'database';
            if (preg_match('/file_(?:get|put)_contents|fopen|fwrite/', $code)) $externalCalls[] = 'filesystem';
            if (preg_match('/curl_|file_get_contents\s*\(\s*[\'"]https?:/i', $code)) $externalCalls[] = 'http';
            $explanation['structure']['externalCalls'] = $externalCalls;
        }
        
        // Count complexity indicators
        $explanation['metrics'] = [
            'lines' => $endLine - $startLine + 1,
            'blankLines' => count(array_filter($codeSection, fn($l) => trim($l) === '')),
            'commentLines' => count(array_filter($codeSection, fn($l) => preg_match('/^\s*(\/\/|#|\/\*|\*)/', $l))),
        ];
        
        return $explanation;
    }

    #[McpTool(
        name: 'find_related_files',
        description: 'Find files related to a given file (same class family, tests, configs, etc.). Helps understand the full context of a component.'
    )]
    public function findRelatedFiles(string $path): array
    {
        $root = realpath(self::repoRoot());
        $target = self::resolvePath($path);
        $baseName = pathinfo($path, PATHINFO_FILENAME);
        $dir = dirname($path);
        
        $related = [
            'path' => $path,
            'related' => [],
        ];
        
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        // Patterns for related files
        $patterns = [
            'test' => ['Test' . $baseName, $baseName . 'Test', $baseName . '.test', $baseName . '.spec'],
            'interface' => [$baseName . 'Interface', 'I' . $baseName],
            'abstract' => ['Abstract' . $baseName, 'Base' . $baseName],
            'factory' => [$baseName . 'Factory'],
            'repository' => [$baseName . 'Repository'],
            'service' => [$baseName . 'Service'],
            'controller' => [$baseName . 'Controller'],
            'config' => [$baseName . '.config', $baseName . '.json', $baseName . '.yaml'],
        ];
        
        foreach ($it as $file) {
            if (!$file->isFile()) continue;
            
            $filePath = $file->getPathname();
            $rel = substr($filePath, strlen($root) + 1);
            $fileName = pathinfo($filePath, PATHINFO_FILENAME);
            
            if (preg_match('#^(vendor|node_modules|\.git)/#', $rel)) continue;
            
            foreach ($patterns as $type => $names) {
                foreach ($names as $name) {
                    if (strcasecmp($fileName, $name) === 0 || stripos($fileName, $name) !== false) {
                        $related['related'][] = [
                            'file' => $rel,
                            'relationship' => $type,
                        ];
                        break 2;
                    }
                }
            }
            
            // Also find files with similar names
            if ($fileName !== $baseName && stripos($fileName, $baseName) !== false) {
                $exists = array_filter($related['related'], fn($r) => $r['file'] === $rel);
                if (empty($exists)) {
                    $related['related'][] = [
                        'file' => $rel,
                        'relationship' => 'similar_name',
                    ];
                }
            }
        }
        
        return $related;
    }

    #[McpTool(
        name: 'compare_files',
        description: 'Compare two files and show their differences. Useful for understanding changes or variations between files.'
    )]
    public function compareFiles(string $file1, string $file2): array
    {
        $target1 = self::resolvePath($file1);
        $target2 = self::resolvePath($file2);
        
        if (!is_file($target1)) {
            throw new \RuntimeException("File not found: {$file1}");
        }
        if (!is_file($target2)) {
            throw new \RuntimeException("File not found: {$file2}");
        }
        
        $content1 = file($target1, FILE_IGNORE_NEW_LINES);
        $content2 = file($target2, FILE_IGNORE_NEW_LINES);
        
        if ($content1 === false || $content2 === false) {
            throw new \RuntimeException("Unable to read files");
        }
        
        // Simple diff
        $differences = [];
        $maxLines = max(count($content1), count($content2));
        
        for ($i = 0; $i < $maxLines; $i++) {
            $line1 = $content1[$i] ?? null;
            $line2 = $content2[$i] ?? null;
            
            if ($line1 !== $line2) {
                $differences[] = [
                    'line' => $i + 1,
                    'file1' => $line1,
                    'file2' => $line2,
                ];
            }
        }
        
        return [
            'file1' => $file1,
            'file2' => $file2,
            'identical' => empty($differences),
            'differenceCount' => count($differences),
            'differences' => array_slice($differences, 0, 50), // Limit output
            'truncated' => count($differences) > 50,
        ];
    }

    #[McpTool(
        name: 'walk_files',
        description: 'Walk through repository files, analyzing their contents. Supports streaming progress updates. Useful for understanding codebase structure and finding patterns across multiple files.'
    )]
    public function walkFiles(
        string $path = '.',
        int $maxFiles = 50,
        array $includeExtensions = [],
        array $excludePatterns = ['vendor/*', 'node_modules/*', '.git/*', 'storage/*'],
        bool $analyzeContent = true,
        callable $progressCallback = null
    ): array {
        $target = self::resolvePath($path);
        $root = realpath(self::repoRoot());
        
        if (!is_dir($target)) {
            throw new \RuntimeException("Directory not found: {$path}");
        }
        
        $results = [];
        $filesProcessed = 0;
        $filesSkipped = 0;
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($target, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            if (!$file->isFile()) continue;
            if ($filesProcessed >= $maxFiles) break;
            
            $filePath = $file->getPathname();
            $relativePath = substr($filePath, strlen($root) + 1);
            
            // Check exclude patterns
            $shouldExclude = false;
            foreach ($excludePatterns as $pattern) {
                if (fnmatch($pattern, $relativePath)) {
                    $shouldExclude = true;
                    break;
                }
            }
            
            if ($shouldExclude) {
                $filesSkipped++;
                continue;
            }
            
            // Check include extensions
            if (!empty($includeExtensions)) {
                $ext = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
                if (!in_array($ext, $includeExtensions)) {
                    $filesSkipped++;
                    continue;
                }
            }
            
            // Notify progress callback if provided (for streaming)
            if ($progressCallback) {
                call_user_func($progressCallback, [
                    'type' => 'file_progress',
                    'file' => $relativePath,
                    'processed' => $filesProcessed,
                    'total_estimate' => $maxFiles,
                    'status' => 'analyzing'
                ]);
            }
            
            $fileInfo = [
                'path' => $relativePath,
                'size' => $file->getSize(),
                'modified' => date('Y-m-d H:i:s', $file->getMTime()),
                'extension' => pathinfo($relativePath, PATHINFO_EXTENSION),
            ];
            
            // Analyze content if requested and file is readable text
            if ($analyzeContent && $file->getSize() < 1024 * 1024) { // Skip files > 1MB
                try {
                    $content = file_get_contents($filePath);
                    if ($content !== false) {
                        // Basic analysis
                        $lines = explode("\n", $content);
                        $fileInfo['analysis'] = [
                            'lines' => count($lines),
                            'chars' => strlen($content),
                            'non_empty_lines' => count(array_filter($lines, fn($l) => trim($l) !== '')),
                            'functions' => substr_count($content, 'function '),
                            'classes' => substr_count($content, 'class '),
                            'imports' => substr_count($content, 'use ') + substr_count($content, 'require') + substr_count($content, 'import'),
                        ];
                        
                        // Extract first few non-empty lines for preview
                        $previewLines = array_slice(array_filter($lines, fn($l) => trim($l) !== ''), 0, 3);
                        $fileInfo['preview'] = implode('\n', $previewLines);
                    }
                } catch (\Throwable $e) {
                    $fileInfo['error'] = 'Could not read file: ' . $e->getMessage();
                }
            }
            
            $results[] = $fileInfo;
            $filesProcessed++;
            
            // Small delay for streaming effect (remove in production if needed)
            if ($progressCallback) {
                usleep(10000); // 10ms
            }
        }
        
        // Final progress update
        if ($progressCallback) {
            call_user_func($progressCallback, [
                'type' => 'file_progress',
                'file' => null,
                'processed' => $filesProcessed,
                'total_estimate' => $filesProcessed,
                'status' => 'completed'
            ]);
        }
        
        return [
            'path' => $path,
            'files_processed' => $filesProcessed,
            'files_skipped' => $filesSkipped,
            'total_files_analyzed' => count($results),
            'files' => $results,
            'summary' => $this->generateFileWalkSummary($results),
        ];
    }
    
    private function generateFileWalkSummary(array $files): array
    {
        $extensions = [];
        $totalLines = 0;
        $totalChars = 0;
        $totalFunctions = 0;
        $totalClasses = 0;
        
        foreach ($files as $file) {
            $ext = $file['extension'] ?? '';
            if ($ext) {
                $extensions[$ext] = ($extensions[$ext] ?? 0) + 1;
            }
            
            if (isset($file['analysis'])) {
                $totalLines += $file['analysis']['lines'] ?? 0;
                $totalChars += $file['analysis']['chars'] ?? 0;
                $totalFunctions += $file['analysis']['functions'] ?? 0;
                $totalClasses += $file['analysis']['classes'] ?? 0;
            }
        }
        
        arsort($extensions);
        
        return [
            'file_types' => $extensions,
            'totals' => [
                'lines' => $totalLines,
                'characters' => $totalChars,
                'functions' => $totalFunctions,
                'classes' => $totalClasses,
            ],
            'most_common_type' => !empty($extensions) ? array_key_first($extensions) : null,
        ];
    }
}

