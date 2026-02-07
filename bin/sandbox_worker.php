#!/usr/bin/env php
<?php
// Worker that processes sandbox jobs enqueued by UnifiedSandbox::enqueueJob
// Usage: php bin/sandbox_worker.php [--once]

chdir(__DIR__ . '/..');
require_once __DIR__ . '/../vendor/autoload.php';

use Ginto\Helpers\UnifiedSandbox;

$once = in_array('--once', $argv, true);
$pollInterval = 2;

$jobDir = UnifiedSandbox::getJobDir();
if (!is_dir($jobDir)) {
    echo "Job dir not found: $jobDir\n";
    exit(1);
}

echo "Sandbox worker started (job dir: $jobDir)\n";

while (true) {
    $files = glob($jobDir . '/job_*.json');
    if (!empty($files)) {
        foreach ($files as $file) {
            // Basic locking: rename to .lock to claim
            $lockFile = $file . '.lock';
            if (!@rename($file, $lockFile)) {
                continue; // someone else claimed or race
            }

            $job = json_decode(file_get_contents($lockFile), true);
            if (!is_array($job)) {
                // corrupt file
                @unlink($lockFile);
                continue;
            }

            $job['status'] = 'running';
            $job['started_at'] = time();
            file_put_contents($lockFile, json_encode($job, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $sandboxId = $job['sandbox_id'] ?? null;
            $cmd = $job['command'] ?? '';
            $cwd = $job['cwd'] ?? '/home/sandbox';
            $timeout = intval($job['timeout'] ?? 300);

            echo "Processing job {$job['id']} for sandbox {$sandboxId}\n";

            try {
                [$exit, $stdout, $stderr] = UnifiedSandbox::exec($sandboxId, $cmd, $cwd, $timeout);
            } catch (Throwable $e) {
                $exit = 1;
                $stdout = '';
                $stderr = $e->getMessage();
            }

            $job['exit_code'] = $exit;
            $job['stdout'] = $stdout;
            $job['stderr'] = $stderr;
            $job['finished_at'] = time();
            $job['status'] = ($exit === 0) ? 'completed' : 'failed';

            // write result back to stable filename
            $resultFile = $jobDir . '/' . $job['id'] . '.json';
            file_put_contents($resultFile, json_encode($job, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            // remove lock
            @unlink($lockFile);

            echo "Job {$job['id']} finished with code {$exit}\n";

            if ($once) {
                exit(0);
            }
        }
    }

    sleep($pollInterval);
}
