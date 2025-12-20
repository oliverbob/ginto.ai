<?php

declare(strict_types=1);

namespace App\Handlers;

use PhpMcp\Server\Attributes\McpTool;

/**
 * Cursor-like repository context tools for local MCP usage.
 *
 * Provides lightweight repo and file helpers so clients can request
 * repository context and file contents for better code understanding
 * regardless of which model is used.
 */
final class CursorContext
{
    private static function repoRoot(): string
    {
        return defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2);
    }

    #[McpTool(name: 'repo/describe')]
    public function describeRepo(): array
    {
        $root = self::repoRoot();
        $composer = @json_decode(@file_get_contents($root . DIRECTORY_SEPARATOR . 'composer.json'), true) ?: [];
        return [
            'name' => $composer['name'] ?? basename($root),
            'description' => $composer['description'] ?? null,
            'path' => $root,
            'php_version' => $composer['require']['php'] ?? null,
            'files_count' => $this->countFiles($root),
        ];
    }

    #[McpTool(name: 'repo/list_files')]
    public function listFiles(string $path = '.', int $max = 200): array
    {
        $root = realpath(self::repoRoot());
        $target = realpath($root . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR));
        if ($target === false || strpos($target, $root) !== 0) {
            throw new \RuntimeException('Invalid path');
        }

        $files = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($target));
        foreach ($it as $f) {
            if ($f->isDir()) continue;
            $rel = substr($f->getPathname(), strlen($root) + 1);
            // skip vendor and storage by default
            if (preg_match('#^(vendor|storage|node_modules)/#', $rel)) continue;
            $files[] = $rel;
            if (count($files) >= $max) break;
        }
        return $files;
    }

    #[McpTool(name: 'repo/get_file')]
    public function getFile(string $path, int $maxBytes = 1024 * 64): array
    {
        $root = realpath(self::repoRoot());
        $target = realpath($root . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR));
        if ($target === false || strpos($target, $root) !== 0 || !is_file($target)) {
            throw new \RuntimeException('File not found');
        }
        $size = filesize($target);
        $contents = @file_get_contents($target, false, null, 0, $maxBytes);
        if ($contents === false) throw new \RuntimeException('Unable to read file');
        return [
            'path' => substr($target, strlen($root) + 1),
            'size' => $size,
            'truncated' => ($size > strlen($contents)),
            'content' => $contents,
        ];
    }

    #[McpTool(name: 'repo/create_or_update_file', description: 'Create or update any file in the repository. Supports all file types including .php, .js, .py, .md, .txt, .json, .html, .css, and any other extension. Use this tool whenever the user asks to create, make, or write a file.')]
    public function createOrUpdateFile(string $file_path, string $content): array
    {
        $root = realpath(self::repoRoot());
        $targetRel = ltrim($file_path, "\/\\");
        $target = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $targetRel);

        // Prevent path traversal: resolve realpath of parent dir after creating dirs
        $dir = dirname($target);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0775, true)) {
                throw new \RuntimeException('Unable to create directory: ' . $dir);
            }
        }

        // Ensure the target is still within repo root
        $realTargetDir = realpath($dir);
        if ($realTargetDir === false || strpos($realTargetDir, $root) !== 0) {
            throw new \RuntimeException('Invalid file path');
        }

        $bytes = @file_put_contents($target, $content);
        if ($bytes === false) {
            throw new \RuntimeException('Failed to write file');
        }

        return [
            'path' => substr($target, strlen($root) + 1),
            'size' => $bytes,
            'message' => 'written',
        ];
    }

    #[McpTool(name: 'repo/git_status')]
    public function gitStatus(): array
    {
        $root = self::repoRoot();
        if (!is_dir($root . DIRECTORY_SEPARATOR . '.git')) return ['git' => false, 'status' => []];
        $cmd = 'git -C ' . escapeshellarg($root) . ' status --porcelain=v1 -z 2>&1';
        $out = null; $code = 0; @exec($cmd, $out, $code);
        $raw = is_array($out) ? implode("\n", $out) : (string)$out;
        $entries = [];
        if ($raw !== '') {
            // parse porcelain -z (NUL separated entries) fallback to lines
            $parts = preg_split('/\x00/', $raw);
            foreach ($parts as $p) {
                $p = trim($p);
                if ($p === '') continue;
                // format: XY path
                $entries[] = $p;
            }
        }
        return ['git' => true, 'status' => $entries, 'exit_code' => $code];
    }

    private function countFiles(string $dir): int
    {
        $count = 0;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($it as $f) {
            if ($f->isFile()) $count++;
            if ($count > 100000) break;
        }
        return $count;
    }
}
