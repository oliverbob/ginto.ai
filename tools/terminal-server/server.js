#!/usr/bin/env node
// Simple PTY -> WebSocket bridge for development
// Usage: set PORT and optionally HOST_ALLOW or leave default 0.0.0.0

const http = require('http');
const url = require('url');
const WebSocket = require('ws');
const pty = require('node-pty');
const { spawnSync } = require('child_process');

const PORT = process.env.GINTO_TERMINAL_PORT ? parseInt(process.env.GINTO_TERMINAL_PORT) : 8081;
const HOST = process.env.GINTO_TERMINAL_HOST || '0.0.0.0';
const ALLOW_REMOTE = process.env.GINTO_TERMINAL_ALLOW_REMOTE === '1';

const server = http.createServer((req, res) => {
  res.writeHead(200);
  res.end('Ginto terminal websocket server');
});

const wss = new WebSocket.Server({ server });

wss.on('connection', function connection(ws, req) {
  // Parse query args
  const u = url.parse(req.url, true);
  const q = u.query || {};
  const mode = (q.mode || 'sandbox'); // 'os' or 'sandbox'
  const container = q.container || null;
  const cols = parseInt(q.cols || '80', 10) || 80;
  const rows = parseInt(q.rows || '24', 10) || 24;

  // Very small safety: only allow remote if explicitly enabled
  const remoteIp = (req.socket && req.socket.remoteAddress) ? req.socket.remoteAddress : null;
  if (remoteIp && !ALLOW_REMOTE && remoteIp !== '::1' && remoteIp !== '127.0.0.1' && remoteIp !== '::ffff:127.0.0.1') {
    ws.send('Connection rejected: remote connections disabled');
    ws.close();
    return;
  }

  let shell = '/bin/bash';
  let args = [];

    if (mode === 'sandbox' && container) {
    // Validate container name very conservatively
    const safeContainer = String(container).replace(/[^a-zA-Z0-9_\-]/g, '_');
    // Check machinectl status for systemd-nspawn machine
    const r = spawnSync('machinectl', ['status', safeContainer]);
    if (r.status === 0) {
      // Use machinectl shell <machine> /bin/sh
      shell = 'machinectl';
      args = ['shell', safeContainer, '/bin/sh'];
    } else {
      // fallback to plain shell
      shell = '/bin/bash'; args = [];
    }
  } else if (mode === 'os') {
    shell = '/bin/bash'; args = [];
  }

  // spawn pty
  const term = pty.spawn(shell, args, {
    name: 'xterm-color',
    cols: cols,
    rows: rows,
    cwd: process.env.HOME,
    env: Object.assign({}, process.env)
  });

  // Send data from pty to client
  term.onData(function(data) {
    try { ws.send(data); } catch (e) {}
  });

  ws.on('message', function incoming(message) {
    // incoming is expected to be raw input or a JSON resize command
    try {
      // try JSON parse for control messages
      const s = message.toString();
      if (s && s[0] === '{') {
        const msg = JSON.parse(s);
        if (msg.type === 'resize' && msg.cols && msg.rows) {
          term.resize(msg.cols, msg.rows);
          return;
        }
        // Handle ping messages (keepalive) - silently ignore, don't write to PTY
        if (msg.type === 'ping') {
          return;
        }
      }
    } catch (e) {}
    try { term.write(message); } catch (e) {}
  });

  ws.on('close', function() {
    try { term.kill(); } catch (e) {}
  });

  ws.on('error', function(){ try{ term.kill(); }catch(e){} });
});

server.listen(PORT, HOST, () => {
  console.log('Ginto PTY server listening on ' + HOST + ':' + PORT);
  if (!process.env.GINTO_TERMINAL_ALLOW_REMOTE) console.log('Remote connections disabled by default. Set GINTO_TERMINAL_ALLOW_REMOTE=1 to allow.');
});
