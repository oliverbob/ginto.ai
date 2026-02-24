<?php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
$csrfToken = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrfToken;
$currentPage = 'tunnels';
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="icon" type="image/png" href="/assets/images/ginto.png" />
  <title>FRP Tunnels - Server Hosting</title>
  <script src="/assets/js/tailwindcss.js"></script>
  <script>tailwind.config = { darkMode: 'class' }</script>
  <link rel="stylesheet" href="/lib/fontawesome/css/all.min.css">
</head>
<body class="min-h-screen bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100">
  <?php include __DIR__ . '/partials/header.php'; ?>
  <div class="flex h-[calc(100vh-57px)]">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <main class="flex-1 overflow-y-auto p-6">
      <div class="mb-6 flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold">FRP Tunnels</h1>
          <p class="text-gray-500">Monitor active tunnel connections</p>
        </div>
        <div class="flex gap-2">
          <button onclick="clearOffline()" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg transition-colors">
            <i class="fas fa-broom mr-2"></i>Clear Offline
          </button>
          <button onclick="loadTunnels()" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition-colors">
            <i class="fas fa-sync-alt mr-2"></i>Refresh
          </button>
        </div>
      </div>

      <!-- Stats Cards -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-emerald-500/10 flex items-center justify-center">
              <i class="fas fa-plug text-emerald-500"></i>
            </div>
            <div>
              <p class="text-2xl font-bold" id="stat-http">-</p>
              <p class="text-sm text-gray-500">HTTP Proxies</p>
            </div>
          </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-blue-500/10 flex items-center justify-center">
              <i class="fas fa-lock text-blue-500"></i>
            </div>
            <div>
              <p class="text-2xl font-bold" id="stat-https">-</p>
              <p class="text-sm text-gray-500">HTTPS Proxies</p>
            </div>
          </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-purple-500/10 flex items-center justify-center">
              <i class="fas fa-server text-purple-500"></i>
            </div>
            <div>
              <p class="text-2xl font-bold" id="stat-tcp">-</p>
              <p class="text-sm text-gray-500">TCP Proxies</p>
            </div>
          </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-amber-500/10 flex items-center justify-center">
              <i class="fas fa-users text-amber-500"></i>
            </div>
            <div>
              <p class="text-2xl font-bold" id="stat-clients">-</p>
              <p class="text-sm text-gray-500">Connected Clients</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Active Tunnels Table -->
      <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
          <h2 class="font-semibold">Active Tunnels</h2>
          <span id="registry-info" class="text-sm text-gray-500"></span>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-700/50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subdomain / Port</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Client IP</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Started</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Expires</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Traffic</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
              </tr>
            </thead>
            <tbody id="tunnels-list" class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr><td colspan="9" class="px-4 py-8 text-center text-gray-500"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Server Info -->
      <div class="mt-6 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
        <h2 class="font-semibold mb-3">FRP Server Info</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
          <div>
            <span class="text-gray-500">Server:</span>
            <span class="ml-2 font-mono">ginto.ai:7000</span>
          </div>
          <div>
            <span class="text-gray-500">HTTP VHost:</span>
            <span class="ml-2 font-mono">:7080</span>
          </div>
          <div>
            <span class="text-gray-500">HTTPS VHost:</span>
            <span class="ml-2 font-mono">:7443</span>
          </div>
          <div>
            <span class="text-gray-500">Subdomain Host:</span>
            <span class="ml-2 font-mono">*.ginto.ai</span>
          </div>
        </div>

        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
          <h3 class="font-semibold mb-2">Unified Relay (/tunnel)</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
            <div><span class="text-gray-500">Relay Domain:</span> <span id="relay-domain" class="ml-2 font-mono">-</span></div>
            <div><span class="text-gray-500">Relay Endpoint:</span> <span id="relay-endpoint" class="ml-2 font-mono">-</span></div>
            <div><span class="text-gray-500">Approval Check:</span> <span id="relay-approval-url" class="ml-2 font-mono">-</span></div>
            <div><span class="text-gray-500">Reserved Local Port:</span> <span id="relay-local-port" class="ml-2 font-mono">-</span></div>
            <div><span class="text-gray-500">Status:</span> <span id="relay-status" class="ml-2">-</span></div>
            <div><span class="text-gray-500">Expires:</span> <span id="relay-expiry" class="ml-2">-</span></div>
            <div><span class="text-gray-500">Relay Process:</span> <span id="relay-frpc-status" class="ml-2">-</span></div>
            <div><span class="text-gray-500">Relay Config:</span> <span id="relay-frpc-config" class="ml-2 font-mono">-</span></div>
            <div><span class="text-gray-500">Caddy Available:</span> <span id="relay-caddy-available" class="ml-2 font-mono">-</span></div>
            <div><span class="text-gray-500">Caddy Enabled:</span> <span id="relay-caddy-enabled" class="ml-2 font-mono">-</span></div>
            <div><span class="text-gray-500">DNS Zone:</span> <span id="relay-dns-zone" class="ml-2">-</span></div>
            <div><span class="text-gray-500">Last Approval Check:</span> <span id="relay-last-check" class="ml-2">-</span></div>
            <div><span class="text-gray-500">Check Source:</span> <span id="relay-last-check-ip" class="ml-2 font-mono">-</span></div>
          </div>
          <div class="mt-4 flex flex-wrap items-center gap-2">
            <label for="relay-approve-minutes" class="text-xs text-gray-500">Approval Duration</label>
            <select id="relay-approve-minutes" class="px-2 py-1 text-xs rounded bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600">
              <option value="60">1 hour</option>
              <option value="360">6 hours</option>
              <option value="1440" selected>24 hours</option>
              <option value="10080">7 days</option>
              <option value="43200">30 days</option>
              <option value="525600">1 year</option>
              <option value="1576800">3 years</option>
              <option value="2628000">5 years</option>
              <option value="-1">Never expire</option>
            </select>
            <button id="relay-approve-btn" onclick="approveRelay()" class="px-3 py-1.5 text-xs bg-emerald-600 hover:bg-emerald-700 text-white rounded transition-colors">
              <i class="fas fa-check mr-1"></i>Approve
            </button>
            <button id="relay-revoke-btn" onclick="revokeRelay()" class="px-3 py-1.5 text-xs bg-red-600 hover:bg-red-700 text-white rounded transition-colors">
              <i class="fas fa-ban mr-1"></i>Revoke
            </button>
          </div>
        </div>
      </div>
    </main>
  </div>

  <script>
    const csrfToken = '<?= $csrfToken ?>';
    let relayApi = { approveUrl: '/admin/hosting/tunnels/relay/approve', revokeUrl: '/admin/hosting/tunnels/relay/revoke', subdomain: 'vision' };
    const expiryUrl = '/admin/hosting/tunnels/expiry';
    const accessKeyUrl = '/admin/hosting/tunnels/access-key';

    async function loadTunnels() {
      try {
        const res = await fetch('/admin/hosting/tunnels/api');
        const data = await res.json();
        
        // Update stats
        document.getElementById('stat-http').textContent = data.stats?.http || 0;
        document.getElementById('stat-https').textContent = data.stats?.https || 0;
        document.getElementById('stat-tcp').textContent = data.stats?.tcp || 0;
        document.getElementById('stat-clients').textContent = data.stats?.clients || 0;
        
        // Registry info
        document.getElementById('registry-info').textContent = 
          `Registry: ${data.registry_count || 0} | Blocked: ${data.blocklist_count || 0}`;

        const relay = data.relay || {};
        const relayDomain = relay.domain || '-';
        const relayEndpoint = relay.endpoint || '/tunnel';
        const relayApproved = !!relay.approved;
        const relayBlocked = !!relay.blocked;
        const relayNeverExpires = !!relay.never_expires;
        const relayRemaining = Number(relay.remaining || 0);
        const relayExpiresAt = Number(relay.expires_at || 0);

        document.getElementById('relay-domain').textContent = relayDomain;
        document.getElementById('relay-endpoint').textContent = `https://ginto.ai${relayEndpoint}`;
        document.getElementById('relay-approval-url').textContent = relay.approval_url || '-';
        document.getElementById('relay-local-port').textContent = relay.local_port ? `127.0.0.1:${relay.local_port}` : '-';
        relayApi = {
          approveUrl: relay.approve_url || '/admin/hosting/tunnels/relay/approve',
          revokeUrl: relay.revoke_url || '/admin/hosting/tunnels/relay/revoke',
          subdomain: relay.subdomain || 'vision'
        };

        const relayStatusEl = document.getElementById('relay-status');
        if (relayBlocked) {
          relayStatusEl.innerHTML = '<span class="px-2 py-1 text-xs rounded bg-gray-500/10 text-gray-500">Blocked</span>';
        } else if (relayApproved) {
          relayStatusEl.innerHTML = '<span class="px-2 py-1 text-xs rounded bg-emerald-500/10 text-emerald-500">Approved</span>';
        } else {
          relayStatusEl.innerHTML = '<span class="px-2 py-1 text-xs rounded bg-amber-500/10 text-amber-500">Not Approved</span>';
        }

        const relayExpiryEl = document.getElementById('relay-expiry');
        if (relayNeverExpires) {
          relayExpiryEl.textContent = 'Never';
        } else if (relayExpiresAt > 0) {
          relayExpiryEl.textContent = relayRemaining > 0
            ? `${formatDuration(relayRemaining)} (at ${formatTime(relayExpiresAt)})`
            : `Expired at ${formatTime(relayExpiresAt)}`;
        } else {
          relayExpiryEl.textContent = '-';
        }

        const relayFrpcStatusEl = document.getElementById('relay-frpc-status');
        const relayFrpcRunning = !!relay.frpc_running;
        const relayFrpcPid = relay.frpc_pid || null;
        relayFrpcStatusEl.innerHTML = relayFrpcRunning
          ? `<span class="px-2 py-1 text-xs rounded bg-emerald-500/10 text-emerald-500">Running${relayFrpcPid ? ` (PID ${relayFrpcPid})` : ''}</span>`
          : '<span class="px-2 py-1 text-xs rounded bg-gray-500/10 text-gray-500">Stopped</span>';
        document.getElementById('relay-frpc-config').textContent = relay.frpc_config_path || '-';
        document.getElementById('relay-caddy-available').textContent = relay.caddy_available_path || '-';
        document.getElementById('relay-caddy-enabled').textContent = relay.caddy_enabled_path || '-';
        document.getElementById('relay-dns-zone').innerHTML = relay.dns_zone_exists
          ? '<span class="px-2 py-1 text-xs rounded bg-emerald-500/10 text-emerald-500">Created</span>'
          : '<span class="px-2 py-1 text-xs rounded bg-amber-500/10 text-amber-500">Missing</span>';

        const relayLastCheckAt = Number(relay.last_check_at || 0);
        const relayLastCheckCount = Number(relay.last_check_count || 0);
        const relayLastCheckEl = document.getElementById('relay-last-check');
        relayLastCheckEl.textContent = relayLastCheckAt > 0
          ? `${formatTime(relayLastCheckAt)} (#${relayLastCheckCount})`
          : '-';
        const relayLastCheckIpEl = document.getElementById('relay-last-check-ip');
        relayLastCheckIpEl.textContent = relay.last_check_ip || '-';

        const approveBtn = document.getElementById('relay-approve-btn');
        const revokeBtn = document.getElementById('relay-revoke-btn');
        approveBtn.disabled = false;
        revokeBtn.disabled = !relayApproved && !relayBlocked;
        approveBtn.classList.toggle('opacity-50', approveBtn.disabled);
        revokeBtn.classList.toggle('opacity-50', revokeBtn.disabled);
        approveBtn.innerHTML = relayApproved && !relayBlocked
          ? '<i class="fas fa-sync mr-1"></i>Reapply'
          : '<i class="fas fa-check mr-1"></i>Approve';
        
        const tbody = document.getElementById('tunnels-list');
        
        if (!data.proxies || data.proxies.length === 0) {
          tbody.innerHTML = '<tr><td colspan="9" class="px-4 py-8 text-center text-gray-500">No active tunnels</td></tr>';
          return;
        }

        tbody.innerHTML = data.proxies.map(p => {
          const subdomain = p.subdomain || p.name;
          const clientIp = p.client_ip || '-';
          const startedAt = p.started_at ? formatTime(p.started_at) : '-';
          const expiresAt = p.expires_at ? formatTime(p.expires_at) : '-';
          const remaining = p.remaining !== null ? formatDuration(p.remaining) : '-';
          const isExpired = p.expired || (p.remaining !== null && p.remaining <= 0);
          const isBlocked = p.blocked;
          
          let statusClass = 'bg-emerald-500/10 text-emerald-500';
          let statusText = p.status || 'unknown';
          
          if (isBlocked) {
            statusClass = 'bg-gray-500/10 text-gray-500';
            statusText = 'blocked';
          } else if (isExpired) {
            statusClass = 'bg-orange-500/10 text-orange-500';
            statusText = 'expired';
          } else if (p.status !== 'online') {
            statusClass = 'bg-red-500/10 text-red-500';
          }
          
          return `
          <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 ${isExpired || isBlocked ? 'opacity-60' : ''}">
            <td class="px-4 py-3">
              <div class="flex items-center gap-2">
                <i class="fas fa-network-wired text-gray-500"></i>
                <span class="font-medium">${p.name}</span>
              </div>
            </td>
            <td class="px-4 py-3">
              <span class="px-2 py-1 text-xs rounded ${getTypeColor(p.type)}">
                ${p.type.toUpperCase()}
              </span>
            </td>
            <td class="px-4 py-3 font-mono text-sm">
              ${p.subdomain ? `<a href="https://${p.subdomain}.ginto.ai" target="_blank" class="text-blue-500 hover:text-blue-400">${p.subdomain}.ginto.ai</a>` : (p.remote_port || '-')}
            </td>
            <td class="px-4 py-3 font-mono text-sm text-gray-400">
              ${clientIp}
            </td>
            <td class="px-4 py-3 text-sm text-gray-500">
              ${startedAt}
            </td>
            <td class="px-4 py-3 text-sm">
              ${isExpired 
                ? `<span class="text-red-500">${expiresAt}</span>` 
                : (remaining !== '-' 
                    ? `<span class="text-amber-500" title="Expires: ${expiresAt}">${remaining}</span>` 
                    : expiresAt)}
            </td>
            <td class="px-4 py-3">
              <span class="px-2 py-1 text-xs rounded ${statusClass}">
                ${statusText}
              </span>
            </td>
            <td class="px-4 py-3 text-sm text-gray-500">
              <span title="In">&darr; ${formatBytes(p.traffic_in || 0)}</span>
              <span class="mx-1">|</span>
              <span title="Out">&uarr; ${formatBytes(p.traffic_out || 0)}</span>
            </td>
            <td class="px-4 py-3">
              ${isBlocked 
                ? `<button onclick="unblockTunnel('${subdomain}')" class="px-2 py-1 text-xs bg-emerald-500/10 text-emerald-500 hover:bg-emerald-500/20 rounded transition-colors">
                     <i class="fas fa-check mr-1"></i>Unblock
                   </button>`
                : `<button onclick="disconnectTunnel('${subdomain}')" class="px-2 py-1 text-xs bg-red-500/10 text-red-500 hover:bg-red-500/20 rounded transition-colors">
                     <i class="fas fa-ban mr-1"></i>Block
                   </button>`}
              <div class="mt-2 flex items-center gap-1">
                <select id="expiry-${subdomain}" class="px-2 py-1 text-[11px] rounded bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600">
                  <option value="60">1h</option>
                  <option value="360">6h</option>
                  <option value="1440">24h</option>
                  <option value="10080">7d</option>
                  <option value="43200">30d</option>
                  <option value="525600">1y</option>
                  <option value="-1">Never</option>
                </select>
                <button onclick="setExpiry('${subdomain}')" class="px-2 py-1 text-[11px] bg-blue-500/10 text-blue-500 hover:bg-blue-500/20 rounded transition-colors">
                  <i class="fas fa-clock mr-1"></i>Set
                </button>
              </div>
              <div class="mt-2">
                <div class="flex items-center gap-1">
                  <input id="key-${subdomain}" type="password" placeholder="Access key" class="w-28 px-2 py-1 text-[11px] rounded bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600" />
                  <button onclick="enableAccessKey('${subdomain}')" class="px-2 py-1 text-[11px] bg-purple-500/10 text-purple-500 hover:bg-purple-500/20 rounded transition-colors">
                    <i class="fas fa-lock mr-1"></i>Enable
                  </button>
                  <button onclick="disableAccessKey('${subdomain}')" class="px-2 py-1 text-[11px] bg-gray-500/10 text-gray-500 hover:bg-gray-500/20 rounded transition-colors">
                    <i class="fas fa-unlock mr-1"></i>Disable
                  </button>
                </div>
                ${p.access_key_enabled ? '<div class="mt-1 text-[11px] text-purple-400">Key required</div>' : ''}
              </div>
            </td>
          </tr>`;
        }).join('');
        
      } catch (e) {
        console.error('Failed to load tunnels:', e);
        document.getElementById('tunnels-list').innerHTML = `
          <tr><td colspan="9" class="px-4 py-8 text-center text-red-500">
            <i class="fas fa-exclamation-triangle mr-2"></i>Failed to load: ${e.message}
          </td></tr>`;
      }
    }

    async function disconnectTunnel(subdomain) {
      if (!confirm(`Block tunnel "${subdomain}"? This will prevent reconnection.`)) return;
      
      try {
        const res = await fetch('/admin/hosting/tunnels/disconnect', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ subdomain, csrf_token: csrfToken })
        });
        const data = await res.json();
        if (data.success) {
          loadTunnels();
        } else {
          alert('Failed: ' + (data.error || 'Unknown error'));
        }
      } catch (e) {
        alert('Error: ' + e.message);
      }
    }

    async function unblockTunnel(subdomain) {
      try {
        const res = await fetch('/admin/hosting/tunnels/unblock', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ subdomain, csrf_token: csrfToken })
        });
        const data = await res.json();
        if (data.success) {
          loadTunnels();
        } else {
          alert('Failed: ' + (data.error || 'Unknown error'));
        }
      } catch (e) {
        alert('Error: ' + e.message);
      }
    }

    async function clearOffline() {
      try {
        const res = await fetch('/admin/hosting/tunnels/clear-offline', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ csrf_token: csrfToken })
        });
        const data = await res.json();
        if (data.success) {
          loadTunnels();
        } else {
          alert('Failed: ' + (data.error || 'Unknown error'));
        }
      } catch (e) {
        alert('Error: ' + e.message);
      }
    }

    async function setExpiry(subdomain) {
      const sel = document.getElementById(`expiry-${subdomain}`);
      const minutes = parseInt(sel?.value || '60', 10);
      try {
        const res = await fetch(expiryUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ subdomain, minutes, csrf_token: csrfToken })
        });
        const data = await res.json();
        if (data.success) {
          loadTunnels();
          return;
        }
        alert('Failed: ' + (data.error || 'Unknown error'));
      } catch (e) {
        alert('Error: ' + e.message);
      }
    }

    async function enableAccessKey(subdomain) {
      const key = (document.getElementById(`key-${subdomain}`)?.value || '').trim();
      try {
        const res = await fetch(accessKeyUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ subdomain, access_key: key, enabled: true, csrf_token: csrfToken })
        });
        const data = await res.json();
        if (data.success) {
          loadTunnels();
          return;
        }
        alert('Failed: ' + (data.error || 'Unknown error'));
      } catch (e) {
        alert('Error: ' + e.message);
      }
    }

    async function disableAccessKey(subdomain) {
      try {
        const res = await fetch(accessKeyUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ subdomain, enabled: false, csrf_token: csrfToken })
        });
        const data = await res.json();
        if (data.success) {
          loadTunnels();
          return;
        }
        alert('Failed: ' + (data.error || 'Unknown error'));
      } catch (e) {
        alert('Error: ' + e.message);
      }
    }

    async function approveRelay() {
      const minutesEl = document.getElementById('relay-approve-minutes');
      const minutes = parseInt(minutesEl.value || '1440', 10);
      try {
        const res = await fetch(relayApi.approveUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ subdomain: relayApi.subdomain, minutes, csrf_token: csrfToken })
        });
        const data = await res.json();
        if (data.success) {
          alert(data.message || 'Relay approved');
          loadTunnels();
          return;
        }
        alert('Failed: ' + (data.error || 'Unknown error'));
      } catch (e) {
        alert('Error: ' + e.message);
      }
    }

    async function revokeRelay() {
      if (!confirm('Revoke relay approval and block it?')) return;
      try {
        const res = await fetch(relayApi.revokeUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ subdomain: relayApi.subdomain, csrf_token: csrfToken })
        });
        const data = await res.json();
        if (data.success) {
          alert(data.message || 'Relay revoked');
          loadTunnels();
          return;
        }
        alert('Failed: ' + (data.error || 'Unknown error'));
      } catch (e) {
        alert('Error: ' + e.message);
      }
    }

    function getTypeColor(type) {
      const colors = {
        http: 'bg-emerald-500/10 text-emerald-500',
        https: 'bg-blue-500/10 text-blue-500',
        tcp: 'bg-purple-500/10 text-purple-500',
        udp: 'bg-amber-500/10 text-amber-500',
        stcp: 'bg-pink-500/10 text-pink-500'
      };
      return colors[type] || 'bg-gray-500/10 text-gray-500';
    }

    function formatBytes(bytes) {
      if (bytes === 0) return '0 B';
      const k = 1024;
      const sizes = ['B', 'KB', 'MB', 'GB'];
      const i = Math.floor(Math.log(bytes) / Math.log(k));
      return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    }

    function formatTime(timestamp) {
      const d = new Date(timestamp * 1000);
      return d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    }

    function formatDuration(seconds) {
      if (seconds <= 0) return 'Expired';
      const m = Math.floor(seconds / 60);
      const s = seconds % 60;
      if (m > 0) return `${m}m ${s}s`;
      return `${s}s`;
    }

    // Auto-refresh every 10 seconds
    loadTunnels();
    setInterval(loadTunnels, 10000);
  </script>
</body>
</html>
