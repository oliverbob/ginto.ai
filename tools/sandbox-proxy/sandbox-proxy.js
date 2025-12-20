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
    // If true, auto-create sandbox if it doesn't exist
    autoCreate: process.env.AUTO_CREATE_SANDBOX === '1',
    // Base image for new sandboxes
    baseImage: process.env.LXD_BASE_IMAGE || 'ginto-sandbox',
    // Container name prefix
    containerPrefix: 'ginto-sandbox-',
    
    // Network configuration for deterministic IP
    // Set LXD_NETWORK_PREFIX to your LXD bridge subnet (e.g., "10.166.3")
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
 * Redis is available for agent communication (queues, pub/sub, state)
 * but NOT used for IP routing (that's deterministic via SHA256)
 */
async function initRedis() {
    return new Promise((resolve) => {
        const timeout = setTimeout(() => {
            console.log('[Redis] Connection timeout (2s), Redis unavailable for agent state');
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
                    reconnectStrategy: false // Don't retry if initial connection fails
                }
            });
            
            redis.on('error', (err) => {
                // Only log once, not on every retry
                if (!redis._errorLogged) {
                    console.error('[Redis] Connection error:', err.message);
                    redis._errorLogged = true;
                }
            });
            
            redis.connect().then(() => {
                clearTimeout(timeout);
                console.log(`[Redis] Connected - available for agent communication`);
                resolve();
            }).catch((err) => {
                clearTimeout(timeout);
                console.log('[Redis] Failed to connect:', err.message);
                console.log('[Redis] Agent state features unavailable (routing still works)');
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
// DETERMINISTIC IP ALLOCATION
// Computes IP directly from sandbox ID using SHA256 - no lookup needed
// ============================================================================

/**
 * Convert sandbox ID to deterministic IP address
 * 
 * Two modes:
 * 1. Full IP mode (datacenter): Uses all 4 bytes of SHA256 for full 32-bit IP
 * 2. Subnet mode (local): Uses 1 byte mapped to last octet within network prefix
 * 
 * Set LXD_NETWORK_PREFIX env var to your subnet (e.g., "10.166.3") for local mode.
 * 
 * @param {string} sandboxId - The sandbox/agent identifier
 * @returns {string} IP address (e.g., "142.87.203.91" or "10.166.3.142")
 */
function sandboxToIp(sandboxId) {
    const hash = crypto.createHash('sha256').update(sandboxId).digest();
    
    if (CONFIG.networkPrefix) {
        // Subnet mode: map hash to last octet within network prefix
        // Use hash byte to get value 2-254 (avoid .0, .1, .255)
        let lastOctet = hash[0];
        if (lastOctet < 2) lastOctet = 2;
        if (lastOctet > 254) lastOctet = 254;
        // Better distribution: ensure we use range 2-254
        lastOctet = 2 + (hash[0] % 253);
        
        return `${CONFIG.networkPrefix}.${lastOctet}`;
    }
    
    // Datacenter mode: full 32-bit IP space
    let octets = [hash[0], hash[1], hash[2], hash[3]];
    
    // Avoid reserved addresses: .0 (network) and .255 (broadcast)
    octets = octets.map(b => {
        if (b === 0) return 1;
        if (b === 255) return 254;
        return b;
    });
    
    return octets.join('.');
}

/**
 * Get container name from sandbox ID
 */
function containerName(sandboxId) {
    const safe = sandboxId.replace(/[^a-zA-Z0-9_-]/g, '');
    return CONFIG.containerPrefix + safe;
}

// ============================================================================
// AGENT STATE (Redis) - Optional features, not required for routing
// ============================================================================

/**
 * Update last access timestamp for agent (async, non-blocking)
 */
async function updateLastAccess(sandboxId) {
    if (!redis?.isReady) return;
    try {
        await redis.set(`${CONFIG.redisPrefix}${sandboxId}:last`, Date.now().toString());
    } catch {}
}

/**
 * Track agent metrics (async, non-blocking)
 */
async function trackRequest(sandboxId) {
    if (!redis?.isReady) return;
    try {
        await redis.incr(`${CONFIG.redisPrefix}${sandboxId}:requests`);
    } catch {}
}

// ============================================================================
// CONTAINER MANAGEMENT
// ============================================================================

/**
 * Check if container exists in LXD
 */
async function containerExists(sandboxId) {
    const name = containerName(sandboxId);
    
    try {
        const { stdout } = await execAsync(`lxc list --format csv -c n 2>/dev/null`);
        const containers = stdout.trim().split('\n');
        return containers.includes(name);
    } catch (err) {
        return false;
    }
}

/**
 * Create a new sandbox container with static IP
 * IP is deterministic from sandboxId - no DHCP needed
 */
async function createSandbox(sandboxId) {
    const name = containerName(sandboxId);
    const ip = sandboxToIp(sandboxId);
    
    console.log(`[Sandbox] Creating ${name} with static IP ${ip}`);
    
    try {
        // Launch container
        await execAsync(`lxc launch ${CONFIG.baseImage} ${name}`);
        
        // Assign static IP (override eth0 device)
        await execAsync(`lxc config device override ${name} eth0 ipv4.address=${ip}`);
        
        // Restart networking to apply static IP
        await execAsync(`lxc restart ${name}`);
        
        // Brief wait for container to be ready
        await new Promise(resolve => setTimeout(resolve, 1500));
        
        console.log(`[Sandbox] Created ${name} at ${ip}`);
        
        // Track agent creation in Redis (if available)
        if (redis?.isReady) {
            try {
                await redis.set(`${CONFIG.redisPrefix}${sandboxId}:created`, Date.now().toString());
            } catch {}
        }
        
        return ip;
    } catch (err) {
        console.error(`[Sandbox] Failed to create ${name}:`, err.message);
        return null;
    }
}

/**
 * Check if IP is reachable (quick HTTP check)
 */
async function isIpReachable(ip) {
    return new Promise((resolve) => {
        const req = http.request({
            hostname: ip,
            port: CONFIG.containerPort,
            path: '/',
            method: 'HEAD',
            timeout: 1000
        }, (res) => {
            resolve(true);
        });
        req.on('error', () => resolve(false));
        req.on('timeout', () => {
            req.destroy();
            resolve(false);
        });
        req.end();
    });
}

/**
 * Get container IP - DETERMINISTIC (no lookup needed!)
 * 
 * The IP is computed directly from the sandbox ID using SHA256.
 * No Redis lookup, no LXD query - pure computation.
 * 
 * If autoCreate is enabled and container doesn't exist, creates it.
 */
async function getContainerIp(sandboxId) {
    // Compute IP deterministically - O(1), no I/O
    const ip = sandboxToIp(sandboxId);
    
    // Quick reachability check
    const reachable = await isIpReachable(ip);
    
    if (reachable) {
        console.log(`[Route] ${sandboxId} -> ${ip} (computed, reachable)`);
        // Fire-and-forget: track access in Redis
        updateLastAccess(sandboxId);
        trackRequest(sandboxId);
        return ip;
    }
    
    // Not reachable - container may not exist yet
    if (CONFIG.autoCreate) {
        // Check if container exists but just isn't responding
        const exists = await containerExists(sandboxId);
        
        if (!exists) {
            // Create with the predetermined static IP
            const createdIp = await createSandbox(sandboxId);
            if (createdIp) {
                return createdIp;
            }
        } else {
            // Container exists but not responding - might be starting
            console.log(`[Route] ${sandboxId} -> ${ip} (computed, container exists but not responding)`);
            return ip;
        }
    }
    
    // Return computed IP even if not reachable (let proxy handle the error)
    console.log(`[Route] ${sandboxId} -> ${ip} (computed, not reachable)`);
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
    console.error('[Proxy Error]', err.code, err.message, `target=${req._proxyTarget || 'unknown'}`);
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
    
    // Get container IP
    const ip = await getContainerIp(userId);
    
    if (!ip) {
        return sendError(res, 404, `Sandbox not found for user: ${userId}`);
    }
    
    // Get the path to forward
    const forwardPath = getForwardPath(req, userId);
    
    // Proxy the request
    const target = `http://${ip}:${CONFIG.containerPort}`;
    console.log(`[Proxy] ${userId} -> ${target}${forwardPath}`);
    
    // Store target for error logging
    req._proxyTarget = target;
    
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
    const ip = await getContainerIp(userId);
    
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
    console.log('=== Ginto Sandbox Proxy ===');
    console.log(`Config: port=${CONFIG.port}, autoCreate=${CONFIG.autoCreate}, networkPrefix=${CONFIG.networkPrefix || 'FULL_IP_MODE'}`);
    
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
