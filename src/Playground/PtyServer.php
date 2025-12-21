<?php
namespace Playground;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class PtyServer implements MessageComponentInterface
{
    /** @var \SplObjectStorage */
    private $clients;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage();
    }

    public function onOpen(ConnectionInterface $conn)
    {
        // Parse query params from HTTP request if available
        $mode = 'sandbox'; $container = null;
        try {
            if (isset($conn->httpRequest)) {
                $uri = $conn->httpRequest->getUri();
                parse_str($uri->getQuery() ?? '', $q);
                if (!empty($q['mode'])) $mode = $q['mode'];
                if (!empty($q['container'])) $container = $q['container'];
            }
        } catch (\Throwable $_) {}

        $this->clients[$conn] = [
            'process' => null,
            'pipes' => null,
            'timer' => null,
            'mode' => $mode,
            'container' => $container
        ];

        // Determine command
        $cmd = null;
        if ($mode === 'sandbox' && $container) {
            // Map container id to safe name
            $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '_', (string)$container);
            // Check whether a machine exists using SandboxManager abstraction
            try {
                if (\Ginto\Helpers\SandboxManager::sandboxExists($safe)) {
                    // Use machinectl shell for systemd-nspawn machines
                    if (\Ginto\Helpers\SandboxManager::containerNameForSandbox($safe)) {
                        // machinectl shell <name> /bin/sh
                        $cmd = ['machinectl','shell',$safe,'/bin/sh'];
                    }
                }
            } catch (\Throwable $_) {
                // ignore and fall back to local shell
            }
        }
        if ($cmd === null) {
            // Fallback to allocating a pty via `script` and launching bash
            // script -q -c '/bin/bash' /dev/null
            $cmd = ['script','-q','-c','/bin/bash','/dev/null'];
        }

        $descriptors = [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']];
        $process = proc_open($cmd, $descriptors, $pipes, null, null);
        if (!is_resource($process)) {
            $conn->send("Failed to spawn pty process\n");
            $conn->close();
            return;
        }

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $state = $this->clients[$conn];
        $state['process'] = $process;
        $state['pipes'] = $pipes;
        $this->clients[$conn] = $state;

        // Use React loop to poll stdout/err
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
        // support JSON control messages (resize, ping)
        $state = $this->clients[$from] ?? null;
        $pipes = $state['pipes'] ?? null;
        if (!$pipes) return;
        try {
            if (is_string($msg) && strlen($msg) && $msg[0] === '{') {
                $j = json_decode($msg, true);
                if (is_array($j) && !empty($j['type'])) {
                    // Handle resize messages
                    if ($j['type'] === 'resize' && !empty($j['cols']) && !empty($j['rows'])) {
                        // we don't have a direct pty resize API here; ignore or implement via external tools
                        return;
                    }
                    // Handle ping messages (keepalive) - silently ignore, don't write to PTY
                    if ($j['type'] === 'ping') {
                        return;
                    }
                }
            }
        } catch (\Throwable $_) {}

        // write raw to stdin
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
