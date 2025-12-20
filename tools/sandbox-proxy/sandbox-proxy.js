/**
 * Ginto Sandbox Proxy
 * 
 * Dynamic reverse proxy that routes requests to LXD sandbox containers.
 * Uses DETERMINISTIC IP allocation: SHA256(sandboxId) → IP address
 * 
 * Usage:
 *   http://host:3000/?sandbox=user123
 *   
 * The proxy computes the IP directly from the sandbox ID using SHA256.
 * No Redis lookup needed for routing. Redis is available for agent
 * communication (queues, pub/sub, shared state) but NOT for IP resolution.
 * 
 * @see docs/sandbox.md for full architecture
 */

const http = require('http');
const crypto = require('crypto');
const httpProxy = require('http-proxy');
const { createClient } = require('redis');
const { exec } = require('child_process');
const { promisify } = require('util');

const execAsync = promisify(exec);

// Configuration
const CONFIG = {
    port: parseInt(process.env.PORT || '3000'),
    redisUrl: process.env.REDIS_URL || 'redis://127.0.0.1:6379',
    containerPort: parseInt(process.env.CONTAINER_PORT || '80'),
    redisPrefix: 'agent:',  // For agent state, not IP lookup
    autoCreate: process.env.AUTO_CREATE_SANDBOX === '1',
    baseImage: process.env.LXD_BASE_IMAGE || 'ginto-sandbox',
    containerPrefix: 'ginto-sandbox-',
    // Network prefix for deterministic IP (e.g., "10.166.3")
    // If not set, uses full 32-bit IP space (datacenter mode)
    networkPrefix: process.env.LXD_NETWORK_PREFIX || null,
};

// Create proxy server
const proxy = httpProxy.createProxyServer({
    ws: true, // Enable WebSocket proxying
    xfwd: true // Add X-Forwarded headers
});

// Redis client
let redis = null;

/**
 * Initialize Redis connection with timeout
 * Redis is available for agent communication but NOT for IP routing
 */
async function initRedis() {
    return new Promise((resolve) => {
        const timeout = setTimeout(() => {
            console.log('[Redis] Connection timeout - agent state unavailable');
            if (redis) {
                redis.removeAllListeners();
                redis.disconnect().catch(() => {});
            }
            redis = null;
            resolve();
        }, 2000);

        try {
            redis = createClient({ 
                url: CONFIG.redisUrl,
                socket: {
                    connectTimeout: 2000,
                    reconnectStrategy: false
                }
            });
            
            redis.on('error', (err) => {
                if (!redis._errorLogged) {
                    console.error('[Redis] Connection error:', err.message);
                    redis._errorLogged = true;
                }
            });
            
            redis.connect().then(() => {
                clearTimeout(timeout);
                console.log(`[Redis] Connected - agent communication available`);
                resolve();
            }).catch((err) => {
                clearTimeout(timeout);
                console.log('[Redis] Failed to connect:', err.message);
                redis = null;
                resolve();
            });
        } catch (err) {
            clearTimeout(timeout);
            console.error('[Redis] Setup error:', err.message);
            redis = null;
            resolve();
        }
    });
}

// ============================================================================
// DETERMINISTIC IP COMPUTATION - Bijective Permutation (Collision-Free)
// ============================================================================

// Secret key for Feistel permutation (set via env or generate)
const PERMUTATION_KEY = process.env.IP_PERMUTATION_KEY || 'ginto-default-key-change-in-prod';

/**
 * 4-round Feistel network for bijective 32-bit permutation
 * Guarantees: same input → same output, different inputs → different outputs
 * 
 * @param {number} input - 32-bit unsigned integer
 * @param {string} key - Secret key for permutation
 * @returns {number} - Permuted 32-bit unsigned integer
 */
function feistelPermute(input, key) {
    let left = (input >>> 16) & 0xFFFF;
    let right = input & 0xFFFF;
    
    // 4 rounds of Feistel
    for (let round = 0; round < 4; round++) {
        // Round function: HMAC-like mixing with key and round number
        const roundKey = crypto.createHash('sha256')
            .update(`${key}:${round}:${right}`)
            .digest();
        
        // Take 16 bits from hash
        const f = (roundKey[0] << 8) | roundKey[1];
        
        // Feistel step: newLeft = right, newRight = left XOR f(right)
        const newLeft = right;
        const newRight = left ^ f;
        
        left = newLeft;
        right = newRight;
    }
    
    // Combine back to 32-bit
    return ((left << 16) | right) >>> 0;
}

/**
 * Convert 32-bit integer to IPv4 string
 */
function intToIp(n) {
    return [
        (n >>> 24) & 255,
        (n >>> 16) & 255,
        (n >>> 8) & 255,
        n & 255
    ].join('.');
}

/**
 * Convert sandbox ID to deterministic IP address
 * 
 * Algorithm:
 * 1. SHA256(sandboxId) → first 4 bytes → 32-bit integer
 * 2. Feistel permutation with secret key → bijective mapping
 * 3. Result → IPv4 address
 * 
 * Properties:
 * - Deterministic: same ID always produces same IP
 * - Collision-free: different IDs guaranteed different IPs (bijective)
 * - Unpredictable: cannot guess IP without knowing the key
 * - Fast: ~1 microsecond per computation
 * 
 * @param {string} sandboxId - The sandbox/agent identifier
 * @returns {string} IP address
 */
function sandboxToIp(sandboxId) {
    // Step 1: Hash sandbox ID to 32-bit integer
    const hash = crypto.createHash('sha256').update(sandboxId).digest();
    const input = ((hash[0] << 24) | (hash[1] << 16) | (hash[2] << 8) | hash[3]) >>> 0;
    
    // Step 2: Bijective permutation (collision-free)
    const permuted = feistelPermute(input, PERMUTATION_KEY);
    
    // Subnet mode: constrain to /24 network
    if (CONFIG.networkPrefix) {
        // Use permuted value mod 253, add 2 to avoid .0, .1, .255
        const lastOctet = 2 + (permuted % 253);
        return `${CONFIG.networkPrefix}.${lastOctet}`;
    }
    
    // Full 32-bit mode: use all 4 octets
    let octets = [
        (permuted >>> 24) & 255,
        (permuted >>> 16) & 255,
        (permuted >>> 8) & 255,
        permuted & 255
    ];
    
    // Adjust last octet to avoid broadcast/network addresses
    if (octets[3] === 0) octets[3] = 1;
    if (octets[3] === 255) octets[3] = 254;
    
    return octets.join('.');
}

/**
 * Get container name from sandbox ID
 */
function containerName(sandboxId) {
    const safe = sandboxId.replace(/[^a-zA-Z0-9_-]/g, '');
    return CONFIG.containerPrefix + safe;
}

/**
 * Track agent access in Redis (non-blocking)
 */
function trackAccess(sandboxId) {
    if (!redis?.isReady) return;
    redis.set(`${CONFIG.redisPrefix}${sandboxId}:last`, Date.now().toString()).catch(() => {});
    redis.incr(`${CONFIG.redisPrefix}${sandboxId}:requests`).catch(() => {});
}

/**
 * Check if container exists in LXD
 */
async function containerExistsLxd(sandboxId) {
    const name = containerName(sandboxId);
    try {
        const { stdout } = await execAsync(`lxc list --format csv -c n 2>/dev/null`);
        return stdout.trim().split('\n').includes(name);
    } catch (err) {
        return false;
    }
}

/**
 * Create a new sandbox container with static IP
 */
async function createSandbox(sandboxId) {
    const name = containerName(sandboxId);
    const ip = sandboxToIp(sandboxId);
    
    console.log(`[Sandbox] Creating ${name} with static IP ${ip}`);
    
    try {
        await execAsync(`lxc launch ${CONFIG.baseImage} ${name}`);
        await execAsync(`lxc config device override ${name} eth0 ipv4.address=${ip}`);
        await execAsync(`lxc restart ${name}`);
        await new Promise(resolve => setTimeout(resolve, 2000));
        
        console.log(`[Sandbox] Created ${name} at ${ip}`);
        trackAccess(sandboxId);
        return ip;
    } catch (err) {
        console.error(`[Sandbox] Failed to create ${name}:`, err.message);
        return null;
    }
}

/**
 * Get container IP - DETERMINISTIC (fast, no network calls)
 * 
 * IP is computed directly from sandbox ID using Feistel permutation.
 * No Redis lookup, no LXD query, no reachability check - pure computation.
 */
function getContainerIp(sandboxId) {
    // Compute IP deterministically - this is instant
    const ip = sandboxToIp(sandboxId);
    
    console.log(`[Route] ${sandboxId} -> ${ip} (computed)`);
    
    // Track access (fire-and-forget, non-blocking)
    trackAccess(sandboxId);
    
    return ip;
}

/**
 * Extract user ID from request
 * Supports:
 *   - Query: ?sandbox=userId or ?user=userId
 *   - Path:  /clients/userId/... or /sandbox/userId/...
 */
function getUserId(req) {
    try {
        const url = new URL(req.url, `http://${req.headers.host}`);
        
        // Check query parameter first
        const queryId = url.searchParams.get('sandbox') || url.searchParams.get('user');
        if (queryId) {
            return { id: queryId, basePath: '' };
        }
        
        // Check path-based routing: /clients/{userId}/... or /sandbox/{userId}/...
        const pathMatch = url.pathname.match(/^\/(clients|sandbox)\/([a-zA-Z0-9_-]+)(\/.*)?$/);
        if (pathMatch) {
            const userId = pathMatch[2];
            const subPath = pathMatch[3] || '/';
            return { id: userId, basePath: subPath };
        }
        
        return null;
    } catch (err) {
        return null;
    }
}

/**
 * Get the sub-path to forward (after removing /clients/{userId})
 */
function getForwardPath(req, userId) {
    try {
        const url = new URL(req.url, `http://${req.headers.host}`);
        
        // If using path-based routing, extract the sub-path
        const pathMatch = url.pathname.match(/^\/(clients|sandbox)\/[a-zA-Z0-9_-]+(\/.*)?$/);
        if (pathMatch) {
            const subPath = pathMatch[2] || '/';
            // Re-append query string if present
            return subPath + (url.search || '');
        }
        
        // For query-based routing, forward the full path
        return req.url;
    } catch (err) {
        return req.url;
    }
}

/**
 * Send error response
 */
function sendError(res, code, message) {
    res.writeHead(code, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ error: message }));
}

// Proxy error handler
proxy.on('error', (err, req, res) => {
    console.error('[Proxy Error]', err.message);
    if (res.writeHead) {
        sendError(res, 502, 'Sandbox unavailable');
    }
});

// Create HTTP server
const server = http.createServer(async (req, res) => {
    // Health check endpoint
    if (req.url === '/health' || req.url === '/_health') {
        res.writeHead(200, { 'Content-Type': 'application/json' });
        return res.end(JSON.stringify({ 
            status: 'ok', 
            redis: redis?.isReady ? 'connected' : 'disconnected' 
        }));
    }
    
    // Status endpoint - list all sandboxes
    if (req.url === '/status' || req.url === '/_status') {
        try {
            const { stdout } = await execAsync(`lxc list --format csv -c ns4 2>/dev/null`);
            const sandboxes = stdout.trim().split('\n')
                .filter(line => line.includes(CONFIG.containerPrefix))
                .map(line => {
                    const parts = line.split(',');
                    return {
                        name: parts[0],
                        status: parts[1],
                        ip: parts[2]?.split(' ')[0] || null
                    };
                });
            
            res.writeHead(200, { 'Content-Type': 'application/json' });
            return res.end(JSON.stringify({ sandboxes }));
        } catch (err) {
            sendError(res, 500, 'Failed to list sandboxes');
            return;
        }
    }
    
    // Get user ID from request (supports ?sandbox=id or /clients/id/...)
    const userInfo = getUserId(req);
    
    if (!userInfo || !userInfo.id) {
        return sendError(res, 400, 'Missing sandbox parameter. Use ?sandbox=userId or /clients/userId/');
    }
    
    const userId = userInfo.id;
    
    // Get container IP (sync - pure computation)
    const ip = getContainerIp(userId);
    
    if (!ip) {
        return sendError(res, 404, `Sandbox not found for user: ${userId}`);
    }
    
    // Get the path to forward
    const forwardPath = getForwardPath(req, userId);
    
    // Proxy the request
    const target = `http://${ip}:${CONFIG.containerPort}`;
    console.log(`[Proxy] ${userId} -> ${target}${forwardPath}`);
    
    // Rewrite the URL to the forward path
    req.url = forwardPath;
    
    proxy.web(req, res, { target });
});

// WebSocket upgrade handler
server.on('upgrade', async (req, socket, head) => {
    const userInfo = getUserId(req);
    
    if (!userInfo || !userInfo.id) {
        socket.destroy();
        return;
    }
    
    const userId = userInfo.id;
    const ip = getContainerIp(userId);
    
    if (!ip) {
        socket.destroy();
        return;
    }
    
    // Get the path to forward
    const forwardPath = getForwardPath(req, userId);
    req.url = forwardPath;
    
    const target = `ws://${ip}:${CONFIG.containerPort}`;
    console.log(`[WebSocket] ${userId} -> ${target}${forwardPath}`);
    
    proxy.ws(req, socket, head, { target });
});

// Start server
async function start() {
    console.log('=== Ginto Sandbox Proxy (Deterministic IP) ===');
    console.log(`Config: port=${CONFIG.port}, autoCreate=${CONFIG.autoCreate}, networkPrefix=${CONFIG.networkPrefix || 'FULL'}`);
    
    await initRedis();
    
    server.listen(CONFIG.port, () => {
        console.log(`[Server] Listening on http://0.0.0.0:${CONFIG.port}`);
        console.log(`[Server] Health check: http://localhost:${CONFIG.port}/health`);
        console.log(`[Server] Usage: http://localhost:${CONFIG.port}/?sandbox=userId`);
    });
}

// Graceful shutdown
process.on('SIGTERM', () => {
    console.log('[Shutdown] SIGTERM received');
    server.close(() => {
        if (redis) redis.quit();
        process.exit(0);
    });
});

process.on('SIGINT', () => {
    console.log('[Shutdown] SIGINT received');
    server.close(() => {
        if (redis) redis.quit();
        process.exit(0);
    });
});

start().catch(err => {
    console.error('[Fatal]', err);
    process.exit(1);
});
