<?php
namespace Playground;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

/**
 * PTY WebSocket Server with Session Persistence
 * 
 * Sessions survive WebSocket disconnects and can be rejoined.
 * Query params:
 *   mode=os|sandbox - shell mode
 *   session=<id>    - session ID (optional, will rejoin if exists)
 *   container=<id>  - for sandbox mode, container name
 */
class PtyServer implements MessageComponentInterface
{
    /** @var \SplObjectStorage - Active WebSocket connections */
    private $clients;
    
    /** @var array - Persistent sessions: sessionId => ['process', 'pipes', 'buffer', 'lastActivity', 'conn', 'timer'] */
    private static $sessions = [];
    
    /** Session timeout in seconds (30 minutes) */
    private const SESSION_TIMEOUT = 1800;
    
    /** Max buffer size for replay (50KB) */
    private const BUFFER_MAX_SIZE = 50000;
    
    /** Last cleanup time */
    private static $lastCleanup = 0;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage();
    }
    
    /**
     * Clean up idle sessions periodically
     */
    private function cleanupIdleSessions(): void
    {
        $now = time();
        
        // Only run cleanup every 60 seconds
        if ($now - self::$lastCleanup < 60) {
            return;
        }
        self::$lastCleanup = $now;
        
        foreach (self::$sessions as $sessionId => $session) {
            // Only cleanup sessions without active connections that have timed out
            if (!$session['conn'] && ($now - $session['lastActivity'] > self::SESSION_TIMEOUT)) {
                error_log("[PtyServer] Cleaning up idle session: {$sessionId}");
                $this->destroySession($sessionId);
            }
        }
    }
    
    /**
     * Destroy a session completely
     */
    private function destroySession(string $sessionId): void
    {
        if (!isset(self::$sessions[$sessionId])) {
            return;
        }
        
        $session = self::$sessions[$sessionId];
        
        // Cancel timer
        if (!empty($session['timer'])) {
            try { \React\EventLoop\Loop::get()->cancelTimer($session['timer']); } catch (\Throwable $_) {}
        }
        
        // Close pipes
        if (!empty($session['pipes'])) {
            foreach ($session['pipes'] as $p) {
                try { @fclose($p); } catch (\Throwable $_) {}
            }
        }
        
        // Terminate process
        if (!empty($session['process'])) {
            try { proc_terminate($session['process']); } catch (\Throwable $_) {}
        }
        
        unset(self::$sessions[$sessionId]);
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->cleanupIdleSessions();
        
        // Parse query params from HTTP request if available
        $mode = 'sandbox';
        $container = null;
        $sessionId = null;
        
        try {
            if (isset($conn->httpRequest)) {
                $uri = $conn->httpRequest->getUri();
                parse_str($uri->getQuery() ?? '', $q);
                if (!empty($q['mode'])) $mode = $q['mode'];
                if (!empty($q['container'])) $container = $q['container'];
                if (!empty($q['session'])) $sessionId = $q['session'];
            }
        } catch (\Throwable $_) {}
        
        // Check if we're rejoining an existing session
        if ($sessionId && isset(self::$sessions[$sessionId])) {
            $session = &self::$sessions[$sessionId];
            
            // Check if process is still running
            $status = proc_get_status($session['process']);
            if (!$status['running']) {
                // Process died, destroy session and create new
                $this->destroySession($sessionId);
            } else {
                // Rejoin existing session
                error_log("[PtyServer] Reconnecting to session: {$sessionId}");
                
                // Disconnect any existing connection for this session
                if ($session['conn']) {
                    try { $session['conn']->close(); } catch (\Throwable $_) {}
                }
                
                $session['conn'] = $conn;
                $session['lastActivity'] = time();
                
                $this->clients[$conn] = [
                    'sessionId' => $sessionId
                ];
                
                // Send reconnected message and replay buffer
                $conn->send("\r\n\033[32m*** Reconnected to session {$sessionId} ***\033[0m\r\n");
                if (!empty($session['buffer'])) {
                    $conn->send($session['buffer']);
                }
                
                return;
            }
        }
        
        // Generate new session ID if not provided
        if (!$sessionId) {
            $sessionId = 'session-' . time() . '-' . bin2hex(random_bytes(4));
        }

        // Determine command
        $cmd = null;
        
        if ($mode === 'os') {
            error_log("[PtyServer] OS mode terminal opened, session: {$sessionId}");
        }
        
        if ($mode === 'sandbox' && $container) {
            $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '_', (string)$container);
            
            if (str_starts_with($safe, 'ginto-sandbox-')) {
                $safe = substr($safe, strlen('ginto-sandbox-'));
            }
            
            try {
                if (\Ginto\Helpers\LxdSandboxManager::sandboxExists($safe)) {
                    $containerName = \Ginto\Helpers\LxdSandboxManager::containerName($safe);
                    
                    if (\Ginto\Helpers\LxdSandboxManager::sandboxRunning($safe)) {
                        $lxcPath = '/snap/bin/lxc';
                        if (!file_exists($lxcPath)) $lxcPath = '/usr/bin/lxc';
                        $cmd = ['script', '-q', '-c', "{$lxcPath} exec {$containerName} -- /bin/sh -l", '/dev/null'];
                        error_log("[PtyServer] Sandbox terminal opened for container: {$containerName}, session: {$sessionId}");
                    } else {
                        $conn->send("Sandbox is not running. Please start your sandbox first.\r\n");
                        $conn->close();
                        return;
                    }
                } else {
                    $conn->send("Sandbox not found. Please create your sandbox first.\r\n");
                    $conn->close();
                    return;
                }
            } catch (\Throwable $e) {
                error_log("[PtyServer] Error checking sandbox: " . $e->getMessage());
                $conn->send("Error accessing sandbox: " . $e->getMessage() . "\r\n");
                $conn->close();
                return;
            }
        }
        
        if ($cmd === null) {
            $cmd = ['script','-q','-c','/bin/bash','/dev/null'];
        }

        $homeDir = getenv('HOME') ?: '/home/oliverbob';
        $env = [
            'TERM' => 'xterm-256color',
            'COLUMNS' => '120',
            'LINES' => '30',
            'HOME' => $homeDir,
            'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin',
        ];
        $descriptors = [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']];
        $process = proc_open($cmd, $descriptors, $pipes, $homeDir, $env);
        if (!is_resource($process)) {
            $conn->send("Failed to spawn pty process\n");
            $conn->close();
            return;
        }

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        fwrite($pipes[0], "stty cols 120 rows 30 2>/dev/null; clear\n");
        
        // Create session
        self::$sessions[$sessionId] = [
            'process' => $process,
            'pipes' => $pipes,
            'buffer' => '',
            'lastActivity' => time(),
            'conn' => $conn,
            'timer' => null,
            'mode' => $mode,
            'container' => $container
        ];
        
        $this->clients[$conn] = [
            'sessionId' => $sessionId
        ];
        
        // Send session ID to client
        $conn->send("\033[90m[Session: {$sessionId}]\033[0m\r\n");

        // Set up output polling timer
        $loop = \React\EventLoop\Loop::get();
        $timer = $loop->addPeriodicTimer(0.05, function() use ($sessionId) {
            if (!isset(self::$sessions[$sessionId])) return;
            
            $session = &self::$sessions[$sessionId];
            $pipes = $session['pipes'];
            if (!$pipes) return;
            
            try {
                $out = stream_get_contents($pipes[1]);
                $err = stream_get_contents($pipes[2]);
                $data = $out . $err;
                
                if ($data !== '') {
                    $session['lastActivity'] = time();
                    
                    // Buffer output for replay on reconnect
                    $session['buffer'] .= $data;
                    if (strlen($session['buffer']) > self::BUFFER_MAX_SIZE) {
                        $session['buffer'] = substr($session['buffer'], -self::BUFFER_MAX_SIZE);
                    }
                    
                    // Send to connected client if any
                    if ($session['conn']) {
                        try {
                            $session['conn']->send($data);
                        } catch (\Throwable $_) {
                            // Connection may have closed
                        }
                    }
                }
            } catch (\Throwable $_) {}
        });

        self::$sessions[$sessionId]['timer'] = $timer;
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $clientData = $this->clients[$from] ?? null;
        if (!$clientData || empty($clientData['sessionId'])) return;
        
        $sessionId = $clientData['sessionId'];
        if (!isset(self::$sessions[$sessionId])) return;
        
        $session = &self::$sessions[$sessionId];
        $pipes = $session['pipes'] ?? null;
        if (!$pipes) return;
        
        $session['lastActivity'] = time();
        
        try {
            if (is_string($msg) && strlen($msg) && $msg[0] === '{') {
                $j = json_decode($msg, true);
                if (is_array($j) && !empty($j['type'])) {
                    if ($j['type'] === 'resize' && !empty($j['cols']) && !empty($j['rows'])) {
                        return;
                    }
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
        $clientData = $this->clients[$conn] ?? null;
        
        if ($clientData && !empty($clientData['sessionId'])) {
            $sessionId = $clientData['sessionId'];
            
            if (isset(self::$sessions[$sessionId])) {
                // DON'T destroy the session - just detach the connection
                // The process continues running and user can reconnect
                $session = &self::$sessions[$sessionId];
                if ($session['conn'] === $conn) {
                    $session['conn'] = null;
                }
                $session['lastActivity'] = time();
                error_log("[PtyServer] WebSocket closed, session preserved: {$sessionId}");
            }
        }
        
        if ($this->clients->contains($conn)) {
            $this->clients->detach($conn);
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        try { $conn->send("Error: " . $e->getMessage()); } catch (\Throwable $_) {}
        $this->onClose($conn);
    }
}
