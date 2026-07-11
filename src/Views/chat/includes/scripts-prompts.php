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
              <div style="flex:1 1 240px;min-width:220px;">
                <svg viewBox="0 0 320 160" width="100%" style="display:block;max-width:340px;margin:0 auto;">
                  <line x1="10" y1="30" x2="310" y2="30" stroke="rgba(255,255,255,0.35)" stroke-width="1" stroke-dasharray="4 4"/>
                  <text x="14" y="24" fill="rgba(255,255,255,0.8)" font-size="10">target</text>
                  <line x1="10" y1="120" x2="310" y2="120" stroke="rgba(255,255,255,0.35)" stroke-width="1" stroke-dasharray="4 4"/>
                  <text x="14" y="134" fill="rgba(255,255,255,0.8)" font-size="10">entry</text>
                  <!-- candlesticks trending up -->
                  <g stroke-linecap="round">
                    <line x1="34"  y1="98"  x2="34"  y2="128" stroke="rgba(255,255,255,0.55)"/><rect x="27"  y="104" width="14" height="18" rx="1.5" fill="#f87171"/>
                    <line x1="70"  y1="82"  x2="70"  y2="112" stroke="rgba(255,255,255,0.55)"/><rect x="63"  y="88"  width="14" height="18" rx="1.5" fill="#34d399"/>
                    <line x1="106" y1="70"  x2="106" y2="98"  stroke="rgba(255,255,255,0.55)"/><rect x="99"  y="76"  width="14" height="16" rx="1.5" fill="#34d399"/>
                    <line x1="142" y1="74"  x2="142" y2="100" stroke="rgba(255,255,255,0.55)"/><rect x="135" y="80"  width="14" height="14" rx="1.5" fill="#f87171"/>
                    <line x1="178" y1="56"  x2="178" y2="88"  stroke="rgba(255,255,255,0.55)"/><rect x="171" y="62"  width="14" height="20" rx="1.5" fill="#34d399"/>
                    <line x1="214" y1="44"  x2="214" y2="70"  stroke="rgba(255,255,255,0.55)"/><rect x="207" y="50"  width="14" height="16" rx="1.5" fill="#34d399"/>
                    <line x1="250" y1="48"  x2="250" y2="72"  stroke="rgba(255,255,255,0.55)"/><rect x="243" y="54"  width="14" height="12" rx="1.5" fill="#f87171"/>
                    <line x1="286" y1="30"  x2="286" y2="58"  stroke="rgba(255,255,255,0.55)"/><rect x="279" y="36"  width="14" height="18" rx="1.5" fill="#34d399"/>
                  </g>
                </svg>
              </div>
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
    }
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
