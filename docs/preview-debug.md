Preview Debug — opt-in developer console
======================================

Overview
--------
The Preview Debug tool provides an on-screen, lightweight developer console that can be loaded into the admin editor preview workflow. It mimics a few capabilities of browser devtools: live console messages, a small JS evaluation input, message inspection, and click-to-highlight markers for coordinate-style logs.

Important: The panel is intentionally disabled by default and must be explicitly enabled by developers.

How to enable
-------------
1. URL param (temporary): Add ?preview_debug=1 to the editor page URL to load the debug UI for that page load.
   Example: /admin/edit-view?preview_debug=1

2. Local storage (persistent): In your browser console set the key:

   window.localStorage.setItem('ginto.preview.debug', '1')

   This will cause subsequent editor loads to load the debug UI automatically.

Where code lives
---------------
- public/assets/js/preview-debug.js — the standalone, maintainable JS implementation
- src/Views/admin/pages/edit_script.php — small loader snippet that only injects the script when enabled

Notes
-----
- The debug panel only appears while a preview overlay is open — enabling the feature doesn't show it until you open a preview.
- The panel supports same-origin evaluation inside the preview iframe; cross-origin previews fall back to admin-frame evaluation.
- Logs are kept in memory (up to ~1500 entries) and shown in the panel; logs are not persisted to the server.

If you want me to wire server-side debug events or SQL logs to this panel (with a backend endpoint), I can add a secure, opt-in route and client-side polling/posting next.
