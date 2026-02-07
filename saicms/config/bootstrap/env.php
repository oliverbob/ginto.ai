<?php

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

foreach ($_ENV as $key => $value) {
    putenv("$key=$value");
}

$dotenv->required([
    'APP_NAME',
    'APP_ENV',
    'APP_URL',
    'DB_HOST',
    'DB_NAME',
    'DB_USER',
    'DB_PASS'
]);

// Optional: Default APP_DEBUG to false if not set
$debug = $_ENV['APP_DEBUG'] ?? 'false';

if (filter_var($debug, FILTER_VALIDATE_BOOLEAN)) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}
