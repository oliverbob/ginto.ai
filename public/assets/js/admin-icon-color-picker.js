(function () {
  // Centralized icon color loader + Settings editor
  // Behavior:
  // - On any page: fetch server-stored mapping and apply colors to matching sidebar icons
  // - On /admin/settings page: render an editor UI (list of nav items + color pickers) so admins can change colors centrally

  const STORAGE_KEY = 'ginto_admin_icon_colors';

  function readStorage() { try { return JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}'); } catch (e) { return {}; } }
  function writeStorage(obj) { try { localStorage.setItem(STORAGE_KEY, JSON.stringify(obj)); } catch (e) {} }

  function rgbToHex(rgb) {
    if (!rgb) return null;
    const m = rgb.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/i);
    if (!m) return null;
    const r = parseInt(m[1], 10), g = parseInt(m[2], 10), b = parseInt(m[3], 10);
    return '#' + [r,g,b].map(x => x.toString(16).padStart(2,'0')).join('');
  }

  async function fetchServerMap() {
    try {
      const res = await fetch('/admin/settings/icon-colors');
      if (!res.ok) return {};
      return await res.json();
    } catch (err) { return {}; }
  }

  function getCsrfToken() {
    const m = document.querySelector('meta[name="csrf-token"]');
    if (m) return m.getAttribute('content') || '';
    if (window.csrf_token) return window.csrf_token;
    return '';
  }

  // Convert hex color like #rrggbb into rgba string with alpha.
  function hexToRgba(hex, alpha) {
    if (!hex) return null;
    hex = hex.trim();
    if (hex.indexOf('#') === 0) hex = hex.slice(1);
    if (hex.length === 3) {
      hex = hex.split('').map(c => c + c).join('');
    }
    const r = parseInt(hex.slice(0,2), 16);
    const g = parseInt(hex.slice(2,4), 16);
    const b = parseInt(hex.slice(4,6), 16);
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
  }

  // Derive a tiny palette (text color, light/dark background and hover) from an accent hex
  function derivePalette(accentHex) {
    if (!accentHex) return null;
    const p = {};
    p.accent = accentHex;
    p.text = readableTextColor(accentHex);
    try { p.bgLight = hexToRgba(accentHex, 0.92); } catch (_) { p.bgLight = null; }
    try { p.bgDark = hexToRgba(accentHex, 0.7); } catch (_) { p.bgDark = null; }
    try { p.hover = hexToRgba(accentHex, 0.9); } catch (_) { p.hover = null; }
    return p;
  }

  // Choose the most appropriate mapping key for a page context.
  // Goal: on admin pages prefer any mapping that targets /admin (exact or prefixed) before falling back
  function pickKeyForContext(mapping, curPath) {
    if (!mapping || typeof mapping !== 'object') return '';
    const keys = Object.keys(mapping || {});
    // exact match for current path
    if (curPath && mapping[curPath]) return curPath;
    // if we're on an admin page prefer any admin-prefixed mapping
    if (curPath && curPath.indexOf('/admin') === 0) {
      for (let k of keys) {
        if (k && k.indexOf('/admin') === 0) return k;
      }
      if (mapping['/admin']) return '/admin';
      if (mapping['/accent']) return '/accent';
      // prefer any mapping that is not the user-facing /dashboard
      for (let k of keys) {
        if (k !== '/dashboard') return k;
      }
      // Avoid falling back to the user-facing /dashboard palette on admin pages —
      // prefer explicit admin/accent or any non-dashboard mapping. If none exist, return empty
      // so admin visuals aren't overridden by a dashboard-only palette.
      return '';
    }

    // non-admin: prefer explicit dashboard -> accent -> admin -> first key
    if (mapping['/dashboard']) return '/dashboard';
    if (mapping['/accent']) return '/accent';
    if (mapping['/admin']) return '/admin';
    return keys[0] || '';
  }

  // Compute readable accent text (white vs black) based on luminance
  function readableTextColor(hex) {
    if (!hex) return '#fff';
    hex = hex.trim();
    if (hex.indexOf('#') === 0) hex = hex.slice(1);
    if (hex.length === 3) hex = hex.split('').map(c => c + c).join('');
    const r = parseInt(hex.slice(0,2), 16) / 255;
    const g = parseInt(hex.slice(2,4), 16) / 255;
    const b = parseInt(hex.slice(4,6), 16) / 255;
    // relative luminance
    const lum = 0.2126 * r + 0.7152 * g + 0.0722 * b;
    return lum < 0.6 ? '#ffffff' : '#111827';
  }

  // Apply mapping to all sidebar anchors on the page and surface-wide CSS vars
  function applyMapping(mapping) {
    if (!mapping) return;
    try {
      // allow mapping to influence site-wide accent. Prefer a mapping for the current
      // page path (so /admin/users picks up its own color), then fall back to sensible keys.
      const currentPath = (window.location && window.location.pathname) ? window.location.pathname : '';
      let accent = '';
      let accentKey = '';
      // Pick the best key for this context — admin-prefixed keys are preferred on admin pages
      try {
        const picked = pickKeyForContext(mapping, currentPath);
        if (picked) { accentKey = picked; accent = mapping[picked]; }
      } catch (_) {}
      // As a last resort take the first mapping value (helps when only a few keys exist)
      if (!accent) {
        const keys = Object.keys(mapping || {});
        if (keys.length) accent = mapping[keys[0]];
      }
      accent = (accent || '').trim();
      if (accent) {
        // Set both a generic --accent and a page-specific --dashboard-accent variable
        document.documentElement.style.setProperty('--accent', accent);
        document.documentElement.style.setProperty('--dashboard-accent', accent);
        // compute a small palette and expose it for other scripts to read
        const palette = derivePalette(accent);
        if (palette && palette.text) document.documentElement.style.setProperty('--accent-text', palette.text);
        try { if (palette && palette.hover) document.documentElement.style.setProperty('--accent-hover', palette.hover); } catch (_) {}
        try { window._gintoPalette = window._gintoPalette || {}; if (accentKey) window._gintoPalette[accentKey] = palette; } catch (_) {}
      }
    } catch (_) {}
    const anchors = document.querySelectorAll('aside#sidebar nav a, #sidebar nav a');
    anchors.forEach(link => {
      const key = link.dataset.iconKey || link.getAttribute('href') || (link.querySelector('span')?.textContent || '');
      const icon = link.querySelector('svg') || link.querySelector('i');
      if (!icon) return;
      const color = mapping[key];
      if (color) {
        icon.style.color = color;
        // Font Awesome <i> elements may need color on the element itself
      }
    });

    // Re-apply mapping to other UI components when present (cards, links)
    try {
      // If a dashboard/admin accent exists or was chosen above, apply it to elements that make sense
      const secondaryAccent = (accentKey && mapping[accentKey]) ? mapping[accentKey] : (mapping['/dashboard'] || mapping['/accent'] || '').trim();
      if (secondaryAccent) {
        // set CSS vars so templates can react
        document.documentElement.style.setProperty('--dashboard-accent', secondaryAccent);
        // In case components read --accent, keep it in sync
        document.documentElement.style.setProperty('--accent', secondaryAccent);
      }
    } catch (_) {}
  }

  // Render settings editor (list of nav items + color pickers) inside #icon-colors-editor
  function renderSettingsEditor(container, serverMap, localMap) {
    container.innerHTML = '';
    const anchors = Array.from(document.querySelectorAll('aside#sidebar nav a, #sidebar nav a'));
    if (!anchors.length) {
      container.innerHTML = '<p class="text-sm text-gray-600">No sidebar items found to edit on this page.</p>';
      return;
    }

    const map = { ...(localMap || {}), ...(serverMap || {}) };

    const list = document.createElement('div');
    list.className = 'space-y-2';

    anchors.forEach(link => {
      const key = link.dataset.iconKey || link.getAttribute('href') || (link.querySelector('span')?.textContent || '');
      const label = link.querySelector('span')?.textContent?.trim() || key;
      const icon = link.querySelector('svg') || link.querySelector('i');
      const curColor = map[key] || rgbToHex(window.getComputedStyle(icon).color) || '';

      const row = document.createElement('div');
      row.className = 'flex items-center justify-between p-2 border rounded';

      const left = document.createElement('div');
      left.className = 'flex items-center gap-3';
      const preview = document.createElement('div');
      preview.style.width = '28px';
      preview.style.height = '28px';
      preview.style.borderRadius = '6px';
      preview.style.background = curColor || 'transparent';
      // border should be visible in both light and dark themes
      preview.style.border = (document.documentElement.classList.contains('dark') ? '1px solid rgba(255,255,255,0.06)' : '1px solid rgba(0,0,0,0.06)');
      const text = document.createElement('div');
      text.innerHTML = '<div class="font-medium">' + label + '</div><div class="text-xs text-gray-500">' + key + '</div>';
      left.appendChild(preview);
      left.appendChild(text);

      const right = document.createElement('div');
      right.className = 'flex items-center gap-2';
      // color input
      const input = document.createElement('input');
      input.type = 'color';
      input.value = curColor || '#ef4444';
      input.title = 'Choose color for ' + key;
      input.style.width = '40px';
      input.style.height = '36px';

      // quick swatches to choose common colours fast
      const swatches = ['#ef4444','#f97316','#f59e0b','#10b981','#3b82f6','#6366f1','#a78bfa','#ec4899','#6b7280'];
      const swatchRow = document.createElement('div');
      swatchRow.style.display = 'flex';
      swatchRow.style.gap = '6px';
      swatchRow.style.alignItems = 'center';

      swatches.forEach(s => {
        const sEl = document.createElement('button');
        sEl.type = 'button';
        sEl.className = 'swatch rounded-full border';
        sEl.style.width = '18px';
        sEl.style.height = '18px';
        sEl.style.padding = '0';
        sEl.style.borderWidth = '1px';
        sEl.style.background = s;
        sEl.title = s;
        sEl.addEventListener('click', function(e){ e.stopPropagation(); input.value = s; input.dispatchEvent(new Event('input')); });
        swatchRow.appendChild(sEl);
      });

      const saveBtn = document.createElement('button');
      saveBtn.type = 'button';
      saveBtn.className = 'px-3 py-1 rounded bg-indigo-600 text-white text-sm';
      saveBtn.textContent = 'Save';

      const resetBtn = document.createElement('button');
      resetBtn.type = 'button';
      resetBtn.className = 'px-2 py-1 rounded bg-gray-200 text-sm';
      resetBtn.textContent = 'Reset';

      right.appendChild(input);
      right.appendChild(swatchRow);
      right.appendChild(saveBtn);
      right.appendChild(resetBtn);

      row.appendChild(left);
      row.appendChild(right);

      list.appendChild(row);

      // events
      input.addEventListener('input', function() { preview.style.background = input.value; icon && (icon.style.color = input.value); });

      saveBtn.addEventListener('click', async function(){
        const m = readStorage();
        m[key] = input.value;
        writeStorage(m);
        // update server mapping (best effort) — include CSRF header + X-Requested-With and credentials so middleware recognizes AJAX
        try {
          const r = await fetch('/admin/settings/save', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
              'Content-Type':'application/json',
              'X-CSRF-TOKEN': getCsrfToken(),
              'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ icon_colors: m })
          });
          if (!r.ok) {
            let body = '';
            try { body = await r.json(); } catch (_) { body = await r.text().catch(()=>''); }
            console.warn('Failed saving icon color:', r.status, body);
            alert('Failed to save color to server. See console for details.');
          }
        } catch (e) { console.warn('Save failed', e); alert('Error saving to server: ' + (e && e.message)); }

        // update textarea if present
        const txt = document.getElementById('icon-colors-text'); if (txt) txt.value = JSON.stringify(m, null, 2);
        try {
          const curPath = (window.location && window.location.pathname) ? window.location.pathname : '';
          const keyUsed = pickKeyForContext(m, curPath);
          const accent = keyUsed ? (m[keyUsed] || '') : '';
          const pal = accent ? derivePalette(accent) : null;
          try { window._gintoPalette = window._gintoPalette || {}; if (keyUsed && pal) window._gintoPalette[keyUsed] = pal; } catch(_) {}
          window.dispatchEvent(new CustomEvent('site-palette-changed', { detail: { map: m, path: curPath, key: keyUsed, accent: accent, palette: pal } }));
        } catch (_) {}
      });

      resetBtn.addEventListener('click', function(){
        const m = readStorage();
        delete m[key];
        writeStorage(m);
        icon && (icon.style.color = '');
        preview.style.background = 'transparent';
        const txt = document.getElementById('icon-colors-text'); if (txt) txt.value = JSON.stringify(m, null, 2);
        try {
          const curPath = (window.location && window.location.pathname) ? window.location.pathname : '';
          const keyUsed = pickKeyForContext(m, curPath);
          const accent = keyUsed ? (m[keyUsed] || '') : '';
          const pal = accent ? derivePalette(accent) : null;
          try { window._gintoPalette = window._gintoPalette || {}; if (keyUsed && pal) window._gintoPalette[keyUsed] = pal; } catch(_) {}
          window.dispatchEvent(new CustomEvent('site-palette-changed', { detail: { map: m, path: curPath, key: keyUsed, accent: accent, palette: pal } }));
        } catch (_) {}
      });
    });

    // helper controls
    const controls = document.createElement('div');
    controls.className = 'flex items-center justify-between mt-4';

    const leftCtrl = document.createElement('div');
    leftCtrl.className = 'flex items-center gap-2';
    const saveAll = document.createElement('button'); saveAll.className = 'px-3 py-2 rounded bg-indigo-600 text-white'; saveAll.textContent = 'Save all to server';
    const applyLocal = document.createElement('button'); applyLocal.className = 'px-3 py-2 rounded bg-yellow-500 text-white'; applyLocal.textContent = 'Apply locally';
    const loadServer = document.createElement('button'); loadServer.className = 'px-3 py-2 rounded bg-gray-200'; loadServer.textContent = 'Reload server mapping';
    leftCtrl.appendChild(saveAll); leftCtrl.appendChild(applyLocal); leftCtrl.appendChild(loadServer);

    const rightCtrl = document.createElement('div'); rightCtrl.className = 'text-sm text-gray-500'; rightCtrl.textContent = "Changes are saved to the 'admin_icon_colors' setting.";
    controls.appendChild(leftCtrl); controls.appendChild(rightCtrl);

    container.appendChild(list);
    container.appendChild(controls);

    saveAll.addEventListener('click', async function(){
      // collect mapping from local storage
      const m = readStorage();

      // If the main form/textarea exists on this page, mirror the top "Save to server" behaviour
      // by populating the textarea and submitting the form so the server returns a regular redirect + flash.
      const form = document.getElementById('icon-colors-form');
      const txt = document.getElementById('icon-colors-text');
      if (form && txt) {
        txt.value = JSON.stringify(m, null, 2);
        // submit the form (same as top 'Save to server') — this keeps session/CSRF consistent
        try {
          form.submit();
          return;
        } catch (e) {
          // fallback to AJAX if form submit fails
          console.warn('Form submit failed, falling back to AJAX', e);
        }
      }

      // fallback: perform an AJAX POST with proper headers (works on pages without the form)
      try {
        const r = await fetch('/admin/settings/save', {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type':'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: JSON.stringify({ icon_colors: m })
        });
        if (r.ok) {
          try {
            const j = await r.json().catch(()=>null);
            if (j && j.success === true) alert('Saved to server'); else alert('Saved (server returned unexpected response)');
          } catch (_) { alert('Saved to server'); }
        } else {
          let msg = 'Server error while saving (' + r.status + ')';
          try { const j = await r.json(); if (j && j.message) msg += ': ' + j.message; } catch (_) {}
          alert(msg);
        }
      } catch (e) { alert('Error saving to server: ' + (e && e.message)); }
    });

    applyLocal.addEventListener('click', function(){
      const m = readStorage();
      applyMapping(m);
      try {
        const curPath = (window.location && window.location.pathname) ? window.location.pathname : '';
        const keyUsed = pickKeyForContext(m, curPath);
        const accent = keyUsed ? (m[keyUsed] || '') : '';
        const pal = accent ? derivePalette(accent) : null;
        try { window._gintoPalette = window._gintoPalette || {}; if (keyUsed && pal) window._gintoPalette[keyUsed] = pal; } catch(_) {}
        window.dispatchEvent(new CustomEvent('site-palette-changed', { detail: { map: m, path: curPath, key: keyUsed, accent: accent, palette: pal } }));
      } catch (_) {}
      // reflect in textarea
      const txt = document.getElementById('icon-colors-text'); if (txt) txt.value = JSON.stringify(m, null, 2);
    });

    loadServer.addEventListener('click', async function(){
      const s = await fetchServerMap();
      writeStorage(s);
      renderSettingsEditor(container, s, readStorage());
      applyMapping(s);
      const txt = document.getElementById('icon-colors-text'); if (txt) txt.value = JSON.stringify(s, null, 2);
      try {
        const curPath = (window.location && window.location.pathname) ? window.location.pathname : '';
        const keyUsed = pickKeyForContext(s, curPath);
        const accent = keyUsed ? (s[keyUsed] || '') : '';
        const pal = accent ? derivePalette(accent) : null;
        try { window._gintoPalette = window._gintoPalette || {}; if (keyUsed && pal) window._gintoPalette[keyUsed] = pal; } catch(_) {}
        window.dispatchEvent(new CustomEvent('site-palette-changed', { detail: { map: s, path: curPath, key: keyUsed, accent: accent, palette: pal } }));
      } catch (_) {}
    });
  }

  // Initialize: apply mapping across pages and render editor only on /admin/settings
  document.addEventListener('DOMContentLoaded', async function() {
    // migrate legacy single-item setting if present
    try {
      const legacy = localStorage.getItem('ginto_admin_users_icon_color');
      if (legacy) {
        const m = readStorage();
        m['/admin/users'] = legacy;
        writeStorage(m);
        localStorage.removeItem('ginto_admin_users_icon_color');
      }
    } catch (e) {}

    const serverMap = await fetchServerMap();
    const localMap = readStorage();

    // Merge local + server maps (server takes precedence)
    const merged = Object.assign({}, localMap, serverMap);
    Object.keys(serverMap || {}).forEach(k => { merged[k] = serverMap[k]; });

    // Apply mapping to page icons
    applyMapping(merged);
    // announce palette change so other components (charts, UI) can react
    try {
      const curPath = (window.location && window.location.pathname) ? window.location.pathname : '';
      const keyToUse = pickKeyForContext(merged, curPath);
      const accent = keyToUse ? (merged[keyToUse] || '') : '';
      const pal = accent ? derivePalette(accent) : null;
      try { window._gintoPalette = window._gintoPalette || {}; if (keyToUse && pal) window._gintoPalette[keyToUse] = pal; } catch(_) {}
      window.dispatchEvent(new CustomEvent('site-palette-changed', { detail: { map: merged, path: curPath, key: keyToUse, accent: accent, palette: pal } }));
    } catch (_) {}
    // keep accessible globally so other scripts (theme toggles) can re-apply when theme changes
    try { window._gintoIconColorMap = merged; } catch (_) {}

    // Re-apply mapping when theme changes (site theme manager broadcasts 'site-theme-changed')
    try {
      window.addEventListener('site-theme-changed', function () {
        try { applyMapping(window._gintoIconColorMap || {}); } catch (_) {}
      });
    } catch (_) {}

    // If on settings page, render editor UI
    if (window.location.pathname && window.location.pathname.indexOf('/admin/settings') === 0) {
      const container = document.getElementById('icon-colors-editor');
      if (container) {
        renderSettingsEditor(container, serverMap, localMap);
        const txt = document.getElementById('icon-colors-text');
        if (txt) txt.value = JSON.stringify(Object.keys(serverMap || {}).length ? serverMap : localMap, null, 2);
      }
    }
  });
})();
