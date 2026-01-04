<?php
/**
 * Chat Page Scripts
 * Contains all JavaScript functionality for the chat interface
 */
?>
<!-- Sidebar & Settings Toggle Scripts -->
<script>
  // Theme toggle functionality
  (function initTheme() {
    const html = document.documentElement;
    const themeToggle = document.getElementById('theme-toggle');
    const mobileThemeToggle = document.getElementById('mobile-theme-toggle');
    
    // Check for saved preference - default to dark if not set
    const savedTheme = localStorage.getItem('ginto-theme');
    if (savedTheme) {
      html.classList.toggle('dark', savedTheme === 'dark');
    } else {
      // Default to dark mode (already set in HTML, just ensure it's there)
      html.classList.add('dark');
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
</script>

<?php include __DIR__ . '/scripts-modals.php'; ?>
<?php include __DIR__ . '/scripts-api-keys.php'; ?>
<?php include __DIR__ . '/scripts-editor.php'; ?>
<?php include __DIR__ . '/scripts-sandbox-wizard.php'; ?>
<?php include __DIR__ . '/scripts-vnc-console.php'; ?>
<?php include __DIR__ . '/scripts-prompts.php'; ?>
<?php include __DIR__ . '/scripts-transaction-modal.php'; ?>
<?php if (!empty($isAdmin)): ?>
<?php include __DIR__ . '/scripts-model-selector.php'; ?>
<?php endif; ?>

<script src="/assets/js/chat.js?v=<?= time() ?>"></script>
