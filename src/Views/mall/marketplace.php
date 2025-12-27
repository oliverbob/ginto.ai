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
            .mobile-toggle { display: block; font-size: 1.5rem; background: none; border: none; color: var(--text-main); }
            .sidebar {
                position: fixed; top: var(--header-height); left: 0; bottom: 0;
                width: 280px; background: var(--bg-surface); z-index: 50;
                padding: 20px; border-right: 1px solid var(--border);
                transform: translateX(-100%); transition: transform 0.3s;
            }
            .sidebar.open { transform: translateX(0); }
            .wrapper { 
                display: block; 
                margin-top: 10px; /* Reduced top margin */
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
<body>

    <!-- Header -->
    <header class="header">
        <div class="container header-inner shopee-header">
            <div class="header-left">
                <button class="mobile-toggle" aria-label="Toggle Menu" onclick="toggleSidebar()">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/></svg>
                </button>
                <a href="#" class="brand">
                    <img src="/assets/images/ginto.png" alt="ginto.ai" style="height:32px;width:auto;display:block;" />
                    <span class="brand-text">
                        <svg class="brand-lightning" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align:middle;margin-right:4px;"><path d="M11.5 1L3 11.5H9L8.5 19L17 8.5H11L11.5 1Z" fill="#facc15" stroke="#eab308" stroke-width="1.2" stroke-linejoin="round"/></svg>
                        ePower
                    </span>
                        
                </a>
                    
            </div>
            <form class="search-bar shopee-search" id="searchContainer" onsubmit="event.preventDefault();">
                <div class="input-group">
                    <input id="searchInput" type="text" placeholder="Search products..." aria-label="Search">
                </div>
                <button type="submit" class="search-btn" aria-label="Search">
                    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                </button>
            </form>
                        <div class="sortby-wrap">
                            <button id="sortByBtn" class="sortby-btn" aria-haspopup="listbox" aria-expanded="false" type="button">
                                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle"><path d="M3 18h6M3 6h18M3 12h12"/></svg>
                                <span class="sortby-label">Sort By</span>
                                <span class="chev">▾</span>
                            </button>
                            <ul id="sortByDropdown" class="sortby-dropdown" role="listbox" tabindex="-1" style="display:none">
                                <li role="option" data-value="default" aria-selected="true">Sort By</li>
                                <li role="option" data-value="price_asc">Price: Low to High</li>
                                <li role="option" data-value="price_desc">Price: High to Low</li>
                                <li role="option" data-value="rating">Highest Rated</li>
                            </ul>
                        </div>
            <div class="header-actions">
                <?php if (!empty($_SESSION['user_id'])): ?>
                <button class="icon-btn" id="sellBtn" aria-label="Sell an item" onclick="openUploadModal()" title="Sell an item">🏷️</button>
                <?php endif; ?>
                <button class="icon-btn" id="themeToggle" aria-label="Toggle Theme">
                    <span id="themeIcon">☀️</span>
                </button>
                <button class="icon-btn" onclick="toggleCart()" aria-label="Open Cart">
                    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 110-4 2 2 0 010 4z"/></svg>
                    <span class="badge-count" id="cartCount">0</span>
                </button>
            </div>
        </div>

    </header>

    <!-- Main Layout -->
    <div class="container wrapper">
        <!-- Sidebar Filters -->
        <aside class="sidebar" id="sidebar">
            <div class="filter-group">
                <div class="filter-title">Categories</div>
                <button class="cat-btn active" data-cat="all">All Products</button>
                <button class="cat-btn" data-cat="electronics">Electronics</button>
                <button class="cat-btn" data-cat="fashion">Fashion</button>
                <button class="cat-btn" data-cat="home">Home & Living</button>
                <button class="cat-btn" data-cat="sports">Sports</button>
            </div>

            <div class="filter-group">
                <div class="filter-title">Status</div>
                <label style="display:flex;gap:8px;margin-bottom:6px;cursor:pointer">
                    <input type="checkbox" id="filterSale"> On Sale
                </label>
                <label style="display:flex;gap:8px;cursor:pointer">
                    <input type="checkbox" id="filterShip"> Free Shipping
                </label>
            </div>
            <div class="gold-cards-heading" style="font-size:1.08rem;font-weight:400;color:#111;text-align:center;margin-bottom:10px;letter-spacing:0.01em;">Order your virtual card now!</div>
                    
            <div class="gold-cards-grid">
                <div class="gold-card" onclick="addToCart(101)">
                    <img class="gold-card-img" src="/assets/images/ginto2.png" alt="ePower Mall Card 1" />
                    <div class="gold-card-overlay"></div>
                    <div class="gold-card-label"><span class="card-num">1</span>ePower Starter</div>
                </div>
                <div class="gold-card" onclick="addToCart(102)">
                    <img class="gold-card-img" src="/assets/images/ginto3.png" alt="ePower Mall Card 2" />
                    <div class="gold-card-overlay"></div>
                    <div class="gold-card-label"><span class="card-num">2</span>ePower Gold</div>
                </div>
                <div class="gold-card" onclick="addToCart(103)" style="grid-column:1/3;">
                    <img class="gold-card-img" src="/assets/images/ginto4.png" alt="ePower Mall Card 3" />
                    <div class="gold-card-overlay"></div>
                    <div class="gold-card-label"><span class="card-num">3</span>ePower Premium</div>
                </div>
            </div>
        </aside>

        <!-- Product Grid -->
        <main class="main-content">
            <div class="top-controls">
                <div class="text-muted">Showing <strong id="resultCount">0</strong> results</div>
            </div>
            <div id="productGrid" class="grid">
                <!-- Products Injected Here -->
            </div>
        </main>
    </div>

        <!-- Upload Modal (for sellers) -->
        <div id="uploadBackdrop" class="modal-backdrop" aria-hidden="true">
            <div class="modal" id="uploadModal" role="dialog" aria-modal="true" style="max-width:720px;grid-template-columns:1fr;">
                <div style="padding:22px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                        <h2 style="margin:0;font-size:1.25rem">Sell an Item</h2>
                        <button class="btn-secondary" onclick="closeUploadModal()" style="padding:6px 10px">✕</button>
                    </div>
                    <form id="uploadForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <div style="display:flex;gap:12px;flex-direction:column">
                            <label>Title <input name="title" required placeholder="Product title" style="width:100%;padding:8px;border-radius:8px;border:1px solid var(--border)"></label>
                            <label>Price <input name="price" type="number" step="0.01" required placeholder="9.99" style="width:200px;padding:8px;border-radius:8px;border:1px solid var(--border)"></label>
                            <label>Category <input name="category" placeholder="fashion" style="width:200px;padding:8px;border-radius:8px;border:1px solid var(--border)"></label>
                            <label>Description <textarea name="description" rows="4" placeholder="Short description" style="width:100%;padding:8px;border-radius:8px;border:1px solid var(--border)"></textarea></label>
                            <label>Image <input type="file" name="image" accept="image/*" id="uploadImageInput"></label>
                            <div style="display:flex;gap:8px;align-items:center">
                                <button class="btn btn-primary" type="submit">Upload</button>
                                <button type="button" class="btn btn-secondary" onclick="closeUploadModal()">Cancel</button>
                                <div id="uploadPreview" style="margin-left:10px"></div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Quick View Modal -->
    <div id="qvBackdrop" class="modal-backdrop">
        <div class="modal" id="qvModal" role="dialog" aria-modal="true">
            <img id="qvImg" src="" alt="Product" class="modal-img">
            <div class="modal-content">
                <div style="margin-bottom: auto;">
                    <div style="display:flex;justify-content:space-between;align-items:start">
                        <h2 id="qvTitle" style="margin:0;font-size:1.5rem;">Product Title</h2>
                        <button class="btn-secondary" onclick="closeModal()" style="padding:4px 8px;">✕</button>
                    </div>
                    <div id="qvRating" style="color:#f59e0b; margin:8px 0;">★★★★☆</div>
                    <div id="qvPrice" style="font-size:1.5rem; font-weight:700; margin:12px 0; color:var(--accent);"></div>
                    <p id="qvDesc" style="color:var(--text-muted); line-height:1.6;"></p>
                </div>
                <button id="qvAddBtn" class="btn btn-primary">Add to Cart</button>
            </div>
        </div>
    </div>

    <!-- Cart Drawer -->
    <div id="cartDrawerContainer">
        <div class="drawer-backdrop" onclick="toggleCart()"></div>
        <div class="drawer">
            <div class="drawer-header">
                <h3 style="margin:0">Your Cart</h3>
                <button class="btn-secondary" onclick="toggleCart()" style="padding:6px 10px">✕</button>
            </div>
            <div class="drawer-body" id="cartItems">
                <!-- Cart Items -->
            </div>
            <div class="drawer-footer">
                <div style="display:flex;justify-content:space-between;margin-bottom:16px;font-weight:700;font-size:1.1rem">
                    <span>Total</span>
                    <span id="cartTotal">$0.00</span>
                </div>
                <button class="btn btn-primary" onclick="checkout()">Checkout</button>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <script src="/assets/js/dom.js"></script>
    <script>
        // --- Sort By dropdown below search bar ---
        (function(){
            const btn = document.getElementById('sortByBtn');
            const dropdown = document.getElementById('sortByDropdown');
            let expanded = false;
            function openDropdown() {
                // MODIFIED: Use setAttribute to enable the expanded button style via CSS
                btn.setAttribute('aria-expanded', 'true');
                dropdown.classList.add('open');
                dropdown.style.display = 'block';
                expanded = true;
            }
            function closeDropdown() {
                // MODIFIED: Use setAttribute to enable the collapsed button style via CSS
                btn.setAttribute('aria-expanded', 'false');
                dropdown.classList.remove('open');
                // Use a slight delay to allow CSS animation to finish before hiding
                setTimeout(() => {
                    if(!expanded) dropdown.style.display = 'none';
                }, 200);
                expanded = false;
            }
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                if (expanded) closeDropdown(); else openDropdown();
            });
            document.addEventListener('click', function(e) {
                if (!btn.contains(e.target) && !dropdown.contains(e.target)) closeDropdown();
            });
            dropdown.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') closeDropdown();
            });
            // Option click
            dropdown.querySelectorAll('li').forEach(li => {
                li.addEventListener('click', function() {
                    dropdown.querySelectorAll('li').forEach(i => i.setAttribute('aria-selected','false'));
                    li.setAttribute('aria-selected','true');
                    // Find the selected option text for the default display in the collapsed state
                    const selectedText = li.textContent;
                    // Find the "Sort By" label element in the button
                    const sortbyLabel = btn.querySelector('.sortby-label');
                    
                    // Update the label text
                    if(sortbyLabel) {
                        // Keep 'Sort By' if default is selected, otherwise use the selected value
                        sortbyLabel.textContent = li.dataset.value === 'default' ? 'Sort By' : selectedText;
                    }

                    closeDropdown();
                    // update state and re-render
                    state.filters.sort = li.dataset.value || 'default';
                    renderProducts();
                });
            });
        })();
        /* --- Data --- */
        const PRODUCTS = [
                        // Gold Cards (IDs: 101, 102, 103)
                        { id: 101, title: 'ePower Mall Card 1', price: 100.00, cat: 'goldcard', rating: 5.0, img: '/assets/images/gold_card1.png', badge: 'Gold', desc: 'Exclusive ePower Mall Card 1. Unlock premium benefits.' },
                        { id: 102, title: 'ePower Mall Card 2', price: 200.00, cat: 'goldcard', rating: 5.0, img: '/assets/images/gold_card2.png', badge: 'Gold', desc: 'Exclusive ePower Mall Card 2. Unlock more rewards.' },
                        { id: 103, title: 'ePower Mall Card 3', price: 300.00, cat: 'goldcard', rating: 5.0, img: '/assets/images/gold_card3.png', badge: 'Gold', desc: 'Top-tier ePower Mall Card 3. Maximum privileges.' },
            { id: 1, title: 'Noise-Cancel Headphones', price: 249.00, cat: 'electronics', rating: 4.8, img: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=500&q=80', badge: 'Best Seller', desc: 'Immersive sound with industry-leading noise cancellation.' },
            { id: 2, title: 'Smart Fitness Watch', price: 129.99, cat: 'electronics', rating: 4.5, img: 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=500&q=80', badge: 'New', desc: 'Track your workouts, heart rate, and sleep quality.' },
            { id: 3, title: 'Premium Denim Jacket', price: 89.50, cat: 'fashion', rating: 4.3, img: 'https://images.unsplash.com/photo-1523381210434-271e8be1f52b?w=500&q=80', badge: '', desc: 'Classic vintage style denim jacket for all seasons.' },
            { id: 4, title: 'Modern Ceramic Vase', price: 34.00, cat: 'home', rating: 4.7, img: 'https://images.unsplash.com/photo-1589365278144-bd407351c093?w=500&q=80', badge: 'Sale', desc: 'Minimalist design perfect for contemporary homes.' },
            { id: 5, title: 'Yoga Mat & Strap', price: 45.00, cat: 'sports', rating: 4.6, img: 'https://images.unsplash.com/photo-1601925260368-ae2f83cf8b7f?w=500&q=80', badge: 'Eco', desc: 'Non-slip organic rubber mat with carrying strap.' },
            { id: 6, title: 'Leather Weekend Bag', price: 195.00, cat: 'fashion', rating: 4.9, img: 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=500&q=80', badge: '', desc: 'Handcrafted leather bag for short trips and getaways.' }
        ];

        // Fallback placeholder file (local SVG) used when an image fails to load
        // Saved at: /assets/images/placeholder_ceramic.svg
        const PLACEHOLDER_SVG = '/assets/images/placeholder_ceramic.svg';

        /* --- State --- */
        let state = {
            products: [...PRODUCTS],
            cart: JSON.parse(localStorage.getItem('epower_cart') || '[]'),
            filters: { cat: 'all', search: '', sort: 'default' }
        };

        /* --- DOM Elements --- */
        const grid = document.getElementById('productGrid');
        const resultCount = document.getElementById('resultCount');
        const cartCount = document.getElementById('cartCount');
        
        /* --- Initialization --- */
        async function loadServerProducts() {
            try {
                const resp = await fetch('/api/mall/products');
                const json = await resp.json();
                if (json && json.success && Array.isArray(json.products)) {
                    // prepend server products so user-uploaded items appear first
                    state.products = [...json.products, ...state.products];
                }
            } catch (e) {
                // ignore failures; this is an enhancement
                console.warn('Failed to load server products', e);
            }
        }

        async function init() {
            await loadServerProducts();
            renderProducts();
            updateCartUI();
            applyTheme(localStorage.getItem('epower_theme') || 'dark');
        }

        /* --- Rendering --- */
        function renderProducts() {
            let filtered = state.products.filter(p => {
                const matchCat = state.filters.cat === 'all' || p.cat === state.filters.cat;
                const matchSearch = p.title.toLowerCase().includes(state.filters.search.toLowerCase());
                return matchCat && matchSearch;
            });

            // Sorting
            if(state.filters.sort === 'price_asc') filtered.sort((a,b) => a.price - b.price);
            if(state.filters.sort === 'price_desc') filtered.sort((a,b) => b.price - a.price);
            if(state.filters.sort === 'rating') filtered.sort((a,b) => b.rating - a.rating);

            resultCount.textContent = filtered.length;
            grid.innerHTML = '';

            if (filtered.length === 0) {
                grid.innerHTML = `<div class="empty-state" style="grid-column:1/-1">No products found matching your criteria.</div>`;
                return;
            }

                filtered.forEach(p => {
                const div = document.createElement('div');
                div.className = 'card';
                div.innerHTML = `
                    <div class="card-img-wrap" onclick="openModal(${p.id})" style="cursor:pointer">
                        <img src="${p.img}" class="card-img" alt="${p.title}" loading="lazy" onerror="this.onerror=null;this.src=PLACEHOLDER_SVG;this.classList.add('img-broken');">
                        ${p.badge ? `<span class="card-badge">${p.badge}</span>` : ''}
                    </div>
                    <div class="card-body">
                        <h3 class="card-title">${p.title}</h3>
                        <div class="card-meta">
                            <span>${p.cat}</span>
                            <span style="color:#f59e0b">★ ${p.rating}</span>
                        </div>
                        <div class="card-price">${p.formatted_price || ('$' + (p.price || 0).toFixed(2))}</div>
                        <button class="btn btn-primary" onclick="addToCart(${p.id})">Add to Cart</button>
                    </div>
                `;
                grid.appendChild(div);
            });
        }

        /* --- Logic: Cart --- */
        function addToCart(id) {
            const product = PRODUCTS.find(p => p.id === id);
            const existing = state.cart.find(item => item.id === id);
            
            if (existing) {
                existing.qty++;
                showToast(`Increased quantity: ${product.title}`);
            } else {
                state.cart.push({ ...product, qty: 1 });
                showToast(`Added to cart: ${product.title}`);
            }
            saveCart();
        }

        function updateCartQty(id, delta) {
            const item = state.cart.find(i => i.id === id);
            if(!item) return;
            item.qty += delta;
            if(item.qty <= 0) state.cart = state.cart.filter(i => i.id !== id);
            saveCart();
        }

        function saveCart() {
            localStorage.setItem('epower_cart', JSON.stringify(state.cart));
            updateCartUI();
        }

        function updateCartUI() {
            const totalQty = state.cart.reduce((acc, item) => acc + item.qty, 0);
            const totalPrice = state.cart.reduce((acc, item) => acc + (item.price * item.qty), 0);
            
            cartCount.textContent = totalQty;
            cartCount.style.display = totalQty > 0 ? 'block' : 'none';
            document.getElementById('cartTotal').textContent = '$' + totalPrice.toFixed(2);

            const cartList = document.getElementById('cartItems');
            cartList.innerHTML = '';
            
            if(state.cart.length === 0) {
                cartList.innerHTML = '<div class="text-center text-muted" style="margin-top:40px">Your cart is empty.</div>';
                return;
            }

            state.cart.forEach(item => {
                cartList.innerHTML += `
                    <div class="cart-item">
                        <img src="${item.img}" alt="${item.title}">
                        <div class="cart-item-details">
                            <div style="font-weight:600;font-size:0.9rem">${item.title}</div>
                            <div style="font-size:0.85rem;color:var(--text-muted)">$${item.price}</div>
                            <div class="cart-controls">
                                <button class="qty-btn" onclick="updateCartQty(${item.id}, -1)">-</button>
                                <span>${item.qty}</span>
                                <button class="qty-btn" onclick="updateCartQty(${item.id}, 1)">+</button>
                            </div>
                        </div>
                        <div style="font-weight:600">$${(item.price * item.qty).toFixed(2)}</div>
                    </div>
                `;
            });
        }

        function toggleCart() {
            document.getElementById('cartDrawerContainer').classList.toggle('drawer-open');
        }

        function checkout() {
            if(state.cart.length === 0) return;
            alert('Proceeding to checkout demo...');
        }

        /* --- Logic: Modal --- */
        function openModal(id) {
            const p = PRODUCTS.find(x => x.id === id);
            document.getElementById('qvImg').src = p.img;
            document.getElementById('qvTitle').textContent = p.title;
            document.getElementById('qvRating').textContent = '★'.repeat(Math.floor(p.rating)) + '☆'.repeat(5 - Math.floor(p.rating));
            document.getElementById('qvPrice').textContent = p.formatted_price || ('$' + (p.price || 0).toFixed(2));
            document.getElementById('qvDesc').textContent = p.desc;
            document.getElementById('qvAddBtn').onclick = () => { addToCart(id); closeModal(); };
            
            document.getElementById('qvBackdrop').classList.add('open');
        }
        function closeModal() {
            document.getElementById('qvBackdrop').classList.remove('open');
        }


        /* --- Logic: Filters & Listeners --- */
        document.querySelectorAll('.cat-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                document.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('active'));
                e.target.classList.add('active');
                state.filters.cat = e.target.dataset.cat;
                renderProducts();
                if(window.innerWidth < 800) toggleSidebar(); // close sidebar on mobile selection
            });
        });

        document.getElementById('searchInput').addEventListener('input', (e) => {
            state.filters.search = e.target.value;
            renderProducts();
        });

        /* --- Logic: UI Utilities --- */
        function showToast(msg) {
            const toast = document.createElement('div');
            toast.className = 'toast';
            toast.textContent = msg;
            document.getElementById('toastContainer').appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }

        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
        }

        /* --- Theme Logic --- */
        const themeToggle = document.getElementById('themeToggle');
        themeToggle.addEventListener('click', () => {
            const isLight = document.body.classList.contains('light');
            applyTheme(isLight ? 'dark' : 'light');
        });

        function applyTheme(theme) {
            if(theme === 'light') {
                document.body.classList.add('light');
                document.getElementById('themeIcon').textContent = '🌙';
            } else {
                document.body.classList.remove('light');
                document.getElementById('themeIcon').textContent = '☀️';
            }
            localStorage.setItem('epower_theme', theme);
        }

        // Close modal on Esc
        document.addEventListener('keydown', (e) => {
            if(e.key === 'Escape') { closeModal(); document.getElementById('cartDrawerContainer').classList.remove('drawer-open'); }
        });

        // Start
        init();
    </script>
        <script>
            // Upload modal logic
            function openUploadModal() {
                const el = document.getElementById('uploadBackdrop');
                if (el) el.classList.add('open');
            }
            function closeUploadModal() {
                const el = document.getElementById('uploadBackdrop');
                if (el) el.classList.remove('open');
                const form = document.getElementById('uploadForm');
                if (form) form.reset();
                const prev = document.getElementById('uploadPreview'); if(prev) prev.innerHTML = '';
            }

            // Image preview
            const imgInput = document.getElementById('uploadImageInput');
            if (imgInput) {
                imgInput.addEventListener('change', (e) => {
                    const file = e.target.files[0];
                    const prev = document.getElementById('uploadPreview');
                    if (!file) { if(prev) prev.innerHTML = ''; return; }
                    const reader = new FileReader();
                    reader.onload = function(evt) { if(prev) prev.innerHTML = `<img src="${evt.target.result}" style="height:48px;border-radius:6px;object-fit:cover">`; };
                    reader.readAsDataURL(file);
                });
            }

            // Handle upload submit
            const uploadForm = document.getElementById('uploadForm');
            if (uploadForm) {
                uploadForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    const btn = this.querySelector('button[type=submit]');
                    if (btn) btn.disabled = true;
                    const fd = new FormData(this);
                    try {
                        const res = await fetch('/mall/upload', { method: 'POST', body: fd });
                        const json = await res.json();
                        if (!json || !json.success) {
                            showToast(json.message || 'Upload failed');
                            if (btn) btn.disabled = false;
                            return;
                        }
                        // Refresh server products so we include server-side formatted prices
                        try {
                            const r = await fetch('/api/mall/products');
                            const d = await r.json();
                            if (d && d.success && Array.isArray(d.products)) {
                                // merge server products with existing client PRODUCTS (server first)
                                state.products = [...d.products, ...state.products.filter(p => !d.products.find(sp => sp.id === p.id))];
                            } else {
                                // fallback: add returned product
                                state.products.unshift(json.product);
                            }
                            renderProducts();
                        } catch (e) {
                            state.products.unshift(json.product);
                            renderProducts();
                        }
                        showToast('Product uploaded');
                        closeUploadModal();
                    } catch (err) {
                        console.error('Upload error', err);
                        showToast('Upload failed');
                    } finally {
                        if (btn) btn.disabled = false;
                    }
                });
            }
        </script>
</body>
</html>