<?php
// src/Playground/FixedFileWriteStreamer.php
namespace Playground;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class FixedFileWriteStreamer implements MessageComponentInterface {
    /** @var \SplObjectStorage */
    private $clients;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage();
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        try { $conn->send(json_encode(['type' => 'connected', 'ts' => time()])); } catch (\Throwable $_) {}
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        foreach ($this->clients as $client) {
            if ($from === $client) continue;
            try {
                $client->send($msg);
            } catch (\Throwable $e) {
                try { $client->close(); } catch (\Throwable $_) {}
                try { $this->clients->detach($client); } catch (\Throwable $_) {}
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        try { if ($this->clients->contains($conn)) $this->clients->detach($conn); } catch (\Throwable $_) {}
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        try { $conn->send(json_encode(['type' => 'error', 'message' => $e->getMessage()])); } catch (\Throwable $_) {}
        try { $conn->close(); } catch (\Throwable $_) {}
        try { if ($this->clients->contains($conn)) $this->clients->detach($conn); } catch (\Throwable $_) {}
    }
}
