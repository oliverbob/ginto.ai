# Editor sticky header (plain HTML/CSS/JS)

This page implements a lightweight floating header for the built-in script editor using a plain HTML/CSS/JS approach. The implementation prioritises safety: no full-file scans, limited backward search windows, and a small registration API for Monaco so the page never exposes the editor instance globally by default.

What it does
- Displays a small, theme-aware header at the top of the editor canvas when the user scrolls. The header shows a short "code context" for the top visible line (e.g., nearest class, function, or the line text itself).
- Works with the textarea fallback (`#editor-content`).
-- Attempts to attach to a Monaco instance when available via a safe registration function: `window.gintoRegisterMonacoEditor(editor)`. This avoids forcing a global editor variable and keeps attachment opt-in.

Files added
- `public/assets/css/editor-sticky.css` — styles for the overlay header and placeholder space.
- `public/assets/js/editor-sticky.js` — the safe textarea-first fallback implementation (bounded searches, throttling).
- `public/assets/js/monaco-sticky.js` — the Monaco-first sticky implementation that attaches via `window.gintoRegisterMonacoEditor(editor)` and uses DocumentSymbol providers when available.

Page integration
- `src/Views/admin/pages/edit_script.php` now includes the sticky header placeholder and loads the CSS/JS files.

How to test locally
1. Start your local dev server and open the script editor page (e.g. `/admin/pages/scripts/edit?file=...`).
2. Scroll the editor textarea — you should see the sticky header appear when there is context to show.
3. Click the small "Go" button in the header: it will focus the editor and reveal the top-visible line (works in textarea and, if Monaco is loaded in the page, it will reveal in Monaco).
4. Switch files using the file selector — header updates based on the newly loaded content.
5. If Monaco is enabled, the page registers the Monaco instance safely (via `window.gintoRegisterMonacoEditor`) so the sticky header can attach and listen to scroll/cursor updates without scanning the whole document.

Usage notes: the page bootstraps Monaco and calls `window.gintoRegisterMonacoEditor(monacoEditor)` after initialization; that function is implemented by `monaco-sticky.js` if present and will create an overlay widget that uses provider data where possible.

Configuration / toggling
------------------------

You can enable or disable this feature at the application level. The project exposes a convenient environment variable and a JSON-style runtime config key to client pages:

- Environment variable (server-side): `EDITOR_STICKY_SCROLL_ENABLED=true|false` (also included in `.env.example`)
- Client-side JSON key: `editor.stickyScroll.enabled` — pages expose this value via `window.GINTO_EDITOR_SETTINGS` so client scripts (Monaco/textarea sticky helpers) can decide whether to attach.

For example, set in your `.env`:

```bash
EDITOR_STICKY_SCROLL_ENABLED=false
```

When disabled, the page will not register the editor for sticky attachments and the sticky helper scripts will not attach to the editor.

Notes / Enhancements
- This is intentionally lightweight and uses simple regex heuristics to find function/class lines. For more accurate symbol detection, integrate the language's DocumentSymbol provider (via LSP or Monaco providers).
- Behaviour for extremely large files is intentionally conservative: the script never splits the entire document, instead it searches in a bounded chunk around the viewport and scans a limited number of lines backwards. For accurate symbol extraction at scale, integrate DocumentSymbol providers from LSP / Monaco.
