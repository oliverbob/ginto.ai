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
        <button id="copyProfileUrl">Copy</button>
      </div>
      <div id="copyProfileNotice" style="margin-top:6px;color:green;display:none">Copied to clipboard</div>
    </div>

    <div style="margin-top:18px; max-width:720px;">
      <label style="display:block;font-weight:600;margin-bottom:6px">Referral link</label>
      <div style="display:flex;gap:8px;align-items:center">
        <input id="referralUrl" type="text" readonly value="<?php echo htmlspecialchars($referralUrl); ?>" />
        <button id="copyReferralUrl">Copy</button>
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
          <button id="rotateDefaultKey" style="padding:8px 10px; border-radius:6px; cursor:pointer; border:1px solid #cbd5e1; background:#ffffff; color:#0f1724; font-size:13px;">Generate/Rotate</button>
        </div>
      </div>

      <div id="defaultKeyResult" style="display:none; margin-top:12px;">
        <div style="font-size:12px; color:#64748b; margin-bottom:6px;">Copy this key now (shown only once):</div>
        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
          <input id="defaultApiKey" type="text" readonly value="" />
          <button id="copyDefaultApiKey">Copy</button>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
input[type="text"], #defaultApiKey { flex:1; padding:8px; border-radius:6px; border:1px solid #d1d5db; background:#f8fafc; color:#0f1724; font-size:13px; }
input[readonly] { cursor: text; }
button, a { user-select:none; }
#copyProfileUrl,#copyReferralUrl,#copyDefaultApiKey,#rotateDefaultKey { padding:8px 10px; border-radius:6px; cursor:pointer; border:1px solid #cbd5e1; background:#ffffff; color:#0f1724; font-size:13px; }
.dark input[type="text"], .dark #defaultApiKey { background:#0b1220; color:#e6eef8; border-color:#334155; }
.dark #copyProfileUrl, .dark #copyReferralUrl, .dark #copyDefaultApiKey, .dark #rotateDefaultKey, .dark a { background:#111827; color:#e6eef8; border-color:#374151; }
</style>

<script>
(function(){
  function copyFrom(inputId, noticeId) {
    var input = document.getElementById(inputId);
    var notice = document.getElementById(noticeId);
    if (!input) return;
    try {
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(input.value).then(function(){
          if (notice) { notice.style.display='block'; setTimeout(function(){ notice.style.display='none'; }, 2000); }
        }, function(){
          input.select(); document.execCommand('copy');
          if (notice) { notice.style.display='block'; setTimeout(function(){ notice.style.display='none'; }, 2000); }
        });
      } else {
        input.select(); input.setSelectionRange(0, 99999); document.execCommand('copy');
        if (notice) { notice.style.display='block'; setTimeout(function(){ notice.style.display='none'; }, 2000); }
      }
    } catch (e) {}
  }

  var btn1 = document.getElementById('copyProfileUrl');
  btn1 && btn1.addEventListener('click', function(){ copyFrom('profileUrl','copyProfileNotice'); });
  var btn2 = document.getElementById('copyReferralUrl');
  btn2 && btn2.addEventListener('click', function(){ copyFrom('referralUrl','copyReferralNotice'); });
  var btn3 = document.getElementById('copyDefaultApiKey');
  btn3 && btn3.addEventListener('click', function(){ copyFrom('defaultApiKey', null); });

  async function loadDefaultStatus() {
    try {
      const res = await fetch('/api/account/default-key/status', { credentials: 'same-origin' });
      const data = await res.json();
      const el = document.getElementById('defaultKeyStatus');
      if (!data.success) { el.textContent = 'Unavailable'; return; }
      if (!data.has_key) { el.textContent = 'No key yet'; return; }
      el.textContent = 'Key exists' + (data.created_at ? (' (created ' + data.created_at + ')') : '');
    } catch (e) {
      const el = document.getElementById('defaultKeyStatus');
      el.textContent = 'Unavailable';
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
        return;
      }
      document.getElementById('defaultApiKey').value = data.token;
      document.getElementById('defaultKeyResult').style.display = 'block';
      loadDefaultStatus();
    } catch (e) {
      alert('Error: ' + e.message);
    }
  }

  var rotateBtn = document.getElementById('rotateDefaultKey');
  rotateBtn && rotateBtn.addEventListener('click', rotateDefaultKey);
  loadDefaultStatus();
})();
</script>

<?php include ROOT_PATH . '/src/Views/layout/footer.php'; ?>
