<?php
// bin/start_rachet_stream_fixed.php
require __DIR__ . '/../vendor/autoload.php';

use Playground\FixedFileWriteStreamer;
use Playground\FixedPtyServer;
use Ratchet\App;

$port = 31827;

$app = new App('127.0.0.1', $port, '0.0.0.0');
$app->route('/stream', new FixedFileWriteStreamer(), array('*'));
$app->route('/terminal', new FixedPtyServer(), array('*'));

echo "Ratchet WebSocket server (fixed) started on port $port\n";
$app->run();
