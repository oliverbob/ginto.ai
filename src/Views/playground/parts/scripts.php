<?php
/**
 * Playground - Scripts partial
 * JavaScript for sidebar, theme, and interactivity
 */
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Enable theme transitions after initial load (prevents flash)
    requestAnimationFrame(() => {
        document.documentElement.classList.add('theme-transition');
    });
    
    // Elements
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebarCollapse = document.getElementById('sidebar-collapse');
    const sidebarOverlay = document.getElementById('sidebar-overlay');
    const collapseIcon = document.getElementById('collapse-icon');
    const themeToggle = document.getElementById('theme-toggle');
    const themeIcon = document.getElementById('theme-icon');
    const userMenuBtn = document.getElementById('user-menu-btn');
    const userDropdown = document.getElementById('user-dropdown');
    const globalSearch = document.getElementById('global-search');
    
    // State
    let isCollapsed = localStorage.getItem('sidebar-collapsed') === 'true';
    let isMobileOpen = false;
    
    // Initialize sidebar state
    if (isCollapsed && window.innerWidth >= 1024) {
        sidebar.classList.add('collapsed');
        mainContent.classList.add('expanded');
        if (collapseIcon) collapseIcon.style.transform = 'rotate(180deg)';
    }
    
    // Update theme icon based on current state
    function updateThemeIcon() {
        if (!themeIcon) return;
        const isDark = document.documentElement.classList.contains('dark');
        themeIcon.innerHTML = isDark 
            ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />'
            : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />';
    }
    updateThemeIcon();
    
    // Mobile toggle
    sidebarToggle?.addEventListener('click', function() {
        isMobileOpen = !isMobileOpen;
        sidebar.classList.toggle('open', isMobileOpen);
        sidebarOverlay.classList.toggle('opacity-0', !isMobileOpen);
        sidebarOverlay.classList.toggle('invisible', !isMobileOpen);
        document.body.classList.toggle('overflow-hidden', isMobileOpen);
    });
    
    // Close on overlay click
    sidebarOverlay?.addEventListener('click', function() {
        isMobileOpen = false;
        sidebar.classList.remove('open');
        sidebarOverlay.classList.add('opacity-0', 'invisible');
        document.body.classList.remove('overflow-hidden');
    });
    
    // Desktop collapse toggle
    sidebarCollapse?.addEventListener('click', function() {
        isCollapsed = !isCollapsed;
        sidebar.classList.toggle('collapsed', isCollapsed);
        mainContent.classList.toggle('expanded', isCollapsed);
        if (collapseIcon) collapseIcon.style.transform = isCollapsed ? 'rotate(180deg)' : '';
        localStorage.setItem('sidebar-collapsed', isCollapsed);
    });
    
    // Theme toggle with proper cycling
    themeToggle?.addEventListener('click', function() {
        const isDark = document.documentElement.classList.toggle('dark');
        localStorage.setItem('playground-theme', isDark ? 'dark' : 'light');
        updateThemeIcon();
    });
    
    // Listen for system theme changes
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
        const storedTheme = localStorage.getItem('playground-theme');
        // Only auto-switch if user hasn't set a preference
        if (!storedTheme) {
            if (e.matches) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
            updateThemeIcon();
        }
    });
    
    // User menu dropdown
    userMenuBtn?.addEventListener('click', function(e) {
        e.stopPropagation();
        const isOpen = !userDropdown.classList.contains('invisible');
        if (isOpen) {
            userDropdown.classList.add('opacity-0', 'invisible', 'translate-y-2');
        } else {
            userDropdown.classList.remove('opacity-0', 'invisible', 'translate-y-2');
        }
    });
    
    // Close dropdown on outside click
    document.addEventListener('click', function(e) {
        if (!userDropdown?.contains(e.target) && !userMenuBtn?.contains(e.target)) {
            userDropdown?.classList.add('opacity-0', 'invisible', 'translate-y-2');
        }
    });
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Cmd/Ctrl + K for search
        if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
            e.preventDefault();
            globalSearch?.focus();
        }
        
        // Escape to close modals/dropdowns
        if (e.key === 'Escape') {
            userDropdown?.classList.add('opacity-0', 'invisible', 'translate-y-2');
            if (isMobileOpen) {
                isMobileOpen = false;
                sidebar.classList.remove('open');
                sidebarOverlay.classList.add('opacity-0', 'invisible');
                document.body.classList.remove('overflow-hidden');
            }
        }
        
        // Cmd/Ctrl + B to toggle sidebar
        if ((e.metaKey || e.ctrlKey) && e.key === 'b') {
            e.preventDefault();
            sidebarCollapse?.click();
        }
    });
    
    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 1024) {
            // Close mobile menu on desktop
            isMobileOpen = false;
            sidebar.classList.remove('open');
            sidebarOverlay.classList.add('opacity-0', 'invisible');
            document.body.classList.remove('overflow-hidden');
        }
    });
    
    // Add ripple effect to menu items
    document.querySelectorAll('.menu-item').forEach(item => {
        item.addEventListener('click', function(e) {
            const ripple = document.createElement('span');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.style.cssText = `
                position: absolute;
                width: ${size}px;
                height: ${size}px;
                left: ${x}px;
                top: ${y}px;
                background: currentColor;
                opacity: 0.1;
                border-radius: 50%;
                transform: scale(0);
                animation: ripple 0.6s ease-out;
                pointer-events: none;
            `;
            
            this.style.position = 'relative';
            this.style.overflow = 'hidden';
            this.appendChild(ripple);
            
            setTimeout(() => ripple.remove(), 600);
        });
    });
});

// Ripple animation
const style = document.createElement('style');
style.textContent = `
    @keyframes ripple {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);
</script>
