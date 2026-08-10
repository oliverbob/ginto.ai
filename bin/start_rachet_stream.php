<?php
// bin/start_rachet_stream.php
require __DIR__ . '/../vendor/autoload.php';

use Playground\FileWriteStreamer;
use Playground\LtradingServer;
use Playground\PtyServer;
use Playground\MessengerServer;
use Playground\MallNotifyServer;
use Ratchet\App;
use React\EventLoop\Factory as LoopFactory;

$port = 31827;

// The loop is created here rather than left to Ratchet so it can be handed to
// components that need to do work on a timer instead of only in response to a
// client. LtradingServer is the reason: its whole purpose is to compute the
// trading state once per tick and fan it out, which is work that belongs to the
// loop, not to any one connection.
$loop = LoopFactory::create();

// Use 'localhost' as httpHost but bind to 0.0.0.0 for all interfaces
// The array('*') allows connections from any origin
// Routes match regardless of Host header when using 'localhost' httpHost
$app = new App('localhost', $port, '0.0.0.0', $loop);
$app->route('/stream', new FileWriteStreamer(), array('*'));
$app->route('/terminal', new PtyServer(), array('*'));
$app->route('/messenger', new MessengerServer(), array('*'));
$app->route('/mall-notify', new MallNotifyServer(), array('*'));

$ltrading = new LtradingServer();
$ltrading->attach($loop);
$app->route('/ltrading', $ltrading, array('*'));

echo "Ratchet WebSocket server started on port $port\n";
echo "  - /stream      : File write streaming\n";
echo "  - /terminal    : PTY terminal\n";
echo "  - /messenger   : Member messaging\n";
echo "  - /mall-notify : Mall push notifications & GPS\n";
echo "  - /ltrading    : Live trading stream (one tick, fanned out)\n";
$app->run();
