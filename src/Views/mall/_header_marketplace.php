<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ePower Mall — Premium Demo</title>
    <meta name="description" content="ePower Mall — demo storefront">
    
    <!-- Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Vendor assets used across marketplace & seller pages -->
    <link rel="stylesheet" href="/lib/fontawesome/css/all.min.css">
    <script src="/assets/js/tailwindcss.js"></script>
    <script src="https://www.paypal.com/sdk/js?client-id=AZgBWH2d8yEZqBxYOwpQhU_pD8M2R2InPsU80V97EGksIZzw-QzfvWcCbP3J3nKaQ6xQZ3ZkdurydxKo&vault=true&intent=subscription&components=buttons"></script>

    <style>
        /* --- Variables & Reset --- */
        :root {
            /* Dark Mode (Default) */
            --bg-body: #0f172a;
            --bg-surface: #1e293b;
            --bg-surface-hover: #334155;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border: #334155;
            --accent: #3b82f6; /* Blue */
            --accent-hover: #2563eb;
            --accent-text: #ffffff;
            --danger: #ef4444;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3);
            --radius: 12px;
            --header-height: 70px;
        }
        body.light { --bg-body: #f1f5f9; --bg-surface: #ffffff; }
        *{ box-sizing: border-box; outline-color: var(--accent); }
        body { margin:0; font-family:'Inter', system-ui, -apple-system, sans-serif; background:var(--bg-body); color:var(--text-main); }
        /* Header layout (kept concise) */
        .container{ max-width:1200px; margin:0 auto; padding:0 20px; }
        .header{ height:var(--header-height); position:sticky; top:0; z-index:40; background:rgba(15,23,42,0.85); backdrop-filter: blur(12px); border-bottom:1px solid var(--border); display:flex; align-items:center; }
        .header-inner{ display:flex; align-items:center; justify-content:space-between; gap:20px; width:100%; }
        .brand{ font-size:1.25rem; font-weight:800; color:var(--accent); display:flex; align-items:center; gap:8px; }
    </style>
</head>
<body>

    <!-- Header -->
    <header class="header">
        <div class="container header-inner">
            <button class="mobile-toggle" aria-label="Toggle Menu" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>
            <a href="/marketplace" class="brand">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                ePower
            </a>
            <div class="header-actions">
                <button class="icon-btn" id="themeToggle" aria-label="Toggle Theme"><span id="themeIcon">☀️</span></button>
            </div>
        </div>
    </header>

    <!-- Main Layout -->
    <div class="container wrapper">
