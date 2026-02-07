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
          <div class="relative w-full" style="padding-bottom: 56.25%;">
            <iframe 
              class="absolute top-0 left-0 w-full h-full rounded-xl shadow-2xl"
              src="https://www.youtube.com/embed/BLLf4e9BqXs?rel=0&modestbranding=1&autoplay=1&mute=1" 
              title="Ginto AI Demo"
              frameborder="0" 
              allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" 
              allowfullscreen>
            </iframe>
          </div>
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
