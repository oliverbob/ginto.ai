<?php

declare(strict_types=1);

/*
 * Builds one SilverQueen Premium free preview, in the background.
 * Started by Ginto\Sites\Previews::spawn; safe to run by hand: php bin/sites-build.php <preview id>
 */

define('ROOT_PATH', dirname(__DIR__));
if (!defined('STORAGE_PATH')) {
    define('STORAGE_PATH', dirname(ROOT_PATH) . '/storage');
}
require ROOT_PATH . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(ROOT_PATH)->safeLoad();

$id = (int) ($argv[1] ?? 0);
if ($id <= 0) {
    fwrite(STDERR, "usage: php bin/sites-build.php <preview id>\n");
    exit(2);
}

(new Ginto\Sites\Previews(Ginto\Core\Database::getInstance()))->build($id);
