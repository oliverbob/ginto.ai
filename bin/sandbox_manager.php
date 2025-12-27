#!/usr/bin/env php
<?php
// Simple CLI utility for sandbox management (list/show/reset/delete)
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Core/Database.php';

use Medoo\Medoo;

try {
    $db = \Ginto\Core\Database::getInstance();
} catch (Throwable $e) {
    // Try to connect using environment fallback
    $config = [];
    $db = null;
}

$cmd = $argv[1] ?? 'list';

function dir_size($path) {
    $s = 0;
    if (!is_dir($path)) return 0;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) $s += $f->getSize();
    return $s;
}

switch ($cmd) {
    case 'list':
        if (!$db) { echo "No DB available\n"; exit(1); }
        $rows = $db->select('client_sandboxes', '*') ?: [];
        foreach ($rows as $r) {
            $path = realpath(__DIR__ . '/../clients/' . $r['sandbox_id']);
            $size = $path ? dir_size($path) : 0;
            echo sprintf("%s \t user=%s \t quota=%d \t used=%d\n", $r['sandbox_id'], $r['user_id'] ?? $r['public_id'] ?? 'none', $r['quota_bytes'], $size);
        }
        break;
    case 'show':
        $id = $argv[2] ?? null;
        if (!$id) { echo "Usage: sandbox_manager.php show <id>\n"; exit(1); }
        $r = $db ? $db->get('client_sandboxes', '*', ['sandbox_id' => $id]) : null;
        if (!$r) { echo "Not found\n"; exit(1); }
        $path = realpath(__DIR__ . '/../clients/' . $r['sandbox_id']);
        echo "Sandbox: {$r['sandbox_id']}\n";
        echo "User ID: " . ($r['user_id'] ?? '(none)') . "\n";
        echo "Quota: {$r['quota_bytes']}\n";
        echo "Used (db): {$r['used_bytes']}\n";
        echo "Path: " . ($path ?: '(missing)') . "\n";
        echo "Actual size: " . ($path ? dir_size($path) : 0) . "\n";
        break;
    case 'reset':
        $id = $argv[2] ?? null;
        if (!$id) { echo "Usage: sandbox_manager.php reset <id>\n"; exit(1); }
        $path = realpath(__DIR__ . '/../clients/' . $id);
        if ($path && is_dir($path)) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($it as $f) {
                if ($f->isDir()) rmdir($f->getPathname()); else unlink($f->getPathname());
            }
            echo "Reset sandbox files for $id\n";
        } else echo "Sandbox path not found\n";
        break;
    case 'delete':
        $id = $argv[2] ?? null;
        if (!$id) { echo "Usage: sandbox_manager.php delete <id>\n"; exit(1); }
        $path = realpath(__DIR__ . '/../clients/' . $id);
        if ($path && is_dir($path)) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($it as $f) { if ($f->isDir()) rmdir($f->getPathname()); else unlink($f->getPathname()); }
            @rmdir($path);
        }
        if ($db) $db->delete('client_sandboxes', ['sandbox_id' => $id]);
        echo "Deleted sandbox $id\n";
        break;
    default:
        echo "Unknown command. Available: list, show <id>, reset <id>, delete <id>\n";
}
