#!/usr/bin/env node
/**
 * Stealth Browser HTTP Server
 * 
 * Wraps stealth-browser.js as an HTTP API so it can be called from PHP Docker container.
 * Runs on the LXC host where Node.js + Chromium are installed.
 * 
 * Usage: node stealth-server.cjs --port 8889 [--headed]
 * 
 * Options:
 *   --headed    Run Chrome in headed mode using Xvfb virtual display
 *               This helps bypass Cloudflare Turnstile detection
 * 
 * API:
 *   POST /execute
 *   Body: { operation: "fetch|click|fillForm|screenshot|generateRaphael", params: {...} }
 *   Response: JSON result from stealth-browser.js
 */

const http = require('http');
const { spawn, execSync } = require('child_process');
const path = require('path');

const PORT = parseInt(process.argv.find(a => a.startsWith('--port='))?.split('=')[1] || '8889');
const USE_HEADED = process.argv.includes('--headed');
const SCRIPT_PATH = path.join(__dirname, 'stealth-browser.js');

let xvfbProcess = null;
let xvfbDisplay = ':99';

/**
 * Start Xvfb virtual display for headed mode
 */
function startXvfb() {
  if (!USE_HEADED) return null;
  
  // Check if Xvfb is already running on :99
  try {
    execSync('pgrep -f "Xvfb :99"', { stdio: 'pipe' });
    console.log('Xvfb already running on :99');
    return null;
  } catch {
    // Not running, start it
  }
  
  console.log('Starting Xvfb virtual display on :99...');
  const proc = spawn('Xvfb', [':99', '-screen', '0', '1920x1080x24', '-ac'], {
    detached: true,
    stdio: 'ignore',
  });
  proc.unref();
  
  // Give Xvfb time to start
  execSync('sleep 1');
  console.log('Xvfb started');
  return proc;
}

/**
 * Execute stealth-browser.js with the given payload
 */
function executeStealthBrowser(payload) {
  return new Promise((resolve, reject) => {
    const base64Input = Buffer.from(JSON.stringify(payload)).toString('base64');
    
    // Set environment for headed/headless mode
    const env = { ...process.env };
    let cmd, args;
    
    if (USE_HEADED) {
      // Use xvfb-run to wrap the command - it handles DISPLAY automatically
      cmd = 'xvfb-run';
      args = ['-a', '--server-args=-screen 0 1920x1080x24 -ac', 'node', SCRIPT_PATH, base64Input];
      env.USE_HEADED = '1';
    } else {
      cmd = 'node';
      args = [SCRIPT_PATH, base64Input];
      env.DISPLAY = '';
    }
    
    const proc = spawn(cmd, args, {
      cwd: __dirname,
      env,
    });
    
    let stdout = '';
    let stderr = '';
    
    proc.stdout.on('data', (data) => {
      stdout += data.toString();
    });
    
    proc.stderr.on('data', (data) => {
      stderr += data.toString();
      console.error('[stealth-browser stderr]', data.toString());
    });
    
    proc.on('close', (code) => {
      console.log(`[stealth-browser] exited with code ${code}`);
      
      // Parse the last JSON line from stdout
      const lines = stdout.trim().split('\n').filter(l => l.trim());
      let result = null;
      
      for (let i = lines.length - 1; i >= 0; i--) {
        const line = lines[i].trim();
        if (line.startsWith('{')) {
          try {
            result = JSON.parse(line);
            break;
          } catch (e) {
            // Not valid JSON, continue
          }
        }
      }
      
      if (result) {
        resolve(result);
      } else if (code !== 0) {
        reject(new Error(`stealth-browser exited with code ${code}: ${stderr}`));
      } else {
        reject(new Error('No valid JSON output from stealth-browser'));
      }
    });
    
    proc.on('error', (err) => {
      reject(err);
    });
    
    // Timeout after 3 minutes
    setTimeout(() => {
      proc.kill();
      reject(new Error('stealth-browser timeout after 180s'));
    }, 180000);
  });
}

/**
 * HTTP request handler
 */
async function handleRequest(req, res) {
  // CORS headers
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type');
  
  if (req.method === 'OPTIONS') {
    res.writeHead(204);
    res.end();
    return;
  }
  
  if (req.method !== 'POST' || req.url !== '/execute') {
    res.writeHead(404, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ success: false, error: 'Not found. Use POST /execute' }));
    return;
  }
  
  // Read body
  let body = '';
  for await (const chunk of req) {
    body += chunk;
  }
  
  let payload;
  try {
    payload = JSON.parse(body);
  } catch (e) {
    res.writeHead(400, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ success: false, error: 'Invalid JSON body' }));
    return;
  }
  
  if (!payload.operation) {
    res.writeHead(400, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ success: false, error: 'Missing "operation" field' }));
    return;
  }
  
  console.log(`[${new Date().toISOString()}] Executing: ${payload.operation} (headed=${USE_HEADED})`);
  
  try {
    const result = await executeStealthBrowser(payload);
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify(result));
  } catch (err) {
    console.error(`[${new Date().toISOString()}] Error:`, err.message);
    res.writeHead(500, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ success: false, error: err.message }));
  }
}

// Start Xvfb if in headed mode (but we'll use xvfb-run instead for reliability)
// if (USE_HEADED) {
//   xvfbProcess = startXvfb();
// }

// Create server
const server = http.createServer(handleRequest);

server.listen(PORT, '0.0.0.0', () => {
  console.log(`Stealth Browser Server listening on port ${PORT}`);
  console.log(`Mode: ${USE_HEADED ? 'HEADED (Xvfb)' : 'HEADLESS'}`);
  console.log(`API: POST http://localhost:${PORT}/execute`);
  console.log(`Script: ${SCRIPT_PATH}`);
});

// Graceful shutdown
process.on('SIGINT', () => {
  console.log('\nShutting down...');
  if (xvfbProcess) {
    xvfbProcess.kill();
  }
  server.close(() => process.exit(0));
});
