<?php
/**
 * SilverQueen payment verifier — re-checks submitted USDT (BEP20) transfers against
 * the public BNB Smart Chain nodes and settles the ones that have cleared.
 *
 * Payments are verified once the moment a buyer submits their TxHash; this sweep
 * catches the rest — a transfer submitted before it had enough confirmations, or
 * one checked while the RPC endpoints were unreachable. Run it every minute:
 *
 *   * * * * * cd /home/oliverbob/silverqueen.pro && /usr/bin/php bin/silverqueen_verify.php >> /tmp/silverqueen_verify.log 2>&1
 *
 * Confirming here grants the allocation and pays the referral overrides, exactly as
 * an admin confirmation would — the difference is that the evidence came from the
 * chain. An inconclusive answer never rejects anything, so an RPC outage can only
 * delay an order, never cancel a real payment.
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

// Single instance only — a slow sweep must never overlap the next cron tick.
$lock = fopen(sys_get_temp_dir() . '/silverqueen_verify.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0);
}

$started = microtime(true);
try {
    $engine = new \Ginto\Services\SilverQueenEngine();
    $r = $engine->verifyPending((int) (getenv('SQ_VERIFY_BATCH') ?: 25));

    // Stay quiet when there was nothing to do, so the log stays readable.
    if ($r['checked'] > 0) {
        printf("[%s] SilverQueen verify: %d checked, %d completed, %d rejected, %d still waiting (%.2fs)\n",
            date('Y-m-d H:i:s'), $r['checked'], $r['completed'], $r['rejected'], $r['waiting'],
            microtime(true) - $started);
    }
} catch (\Throwable $e) {
    fwrite(STDERR, '[' . date('Y-m-d H:i:s') . '] SilverQueen verifier failed: ' . $e->getMessage() . "\n");
    flock($lock, LOCK_UN);
    exit(1);
}

flock($lock, LOCK_UN);
exit(0);
