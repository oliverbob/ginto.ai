<?php
// src/Playground/FileWriteStreamer.php
namespace Playground;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class FileWriteStreamer implements MessageComponentInterface
{
    /** @var \SplObjectStorage */
    protected $clients;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage();
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        // Broadcast incoming message to all other connected clients.
        foreach ($this->clients as $client) {
            if ($client === $from) {
                continue;
            }
            try {
                $client->send($msg);
            } catch (\Exception $e) {
                error_log('FileWriteStreamer send error: ' . $e->getMessage());
                $this->clients->detach($client);
                try {
                    $client->close();
                } catch (\Exception $ex) {
                    // ignore
                }
            }
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        if ($this->clients->contains($conn)) {
            $this->clients->detach($conn);
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        error_log('FileWriteStreamer error: ' . $e->getMessage());
        try {
            $conn->close();
        } catch (\Exception $ex) {
            // ignore
        }
    }
}
