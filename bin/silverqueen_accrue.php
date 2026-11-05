<?php
/**
 * SilverQueen yield worker — settles the daily accrual on every active SQB
 * allocation and runs the 24-hour compounding cycle on every funded wallet.
 *
 * Hourly from cron is plenty; the engine is time-anchored rather than tick-driven,
 * so running more often is harmless and running less often catches up:
 *
 *   0 * * * * cd /home/oliverbob/silverqueen.pro && /usr/bin/php bin/silverqueen_accrue.php >> /tmp/silverqueen.log 2>&1
 *
 * The dashboard also syncs the visiting member on page load, so this worker exists
 * to keep members who never log in — and their uplines' ledgers — current.
 */

$root = dirname(__DIR__);
chdir($root);
if (!defined('ROOT_PATH'))    define('ROOT_PATH', $root);
if (!defined('STORAGE_PATH')) define('STORAGE_PATH', $root . '/storage');

require $root . '/vendor/autoload.php';
try {
    if (class_exists('Dotenv\\Dotenv')) {
        Dotenv\Dotenv::createImmutable($root)->safeLoad();
    }
} catch (\Throwable $e) {}

// Single instance only — a slow pass must never overlap the next cron tick.
$lock = fopen(sys_get_temp_dir() . '/silverqueen_accrue.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0);
}

$started = microtime(true);
try {
    $engine = new \Ginto\Services\SilverQueenEngine();

    $accrual  = $engine->accrueAll();
    $compound = $engine->compoundAllWallets();
    $engine->stampWorkerRun();

    printf(
        "[%s] SilverQueen: %d allocations scanned, %d credited (\$%.8f); %d wallets scanned, %d compounded (\$%.8f) in %.2fs\n",
        date('Y-m-d H:i:s'),
        $accrual['allocations'], $accrual['credited'], $accrual['amount'],
        $compound['wallets'], $compound['compounded'], $compound['amount'],
        microtime(true) - $started
    );
} catch (\Throwable $e) {
    fwrite(STDERR, '[' . date('Y-m-d H:i:s') . '] SilverQueen worker failed: ' . $e->getMessage() . "\n");
    flock($lock, LOCK_UN);
    exit(1);
}

flock($lock, LOCK_UN);
exit(0);
