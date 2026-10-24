<?php

declare(strict_types=1);

/*
 * SilverQueen Sites, once a day: a month unpaid past its end (plus the grace
 * days) lapses, its site is taken down and its Founding price is gone; QR codes
 * nobody paid in a day are dropped. Cron: /etc/cron.d/ginto-sites-daily.
 */

define('ROOT_PATH', dirname(__DIR__));
if (!defined('STORAGE_PATH')) {
    define('STORAGE_PATH', dirname(ROOT_PATH) . '/storage');
}
require ROOT_PATH . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(ROOT_PATH)->safeLoad();

$lapsed = (new Ginto\Sites\Subscriptions(Ginto\Core\Database::getInstance()))->lapseDue();
echo date('c'), ' lapsed: ', $lapsed === [] ? 'none' : implode(', ', $lapsed), PHP_EOL;
