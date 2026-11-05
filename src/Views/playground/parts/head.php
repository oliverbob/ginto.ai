<?php
/**
 * Playground - Head partial
 * Theme-aware head section with CSS, meta tags, and dark mode support
 */
$pageTitle = $pageTitle ?? 'Playground - Ginto CMS';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <link rel="shortcut icon" href="/assets/images/ginto.png">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    
    <!-- Theme detection script - MUST run before anything else to prevent flash -->
    <script>
        (function() {
            const stored = localStorage.getItem('playground-theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const shouldBeDark = stored === 'dark' || (!stored && prefersDark);
            
            if (shouldBeDark) {
                document.documentElement.classList.add('dark');
            }
            window.__initialTheme = shouldBeDark ? 'dark' : 'light';
        })();
    </script>
    
    <!-- Tailwind CSS -->
    <link href="/assets/css/tailwind.css" rel="stylesheet">
    <!-- Ensure FontAwesome is available so the editor action icons render correctly -->
    <link rel="stylesheet" href="/assets/fontawesome/css/all.min.css">
    <!-- Dark Mode Overrides (since Tailwind dark: variants aren't compiled) -->
    <style>
        :root {
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 64px;
            --header-height: 56px;
            --transition-speed: 200ms;
        }
        
        /* ============================================
           BASE LIGHT/DARK MODE STYLES
           ============================================ */
        
        /* Prevent flash of unstyled content */
        html { background-color: #f9fafb; }
        html.dark { background-color: #030712; }
        
        /* Body */
        body { background-color: #f9fafb; color: #111827; margin: 0; padding: 0; }
        .dark body { background-color: #030712; color: #f3f4f6; }
        
        /* ============================================
           LIGHT MODE - Ensure proper colors
           ============================================ */
        
        /* Light mode backgrounds */
        .bg-white { background-color: #ffffff; }
        .bg-gray-50 { background-color: #f9fafb; }
        .bg-gray-100 { background-color: #f3f4f6; }
        .bg-gray-200 { background-color: #e5e7eb; }
        .bg-gray-800 { background-color: #1f2937; }
        .bg-gray-900 { background-color: #111827; }
        
        /* Gradient backgrounds - ensure they show properly */
        .bg-gradient-to-br { background-image: linear-gradient(to bottom right, var(--tw-gradient-stops)); }
        .bg-gradient-to-r { background-image: linear-gradient(to right, var(--tw-gradient-stops)); }
        .from-violet-600 { --tw-gradient-from: #7c3aed; --tw-gradient-stops: var(--tw-gradient-from), var(--tw-gradient-to); }
        .via-purple-600 { --tw-gradient-stops: var(--tw-gradient-from), #9333ea, var(--tw-gradient-to); }
        .to-indigo-700 { --tw-gradient-to: #4338ca; }
        .to-purple-600 { --tw-gradient-to: #9333ea; }
        
        /* Light mode text */
        .text-white { color: #ffffff !important; }
        .text-gray-900 { color: #111827; }
        .text-gray-800 { color: #1f2937; }
        .text-gray-700 { color: #374151; }
        .text-gray-600 { color: #4b5563; }
        .text-gray-500 { color: #6b7280; }
        .text-gray-400 { color: #9ca3af; }
        
        /* Light mode borders */
        .border-gray-200 { border-color: #e5e7eb; }
        .border-gray-200\/50 { border-color: rgba(229, 231, 235, 0.5); }
        .border-gray-300 { border-color: #d1d5db; }
        
        /* Light mode header */
        header { background-color: rgba(255, 255, 255, 0.8); }
        .dark header { background-color: rgba(17, 24, 39, 0.95) !important; }
        
        /* ============================================
           DARK MODE OVERRIDES
           ============================================ */
        
        /* Main content area background */
        .dark .bg-gray-50 { background-color: #030712 !important; }
        .dark .bg-gray-950 { background-color: #030712 !important; }
        
        /* Text colors - dark mode overrides */
        .dark .text-gray-900 { color: #f9fafb !important; }
        .dark .text-gray-800 { color: #f3f4f6 !important; }
        .dark .text-gray-700 { color: #e5e7eb !important; }
        .dark .text-gray-600 { color: #d1d5db !important; }
        .dark .text-gray-500 { color: #9ca3af !important; }
        .dark .text-gray-400 { color: #6b7280 !important; }
        .dark .text-gray-300 { color: #d1d5db !important; }
        .dark .text-gray-200 { color: #e5e7eb !important; }
        
        /* White/light backgrounds - dark mode */
        .dark .bg-white { background-color: #111827 !important; }
        .dark .bg-gray-100 { background-color: #1f2937 !important; }
        .dark .bg-gray-200 { background-color: #374151 !important; }
        .dark .bg-gray-800 { background-color: #1f2937 !important; }
        .dark .bg-gray-900 { background-color: #111827 !important; }
        
        /* Header/Sidebar with transparency - dark mode */
        .dark .bg-white\/80 { background-color: rgba(17, 24, 39, 0.8) !important; }
        .dark .bg-gray-900\/80 { background-color: rgba(17, 24, 39, 0.8) !important; }
        .dark .bg-white\/50 { background-color: rgba(17, 24, 39, 0.5) !important; }
        .dark .bg-gray-900\/50 { background-color: rgba(17, 24, 39, 0.5) !important; }
        
        /* Header specific - ensure it's visible and above sidebar */
        header { 
            z-index: 50 !important; 
            position: fixed !important;
        }
        .dark header { background-color: rgba(17, 24, 39, 0.95) !important; }
        
        /* Ensure header content is properly laid out */
        header > div {
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            width: 100% !important;
        }
        
        /* Fix absolute positioning inside header search */
        header .relative { position: relative !important; }
        header .absolute { position: absolute !important; }
        header .top-1\/2 { top: 50% !important; }
        header .-translate-y-1\/2 { transform: translateY(-50%) !important; }
        header .left-3 { left: 0.75rem !important; }
        header .right-3 { right: 0.75rem !important; }
        
        /* Search input styling */
        header input[type="text"] {
            padding-left: 2.5rem !important;
            padding-right: 3rem !important;
        }
        
        /* Fix the branding text visibility */
        .bg-clip-text { 
            -webkit-background-clip: text !important; 
            background-clip: text !important; 
        }
        .text-transparent { color: transparent !important; }
        
        /* Gradient text for branding - light mode */
        .bg-gradient-to-r.from-violet-600.to-purple-600 {
            background-image: linear-gradient(to right, #7c3aed, #9333ea) !important;
        }
        /* Gradient text for branding - dark mode */
        .dark .bg-gradient-to-r.from-violet-600.to-purple-600,
        .dark .bg-gradient-to-r.from-violet-400.to-purple-400 {
            background-image: linear-gradient(to right, #a78bfa, #c084fc) !important;
        }
        
        /* Violet text colors - light mode */
        .text-violet-600 { color: #7c3aed !important; }
        .text-violet-500 { color: #8b5cf6 !important; }
        .text-violet-400 { color: #a78bfa !important; }
        .text-violet-100 { color: #ede9fe !important; }
        
        /* Branding area - ensure proper inline layout */
        header a[href="/playground"] {
            display: flex !important;
            flex-direction: row !important;
            align-items: center !important;
            gap: 0.75rem !important;
        }
        header a[href="/playground"] > div:first-child {
            flex-shrink: 0 !important;
            width: 2rem !important;
            height: 2rem !important;
            border-radius: 0.5rem !important;
            background: linear-gradient(to bottom right, #8b5cf6, #9333ea) !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }
        header a[href="/playground"] > div:first-child svg {
            width: 1.25rem !important;
            height: 1.25rem !important;
            color: white !important;
        }
        /* Branding text container - vertically centered next to icon */
        header a[href="/playground"] > div:nth-child(2) {
            display: flex !important;
            flex-direction: column !important;
            justify-content: center !important;
            flex-shrink: 0 !important;
        }
        
        /* Gradient backgrounds */
        .bg-gradient-to-br { background-image: linear-gradient(to bottom right, var(--tw-gradient-from), var(--tw-gradient-to)) !important; }
        .from-violet-500 { --tw-gradient-from: #8b5cf6; }
        .to-purple-600 { --tw-gradient-to: #9333ea; }
        .from-emerald-400 { --tw-gradient-from: #34d399; }
        .to-cyan-500 { --tw-gradient-to: #06b6d4; }
        
        /* Welcome banner - explicit styling for both light and dark */
        .bg-gradient-to-br.from-violet-600.via-purple-600.to-indigo-700 {
            background: linear-gradient(to bottom right, #7c3aed, #9333ea, #4338ca) !important;
        }
        .bg-gradient-to-br.from-violet-600.via-purple-600.to-indigo-700 h1,
        .bg-gradient-to-br.from-violet-600.via-purple-600.to-indigo-700 p {
            color: white !important;
        }
        .bg-gradient-to-br.from-violet-600.via-purple-600.to-indigo-700 .text-violet-100 {
            color: #ede9fe !important;
        }
        
        /* Shadows */
        .shadow-lg { box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05) !important; }
        .shadow-violet-500\/25 { --tw-shadow-color: rgba(139, 92, 246, 0.25); }
        .shadow-emerald-500\/25 { --tw-shadow-color: rgba(16, 185, 129, 0.25); }
        
        /* Negative margin fix */
        .-mt-1 { margin-top: -1.5rem !important; }
        
        /* Ensure right section elements are visible */
        header .flex.items-center.gap-2 {
            display: flex !important;
            align-items: center !important;
            gap: 0.5rem !important;
        }
        header button, header a {
            display: flex !important;
        }
        header .p-2 {
            padding: 0.5rem !important;
        }
        header .rounded-lg {
            border-radius: 0.5rem !important;
        }
        header .rounded-xl {
            border-radius: 0.75rem !important;
        }
        
        /* User avatar */
        header .w-8.h-8.rounded-lg {
            width: 2rem !important;
            height: 2rem !important;
            border-radius: 0.5rem !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }
        
        /* Notification badge */
        header .w-2.h-2 {
            width: 0.5rem !important;
            height: 0.5rem !important;
        }
        header .bg-red-500 {
            background-color: #ef4444 !important;
        }
        header .rounded-full {
            border-radius: 9999px !important;
        }
        
        /* Backdrop blur support */
        .backdrop-blur-xl { backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px); }
        .backdrop-blur-sm { backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); }
        
        /* Ensure flexbox utilities work */
        .flex { display: flex !important; }
        .hidden { display: none !important; }
        .items-center { align-items: center !important; }
        .justify-between { justify-content: space-between !important; }
        .gap-2 { gap: 0.5rem !important; }
        .gap-3 { gap: 0.75rem !important; }
        .gap-4 { gap: 1rem !important; }
        
        /* Responsive display */
        @media (min-width: 640px) {
            .sm\:flex { display: flex !important; }
            .sm\:block { display: block !important; }
            .sm\:inline-flex { display: inline-flex !important; }
            .hidden.sm\:block { display: block !important; }
            .hidden.sm\:flex { display: flex !important; }
        }
        @media (max-width: 639px) {
            .hidden.sm\:block { display: none !important; }
            .hidden.sm\:flex { display: none !important; }
        }
        @media (min-width: 768px) {
            .md\:flex { display: flex !important; }
            .hidden.md\:flex { display: flex !important; }
        }
        @media (max-width: 767px) {
            .hidden.md\:flex { display: none !important; }
        }
        @media (min-width: 1024px) {
            .lg\:flex { display: flex !important; }
            .lg\:hidden { display: none !important; }
            .hidden.lg\:flex { display: flex !important; }
        }
        @media (max-width: 1023px) {
            .hidden.lg\:flex { display: none !important; }
            .lg\:hidden { display: block !important; }
        }
        
        /* Borders - dark mode */
        .dark .border-gray-200 { border-color: #374151 !important; }
        .dark .border-gray-200\/50 { border-color: rgba(55, 65, 81, 0.5) !important; }
        .dark .border-gray-300 { border-color: #4b5563 !important; }
        .dark .border-gray-700 { border-color: #374151 !important; }
        .dark .border-gray-700\/50 { border-color: rgba(55, 65, 81, 0.5) !important; }
        
        /* Hover states - dark mode */
        .dark .hover\:bg-gray-100:hover { background-color: #1f2937 !important; }
        .dark .hover\:bg-gray-50:hover { background-color: #1f2937 !important; }
        .dark .hover\:bg-gray-800:hover { background-color: #374151 !important; }
        .dark .hover\:text-gray-900:hover { color: #f9fafb !important; }
        .dark .hover\:text-white:hover { color: #ffffff !important; }
        
        /* Colored backgrounds - dark mode (for stat cards, etc) */
        .dark .bg-blue-100 { background-color: rgba(59, 130, 246, 0.2) !important; }
        .dark .bg-green-100 { background-color: rgba(16, 185, 129, 0.2) !important; }
        .dark .bg-purple-100 { background-color: rgba(139, 92, 246, 0.2) !important; }
        .dark .bg-red-100 { background-color: rgba(239, 68, 68, 0.2) !important; }
        .dark .bg-amber-100 { background-color: rgba(245, 158, 11, 0.2) !important; }
        .dark .bg-violet-100 { background-color: rgba(139, 92, 246, 0.2) !important; }
        
        /* Colored backgrounds 900/30 variants */
        .dark .bg-blue-900\/30 { background-color: rgba(30, 58, 138, 0.3) !important; }
        .dark .bg-green-900\/30 { background-color: rgba(6, 78, 59, 0.3) !important; }
        .dark .bg-purple-900\/30 { background-color: rgba(76, 29, 149, 0.3) !important; }
        .dark .bg-red-900\/30 { background-color: rgba(127, 29, 29, 0.3) !important; }
        .dark .bg-amber-900\/30 { background-color: rgba(120, 53, 15, 0.3) !important; }
        .dark .bg-violet-900\/30 { background-color: rgba(76, 29, 149, 0.3) !important; }
        .dark .bg-amber-900\/20 { background-color: rgba(120, 53, 15, 0.2) !important; }
        
        /* Text colors - dark mode colored */
        .dark .text-blue-600 { color: #60a5fa !important; }
        .dark .text-green-600 { color: #34d399 !important; }
        .dark .text-purple-600 { color: #a78bfa !important; }
        .dark .text-red-600 { color: #f87171 !important; }
        .dark .text-amber-600 { color: #fbbf24 !important; }
        .dark .text-violet-600 { color: #a78bfa !important; }
        
        .dark .text-blue-400 { color: #60a5fa !important; }
        .dark .text-green-400 { color: #34d399 !important; }
        .dark .text-purple-400 { color: #a78bfa !important; }
        .dark .text-red-400 { color: #f87171 !important; }
        .dark .text-amber-400 { color: #fbbf24 !important; }
        .dark .text-violet-400 { color: #a78bfa !important; }
        
        /* Amber alert box in sidebar */
        .dark .bg-amber-50 { background-color: rgba(245, 158, 11, 0.15) !important; }
        .dark .border-amber-200 { border-color: rgba(245, 158, 11, 0.4) !important; }
        .dark .border-amber-800 { border-color: rgba(245, 158, 11, 0.4) !important; }
        .dark .text-amber-700 { color: #fbbf24 !important; }
        
        /* Violet gradient colors */
        .dark .from-violet-600 { --tw-gradient-from: #8b5cf6; }
        .dark .to-purple-600 { --tw-gradient-to: #a855f7; }
        .dark .from-violet-400 { --tw-gradient-from: #a78bfa; }
        .dark .to-purple-400 { --tw-gradient-to: #c084fc; }
        
        /* Gradient text for dark mode */
        .dark .bg-gradient-to-r.from-violet-600.to-purple-600 {
            --tw-gradient-from: #a78bfa;
            --tw-gradient-to: #c084fc;
        }
        
        /* Violet hover/active menu items */
        .dark .from-violet-500\/10 { --tw-gradient-from: rgba(139, 92, 246, 0.1); }
        .dark .to-purple-500\/10 { --tw-gradient-to: rgba(168, 85, 247, 0.1); }
        .dark .text-violet-600 { color: #a78bfa !important; }
        .dark .text-violet-500 { color: #a78bfa !important; }
        
        /* Input fields - dark mode */
        .dark input, .dark textarea, .dark select {
            background-color: #1f2937 !important;
            border-color: #374151 !important;
            color: #f3f4f6 !important;
        }
        .dark input::placeholder, .dark textarea::placeholder {
            color: #6b7280 !important;
        }
        .dark input:focus, .dark textarea:focus, .dark select:focus {
            background-color: #111827 !important;
            border-color: #8b5cf6 !important;
        }
        
        /* Focus ring colors */
        .dark .focus\:border-violet-500:focus { border-color: #8b5cf6 !important; }
        .dark .focus\:border-violet-400:focus { border-color: #a78bfa !important; }
        .dark .focus\:bg-white:focus { background-color: #111827 !important; }
        .dark .focus\:bg-gray-900:focus { background-color: #111827 !important; }
        
        /* ============================================
           COMPONENT STYLES
           ============================================ */
        
        /* Theme transitions (after initial load) */
        html.theme-transition,
        html.theme-transition *,
        html.theme-transition *::before,
        html.theme-transition *::after {
            transition: background-color 200ms ease, border-color 200ms ease, color 200ms ease !important;
        }
        
        /* Scrollbar styling */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        .dark ::-webkit-scrollbar-thumb { background: #475569; }
        .dark ::-webkit-scrollbar-thumb:hover { background: #64748b; }
        
        /* Sidebar transitions */
        .sidebar {
            width: var(--sidebar-width);
            transition: width var(--transition-speed) ease-in-out, transform var(--transition-speed) ease-in-out;
        }
        .sidebar.collapsed { width: var(--sidebar-collapsed-width); }
        .sidebar.collapsed .sidebar-text { opacity: 0; visibility: hidden; }
        .sidebar.collapsed .sidebar-icon { margin-right: 0; }
        
        /* Main content area */
        .main-content {
            margin-left: var(--sidebar-width);
            transition: margin-left var(--transition-speed) ease-in-out;
        }
        .main-content.expanded { margin-left: var(--sidebar-collapsed-width); }
        
        /* Mobile responsive */
        @media (max-width: 1023px) {
            .sidebar {
                transform: translateX(-100%);
                position: fixed;
                z-index: 50;
            }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0 !important; }
        }
        
        /* Menu item hover effects */
        .menu-item { position: relative; overflow: hidden; }
        .menu-item::before {
            content: '';
            position: absolute;
            left: 0; top: 0;
            height: 100%; width: 3px;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            transform: scaleY(0);
            transition: transform var(--transition-speed) ease;
        }
        .menu-item:hover::before, .menu-item.active::before { transform: scaleY(1); }
        
        /* Tooltip for collapsed sidebar */
        .sidebar-tooltip {
            visibility: hidden; opacity: 0;
            position: absolute;
            left: calc(var(--sidebar-collapsed-width) + 8px);
            background: #1e293b; color: #f1f5f9;
            padding: 6px 12px; border-radius: 6px;
            font-size: 0.875rem; white-space: nowrap;
            z-index: 100;
            transition: opacity 150ms, visibility 150ms;
        }
        .sidebar.collapsed .menu-item:hover .sidebar-tooltip {
            visibility: visible; opacity: 1;
        }
        
        /* Code block styling */
        .code-block {
            font-family: 'JetBrains Mono', 'Fira Code', 'Monaco', monospace;
            background: #1e293b;
            border-radius: 8px;
            overflow-x: auto;
        }
        .dark .code-block { background: #0f172a; }
    </style>
</head>
