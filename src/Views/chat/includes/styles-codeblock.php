<?php
/**
 * Code Block Styles
 * Styling for syntax-highlighted code blocks with CodeMirror
 */
?>
<style>
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

    /* Buttons container */
    .code-header-buttons {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      font-size: 0.75rem;
      min-height: calc(2.375rem - 2px); /* match header height minus top/bottom borders */
    }
    
    /* When individual buttons are stuck (applied via JS) */
    .code-header-buttons .code-action-btn.stuck {
      position: fixed;
      top: 64px !important; /* 56px header + 8px margin */
      z-index: 30; /* Above CodeMirror but below composer (z-40) */
      /* left is set dynamically by JS to preserve original horizontal position */
    }
    @media (max-width: 1023px) {
      .code-header-buttons .code-action-btn.stuck {
        top: 56px !important; /* 48px header + 8px margin */
      }
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
    
    /* CodeMirror overrides for code blocks */
    .code-content .CodeMirror {
      height: auto !important;
      background: transparent;
      font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, monospace;
      font-size: 0.875rem;
      line-height: 1.5;
      cursor: text;
    }
    .code-content .CodeMirror-scroll {
      overflow-x: auto !important;
      overflow-y: hidden !important;
    }
    .code-content .CodeMirror-gutters {
      background: #1e1e1e;
      border-right: 1px solid #21262d;
    }
    .code-content .CodeMirror-linenumber {
      color: #484f58;
      padding: 0 8px 0 4px;
    }
    /* Ensure text selection works in readonly CodeMirror */
    .code-content .CodeMirror-cursor {
      border-left: 1px solid #528bff;
      visibility: visible !important;
    }
    .code-content .CodeMirror-selected {
      background: rgba(82, 139, 255, 0.3) !important;
    }
    /* Ensure CodeMirror code area receives clicks */
    .code-content .CodeMirror-code {
      pointer-events: auto;
      cursor: text;
    }
    .code-content .CodeMirror-lines {
      pointer-events: auto;
      cursor: text;
    }
    .code-content .CodeMirror-line {
      pointer-events: auto;
      cursor: text;
    }
    .code-content .CodeMirror-line::selection,
    .code-content .CodeMirror-line > span::selection,
    .code-content .CodeMirror-line > span > span::selection {
      background: rgba(82, 139, 255, 0.3);
    }
    .code-content .CodeMirror-line::-moz-selection,
    .code-content .CodeMirror-line > span::-moz-selection,
    .code-content .CodeMirror-line > span > span::-moz-selection {
      background: rgba(82, 139, 255, 0.3);
    }
    /* Note: textarea styling handled by JavaScript inline styles for CodeMirror fallback */
    
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
      padding: 0 0.15rem;
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
    
    /* Preview Iframe - always white background for neutral preview */
    .code-preview-iframe {
      width: 100%;
      min-height: 300px;
      border: none;
      background: #fff;
      border-radius: 0 0 0.5rem 0.5rem;
    }
</style>
