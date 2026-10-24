<?php

declare(strict_types=1);

/*
 * SilverQueen Sites, every hour: free trials that ended come down (one reminder
 * to the member's bell), held names nobody paid for in 24 more hours are
 * released (their work stays). Cron: /etc/cron.d/ginto-sites-hourly.
 */

define('ROOT_PATH', dirname(__DIR__));
if (!defined('STORAGE_PATH')) {
    define('STORAGE_PATH', dirname(ROOT_PATH) . '/storage');
}
require ROOT_PATH . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(ROOT_PATH)->safeLoad();

$r = (new Ginto\Sites\Trial(Ginto\Core\Database::getInstance()))->tick();
if ($r['ended'] !== [] || $r['released'] !== []) {
    echo date('c'), ' trial ended: ', implode(', ', $r['ended']) ?: '-', '; released: ', implode(', ', $r['released']) ?: '-', PHP_EOL;
}
