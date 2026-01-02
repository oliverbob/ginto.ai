<?php
// bin/start_rachet_stream.php
require __DIR__ . '/../vendor/autoload.php';

use Playground\FileWriteStreamer;
use Playground\PtyServer;
use Ratchet\App;

$port = 31827;

// Use 'localhost' as httpHost but bind to 0.0.0.0 for all interfaces
// The array('*') allows connections from any origin
// Routes match regardless of Host header when using 'localhost' httpHost
$app = new App('localhost', $port, '0.0.0.0');
$app->route('/stream', new FileWriteStreamer(), array('*'));
$app->route('/terminal', new PtyServer(), array('*'));

echo "Ratchet WebSocket server started on port $port\n";
$app->run();
