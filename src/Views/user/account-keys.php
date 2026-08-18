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

<style>
  .ak-generate-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 10px;
    align-items: end;
  }
  .ak-generate-btn {
    width: 100%;
    min-height: 42px;
  }
  .ak-action-wrap {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
  }

  @media (max-width: 767px) {
    .ak-table-wrap table,
    .ak-table-wrap thead,
    .ak-table-wrap tbody,
    .ak-table-wrap th,
    .ak-table-wrap td,
    .ak-table-wrap tr {
      display: block;
      width: 100%;
    }
    .ak-table-wrap thead {
      display: none;
    }
    .ak-table-wrap tr {
      border-top: 1px solid rgba(148, 163, 184, 0.25);
      padding: 10px 12px;
    }
    .ak-table-wrap td {
      border: 0;
      padding: 6px 0;
      display: flex;
      justify-content: space-between;
      gap: 10px;
      align-items: center;
    }
    .ak-table-wrap td::before {
      content: attr(data-label);
      font-size: 11px;
      font-weight: 600;
      letter-spacing: .02em;
      text-transform: uppercase;
      color: #64748b;
      flex: 0 0 92px;
    }
    .ak-table-wrap td[data-label="Action"] {
      align-items: flex-start;
    }
    .ak-table-wrap td[data-label="Action"]::before {
      padding-top: 6px;
    }
  }

  @media (min-width: 768px) {
    .ak-generate-grid {
      grid-template-columns: minmax(220px, 1fr) minmax(220px, 1fr) auto;
    }
    .ak-generate-btn {
      min-width: 130px;
      width: auto;
    }
  }
</style>

<div id="mainContent" class="p-6">
  <h1 class="text-2xl font-bold mb-2">Account Keys</h1>
  <p class="text-gray-500 mb-6">Generate and manage tunnel access tokens. First active key is free; additional active key slots are $10/month each.</p>

  <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 mb-6" style="max-width: 860px;">
    <h2 class="font-semibold mb-3">Generate Key</h2>
    <div class="ak-generate-grid">
      <div>
        <label class="block text-sm text-gray-500 mb-1">Subdomain</label>
        <input id="akSubdomain" type="text" placeholder="test" class="w-full px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900" />
      </div>
      <div>
        <label class="block text-sm text-gray-500 mb-1">TTL</label>
        <select id="akTtl" class="w-full px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900">
          <option value="3600" data-months="0" selected>1 hour</option>
          <option value="21600" data-months="0">6 hours</option>
          <option value="86400" data-months="0">24 hours</option>
          <option value="2592000" data-months="1">1 month</option>
          <option value="31536000" data-months="12">1 year</option>
          <option value="94608000" data-months="36">3 years</option>
          <option value="157680000" data-months="60">5 years</option>
        </select>
      </div>
      <button id="akGenerate" type="button" class="ak-generate-btn px-4 py-2 rounded bg-emerald-600 hover:bg-emerald-700 text-white">Generate</button>
    </div>
    <div class="text-xs text-gray-500 mt-2" style="line-height:1.35; max-width:720px;">
      TTL controls how long this key remains valid. After it expires, the subdomain will show Unauthorized until you provide a new key.
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

  <div id="serverlessUpgradeModal" style="display:none; position:fixed; inset:0; z-index:9999; align-items:center; justify-content:center; padding:12px;">
    <div id="serverlessUpgradeBackdrop" style="position:absolute; inset:0; background:rgba(0,0,0,0.55);"></div>
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700" style="position:relative; width:min(560px, calc(100% - 24px)); padding:18px;">
      <div class="flex items-start justify-between gap-3">
        <div>
          <div class="text-lg font-semibold">Serverless Subscription</div>
          <div id="serverlessUpgradePrice" class="text-sm text-gray-500">$10 / month per additional key</div>
          <div id="serverlessUpgradeMode" class="text-xs text-gray-500 mt-1">Billing mode: One-time payment for 1 month</div>
        </div>
        <button id="serverlessUpgradeClose" type="button" class="px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-700">Close</button>
      </div>

      <div class="mt-4 text-sm text-gray-600 dark:text-gray-300">
        <div class="font-semibold mb-2">Benefits</div>
        <ul class="list-disc" style="padding-left:18px;">
          <li>Create additional web server tunnel keys beyond the 1 free active key</li>
          <li>Each completed payment adds 1 extra unrevoked key slot for the selected term</li>
          <li>Instant activation after PayPal approval/capture</li>
          <li>Cancel anytime</li>
        </ul>
      </div>

      <div id="serverlessUpgradeError" class="mt-4" style="display:none;"></div>

      <div class="mt-4">
        <div id="serverlessPaypalLoading" class="text-sm text-gray-500">Loading PayPal…</div>
        <div id="serverlessPaypalButtons" style="margin-top:10px;"></div>
        <div id="serverlessOneTime" style="display:none; margin-top:10px;">
          <button id="serverlessOneTimePay" type="button" class="px-4 py-2 rounded bg-blue-600 hover:bg-blue-700 text-white border border-blue-700" style="font-weight:600; background:#0070ba; color:#fff; border-color:#005ea6;">Pay with PayPal</button>
          <div class="text-xs text-gray-500 mt-2">One-time payment. Grants 1 additional key slot until the selected TTL expires.</div>
        </div>
      </div>
    </div>
  </div>

  <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700" style="max-width: 860px;">
    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
      <h2 class="font-semibold">Your Keys</h2>
      <button class="px-3 py-1.5 rounded bg-gray-700 hover:bg-gray-800 text-white" onclick="loadKeys()">Refresh</button>
    </div>
    <div class="ak-table-wrap overflow-x-auto">
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
    modal.style.display = 'flex';
    const err = document.getElementById('serverlessUpgradeError');
    if (err) err.style.display = 'none';
    const oneTimeBtn = document.getElementById('serverlessOneTimePay');
    if (oneTimeBtn) {
      oneTimeBtn.disabled = false;
      oneTimeBtn.textContent = 'Pay with PayPal';
    }
    initServerlessPaypalButtons();
  }

  function hideServerlessUpgradeModal() {
    const modal = document.getElementById('serverlessUpgradeModal');
    if (!modal) return;
    modal.style.display = 'none';
  }

  function updateServerlessModeLabel(months, mode) {
    const el = document.getElementById('serverlessUpgradeMode');
    if (!el) return;
    const m = Math.max(1, parseInt(String(months || 1), 10) || 1);
    if (mode === 'one_time') {
      if (m < 12) {
        el.textContent = 'Billing mode: One-time payment for ' + m + ' month' + (m > 1 ? 's' : '');
      } else {
        const years = Math.max(1, Math.floor(m / 12));
        el.textContent = 'Billing mode: One-time payment for ' + years + ' year' + (years > 1 ? 's' : '');
      }
      return;
    }
    if (m >= 12) {
      el.textContent = 'Billing mode: Yearly subscription';
      return;
    }
    el.textContent = 'Billing mode: Monthly subscription';
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
  let serverlessAddonType = 'serverless_key_1m';
  let serverlessTtlMonths = 1;
  let serverlessPaymentMode = 'subscription'; // subscription | one_time

  function getSelectedTtlInfo() {
    const sel = document.getElementById('akTtl');
    const opt = sel && sel.options ? sel.options[sel.selectedIndex] : null;
    const seconds = parseInt(sel?.value || '3600', 10);
    const months = parseInt(opt?.getAttribute('data-months') || '0', 10);
    return { seconds: Number.isFinite(seconds) ? seconds : 3600, months: Number.isFinite(months) ? months : 0 };
  }

  function addonTypeForMonths(months) {
    // Compute only in months, then map to pre-created PayPal addon plans.
    if (months <= 1) return 'serverless_key_1m';
    // PayPal subscription plans do not support multi-year interval counts reliably;
    // bill annually for any year-based TTL.
    if (months >= 12) return 'serverless_key_1y';
    return 'serverless_key_1m';
  }

  function paymentModeForMonths(months) {
    return 'one_time';
  }

  function updateServerlessPriceLabel(months) {
    const el = document.getElementById('serverlessUpgradePrice');
    if (!el) return;
    const m = Math.max(1, parseInt(String(months || 1), 10) || 1);
    const total = 10 * m;
    if (m === 1) {
      el.textContent = '$10 / month per additional key';
      return;
    }
    if (m >= 36) {
      const years = Math.floor(m / 12);
      el.textContent = '$10 / month per additional key • one-time payment ($' + total + ' for ' + years + ' years)';
      return;
    }
    if (m >= 12) {
      el.textContent = '$10 / month per additional key • billed yearly ($120 / year)';
      return;
    }
    el.textContent = '$10 / month per additional key • billed for ' + m + ' months ($' + total + ')';
  }

  async function initServerlessPaypalButtons() {
    // One-time flow: show a simple PayPal redirect button instead of subscription SDK.
    const oneTimeWrap = document.getElementById('serverlessOneTime');
    const oneTimeBtn = document.getElementById('serverlessOneTimePay');
    const loading = document.getElementById('serverlessPaypalLoading');
    const buttons = document.getElementById('serverlessPaypalButtons');
    const errBox = document.getElementById('serverlessUpgradeError');
    const showErr = (msg) => {
      if (!errBox) return;
      errBox.style.display = 'block';
      errBox.className = 'mt-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 p-3 rounded';
      errBox.textContent = msg || 'Upgrade failed.';
    };

    if (serverlessPaymentMode === 'one_time') {
      if (buttons) buttons.innerHTML = '';
      if (loading) loading.style.display = 'none';
      if (oneTimeWrap) oneTimeWrap.style.display = 'block';

      if (oneTimeBtn && !oneTimeBtn.dataset.bound) {
        oneTimeBtn.dataset.bound = '1';
        oneTimeBtn.addEventListener('click', async () => {
          try {
            oneTimeBtn.disabled = true;
            oneTimeBtn.textContent = 'Redirecting…';
            const res = await fetch('/api/addon/one-time/create-order', {
              method: 'POST',
              credentials: 'same-origin',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ months: serverlessTtlMonths, csrf_token: csrfToken })
            });
            const data = await res.json();
            if (!data || !data.success) throw new Error(data?.error || 'Failed to start PayPal checkout');
            window.location.href = data.approve_url;
          } catch (e) {
            showErr(e.message);
            oneTimeBtn.disabled = false;
            oneTimeBtn.textContent = 'Pay with PayPal';
          }
        });
      }
      return;
    }

    if (serverlessPaypalInit) return;
    serverlessPaypalInit = true;

    if (oneTimeWrap) oneTimeWrap.style.display = 'none';
    if (loading) loading.style.display = 'block';

    try {
      const info = await loadAddonInfo(serverlessAddonType);
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
            body: JSON.stringify({ addon_type: serverlessAddonType, subscription_id: data.subscriptionID })
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
        const expMs = k.expires_at ? Date.parse(String(k.expires_at).replace(' ', 'T') + 'Z') : NaN;
        const expired = !revoked && Number.isFinite(expMs) && expMs <= Date.now();
        const statusBadge = revoked
          ? '<span class="px-2 py-1 text-xs rounded bg-gray-500/10 text-gray-500">revoked</span>'
          : (expired
              ? '<span class="px-2 py-1 text-xs rounded bg-amber-500/10 text-amber-600">expired</span>'
              : '<span class="px-2 py-1 text-xs rounded bg-emerald-500/10 text-emerald-500">active</span>'
            );
        const canReactivate = revoked || expired;
        const revokeBtn = revoked
          ? ''
          : `<button class="px-3 py-1.5 rounded bg-red-600 hover:bg-red-700 text-white" onclick="revokeKey(${Number(k.id)})">Revoke</button>`;
        const reactivateBtn = canReactivate
          ? `<button class="px-3 py-1.5 rounded bg-emerald-600 hover:bg-emerald-700 text-white" onclick="reactivateKey(${Number(k.id)}, '${esc(k.subdomain)}')">Reactivate</button>`
          : '';
        const deleteBtn = `<button class="px-3 py-1.5 rounded bg-gray-600 hover:bg-gray-700 text-white" onclick="deleteKey(${Number(k.id)})">Delete</button>`;
        const actionButtons = [reactivateBtn, revokeBtn, deleteBtn].filter(Boolean).join(' ');
        return `
          <tr>
            <td class="px-4 py-3 font-mono" data-label="Subdomain">${esc(k.subdomain)}</td>
            <td class="px-4 py-3" data-label="Created">${esc(k.created_at)}</td>
            <td class="px-4 py-3" data-label="Expires">${esc(k.expires_at || '')}</td>
            <td class="px-4 py-3" data-label="Last used">${esc(k.last_used_at || '')}</td>
            <td class="px-4 py-3" data-label="Status">${statusBadge}</td>
            <td class="px-4 py-3" data-label="Action"><div class="ak-action-wrap">${actionButtons}</div></td>
          </tr>
        `;
      }).join('');
    } catch (e) {
      tbody.innerHTML = '<tr><td colspan="6" class="px-4 py-6 text-center text-red-500">Failed to load keys</td></tr>';
    }
  }

  function showGeneratedToken(subdomain, token) {
    const link = `https://${subdomain}.silverqueen.pro/?token=${encodeURIComponent(token)}`;
    setMaskedValue(document.getElementById('akToken'), token, false);
    setEyeIcon(false);
    const linkEl = document.getElementById('akLink');
    if (linkEl) {
      linkEl.dataset.full = link;
      linkEl.value = link;
    }
    document.getElementById('akResult').style.display = 'block';
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
    const ttlInfo = getSelectedTtlInfo();
    const ttl = ttlInfo.seconds;
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
          serverlessTtlMonths = Math.max(1, ttlInfo.months || 1);
          serverlessPaymentMode = paymentModeForMonths(serverlessTtlMonths);
          serverlessAddonType = addonTypeForMonths(serverlessTtlMonths);
          updateServerlessPriceLabel(serverlessTtlMonths);
          updateServerlessModeLabel(serverlessTtlMonths, serverlessPaymentMode);
          // Reset PayPal button render for a potentially different plan.
          serverlessPaypalInit = false;
          const btn = document.getElementById('serverlessPaypalButtons');
          if (btn) btn.innerHTML = '';
          const loading = document.getElementById('serverlessPaypalLoading');
          if (loading) loading.style.display = 'block';
          showServerlessUpgradeModal();
          return;
        }
        if (data.code === 'DOMAIN_NOT_AVAILABLE') {
          uiModal('Domain not available', 'That subdomain is not available. Choose another.');
          return;
        }
        uiModal('Generate failed', data.error || 'Failed to generate');
        return;
      }
      showGeneratedToken(subdomain, data.token);
      loadKeys();
    } catch (e) {
      uiModal('Network error', 'Error: ' + e.message);
    }
  }

  async function deleteKey(id) {
    const doDelete = () => (async () => {
      try {
        const res = await fetch('/api/tunnel/access-key/delete', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id, csrf_token: csrfToken })
        });
        const data = await res.json();
        if (!data.success) {
          uiModal('Delete failed', data.error || 'Failed to delete key');
          return;
        }
        loadKeys();
      } catch (e) {
        uiModal('Network error', 'Error: ' + e.message);
      }
    })();

    try {
      if (typeof window.showConfirmModal === 'function') {
        window.showConfirmModal('Delete key record?', 'This permanently removes this key row from history.', doDelete, null, 'Delete');
        return;
      }
    } catch (e) {}

    if (confirm('Delete this key record permanently?')) {
      doDelete();
    }
  }

  async function reactivateKey(id, subdomain) {
    const ttlInfo = getSelectedTtlInfo();
    const ttl = ttlInfo.seconds;
    try {
      const res = await fetch('/api/tunnel/access-key/reactivate', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, ttl_seconds: ttl, csrf_token: csrfToken })
      });
      const data = await res.json();
      if (!data.success) {
        if (data.code === 'KEY_LIMIT_REACHED') {
          serverlessTtlMonths = Math.max(1, ttlInfo.months || 1);
          serverlessPaymentMode = paymentModeForMonths(serverlessTtlMonths);
          serverlessAddonType = addonTypeForMonths(serverlessTtlMonths);
          updateServerlessPriceLabel(serverlessTtlMonths);
          updateServerlessModeLabel(serverlessTtlMonths, serverlessPaymentMode);
          serverlessPaypalInit = false;
          const btn = document.getElementById('serverlessPaypalButtons');
          if (btn) btn.innerHTML = '';
          const loading = document.getElementById('serverlessPaypalLoading');
          if (loading) loading.style.display = 'block';
          showServerlessUpgradeModal();
          return;
        }
        uiModal('Reactivate failed', data.error || 'Failed to reactivate');
        return;
      }
      showGeneratedToken(subdomain, data.token);
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
  const upgradeBackdrop = document.getElementById('serverlessUpgradeBackdrop');
  upgradeBackdrop && upgradeBackdrop.addEventListener('click', hideServerlessUpgradeModal);

  // One-time PayPal return handling: PayPal redirects back with ?token=<ORDER_ID>&pp_term=1&state=...
  (function handleOneTimeReturn(){
    try {
      const qs = new URLSearchParams(window.location.search || '');
      if (qs.get('pp_term') !== '1') return;
      const orderId = qs.get('token') || '';
      const state = qs.get('state') || '';
      if (!orderId || !state) return;

      uiModal('Completing purchase…', 'Finalizing your PayPal payment.');
      fetch('/api/addon/one-time/capture', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ paypal_order_id: orderId, state, csrf_token: csrfToken })
      })
      .then(r => r.json())
      .then(result => {
        if (!result || !result.success) throw new Error(result?.error || 'Failed to complete purchase');
        // Remove params so refresh doesn't re-capture.
        const clean = window.location.pathname;
        window.history.replaceState({}, '', clean);
        loadKeys();
      })
      .catch(e => {
        uiModal('Upgrade failed', e.message);
      });
    } catch (e) {}
  })();

  loadKeys();
</script>

<?php include ROOT_PATH . '/src/Views/layout/footer.php'; ?>
