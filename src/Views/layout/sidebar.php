<?php
/**
 * Shared sidebar partial extracted from user/network-tree.php
 * Renders a responsive, theme-aware sidebar and hamburger button.
 */
?>

<?php
// Include icon helper for consistent icon colors
// Parts moved into admin/parts — include from there so shared layouts can access the admin helpers
require_once __DIR__ . '/../admin/parts/icon_helpers.php';
?>

<!-- Hamburger Menu Button -->
<button id="hamburgerBtn" class="fixed top-4 left-4 z-50 bg-gray-800 hover:bg-gray-700 dark:bg-gray-700 dark:hover:bg-gray-600 text-white p-3 rounded-lg shadow-lg transition-all duration-200 hover:scale-105 lg:hidden">
    <i class="fas fa-bars text-lg"></i>
</button>

<!-- Sidebar -->
<div id="sidebar" class="sidebar fixed left-0 top-0 h-full w-64 themed-sidebar text-gray-900 dark:text-white shadow-xl transform -translate-x-full lg:translate-x-0 transition-all duration-300 ease-in-out z-50 flex flex-col themed-border">
    <!-- Sidebar Header -->
    <div class="flex items-center justify-between p-4 border-b themed-border" style="position:relative;height:72px;">
        <div class="flex items-center space-x-3">
            <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center">
                <i class="fas fa-user text-white text-sm"></i>
            </div>
            <div>
                <div class="flex items-center">
                    <h2 class="font-bold text-lg themed-text">User Panel</h2>
                </div>
                <p class="text-xs themed-text-secondary">Member Console</p>
            </div>
        </div>
        <button id="closeSidebar" class="lg:hidden themed-text-secondary">
            <i class="fas fa-times"></i>
        </button>
            <!-- Small chevron placed at the top-right of the sidebar header (visible only inside the nav) -->
            <span id="sidebarPanelArrow" class="hidden lg:inline-block themed-text-secondary transition-transform duration-200" title="Collapse/Expand" style="position:absolute; right:12px; top:8px; font-size:20px; padding:10px; cursor:pointer;">
                <i class="fas fa-chevron-left"></i>
            </span>
        
    </div>

    <!-- Navigation Links -->
    <nav class="flex-1 p-4 space-y-2">
        <a href="/dashboard" class="flex items-center px-4 py-2 themed-hover rounded-md transition-colors group">
            <i class="fas fa-home w-5 text-center mr-3 <?= activeIconClass('/dashboard', 'themed-text-secondary') ?>"></i>
            <span class="font-medium themed-text">Dashboard</span>
        </a>
        <!-- Additional dashboard menu items imported from ginto dashboard view -->
        <a href="/downline" class="flex items-center px-4 py-2 themed-hover rounded-lg transition-colors group">
            <i class="fas fa-users w-5 text-center mr-3 <?= activeIconClass('/downline', 'text-green-600 dark:text-green-500') ?>"></i>
            <span class="font-medium themed-text">My Network</span>
        </a>
        <a href="/user/network-tree" class="flex items-center px-4 py-2 themed-hover rounded-lg transition-colors group">
            <i class="fas fa-sitemap w-5 text-center mr-3 <?= activeIconClass('/user/network-tree', 'text-blue-600 dark:text-blue-400') ?>"></i>
            <span class="font-medium themed-text">Network Tree</span>
        </a>
        <a href="#" class="flex items-center px-4 py-2 themed-hover rounded-lg transition-colors group">
            <i class="fab fa-bitcoin w-5 text-center mr-3 <?= activeIconClass('/', 'text-amber-500 dark:text-amber-400') ?>"></i>
            <span class="font-medium themed-text">BTC Earnings</span>
        </a>
        <a href="/dashboard#registrationForm" class="flex items-center px-4 py-2 themed-hover rounded-lg transition-colors group">
            <i class="fas fa-user-plus w-5 text-center mr-3 <?= activeIconClass('/dashboard#registrationForm', 'text-green-500 dark:text-green-400') ?>"></i>
            <span class="font-medium themed-text">New Registration</span>
        </a>
        <a href="#" class="flex items-center px-4 py-2 themed-hover rounded-lg transition-colors group">
            <i class="fas fa-exchange-alt w-5 text-center mr-3 <?= activeIconClass('/', 'text-purple-500 dark:text-purple-400') ?>"></i>
            <span class="font-medium themed-text">Transactions</span>
        </a>
        <a href="/user/commissions" class="flex items-center px-4 py-2 themed-hover rounded-lg transition-colors group">
            <i class="fas fa-dollar-sign w-5 text-center mr-3 <?= activeIconClass('/user/commissions', 'text-yellow-600 dark:text-yellow-400') ?>"></i>
            <span class="font-medium themed-text">Commissions</span>
        </a>
        <a href="/user/settings" class="flex items-center px-4 py-2 themed-hover rounded-lg transition-colors group">
            <i class="fas fa-cog w-5 text-center mr-3 <?= activeIconClass('/user/settings', 'themed-text-secondary') ?>"></i>
            <span class="font-medium themed-text">Settings</span>
        </a>
        <?php if (isset($_SESSION['role_id']) && in_array($_SESSION['role_id'], [1,2])): ?>
        <a href="/admin" class="flex items-center px-4 py-2 themed-hover rounded-lg transition-colors group">
            <i class="fas fa-user-shield w-5 text-center mr-3 <?= activeIconClass('/admin', 'text-red-600 dark:text-red-500') ?>"></i>
            <span class="font-medium themed-text">Admin Panel</span>
        </a>
        <a href="/playground" class="flex items-center px-4 py-2 themed-hover rounded-lg transition-colors group">
            <i class="fas fa-code w-5 text-center mr-3 <?= activeIconClass('/playground', 'text-violet-500 dark:text-violet-400') ?>"></i>
            <span class="font-medium themed-text" style="color: #8b5cf6;">Playground</span>
        </a>
        <?php endif; ?>
        <!-- Theme toggle (keeps parity with dashboard view) -->
        <button id="themeToggleNav" class="flex items-center px-4 py-2 themed-hover rounded-lg w-full group transition-colors duration-200">
            <i class="fas fa-moon mr-3 dark:hidden text-blue-600 group-hover:text-blue-700"></i>
            <i class="fas fa-sun mr-3 hidden dark:block text-amber-400 group-hover:text-amber-300"></i>
            <span class="dark:hidden">Dark Mode</span>
            <span class="hidden dark:block">Light Mode</span>
        </button>
    </nav>

    <!-- User Profile Section -->
    <div class="p-4 border-t border-b border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 rounded-none themed-border themed-card" style="border-radius:0; border-left:0; border-right:0;">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-purple-600 rounded-none flex items-center justify-center" style="border-radius:0;">
                <span class="text-white font-semibold text-sm">U</span>
            </div>
            <div class="flex-1 min-w-0">
                <?php $username = $_SESSION['username'] ?? 'User'; ?>
                <p class="text-sm font-medium text-gray-900 dark:text-white themed-text truncate"><?= htmlspecialchars($username) ?></p>
                <p class="text-xs text-gray-500 dark:text-gray-400 themed-text-secondary truncate">Member</p>
            </div>
            <a href="/logout" class="text-gray-500 dark:text-gray-400 themed-text-secondary p-1 rounded hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors" title="Sign Out">
                <i class="fas fa-sign-out-alt text-sm <?= activeIconClass('/logout', 'themed-text-secondary') ?>"></i>
            </a>
        </div>
    </div>
</div>

<!-- Overlay for mobile (support either overlay id used across views) -->
<div id="sidebarOverlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 hidden lg:hidden"></div>
<div id="sidebarBackdrop" style="display:none"></div>

<!-- Color picker UI is centralized in Admin → Settings — any server-saved colors are applied automatically on page load -->

<style>
/* Collapsed sidebar behaviour on desktop */
@media (min-width:1024px) {
    #sidebar { width: 16rem; }
    #sidebar.collapsed { width: 4.5rem; }
    /* hide text labels when collapsed */
    #sidebar.collapsed nav a span { display: none; }
    /* center icons when collapsed */
    #sidebar.collapsed nav a i { margin-right: 0; display: inline-block; width: 100%; text-align: center; }
    /* keep header compact spacing when collapsed */
    #sidebar.collapsed .flex.items-center.space-x-3 { gap: 0; }
    /* center theme toggle icon when collapsed */
    #sidebar.collapsed #themeToggleNav i { margin-right: 0; display: inline-block; width: 100%; text-align: center; }
    #sidebar.collapsed #themeToggleNav span { display: none; }


/* Modern scrollbar styling for sidebar/dashboard */
#sidebar, .dashboard-scroll, body {
    scrollbar-width: thin;
    scrollbar-color: #4B5563 #1F2937;
}
#sidebar::-webkit-scrollbar, .dashboard-scroll::-webkit-scrollbar, body::-webkit-scrollbar {
    width: 8px;
    background: #1F2937;
    border-radius: 8px;
}
#sidebar::-webkit-scrollbar-thumb, .dashboard-scroll::-webkit-scrollbar-thumb, body::-webkit-scrollbar-thumb {
    background: #4B5563;
    border-radius: 8px;
    border: 2px solid #1F2937;
}
#sidebar::-webkit-scrollbar-thumb:hover, .dashboard-scroll::-webkit-scrollbar-thumb:hover, body::-webkit-scrollbar-thumb:hover {
    background: #6B7280;
}
    /* hide other textual elements when collapsed */
    #sidebar.collapsed .font-bold.text-lg.themed-text { display: none; } /* header title */
    #sidebar.collapsed .text-xs.themed-text-secondary { display: none; } /* header subtitle and small labels */
    #sidebar.collapsed .flex-1.min-w-0 { display: none; } /* profile name/role */
    #sidebar.collapsed #themeToggleNav span { display: none; } /* theme toggle text */

    /* compact nav item padding when collapsed */
    #sidebar.collapsed nav a { padding-left: 0.5rem; padding-right: 0.5rem; justify-content: center; }
    #sidebar.collapsed nav { align-items: center; }
    /* ensure header icons remain visible and aligned */
    #sidebar.collapsed .w-8.h-8 { margin-left: 0.15rem; }
}

.sidebar-nav, .sidebar-menu, .sidebar a {
  font-size: 0.95rem; /* or 0.95rem, adjust as needed */
}

</style>

<script>
function initializeUniversalSidebar() {
    const sidebar = document.getElementById('sidebar');
    // accept either hamburger id used in different views
    const hamburgerBtn = document.getElementById('hamburgerBtn') || document.getElementById('hamburgerToggle');
    // accept either overlay/backdrop id
    const overlay = document.getElementById('sidebarOverlay') || document.getElementById('sidebarBackdrop');
    // accept either main content id used across views
    const mainContent = document.getElementById('mainContent') || document.getElementById('mainContentWrapper');

    // Helper that returns true when the sidebar is effectively visible
    const isSidebarVisible = () => window.innerWidth >= 1024 || (sidebar && !sidebar.classList.contains('-translate-x-full'));

    function updateHamburgerIcon(isOpen) {
        if (!hamburgerBtn) return;
        // Use a left-arrow when open to indicate 'close/hide'
        hamburgerBtn.innerHTML = isOpen ? '<i class="fas fa-arrow-left text-lg"></i>' : '<i class="fas fa-bars text-lg"></i>';
        hamburgerBtn.title = isOpen ? 'Hide navigation' : 'Show navigation';
    }

    function updateSidebarPanelArrow(isOpen) {
        const el = document.getElementById('sidebarPanelArrow');
        if (!el) return;
        // On desktop, arrow indicates collapse/expand (collapsed => show right chevron)
        if (window.innerWidth >= 1024 && sidebar) {
            if (sidebar.classList.contains('collapsed')) {
                el.innerHTML = '<i class="fas fa-chevron-right"></i>'; // collapsed: show expand
            } else {
                el.innerHTML = '<i class="fas fa-chevron-left"></i>'; // expanded: show collapse
            }
        } else {
            // Mobile: reflect open/closed overlay state
            el.innerHTML = isOpen ? '<i class="fas fa-chevron-right"></i>' : '<i class="fas fa-chevron-left"></i>';
        }
        // also rotate for a smooth visual cue (optional)
        if (el.firstElementChild) el.classList.toggle('rotate-0', !sidebar?.classList.contains('collapsed'));
    }

    function openSidebar() {
        sidebar.classList.remove('-translate-x-full');
        overlay.classList.remove('hidden');
        updateHamburgerIcon(true);
        updateSidebarPanelArrow(true);
        if (mainContent && window.innerWidth >= 1024) {
            mainContent.style.marginLeft = '16rem';
        }
    }
    function closeSidebarFn() {
        sidebar.classList.add('-translate-x-full');
        overlay.classList.add('hidden');
        updateHamburgerIcon(false);
        updateSidebarPanelArrow(false);
        if (mainContent) {
            mainContent.style.marginLeft = '0';
        }
    }

    // Toggle behavior: if sidebar closed -> open, else close
    function toggleSidebar() {
        if (!sidebar) return;
        const isClosed = sidebar.classList.contains('-translate-x-full');
        if (isClosed) openSidebar(); else closeSidebarFn();
    }

    hamburgerBtn?.addEventListener('click', toggleSidebar);
    // allow clicking the small chevron in the header to collapse (desktop) or toggle (mobile)
    const panelArrow = document.getElementById('sidebarPanelArrow');
    if (panelArrow) {
        panelArrow.setAttribute('role', 'button');
        panelArrow.setAttribute('tabindex', '0');
        function collapseSidebarDesktop() {
            if (!sidebar) return;
            sidebar.classList.add('collapsed');
            if (mainContent && window.innerWidth >= 1024) mainContent.style.marginLeft = '4.5rem';
            updateHamburgerIcon(true);
            updateSidebarPanelArrow(false);
        }
        function expandSidebarDesktop() {
            if (!sidebar) return;
            sidebar.classList.remove('collapsed');
            if (mainContent && window.innerWidth >= 1024) mainContent.style.marginLeft = '16rem';
            updateHamburgerIcon(true);
            updateSidebarPanelArrow(true);
        }
        panelArrow.addEventListener('click', function(e){
            e.stopPropagation();
            if (window.innerWidth >= 1024) {
                // collapse / expand behaviour on desktop
                if (sidebar.classList.contains('collapsed')) expandSidebarDesktop(); else collapseSidebarDesktop();
            } else {
                toggleSidebar();
            }
        });
        panelArrow.addEventListener('keydown', function(e){
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault();
                if (window.innerWidth >= 1024) {
                    if (sidebar.classList.contains('collapsed')) expandSidebarDesktop(); else collapseSidebarDesktop();
                } else {
                    toggleSidebar();
                }
            }
        });
    }
    overlay?.addEventListener('click', closeSidebarFn);
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 1024) {
            overlay.classList.add('hidden');
            // on desktop, respect collapsed state
            if (sidebar && sidebar.classList.contains('collapsed')) {
                if (mainContent) mainContent.style.marginLeft = '4.5rem';
                updateHamburgerIcon(true);
                updateSidebarPanelArrow(false);
            } else {
                if (mainContent) mainContent.style.marginLeft = '16rem';
                updateHamburgerIcon(true);
                updateSidebarPanelArrow(true);
            }
        } else {
            // mobile/tablet
            if (mainContent) mainContent.style.marginLeft = '0';
            updateHamburgerIcon(false);
            updateSidebarPanelArrow(false);
        }
    });
    if (mainContent) {
        mainContent.style.marginLeft = '0';
    }

    // Ensure icon and arrow reflect initial state (treat desktop as visible)
    const initialOpen = isSidebarVisible();
    updateHamburgerIcon(initialOpen);
    updateSidebarPanelArrow(initialOpen);
    // Apply initial main content margin when sidebar is visible on desktop
    if (mainContent) {
        if (initialOpen && window.innerWidth >= 1024) {
            mainContent.style.marginLeft = '16rem';
        } else {
            mainContent.style.marginLeft = '0';
        }
    }
}
document.addEventListener('DOMContentLoaded', function() {
    initializeUniversalSidebar();
});
</script>
