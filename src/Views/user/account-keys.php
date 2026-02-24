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

$paypalEnv = strtolower((string)(getenv('PAYPAL_ENVIRONMENT') ?: ($_ENV['PAYPAL_ENVIRONMENT'] ?? 'sandbox')));
$paypalEnv = ($paypalEnv === 'live' || $paypalEnv === 'production') ? 'live' : 'sandbox';
$paypalClientId = ($paypalEnv === 'sandbox')
  ? (getenv('PAYPAL_CLIENT_ID_SANDBOX') ?: ($_ENV['PAYPAL_CLIENT_ID_SANDBOX'] ?? ''))
  : (getenv('PAYPAL_CLIENT_ID') ?: ($_ENV['PAYPAL_CLIENT_ID'] ?? ''));
$userId = (int)($_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? 0));
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
      <button id="akGenerate" type="button" class="px-4 py-2 rounded bg-emerald-600 hover:bg-emerald-700 text-white">Generate</button>
    </div>

    <div id="akResult" class="mt-4" style="display:none;">
      <div class="p-3 rounded border border-emerald-500/30 bg-emerald-500/10">
        <div class="text-sm text-gray-500 mb-2">Copy this token now (it is shown only once):</div>
        <div style="display:flex; gap:8px; align-items:center; flex-wrap: wrap;">
          <div class="flex-1 min-w-[320px]" style="position:relative;">
            <input id="akToken" type="text" readonly class="w-full px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 font-mono text-xs" style="padding-right:40px;" />
            <button id="akTokenEye" type="button" title="Show/Hide" aria-label="Show/Hide" style="position:absolute; right:8px; top:50%; transform:translateY(-50%); padding:4px; border-radius:8px; border:0; background:transparent; cursor:pointer; color:#64748b;">
              <i id="akTokenEyeIcon" class="fas fa-eye"></i>
            </button>
          </div>
          <button id="akCopy" type="button" class="px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-700">Copy</button>
        </div>
        <div class="mt-3 text-sm text-gray-500">Link format:</div>
        <div style="display:flex; gap:8px; align-items:center; flex-wrap: wrap;">
          <input id="akLink" type="text" readonly class="flex-1 min-w-[320px] px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 font-mono text-xs" />
          <button id="akCopyLink" type="button" class="px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-700">Copy link</button>
        </div>
      </div>
    </div>
  </div>

  <div id="serverlessUpgradeModal" style="display:none; position:fixed; inset:0; z-index:9999;">
    <div style="position:absolute; inset:0; background:rgba(0,0,0,0.55);"></div>
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700" style="position:relative; width:min(560px, calc(100% - 24px)); margin:10vh auto 0 auto; padding:18px;">
      <div class="flex items-start justify-between gap-3">
        <div>
          <div class="text-lg font-semibold">Serverless Subscription</div>
          <div class="text-sm text-gray-500">$105 / month per additional key</div>
        </div>
        <button id="serverlessUpgradeClose" type="button" class="px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-700">Close</button>
      </div>

      <div class="mt-4 text-sm text-gray-600 dark:text-gray-300">
        <div class="font-semibold mb-2">Benefits</div>
        <ul class="list-disc" style="padding-left:18px;">
          <li>Create additional web server tunnel keys beyond the free limit</li>
          <li>Each active subscription adds 1 extra unrevoked key slot</li>
          <li>Instant activation after PayPal approval</li>
          <li>Cancel anytime</li>
        </ul>
      </div>

      <div id="serverlessUpgradeError" class="mt-4" style="display:none;"></div>

      <div class="mt-4">
        <div id="serverlessPaypalLoading" class="text-sm text-gray-500">Loading PayPal…</div>
        <div id="serverlessPaypalButtons" style="margin-top:10px;"></div>
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
  // PayPal config (client-id is public; never embed any PayPal secrets in HTML/JS)
  const paypalEnv = <?= json_encode($paypalEnv) ?>;
  const paypalClientId = <?= json_encode($paypalClientId) ?>;
  const currentUserId = <?= (int)$userId ?>;

  const csrfToken = <?= json_encode($csrf) ?>;
  function esc(s){ return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c])); }

  function uiModal(title, message) {
    try {
      if (typeof window.showModal === 'function') {
        window.showModal(title, message, 'fas fa-exclamation-circle', 'text-yellow-500');
        return;
      }
    } catch (e) {}
    alert(message || title || '');
  }

  function setTempButtonState(btn, text, ms) {
    if (!btn) return;
    const prev = btn.textContent;
    btn.textContent = text;
    setTimeout(() => { btn.textContent = prev; }, ms || 1200);
  }

  async function copyTextToClipboard(text) {
    if (!text) return false;
    try {
      if (navigator.clipboard && navigator.clipboard.writeText && window.isSecureContext) {
        await navigator.clipboard.writeText(text);
        return true;
      }
    } catch (e) {
      // fall through
    }
    try {
      const ta = document.createElement('textarea');
      ta.value = text;
      ta.setAttribute('readonly', '');
      ta.style.position = 'fixed';
      ta.style.opacity = '0';
      ta.style.left = '-9999px';
      document.body.appendChild(ta);
      ta.focus();
      ta.select();
      const ok = document.execCommand('copy');
      document.body.removeChild(ta);
      return !!ok;
    } catch (e) {
      return false;
    }
  }

  function maskKey(key) {
    const s = String(key || '');
    if (!s) return '';
    const keep = Math.min(12, s.length);
    return s.slice(0, keep) + '********';
  }

  function setMaskedValue(input, full, revealed) {
    if (!input) return;
    input.dataset.full = String(full || '');
    input.dataset.revealed = revealed ? '1' : '0';
    input.value = revealed ? String(full || '') : maskKey(full);
  }

  function setEyeIcon(revealed) {
    const icon = document.getElementById('akTokenEyeIcon');
    if (icon) icon.className = revealed ? 'fas fa-eye-slash' : 'fas fa-eye';
  }

  function showServerlessUpgradeModal() {
    const modal = document.getElementById('serverlessUpgradeModal');
    if (!modal) return;
    modal.style.display = 'block';
    initServerlessPaypalButtons();
  }

  function hideServerlessUpgradeModal() {
    const modal = document.getElementById('serverlessUpgradeModal');
    if (!modal) return;
    modal.style.display = 'none';
  }

  async function loadAddonInfo(addonType) {
    const res = await fetch('/api/addon/info/' + encodeURIComponent(addonType), { credentials: 'same-origin' });
    const data = await res.json();
    if (!data || !data.success) throw new Error(data?.error || 'Failed to load addon info');
    return data;
  }

  function loadPayPalSdkOnce() {
    return new Promise((resolve, reject) => {
      if (window.paypal && window.paypal.Buttons) return resolve(true);
      if (!paypalClientId) return reject(new Error('PayPal client ID not configured'));
      const existing = document.getElementById('paypal-sdk');
      if (existing) {
        existing.addEventListener('load', () => resolve(true));
        existing.addEventListener('error', () => reject(new Error('Failed to load PayPal SDK')));
        return;
      }
      const script = document.createElement('script');
      script.id = 'paypal-sdk';
      script.src = 'https://www.paypal.com/sdk/js?client-id=' + encodeURIComponent(paypalClientId) + '&vault=true&intent=subscription&currency=USD';
      script.onload = () => resolve(true);
      script.onerror = () => reject(new Error('Failed to load PayPal SDK'));
      document.head.appendChild(script);
    });
  }

  let serverlessPaypalInit = false;
  async function initServerlessPaypalButtons() {
    if (serverlessPaypalInit) return;
    serverlessPaypalInit = true;

    const loading = document.getElementById('serverlessPaypalLoading');
    const buttons = document.getElementById('serverlessPaypalButtons');
    const errBox = document.getElementById('serverlessUpgradeError');
    const showErr = (msg) => {
      if (!errBox) return;
      errBox.style.display = 'block';
      errBox.className = 'mt-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 p-3 rounded';
      errBox.textContent = msg || 'Upgrade failed.';
    };

    try {
      const info = await loadAddonInfo('serverless_key');
      const planId = info.paypal_plan_id;
      if (!planId) throw new Error('Serverless plan is not configured');

      await loadPayPalSdkOnce();
      if (loading) loading.style.display = 'none';

      window.paypal.Buttons({
        style: { shape: 'rect', color: 'blue', layout: 'vertical', label: 'subscribe' },
        createSubscription: function(data, actions) {
          return actions.subscription.create({
            plan_id: planId,
            custom_id: String(currentUserId || '')
          });
        },
        onApprove: function(data) {
          // Activate addon server-side (verifies with PayPal)
          return fetch('/api/addon/activate', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ addon_type: 'serverless_key', subscription_id: data.subscriptionID })
          })
          .then(r => r.json())
          .then(result => {
            if (!result || !result.success) throw new Error(result?.error || 'Activation failed');
            hideServerlessUpgradeModal();
            // retry original action after successful upgrade
            serverlessPaypalInit = false;
            const btn = document.getElementById('serverlessPaypalButtons');
            if (btn) btn.innerHTML = '';
            if (loading) loading.style.display = 'block';
            generateKey();
          })
          .catch(e => { showErr(e.message); });
        },
        onError: function(err) {
          showErr('PayPal error. Please try again.');
          console.error(err);
        }
      }).render(buttons);
    } catch (e) {
      if (loading) loading.textContent = 'Unable to load PayPal.';
      showErr(e.message);
      serverlessPaypalInit = false;
    }
  }

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
    const doRevoke = () => (async () => {
    try {
      const res = await fetch('/api/tunnel/access-key/revoke', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, csrf_token: csrfToken })
      });
      const data = await res.json();
      if (!data.success) {
        uiModal('Revoke failed', data.error || 'Failed to revoke');
        return;
      }
      loadKeys();
    } catch (e) {
      uiModal('Network error', 'Error: ' + e.message);
    }
    })();

    try {
      if (typeof window.showConfirmModal === 'function') {
        window.showConfirmModal('Revoke key?', 'This key will stop working immediately.', doRevoke, null, 'Revoke');
        return;
      }
    } catch (e) {}

    if (confirm('Revoke this key?')) {
      doRevoke();
    }
  }

  async function generateKey() {
    const subdomain = (document.getElementById('akSubdomain').value || '').trim().toLowerCase();
    const ttl = parseInt(document.getElementById('akTtl').value || '3600', 10);
    if (!subdomain) {
      uiModal('Missing subdomain', 'Enter a subdomain');
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
        if (data.code === 'KEY_LIMIT_REACHED') {
          showServerlessUpgradeModal();
          return;
        }
        uiModal('Generate failed', data.error || 'Failed to generate');
        return;
      }
      const token = data.token;
      const link = `https://${subdomain}.ginto.ai/?token=${encodeURIComponent(token)}`;
      setMaskedValue(document.getElementById('akToken'), token, false);
      setEyeIcon(false);
      const linkEl = document.getElementById('akLink');
      if (linkEl) {
        linkEl.dataset.full = link;
        linkEl.value = link;
      }
      document.getElementById('akResult').style.display = 'block';
      loadKeys();
    } catch (e) {
      uiModal('Network error', 'Error: ' + e.message);
    }
  }

  async function copyValue(id, btnId) {
    const el = document.getElementById(id);
    const btn = btnId ? document.getElementById(btnId) : null;
    if (!el) return;
    const text = (el.dataset && el.dataset.full) ? el.dataset.full : el.value;
    const ok = await copyTextToClipboard(text);
    if (ok) {
      setTempButtonState(btn, 'Copied', 1200);
    } else {
      setTempButtonState(btn, 'Copy failed', 1400);
      uiModal('Copy failed', 'Your browser blocked clipboard access.');
    }
  }

  document.getElementById('akGenerate').addEventListener('click', generateKey);
  document.getElementById('akCopy').addEventListener('click', () => copyValue('akToken','akCopy'));
  document.getElementById('akCopyLink').addEventListener('click', () => copyValue('akLink','akCopyLink'));

  const eye = document.getElementById('akTokenEye');
  if (eye) {
    eye.addEventListener('click', () => {
      const input = document.getElementById('akToken');
      if (!input) return;
      const full = (input.dataset && input.dataset.full) ? input.dataset.full : input.value;
      const revealed = (input.dataset && input.dataset.revealed === '1');
      setMaskedValue(input, full, !revealed);
      setEyeIcon(!revealed);
    });
  }

  const upgradeClose = document.getElementById('serverlessUpgradeClose');
  upgradeClose && upgradeClose.addEventListener('click', hideServerlessUpgradeModal);

  loadKeys();
</script>

<?php include ROOT_PATH . '/src/Views/layout/footer.php'; ?>
