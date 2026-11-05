<?php

declare(strict_types=1);

/*
 * SilverQueen Premium, Monday mornings: each active site's week in its owner's
 * bell (Ginto\Sites\Insights::weekly). Cron: /etc/cron.d/ginto-sites-weekly.
 */

define('ROOT_PATH', dirname(__DIR__));
if (!defined('STORAGE_PATH')) {
    define('STORAGE_PATH', dirname(ROOT_PATH) . '/storage');
}
require ROOT_PATH . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(ROOT_PATH)->safeLoad();

$sent = (new Ginto\Sites\Insights(Ginto\Core\Database::getInstance()))->weekly();
echo date('c'), ' insights: ', $sent === [] ? 'none' : implode(', ', $sent), PHP_EOL;
