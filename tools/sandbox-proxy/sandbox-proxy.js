/**
 * Ginto Sandbox Proxy
 * 
 * Dynamic reverse proxy that routes requests to LXD sandbox containers.
 * Uses Redis to lookup user->containerIP mappings.
 * 
 * Usage:
 *   http://host:3000/?sandbox=user123
 *   
 * The proxy looks up user123 in Redis (key: sandbox:user123) to get the
 * container IP, then forwards the request to http://<container_ip>:80
 * 
 * @see docs/sandbox.md for full architecture
 */

const http = require('http');
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
    redisPrefix: 'sandbox:',
    // If true, auto-create sandbox if it doesn't exist
    autoCreate: process.env.AUTO_CREATE_SANDBOX === '1',
    // Base image for new sandboxes
    baseImage: process.env.LXD_BASE_IMAGE || 'ginto',
    // Container name prefix
    containerPrefix: 'sandbox-'
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
 * Falls back to LXD direct lookups if Redis is unavailable
 */
async function initRedis() {
    return new Promise((resolve) => {
        const timeout = setTimeout(() => {
            console.log('[Redis] Connection timeout (2s), falling back to LXD lookups');
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
                console.log(`[Redis] Connected to ${CONFIG.redisUrl}`);
                resolve();
            }).catch((err) => {
                clearTimeout(timeout);
                console.log('[Redis] Failed to connect:', err.message);
                console.log('[Redis] Falling back to LXD direct lookups');
                redis = null;
                resolve();
            });
        } catch (err) {
            clearTimeout(timeout);
            console.error('[Redis] Setup error:', err.message);
            console.log('[Redis] Falling back to LXD direct lookups');
            redis = null;
            resolve();
        }
    });
}

/**
 * Get container IP from Redis cache
 */
async function getCachedIp(userId) {
    if (!redis || !redis.isReady) return null;
    try {
        return await redis.get(CONFIG.redisPrefix + userId);
    } catch (err) {
        console.error('[Redis] Get error:', err.message);
        return null;
    }
}

/**
 * Cache container IP in Redis
 */
async function cacheIp(userId, ip) {
    if (!redis || !redis.isReady) return;
    try {
        await redis.set(CONFIG.redisPrefix + userId, ip);
    } catch (err) {
        console.error('[Redis] Set error:', err.message);
    }
}

/**
 * Get container IP directly from LXD
 */
async function getLxdIp(userId) {
    const containerName = CONFIG.containerPrefix + userId.replace(/[^a-zA-Z0-9_-]/g, '');
    
    try {
        const { stdout } = await execAsync(`sudo lxc list ${containerName} -c 4 --format csv 2>/dev/null`);
        const ip = stdout.trim().split(' ')[0];
        
        if (ip && /^\d+\.\d+\.\d+\.\d+$/.test(ip)) {
            return ip;
        }
    } catch (err) {
        // Container doesn't exist or other error
    }
    
    return null;
}

/**
 * Check if container exists
 */
async function containerExists(userId) {
    const containerName = CONFIG.containerPrefix + userId.replace(/[^a-zA-Z0-9_-]/g, '');
    
    try {
        const { stdout } = await execAsync(`sudo lxc list --format csv -c n 2>/dev/null`);
        const containers = stdout.trim().split('\n');
        return containers.includes(containerName);
    } catch (err) {
        return false;
    }
}

/**
 * Create a new sandbox container
 */
async function createSandbox(userId) {
    const containerName = CONFIG.containerPrefix + userId.replace(/[^a-zA-Z0-9_-]/g, '');
    
    console.log(`[Sandbox] Creating new sandbox: ${containerName}`);
    
    try {
        // Launch container
        await execAsync(`sudo lxc launch ${CONFIG.baseImage} ${containerName}`);
        
        // Wait for container to get IP
        await new Promise(resolve => setTimeout(resolve, 3000));
        
        // Get IP
        const ip = await getLxdIp(userId);
        
        if (ip) {
            await cacheIp(userId, ip);
            console.log(`[Sandbox] Created ${containerName} with IP ${ip}`);
            return ip;
        }
        
        return null;
    } catch (err) {
        console.error(`[Sandbox] Failed to create ${containerName}:`, err.message);
        return null;
    }
}

/**
 * Check if IP is reachable (quick check)
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
 * Get container IP (with optional auto-creation)
 */
async function getContainerIp(userId) {
    // 1. Try Redis cache
    let ip = await getCachedIp(userId);
    if (ip) {
        // Verify cached IP is still valid by checking LXD
        const lxdIp = await getLxdIp(userId);
        if (lxdIp && lxdIp !== ip) {
            // IP changed (container recreated), update cache
            console.log(`[Lookup] ${userId} -> ${lxdIp} (cache stale, was ${ip})`);
            await cacheIp(userId, lxdIp);
            return lxdIp;
        } else if (lxdIp) {
            console.log(`[Lookup] ${userId} -> ${ip} (cached, verified)`);
            return ip;
        }
        // Container no longer exists, clear cache and continue
        console.log(`[Lookup] ${userId} -> cache invalid, container gone`);
    }
    
    // 2. Try LXD direct
    ip = await getLxdIp(userId);
    if (ip) {
        console.log(`[Lookup] ${userId} -> ${ip} (lxd)`);
        await cacheIp(userId, ip);
        return ip;
    }
    
    // 3. Auto-create if enabled
    if (CONFIG.autoCreate) {
        ip = await createSandbox(userId);
        if (ip) {
            return ip;
        }
    }
    
    return null;
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
            const { stdout } = await execAsync(`sudo lxc list --format csv -c ns4 2>/dev/null`);
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
    console.log(`Config: port=${CONFIG.port}, autoCreate=${CONFIG.autoCreate}`);
    
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
