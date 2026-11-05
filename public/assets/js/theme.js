if (typeof window.themeManager === 'undefined') {
    // Theme Management
    window.themeManager = {
        updateButtonUI(isDark) {
                // Several places in the app use slightly different IDs or markup for the
                // toggle UI. Update all known variants so the visible toggle stays in-sync.
                const textIds = ['themeText', 'theme-toggle-text'];
                for (const id of textIds) {
                    const el = document.getElementById(id);
                    // Show the *action* the button will perform (if we're currently dark,
                    // show 'Light' because clicking will switch to light mode).
                    if (el) el.textContent = isDark ? 'Light' : 'Dark';
                }
                
                // Icon elements — some templates use a span with inline svg, others use
                // a simple <i> with classes. Replace the inner SVG or the class where present.
                const iconMap = {
                    sun: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>',
                    moon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>'
                };

                // Preferred icon ids found in templates
                const iconIds = ['themeIcon', 'theme-toggle-icon'];
                for (const id of iconIds) {
                    const el = document.getElementById(id);
                    if (!el) continue;
                    // If it's an <i> tag or similar class-based icon, set className
                    if (el.tagName.toLowerCase() === 'i' || el.tagName.toLowerCase() === 'span' && el.classList.contains('fa')) {
                        el.className = isDark ? 'fas fa-sun' : 'fas fa-moon';
                    } else {
                        // Replace innerHTML with inline SVG
                        el.innerHTML = isDark ? iconMap.sun : iconMap.moon;
                    }
                }

                // Mark toggle buttons accessible (pressed state) when present.
                const toggleButtons = document.querySelectorAll('#theme-toggle, #themeToggle, .theme-toggle');
                toggleButtons.forEach(btn => {
                    try { btn.setAttribute('aria-pressed', !!isDark); } catch (_) {}
                    try { btn.dataset.theme = isDark ? 'dark' : 'light'; } catch (_) {}
                });
            },
        // Apply theme in a single, centralized place. This mirrors the
        // 'dark' class onto both document.documentElement and document.body
        // (some templates toggle body, others toggle root) and ensures UI
        // elements and storage are kept in-sync. Returns the applied theme
        // string ('dark'|'light').
        applyTheme(isDark) {
            try {
                // Mirror to both places for compatibility (some templates expect the
                // class on <html>, others on <body>). Keep both in-sync.
                if (isDark) {
                    document.documentElement.classList.add('dark');
                    try { document.body.classList.add('dark'); } catch (_) {}
                } else {
                    document.documentElement.classList.remove('dark');
                    try { document.body.classList.remove('dark'); } catch (_) {}
                }
            } catch (_) { /* ignore DOM issues in exotic embed contexts */ }

                try { localStorage.setItem('theme', isDark ? 'dark' : 'light'); } catch (_) {}
                // Also mirror theme to a cookie so server-side templates that
                // read $_COOKIE['theme'] can render consistently on first load.
                try {
                    var cookieVal = isDark ? 'dark' : 'light';
                    // set cookie for 1 year
                    document.cookie = 'theme=' + cookieVal + '; path=/; max-age=' + (60 * 60 * 24 * 365);
                } catch (_) {}
            try { this.updateButtonUI(isDark); } catch (_) {}
            try { window.dispatchEvent(new CustomEvent('site-theme-changed', { detail: { theme: isDark ? 'dark' : 'light' } })); } catch (e) {}
            return isDark ? 'dark' : 'light';
        },

        // Toggle the site theme (convenience wrapper)
        toggle() {
            const isNowDark = document.documentElement.classList.contains('dark');
            return this.applyTheme(!isNowDark);
        },

        init() {
            // If the current page was opened as a preview (admin editor iframe)
            // we'll detect a 'preview=light' query parameter and force a light
            // rendering here — but do not touch localStorage or cookies so the
            // preview remains isolated from the parent admin UI.
            try {
                const params = new URLSearchParams(window.location.search);
                const previewParam = params.get('preview') || params.get('preview_mode') || params.get('preview_light') || params.get('__preview');
                if (previewParam === 'light' || previewParam === '1') {
                    // Force a light-only mode inside this frame and bail out of
                    // initialization so the page doesn't react to storage/mutation changes
                    try { document.documentElement.classList.remove('dark'); } catch (_) {}
                    try { document.body.classList.remove('dark'); } catch (_) {}
                    // update any theme UI but do NOT persist to storage/cookies
                    try { this.updateButtonUI(false); } catch (_) {}
                    try { window.dispatchEvent(new CustomEvent('site-theme-changed', { detail: { theme: 'light' } })); } catch (_) {}

                    // PREVIEW MODE: prevent theme changes in this iframe from
                    // persisting to localStorage or cookies (which would affect the
                    // parent through storage events) and hide toggle UI so the
                    // preview stays isolated.
                    try {
                        // Block writes to the 'theme' key in localStorage inside preview.
                        if (window.localStorage && typeof window.localStorage.setItem === 'function') {
                            const origSet = window.localStorage.setItem.bind(window.localStorage);
                            window.localStorage.setItem = function(k, v) {
                                if (String(k) === 'theme') return; // ignore theme persistence
                                return origSet(k, v);
                            };
                        }
                    } catch (_) {}

                    try {
                        // Block attempts to write theme cookie inside preview. We wrap
                        // the document.cookie setter so writes that contain 'theme='
                        // are ignored.
                        const docProto = Document.prototype || HTMLDocument.prototype || null;
                        if (docProto) {
                            const desc = Object.getOwnPropertyDescriptor(docProto, 'cookie') || Object.getOwnPropertyDescriptor(Document.prototype, 'cookie');
                            if (desc && desc.set) {
                                const origCookieSet = desc.set.bind(document);
                                Object.defineProperty(document, 'cookie', {
                                    configurable: true,
                                    enumerable: true,
                                    get: desc.get ? desc.get.bind(document) : function(){ return '' },
                                    set: function(v) {
                                        try {
                                            if (String(v).indexOf('theme=') !== -1) return; // ignore theme cookie writes
                                        } catch (e) {}
                                        return origCookieSet(v);
                                    }
                                });
                            }
                        }
                    } catch (_) {}

                    try {
                        // Hide any visible theme toggles so users can't change theme within preview UI.
                        const toggleSelectors = ['#theme-toggle', '#themeToggle', '#themeToggleNav', '.theme-toggle', '#theme-toggle-nav'];
                        toggleSelectors.forEach(sel => {
                            try { document.querySelectorAll(sel).forEach(el => { el.style.display = 'none'; el.disabled = true; }); } catch (_) {}
                        });
                    } catch (_) {}

                    return;
                } else if (previewParam === 'dark') {
                    // Force dark preview mode inside this frame while remaining isolated
                    try { document.documentElement.classList.add('dark'); } catch (_) {}
                    try { document.body.classList.add('dark'); } catch (_) {}
                    try { this.updateButtonUI(true); } catch (_) {}
                    try { window.dispatchEvent(new CustomEvent('site-theme-changed', { detail: { theme: 'dark' } })); } catch (_) {}

                    // Block persistence (same as light preview) to keep parent unaffected
                    try {
                        if (window.localStorage && typeof window.localStorage.setItem === 'function') {
                            const origSet = window.localStorage.setItem.bind(window.localStorage);
                            window.localStorage.setItem = function(k, v) {
                                if (String(k) === 'theme') return; // ignore theme persistence
                                return origSet(k, v);
                            };
                        }
                    } catch (_) {}

                    try {
                        const docProto = Document.prototype || HTMLDocument.prototype || null;
                        if (docProto) {
                            const desc = Object.getOwnPropertyDescriptor(docProto, 'cookie') || Object.getOwnPropertyDescriptor(Document.prototype, 'cookie');
                            if (desc && desc.set) {
                                const origCookieSet = desc.set.bind(document);
                                Object.defineProperty(document, 'cookie', {
                                    configurable: true,
                                    enumerable: true,
                                    get: desc.get ? desc.get.bind(document) : function(){ return '' },
                                    set: function(v) {
                                        try {
                                            if (String(v).indexOf('theme=') !== -1) return; // ignore theme cookie writes
                                        } catch (e) {}
                                        return origCookieSet(v);
                                    }
                                });
                            }
                        }
                    } catch (_) {}

                    try {
                        const toggleSelectors = ['#theme-toggle', '#themeToggle', '#themeToggleNav', '.theme-toggle', '#theme-toggle-nav'];
                        toggleSelectors.forEach(sel => {
                            try { document.querySelectorAll(sel).forEach(el => { el.style.display = 'none'; el.disabled = true; }); } catch (_) {}
                        });
                    } catch (_) {}

                    return;
                }
            } catch (_) {}

            let savedTheme = localStorage.getItem('theme');
            // Consider three inputs: explicit savedTheme, existing document class (set elsewhere), or prefers-color-scheme
            const prefersDark = (!savedTheme && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
            let isDark = (savedTheme === 'dark') || document.documentElement.classList.contains('dark') || prefersDark;

            if (isDark) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
                // Ensure we have an explicit saved preference (default to light if nothing saved)
                if (!savedTheme) try { localStorage.setItem('theme', 'light'); } catch (_) {}
            }
            this.updateButtonUI(isDark);
            // announce initial theme so other components (rich editors, overlays) can react
            try { window.dispatchEvent(new CustomEvent('site-theme-changed', { detail: { theme: isDark ? 'dark' : 'light' } })); } catch (e) {}

            // Set up theme toggle listeners — accept several ID variants and class-based toggles.
            // Some templates attach inline `onclick="toggleTheme()"` handlers
            // (or define a global toggleTheme function) — if a toggle element has
            // an inline onclick calling the global toggleTheme we SHOULD NOT add
            // an additional click listener here (otherwise a click will run both
            // the inline handler AND this handler causing a double-toggle).
            const themeToggles = document.querySelectorAll('#themeToggleNav, #themeToggle, #theme-toggle, #theme-toggle-nav, .theme-toggle');
            themeToggles.forEach(toggle => {
                // If an element already has an explicit inline onclick attribute
                // that calls toggleTheme, let that handler run (we'll wrap the
                // global toggleTheme to keep state/UI consistent). Avoid attaching
                // our own click listener in that case so the effect is not applied
                // twice.
                try {
                    const onclickAttr = (toggle.getAttribute && toggle.getAttribute('onclick')) || '';
                    if (onclickAttr && onclickAttr.indexOf('toggleTheme') !== -1) {
                        // mark that this element's toggle is handled by the
                        // inline onclick/wrapped toggleTheme and skip adding an
                        // extra listener here
                        toggle.dataset.themeManager = 'skipped-inline';
                        return;
                    }
                } catch (_) { /* ignore reading attributes on exotic nodes */ }

                // Otherwise attach our unified toggle implementation
                toggle.addEventListener('click', (ev) => {
                    ev && ev.preventDefault && ev.preventDefault();
                    this.toggle();
                });
            });

            // Keep everything in sync when theme changes happen elsewhere.
            // 1) Listen for cross-tab localStorage updates
            window.addEventListener('storage', (ev) => {
                try {
                    // Ignore storage events originating from preview iframes.
                    // The storage event includes the URL of the document which made the change
                    // (ev.url). When that URL contains a preview flag we should not react
                    // in the parent because preview frames intentionally isolate theme changes.
                    try {
                        const originUrl = ev && ev.url ? String(ev.url) : '';
                        if (originUrl.indexOf('preview=') !== -1 || originUrl.indexOf('preview_light') !== -1 || originUrl.indexOf('__preview') !== -1) {
                            return; // ignore preview-sourced storage events
                        }
                    } catch (_) {}

                    if (ev.key === 'theme') {
                        const isDarkEvent = ev.newValue === 'dark';
                        this.applyTheme(isDarkEvent);
                    }
                } catch (_) {}
            });

            // 2) Allow other components to broadcast theme changes via a custom event
            window.addEventListener('site-theme-changed', (e) => {
                try {
                    const theme = (e && e.detail && e.detail.theme) ? e.detail.theme : null;
                    if (theme === 'dark' || theme === 'light') {
                        // Avoid re-applying the same theme which would cause a dispatch -> listener loop.
                        // Only call applyTheme if the DOM state actually differs from the requested theme.
                        const isDarkNow = document.documentElement.classList.contains('dark');
                        const shouldBeDark = (theme === 'dark');
                        if (shouldBeDark !== isDarkNow) {
                            this.applyTheme(shouldBeDark);
                        } else {
                            // nothing to do, but keep UI in sync
                            try { this.updateButtonUI(shouldBeDark); } catch (_) {}
                        }
                    } else {
                        // Fallback: reflect current DOM state into our UI
                        const isDarkNow = document.documentElement.classList.contains('dark');
                        this.updateButtonUI(isDarkNow);
                    }
                } catch (_) {}
            });

            // 3) As a final safety net, watch for DOM class changes on root
            //    (handles any stray code that toggles the class directly)
            try {
                const observeTarget = document.documentElement;
                const mo = new MutationObserver(() => {
                    const isDarkNow = document.documentElement.classList.contains('dark');
                    // If we detect a mismatch with localStorage, update storage/UI
                    const currentSaved = localStorage.getItem('theme');
                    const expected = isDarkNow ? 'dark' : 'light';
                    if (currentSaved !== expected) try { localStorage.setItem('theme', expected); } catch (_) {}
                    this.updateButtonUI(isDarkNow);
                });
                mo.observe(observeTarget, { attributes: true, attributeFilter: ['class'] });
            } catch (_) {}

                        // Wrap any existing global toggleTheme() implementation so it also
                        // notifies the centralized theme manager. This helps templates that
                        // define their own toggleTheme function (inline) to still work with
                        // the new eventing model and keep UI elements in sync.
                        try {
                            if (typeof window.toggleTheme === 'function' && !window.toggleTheme._wrappedByThemeManager) {
                                const orig = window.toggleTheme;
                                window.toggleTheme = function () {
                                    // Run original behavior (some pages have custom applyTheme implementations)
                                    const before = document.documentElement.classList.contains('dark');
                                    const res = orig.apply(this, arguments);
                                    const after = document.documentElement.classList.contains('dark');
                                    // If the original implementation actually toggled the DOM class,
                                    // update localStorage / UI / broadcasts to keep everything in sync.
                                    if (after !== before) {
                                        try { localStorage.setItem('theme', after ? 'dark' : 'light'); } catch (_) {}
                                        try { window.themeManager.updateButtonUI(after); } catch (_) {}
                                        try { window.dispatchEvent(new CustomEvent('site-theme-changed', { detail: { theme: after ? 'dark' : 'light' } })); } catch (_) {}
                                    } else {
                                        // Otherwise, fall back to the central toggle method so behavior is unified
                                        try { window.themeManager.toggle(); } catch (err) { /* swallow */ }
                                    }
                                    return res;
                                };
                                window.toggleTheme._wrappedByThemeManager = true;
                            }
                        } catch (_) {}
        }
    };

    // Initialize theme management when the DOM is ready
            document.addEventListener('DOMContentLoaded', () => {
        window.themeManager.init();
    });
}