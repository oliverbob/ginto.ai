<?php
/**
 * Example Prompts Loading and Rendering Scripts
 */
?>
<script>
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
              <div id="home-academy-charts" style="flex:1 1 260px;min-width:220px;display:flex;flex-direction:column;gap:8px;"></div>
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

  // Live gainer / popular / loser mini charts for the homepage Academy banner (Binance data).
  function renderHomeAcademyCharts() {
    const wrap = document.getElementById('home-academy-charts');
    if (!wrap) return;
    function go() {
      fetch('https://api.binance.com/api/v3/ticker/24hr')
        .then(function (r) { return r.json(); })
        .then(function (all) {
          if (!Array.isArray(all)) return;
          const stable = ['USDCUSDT','FDUSDUSDT','TUSDUSDT','BUSDUSDT','DAIUSDT','EURUSDT','USD1USDT'];
          const usdt = all.filter(function (t) {
            return /USDT$/.test(t.symbol) && !/(UP|DOWN|BULL|BEAR)USDT$/.test(t.symbol)
              && stable.indexOf(t.symbol) < 0 && parseFloat(t.quoteVolume) > 30000000;
          });
          if (usdt.length < 3) return;
          usdt.sort(function (a, b) { return parseFloat(b.priceChangePercent) - parseFloat(a.priceChangePercent); });
          const popular = all.find(function (t) { return t.symbol === 'BTCUSDT'; }) || usdt[Math.floor(usdt.length / 2)];
          const picks = [
            { t: usdt[0], tag: 'TOP GAINER' },
            { t: popular, tag: 'POPULAR' },
            { t: usdt[usdt.length - 1], tag: 'TOP LOSER' }
          ];
          wrap.innerHTML = '';
          picks.forEach(function (p) {
            const d = document.createElement('div');
            d.setAttribute('data-symbol', p.t.symbol);
            d.setAttribute('data-chg', parseFloat(p.t.priceChangePercent).toFixed(2));
            d.setAttribute('data-tag', p.tag);
            d.style.cssText = 'position:relative;height:56px;border-radius:8px;overflow:hidden;background:rgba(0,0,0,0.18);';
            wrap.appendChild(d);
            drawHomeMini(d);
          });
        }).catch(function () {});
    }
    if (typeof LightweightCharts !== 'undefined') go();
    else {
      const s = document.createElement('script');
      s.src = 'https://cdn.jsdelivr.net/npm/lightweight-charts@4.2.0/dist/lightweight-charts.standalone.production.js';
      s.onload = go; document.head.appendChild(s);
    }
  }
  function drawHomeMini(el) {
    if (typeof LightweightCharts === 'undefined') return;
    const sym = el.getAttribute('data-symbol');
    const chg = parseFloat(el.getAttribute('data-chg'));
    const tag = el.getAttribute('data-tag');
    const up = chg >= 0, col = up ? '#22c55e' : '#ef4444';
    const label = document.createElement('div');
    label.style.cssText = 'position:absolute;inset:0;z-index:2;display:flex;justify-content:space-between;align-items:flex-start;padding:4px 8px;font-size:10px;font-weight:700;color:#fff;pointer-events:none;';
    label.innerHTML = '<span style="opacity:.85">' + tag + ' · ' + sym.replace('USDT', '') + '</span><span style="color:' + col + '">' + (up ? '+' : '') + chg + '%</span>';
    el.appendChild(label);
    const chart = LightweightCharts.createChart(el, {
      width: el.clientWidth || 240, height: 56,
      layout: { background: { type: 'solid', color: 'transparent' }, textColor: 'rgba(0,0,0,0)', fontSize: 1 },
      grid: { vertLines: { visible: false }, horzLines: { visible: false } },
      rightPriceScale: { visible: false }, leftPriceScale: { visible: false }, timeScale: { visible: false },
      handleScale: false, handleScroll: false, crosshair: { mode: 0 }
    });
    const series = chart.addAreaSeries({ lineColor: col, topColor: up ? 'rgba(34,197,94,0.35)' : 'rgba(239,68,68,0.35)', bottomColor: 'rgba(0,0,0,0)', lineWidth: 2, priceLineVisible: false, lastValueVisible: false });
    fetch('https://api.binance.com/api/v3/klines?symbol=' + sym + '&interval=15m&limit=48')
      .then(function (r) { return r.json(); })
      .then(function (rows) { if (!Array.isArray(rows)) return; series.setData(rows.map(function (k) { return { time: Math.floor(k[0] / 1000), value: +k[4] }; })); chart.timeScale().fitContent(); })
      .catch(function () {});
    try { new ResizeObserver(function () { try { chart.applyOptions({ width: el.clientWidth }); } catch (e) {} }).observe(el); } catch (e) {}
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
    
    // If not logged in, show YouTube video instead
    if (!isLoggedIn) {
      renderYouTubeEmbed();
      return;
    }
    
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
