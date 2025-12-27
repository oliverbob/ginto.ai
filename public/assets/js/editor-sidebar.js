// Lightweight extension API for the editor left sidebar
(function(win){
  if (!win) return;
  var registry = { mounted: false, container: null, subscribers: [] };

  function findContainer() {
    try {
      var el = document.querySelector('#editor-canvas .editor-left-sidebar');
      return el || null;
    } catch (e) { return null; }
  }

  function mount() {
    if (registry.mounted) return registry;
    registry.container = findContainer();
    registry.mounted = !!registry.container;
    // call subscribers that queued before mount
    if (registry.mounted) {
      registry.subscribers.forEach(function(s){ try { s(registry.container); } catch(e){} });
      registry.subscribers = [];
    }
    try {
      // Add persistent resizer handle between sidebar and workspace
      var sidebar = registry.container;
        var parent = sidebar && sidebar.parentElement;
        // When deciding whether to create a left resizer, only treat
        // it as "already present" if there is a resizer immediately
        // after the sidebar and it is not the right-pane resizer. A
        // generic parent.querySelector('.editor-resizer') would also
        // match the right-side resizer and prevent creating the left
        // resizer when both panes are present.
        var next = sidebar && sidebar.nextElementSibling;
        var leftResizerExists = !!(
          next && next.classList && next.classList.contains('editor-resizer') && !next.classList.contains('editor-resizer-right')
        );
        if (sidebar && parent && !leftResizerExists) {
        var resizer = document.createElement('div');
        resizer.className = 'editor-resizer';
        resizer.setAttribute('role','separator');
        resizer.tabIndex = 0;
        resizer.setAttribute('aria-orientation','vertical');
        resizer.setAttribute('aria-label','Resize editor sidebar');

        // Insert resizer immediately after the sidebar
        try { parent.insertBefore(resizer, sidebar.nextSibling); } catch(e) { parent.appendChild(resizer); }

        // restore persisted width
        var key = 'ginto.editor.sidebar.width';
        var saved = null;
        try { saved = parseInt(localStorage.getItem(key), 10); } catch(e) { saved = null; }
        if (!isNaN(saved) && saved > 0) {
          sidebar.style.width = saved + 'px';
          sidebar.style.flex = '0 0 ' + saved + 'px';
        }

        // dragging logic
        var dragging = false, startX=0, startWidth=0, minW = 120, maxW = 900;
        var usingPointerEvents = !!window.PointerEvent;
        var activePointerId = null;

        function setSidebarWidth(w) {
          var clamped = Math.max(minW, Math.min(maxW, w));
          sidebar.style.width = clamped + 'px';
          sidebar.style.flex = '0 0 ' + clamped + 'px';
        }

        function onPointerMove(e) {
          if (!dragging) return;
          var clientX = e.touches ? e.touches[0].clientX : e.clientX;
          var delta = clientX - startX;
          var newWidth = Math.round(startWidth + delta);
          setSidebarWidth(newWidth);
          // Post debug message for parent-level pointer moves so the editor
          // debug panel can show exactly what the resizer is doing.
            try {
              // Prefer calling the direct helper when available (faster, avoids
              // postMessage edge cases). Fallback to window.postMessage for cross-
              // frame listeners.
              if (window.__ginto_preview_log && typeof window.__ginto_preview_log === 'function') {
                window.__ginto_preview_log('parent-resizer', { ev: 'pointermove', x: clientX, delta: delta, width: newWidth });
              } else if (window && window.postMessage) {
                window.postMessage({ __ginto_preview: true, type: 'parent-resizer', msg: JSON.stringify({ ev: 'pointermove', x: clientX, delta: delta, width: newWidth }) }, window.location.origin);
              }
          } catch (e) {}
        }

        function stopDrag() {
          if (!dragging) return;
          dragging = false; resizer.classList.remove('resizing'); sidebar.classList.remove('resizing');
          // persist width
          try { localStorage.setItem(key, Math.round(sidebar.getBoundingClientRect().width)); } catch(e) {}
          // remove all possible listeners depending on the input model used
          try { document.removeEventListener('mousemove', onPointerMove); } catch(e){}
          try { document.removeEventListener('mouseup', stopDrag); } catch(e){}
          try { document.removeEventListener('touchmove', onPointerMove); } catch(e){}
          try { document.removeEventListener('touchend', stopDrag); } catch(e){}
          try { document.removeEventListener('pointermove', onPointerMove); } catch(e){}
          try { document.removeEventListener('pointerup', stopDrag); } catch(e){}
          try {
            if (window.__ginto_preview_log && typeof window.__ginto_preview_log === 'function') {
              window.__ginto_preview_log('parent-resizer', { ev: 'pointerup' });
            } else {
              window.postMessage({ __ginto_preview: true, type: 'parent-resizer', msg: JSON.stringify({ ev: 'pointerup' }) }, window.location.origin);
            }
          } catch(e){}
        }

        // Prefer PointerEvents when available – they allow pointer capture
        // which improves drag stability when the cursor passes over iframes.
        if (usingPointerEvents) {
          resizer.addEventListener('pointerdown', function(ev){
            try {
              // only react to primary button
              if (ev.isPrimary === false) return;
              dragging = true; startX = ev.clientX; startWidth = Math.round(sidebar.getBoundingClientRect().width);
              activePointerId = ev.pointerId;
              try { if (resizer.setPointerCapture) resizer.setPointerCapture(activePointerId); } catch(e){}
              resizer.classList.add('resizing'); sidebar.classList.add('resizing');
              document.addEventListener('pointermove', onPointerMove);
              document.addEventListener('pointerup', stopDrag);
              ev.preventDefault();
              try {
                if (window.__ginto_preview_log && typeof window.__ginto_preview_log === 'function') {
                  window.__ginto_preview_log('parent-resizer', { ev: 'pointerdown', x: ev.clientX, pointerId: ev.pointerId });
                } else {
                  window.postMessage({ __ginto_preview: true, type: 'parent-resizer', msg: JSON.stringify({ ev: 'pointerdown', x: ev.clientX, pointerId: ev.pointerId }) }, window.location.origin);
                }
              } catch(e){}
            } catch(e){}
          });
        } else {
          resizer.addEventListener('mousedown', function(ev){
            dragging = true; startX = ev.clientX; startWidth = Math.round(sidebar.getBoundingClientRect().width);
            resizer.classList.add('resizing'); sidebar.classList.add('resizing');
            document.addEventListener('mousemove', onPointerMove);
            document.addEventListener('mouseup', stopDrag);
            ev.preventDefault();
            try {
              if (window.__ginto_preview_log && typeof window.__ginto_preview_log === 'function') {
                window.__ginto_preview_log('parent-resizer', { ev: 'pointerdown', x: ev.clientX });
              } else {
                window.postMessage({ __ginto_preview: true, type: 'parent-resizer', msg: JSON.stringify({ ev: 'pointerdown', x: ev.clientX }) }, window.location.origin);
              }
            } catch(e){}
          });
        }

        resizer.addEventListener('touchstart', function(ev){
          dragging = true; startX = ev.touches[0].clientX; startWidth = Math.round(sidebar.getBoundingClientRect().width);
          resizer.classList.add('resizing'); sidebar.classList.add('resizing');
          document.addEventListener('touchmove', onPointerMove, { passive: false });
          document.addEventListener('touchend', stopDrag);
          ev.preventDefault();
          try { var t = ev.touches && ev.touches[0];
            if (window.__ginto_preview_log && typeof window.__ginto_preview_log === 'function') {
              window.__ginto_preview_log('parent-resizer', { ev: 'touchstart', x: t && t.clientX || 0, y: t && t.clientY || 0 });
            } else {
              window.postMessage({ __ginto_preview: true, type: 'parent-resizer', msg: JSON.stringify({ ev: 'touchstart', x: t && t.clientX || 0, y: t && t.clientY || 0 }) }, window.location.origin);
            }
          } catch(e){}
        }, { passive: false });

        // When pointer-based dragging ends — if pointer capture was used, try to release it
        var _origStopDrag = stopDrag;
        stopDrag = function() {
          try {
            if (activePointerId && resizer.releasePointerCapture) {
              try { resizer.releasePointerCapture(activePointerId); } catch(e){}
            }
          } catch(e) {}
          activePointerId = null;
          try { window.postMessage({ __ginto_preview: true, type: 'parent-resizer', msg: JSON.stringify({ ev: 'pointerended' }) }, window.location.origin); } catch(e){}
          return _origStopDrag();
        };

        // keyboard and double-click toggles collapse/restore
        resizer.addEventListener('keydown', function(ev){
          // left/right arrows resize when focused
          if (ev.key === 'ArrowLeft' || ev.key === 'ArrowRight') {
            ev.preventDefault();
            var rect = sidebar.getBoundingClientRect();
            var delta = (ev.key === 'ArrowLeft') ? -12 : 12;
            setSidebarWidth(Math.round(rect.width + delta));
            // persist change
            try { localStorage.setItem(key, Math.round(sidebar.getBoundingClientRect().width)); } catch(e) {}
            return;
          }
          // Enter toggles collapse/restore
          if (ev.key === 'Enter' || ev.key === ' ') {
            ev.preventDefault(); resizer.dispatchEvent(new MouseEvent('dblclick'));
          }
        });

        // double-click toggles collapse/restore
        resizer.addEventListener('dblclick', function(){
          try {
            var collapsedKey = 'ginto.editor.sidebar.collapsed';
            var collapsed = sidebar.classList.toggle('collapsed');
            if (collapsed) {
              // save current width to local storage so restore works
              var cur = Math.round(sidebar.getBoundingClientRect().width);
              localStorage.setItem('ginto.editor.sidebar.lastWidth', String(cur));
              // enforce small width for collapsed
              sidebar.style.width = '40px'; sidebar.style.flex = '0 0 40px';
              localStorage.setItem(collapsedKey, '1');
            } else {
              // restore width
              var last = parseInt(localStorage.getItem('ginto.editor.sidebar.lastWidth') || '220', 10) || 220;
              setSidebarWidth(last);
              localStorage.setItem(collapsedKey, '0');
            }
          } catch(e) {}
        });

        // Provide API to control programmatically
        registry.resizer = {
          setWidth: function(w){ setSidebarWidth(Number(w)); try{ localStorage.setItem(key, Math.round(sidebar.getBoundingClientRect().width)); }catch(e){} },
          getWidth: function(){ return Math.round(sidebar.getBoundingClientRect().width); },
          collapse: function(){ sidebar.classList.add('collapsed'); sidebar.style.width = '40px'; sidebar.style.flex = '0 0 40px'; try{ localStorage.setItem('ginto.editor.sidebar.collapsed','1'); }catch(e){} },
          restore: function(){ sidebar.classList.remove('collapsed'); var last = parseInt(localStorage.getItem('ginto.editor.sidebar.lastWidth') || '220', 10) || 220; setSidebarWidth(last); try{ localStorage.setItem('ginto.editor.sidebar.collapsed','0'); }catch(e){} }
        };

        // apply collapsed state if set
        try { if (localStorage.getItem('ginto.editor.sidebar.collapsed') === '1') { sidebar.classList.add('collapsed'); sidebar.style.width = '40px'; sidebar.style.flex = '0 0 40px'; } } catch(e) {}
        
          // Observe assistant pane width and toggle narrow-mode on the footer only
          try {
            (function(){
              var assistant = document.getElementById('assistant-pane') || document.querySelector('.assistant-pane');
              if (!assistant) return;
              var footer = assistant.querySelector('.assistant-footer');
              var footerOverlay = assistant.querySelector('.assistant-footer-overlay');
              var threshold = 220; // width (px) below which we consider the footer "narrow"
              function applyNarrow(w){
                try {
                  var width = (typeof w === 'number') ? w : Math.round(assistant.getBoundingClientRect().width || 0);
                  var isNarrow = (width > 0 && width < threshold);
                  if (footer) {
                    footer.classList.toggle('narrow', !!isNarrow);
                  }
                  if (footerOverlay) {
                    footerOverlay.classList.toggle('narrow', !!isNarrow);
                  }
                } catch(e){}
              }
              // Prefer ResizeObserver if available
              if (window.ResizeObserver) {
                try {
                  var ro = new ResizeObserver(function(entries){
                    entries.forEach(function(en){ applyNarrow(en.contentRect ? en.contentRect.width : assistant.getBoundingClientRect().width); });
                  });
                  ro.observe(assistant);
                } catch(e){
                  // fallback
                  window.addEventListener('resize', function(){ applyNarrow(); });
                  applyNarrow();
                }
              } else {
                window.addEventListener('resize', function(){ applyNarrow(); });
                // initial check
                applyNarrow();
              }
            })();
          } catch(e) {}
      
        // Add persistent resizer handle for the right pane (workspace <-> right pane)
        try {
          var rightPane = parent && parent.querySelector && parent.querySelector('.editor-right-pane');
          if (rightPane && parent && !parent.querySelector('.editor-resizer-right')) {
            var rResizer = document.createElement('div');
            rResizer.className = 'editor-resizer editor-resizer-right';
            rResizer.setAttribute('role','separator');
            rResizer.tabIndex = 0;
            rResizer.setAttribute('aria-orientation','vertical');
            rResizer.setAttribute('aria-label','Resize right pane');

            // Insert resizer immediately before the right pane
            try { parent.insertBefore(rResizer, rightPane); } catch(e) { parent.appendChild(rResizer); }

            // restore persisted right pane width (fallback to data-width attr or 320)
            var keyR = 'ginto.editor.rightpane.width';
            var savedR = null; try { savedR = parseInt(localStorage.getItem(keyR), 10); } catch(e) { savedR = null; }
            var defaultR = parseInt(rightPane.getAttribute('data-width') || '320', 10) || 320;
            if (!isNaN(savedR) && savedR > 0) {
              rightPane.style.width = savedR + 'px'; rightPane.style.flex = '0 0 ' + savedR + 'px';
            } else {
              rightPane.style.width = defaultR + 'px'; rightPane.style.flex = '0 0 ' + defaultR + 'px';
            }

            // dragging logic for right pane
            var draggingR = false, startXR=0, startWidthR=0, minWR = 160, maxWR = 1200;

            function setRightWidth(w) {
              var clamped = Math.max(minWR, Math.min(maxWR, w));
              rightPane.style.width = clamped + 'px';
              rightPane.style.flex = '0 0 ' + clamped + 'px';
            }

            function onPointerMoveR(e) {
              if (!draggingR) return;
              var clientX = e.touches ? e.touches[0].clientX : e.clientX;
              var delta = clientX - startXR; // positive if moved right
              var newWidth = Math.round(startWidthR - delta); // moving right reduces width
              setRightWidth(newWidth);
              try { if (window.__ginto_preview_log && typeof window.__ginto_preview_log === 'function') { window.__ginto_preview_log('parent-resizer-right', { ev: 'pointermove', x: clientX, delta: delta, width: newWidth }); } else { window.postMessage({ __ginto_preview: true, type: 'parent-resizer-right', msg: JSON.stringify({ ev: 'pointermove', x: clientX, delta: delta, width: newWidth }) }, window.location.origin); } } catch(e){}
            }

            function stopDragR() {
              if (!draggingR) return;
              draggingR = false; rResizer.classList.remove('resizing'); rightPane.classList.remove('resizing');
              try { localStorage.setItem(keyR, Math.round(rightPane.getBoundingClientRect().width)); } catch(e) {}
              try { document.removeEventListener('mousemove', onPointerMoveR); } catch(e){}
              try { document.removeEventListener('mouseup', stopDragR); } catch(e){}
              try { document.removeEventListener('touchmove', onPointerMoveR); } catch(e){}
              try { document.removeEventListener('touchend', stopDragR); } catch(e){}
              try { document.removeEventListener('pointermove', onPointerMoveR); } catch(e){}
              try { document.removeEventListener('pointerup', stopDragR); } catch(e){}
              try { if (window.__ginto_preview_log && typeof window.__ginto_preview_log === 'function') { window.__ginto_preview_log('parent-resizer-right', { ev: 'pointerup' }); } else { window.postMessage({ __ginto_preview: true, type: 'parent-resizer-right', msg: JSON.stringify({ ev: 'pointerup' }) }, window.location.origin); } } catch(e){}
            }

            var usingPointerEventsR = !!window.PointerEvent;
            var activePointerIdR = null;
            if (usingPointerEventsR) {
              rResizer.addEventListener('pointerdown', function(ev){
                try { if (ev.isPrimary === false) return; draggingR = true; startXR = ev.clientX; startWidthR = Math.round(rightPane.getBoundingClientRect().width); activePointerIdR = ev.pointerId; try { if (rResizer.setPointerCapture) rResizer.setPointerCapture(activePointerIdR); } catch(e){} rResizer.classList.add('resizing'); rightPane.classList.add('resizing'); document.addEventListener('pointermove', onPointerMoveR); document.addEventListener('pointerup', stopDragR); ev.preventDefault(); if (window.__ginto_preview_log && typeof window.__ginto_preview_log === 'function') { window.__ginto_preview_log('parent-resizer-right', { ev: 'pointerdown', x: ev.clientX, pointerId: ev.pointerId }); } else { window.postMessage({ __ginto_preview: true, type: 'parent-resizer-right', msg: JSON.stringify({ ev: 'pointerdown', x: ev.clientX, pointerId: ev.pointerId }) }, window.location.origin); } } catch(e){}
              });
            } else {
              rResizer.addEventListener('mousedown', function(ev){ draggingR = true; startXR = ev.clientX; startWidthR = Math.round(rightPane.getBoundingClientRect().width); rResizer.classList.add('resizing'); rightPane.classList.add('resizing'); document.addEventListener('mousemove', onPointerMoveR); document.addEventListener('mouseup', stopDragR); ev.preventDefault(); try { if (window.__ginto_preview_log && typeof window.__ginto_preview_log === 'function') { window.__ginto_preview_log('parent-resizer-right', { ev: 'pointerdown', x: ev.clientX }); } else { window.postMessage({ __ginto_preview: true, type: 'parent-resizer-right', msg: JSON.stringify({ ev: 'pointerdown', x: ev.clientX }) }, window.location.origin); } } catch(e){} });
            }

            rResizer.addEventListener('touchstart', function(ev){ draggingR = true; startXR = ev.touches[0].clientX; startWidthR = Math.round(rightPane.getBoundingClientRect().width); rResizer.classList.add('resizing'); rightPane.classList.add('resizing'); document.addEventListener('touchmove', onPointerMoveR, { passive: false }); document.addEventListener('touchend', stopDragR); ev.preventDefault(); try { var t = ev.touches && ev.touches[0]; if (window.__ginto_preview_log && typeof window.__ginto_preview_log === 'function') { window.__ginto_preview_log('parent-resizer-right', { ev: 'touchstart', x: t && t.clientX || 0, y: t && t.clientY || 0 }); } else { window.postMessage({ __ginto_preview: true, type: 'parent-resizer-right', msg: JSON.stringify({ ev: 'touchstart', x: t && t.clientX || 0, y: t && t.clientY || 0 }) }, window.location.origin); } } catch(e){} }, { passive: false });

            var _origStopDragR = stopDragR;
            stopDragR = function() {
              try { if (activePointerIdR && rResizer.releasePointerCapture) { try { rResizer.releasePointerCapture(activePointerIdR); } catch(e) {} } } catch(e) {}
              activePointerIdR = null;
              try { window.postMessage({ __ginto_preview: true, type: 'parent-resizer-right', msg: JSON.stringify({ ev: 'pointerended' }) }, window.location.origin); } catch(e){}
              return _origStopDragR();
            };

            // keyboard: left increases width, right decreases width; Enter/space double-click behavior
            rResizer.addEventListener('keydown', function(ev){
              if (ev.key === 'ArrowLeft' || ev.key === 'ArrowRight') {
                ev.preventDefault(); var rect = rightPane.getBoundingClientRect(); var delta = (ev.key === 'ArrowLeft') ? 12 : -12; setRightWidth(Math.round(rect.width + delta)); try { localStorage.setItem(keyR, Math.round(rightPane.getBoundingClientRect().width)); } catch(e) {} return;
              }
              if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); rResizer.dispatchEvent(new MouseEvent('dblclick')); }
            });

            // double-click toggles collapse/restore
            rResizer.addEventListener('dblclick', function(){
              try {
                var collapsedKeyR = 'ginto.editor.rightpane.collapsed';
                var collapsedR = rightPane.classList.toggle('collapsed');
                if (collapsedR) {
                  var cur = Math.round(rightPane.getBoundingClientRect().width);
                  localStorage.setItem('ginto.editor.rightpane.lastWidth', String(cur));
                  rightPane.style.width = '40px'; rightPane.style.flex = '0 0 40px';
                  localStorage.setItem(collapsedKeyR, '1');
                } else {
                  var lastR = parseInt(localStorage.getItem('ginto.editor.rightpane.lastWidth') || String(defaultR), 10) || defaultR;
                  setRightWidth(lastR);
                  localStorage.setItem(collapsedKeyR, '0');
                }
              } catch(e) {}
            });

            // expose small API
            registry.rightResizer = {
              setWidth: function(w) { setRightWidth(Number(w)); try { localStorage.setItem(keyR, Math.round(rightPane.getBoundingClientRect().width)); } catch(e) {} },
              getWidth: function() { return Math.round(rightPane.getBoundingClientRect().width); },
              collapse: function() { rightPane.classList.add('collapsed'); rightPane.style.width = '40px'; rightPane.style.flex = '0 0 40px'; try { localStorage.setItem('ginto.editor.rightpane.collapsed','1'); } catch(e) {} },
              restore: function() { rightPane.classList.remove('collapsed'); var lastR = parseInt(localStorage.getItem('ginto.editor.rightpane.lastWidth') || String(defaultR), 10) || defaultR; setRightWidth(lastR); try { localStorage.setItem('ginto.editor.rightpane.collapsed','0'); } catch(e) {} }
            };

            try { if (localStorage.getItem('ginto.editor.rightpane.collapsed') === '1') { rightPane.classList.add('collapsed'); rightPane.style.width = '40px'; rightPane.style.flex = '0 0 40px'; } } catch(e) {}
          }
        } catch(e) { /* ignore right resizer errors */ }
      
      // If a right resizer was already present in the server-rendered HTML
      // (for example when the page template injects it) we still need to
      // attach the drag handlers — do that here if not already wired.
      try {
        var existingR = document.querySelector('#editor-canvas .editor-resizer-right');
        var existingPane = document.querySelector('#editor-canvas .editor-right-pane');
        if (existingR && existingPane && !existingR.dataset.gintoAttached) {
          // Mark attached to avoid double-binding
          existingR.dataset.gintoAttached = '1';
          // restore persisted width
          try {
            var keyR = 'ginto.editor.rightpane.width';
            var savedR = null; try { savedR = parseInt(localStorage.getItem(keyR), 10); } catch(e) { savedR = null; }
            var defaultR = parseInt(existingPane.getAttribute('data-width') || '320', 10) || 320;
            if (!isNaN(savedR) && savedR > 0) { existingPane.style.width = savedR + 'px'; existingPane.style.flex = '0 0 ' + savedR + 'px'; }
            else { existingPane.style.width = defaultR + 'px'; existingPane.style.flex = '0 0 ' + defaultR + 'px'; }
          } catch(e) {}

          // Attach listeners (mirrors the logic used when JS created the resizer)
          (function(rRes, pane){
            var draggingR = false, startXR = 0, startWidthR = 0, minWR = 160, maxWR = 1200;
            function setRightWidth(w) { var clamped = Math.max(minWR, Math.min(maxWR, w)); pane.style.width = clamped + 'px'; pane.style.flex = '0 0 ' + clamped + 'px'; }
            function onPointerMoveR(e) { if (!draggingR) return; var clientX = e.touches ? e.touches[0].clientX : e.clientX; var delta = clientX - startXR; var newWidth = Math.round(startWidthR - delta); setRightWidth(newWidth); }
            function stopDragR() { if (!draggingR) return; draggingR = false; rRes.classList.remove('resizing'); pane.classList.remove('resizing'); try { localStorage.setItem('ginto.editor.rightpane.width', Math.round(pane.getBoundingClientRect().width)); } catch(e){}; try { document.removeEventListener('mousemove', onPointerMoveR); document.removeEventListener('mouseup', stopDragR); document.removeEventListener('touchmove', onPointerMoveR); document.removeEventListener('touchend', stopDragR); document.removeEventListener('pointermove', onPointerMoveR); document.removeEventListener('pointerup', stopDragR); } catch(e){} }
            var usingPointerEventsR = !!window.PointerEvent; var activePointerIdR = null;
            if (usingPointerEventsR) {
              rRes.addEventListener('pointerdown', function(ev){ try { if (ev.isPrimary === false) return; draggingR = true; startXR = ev.clientX; startWidthR = Math.round(pane.getBoundingClientRect().width); activePointerIdR = ev.pointerId; try { if (rRes.setPointerCapture) rRes.setPointerCapture(activePointerIdR); } catch(e){} rRes.classList.add('resizing'); pane.classList.add('resizing'); document.addEventListener('pointermove', onPointerMoveR); document.addEventListener('pointerup', stopDragR); ev.preventDefault(); } catch(e){} });
            } else {
              rRes.addEventListener('mousedown', function(ev){ draggingR = true; startXR = ev.clientX; startWidthR = Math.round(pane.getBoundingClientRect().width); rRes.classList.add('resizing'); pane.classList.add('resizing'); document.addEventListener('mousemove', onPointerMoveR); document.addEventListener('mouseup', stopDragR); ev.preventDefault(); });
            }
            rRes.addEventListener('touchstart', function(ev){ draggingR = true; startXR = ev.touches[0].clientX; startWidthR = Math.round(pane.getBoundingClientRect().width); rRes.classList.add('resizing'); pane.classList.add('resizing'); document.addEventListener('touchmove', onPointerMoveR, { passive:false }); document.addEventListener('touchend', stopDragR); ev.preventDefault(); }, { passive:false });
            var _origStopDragR = stopDragR; stopDragR = function(){ try { if (activePointerIdR && rRes.releasePointerCapture) { try { rRes.releasePointerCapture(activePointerIdR); } catch(e) {} } } catch(e) {} activePointerIdR = null; return _origStopDragR(); };
            // keyboard support
            rRes.addEventListener('keydown', function(ev){ if (ev.key === 'ArrowLeft' || ev.key === 'ArrowRight') { ev.preventDefault(); var rect = pane.getBoundingClientRect(); var delta = (ev.key === 'ArrowLeft') ? 12 : -12; setRightWidth(Math.round(rect.width + delta)); try { localStorage.setItem('ginto.editor.rightpane.width', Math.round(pane.getBoundingClientRect().width)); } catch(e) {} return; } if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); rRes.dispatchEvent(new MouseEvent('dblclick')); } });
            // double click toggle
            rRes.addEventListener('dblclick', function(){ try { var collapsedKeyR = 'ginto.editor.rightpane.collapsed'; var collapsedR = pane.classList.toggle('collapsed'); if (collapsedR) { var cur = Math.round(pane.getBoundingClientRect().width); localStorage.setItem('ginto.editor.rightpane.lastWidth', String(cur)); pane.style.width = '40px'; pane.style.flex = '0 0 40px'; localStorage.setItem(collapsedKeyR, '1'); } else { var lastR = parseInt(localStorage.getItem('ginto.editor.rightpane.lastWidth') || String(parseInt(pane.getAttribute('data-width') || '320',10)), 10) || parseInt(pane.getAttribute('data-width')||'320',10); setRightWidth(lastR); localStorage.setItem(collapsedKeyR, '0'); } } catch(e){} });
          })(existingR, existingPane);
        }
      } catch(e) { /* ignore attach errors */ }
      }
    } catch (e) { /* ignore resizer failure */ }
    return registry;
  }

  // Public API
  var api = {
    mount: mount,
    getContainer: function(){ return registry.container || findContainer(); },
    onReady: function(cb){
      if (!cb || typeof cb !== 'function') return;
      if (registry.container) return cb(registry.container);
      registry.subscribers.push(cb);
      // attempt mount if possible
      mount();
    },
    createPanel: function(html) {
      var c = registry.container || findContainer();
      if (!c) return null;
      var wrapper = document.createElement('div');
      wrapper.className = 'editor-sidebar-panel';
      wrapper.innerHTML = html || '';
      c.appendChild(wrapper);
      return wrapper;
    }
  };

  // Expose name-spaced API (guarantee not to overwrite if present)
  try { if (!win.gintoEditorSidebar) win.gintoEditorSidebar = api; } catch(e){}

  // Try to mount once on DOMContentLoaded
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mount);
  else setTimeout(mount, 20);

  // Expose for module systems too
  try { if (typeof module !== 'undefined' && module.exports) module.exports = api; } catch(e){}
})(window);
