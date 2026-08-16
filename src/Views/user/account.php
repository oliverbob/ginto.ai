<?php
/**
 * Account summary
 * Expects: $user (array)
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

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$publicId = $user['public_id'] ?? $user['id'] ?? '';
$profileUrl = $scheme . '://' . $host . '/user/profile/' . rawurlencode((string)$publicId);
$referralUrl = $scheme . '://' . $host . '/register?ref=' . rawurlencode((string)$publicId);
$csrf = $_SESSION['csrf_token'] ?? '';
?>

<div id="mainContent" class="p-6">
  <h1 class="text-2xl font-bold mb-2">Account</h1>
  <p class="text-gray-500 mb-6">Profile links and API keys</p>

  <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4" style="max-width: 860px;">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <div class="text-xs text-gray-500 mb-1">Username</div>
        <div class="font-medium"><?php echo htmlspecialchars($user['username'] ?? ''); ?></div>
      </div>
      <div>
        <div class="text-xs text-gray-500 mb-1">Email</div>
        <div class="font-medium"><?php echo htmlspecialchars($user['email'] ?? ''); ?></div>
      </div>
    </div>

    <div style="margin-top:18px; max-width:720px;">
      <label style="display:block;font-weight:600;margin-bottom:6px">Profile link</label>
      <div style="display:flex;gap:8px;align-items:center">
        <input id="profileUrl" type="text" readonly value="<?php echo htmlspecialchars($profileUrl); ?>" />
        <button id="copyProfileUrl" type="button">Copy</button>
      </div>
      <div id="copyProfileNotice" style="margin-top:6px;color:green;display:none">Copied to clipboard</div>
    </div>

    <div style="margin-top:18px; max-width:720px;">
      <label style="display:block;font-weight:600;margin-bottom:6px">Referral link</label>
      <div style="display:flex;gap:8px;align-items:center">
        <input id="referralUrl" type="text" readonly value="<?php echo htmlspecialchars($referralUrl); ?>" />
        <button id="copyReferralUrl" type="button">Copy</button>
      </div>
      <div id="copyReferralNotice" style="margin-top:6px;color:green;display:none">Copied to clipboard</div>
    </div>

    <div style="margin-top:22px; padding-top:16px; border-top:1px solid rgba(148,163,184,0.35); max-width:720px;">
      <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap;">
        <div>
          <div style="font-weight:600;">Default API key</div>
          <div style="font-size:12px; color:#64748b;">Use as <code>Authorization: Bearer &lt;token&gt;</code></div>
          <div id="defaultKeyStatus" style="font-size:12px; color:#64748b; margin-top:6px;">Loading…</div>
        </div>
        <div style="display:flex; gap:8px; align-items:center;">
          <a href="/account/keys" style="padding:8px 10px; border-radius:6px; border:1px solid #cbd5e1; background:#ffffff; color:#0f1724; font-size:13px; text-decoration:none;">Tunnel Keys</a>
          <button id="rotateDefaultKey" type="button" style="padding:8px 10px; border-radius:6px; cursor:pointer; border:1px solid #cbd5e1; background:#ffffff; color:#0f1724; font-size:13px;">Generate/Rotate</button>
        </div>
      </div>

      <div id="defaultKeyResult" style="display:none; margin-top:12px;">
        <div style="font-size:12px; color:#64748b; margin-bottom:6px;">Default key (copy):</div>
        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
          <div style="flex:1; min-width:260px; position:relative;">
            <input id="defaultApiKey" type="text" readonly value="" style="width:100%; padding-right:44px;" />
            <button id="toggleDefaultApiKey" type="button" title="Show/Hide" aria-label="Show/Hide" style="position:absolute; right:10px; top:50%; transform:translateY(-50%); padding:6px; border-radius:8px; border:0; background:transparent; cursor:pointer; color:#64748b;">
              <i id="defaultApiKeyEye" class="fas fa-eye"></i>
            </button>
          </div>
          <button id="copyDefaultApiKey" type="button">Copy</button>
        </div>
      </div>
    </div>

    <div style="margin-top:22px; padding-top:16px; border-top:1px solid rgba(148,163,184,0.35); max-width:720px;">
      <div style="font-weight:600; margin-bottom:8px;">API keys</div>
      <div style="font-size:12px; color:#64748b; margin-bottom:10px;">Create multiple API keys for different devices or clients.</div>

      <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-bottom:12px;">
        <input id="newApiKeyName" type="text" placeholder="Key name (e.g. laptop, server)" style="min-width:240px;" />
        <button id="createApiKey" type="button">Create key</button>
      </div>

      <div id="newApiKeyResult" style="display:none; margin-bottom:12px;">
        <div style="font-size:12px; color:#64748b; margin-bottom:6px;">New key (copy now):</div>
        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
          <div style="flex:1; min-width:260px; position:relative;">
            <input id="newApiKeyValue" type="text" readonly value="" style="width:100%; padding-right:44px;" />
            <button id="toggleNewApiKey" type="button" title="Show/Hide" aria-label="Show/Hide" style="position:absolute; right:10px; top:50%; transform:translateY(-50%); padding:6px; border-radius:8px; border:0; background:transparent; cursor:pointer; color:#64748b;">
              <i id="newApiKeyEye" class="fas fa-eye"></i>
            </button>
          </div>
          <button id="copyNewApiKey" type="button">Copy</button>
        </div>
      </div>

      <div id="apiKeysListStatus" style="font-size:12px; color:#64748b; margin-bottom:8px;">Loading keys…</div>
      <div style="overflow:auto; border:1px solid #e2e8f0; border-radius:8px;">
        <table style="width:100%; border-collapse:collapse; font-size:13px;">
          <thead>
            <tr style="background:#f8fafc; text-align:left;">
              <th style="padding:8px; border-bottom:1px solid #e2e8f0;">Name</th>
              <th style="padding:8px; border-bottom:1px solid #e2e8f0;">Created</th>
              <th style="padding:8px; border-bottom:1px solid #e2e8f0;">Last used</th>
              <th style="padding:8px; border-bottom:1px solid #e2e8f0;">Status</th>
              <th style="padding:8px; border-bottom:1px solid #e2e8f0;">Action</th>
            </tr>
          </thead>
          <tbody id="apiKeysListBody"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- ── Two-factor authentication ───────────────────────────────────── -->
<div id="mainContent-2fa" class="px-6 pb-2" style="max-width:900px;">
  <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 mt-6" style="max-width:860px;">
    <h2 class="text-lg font-semibold">Two-factor authentication</h2>
    <p id="totpStatus" class="text-sm text-gray-500 mt-1">Loading security status…</p>
    <div id="totpMessage" class="hidden text-sm rounded p-2 mt-3"></div>

    <div id="totpOff" class="mt-4">
      <p class="text-sm text-gray-600 dark:text-gray-300 mb-3">Protect sign-ins and Chat with a 6-digit code from Google Authenticator.</p>
      <button id="totpBegin" type="button" class="bg-amber-500 hover:bg-amber-600 text-white font-semibold px-4 py-2 rounded-lg text-sm">Set up Google Authenticator</button>
    </div>
    <div id="totpSetup" class="hidden mt-4 p-4 rounded-lg border border-amber-300 bg-amber-50 dark:bg-gray-900">
      <p class="text-sm font-medium">In Google Authenticator, choose <em>Add a code</em> → <em>Enter a setup key</em>.</p>
      <p class="text-sm mt-2">Account: <strong id="totpAccount"></strong><br>Key: <code id="totpSecret" class="select-all break-all"></code><br>Type: Time based</p>
      <form id="totpConfirmForm" class="mt-4 flex gap-2 flex-wrap">
        <input id="totpConfirmCode" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required placeholder="6-digit code" class="border rounded px-3 py-2 text-sm">
        <button type="submit" class="bg-amber-500 hover:bg-amber-600 text-white font-semibold px-4 py-2 rounded-lg text-sm">Enable 2FA</button>
      </form>
    </div>
    <div id="totpOn" class="hidden mt-4">
      <p class="text-sm text-green-700 dark:text-green-300 mb-3">Two-factor authentication is enabled.</p>
      <form id="totpDisableForm" class="flex gap-2 flex-wrap items-end">
        <label class="text-sm">Current password<input name="password" type="password" required class="block border rounded px-3 py-2 mt-1"></label>
        <label class="text-sm">Authenticator code<input name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required class="block border rounded px-3 py-2 mt-1"></label>
        <button type="submit" class="border border-red-400 text-red-700 hover:bg-red-50 font-semibold px-4 py-2 rounded-lg text-sm">Disable 2FA</button>
      </form>
    </div>
  </div>
</div>

<!-- ── Change Password ──────────────────────────────────────────────── -->
<div id="mainContent-pw" class="px-6 pb-8" style="max-width:900px;">
  <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 mt-6" style="max-width:860px;">
    <h2 class="text-lg font-semibold mb-4">Change Password</h2>

    <div id="pwMsg" class="mb-3 hidden text-sm rounded p-2"></div>

    <form id="changePwForm" class="space-y-4" style="max-width:420px;" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

      <div>
        <label class="block text-xs text-gray-500 mb-1">Current Password</label>
        <input type="password" name="current_password" id="current_password" required
               placeholder="Current password"
               class="w-full border rounded-lg p-3 focus:ring-2 focus:ring-amber-500
                      bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100
                      border-gray-300 dark:border-gray-600 text-sm">
      </div>
      <div>
        <label class="block text-xs text-gray-500 mb-1">New Password</label>
        <input type="password" name="new_password" id="new_password" required minlength="8"
               placeholder="At least 8 characters"
               class="w-full border rounded-lg p-3 focus:ring-2 focus:ring-amber-500
                      bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100
                      border-gray-300 dark:border-gray-600 text-sm">
      </div>
      <div>
        <label class="block text-xs text-gray-500 mb-1">Confirm New Password</label>
        <input type="password" name="new_password_confirm" id="new_password_confirm" required
               placeholder="Repeat new password"
               class="w-full border rounded-lg p-3 focus:ring-2 focus:ring-amber-500
                      bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100
                      border-gray-300 dark:border-gray-600 text-sm">
      </div>
      <button type="submit" id="changePwBtn"
              class="bg-amber-500 hover:bg-amber-600 text-white font-semibold px-6 py-2 rounded-lg text-sm transition-colors">
        Update Password
      </button>
    </form>
  </div>
</div>

<script>
(function () {
  var csrf = <?= json_encode($csrf) ?>;
  var status = document.getElementById('totpStatus'), off = document.getElementById('totpOff'), on = document.getElementById('totpOn'), setup = document.getElementById('totpSetup'), message = document.getElementById('totpMessage');
  function note(text, ok) { message.textContent = text; message.className = 'text-sm rounded p-2 mt-3 ' + (ok ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'); }
  async function request(url, data) { var body = new URLSearchParams(data || {}); body.set('csrf_token', csrf); var r = await fetch(url, {method: data ? 'POST' : 'GET', credentials: 'same-origin', headers: data ? {'Content-Type':'application/x-www-form-urlencoded'} : {}}); return r.json(); }
  async function load() { try { var d = await request('/api/account/2fa/status'); if (!d.success) throw Error(d.error); status.textContent = d.enabled ? 'Enabled — a code is required after your password.' : 'Not enabled.'; off.classList.toggle('hidden', d.enabled); on.classList.toggle('hidden', !d.enabled); setup.classList.add('hidden'); } catch(e) { status.textContent = 'Unable to load two-factor status.'; } }
  document.getElementById('totpBegin').addEventListener('click', async function () { try { var d = await request('/api/account/2fa/begin', {}); if (!d.success) throw Error(d.error); document.getElementById('totpSecret').textContent = d.secret; document.getElementById('totpAccount').textContent = 'Ginto AI'; setup.classList.remove('hidden'); note('Enter the setup key in Google Authenticator, then confirm a code below.', true); } catch(e) { note(e.message || 'Unable to start setup.', false); } });
  document.getElementById('totpConfirmForm').addEventListener('submit', async function(e) { e.preventDefault(); try { var d = await request('/api/account/2fa/confirm', {code: document.getElementById('totpConfirmCode').value}); if (!d.success) throw Error(d.error); note('Two-factor authentication is enabled.', true); load(); } catch(err) { note(err.message || 'Unable to enable two-factor authentication.', false); } });
  document.getElementById('totpDisableForm').addEventListener('submit', async function(e) { e.preventDefault(); if (!confirm('Disable two-factor authentication?')) return; var f = e.currentTarget; try { var d = await request('/api/account/2fa/disable', {password: f.password.value, code: f.code.value}); if (!d.success) throw Error(d.error); f.reset(); note('Two-factor authentication is disabled.', true); load(); } catch(err) { note(err.message || 'Unable to disable two-factor authentication.', false); } });
  load();
})();
</script>

<script>
(function () {
  var form = document.getElementById('changePwForm');
  var msg  = document.getElementById('pwMsg');
  if (!form) return;

  form.addEventListener('submit', async function (e) {
    e.preventDefault();
    msg.className = 'mb-3 hidden text-sm rounded p-2';

    var current  = form.querySelector('[name="current_password"]').value;
    var np       = form.querySelector('[name="new_password"]').value;
    var nc       = form.querySelector('[name="new_password_confirm"]').value;
    var csrf     = form.querySelector('[name="csrf_token"]').value;

    if (np.length < 8) {
      showMsg('New password must be at least 8 characters.', false);
      return;
    }
    if (np !== nc) {
      showMsg('Passwords do not match.', false);
      return;
    }

    var btn = document.getElementById('changePwBtn');
    btn.disabled = true;
    btn.textContent = 'Saving…';

    try {
      var res = await fetch('/api/account/change-password', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf_token: csrf, current_password: current, new_password: np })
      });
      var data = await res.json();
      if (data && data.success) {
        showMsg('Password updated successfully!', true);
        form.reset();
      } else {
        showMsg((data && data.error) ? data.error : 'Failed to update password.', false);
      }
    } catch (err) {
      showMsg('Error: ' + err.message, false);
    }

    btn.disabled = false;
    btn.textContent = 'Update Password';
  });

  function showMsg(text, ok) {
    msg.textContent = text;
    msg.className = 'mb-3 text-sm rounded p-2 ' + (ok
      ? 'bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300 border border-green-300 dark:border-green-700'
      : 'bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300 border border-red-300 dark:border-red-700');
  }
})();
</script>

<style>
input[type="text"], #defaultApiKey { flex:1; padding:8px; border-radius:6px; border:1px solid #d1d5db; background:#f8fafc; color:#0f1724; font-size:13px; }
input[readonly] { cursor: text; }
button, a { user-select:none; }
#copyProfileUrl,#copyReferralUrl,#copyDefaultApiKey,#rotateDefaultKey { padding:8px 10px; border-radius:6px; cursor:pointer; border:1px solid #cbd5e1; background:#ffffff; color:#0f1724; font-size:13px; }
#createApiKey,#copyNewApiKey { padding:8px 10px; border-radius:6px; cursor:pointer; border:1px solid #cbd5e1; background:#ffffff; color:#0f1724; font-size:13px; }
.dark input[type="text"], .dark #defaultApiKey { background:#0b1220; color:#e6eef8; border-color:#334155; }
.dark #copyProfileUrl, .dark #copyReferralUrl, .dark #copyDefaultApiKey, .dark #rotateDefaultKey, .dark #createApiKey, .dark #copyNewApiKey, .dark a { background:#111827; color:#e6eef8; border-color:#374151; }
</style>

<script>
(function(){
  function maskKey(key) {
    var s = String(key || '');
    if (!s) return '';
    var keep = Math.min(12, s.length);
    return s.slice(0, keep) + '********';
  }

  function setMaskedValue(input, full, revealed) {
    if (!input) return;
    input.dataset.full = String(full || '');
    input.dataset.revealed = revealed ? '1' : '0';
    input.value = revealed ? String(full || '') : maskKey(full);
  }

  function setTempButtonState(btn, text, ms) {
    if (!btn) return;
    var prev = btn.textContent;
    btn.textContent = text;
    setTimeout(function(){ btn.textContent = prev; }, ms || 1200);
  }

  async function copyTextToClipboard(text) {
    if (!text) return false;
    try {
      if (navigator.clipboard && navigator.clipboard.writeText && window.isSecureContext) {
        await navigator.clipboard.writeText(text);
        return true;
      }
    } catch (e) {
      // fall through to legacy method
    }

    try {
      var ta = document.createElement('textarea');
      ta.value = text;
      ta.setAttribute('readonly', '');
      ta.style.position = 'fixed';
      ta.style.opacity = '0';
      ta.style.left = '-9999px';
      document.body.appendChild(ta);
      ta.focus();
      ta.select();
      var ok = document.execCommand('copy');
      document.body.removeChild(ta);
      return !!ok;
    } catch (e) {
      return false;
    }
  }

  function copyFrom(inputId, noticeId, buttonId) {
    var input = document.getElementById(inputId);
    var notice = noticeId ? document.getElementById(noticeId) : null;
    var btn = buttonId ? document.getElementById(buttonId) : null;
    if (!input) return;

    var text = (input.dataset && input.dataset.full) ? input.dataset.full : input.value;
    copyTextToClipboard(text).then(function(ok){
      if (ok) {
        if (notice) { notice.style.display='block'; setTimeout(function(){ notice.style.display='none'; }, 2000); }
        setTempButtonState(btn, 'Copied', 1200);
      } else {
        setTempButtonState(btn, 'Copy failed', 1400);
      }
    });
  }

  var btn1 = document.getElementById('copyProfileUrl');
  btn1 && btn1.addEventListener('click', function(){ copyFrom('profileUrl','copyProfileNotice','copyProfileUrl'); });
  var btn2 = document.getElementById('copyReferralUrl');
  btn2 && btn2.addEventListener('click', function(){ copyFrom('referralUrl','copyReferralNotice','copyReferralUrl'); });
  var btn3 = document.getElementById('copyDefaultApiKey');
  btn3 && btn3.addEventListener('click', function(){ copyFrom('defaultApiKey', null, 'copyDefaultApiKey'); });
  var btn4 = document.getElementById('copyNewApiKey');
  btn4 && btn4.addEventListener('click', function(){ copyFrom('newApiKeyValue', null, 'copyNewApiKey'); });

  async function loadDefaultStatus() {
    try {
      const res = await fetch('/api/account/default-key/status', { credentials: 'same-origin' });
      const data = await res.json();
      const el = document.getElementById('defaultKeyStatus');
      if (!data.success) { el.textContent = 'Unavailable'; return; }
      if (!data.has_key) { el.textContent = 'No key yet'; return; }
      if (data.token) {
        setMaskedValue(document.getElementById('defaultApiKey'), data.token, false);
        document.getElementById('defaultKeyResult').style.display = 'block';
        el.textContent = 'Key ready';
        return;
      }
      el.textContent = 'Key exists (rotate to display)' + (data.created_at ? (' (created ' + data.created_at + ')') : '');
    } catch (e) {
      const el = document.getElementById('defaultKeyStatus');
      el.textContent = 'Unavailable';
    }
  }

  async function ensureDefaultKeyOnce() {
    // Behave like typical API providers: create a default key automatically
    // the first time the user visits /account.
    try {
      // Bump flag version so we retry for users who hit earlier failures.
      const flag = 'ginto_default_api_key_autocreated_v3';
      if (window.localStorage && localStorage.getItem(flag) === '1') {
        return;
      }

      const res = await fetch('/api/account/default-key/status', { credentials: 'same-origin' });
      const data = await res.json();
      if (!data || !data.success) {
        return;
      }
      // If there's no key, create one. If there's a legacy key but it's not
      // displayable (no encrypted payload), rotate once to produce a showable key.
      if (!data.has_key || (data.has_key && !data.token)) {
        const ok = await rotateDefaultKey();
        if (ok && window.localStorage) localStorage.setItem(flag, '1');
        return;
      }

      if (data.has_key && data.token) {
        if (window.localStorage) localStorage.setItem(flag, '1');
      }
    } catch (e) {
      // Don't block page load; user can still click Generate/Rotate manually.
    }
  }

  async function rotateDefaultKey() {
    try {
      const res = await fetch('/api/account/default-key/rotate', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf_token: <?php echo json_encode($csrf); ?> })
      });
      const data = await res.json();
      if (!data.success) {
        alert(data.error || 'Failed');
        return false;
      }
      setMaskedValue(document.getElementById('defaultApiKey'), data.token, false);
      document.getElementById('defaultKeyResult').style.display = 'block';
      loadDefaultStatus();
      return true;
    } catch (e) {
      alert('Error: ' + e.message);
      return false;
    }
  }

  function renderApiKeys(keys) {
    var body = document.getElementById('apiKeysListBody');
    var status = document.getElementById('apiKeysListStatus');
    if (!body || !status) return;

    if (!Array.isArray(keys) || keys.length === 0) {
      body.innerHTML = '<tr><td colspan="5" style="padding:10px; color:#64748b;">No keys yet.</td></tr>';
      status.textContent = 'No API keys.';
      return;
    }

    var rows = keys.map(function(k){
      var id = Number(k.id || 0);
      var name = String(k.name || '');
      var created = String(k.created_at || '');
      var used = String(k.last_used_at || 'Never');
      var revoked = Number(k.revoked || 0) === 1;
      var statusText = revoked ? 'revoked' : 'active';
      var action = revoked ? '<span style="color:#94a3b8;">—</span>' : '<button type="button" data-revoke-id="'+ id +'">Revoke</button>';
      return '<tr>'
        + '<td style="padding:8px; border-bottom:1px solid #e2e8f0;">' + name.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</td>'
        + '<td style="padding:8px; border-bottom:1px solid #e2e8f0;">' + created + '</td>'
        + '<td style="padding:8px; border-bottom:1px solid #e2e8f0;">' + used + '</td>'
        + '<td style="padding:8px; border-bottom:1px solid #e2e8f0;">' + statusText + '</td>'
        + '<td style="padding:8px; border-bottom:1px solid #e2e8f0;">' + action + '</td>'
        + '</tr>';
    }).join('');

    body.innerHTML = rows;
    status.textContent = keys.length + ' key(s)';

    body.querySelectorAll('button[data-revoke-id]').forEach(function(btn){
      btn.addEventListener('click', function(){
        var id = Number(btn.getAttribute('data-revoke-id') || '0');
        if (!id) return;
        revokeApiKey(id);
      });
    });
  }

  async function loadApiKeys() {
    var status = document.getElementById('apiKeysListStatus');
    if (status) status.textContent = 'Loading keys…';
    try {
      const res = await fetch('/api/account/keys/list', { credentials: 'same-origin' });
      const data = await res.json();
      if (!data || !data.success) {
        if (status) status.textContent = 'Failed to load keys';
        return;
      }
      renderApiKeys(data.keys || []);
    } catch (e) {
      if (status) status.textContent = 'Failed to load keys';
    }
  }

  async function createApiKey() {
    var nameInput = document.getElementById('newApiKeyName');
    var name = nameInput ? String(nameInput.value || '').trim() : '';
    try {
      const res = await fetch('/api/account/keys/create', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf_token: <?php echo json_encode($csrf); ?>, name: name })
      });
      const data = await res.json();
      if (!data || !data.success) {
        alert((data && data.error) ? data.error : 'Failed to create key');
        return;
      }

      setMaskedValue(document.getElementById('newApiKeyValue'), data.token, false);
      document.getElementById('newApiKeyResult').style.display = 'block';
      if (nameInput) nameInput.value = '';
      await loadApiKeys();
    } catch (e) {
      alert('Error: ' + e.message);
    }
  }

  async function revokeApiKey(id) {
    try {
      const res = await fetch('/api/account/keys/revoke', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf_token: <?php echo json_encode($csrf); ?>, id: id })
      });
      const data = await res.json();
      if (!data || !data.success) {
        alert((data && data.error) ? data.error : 'Failed to revoke key');
        return;
      }
      await loadApiKeys();
    } catch (e) {
      alert('Error: ' + e.message);
    }
  }

  var eyeBtn = document.getElementById('toggleDefaultApiKey');
  eyeBtn && eyeBtn.addEventListener('click', function(){
    var input = document.getElementById('defaultApiKey');
    if (!input) return;
    var full = (input.dataset && input.dataset.full) ? input.dataset.full : input.value;
    var revealed = (input.dataset && input.dataset.revealed === '1');
    setMaskedValue(input, full, !revealed);
    var icon = document.getElementById('defaultApiKeyEye');
    if (icon) icon.className = (!revealed) ? 'fas fa-eye-slash' : 'fas fa-eye';
  });

  var rotateBtn = document.getElementById('rotateDefaultKey');
  rotateBtn && rotateBtn.addEventListener('click', rotateDefaultKey);

  var createBtn = document.getElementById('createApiKey');
  createBtn && createBtn.addEventListener('click', createApiKey);

  var newEyeBtn = document.getElementById('toggleNewApiKey');
  newEyeBtn && newEyeBtn.addEventListener('click', function(){
    var input = document.getElementById('newApiKeyValue');
    if (!input) return;
    var full = (input.dataset && input.dataset.full) ? input.dataset.full : input.value;
    var revealed = (input.dataset && input.dataset.revealed === '1');
    setMaskedValue(input, full, !revealed);
    var icon = document.getElementById('newApiKeyEye');
    if (icon) icon.className = (!revealed) ? 'fas fa-eye-slash' : 'fas fa-eye';
  });

  loadDefaultStatus();
  ensureDefaultKeyOnce();
  loadApiKeys();
})();
</script>

<?php include ROOT_PATH . '/src/Views/layout/footer.php'; ?>
