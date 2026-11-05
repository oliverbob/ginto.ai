// Note: file tokens passed in the DOM are produced by PHP as `rawurlencode(base64_encode($path))`.
// This script expects the `data-encoded` attribute to contain the already-encoded token and
// must not call encodeURIComponent() again — doing so will break decoding server-side.
document.addEventListener('DOMContentLoaded', function() {
  const tree = document.getElementById('scripts-tree');
  const preview = document.getElementById('file-preview');
  const menu = document.getElementById('script-context-menu');
  let currentFile = null;

  // Toggle directories
  tree.querySelectorAll('.dir-toggle').forEach(btn => {
    btn.addEventListener('click', function(e) {
      const parent = e.target.closest('.tree-node');
      if (!parent) return;
      const children = parent.querySelector(':scope > .tree-children');
      if (!children) return;
      const opened = !children.classList.contains('hidden');
      if (opened) {
        children.classList.add('hidden');
        btn.textContent = '▸';
      } else {
        children.classList.remove('hidden');
        btn.textContent = '▾';
      }
    });
  });

  // Helpers
  function hideMenu() {
    if (menu) {
      menu.classList.add('hidden');
      menu.style.left = '-9999px';
      menu.style.top = '-9999px';
    }
  }

  function selectFileEl(el) {
    // clear selection
    tree.querySelectorAll('.file-item').forEach(x => x.classList.remove('bg-yellow-50'));
    if (!el) return;
    el.classList.add('bg-yellow-50');
    currentFile = el.getAttribute('data-encoded');
    const path = el.getAttribute('data-path');
    const link = '/admin/pages/scripts/edit?file=' + currentFile;
    preview.innerHTML = '<div class="flex items-center justify-between"><div class="text-sm font-medium">' +
      escapeHtml(path) + '</div><div><a href="' + link + '" class="px-3 py-1 bg-yellow-400 text-white rounded text-sm">Edit</a></div></div>';
  }

  // click file -> select / show preview
  tree.querySelectorAll('.file-item').forEach(el => {
    el.addEventListener('click', function(e) {
      selectFileEl(el);
      hideMenu();
    });

    el.addEventListener('dblclick', function(e) {
      const enc = el.getAttribute('data-encoded');
      window.location.href = '/admin/pages/scripts/edit?file=' + enc;
    });

    el.addEventListener('contextmenu', function(e) {
      e.preventDefault();
      selectFileEl(el);
      // position menu
      const x = e.pageX;
      const y = e.pageY;
      menu.style.left = x + 'px';
      menu.style.top = y + 'px';
      menu.classList.remove('hidden');
      // associate the encoded path on the menu
      menu.setAttribute('data-encoded', el.getAttribute('data-encoded'));
    });
  });

  // menu actions
  menu.addEventListener('click', function(e) {
    const action = e.target.getAttribute('data-action');
    const enc = menu.getAttribute('data-encoded');
    if (action === 'edit' && enc) {
      window.location.href = '/admin/pages/scripts/edit?file=' + enc;
    }
    hideMenu();
  });

  // hide on click outside
  document.addEventListener('click', function(e) {
    if (!menu.contains(e.target)) hideMenu();
  });

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') hideMenu();
  });

  function escapeHtml(unsafe) {
    return unsafe.replace(/[&<>"'`]/g, function(s) { return ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;', '`': '&#96;'
    })[s]; });
  }
});
