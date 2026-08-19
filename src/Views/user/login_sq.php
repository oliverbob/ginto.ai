<?php /** @var string $title */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Ginto') ?></title>
    <link rel="stylesheet" href="/assets/css/tailwind.css">
    <link rel="stylesheet" href="/assets/fontawesome/css/all.min.css">
    <link rel="icon" type="image/png" href="/assets/images/ginto.png">
    <script src="/assets/js/theme.js"></script>
    <style>
        :root {
            --bg-primary: #ffffff; --bg-secondary: #f3f4f6; --text-primary: #111827;
            --text-secondary: #4b5563; --border-color: #e5e7eb; --accent-color: #3b82f6;
            --sidebar-bg: #ffffff; --card-bg: #ffffff; --hover-bg: #f3f4f6;
        }
        :root[class~="dark"] {
            --bg-primary: #111827; --bg-secondary: #1f2937; --text-primary: #f3f4f6;
            --text-secondary: #9ca3af; --border-color: #374151; --accent-color: #60a5fa;
            --sidebar-bg: #1f2937; --card-bg: #1f2937; --hover-bg: #374151;
        }
        body { background-color: var(--bg-secondary); color: var(--text-primary); }
    </style>
</head>
<body class="antialiased">

<style>
    .sq-page {
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 0.5rem 1.5rem 1.5rem;
        background: linear-gradient(135deg, #f5f3ff 0%, #e0e7ff 100%);
        transition: background 0.3s;
    }
    :root[class~="dark"] .sq-page {
        background: linear-gradient(135deg, #111827 0%, #1f2937 100%);
    }

    .sq-card {
        background: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: 1rem;
        box-shadow: 0 10px 40px rgba(0,0,0,0.12);
        padding: 2rem 2rem 1.75rem;
        width: 100%;
        max-width: 26rem;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .sq-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 14px 50px rgba(0,0,0,0.18);
    }

    /* Tabs */
    .sq-tabs {
        display: flex;
        border-radius: 0.625rem;
        overflow: hidden;
        border: 1px solid var(--border-color);
        margin-bottom: 0.35rem;
        position: relative;
    }
    .sq-tab {
        flex: 1;
        padding: 0.625rem 0;
        text-align: center;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        border: none;
        background: transparent;
        color: var(--text-secondary);
        transition: all 0.25s ease;
        position: relative;
    }
    .sq-tab:not(:last-child) {
        border-right: 1px solid var(--border-color);
    }
    .sq-tab:hover {
        background: var(--hover-bg);
    }
    .sq-tab.active-ginto {
        background: rgba(191,161,74,0.1);
        color: #bfa14a;
    }
    .sq-tab.active-sqs {
        background: rgba(99,102,241,0.1);
        color: #6366f1;
    }

    /* Tab indicator bar */
    .sq-tab-indicator {
        height: 3px;
        border-radius: 2px;
        transition: background 0.3s, width 0.3s, left 0.3s;
        position: absolute;
        bottom: -2px;
        left: 0;
        z-index: 1;
    }
    .sq-tab-indicator.ginto {
        width: 40%;
        left: 5%;
        background: linear-gradient(90deg, #bfa14a, #d4a843);
    }
    .sq-tab-indicator.sqs {
        width: 40%;
        left: 55%;
        background: linear-gradient(90deg, #6366f1, #818cf8);
    }

    /* Logo area */
    .sq-logo-area {
        text-align: center;
        margin-bottom: 1.25rem;
        transition: opacity 0.2s;
    }
    .sq-logo-area img {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        display: block;
        margin: 0 auto 0.5rem;
        transition: box-shadow 0.3s;
    }
    .sq-logo-area.ginto img {
        box-shadow: 0 0 14px rgba(191,161,74,0.4);
    }
    .sq-logo-crown {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 0.5rem;
        background: rgba(99,102,241,0.1);
        box-shadow: 0 0 14px rgba(99,102,241,0.35);
        transition: box-shadow 0.3s;
    }
    .sq-logo-crown i {
        font-size: 1.5rem;
        color: #6366f1;
    }
    .sq-logo-area h2 {
        font-size: 1.2rem;
        font-weight: 700;
        margin: 0 0 0.15rem;
        color: var(--text-primary);
    }
    .sq-logo-area p {
        font-size: 0.8rem;
        margin: 0;
        color: var(--text-secondary);
    }

    /* Form shared */
    .sq-field { margin-bottom: 1rem; }
    .sq-field label {
        display: block;
        font-size: 0.82rem;
        font-weight: 500;
        color: var(--text-secondary);
        margin-bottom: 0.35rem;
    }
    .sq-input {
        width: 100%;
        border-radius: 0.5rem;
        padding: 0.7rem 0.75rem;
        border: 1px solid var(--border-color);
        background: var(--bg-primary);
        color: var(--text-primary);
        font-size: 0.92rem;
        transition: border-color 0.2s, box-shadow 0.2s;
        box-sizing: border-box;
    }
    .sq-input:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba(124,58,237,0.12);
    }
    .sq-input.ginto-focus:focus {
        border-color: #bfa14a;
        box-shadow: 0 0 0 3px rgba(191,161,74,0.15);
    }
    .sq-input.sqs-focus:focus {
        border-color: #6366f1;
        box-shadow: 0 0 0 3px rgba(99,102,241,0.15);
    }
    .sq-input::placeholder {
        color: var(--text-secondary);
        opacity: 0.55;
    }

    .sq-pw-wrap { position: relative; }
    .sq-pw-toggle {
        position: absolute;
        right: 0.65rem;
        top: 2.15rem;
        background: none;
        border: none;
        cursor: pointer;
        color: #9ca3af;
        padding: 0.25rem;
        transition: color 0.2s;
    }
    .sq-pw-toggle:hover { color: #7c3aed; }

    .sq-btn {
        width: 100%;
        padding: 0.7rem;
        border-radius: 0.5rem;
        font-weight: 600;
        font-size: 0.92rem;
        color: #fff;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
        margin-top: 0.25rem;
    }
    .sq-btn:active { transform: scale(0.98); }
    .sq-btn-ginto {
        background: linear-gradient(135deg, #bfa14a, #d4a843);
    }
    .sq-btn-ginto:hover {
        background: linear-gradient(135deg, #a8903d, #c49a38);
    }
    .sq-btn-sqs {
        background: linear-gradient(135deg, #6366f1, #818cf8);
    }
    .sq-btn-sqs:hover {
        background: linear-gradient(135deg, #4f46e5, #6366f1);
    }

    .sq-footer-links {
        margin-top: 1rem;
        text-align: center;
        font-size: 0.82rem;
        color: var(--text-secondary);
    }
    .sq-footer-links span { opacity: 0.3; margin: 0 0.5rem; }
    .sq-link { text-decoration: none; font-weight: 500; }
    .sq-link:hover { text-decoration: underline; }
    .sq-link-ginto { color: #bfa14a; }
    .sq-link-sqs { color: #6366f1; }

    /* Tab content panels */
    .sq-panel { display: none; padding-top: 1rem; }
    .sq-panel.active { display: block; }

    .sq-title {
        font-size: 1.5rem;
        font-weight: 700;
        text-align: center;
        margin-bottom: 0.25rem;
        color: var(--text-primary);
    }
    @media (min-width: 640px) {
        .sq-title { font-size: 1.875rem; }
    }
    .sq-subtitle {
        text-align: center;
        margin-bottom: 1.25rem;
        color: var(--text-secondary);
    }
    .sq-powered {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        opacity: 0.6;
        transition: opacity 0.2s;
        text-decoration: none;
        margin-bottom: 0.5rem;
    }
    .sq-powered:hover { opacity: 1; }
    .sq-powered span {
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--text-secondary);
    }
</style>

<div class="sq-page">

    <a href="/" class="sq-powered">
        <img src="/assets/images/ginto.png" alt="Ginto" style="width:24px;height:24px;border-radius:50%;">
        <span>Powered by Ginto</span>
    </a>

    <h1 class="sq-title">Welcome to SilverQueen</h1>
    <p class="sq-subtitle">Choose a platform to sign in</p>

    <div class="sq-card">

        <!-- Tabs -->
        <div class="sq-tabs">
            <button type="button" class="sq-tab active-ginto" id="tab-ginto" onclick="switchTab('ginto')">
                <i class="fas fa-coins" style="margin-right:0.35rem;"></i>Ginto
            </button>
            <button type="button" class="sq-tab" id="tab-sqs" onclick="switchTab('sqs')">
                <i class="fas fa-gem" style="margin-right:0.35rem;"></i>SilverQueen
            </button>
        </div>
        <div class="sq-tab-indicator ginto" id="tab-indicator"></div>

        <!-- Ginto Panel -->
        <div class="sq-panel active" id="panel-ginto">
            <div class="sq-logo-area ginto">
                <img src="/assets/images/ginto.png" alt="Ginto">
                <h2>Ginto</h2>
                <p>silverqueen.pro</p>
            </div>
            <form action="https://silverqueen.pro/login" method="POST">
                <div class="sq-field">
                    <label>Email, Username, or Phone</label>
                    <input type="text" name="identifier" required placeholder="Enter your email, username, or phone" class="sq-input ginto-focus">
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
                <a href="https://silverqueen.pro/register" class="sq-link sq-link-ginto">Create an account</a>
                <span>|</span>
                <a href="https://silverqueen.pro/forgot-password" class="sq-link sq-link-ginto">Forgot password?</a>
            </div>
        </div>

        <!-- SilverQueen Panel -->
        <div class="sq-panel" id="panel-sqs">
            <div class="sq-logo-area sqs">
                <div class="sq-logo-crown"><i class="fas fa-crown"></i></div>
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
                <button type="submit" id="sq-submit-btn" class="sq-btn sq-btn-sqs">Login to SilverQueen</button>
            </form>
            <div class="sq-footer-links">
                <a href="https://sq.silverqueen.pro/register" class="sq-link sq-link-sqs">Create an account</a>
                <span>|</span>
                <a href="https://sq.silverqueen.pro/forgot-password" class="sq-link sq-link-sqs">Forgot password?</a>
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

    if (tab === 'ginto') {
        gintoTab.classList.add('active-ginto');
        gintoPanel.classList.add('active');
        indicator.className = 'sq-tab-indicator ginto';
    } else {
        sqsTab.classList.add('active-sqs');
        sqsPanel.classList.add('active');
        indicator.className = 'sq-tab-indicator sqs';
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
    errEl.style.display = 'none';
    btn.textContent = 'Signing in…';
    btn.disabled = true;

    fetch('https://sq.silverqueen.pro/api/auth/cross-login', {
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
        var accessToken = tokens.access_token || user.access_token || data.access_token;
        if (accessToken) {
            localStorage.setItem('sq_token', accessToken);
            localStorage.setItem('sq_refresh', tokens.refresh_token || user.refresh_token || '');
            localStorage.setItem('sq_user', JSON.stringify(user.user || user));
            window.location.href = 'https://sq.silverqueen.pro/wallet';
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
</script>

</body></html>
