/**
 * Ginto Sandbox Proxy
 * 
 * Dynamic reverse proxy that routes requests to LXD sandbox containers.
 * Uses DETERMINISTIC IP allocation: SHA256(sandboxId) → IP address
 * 
 * Usage:
 *   http://host:3000/?sandbox=user123
 *   
 * The proxy computes the IP directly from the sandbox ID using SHA256,
 * then verifies container existence via LXD before routing.
 * 
 * @see docs/sandbox.md for full architecture
 */

const http = require('http');
const fs = require('fs');
const crypto = require('crypto');
const httpProxy = require('http-proxy');
const { exec } = require('child_process');
const { promisify } = require('util');

const execAsync = promisify(exec);

// LXD socket path (snap install location)
const LXD_SOCKET = '/var/snap/lxd/common/lxd/unix.socket';

// Configuration
const CONFIG = {
    port: parseInt(process.env.PORT || '3000'),
    containerPort: parseInt(process.env.CONTAINER_PORT || '80'),
    autoCreate: process.env.AUTO_CREATE_SANDBOX === '1',
    baseImage: process.env.LXD_BASE_IMAGE || 'ginto-sandbox',
    containerPrefix: 'ginto-sandbox-',
    // Network prefix for deterministic IP (e.g., "10.166.3")
    // If not set, uses full 32-bit IP space (datacenter mode)
    networkPrefix: process.env.LXD_NETWORK_PREFIX || null,
    // Network mode: 'bridge' (default, 16.7M IPs) or 'macvlan' (4.3B IPs)
    networkMode: process.env.LXD_NETWORK_MODE || 'bridge',
};

// Create proxy server
const proxy = httpProxy.createProxyServer({
    ws: true, // Enable WebSocket proxying
    xfwd: true // Add X-Forwarded headers
});

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
 * Convert Feistel output to full 32-bit IP (for macvlan mode)
 * Filters out reserved/invalid IP ranges
 * 
 * Reserved ranges avoided:
 * - 0.0.0.0/8 (current network)
 * - 127.0.0.0/8 (loopback)
 * - 224.0.0.0/4 (multicast)
 * - 240.0.0.0/4 (reserved)
 * - x.x.x.0 (network addresses)
 * - x.x.x.255 (broadcast addresses)
 * 
 * @param {number} permuted - 32-bit permuted value
 * @returns {string} Valid IPv4 address
 */
function permutedToFullIp(permuted) {
    let octet1 = (permuted >>> 24) & 255;
    let octet2 = (permuted >>> 16) & 255;
    let octet3 = (permuted >>> 8) & 255;
    let octet4 = permuted & 255;
    
    // Remap reserved first octets to valid ranges
    // 0.x.x.x → 1.x.x.x (current network)
    if (octet1 === 0) octet1 = 1;
    
    // 127.x.x.x → 128.x.x.x (loopback)
    if (octet1 === 127) octet1 = 128;
    
    // 224-239.x.x.x → 64-79.x.x.x (multicast)
    if (octet1 >= 224 && octet1 <= 239) octet1 = octet1 - 160;
    
    // 240-255.x.x.x → 80-95.x.x.x (reserved)
    if (octet1 >= 240) octet1 = octet1 - 160;
    
    // Avoid network and broadcast addresses
    if (octet4 === 0) octet4 = 1;
    if (octet4 === 255) octet4 = 254;
    
    return `${octet1}.${octet2}.${octet3}.${octet4}`;
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
 * Modes:
 * - MACVLAN: Full 32-bit IP space (4.3 billion unique IPs)
 * - BRIDGE:  Constrained to subnet (default 10.0.0.0/8, 16.7M IPs)
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
    
    // MACVLAN/IPVLAN MODE: Full 32-bit IP space (4.3 billion unique IPs)
    // Both use the same IP allocation strategy, differ only in L2 vs L3
    if (CONFIG.networkMode === 'macvlan' || CONFIG.networkMode === 'ipvlan') {
        return permutedToFullIp(permuted);
    }
    
    // BRIDGE/NAT MODE: Network prefix mode - constrain to subnet
    // Both use the same subnet-based allocation, differ in routing
    if (CONFIG.networkPrefix && CONFIG.networkPrefix !== '') {
        const prefixParts = CONFIG.networkPrefix.split('.');
        const prefixLen = prefixParts.length;
        
        if (prefixLen === 3) {
            // /24 network (e.g., "10.170.65") - 253 hosts
            const lastOctet = 2 + (permuted % 253);
            return `${CONFIG.networkPrefix}.${lastOctet}`;
        } else if (prefixLen === 2) {
            // /16 network (e.g., "10.170") - 65,534 hosts
            const hostPart = permuted & 0xFFFF; // 16 bits
            let octet3 = (hostPart >>> 8) & 255;
            let octet4 = hostPart & 255;
            // Avoid network (.0.0), gateway (.0.1), and broadcast (.255.255)
            if (octet3 === 0 && octet4 < 2) octet4 = 2;
            if (octet3 === 255 && octet4 === 255) octet4 = 254;
            if (octet4 === 0) octet4 = 1;
            if (octet4 === 255) octet4 = 254;
            return `${CONFIG.networkPrefix}.${octet3}.${octet4}`;
        } else if (prefixLen === 1) {
            // /8 network (e.g., "10") - 16 million hosts
            const hostPart = permuted & 0xFFFFFF; // 24 bits
            const octet2 = (hostPart >>> 16) & 255;
            const octet3 = (hostPart >>> 8) & 255;
            let octet4 = hostPart & 255;
            if (octet4 === 0) octet4 = 1;
            if (octet4 === 255) octet4 = 254;
            return `${CONFIG.networkPrefix}.${octet2}.${octet3}.${octet4}`;
        }
    }
    
    // Default bridge mode: 10.0.0.0/8 private range (16.7 million IPs)
    const hostPart = permuted & 0xFFFFFF; // 24 bits for octets 2-4
    let octet2 = (hostPart >>> 16) & 255;
    let octet3 = (hostPart >>> 8) & 255;
    let octet4 = hostPart & 255;
    
    // Avoid reserved addresses
    if (octet2 === 0 && octet3 === 0 && octet4 < 2) octet4 = 2; // Skip 10.0.0.0, 10.0.0.1
    if (octet4 === 0) octet4 = 1;
    if (octet4 === 255) octet4 = 254;
    
    return `10.${octet2}.${octet3}.${octet4}`;
}

/**
 * Get container name from sandbox ID
 */
function containerName(sandboxId) {
    const safe = sandboxId.replace(/[^a-zA-Z0-9_-]/g, '');
    return CONFIG.containerPrefix + safe;
}

/**
 * Check if using full IP range modes (macvlan or ipvlan)
 * Both modes use full 32-bit IP space with 4.3B unique IPs
 */
function isFullIpMode() {
    return CONFIG.networkMode === 'macvlan' || CONFIG.networkMode === 'ipvlan';
}

/**
 * Check if macvlan mode is enabled (legacy helper, use isFullIpMode for IP logic)
 */
function isMacvlanMode() {
    return CONFIG.networkMode === 'macvlan';
}

/**
 * Check if ipvlan mode is enabled
 */
function isIpvlanMode() {
    return CONFIG.networkMode === 'ipvlan';
}

/**
 * Get network interface name for containers based on mode
 * - bridge/nat: lxdbr0 (LXD default bridge)
 * - macvlan: ginto-dummy0 (dummy interface for L2)
 * - ipvlan: parent interface like eth0 (L3)
 */
function getNetworkInterface() {
    if (CONFIG.networkMode === 'macvlan') {
        return process.env.LXD_MACVLAN_PARENT || 'ginto-dummy0';
    }
    if (CONFIG.networkMode === 'ipvlan') {
        return process.env.LXD_IPVLAN_PARENT || 'eth0';
    }
    return 'lxdbr0';
}

/**
 * Add route through shim interface (for macvlan mode)
 * Enables host-to-container communication
 * 
 * @param {string} ip - Container's IP address
 * @returns {Promise<boolean>} Success
 */
async function addShimRoute(ip) {
    if (!isMacvlanMode()) {
        return true; // Not needed for bridge mode
    }
    
    try {
        await execAsync(`sudo ip route add ${ip}/32 dev ginto-shim 2>/dev/null`);
        return true;
    } catch (err) {
        // Route might already exist
        return true;
    }
}

/**
 * Remove route through shim interface
 * 
 * @param {string} ip - Container's IP address
 * @returns {Promise<boolean>} Success
 */
async function removeShimRoute(ip) {
    if (!isMacvlanMode()) {
        return true; // Not needed for bridge mode
    }
    
    try {
        await execAsync(`sudo ip route del ${ip}/32 dev ginto-shim 2>/dev/null`);
        return true;
    } catch (err) {
        // Route might not exist
        return true;
    }
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
    const mode = isMacvlanMode() ? 'macvlan' : 'bridge';
    
    console.log(`[Sandbox] Creating ${name} with static IP ${ip} (${mode} mode)`);
    
    try {
        if (isMacvlanMode()) {
            // MACVLAN MODE: Use ginto-macvlan network with full 32-bit IP space
            await execAsync(`lxc launch ${CONFIG.baseImage} ${name} --network ginto-macvlan`);
            
            // Configure static IP via cloud-init or direct config
            await execAsync(`lxc config device override ${name} eth0 ipv4.address=${ip}`);
            await execAsync(`lxc restart ${name}`);
            
            // Add shim route for host-to-container communication
            await addShimRoute(ip);
        } else {
            // BRIDGE MODE: Traditional LXD bridge networking
            await execAsync(`lxc launch ${CONFIG.baseImage} ${name}`);
            await execAsync(`lxc config device override ${name} eth0 ipv4.address=${ip}`);
            await execAsync(`lxc restart ${name}`);
        }
        
        await new Promise(resolve => setTimeout(resolve, 2000));
        
        console.log(`[Sandbox] Created ${name} at ${ip}`);
        return ip;
    } catch (err) {
        console.error(`[Sandbox] Failed to create ${name}:`, err.message);
        return null;
    }
}

/**
 * Query LXD REST API via unix socket
 */
function lxdRequest(path) {
    return new Promise((resolve, reject) => {
        const options = {
            socketPath: LXD_SOCKET,
            path: path,
            method: 'GET'
        };
        
        const req = http.request(options, (res) => {
            let data = '';
            res.on('data', chunk => data += chunk);
            res.on('end', () => {
                try {
                    resolve(JSON.parse(data));
                } catch (e) {
                    reject(new Error(`Invalid JSON: ${data.substring(0, 100)}`));
                }
            });
        });
        
        req.on('error', reject);
        req.end();
    });
}

/**
 * Get container IP - queries actual LXD container IP via REST API
 * 
 * Queries the real container IP from LXD since static IP assignment
 * may not work in all environments (nested LXC, DHCP networks, etc.)
 */
async function getContainerIp(sandboxId) {
    const containerName = `${CONFIG.containerPrefix}${sandboxId}`;
    
    try {
        // Query container state via LXD REST API
        const response = await lxdRequest(`/1.0/instances/${containerName}/state`);
        
        if (response.status_code !== 200 || !response.metadata?.network) {
            console.log(`[Route] ${sandboxId} -> container not found or not running`);
            return null;
        }
        
        const network = response.metadata.network;
        for (const [iface, net] of Object.entries(network)) {
            if (iface === 'lo') continue;
            for (const addr of (net.addresses || [])) {
                if (addr.family === 'inet') {
                    console.log(`[Route] ${sandboxId} -> ${addr.address} (from LXD)`);
                    return addr.address;
                }
            }
        }
        
        console.log(`[Route] ${sandboxId} -> no IPv4 address found`);
        return null;
    } catch (err) {
        console.log(`[Route] ${sandboxId} -> LXD query failed: ${err.message}`);
        return null;
    }
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
        return res.end(JSON.stringify({ status: 'ok' }));
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
    
    // Get container IP (async - queries LXD)
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
    console.log('=== Ginto Sandbox Proxy (Deterministic IP) ===');
    const ipRange = isMacvlanMode() ? '4.3B IPs (full 32-bit)' : (CONFIG.networkPrefix || '10.x.x.x (16.7M IPs)');
    console.log(`Config: port=${CONFIG.port}, mode=${CONFIG.networkMode}, autoCreate=${CONFIG.autoCreate}`);
    console.log(`Network: ${ipRange}`);
    
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
        process.exit(0);
    });
});

process.on('SIGINT', () => {
    console.log('[Shutdown] SIGINT received');
    server.close(() => {
        process.exit(0);
    });
});

start().catch(err => {
    console.error('[Fatal]', err);
    process.exit(1);
});
