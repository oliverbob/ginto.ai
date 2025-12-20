<?php
/**
 * User public profile view
 * Expects: $user (array)
 */
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__, 3));
}

include ROOT_PATH . '/src/Views/layout/header.php';
include ROOT_PATH . '/src/Views/layout/sidebar.php';

$publicId = $user['public_id'] ?? $user['id'];
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$profileUrl = $scheme . '://' . $host . '/user/profile/' . rawurlencode($publicId);
?>
<div id="mainContent" class="p-6">
    <h1>Profile: <?php echo htmlspecialchars($user['fullname'] ?? $user['username'] ?? ''); ?></h1>
    <p>Username: <?php echo htmlspecialchars($user['username'] ?? ''); ?></p>
    <p>Member ID: <?php echo htmlspecialchars($publicId); ?></p>
    <p>Email: <?php echo htmlspecialchars($user['email'] ?? ''); ?></p>

    <div style="margin-top:12px; max-width:720px;">
        <label style="display:block;font-weight:600;margin-bottom:6px">Public Profile URL</label>
        <div style="display:flex;gap:8px;align-items:center">
            <input id="publicProfileUrl" type="text" readonly value="<?php echo htmlspecialchars($profileUrl); ?>" />
            <button id="copyProfileUrl">Copy</button>
        </div>
        <div id="copyNotice" style="margin-top:6px;color:green;display:none">Copied to clipboard</div>
    </div>

    <div style="margin-top:18px; max-width:720px;">
        <label style="display:block;font-weight:600;margin-bottom:6px">Referral Link</label>
        <div style="display:flex;gap:8px;align-items:center">
            <?php
                $referralLink = $scheme . '://' . $host . '/register?ref=' . rawurlencode($publicId);
            ?>
            <input id="referralLink" type="text" readonly value="<?php echo htmlspecialchars($referralLink); ?>" />
            <button id="copyReferralLink">Copy</button>
        </div>
        <div id="copyReferralNotice" style="margin-top:6px;color:green;display:none">Copied to clipboard</div>
    </div>
</div>

<style>
#referralLink { flex:1; padding:8px; border-radius:6px; border:1px solid #d1d5db; background:#f8fafc; color:#0f1724; font-size:13px; }
#referralLink[readonly] { cursor: text; }
#copyReferralLink { padding:8px 10px; border-radius:6px; cursor:pointer; border:1px solid #cbd5e1; background:#ffffff; color:#0f1724; font-size:13px; }
.dark #referralLink { background:#0b1220; color:#e6eef8; border-color:#334155; }
.dark #copyReferralLink { background:#111827; color:#e6eef8; border-color:#374151; }
#copyReferralLink:hover { filter:brightness(0.98); }
/* Public profile URL styling with dark-mode contrasts */
#publicProfileUrl { flex:1; padding:8px; border-radius:6px; border:1px solid #d1d5db; background:#f8fafc; color:#0f1724; font-size:13px; }
#publicProfileUrl[readonly] { cursor: text; }
#copyProfileUrl { padding:8px 10px; border-radius:6px; cursor:pointer; border:1px solid #cbd5e1; background:#ffffff; color:#0f1724; font-size:13px; }
#publicProfileUrl:focus { outline: none; box-shadow: 0 0 0 3px rgba(99,102,241,0.12); }
.dark #publicProfileUrl { background:#0b1220; color:#e6eef8; border-color:#334155; }
.dark #copyProfileUrl { background:#111827; color:#e6eef8; border-color:#374151; }
#copyProfileUrl:hover { filter:brightness(0.98); }
</style>

<script>
(function(){
    var btn = document.getElementById('copyReferralLink');
    var input = document.getElementById('referralLink');
    var notice = document.getElementById('copyReferralNotice');
    if (btn && input) {
        btn.addEventListener('click', function(){
            try {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(input.value).then(function(){
                        if (notice) { notice.style.display = 'block'; setTimeout(function(){ notice.style.display = 'none'; }, 2000); }
                    }, function(){
                        input.select(); document.execCommand('copy'); if (notice) { notice.style.display = 'block'; setTimeout(function(){ notice.style.display = 'none'; }, 2000); }
                    });
                } else {
                    input.select(); input.setSelectionRange(0, 99999); document.execCommand('copy'); if (notice) { notice.style.display = 'block'; setTimeout(function(){ notice.style.display = 'none'; }, 2000); }
                }
            } catch (e) {
                alert('Copy failed — select and copy the URL manually.');
            }
        });
    }
})();
(function(){
    var btn = document.getElementById('copyProfileUrl');
    var input = document.getElementById('publicProfileUrl');
    var notice = document.getElementById('copyNotice');
    if (btn && input) {
        btn.addEventListener('click', function(){
            // Prefer modern Clipboard API
            try {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(input.value).then(function(){
                        if (notice) { notice.style.display = 'block'; setTimeout(function(){ notice.style.display = 'none'; }, 2000); }
                    }, function(){
                        input.select(); document.execCommand('copy'); if (notice) { notice.style.display = 'block'; setTimeout(function(){ notice.style.display = 'none'; }, 2000); }
                    });
                } else {
                    input.select(); input.setSelectionRange(0, 99999); document.execCommand('copy'); if (notice) { notice.style.display = 'block'; setTimeout(function(){ notice.style.display = 'none'; }, 2000); }
                }
            } catch (e) {
                alert('Copy failed — select and copy the URL manually.');
            }
        });
    }
})();
</script>

<?php include ROOT_PATH . '/src/Views/layout/footer.php';
