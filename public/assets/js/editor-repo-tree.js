/* Render a repository file tree in the editor left sidebar.
 * Expects a global `window.gintoRepoTree` variable containing a hierarchical
 * JSON structure in the same shape the PagesController.buildViewScriptsTree uses.
 */
(function(){
  // Route map provided by server: { 'src/Views/user/commissions.php': '/api/user/commissions' }
  var gintoRouteMap = (window.gintoRouteMap && typeof window.gintoRouteMap === 'object') ? window.gintoRouteMap : {};
  // Build quick basename map (basename -> route) favoring API and longer paths where applicable
  var gintoRouteBase = {};
  Object.keys(gintoRouteMap).forEach(function(fp){
    try {
      var base = fp.split('/').pop();
      var existing = gintoRouteBase[base];
      // prefer API routes
      if (!existing) gintoRouteBase[base] = gintoRouteMap[fp];
      else if (existing && (existing.indexOf('/api/') === 0) && gintoRouteMap[fp].indexOf('/api/') !== 0) {
        // keep existing which is API
      } else if (gintoRouteMap[fp].indexOf('/api/') === 0) {
        gintoRouteBase[base] = gintoRouteMap[fp];
      } else {
        // prefer longer path (more specific)
        if ((fp || '').length > ((existing && existing.length) || 0)) gintoRouteBase[base] = gintoRouteMap[fp];
      }
    } catch(e){}
  });
  function escapeHtml(unsafe) {
    return String(unsafe).replace(/[&<>'"`]/g, function(s){ return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;","`":"&#96;"})[s]; });
  }

  function createNode(item, fullPath) {
    if (!item) return null;
    var li = document.createElement('li');
    var node = document.createElement('div'); node.className = 'tree-node';

    if (item.type === 'dir' || item.children) {
      var toggle = document.createElement('button'); toggle.className = 'dir-toggle'; toggle.type = 'button';
      // chevron SVG (compact, rotates when the .open class is applied)
      toggle.innerHTML = '<svg class="chev" aria-hidden="true" width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">' +
             '<polyline points="3,2 8,6 3,10" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" fill="none" /></svg>';
      node.appendChild(toggle);
      var name = document.createElement('div'); name.className = 'dir-name';
      // mark node so CSS can show connectors for branches
      if (node && node.classList) node.classList.add('has-children');
      // Use the chevron toggle as the visual affordance — keep dir label only
      var labelText = document.createElement('span'); labelText.className = 'dir-label'; labelText.textContent = item.name || item.label || fullPath;
      name.appendChild(labelText);
      node.appendChild(name);
      li.appendChild(node);

      var children = document.createElement('ul'); children.className = 'tree-children hidden';
      // Prefer directories first (alphabetically) then files (alphabetically)
      var entries = Object.entries(item.children || {});
      var dirs = entries.filter(function(e){ return e[1] && e[1].type === 'dir'; }).map(function(e){ return e[0]; }).sort();
      var files = entries.filter(function(e){ return !e[1] || e[1].type !== 'dir'; }).map(function(e){ return e[0]; }).sort();
      var keys = dirs.concat(files);

      keys.forEach(function(k){
        var child = item.children[k];
        child.name = k;
        var childEl = createNode(child, fullPath ? fullPath + '/' + k : k);
        if (childEl) children.appendChild(childEl);
      });
      li.appendChild(children);

      // Attach a data-path to the list item for persistence
      li.setAttribute('data-path', fullPath);
      toggle.addEventListener('click', function(){
        var opened = !children.classList.contains('hidden');
        if (opened) {
          children.classList.add('hidden'); toggle.classList.remove('open'); toggle.setAttribute('aria-expanded','false');
          // Persist closed state
          persistDir(fullPath, false);
        }
        else {
          children.classList.remove('hidden'); toggle.classList.add('open'); toggle.setAttribute('aria-expanded','true');
          // Persist open state
          persistDir(fullPath, true);
          // Update header file-select to show files under this directory
          try { populateFileSelectForDir(fullPath); } catch(e) {}
        }
      });
      // clicking the name should also toggle for ease of use
      name.addEventListener('click', function(){ toggle.click(); });
    } else { // file
      var itemEl = document.createElement('div'); itemEl.className = 'file-item';
      itemEl.setAttribute('data-path', item.path || fullPath || item.name || '');
      if (item.encoded) itemEl.setAttribute('data-encoded', item.encoded);
      // mark editable state on DOM so preview knows what to show
      if (item.editable) itemEl.setAttribute('data-editable', '1'); else itemEl.setAttribute('data-editable', '0');
      // choose an icon based on file extension
      var pathString = item.path || '';
      var ext = '';
      if (pathString.indexOf('.') !== -1) {
        ext = pathString.split('.').pop().toLowerCase();
      }
      // iconClass holds a class-string (eg. 'far fa-file' (outlined) or 'fab fa-js')
      // prefer 'regular' (outlined) file icons for closer VS Code look
      var iconClass = 'far fa-file';
      var fileTypeClass = 'ft-default';
      // Common mappings — extend this list as needed
      // Special-case exact basenames like .env or .installed
      var baseName = (item.path || '').split('/').pop().toLowerCase();
      if (baseName === '.installed' || baseName === 'installed') {
        ext = 'installed';
      }
      // Treat Apache htaccess files specially (basename `.htaccess`)
      if (baseName === '.htaccess' || baseName === 'htaccess') {
        ext = 'htaccess';
      }

      switch (ext) {
        case 'php': iconClass = 'fa fa-file-code'; fileTypeClass = 'ft-php'; break;
        case 'js': iconClass = 'fab fa-js'; fileTypeClass = 'ft-js'; break;
        case 'json': iconClass = 'fa fa-code'; fileTypeClass = 'ft-json'; break;
        case 'md': case 'markdown': iconClass = 'far fa-file-lines'; fileTypeClass = 'ft-md'; break;
        case 'css': iconClass = 'fab fa-css3-alt'; fileTypeClass = 'ft-css'; break;
        case 'html': case 'htm': iconClass = 'fab fa-html5'; fileTypeClass = 'ft-html'; break;
        case 'png': case 'jpg': case 'jpeg': case 'gif': case 'svg': iconClass = 'fa fa-file-image'; fileTypeClass = 'ft-image'; break;
        case 'sql': iconClass = 'fa fa-database'; fileTypeClass = 'ft-sql'; break;
        case 'env': case 'dotenv': iconClass = 'fa fa-cog'; fileTypeClass = 'ft-env'; break;
        case 'installed': iconClass = 'fa fa-th-large'; fileTypeClass = 'ft-installed'; break;
        case 'htaccess': iconClass = 'fa fa-server'; fileTypeClass = 'ft-htaccess'; break;
        case 'yml': case 'yaml': iconClass = 'fa fa-file-code'; fileTypeClass = 'ft-yaml'; break;
        case 'lock':
          // Only show a lock icon to admins; non-admins see a neutral file icon.
          try {
            if (typeof window.GINTO_isAdmin !== 'undefined' && (window.GINTO_isAdmin === true || window.GINTO_isAdmin === 'true')) {
              iconClass = 'fa fa-lock'; fileTypeClass = 'ft-lock';
            } else {
              iconClass = 'far fa-file'; fileTypeClass = 'ft-default';
            }
          } catch(e) { iconClass = 'far fa-file'; fileTypeClass = 'ft-default'; }
          break;
        case 'csv': iconClass = 'fa fa-table'; fileTypeClass = 'ft-csv'; break;
        case 'xml': iconClass = 'fa fa-code'; fileTypeClass = 'ft-xml'; break;
        case 'py': iconClass = 'fab fa-python'; fileTypeClass = 'ft-py'; break;
        case 'rb': iconClass = 'fa fa-gem'; fileTypeClass = 'ft-rb'; break;
        case 'go': iconClass = 'fa fa-code'; fileTypeClass = 'ft-go'; break;
        case 'java': iconClass = 'fa fa-coffee'; fileTypeClass = 'ft-java'; break;
        case 'c': case 'cpp': case 'h': iconClass = 'fa fa-code'; fileTypeClass = 'ft-c'; break;
        case 'sh': case 'bash': iconClass = 'fa fa-terminal'; fileTypeClass = 'ft-sh'; break;
        default: iconClass = 'far fa-file'; fileTypeClass = 'ft-default'; break;
      }

      var ico = document.createElement('i'); ico.className = iconClass + ' file-ico ' + fileTypeClass; ico.setAttribute('aria-hidden','true');
      var label = document.createElement('span'); label.className = 'file-label'; label.textContent = item.name || item.path || fullPath;
      // Eye icon for routable files
      var eyeIcon = null;
      try {
        var routeForFile = null;
        // Check exact path mapping first (item.path defaults to repo-relative path)
        try { routeForFile = gintoRouteMap[item.path || fullPath] || null; } catch(e) { routeForFile = null; }
        // Fallback: check basename mapping
        if (!routeForFile) {
          try { routeForFile = gintoRouteBase[item.name || ''] || null; } catch(e) { routeForFile = null; }
        }
        if (routeForFile) {
          eyeIcon = document.createElement('span');
          eyeIcon.title = 'Viewable route: ' + routeForFile;
          eyeIcon.style.color = '#4caf50';
          eyeIcon.style.fontSize = '1.1em';
          eyeIcon.style.verticalAlign = 'middle';
          eyeIcon.style.marginLeft = '4px';
          eyeIcon.textContent = '👁️';
          // make the icon clickable — open the route in a new tab
          eyeIcon.style.cursor = 'pointer';
          eyeIcon.addEventListener('click', function(ev){
            ev.stopPropagation();
            try { window.open(routeForFile, '_blank'); } catch(e){}
          });
        }
      } catch(e){}
      var meta = document.createElement('span'); meta.className = 'meta'; meta.textContent = item.mtime ? (new Date(item.mtime * 1000)).toLocaleDateString() : '';
      itemEl.appendChild(ico); itemEl.appendChild(label); if (eyeIcon) itemEl.appendChild(eyeIcon); itemEl.appendChild(meta);
      node.appendChild(itemEl); li.appendChild(node);

      // click/select
      itemEl.addEventListener('click', function(){
        selectFile(itemEl);
        persistFile(item.path || fullPath);
        // Ensure the header select lists files under the containing directory
        try {
          var parentLi = itemEl.closest('li').parentElement.closest('li');
          var parentPath = parentLi ? parentLi.getAttribute('data-path') : null;
          if (parentPath) populateFileSelectForDir(parentPath, item.encoded || item.path || fullPath);
        } catch(e){}
      });
      itemEl.addEventListener('dblclick', function(){
        if (item.encoded) {
          window.location.href = '/admin/pages/scripts/edit?file=' + item.encoded;
        }
      });
    }

    return li;
  }

  var container = null;
  function selectFile(el) {
    if (!container) container = document.querySelector('.editor-left-sidebar');
    if (!container) return;
    // clear previous selection for files and directory highlight
    var previousFiles = container.querySelectorAll('.file-item.selected');
    previousFiles.forEach(function(p){ p.classList.remove('selected'); p.style.background = ''; });
    // clear previously-marked directories
    var prevDirs = container.querySelectorAll('.dir-name.selected');
    prevDirs.forEach(function(d){ d.classList.remove('selected'); });
    el.classList.add('selected'); el.style.background = 'rgba(255,255,0,0.06)';
    // Persist selected file
    persistFile(el.getAttribute('data-path'));

    // Highlight parent directories up the tree
    try {
      var node = el.closest('li');
      while (node && node.parentElement) {
        var parentUl = node.parentElement.closest('li');
        if (!parentUl) break;
        var label = parentUl.querySelector(':scope > .tree-node > .dir-name');
        if (label) {
          label.classList.add('selected');
        }

        // Ensure parent directory is expanded so the selected file is visible
        try {
          var parentChildren = parentUl.querySelector(':scope > ul.tree-children');
          var parentToggle = parentUl.querySelector(':scope > .tree-node > .dir-toggle');
          var parentPath = parentUl.getAttribute('data-path');
          if (parentChildren && parentChildren.classList.contains('hidden')) {
            parentChildren.classList.remove('hidden');
            if (parentToggle) parentToggle.classList.add('open');
            if (parentToggle) parentToggle.setAttribute('aria-expanded','true');
            // Persist that this directory is open
            try { persistDir(parentPath, true); } catch(e){}
          }
        } catch(e){}

        node = parentUl;
      }
    } catch(e) {}

    // Ensure the header 'Open' link uses the mapped route if available
    try {
      var hdrOpen = document.getElementById('open-live-btn-header');
      var selectedRoute = null;
      try { selectedRoute = gintoRouteMap[el.getAttribute('data-path')] || gintoRouteBase[el.getAttribute('data-path') && el.getAttribute('data-path').split('/').pop()]; } catch(e) { selectedRoute = null; }
      if (hdrOpen && selectedRoute) { hdrOpen.href = selectedRoute; }
    } catch(e){}

    // Make sure header select shows files from the current directory (keep UI in-sync)
    try {
      var parentLi = el.closest('li').parentElement.closest('li');
      var parentPath = parentLi ? parentLi.getAttribute('data-path') : null;
      if (parentPath) populateFileSelectForDir(parentPath);
    } catch(e){}

    // Hide any global preview overlay (the large iframe overlay) when a
    // file is clicked so it doesn't keep covering the editor.
    try {
      var globalOverlay = document.getElementById('editor-preview-overlay');
      if (globalOverlay && globalOverlay.style.display !== 'none') {
        var giframe = globalOverlay.querySelector('iframe');
        if (giframe) {
          try { giframe.src = 'about:blank'; giframe.srcdoc = ''; } catch(e){}
        }
        try { globalOverlay.style.display = 'none'; } catch(e){}
      }
    } catch(e) {}

    // update preview area
    // Prefer rendering preview inside the editor workspace so it doesn't
    // visually overflow into the file list. If workspace not found, fall
    // back to placing preview in the sidebar.
    var workspace = document.querySelector('.editor-workspace');
    var preview = null;
    if (workspace) {
      preview = workspace.querySelector('.repo-preview');
      if (!preview) {
        preview = document.createElement('div'); preview.className = 'repo-preview';
        // Insert preview at top of workspace so it sits above the editor content
        workspace.insertBefore(preview, workspace.firstElementChild || null);
      }
    } else {
      preview = container.querySelector('.repo-preview');
      if (!preview) {
        preview = document.createElement('div'); preview.className = 'repo-preview';
        container.appendChild(preview);
      }
    }
    var path = el.getAttribute('data-path') || '';
    var enc = el.getAttribute('data-encoded') || '';
    var html = '<div class="path">' + escapeHtml(path) + '</div>';
    html += '<div class="buttons" style="display:flex;gap:8px;align-items:center">';
    var isEditable = el.getAttribute('data-editable') === '1';
    if (isEditable && enc) html += '<a class="px-3 py-1 bg-blue-500 text-white rounded text-xs" href="/admin/pages/scripts/edit?file=' + enc + '">Open in editor</a>';
    else html += '<span class="px-3 py-1 text-xs text-gray-500 rounded">Not editable</span>';
    // no generic preview endpoint available by default; only show open-in-editor if available
    html += '</div>';
    // Do not show the inline preview for editable files to avoid a
    // small filename/preview text overlapping the editor area. For
    // non-editable files we still show a small preview area.
    if (isEditable && enc) {
      try { preview.innerHTML = ''; preview.style.display = 'none'; } catch(e) {}
    } else {
      try { preview.style.display = ''; preview.innerHTML = html; } catch(e) {}
    }

    // If file is editable, try to load it into the on-page editor (AJAX) so clicking reveals it
    if (isEditable && enc) {
      try {
        // Try to perform an AJAX request to the edit endpoint with ajax=1 which returns JSON
        fetch('/admin/pages/scripts/edit?file=' + enc + '&ajax=1', { headers: { Accept: 'application/json' } })
          .then(function(res){ if (!res.ok) return res.json().then(function(err){ throw err; }); return res.json(); })
          .then(function(data){
            // If there's a textarea (#editor-content) update it
            var ta = document.getElementById('editor-content');
            if (ta && typeof data.content === 'string') {
              ta.value = data.content || '';
              // if Monaco editor present, try to update it
              try {
                if (window.gintoMonacoEditor) {
                  window.gintoMonacoEditor.setValue(ta.value);
                }
              } catch(e){}

              // update the hidden file input and label
              try {
                var fileInput = document.querySelector('input[name="file"]');
                if (fileInput) fileInput.value = data.encoded || enc;
                var labelEl = document.querySelector('#editor-label .text-sm');
                if (labelEl) labelEl.textContent = data.file || labelEl.textContent;
                var openLive = document.getElementById('open-live-btn-header');
                if (openLive && data.live_url) openLive.href = data.live_url;
              } catch(e){}

              // scroll editor textarea to top and focus
              try { ta.scrollTop = 0; ta.selectionStart = ta.selectionEnd = 0; ta.focus(); } catch(e){}
              // Once the file is loaded into the editor, ensure any small
              // inline preview is removed so nothing overlaps the editor.
              try { if (preview) { preview.innerHTML = ''; preview.style.display = 'none'; } } catch(e) {}
              // focus Monaco editor if present
              try { if (window.gintoMonacoEditor && typeof window.gintoMonacoEditor.focus === 'function') window.gintoMonacoEditor.focus(); } catch(e){}
            }
          })
          .catch(function(err){
            // surface a helpful error message in the preview area
            try {
              var message = (err && err.message) ? escapeHtml(err.message) : 'Failed to load file';
              preview.innerHTML = '<div class="path">' + escapeHtml(path) + '</div>' +
                '<div class="text-xs text-red-400 mt-2">' + message + '</div>';
            } catch(e) {}
          });
      } catch(e) {}
    }
  }

  // --- Persistence helpers (module scope) ---
  function persistDir(path, open) {
    try {
      var key = 'ginto.editor.openDirs';
      var dirs = [];
      try { dirs = JSON.parse(localStorage.getItem(key) || '[]'); } catch(e){}
      path = String(path || '');
      if (open) {
        if (!dirs.includes(path)) dirs.push(path);
      } else {
        dirs = dirs.filter(function(p){ return p !== path; });
      }
      localStorage.setItem(key, JSON.stringify(dirs));
    } catch(e){}
  }

  function persistFile(path) {
    try {
      localStorage.setItem('ginto.editor.selectedFile', String(path || ''));
    } catch(e){}
  }

  function restorePersistence() {
    // Restore open directories
    var key = 'ginto.editor.openDirs';
    var dirs = [];
    try { dirs = JSON.parse(localStorage.getItem(key) || '[]'); } catch(e){}
    if (dirs.length) {
      var allDirs = document.querySelectorAll('li[data-path]');
      allDirs.forEach(function(node){
        var path = node.getAttribute('data-path');
        if (dirs.includes(path)) {
          var toggle = node.querySelector(':scope > .tree-node > .dir-toggle');
          var children = node.querySelector(':scope > ul.tree-children');
          if (toggle && children) {
            children.classList.remove('hidden');
            toggle.classList.add('open');
            toggle.setAttribute('aria-expanded','true');
          }
        }
      });
    }
    // Restore selected file
    var sel = localStorage.getItem('ginto.editor.selectedFile');
    if (sel) {
      var fileEl = document.querySelector('.file-item[data-path="' + sel.replace(/"/g,'\\"') + '"]');
      if (fileEl) selectFile(fileEl);
    }
  }

  // Render when tree data is present
  function renderTree(treeRoot) {
    var sidebar = document.querySelector('.editor-left-sidebar');
    if (!sidebar) return;
    // clear placeholder
    var placeholder = sidebar.querySelector('.placeholder'); if (placeholder) placeholder.remove();

    var wrapper = document.createElement('div'); wrapper.className = 'repo-tree';
    var ul = document.createElement('ul');

    // top-level: directories first then files
    var topEntries = Object.entries(treeRoot || {});
    var topDirs = topEntries.filter(function(e){ return e[1] && e[1].type === 'dir'; }).map(function(e){ return e[0]; }).sort();
    var topFiles = topEntries.filter(function(e){ return !e[1] || e[1].type !== 'dir'; }).map(function(e){ return e[0]; }).sort();
    var keys = topDirs.concat(topFiles);

    keys.forEach(function(k){
      var node = treeRoot[k]; node.name = k;
      var li = createNode(node, k);
      if (li) ul.appendChild(li);
    });

    wrapper.appendChild(ul);
    sidebar.appendChild(wrapper);
    // Restore open dirs and selected file
    setTimeout(restorePersistence, 10);
    // Populate the file-select with current file's directory contents if possible
    try {
      var initSel = document.getElementById('file-select');
      if (initSel) {
        // If a file input exists, try to decode the selected value to pick its parent dir
        var fileInput = document.querySelector('input[name="file"]');
        if (fileInput && fileInput.value) {
          // Attempt to find a matching tree node for the decoded token's path
          // We can't decode base64 easily here, but we have a current label in the UI which contains the path
          var headerLabel = document.querySelector('#editor-label .text-sm');
          if (headerLabel && headerLabel.textContent) {
            // headerLabel contains decoded file path; find parent dir
            var cur = headerLabel.textContent || '';
            var idx = cur.lastIndexOf('/');
            var dir = (idx > -1) ? cur.substring(0, idx) : '';
            if (dir) populateFileSelectForDir(dir);
          }
        }
      }
    } catch(e){}
  }

  // Mounting: read global var and render
  function init() {
    try {
      if (window.gintoRepoTree) renderTree(window.gintoRepoTree);
      else {
        // try to find data attribute on sidebar
        var s = document.querySelector('.editor-left-sidebar');
        if (s && s.getAttribute('data-tree')) {
          try { var t = JSON.parse(s.getAttribute('data-tree')); renderTree(t); } catch(e){}
        }
      }
    } catch(e) { console.warn('repo-tree init failed', e); }
  }

  // Helper: find nested node by repo path (e.g. 'src/Views/user')
  function getNodeForPath(path) {
    try {
      if (!path) return null;
      var parts = path.split('/').filter(Boolean);
      var node = window.gintoRepoTree || {};
      for (var i = 0; i < parts.length; i++) {
        var p = parts[i];
        if (!node[p]) return null;
        if (i === parts.length - 1) return node[p];
        node = node[p].children || {};
      }
      return null;
    } catch (e) { return null; }
  }

  // Helper: recursively collect file entries under a node
  function collectFilesUnder(node, base) {
    var out = [];
    try {
      if (!node) return out;
      var children = node.children || node;
      Object.keys(children || {}).forEach(function(k){
        var n = children[k];
        if (!n) return;
        if (n.type === 'file') {
          var rel = (base ? base + '/' + k : k);
          out.push({ rel: rel, encoded: n.encoded, name: k });
        } else if (n.type === 'dir' || n.children) {
          var nextBase = base ? (base + '/' + k) : k;
          out = out.concat(collectFilesUnder(n, nextBase));
        }
      });
    } catch(e){}
    return out;
  }

  // Populate the header file-select with files under dirPath (relative display)
  function populateFileSelectForDir(dirPath, preferred) {
    try {
      if (!dirPath) return;
      var sel = document.getElementById('file-select');
      if (!sel) return;
      var node = getNodeForPath(dirPath);
      if (!node) return;
      var files = collectFilesUnder(node, dirPath);
      // Sort by display path
      files.sort(function(a,b){ return (a.rel || '').localeCompare(b.rel || ''); });
      sel.innerHTML = '';
      var currentEnc = (document.querySelector('input[name="file"]') || {}).value || null;
      var headerLabelText = (document.querySelector('#editor-label .text-sm') || {}).textContent || '';
      var persistedSelected = null;
      try { persistedSelected = localStorage.getItem('ginto.editor.selectedFile') || null; } catch(e) { persistedSelected = null; }
      // Prefer the DOM's current selection (the element we just clicked) so
      // the header select updates immediately on the first click.
      var domSelectedPath = null;
      try { var selEl = document.querySelector('.file-item.selected'); if (selEl) domSelectedPath = selEl.getAttribute('data-path') || null; } catch(e) { domSelectedPath = null; }
      var selectedFound = false;
      files.forEach(function(f){
        // Show the full repo-relative path as the option label so files with
        // duplicate basenames are easier to distinguish in the dropdown.
        var displayFull = f.rel || f.name || '';
        var o = document.createElement('option');
        o.value = f.encoded || '';
        o.textContent = displayFull || o.value;
        // Add a title so the full path is visible on hover in most browsers
        o.title = displayFull || o.value;
        // prefer DOM current selection first (single-click should update select)
        if (domSelectedPath && (domSelectedPath === f.rel || domSelectedPath === f.name || domSelectedPath.split('/').pop() === f.name)) {
          o.selected = true; selectedFound = true;
        }

        // prefer exact encoded match
        if (currentEnc && o.value === currentEnc) {
          o.selected = true; selectedFound = true;
        }
        // fallback: match by header label full path or basename
        else if (!selectedFound && headerLabelText) {
          var headerTrim = headerLabelText.trim();
          if (headerTrim === f.rel || headerTrim === (dirPath + '/' + f.rel) || headerTrim.split('/').pop() === f.name) {
            o.selected = true; selectedFound = true;
          }
        }
        // fallback 2: persisted selected path (localStorage) match
        else if (!selectedFound && persistedSelected) {
          // persistedSelected stores data-path like 'src/Views/user/register.php'
          if (persistedSelected === (f.rel)) { o.selected = true; selectedFound = true; }
          // also try basename match
          try { if (!selectedFound && persistedSelected.split('/').pop() === f.name) { o.selected = true; selectedFound = true; } } catch(e){}
        }
        // Lastly, if we didn't find a match yet, check the DOM-selected path
        if (!selectedFound && domSelectedPath) {
          if (domSelectedPath === f.rel || domSelectedPath === f.name || domSelectedPath.split('/').pop() === f.name) {
            o.selected = true; selectedFound = true;
          }
        }
        sel.appendChild(o);
      });
      // If the caller provided a preferred selection (encoded token or path), try to select it now
      try {
        if (preferred) {
          // prefer encoded token match first
          var opt = null;
          // If preferred looks like an encoded token (contains % or base64-ish), prefer value
          if (preferred.indexOf('%') !== -1 || preferred.length > 10) {
            opt = Array.from(sel.options).find(function(x){ return x.value === preferred; });
          }
          // fallback to matching by path / displayed text
          if (!opt) opt = Array.from(sel.options).find(function(x){ var t = x.textContent || x.title || ''; return t === preferred || t.endsWith('/' + preferred) || t.split('/').pop() === preferred; });
          if (opt) opt.selected = true;
        }
      } catch(e) {}
    } catch(e) {}
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else setTimeout(init,20);
})();
