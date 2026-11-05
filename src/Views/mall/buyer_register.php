<?php
$title = $title ?? 'Create Buyer Account - Ginto Mall';
$error = $error ?? null;
$old   = $old ?? [];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title) ?></title>
    <meta name="description" content="Create a free buyer account to shop on Ginto Mall">
    <link rel="icon" type="image/svg+xml" href="/assets/images/mall-favicon.svg">
    <link rel="shortcut icon" href="/assets/images/mall-favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg:       #0f172a;
            --surface:  #1e293b;
            --surface2: #263347;
            --text:     #f1f5f9;
            --muted:    #94a3b8;
            --border:   #2d3f55;
            --accent:   #3b82f6;
            --accent-h: #2563eb;
            --danger:   #ef4444;
            --radius:   12px;
            --trans:    0.2s ease;
        }
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            -webkit-font-smoothing: antialiased;
        }
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 2rem 2rem 2.5rem;
            width: 100%;
            max-width: 420px;
        }
        .logo-row {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            margin-bottom: 1.75rem;
            justify-content: center;
        }
        .logo-row img {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            object-fit: contain;
        }
        .logo-row span {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text);
        }
        h1 {
            font-size: 1.4rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 0.4rem;
        }
        .subtitle {
            font-size: 0.875rem;
            color: var(--muted);
            text-align: center;
            margin-bottom: 1.75rem;
        }
        .error-box {
            background: rgba(239,68,68,0.12);
            border: 1px solid rgba(239,68,68,0.4);
            color: #fca5a5;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 0.85rem;
            margin-bottom: 1.25rem;
            line-height: 1.5;
        }
        .error-box a { color: var(--accent); text-decoration: underline; }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
            margin-bottom: 1rem;
        }
        label {
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--muted);
            letter-spacing: 0.01em;
        }
        input {
            background: var(--surface2);
            border: 1px solid var(--border);
            color: var(--text);
            border-radius: 8px;
            padding: 0.65rem 0.85rem;
            font-size: 0.95rem;
            font-family: inherit;
            transition: border-color var(--trans), box-shadow var(--trans);
            outline: none;
            width: 100%;
        }
        input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(59,130,246,0.18);
        }
        input::placeholder { color: #475569; }
        .pw-wrap {
            position: relative;
        }
        .pw-toggle {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--muted);
            cursor: pointer;
            font-size: 1rem;
            padding: 0.2rem;
            line-height: 1;
            outline: none;
        }
        .pw-toggle:focus-visible { color: var(--accent); }
        .btn-primary {
            width: 100%;
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 0.8rem;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            margin-top: 0.5rem;
            transition: background var(--trans), transform 0.1s;
            font-family: inherit;
        }
        .btn-primary:hover { background: var(--accent-h); }
        .btn-primary:active { transform: scale(0.98); }
        .btn-primary:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
        .divider {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin: 1.25rem 0;
            color: var(--muted);
            font-size: 0.8rem;
        }
        .divider::before, .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }
        .login-link {
            text-align: center;
            font-size: 0.875rem;
            color: var(--muted);
        }
        .login-link a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
        }
        .login-link a:hover { text-decoration: underline; }
        .back-link {
            text-align: center;
            font-size: 0.82rem;
            margin-top: 1rem;
        }
        .back-link a { color: var(--muted); text-decoration: none; }
        .back-link a:hover { color: var(--text); text-decoration: underline; }
        .badge {
            display: inline-block;
            background: rgba(59,130,246,0.15);
            color: var(--accent);
            border: 1px solid rgba(59,130,246,0.3);
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.2rem 0.65rem;
            margin: 0 auto 1.5rem;
            display: block;
            width: fit-content;
            text-align: center;
        }
    </style>
</head>
<body>
<div class="card">
    <div class="logo-row">
        <img src="/assets/images/mall.png" alt="Ginto Mall" onerror="this.style.display='none'">
        <span>Ginto Mall</span>
    </div>

    <span class="badge">🛍️ It's free — takes 30 seconds</span>

    <h1>Create a Buyer Account</h1>
    <p class="subtitle">Sign up to complete your purchase. No credit card needed.</p>

    <?php if ($error): ?>
        <div class="error-box"><?= $error /* intentionally unescaped — we control this value and embed a link */ ?></div>
    <?php endif; ?>

    <form method="POST" action="/mall/buyer-register" id="buyerForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">

        <div class="form-group">
            <label for="fullname">Full Name</label>
            <input type="text" id="fullname" name="fullname"
                   value="<?= htmlspecialchars($old['fullname'] ?? '') ?>"
                   placeholder="e.g. Maria Santos"
                   required autocomplete="name">
        </div>

        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email"
                   value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                   placeholder="you@example.com"
                   required autocomplete="email">
        </div>

        <div class="form-group">
            <label for="phone">Phone Number</label>
            <input type="tel" id="phone" name="phone"
                   value="<?= htmlspecialchars($old['phone'] ?? '') ?>"
                   placeholder="+63 912 345 6789"
                   required autocomplete="tel">
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <div class="pw-wrap">
                <input type="password" id="password" name="password"
                       placeholder="At least 6 characters"
                       required autocomplete="new-password" minlength="6">
                <button type="button" class="pw-toggle" id="pwToggle" aria-label="Show/hide password">👁</button>
            </div>
        </div>

        <button type="submit" class="btn-primary" id="submitBtn">Create Account &amp; Continue</button>
    </form>

    <div class="divider">or</div>

    <p class="login-link">Already have an account? <a href="/login">Log in</a></p>
    <p class="back-link"><a href="javascript:history.back()">← Back to shop</a></p>
</div>

<script>
    // Toggle password visibility
    document.getElementById('pwToggle').addEventListener('click', function() {
        const pw = document.getElementById('password');
        if (pw.type === 'password') {
            pw.type = 'text';
            this.textContent = '🙈';
        } else {
            pw.type = 'password';
            this.textContent = '👁';
        }
    });

    // Prevent double-submit
    document.getElementById('buyerForm').addEventListener('submit', function() {
        const btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.textContent = 'Creating account…';
    });
</script>
</body>
</html>
