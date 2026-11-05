// Minimal Monaco initializer (fresh implementation)
// - Looks for #editor-canvas and #editor-content (fallback textarea)
// - Attempts to load a local vendor loader first, then falls back to CDN
// - If Monaco loads, creates an editable PHP editor and hides the textarea
// - On form submit copies editor contents back to the textarea

(function () {
  function ready(cb) { if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', cb); else cb(); }

  ready(function () {
    const container = document.getElementById('editor-canvas');
    if (!container) return;

    const textarea = document.getElementById('editor-content');
    const form = container.closest('form') || document.querySelector('form');

    const initialText = (textarea && textarea.value) ? textarea.value : '';

    const vendorLoader = '/assets/vendor/monaco-editor/min/vs/loader.js';

    function insertScript(src, onload, onerror) {
      const s = document.createElement('script');
      s.src = src; s.async = true;
      s.onload = onload; s.onerror = onerror;
      document.head.appendChild(s);
    }

    // UI status helper: we display a small message in the editor-canvas so the
    // user sees progress (or errors) when the loader is attempted.
    function setStatus(msg, isError) {
      try {
        const statusEl = container.querySelector('.editor-status');
        if (statusEl) {
          statusEl.textContent = msg;
          statusEl.style.color = isError ? '#f87171' : '#d1d5db';
        }
        console.debug('Editor status:', msg);
      } catch (_) {}
    }

    function configureAndCreate(loaderUrl) {
      setStatus('Configuring editor…');
      try {
        const base = (loaderUrl || '').replace(/\/loader\.js$/, '');
        if (typeof window.require === 'function') {
          try { window.require.config({ paths: { vs: base } }); } catch (_) {}
        }

        // helper to map site theme
        function siteToMonacoTheme() { return document.documentElement.classList.contains('dark') ? 'vs-dark' : 'vs'; }

        // require editor (if the AMD loader is available)
        if (typeof window.require === 'function') {
          // Wait for require to be usable and then try to load the editor
          setStatus('Waiting for loader...', false);
          const start = Date.now();
          let polls = 0;
          const wait = function () {
            polls++;
            if (typeof window.require === 'function') {
              // try to load editor.main now using AMD
              setStatus('Loading editor modules…', false);
              return window.require(['vs/editor/editor.main'], function () {
                try { createEditor(siteToMonacoTheme()); } catch (e) { console.error('editor init failed', e); setStatus('Editor init failed', true); }
              }, function (err) {
                console.error('require failed for editor.main', err);
                setStatus('Failed to load editor modules via AMD — trying direct bundle', true);
                // fallback to injecting the editor bundle directly
                insertScript(base + '/editor/editor.main.js', function () {
                  setStatus('Editor bundle loaded — creating editor', false);
                  if (window.monaco) createEditor(siteToMonacoTheme());
                }, function () {
                  console.warn('direct editor.main injection failed');
                  setStatus('Editor bundle not available', true);
                });
              });
            }
            if (Date.now() - start < 5000) return setTimeout(wait, 80);
            // timed out waiting for require — try direct bundle path
            console.warn('Timed out waiting for AMD require — trying direct editor.main injection');
            setStatus('Loader present but not responding — trying direct bundle', true);
            insertScript(base + '/editor/editor.main.js', function () {
              setStatus('Editor bundle loaded — creating editor', false);
              if (window.monaco) createEditor(siteToMonacoTheme());
            }, function () {
              console.warn('direct editor.main injection failed (timeout)');
              setStatus('Failed to load the editor; fallback to textarea', true);
            });
          };
          wait();
          return;
        } else if (window.monaco) {
          // If global monaco exists, create directly
          createEditor(siteToMonacoTheme());
        } else {
          // As last resort try to inject a direct editor.main script from the loader base
          insertScript(base + '/editor/editor.main.js', function () {
            if (window.monaco) createEditor(siteToMonacoTheme());
          }, function () { console.warn('direct editor.main injection failed'); });
        }
      } catch (e) { console.error('configureAndCreate failed', e); setStatus('Configuration error', true); }
    }

    function createEditor(theme) {
      setStatus('Creating editor…', false);
      if (!window.monaco || !container) return;
      const ed = monaco.editor.create(container, {
        value: initialText,
        language: 'php',
        theme: theme,
        automaticLayout: true,
        minimap: { enabled: false },
        fontSize: 12,
        lineNumbers: 'on',
        wordWrap: 'off'
      });

      // Ensure the minimap is disabled after creation as a safety measure —
      // some bundlings or defaults can re-enable it. Also force a layout so
      // the editor fills the container correctly after any DOM changes.
      try { ed.updateOptions({ minimap: { enabled: false } }); } catch(_) {}
      try { if (typeof ed.layout === 'function') ed.layout(); } catch(_) {}
      // Remove minimap DOM nodes if they somehow still exist (defensive):
      try {
        const mm = container.querySelectorAll('.minimap, .monaco-minimap, .monaco-editor .minimap');
        mm.forEach(function(el){ try{ el.style.display = 'none'; el.style.width = '0px'; el.style.minWidth='0px'; } catch(_){} });
      } catch(_) {}

      // hide the textarea fallback
      if (textarea) textarea.classList.add('hidden');

      // On submit copy from editor
      if (form && textarea) {
        form.addEventListener('submit', function () { try { textarea.value = ed.getValue(); } catch (_) {} });
      }

      // store globally for other UI
      window.AdminEditor = window.AdminEditor || {};
      window.AdminEditor.editor = ed;
      setStatus('Editor ready', false);
    }

    // Try vendor loader only (no CDN). Probe the vendor path before attempting
    // to insert the script so we avoid noisy 404s in the console.
    setStatus('Checking vendor loader…');
    if (typeof fetch === 'function') {
      fetch(vendorLoader, { method: 'HEAD' }).then(function (res) {
        if (res.ok) {
          setStatus('Loading vendor loader…');
          insertScript(vendorLoader, function () { configureAndCreate(vendorLoader); }, function () {
            console.info('vendor loader failed to execute');
            setStatus('Vendor loader failed to execute — editor unavailable', true);
          });
        } else {
          console.info('vendor loader not present (HTTP ' + res.status + ')');
          setStatus('Vendor loader not present — please vendor the editor locally (see docs/scripts/install_monaco.sh)', true);
        }
      }).catch(function (e) {
        console.debug('Failed to probe vendor loader by HEAD; falling back to trying vendor script directly', e);
        insertScript(vendorLoader, function () { configureAndCreate(vendorLoader); }, function () { console.info('vendor loader not present (probe failed)'); setStatus('Vendor loader not present (probe failed) — please vendor the editor locally', true); });
      });
    } else {
      // No fetch available, insert vendor and hope for best
      insertScript(vendorLoader, function () { configureAndCreate(vendorLoader); }, function () { console.info('vendor loader not present, editor unavailable'); setStatus('Vendor loader not present — please vendor the editor locally', true); });
    }
  });
})();
