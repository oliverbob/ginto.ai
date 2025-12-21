#!/usr/bin/env php
<?php
/**
 * Ollama Status Worker
 * 
 * Background worker that periodically checks Ollama's /api/ps endpoint
 * and caches the running models list. This avoids expensive curl calls
 * on every /api/models request.
 * 
 * Usage: php bin/ollama_status_worker.php
 * 
 * Run as a background service or via cron every 5-10 seconds.
 * Can also be started by the Ratchet server.
 */

define('ROOT_PATH', dirname(__DIR__));

$cacheDir = ROOT_PATH . '/storage/cache';
$cacheFile = $cacheDir . '/ollama_ps.json';
$checkInterval = 5; // seconds between checks
$ollamaUrl = getenv('OLLAMA_URL') ?: 'http://localhost:11434';

// Ensure cache directory exists
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}

echo "[ollama_status_worker] Starting... (checking every {$checkInterval}s)\n";

/**
 * Check Ollama /api/ps and return running models
 */
function checkOllamaStatus(string $ollamaUrl): array {
    $runningModels = [];
    
    try {
        $ch = curl_init($ollamaUrl . '/api/ps');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 2,
            CURLOPT_CONNECTTIMEOUT => 1,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            if (!empty($data['models']) && is_array($data['models'])) {
                foreach ($data['models'] as $m) {
                    if (!empty($m['name'])) {
                        $runningModels[] = $m['name'];
                    }
                }
            }
        }
    } catch (\Throwable $e) {
        // Ollama not reachable
    }
    
    return $runningModels;
}

/**
 * Write cache file atomically
 */
function writeCache(string $cacheFile, array $models): void {
    $data = [
        'models' => $models,
        'updated_at' => time(),
        'updated_at_iso' => date('c'),
    ];
    
    $tempFile = $cacheFile . '.tmp';
    file_put_contents($tempFile, json_encode($data, JSON_PRETTY_PRINT));
    rename($tempFile, $cacheFile);
}

// Main loop
$lastModels = null;
while (true) {
    $models = checkOllamaStatus($ollamaUrl);
    
    // Only write if changed (reduce disk I/O)
    if ($models !== $lastModels) {
        writeCache($cacheFile, $models);
        $modelCount = count($models);
        $modelList = $modelCount > 0 ? implode(', ', $models) : 'none';
        echo "[" . date('H:i:s') . "] Ollama models updated: {$modelList}\n";
        $lastModels = $models;
    }
    
    sleep($checkInterval);
}
