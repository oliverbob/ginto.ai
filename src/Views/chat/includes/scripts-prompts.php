<?php
/**
 * Example Prompts Loading and Rendering Scripts
 */
// Inline the cached movers so the homepage charts render from the page itself — no dependency on a
// client-side fetch that can silently fail (which is why the charts kept vanishing for guests).
$__moversInline = 'null';
try {
    $__cf = (defined('STORAGE_PATH') ? rtrim(STORAGE_PATH, '/') : sys_get_temp_dir()) . '/academy_movers.json';
    if (is_file($__cf) && (time() - filemtime($__cf) < 600)) {
        $__raw = trim((string) @file_get_contents($__cf));
        if ($__raw !== '' && $__raw[0] === '{') $__moversInline = $__raw;
    }
} catch (\Throwable $e) {}
?>
<script>
  window.GTB_MOVERS_INLINE = <?= $__moversInline ?>;
  // ========================================
  // Example Prompts for Welcome Screen
  // ========================================
  
  // Render YouTube video embed for non-logged-in users
  function renderYouTubeEmbed() {
    // Hide the welcome hint area and show full-width video
    const hintArea = document.querySelector('.bg-hint');
    if (hintArea) {
      hintArea.innerHTML = `
        <div class="w-full max-w-4xl mx-auto">
          <a href="/academy" aria-label="Ginto Trading Academy" style="display:block;text-decoration:none;border-radius:16px;overflow:hidden;box-shadow:0 12px 40px rgba(99,102,241,0.25);">
            <div style="background:linear-gradient(135deg,#4f46e5,#7c3aed 55%,#8b5cf6);padding:26px;color:#fff;display:flex;flex-wrap:wrap;align-items:center;gap:22px;">
              <div style="flex:1 1 260px;min-width:240px;">
                <div style="font-size:0.72rem;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;opacity:0.85;">🎓 Ginto Trading Academy</div>
                <div style="font-size:1.65rem;font-weight:800;margin-top:8px;line-height:1.18;">Read the chart.<br>Trade with discipline.</div>
                <div style="margin-top:10px;opacity:0.92;font-size:0.95rem;">Hands-on crypto trading lessons — taught on a real, live AI bot, not slides. Start with the basics of a candlestick.</div>
                <div style="display:flex;flex-wrap:wrap;gap:14px;margin-top:16px;font-size:0.82rem;opacity:0.95;">
                  <span>📈 Charts</span><span>🛡️ Risk</span><span>🤖 The Bot</span><span>🧠 PineScript</span>
                </div>
                <div style="margin-top:18px;display:inline-flex;align-items:center;gap:8px;background:#fff;color:#4f46e5;font-weight:800;padding:11px 20px;border-radius:12px;font-size:0.9rem;">Start learning <span>→</span></div>
              </div>
              <div id="home-academy-charts" style="flex:1 1 260px;min-width:220px;display:flex;flex-direction:column;gap:14px;"></div>
            </div>
          </a>
          <a href="/marketplace" aria-label="Visit Ginto Mall" style="display:block;margin-top:18px;border-radius:14px;overflow:hidden;box-shadow:0 8px 32px rgba(0,0,0,0.18);transition:transform 0.2s,box-shadow 0.2s;text-decoration:none;" onmouseover="this.style.transform='scale(1.015)';this.style.boxShadow='0 12px 40px rgba(0,0,0,0.28)';" onmouseout="this.style.transform='';this.style.boxShadow='0 8px 32px rgba(0,0,0,0.18)';">
            <img src="/assets/images/mall.png" alt="Ginto Mall" style="width:100%;display:block;border-radius:14px 14px 0 0;">
            <div style="background:linear-gradient(90deg,#6366f1,#8b5cf6);padding:10px 16px;border-radius:0 0 14px 14px;display:flex;align-items:center;justify-content:center;gap:8px;">
              <span style="font-size:1.1rem;">🛍️</span>
              <span style="color:#fff;font-weight:700;font-size:0.92rem;letter-spacing:0.01em;">Shop and sell now at Ginto Mall</span>
              <span style="color:rgba(255,255,255,0.75);font-size:0.85rem;">→</span>
            </div>
          </a>
        </div>
      `;
      renderHomeAcademyCharts();
    }
  }

  // Live gainer / popular / loser mini charts — data via our own server (reliable),
  // drawn as pure SVG sparklines (no external library, no client-side Binance call).
  function gtbPaintMovers(wrap, movers) {
    if (!wrap || !Array.isArray(movers) || !movers.length) return false;
    try { wrap.innerHTML = movers.map(homeMiniHtml).join(''); wrap.style.display = ''; return true; }
    catch (e) { return false; }
  }
  function renderHomeAcademyCharts(attempt) {
    // There can be more than one #home-academy-charts (guest banner + logged-in ad card); paint every one.
    const wraps = Array.prototype.slice.call(document.querySelectorAll('#home-academy-charts'));
    if (!wraps.length) return;
    attempt = attempt || 0;
    // 1) Immediate paint from server-inlined data (no fetch) so the charts are never empty.
    if (attempt === 0) {
      const inl = window.GTB_MOVERS_INLINE;
      if (inl && inl.ok && Array.isArray(inl.movers)) wraps.forEach(function (w) { gtbPaintMovers(w, inl.movers); });
    }
    // 2) Refresh from the live endpoint (also covers a cold cache); retry transient failures.
    fetch('/api/academy/movers', { cache: 'no-store', credentials: 'same-origin' })
      .then(function (r) { if (!r.ok) throw new Error('http ' + r.status); return r.json(); })
      .then(function (d) {
        if (!d || !d.ok || !Array.isArray(d.movers) || !d.movers.length) throw new Error('no data');
        wraps.forEach(function (w) { gtbPaintMovers(w, d.movers); });
      })
      .catch(function () {
        if (attempt < 4) { setTimeout(function () { renderHomeAcademyCharts(attempt + 1); }, 1500 + attempt * 1000); }
        else { wraps.forEach(function (w) { if (!w.children.length) w.style.display = 'none'; }); }  // only hide if still empty
      });
  }
  function homeSparkline(vals, col) {
    if (!vals || vals.length < 2) return '';
    var w = 280, h = 56, min = Math.min.apply(null, vals), max = Math.max.apply(null, vals), rng = (max - min) || 1;
    var pts = vals.map(function (v, i) {
      var x = (i / (vals.length - 1)) * w;
      var y = h - 6 - ((v - min) / rng) * (h - 16);
      return x.toFixed(1) + ',' + y.toFixed(1);
    });
    var id = 'g' + Math.random().toString(36).slice(2, 7);
    return '<svg viewBox="0 0 ' + w + ' ' + h + '" preserveAspectRatio="none" width="100%" height="100%" style="position:absolute;top:0;left:0;width:100%;height:100%;display:block;">'
      + '<defs><linearGradient id="' + id + '" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="' + col + '" stop-opacity="0.28"/><stop offset="1" stop-color="' + col + '" stop-opacity="0"/></linearGradient></defs>'
      + '<path d="M0,' + h + ' L' + pts.join(' L') + ' L' + w + ',' + h + ' Z" fill="url(#' + id + ')"/>'
      + '<path d="M' + pts.join(' L') + '" fill="none" stroke="' + col + '" stroke-width="1.75" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"/></svg>';
  }
  function homeMiniHtml(m) {
    var up = (+m.chg) >= 0, col = up ? '#22c55e' : '#ef4444';
    return '<div style="border-radius:10px;overflow:hidden;background:rgba(12,10,32,0.30);border:1px solid rgba(255,255,255,0.12);box-shadow:0 1px 3px rgba(0,0,0,0.18);">'
      + '<div style="display:flex;justify-content:space-between;align-items:center;gap:8px;padding:7px 11px 4px;">'
      +   '<span style="font-size:9.5px;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;color:rgba(255,255,255,0.78);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + m.tag + ' · ' + m.base + '</span>'
      +   '<span style="font-size:11px;font-weight:800;color:' + col + ';white-space:nowrap;">' + (up ? '+' : '') + m.chg + '%</span>'
      + '</div>'
      + '<div style="position:relative;height:34px;">' + homeSparkline(m.closes || [], col) + '</div>'
      + '</div>';
  }

  // Example prompts: fetch role-based prompts from server and render
  function renderPrompts(prompts) {
    const container = document.getElementById('welcome-prompts');
    if (!container) return;
    container.innerHTML = '';
    // Limit to at most 4 prompts
    prompts = Array.isArray(prompts) ? prompts.slice(0, 4) : [];
    prompts.forEach(p => {
      const btn = document.createElement('button');
      btn.className = 'example-prompt px-4 py-3 text-left bg-gray-100 dark:bg-gray-800/50 hover:bg-gray-200 dark:hover:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl transition-colors text-sm';
      btn.innerHTML = `<span class="text-gray-700 dark:text-gray-300">${escapeHtml(p.title)}</span>`;
      btn.addEventListener('click', () => {
        const promptEl = document.getElementById('prompt');
        if (promptEl) {
          promptEl.value = p.prompt || p.title || '';
          promptEl.focus();
        }
      });
      container.appendChild(btn);
    });
  }

  function escapeHtml(s) { return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

  async function loadPrompts() {
    // Check if user is logged in
    const isLoggedIn = window.GINTO_AUTH?.isLoggedIn;
    
    // If not logged in, show the Academy banner (which renders its own live charts)
    if (!isLoggedIn) {
      renderYouTubeEmbed();
      return;
    }
    // Logged-in: fill the Academy ad card's live gainer/popular/loser mini-charts.
    renderHomeAcademyCharts();

    try {
      await window.GINTO_AUTH_PROMISE; // ensure auth state ready
      const res = await fetch('/chat/prompts/', { credentials: 'same-origin' });
      if (!res.ok) throw new Error('Network error');
      const j = await res.json().catch(() => null);
      const prompts = (j && Array.isArray(j.prompts)) ? j.prompts : null;
      if (prompts) {
        renderPrompts(prompts);
      } else {
        // fallback: show a small set of safe prompts
        renderPrompts([
            { title: 'Describe this file', prompt: 'Describe the selected file.' },
            { title: 'Help debug a sandbox error', prompt: 'I have an error in my sandboxed file.' },
            { title: 'How do I upload a file?', prompt: 'How do I upload a file to my sandbox?' },
            { title: 'Show recent files', prompt: 'List recent files I added to my sandbox.' }
          ]);
      }
    } catch (err) {
      console.error('Failed to load prompts:', err);
      renderPrompts([
        { title: 'Describe this file', prompt: 'Describe the selected file.' },
        { title: 'Help debug a sandbox error', prompt: 'I have an error in my sandboxed file.' },
        { title: 'How do I upload a file?', prompt: 'How do I upload a file to my sandbox?' }
      ]);
    }
  }

  // Kick off prompt loading when auth state is ready
  loadPrompts();
</script>
