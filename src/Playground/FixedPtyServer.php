<?php
namespace Playground;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class FixedPtyServer implements MessageComponentInterface
{
    /** @var \SplObjectStorage */
    private $clients;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage();
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $mode = 'sandbox'; $container = null;
        try {
            if (isset($conn->httpRequest)) {
                $uri = $conn->httpRequest->getUri();
                parse_str($uri->getQuery() ?? '', $q);
                if (!empty($q['mode'])) $mode = $q['mode'];
                if (!empty($q['container'])) $container = $q['container'];
            }
        } catch (\Throwable $_) {}

        $this->clients->attach($conn, [
            'process' => null,
            'pipes' => null,
            'timer' => null,
            'mode' => $mode,
            'container' => $container
        ]);

        $cmd = null;
        if ($mode === 'sandbox' && $container) {
            $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '_', (string)$container);
            try {
                if (\Ginto\Helpers\SandboxManager::sandboxExists($safe)) {
                    if (\Ginto\Helpers\SandboxManager::containerNameForSandbox($safe)) {
                        $cmd = ['machinectl','shell',$safe,'/bin/sh'];
                    }
                }
            } catch (\Throwable $_) {}
        }
        if ($cmd === null) {
            $cmd = ['script','-q','-c','/bin/bash','/dev/null'];
        }

        // Start terminal in user's home directory to avoid accidental edits to repo
        $homeDir = getenv('HOME') ?: '/home/oliverbob';
        $descriptors = [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']];
        $process = proc_open($cmd, $descriptors, $pipes, $homeDir, null);
        if (!is_resource($process)) {
            try { $conn->send("Failed to spawn pty process\n"); } catch (\Throwable $_) {}
            $conn->close();
            return;
        }

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $this->clients[$conn] = [
            'process' => $process,
            'pipes' => $pipes,
            'timer' => null,
            'mode' => $mode,
            'container' => $container
        ];

        $loop = \React\EventLoop\Loop::get();
        $timer = $loop->addPeriodicTimer(0.05, function() use ($conn) {
            try {
                $state = $this->clients[$conn] ?? null;
                if (!$state) return;
                $pipes = $state['pipes'];
                if (!$pipes) return;
                $out = stream_get_contents($pipes[1]);
                $err = stream_get_contents($pipes[2]);
                if ($out !== '') {
                    $conn->send($out);
                }
                if ($err !== '') {
                    $conn->send($err);
                }
            } catch (\Throwable $_) {}
        });

        $state = $this->clients[$conn] ?? [];
        $state['timer'] = $timer;
        $this->clients[$conn] = $state;
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $state = $this->clients[$from] ?? null;
        $pipes = $state['pipes'] ?? null;
        if (!$pipes) return;
        try {
            if (is_string($msg) && strlen($msg) && $msg[0] === '{') {
                $j = json_decode($msg, true);
                if (is_array($j) && !empty($j['type'])) {
                    // Handle resize messages
                    if ($j['type'] === 'resize' && !empty($j['cols']) && !empty($j['rows'])) {
                        return;
                    }
                    // Handle ping messages (keepalive) - silently ignore, don't write to PTY
                    if ($j['type'] === 'ping') {
                        return;
                    }
                }
            }
        } catch (\Throwable $_) {}

        try { fwrite($pipes[0], $msg); } catch (\Throwable $_) {}
    }

    public function onClose(ConnectionInterface $conn)
    {
        try {
            $state = $this->clients[$conn] ?? null;
            if ($state && !empty($state['timer'])) { \React\EventLoop\Loop::get()->cancelTimer($state['timer']); }
        } catch (\Throwable $_) {}
        try { $state = $this->clients[$conn] ?? null; if ($state && !empty($state['pipes'])) { foreach ($state['pipes'] as $p) @fclose($p); } } catch (\Throwable $_) {}
        try { $state = $this->clients[$conn] ?? null; if ($state && !empty($state['process'])) proc_terminate($state['process']); } catch (\Throwable $_) {}
        try { if ($this->clients->contains($conn)) { $this->clients->detach($conn); } } catch (\Throwable $_) {}
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        try { $conn->send("Error: " . $e->getMessage()); } catch (\Throwable $_) {}
        $this->onClose($conn);
    }
}
