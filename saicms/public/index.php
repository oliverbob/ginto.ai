<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- START: Simple Visitor Logger ---

// Define the log file path. Make sure your web server has permission to write to this file/directory.
// It's best to place logs outside the public web root for security.
$logFile = __DIR__ . '/../../storage/logs/access.log';
$logDirectory = dirname($logFile);

// Create the log directory if it doesn't exist
if (!is_dir($logDirectory)) {
    mkdir($logDirectory, 0755, true);
}

$timestamp = date('Y-m-d H:i:s');

$real_ip        = $_SERVER['REMOTE_ADDR']            ?? 'UNKNOWN_REMOTE_ADDR';
$forwarded_for  = $_SERVER['HTTP_X_FORWARDED_FOR']   ?? 'NOT_SET';
$proto          = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'NOT_SET';
$user_agent     = $_SERVER['HTTP_USER_AGENT']        ?? 'UNKNOWN_AGENT';
$method         = $_SERVER['REQUEST_METHOD']         ?? 'UNKNOWN_METHOD';
$referrer       = $_SERVER['HTTP_REFERER']           ?? 'NO_REFERRER';
$request_uri    = $_SERVER['REQUEST_URI']            ?? 'UNKNOWN_URI';
$host           = $_SERVER['HTTP_HOST']              ?? 'UNKNOWN_HOST';

// Reconstruct the full URL if possible
$full_url = "$proto://$host$request_uri";

// Format log entry
$logEntry = "[$timestamp] Real-IP: $real_ip | X-Forwarded-For: $forwarded_for | Protocol: $proto | Method: $method | Host: $host | URI: $request_uri | Full URL: $full_url | Referrer: $referrer | User-Agent: $user_agent \n";

file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

require_once __DIR__ . '/../vendor/autoload.php';




require_once __DIR__ . '/../config/bootstrap.php';

use Core\Router;

// use Ratchet\Server\IoServer;
// use App\WebSocket\WSController;


$router = new Router();
require_once __DIR__ . '/../routes/web.php';

$router->dispatch($_SERVER['REQUEST_URI']);
