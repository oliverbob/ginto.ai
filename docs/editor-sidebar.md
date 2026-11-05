# Editor left sidebar (extension point)

This page documents the new empty left sidebar inside the built-in script editor. The sidebar is intentionally simple: an unobtrusive container that extensions and small helper scripts can populate using a tiny runtime API.

Files added:
- `public/assets/css/editor-sidebar.css` — styles for the left sidebar and workspace layout.
- `public/assets/js/editor-sidebar.js` — a small runtime API that exposes `window.gintoEditorSidebar`.

Quick usage:

1. Wait for the page to be ready or register using the API:

```js
window.gintoEditorSidebar.onReady(function(container){
  // container is the sidebar DOM node — append your UI
  var panel = document.createElement('div');
  panel.innerHTML = '<h3>My Extension</h3><p>empty by default</p>';
  container.appendChild(panel);
});
```

2. Alternatively, call `window.gintoEditorSidebar.createPanel(html)` to create and append a small panel.

The left sidebar is responsive: on smaller screens it is hidden by default (extensions may expose a toggle). The CSS tokens follow the editor theme tokens so appearance adapts to light/dark modes.

Repository tree
---------------

The editor now exposes the entire repository file structure inside the left sidebar on the script editor page. The view populates a JS variable called `window.gintoRepoTree` which the renderer script consumes and draws a nested file tree.

- Renderer: `public/assets/js/editor-repo-tree.js` — turns `window.gintoRepoTree` into a navigable file tree in the sidebar.
- Only files under `src/Views/user/` are currently editable from the editor UI. Files under other paths will show a `Not editable` label.

Clicking a file selects it and shows a small preview area with an "Open in editor" button if the file is editable.

Click-to-open in editor
------------------------

If the file is editable (files under `src/Views/user/`), clicking a file in the tree will now load its content directly into the on-page editor (textarea or Monaco) using the app's existing AJAX edit endpoint. This keeps you on the same page and avoids a full reload when switching files.

Resizer (persistent)
---------------------

You can now resize the left sidebar using a vertical resizer between the sidebar and the editor workspace. The width is saved to localStorage and will persist across page loads. Double-click the resizer to toggle collapse and restore (the last width is preserved and restored).

The resizer is implemented in `public/assets/js/editor-sidebar.js` and styled by `public/assets/css/editor-sidebar.css`.
