<!doctype html>
<html lang="en" class="scroll-smooth dark">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="icon" type="image/png" href="/assets/images/ginto.png" />
  <title><?= htmlspecialchars($title ?? 'Ginto Chat') ?></title>
  <script>
    // Auth status - will be populated by fetching /user endpoint
    window.GINTO_AUTH = {
      isLoggedIn: <?= json_encode($isLoggedIn ?? false) ?>,
      userId: <?= json_encode($userId ?? null) ?>,
      userDisplayName: 'User', // Will be updated from /user endpoint
      csrfToken: null, // Will be fetched from /user endpoint
      isAdmin: <?= json_encode($isAdmin ?? false) ?>, // Server-side check, also updated from /user
      sandbox: null, // Will be updated from /user endpoint
      canPersistLocally: true,
      ready: false // Flag to indicate when user info is loaded
    };
    
    // Fetch user info from /user endpoint if logged in
    // This must complete before rendering messages
    window.GINTO_AUTH_PROMISE = window.GINTO_AUTH.isLoggedIn 
      ? fetch('/user', { credentials: 'same-origin' })
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              window.GINTO_AUTH.userDisplayName = data.user.displayName;
              window.GINTO_AUTH.csrfToken = data.csrf_token;
              window.GINTO_AUTH.isAdmin = data.user.isAdmin || false;
              window.GINTO_AUTH.sandbox = data.user.sandbox || null;
              window.GINTO_AUTH.ready = true;
              console.log('User info loaded:', data.user);
              
              // Display admin/sandbox status
              if (data.user.isAdmin) {
                console.log('✅ Admin user detected');
              }
              if (data.user.sandbox?.enabled) {
                console.log('📦 Sandbox enabled:', data.user.sandbox.id);
              }
              
              // Trigger custom event so chat.js can re-render if needed
              window.dispatchEvent(new CustomEvent('gintoAuthReady', { detail: data.user }));
              
              // Show admin-only elements for admin users
              if (data.user.isAdmin) {
                // Show MCP tab in settings panel
                const mcpTab = document.getElementById('tab-mcp');
                if (mcpTab) mcpTab.classList.remove('hidden');
                
                // Show Admin/API Keys tab in settings panel
                const adminTab = document.getElementById('tab-admin');
                if (adminTab) adminTab.classList.remove('hidden');
                
                // Show MCP status button in sidebar
                const mcpBtn = document.getElementById('open-mcp-tab');
                if (mcpBtn) mcpBtn.classList.remove('hidden');
              }
            }
            return data;
          })
          .catch(err => {
            console.error('Failed to fetch user info:', err);
            window.GINTO_AUTH.ready = true;
            return null;
          })
      : fetch('/dev/csrf', { credentials: 'same-origin' })
          .then(res => res.json())
          .then(data => {
            if (data.success && data.csrf_token) {
              window.GINTO_AUTH.csrfToken = data.csrf_token;
              console.log('Visitor CSRF token loaded');
              
              // Set up session expiration timer for visitors
              if (data.expires_in && data.expires_in > 0) {
                window.GINTO_AUTH.csrfExpires = data.expires;
                window.GINTO_AUTH.csrfExpiresIn = data.expires_in;
                
                // Show warning modal 1 minute before expiration (or at expiration if less than 1 min)
                const warningTime = Math.max(0, (data.expires_in - 60) * 1000);
                setTimeout(() => {
                  if (!window.GINTO_AUTH.userId) { // Still a visitor
                    showSessionExpiredModal();
                  }
                }, warningTime);
                
                console.log('Visitor session expires in', data.expires_in, 'seconds');
              }
            }
            window.GINTO_AUTH.ready = true;
            return null;
          })
          .catch(err => {
            console.error('Failed to fetch CSRF for visitor:', err);
            window.GINTO_AUTH.ready = true;
            return null;
          });
  </script>
  
  <!-- Chat configuration from server -->
  <script>
    window.GINTO_CONFIG = {
      agentPlan: {
        maxToolCallsPerPlan: <?= json_encode((int)\Ginto\Helpers\ChatConfig::get('agentPlan.maxToolCallsPerPlan', 10)) ?>
      },
      streaming: {
        renderMarkdownOnServer: <?= json_encode((bool)\Ginto\Helpers\ChatConfig::get('streaming.renderMarkdownOnServer', true)) ?>
      }
    };
  </script>
  
  <script src="/assets/js/tailwindcss.js"></script>
  <!-- Font Awesome for small icons in the sidebar -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          colors: {
            primary: '#6366f1',
            secondary: '#8b5cf6',
            dark: {
              bg: '#1a1a2e',
              surface: '#16213e',
              card: '#1f2937',
              border: '#374151'
            }
          }
        }
      }
    };
  </script>
  <style>
    /* Custom scrollbar */
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #4b5563; border-radius: 3px; }
    ::-webkit-scrollbar-thumb:hover { background: #6b7280; }
    
    /* Message animations */
    .msg-enter { animation: msgSlide 0.3s ease-out; }
    @keyframes msgSlide {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    /* Typing indicator */
    .typing-dot { animation: typingBounce 1.4s infinite ease-in-out both; }
    .typing-dot:nth-child(1) { animation-delay: -0.32s; }
    .typing-dot:nth-child(2) { animation-delay: -0.16s; }
    @keyframes typingBounce {
      0%, 80%, 100% { transform: scale(0); }
      40% { transform: scale(1); }
    }
    
    /* Old code block styles removed - see new styles below */

    /* ChatGPT-style sidebar scrollbar */
    .sidebar-scroll::-webkit-scrollbar { width: 6px; }
    .sidebar-scroll::-webkit-scrollbar-track { background: transparent; }
    .sidebar-scroll::-webkit-scrollbar-thumb { background: #404040; border-radius: 3px; }
    .sidebar-scroll::-webkit-scrollbar-thumb:hover { background: #525252; }
    
    /* Conversation item in sidebar */
    .convo-item {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.5rem 0.75rem;
      border-radius: 0.5rem;
      color: #d1d5db;
      font-size: 0.875rem;
      cursor: pointer;
      transition: background 0.15s;
    }
    .convo-item:hover { background: rgba(255,255,255,0.05); }
    .convo-item.active { background: rgba(255,255,255,0.1); }
    .convo-item-icon {
      width: 1.25rem;
      height: 1.25rem;
      flex-shrink: 0;
      color: #9ca3af;
    }
    .convo-item-text {
      flex: 1;
      min-width: 0;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    
    .copy-code-btn { 
      position: absolute; 
      top: 8px; 
      right: 8px; 
      background: rgba(75, 85, 99, 0.8); 
      color: #fff; 
      border: none; 
      padding: 4px 10px; 
      border-radius: 4px; 
      font-size: 12px; 
      cursor: pointer; 
      opacity: 0.7; 
      transition: opacity 0.2s;
    }
    .copy-code-btn:hover { opacity: 1; }
    .tree-output { white-space: pre; font-family: 'Fira Code', Consolas, Monaco, monospace; }
    
    /* Hide bg-hint when messages exist */
    #messages:has(.msg) .bg-hint { display: none; }
    
    /* Hide scrollbar when empty (no messages) - show when messages exist */
    html:has(#messages:not(:has(.msg))) { overflow: hidden; }
    
    /* No animation on collapse - instant switch */
    .sidebar-transition { transition: transform 0.3s ease-in-out; }
    
    /* Base sidebar padding - applies to all screen sizes */
    .sidebar-header { padding-left: 8px !important; padding-right: 0.5rem; }
    .nav-item { padding-left: 12px !important; }
    
    /* Fixed icon positioning - icons always at same X position (centered in 44px) */
    @media (min-width: 1024px) {
      /* Logo: 28px wide, needs 8px left margin to center in 44px */
      .sidebar-header > div > div:first-child { padding: 0; }
      
      /* When expanded, collapse button goes to right */
      .sidebar-expanded .sidebar-header > div { justify-content: space-between; }
    }
    
    /* Default: hide collapsed-only elements */
    .sidebar-collapsed-only { display: none; }
    
    /* Sidebar collapse behavior - large screens only */
    @media (min-width: 1024px) {
      .sidebar-expanded { width: 256px; } /* w-64 */
      .sidebar-collapsed { width: 44px; } /* slightly wider for better icons */
      .sidebar-collapsed .sidebar-label { display: none; }
      .sidebar-collapsed .sidebar-hide-collapsed { display: none; }
      .sidebar-collapsed #search-section { display: none; }
      .sidebar-collapsed #quick-actions { display: none; }
      
      /* Show/hide elements based on collapsed state */
      .sidebar-expanded .sidebar-collapsed-only { display: none !important; }
      .sidebar-collapsed .sidebar-collapsed-only { display: flex !important; }
      .sidebar-expanded .sidebar-expanded-only { display: flex; }
      .sidebar-collapsed .sidebar-expanded-only { display: none !important; }
      
      /* Adjust main content when sidebar is collapsed */
      #main-content { transition: margin-left 0.3s ease-in-out; }
      
      /* Show expand icon when collapsed, collapse icon when expanded */
      .sidebar-expanded #sidebar-expand-icon { display: none; }
      .sidebar-expanded #sidebar-collapse-icon { display: block; }
      .sidebar-collapsed #sidebar-expand-icon { display: block; }
      .sidebar-collapsed #sidebar-collapse-icon { display: none; }
      
      /* Icons when collapsed - hide labels only, icons stay in place */
      .sidebar-collapsed .footer-icon { width: 1.25rem; height: 1.25rem; }
      .sidebar-collapsed .user-avatar { width: 1.375rem; height: 1.375rem; font-size: 0.625rem; }
      
      /* Keep logo size fixed regardless of collapse state */
      .sidebar-header img { width: 1.75rem !important; height: 1.75rem !important; min-width: 1.75rem; min-height: 1.75rem; }
      
      /* Hide conversation list when collapsed */
      .sidebar-collapsed #conversations-section h3 { display: none; }
    }
    
    /* Message styles - mobile first, messenger-like */
    .msg { 
      max-width: 100%;
      width: auto;
      padding: 12px 16px;
      border-radius: 12px;
      animation: msgSlide 0.3s ease-out;
      word-wrap: break-word;
      overflow-wrap: break-word;
      overflow: visible !important;
    }
    
    /* User messages align right */
    .msg.user { 
      background: linear-gradient(135deg, #4f46e5, #7c3aed);
      color: white;
      margin-left: auto;
      margin-right: 0;
      align-self: flex-end;
    }
    
    /* Assistant messages align left */
    .msg.assistant { 
      background: #f3f4f6;
      border: 1px solid #e5e7eb;
      margin-left: 0;
      margin-right: auto;
      align-self: flex-start;
      overflow: visible !important;
    }
    
    /* Short messages stay narrower on larger screens */
    @media (min-width: 641px) {
      .msg.short {
        max-width: 60%;
      }
    }
    .dark .msg.assistant { 
      background: #1f2937;
      border: 1px solid #374151;
    }
    .msg .meta {
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      opacity: 0.7;
      margin-bottom: 6px;
      font-weight: 500;
    }
    .msg.user .meta { color: rgba(255,255,255,0.8); }
    .msg.assistant .meta { color: #6b7280; }
    .dark .msg.assistant .meta { color: #9ca3af; }
    .msg .content { line-height: 1.6; }
    .msg .content p { margin: 0 0 8px 0; }
    .msg .content p:last-child { margin-bottom: 0; }
    .msg .content code { 
      background: rgba(0,0,0,0.1); 
      padding: 2px 6px; 
      border-radius: 4px; 
      font-size: 13px;
    }
    .dark .msg .content code { 
      background: rgba(0,0,0,0.3); 
    }
    .msg .content ul, .msg .content ol { 
      margin: 8px 0; 
      padding-left: 20px; 
    }
    .msg .content li { margin: 4px 0; }
    
    /* Light mode scrollbar */
    .dark ::-webkit-scrollbar-thumb { background: #4b5563; }
    ::-webkit-scrollbar-thumb { background: #d1d5db; }
    
    /* Sidebar custom scrollbar */
    .sidebar-scroll::-webkit-scrollbar { width: 4px; }
    .sidebar-scroll::-webkit-scrollbar-track { background: transparent; }
    .sidebar-scroll::-webkit-scrollbar-thumb { background: #6b7280; border-radius: 2px; }
    .sidebar-scroll::-webkit-scrollbar-thumb:hover { background: #9ca3af; }
    .dark .sidebar-scroll::-webkit-scrollbar-thumb { background: #4b5563; }
    .dark .sidebar-scroll::-webkit-scrollbar-thumb:hover { background: #6b7280; }
    
    /* ============ WEBSEARCH-STYLE CONVERSATION CARDS ============ */
    .activity-spinner { animation: spin 1s linear infinite; }
    @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    
    .modern-scroll::-webkit-scrollbar { width: 6px; height: 6px; }
    .modern-scroll::-webkit-scrollbar-track { background: transparent; }
    .modern-scroll::-webkit-scrollbar-thumb { background: #4b5563; border-radius: 3px; }
    .modern-scroll::-webkit-scrollbar-thumb:hover { background: #6b7280; }
    .modern-scroll { scrollbar-width: thin; scrollbar-color: #4b5563 transparent; }
    
    .site-badge { 
      display: inline-flex; align-items: center; gap: 0.25rem;
      padding: 0.25rem 0.5rem; background: #f3f4f6; 
      border-radius: 0.375rem; font-size: 0.75rem; color: #64748b;
    }
    .dark .site-badge { background: #1e293b; color: #94a3b8; }
    
    /* Reasoning timeline - Groq style */
    .reasoning-timeline { 
      position: relative; 
      padding-left: 0.5rem;
    }
    .reasoning-header {
      font-size: 0.875rem; font-weight: 500; color: #6b7280;
      cursor: pointer; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;
      position: relative;
    }
    .dark .reasoning-header { color: #9ca3af; }
    .reasoning-header:hover { color: #374151; }
    .dark .reasoning-header:hover { color: #d1d5db; }
    .reasoning-chevron { transition: transform 0.2s; width: 1rem; height: 1rem; }
    .reasoning-chevron.open { transform: rotate(180deg); }
    .reasoning-content {
      font-size: 0.8125rem; line-height: 1.6; color: #6b7280;
      padding-right: 0.5rem; max-height: 300px; overflow-y: auto; overflow-x: hidden;
      position: relative;
    }
    .dark .reasoning-content { color: #9ca3af; }
    /* Each reasoning step row */
    .reasoning-item {
      display: flex;
      align-items: stretch;
      gap: 1rem;
      padding-left: 0.375rem;
    }
    /* Left column with dot and line */
    .reasoning-item-indicator {
      display: flex;
      flex-direction: column;
      width: 0.75rem;
      flex-shrink: 0;
      align-items: center;
      position: relative;
    }
    .reasoning-item-dot {
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: #9ca3af;
      margin-top: 0.25rem;
      flex-shrink: 0;
    }
    .dark .reasoning-item-dot { background: #6b7280; }
    /* Green dot for the last/latest reasoning step */
    .reasoning-item-dot-green {
      background: #22c55e !important;
    }
    .dark .reasoning-item-dot-green { background: #4ade80 !important; }
    .reasoning-item-line {
      position: absolute;
      top: 10px;
      width: 1px;
      background: #d1d5db;
      height: 100%;
    }
    .dark .reasoning-item-line { background: #4b5563; }
    .reasoning-item:last-child .reasoning-item-line {
      display: none;
    }
    /* Text content */
    .reasoning-item-text {
      padding-bottom: 1rem;
      flex: 1;
    }
    .reasoning-item-text p { margin: 0; padding-top: 0; }
    
    .response-label { font-size: 0.875rem; font-weight: 500; color: #6b7280; margin-bottom: 0.5rem; }
    .dark .response-label { color: #9ca3af; }
    
    /* Action buttons */
    .action-btn {
      display: inline-flex; align-items: center; justify-content: center;
      width: 2rem; height: 2rem; border-radius: 0.375rem;
      color: #6b7280; transition: all 0.15s; background: transparent; border: none; cursor: pointer;
    }
    .dark .action-btn { color: #9ca3af; }
    .action-btn:hover { background: #e5e7eb; color: #374151; }
    .dark .action-btn:hover { background: #374151; color: #e5e7eb; }
    .action-btn.active { color: #3b82f6; }
    .dark .action-btn.active { color: #60a5fa; }
    .action-btn svg { width: 1.125rem; height: 1.125rem; }
    
    /* Action group with dropdown */
    .action-group { position: relative; }
    .action-more-dropdown,
    .dropdown-menu,
    .card-more-menu {
      position: absolute;
      bottom: 100%;
      right: 0;
      margin-bottom: 0.5rem;
      background: linear-gradient(135deg, #ffffff 0%, #f9fafb 100%);
      border: 1px solid #e5e7eb;
      border-radius: 0.75rem;
      padding: 0.375rem;
      min-width: 200px;
      box-shadow: 0 20px 40px rgba(0,0,0,0.1), 0 0 0 1px rgba(0,0,0,0.05) inset;
      z-index: 50;
      backdrop-filter: blur(12px);
      animation: dropdownFadeIn 0.15s ease-out;
    }
    .dark .action-more-dropdown,
    .dark .dropdown-menu,
    .dark .card-more-menu {
      background: linear-gradient(135deg, #1f2937 0%, #111827 100%);
      border-color: rgba(99, 102, 241, 0.2);
      box-shadow: 0 20px 40px rgba(0,0,0,0.4), 0 0 0 1px rgba(255,255,255,0.05) inset;
    }
    @keyframes dropdownFadeIn {
      from { opacity: 0; transform: translateY(8px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .dropdown-item {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.75rem 1rem;
      border-radius: 0.5rem;
      color: #374151;
      font-size: 0.875rem;
      cursor: pointer;
      transition: all 0.15s ease;
    }
    .dark .dropdown-item { color: #e5e7eb; }
    .dropdown-item:hover { 
      background: rgba(99, 102, 241, 0.1); 
      color: #1f2937;
    }
    .dark .dropdown-item:hover { 
      background: rgba(99, 102, 241, 0.15); 
      color: #fff;
    }
    .dropdown-item:hover svg { color: #6366f1; }
    .dark .dropdown-item:hover svg { color: #a5b4fc; }
    .dropdown-item svg { 
      width: 1.125rem; 
      height: 1.125rem; 
      color: #9ca3af;
      transition: color 0.15s;
    }
    .dark .dropdown-item svg { color: #6b7280; }
    
    /* Citations */
    .citation {
      display: inline-flex; align-items: center; gap: 0.25rem;
      padding: 0.125rem 0.5rem; background: #f3f4f6; border: 1px solid #e5e7eb;
      border-radius: 1rem; font-size: 0.75rem; color: #64748b;
      text-decoration: none; transition: all 0.15s;
    }
    .dark .citation { background: #1e293b; border-color: #374151; color: #94a3b8; }
    .citation:hover { background: #e5e7eb; color: #374151; border-color: #d1d5db; }
    .dark .citation:hover { background: #374151; color: #e5e7eb; border-color: #4b5563; }
    .citation-num {
      display: inline-flex; align-items: center; justify-content: center;
      width: 1.25rem; height: 1.25rem; background: #9ca3af;
      border-radius: 50%; font-size: 0.625rem; font-weight: 600; color: #ffffff;
    }
    .dark .citation-num { background: #4b5563; color: #e5e7eb; }
    
    /* Sources stack */
    .sources-stack {
      display: flex; align-items: center; gap: 0.5rem; margin-left: auto;
      cursor: pointer; padding: 0.25rem 0.5rem; border-radius: 0.375rem; transition: background 0.15s;
    }
    .sources-stack:hover { background: #e5e7eb; }
    .dark .sources-stack:hover { background: #374151; }
    .sources-icons { display: flex; align-items: center; }
    .sources-icons img {
      width: 1.5rem; height: 1.5rem; border-radius: 50%; border: 2px solid #ffffff;
      background: #e5e7eb; object-fit: cover;
    }
    .dark .sources-icons img { border-color: #1f2937; background: #374151; }
    .sources-icons img:not(:first-child) { margin-left: -0.5rem; }
    .sources-label { font-size: 0.875rem; color: #6b7280; }
    .dark .sources-label { color: #9ca3af; }
    .sources-stack:hover .sources-label { color: #374151; }
    .dark .sources-stack:hover .sources-label { color: #e5e7eb; }
    
    /* Drag and drop styling */
    #composer.drag-over {
      outline: 2px dashed #6366f1;
      outline-offset: -2px;
      background: rgba(99, 102, 241, 0.05);
    }
    .dark #composer.drag-over {
      background: rgba(99, 102, 241, 0.1);
    }
    
    /* Conversation Cards */
    .convo-history { display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 1.5rem; }
    .convo-card {
      background: linear-gradient(135deg, #ffffff 0%, #f9fafb 100%);
      border: 1px solid #e5e7eb; border-radius: 0.75rem; overflow: hidden;
    }
    .dark .convo-card {
      background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
      border-color: #334155;
    }
    .convo-card-header {
      display: flex; align-items: center; gap: 0.75rem;
      padding: 0.875rem 1rem; cursor: pointer; transition: background 0.15s;
    }
    .convo-card-header:hover { background: rgba(99, 102, 241, 0.05); }
    .dark .convo-card-header:hover { background: rgba(99, 102, 241, 0.1); }
    .convo-card-icon {
      width: 2rem; height: 2rem; border-radius: 0.5rem;
      display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .convo-card-icon.search { background: #3b82f6; }
    .convo-card-icon.weather { background: #f59e0b; }
    .convo-card-icon.news { background: #ef4444; }
    .convo-card-icon.general { background: #6366f1; }
    .convo-card-icon svg { width: 1rem; height: 1rem; color: white; }
    .convo-card-info { flex: 1; min-width: 0; }
    .convo-card-query {
      font-weight: 500; color: #1f2937; font-size: 0.9375rem;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .dark .convo-card-query { color: #e2e8f0; }
    .convo-card-meta { font-size: 0.75rem; color: #9ca3af; margin-top: 0.125rem; }
    .dark .convo-card-meta { color: #64748b; }
    .convo-card-chevron {
      width: 1.25rem; height: 1.25rem; color: #9ca3af;
      transition: transform 0.2s; flex-shrink: 0;
    }
    .dark .convo-card-chevron { color: #6b7280; }
    .convo-card-chevron.collapsed { transform: rotate(-90deg); }
    .convo-card-body {
      border-top: 1px solid #e5e7eb; padding: 1rem;
    }
    .dark .convo-card-body { border-top-color: #334155; }
    .convo-card-body.collapsed { display: none; }
    
    /* Prose / HTML content styling */
    .prose { line-height: 1.7; color: #374151; }
    .dark .prose { color: #d1d5db; }
    .prose h1, .prose h2, .prose h3 { color: #1f2937; margin-top: 1em; }
    .dark .prose h1, .dark .prose h2, .dark .prose h3 { color: #e2e8f0; }
    .prose p { margin: 0.5em 0; }
    .prose table { width: 100%; border-collapse: collapse; margin: 1em 0; }
    .prose th, .prose td { border: 1px solid #e5e7eb; padding: 0.5em; text-align: left; color: #374151; }
    .dark .prose th, .dark .prose td { border-color: #374151; color: #d1d5db; }
    .prose th { background: #f3f4f6; color: #1f2937; font-weight: 600; }
    .dark .prose th { background: #1e293b; color: #e2e8f0; }
    .prose strong { color: #111827; }
    .dark .prose strong { color: #f8fafc; }
    .prose ul, .prose ol { margin: 0.5em 0; padding-left: 1.5em; }
    .prose li { margin: 0.25em 0; }
    
    /* Code blocks - base styling for unwrapped pre elements */
    .prose pre {
      background: #f5f5f5;
      border: 1px solid #e5e7eb;
      border-radius: 0.5rem;
      padding: 1rem;
      overflow-x: auto;
      margin: 1em 0;
    }
    .dark .prose pre {
      background: #0d1117;
      border-color: #30363d;
    }
    .prose pre code {
      background: transparent;
      padding: 0;
      font-size: 0.8125rem;
      line-height: 1.6;
      color: #1f2937;
      font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, "Liberation Mono", monospace;
    }
    .dark .prose pre code {
      color: #e6edf3;
    }
    .prose code {
      background: #f3f4f6;
      padding: 0.125rem 0.375rem;
      border-radius: 0.25rem;
      font-size: 0.8125rem;
      color: #db2777;
      font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, "Liberation Mono", monospace;
    }
    .dark .prose code {
      background: #1e293b;
      color: #f472b6;
    }
    
    /* Code block - full height, no internal scroll */
    .code-block-wrapper {
      position: relative;
      margin: 1em 0;
      border-radius: 0.5rem;
      border: 1px solid #e5e7eb;
      background: #f9fafb;
      overflow: visible;
      display: flex;
      flex-direction: column;
    }
    .dark .code-block-wrapper {
      border-color: #30363d;
      background: #0d1117;
    }

    /* Header with language label and buttons */
    .code-block-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      background: #f3f4f6;
      padding: 0rem 0.7rem;
      font-size: 0.75rem;
      color: #6b7280;
      min-height: 2.375rem;
      border-radius: 0.5rem 0.5rem 0 0;
      border-bottom: 1px solid #e5e7eb;
      position: relative;
    }
    .dark .code-block-header {
      background: #161b22;
      color: #8b949e;
      border-bottom-color: #30363d;
    }

    /* Buttons container - will be made sticky via JavaScript */
    .code-header-buttons {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      font-size: 0.75rem;
      min-height: calc(2.375rem - 2px); /* match header height minus top/bottom borders */
    }
    
    /* When buttons are stuck (applied via JS) - no visual change, just positioning */
    .code-header-buttons.stuck {
      position: fixed;
      top: 56px;
      left: auto; /* will be set by JS for precise positioning */
      z-index: 45;
      /* No background, border, padding changes - maintain exact appearance */
    }
    
    /* Full height code area */
    .code-content {
      display: block;
      font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, monospace;
      font-size: 0.875rem;
      line-height: 1.5;
      overflow-x: auto;
      overflow-y: visible;
      flex: 1;
      min-height: 0;
    }
    
    .code-block-wrapper pre {
      margin: 0;
      border: none;
      border-radius: 0;
      background: transparent;
      padding: 0;
    }
    .code-block-wrapper code {
      display: block;
      background: transparent;
    }
    
    /* Code table with horizontal scroll */
    .code-table {
      display: table;
      width: 100%;
      border-collapse: collapse;
    }
    .code-row {
      display: table-row;
    }
    .code-row:hover {
      background: rgba(110, 118, 129, 0.05);
    }
    .dark .code-row:hover {
      background: rgba(110, 118, 129, 0.1);
    }
    .code-line-num {
      display: table-cell;
      padding: 0 0.75rem;
      text-align: right;
      color: #9ca3af;
      user-select: none;
      vertical-align: top;
      white-space: nowrap;
      border-right: 1px solid #e5e7eb;
      background: #f9fafb;
      position: sticky;
      left: 0;
    }
    .dark .code-line-num {
      color: #484f58;
      border-right-color: #21262d;
      background: #0d1117;
    }
    .code-line-text {
      display: table-cell;
      padding: 0 1rem;
      white-space: pre;
      color: #1f2937;
    }
    .dark .code-line-text {
      color: #e6edf3;
    }
    /* Code action buttons - small, text-like, transparent, no vertical padding */
    .code-action-btn {
      display: inline-flex;
      align-items: center;
      gap: 0.25rem;
      padding: 0 0.375rem;
      margin: 0;
      background: transparent;
      border: none;
      border-radius: 0;
      color: #6b7280;
      font-size: 0.75rem;
      line-height: 1;
      cursor: pointer;
      transition: all 0.15s ease-in-out;
      white-space: nowrap;
      position: relative;
      z-index: 1;
    }
    .dark .code-action-btn {
      color: #8b949e;
    }
    .code-action-btn:last-child { margin-right: 0; }
    .code-action-btn:hover { 
      background: rgba(107, 114, 128, 0.15); 
      color: #374151; 
      border-radius: 0.25rem; 
    }
    .dark .code-action-btn:hover { 
      background: rgba(48, 54, 61, 0.5); 
      color: #e6edf3; 
    }
    .code-action-btn svg { width: 0.75rem; height: 0.75rem; vertical-align: -0.1rem; }
    .code-action-btn.save-btn:hover { color: #10b981; }
    .dark .code-action-btn.save-btn:hover { color: #3fb950; }
    .code-copy-btn {
      display: inline-flex;
      align-items: center;
      gap: 0.25rem;
      padding: 0.25rem 0.5rem;
      margin-right: 0.5rem;
      background: rgba(243, 244, 246, 0.8);
      border: 1px solid #e5e7eb;
      border-radius: 0.25rem;
      color: #6b7280;
      font-size: 0.75rem;
      cursor: pointer;
      transition: all 0.15s ease-in-out;
      position: relative;
      z-index: 1;
    }
    .dark .code-copy-btn {
      background: rgba(33, 38, 45, 0.8);
      border-color: #30363d;
      color: #8b949e;
    }
    .code-copy-btn:last-child { margin-right: 0; }
    .code-copy-btn:hover { 
      background: rgba(229, 231, 235, 0.9); 
      color: #374151; 
      border-color: #d1d5db; 
    }
    .dark .code-copy-btn:hover { 
      background: rgba(48, 54, 61, 0.9); 
      color: #e6edf3; 
      border-color: #484f58; 
    }
    .code-copy-btn svg { width: 0.875rem; height: 0.875rem; }
    
    /* Active state for Code/Preview toggle buttons */
    .code-action-btn.active { 
      color: #3b82f6;
      background: rgba(59, 130, 246, 0.1);
      border-bottom: 2px solid #3b82f6;
      padding-bottom: calc(0px - 2px);
    }
    .dark .code-action-btn.active { 
      color: #58a6ff;
      background: rgba(88, 166, 255, 0.1);
      border-bottom-color: #58a6ff;
    }
    
    /* Preview Iframe */
    .code-preview-iframe {
      width: 100%;
      min-height: 300px;
      border: none;
      background: #fff;
      border-radius: 0 0 0.5rem 0.5rem;
    }
    .dark .code-preview-iframe {
      background: #1f2937;
    }
  </style>
</head>
<body class="bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100 min-h-screen transition-colors duration-200">
  
  <!-- Mobile Header with Hamburger -->
  <header class="lg:hidden fixed top-0 left-0 right-0 z-40 bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between px-4 py-2">
    <div class="flex items-center gap-2">
      <button id="mobile-menu-toggle" class="p-2 rounded-lg hover:bg-indigo-100 dark:hover:bg-indigo-900/30 text-gray-600 dark:text-gray-300 hover:text-indigo-700 dark:hover:text-indigo-300" aria-label="Toggle menu">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
      </button>
      <span class="w-2 h-2 bg-green-500 rounded-full hidden min-[350px]:block"></span>
      <span class="text-sm font-medium text-gray-700 dark:text-gray-200 hidden min-[350px]:block">Ginto AI</span>
    </div>
    <?php if (empty($isLoggedIn)): ?>
    <div class="flex items-center gap-1">
      <a href="/login" class="flex items-center gap-1 px-2 py-1.5 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors text-xs font-medium" title="Login">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
        </svg>
        <span class="hidden min-[400px]:inline">Login</span>
      </a>
      <a href="/register" class="flex items-center gap-1 px-2 py-1.5 rounded-lg text-green-600 dark:text-green-400 hover:bg-green-100 dark:hover:bg-green-900/30 transition-colors text-xs font-medium" title="Register">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
        </svg>
        <span class="hidden min-[400px]:inline">Register</span>
      </a>
      <a href="https://github.com/nickolasbautista/ginto" target="_blank" class="p-1.5 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors" title="Star us on GitHub">
        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg>
      </a>
      <button id="mobile-theme-toggle" class="p-1.5 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors" title="Toggle theme">
        <svg class="w-4 h-4 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
        </svg>
        <svg class="w-4 h-4 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
        </svg>
      </button>
    </div>
    <?php else: ?>
    <div class="flex items-center gap-1">
      <a href="https://github.com/nickolasbautista/ginto" target="_blank" class="p-1.5 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors" title="Star us on GitHub">
        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg>
      </a>
      <button id="mobile-theme-toggle" class="p-1.5 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors" title="Toggle theme">
        <svg class="w-4 h-4 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
        </svg>
        <svg class="w-4 h-4 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
        </svg>
      </button>
      <button id="mobile-settings" class="p-1.5 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-indigo-100 dark:hover:bg-indigo-900/30 transition-colors" aria-label="Settings">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z"/>
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
        </svg>
      </button>
    </div>
    <?php endif; ?>
  </header>

  <!-- Sidebar - Collapsible design -->
  <aside id="sidebar" class="sidebar-expanded w-64 bg-white dark:bg-gray-900 flex flex-col fixed inset-y-0 left-0 z-50 sidebar-transition lg:translate-x-0 -translate-x-full text-gray-900 dark:text-gray-100 overflow-hidden border-r border-gray-200 dark:border-gray-800" role="navigation" aria-label="Main navigation">
    
    <!-- Top Header Row - Always visible with logo -->
    <div class="sidebar-header py-2">
      <!-- Logo and controls - always visible -->
      <div class="flex items-center justify-between">
        <!-- Logo and text separated for consistency -->
        <div class="flex items-center gap-2">
          <!-- Logo with chevron overlay for expand toggle (when collapsed) -->
          <div class="relative group">
            <a href="/" id="logo-link" class="block" title="Ginto Home">
              <img src="/assets/images/ginto.png" alt="Ginto" class="w-7 h-7 rounded flex-shrink-0" onerror="this.style.display='none'">
            </a>
            <!-- Chevron overlay (desktop only, expand - shown when collapsed) -->
            <button id="sidebar-expand-toggle" class="hidden absolute inset-0 w-7 h-7 items-center justify-center bg-transparent rounded opacity-75 hover:opacity-100 transition-opacity text-white z-10" title="Expand sidebar" aria-label="Expand sidebar">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
              </svg>
            </button>
          </div>
          <!-- Text separate from logo -->
          <span class="sidebar-label text-lg font-semibold bg-gradient-to-r from-indigo-500 to-purple-500 bg-clip-text text-transparent">Ginto</span>
        </div>
        <!-- Close/Collapse buttons - stacked in same position -->
        <div class="flex items-center gap-1">
          <!-- Desktop collapse button (shown when expanded) -->
          <button id="sidebar-collapse-toggle" class="lg:flex hidden p-1 rounded hover:bg-indigo-100 dark:hover:bg-indigo-900/30 transition-colors text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400" title="Collapse sidebar" aria-label="Collapse sidebar">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
          </button>
          <!-- Mobile close button (X) -->
          <button id="sidebar-close-mobile" class="lg:hidden p-1 rounded hover:bg-indigo-100 dark:hover:bg-indigo-900/30 transition-colors text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400" title="Close sidebar" aria-label="Close sidebar">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>
      </div>
    </div>
    
    <!-- Nav Items (icon-only when collapsed) -->
    <div class="pb-2 space-y-0.5">
      <!-- New Chat -->
      <button id="new_chat" class="nav-item w-full flex items-center gap-2 py-1.5 rounded-md hover:bg-indigo-50 dark:hover:bg-indigo-900/20 text-gray-700 dark:text-gray-300 hover:text-indigo-700 dark:hover:text-indigo-300 text-sm transition-colors group">
        <svg class="nav-icon w-5 h-5 text-gray-500 dark:text-gray-400 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/>
        </svg>
        <span class="sidebar-label">New chat</span>
      </button>
      
      <!-- Search icon + Search Bar (icon overlays the search input) -->
      <div class="nav-item relative flex items-center py-1.5">
        <input id="convo-search" type="search" placeholder="Search chats" autocomplete="off" class="sidebar-label absolute py-1.5 pl-8 pr-3 bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-md text-sm text-gray-800 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500" style="left: 6px; right: 6px;">
        <svg class="nav-icon w-5 h-5 text-gray-500 dark:text-gray-400 flex-shrink-0 relative z-10" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
      </div>
      
      <!-- Courses -->
      <a href="/courses" id="open-courses" class="nav-item flex items-center gap-2 py-1.5 rounded-md hover:bg-indigo-50 dark:hover:bg-indigo-900/20 text-gray-700 dark:text-gray-300 hover:text-indigo-700 dark:hover:text-indigo-300 text-sm transition-colors group">
        <svg class="nav-icon w-5 h-5 text-gray-500 dark:text-gray-400 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5"/>
        </svg>
        <span class="sidebar-label">Courses</span>
      </a>
      
      <!-- Masterclasses -->
      <a href="/masterclass" id="open-masterclass" class="nav-item flex items-center gap-2 py-1.5 rounded-md hover:bg-teal-50 dark:hover:bg-teal-900/20 text-gray-700 dark:text-gray-300 hover:text-teal-700 dark:hover:text-teal-300 text-sm transition-colors group">
        <svg class="nav-icon w-5 h-5 text-gray-500 dark:text-gray-400 group-hover:text-teal-600 dark:group-hover:text-teal-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z"/>
        </svg>
        <span class="sidebar-label">Masterclasses</span>
      </a>
      
      <!-- Dashboard (logged-in users only, requires ENABLE_DASHBOARD=true) -->
      <?php 
      $enableDashboard = filter_var(getenv('ENABLE_DASHBOARD') ?: ($_ENV['ENABLE_DASHBOARD'] ?? 'false'), FILTER_VALIDATE_BOOLEAN);
      if (($isLoggedIn ?? false) && $enableDashboard): 
      ?>
      <a href="/dashboard" id="open-dashboard" class="nav-item flex items-center gap-2 py-1.5 rounded-md hover:bg-violet-50 dark:hover:bg-violet-900/20 text-gray-700 dark:text-gray-300 hover:text-violet-700 dark:hover:text-violet-300 text-sm transition-colors group">
        <svg class="nav-icon w-5 h-5 text-gray-500 dark:text-gray-400 group-hover:text-violet-600 dark:group-hover:text-violet-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/>
        </svg>
        <span class="sidebar-label">Dashboard</span>
        <span class="sidebar-label text-xs text-amber-500 dark:text-amber-400">(Beta)</span>
      </a>
      <?php endif; ?>
      
      <!-- My Files -->
      <a href="#" id="open-my-files" class="nav-item flex items-center gap-2 py-1.5 rounded-md hover:bg-indigo-50 dark:hover:bg-indigo-900/20 text-gray-700 dark:text-gray-300 hover:text-indigo-700 dark:hover:text-indigo-300 text-sm transition-colors group">
        <svg class="nav-icon w-5 h-5 text-gray-500 dark:text-gray-400 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z"/>
        </svg>
        <span class="sidebar-label">My Files</span>
        <span id="sandbox-status-indicator" class="hidden sidebar-label">
          <span class="w-2 h-2 rounded-full bg-gray-400 inline-block" title="Sandbox not installed"></span>
        </span>
      </a>
      
      <!-- Console (Admin Only) -->
      <?php if (!empty($isAdmin)): ?>
      <a href="#" id="open-console" class="nav-item flex items-center gap-2 py-1.5 rounded-md hover:bg-indigo-50 dark:hover:bg-indigo-900/20 text-gray-700 dark:text-gray-300 hover:text-indigo-700 dark:hover:text-indigo-300 text-sm transition-colors group">
        <svg class="nav-icon w-5 h-5 text-gray-500 dark:text-gray-400 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 7.5l3 2.25-3 2.25m4.5 0h3m-9 8.25h13.5A2.25 2.25 0 0021 18V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v12a2.25 2.25 0 002.25 2.25z"/>
        </svg>
        <span class="sidebar-label">Console</span>
      </a>
      <?php endif; ?>
    </div>
    
    <!-- Divider -->
    <div class="mx-2 border-t border-gray-200 dark:border-gray-800"></div>
    
    <!-- Conversations Section (hidden when collapsed) -->
    <div class="sidebar-expanded-only flex-1 overflow-y-auto sidebar-scroll pt-2" style="flex-direction: column;">
      <h2 class="sidebar-label text-xs font-medium text-gray-500 uppercase tracking-wider px-4 mb-2">Conversations</h2>
      <nav id="conversation-list" role="list" class="px-2">
        <div class="text-sm text-gray-500 dark:text-gray-400 px-2 py-2 sidebar-label">No conversations yet</div>
      </nav>
    </div>
    
    <!-- Divider -->
    <div class="mx-2 border-t border-gray-200 dark:border-gray-800 mt-auto"></div>
    
    <!-- Footer - Admin & Settings (always at bottom) -->
    <div class="pb-2 space-y-0.5">
      <!-- MCP Status (admin only) -->
      <button id="open-mcp-tab" class="hidden nav-item w-full flex items-center gap-2 py-1.5 rounded-md hover:bg-indigo-50 dark:hover:bg-indigo-900/20 text-gray-700 dark:text-gray-300 hover:text-indigo-700 dark:hover:text-indigo-300 text-sm transition-colors group" data-admin-only="true">
        <svg class="nav-icon w-5 h-5 text-gray-500 dark:text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
          <circle cx="12" cy="12" r="3" id="mcp-status-dot-inner"/>
        </svg>
        <span id="mcp-badge" class="sidebar-label">MCP Status</span>
      </button>
      
      <!-- Settings -->
      <button id="toggle-settings" class="nav-item w-full flex items-center gap-2 py-1.5 rounded-md hover:bg-indigo-50 dark:hover:bg-indigo-900/20 text-gray-700 dark:text-gray-300 hover:text-indigo-700 dark:hover:text-indigo-300 text-sm transition-colors group" aria-label="Settings">
        <svg class="nav-icon w-5 h-5 text-gray-500 dark:text-gray-400 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z"/>
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
        </svg>
        <span class="sidebar-label">Settings</span>
      </button>
    </div>
    
    <!-- User Account (always at bottom) -->
    <div class="pb-2 space-y-0.5 border-t border-gray-200 dark:border-gray-800">
      <?php if (!empty($isLoggedIn)): ?>
      <div class="nav-item flex items-center gap-2 py-1.5 rounded-md hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition-colors cursor-pointer group">
        <div class="nav-icon w-5 h-5 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white text-xs font-medium flex-shrink-0">
          <?= strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)) ?>
        </div>
        <div class="flex-1 min-w-0 sidebar-label">
          <span class="text-sm text-gray-700 dark:text-gray-200 truncate block"><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></span>
        </div>
        <a id="logout-link" href="/logout" class="sidebar-label p-1 rounded hover:bg-gray-200 dark:hover:bg-gray-700 text-red-400 hover:text-red-500 transition-all" title="Logout">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
          </svg>
        </a>
      </div>
      <?php else: ?>
      <a href="/login" class="nav-item flex items-center gap-2 py-1.5 rounded-md hover:bg-indigo-50 dark:hover:bg-indigo-900/20 text-gray-700 dark:text-gray-300 hover:text-indigo-700 dark:hover:text-indigo-300 text-sm transition-colors group">
        <div class="nav-icon w-5 h-5 rounded-full bg-gray-200 dark:bg-gray-800 flex items-center justify-center flex-shrink-0">
          <svg class="w-3 h-3 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/>
          </svg>
        </div>
        <span class="sidebar-label">Login</span>
      </a>
      <a href="/register" class="nav-item flex items-center gap-2 py-1.5 rounded-md hover:bg-green-50 dark:hover:bg-green-900/20 text-gray-700 dark:text-gray-300 hover:text-green-700 dark:hover:text-green-300 text-sm transition-colors group">
        <div class="nav-icon w-5 h-5 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center flex-shrink-0">
          <svg class="w-3 h-3 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z"/>
          </svg>
        </div>
        <span class="sidebar-label">Register</span>
      </a>
      <?php endif; ?>
    </div>
  </aside>
  
  <!-- Mobile Sidebar Overlay -->
  <div id="sidebar-overlay" class="fixed inset-0 bg-black/50 z-20 lg:hidden hidden" onclick="toggleSidebar()"></div>
  
  <!-- Main Content - offset by sidebar width on large screens -->
  <main id="main-content" class="flex-1 flex flex-col min-h-screen pt-14 lg:pt-0 lg:ml-64">
    <?php if (($paymentStatus ?? null) === 'pending'): ?>
    <!-- Premium Account Pending Banner -->
    <div class="bg-gradient-to-r from-amber-500 to-orange-500 text-white px-4 py-3 text-center">
      <div class="flex items-center justify-center gap-2 flex-wrap">
        <svg class="w-5 h-5 flex-shrink-0 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span class="font-semibold">Premium Account Pending</span>
        <span class="hidden sm:inline">—</span>
        <span class="text-white/90 text-sm">Your payment is being verified. Premium features will be unlocked once confirmed.</span>
        <button type="button" onclick="showTransactionDetails()" class="ml-2 px-2 py-0.5 bg-white/20 hover:bg-white/30 rounded text-xs font-medium transition underline-offset-2 hover:underline">
          <i class="fas fa-receipt mr-1"></i>See transaction details
        </button>
      </div>
    </div>
    <?php endif; ?>
    <!-- Header - hidden on mobile since we have fixed mobile header -->
    <header id="main-header" class="hidden lg:block sticky top-0 z-50 bg-white/80 dark:bg-gray-950/80 backdrop-blur-sm border-b border-gray-200 dark:border-gray-800">
      <div class="flex items-center justify-between px-4 h-14">
        <!-- Model selector -->
        <div class="flex items-center gap-2">
          <div class="flex items-center gap-2 px-3 py-1.5 bg-gray-100 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="w-2 h-2 rounded-full bg-green-500"></div>
            <span class="text-sm text-gray-700 dark:text-gray-200" id="model-name">Ginto AI</span>
          </div>
        </div>
        
        <!-- Star on GitHub + Theme toggle -->
        <div class="flex items-center gap-2">
          <a href="https://github.com/oliverbob/ginto.ai" target="_blank" 
             class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors text-sm"
             title="Star us on GitHub">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg>
            <span>Star us</span>
          </a>
          <button id="theme-toggle" class="p-2 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-800 transition-colors" title="Toggle theme">
            <!-- Sun icon (shown in dark mode) -->
            <svg class="w-5 h-5 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
            </svg>
            <!-- Moon icon (shown in light mode) -->
            <svg class="w-5 h-5 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
            </svg>
          </button>
        </div>
      </div>
    </header>
    
    <!-- Chat Area -->
    <div class="flex-1 flex flex-col max-w-4xl mx-auto w-full px-4">
      <!-- Messages Container -->
      <div id="messages" class="pt-2 pb-6 space-y-4 flex flex-col" aria-live="polite">
        <!-- Empty State -->
        <div class="bg-hint flex flex-col items-center text-center pb-8">
          <div class="w-16 h-16 mb-6 rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center">
            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
            </svg>
          </div>
          <h2 class="text-2xl font-semibold text-gray-800 dark:text-gray-200 mb-2">Ginto Chat</h2>
          <p class="text-gray-500 dark:text-gray-400 mb-4 max-w-md">Type a message or upload files to get started. I can help you build, analyze, and create.</p>

          <!-- Promotional banner (styled like prompts) -->
          <div class="mb-6 w-full max-w-md">
            <div class="px-4 py-3 bg-gray-100 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-700 rounded-xl transition-colors">
              <div class="flex items-start gap-3">
                <div class="flex-shrink-0">
                  <svg class="w-6 h-6 text-gray-700 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 1.343-3 3v3h6v-3c0-1.657-1.343-3-3-3z"/></svg>
                </div>
                <div class="flex-1 text-sm leading-tight text-gray-700 dark:text-gray-300">
                  <strong class="block">P50,000 (≈ $1,000) worth of premium is Free</strong>
                  <span class="block">Available until December 18 for new registrants applying as <em>Platform Technolvangelists partner admin roles with their free accounts.</em>. <br /><br />
                  Additionally, the entire code stack for Ginto Chat is also for sale to those interested. Register and chat with admin to claim.</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Example prompts (loaded dynamically) -->
          <div id="welcome-prompts" class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-w-lg">
            <!-- Prompts will be fetched from /chat/prompts/ and injected here -->
            <div id="welcome-prompts-loading" class="text-sm text-gray-500">Loading prompts…</div>
          </div>
        </div>
      </div>
      
      <!-- Composer -->
      <div id="composer" class="sticky bottom-0 pb-6 pt-4 bg-gradient-to-t from-gray-50 dark:from-gray-950 via-gray-50 dark:via-gray-950 to-transparent">
        <div class="relative bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-xl">
          <!-- Attachment Preview -->
          <div id="attach-preview" class="hidden px-4 pt-3 pb-2 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-start gap-3">
              <div class="relative">
                <img id="attach-preview-img" class="w-20 h-20 object-cover rounded-lg border border-gray-300 dark:border-gray-600" src="" alt="Attached image">
                <button id="attach-remove" class="absolute -top-2 -right-2 w-5 h-5 bg-red-500 hover:bg-red-600 text-white rounded-full flex items-center justify-center text-xs shadow-md" title="Remove attachment">
                  <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                  </svg>
                </button>
              </div>
              <div class="flex-1 text-sm text-gray-600 dark:text-gray-400">
                <p id="attach-filename" class="font-medium truncate"></p>
                <p class="text-xs text-indigo-500">Image will be analyzed with vision model</p>
              </div>
            </div>
          </div>
          <textarea 
            id="prompt" 
            rows="1"
            placeholder="Ask anything..." 
            class="w-full px-4 py-4 pr-24 bg-transparent resize-none focus:outline-none text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 max-h-40"
            style="min-height: 56px;"
          ></textarea>
          
          <!-- Composer Actions -->
          <div class="absolute right-2 bottom-2 flex items-center gap-1">
            <!-- Attach file (hidden input) -->
            <input type="file" id="attach-input" accept="image/*" class="hidden">
            <button id="attach-btn" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors" title="Attach image">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
              </svg>
            </button>
            
            <!-- Send button -->
            <button id="send" class="p-2.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center" title="Send message">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 2L11 13"/>
                <path d="M22 2L15 22L11 13L2 9L22 2Z"/>
              </svg>
            </button>
          </div>
        </div>
        
        <!-- Keyboard hint (hidden on mobile) -->
        <div class="hidden md:flex items-center justify-center gap-4 mt-2 text-xs text-gray-500 dark:text-gray-600">
          <span>Press <kbd class="px-1.5 py-0.5 bg-gray-200 dark:bg-gray-800 rounded text-gray-600 dark:text-gray-400">Enter</kbd> to send</span>
          <span><kbd class="px-1.5 py-0.5 bg-gray-200 dark:bg-gray-800 rounded text-gray-600 dark:text-gray-400">Shift + Enter</kbd> for new line</span>
        </div>
      </div>
    </div>
  </main>
  
  <!-- Toast Notification Container -->
  <div id="toast-container" class="fixed bottom-4 right-4 z-[100] flex flex-col gap-2 pointer-events-none"></div>
  
  <!-- Universal Confirmation Modal -->
  <div id="confirm-modal" class="fixed inset-0 z-[110] hidden">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeConfirmModal()"></div>
    <!-- Modal Content -->
    <div class="absolute inset-0 flex items-center justify-center p-4">
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-md w-full transform transition-all scale-95 opacity-0" id="confirm-modal-content">
        <!-- Header -->
        <div class="p-5 border-b border-gray-200 dark:border-gray-700">
          <div class="flex items-center gap-3">
            <div id="confirm-modal-icon" class="w-10 h-10 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center flex-shrink-0">
              <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
              </svg>
            </div>
            <div>
              <h3 id="confirm-modal-title" class="text-lg font-semibold text-gray-900 dark:text-white">Confirm Action</h3>
              <p id="confirm-modal-message" class="text-sm text-gray-500 dark:text-gray-400 mt-1">Are you sure you want to proceed?</p>
            </div>
          </div>
        </div>
        <!-- Auto-approve checkbox (hidden by default, shown for tool execution) -->
        <div id="confirm-modal-auto-approve" class="hidden px-5 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
          <label class="flex items-center gap-3 cursor-pointer group">
            <input type="checkbox" id="confirm-modal-auto-approve-checkbox" class="w-4 h-4 text-indigo-600 bg-gray-100 dark:bg-gray-700 border-gray-300 dark:border-gray-600 rounded focus:ring-indigo-500 focus:ring-2">
            <span class="text-sm text-gray-600 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-gray-200">Don't ask again (auto-approve tool execution)</span>
          </label>
        </div>
        <!-- Actions -->
        <div class="p-4 flex justify-end gap-3 bg-gray-50 dark:bg-gray-800/50 rounded-b-xl">
          <button onclick="closeConfirmModal()" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
            Cancel
          </button>
          <button id="confirm-modal-action" class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors">
            Delete
          </button>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Premium Upgrade Modal -->
  <div id="upgrade-modal" class="fixed inset-0 z-[110] hidden">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeUpgradeModal()"></div>
    <!-- Modal Content -->
    <div class="absolute inset-0 flex items-center justify-center p-4">
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-md w-full transform transition-all scale-95 opacity-0" id="upgrade-modal-content">
        <!-- Header with gradient -->
        <div class="p-6 bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-500 rounded-t-xl">
          <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-full bg-white/20 flex items-center justify-center flex-shrink-0">
              <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
              </svg>
            </div>
            <div>
              <h3 class="text-xl font-bold text-white">Upgrade to Premium</h3>
              <p class="text-white/80 text-sm mt-1">Unlock full sandbox capabilities</p>
            </div>
          </div>
        </div>
        <!-- Content -->
        <div class="p-5">
          <p id="upgrade-modal-message" class="text-gray-600 dark:text-gray-300 mb-4">
            Command execution requires a Premium subscription to keep our sandbox infrastructure secure and sustainable.
          </p>
          <!-- Pricing -->
          <div class="bg-gradient-to-r from-indigo-50 to-purple-50 dark:from-indigo-900/20 dark:to-purple-900/20 rounded-lg p-4 mb-4 border border-indigo-200 dark:border-indigo-800">
            <div class="flex items-center justify-between">
              <div>
                <span class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">₱200</span>
                <span class="text-gray-500 dark:text-gray-400">/week</span>
              </div>
              <span class="bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 text-xs font-semibold px-2.5 py-1 rounded-full">BEST VALUE</span>
            </div>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">That's less than ₱30/day for unlimited AI-powered development!</p>
          </div>
          <!-- Features -->
          <ul class="space-y-2 mb-5">
            <li class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
              <svg class="w-5 h-5 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
              </svg>
              <span>Full tool-calling capabilities on sandbox</span>
            </li>
            <li class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
              <svg class="w-5 h-5 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
              </svg>
              <span>Execute commands (npm, pip, composer, etc.)</span>
            </li>
            <li class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
              <svg class="w-5 h-5 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
              </svg>
              <span>Run scripts and build projects</span>
            </li>
            <li class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
              <svg class="w-5 h-5 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
              </svg>
              <span>Priority support &amp; faster responses</span>
            </li>
          </ul>
        </div>
        <!-- Actions -->
        <div class="p-4 flex justify-end gap-3 bg-gray-50 dark:bg-gray-800/50 rounded-b-xl border-t border-gray-200 dark:border-gray-700">
          <button onclick="closeUpgradeModal()" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
            Maybe Later
          </button>
          <a href="/upgrade" class="px-5 py-2 text-sm font-medium text-white bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 rounded-lg transition-all shadow-lg shadow-indigo-500/25 flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
            </svg>
            Upgrade Now
          </a>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Register Required Modal (Visitor Limit) -->
  <div id="register-modal" class="fixed inset-0 z-[110] hidden">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeRegisterModal()"></div>
    <!-- Modal Content -->
    <div class="absolute inset-0 flex items-center justify-center p-4">
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-md w-full transform transition-all scale-95 opacity-0" id="register-modal-content">
        <!-- Header with gradient -->
        <div class="p-6 bg-gradient-to-r from-teal-500 via-cyan-500 to-blue-500 rounded-t-xl">
          <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-full bg-white/20 flex items-center justify-center flex-shrink-0">
              <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
              </svg>
            </div>
            <div>
              <h3 class="text-xl font-bold text-white">Free Limit Reached</h3>
              <p class="text-white/80 text-sm mt-1">Create an account to continue</p>
            </div>
          </div>
        </div>
        <!-- Content -->
        <div class="p-5">
          <p id="register-modal-message" class="text-gray-600 dark:text-gray-300 mb-4">
            You've used all 5 free messages this hour. Register for free to unlock unlimited conversations with Ginto!
          </p>
          <!-- Benefits -->
          <div class="bg-gradient-to-r from-teal-50 to-cyan-50 dark:from-teal-900/20 dark:to-cyan-900/20 rounded-lg p-4 mb-4 border border-teal-200 dark:border-teal-800">
            <div class="flex items-center gap-2 mb-2">
              <span class="text-lg font-bold text-teal-600 dark:text-teal-400">FREE</span>
              <span class="bg-teal-100 dark:bg-teal-900/50 text-teal-700 dark:text-teal-300 text-xs font-semibold px-2.5 py-1 rounded-full">NO CREDIT CARD</span>
            </div>
            <p class="text-sm text-gray-500 dark:text-gray-400">Create an account in seconds and continue chatting!</p>
          </div>
          <!-- Features -->
          <ul class="space-y-2 mb-5">
            <li class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
              <svg class="w-5 h-5 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
              </svg>
              <span>Unlimited conversations with Ginto AI</span>
            </li>
            <li class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
              <svg class="w-5 h-5 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
              </svg>
              <span>Save and sync your chat history</span>
            </li>
            <li class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
              <svg class="w-5 h-5 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
              </svg>
              <span>Personal sandbox environment</span>
            </li>
            <li class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
              <svg class="w-5 h-5 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
              </svg>
              <span>Access to all free features</span>
            </li>
          </ul>
        </div>
        <!-- Actions -->
        <div class="p-4 flex justify-end gap-3 bg-gray-50 dark:bg-gray-800/50 rounded-b-xl border-t border-gray-200 dark:border-gray-700">
          <button onclick="closeRegisterModal()" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
            Maybe Later
          </button>
          <a href="/register" class="px-5 py-2 text-sm font-medium text-white bg-gradient-to-r from-teal-500 to-cyan-500 hover:from-teal-600 hover:to-cyan-600 rounded-lg transition-all shadow-lg shadow-teal-500/25 flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
            </svg>
            Register Free
          </a>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Image Viewer Modal -->
  <div id="image-modal" class="fixed inset-0 z-[115] hidden">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" onclick="closeImageModal()"></div>
    <!-- Modal Content -->
    <div class="absolute inset-0 flex items-center justify-center p-4">
      <div class="relative max-w-4xl max-h-[90vh] w-full transform transition-all scale-95 opacity-0" id="image-modal-content">
        <!-- Top controls -->
        <div class="absolute -top-10 right-0 flex items-center gap-3">
          <!-- Zoom controls -->
          <div class="flex items-center gap-1 bg-black/50 rounded-lg px-2 py-1">
            <button onclick="zoomImage(-0.25)" class="text-white/80 hover:text-white transition-colors p-1" title="Zoom out">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM13 10H7"/>
              </svg>
            </button>
            <span id="image-zoom-level" class="text-white/80 text-sm min-w-[3rem] text-center">100%</span>
            <button onclick="zoomImage(0.25)" class="text-white/80 hover:text-white transition-colors p-1" title="Zoom in">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m3-3H7"/>
              </svg>
            </button>
            <button onclick="resetZoom()" class="text-white/80 hover:text-white transition-colors p-1 ml-1 border-l border-white/20 pl-2" title="Reset zoom">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
              </svg>
            </button>
          </div>
          <!-- Close button -->
          <button onclick="closeImageModal()" class="text-white/80 hover:text-white transition-colors">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>
        <!-- Image container -->
        <div class="bg-gray-900 rounded-xl overflow-auto shadow-2xl max-h-[85vh]" id="image-modal-container">
          <img id="image-modal-img" src="" alt="Full size image" class="mx-auto object-contain transition-transform duration-150" style="transform-origin: center center;">
        </div>
      </div>
    </div>
  </div>
  
  <!-- TTS Rate Limit Info Modal -->
  <div id="tts-limit-modal" class="fixed inset-0 z-[110] hidden">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeTtsLimitModal()"></div>
    <!-- Modal Content -->
    <div class="absolute inset-0 flex items-center justify-center p-4">
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-md w-full transform transition-all scale-95 opacity-0" id="tts-limit-modal-content">
        <!-- Header -->
        <div class="p-5 border-b border-gray-200 dark:border-gray-700">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center flex-shrink-0">
              <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2"/>
              </svg>
            </div>
            <div>
              <h3 id="tts-limit-modal-title" class="text-lg font-semibold text-gray-900 dark:text-white">Text-to-Speech Limit Reached</h3>
              <p id="tts-limit-modal-message" class="text-sm text-gray-500 dark:text-gray-400 mt-1">You've reached your TTS limit for this session.</p>
            </div>
          </div>
        </div>
        <!-- Content area for extra message -->
        <div id="tts-limit-modal-extra" class="px-5 py-3 text-sm text-gray-600 dark:text-gray-300 hidden"></div>
        <!-- Actions - will be filled dynamically -->
        <div id="tts-limit-modal-actions" class="p-4 flex justify-end gap-3 bg-gray-50 dark:bg-gray-800/50 rounded-b-xl">
          <button onclick="closeTtsLimitModal()" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
            Got it
          </button>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Session Expired Modal (for visitors) -->
  <div id="session-expired-modal" class="fixed inset-0 z-[120] hidden">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-black/70 backdrop-blur-sm"></div>
    <!-- Modal Content -->
    <div class="absolute inset-0 flex items-center justify-center p-4">
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-md w-full transform transition-all scale-95 opacity-0" id="session-expired-modal-content">
        <!-- Header -->
        <div class="p-5 border-b border-gray-200 dark:border-gray-700">
          <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-full bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center flex-shrink-0">
              <svg class="w-6 h-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
            </div>
            <div>
              <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Session Expiring Soon</h3>
              <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">To avoid losing your session, please register or login.</p>
            </div>
          </div>
        </div>
        <!-- Content -->
        <div class="p-5 space-y-4">
          <div class="bg-amber-50 dark:bg-amber-900/20 rounded-lg p-4 border border-amber-200 dark:border-amber-800">
            <p class="text-sm text-amber-800 dark:text-amber-200">
              <strong>⏰ Important:</strong> Your sandbox will remain active only for <strong>one hour</strong> while you're not logged in. 
              Register or log in to keep your sandbox and files permanently.
            </p>
          </div>
          
          <div class="bg-indigo-50 dark:bg-indigo-900/20 rounded-lg p-4">
            <p class="font-medium text-indigo-700 dark:text-indigo-300">🎁 Benefits of a free account:</p>
            <ul class="text-sm mt-2 text-indigo-600 dark:text-indigo-400 space-y-1">
              <li>• Your sandbox is saved permanently</li>
              <li>• Access your files from any device</li>
              <li>• More AI requests and features</li>
            </ul>
          </div>
        </div>
        <!-- Actions -->
        <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-b-xl flex justify-end gap-3">
          <button onclick="closeSessionExpiredModal()" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
            Maybe Later
          </button>
          <a href="/login" class="px-4 py-2 text-sm font-medium text-indigo-600 dark:text-indigo-400 bg-white dark:bg-gray-700 border border-indigo-300 dark:border-indigo-600 rounded-lg hover:bg-indigo-50 dark:hover:bg-gray-600 transition-colors inline-flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
            </svg>
            Login
          </a>
          <a href="/register" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition-colors inline-flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
            </svg>
            Register Free
          </a>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Sandbox Installation Wizard Modal -->
  <div id="sandbox-wizard-modal" class="fixed inset-0 z-[100] hidden">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" onclick="closeSandboxWizard()"></div>
    <!-- Modal Content -->
    <div class="absolute inset-0 flex items-center justify-center p-4">
      <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl max-w-lg w-full transform transition-all" id="sandbox-wizard-content">
        
        <!-- Step 1: Welcome/Introduction -->
        <div id="wizard-step-1" class="wizard-step">
          <!-- Header with icon -->
          <div class="p-6 pb-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center gap-4">
              <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-violet-500 to-purple-600 flex items-center justify-center shadow-lg">
                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                </svg>
              </div>
              <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">Welcome to Your Sandbox</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Secure isolated environment for your files</p>
              </div>
            </div>
          </div>
          
          <!-- Content -->
          <div class="p-6 space-y-4">
            <p class="text-gray-600 dark:text-gray-300">
              A <strong>sandbox</strong> is your personal, isolated workspace where you can safely create, edit, and manage files without affecting other users or system files.
            </p>
            
            <div class="space-y-3">
              <div class="flex items-start gap-3 p-3 bg-violet-50 dark:bg-violet-900/20 rounded-xl">
                <svg class="w-5 h-5 text-violet-600 dark:text-violet-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                <div>
                  <div class="font-medium text-gray-900 dark:text-white">Isolated Environment</div>
                  <div class="text-sm text-gray-600 dark:text-gray-400">Your files are stored in a secure LXC container</div>
                </div>
              </div>
              
              <div class="flex items-start gap-3 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-xl">
                <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/>
                </svg>
                <div>
                  <div class="font-medium text-gray-900 dark:text-white">Your Personal Space</div>
                  <div class="text-sm text-gray-600 dark:text-gray-400">Store code, notes, and project files</div>
                </div>
              </div>
              
              <div class="flex items-start gap-3 p-3 bg-emerald-50 dark:bg-emerald-900/20 rounded-xl">
                <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
                <div>
                  <div class="font-medium text-gray-900 dark:text-white">Run Code Safely</div>
                  <div class="text-sm text-gray-600 dark:text-gray-400">Execute scripts without risk to the system</div>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Actions -->
          <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-b-2xl flex justify-between items-center">
            <button onclick="closeSandboxWizard()" class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors">
              Maybe Later
            </button>
            <button onclick="showWizardStep(2)" class="px-5 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-500 hover:to-purple-500 rounded-xl shadow-lg shadow-violet-500/25 transition-all">
              Continue
              <svg class="w-4 h-4 inline-block ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
              </svg>
            </button>
          </div>
        </div>
        
        <!-- Step 2: Terms & Conditions -->
        <div id="wizard-step-2" class="wizard-step hidden">
          <!-- Header -->
          <div class="p-6 pb-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center gap-4">
              <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center shadow-lg">
                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
              </div>
              <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">Terms & Privacy</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Please review before proceeding</p>
              </div>
            </div>
          </div>
          
          <!-- Content -->
          <div class="p-6 space-y-4">
            <div class="max-h-48 overflow-y-auto p-4 bg-gray-50 dark:bg-gray-800 rounded-xl text-sm text-gray-600 dark:text-gray-300 space-y-3 border border-gray-200 dark:border-gray-700">
              <p class="font-semibold text-gray-900 dark:text-white">Sandbox Usage Terms</p>
              <p>By creating a sandbox, you agree to the following:</p>
              <ul class="list-disc list-inside space-y-2 pl-2">
                <li><strong>Visitor Sessions:</strong> If you are not logged in, your sandbox session is limited to <strong>one hour</strong>. Register or log in to keep your sandbox permanently.</li>
                <li><strong>File Storage:</strong> Files stored in your sandbox are associated with your account. You are responsible for backing up important data.</li>
                <li><strong>Resource Limits:</strong> Your sandbox has limited CPU, memory, and storage. Excessive usage may result in throttling.</li>
                <li><strong>No Illegal Activity:</strong> You may not use the sandbox for illegal purposes, malware distribution, or attacks on other systems.</li>
                <li><strong>Data Retention:</strong> Inactive sandboxes may be paused or archived after extended periods of inactivity.</li>
                <li><strong>Privacy:</strong> We do not actively monitor your files, but may access them for security or legal compliance purposes.</li>
                <li><strong>No Warranty:</strong> The sandbox is provided "as is" without guarantees of uptime or data preservation.</li>
              </ul>
            </div>
            
            <label class="flex items-start gap-3 cursor-pointer group">
              <input type="checkbox" id="accept-sandbox-terms" class="mt-1 w-5 h-5 rounded border-gray-300 dark:border-gray-600 text-violet-600 focus:ring-violet-500 dark:bg-gray-700">
              <span class="text-sm text-gray-700 dark:text-gray-300 group-hover:text-gray-900 dark:group-hover:text-white transition-colors">
                I have read and agree to the <strong>Sandbox Terms of Use</strong> and understand my responsibilities regarding file storage and privacy.
              </span>
            </label>
            
            <?php if (!empty($isAdmin)): ?>
            <!-- LXD Nesting Warning (Admin only) -->
            <div class="mt-4 p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl">
              <div class="flex items-start gap-2">
                <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <div class="text-xs text-amber-800 dark:text-amber-300">
                  <strong>Running inside LXD/LXC?</strong> Enable nesting on the host:<br>
                  <code class="bg-amber-100 dark:bg-amber-900/50 px-1 rounded text-[10px] block mt-1">lxc profile set default security.nesting=true</code>
                  <span class="text-[10px] opacity-75">Or for specific container: <code>lxc config set &lt;name&gt; security.nesting=true</code></span>
                </div>
              </div>
            </div>
            <?php endif; ?>
          </div>
          
          <!-- Actions -->
          <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-b-2xl flex justify-between items-center">
            <button onclick="showWizardStep(1)" class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors flex items-center gap-1">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/>
              </svg>
              Back
            </button>
            <button id="wizard-install-btn" onclick="installSandbox()" disabled class="px-5 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-500 hover:to-purple-500 rounded-xl shadow-lg shadow-violet-500/25 transition-all disabled:opacity-50 disabled:cursor-not-allowed disabled:shadow-none">
              <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
              </svg>
              Install My Sandbox
            </button>
          </div>
        </div>
        
        <!-- Step 3: Installing -->
        <div id="wizard-step-3" class="wizard-step hidden">
          <!-- Header -->
          <div class="p-6 pb-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center gap-4">
              <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-blue-500 to-cyan-600 flex items-center justify-center shadow-lg">
                <svg class="w-7 h-7 text-white animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
              </div>
              <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">Installing Sandbox</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Please wait while we set up your environment</p>
              </div>
            </div>
          </div>
          
          <!-- Content -->
          <div class="p-6 space-y-6">
            <div class="space-y-3">
              <div id="install-step-1" class="flex items-center gap-3 p-3 rounded-xl bg-gray-50 dark:bg-gray-800 transition-all">
                <div class="install-icon w-8 h-8 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                  <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z"/>
                  </svg>
                </div>
                <span class="text-sm text-gray-600 dark:text-gray-400">Creating sandbox directory...</span>
              </div>
              
              <div id="install-step-2" class="flex items-center gap-3 p-3 rounded-xl bg-gray-50 dark:bg-gray-800 transition-all">
                <div class="install-icon w-8 h-8 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                  <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z"/>
                  </svg>
                </div>
                <span class="text-sm text-gray-600 dark:text-gray-400">Launching LXC container...</span>
              </div>
              
              <div id="install-step-3" class="flex items-center gap-3 p-3 rounded-xl bg-gray-50 dark:bg-gray-800 transition-all">
                <div class="install-icon w-8 h-8 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                  <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z"/>
                  </svg>
                </div>
                <span class="text-sm text-gray-600 dark:text-gray-400">Configuring environment...</span>
              </div>
              
              <div id="install-step-4" class="flex items-center gap-3 p-3 rounded-xl bg-gray-50 dark:bg-gray-800 transition-all">
                <div class="install-icon w-8 h-8 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                  <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z"/>
                  </svg>
                </div>
                <span class="text-sm text-gray-600 dark:text-gray-400">Finalizing setup...</span>
              </div>
            </div>
            
            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 overflow-hidden">
              <div id="install-progress-bar" class="bg-gradient-to-r from-violet-500 to-purple-500 h-full rounded-full transition-all duration-500" style="width: 0%"></div>
            </div>
            
            <p id="install-status-text" class="text-sm text-center text-gray-500 dark:text-gray-400">
              Preparing your sandbox environment...
            </p>
          </div>
        </div>
        
        <!-- Step 4: Success -->
        <div id="wizard-step-4" class="wizard-step hidden">
          <!-- Header -->
          <div class="p-6 pb-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center gap-4">
              <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-emerald-500 to-green-600 flex items-center justify-center shadow-lg">
                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
              </div>
              <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">Sandbox Ready!</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Your environment is set up and running</p>
              </div>
            </div>
          </div>
          
          <!-- Content -->
          <div class="p-6 space-y-4">
            <div class="p-4 bg-emerald-50 dark:bg-emerald-900/20 rounded-xl border border-emerald-200 dark:border-emerald-800">
              <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div class="text-sm">
                  <span class="text-emerald-800 dark:text-emerald-200 font-medium">Sandbox ID: </span>
                  <code id="wizard-sandbox-id" class="px-2 py-0.5 bg-emerald-100 dark:bg-emerald-800 text-emerald-700 dark:text-emerald-300 rounded font-mono text-xs">---</code>
                </div>
              </div>
            </div>
            
            <p class="text-gray-600 dark:text-gray-300">
              Your sandbox is now ready to use. You can create, edit, and manage files in your personal workspace.
            </p>
            
            <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
              <span>Click "Open Files" to start using your sandbox.</span>
            </div>
          </div>
          
          <!-- Actions -->
          <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-b-2xl flex justify-end">
            <button onclick="openSandboxAfterInstall()" class="px-5 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-emerald-600 to-green-600 hover:from-emerald-500 hover:to-green-500 rounded-xl shadow-lg shadow-emerald-500/25 transition-all">
              <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
              </svg>
              Open My Files
            </button>
          </div>
        </div>
        
        <!-- Error State -->
        <div id="wizard-step-error" class="wizard-step hidden">
          <!-- Header -->
          <div class="p-6 pb-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center gap-4">
              <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-red-500 to-rose-600 flex items-center justify-center shadow-lg">
                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
              </div>
              <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">Installation Failed</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Something went wrong</p>
              </div>
            </div>
          </div>
          
          <!-- Content -->
          <div class="p-6 space-y-4">
            <div class="p-4 bg-red-50 dark:bg-red-900/20 rounded-xl border border-red-200 dark:border-red-800">
              <p id="wizard-error-message" class="text-sm text-red-700 dark:text-red-300">
                An error occurred while creating your sandbox.
              </p>
            </div>
            
            <p class="text-gray-600 dark:text-gray-300 text-sm">
              This could be due to a temporary server issue. Please try again in a few moments.
            </p>
          </div>
          
          <!-- Actions -->
          <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-b-2xl flex justify-between items-center">
            <button onclick="closeSandboxWizard()" class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors">
              Close
            </button>
            <button onclick="showWizardStep(2)" class="px-5 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-500 hover:to-purple-500 rounded-xl shadow-lg shadow-violet-500/25 transition-all">
              Try Again
            </button>
          </div>
        </div>
        
        <!-- LXC Installation Required Step -->
        <div id="wizard-step-lxc-install" class="wizard-step hidden">
          <!-- Header -->
          <div class="p-6 pb-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center gap-4">
              <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center shadow-lg">
                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
              </div>
              <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">Setup Required</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">LXC/LXD needs to be installed</p>
              </div>
            </div>
          </div>
          
          <!-- Content -->
          <div class="p-6 space-y-4">
            <div class="p-4 bg-amber-50 dark:bg-amber-900/20 rounded-xl border border-amber-200 dark:border-amber-800">
              <p id="lxc-install-message" class="text-sm text-amber-700 dark:text-amber-300">
                The sandbox system requires LXC/LXD to be installed on the server.
              </p>
            </div>
            
            <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-xl border border-blue-200 dark:border-blue-800">
              <p class="text-sm text-blue-700 dark:text-blue-300">
                <strong>ginto.sh</strong> is the Ginto installer script that will:
              </p>
              <ul class="text-sm text-blue-600 dark:text-blue-400 mt-2 ml-4 list-disc space-y-1">
                <li>Install LXC/LXD container system</li>
                <li>Configure network bridges and storage</li>
                <li>Set up the Alpine Linux sandbox container</li>
                <li>Initialize all required permissions</li>
              </ul>
            </div>
            
            <p class="text-gray-600 dark:text-gray-300 text-sm font-medium">
              Run the installer in your server's SSH terminal:
            </p>
            
            <!-- Primary: Manual install command -->
            <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-xl border-2 border-violet-300 dark:border-violet-600">
              <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-lg bg-violet-100 dark:bg-violet-800 flex items-center justify-center flex-shrink-0">
                  <svg class="w-5 h-5 text-violet-600 dark:text-violet-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                  </svg>
                </div>
                <div class="flex-1">
                  <h4 class="font-semibold text-gray-900 dark:text-white">SSH into your server and run:</h4>
                  <div class="flex items-center bg-gray-900 rounded-lg mt-2">
                    <code id="lxc-install-cmd" class="flex-1 text-green-400 p-3 text-sm font-mono select-all">sudo bash ~/ginto/bin/ginto.sh install</code>
                    <button type="button" onclick="copyLxcInstallCmd()" class="p-3 text-gray-400 hover:text-white hover:bg-gray-700 rounded-r-lg transition-colors" title="Copy to clipboard">
                      <svg id="lxc-copy-icon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                      <svg id="lxc-check-icon" class="w-5 h-5 hidden text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </button>
                  </div>
                  <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                    Follow the interactive prompts. Installation takes 2-5 minutes.
                  </p>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Actions -->
          <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-b-2xl flex justify-between items-center">
            <button onclick="closeSandboxWizard()" class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors">
              Close
            </button>
            <button onclick="showWizardStep(2)" class="px-4 py-2 text-sm font-medium text-violet-600 dark:text-violet-400 hover:text-violet-800 dark:hover:text-violet-200 transition-colors">
              I've installed it, retry →
            </button>
          </div>
        </div>
        
        <!-- LXC Auto-Install Terminal Step -->
        <div id="wizard-step-lxc-terminal" class="wizard-step hidden">
          <!-- Header -->
          <div class="p-6 pb-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center gap-4">
              <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center shadow-lg">
                <svg class="w-7 h-7 text-white animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
              </div>
              <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">Installing LXC/LXD</h2>
                <p id="lxc-terminal-status" class="text-sm text-gray-500 dark:text-gray-400 mt-1">Running setup script...</p>
              </div>
            </div>
          </div>
          
          <!-- Terminal Output -->
          <div class="p-4">
            <div id="lxc-terminal-output" class="bg-gray-900 rounded-xl p-4 h-64 overflow-y-auto font-mono text-sm text-green-400 whitespace-pre-wrap">
              <span class="text-gray-500">$ sudo bash ~/ginto/bin/ginto.sh install</span>
              <br><span class="text-yellow-400">Starting LXC/LXD setup...</span>
              <br>
            </div>
          </div>
          
          <!-- Actions -->
          <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-b-2xl flex justify-between items-center">
            <button onclick="closeSandboxWizard()" class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors">
              Close
            </button>
            <button id="lxc-install-done-btn" onclick="showWizardStep(2)" disabled class="px-5 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-500 hover:to-purple-500 rounded-xl shadow-lg shadow-violet-500/25 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
              Continue to Sandbox Setup →
            </button>
          </div>
        </div>
        
      </div>
    </div>
  </div>
  
  <!-- Editor Modal (Full Screen) -->
  <div id="editor-modal" class="fixed inset-0 bg-black/50 dark:bg-black/90 z-50 hidden flex items-center justify-center">
    <div class="w-full h-full flex flex-col">
      <!-- Modal Header -->
      <div class="bg-gray-100 dark:bg-gray-900 border-b border-gray-300 dark:border-gray-700 px-4 py-3 flex items-center justify-between flex-shrink-0">
        <h3 class="text-gray-900 dark:text-white font-semibold">My Files</h3>
        <button id="close-editor" class="p-2 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-800 text-gray-700 dark:text-white">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>
      <!-- Editor iFrame -->
      <iframe id="editor-iframe" src="" class="flex-1 w-full border-0"></iframe>
    </div>
  </div>
  
  <!-- Console Terminal Modal (Full Screen - Admin Only) -->
  <?php if (!empty($isAdmin)): ?>
  <div id="console-modal" class="fixed inset-0 bg-black/50 dark:bg-black/90 z-50 hidden flex items-center justify-center">
    <div class="w-full h-full flex flex-col">
      <!-- Modal Header -->
      <div class="bg-gray-900 border-b border-gray-700 px-4 py-3 flex items-center justify-between flex-shrink-0">
        <div class="flex items-center gap-3">
          <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 7.5l3 2.25-3 2.25m4.5 0h3m-9 8.25h13.5A2.25 2.25 0 0021 18V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v12a2.25 2.25 0 002.25 2.25z"/>
          </svg>
          <h3 class="text-white font-semibold">Console</h3>
          <span id="console-status" class="text-xs px-2 py-0.5 rounded-full bg-gray-700 text-gray-400">Disconnected</span>
        </div>
        <div class="flex items-center gap-2">
          <button id="console-reconnect" class="px-3 py-1.5 text-sm bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition-colors">
            Reconnect
          </button>
          <button id="close-console" class="p-2 rounded-lg hover:bg-gray-800 text-white">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>
      </div>
      <!-- Terminal Container -->
      <div id="console-terminal" class="flex-1 w-full bg-black"></div>
    </div>
  </div>
  <!-- xterm.js for Console -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/xterm@5.1.0/css/xterm.css" />
  <script src="https://cdn.jsdelivr.net/npm/xterm@5.1.0/lib/xterm.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/xterm-addon-fit@0.7.0/lib/xterm-addon-fit.min.js"></script>
  <script>
  (function() {
    const modal = document.getElementById('console-modal');
    const termEl = document.getElementById('console-terminal');
    const openBtn = document.getElementById('open-console');
    const closeBtn = document.getElementById('close-console');
    const reconnectBtn = document.getElementById('console-reconnect');
    const statusEl = document.getElementById('console-status');
    
    if (!modal || !termEl || !openBtn) return;
    
    let term = null;
    let ws = null;
    let fitAddon = null;
    let pingInterval = null;
    let reconnectTimeout = null;
    let autoReconnect = true;
    const PING_INTERVAL_MS = 25000; // Send ping every 25 seconds to keep connection alive
    const RECONNECT_DELAY_MS = 2000; // Wait 2 seconds before reconnecting
    
    function updateStatus(status, color) {
      statusEl.textContent = status;
      statusEl.className = 'text-xs px-2 py-0.5 rounded-full ' + color;
    }
    
    function startPing() {
      stopPing();
      pingInterval = setInterval(function() {
        if (ws && ws.readyState === WebSocket.OPEN) {
          // Send a ping message (empty JSON object) to keep connection alive
          try { ws.send(JSON.stringify({ type: 'ping' })); } catch(e) {}
        }
      }, PING_INTERVAL_MS);
    }
    
    function stopPing() {
      if (pingInterval) {
        clearInterval(pingInterval);
        pingInterval = null;
      }
    }
    
    function scheduleReconnect() {
      if (!autoReconnect) return;
      if (reconnectTimeout) clearTimeout(reconnectTimeout);
      reconnectTimeout = setTimeout(function() {
        if (modal.classList.contains('hidden')) return; // Don't reconnect if modal is closed
        connectTerminal();
      }, RECONNECT_DELAY_MS);
    }
    
    function connectTerminal() {
      if (ws && ws.readyState === WebSocket.OPEN) return;
      if (reconnectTimeout) { clearTimeout(reconnectTimeout); reconnectTimeout = null; }
      
      updateStatus('Connecting...', 'bg-yellow-600 text-yellow-100');
      
      const host = window.location.hostname || '127.0.0.1';
      const cols = term ? term.cols : 120;
      const rows = term ? term.rows : 30;
      
      // Connect via Caddy proxy (port 80/443) - /terminal is proxied to Ratchet on 31827
      const wsUrl = (location.protocol === 'https:' ? 'wss://' : 'ws://') + host + '/terminal/terminal?mode=os&cols=' + cols + '&rows=' + rows;
      
      ws = new WebSocket(wsUrl);
      ws.binaryType = 'arraybuffer';
      
      ws.addEventListener('open', function() {
        updateStatus('Connected', 'bg-green-600 text-green-100');
        term.write('\r\n\x1b[32m*** Connected to host terminal ***\x1b[0m\r\n\r\n');
        startPing(); // Start keepalive pings
        
        // If there's a pending command, send it after a brief delay
        if (pendingCommand) {
          setTimeout(function() {
            ws.send(pendingCommand + '\n');
            pendingCommand = null;
          }, 500);
        }
      });
      
      ws.addEventListener('message', function(e) {
        try {
          term.write(typeof e.data === 'string' ? e.data : new TextDecoder().decode(e.data));
        } catch(err) {}
      });
      
      ws.addEventListener('close', function() {
        stopPing();
        updateStatus('Disconnected', 'bg-gray-700 text-gray-400');
        term.write('\r\n\x1b[31m*** Disconnected ***\x1b[0m\r\n');
        scheduleReconnect(); // Auto-reconnect after disconnect
      });
      
      ws.addEventListener('error', function() {
        stopPing();
        updateStatus('Error', 'bg-red-600 text-red-100');
      });
    }
    
    function initTerminal() {
      if (term) return;
      
      term = new window.Terminal({
        cols: 120,
        rows: 30,
        cursorBlink: true,
        fontSize: 14,
        fontFamily: 'Menlo, Monaco, "Courier New", monospace',
        theme: {
          background: '#0d1117',
          foreground: '#c9d1d9',
          cursor: '#58a6ff',
          cursorAccent: '#0d1117',
          black: '#0d1117',
          red: '#ff7b72',
          green: '#3fb950',
          yellow: '#d29922',
          blue: '#58a6ff',
          magenta: '#bc8cff',
          cyan: '#39c5cf',
          white: '#b1bac4'
        }
      });
      
      fitAddon = new window.FitAddon.FitAddon();
      term.loadAddon(fitAddon);
      term.open(termEl);
      fitAddon.fit();
      
      term.onData(function(data) {
        if (ws && ws.readyState === WebSocket.OPEN) {
          ws.send(data);
        }
      });
      
      // Handle resize
      window.addEventListener('resize', function() {
        if (modal.classList.contains('hidden')) return;
        fitAddon.fit();
        if (ws && ws.readyState === WebSocket.OPEN) {
          ws.send(JSON.stringify({ type: 'resize', cols: term.cols, rows: term.rows }));
        }
      });
    }
    
    // Pending command to run after connection
    let pendingCommand = null;
    
    function openConsole(initialCommand) {
      // Store command to run after connection
      if (initialCommand) {
        pendingCommand = initialCommand;
      }
      
      modal.classList.remove('hidden');
      document.body.style.overflow = 'hidden';
      
      if (!term) {
        initTerminal();
      }
      
      setTimeout(function() {
        fitAddon.fit();
        term.focus();
        connectTerminal();
      }, 100);
    }
    
    // Expose globally for use from chat.js
    window.openConsoleWithCommand = function(command) {
      openConsole(command);
    };
    
    function closeConsole() {
      autoReconnect = false; // Stop auto-reconnect when manually closed
      stopPing();
      if (reconnectTimeout) { clearTimeout(reconnectTimeout); reconnectTimeout = null; }
      modal.classList.add('hidden');
      document.body.style.overflow = '';
      if (ws) {
        ws.close();
        ws = null;
      }
    }
    
    openBtn.addEventListener('click', function(e) {
      e.preventDefault();
      autoReconnect = true; // Re-enable auto-reconnect when opening
      openConsole();
    });
    
    closeBtn.addEventListener('click', closeConsole);
    reconnectBtn.addEventListener('click', function() {
      if (ws) ws.close();
      ws = null;
      setTimeout(connectTerminal, 100);
    });
    
    // Close on Escape
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
        closeConsole();
      }
    });
  })();
  </script>
  <?php endif; ?>
  
  <!-- Settings Panel (Slide-over) -->
  <div id="settings-panel" class="fixed inset-y-0 right-0 w-96 bg-white dark:bg-gray-900 border-l border-gray-200 dark:border-gray-800 transform translate-x-full transition-transform duration-300 z-[60] flex flex-col shadow-2xl">
    <!-- Header with close button -->
    <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-800 flex-shrink-0">
      <h3 class="font-semibold" id="settings-panel-title">Settings</h3>
      <button id="close-settings" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>
    
    <!-- Tabs -->
    <div class="flex border-b border-gray-200 dark:border-gray-800 flex-shrink-0">
      <button id="tab-settings" class="flex-1 px-4 py-3 text-sm font-medium text-indigo-600 dark:text-indigo-400 border-b-2 border-indigo-600 dark:border-indigo-400 transition-colors" data-tab="settings">
        Settings
      </button>
      <!-- MCP tab hidden by default, shown only for admin users -->
      <button id="tab-mcp" class="flex-1 px-4 py-3 text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 border-b-2 border-transparent transition-colors <?= empty($isAdmin) ? 'hidden' : '' ?>" data-tab="mcp">
        MCP Tools
      </button>
      <!-- Admin tab for API keys, hidden by default -->
      <button id="tab-admin" class="flex-1 px-4 py-3 text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 border-b-2 border-transparent transition-colors <?= empty($isAdmin) ? 'hidden' : '' ?>" data-tab="admin">
        API Keys
      </button>
    </div>
    
    <!-- Tab Content: Settings -->
    <div id="panel-settings" class="flex-1 overflow-y-auto sidebar-scroll p-4 space-y-6">
      <!-- Audio Settings -->
      <div>
        <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Audio</h4>
        <div class="space-y-3">
          <label class="flex items-center justify-between">
            <span class="text-sm">Enable TTS</span>
            <input type="checkbox" id="enable_audio" class="w-4 h-4 rounded bg-gray-200 dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-indigo-500 focus:ring-indigo-500">
          </label>
          <button id="stop_audio" class="w-full px-3 py-2 text-sm bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg transition-colors">
            Stop Audio
          </button>
          <div id="tts_state" class="text-xs text-gray-500">(idle)</div>
        </div>
      </div>
      
      <!-- Speech-to-Text -->
      <div>
        <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Speech-to-Text</h4>
        <div class="space-y-3">
          <div class="flex gap-2">
            <button id="start_stt" class="flex-1 px-3 py-2 text-sm bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition-colors">
              Start Listening
            </button>
            <button id="stop_stt" disabled class="flex-1 px-3 py-2 text-sm bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg transition-colors disabled:opacity-50">
              Stop
            </button>
          </div>
          <div id="stt_transcript" class="p-3 bg-gray-100 dark:bg-gray-800 rounded-lg text-sm text-gray-600 dark:text-gray-400 min-h-[60px]">(not listening)</div>
          <div id="stt_debug" class="text-xs text-gray-500 dark:text-gray-600">&nbsp;</div>
        </div>
      </div>
      
      <!-- Wake Word -->
      <div>
        <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Wake Word</h4>
        <div class="space-y-3">
          <button id="train_wake" class="w-full px-3 py-2 text-sm bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg transition-colors">
            Train Wake Word
          </button>
          <label class="flex items-center justify-between">
            <span class="text-sm">Enable wake-word</span>
            <input type="checkbox" id="enable_wake" class="w-4 h-4 rounded bg-gray-200 dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-indigo-500 focus:ring-indigo-500">
          </label>
          <div id="wake_status" class="text-xs text-gray-500">(wake not trained)</div>
        </div>
      </div>
      
      <!-- Tools -->
      <div>
        <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Tools</h4>
        <div id="auto-run-tools-container" class="mb-3">
          <!-- Auto-run tools toggle will be injected by JS -->
        </div>
      </div>
      
      <!-- Chat Controls -->
      <div>
        <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Chat</h4>
        <div class="space-y-2">
          <button id="describe_repo" class="w-full px-3 py-2 text-sm bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg transition-colors text-left">
            📁 Describe Repository
          </button>
          <button id="reset_history" class="w-full px-3 py-2 text-sm bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg transition-colors text-left">
            🗑️ Reset History
          </button>
          <button id="clear" class="w-full px-3 py-2 text-sm bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg transition-colors text-left">
            ✨ Clear Display
          </button>
        </div>
      </div>
    </div>
    
    <!-- Tab Content: MCP (admin only) -->
    <div id="panel-mcp" class="flex-1 overflow-y-auto sidebar-scroll p-4 hidden" data-admin-only="true">
      <!-- MCP Status Header -->
      <div class="flex items-center gap-2 mb-4 p-3 bg-gray-100 dark:bg-gray-800 rounded-lg">
        <div class="w-3 h-3 rounded-full bg-gray-400" id="mcp-status-dot-panel"></div>
        <span class="font-medium">MCP Status:</span>
        <span id="mcp-status-text" class="text-gray-500 dark:text-gray-400">Checking...</span>
      </div>
      
      <!-- MCP Tools List -->
      <div>
        <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Available Tools</h4>
        <div id="mcp-capabilities" class="space-y-2 text-sm">
          <div class="text-gray-500">Loading MCP tools...</div>
        </div>
      </div>
      
      <!-- Copy JSON Button -->
      <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-800">
        <button id="mcp-copy-json" class="w-full px-3 py-2 text-sm bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg transition-colors">
          📋 Copy Discovery JSON
        </button>
      </div>
    </div>
    
    <!-- Tab Content: Admin API Keys (admin only) -->
    <div id="panel-admin" class="flex-1 overflow-y-auto sidebar-scroll p-4 hidden" data-admin-only="true">
      <!-- Add New Key Form -->
      <div class="mb-6 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Add New API Key</h4>
        <form id="add-api-key-form" class="space-y-3">
          <div>
            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Provider</label>
            <select name="provider" class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200">
              <option value="cerebras">Cerebras</option>
              <option value="groq">Groq</option>
            </select>
          </div>
          <div>
            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Key Name</label>
            <input type="text" name="key_name" placeholder="e.g., Production Key 2" class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200">
          </div>
          <div>
            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">API Key</label>
            <input type="password" name="api_key" required placeholder="gsk_... or csk-..." class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200">
          </div>
          <div>
            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Tier</label>
            <select name="tier" class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200">
              <option value="basic">Basic (Free)</option>
              <option value="production">Production (Paid)</option>
            </select>
          </div>
          <div class="flex items-center gap-4">
            <label class="flex items-center gap-2 text-sm">
              <input type="checkbox" name="is_default" class="w-4 h-4 rounded">
              <span>Set as default</span>
            </label>
          </div>
          <button type="submit" class="w-full px-3 py-2 text-sm bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition-colors">
            Add API Key
          </button>
        </form>
      </div>
      
      <!-- Existing Keys List -->
      <div>
        <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Existing API Keys</h4>
        <div id="api-keys-list" class="space-y-2">
          <div class="text-gray-500 text-sm">Loading keys...</div>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Settings Overlay -->
  <div id="settings-overlay" class="fixed inset-0 bg-black/50 z-30 hidden" onclick="closeSettings()"></div>
  
  <!-- Hidden elements for JS compatibility -->
  <input type="hidden" id="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
  <script>window.CSRF_TOKEN = <?= json_encode($csrf_token ?? '') ?>;</script>
  
  <!-- Sidebar & Settings Toggle Scripts -->
  <script>
    // Theme toggle functionality
    (function initTheme() {
      const html = document.documentElement;
      const themeToggle = document.getElementById('theme-toggle');
      const mobileThemeToggle = document.getElementById('mobile-theme-toggle');
      
      // Check for saved preference or system preference
      const savedTheme = localStorage.getItem('ginto-theme');
      if (savedTheme) {
        html.classList.toggle('dark', savedTheme === 'dark');
      } else {
        // Default to system preference
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        html.classList.toggle('dark', prefersDark);
      }
      
      // Toggle theme function
      function toggleTheme() {
        const isDark = html.classList.toggle('dark');
        localStorage.setItem('ginto-theme', isDark ? 'dark' : 'light');
      }
      
      // Toggle theme on button click (desktop and mobile)
      themeToggle?.addEventListener('click', toggleTheme);
      mobileThemeToggle?.addEventListener('click', toggleTheme);
    })();
    
    function toggleSidebar() {
      const sidebar = document.getElementById('sidebar');
      const overlay = document.getElementById('sidebar-overlay');
      sidebar.classList.toggle('-translate-x-full');
      overlay.classList.toggle('hidden');
    }
    
    // Sidebar collapse toggle for desktop
    function toggleSidebarCollapse() {
      const sidebar = document.getElementById('sidebar');
      const mainContent = document.getElementById('main-content');
      const collapseBtn = document.getElementById('sidebar-collapse-toggle');
      const expandBtn = document.getElementById('sidebar-expand-toggle');
      const isCollapsed = sidebar.classList.contains('sidebar-collapsed');
      
      // Get all toggle elements
      const expandedElements = sidebar.querySelectorAll('.sidebar-expanded-only');
      const collapsedElements = sidebar.querySelectorAll('.sidebar-collapsed-only');
      const labelElements = sidebar.querySelectorAll('.sidebar-label');
      
      if (isCollapsed) {
        // Expand
        sidebar.classList.remove('sidebar-collapsed');
        sidebar.classList.add('sidebar-expanded');
        sidebar.style.width = '256px';
        mainContent.style.marginLeft = '256px';
        
        // Show collapse button (at right edge), hide expand button (overlay)
        if (collapseBtn) {
          collapseBtn.classList.remove('hidden');
          collapseBtn.classList.add('lg:flex');
        }
        if (expandBtn) {
          expandBtn.classList.add('hidden');
          expandBtn.classList.remove('lg:flex');
        }
        
        // Show expanded elements, hide collapsed elements
        expandedElements.forEach(el => {
          if (el.classList.contains('flex-1')) {
            el.style.display = 'flex';
            el.style.flexDirection = 'column';
          } else {
            el.style.display = 'flex';
          }
        });
        collapsedElements.forEach(el => el.style.display = 'none');
        labelElements.forEach(el => el.style.display = '');
        
        localStorage.setItem('sidebar-collapsed', 'false');
      } else {
        // Collapse
        sidebar.classList.remove('sidebar-expanded');
        sidebar.classList.add('sidebar-collapsed');
        sidebar.style.width = '44px';
        mainContent.style.marginLeft = '44px';
        
        // Hide collapse button, show expand button (overlay on logo)
        if (collapseBtn) {
          collapseBtn.classList.add('hidden');
          collapseBtn.classList.remove('lg:flex');
        }
        if (expandBtn) {
          expandBtn.classList.remove('hidden');
          expandBtn.classList.add('lg:flex');
        }
        
        // Hide expanded elements, show collapsed elements
        expandedElements.forEach(el => el.style.display = 'none');
        collapsedElements.forEach(el => el.style.display = 'flex');
        labelElements.forEach(el => el.style.display = 'none');
        
        localStorage.setItem('sidebar-collapsed', 'true');
      }
    }
    
    // Restore sidebar collapse state from localStorage (only if was collapsed)
    (function restoreSidebarState() {
      const sidebar = document.getElementById('sidebar');
      const mainContent = document.getElementById('main-content');
      const collapseBtn = document.getElementById('sidebar-collapse-toggle');
      const expandBtn = document.getElementById('sidebar-expand-toggle');
      
      // Only apply collapse logic on desktop
      if (window.innerWidth >= 1024) {
        const wasCollapsed = localStorage.getItem('sidebar-collapsed') === 'true';
        
        // Set initial margin without animation (expanded is default)
        if (mainContent) mainContent.style.marginLeft = wasCollapsed ? '44px' : '256px';
        
        // Only modify if was collapsed (expanded is already default in HTML)
        if (wasCollapsed) {
          const expandedElements = sidebar.querySelectorAll('.sidebar-expanded-only');
          const collapsedElements = sidebar.querySelectorAll('.sidebar-collapsed-only');
          const labelElements = sidebar.querySelectorAll('.sidebar-label');
          
          sidebar.classList.remove('sidebar-expanded');
          sidebar.classList.add('sidebar-collapsed');
          sidebar.style.width = '44px';
          
          // Hide collapse button, show expand button (overlay)
          if (collapseBtn) {
            collapseBtn.classList.add('hidden');
            collapseBtn.classList.remove('lg:flex');
          }
          if (expandBtn) {
            expandBtn.classList.remove('hidden');
            expandBtn.classList.add('lg:flex');
          }
          
          expandedElements.forEach(el => el.style.display = 'none');
          collapsedElements.forEach(el => el.style.display = 'flex');
          labelElements.forEach(el => el.style.display = 'none');
        }
      } else {
        // Mobile: no margin
        if (mainContent) mainContent.style.marginLeft = '0';
      }
    })();
    
    // Handle window resize for sidebar
    window.addEventListener('resize', function() {
      const mainContent = document.getElementById('main-content');
      const sidebar = document.getElementById('sidebar');
      if (window.innerWidth >= 1024) {
        // Desktop: apply margin based on collapse state
        const isCollapsed = sidebar.classList.contains('sidebar-collapsed');
        mainContent.style.marginLeft = isCollapsed ? '44px' : '256px';
        // Ensure sidebar is visible on desktop
        sidebar.classList.remove('-translate-x-full');
      } else {
        // Mobile: no margin, sidebar slides in/out
        mainContent.style.marginLeft = '0';
      }
    });
    
    // Mobile header event handlers
    document.getElementById('mobile-menu-toggle')?.addEventListener('click', toggleSidebar);
    document.getElementById('sidebar-close-mobile')?.addEventListener('click', toggleSidebar);
    document.getElementById('sidebar-collapse-toggle')?.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      toggleSidebarCollapse();
    });
    document.getElementById('sidebar-expand-toggle')?.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      toggleSidebarCollapse();
    });
    document.getElementById('mobile-settings')?.addEventListener('click', () => openSettings('settings'));
    
    function closeSettings() {
      document.getElementById('settings-panel').classList.add('translate-x-full');
      document.getElementById('settings-overlay').classList.add('hidden');
    }
    
    function openSettings(tab = 'settings') {
      document.getElementById('settings-panel').classList.remove('translate-x-full');
      document.getElementById('settings-overlay').classList.remove('hidden');
      switchTab(tab);
    }
    
    function switchTab(tabName) {
      const tabSettings = document.getElementById('tab-settings');
      const tabMcp = document.getElementById('tab-mcp');
      const tabAdmin = document.getElementById('tab-admin');
      const panelSettings = document.getElementById('panel-settings');
      const panelMcp = document.getElementById('panel-mcp');
      const panelAdmin = document.getElementById('panel-admin');
      const title = document.getElementById('settings-panel-title');
      
      // Helper to deactivate all tabs
      const deactivateAll = () => {
        [tabSettings, tabMcp, tabAdmin].forEach(t => {
          if (t) {
            t.classList.remove('text-indigo-600', 'dark:text-indigo-400', 'border-indigo-600', 'dark:border-indigo-400');
            t.classList.add('text-gray-500', 'dark:text-gray-400', 'border-transparent');
          }
        });
        [panelSettings, panelMcp, panelAdmin].forEach(p => {
          if (p) p.classList.add('hidden');
        });
      };
      
      // Helper to activate a tab
      const activateTab = (tab, panel, titleText) => {
        if (tab) {
          tab.classList.add('text-indigo-600', 'dark:text-indigo-400', 'border-indigo-600', 'dark:border-indigo-400');
          tab.classList.remove('text-gray-500', 'dark:text-gray-400', 'border-transparent');
        }
        if (panel) panel.classList.remove('hidden');
        if (title) title.textContent = titleText;
      };
      
      deactivateAll();
      
      if (tabName === 'mcp') {
        activateTab(tabMcp, panelMcp, 'MCP Tools');
      } else if (tabName === 'admin') {
        activateTab(tabAdmin, panelAdmin, 'API Keys');
        loadApiKeys(); // Load keys when opening admin tab
      } else {
        activateTab(tabSettings, panelSettings, 'Settings');
      }
    }
    
    // Settings button opens Settings tab
    document.getElementById('toggle-settings')?.addEventListener('click', () => openSettings('settings'));
    
    // MCP Available button opens MCP tab
    document.getElementById('open-mcp-tab')?.addEventListener('click', () => openSettings('mcp'));
    
    // Tab click handlers
    document.getElementById('tab-settings')?.addEventListener('click', () => switchTab('settings'));
    document.getElementById('tab-mcp')?.addEventListener('click', () => switchTab('mcp'));
    document.getElementById('tab-admin')?.addEventListener('click', () => switchTab('admin'));
    
    // ============= Toast Notification System =============
    function showToast(message, type = 'success', duration = 4000) {
      const container = document.getElementById('toast-container');
      if (!container) return;
      
      const toast = document.createElement('div');
      toast.className = `
        pointer-events-auto max-w-sm w-full px-4 py-3 rounded-lg shadow-lg transform transition-all duration-300 ease-out
        translate-x-full opacity-0 flex items-start gap-3
        ${type === 'success' 
          ? 'bg-green-600 text-white' 
          : type === 'error' 
            ? 'bg-red-600 text-white' 
            : 'bg-gray-800 text-white'}
      `;
      
      const icon = type === 'success' 
        ? `<svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
             <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
           </svg>`
        : type === 'error'
          ? `<svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
               <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
             </svg>`
          : `<svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
               <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
             </svg>`;
      
      toast.innerHTML = `
        ${icon}
        <div class="flex-1 text-sm font-medium">${message}</div>
        <button onclick="this.parentElement.remove()" class="flex-shrink-0 p-1 rounded hover:bg-white/20 transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      `;
      
      container.appendChild(toast);
      
      // Animate in
      requestAnimationFrame(() => {
        toast.classList.remove('translate-x-full', 'opacity-0');
        toast.classList.add('translate-x-0', 'opacity-100');
      });
      
      // Auto remove after duration
      setTimeout(() => {
        toast.classList.remove('translate-x-0', 'opacity-100');
        toast.classList.add('translate-x-full', 'opacity-0');
        setTimeout(() => toast.remove(), 300);
      }, duration);
    }
    
    // ============= Universal Confirmation Modal =============
    let confirmModalCallback = null;
    const AUTO_APPROVE_TOOLS_KEY = 'ginto_auto_approve_tools';
    
    function showConfirmModal(options) {
      // Check for auto-approve for tool execution
      if (options.showAutoApprove && localStorage.getItem(AUTO_APPROVE_TOOLS_KEY) === '1') {
        return Promise.resolve(true);
      }
      
      const modal = document.getElementById('confirm-modal');
      const content = document.getElementById('confirm-modal-content');
      const title = document.getElementById('confirm-modal-title');
      const message = document.getElementById('confirm-modal-message');
      const actionBtn = document.getElementById('confirm-modal-action');
      const iconContainer = document.getElementById('confirm-modal-icon');
      const autoApproveContainer = document.getElementById('confirm-modal-auto-approve');
      const autoApproveCheckbox = document.getElementById('confirm-modal-auto-approve-checkbox');
      
      if (!modal) return Promise.resolve(false);
      
      // Set content
      title.textContent = options.title || 'Confirm Action';
      message.textContent = options.message || 'Are you sure you want to proceed?';
      actionBtn.textContent = options.confirmText || 'Confirm';
      
      // Show/hide auto-approve checkbox
      if (options.showAutoApprove && autoApproveContainer && autoApproveCheckbox) {
        autoApproveContainer.classList.remove('hidden');
        autoApproveCheckbox.checked = false;
      } else if (autoApproveContainer) {
        autoApproveContainer.classList.add('hidden');
      }
      
      // Set button color based on type
      const type = options.type || 'danger';
      actionBtn.className = `px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors ${
        type === 'danger' ? 'bg-red-600 hover:bg-red-700' :
        type === 'warning' ? 'bg-amber-600 hover:bg-amber-700' :
        'bg-indigo-600 hover:bg-indigo-700'
      }`;
      
      // Set icon based on type
      iconContainer.className = `w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 ${
        type === 'danger' ? 'bg-red-100 dark:bg-red-900/30' :
        type === 'warning' ? 'bg-amber-100 dark:bg-amber-900/30' :
        'bg-indigo-100 dark:bg-indigo-900/30'
      }`;
      iconContainer.innerHTML = type === 'danger' 
        ? `<svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
             <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
           </svg>`
        : type === 'warning'
          ? `<svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
               <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
             </svg>`
          : `<svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
               <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
             </svg>`;
      
      // Show modal
      modal.classList.remove('hidden');
      requestAnimationFrame(() => {
        content.classList.remove('scale-95', 'opacity-0');
        content.classList.add('scale-100', 'opacity-100');
      });
      
      // Return promise that resolves when user makes a choice
      return new Promise((resolve) => {
        confirmModalCallback = resolve;
        
        // Set up action button click
        actionBtn.onclick = () => {
          // Save auto-approve preference if checkbox is checked
          if (options.showAutoApprove && autoApproveCheckbox && autoApproveCheckbox.checked) {
            localStorage.setItem(AUTO_APPROVE_TOOLS_KEY, '1');
          }
          confirmModalCallback = null; // Clear callback before closing to prevent double-resolve
          closeConfirmModal();
          resolve(true);
        };
      });
    }
    
    function closeConfirmModal() {
      const modal = document.getElementById('confirm-modal');
      const content = document.getElementById('confirm-modal-content');
      
      if (!modal) return;
      
      content.classList.remove('scale-100', 'opacity-100');
      content.classList.add('scale-95', 'opacity-0');
      
      setTimeout(() => {
        modal.classList.add('hidden');
        if (confirmModalCallback) {
          confirmModalCallback(false);
          confirmModalCallback = null;
        }
      }, 150);
    }
    
    // ============= Premium Upgrade Modal =============
    function showUpgradeModal(message) {
      const modal = document.getElementById('upgrade-modal');
      const content = document.getElementById('upgrade-modal-content');
      const messageEl = document.getElementById('upgrade-modal-message');
      
      if (!modal) return;
      
      if (message && messageEl) {
        messageEl.textContent = message;
      }
      
      modal.classList.remove('hidden');
      requestAnimationFrame(() => {
        content.classList.remove('scale-95', 'opacity-0');
        content.classList.add('scale-100', 'opacity-100');
      });
    }
    
    function closeUpgradeModal() {
      const modal = document.getElementById('upgrade-modal');
      const content = document.getElementById('upgrade-modal-content');
      
      if (!modal) return;
      
      content.classList.remove('scale-100', 'opacity-100');
      content.classList.add('scale-95', 'opacity-0');
      
      setTimeout(() => {
        modal.classList.add('hidden');
      }, 150);
    }
    
    // Make showUpgradeModal available globally for chat.js
    window.showUpgradeModal = showUpgradeModal;
    
    // ============= Register Required Modal (Visitor Limit) =============
    function showRegisterModal(message) {
      const modal = document.getElementById('register-modal');
      const content = document.getElementById('register-modal-content');
      const messageEl = document.getElementById('register-modal-message');
      
      if (!modal) return;
      
      if (message && messageEl) {
        messageEl.textContent = message;
      }
      
      modal.classList.remove('hidden');
      requestAnimationFrame(() => {
        content.classList.remove('scale-95', 'opacity-0');
        content.classList.add('scale-100', 'opacity-100');
      });
    }
    
    function closeRegisterModal() {
      const modal = document.getElementById('register-modal');
      const content = document.getElementById('register-modal-content');
      
      if (!modal) return;
      
      content.classList.remove('scale-100', 'opacity-100');
      content.classList.add('scale-95', 'opacity-0');
      
      setTimeout(() => {
        modal.classList.add('hidden');
      }, 150);
    }
    
    // Make showRegisterModal available globally for chat.js
    window.showRegisterModal = showRegisterModal;
    
    // ============= Image Viewer Modal =============
    function showImageModal(imageSrc) {
      const modal = document.getElementById('image-modal');
      const content = document.getElementById('image-modal-content');
      const img = document.getElementById('image-modal-img');
      
      if (!modal || !img) return;
      
      img.src = imageSrc;
      modal.classList.remove('hidden');
      requestAnimationFrame(() => {
        content.classList.remove('scale-95', 'opacity-0');
        content.classList.add('scale-100', 'opacity-100');
      });
    }
    
    // Image zoom state
    let currentZoom = 1;
    
    function closeImageModal() {
      const modal = document.getElementById('image-modal');
      const content = document.getElementById('image-modal-content');
      
      if (!modal) return;
      
      content.classList.remove('scale-100', 'opacity-100');
      content.classList.add('scale-95', 'opacity-0');
      
      setTimeout(() => {
        modal.classList.add('hidden');
        document.getElementById('image-modal-img').src = '';
        resetZoom();
      }, 150);
    }
    
    function zoomImage(delta) {
      const img = document.getElementById('image-modal-img');
      const zoomLabel = document.getElementById('image-zoom-level');
      if (!img) return;
      
      currentZoom = Math.max(0.25, Math.min(4, currentZoom + delta));
      img.style.transform = `scale(${currentZoom})`;
      zoomLabel.textContent = Math.round(currentZoom * 100) + '%';
    }
    
    function resetZoom() {
      const img = document.getElementById('image-modal-img');
      const zoomLabel = document.getElementById('image-zoom-level');
      if (!img) return;
      
      currentZoom = 1;
      img.style.transform = 'scale(1)';
      zoomLabel.textContent = '100%';
    }
    
    // Make it globally accessible
    window.showImageModal = showImageModal;
    window.closeImageModal = closeImageModal;
    window.zoomImage = zoomImage;
    window.resetZoom = resetZoom;
    
    // Close modal on Escape key
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && !document.getElementById('confirm-modal').classList.contains('hidden')) {
        closeConfirmModal();
      }
      if (e.key === 'Escape' && !document.getElementById('tts-limit-modal').classList.contains('hidden')) {
        closeTtsLimitModal();
      }
      if (e.key === 'Escape' && !document.getElementById('image-modal').classList.contains('hidden')) {
        closeImageModal();
      }
    });
    
    // ============= TTS Rate Limit Modal =============
    // Global flag to track if TTS limit modal has been shown this session
    window.ttsLimitModalShown = false;
    
    /**
     * Show the TTS rate limit modal with appropriate message/actions based on user role
     * @param {object} data - Rate limit data from server
     *   data.user_role: 'visitor', 'user', or 'admin'
     *   data.reason: 'visitor_session_limit', 'user_hourly_limit', 'org_rpm_threshold', etc.
     *   data.message: Server-provided message
     */
    function showTtsLimitModal(data) {
      // Only show once per session to avoid spamming
      if (window.ttsLimitModalShown) return;
      window.ttsLimitModalShown = true;
      
      const modal = document.getElementById('tts-limit-modal');
      const content = document.getElementById('tts-limit-modal-content');
      const title = document.getElementById('tts-limit-modal-title');
      const message = document.getElementById('tts-limit-modal-message');
      const extra = document.getElementById('tts-limit-modal-extra');
      const actions = document.getElementById('tts-limit-modal-actions');
      
      if (!modal) return;
      
      const userRole = data.user_role || 'visitor';
      const reason = data.reason || 'unknown';
      
      // Set title and message
      title.textContent = 'Text-to-Speech Limit Reached';
      message.textContent = data.message || 'You\'ve reached your TTS limit.';
      
      // Build actions based on user role
      if (userRole === 'visitor') {
        // Visitor: prompt to register
        extra.innerHTML = `
          <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 mt-2 mb-2">
            <p class="text-sm text-gray-600 dark:text-gray-300">💬 You can still continue chatting with AI - only voice playback is paused.</p>
          </div>
          <div class="bg-indigo-50 dark:bg-indigo-900/20 rounded-lg p-3">
            <p class="font-medium text-indigo-700 dark:text-indigo-300">🎁 Get more with a free account!</p>
            <p class="text-xs mt-1 text-indigo-600 dark:text-indigo-400">Registered users get 30 TTS requests per hour, plus more features.</p>
          </div>
        `;
        extra.classList.remove('hidden');
        
        actions.innerHTML = `
          <button onclick="closeTtsLimitModal()" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
            Maybe Later
          </button>
          <a href="/register" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition-colors inline-flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
            </svg>
            Register Free
          </a>
        `;
      } else if (userRole === 'user') {
        // Logged-in user: prompt to upgrade or collaborate
        extra.innerHTML = `
          <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 mt-2 mb-2">
            <p class="text-sm text-gray-600 dark:text-gray-300">💬 You can still continue chatting with AI - only voice playback is paused.</p>
          </div>
          <div class="bg-amber-50 dark:bg-amber-900/20 rounded-lg p-3">
            <p class="font-medium text-amber-700 dark:text-amber-300">⚡ Need more TTS capacity?</p>
            <p class="text-xs mt-1 text-amber-600 dark:text-amber-400">Upgrade your plan or contact us about custom limits as collaborators or partners.</p>
          </div>
        `;
        extra.classList.remove('hidden');
        
        actions.innerHTML = `
          <button onclick="closeTtsLimitModal()" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
            Got it
          </button>
          <a href="/contact?subject=TTS%20Upgrade" class="px-4 py-2 text-sm font-medium text-white bg-amber-600 hover:bg-amber-700 rounded-lg transition-colors inline-flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
            Contact Us
          </a>
        `;
      } else {
        // Admin or org limit: just informational
        extra.innerHTML = `
          <div class="bg-gray-100 dark:bg-gray-700/50 rounded-lg p-3 mt-2">
            <p class="text-xs text-gray-500 dark:text-gray-400">TTS will automatically resume when capacity is available. You can still use text chat normally.</p>
          </div>
        `;
        extra.classList.remove('hidden');
        
        actions.innerHTML = `
          <button onclick="closeTtsLimitModal()" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition-colors">
            Got it
          </button>
        `;
      }
      
      // Show modal
      modal.classList.remove('hidden');
      requestAnimationFrame(() => {
        content.classList.remove('scale-95', 'opacity-0');
        content.classList.add('scale-100', 'opacity-100');
      });
    }
    
    function closeTtsLimitModal() {
      const modal = document.getElementById('tts-limit-modal');
      const content = document.getElementById('tts-limit-modal-content');
      
      if (!modal) return;
      
      content.classList.remove('scale-100', 'opacity-100');
      content.classList.add('scale-95', 'opacity-0');
      
      setTimeout(() => {
        modal.classList.add('hidden');
      }, 150);
    }
    
    // Expose globally so chat.js can call it
    window.showTtsLimitModal = showTtsLimitModal;
    window.closeTtsLimitModal = closeTtsLimitModal;
    
    // ============= Session Expired Modal (Visitors) =============
    // Global flag to track if session expired modal has been shown
    window.sessionExpiredModalShown = false;
    
    /**
     * Show the session expired modal to visitors when their CSRF token is about to expire
     */
    function showSessionExpiredModal() {
      // Only show once per session
      if (window.sessionExpiredModalShown) return;
      // Only show to visitors (non-logged-in users)
      if (window.GINTO_AUTH && window.GINTO_AUTH.userId) return;
      
      window.sessionExpiredModalShown = true;
      
      const modal = document.getElementById('session-expired-modal');
      const content = document.getElementById('session-expired-modal-content');
      
      if (!modal) return;
      
      // Show modal
      modal.classList.remove('hidden');
      requestAnimationFrame(() => {
        content.classList.remove('scale-95', 'opacity-0');
        content.classList.add('scale-100', 'opacity-100');
      });
    }
    
    function closeSessionExpiredModal() {
      const modal = document.getElementById('session-expired-modal');
      const content = document.getElementById('session-expired-modal-content');
      
      if (!modal) return;
      
      content.classList.remove('scale-100', 'opacity-100');
      content.classList.add('scale-95', 'opacity-0');
      
      setTimeout(() => {
        modal.classList.add('hidden');
      }, 150);
    }
    
    // Expose globally
    window.showSessionExpiredModal = showSessionExpiredModal;
    window.closeSessionExpiredModal = closeSessionExpiredModal;

    // ============= API Keys Management =============
    async function loadApiKeys() {
      const list = document.getElementById('api-keys-list');
      if (!list) return;
      
      try {
        const res = await fetch('/api/provider-keys', { credentials: 'same-origin' });
        const data = await res.json();
        
        if (!data.success || !data.keys || data.keys.length === 0) {
          list.innerHTML = '<div class="text-gray-500 text-sm">No API keys configured.</div>';
          return;
        }
        
        list.innerHTML = data.keys.map(key => `
          <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700" data-key-id="${key.id}">
            <div class="flex items-center justify-between mb-2 flex-wrap gap-1">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="font-medium text-sm">${escapeHtml(key.key_name || 'Unnamed')}</span>
                ${key.is_default ? '<span class="text-xs bg-indigo-100 dark:bg-indigo-900 text-indigo-700 dark:text-indigo-300 px-2 py-0.5 rounded">Default</span>' : ''}
              </div>
              <div class="flex items-center gap-2">
                <span class="text-xs px-2 py-0.5 rounded flex-shrink-0 ${key.tier === 'production' ? 'bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300' : 'bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-400'}">${key.tier}</span>
                <button onclick="deleteApiKey(${key.id}, '${escapeHtml(key.key_name || 'this key')}')" class="p-1 rounded text-red-500 hover:bg-red-100 dark:hover:bg-red-900/30 transition-colors" title="Delete key">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                  </svg>
                </button>
              </div>
            </div>
            <div class="text-xs font-mono text-gray-500 dark:text-gray-400 mb-1 break-all">${escapeHtml(key.api_key_masked)}</div>
            <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
              <span>${key.provider}</span>
              <span class="text-gray-400">•</span>
              <span>ID: ${key.id}</span>
            </div>
            ${key.rate_limit_reset_at ? `<div class="text-xs text-amber-600 dark:text-amber-400 mt-2">⚠️ Rate limited until ${new Date(key.rate_limit_reset_at).toLocaleTimeString()}</div>` : ''}
            ${key.error_count > 0 ? `<div class="text-xs text-red-500 mt-1">Errors: ${key.error_count}</div>` : ''}
            ${key.last_used_at ? `<div class="text-xs text-gray-400 mt-1">Last used: ${new Date(key.last_used_at).toLocaleString()}</div>` : ''}
          </div>
        `).join('');
      } catch (e) {
        list.innerHTML = '<div class="text-red-500 text-sm">Failed to load API keys.</div>';
      }
    }
    
    function escapeHtml(str) {
      if (!str) return '';
      return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
    
    // Delete API key function
    async function deleteApiKey(id, name) {
      const confirmed = await showConfirmModal({
        title: 'Delete API Key',
        message: `Are you sure you want to delete "${name}"? This action cannot be undone.`,
        confirmText: 'Delete',
        type: 'danger'
      });
      
      if (!confirmed) return;
      
      try {
        const res = await fetch('/api/provider-keys', {
          method: 'POST',
          headers: { 
            'Content-Type': 'application/json',
            'X-CSRF-Token': window.GINTO_AUTH.csrfToken || window.CSRF_TOKEN || ''
          },
          body: JSON.stringify({ action: 'delete', id }),
          credentials: 'same-origin'
        });
        const data = await res.json();
        if (data.success) {
          loadApiKeys();
          showToast('API key deleted successfully', 'success');
        } else {
          showToast(data.error || 'Failed to delete API key', 'error');
        }
      } catch (e) {
        console.error('Delete API key error:', e);
        showToast('Failed to delete API key', 'error');
      }
    }
    
    // Add new API key form handler
    document.getElementById('add-api-key-form')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const form = e.target;
      const formData = new FormData(form);
      
      try {
        const res = await fetch('/api/provider-keys', {
          method: 'POST',
          headers: { 
            'Content-Type': 'application/json',
            'X-CSRF-Token': window.GINTO_AUTH.csrfToken || window.CSRF_TOKEN || ''
          },
          body: JSON.stringify({
            action: 'add',
            provider: formData.get('provider'),
            key_name: formData.get('key_name'),
            api_key: formData.get('api_key'),
            tier: formData.get('tier'),
            is_default: formData.get('is_default') === 'on'
          }),
          credentials: 'same-origin'
        });
        const data = await res.json();
        console.log('Add API key response:', data);
        if (data.success) {
          form.reset();
          loadApiKeys();
          showToast('API key added successfully!', 'success');
        } else {
          showToast(data.error || 'Failed to add API key', 'error');
        }
      } catch (e) {
        console.error('Add API key error:', e);
        showToast('Failed to add API key: ' + e.message, 'error');
      }
    });
    
    document.getElementById('close-settings')?.addEventListener('click', closeSettings);
    
    // Editor Modal Controls
    const editorModal = document.getElementById('editor-modal');
    const editorIframe = document.getElementById('editor-iframe');
    const openMyFilesBtn = document.getElementById('open-my-files');
    const closeEditorBtn = document.getElementById('close-editor');
    const sandboxIdDisplay = document.getElementById('sandbox-id-display');
    
    // Update sandbox ID display when auth is ready
    window.addEventListener('gintoAuthReady', (e) => {
      if (e.detail && e.detail.sandbox && e.detail.sandbox.id && sandboxIdDisplay) {
        sandboxIdDisplay.textContent = e.detail.sandbox.id.substring(0, 8);
      }
    });

    // Also attempt to populate the sandbox display immediately from any
    // available client-side state (helps when session was set by /editor).
    (function populateSandboxDisplay() {
      function setIf(id) {
        if (!id || !sandboxIdDisplay) return false;
        sandboxIdDisplay.textContent = id.substring(0, 8);
        return true;
      }

      try {
        // 1) server-rendered variable (already handled by PHP), leave as-is
        // 2) check global auth object if present
        if (window.GINTO_AUTH && window.GINTO_AUTH.sandbox && window.GINTO_AUTH.sandbox.id) {
          if (setIf(window.GINTO_AUTH.sandbox.id)) return;
        }

        // 3) check editorConfig if populated by any prior editor load
        if (window.editorConfig && window.editorConfig.sandboxId) {
          if (setIf(window.editorConfig.sandboxId)) return;
        }

        // 4) if auth promise exists, attach a handler to update once ready
        if (window.GINTO_AUTH_PROMISE && typeof window.GINTO_AUTH_PROMISE.then === 'function') {
          window.GINTO_AUTH_PROMISE.then(function() {
            try {
              if (window.GINTO_AUTH && window.GINTO_AUTH.sandbox && window.GINTO_AUTH.sandbox.id) {
                setIf(window.GINTO_AUTH.sandbox.id);
              }
            } catch (e) {}
          }).catch(function(){});
        }

        // 5) listen for editor iframe postMessage events announcing sandbox creation or destruction
        window.addEventListener('message', function(ev) {
          try {
            var d = ev && ev.data;
            if (!d) return;
            if (d.type === 'sandbox_created' && d.id) {
              setIf(d.id);
            }
            // Handle sandbox destruction - close editor modal
            if (d.type === 'sandbox_destroyed') {
              console.log('Sandbox destroyed message received');
              var modal = document.getElementById('editor-modal');
              if (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
              }
              // Clear the sandbox ID display
              var sandboxDisplay = document.getElementById('sandbox-id-display');
              if (sandboxDisplay) {
                sandboxDisplay.textContent = '';
              }
              // Update status indicator directly to show sandbox not installed
              var indicator = document.getElementById('sandbox-status-indicator');
              if (indicator) {
                var dot = indicator.querySelector('span');
                if (dot) {
                  dot.className = 'w-2 h-2 rounded-full bg-gray-400 inline-block';
                  dot.title = 'Sandbox not installed';
                }
              }
            }
          } catch (e) {
            console.error('Error handling postMessage:', e);
          }
        }, false);
      } catch (e) {
        // ignore failures
      }
    })();
    
    // ========================================
    // Sandbox Installation Wizard Functions
    // ========================================
    let installedSandboxId = null;
    
    function showSandboxWizard() {
      const modal = document.getElementById('sandbox-wizard-modal');
      if (modal) {
        modal.classList.remove('hidden');
        showWizardStep(1);
      }
    }
    
    function closeSandboxWizard() {
      const modal = document.getElementById('sandbox-wizard-modal');
      if (modal) {
        modal.classList.add('hidden');
      }
    }
    
    // Expose sandbox wizard functions globally for chat.js tool handler
    window.showSandboxWizard = showSandboxWizard;
    window.closeSandboxWizard = closeSandboxWizard;
    
    // Update sandbox status indicator in sidebar
    function updateSandboxStatusIndicator(status) {
      const indicator = document.getElementById('sandbox-status-indicator');
      if (!indicator) return;
      
      const dot = indicator.querySelector('span');
      if (!dot) return;
      
      indicator.classList.remove('hidden');
      
      switch (status) {
        case 'running':
        case 'ready':
          dot.className = 'w-2 h-2 rounded-full bg-emerald-500 inline-block animate-pulse';
          dot.title = 'Sandbox running';
          break;
        case 'stopped':
        case 'installed':
          dot.className = 'w-2 h-2 rounded-full bg-amber-500 inline-block';
          dot.title = 'Sandbox stopped';
          break;
        case 'not_created':
        case 'not_installed':
          dot.className = 'w-2 h-2 rounded-full bg-gray-400 inline-block';
          dot.title = 'Sandbox not installed';
          break;
        case 'error':
          dot.className = 'w-2 h-2 rounded-full bg-red-500 inline-block';
          dot.title = 'Sandbox error';
          break;
        default:
          indicator.classList.add('hidden');
      }
    }
    
    // Pre-fetch sandbox status on page load to show indicator
    (async function prefetchSandboxStatus() {
      try {
        await window.GINTO_AUTH_PROMISE;
        const res = await fetch('/sandbox/status', { credentials: 'same-origin' });
        const data = await res.json().catch(() => null);
        if (data && data.status) {
          updateSandboxStatusIndicator(data.container_status || data.status);
        }
      } catch (e) {
        // ignore
      }
    })();
    
    function showWizardStep(step) {
      // Hide all steps
      document.querySelectorAll('.wizard-step').forEach(el => el.classList.add('hidden'));
      // Show target step
      const target = document.getElementById('wizard-step-' + step);
      if (target) target.classList.remove('hidden');
      
      // Reset install button state if going to step 2
      if (step === 2) {
        const checkbox = document.getElementById('accept-sandbox-terms');
        const btn = document.getElementById('wizard-install-btn');
        if (checkbox && btn) {
          btn.disabled = !checkbox.checked;
        }
      }
    }
    
    // Enable install button when terms are accepted
    document.getElementById('accept-sandbox-terms')?.addEventListener('change', function() {
      const btn = document.getElementById('wizard-install-btn');
      if (btn) btn.disabled = !this.checked;
    });
    
    // Helper to refresh CSRF token (for visitors whose session was reset)
    async function refreshCsrfToken() {
      try {
        const res = await fetch('/dev/csrf', { credentials: 'same-origin' });
        const data = await res.json();
        if (data.success && data.csrf_token) {
          window.GINTO_AUTH.csrfToken = data.csrf_token;
          console.log('CSRF token refreshed');
          return true;
        }
      } catch (e) {
        console.error('Failed to refresh CSRF token:', e);
      }
      return false;
    }
    
    async function installSandbox() {
      const checkbox = document.getElementById('accept-sandbox-terms');
      if (!checkbox || !checkbox.checked) {
        alert('Please accept the terms and conditions to continue.');
        return;
      }
      
      // Show installing step
      showWizardStep(3);
      
      // Update progress with animation
      const progressBar = document.getElementById('install-progress-bar');
      const statusText = document.getElementById('install-status-text');
      
      const updateInstallStep = (stepNum, status) => {
        const step = document.getElementById('install-step-' + stepNum);
        if (!step) return;
        const icon = step.querySelector('.install-icon');
        if (status === 'active') {
          step.classList.add('bg-violet-50', 'dark:bg-violet-900/20');
          step.classList.remove('bg-gray-50', 'dark:bg-gray-800');
          icon.innerHTML = '<svg class="w-4 h-4 text-violet-600 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>';
          icon.classList.add('bg-violet-100', 'dark:bg-violet-800');
          icon.classList.remove('bg-gray-200', 'dark:bg-gray-700');
        } else if (status === 'done') {
          step.classList.add('bg-emerald-50', 'dark:bg-emerald-900/20');
          step.classList.remove('bg-violet-50', 'dark:bg-violet-900/20', 'bg-gray-50', 'dark:bg-gray-800');
          icon.innerHTML = '<svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>';
          icon.classList.add('bg-emerald-100', 'dark:bg-emerald-800');
          icon.classList.remove('bg-violet-100', 'dark:bg-violet-800', 'bg-gray-200', 'dark:bg-gray-700');
        }
      };
      
      // Helper function to make the install request
      const doInstallRequest = async () => {
        const body = new URLSearchParams();
        body.append('csrf_token', window.GINTO_AUTH?.csrfToken || '');
        body.append('accept_terms', '1');
        
        const res = await fetch('/sandbox/install', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: body.toString()
        });
        
        const data = await res.json().catch(() => null);
        return { res, data };
      };
      
      try {
        // Animate through steps
        updateInstallStep(1, 'active');
        progressBar.style.width = '10%';
        statusText.textContent = 'Creating sandbox directory...';
        await new Promise(r => setTimeout(r, 500));
        
        updateInstallStep(1, 'done');
        updateInstallStep(2, 'active');
        progressBar.style.width = '30%';
        statusText.textContent = 'Launching LXC container...';
        
        // Make API call to install sandbox
        let { res, data } = await doInstallRequest();
        
        // If CSRF token is invalid, try refreshing it and retry once
        if (res.status === 403 && data?.error?.toLowerCase().includes('csrf')) {
          console.log('CSRF token invalid, refreshing and retrying...');
          statusText.textContent = 'Refreshing session...';
          const refreshed = await refreshCsrfToken();
          if (refreshed) {
            statusText.textContent = 'Launching LXC container...';
            ({ res, data } = await doInstallRequest());
          }
        }
        
        // Check if LXC installation is required
        if (data?.install_required || data?.error_code?.startsWith('lxc_') || data?.error_code === 'base_image_missing' || data?.error_code === 'lxd_not_initialized') {
          // Close the wizard and open web terminal with auto-install command (admin only)
          closeSandboxWizard();
          
          // Only admins have access to the console - open it with install command
          if (typeof window.openConsoleWithCommand === 'function') {
            window.openConsoleWithCommand('sudo bash ~/ginto/bin/ginto.sh install');
          }
          return;
        }
        
        if (!res.ok || !data || !data.success) {
          throw new Error(data?.error || 'Installation failed');
        }
        
        // Continue progress animation
        updateInstallStep(2, 'done');
        updateInstallStep(3, 'active');
        progressBar.style.width = '70%';
        statusText.textContent = 'Configuring environment...';
        await new Promise(r => setTimeout(r, 800));
        
        updateInstallStep(3, 'done');
        updateInstallStep(4, 'active');
        progressBar.style.width = '90%';
        statusText.textContent = 'Finalizing setup...';
        await new Promise(r => setTimeout(r, 500));
        
        updateInstallStep(4, 'done');
        progressBar.style.width = '100%';
        statusText.textContent = 'Complete!';
        
        // Store sandbox ID
        installedSandboxId = data.sandbox_id;
        
        // Update display
        if (sandboxIdDisplay) {
          sandboxIdDisplay.textContent = data.sandbox_id.substring(0, 8);
        }
        
        // Update status indicator to running
        updateSandboxStatusIndicator('running');
        
        await new Promise(r => setTimeout(r, 500));
        
        // Show success step
        showWizardStep(4);
        document.getElementById('wizard-sandbox-id').textContent = data.sandbox_id;
        
      } catch (err) {
        console.error('Sandbox installation error:', err);
        document.getElementById('wizard-error-message').textContent = err.message || 'An unknown error occurred.';
        showWizardStep('error');
      }
    }
    
    function openSandboxAfterInstall() {
      closeSandboxWizard();
      const sandboxId = installedSandboxId || (sandboxIdDisplay?.textContent || '').trim();
      let editorUrl = '/editor';
      if (sandboxId && sandboxId !== 'unavailable' && sandboxId !== 'default') {
        editorUrl += '?sandbox=' + encodeURIComponent(sandboxId);
      }
      editorIframe.src = editorUrl;
      editorModal.classList.remove('hidden');
      editorModal.classList.add('flex');
    }
    
    // Copy LXC install command to clipboard
    function copyLxcInstallCmd() {
      const cmd = document.getElementById('lxc-install-cmd').textContent;
      
      function copySuccess() {
        const copyIcon = document.getElementById('lxc-copy-icon');
        const checkIcon = document.getElementById('lxc-check-icon');
        copyIcon.classList.add('hidden');
        checkIcon.classList.remove('hidden');
        showToast('Copied to clipboard!');
        setTimeout(() => {
          copyIcon.classList.remove('hidden');
          checkIcon.classList.add('hidden');
        }, 2000);
      }
      
      function copyFailed() {
        showToast('Failed to copy. Please select and copy manually.', 3000);
      }
      
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(cmd).then(copySuccess).catch(copyFailed);
      } else {
        const textArea = document.createElement('textarea');
        textArea.value = cmd;
        textArea.style.position = 'fixed';
        textArea.style.left = '-9999px';
        textArea.style.top = '-9999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        try {
          document.execCommand('copy') ? copySuccess() : copyFailed();
        } catch (err) {
          copyFailed();
        }
        document.body.removeChild(textArea);
      }
    }
    
    // Toast notification helper
    function showToast(message, duration = 2000) {
      const existingToast = document.getElementById('ginto-toast');
      if (existingToast) existingToast.remove();
      
      const toast = document.createElement('div');
      toast.id = 'ginto-toast';
      toast.className = 'fixed bottom-8 left-1/2 transform -translate-x-1/2 bg-green-600 text-white px-6 py-3 rounded-lg shadow-lg flex items-center gap-2 z-[9999]';
      toast.innerHTML = `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg><span>${message}</span>`;
      document.body.appendChild(toast);
      
      setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.3s';
        setTimeout(() => toast.remove(), 300);
      }, duration);
    }
    
    // Start LXC auto-install via Console/Terminal
    async function startLxcAutoInstall() {
      showWizardStep('lxc-terminal');
      
      const terminalOutput = document.getElementById('lxc-terminal-output');
      const statusText = document.getElementById('lxc-terminal-status');
      const doneBtn = document.getElementById('lxc-install-done-btn');
      
      // Add initial output
      terminalOutput.innerHTML = '<span class="text-gray-500">$ sudo bash ~/ginto/bin/ginto.sh install</span>\n<span class="text-yellow-400">Starting LXC/LXD setup...</span>\n\n';
      
      try {
        // Call the exec endpoint to run the install script
        const res = await fetch('/sandbox/exec', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            command: 'sudo bash ~/ginto/bin/ginto.sh --auto',
            csrf_token: window.GINTO_AUTH?.csrfToken || ''
          })
        });
        
        // Stream the response
        const reader = res.body?.getReader();
        const decoder = new TextDecoder();
        
        if (!reader) {
          throw new Error('Streaming not supported');
        }
        
        while (true) {
          const { done, value } = await reader.read();
          if (done) break;
          
          const text = decoder.decode(value);
          terminalOutput.innerHTML += text;
          terminalOutput.scrollTop = terminalOutput.scrollHeight;
        }
        
        terminalOutput.innerHTML += '\n<span class="text-green-400">✓ Installation complete!</span>\n';
        statusText.textContent = 'Installation complete!';
        doneBtn.disabled = false;
        
      } catch (err) {
        console.error('LXC auto-install error:', err);
        terminalOutput.innerHTML += `\n<span class="text-red-400">Error: ${err.message}</span>\n`;
        terminalOutput.innerHTML += '<span class="text-yellow-400">Please run the command manually in your server terminal.</span>\n';
        statusText.textContent = 'Installation failed - try manual install';
        doneBtn.disabled = false;
        doneBtn.textContent = 'Retry Sandbox Setup →';
      }
    }
    
    // Check sandbox status before opening files
    async function checkAndOpenSandbox() {
      try {
        await window.GINTO_AUTH_PROMISE;
        
        // Check sandbox status first
        const statusRes = await fetch('/sandbox/status', { credentials: 'same-origin' });
        const statusData = await statusRes.json().catch(() => null);
        
        if (!statusRes.ok || !statusData) {
          console.error('Failed to check sandbox status');
          // Fall back to old behavior
          openEditorDirectly();
          return;
        }
        
        // Update status indicator
        updateSandboxStatusIndicator(statusData.container_status || statusData.status);
        
        // Handle based on status
        if (statusData.status === 'unauthorized') {
          // Not logged in - redirect to login
          window.location.href = '/login?redirect=/chat';
          return;
        }
        
        if (statusData.status === 'not_created' || statusData.status === 'not_installed') {
          // No sandbox - show wizard
          showSandboxWizard();
          return;
        }
        
        if (statusData.status === 'installed' || statusData.container_status === 'stopped') {
          // Sandbox exists but not running - try to start it
          try {
            const startBody = new URLSearchParams();
            startBody.append('csrf_token', window.GINTO_AUTH?.csrfToken || '');
            const startRes = await fetch('/sandbox/start', {
              method: 'POST',
              credentials: 'same-origin',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: startBody.toString()
            });
            const startData = await startRes.json().catch(() => null);
            
            if (!startRes.ok || !startData?.success) {
              console.warn('Failed to start sandbox, opening anyway');
            } else {
              // Update indicator to running
              updateSandboxStatusIndicator('running');
            }
          } catch (e) {
            console.warn('Error starting sandbox:', e);
          }
        }
        
        // Update display with sandbox ID
        if (statusData.sandbox_id && sandboxIdDisplay) {
          sandboxIdDisplay.textContent = statusData.sandbox_id.substring(0, 8);
        }
        
        // Open editor with sandbox context
        // This displays the Monaco editor with the sandbox files in the explorer
        let editorUrl = '/editor?sandbox=' + encodeURIComponent(statusData.sandbox_id);
        editorIframe.src = editorUrl;
        editorModal.classList.remove('hidden');
        editorModal.classList.add('flex');
        
      } catch (err) {
        console.error('Error checking sandbox:', err);
        openEditorDirectly();
      }
    }
    
    function openEditorDirectly() {
      // Try to get sandbox ID from display
      let sandboxId = null;
      try {
        let displayNorm = (sandboxIdDisplay?.textContent || '').trim();
        if (displayNorm.startsWith('[') && displayNorm.endsWith(']')) displayNorm = displayNorm.slice(1, -1).trim();
        if (displayNorm && displayNorm !== 'unavailable' && displayNorm !== 'default') {
          sandboxId = displayNorm;
        }
      } catch (e) {}
      
      // Open editor with sandbox ID if available
      if (sandboxId) {
        editorIframe.src = '/editor?sandbox=' + encodeURIComponent(sandboxId);
      } else {
        // Fallback to editor without sandbox
        editorIframe.src = '/editor';
      }
      editorModal.classList.remove('hidden');
      editorModal.classList.add('flex');
    }
    
    // Open editor in modal - now checks sandbox status first
    openMyFilesBtn?.addEventListener('click', checkAndOpenSandbox);
    
    // Close editor modal
    closeEditorBtn?.addEventListener('click', () => {
      editorModal.classList.add('hidden');
      editorModal.classList.remove('flex');
      // Keep iframe loaded to preserve state
    });
    
    // ESC key closes editor
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && !editorModal.classList.contains('hidden')) {
        closeEditorBtn.click();
      }
    });
    
    // New chat button
    document.getElementById('new_chat')?.addEventListener('click', () => {
      if (window.newConvo) {
        window.newConvo();
      } else {
        // fallback: reload page if JS not loaded
        location.reload();
      }
    });
    
    // Example prompts
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
    
    // Auto-resize textarea
    const promptEl = document.getElementById('prompt');
    promptEl?.addEventListener('input', function() {
      this.style.height = 'auto';
      this.style.height = Math.min(this.scrollHeight, 160) + 'px';
    });
  </script>
  
  <script>
    (function(){
      const sidebar = document.getElementById('sidebar');
      const btn = document.getElementById('sidebar-collapse-toggle');
      const icon = document.getElementById('sidebar-collapse-icon');
      const key = 'ginto_sidebar_collapsed';
      function applyCollapsed(collapsed){
        if(!sidebar) return;
        if(collapsed) sidebar.classList.add('collapsed'); else sidebar.classList.remove('collapsed');
        if(icon) icon.style.transform = collapsed ? 'rotate(180deg)' : 'rotate(0deg)';
      }
      try{
        const saved = localStorage.getItem(key);
        if(saved === '1' && window.innerWidth >= 1024) applyCollapsed(true);
      }catch(e){}
      btn?.addEventListener('click', function(e){
        e.preventDefault();
        const collapsed = !sidebar.classList.contains('collapsed');
        applyCollapsed(collapsed);
        try{ localStorage.setItem(key, collapsed ? '1' : '0'); }catch(e){}
        // Update aria-expanded
        try{ btn.setAttribute('aria-expanded', collapsed ? 'true' : 'false'); }catch(e){}
      });
      // Remove collapsed on small screens to show hamburger-only
      window.addEventListener('resize', function(){
        try{
          if(window.innerWidth < 1024 && sidebar.classList.contains('collapsed')) sidebar.classList.remove('collapsed');
          else {
            const saved = localStorage.getItem(key);
            if(saved === '1' && window.innerWidth >= 1024) sidebar.classList.add('collapsed');
          }
        }catch(e){}
      });
    })();
  </script>

  <!-- Transaction Details Modal -->
  <div id="transaction-modal" class="fixed inset-0 z-[9999] hidden">
    <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="closeTransactionModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4 pointer-events-none">
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-md w-full pointer-events-auto max-h-[80vh] overflow-y-auto">
        <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
          <h3 class="font-semibold text-lg text-gray-900 dark:text-white flex items-center gap-2">
            <i class="fas fa-receipt text-amber-500"></i> Transaction Details
          </h3>
          <button onclick="closeTransactionModal()" class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div id="transaction-content" class="p-4">
          <div class="text-center py-8">
            <div class="animate-spin w-8 h-8 border-4 border-amber-500 border-t-transparent rounded-full mx-auto mb-3"></div>
            <p class="text-gray-500 dark:text-gray-400">Loading transaction details...</p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    function showTransactionDetails() {
      document.getElementById('transaction-modal').classList.remove('hidden');
      fetch('/api/user/payment-details', {
        credentials: 'same-origin',
        headers: { 
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-Token': window.GINTO_AUTH?.csrfToken || window.CSRF_TOKEN || ''
        }
      })
      .then(r => r.json())
      .then(data => {
        const content = document.getElementById('transaction-content');
        if (data.success && data.payment) {
          const p = data.payment;
          const pendingReviewsCount = data.pending_reviews_count || 0;
          const queuePosition = data.queue_position || null;
          const methodLabels = {
            'crypto_usdt_bep20': 'Crypto (USDT BEP20)',
            'bank_transfer': 'Bank Transfer',
            'gcash': 'GCash'
          };
          const method = methodLabels[p.payment_method] || p.payment_method;
          const statusColor = p.status === 'pending' ? 'text-amber-500' : 
                              p.status === 'completed' ? 'text-green-500' : 'text-red-500';
          
          // Build verification link based on payment method
          let verifyLink = '';
          if (p.payment_reference) {
            if (p.payment_method === 'crypto_usdt_bep20') {
              verifyLink = `<a href="https://bscscan.com/tx/${p.payment_reference}" target="_blank" class="text-blue-500 hover:underline text-sm"><i class="fas fa-external-link-alt mr-1"></i>View on BscScan</a>`;
            } else if (p.payment_method === 'gcash') {
              verifyLink = `<span class="text-blue-500 text-sm"><i class="fas fa-mobile-alt mr-1"></i>GCash Ref: ${p.payment_reference}</span>`;
            } else if (p.payment_method === 'bank_transfer') {
              verifyLink = `<span class="text-blue-500 text-sm"><i class="fas fa-university mr-1"></i>Bank Ref: ${p.payment_reference}</span>`;
            }
          }
          content.innerHTML = `
            <div class="space-y-3">
              <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-3">
                <p class="text-sm text-amber-800 dark:text-amber-200">
                  <i class="fas fa-info-circle mr-1"></i>
                  Your payment is being verified. This usually takes 24-48 hours.
                </p>
              </div>
              <div class="grid grid-cols-2 gap-3">
                <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3">
                  <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Transaction ID</p>
                  <p class="font-mono text-sm text-gray-900 dark:text-white">${p.transaction_id || '#' + p.id}</p>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3">
                  <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Status</p>
                  <p class="font-semibold text-sm ${statusColor} capitalize">${p.status}</p>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3">
                  <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Method</p>
                  <p class="font-medium text-sm text-gray-900 dark:text-white">${method}</p>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3">
                  <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Amount</p>
                  <p class="font-semibold text-sm text-gray-900 dark:text-white">${p.currency} ${parseFloat(p.amount).toFixed(2)}</p>
                </div>
              </div>
              ${p.payment_reference ? `
              <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3">
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Reference / TxHash</p>
                <p class="font-mono text-xs text-gray-900 dark:text-white break-all">${p.payment_reference}</p>
                ${verifyLink}
              </div>
              ` : ''}
              <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3">
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Submitted</p>
                <p class="text-sm text-gray-900 dark:text-white">${new Date(p.created_at).toLocaleString()}</p>
              </div>
              ${p.receipt_filename ? `
              <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-3">
                <p class="text-sm text-green-700 dark:text-green-300 mb-2">
                  <i class="fas fa-check-circle mr-1"></i> Receipt uploaded successfully
                </p>
                <div class="mt-2">
                  <a href="/receipt-image/${encodeURIComponent(p.receipt_filename)}" target="_blank" class="block">
                    <img src="/receipt-image/${encodeURIComponent(p.receipt_filename)}" 
                         alt="Payment Receipt" 
                         class="max-w-full max-h-48 rounded border border-gray-300 dark:border-gray-600 cursor-pointer hover:opacity-90 transition-opacity"
                         onerror="this.parentElement.innerHTML='<span class=\\'text-xs text-gray-500\\'>Unable to load receipt image</span>'"
                    />
                  </a>
                  <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Click to view full size</p>
                </div>
              </div>
              ` : ''}
              
              <!-- Audit Trail Section -->
              <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-3 uppercase tracking-wider">
                  <i class="fas fa-shield-alt mr-1"></i> Transaction Security Info
                </p>
                <div class="grid grid-cols-2 gap-2 text-xs">
                  ${p.ip_address ? `
                  <div class="bg-gray-100 dark:bg-gray-700 rounded p-2">
                    <span class="text-gray-500 dark:text-gray-400">IP Address</span>
                    <p class="font-mono text-gray-900 dark:text-white">${['127.0.0.1', '::1', 'localhost'].includes(p.ip_address) ? 'Local Development' : p.ip_address}</p>
                  </div>
                  ` : ''}
                  ${p.geo_country || p.geo_city ? `
                  <div class="bg-gray-100 dark:bg-gray-700 rounded p-2">
                    <span class="text-gray-500 dark:text-gray-400">Location</span>
                    <p class="text-gray-900 dark:text-white">${[p.geo_city, p.geo_country].filter(Boolean).join(', ') || 'Unknown'}</p>
                  </div>
                  ` : ''}
                </div>
                ${(() => {
                  try {
                    const device = p.device_info ? JSON.parse(p.device_info) : null;
                    if (!device) return '';
                    return `
                    <div class="mt-2 bg-gray-100 dark:bg-gray-700 rounded p-2 text-xs">
                      <span class="text-gray-500 dark:text-gray-400">Device</span>
                      <p class="text-gray-900 dark:text-white">
                        ${device.browser || 'Unknown Browser'}${device.browser_version ? ' ' + device.browser_version : ''} 
                        on ${device.os || 'Unknown OS'}${device.os_version ? ' ' + device.os_version : ''}
                        <span class="text-gray-500">(${device.device_type || 'unknown'})</span>
                      </p>
                    </div>
                    `;
                  } catch(e) { return ''; }
                })()}
              </div>
              
              <!-- Current Session Info (not saved) -->
              <!-- Current Session Info (not saved) -->
              <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-3 uppercase tracking-wider">
                  <i class="fas fa-eye mr-1"></i> Current Session (viewing now)
                </p>
                <div class="grid grid-cols-2 gap-2 text-xs" id="current-session-info">
                  <div class="bg-blue-50 dark:bg-blue-900/20 rounded p-2">
                    <span class="text-gray-500 dark:text-gray-400">Your IP</span>
                    <p class="font-mono text-gray-900 dark:text-white"><?= \Ginto\Helpers\TransactionHelper::getDisplayIp() ?></p>
                  </div>
                  <div class="bg-blue-50 dark:bg-blue-900/20 rounded p-2">
                    <span class="text-gray-500 dark:text-gray-400">Location</span>
                    <p class="text-gray-900 dark:text-white" id="current-geo">Detecting...</p>
                  </div>
                </div>
                <div class="mt-2 bg-blue-50 dark:bg-blue-900/20 rounded p-2 text-xs">
                  <span class="text-gray-500 dark:text-gray-400">Your Device</span>
                  <p class="text-gray-900 dark:text-white" id="current-device">Detecting...</p>
                </div>
              </div>
              
              <!-- Action Buttons for Pending Payments -->
              ${p.status === 'pending' ? `
              <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                <div class="flex flex-wrap gap-2">
                  <button onclick="refreshPaymentStatus(${p.id})" id="refresh-status-btn" class="flex items-center gap-1 px-3 py-2 text-sm font-medium text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 hover:bg-blue-100 dark:hover:bg-blue-900/30 rounded-lg transition-colors">
                    <i class="fas fa-sync-alt"></i>
                    <span>Refresh Status</span>
                  </button>
                  ${p.admin_review_requested ? `
                  <span class="flex items-center gap-1 px-3 py-2 text-sm font-medium text-green-600 dark:text-green-400 bg-green-50 dark:bg-green-900/20 rounded-lg">
                    <i class="fas fa-check-circle"></i>
                    Admin Review Requested
                  </span>
                  ` : `
                  <button onclick="requestAdminReview(${p.id})" id="request-review-btn" class="flex items-center gap-1 px-3 py-2 text-sm font-medium text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 hover:bg-amber-100 dark:hover:bg-amber-900/30 rounded-lg transition-colors">
                    <i class="fas fa-user-shield"></i>
                    <span>Request Admin Review</span>
                  </button>
                  `}
                </div>
                ${p.admin_review_requested && queuePosition ? `
                <div class="mt-2 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-2">
                  <p class="text-xs text-blue-700 dark:text-blue-300">
                    <i class="fas fa-users mr-1"></i>
                    Your position in queue: <strong>#${queuePosition}</strong> of ${pendingReviewsCount} pending reviews
                  </p>
                </div>
                ` : ''}
                <p id="status-message" class="mt-2 text-xs text-gray-500 dark:text-gray-400 hidden"></p>
              </div>
              ` : ''}
            </div>
          `;
          
          // Detect current session info client-side
          setTimeout(() => {
            // Parse current user agent
            const ua = navigator.userAgent;
            let browser = 'Unknown', os = 'Unknown', deviceType = 'desktop';
            
            if (ua.includes('Chrome')) browser = 'Chrome ' + (ua.match(/Chrome\/(\d+)/)?.[1] || '');
            else if (ua.includes('Firefox')) browser = 'Firefox ' + (ua.match(/Firefox\/(\d+)/)?.[1] || '');
            else if (ua.includes('Safari') && !ua.includes('Chrome')) browser = 'Safari';
            else if (ua.includes('Edge') || ua.includes('Edg')) browser = 'Edge';
            
            if (ua.includes('Windows')) os = 'Windows';
            else if (ua.includes('Mac OS')) os = 'macOS';
            else if (ua.includes('Android')) { os = 'Android'; deviceType = 'mobile'; }
            else if (ua.includes('iPhone') || ua.includes('iPad')) { os = 'iOS'; deviceType = ua.includes('iPad') ? 'tablet' : 'mobile'; }
            else if (ua.includes('Linux')) os = 'Linux';
            
            const deviceEl = document.getElementById('current-device');
            if (deviceEl) deviceEl.textContent = `${browser} on ${os} (${deviceType})`;
            
            // Get current location from IP
            fetch('https://ipapi.co/json/')
              .then(r => r.json())
              .then(geo => {
                const geoEl = document.getElementById('current-geo');
                if (geoEl) geoEl.textContent = `${geo.city || ''}, ${geo.country_code || ''}`.replace(/^, |, $/g, '') || 'Unknown';
              })
              .catch(() => {
                const geoEl = document.getElementById('current-geo');
                if (geoEl) geoEl.textContent = 'Unavailable';
              });
          }, 100);
        } else {
          content.innerHTML = `
            <div class="text-center py-6">
              <i class="fas fa-exclamation-circle text-4xl text-gray-400 mb-3"></i>
              <p class="text-gray-500 dark:text-gray-400">${data.message || 'No pending payment found.'}</p>
            </div>
          `;
        }
      })
      .catch(err => {
        document.getElementById('transaction-content').innerHTML = `
          <div class="text-center py-6">
            <i class="fas fa-exclamation-triangle text-4xl text-red-400 mb-3"></i>
            <p class="text-red-500">Failed to load transaction details.</p>
          </div>
        `;
      });
    }
    
    function closeTransactionModal() {
      document.getElementById('transaction-modal').classList.add('hidden');
    }
    
    function refreshPaymentStatus(paymentId) {
      const btn = document.getElementById('refresh-status-btn');
      const msgEl = document.getElementById('status-message');
      
      if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Checking...</span>';
      }
      
      fetch(`/api/payment/check-status/${paymentId}`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 
          'Content-Type': 'application/json',
          'X-CSRF-Token': window.GINTO_AUTH?.csrfToken || window.CSRF_TOKEN || ''
        }
      })
      .then(r => {
        if (!r.ok) {
          return r.json().then(err => { throw new Error(err.message || 'Request failed: ' + r.status); });
        }
        return r.json();
      })
      .then(data => {
        if (data.success) {
          if (data.status_changed) {
            // Status changed - reload the modal
            msgEl.textContent = `Status updated to: ${data.new_status}`;
            msgEl.className = 'mt-2 text-xs text-green-600 dark:text-green-400';
            msgEl.classList.remove('hidden');
            // Refresh modal after a short delay
            setTimeout(() => showTransactionDetails(paymentId), 1500);
          } else {
            // No change
            msgEl.textContent = `Status is still: ${data.current_status}`;
            msgEl.className = 'mt-2 text-xs text-gray-500 dark:text-gray-400';
            msgEl.classList.remove('hidden');
          }
        } else {
          msgEl.textContent = data.message || 'Failed to check status';
          msgEl.className = 'mt-2 text-xs text-red-500';
          msgEl.classList.remove('hidden');
        }
      })
      .catch(err => {
        msgEl.textContent = err.message || 'Network error checking status';
        msgEl.className = 'mt-2 text-xs text-red-500';
        msgEl.classList.remove('hidden');
      })
      .finally(() => {
        if (btn) {
          btn.disabled = false;
          btn.innerHTML = '<i class="fas fa-sync-alt"></i> <span>Refresh Status</span>';
        }
      });
    }
    
    function requestAdminReview(paymentId) {
      const btn = document.getElementById('request-review-btn');
      const msgEl = document.getElementById('status-message');
      
      if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Requesting...</span>';
      }
      
      fetch(`/api/payment/request-review/${paymentId}`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 
          'Content-Type': 'application/json',
          'X-CSRF-Token': window.GINTO_AUTH?.csrfToken || window.CSRF_TOKEN || ''
        }
      })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          // Replace button with confirmation
          if (btn) {
            btn.outerHTML = `
              <span class="flex items-center gap-1 px-3 py-2 text-sm font-medium text-green-600 dark:text-green-400 bg-green-50 dark:bg-green-900/20 rounded-lg">
                <i class="fas fa-check-circle"></i>
                Admin Review Requested
              </span>
            `;
          }
          msgEl.textContent = 'An admin will review your payment shortly.';
          msgEl.className = 'mt-2 text-xs text-green-600 dark:text-green-400';
          msgEl.classList.remove('hidden');
        } else {
          msgEl.textContent = data.message || 'Failed to request review';
          msgEl.className = 'mt-2 text-xs text-red-500';
          msgEl.classList.remove('hidden');
          if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-user-shield"></i> <span>Request Admin Review</span>';
          }
        }
      })
      .catch(err => {
        msgEl.textContent = 'Network error requesting review';
        msgEl.className = 'mt-2 text-xs text-red-500';
        msgEl.classList.remove('hidden');
        if (btn) {
          btn.disabled = false;
          btn.innerHTML = '<i class="fas fa-user-shield"></i> <span>Request Admin Review</span>';
        }
      });
    }
  </script>

  <script src="/assets/js/chat.js"></script>
</body>
</html>
