<?php

declare(strict_types=1);

/*
 * SilverQueen Premium, every ten minutes: paid QR codes confirmed; free trials that ended come down (one reminder
 * to the member's bell), held names nobody paid for in 24 more hours are
 * released (their work stays). Cron: /etc/cron.d/ginto-sites-hourly.
 */

define('ROOT_PATH', dirname(__DIR__));
if (!defined('STORAGE_PATH')) {
    define('STORAGE_PATH', dirname(ROOT_PATH) . '/storage');
}
require ROOT_PATH . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(ROOT_PATH)->safeLoad();

$db = Ginto\Core\Database::getInstance();
// Paid QR codes nobody was watching: confirmed, and the site goes live.
foreach ((new Ginto\Sites\Subscriptions($db))->confirmPending() as $live) {
    echo date('c'), " paid and live: {$live}", PHP_EOL;
}
foreach ((new Ginto\Sites\Subscriptions($db))->remindRenewals() as $n) {
    echo date('c'), " renewal reminder: {$n}", PHP_EOL;
}
// Visits to sites, counted as views of the posts they show.
foreach ((new Ginto\Sites\Insights($db))->creditVisits() as $n => $views) {
    echo date('c'), " visits credited: {$n} +{$views}", PHP_EOL;
}
// Search engines: changed sites checked by SilverQueen AI, then opened or closed.
foreach ((new Ginto\Sites\Indexing($db))->tick() as $line) {
    echo date('c'), " indexing: {$line}", PHP_EOL;
}
$r = (new Ginto\Sites\Trial($db))->tick();
if ($r['ended'] !== [] || $r['released'] !== []) {
    echo date('c'), ' trial ended: ', implode(', ', $r['ended']) ?: '-', '; released: ', implode(', ', $r['released']) ?: '-', PHP_EOL;
}
