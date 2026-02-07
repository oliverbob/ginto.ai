<?php
$title = $title ?? 'ePower Mall — Premium Demo';
?>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title) ?></title>
    <meta name="description" content="ePower Mall — demo storefront">
    
    <!-- Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
                .gold-cards-grid {
                    display: flex;
                    flex-direction: column;
                    gap: 16px;
                    margin-bottom: 28px;
                }
                .gold-card {
                    width: 100%;
                    background: #23272f;
                    border-radius: 16px;
                    box-shadow: 0 4px 24px #eab30833;
                    position: relative;
                    overflow: hidden;
                    min-height: 110px;
                    max-width: 100%;
                    display: flex;
                    flex-direction: column;
                    justify-content: flex-end;
                    cursor: pointer;
                    transition: box-shadow 0.18s, transform 0.16s;
                }
                .gold-card:hover {
                    box-shadow: 0 8px 32px #facc1588;
                    transform: translateY(-2px) scale(1.025);
                }
                .gold-card-img {
                    position: absolute;
                    inset: 0;
                    width: 100%;
                    height: 100%;
                    object-fit: cover;
                    border-radius: 16px;
                    z-index: 1;
                }
                .gold-card-overlay {
                    position: absolute;
                    left: 0; right: 0; bottom: 0;
                    height: 38%;
                    background: linear-gradient(0deg, rgba(0,0,0,0.72) 70%, rgba(0,0,0,0.12) 100%);
                    z-index: 2;
                    display: flex;
                    align-items: flex-end;
                    border-radius: 0 0 16px 16px;
                }
                .gold-card-label {
                    position: relative;
                    z-index: 3;
                    display: flex;
                    align-items: center;
                    font-size: 1.13rem;
                    font-weight: 600;
                    color: #fff;
                    padding: 0 0 10px 14px;
                    text-shadow: 0 2px 8px #000a;
                }
                .gold-card-label .card-num {
                    font-size: 1.35rem;
                    font-weight: 900;
                    color: #ffe066;
                    margin-right: 10px;
                    text-shadow: 0 2px 8px #000a, 0 1px 0 #fff8;
                }
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

        body.light {
            --bg-body: #f1f5f9;
            --bg-surface: #ffffff;
            --bg-surface-hover: #f8fafc;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --accent: #2563eb;
            --accent-hover: #1d4ed8;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        * { box-sizing: border-box; outline-color: var(--accent); }
        body {
            margin: 0;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--bg-body);
            color: var(--text-main);
            line-height: 1.5;
            transition: background 0.3s, color 0.3s;
        }
        a { color: inherit; text-decoration: none; }
        button { cursor: pointer; font-family: inherit; }
        
        /* --- Layout --- */
        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        
        /* Header */
        .header {
            height: var(--header-height);
            position: sticky; top: 0; z-index: 40;
            background: rgba(15, 23, 42, 0.85); /* Dark fallback */
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center;
        }
        body.light .header { background: rgba(255, 255, 255, 0.85); }

        .header-inner {
                    .header-row1 {
                        min-height: 56px;
                    }
                    .header-row2 {
                        margin-top: 0; margin-bottom: 0; padding-top: 0; padding-bottom: 12px;
                        display: flex; align-items: center; justify-content: center;
                    }
                    .header-row2 .search-bar {
                        flex: 1;
                        max-width: 600px;
                    }
            display: flex; align-items: center; justify-content: space-between; gap: 20px; width: 100%;
        }
        .brand { font-size: 1.25rem; font-weight: 800; color: var(--accent); letter-spacing: -0.5px; display: flex; align-items: center; gap: 8px; }
        
        .search-bar {
            flex: 1; max-width: 500px; display: flex; gap: 10px;
        }
        .input-group {
            display: flex; flex: 1; position: relative;
        }
        input, select {
            background: var(--bg-surface); border: 1px solid var(--border);
            color: var(--text-main); padding: 10px 16px; border-radius: var(--radius);
            width: 100%; font-size: 0.95rem; transition: 0.2s; line-height: 1.2;
            -webkit-font-smoothing: antialiased;
        }
        /* Improve native select dropdown spacing (best-effort across browsers) */
        .search-bar select { padding: 10px 14px; min-width: 140px; padding-right: 40px; }
        /* Option spacing: many browsers ignore option padding, but setting line-height + min-height helps */
        select option {
            padding: 8px 14px;
            line-height: 1.6;
            min-height: 36px; /* helps spacing in some browsers */
            box-sizing: border-box;
            font-size: 0.95rem;
        }
        /* Provide space for native dropdown chevron and avoid clipped text */
        .search-bar select { padding-right: 40px; }
        /* For browsers that support accent-color, set it for consistent checked styles */
        input[type="checkbox"] { accent-color: var(--accent); }

        /* Custom select styles */
        .custom-select { position: relative; display: inline-block; min-width: 160px; }
        .custom-select-btn {
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
            background: var(--bg-surface); border: 1px solid var(--border); color: var(--text-main);
            padding: 9px 12px; border-radius: 10px; width: 100%; cursor: pointer; font-weight: 600;
        }
        .custom-select .chev { opacity: 0.9; margin-left: 6px; }
        .custom-select-list {
            position: absolute; right: 0; left: 0; margin-top: 8px; list-style:none; padding:6px 6px; border-radius:10px;
            background: var(--bg-surface); border: 1px solid var(--border); box-shadow: 0 8px 24px rgba(2,6,23,0.6);
            max-height: 260px; overflow:auto; display:none; z-index: 60;
        }
        .custom-select[aria-expanded="true"] .custom-select-list { display:block; }
        .custom-select-list li { padding: 10px 12px; cursor: pointer; color: var(--text-main); border-radius:6px; }
        .custom-select-list li[aria-selected="true"], .custom-select-list li:hover { background: var(--accent); color: var(--accent-text); }
        input:focus, select:focus { border-color: var(--accent); box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2); outline: none; }

        .header-actions { display: flex; gap: 12px; align-items: center; }
        .icon-btn {
            background: transparent; border: none; color: var(--text-muted);
            padding: 8px; border-radius: 50%; transition: 0.2s;
            position: relative;
        }
        .icon-btn:hover { background: var(--bg-surface-hover); color: var(--accent); }
        .badge-count {
            position: absolute; top: -2px; right: -2px;
            background: var(--danger); color: white; font-size: 0.7rem;
            padding: 2px 6px; border-radius: 10px; font-weight: 700;
            border: 2px solid var(--bg-body);
        }

        /* Main Layout */
        .wrapper { display: flex; gap: 30px; margin-top: 30px; padding-bottom: 40px; }
        
        /* Sidebar */
        .sidebar { width: 250px; flex-shrink: 0; }
        .filter-group { margin-bottom: 24px; }
        .filter-title { font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); font-weight: 700; margin-bottom: 12px; }

        /* Sidebar checkbox styling to avoid large default white squares */
        .filter-group label { display: flex; align-items: center; gap: 8px; cursor: pointer; color: var(--text-main); margin-bottom: 6px; }
        .filter-group input[type="checkbox"] {
            -webkit-appearance: none; appearance: none;
            width: 18px; height: 18px; min-width: 18px; min-height: 18px;
            border-radius: 4px; border: 1px solid var(--border);
            background: transparent; display: inline-block; position: relative;
            box-shadow: none; vertical-align: middle;
        }
        .filter-group input[type="checkbox"]:checked {
            background: var(--accent); border-color: var(--accent);
        }
        .filter-group input[type="checkbox"]::after {
            content: '';
            position: absolute;
            left: 50%; top: 50%;
            width: 6px; height: 10px;
            border: solid var(--accent-text);
            border-width: 0 2px 2px 0;
            transform: translate(-50%, -60%) rotate(45deg);
            display: none;
            box-sizing: border-box;
        }
        .filter-group input[type="checkbox"]:checked::after { display: block; }
        
        .cat-btn {
            display: block; width: 100%; text-align: left;
            padding: 10px 14px; border: none; background: transparent;
            color: var(--text-main); border-radius: var(--radius);
            font-weight: 500; transition: 0.2s;
        }
        .cat-btn:hover, .cat-btn.active { background: var(--bg-surface); color: var(--accent); }
        .cat-btn.active { font-weight: 700; }

        /* Grid */
        .main-content { flex: 1; }
        .top-controls { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 24px;
        }

        /* Card */
        .card {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius); overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative; display: flex; flex-direction: column;
        }
        .card:hover { transform: translateY(-4px); box-shadow: var(--shadow); border-color: var(--accent); }
        
        .card-img-wrap { position: relative; padding-top: 75%; overflow: hidden; background: #000; }
        .card-img {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            object-fit: cover; transition: transform 0.5s;
        }
        .card:hover .card-img { transform: scale(1.05); }
        
        .card-badge {
            position: absolute; top: 12px; left: 12px;
            background: var(--accent); color: white;
            padding: 4px 10px; border-radius: 6px;
            font-size: 0.75rem; font-weight: 600;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .card-body { padding: 16px; display: flex; flex-direction: column; flex: 1; }
        .card-title { font-size: 1.05rem; font-weight: 600; margin: 0 0 6px 0; }
        .card-meta { font-size: 0.85rem; color: var(--text-muted); display: flex; justify-content: space-between; }
        .card-price { font-size: 1.15rem; font-weight: 700; color: var(--text-main); margin-top: 12px; }
        
        .btn {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 10px 20px; border-radius: var(--radius); border: none;
            font-weight: 600; transition: 0.2s;
        }
        .btn-primary { background: var(--accent); color: var(--accent-text); width: 100%; margin-top: 16px; }
        .btn-primary:hover { background: var(--accent-hover); }
        .btn-secondary { background: transparent; border: 1px solid var(--border); color: var(--text-main); }
        .btn-secondary:hover { border-color: var(--text-muted); }

        /* Quick View Modal */
        .modal-backdrop {
            position: fixed; inset: 0; background: rgba(0,0,0,0.7);
            z-index: 100; display: flex; align-items: center; justify-content: center;
            opacity: 0; pointer-events: none; transition: opacity 0.3s;
            backdrop-filter: blur(4px);
        }
        .modal-backdrop.open { opacity: 1; pointer-events: auto; }
        
        .modal {
            background: var(--bg-body); width: 90%; max-width: 900px;
            border-radius: var(--radius); overflow: hidden;
            display: grid; grid-template-columns: 1fr 1fr;
            border: 1px solid var(--border); box-shadow: var(--shadow);
            transform: scale(0.95); transition: transform 0.3s;
        }
        .modal-backdrop.open .modal { transform: scale(1); }
        
        .modal-img { width: 100%; height: 100%; object-fit: cover; min-height: 400px; background: #111; }
        .modal-content { padding: 30px; display: flex; flex-direction: column; justify-content: center; }
        
        /* Cart Drawer */
        .drawer-backdrop {
            position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 200;
            opacity: 0; pointer-events: none; transition: 0.3s;
        }
        .drawer {
            position: fixed; top: 0; right: 0; bottom: 0; width: 100%; max-width: 400px;
            background: var(--bg-surface); z-index: 201;
            box-shadow: -4px 0 20px rgba(0,0,0,0.3);
            transform: translateX(100%); transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex; flex-direction: column;
        }
        .drawer-open .drawer-backdrop { opacity: 1; pointer-events: auto; }
        .drawer-open .drawer { transform: translateX(0); }
        
        .drawer-header { padding: 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .drawer-body { flex: 1; overflow-y: auto; padding: 20px; }
        .drawer-footer { padding: 20px; border-top: 1px solid var(--border); background: var(--bg-body); }
        
        .cart-item { display: flex; gap: 12px; margin-bottom: 16px; padding-bottom: 16px; border-bottom: 1px solid var(--border); }
        .cart-item img { width: 70px; height: 70px; border-radius: 8px; object-fit: cover; }
        .cart-item-details { flex: 1; }
        .cart-controls { display: flex; align-items: center; gap: 10px; margin-top: 8px; }
        .qty-btn { width: 24px; height: 24px; border-radius: 4px; border: 1px solid var(--border); background: transparent; color: var(--text-main); display: flex; align-items: center; justify-content: center; }
        
        /* Toast */
        .toast-container { position: fixed; bottom: 24px; right: 24px; z-index: 300; display: flex; flex-direction: column; gap: 10px; }
        .toast {
            background: var(--bg-surface); color: var(--text-main);
            padding: 12px 16px; border-radius: 8px; border: 1px solid var(--border);
            box-shadow: var(--shadow); border-left: 4px solid var(--accent);
            animation: slideIn 0.3s ease forwards;
        }
        @keyframes slideIn { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        /* Utilities & Mobile */
        .text-center { text-align: center; }
        .empty-state { padding: 40px; text-align: center; color: var(--text-muted); }
        .mobile-toggle { display: none; }

        @media (max-width: 900px) {
            .modal { grid-template-columns: 1fr; max-height: 90vh; overflow-y: auto; }
            .modal-img { height: 250px; min-height: auto; }
        }

        @media (max-width: 800px) {
            .container { padding: 0 8px; /* Tighter padding for mobile container */ }
            .header-row2 { padding-bottom: 8px; }
            .header-row2 .search-bar { max-width: 100%; }
            /* Mobile hamburger (restored in-flow): keep visible and clickable but not fixed so header order remains "hamburger, logo, search" */
            .mobile-toggle { display: block; font-size: 1.5rem; background: none; border: none; color: var(--text-main);
                position: relative; z-index: 10002; padding: 8px; border-radius: 8px; display:flex; align-items:center; justify-content:center; margin-right: 8px; }
            .mobile-toggle:focus { outline: 2px solid rgba(59,130,246,0.4); }

            /* Backdrop that appears when sidebar opens */
            .sidebar-backdrop { position: fixed; inset: 0; background: rgba(2,6,23,0.72); backdrop-filter: blur(6px); opacity: 0; pointer-events: none; transition: opacity 0.25s ease; z-index: 9998; }
            .sidebar-backdrop.open { opacity: 1; pointer-events: auto; }

            /* Mobile sidebar header sizing variable - use this to reserve space so content starts below it */
            :root { --mobile-sidebar-header-height: 56px; }

            /* Mobile overlay sidebar styling – slightly wider and rounded to match new design */
            .sidebar {
                /* Make sidebar overlay the header on mobile and ensure content is pushed below the header */
                position: fixed; top: 0; left: 0; bottom: 0;
                width: 320px; background: var(--bg-surface); z-index: 9999;
                /* Reserve top space for the internal header so content begins below it */
                padding: calc(var(--mobile-sidebar-header-height) + 18px) 18px 20px 18px;
                border-right: 1px solid rgba(255,255,255,0.04);
                border-radius: 0 12px 12px 0;
                box-shadow: 0 30px 80px rgba(2,6,23,0.6);
                /* slide fully off-screen (a bit more than 100% to prevent small overlap) */
                transform: translateX(-112%); transition: transform 0.32s cubic-bezier(0.16, 1, 0.3, 1);
            }

            /* Small header area inside the sidebar (logo + close button) - positioned at the very top */
            .sidebar .mobile-sidebar-header { position:absolute; top:12px; left:20px; right:20px; height:var(--mobile-sidebar-header-height); display:flex; align-items:center; justify-content:space-between; gap:12px; z-index:10000; }
            .sidebar .mobile-sidebar-header img { height:44px; width:44px; object-fit:cover; border-radius:50%; box-shadow:0 6px 18px rgba(2,6,23,0.5); border:2px solid #f59e0b; background:#fff; }
            .sidebar .close-btn { background:transparent; border:2px solid rgba(255,255,255,0.06); color:var(--text-main); font-size:1.1rem; padding:6px 8px; border-radius:10px; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; backdrop-filter: blur(4px); }
            .sidebar .close-btn:focus { outline: 2px solid rgba(59,130,246,0.4); box-shadow:0 2px 8px rgba(2,6,23,0.25); }
            .wrapper { 
                display: block; 
                margin-top: 10px; /* Reduced top margin */
            }
            .sidebar.open { transform: translateX(0); z-index: 9999; }

            /* Small header area inside the sidebar (logo + close button) - positioned at the very top */
            .sidebar .mobile-sidebar-header { position:absolute; top:12px; left:20px; right:20px; height:var(--mobile-sidebar-header-height); display:flex; align-items:center; justify-content:space-between; gap:12px; z-index:10000; }
            .sidebar .mobile-sidebar-header img { height:44px; width:44px; object-fit:cover; border-radius:50%; box-shadow:0 6px 18px rgba(2,6,23,0.5); border:2px solid #f59e0b; background:#fff; }
            .sidebar .close-btn { background:transparent; border:2px solid rgba(255,255,255,0.06); color:var(--text-main); font-size:1.1rem; padding:6px 8px; border-radius:10px; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; backdrop-filter: blur(4px); }
            .sidebar .close-btn:focus { outline: 2px solid rgba(59,130,246,0.4); box-shadow:0 2px 8px rgba(2,6,23,0.25); }
            .wrapper { 
                display: block; 
                margin-top: 10px; /* Reduced top margin */
            }

/* Sidebar close button behavior: hidden by default (desktop) and shown only when the sidebar drawer is open */
.mobile-sidebar-header .close-btn,
.sidebar #sidebarCloseBtn.close-btn,
.sidebar .close-btn {
    display: none !important;
    visibility: hidden !important;
    pointer-events: none !important;
    width: 0 !important;
    height: 0 !important;
    opacity: 0 !important;
}

/* Show the close button only on small screens when the sidebar drawer is open (overlay state) */
@media (max-width: 800px) {
    .sidebar.open .mobile-sidebar-header .close-btn,
    .sidebar.open #sidebarCloseBtn.close-btn {
        display: inline-flex !important;
        visibility: visible !important;
        pointer-events: auto !important;
        width: auto !important;
        height: auto !important;
        opacity: 1 !important;
    }
}
            /* .search-bar { display: none; }  Removed to keep search bar visible on mobile */
            .search-bar.mobile-visible { display: flex; position: absolute; top: 100%; left:0; right:0; padding: 10px; background: var(--bg-surface); border-bottom: 1px solid var(--border); }

            /* --- Shopee Grid Fixes --- */
            .main-content { padding: 0; /* Remove side padding on mobile main content */ }
            .top-controls { 
                margin-bottom: 8px; 
                padding: 0 8px; /* Add padding here instead of main-content */
            }
            .grid {
                /* MODIFIED: Two columns on mobile, smaller gap */
                grid-template-columns: 1fr 1fr; 
                gap: 8px; /* Tight grid gap */
                padding: 0 8px; /* Add padding to align with container content */
            }
            .card {
                /* MODIFIED: Tighter card padding and border radius */
                border-radius: 6px; 
            }
            .card-body {
                padding: 8px; 
                min-height: 100px; /* Ensure a minimum height for product info */
            }
            .card-title {
                font-size: 0.9rem; /* Smaller title */
                margin: 0 0 4px 0;
                /* Optional: Limit title to two lines for cleaner look */
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }
            .card-meta {
                font-size: 0.75rem; /* Smaller meta info */
            }
            .card-price {
                font-size: 1rem; /* Smaller price */
                margin-top: 6px; 
            }
            .btn-primary {
                /* MODIFIED: Smaller "Add to Cart" button, especially in the grid */
                padding: 6px 10px;
                font-size: 0.85rem;
                margin-top: 8px;
                border-radius: 6px;
            }
            .card-badge {
                /* Smaller badge on mobile */
                top: 6px; left: 6px;
                padding: 2px 6px;
                font-size: 0.7rem;
            }
            /* --- END Shopee Grid Fixes --- */
        }

        /* Shopee-style header layout */
        .shopee-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            min-height: 60px;
        }
        .header-left {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .shopee-search {
            display: flex;
            align-items: center;
            flex: 1 1 0%;
            background: #fff;
            border-radius: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            padding: 4px 12px 4px 12px;
            max-width: 600px;
            margin: 0 16px;
            border: 1.5px solid var(--border);
        }
        .shopee-search input {
            border: none;
            background: transparent;
            color: #222;
            font-size: 1rem;
            flex: 1;
            outline: none;
            padding: 8px;
            box-shadow: none;
        }
        .shopee-search input:focus {
            outline: none !important;
            box-shadow: none !important;
        }
        .shopee-search:focus-within {
            box-shadow: none !important;
            border-color: var(--border) !important;
        }
        .shopee-search .search-btn {
            background: none;
            border: none;
            padding: 4px 8px;
            display: flex;
            align-items: center;
            color: var(--accent);
            cursor: pointer;
        }
        .shopee-search .custom-select {
            margin-left: 8px;
        }
        .header-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        @media (max-width: 800px) {
            .shopee-header {
                flex-wrap: nowrap; /* Keep items on a single line */
                gap: 2px;
                min-height: 48px;
            }
            .shopee-search {
                margin: 0 2px;
                max-width: 100%;
                padding: 2px 4px;
            }
            .header-actions {
                gap: 2px;
            }
            .icon-btn {
                padding: 4px;
            }
        }

        /* Brand icon image for ginto.ai */
        .brand img {
            height: 32px;
            width: 32px;
            display: block;
            border-radius: 50%;
            background: #fff;
            object-fit: cover;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
        }
        @media (max-width: 800px) {
            .header-left {
                gap: 2px;
            }
            .brand img {
                height: 28px;
            }
        }

        /* Sort By mobile/desktop toggle (re-used from previous task) */
        .sortby-wrap {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            margin-left: 12px;
            flex-shrink: 0;
        }
        
        .sortby-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            width: 44px;
            height: 44px;
            padding: 0;
            background: var(--bg-surface);
            border: 1px solid var(--border);
            color: var(--text-main);
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            font-size: 1rem;
            flex-shrink: 0;
            transition: width 0.2s, padding 0.2s;
        }
        .sortby-btn .sortby-label, .sortby-btn .chev {
            display: none;
            opacity: 0;
            transition: opacity 0.2s;
        }
        .sortby-btn svg {
            margin: 0;
            vertical-align: middle;
            flex-shrink: 0;
        }
        
        .sortby-btn[aria-expanded="true"] {
            width: auto;
            padding: 8px 16px;
        }
        .sortby-btn[aria-expanded="true"] .sortby-label, .sortby-btn[aria-expanded="true"] .chev {
            display: inline;
            opacity: 1;
        }
        .sortby-btn[aria-expanded="true"] svg {
            margin-right: 6px;
        }

        .sortby-dropdown {
            position: absolute;
            top: 110%;
            right: 0;
            min-width: 160px;
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            box-shadow: 0 8px 24px rgba(2,6,23,0.12);
            z-index: 100;
            list-style: none;
            margin: 0;
            padding: 6px 0;
            display: none;
        }
        .sortby-dropdown[aria-expanded="true"], .sortby-dropdown.open {
            display: block;
            animation: fadeIn 0.18s;
        }
        .sortby-dropdown li {
            padding: 10px 16px;
            cursor: pointer;
            color: var(--text-main);
            border-radius: 6px;
            transition: background 0.15s;
        }
        .sortby-dropdown li[aria-selected="true"], .sortby-dropdown li:hover {
            background: var(--accent);
            color: var(--accent-text);
        }
        @media (max-width: 800px) {
            .sortby-wrap {
                width: auto;
                align-items: center;
                margin-left: 6px;
                margin-top: 0;
            }
            .sortby-btn {
                width: 36px;
                height: 36px;
            }
            .sortby-btn[aria-expanded="true"] {
                padding: 4px 12px;
            }
            .sortby-dropdown {
                right: 0;
                left: auto;
                min-width: 140px;
            }
        }

        .brand-text {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--accent);
            letter-spacing: -0.5px;
            margin-left: 8px;
            display: inline-block;
        }
        @media (max-width: 800px) {
            .brand-text {
                display: none;
            }
        }
        .brand-text {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--accent);
            letter-spacing: -0.5px;
            margin-left: 8px;
            display: inline-block;
        }
        .brand-lightning {
            display: inline-block;
            vertical-align: middle;
            margin-bottom: 2px;
        }
        @media (max-width: 800px) {
            .brand-text {
                display: none;
            }
        }
        .gold-cards-heading {
            font-size: 1.08rem;
            font-weight: 400;
            color: #fff !important;
            text-align: center;
            margin-bottom: 10px;
            letter-spacing: 0.01em;
        }
        body.light .gold-cards-heading {
            color: #111 !important;
        }
    </style>
</head>