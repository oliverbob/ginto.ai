#!/usr/bin/env node
// Ginto PTY -> WebSocket bridge with session persistence
// Sessions survive WebSocket disconnects and can be rejoined
//
// Query params:
//   mode=os|sandbox - shell mode
//   session=<id>    - session ID (optional, will rejoin if exists)
//   container=<id>  - for sandbox mode, container name
//   cols=<n>        - terminal columns
//   rows=<n>        - terminal rows

const http = require('http');
const url = require('url');
const WebSocket = require('ws');
const pty = require('node-pty');
const { spawnSync } = require('child_process');

const PORT = process.env.GINTO_TERMINAL_PORT ? parseInt(process.env.GINTO_TERMINAL_PORT) : 8081;
const HOST = process.env.GINTO_TERMINAL_HOST || '0.0.0.0';
const ALLOW_REMOTE = process.env.GINTO_TERMINAL_ALLOW_REMOTE === '1';

// Session storage: sessionId -> { term, buffer, lastActivity, mode, container }
const sessions = new Map();
const SESSION_TIMEOUT_MS = 30 * 60 * 1000; // 30 minutes idle timeout
const BUFFER_MAX_SIZE = 50000; // Keep last 50KB of output for replay

// Cleanup idle sessions periodically
setInterval(() => {
  const now = Date.now();
  for (const [sessionId, session] of sessions) {
    if (!session.ws && (now - session.lastActivity > SESSION_TIMEOUT_MS)) {
      console.log('Cleaning up idle session:', sessionId);
      try { session.term.kill(); } catch (e) {}
      sessions.delete(sessionId);
    }
  }
}, 60000); // Check every minute

const server = http.createServer((req, res) => {
  res.writeHead(200);
  res.end('Ginto terminal websocket server (with session persistence)');
});

const wss = new WebSocket.Server({ server });

wss.on('connection', function connection(ws, req) {
  const u = url.parse(req.url, true);
  const q = u.query || {};
  const mode = (q.mode || 'sandbox');
  const container = q.container || null;
  const cols = parseInt(q.cols || '80', 10) || 80;
  const rows = parseInt(q.rows || '24', 10) || 24;
  let sessionId = q.session || null;

  // Security check
  const remoteIp = (req.socket && req.socket.remoteAddress) ? req.socket.remoteAddress : null;
  if (remoteIp && !ALLOW_REMOTE && remoteIp !== '::1' && remoteIp !== '127.0.0.1' && remoteIp !== '::ffff:127.0.0.1') {
    ws.send('Connection rejected: remote connections disabled');
    ws.close();
    return;
  }

  // Check if we're rejoining an existing session
  if (sessionId && sessions.has(sessionId)) {
    const session = sessions.get(sessionId);
    
    // Disconnect any existing WebSocket for this session
    if (session.ws) {
      try { session.ws.close(); } catch (e) {}
    }
    
    session.ws = ws;
    session.lastActivity = Date.now();
    
    // Replay buffered output
    if (session.buffer) {
      ws.send('\x1b[32m*** Reconnected to session ' + sessionId + ' ***\x1b[0m\r\n');
      ws.send(session.buffer);
    }
    
    // Resize if needed
    try { session.term.resize(cols, rows); } catch (e) {}
    
    // Set up message handler for this websocket
    ws.on('message', function incoming(message) {
      session.lastActivity = Date.now();
      try {
        const s = message.toString();
        if (s && s[0] === '{') {
          const msg = JSON.parse(s);
          if (msg.type === 'resize' && msg.cols && msg.rows) {
            session.term.resize(msg.cols, msg.rows);
            return;
          }
          if (msg.type === 'ping') {
            return;
          }
        }
      } catch (e) {}
      try { session.term.write(message); } catch (e) {}
    });
    
    ws.on('close', function() {
      // Don't kill the PTY, just detach the WebSocket
      if (session.ws === ws) {
        session.ws = null;
      }
      session.lastActivity = Date.now();
    });
    
    ws.on('error', function() {
      if (session.ws === ws) {
        session.ws = null;
      }
    });
    
    return;
  }

  // Create new session
  sessionId = sessionId || ('session-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9));
  
  let shell = '/bin/bash';
  let args = [];

  if (mode === 'sandbox' && container) {
    const safeContainer = String(container).replace(/[^a-zA-Z0-9_\-]/g, '_');
    const lxcContainer = safeContainer.startsWith('ginto-sandbox-') ? safeContainer : 'ginto-sandbox-' + safeContainer;
    
    // Detect sandbox backend: Docker or LXD
    // Check SANDBOX_MODE env var first, then auto-detect
    const sandboxMode = process.env.SANDBOX_MODE || 'auto';
    let useDocker = false;
    
    if (sandboxMode === 'docker') {
      useDocker = true;
    } else if (sandboxMode === 'lxd') {
      useDocker = false;
    } else {
      // Auto-detect: check if running inside Docker (/.dockerenv exists) or if docker is available
      const fs = require('fs');
      const inDocker = fs.existsSync('/.dockerenv');
      if (inDocker) {
        useDocker = true;
      } else {
        // Check if LXC is available
        const lxcCheck = spawnSync('which', ['/snap/bin/lxc'], { encoding: 'utf8' });
        if (lxcCheck.status !== 0) {
          // LXC not available, try Docker
          useDocker = true;
        }
      }
    }
    
    if (useDocker) {
      // Docker mode - use docker exec
      const dockerCheck = spawnSync('docker', ['inspect', '--format', '{{.State.Running}}', lxcContainer], { encoding: 'utf8' });
      if (dockerCheck.status === 0 && dockerCheck.stdout && dockerCheck.stdout.trim() === 'true') {
        shell = 'docker';
        args = ['exec', '-it', lxcContainer, '/bin/bash'];
        // Fall back to /bin/sh if bash not available
      } else {
        shell = '/bin/bash';
        args = ['-c', 'echo "Sandbox not found. Please create your sandbox first." && exit 1'];
      }
    } else {
      // LXD mode - use lxc exec
      const r = spawnSync('/snap/bin/lxc', ['info', lxcContainer], { encoding: 'utf8' });
      if (r.status === 0 && r.stdout && r.stdout.includes('Status: RUNNING')) {
        shell = '/snap/bin/lxc';
        args = ['exec', lxcContainer, '--', '/bin/sh'];
      } else {
        shell = '/bin/bash';
        args = ['-c', 'echo "Sandbox container not running. Please start your sandbox first." && exit 1'];
      }
    }
  } else if (mode === 'os') {
    // Admin OS mode: use nsenter to access the host (LXC) namespace
    // This breaks out of the Docker container to the LXC host
    // Requires pid: host and privileged: true in docker-compose
    const fs = require('fs');
    const inDocker = fs.existsSync('/.dockerenv');
    if (inDocker) {
      shell = 'nsenter';
      args = ['-t', '1', '-m', '-u', '-i', '-n', '-p', '--', '/bin/bash'];
    } else {
      shell = '/bin/bash';
      args = [];
    }
  }

  // Spawn PTY
  const term = pty.spawn(shell, args, {
    name: 'xterm-color',
    cols: cols,
    rows: rows,
    cwd: process.env.HOME,
    env: Object.assign({}, process.env)
  });

  const session = {
    term: term,
    ws: ws,
    buffer: '',
    lastActivity: Date.now(),
    mode: mode,
    container: container
  };
  
  sessions.set(sessionId, session);
  
  // Send session ID to client so it can reconnect
  ws.send('\x1b[90m[Session: ' + sessionId + ']\x1b[0m\r\n');

  // Send data from PTY to client and buffer it
  term.onData(function(data) {
    session.lastActivity = Date.now();
    
    // Buffer output for replay on reconnect
    session.buffer += data;
    if (session.buffer.length > BUFFER_MAX_SIZE) {
      session.buffer = session.buffer.slice(-BUFFER_MAX_SIZE);
    }
    
    if (session.ws && session.ws.readyState === WebSocket.OPEN) {
      try { session.ws.send(data); } catch (e) {}
    }
  });
  
  // Handle PTY exit
  term.onExit(function() {
    console.log('Session PTY exited:', sessionId);
    if (session.ws) {
      try { session.ws.send('\r\n\x1b[33m*** Process exited ***\x1b[0m\r\n'); } catch (e) {}
    }
    // Keep session for a bit so user can see exit message
    setTimeout(() => {
      sessions.delete(sessionId);
    }, 5000);
  });

  ws.on('message', function incoming(message) {
    session.lastActivity = Date.now();
    try {
      const s = message.toString();
      if (s && s[0] === '{') {
        const msg = JSON.parse(s);
        if (msg.type === 'resize' && msg.cols && msg.rows) {
          term.resize(msg.cols, msg.rows);
          return;
        }
        if (msg.type === 'ping') {
          return;
        }
      }
    } catch (e) {}
    try { term.write(message); } catch (e) {}
  });

  ws.on('close', function() {
    // Don't kill the PTY, just detach the WebSocket
    if (session.ws === ws) {
      session.ws = null;
    }
    session.lastActivity = Date.now();
    console.log('WebSocket disconnected, session preserved:', sessionId);
  });

  ws.on('error', function() {
    if (session.ws === ws) {
      session.ws = null;
    }
  });
});

server.listen(PORT, HOST, () => {
  console.log('Ginto PTY server listening on ' + HOST + ':' + PORT + ' (with session persistence)');
  if (!process.env.GINTO_TERMINAL_ALLOW_REMOTE) console.log('Remote connections disabled by default. Set GINTO_TERMINAL_ALLOW_REMOTE=1 to allow.');
});
