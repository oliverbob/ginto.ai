<style>
.sq-login-widget{--bg-primary:rgba(5,10,16,.88);--card-bg:rgba(5,12,20,.94);--text-primary:#F4F8FA;--text-secondary:#A5B7C1;--border-color:rgba(179,222,237,.2);position:relative;width:min(100%,30rem);margin:0 auto}
.sq-login-card{width:100%;padding:1.25rem;border:1px solid rgba(0,229,255,.2);border-radius:1.1rem;background:linear-gradient(145deg,rgba(7,14,23,.97),rgba(7,14,23,.9));box-shadow:0 22px 75px rgba(0,0,0,.44);backdrop-filter:blur(18px);transition:opacity .2s,filter .2s}
.sq-login-widget:not(.is-ready) .sq-login-card{opacity:.16;filter:blur(2px);pointer-events:none}
.sq-login-preloader{position:absolute;z-index:2;inset:0;display:flex;align-items:center;justify-content:center;gap:.7rem;color:#C8D5DC;font-size:.9rem}
.sq-login-widget.is-ready .sq-login-preloader{display:none}
.sq-login-spinner{width:19px;height:19px;border:2px solid rgba(0,229,255,.2);border-top-color:#00E5FF;border-radius:50%;animation:sq-spin .7s linear infinite}
@keyframes sq-spin{to{transform:rotate(360deg)}}
.sq-login-card h3{margin:0 0 .3rem;color:#F4F8FA;font:750 1.22rem/1.2 var(--display);letter-spacing:-.03em;text-align:center}
.sq-login-intro{margin:0 0 1rem;text-align:center;color:#A5B7C1;font-size:.88rem}
.sq-tabs{position:relative;display:flex;overflow:hidden;border:1px solid rgba(179,222,237,.18);border-radius:.7rem;margin-bottom:.4rem;background:rgba(255,255,255,.025)}
.sq-tab{position:relative;flex:1;min-height:46px;padding:.65rem .4rem;border:0;background:transparent;color:#A5B7C1;font:700 .88rem var(--body);cursor:pointer;transition:background .2s,color .2s}
.sq-tab:not(:last-child){border-right:1px solid rgba(179,222,237,.14)}.sq-tab:hover{background:rgba(255,255,255,.06)}
.sq-tab.active-sqs{background:rgba(0,229,255,.1);color:#00E5FF}.sq-tab.active-ginto{background:rgba(255,200,61,.1);color:#FFC83D}
.sq-tab-indicator{position:relative;height:3px;width:40%;margin-top:-3px;border-radius:2px;transition:margin-left .22s,background .22s}
.sq-tab-indicator.sqs{margin-left:5%;background:linear-gradient(90deg,#00AFC5,#00E5FF)}.sq-tab-indicator.ginto{margin-left:55%;background:linear-gradient(90deg,#bfa14a,#d4a843)}
.sq-panel{display:none;padding-top:.9rem}.sq-panel.active{display:block}
.sq-logo-area{text-align:center;margin:0 0 1rem}.sq-logo-area img,.sq-logo-crown{display:grid;width:48px;height:48px;margin:0 auto .45rem;border-radius:50%;object-fit:cover}
.sq-logo-area.ginto img{box-shadow:0 0 14px rgba(255,200,61,.28)}.sq-logo-crown{place-items:center;background:rgba(0,229,255,.09);box-shadow:0 0 14px rgba(0,229,255,.25)}.sq-logo-crown i{color:#00E5FF;font-size:1.35rem}
.sq-logo-area h2{max-width:none;margin:0;color:#F4F8FA;font:750 1.05rem/1.2 var(--display);letter-spacing:-.02em}.sq-logo-area p{margin:.15rem 0 0;color:#8195A0;font-size:.75rem}
.sq-field{margin-bottom:.85rem}.sq-field label{display:block;margin-bottom:.32rem;color:#B8C7CE;font-size:.8rem;font-weight:600}
.sq-input{box-sizing:border-box;width:100%;padding:.72rem .78rem;border:1px solid rgba(179,222,237,.2);border-radius:.55rem;background:rgba(3,8,14,.78);color:#F4F8FA;font:inherit;font-size:.92rem;transition:border-color .18s,box-shadow .18s}
.sq-input::placeholder{color:#8195A0;opacity:.72}.sq-input:focus{outline:none;border-color:#00E5FF;box-shadow:0 0 0 3px rgba(0,229,255,.12)}.sq-input.ginto-focus:focus{border-color:#FFC83D;box-shadow:0 0 0 3px rgba(255,200,61,.13)}
.sq-pw-wrap{position:relative}.sq-pw-toggle{position:absolute;top:2rem;right:.6rem;padding:.25rem;border:0;background:none;color:#9ca3af;cursor:pointer}.sq-pw-toggle:hover{color:#00E5FF}
.sq-btn{width:100%;margin-top:.25rem;padding:.75rem;border:0;border-radius:.55rem;color:#061018;font-size:.92rem;font-weight:800;cursor:pointer;transition:filter .2s,transform .15s}.sq-btn:hover{filter:brightness(1.08)}.sq-btn:active{transform:scale(.985)}.sq-btn-sqs{background:linear-gradient(135deg,#00CDE3,#00E5FF)}.sq-btn-ginto{background:linear-gradient(135deg,#FFC83D,#E4A91B)}
.sq-footer-links{display:flex;justify-content:center;flex-wrap:wrap;gap:.45rem;margin-top:.85rem;color:#8195A0;font-size:.76rem}.sq-footer-links span{opacity:.4}.sq-link{font-weight:650;text-decoration:none}.sq-link:hover{text-decoration:underline}.sq-link-sqs{color:#00E5FF}.sq-link-ginto{color:#FFC83D}
.sq-login-error{margin:.7rem 0;padding:.65rem .75rem;border:1px solid rgba(255,46,147,.4);border-radius:.55rem;background:rgba(255,46,147,.08);color:#ff93c3;font-size:.85rem}
@media(max-width:760px){.sq-login-widget{width:100%}.sq-login-card{padding:1rem}.sq-tab{font-size:.82rem}}
@media(prefers-reduced-motion:reduce){.sq-login-spinner{animation:none}}
</style>
<noscript><style>.sq-login-widget .sq-login-preloader{display:none!important}.sq-login-widget .sq-login-card{opacity:1!important;filter:none!important;pointer-events:auto!important}</style></noscript>
<?php
$gintoLoginActive = !empty($gintoLoginActive) || !empty($loginError);
$loginOld = isset($loginOld) && is_array($loginOld) ? $loginOld : [];
?>
<div class="sq-login-widget" id="sq-login-widget">
  <div class="sq-login-preloader" role="status"><span class="sq-login-spinner" aria-hidden="true"></span><span>Preparing secure sign-in…</span></div>
  <div class="sq-login-card">
    <h3>Choose your sign-in</h3>
    <p class="sq-login-intro">SilverQueen is selected. Ginto is available on the second tab.</p>
    <?php if (!empty($loginError)): ?><div class="sq-login-error" role="alert"><?= htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <div class="sq-tabs" role="tablist" aria-label="Choose a sign-in platform">
      <button type="button" role="tab" aria-selected="<?= $gintoLoginActive ? 'false' : 'true' ?>" class="sq-tab <?= $gintoLoginActive ? '' : 'active-sqs' ?>" id="tab-sqs" onclick="switchTab('sqs')"><svg aria-hidden="true" viewBox="0 0 24 24" style="width:15px;height:15px;vertical-align:-2px;margin-right:.35rem;fill:currentColor"><path d="M12 2 2 9l10 13L22 9 12 2Zm0 3.1L16.1 8H7.9L12 5.1ZM5.2 10h3.5l2.1 7.1L5.2 10Zm7.9 7.1 2.1-7.1h3.5l-5.6 7.1ZM18.3 8h-2.9l-1.8-2.1L18.3 8ZM10.4 5.9 8.6 8H5.7l4.7-2.1Z"/></svg>SilverQueen</button>
      <button type="button" role="tab" aria-selected="<?= $gintoLoginActive ? 'true' : 'false' ?>" class="sq-tab <?= $gintoLoginActive ? 'active-ginto' : '' ?>" id="tab-ginto" onclick="switchTab('ginto')"><svg aria-hidden="true" viewBox="0 0 24 24" style="width:15px;height:15px;vertical-align:-2px;margin-right:.35rem;fill:currentColor"><path d="M12 2a2 2 0 0 0-1 3.73V7H7a3 3 0 0 0-3 3v7a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3v-7a3 3 0 0 0-3-3h-4V5.73A2 2 0 0 0 12 2ZM7 9h10a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1v-7a1 1 0 0 1 1-1Zm1.5 3a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3Zm7 0a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3ZM9 17h6v1H9z"/></svg>Ginto</button>
    </div>
    <div class="sq-tab-indicator <?= $gintoLoginActive ? 'ginto' : 'sqs' ?>" id="tab-indicator"></div>
    <div class="sq-panels">
        <!-- SilverQueen login panel -->
        <div class="sq-panel <?= $gintoLoginActive ? '' : 'active' ?>" id="panel-sqs">
            <div class="sq-logo-area sqs">
                <div class="sq-logo-crown"><svg aria-hidden="true" viewBox="0 0 24 24" style="width:25px;height:25px;fill:currentColor"><path d="M3 18h18l-1.5-9-4.2 3.5L12 5l-3.3 7.5L4.5 9 3 18Zm1.1 2h15.8v1H4.1z"/></svg></div>
                <h2>SilverQueen</h2>
                <p>sq.silverqueen.pro</p>
            </div>
            <form id="sq-form" onsubmit="return sqLogin(event)">
                <div class="sq-field">
                    <label>Email, Username, or Phone</label>
                    <input type="text" name="identifier" id="sq-identifier" required placeholder="Enter your email, username, or phone" class="sq-input sqs-focus">
                </div>
                <div class="sq-field sq-pw-wrap">
                    <label>Password</label>
                    <input type="password" name="password" id="pw-sqs" required placeholder="Password" class="sq-input sqs-focus" style="padding-right:2.75rem;">
                    <button type="button" onclick="togglePw('pw-sqs',this)" class="sq-pw-toggle" aria-label="Toggle password">
                        <svg class="pw-open" style="display:none;width:18px;height:18px;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        <svg class="pw-closed" style="width:18px;height:18px;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7 1.274-4.057 5.065-7 9.542-7 1.05 0 2.05.15 3 .425M12 5c4.477 0 8.268 2.943 9.542 7a10.04 10.04 0 01-1.5 3.5M16.5 13.5a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z"/></svg>
                    </button>
                </div>
                <div id="sq-error" style="display:none;color:#ef4444;font-size:0.82rem;margin-bottom:0.75rem;text-align:center;"></div>
                <div id="sq-2fa-section" style="display:none;margin-bottom:0.75rem;">
                    <label style="display:block;font-size:0.8rem;color:#94a3b8;margin-bottom:0.35rem;">Two-Factor Code</label>
                    <input type="text" id="sq-2fa-code" placeholder="Enter 6-digit code or recovery code" class="sq-input sqs-focus" maxlength="20" style="width:100%;box-sizing:border-box;">
                </div>
                <button type="submit" id="sq-submit-btn" class="sq-btn sq-btn-sqs">Login to SilverQueen</button>
            </form>
            <div class="sq-footer-links">
                <a href="https://sq.silverqueen.pro/register" class="sq-link sq-link-sqs">Create an account</a>
                <span>|</span>
                <a href="https://sq.silverqueen.pro/forgot-password" class="sq-link sq-link-sqs">Forgot password?</a>
            </div>
        </div>
        <!-- Ginto login panel -->
        <div class="sq-panel <?= $gintoLoginActive ? 'active' : '' ?>" id="panel-ginto">
            <div class="sq-logo-area ginto">
                <img src="/assets/images/ginto.png" alt="Ginto">
                <h2>Ginto</h2>
                <p>Ginto AI · silverqueen.pro</p>
            </div>
            <form action="https://silverqueen.pro/login" method="POST">
                <div class="sq-field">
                    <label>Email, Username, or Phone</label>
                    <input type="text" name="identifier" required placeholder="Enter your email, username, or phone" value="<?= htmlspecialchars($loginOld['identifier'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="sq-input ginto-focus">
                </div>
                <div class="sq-field sq-pw-wrap">
                    <label>Password</label>
                    <input type="password" name="password" id="pw-ginto" required placeholder="Password" class="sq-input ginto-focus" style="padding-right:2.75rem;">
                    <button type="button" onclick="togglePw('pw-ginto',this)" class="sq-pw-toggle" aria-label="Toggle password">
                        <svg class="pw-open" style="display:none;width:18px;height:18px;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        <svg class="pw-closed" style="width:18px;height:18px;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7 1.274-4.057 5.065-7 9.542-7 1.05 0 2.05.15 3 .425M12 5c4.477 0 8.268 2.943 9.542 7a10.04 10.04 0 01-1.5 3.5M16.5 13.5a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z"/></svg>
                    </button>
                </div>
                <button type="submit" class="sq-btn sq-btn-ginto">Login to Ginto</button>
            </form>
            <div class="sq-footer-links">
                <a href="https://silverqueen.pro/register" class="sq-link sq-link-ginto">Create a Ginto account</a>
                <span>|</span>
                <a href="https://silverqueen.pro/forgot-password" class="sq-link sq-link-ginto">Forgot password?</a>
            </div>
        </div>

    </div>
  </div>
</div>
<script>

function switchTab(tab) {
    var gintoTab = document.getElementById('tab-ginto');
    var sqsTab = document.getElementById('tab-sqs');
    var gintoPanel = document.getElementById('panel-ginto');
    var sqsPanel = document.getElementById('panel-sqs');
    var indicator = document.getElementById('tab-indicator');

    gintoTab.className = 'sq-tab';
    sqsTab.className = 'sq-tab';
    gintoPanel.className = 'sq-panel';
    sqsPanel.className = 'sq-panel';
    gintoTab.setAttribute('aria-selected', 'false');
    sqsTab.setAttribute('aria-selected', 'false');

    if (tab === 'sqs') {
        sqsTab.classList.add('active-sqs');
        sqsTab.setAttribute('aria-selected', 'true');
        sqsPanel.classList.add('active');
        indicator.className = 'sq-tab-indicator sqs';
    } else {
        gintoTab.classList.add('active-ginto');
        gintoTab.setAttribute('aria-selected', 'true');
        gintoPanel.classList.add('active');
        indicator.className = 'sq-tab-indicator ginto';
    }
}

function togglePw(id, btn) {
    var input = document.getElementById(id);
    var isOpen = input.type === 'text';
    input.type = isOpen ? 'password' : 'text';
    btn.querySelector('.pw-open').style.display = isOpen ? 'none' : 'block';
    btn.querySelector('.pw-closed').style.display = isOpen ? 'block' : 'none';
}

function sqLogin(e) {
    e.preventDefault();
    var errEl = document.getElementById('sq-error');
    var btn   = document.getElementById('sq-submit-btn');
    var twofa = document.getElementById('sq-2fa-section');
    var twofaInput = document.getElementById('sq-2fa-code');
    errEl.style.display = 'none';

    // If 2FA section is visible, we're on step 2 — submit the code.
    if (twofa.style.display !== 'none') {
        var code = twofaInput.value.trim();
        if (!code) { errEl.textContent = 'Enter your code.'; errEl.style.display = 'block'; return false; }
        btn.textContent = 'Verifying…';
        btn.disabled = true;

        fetch('/api/auth/cross-login/verify', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ temp_token: sqTempToken, code: code })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var user = data.data || data;
            var tokens = user.tokens || data.tokens || {};
            var accessToken = tokens.access_token || user.access_token || data.access_token || user.token || data.token;
            if (accessToken) {
                localStorage.setItem('sq_token', accessToken);
                localStorage.setItem('sq_refresh', tokens.refresh_token || '');
                localStorage.setItem('sq_user', JSON.stringify(user.user || user));
                window.location.href = '/login-jwt?token=' + encodeURIComponent(accessToken);
            } else {
                var errMsg = (user.error && typeof user.error === 'string') ? user.error
                    : (data.error && typeof data.error === 'string') ? data.error
                    : 'Invalid code. Please try again.';
                errEl.textContent = errMsg;
                errEl.style.display = 'block';
                btn.textContent = 'Verify Code';
                btn.disabled = false;
            }
        })
        .catch(function() {
            errEl.textContent = 'Network error. Please try again.';
            errEl.style.display = 'block';
            btn.textContent = 'Verify Code';
            btn.disabled = false;
        });
        return false;
    }

    // Step 1 — submit identifier + password.
    btn.textContent = 'Signing in…';
    btn.disabled = true;

    fetch('/api/auth/cross-login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({
            identifier: document.getElementById('sq-identifier').value.trim(),
            password:   document.getElementById('pw-sqs').value
        })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        var user = data.data || data;
        var tokens = user.tokens || data.tokens || {};
        var accessToken = tokens.access_token || user.access_token || data.access_token || user.token || data.token;
        var refreshToken = tokens.refresh_token || user.refresh_token || '';

        if (user.requires_2fa) {
            sqTempToken = user.temp_token || '';
            twofa.style.display = 'block';
            twofaInput.focus();
            btn.textContent = 'Verify Code';
            btn.disabled = false;
            return;
        }

        if (user.two_factor_required || data.two_factor_required) {
            errEl.textContent = user.error || data.error || 'Two-factor authentication is enabled. Sign in through the account security login flow.';
            errEl.style.display = 'block';
            btn.textContent = 'Login to SilverQueen';
            btn.disabled = false;
            return;
        }

        if (accessToken) {
            localStorage.setItem('sq_token', accessToken);
            localStorage.setItem('sq_refresh', refreshToken);
            localStorage.setItem('sq_user', JSON.stringify(user.user || user));
            window.location.href = '/login-jwt?token=' + encodeURIComponent(accessToken);
        } else {
            var errMsg = (user.error && typeof user.error === 'string') ? user.error
                : (data.error && typeof data.error === 'string') ? data.error
                : (user.message || data.message || 'Login failed. Please try again.');
            errEl.textContent = errMsg;
            errEl.style.display = 'block';
            btn.textContent = 'Login to SilverQueen';
            btn.disabled = false;
        }
    })
    .catch(function() {
        errEl.textContent = 'Network error. Please try again.';
        errEl.style.display = 'block';
        btn.textContent = 'Login to SilverQueen';
        btn.disabled = false;
    });
    return false;
}
var sqTempToken = '';

(function(){var widget=document.getElementById('sq-login-widget');if(widget){window.setTimeout(function(){widget.classList.add('is-ready');},180);}})();
</script>
