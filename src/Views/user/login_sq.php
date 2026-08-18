<?php
/** @var string $title */
?>
<?php require_once __DIR__ . '/../layout/login_header.php'; ?>

<style>
    .sq-page {
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 1.5rem;
        background: linear-gradient(135deg, #f5f3ff 0%, #e0e7ff 100%);
        transition: background 0.3s;
    }
    :root[class~="dark"] .sq-page {
        background: linear-gradient(135deg, #111827 0%, #1f2937 100%);
    }

    .sq-row {
        display: flex;
        flex-direction: column;
        align-items: stretch;
        gap: 1.5rem;
        width: 100%;
        max-width: 56rem;
    }
    @media (min-width: 1024px) {
        .sq-row {
            flex-direction: row;
            align-items: flex-start;
            justify-content: center;
            gap: 2rem;
        }
    }

    .sq-card {
        background: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: 1rem;
        box-shadow: 0 10px 40px rgba(0,0,0,0.12);
        padding: 2rem;
        width: 100%;
        max-width: 28rem;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .sq-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 14px 50px rgba(0,0,0,0.18);
    }
    @media (min-width: 1024px) {
        .sq-card {
            max-width: 24rem;
            flex: 1 1 0;
        }
    }

    .sq-divider-v {
        display: none;
    }
    .sq-divider-h {
        display: flex;
        align-items: center;
        gap: 1rem;
        width: 100%;
        max-width: 28rem;
        margin: 0 auto;
    }
    .sq-divider-h::before,
    .sq-divider-h::after {
        content: '';
        flex: 1;
        height: 1px;
        background: var(--border-color);
    }
    @media (min-width: 1024px) {
        .sq-divider-v {
            display: flex;
            align-items: center;
            align-self: stretch;
            padding: 0 0.25rem;
        }
        .sq-divider-h {
            display: none;
        }
    }

    .sq-label {
        color: var(--text-secondary);
        font-size: 0.85rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        white-space: nowrap;
    }
    .sq-label-v {
        writing-mode: vertical-lr;
        transform: rotate(180deg);
    }

    .sq-btn {
        width: 100%;
        padding: 0.75rem;
        border-radius: 0.5rem;
        font-weight: 600;
        font-size: 0.95rem;
        color: #fff;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
    }
    .sq-btn:active { transform: scale(0.98); }
    .sq-btn-sq {
        background: linear-gradient(135deg, #7c3aed, #a855f7);
    }
    .sq-btn-sq:hover {
        background: linear-gradient(135deg, #6d28d9, #9333ea);
    }
    .sq-btn-sqs {
        background: linear-gradient(135deg, #6366f1, #818cf8);
    }
    .sq-btn-sqs:hover {
        background: linear-gradient(135deg, #4f46e5, #6366f1);
    }

    .sq-input {
        width: 100%;
        border-radius: 0.5rem;
        padding: 0.75rem;
        border: 1px solid var(--border-color);
        background: var(--bg-primary);
        color: var(--text-primary);
        font-size: 0.95rem;
        transition: border-color 0.2s, box-shadow 0.2s;
        box-sizing: border-box;
    }
    .sq-input:focus {
        outline: none;
        border-color: #7c3aed;
        box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.15);
    }
    .sq-input::placeholder {
        color: var(--text-secondary);
        opacity: 0.6;
    }

    .sq-link {
        color: #7c3aed;
        text-decoration: none;
        font-weight: 500;
    }
    .sq-link:hover { text-decoration: underline; }

    .sq-badge {
        display: inline-block;
        font-size: 0.65rem;
        padding: 0.15rem 0.55rem;
        border-radius: 9999px;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }
    .sq-badge-main {
        background: rgba(124, 58, 237, 0.12);
        color: #7c3aed;
    }
    .sq-badge-sub {
        background: rgba(99, 102, 241, 0.12);
        color: #6366f1;
    }

    .sq-pw-wrap { position: relative; }
    .sq-pw-toggle {
        position: absolute;
        right: 0.75rem;
        top: 2.25rem;
        background: none;
        border: none;
        cursor: pointer;
        color: #9ca3af;
        padding: 0.25rem;
        transition: color 0.2s;
    }
    .sq-pw-toggle:hover { color: #7c3aed; }

    .sq-field { margin-bottom: 1rem; }
    .sq-field label {
        display: block;
        font-size: 0.85rem;
        font-weight: 500;
        color: var(--text-secondary);
        margin-bottom: 0.375rem;
    }

    .sq-footer-links {
        margin-top: 1rem;
        text-align: center;
        font-size: 0.85rem;
        color: var(--text-secondary);
    }
    .sq-footer-links span { opacity: 0.3; margin: 0 0.5rem; }

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
        margin-bottom: 2rem;
        color: var(--text-secondary);
    }

    .sq-powered {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        opacity: 0.6;
        transition: opacity 0.2s;
        text-decoration: none;
        margin-bottom: 1.5rem;
    }
    .sq-powered:hover { opacity: 1; }
    .sq-powered span {
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--text-secondary);
    }
</style>

<div class="sq-page">

    <!-- Ginto branding -->
    <a href="/" class="sq-powered">
        <img src="/assets/images/ginto.png" alt="Ginto" style="width:24px;height:24px;border-radius:50%;">
        <span>Powered by Ginto</span>
    </a>

    <h1 class="sq-title">Welcome to SilverQueen</h1>
    <p class="sq-subtitle">Choose a platform to sign in</p>

    <div class="sq-row">

        <!-- silverqueen.pro (left / default) -->
        <div class="sq-card">
            <span class="sq-badge sq-badge-main">Default</span>
            <h2 style="font-size:1.25rem;font-weight:700;text-align:center;margin:0.5rem 0 0.25rem;color:var(--text-primary);">SilverQueen</h2>
            <p style="text-align:center;font-size:0.85rem;margin-bottom:1.25rem;color:var(--text-secondary);">silverqueen.pro</p>

            <form action="https://silverqueen.pro/login" method="POST">
                <div class="sq-field">
                    <label>Email, Username, or Phone</label>
                    <input type="text" name="identifier" required placeholder="Enter your email, username, or phone" class="sq-input">
                </div>
                <div class="sq-field sq-pw-wrap">
                    <label>Password</label>
                    <input type="password" name="password" id="pw-sq" required placeholder="Password" class="sq-input" style="padding-right:2.75rem;">
                    <button type="button" onclick="togglePw('pw-sq',this)" class="sq-pw-toggle" aria-label="Toggle password visibility">
                        <svg class="pw-open" style="display:none;width:20px;height:20px;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        <svg class="pw-closed" style="width:20px;height:20px;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7 1.274-4.057 5.065-7 9.542-7 1.05 0 2.05.15 3 .425M12 5c4.477 0 8.268 2.943 9.542 7a10.04 10.04 0 01-1.5 3.5M16.5 13.5a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z"/></svg>
                    </button>
                </div>
                <button type="submit" class="sq-btn sq-btn-sq" style="margin-top:0.5rem;">Login to SilverQueen</button>
            </form>

            <div class="sq-footer-links">
                <a href="https://silverqueen.pro/register" class="sq-link">Create an account</a>
                <span>|</span>
                <a href="https://silverqueen.pro/forgot-password" class="sq-link">Forgot password?</a>
            </div>
        </div>

        <!-- Divider -->
        <div class="sq-divider-v">
            <span class="sq-label sq-label-v">OR</span>
        </div>
        <div class="sq-divider-h">
            <span class="sq-label">OR</span>
        </div>

        <!-- sq.silverqueen.pro (right) -->
        <div class="sq-card">
            <span class="sq-badge sq-badge-sub">Sub-Platform</span>
            <h2 style="font-size:1.25rem;font-weight:700;text-align:center;margin:0.5rem 0 0.25rem;color:var(--text-primary);">SQ Platform</h2>
            <p style="text-align:center;font-size:0.85rem;margin-bottom:1.25rem;color:var(--text-secondary);">sq.silverqueen.pro</p>

            <form action="https://sq.silverqueen.pro/login" method="POST">
                <div class="sq-field">
                    <label>Email, Username, or Phone</label>
                    <input type="text" name="identifier" required placeholder="Enter your email, username, or phone" class="sq-input">
                </div>
                <div class="sq-field sq-pw-wrap">
                    <label>Password</label>
                    <input type="password" name="password" id="pw-sqs" required placeholder="Password" class="sq-input" style="padding-right:2.75rem;">
                    <button type="button" onclick="togglePw('pw-sqs',this)" class="sq-pw-toggle" aria-label="Toggle password visibility">
                        <svg class="pw-open" style="display:none;width:20px;height:20px;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        <svg class="pw-closed" style="width:20px;height:20px;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7 1.274-4.057 5.065-7 9.542-7 1.05 0 2.05.15 3 .425M12 5c4.477 0 8.268 2.943 9.542 7a10.04 10.04 0 01-1.5 3.5M16.5 13.5a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z"/></svg>
                    </button>
                </div>
                <button type="submit" class="sq-btn sq-btn-sqs" style="margin-top:0.5rem;">Login to SQ Platform</button>
            </form>

            <div class="sq-footer-links">
                <a href="https://sq.silverqueen.pro/register" class="sq-link">Create an account</a>
                <span>|</span>
                <a href="https://sq.silverqueen.pro/forgot-password" class="sq-link">Forgot password?</a>
            </div>
        </div>

    </div>

    <p style="margin-top:2rem;text-align:center;font-size:0.75rem;color:var(--text-secondary);opacity:0.5;">
        Both platforms share the same credentials. Pick whichever you prefer.
    </p>

</div>

<script>
function togglePw(id, btn) {
    var input = document.getElementById(id);
    var isOpen = input.type === 'text';
    input.type = isOpen ? 'password' : 'text';
    btn.querySelector('.pw-open').style.display = isOpen ? 'none' : 'block';
    btn.querySelector('.pw-closed').style.display = isOpen ? 'block' : 'none';
}
</script>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
