<?php
/**
 * Ginto Trading Bot — server-side runner (persistence across restarts).
 *
 * Runs one burst of strategy steps while the bot is enabled, then exits. Meant to
 * be launched every minute by cron; an flock prevents overlapping instances, and
 * enabled-state is re-checked each loop so Stop takes effect within seconds:
 *
 *   * * * * * cd /home/oliverbob/ginto.ai && /usr/bin/php bin/gtb_bot.php >> /tmp/gtb_bot.log 2>&1
 *
 * Open positions live in the DB and the enabled flag is persisted, so after a
 * reboot cron relaunches this and the bot resumes managing exactly where it left off.
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

// Single instance only.
$lock = fopen(sys_get_temp_dir() . '/gtb_bot.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0); // another run is active
}

$intervalSec = (int) (getenv('GTB_STEP_SECONDS') ?: 15);
if ($intervalSec < 5) $intervalSec = 5;
$deadline = time() + 55; // finish before cron relaunches

try {
    $state = new \Ginto\Models\GtbBotState();
    do {
        if (!$state->isEnabled()) {
            break; // Stop pressed (or never started)
        }
        try {
            $res = (new \Ginto\Services\GtbStrategy())->step($state->isArmLive());
            $state->markRun($res['action'] ?? '');
            fwrite(STDOUT, date('c') . '  ' . ($res['action'] ?? '') . "\n");
        } catch (\Throwable $e) {
            fwrite(STDERR, date('c') . '  STEP ERROR: ' . $e->getMessage() . "\n");
        }
        if (time() + $intervalSec >= $deadline) break;
        sleep($intervalSec);
    } while (true);
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
