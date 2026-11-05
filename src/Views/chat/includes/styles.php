<?php
/**
 * Chat Styles
 * All embedded CSS styles for messenger UI, code blocks, etc.
 */
?>
<style>
    /* Custom scrollbar */
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #65676b; border-radius: 3px; }
    ::-webkit-scrollbar-thumb:hover { background: #8a8d91; }
    
    /* Message animations - messenger style slide */
    .msg-enter { animation: msgSlide 0.2s ease-out; }
    @keyframes msgSlide {
      from { opacity: 0; transform: translateY(8px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    /* Typing indicator - messenger style */
    .typing-dot { 
      animation: typingBounce 1.4s infinite ease-in-out both;
      background: #65676b;
    }
    .dark .typing-dot { background: #b0b3b8; }
    .typing-dot:nth-child(1) { animation-delay: -0.32s; }
    .typing-dot:nth-child(2) { animation-delay: -0.16s; }
    @keyframes typingBounce {
      0%, 80%, 100% { transform: scale(0); }
      40% { transform: scale(1); }
    }

    /* Sidebar scrollbar */
    .sidebar-scroll::-webkit-scrollbar { width: 6px; }
    .sidebar-scroll::-webkit-scrollbar-track { background: transparent; }
    .sidebar-scroll::-webkit-scrollbar-thumb { background: #525355; border-radius: 3px; }
    .sidebar-scroll::-webkit-scrollbar-thumb:hover { background: #65676b; }
    
    /* Conversation item in sidebar */
    .convo-item {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.5rem 0.75rem;
      border-radius: 8px;
      color: #e4e6eb;
      font-size: 0.875rem;
      cursor: pointer;
      transition: background 0.15s;
    }
    .convo-item:hover { background: rgba(255,255,255,0.08); }
    .convo-item.active { background: rgba(0,132,255,0.15); }
    .convo-item-icon {
      width: 1.25rem;
      height: 1.25rem;
      flex-shrink: 0;
      color: #b0b3b8;
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
      background: rgba(101, 103, 107, 0.9); 
      color: #fff; 
      border: none; 
      padding: 4px 10px; 
      border-radius: 4px; 
      font-size: 12px; 
      cursor: pointer; 
      opacity: 0.8; 
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
    
    /* Mobile-responsive tab labels (< 450px shows shortened text, < 400px icons only) */
    .tab-label-full { display: inline; }
    .tab-label-short { display: none; }
    @media (max-width: 449px) {
      .tab-label-full { display: none; }
      .tab-label-short { display: inline; }
    }
    @media (max-width: 399px) {
      .tab-label-full, .tab-label-short { display: none; }
      .editor-tab { padding-left: 0.75rem; padding-right: 0.75rem; }
    }
    
    /* ============ MESSENGER-STYLE CHAT BUBBLES ============ */
    /* Message styles - Facebook Messenger inspired */
    .msg { 
      max-width: 75%;
      width: auto;
      padding: 10px 14px;
      border-radius: 18px;
      animation: msgSlide 0.3s ease-out;
      word-wrap: break-word;
      overflow-wrap: break-word;
      overflow: visible !important;
      position: relative;
      line-height: 1.4;
    }
    
    /* User messages - right aligned, Messenger blue */
    .msg.user { 
      background: #0084ff;
      color: white;
      margin-left: auto;
      margin-right: 0;
      align-self: flex-end;
      border-bottom-right-radius: 4px;
    }
    
    /* Assistant messages - left aligned, theme neutral gray */
    .msg.assistant { 
      background: #e4e6eb;
      color: #050505;
      border: none;
      margin-left: 0;
      margin-right: auto;
      align-self: flex-start;
      overflow: visible !important;
      border-bottom-left-radius: 4px;
    }
    
    /* Short messages stay narrower on larger screens */
    @media (min-width: 641px) {
      .msg.short {
        max-width: 65%;
      }
    }
    
    /* Dark mode - assistant messages use dark neutral gray */
    .dark .msg.assistant { 
      background: #3e4042;
      color: #e4e6eb;
      border: none;
    }
    
    /* Meta labels hidden in messenger style for cleaner look */
    .msg .meta {
      display: none;
    }
    
    .msg .content { line-height: 1.4; }
    .msg .content p { margin: 0 0 6px 0; }
    .msg .content p:last-child { margin-bottom: 0; }
    .msg .content code { 
      background: rgba(0,0,0,0.08); 
      padding: 2px 6px; 
      border-radius: 4px; 
      font-size: 13px;
    }
    .msg.user .content code {
      background: rgba(255,255,255,0.2);
      color: white;
    }
    .dark .msg.assistant .content code { 
      background: rgba(255,255,255,0.1);
      color: #e4e6eb;
    }
    .msg .content ul, .msg .content ol { 
      margin: 6px 0; 
      padding-left: 18px; 
    }
    .msg .content li { margin: 3px 0; }
    
    /* Scrollbar - messenger neutral gray */
    .dark ::-webkit-scrollbar-thumb { background: #525355; }
    ::-webkit-scrollbar-thumb { background: #c5c6c9; }
    
    /* Sidebar custom scrollbar */
    .sidebar-scroll::-webkit-scrollbar { width: 4px; }
    .sidebar-scroll::-webkit-scrollbar-track { background: transparent; }
    .sidebar-scroll::-webkit-scrollbar-thumb { background: #65676b; border-radius: 2px; }
    .sidebar-scroll::-webkit-scrollbar-thumb:hover { background: #8a8d91; }
    .dark .sidebar-scroll::-webkit-scrollbar-thumb { background: #525355; }
    .dark .sidebar-scroll::-webkit-scrollbar-thumb:hover { background: #65676b; }
    
    /* ============ MESSENGER-STYLE ACTIVITY INDICATORS ============ */
    .activity-spinner { animation: spin 1s linear infinite; vertical-align: middle; }
    @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    
    /* Thinking indicator styling */
    .thinking-indicator-wrapper {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
    }
    .thinking-indicator-wrapper .activity-spinner {
      flex-shrink: 0;
    }
    
    .modern-scroll::-webkit-scrollbar { width: 6px; height: 6px; }
    .modern-scroll::-webkit-scrollbar-track { background: transparent; }
    .modern-scroll::-webkit-scrollbar-thumb { background: #65676b; border-radius: 3px; }
    .modern-scroll::-webkit-scrollbar-thumb:hover { background: #8a8d91; }
    .modern-scroll { scrollbar-width: thin; scrollbar-color: #65676b transparent; }
    
    /* Site badges - messenger style */
    .site-badge { 
      display: inline-flex; align-items: center; gap: 0.25rem;
      padding: 4px 8px; background: rgba(0,0,0,0.05); 
      border-radius: 12px; font-size: 0.75rem; color: #65676b;
    }
    .dark .site-badge { background: rgba(255,255,255,0.1); color: #b0b3b8; }
    
    /* Reasoning timeline - messenger style */
    .reasoning-timeline { 
      position: relative; 
      padding-left: 0.5rem;
    }
    .reasoning-header {
      font-size: 0.8125rem; font-weight: 500; color: #65676b;
      cursor: pointer; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;
      position: relative;
    }
    .dark .reasoning-header { color: #b0b3b8; }
    .reasoning-header:hover { color: #050505; }
    .dark .reasoning-header:hover { color: #e4e6eb; }
    .reasoning-chevron { transition: transform 0.2s; width: 1rem; height: 1rem; }
    .reasoning-chevron.open { transform: rotate(180deg); }
    .reasoning-content {
      font-size: 0.8125rem; line-height: 1.5; color: #65676b;
      padding-right: 0.5rem; max-height: 300px; overflow-y: auto; overflow-x: hidden;
      position: relative;
    }
    .dark .reasoning-content { color: #b0b3b8; }
    /* Each reasoning step row */
    .reasoning-item {
      display: flex;
      align-items: stretch;
      gap: 0.75rem;
      padding-left: 0.25rem;
      position: relative;
    }
    /* Left column with dot and line */
    .reasoning-item-indicator {
      display: flex;
      flex-direction: column;
      width: 0.75rem;
      flex-shrink: 0;
      align-items: center;
      position: relative;
      z-index: 1;
    }
    .reasoning-item-dot {
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: #65676b;
      margin-top: 0.3rem;
      flex-shrink: 0;
    }
    .dark .reasoning-item-dot { background: #b0b3b8; }
    /* Blue dot for the last/latest reasoning step */
    .reasoning-item-dot-green {
      background: #0084ff !important;
    }
    .dark .reasoning-item-dot-green { background: #0084ff !important; }
    .reasoning-item-line {
      position: absolute;
      top: 10px;
      width: 1px;
      background: #c5c6c9;
      height: 100%;
    }
    .dark .reasoning-item-line { background: #525355; }
    .reasoning-item:last-child .reasoning-item-line {
      display: none;
    }
    /* Text content */
    .reasoning-item-text {
      padding-bottom: 0.75rem;
      flex: 1;
    }
    .reasoning-item-text p { margin: 0; padding-top: 0; }
    
    .response-label { font-size: 0.8125rem; font-weight: 500; color: #65676b; margin-bottom: 0.5rem; }
    .dark .response-label { color: #b0b3b8; }
    
    /* Action buttons - messenger style, smaller and more subtle */
    .action-btn {
      display: inline-flex; align-items: center; justify-content: center;
      width: 1.75rem; height: 1.75rem; border-radius: 50%;
      color: #1a1a1a; transition: all 0.15s; background: transparent; border: none; cursor: pointer;
    }
    .action-btn svg { width: 1rem; height: 1rem; stroke: #1a1a1a; }
    html:not(.dark) .action-btn { color: #1a1a1a; }
    html:not(.dark) .action-btn svg { stroke: #1a1a1a; }
    .dark .action-btn, html.dark .action-btn { color: #ffffff; }
    .dark .action-btn svg, html.dark .action-btn svg { stroke: #ffffff; }
    .action-btn:hover { background: rgba(0,0,0,0.15); color: #000000; }
    .action-btn:hover svg { stroke: #000000; }
    .dark .action-btn:hover { background: rgba(255,255,255,0.15); color: #ffffff; }
    .dark .action-btn:hover svg { stroke: #ffffff; }
    .action-btn.active { color: #0084ff; }
    .action-btn.active svg { stroke: #0084ff; }
    .dark .action-btn.active { color: #0084ff; }
    .dark .action-btn.active svg { stroke: #0084ff; }
    
    /* Action group with dropdown - messenger style */
    .action-group { position: relative; }
    .action-more-dropdown,
    .dropdown-menu,
    .card-more-menu {
      position: absolute;
      bottom: 100%;
      right: 0;
      margin-bottom: 8px;
      background: #ffffff;
      border: none;
      border-radius: 8px;
      padding: 6px;
      min-width: 180px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.25), 0 0 0 1px rgba(0,0,0,0.08);
      z-index: 9999;
      animation: dropdownFadeIn 0.15s ease-out;
    }
    .dark .action-more-dropdown,
    .dark .dropdown-menu,
    .dark .card-more-menu {
      background: linear-gradient(to bottom right, rgba(67, 56, 202, 0.075), rgba(17, 24, 39, 0.5), rgba(88, 28, 135, 0.075));
      border: 1px solid rgba(99, 102, 241, 0.125);
      box-shadow: 0 4px 20px rgba(0,0,0,0.25), 0 0 0 1px rgba(99, 102, 241, 0.075);
      backdrop-filter: blur(6px);
      -webkit-backdrop-filter: blur(6px);
    }
    @keyframes dropdownFadeIn {
      from { opacity: .8; transform: translateY(8px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .dropdown-item {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      padding: 8px 12px;
      border-radius: 6px;
      color: #050505;
      font-size: 0.875rem;
      cursor: pointer;
      transition: all 0.15s ease;
    }
    .dark .dropdown-item { color: #e4e6eb; }
    .dropdown-item:hover { 
      background: rgba(0,0,0,0.05); 
      color: #050505;
    }
    .dark .dropdown-item:hover { 
      background: rgba(255,255,255,0.1); 
      color: #e4e6eb;
    }
    .dropdown-item:hover svg { color: #0084ff; }
    .dark .dropdown-item:hover svg { color: #0084ff; }
    .dropdown-item svg { 
      width: 1rem; 
      height: 1rem; 
      color: #65676b;
      transition: color 0.15s;
    }
    .dark .dropdown-item svg { color: #b0b3b8; }
    
    /* Citations - messenger style */
    .citation {
      display: inline-flex; align-items: center; gap: 0.25rem;
      padding: 4px 8px; background: rgba(0,0,0,0.05); border: none;
      border-radius: 12px; font-size: 0.75rem; color: #65676b;
      text-decoration: none; transition: all 0.15s;
    }
    .dark .citation { background: rgba(255,255,255,0.1); color: #b0b3b8; }
    .citation:hover { background: rgba(0,0,0,0.1); color: #050505; }
    .dark .citation:hover { background: rgba(255,255,255,0.15); color: #e4e6eb; }
    .citation-num {
      display: inline-flex; align-items: center; justify-content: center;
      width: 1.125rem; height: 1.125rem; background: #65676b;
      border-radius: 50%; font-size: 0.625rem; font-weight: 600; color: #ffffff;
    }
    .dark .citation-num { background: #b0b3b8; color: #18191a; }
    
    /* Sources stack - messenger style */
    .sources-stack {
      display: flex; align-items: center; gap: 0.375rem; margin-left: auto;
      cursor: pointer; padding: 4px 8px; border-radius: 12px; transition: background 0.15s;
    }
    .sources-stack:hover { background: rgba(0,0,0,0.05); }
    .dark .sources-stack:hover { background: rgba(255,255,255,0.1); }
    .sources-icons { display: flex; align-items: center; }
    .sources-icons img {
      width: 1.25rem; height: 1.25rem; border-radius: 50%; border: 2px solid #ffffff;
      background: #e4e6eb; object-fit: cover;
    }
    .dark .sources-icons img { border-color: #242526; background: #3a3b3c; }
    .sources-icons img:not(:first-child) { margin-left: -0.375rem; }
    .sources-label { font-size: 0.8125rem; color: #65676b; }
    .dark .sources-label { color: #b0b3b8; }
    .sources-stack:hover .sources-label { color: #050505; }
    .dark .sources-stack:hover .sources-label { color: #e4e6eb; }
    
    /* Drag and drop styling - messenger style */
    #composer.drag-over {
      outline: 2px dashed #0084ff;
      outline-offset: -2px;
      background: rgba(0, 132, 255, 0.05);
    }
    .dark #composer.drag-over {
      background: rgba(0, 132, 255, 0.1);
    }
    
    /* ============ MESSENGER BUBBLE STYLES ============ */
    .messenger-pair {
      display: flex;
      flex-direction: column;
      gap: 8px;
      margin-bottom: 16px;
    }
    
    /* User message row - right aligned */
    .messenger-row-user {
      display: flex;
      flex-direction: column;
      align-items: flex-end;
    }
    
    /* Assistant message row - left aligned */
    .messenger-row-assistant {
      display: flex;
      flex-direction: column;
      align-items: flex-start;
    }
    
    /* Assistant bubble - neutral gray, left tail */
    .messenger-bubble-assistant {
      background: #e4e6eb;
      color: #050505;
      border-bottom-left-radius: 4px;
    }
    .dark .messenger-bubble-assistant {
      background: #3e4042;
      color: #e4e6eb;
    }
    
    /* Messenger image preview */
    .messenger-image {
      max-width: 75%;
    }
    .messenger-image img {
      border-radius: 18px;
    }
    
    /* Messenger reasoning (collapsible) */
    .messenger-reasoning {
      margin-bottom: 8px;
      padding-bottom: 8px;
      border-bottom: 1px solid rgba(0,0,0,0.08);
    }
    .dark .messenger-reasoning {
      border-bottom-color: rgba(255,255,255,0.1);
    }
    .messenger-reasoning-toggle {
      display: flex;
      align-items: center;
      gap: 4px;
      cursor: pointer;
      font-size: 0.8125rem;
      font-weight: 500;
      color: #65676b;
    }
    .dark .messenger-reasoning-toggle {
      color: #b0b3b8;
    }
    .messenger-reasoning-toggle:hover {
      color: #050505;
    }
    .dark .messenger-reasoning-toggle:hover {
      color: #e4e6eb;
    }
    .messenger-reasoning-chevron {
      width: 16px;
      height: 16px;
      transition: transform 0.2s;
    }
    .messenger-reasoning-chevron.open {
      transform: rotate(180deg);
    }
    .messenger-reasoning-content {
      font-size: 0.8125rem;
      line-height: 1.5;
      color: #65676b;
      margin-top: 6px;
      max-height: 200px;
      overflow-y: auto;
    }
    .dark .messenger-reasoning-content {
      color: #b0b3b8;
    }
    
    /* Messenger response prose */
    .messenger-response {
      line-height: 1.5;
    }
    .messenger-response p:first-child {
      margin-top: 0;
    }
    .messenger-response p:last-child {
      margin-bottom: 0;
    }
    
    /* Messenger citations */
    .messenger-citations {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      margin-top: 8px;
      padding-top: 8px;
      border-top: 1px solid rgba(0,0,0,0.08);
    }
    .dark .messenger-citations {
      border-top-color: rgba(255,255,255,0.1);
    }
    .messenger-citation {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 4px 8px;
      background: rgba(0,0,0,0.05);
      border-radius: 12px;
      font-size: 0.75rem;
      color: #65676b;
      text-decoration: none;
      transition: background 0.15s;
    }
    .dark .messenger-citation {
      background: rgba(255,255,255,0.1);
      color: #b0b3b8;
    }
    .messenger-citation:hover {
      background: rgba(0,0,0,0.1);
    }
    .dark .messenger-citation:hover {
      background: rgba(255,255,255,0.15);
    }
    
    /* Messenger action buttons */
    .messenger-actions {
      display: flex;
      align-items: center;
      gap: 2px;
      margin-top: 4px;
      opacity: 0;
      transition: opacity 0.15s;
    }
    .messenger-row-assistant:hover .messenger-actions {
      opacity: 1;
    }
    .messenger-action-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 28px;
      height: 28px;
      border-radius: 50%;
      background: transparent;
      border: none;
      cursor: pointer;
      color: #65676b;
      transition: all 0.15s;
    }
    .dark .messenger-action-btn {
      color: #b0b3b8;
    }
    .messenger-action-btn:hover {
      background: rgba(0,0,0,0.05);
      color: #050505;
    }
    .dark .messenger-action-btn:hover {
      background: rgba(255,255,255,0.1);
      color: #e4e6eb;
    }
    .messenger-action-btn.active {
      color: #0084ff;
    }
    .messenger-action-btn svg {
      width: 16px;
      height: 16px;
    }
    
    /* ============ MESSENGER-STYLE CONVERSATION CARDS ============ */
    .convo-history { 
      display: flex !important; 
      flex-direction: column !important; 
      gap: 0.5rem !important; 
      margin-bottom: 1rem !important; 
    }
    .convo-card {
      background: transparent !important;
      border: none !important; 
      border-radius: 0 !important; 
      overflow: visible !important;
    }
    .dark .convo-card {
      background: transparent !important;
      border-color: transparent !important;
    }
    
    /* User message bubble - right aligned, Messenger blue */
    .convo-card-header {
      display: flex !important; 
      align-items: flex-end !important; 
      gap: 0.5rem !important;
      padding: 0 !important; 
      cursor: pointer !important;
      flex-direction: row-reverse !important; 
      justify-content: flex-start !important;
    }
    .convo-card-header:hover { background: transparent !important; }
    .dark .convo-card-header:hover { background: transparent !important; }
    
    /* User avatar - small circle with initial */
    .convo-card-icon {
      width: 1.75rem !important; 
      height: 1.75rem !important; 
      border-radius: 50% !important;
      display: flex !important; 
      align-items: center !important; 
      justify-content: center !important; 
      flex-shrink: 0 !important;
      background: #0084ff !important; 
      font-size: 0.7rem !important; 
      font-weight: 600 !important; 
      color: white !important;
    }
    .convo-card-icon.search, .convo-card-icon.weather, .convo-card-icon.news, .convo-card-icon.general { 
      background: #0084ff !important; 
    }
    .convo-card-icon svg { display: none !important; }
    .convo-card-icon::after { content: 'U' !important; }
    
    /* User message bubble */
    .convo-card-info { 
      flex: 0 1 auto !important; 
      min-width: 0 !important; 
      max-width: 75% !important;
      background: #0084ff !important; 
      color: white !important;
      padding: 10px 14px !important;
      border-radius: 18px 18px 4px 18px !important;
    }
    .dark .convo-card-info { 
      background: #0084ff !important; 
    }
    .convo-card-query {
      font-weight: 400 !important; 
      color: white !important; 
      font-size: 0.9375rem !important;
      white-space: pre-wrap !important; 
      overflow: visible !important; 
      text-overflow: unset !important; 
      line-height: 1.4 !important;
    }
    .dark .convo-card-query { color: white !important; }
    .convo-card-meta { 
      display: none !important; 
    }
    .convo-card-chevron { display: none !important; }
    
    /* Assistant response - LIGHT MODE (default) */
    .convo-card-body {
      border-top: none !important; 
      margin-left: 0 !important; 
      margin-top: 8px !important;
      position: relative !important;
      background: linear-gradient(to bottom right, rgba(99, 102, 241, 0.08), rgba(248, 250, 252, 0.95), rgba(168, 85, 247, 0.08)) !important;
      border: 1px solid rgba(99, 102, 241, 0.2) !important;
      border-radius: 18px 18px 18px 4px !important;
      padding: 10px !important;
      overflow: visible !important;
      z-index: 1 !important;
    }
    /* Assistant response - DARK MODE */
    .dark .convo-card-body { 
      border-top-color: transparent !important; 
      background: linear-gradient(to bottom right, rgba(67, 56, 202, 0.06), rgba(17, 24, 39, 0.4), rgba(88, 28, 135, 0.06)) !important;
      border: 1px solid rgba(99, 102, 241, 0.12) !important;
    }
    .convo-card-body.collapsed { display: none !important; }
    
    /* Remove the G avatar */
    .convo-card-body::before {
      display: none !important;
      content: none !important;
    }
    
    /* Response content - LIGHT MODE (default) */
    .card-response {
      background: transparent !important;
      color: #1f2937 !important;
      padding: 12px 16px !important;
      border-radius: 0 !important;
      display: block !important; 
      max-width: none !important;
      width: 100% !important;
    }
    /* Response content - DARK MODE */
    .dark .card-response { 
      background: transparent !important; 
      color: #e4e6eb !important;
    }
    
    /* Hide response label in messenger style */
    .card-response-label, .response-label {
      display: none !important;
    }
    
    /* Card footer - align with response text */
    .card-footer {
      padding: 8px 0 4px 6px !important;
    }
    
    /* Prose / HTML content styling - optimized for messenger gray bubbles */
    .prose { line-height: 1.5; color: #050505; }
    .dark .prose { color: #e4e6eb; }
    .prose h1, .prose h2, .prose h3 { color: #050505; margin-top: 0.75em; margin-bottom: 0.25em; font-weight: 600; }
    .dark .prose h1, .dark .prose h2, .dark .prose h3 { color: #e4e6eb; }
    .prose p { margin: 0.4em 0; }
    .prose p:first-child { margin-top: 0; }
    .prose p:last-child { margin-bottom: 0; }
    .prose table { width: 100%; border-collapse: collapse; margin: 0.75em 0; }
    .prose th, .prose td { border: 1px solid #c5c6c9; padding: 0.4em 0.6em; text-align: left; color: #050505; }
    .dark .prose th, .dark .prose td { border-color: #525355; color: #e4e6eb; }
    .prose th { background: rgba(0,0,0,0.05); color: #050505; font-weight: 600; }
    .dark .prose th { background: rgba(255,255,255,0.08); color: #e4e6eb; }
    .prose strong { color: #050505; }
    .dark .prose strong { color: #ffffff; }
    .prose ul, .prose ol { margin: 0.4em 0; padding-left: 1.25em; }
    .prose li { margin: 0.2em 0; }
    .prose hr { border: none; border-top: 1px solid #c5c6c9; margin: 1em 0; }
    .dark .prose hr { border-top-color: #525355; }
    
    /* Code blocks - base styling for unwrapped pre elements */
    .prose pre {
      background: rgba(0,0,0,0.06);
      border: none;
      border-radius: 8px;
      padding: 0.75rem;
      overflow-x: auto;
      margin: 0.75em 0;
    }
    .dark .prose pre {
      background: rgba(0,0,0,0.25);
      border: none;
    }
    .prose pre code {
      background: transparent;
      padding: 0;
      font-size: 0.8125rem;
      line-height: 1.5;
      color: #050505;
      font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, "Liberation Mono", monospace;
    }
    .dark .prose pre code {
      color: #e4e6eb;
    }
    .prose code {
      background: rgba(0,0,0,0.08);
      padding: 0.125rem 0.375rem;
      border-radius: 4px;
      font-size: 0.8125rem;
      color: #c026d3;
      font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, "Liberation Mono", monospace;
    }
    .dark .prose code {
      background: rgba(255,255,255,0.1);
      color: #f472b6;
    }
    
    /* Light mode overrides for highlight.js (since we use github-dark.min.css) */
    /* These override the dark theme colors when NOT in dark mode */
    html:not(.dark) .hljs { color: #24292f; background: #f6f8fa; }
    html:not(.dark) .hljs-doctag,
    html:not(.dark) .hljs-keyword,
    html:not(.dark) .hljs-meta .hljs-keyword,
    html:not(.dark) .hljs-template-tag,
    html:not(.dark) .hljs-template-variable,
    html:not(.dark) .hljs-type,
    html:not(.dark) .hljs-variable.language_ { color: #cf222e; }
    html:not(.dark) .hljs-title,
    html:not(.dark) .hljs-title.class_,
    html:not(.dark) .hljs-title.class_.inherited__,
    html:not(.dark) .hljs-title.function_ { color: #8250df; }
    html:not(.dark) .hljs-attr,
    html:not(.dark) .hljs-attribute,
    html:not(.dark) .hljs-literal,
    html:not(.dark) .hljs-meta,
    html:not(.dark) .hljs-number,
    html:not(.dark) .hljs-operator,
    html:not(.dark) .hljs-selector-attr,
    html:not(.dark) .hljs-selector-class,
    html:not(.dark) .hljs-selector-id,
    html:not(.dark) .hljs-variable { color: #0550ae; }
    html:not(.dark) .hljs-meta .hljs-string,
    html:not(.dark) .hljs-regexp,
    html:not(.dark) .hljs-string { color: #0a3069; }
    html:not(.dark) .hljs-built_in,
    html:not(.dark) .hljs-symbol { color: #953800; }
    html:not(.dark) .hljs-code,
    html:not(.dark) .hljs-comment,
    html:not(.dark) .hljs-formula { color: #6e7781; }
    html:not(.dark) .hljs-name,
    html:not(.dark) .hljs-quote,
    html:not(.dark) .hljs-selector-pseudo,
    html:not(.dark) .hljs-selector-tag { color: #116329; }
    html:not(.dark) .hljs-subst { color: #24292f; }
    html:not(.dark) .hljs-section { color: #0550ae; font-weight: 700; }
    html:not(.dark) .hljs-bullet { color: #953800; }
    html:not(.dark) .hljs-emphasis { color: #24292f; font-style: italic; }
    html:not(.dark) .hljs-strong { color: #24292f; font-weight: 700; }
    html:not(.dark) .hljs-addition { color: #116329; background-color: #dafbe1; }
    html:not(.dark) .hljs-deletion { color: #82071e; background-color: #ffebe9; }
  </style>
  
  <?php include __DIR__ . '/styles-codeblock.php'; ?>
  
  <!-- Messenger-style chat CSS (loaded last to override embedded styles) -->
  <link rel="stylesheet" href="/assets/css/chat.css">
