#!/usr/bin/env php
<?php
// bin/chat_server.php - Runner for the WebSocket Chat Server

$projectRoot = dirname(__DIR__);
require $projectRoot . '/vendor/autoload.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Loop;
use App\WebSocket\ChatMessageHandler; // <<<< USE THIS CLASS

// Configuration
$ws_listen_address = getenv('WS_LISTEN_ADDRESS') ?: '0.0.0.0';
$ws_port = (int)(getenv('WS_PORT') ?: 8082);
$zmq_pull_dsn = getenv('ZMQ_PULL_DSN') ?: 'tcp://127.0.0.1:5555'; // Make this null if ZMQ not used/loaded

echo "========================================\n";
echo " SAI Chat WebSocket Server - Runner \n";
echo " (Script: " . __FILE__ . ")\n";
echo "========================================\n";
echo "PHP Version: " . PHP_VERSION . "\n";

$zmqActive = false;
if (extension_loaded('zmq')) {
    $zmqVersion = phpversion('zmq');
    echo "ZMQ PHP Extension Loaded: Version {$zmqVersion}\n";
    $zmqActive = true;
} else {
    echo "\n!!!!!!!!!! WARNING !!!!!!!!!!\n";
    echo "ZMQ PHP Extension is NOT loaded!\n";
    echo "Real-time message updates from HTTP backend will FAIL.\n";
    $zmq_pull_dsn = null; // Don't try to use ZMQ if extension not loaded
    echo "!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!\n\n";
}

if (!class_exists(ChatMessageHandler::class)) {
    echo "ERROR: The ChatMessageHandler class (expected at App\\WebSocket\\ChatMessageHandler) was NOT found.\n";
    exit(1);
}
echo "ChatMessageHandler class found.\n";
echo "----------------------------------------\n";

$loop = Loop::get();
echo "ReactPHP EventLoop obtained.\n";

try {
    // Pass null for ZMQ DSN if ZMQ extension not loaded or not configured
    // The ChatMessageHandler constructor will handle this.
    $chatMessageHandlerInstance = new ChatMessageHandler($loop, $zmqActive ? $zmq_pull_dsn : null);
} catch (\Throwable $e) {
    echo "FATAL ERROR during ChatMessageHandler initialization: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
echo "ChatMessageHandler instance created.\n";

$serverComponent = new HttpServer(new WsServer($chatMessageHandlerInstance));
echo "Ratchet HTTP/WebSocket server components configured.\n";

try {
    $socketServer = new \React\Socket\SocketServer(
        "{$ws_listen_address}:{$ws_port}", [], $loop
    );
} catch (\Throwable $e) {
    echo "FATAL ERROR creating ReactPHP Socket Server: " . $e->getMessage() . "\n";
    exit(1);
}
echo "ReactPHP SocketServer created. Ready to listen on {$ws_listen_address}:{$ws_port}\n";

$ioServer = new IoServer($serverComponent, $socketServer, $loop);
echo "Ratchet IoServer configured.\n";
echo "----------------------------------------\n";
echo "Attempting to run the WebSocket server...\n";
echo "(This will block the terminal. Press Ctrl+C to stop.)\n";

$ioServer->run();

echo "WebSocket server has stopped.\n";
?>