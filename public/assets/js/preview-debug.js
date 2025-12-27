/*
 * Preview Debug UI loader
 * - Add a lightweight on-screen console for same-origin preview debugging
 * - Designed to be loaded opt-in (via query param or localStorage flag)
 */
(function(){
  'use strict';

  if (window.__ginto_preview_debug_loaded) return; // idempotent
  window.__ginto_preview_debug_loaded = true;

  // internal accumulator
  var _logItems = [];

  // small utilities
  function isDark() {
    try { return document.documentElement.classList.contains('dark'); } catch(e) { return false; }
  }

  function createPanel() {
    if (document.getElementById('preview-debug-panel')) return document.getElementById('preview-debug-panel');

    var panel = document.createElement('div');
    panel.id = 'preview-debug-panel';
    panel.className = 'ginto-preview-debug';
    panel.style.display = 'none';
    panel.style.position = 'fixed';
    panel.style.right = '12px';
    panel.style.bottom = '12px';
    panel.style.width = '440px';
    panel.style.maxWidth = 'calc(100vw - 32px)';
    panel.style.height = '320px';
    panel.style.zIndex = 999999;
    panel.style.borderRadius = '8px';
    panel.style.overflow = 'hidden';
    panel.style.boxShadow = '0 10px 30px rgba(0,0,0,0.25)';
    panel.style.background = isDark() ? 'rgba(22,22,24,0.94)' : 'rgba(255,255,255,0.96)';
    panel.style.color = isDark() ? '#ddd' : '#222';
    panel.style.fontFamily = 'system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif';
    panel.style.fontSize = '12px';
    panel.style.border = isDark() ? '1px solid rgba(255,255,255,0.04)' : '1px solid rgba(0,0,0,0.06)';

    panel.innerHTML = `
      <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 10px;border-bottom:1px solid rgba(0,0,0,0.04);background:transparent">
        <div style="display:flex;align-items:center;gap:8px">
          <strong style="font-weight:600">Preview Debug</strong>
          <span style="opacity:0.6;font-size:11px">(console)</span>
        </div>
        <div style="display:flex;gap:6px">
          <button id="preview-debug-clear" title="Clear logs" style="padding:6px 8px;border-radius:6px;border:none;background:transparent;cursor:pointer">Clear</button>
          <button id="preview-debug-close" title="Close" style="padding:6px 8px;border-radius:6px;border:none;background:transparent;cursor:pointer">✕</button>
        </div>
      </div>
      <div style="display:flex;gap:8px;padding:8px">
        <div id="preview-debug-tabs" style="display:flex;gap:6px">
          <button data-tab="console" aria-pressed="true" style="padding:6px 8px;border-radius:6px;border:1px solid rgba(0,0,0,0.04);background:rgba(0,0,0,0.02);cursor:pointer">Console</button>
          <button data-tab="events" aria-pressed="false" style="padding:6px 8px;border-radius:6px;border:1px solid rgba(0,0,0,0.04);background:transparent;cursor:pointer">Events</button>
          <button data-tab="elements" aria-pressed="false" style="padding:6px 8px;border-radius:6px;border:1px solid rgba(0,0,0,0.04);background:transparent;cursor:pointer">Elements</button>
        </div>
        <div style="margin-left:auto;display:flex;gap:6px;align-items:center">
          <input id="preview-debug-filter" placeholder="filter" style="padding:6px 8px;border-radius:6px;border:1px solid rgba(0,0,0,0.06);background:transparent;color:inherit" />
        </div>
      </div>
      <div id="preview-debug-body" style="padding:8px;overflow:auto;height:calc(100% - 136px);box-sizing:border-box">
        <div id="preview-debug-console" style="display:block">
          <div id="preview-debug-log" style="display:flex;flex-direction:column;gap:6px"></div>
        </div>
        <div id="preview-debug-events" style="display:none;color:inherit"></div>
        <div id="preview-debug-elements" style="display:none;color:inherit"></div>
      </div>
      <div style="padding:8px;border-top:1px solid rgba(0,0,0,0.04);display:flex;gap:6px;align-items:center">
        <input id="preview-debug-eval" placeholder="Type JS and press Enter (runs in preview iframe when same-origin)" style="flex:1;padding:6px 8px;border-radius:6px;border:1px solid rgba(0,0,0,0.06);background:transparent;color:inherit" />
        <button id="preview-debug-eval-run" style="padding:6px 8px;border-radius:6px;border:none;background:rgba(0,0,0,0.06);cursor:pointer">Run</button>
      </div>`;

    // styles for entries
    var style = document.createElement('style');
    style.textContent = '#preview-debug-panel .preview-log-entry{padding:6px 8px;border-radius:6px;background:rgba(0,0,0,0.02);cursor:pointer;white-space:pre-wrap;font-family:monospace;font-size:12px} #preview-debug-panel .preview-log-entry:hover{background:rgba(0,0,0,0.04)} #preview-debug-panel .preview-log-entry .meta{opacity:0.6;font-size:11px;margin-bottom:4px;display:block}';
    panel.appendChild(style);

    // append
    (document.body || document.documentElement).appendChild(panel);

    // wiring
    try {
      var clearBtn = panel.querySelector('#preview-debug-clear');
      var closeBtn = panel.querySelector('#preview-debug-close');
      var tabs = panel.querySelectorAll('#preview-debug-tabs [data-tab]');
      var filterInp = panel.querySelector('#preview-debug-filter');
      var consoleView = panel.querySelector('#preview-debug-console');
      var eventsView = panel.querySelector('#preview-debug-events');
      var elemsView = panel.querySelector('#preview-debug-elements');
      var evalInp = panel.querySelector('#preview-debug-eval');
      var evalBtn = panel.querySelector('#preview-debug-eval-run');

      clearBtn.addEventListener('click', function(){ panel.querySelector('#preview-debug-log').innerHTML = ''; _logItems = []; });
      closeBtn.addEventListener('click', function(){ panel.style.display = 'none'; });

      tabs.forEach(function(b){ b.addEventListener('click', function(){
        tabs.forEach(function(x){ x.setAttribute('aria-pressed','false'); x.style.background='transparent'; });
        b.setAttribute('aria-pressed','true'); b.style.background='rgba(0,0,0,0.02)';
        var tab = b.getAttribute('data-tab');
        consoleView.style.display = (tab==='console') ? 'block' : 'none';
        eventsView.style.display = (tab==='events') ? 'block' : 'none';
        elemsView.style.display = (tab==='elements') ? 'block' : 'none';
      })});

      filterInp.addEventListener('input', function(){ var v = (this.value||'').trim().toLowerCase(); filterLogs(v); });

      var runEval = function(){
        var code = evalInp.value || ''; if (!code) return;
        var overlay = document.getElementById('editor-preview-overlay');
        var iframe = overlay ? overlay.querySelector('iframe') : (document.querySelector('#editor-preview-overlay iframe') || null);
        var originOK = false; try { if (iframe && iframe.contentWindow) iframe.contentWindow.document && (originOK = true); } catch(e) { originOK = false; }
        try {
          if (originOK && iframe && iframe.contentWindow) {
            var res = iframe.contentWindow.eval(code);
            appendPreviewDebugLog({type:'eval-result', payload:res});
          } else {
            var res = (new Function('return ('+code+')'))();
            appendPreviewDebugLog({type:'eval-result-admin', payload:res});
          }
        } catch(e) { appendPreviewDebugLog({type:'eval-error', payload:String(e)}); }
      };
      if (evalInp) evalInp.addEventListener('keydown', function(ev){ if (ev.key === 'Enter') { runEval(); ev.preventDefault(); } });
      if (evalBtn) evalBtn.addEventListener('click', runEval);
    } catch(e) { /* non-fatal */ }

    return panel;
  }

  function filterLogs(q) {
    try {
      var container = document.getElementById('preview-debug-log');
      if (!container) return;
      q = (q||'').trim().toLowerCase();
      Array.from(container.children).forEach(function(ch){ var txt = ch.getAttribute('data-full') || ch.textContent || ''; ch.style.display = (q === '' || txt.toLowerCase().indexOf(q)!==-1) ? 'flex' : 'none'; });
    } catch(e){}
  }

  (function(){
    // Filter / dedupe configuration
    var IGNORED_TYPES = new Set(['pointermove','wheel','parent-resizer']);
    var _lastLogHash = null;
    var _lastLogTs = 0;

    function _shouldIgnore(msg) {
      try {
        if (!msg) return false;
        if (typeof msg === 'object') {
          if (msg.type && IGNORED_TYPES.has(String(msg.type))) return true;
          // support nested msg.msg payloads containing the raw string
          if (msg.msg && typeof msg.msg === 'string') {
            if (msg.msg.indexOf('pointermove') !== -1) return true;
            if (msg.msg.indexOf('wheel') !== -1) return true;
          }
        }
        return false;
      } catch(e) { return false; }
    }

    function _isDuplicate(msg) {
      try {
        var hash = (typeof msg === 'string') ? (msg) : JSON.stringify(msg);
        var now = Date.now();
        if (hash === _lastLogHash && (now - _lastLogTs) < 250) return true;
        _lastLogHash = hash; _lastLogTs = now; return false;
      } catch(e) { return false; }
    }

    window.__ginto_preview_debug_filters = { ignoredTypes: IGNORED_TYPES };

    function appendPreviewDebugLog(msg) {
      // Ignored noisy messages
      try { if (_shouldIgnore(msg)) return; } catch(e) {}
      // throttle/skip duplicates that happen very rapidly
      try { if (_isDuplicate(msg)) return; } catch(e) {}
    try {
      var entry = { ts: Date.now(), raw: msg, text: '' };
      if (typeof msg === 'string') entry.text = msg;
      else if (msg && typeof msg === 'object') { try { entry.text = JSON.stringify(msg); } catch(e) { entry.text = String(msg); } }
      else entry.text = String(msg);

      _logItems.push(entry);
      if (_logItems.length > 1500) _logItems.splice(0, _logItems.length - 1500);

      var panel = createPanel();
      var container = panel.querySelector('#preview-debug-log');
      if (!container) return;

      var node = document.createElement('div'); node.className = 'preview-log-entry'; node.style.display = 'flex'; node.style.flexDirection='column';
      node.setAttribute('data-full', entry.text);
      var meta = document.createElement('span'); meta.className = 'meta'; meta.textContent = new Date(entry.ts).toLocaleTimeString() + ' — ' + (msg && msg.type ? msg.type : 'log');
      var content = document.createElement('div'); content.textContent = entry.text;
      node.appendChild(meta); node.appendChild(content);

      // click to show coordinate marker if 'at X,Y' pattern present
      node.addEventListener('click', function(){ try { var m = entry.text.match(/at\s*([0-9]+)\s*,\s*([0-9]+)/i); if (m) { var x=parseInt(m[1],10), y=parseInt(m[2],10); var ov=document.getElementById('editor-preview-overlay')||document.querySelector('#editor-preview-overlay'); if (ov) showMarker(ov, x, y); } } catch(e){} });

      container.appendChild(node);
      if (container.children.length > 1500) container.removeChild(container.children[0]);
      container.scrollTop = container.scrollHeight;
      // Do not echo preview logs to the browser console — keep logs in the in-page panel only.
    } catch(e) { /* suppressed: avoid noisy console output from debug helper */ }
  }

    // export the function (ensures closure exported)
    window.appendPreviewDebugLog = appendPreviewDebugLog;
  })();
  function showMarker(target, x, y) {
    try {
      var marker = document.createElement('div');
      marker.style.pointerEvents='none'; marker.style.position='absolute'; marker.style.left=(x-8)+'px'; marker.style.top=(y-8)+'px'; marker.style.width='18px'; marker.style.height='18px'; marker.style.borderRadius='4px';
      marker.style.border='2px solid rgba(255,160,20,0.95)'; marker.style.boxShadow='0 0 12px rgba(255,160,20,0.65)'; marker.style.background='rgba(255,240,200,0.08)'; marker.style.zIndex=9999999;
      var parent = null;
      if (target.tagName && target.tagName.toLowerCase() === 'iframe') parent = target.parentElement || document.body; else parent = target;
      parent.appendChild(marker);
      var rect = target.getBoundingClientRect ? target.getBoundingClientRect() : { left:0, top:0 };
      if (target.tagName && target.tagName.toLowerCase() === 'iframe') { marker.style.left = (rect.left + x - 8) + 'px'; marker.style.top = (rect.top + y - 8) + 'px'; }
      setTimeout(function(){ try { marker.remove(); } catch(e){} }, 3000);
    } catch(e) {}
  }

  // expose small API (already exported inside the logging closure)

  // email-safe wrapper for other modules
  if (!window.__ginto_preview_log) window.__ginto_preview_log = function(type, payload){ appendPreviewDebugLog({ type: type, payload: payload }); };

  // handle messages
  window.addEventListener('message', function(ev){ try { var d = ev.data || {}; if (!d || !d.__ginto_preview) return; if (ev.origin === window.location.origin) appendPreviewDebugLog(d); } catch(e){} }, false);

  // Create the panel on load but do not show it automatically.
  // The editor is responsible for showing/hiding the panel when a preview
  // overlay exists (so the debug UI only appears during active preview sessions).
  try {
    // Do not create the panel automatically on load. Expose a helper so
    // the editor can create/show the panel when needed (button click).
    window.ensurePreviewDebugPanel = createPanel;
  } catch(e) {}

})();
