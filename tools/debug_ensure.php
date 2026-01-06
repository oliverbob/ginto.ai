<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/Helpers/SandboxManager.php';

use Ginto\Helpers\SandboxManager;

$sid = $argv[1] ?? '2nKOuHHSYDuL';
$path = __DIR__ . "/../clients/" . $sid;

try {
    echo "Calling ensureSandboxRunning for $sid\n";
    $r = SandboxManager::ensureSandboxRunning($sid, $path, 6);
    echo "ensureSandboxRunning returned: "; var_export($r); echo "\n";
} catch (Throwable $e) {
    echo "EXCEPTION: "; echo $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
