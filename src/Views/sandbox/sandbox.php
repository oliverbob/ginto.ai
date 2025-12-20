<!DOCTYPE html><!--Experimental sandbox Manager Mockup-->
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LXD Sandbox Manager</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        h1 {
            color: white;
            text-align: center;
            margin-bottom: 30px;
            font-size: 2.5em;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .dashboard {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            transition: transform 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .card h2 {
            color: #667eea;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 600;
        }

        input, select, textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            width: 100%;
            margin-top: 10px;
        }

        button:hover {
            transform: scale(1.02);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        button:active {
            transform: scale(0.98);
        }

        .btn-danger {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .btn-success {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }

        .status-running {
            background: #4caf50;
            color: white;
        }

        .status-stopped {
            background: #ff9800;
            color: white;
        }

        .status-creating {
            background: #2196f3;
            color: white;
        }

        .list-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.3s;
        }

        .list-item:hover {
            background: #e9ecef;
        }

        .list-item-actions {
            display: flex;
            gap: 10px;
        }

        .list-item-actions button {
            width: auto;
            padding: 8px 16px;
            margin: 0;
            font-size: 12px;
        }

        .log-console {
            background: #1e1e1e;
            color: #00ff00;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            max-height: 300px;
            overflow-y: auto;
            margin-top: 15px;
        }

        .log-entry {
            margin-bottom: 5px;
            padding: 2px 0;
        }

        .log-info { color: #00ff00; }
        .log-warning { color: #ffaa00; }
        .log-error { color: #ff4444; }
        .log-success { color: #00ffff; }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 15px;
        }

        .stat-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }

        .stat-number {
            font-size: 2em;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.9em;
            opacity: 0.9;
        }

        .ip-pool {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }

        .ip-tag {
            background: #e3f2fd;
            color: #1976d2;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: 600;
        }

        .ip-tag.used {
            background: #ffebee;
            color: #c62828;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 10px 0;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
        }

        #redisConfig {
            display: none;
        }

        #redisConfig.show {
            display: block;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🐧 LXD Sandbox Manager</h1>

        <div class="dashboard">
            <!-- Base Images Section -->
            <div class="card">
                <h2>📦 Base Images</h2>
                <div class="form-group">
                    <label>Image Name</label>
                    <input type="text" id="imageName" placeholder="ubuntu-22.04">
                </div>
                <div class="form-group">
                    <label>Distribution</label>
                    <select id="imageDistro">
                        <option value="ubuntu">Ubuntu</option>
                        <option value="debian">Debian</option>
                        <option value="alpine">Alpine</option>
                        <option value="centos">CentOS</option>
                        <option value="fedora">Fedora</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Version</label>
                    <input type="text" id="imageVersion" placeholder="22.04">
                </div>
                <div class="form-group">
                    <label>Host Image Location</label>
                    <input type="text" id="hostLocation" value="localhost:1800" placeholder="localhost:1800">
                </div>
                <div class="checkbox-group">
                    <input type="checkbox" id="installRedis">
                    <label for="installRedis">Install Redis</label>
                </div>
                <div id="redisConfig">
                    <div class="form-group">
                        <label>Redis Port</label>
                        <input type="number" id="redisPort" value="6379">
                    </div>
                    <div class="form-group">
                        <label>Redis Password (optional)</label>
                        <input type="password" id="redisPassword" placeholder="Leave empty for no password">
                    </div>
                    <div class="checkbox-group">
                        <input type="checkbox" id="redisPersistence" checked>
                        <label for="redisPersistence">Enable Persistence</label>
                    </div>
                </div>
                <button onclick="createBaseImage()">Create Base Image</button>
                
                <div style="margin-top: 20px;">
                    <strong>Available Base Images:</strong>
                    <div id="baseImagesList"></div>
                </div>
            </div>

            <!-- User Sandbox Section -->
            <div class="card">
                <h2>👤 Create User Sandbox</h2>
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" id="username" placeholder="john_doe">
                </div>
                <div class="form-group">
                    <label>Base Image</label>
                    <select id="baseImageSelect"></select>
                </div>
                <div class="form-group">
                    <label>CPU Limit (cores)</label>
                    <input type="number" id="cpuLimit" value="2" min="1" max="16">
                </div>
                <div class="form-group">
                    <label>Memory Limit (GB)</label>
                    <input type="number" id="memoryLimit" value="2" min="1" max="32">
                </div>
                <div class="checkbox-group">
                    <input type="checkbox" id="autoStart" checked>
                    <label for="autoStart">Auto-start sandbox</label>
                </div>
                <button onclick="createUserSandbox()">Create Sandbox</button>
                <button onclick="bulkCreateSandboxes()" class="btn-success">Bulk Create (5 Users)</button>
            </div>

            <!-- Active Sandboxes -->
            <div class="card">
                <h2>🚀 Active Sandboxes</h2>
                <div id="activeSandboxes"></div>
            </div>

            <!-- Network Management -->
            <div class="card">
                <h2>🌐 Network Management</h2>
                <div class="form-group">
                    <label>Network Subnet</label>
                    <input type="text" id="networkSubnet" value="10.100.0.0/24" readonly>
                </div>
                <div class="form-group">
                    <label>Gateway</label>
                    <input type="text" id="gateway" value="10.100.0.1" readonly>
                </div>
                <strong>IP Pool Status:</strong>
                <div id="ipPool" class="ip-pool"></div>
            </div>
        </div>

        <!-- Statistics Dashboard -->
        <div class="card" style="grid-column: 1 / -1;">
            <h2>📊 System Statistics</h2>
            <div class="stats-grid">
                <div class="stat-box">
                    <div class="stat-number" id="totalImages">0</div>
                    <div class="stat-label">Base Images</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number" id="totalSandboxes">0</div>
                    <div class="stat-label">Active Sandboxes</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number" id="availableIPs">254</div>
                    <div class="stat-label">Available IPs</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number" id="totalRedis">0</div>
                    <div class="stat-label">Redis Instances</div>
                </div>
            </div>
        </div>

        <!-- Console Log -->
        <div class="card" style="grid-column: 1 / -1;">
            <h2>📝 System Console</h2>
            <div class="log-console" id="logConsole"></div>
        </div>
    </div>

    <script>
        // Data stores
        let baseImages = [];
        let sandboxes = [];
        let ipPool = [];
        let nextIP = 2; // Start from 10.100.0.2

        // Initialize IP Pool
        function initializeIPPool() {
            for (let i = 2; i < 255; i++) {
                ipPool.push({
                    ip: `10.100.0.${i}`,
                    used: false,
                    assignedTo: null
                });
            }
            updateIPPoolDisplay();
        }

        // Logging system
        function log(message, type = 'info') {
            const console = document.getElementById('logConsole');
            const timestamp = new Date().toLocaleTimeString();
            const entry = document.createElement('div');
            entry.className = `log-entry log-${type}`;
            entry.textContent = `[${timestamp}] ${message}`;
            console.appendChild(entry);
            console.scrollTop = console.scrollHeight;
        }

        // Toggle Redis configuration
        document.getElementById('installRedis').addEventListener('change', function() {
            const redisConfig = document.getElementById('redisConfig');
            if (this.checked) {
                redisConfig.classList.add('show');
            } else {
                redisConfig.classList.remove('show');
            }
        });

        // Create Base Image
        function createBaseImage() {
            const imageName = document.getElementById('imageName').value;
            const distro = document.getElementById('imageDistro').value;
            const version = document.getElementById('imageVersion').value;
            const hostLocation = document.getElementById('hostLocation').value;
            const installRedis = document.getElementById('installRedis').checked;

            if (!imageName || !version) {
                log('Please fill in all required fields', 'error');
                return;
            }

            const image = {
                id: `img_${Date.now()}`,
                name: imageName,
                distro: distro,
                version: version,
                hostLocation: hostLocation,
                created: new Date().toISOString(),
                status: 'creating',
                redis: installRedis ? {
                    port: document.getElementById('redisPort').value,
                    password: document.getElementById('redisPassword').value,
                    persistence: document.getElementById('redisPersistence').checked
                } : null
            };

            baseImages.push(image);
            log(`Creating base image: ${imageName} (${distro} ${version})`, 'info');
            
            if (installRedis) {
                log(`Configuring Redis on port ${image.redis.port}...`, 'info');
                setTimeout(() => {
                    log(`Redis installation completed for ${imageName}`, 'success');
                }, 1500);
            }

            setTimeout(() => {
                image.status = 'ready';
                log(`Base image ${imageName} is ready at ${hostLocation}`, 'success');
                updateBaseImagesList();
                updateStats();
            }, 2000);

            updateBaseImagesList();
            updateStats();
            clearImageForm();
        }

        // Create User Sandbox
        function createUserSandbox() {
            const username = document.getElementById('username').value;
            const baseImageId = document.getElementById('baseImageSelect').value;
            const cpuLimit = document.getElementById('cpuLimit').value;
            const memoryLimit = document.getElementById('memoryLimit').value;
            const autoStart = document.getElementById('autoStart').checked;

            if (!username || !baseImageId) {
                log('Please fill in all required fields', 'error');
                return;
            }

            const baseImage = baseImages.find(img => img.id === baseImageId);
            const assignedIP = getNextAvailableIP();

            if (!assignedIP) {
                log('No available IPs in the pool', 'error');
                return;
            }

            const sandbox = {
                id: `sb_${Date.now()}`,
                username: username,
                baseImage: baseImage.name,
                ip: assignedIP,
                cpu: cpuLimit,
                memory: memoryLimit,
                status: 'creating',
                created: new Date().toISOString(),
                autoStart: autoStart
            };

            sandboxes.push(sandbox);
            markIPAsUsed(assignedIP, username);

            log(`Creating sandbox for user: ${username}`, 'info');
            log(`Assigned IP: ${assignedIP}`, 'info');
            log(`Resources: ${cpuLimit} CPU cores, ${memoryLimit}GB RAM`, 'info');

            setTimeout(() => {
                sandbox.status = autoStart ? 'running' : 'stopped';
                log(`Sandbox ${username} is ${sandbox.status}`, 'success');
                updateSandboxList();
                updateStats();
            }, 2500);

            updateSandboxList();
            updateStats();
            clearSandboxForm();
        }

        // Bulk Create Sandboxes
        function bulkCreateSandboxes() {
            const baseImageId = document.getElementById('baseImageSelect').value;
            
            if (!baseImageId) {
                log('Please select a base image first', 'error');
                return;
            }

            const baseImage = baseImages.find(img => img.id === baseImageId);
            const usernames = ['user1', 'user2', 'user3', 'user4', 'user5'];

            log('Starting bulk sandbox creation...', 'info');

            usernames.forEach((username, index) => {
                setTimeout(() => {
                    const assignedIP = getNextAvailableIP();
                    
                    if (!assignedIP) {
                        log(`Failed to create sandbox for ${username}: No IPs available`, 'error');
                        return;
                    }

                    const sandbox = {
                        id: `sb_${Date.now()}_${index}`,
                        username: username,
                        baseImage: baseImage.name,
                        ip: assignedIP,
                        cpu: 2,
                        memory: 2,
                        status: 'creating',
                        created: new Date().toISOString(),
                        autoStart: true
                    };

                    sandboxes.push(sandbox);
                    markIPAsUsed(assignedIP, username);
                    log(`Creating sandbox for ${username} at ${assignedIP}`, 'info');

                    setTimeout(() => {
                        sandbox.status = 'running';
                        updateSandboxList();
                        updateStats();
                    }, 1000);

                }, index * 500);
            });

            setTimeout(() => {
                log('Bulk creation completed!', 'success');
                updateSandboxList();
                updateStats();
            }, 3000);
        }

        // IP Management
        function getNextAvailableIP() {
            const available = ipPool.find(ip => !ip.used);
            return available ? available.ip : null;
        }

        function markIPAsUsed(ip, assignedTo) {
            const ipObj = ipPool.find(i => i.ip === ip);
            if (ipObj) {
                ipObj.used = true;
                ipObj.assignedTo = assignedTo;
                updateIPPoolDisplay();
            }
        }

        function releaseIP(ip) {
            const ipObj = ipPool.find(i => i.ip === ip);
            if (ipObj) {
                ipObj.used = false;
                ipObj.assignedTo = null;
                updateIPPoolDisplay();
            }
        }

        // Update Displays
        function updateBaseImagesList() {
            const container = document.getElementById('baseImagesList');
            const select = document.getElementById('baseImageSelect');
            
            container.innerHTML = '';
            select.innerHTML = '<option value="">Select a base image</option>';

            baseImages.forEach(image => {
                const item = document.createElement('div');
                item.className = 'list-item';
                item.innerHTML = `
                    <div>
                        <strong>${image.name}</strong>
                        <span class="status-badge status-${image.status}">${image.status}</span>
                        <br>
                        <small>${image.distro} ${image.version} @ ${image.hostLocation}</small>
                        ${image.redis ? '<br><small>🔴 Redis installed</small>' : ''}
                    </div>
                    <div class="list-item-actions">
                        <button onclick="deleteBaseImage('${image.id}')">Delete</button>
                    </div>
                `;
                container.appendChild(item);

                if (image.status === 'ready') {
                    const option = document.createElement('option');
                    option.value = image.id;
                    option.textContent = image.name;
                    select.appendChild(option);
                }
            });
        }

        function updateSandboxList() {
            const container = document.getElementById('activeSandboxes');
            container.innerHTML = '';

            sandboxes.forEach(sandbox => {
                const item = document.createElement('div');
                item.className = 'list-item';
                item.innerHTML = `
                    <div>
                        <strong>${sandbox.username}</strong>
                        <span class="status-badge status-${sandbox.status}">${sandbox.status}</span>
                        <br>
                        <small>IP: ${sandbox.ip} | ${sandbox.cpu} CPU | ${sandbox.memory}GB RAM</small>
                        <br>
                        <small>Base: ${sandbox.baseImage}</small>
                    </div>
                    <div class="list-item-actions">
                        ${sandbox.status === 'running' ? 
                            `<button onclick="stopSandbox('${sandbox.id}')">Stop</button>` :
                            `<button class="btn-success" onclick="startSandbox('${sandbox.id}')">Start</button>`
                        }
                        <button class="btn-danger" onclick="deleteSandbox('${sandbox.id}')">Delete</button>
                    </div>
                `;
                container.appendChild(item);
            });
        }

        function updateIPPoolDisplay() {
            const container = document.getElementById('ipPool');
            container.innerHTML = '';

            ipPool.slice(0, 20).forEach(ip => {
                const tag = document.createElement('div');
                tag.className = `ip-tag ${ip.used ? 'used' : ''}`;
                tag.textContent = ip.used ? `${ip.ip} (${ip.assignedTo})` : ip.ip;
                tag.title = ip.used ? `Used by ${ip.assignedTo}` : 'Available';
                container.appendChild(tag);
            });

            if (ipPool.length > 20) {
                const more = document.createElement('div');
                more.className = 'ip-tag';
                more.textContent = `+${ipPool.length - 20} more...`;
                container.appendChild(more);
            }
        }

        function updateStats() {
            document.getElementById('totalImages').textContent = baseImages.length;
            document.getElementById('totalSandboxes').textContent = sandboxes.filter(s => s.status === 'running').length;
            document.getElementById('availableIPs').textContent = ipPool.filter(ip => !ip.used).length;
            document.getElementById('totalRedis').textContent = baseImages.filter(img => img.redis).length;
        }

        // Sandbox actions
        function stopSandbox(id) {
            const sandbox = sandboxes.find(s => s.id === id);
            if (sandbox) {
                sandbox.status = 'stopped';
                log(`Stopped sandbox: ${sandbox.username}`, 'warning');
                updateSandboxList();
                updateStats();
            }
        }

        function startSandbox(id) {
            const sandbox = sandboxes.find(s => s.id === id);
            if (sandbox) {
                sandbox.status = 'running';
                log(`Started sandbox: ${sandbox.username}`, 'success');
                updateSandboxList();
                updateStats();
            }
        }

        function deleteSandbox(id) {
            const sandbox = sandboxes.find(s => s.id === id);
            if (sandbox && confirm(`Delete sandbox for ${sandbox.username}?`)) {
                releaseIP(sandbox.ip);
                sandboxes = sandboxes.filter(s => s.id !== id);
                log(`Deleted sandbox: ${sandbox.username}`, 'warning');
                updateSandboxList();
                updateStats();
            }
        }

        function deleteBaseImage(id) {
            const image = baseImages.find(img => img.id === id);
            if (image && confirm(`Delete base image ${image.name}?`)) {
                baseImages = baseImages.filter(img => img.id !== id);
                log(`Deleted base image: ${image.name}`, 'warning');
                updateBaseImagesList();
                updateStats();
            }
        }

        // Form clearing
        function clearImageForm() {
            document.getElementById('imageName').value = '';
            document.getElementById('imageVersion').value = '';
            document.getElementById('installRedis').checked = false;
            document.getElementById('redisConfig').classList.remove('show');
        }

        function clearSandboxForm() {
            document.getElementById('username').value = '';
        }

        // Initialize
        initializeIPPool();
        log('LXD Sandbox Manager initialized', 'success');
        log('System ready. Waiting for commands...', 'info');
        updateStats();
    </script>
</body>
</html>