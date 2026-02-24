<?php
/**
 * Account Keys UI
 * Lets logged-in users generate/view/revoke tunnel access keys.
 */
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__, 3));
}
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

if (empty($_SESSION['csrf_token'])) {
  try {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  } catch (\Throwable $e) {
    $_SESSION['csrf_token'] = '';
  }
}

include ROOT_PATH . '/src/Views/layout/header.php';
include ROOT_PATH . '/src/Views/layout/sidebar.php';

$csrf = $_SESSION['csrf_token'] ?? '';
?>

<div id="mainContent" class="p-6">
  <h1 class="text-2xl font-bold mb-2">Account Keys</h1>
  <p class="text-gray-500 mb-6">Generate and manage tunnel access tokens (required to view tunneled pages).</p>

  <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 mb-6" style="max-width: 860px;">
    <h2 class="font-semibold mb-3">Generate Key</h2>
    <div class="flex flex-wrap gap-2 items-end">
      <div style="min-width:200px;">
        <label class="block text-sm text-gray-500 mb-1">Subdomain</label>
        <input id="akSubdomain" type="text" placeholder="test" class="w-full px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900" />
      </div>
      <div style="min-width:200px;">
        <label class="block text-sm text-gray-500 mb-1">TTL</label>
        <select id="akTtl" class="w-full px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900">
          <option value="3600" selected>1 hour</option>
          <option value="21600">6 hours</option>
          <option value="86400">24 hours</option>
        </select>
      </div>
      <button id="akGenerate" class="px-4 py-2 rounded bg-emerald-600 hover:bg-emerald-700 text-white">Generate</button>
    </div>

    <div id="akResult" class="mt-4" style="display:none;">
      <div class="p-3 rounded border border-emerald-500/30 bg-emerald-500/10">
        <div class="text-sm text-gray-500 mb-2">Copy this token now (it is shown only once):</div>
        <div style="display:flex; gap:8px; align-items:center; flex-wrap: wrap;">
          <input id="akToken" type="text" readonly class="flex-1 min-w-[320px] px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 font-mono text-xs" />
          <button id="akCopy" class="px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-700">Copy</button>
        </div>
        <div class="mt-3 text-sm text-gray-500">Link format:</div>
        <div style="display:flex; gap:8px; align-items:center; flex-wrap: wrap;">
          <input id="akLink" type="text" readonly class="flex-1 min-w-[320px] px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 font-mono text-xs" />
          <button id="akCopyLink" class="px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-700">Copy link</button>
        </div>
      </div>
    </div>
  </div>

  <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700" style="max-width: 860px;">
    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
      <h2 class="font-semibold">Your Keys</h2>
      <button class="px-3 py-1.5 rounded bg-gray-700 hover:bg-gray-800 text-white" onclick="loadKeys()">Refresh</button>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-700/50">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subdomain</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Expires</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last used</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
          </tr>
        </thead>
        <tbody id="akList" class="divide-y divide-gray-200 dark:divide-gray-700">
          <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">Loading…</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
  const csrfToken = <?= json_encode($csrf) ?>;
  function esc(s){ return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c])); }

  async function loadKeys() {
    const tbody = document.getElementById('akList');
    tbody.innerHTML = '<tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">Loading…</td></tr>';
    try {
      const res = await fetch('/api/tunnel/access-keys', { credentials: 'same-origin' });
      const data = await res.json();
      const keys = Array.isArray(data.keys) ? data.keys : [];
      if (!keys.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">No keys yet</td></tr>';
        return;
      }
      tbody.innerHTML = keys.map(k => {
        const revoked = Number(k.revoked || 0) === 1;
        const status = revoked ? 'revoked' : 'active';
        const statusBadge = revoked
          ? '<span class="px-2 py-1 text-xs rounded bg-gray-500/10 text-gray-500">revoked</span>'
          : '<span class="px-2 py-1 text-xs rounded bg-emerald-500/10 text-emerald-500">active</span>';
        const btn = revoked
          ? ''
          : `<button class="px-3 py-1.5 rounded bg-red-600 hover:bg-red-700 text-white" onclick="revokeKey(${Number(k.id)})">Revoke</button>`;
        return `
          <tr>
            <td class="px-4 py-3 font-mono">${esc(k.subdomain)}</td>
            <td class="px-4 py-3">${esc(k.created_at)}</td>
            <td class="px-4 py-3">${esc(k.expires_at || '')}</td>
            <td class="px-4 py-3">${esc(k.last_used_at || '')}</td>
            <td class="px-4 py-3">${statusBadge}</td>
            <td class="px-4 py-3">${btn}</td>
          </tr>
        `;
      }).join('');
    } catch (e) {
      tbody.innerHTML = '<tr><td colspan="6" class="px-4 py-6 text-center text-red-500">Failed to load keys</td></tr>';
    }
  }

  async function revokeKey(id) {
    if (!confirm('Revoke this key?')) return;
    try {
      const res = await fetch('/api/tunnel/access-key/revoke', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, csrf_token: csrfToken })
      });
      const data = await res.json();
      if (!data.success) {
        alert(data.error || 'Failed to revoke');
        return;
      }
      loadKeys();
    } catch (e) {
      alert('Error: ' + e.message);
    }
  }

  async function generateKey() {
    const subdomain = (document.getElementById('akSubdomain').value || '').trim().toLowerCase();
    const ttl = parseInt(document.getElementById('akTtl').value || '3600', 10);
    if (!subdomain) {
      alert('Enter a subdomain');
      return;
    }
    try {
      const res = await fetch('/api/tunnel/access-key/generate', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ subdomain, ttl_seconds: ttl, csrf_token: csrfToken })
      });
      const data = await res.json();
      if (!data.success) {
        alert(data.error || 'Failed to generate');
        return;
      }
      const token = data.token;
      const link = `https://${subdomain}.ginto.ai/?token=${encodeURIComponent(token)}`;
      document.getElementById('akToken').value = token;
      document.getElementById('akLink').value = link;
      document.getElementById('akResult').style.display = 'block';
      loadKeys();
    } catch (e) {
      alert('Error: ' + e.message);
    }
  }

  function copyValue(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.select();
    el.setSelectionRange(0, 99999);
    try {
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(el.value);
      } else {
        document.execCommand('copy');
      }
    } catch (e) {}
  }

  document.getElementById('akGenerate').addEventListener('click', generateKey);
  document.getElementById('akCopy').addEventListener('click', () => copyValue('akToken'));
  document.getElementById('akCopyLink').addEventListener('click', () => copyValue('akLink'));
  loadKeys();
</script>

<?php include ROOT_PATH . '/src/Views/layout/footer.php'; ?>
