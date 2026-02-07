<?php
/**
 * Chat Head Section
 * Document head with meta, scripts, global config, and library includes
 * 
 * Required variables from parent:
 * - $title (string): Page title
 * - $isLoggedIn (bool): User login status
 * - $userId (int|null): User ID
 * - $isAdmin (bool): Admin status
 * - $sandboxBackend (string): 'docker' or 'lxd'
 */
?>
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="icon" type="image/png" href="/assets/images/ginto.png" />
  <title><?= htmlspecialchars($title ?? 'Ginto Chat') ?></title>
  <script>
    // Ginto installation path (detected from PHP)
    window.GINTO_PATH = <?= json_encode(dirname(__DIR__, 4)) ?>;
    
    // Sandbox backend type (docker or lxd)
    window.SANDBOX_BACKEND = <?= json_encode($sandboxBackend) ?>;
    
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
              
              // Set sandbox info for visitors
              if (data.sandbox) {
                window.GINTO_AUTH.sandbox = data.sandbox;
                console.log('Visitor sandbox ID:', data.sandbox.id);
              }
              
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
  
  <!-- PayPal SDK for addon subscriptions -->
  <?php 
  $paypalEnv = $_ENV['PAYPAL_ENVIRONMENT'] ?? getenv('PAYPAL_ENVIRONMENT') ?? 'sandbox';
  $paypalClientId = $paypalEnv === 'sandbox' 
    ? ($_ENV['PAYPAL_CLIENT_ID_SANDBOX'] ?? getenv('PAYPAL_CLIENT_ID_SANDBOX') ?? '')
    : ($_ENV['PAYPAL_CLIENT_ID'] ?? getenv('PAYPAL_CLIENT_ID') ?? '');
  $paypalClientId = preg_replace('/\s+/', '', $paypalClientId);
  ?>
  <script src="https://www.paypal.com/sdk/js?client-id=<?= htmlspecialchars($paypalClientId, ENT_QUOTES, 'UTF-8') ?>&vault=true&intent=subscription&components=buttons"></script>
  
  <!-- Font Awesome for small icons in the sidebar -->
  <link rel="stylesheet" href="/lib/fontawesome/css/all.min.css">
  
  <!-- Standard LLM response rendering stack -->
  <!-- Highlight.js for code syntax highlighting -->
  <link rel="stylesheet" href="/lib/highlight/github-dark.min.css">
  <script src="/lib/highlight/highlight.min.js"></script>
  
  <!-- marked.js for markdown parsing -->
  <script src="/lib/marked/marked.min.js"></script>
  
  <!-- KaTeX for LaTeX math rendering -->
  <link rel="stylesheet" href="/lib/katex/katex.min.css">
  <script src="/lib/katex/katex.min.js"></script>
  <script src="/lib/katex/contrib/auto-render.min.js"></script>
  
  <!-- CodeMirror for code editing (keep for editor functionality) -->
  <link rel="stylesheet" href="/assets/lib/codemirror/5.65.18/codemirror.min.css">
  <link rel="stylesheet" href="/assets/lib/codemirror/5.65.18/theme/material-darker.min.css">
  <script src="/assets/lib/codemirror/5.65.18/codemirror.min.js"></script>
  <script src="/assets/lib/codemirror/5.65.18/mode/xml/xml.min.js"></script>
  <script src="/assets/lib/codemirror/5.65.18/mode/javascript/javascript.min.js"></script>
  <script src="/assets/lib/codemirror/5.65.18/mode/css/css.min.js"></script>
  <script src="/assets/lib/codemirror/5.65.18/mode/clike/clike.min.js"></script>
  <script src="/assets/lib/codemirror/5.65.18/mode/htmlmixed/htmlmixed.min.js"></script>
  <script src="/assets/lib/codemirror/5.65.18/mode/php/php.min.js"></script>
  <script src="/assets/lib/codemirror/5.65.18/mode/python/python.min.js"></script>
  <script src="/assets/lib/codemirror/5.65.18/mode/shell/shell.min.js"></script>
  <script src="/assets/lib/codemirror/5.65.18/mode/sql/sql.min.js"></script>
  <script src="/assets/lib/codemirror/5.65.18/mode/markdown/markdown.min.js"></script>
  
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
  
  <?php include __DIR__ . '/styles.php'; ?>
  <script>
    // Auto-attach X-CSRF-Token header for JSON fetch requests when token available
    (function(){
      function getCsrf() {
        return (window.GINTO_AUTH && window.GINTO_AUTH.csrfToken) || window.CSRF_TOKEN || (document.querySelector('meta[name="csrf-token"]')?.content) || '';
      }
      const origFetch = window.fetch.bind(window);
      window.fetch = function(resource, init){
        try {
          init = init || {};
          const method = (init.method || 'GET').toUpperCase();
          const token = getCsrf();
          if (token && method !== 'GET') {
            // Normalize headers into a Headers object so we can set values safely
            const headers = new Headers(init.headers || {});
            // Always set X-CSRF-Token for mutating requests
            headers.set('X-CSRF-Token', token);
            init.headers = headers;
          }
        } catch (e) {
          // Fail silently - do not block network calls
        }
        return origFetch(resource, init);
      };
    })();
  </script>
</head>
