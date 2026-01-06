<?php
// Rebuilt Edit view script page — theme-aware, Monaco on-demand, fallback textarea

// Build a repository tree (hierarchical array) for the left sidebar.
// This intentionally serializes file metadata (path, mtime) and an
// encoded token (rawurlencode(base64_encode(path))) for files which
// can be used by existing editor endpoints where applicable.
function _ginto_build_repo_tree($base)
{
  $tree = [];
  try {
    $baseReal = realpath($base);
    if (!$baseReal) return $tree;
    $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($baseReal, \FilesystemIterator::SKIP_DOTS));
    foreach ($rii as $file) {
      // skip .git and big vendor caches to keep tree readable (optional)
      $path = $file->getPathname();
      $rel = substr($path, strlen($baseReal) + 1);
      if ($rel === false) continue;
      // split into parts and build nested nodes
      $parts = explode(DIRECTORY_SEPARATOR, $rel);
      $node =& $tree;
      for ($i = 0; $i < count($parts); $i++) {
        $p = $parts[$i];
        if ($i === count($parts) - 1) {
          if (!isset($node[$p])) {
            if ($file->isDir()) {
              $node[$p] = ['type' => 'dir', 'children' => []];
            } else {
              $normalized = str_replace(DIRECTORY_SEPARATOR, '/', $rel);
              // allow editing of any repository file from the admin editor
              $isEditable = true;
              $node[$p] = [
                'type' => 'file',
                'path' => $normalized,
                'encoded' => rawurlencode(base64_encode($normalized)),
                'editable' => $isEditable,
                'mtime' => $file->getMTime(),
                'size' => $file->getSize()
              ];
            }
          }
        } else {
          if (!isset($node[$p])) $node[$p] = ['type' => 'dir', 'children' => []];
          $node =& $node[$p]['children'];
        }
      }
    }
  } catch (\Throwable $e) {
    // keep quiet on failure — don't break the editor view
    error_log('repo tree build failed: ' . $e->getMessage());
  }
  return $tree;
}

// Build and JSON-encode the repo tree. For very large repositories this
// can be expensive — the output is used to render the left sidebar tree.
$__ginto_repo_tree = [];
try { $__ginto_repo_tree = _ginto_build_repo_tree(ROOT_PATH); } catch (\Throwable $e) { $__ginto_repo_tree = []; }
?>
<?php
// Build a list of routable user view files for sidebar eye icon
global $ROUTE_REGISTRY;
$routableFiles = [];
if (isset($ROUTE_REGISTRY)) {
  // Build more comprehensive file->route map. We try multiple strategies:
  // - /user/<path> -> src/Views/user/<path>.php
  // - /api/user/<last> -> src/Views/user/<last>.php
  // - fallback: match basename against repo tree files
  $fileRouteMap = []; // key: relative file path (src/Views/...), value: route path

  // helper: create a flattened list of files from the repo tree for basename lookup
  $flatFiles = [];
  $iterator = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($__ginto_repo_tree));
  // Because $__ginto_repo_tree is nested associative arrays and not straightforward to iterate,
  // create a simple recursive function instead.
  $flatFiles = [];
  $stack = [ ['node' => $__ginto_repo_tree, 'prefix' => '' ] ];
  while (!empty($stack)) {
    $frame = array_pop($stack);
    $node = $frame['node']; $prefix = $frame['prefix'];
    if (!is_array($node)) continue;
    foreach ($node as $k => $v) {
      if (isset($v['type']) && $v['type'] === 'file') {
        $rel = ($prefix === '') ? $k : ($prefix . '/' . $k);
        $flatFiles[$rel] = $v; // store metadata
      } elseif (is_array($v) && isset($v['children'])) {
        $stack[] = ['node' => $v['children'], 'prefix' => ($prefix === '' ? $k : ($prefix . '/' . $k))];
      } elseif (is_array($v)) {
        // treat as dir
        $stack[] = ['node' => $v, 'prefix' => ($prefix === '' ? $k : ($prefix . '/' . $k))];
      }
    }
  }

  foreach ($ROUTE_REGISTRY as $route) {
    $path = $route['path'] ?? '';
    if (!$path) continue;
    $trim = ltrim($path, '/');
    // strip any route parameters like {ident} for matching
    $cleanPath = preg_replace('#\{[^}]+\}#', '', $trim);
    $cleanPath = trim($cleanPath, '/');

    // 1) /user/ -> check src/Views/user/<rest>.php
    if (str_starts_with($path, '/user/')) {
      // remove the leading 'user/' from cleaned path so we don't duplicate 'user/user'
      $rest = preg_replace('#^user/#', '', $cleanPath);
      $candidate = 'src/Views/user/' . $rest;
      if (is_file(ROOT_PATH . '/' . $candidate . '.php')) {
        $fileRouteMap[$candidate . '.php'] = $path;
        continue;
      }
      // also try without 'user/' prefix (in case route maps to file deeper)
      $candidate2 = 'src/Views/' . $cleanPath;
      if (is_file(ROOT_PATH . '/' . $candidate2 . '.php')) {
        $fileRouteMap[$candidate2 . '.php'] = $path;
        continue;
      }
    }

    // 2) /api/user/<last> -> src/Views/user/<last>.php
    if (str_starts_with($path, '/api/user/')) {
      $parts = explode('/', $trim);
      $last = end($parts);
      if ($last) {
        $candidate = 'src/Views/user/' . $last . '.php';
        if (is_file(ROOT_PATH . '/' . $candidate)) {
          $fileRouteMap[$candidate] = $path;
          continue;
        }
      }
    }

    // 3) try to match by basename across flat files
    $lastSegment = basename($cleanPath);
    if ($lastSegment) {
      foreach ($flatFiles as $rel => $meta) {
        if (basename($rel) === ($lastSegment . '.php')) {
          // prefer more-specific mappings (longer rel path)
          if (!isset($fileRouteMap[$rel]) || strlen($rel) > strlen(array_keys($fileRouteMap)[0] ?? '')) {
            $fileRouteMap[$rel] = $path;
          }
        }
      }
    }
  }

  // expose set of filenames which have routes (for quick eye icon check)
  foreach ($fileRouteMap as $f => $rp) {
    $routableFiles[basename($f)] = true;
  }
}
// ensure $fileRouteMap exists to expose to JS later
if (!isset($fileRouteMap)) $fileRouteMap = [];
?>
<?php $htmlDark = (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark') ? ' class="dark"' : ''; ?>
<!doctype html>
<html lang="en"<?= $htmlDark ?> id="html-root">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="repo-file" content="src/Views/admin/pages/edit_script.php">
  <title>Admin — Edit View Script</title>
  <link rel="stylesheet" href="/assets/css/tailwind.css">
  <!-- Ensure FontAwesome is available so the editor action icons render correctly -->
  <link rel="stylesheet" href="/assets/fontawesome/css/all.min.css">
  <!-- Editor chat styles (moved to separate file) -->
  <link rel="stylesheet" href="/assets/css/editor-chat.css">
  <style>
    /* --- VS Code Theme Tokens --- */
    :root { 
      --vscode-editor-bg: #ffffff; 
      --vscode-foreground: #333333; 
      --vscode-border: #e7e7e7; 
      --vscode-status-bg: #f3f3f3; 
      --vscode-status-fg: #333333; 
      /* chat-specific tokens -- accessible in both themes */
      --editor-chat-placeholder: rgba(0,0,0,0.45);
      --editor-chat-bg: #ffffff;
    }
    :root.dark { 
      --vscode-editor-bg: #1e1e1e; 
      --vscode-foreground: #d4d4d4; 
      --vscode-border: rgba(255,255,255,0.06); 
      /* Use a neutral, low-saturation dark background for the status bar
         in dark mode instead of a vivid blue. This keeps the footer
         visually consistent and subtle against the editor chrome. */
      --vscode-status-bg: #0f1720; /* dark slate-ish */
      --vscode-status-fg: #d4d4d4; /* slightly muted light text for contrast */
      --editor-chat-placeholder: rgba(255,255,255,0.45);
      --editor-chat-bg: rgba(255,255,255,0.02);
    }

    /* --- Core Editor Shell Layout (Strict Flex Column) --- */
    /* This ensures the header/footer stick to top/bottom without using 'position: sticky' */
    .editor-shell {
      display: flex;
      flex-direction: column;
      position: relative;
        /* Default height removed — use a min-height fallback and let JS
          compute a viewport-fitting height at runtime. */
        min-height: 5rem; /* fallback */
      width: 100%;
      background: var(--vscode-editor-bg);
      border: 1px solid var(--vscode-border);
      border-radius: 6px;
      overflow: hidden; 
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    /* Editor-integrated chat panel styles were moved to /assets/css/editor-chat.css */

    /* On small screens use stacked actions to keep layout clean */
    /* small-screen chat-panel rules are in editor-chat.css */

    /* 1. Top Header: Rigid Height (Flex: 0) */
    .editor-shell-top {
      /* allow header height to grow with padding so top == left/right spacing */
      flex: 0 0 auto; /* let content + padding determine size */
      min-height: px; /* preserve minimum height for compact layouts */
      display: flex;
      align-items: center;
      /* add comfortable padding so the left-hand label isn't flush to the edge */
      padding: 8px 12px; /* vertical + horizontal padding for balanced spacing */
      border-bottom: 1px solid var(--vscode-border);
      background: inherit;
      z-index: 20;
      width: 100%;
      box-sizing: border-box;
    }

    /* 2. Middle Canvas: Fills EXACT remaining space (Flex: 1) */
    #editor-canvas {
      flex: 1 1 auto;
      position: relative;
      display: flex;
      flex-direction: column;
      min-height: 0; /* CRITICAL: prevents flex overflow */
      width: 100%;
      background: var(--vscode-editor-bg);
      overflow: hidden;
    }

    /* 3. Footer Status Bar: Rigid Height (Flex: 0) */
    /* VSCode-like status bar: use theme tokens and a segmented layout */
    .status-bar {
      flex: 0 0 30px; /* slightly thinner to match VS Code */
      display: flex;
      align-items: center;
      padding: 0 12px;
      border-top: 1px solid var(--vscode-border);
      background: var(--vscode-status-bg);
      color: var(--vscode-status-fg);
      font-size: 12px;
      width: 100%;
      box-sizing: border-box;
      z-index: 20;
      gap: 12px;
    }

    /* Status segments (left/middle/right) */
    .status-bar > .status-left,
    .status-bar > .status-center,
    .status-bar > .status-right {
      display: inline-flex; align-items: center; gap: 10px; min-height: 100%;
    }

    /* Lightweight token styles similar to VS Code */
    .status-bar .token {
      display: inline-flex; align-items: center; gap: 8px; padding: 6px 10px; border-radius: 4px; cursor: default;
      color: var(--vscode-status-fg); background: transparent; transition: background .08s ease;
      font-size: 12px; line-height: 1; height: 22px; box-sizing: border-box;
    }

    .status-bar .token:hover { background: rgba(0,0,0,0.04); }

    /* Use the border token for separators between tokens (respect theme border token) */
    .status-bar .sep { height: 14px; width: 1px; background: var(--vscode-border); opacity: .6; border-radius:1px; }

    /* Right-aligned area */
    .status-bar .status-right { margin-left: auto; }

    /* --- Inner Controls Alignment --- */
    #editor-label { 
      display: flex; 
      align-items: center; 
      gap: 12px; 
      flex: 1 1 auto; 
      min-width: 0;
      white-space: nowrap;
    }
    .editor-actions {
      display: flex;
      align-items: center;
      gap: 6px;
      margin-left: auto; 
      flex: 0 0 auto;
    }
    .editor-mini-actions {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    /* --- Textarea Styling --- */
    textarea#editor-content {
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, 'Segoe UI', Roboto, monospace;
      font-size: 13px;
      background: transparent;
      color: var(--vscode-foreground);
      border: none;
      resize: none; /* Disable browser resize handle */
      outline: none;
      width: 100%;
      height: 100%; 
      padding: 1rem;
      box-sizing: border-box;
      line-height: 1.5;
    }
    
    /* Scrollbar Polish */
    textarea#editor-content::-webkit-scrollbar { width: 12px; height: 12px; }
    textarea#editor-content::-webkit-scrollbar-thumb { background: rgba(120, 120, 120, 0.2); border-radius: 6px; border: 3px solid transparent; background-clip: content-box; }
    textarea#editor-content::-webkit-scrollbar-thumb:hover { background: rgba(120, 120, 120, 0.4); }

    /* Button Reset */
    .editor-shell button, .editor-shell a { outline: none; }
    .editor-shell button:focus, .editor-shell a:focus { box-shadow: none; }

    /* Header control visuals: remove boxy borders on icon-like buttons and
       keep the 'Save' button visually prominent (green). Non-save controls
       should be subtle icons aligned to the right. */
    .editor-actions > button:not(#save-btn),
    .editor-actions > a:not(#save-btn) {
      border: none !important;
      background: transparent !important;
      color: inherit !important;
      padding: 6px 8px !important;
      display: inline-flex; align-items: center; justify-content:center;
      min-width: 36px; height: 28px; border-radius: 6px;
    }

    /* subtle hover state for compact controls */
    .editor-actions > button:not(#save-btn):hover,
    .editor-actions > a:not(#save-btn):hover { background: rgba(255,255,255,0.03); transform: translateY(-1px); }

    /* --- Mini actions (select + small link actions). Keep file select subtle but make "Open" look like an actual button */
    .editor-mini-actions > select.file-select { padding: 6px 10px; }
    .editor-mini-actions > a#open-live-btn-header {
      border: 1px solid var(--vscode-border) !important;
      background: rgba(255,255,255,0.02) !important;
      padding: 6px 10px !important;
      min-width: 56px; height: 28px; display:inline-flex; align-items:center; justify-content:center;
      border-radius: 6px; color: inherit; text-decoration: none;
    }
    .editor-mini-actions > a#open-live-btn-header:hover { background: rgba(0,0,0,0.04); transform: translateY(-1px); }
    .editor-mini-actions > a#open-live-btn-header:focus { box-shadow: 0 0 0 3px rgba(66,153,225,0.12); outline: none; }

    /* --- FULLSCREEN LOGIC --- */
    /* 
       When fullscreen is active, we make the shell FIXED to the viewport.
       Because the shell is a Flex Column, the header and footer stay pinned
       and the canvas stretches to fill the 100vh.
    */
    .editor-shell:fullscreen,
    .editor-shell:-webkit-full-screen,
    .editor-shell:-moz-full-screen,
    .editor-fullscreen .editor-shell {
      position: fixed !important;
      top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important;
      width: 100vw !important;
      height: 100vh !important;
      z-index: 10040 !important;
      border: none !important;
      border-radius: 0 !important;
      margin: 0 !important;
      max-width: none !important;
    }

    /* Lock body scroll */
    :fullscreen body, :-webkit-full-screen body, .editor-fullscreen body { 
      overflow: hidden !important; 
    }

    /* Ensure children behave in fullscreen */
    .editor-shell:fullscreen .editor-shell-top,
    .editor-fullscreen .editor-shell .editor-shell-top {
      width: 100% !important;
      /* keep a consistent look in fullscreen but allow slightly larger header when needed */
      flex: 0 0 auto !important;
      min-height: 40px !important;
      padding: 12px !important;
    }
    
    .editor-shell:fullscreen #editor-canvas,
    .editor-fullscreen .editor-shell #editor-canvas {
      width: 100% !important;
      flex: 1 1 auto !important; /* Grow to fill screen */
    }

    /* Allow Monaco minimap to show inside the editor shell — keep default sizing handled by Monaco. */
    .editor-shell .minimap, .editor-shell .monaco-editor .minimap { display: block !important; }
  </style>
  <link rel="stylesheet" href="/assets/css/editor-sidebar.css">
  <?php include __DIR__ . '/../parts/favicons.php'; ?>
</head>
<body class="bg-white dark:bg-gray-900 min-h-screen">
  <?php include __DIR__ . '/../parts/sidebar.php'; ?>
  <div id="main-content" class="lg:pl-64">
    <?php include __DIR__ . '/../parts/header.php'; ?>

      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 editor-pane">
        <div class="flex items-center justify-between mb-4 gap-4">
          <div class="flex items-center gap-4">
            <!-- Spacer to maintain layout flow if needed -->
            <div style="width:1px;height:1px;opacity:0"></div>
          </div>
        </div>

        <form method="post" action="/admin/pages/scripts/edit" class="space-y-4" id="edit-script-form">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
          <input type="hidden" name="file" value="<?= htmlspecialchars($encoded ?? '') ?>">

          <!-- Editor Shell -->
          <div class="editor-shell">
            
            <!-- 1. Header (Fixed Height) -->
            <div class="editor-shell-top">
                <div id="editor-label">
                  <div style="display:flex;flex-direction:column;gap:6px;min-width:0;">
                    <!-- Left path indicator removed to reduce redundancy with the dropdown. -->
                    <div style="width:1px;height:1px;opacity:0;" aria-hidden="true"></div>
                    <!-- header snippet removed: small top-of-file preview intentionally disabled -->
                  </div>
                  <div class="editor-mini-actions">
                    <select id="file-select" class="file-select text-xs bg-transparent border border-gray-300 dark:border-gray-600 rounded px-2 py-1 text-gray-800 dark:text-gray-200" title="Jump to file">
                      <?php foreach (($siblings ?? []) as $s): ?>
                        <?php $display = $s['display'] ?? $s['rel'] ?? $s['name'] ?? ''; ?>
                        <option value="<?= htmlspecialchars($s['encoded']) ?>" <?= ($s['rel'] === ($file ?? '')) ? 'selected' : '' ?>><?= htmlspecialchars($display) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <a id="open-live-btn-header" href="<?= htmlspecialchars($previewRoute ?? $live_url ?? '') ?>" target="_blank" class="px-2 py-1 text-xs text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">Open</a>
                    <!-- Preview always renders in light mode inside the editor iframe -->
                    <button id="save-btn" type="submit" class="px-3 py-1 bg-green-600 hover:bg-green-700 text-white rounded text-xs font-semibold shadow-sm">Save</button>
                  </div>
                </div>
                
                <div class="editor-actions">
                  <button id="toggle-editor-btn" type="button" class="px-2 py-1 rounded border border-gray-300 dark:border-gray-600 text-sm bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700" title="Toggle code view">&lt;/&gt;</button>
                  <!-- Preview: Live / Isolated toggle (default: Live) -->
                  <button id="preview-isolation-toggle" type="button" title="Preview mode: Live (click to toggle)" class="px-2 py-1 rounded border border-gray-300 dark:border-gray-600 text-sm bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700" data-mode="live" aria-pressed="false">
                    <i class="fa fa-bolt" aria-hidden="true"></i>
                  </button>
                  <!-- preview eye: show only if routable -->
                  <?php
                  $isRoutable = false;
                  $previewRoute = null;
                  if (!empty($file)) {
                    $base = basename($file);
                    // Find all route paths matching the file base (commissions.php => commissions)
                    $matches = [];
                    if (isset($ROUTE_REGISTRY) && is_array($ROUTE_REGISTRY)) {
                      foreach ($ROUTE_REGISTRY as $route) {
                        $path = $route['path'] ?? '';
                        $last = basename($path);
                        // match last segment against filename without extension
                        if ($last === basename($base, '.php')) {
                          $matches[] = $path;
                        }
                      }
                    }
                    if (!empty($matches)) {
                      $isRoutable = true;
                      // prefer API routes when available (e.g. /api/user/commissions)
                      foreach ($matches as $m) {
                        if (strpos($m, '/api/') === 0) { $previewRoute = $m; break; }
                      }
                      // prefer the /user/ route if no api route
                      if (!$previewRoute) {
                        foreach ($matches as $m) {
                          if (strpos($m, '/user/') === 0) { $previewRoute = $m; break; }
                        }
                      }
                      // fallback to first matching route
                      if (!$previewRoute) $previewRoute = $matches[0];
                    }
                  }
                  ?>
                  <button id="preview-btn" type="button"
                    class="px-2 py-1 rounded border border-gray-300 dark:border-gray-600 text-sm bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700<?php if(!$isRoutable) echo ' opacity-40 cursor-not-allowed'; ?>"
                    title="<?php echo $isRoutable ? 'Preview (opens overlay)' : 'No active route for this file'; ?>"
                    <?php if(!$isRoutable): ?>disabled<?php endif; ?>
                  >
                    <i class="fa fa-eye" aria-hidden="true"></i>
                  </button>
                  <!-- Temporary debug toggle for preview pointer events -->
                  <button id="preview-debug-btn" type="button" title="Toggle preview pointer debug" class="px-2 py-1 rounded border border-gray-300 dark:border-gray-600 text-sm bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700" style="margin-left:8px;">
                    <i class="fa fa-bug" aria-hidden="true"></i>
                  </button>
                  <button id="editor-fullscreen-btn" type="button" title="Toggle fullscreen" class="px-2 py-1 border border-gray-300 dark:border-gray-600 rounded text-sm bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">⤢</button>
                </div>
            </div>

            <!-- 2. Canvas (Fills Remaining Space) -->
            <div id="editor-canvas">
              <div class="editor-status absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 text-xs text-gray-400 italic pointer-events-none z-0">
                <!-- Watermark / Placeholder behind textarea -->
              </div>
              <!-- Editor inner layout: left sidebar + workspace -->
              <div class="editor-inner" role="region" aria-label="Editor inner workspace">
                <div class="editor-left-sidebar" aria-label="Editor left sidebar">
                  <div class="placeholder">Loading file tree…</div>
                </div>

                <div class="editor-workspace">
                  <!-- Monaco editor (created dynamically). If Monaco isn't available we keep the textarea fallback. -->
                  <div id="monaco-editor" style="display:none;width:100%;height:100%;"></div>
                  <textarea id="editor-content" name="content" spellcheck="false" style="height:100%;width:100%;padding:1rem;box-sizing:border-box;"><?= htmlspecialchars($content ?? '') ?></textarea>
                </div>
                <!-- Resizer handle (workspace <-> right pane) -->
                <div id="editor-resizer-right" class="editor-resizer editor-resizer-right" role="separator" aria-orientation="vertical" aria-label="Resize right pane" title="Drag to resize right pane" tabindex="0"></div>
                  <!-- Right-side pane / sidebar (empty by default). Useful for notes, previews, or assistant output. -->
                  <div class="editor-right-pane" id="editor-right-pane" aria-label="Editor right pane" role="region" data-width="320">
                    <!-- Embedded assistant: VS Code-like right pane chat -->
                    <div id="assistant-pane" class="assistant-pane" role="complementary">
                      <div class="assistant-header">
                        <!-- Header: main tabs & controls -->

                        <div class="assistant-title-row">
                          <div class="assistant-tabs">
                            <button class="assistant-tab active" aria-pressed="true">CHAT</button>
                          </div>
                          <div class="assistant-controls" role="toolbar" aria-label="Assistant controls">
                            <button class="as-btn" title="New conversation">＋</button>
                            <button class="as-btn" title="Options">▾</button>
                            <button class="as-btn" title="History">⤺</button>
                            <button class="as-btn" title="Settings">⚙</button>
                            <button class="as-btn" title="More">⋯</button>
                            <div class="as-divider" aria-hidden="true"></div>
                            <button class="as-btn as-compact expand" title="Expand">⧉</button>
                            <button class="as-btn as-close" title="Close pane">✕</button>
                          </div>
                        </div>
                        <div class="assistant-divider"></div>
                      </div>

                      <!-- MCP Tools panel (shows detected MCP tools) -->
                      <div id="mcp_tools_panel" class="mt-2 mx-3 p-3 bg-gray-50 dark:bg-gray-800 rounded border text-sm">
                        <div class="flex items-center justify-between">
                          <div class="font-medium">MCP Tools</div>
                          <div style="display:flex;gap:8px;align-items:center;">
                            <button id="mcp_tools_refresh" type="button" class="text-xs px-2 py-0.5 bg-transparent border border-gray-200 dark:border-gray-700 rounded text-gray-600 dark:text-gray-300">Refresh</button>
                            <div id="mcp_tools_status" class="text-xs text-gray-500">Checking...</div>
                          </div>
                        </div>
                        <ul id="mcp_tools_list" class="mt-2 list-disc list-inside text-sm text-gray-700 dark:text-gray-300"></ul>
                      </div>

                      <div class="assistant-body" id="assistant-body" aria-live="polite">
                        <!-- messages will be mounted here -->
                        <div class="assistant-empty">No conversation yet — ask me about the open file.</div>
                      </div>

                      <div class="assistant-footer">
                        <!-- compact top rows: todos + files-changed (VS Code style) -->
                        <div class="assistant-mini-top">
                          <div class="mini-row todos-row">
                            <button class="mini-toggle" aria-expanded="false" aria-label="Expand section">
                              <svg class="chev" aria-hidden="true" width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg"><polyline points="3,2 8,6 3,10" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" fill="none"></polyline></svg>
                            </button>
                            <div class="mini-label">Todos (3/3)</div>
                            <div style="margin-left:auto;" class="mini-actions">
                              <button class="mini-menu" title="Manage todos">≡</button>
                            </div>
                          </div>
                          <div class="mini-row changes-row">
                            <button class="mini-toggle" aria-expanded="false" aria-label="Expand section">
                              <svg class="chev" aria-hidden="true" width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg"><polyline points="3,2 8,6 3,10" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" fill="none"></polyline></svg>
                            </button>
                            <div class="mini-label">3 files changed <span class="plus">+261</span> <span class="minus">-1953</span></div>
                            <div class="mini-actions">
                              <button class="mini-keep">Keep</button>
                              <button class="mini-undo">Undo</button>
                              <button class="mini-clip" title="Copy">📋</button>
                            </div>
                          </div>
                        </div>

                        <!-- composer with overlayed attached-file and attach button (overlaying the textarea) -->
                        <div class="assistant-composer--vscode" style="position:relative;">
                          <div class="attached-overlay" aria-hidden="false">
                            <div class="attached-file" data-path="src/Views/admin/pages/editor-sidebar.js">
                              <span class="file-tag">JS</span>
                              <span class="file-name">editor-sidebar.js</span>
                            </div>
                            <button id="assistant-attach" class="as-btn as-attach" title="Attach another file">＋</button>
                          </div>

                          <textarea id="assistant-input" class="assistant-input with-overlay" placeholder="Describe what to build next"></textarea>

                          <!-- footer overlay inside the composer (agent, model, tools, small send) -->
                          <div class="assistant-footer-overlay" aria-hidden="false">
                            <div class="assistant-left-tools">
                              <div class="assistant-model-select">
                                <!-- model selection removed (temporarily) -->
                              </div>
                              <div class="assistant-tools">
                                <button class="as-fab" title="Options">⚒</button>
                              </div>
                            </div>
                            <div class="assistant-right-tools">
                              <button class="as-fab" title="Upload to cloud">☁️</button>
                              <button id="assistant-send" class="assistant-send small" aria-label="Send message (Ctrl+Enter)" title="Send message (Ctrl+Enter)" type="button">
                                <svg width="16" height="16" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
                                  <path fill="currentColor" d="M2 21L23 12L2 3v7l13 2-13 2v7z" />
                                </svg>
                              </button>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
              </div>
            </div>

            <!-- 3. Footer (Fixed Height) -->
            <div class="status-bar">
              <div class="status-left">
                <div class="token" id="cursor-token">Ln <span id="line-num">1</span>, Col <span id="col-num">1</span></div>
                <div class="sep" aria-hidden="true"></div>
                <div class="token" id="lang-token">PHP</div>
              </div>
              <div class="status-center">
                <!-- reserved for future mid-status items (encoding, CRLF etc) -->
              </div>
              <div class="status-right text-xs text-gray-500">Script editor</div>
            </div>
          </div>

        </form>

        <?php include __DIR__ . '/../parts/footer.php'; ?>

        <script src="/assets/js/editor-sidebar.js"></script>
        <script>
          // Make repo tree JSON available to the renderer
          window.gintoRepoTree = <?= json_encode($__ginto_repo_tree, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
          // Expose routable files for sidebar eye icon
          window.gintoRoutableFiles = <?= json_encode(array_keys($routableFiles), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
          // Expose detailed mapping from file path -> preferred route (if available)
          window.gintoRouteMap = <?= json_encode($fileRouteMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        </script>
        <script src="/assets/js/editor-repo-tree.js"></script>
        <script>
          (function(){
            // Theme detection and Monaco theme sync
            function getTheme() {
              var theme = (document.cookie.match(/theme=([^;]+)/) || [])[1];
              if (!theme) theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
              return theme;
            }
            function setThemeClassAndMonaco() {
              var html = document.getElementById('html-root');
              var theme = getTheme();
              if (theme === 'dark') html.classList.add('dark');
              else html.classList.remove('dark');
              if (window.monaco && window.monaco.editor) {
                window.monaco.editor.setTheme(theme === 'dark' ? 'vs-dark' : 'vs');
              }
            }
            setThemeClassAndMonaco();
            // Listen for system theme changes
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', setThemeClassAndMonaco);
            // Listen for cookie changes (theme switcher)
            let lastTheme = getTheme();
            setInterval(function() {
              var current = getTheme();
              if (current !== lastTheme) {
                lastTheme = current;
                setThemeClassAndMonaco();
              }
            }, 500);

            function ready(cb){ if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', cb); else cb(); }
            ready(function(){
              const saveBtn = document.getElementById('save-btn');
              const ta = document.getElementById('editor-content');
              // header preview element removed — no top-of-file snippet in header
              const monacoContainer = document.getElementById('monaco-editor');
              let monacoEditor = null;
              let monacoReady = false;
              const fullscreen = document.getElementById('editor-fullscreen-btn');
              const isolationToggle = document.getElementById('preview-isolation-toggle');
              const previewBtn = document.getElementById('preview-btn');
              const fileSelect = document.getElementById('file-select');

              const setFullscreenButtonState = (isOn) => {
                try { fullscreen.setAttribute('aria-pressed', !!isOn); } catch(e){}
                if (isOn) fullscreen.classList.add('bg-gray-200','dark:bg-gray-700'); 
                else fullscreen.classList.remove('bg-gray-200','dark:bg-gray-700');
              };

              // --- File Switching (AJAX) ---
              if (fileSelect) fileSelect.addEventListener('change', async function(){
                const enc = this.value;
                try {
                  const url = '/admin/pages/scripts/edit?file=' + encodeURIComponent(enc) + '&ajax=1';
                  const res = await fetch(url, { headers: { Accept: 'application/json' } });
                  if (!res.ok) throw new Error('network');
                  const data = await res.json();

                  const fileInput = document.querySelector('input[name="file"]');
                  if (fileInput) fileInput.value = data.encoded || enc;

                  const label = document.querySelector('#editor-label .text-sm');
                  if (label) label.textContent = data.file || label.textContent;

                  const openLive = document.getElementById('open-live-btn-header');
                  if (openLive && data.live_url) openLive.href = data.live_url;

                  if (ta && typeof data.content === 'string') {
                    ta.value = data.content;
                    ta.scrollTop = 0; ta.selectionStart = ta.selectionEnd = 0;
                    updateCursorPos();
                    // keep Monaco in sync when switching files
                    if (monacoReady && monacoEditor) {
                      try { monacoEditor.setValue(data.content || ''); } catch(e){}
                    }
                    // ensure Monaco reflows to the new content/size
                    try { if (window.gintoMonacoEditor && typeof window.gintoMonacoEditor.layout === 'function') window.gintoMonacoEditor.layout(); } catch(e) {}
                    // header preview removed — sync only to editor/textarea above
                  }
                  
                  // Reset siblings
                  if (Array.isArray(data.siblings)) {
                    fileSelect.innerHTML = '';
                    data.siblings.forEach(function(s){
                      const o = document.createElement('option');
                      o.value = s.encoded || '';
                      o.textContent = s.display || s.rel || s.name || o.value;
                      if (o.value === enc) o.selected = true;
                      fileSelect.appendChild(o);
                    });
                  }
                  
                  // Hide preview on file change and also hide any debug panel
                  const overlay = document.getElementById('editor-preview-overlay');
                  if (overlay) {
                    overlay.style.display = 'none';
                    // reset potential inherited filters so overlay doesn't get visually altered
                    overlay.style.filter = 'none';
                    overlay.style.mixBlendMode = 'normal';
                  }
                  try {
                    var dbgPanel = document.getElementById('preview-debug-panel');
                    if (dbgPanel) dbgPanel.style.display = 'none';
                  } catch(e){}

                } catch (e) {
                  window.location.href = '/admin/pages/scripts/edit?file=' + enc;
                }
              });

              // --- Preview Logic ---
              function togglePreview() {
                  try {
                    const previewOverlayId = 'editor-preview-overlay';
                    const canvas = document.getElementById('editor-canvas');
                    if (!canvas) return;

                    const content = ta ? ta.value : '';
                    const openLiveBtn = document.getElementById('open-live-btn-header');
                    const liveUrl = openLiveBtn ? openLiveBtn.href : '';
                    const isFS = !!document.fullscreenElement || document.body.classList.contains('editor-fullscreen');
                    // read isolation / live state (default to live)
                    const mode = (isolationToggle && isolationToggle.dataset && isolationToggle.dataset.mode) ? isolationToggle.dataset.mode : 'live';
                    const isLive = mode === 'live';

                    let overlay = document.getElementById(previewOverlayId);
                    
                    // If overlay exists, toggle it
                      if (overlay) {
                      if (overlay.style.display === 'none') {
                         // Update content
                           const f = overlay.querySelector('iframe');
                           // ensure overlay uses a neutral background so dark-mode vars
                           // don't bleed into the preview area
                           overlay.style.background = '#ffffff';
                           // Ensure the iframe can receive pointer events. We'll set
                           // the overlay pointer handling explicitly below when showing.
                           
                           // ensure iframe receives pointer events when present
                          overlay.style.color = '#000';
                          // Ensure overlay allows the iframe to properly receive pointer/touch
                          // events — do not set overlay to none (we want iframe to be interactive).
                          overlay.style.pointerEvents = 'auto';
                          overlay.style.filter = 'none'; // reset any inherited filters that could be used by parent dark-mode
                          overlay.style.mixBlendMode = 'normal';

                           if (f && !f.src) {
                             const previewResetStyles = '<style>' +
                               'html,body{height:100%;margin:0;padding:0;background:#ffffff;color:#000;color-scheme:light;-webkit-text-size-adjust:100%;}' +
                               '*,*::before,*::after{background-color:transparent!important;color:inherit!important;border-color:currentColor!important}' +
                               '::-webkit-scrollbar{width:12px} ::-webkit-scrollbar-thumb{background:linear-gradient(180deg,#6EA6FF,#1E90FF);}' +
                               '</style>';
                              f.srcdoc = previewResetStyles + content;
                              f.style.background = '#ffffff';
                              f.style.color = '#000';
                               f.style.pointerEvents = 'auto';
                               // allow full pointer gestures inside the iframe (drag, pan)
                               f.style.touchAction = 'auto';
                              // Attempt to instrument the iframe's internal document for
                              // same-origin previews so we can capture pointer events.
                              try {
                                f.addEventListener('load', function _ginto_inject_reuse() {
                                  try {
                                    var doc = f.contentDocument; if (!doc) return;
                                    if (doc.__ginto_instrumented) return; doc.__ginto_instrumented = true;
                                    var script = doc.createElement('script');
                                    script.type = 'text/javascript';
                                    script.textContent = '(' + function() {
                                      try {
                                        function send(type, data) { try { window.parent.postMessage({ __ginto_preview: true, type: type, msg: data }, window.location.origin); } catch(e){} }
                                        function fmt(e) { var out = { t: e.type }; try { out.x = e.clientX; out.y = e.clientY; } catch(_){} try { out.buttons = e.buttons; } catch(_){} try { out.pointerId = e.pointerId; } catch(_){} return JSON.stringify(out); }
                                        ['pointerdown','pointerup','pointercancel','mouseenter','mouseleave','wheel'].forEach(function(ev){ window.addEventListener(ev, function(e){ try { if (ev === 'pointerdown' && e.pointerId && e.target && e.target.setPointerCapture) try { e.target.setPointerCapture(e.pointerId); } catch(_){} } catch(_){} send(ev, fmt(e)); }, { passive: true }); });
                                        ['touchstart','touchmove','touchend'].forEach(function(ev){ window.addEventListener(ev, function(e){ try { var t = (e.touches && e.touches[0]) || (e.changedTouches && e.changedTouches[0]) || {}; var d = { t: ev, x: t.clientX || 0, y: t.clientY || 0 }; send(ev, JSON.stringify(d)); } catch(_){} }, { passive: true }); });
                                      } catch(e){}
                                    } + ')();';
                                    try { doc.documentElement.appendChild(script); } catch(e){}
                                  } catch(e) { /* cross-origin or not accessible */ }
                                }, { once: true });
                              } catch(e) {}
                           }

                           // remove any old header bar that may exist in previously-created overlays
                           try {
                             Array.from(overlay.children).forEach(function(ch){
                               if (ch.tagName === 'DIV' && /preview/i.test(ch.textContent || '')) ch.remove();
                             });
                           } catch(e) {}

                           // we deliberately do not add a floating close control — the
                           // preview is toggled using the preview / code buttons.

                           overlay.style.display = 'flex';
                           // Show the debug panel only when the overlay is actually visible.
                           try {
                             if (previewDebugEnabled) {
                               var p = ensurePreviewDebugPanel(); if (p) p.style.display = 'block';
                               // overlay opened — debug attached (no noisy log)
                             }
                           } catch(e){}
                           // Attach debug listeners when re-using overlay
                           try {
                             var dbg = previewDebugEnabled;
                             if (dbg) {
                               // overlay reused — debug attached (noisy log suppressed)
                               ['pointerdown','pointerup','pointercancel','wheel','touchstart','touchmove','touchend'].forEach(function(ev){
                                 overlay.addEventListener(ev, function(e){ appendPreviewDebugLog('[overlay] ' + ev + ' at ' + (e.clientX||0) + ',' + (e.clientY||0)); }, { passive: true });
                               });
                               ['pointerdown','pointerup','wheel','mouseenter','mouseleave'].forEach(function(ev){
                                 f.addEventListener(ev, function(e){ appendPreviewDebugLog('[iframe] ' + ev + ' at ' + (e.clientX||0) + ',' + (e.clientY||0)); }, { passive: true });
                               });
                             }
                           } catch(e) { console.warn('preview debug attach failed', e); }
                           positionOverlay(overlay, isFS);
                           }
                           // if overlay already visible, leave it open — preview button
                           // acts as a simple opener only (close via code-btn)
                      
                      return;
                    }

                    // Create overlay
                    overlay = document.createElement('div'); 
                    overlay.id = previewOverlayId; 
                    overlay.style.display = 'flex'; 
                    overlay.style.flexDirection = 'column'; 
                    // Force a neutral/light background for the preview overlay
                    // so it doesn't visually inherit the admin UI dark-mode vars.
                           overlay.style.background = '#ffffff';
                           overlay.style.color = '#000';
                          // ensure overlay will accept pointer events when visible
                          overlay.style.pointerEvents = 'auto';
                    
                    // Position initially
                    positionOverlay(overlay, isFS);

                    const iframe = document.createElement('iframe');
                    // Add a load handler immediately so that when the iframe's
                    // content becomes available (for same-origin pages or srcdoc)
                    // we can inject our instrumentation script to capture pointer
                    // events inside that document. The injection is a no-op for
                    // cross-origin content because access will throw; we catch
                    // and ignore those errors.
                    try {
                      iframe.addEventListener('load', function _ginto_inject_oncreate() {
                        try {
                          var doc = iframe.contentDocument; if (!doc) return;
                          if (doc.__ginto_instrumented) return; doc.__ginto_instrumented = true;
                          var script = doc.createElement('script');
                          script.type = 'text/javascript';
                          script.textContent = '(' + function() {
                            try {
                              function send(type, data) { try { window.parent.postMessage({ __ginto_preview: true, type: type, msg: data }, window.location.origin); } catch(e){} }
                              function fmt(e) { var out = { t: e.type }; try { out.x = e.clientX; out.y = e.clientY; } catch(_){} try { out.buttons = e.buttons; } catch(_){} try { out.pointerId = e.pointerId; } catch(_){} return JSON.stringify(out); }
                              ['pointerdown','pointerup','pointercancel','mouseenter','mouseleave','wheel'].forEach(function(ev){ window.addEventListener(ev, function(e){ try { if (ev === 'pointerdown' && e.pointerId && e.target && e.target.setPointerCapture) try { e.target.setPointerCapture(e.pointerId); } catch(_){} } catch(_){} send(ev, fmt(e)); }, { passive: true }); });
                              ['touchstart','touchmove','touchend'].forEach(function(ev){ window.addEventListener(ev, function(e){ try { var t = (e.touches && e.touches[0]) || (e.changedTouches && e.changedTouches[0]) || {}; var d = { t: ev, x: t.clientX || 0, y: t.clientY || 0 }; send(ev, JSON.stringify(d)); } catch(_){} }, { passive: true }); });
                            } catch(e){}
                          } + ')();';
                          try { doc.documentElement.appendChild(script); } catch(e){}
                        } catch(e) { /* cross-origin or not accessible */ }
                      }, { once: true });
                    } catch(e) {}
                    if (liveUrl && liveUrl !== '' && liveUrl !== window.location.href) {
                      // Append preview flag so the loaded page knows it's being
                      // rendered inside the editor preview iframe and can force
                      // the selected preview mode independent of the parent UI.
                      // preview mode for liveUrl: use 'isLive' to decide whether to
                      // append preview=light (isolated) or keep the live URL (live)
                        try {
                          if (isLive) {
                            // Live: don't sandbox so iframe behaves like real site
                            try { iframe.removeAttribute('sandbox'); } catch (_) {}
                            try { iframe.removeAttribute('referrerpolicy'); } catch (_) {}
                          } else {
                            // Isolated: sandbox to prevent access to parent and cookies
                            iframe.sandbox = 'allow-scripts';
                            iframe.referrerPolicy = 'no-referrer';
                          }
                          const hasQ = liveUrl.indexOf('?') !== -1;
                          // If isolated, append preview=light to ensure light rendering,
                          // otherwise keep live behavior and do not append preview param.
                          const srcUrl = (!isLive) ? (liveUrl + (hasQ ? '&preview=' + encodeURIComponent('light') : '?preview=' + encodeURIComponent('light'))) : liveUrl;
                          iframe.src = srcUrl;
                        } catch (e) {
                          try { iframe.sandbox = 'allow-scripts'; iframe.referrerPolicy = 'no-referrer'; } catch(_){}
                          iframe.src = liveUrl;
                        }
                    } else {
                      // For srcdoc (editor content) choose sandboxing policy depending on mode
                      if (!isLive) iframe.sandbox = 'allow-scripts';
                      // Ensure the preview (srcdoc) renders using a light theme
                      // and doesn't inherit parent dark-mode styling.
                      const previewResetStyles = '<style>' +
                        'html,body{height:100%;margin:0;padding:0;background:#ffffff;color:#000;color-scheme:light;-webkit-text-size-adjust:100%;}' +
                        '*,*::before,*::after{background-color:transparent!important;color:inherit!important;border-color:currentColor!important}' +
                        '::-webkit-scrollbar{width:12px} ::-webkit-scrollbar-thumb{background:linear-gradient(180deg,#6EA6FF,#1E90FF);}' +
                        '</style>';
                      // default srcdoc uses light reset style; allow mode override
                      iframe.srcdoc = previewResetStyles + content;
                      iframe.style.background = '#ffffff';
                      iframe.style.color = '#000';
                    }
                    iframe.style.width='100%'; iframe.style.height='100%'; iframe.style.border='0'; iframe.style.flex = '1';
                    // Allow the iframe to receive pointer/touch events while keeping
                    // the overlay element itself inert. This prevents the overlay
                    // from capturing drag/touch events and interfering with UI controls.
                    iframe.style.pointerEvents = 'auto';
                    iframe.style.touchAction = 'manipulation';

                    // Remove the prominent header bar (Preview indicator). We
                    // keep the overlay minimal so it doesn't inherit the admin
                    // UI dark-mode styles; the preview is controlled by the
                    // preview toggle button (eye) and code icon.
                    overlay.appendChild(iframe);
                    // If preview debug was enabled, show the panel now that overlay exists
                    try {
                      if (previewDebugEnabled) {
                        var p = ensurePreviewDebugPanel(); if (p) p.style.display = 'block';
                        // overlay created — debug attached (noisy log suppressed)
                      }
                    } catch(e){}

                    // Attach debug listeners (if enabled) so we can inspect
                    // pointer events originating from the overlay/iframe.
                    try {
                      var dbg = previewDebugEnabled;
                      if (dbg) {
                        appendPreviewDebugLog('overlay created — overlay=' + (overlay ? 'present' : 'missing') + ' iframe=' + (iframe ? 'present' : 'missing'));
                        // log pointer events on overlay
                        ['pointerdown','pointerup','pointercancel','wheel','touchstart','touchmove','touchend'].forEach(function(ev){
                          overlay.addEventListener(ev, function(e){ appendPreviewDebugLog('[overlay] ' + ev + ' at ' + (e.clientX||0) + ',' + (e.clientY||0)); }, { passive: true });
                        });
                        // log pointer events on iframe element (element-level events)
                        ['pointerdown','pointerup','wheel','mouseenter','mouseleave'].forEach(function(ev){
                          iframe.addEventListener(ev, function(e){ appendPreviewDebugLog('[iframe] ' + ev + ' at ' + (e.clientX||0) + ',' + (e.clientY||0)); }, { passive: true });
                        });
                        // Log visibility toggle
                        overlay.addEventListener('transitionend', function(){ appendPreviewDebugLog('[overlay] transitionend ' + overlay.style.display + ' pointerEvents=' + overlay.style.pointerEvents); });
                      }
                    } catch(e) { console.warn('preview debug attach failed', e); }

                    // preview button is intentionally static — do not toggle its state
                  } catch (e) { console.warn('preview failed', e); }
              }

              // --- Monaco bootstrap + helpers ---
              // If Monaco is loaded, set its theme to match
              function setMonacoTheme() {
                if (window.monaco && window.monaco.editor) {
                  var theme = (document.cookie.match(/theme=([^;]+)/) || [])[1];
                  if (!theme) theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                  window.monaco.editor.setTheme(theme === 'dark' ? 'vs-dark' : 'vs');
                }
              }
              // Try to set Monaco theme on load and when theme changes
              if (window.monaco) setMonacoTheme();
              window.addEventListener('monacoLoaded', setMonacoTheme);
              window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', setMonacoTheme);
              // header preview removed: we no longer display top-of-file snippets

              // Update cursor position using Monaco when available
              function updateCursorPos() {
                try {
                  const lineEl = document.getElementById('line-num');
                  const colEl = document.getElementById('col-num');
                  if (monacoReady && monacoEditor) {
                    const pos = monacoEditor.getPosition();
                    if (lineEl) lineEl.textContent = pos.lineNumber || 1;
                    if (colEl) colEl.textContent = pos.column || 1;
                    return;
                  }
                } catch(e){}
                try {
                  const pos = ta.selectionStart || 0;
                  const before = ta.value.slice(0, pos);
                  const lines = before.split('\n');
                  const ln = Math.max(1, lines.length);
                  const col = lines.length ? (lines[lines.length-1].length + 1) : 1;
                  if (lineEl) lineEl.textContent = ln;
                  if (colEl) colEl.textContent = col;
                } catch(e){}
              }

              // Try to initialize Monaco; if not present, keep using textarea
              function initMonaco() {
                if (!monacoContainer) return;
                // Prevent double-init
                if (monacoReady || typeof require === 'undefined' && !!window.requireInitAttempt) return;
                // mark we attempted to load
                window.requireInitAttempt = true;

                // load AMD loader if not present
                if (typeof require === 'undefined') {
                  const loader = document.createElement('script');
                  loader.src = '/assets/vendor/monaco-editor/min/vs/loader.js';
                  loader.onload = bootstrapMonaco;
                  loader.onerror = function(){
                    // Local Monaco not available; attempt CDN fallback to keep
                    // the editor functional in environments where vendor files
                    // weren't installed. If CDN fails, gracefully keep the
                    // textarea fallback.
                    try {
                      var cdn = 'https://cdn.jsdelivr.net/npm/monaco-editor@0.39.0/min/vs/loader.js';
                      if (!document.querySelector('script[data-monaco-cdn]')) {
                        var s = document.createElement('script');
                        s.setAttribute('data-monaco-cdn', '1');
                        s.src = cdn;
                        s.onload = bootstrapMonaco;
                        s.onerror = function() { /* leave textarea fallback active */ };
                        document.head.appendChild(s);
                      }
                    } catch(e) { /* ignore — keep textarea */ }
                  };
                  document.head.appendChild(loader);
                } else {
                  bootstrapMonaco();
                }
              }

              function bootstrapMonaco() {
                try {
                  if (typeof require === 'function') {
                    try { require.config({ paths: { vs: '/assets/vendor/monaco-editor/min/vs' } }); } catch(e){}
                    require(['vs/editor/editor.main'], function() {
                      try {
                        monacoEditor = monaco.editor.create(monacoContainer, {
                          value: ta ? ta.value : '',
                          language: 'php',
                          theme: (document.documentElement.classList.contains('dark') ? 'vs-dark' : 'vs'),
                          automaticLayout: true,
                          minimap: { enabled: true, side: 'right', showSlider: 'always' },
                          scrollBeyondLastLine: false,
                          fontSize: 13,
                          wordWrap: 'off',
                          renderLineHighlight: 'all',
                          // Enable Monaco's built-in sticky scroll (header) support when available.
                          // We keep this conservative and also register the editor instance below
                          // for the project's Monaco-first sticky implementation (if present).
                          stickyScroll: {
                            enabled: true,
                            // Keep the sticky header compact and predictable.
                            maxLineCount: 5,
                            outlineModel: 'folding'
                          }
                        });

                        // hide the textarea (use as canonical storage for form submit)
                        try { ta.style.display = 'none'; } catch(e){}
                        try { monacoContainer.style.display = 'block'; } catch(e){}

                        monacoReady = true;

                        // Force an initial layout pass. If the Monaco container was
                        // hidden or had zero height when the editor was created
                        // (common when mounted in a hidden pane), Monaco may not
                        // render until a later resize. Call layout() now and again
                        // shortly after to ensure proper sizing.
                        try {
                          if (monacoEditor && typeof monacoEditor.layout === 'function') {
                            monacoEditor.layout();
                            setTimeout(function(){ try { monacoEditor.layout(); } catch(e){} }, 60);
                          }
                        } catch(e) {}

                        // Expose the instance for the project's sticky helpers. Two helpers
                        // may exist: `gintoRegisterMonacoEditorForEditor` (preferred) or
                        // `gintoRegisterMonacoEditor` (direct). We also stash the instance
                        // on window.gintoMonacoEditor for any late-loading consumers.
                        try { window.gintoMonacoEditor = monacoEditor; } catch(e){}
                        try {
                          if (typeof window.gintoRegisterMonacoEditorForEditor === 'function') {
                            try { window.gintoRegisterMonacoEditorForEditor(monacoEditor); } catch(e){}
                          } else if (typeof window.gintoRegisterMonacoEditor === 'function') {
                            try { window.gintoRegisterMonacoEditor(monacoEditor); } catch(e){}
                          }
                        } catch(e) {}

                        // sync content -> textarea before submit
                        const form = document.getElementById('edit-script-form');
                        if (form) form.addEventListener('submit', function(){
                          try { ta.value = monacoEditor.getValue(); } catch(e) {}
                        });

                        // update header preview and cursor tracking on changes
                        monacoEditor.onDidChangeModelContent(function(){
                          try { const v = monacoEditor.getValue(); ta.value = v; }
                          catch(e){}
                        });

                        monacoEditor.onDidChangeCursorPosition(function(){ updateCursorPos(); });

                        // initial setup: sync textarea value (header preview removed)
                        // Ensure Monaco layout after initial content sync
                        try { if (monacoEditor && typeof monacoEditor.layout === 'function') monacoEditor.layout(); } catch(e) {}

                      } catch(e) {
                        console.warn('monaco init failed', e);
                      }
                    });
                  }
                } catch(e) { console.warn('monaco bootstrap failed', e); }
              }

              // kick off Monaco init non-blocking
              try { initMonaco(); } catch(e) {}

                function positionOverlay(overlay, isFullscreen) {
                  // Prefer to append the overlay to the editor workspace so the
                  // preview remains within the code area and does not cover the
                  // file tree / left sidebar. This keeps the preview visually
                  // bounded to the editor area.
                  const workspace = document.querySelector('.editor-workspace');
                  const canvas = document.getElementById('editor-canvas');
                  const target = workspace || canvas || document.body;
                  if (overlay.parentElement !== target) target.appendChild(overlay);
                  // Ensure the containing element is positioned so absolute
                  // overlay inset:0 works as expected.
                  try { target.style.position = target.style.position || 'relative'; } catch(e) {}
                  overlay.style.position = 'absolute';
                  overlay.style.inset = '0';
                  overlay.style.zIndex = '50';
                  // Confine overflow to the overlay so it doesn't leak outside
                  overlay.style.overflow = 'auto';
                }

              // Attach textarea listeners (cursor/preview) — Monaco will override behaviour when active
              if (ta) {
                ta.addEventListener('keyup', updateCursorPos);
                ta.addEventListener('click', updateCursorPos);
                ta.addEventListener('input', function(){ updateCursorPos(); });
              }

              // --- Fullscreen Toggle ---
              if (fullscreen) fullscreen.addEventListener('click', function(){
                const shell = document.querySelector('.editor-shell');
                // Standard Fullscreen API
                if (!document.fullscreenElement) {
                   if (shell.requestFullscreen) {
                       shell.requestFullscreen().catch(e => {
                           // Fallback to CSS class
                           document.body.classList.toggle('editor-fullscreen');
                           setFullscreenButtonState(document.body.classList.contains('editor-fullscreen'));
                       });
                   } else {
                       document.body.classList.toggle('editor-fullscreen');
                       setFullscreenButtonState(document.body.classList.contains('editor-fullscreen'));
                   }
                } else {
                   if (document.exitFullscreen) document.exitFullscreen();
                }
              });

              // Listen for API changes to sync button state
              document.addEventListener('fullscreenchange', function(){
                  setFullscreenButtonState(!!document.fullscreenElement);
              });

                if (previewBtn) previewBtn.addEventListener('click', togglePreview);
                // preview pointer debug toggle
                var previewDebugBtn = document.getElementById('preview-debug-btn');
                // Start disabled by default; enable explicitly with the debug button
                var previewDebugEnabled = false;
                function setPreviewDebugState(on) {
                  previewDebugEnabled = !!on;
                  try { localStorage.setItem('ginto.preview.debug', previewDebugEnabled ? '1' : '0'); } catch(e){}
                  if (previewDebugBtn) {
                    previewDebugBtn.style.background = previewDebugEnabled ? 'rgba(255,240,200,0.06)' : '';
                    previewDebugBtn.setAttribute('aria-pressed', previewDebugEnabled ? 'true' : 'false');
                  }
                  // show/hide the on-screen debug panel when toggled
                  try {
                      if (previewDebugEnabled) {
                        var overlayEl = document.getElementById('editor-preview-overlay');
                        // If the preview-debug loader hasn't been loaded yet, load it dynamically
                        try {
                          if (!window.__ginto_preview_debug_loaded) {
                            var scr = document.createElement('script');
                            scr.src = '/assets/js/preview-debug.js';
                            scr.async = true;
                            scr.onload = function(){
                              // script loaded — the editor will show the panel when preview opens
                              // When script is loaded, ensure panel is visible if overlay exists
                              try { var p2 = document.getElementById('preview-debug-panel'); if (p2 && overlayEl && overlayEl.style.display && overlayEl.style.display !== 'none') p2.style.display = 'block'; } catch(e){}
                            };
                            (document.body || document.documentElement).appendChild(scr);
                          }
                        } catch(e) {}

                        var p = ensurePreviewDebugPanel();
                        // Only show panel when the overlay is present and visible.
                        if (overlayEl && overlayEl.style.display && overlayEl.style.display !== 'none') {
                          if (p) p.style.display = 'block';
                        } else {
                          // Keep the panel hidden until a preview overlay is opened
                          if (p) p.style.display = 'none';
                          // route lifecycle notices into the in-page panel if present
                          try { appendPreviewDebugLog && appendPreviewDebugLog({ type: 'lifecycle', msg: 'preview debug enabled (panel will be shown when overlay opens)' }); } catch(e){}
                        }
                    } else {
                      var p = document.getElementById('preview-debug-panel'); if (p) p.style.display = 'none';
                    }
                  } catch(e){}
                }
                setPreviewDebugState(previewDebugEnabled);
                if (previewDebugBtn) previewDebugBtn.addEventListener('click', function(){ setPreviewDebugState(!previewDebugEnabled); });
                // Debug UI: on-screen panel for preview pointer logs
                var previewDebugPanel = null;
                function ensurePreviewDebugPanel() {
                  try {
                    // Return cached panel if found
                    if (previewDebugPanel) return previewDebugPanel;
                    // DOM lookup (external script creates the element with this id)
                    var el = document.getElementById('preview-debug-panel');
                    if (el) { previewDebugPanel = el; return previewDebugPanel; }
                    // If the external script exposes an attach/create helper call it
                    if (window.ensurePreviewDebugPanel && typeof window.ensurePreviewDebugPanel === 'function') {
                      try { var p = window.ensurePreviewDebugPanel(); if (p) { previewDebugPanel = p; return p; } } catch(e){}
                    }
                    return null;
                  } catch(e) { return null; }
                }

                function appendPreviewDebugLog(msg) {
                  try {
                    // Prefer external logger if available
                    if (window.appendPreviewDebugLog && typeof window.appendPreviewDebugLog === 'function') {
                      try { window.appendPreviewDebugLog(msg); return; } catch(e){}
                    }
                    // Fallback: do not echo preview logs to the browser console
                    // (the intended target for these messages is the in-page debug panel)
                    try { /* noop */ } catch(e){}
                  } catch(e) { /* ignore */ }
                }

                // Global helper: accept logs sent by other parts of the page (resizer
                // and other top-level modules) via window.__ginto_preview_log when
                // available. This gives us a simple, same-origin direct call path
                // to avoid any postMessage delivery edge cases.
                try {
                  if (!window.__ginto_preview_log) {
                    // forward preview log calls into our local helper which will
                    // attempt to send them to the on-page panel (and not to devtools)
                    window.__ginto_preview_log = function(type, payload) {
                      try { appendPreviewDebugLog && appendPreviewDebugLog({ type: type, payload: payload }); } catch(e){}
                    };
                  }
                } catch(e) {}

                // Receive pointer debug messages from instrumented same-origin previews
                // and forward them into the debug panel. We only accept messages that
                // have the __ginto_preview flag and originate from the same origin.
                // Preview messages (from instrumented iframe) are intentionally ignored now
                // to avoid adding extra UI complexity and reduce surface area for errors.
                window.addEventListener('message', function(ev) {
                  try {
                    var d = ev.data || {};
                    if (!d.__ginto_preview) return; // ignore unrelated messages
                    // Only forward same-origin preview messages into the on-page UI
                    if (ev.origin === window.location.origin) {
                      try { appendPreviewDebugLog && appendPreviewDebugLog(d); } catch(e){}
                    }
                  } catch(e) { /* swallow */ }
                }, false);

                // Toggle between Live and Isolated preview modes
                if (isolationToggle) {
                  isolationToggle.addEventListener('click', function(ev){
                    try {
                      const el = this;
                      const currently = el.dataset.mode || 'live';
                      const next = (currently === 'live') ? 'isolated' : 'live';
                      el.dataset.mode = next;
                      // Update visual cue and title
                      try { el.setAttribute('title', 'Preview mode: ' + (next === 'live' ? 'Live (no sandbox)' : 'Isolated (sandboxed)')); } catch(_){}
                      try { el.setAttribute('aria-pressed', next === 'isolated' ? 'true' : 'false'); } catch(_){}
                      // update icon
                      try {
                        const i = el.querySelector('i');
                        if (i) i.className = (next === 'live') ? 'fa fa-bolt' : 'fa fa-lock';
                      } catch(_){}

                      // If a preview overlay is already open, update the iframe behavior live
                      const overlay = document.getElementById('editor-preview-overlay');
                      if (overlay && overlay.style.display !== 'none') {
                        const f = overlay.querySelector('iframe');
                        if (f) {
                          if (next === 'live') {
                            try { f.removeAttribute('sandbox'); } catch(_){}
                            try { f.removeAttribute('referrerpolicy'); } catch(_){}
                            // remove any preview= param from URL to make it truly live
                            try {
                              const u = new URL(f.src, window.location.href);
                              u.searchParams.delete('preview');
                              u.searchParams.delete('preview_mode');
                              u.searchParams.delete('preview_light');
                              u.searchParams.delete('__preview');
                              f.src = u.toString();
                            } catch(e) {
                              // if parsing fails, leave src as-is
                            }
                          } else {
                            try { f.sandbox = 'allow-scripts'; } catch(_){}
                            try { f.referrerPolicy = 'no-referrer'; } catch(_){}
                            // ensure preview=light exists on URL
                            try {
                              const u = new URL(f.src, window.location.href);
                              if (!u.searchParams.has('preview')) u.searchParams.set('preview','light');
                              f.src = u.toString();
                            } catch(e) {
                              // if parsing fails, don't modify
                            }
                          }
                        }
                      }
                    } catch(e) { /* ignore */ }
                  });
                }

                // --- Responsive sizing: ensure the editor shell fits the
                // available viewport so the footer (status bar) is visible
                // without manual scrolling. Calculate an inline height and
                // reapply on resize / fullscreen / UI changes.
                function recalcEditorShellHeight() {
                  try {
                    const shell = document.querySelector('.editor-shell');
                    if (!shell) return;

                    // Respect fullscreen — allow CSS rules/API to take over
                    const isFullscreen = !!document.fullscreenElement ||
                      document.body.classList.contains('editor-fullscreen');
                    if (isFullscreen) {
                      shell.style.height = '100vh';
                      shell.style.maxHeight = '100vh';
                      return;
                    }

                    const rect = shell.getBoundingClientRect();
                    const topOffset = Math.max(0, rect.top);
                    // keep at least a small margin for breathing room
                    const available = Math.max(320, window.innerHeight - topOffset - 24);

                    shell.style.height = available + 'px';
                    shell.style.maxHeight = 'calc(100vh - ' + topOffset + 'px)';
                    // When the shell size changes, ensure Monaco reflows to the
                    // new container dimensions so it paints correctly outside
                    // of fullscreen toggles.
                    try { if (window.gintoMonacoEditor && typeof window.gintoMonacoEditor.layout === 'function') window.gintoMonacoEditor.layout(); } catch(e) {}
                  } catch (e) { /* ignore */ }
                }

                // Bind events
                window.addEventListener('resize', recalcEditorShellHeight, { passive: true });
                window.addEventListener('orientationchange', recalcEditorShellHeight, { passive: true });
                document.addEventListener('fullscreenchange', recalcEditorShellHeight);

                // Recalc shortly after interactive toggles so layout finishes
                if (document.getElementById('toggle-editor-btn')) {
                  document.getElementById('toggle-editor-btn').addEventListener('click', function(){
                    // If preview overlay is open, hide it when switching to code view
                    try {
                      const overlay = document.getElementById('editor-preview-overlay');
                      if (overlay && overlay.style.display !== 'none') {
                        overlay.style.display = 'none';
                      }
                    } catch(e){}
                    setTimeout(recalcEditorShellHeight, 30);
                  });
                }
                if (previewBtn) previewBtn.addEventListener('click', function(){ setTimeout(recalcEditorShellHeight, 30); });

                // On initial load give the page a beat and compute the proper size
                setTimeout(recalcEditorShellHeight, 25);

                // Watch for changes in the outer layout (sidebar/header toggles)
                try {
                  const main = document.getElementById('main-content');
                  if (main && window.MutationObserver) {
                    const mo = new MutationObserver(function(){ recalcEditorShellHeight(); });
                    mo.observe(main, { attributes: true, childList: true, subtree: true });
                  }
                } catch (e) {}
              });
          })();
        </script>

      </div>
  </div>

                <!-- Editor Assistant will be mounted inside the right pane below. -->
                <!-- The previous floating assistant widget was replaced with a proper embedded pane. -->
        <!-- Preview debug script is loaded only when toggled by the debug button at runtime -->
        <script>
          (function(){
          // Query proxied MCP tools endpoint and render results into the panel
          const statusEl = document.getElementById('mcp_tools_status');
          const listEl = document.getElementById('mcp_tools_list');
          function setStatus(s) { try { if (statusEl) statusEl.textContent = s; } catch(e){} }
          async function loadMcpTools(){
            try {
              // support either input name used across templates
              const csrfInput = document.querySelector('input[name="csrf_token"], input[name="_csrf"], input[name="csrf"]');
              const csrf = csrfInput ? csrfInput.value : '';
              const res = await fetch('/admin/pages/editor/mcp-tools?csrf_token=' + encodeURIComponent(csrf), { credentials: 'same-origin' });
              if (!res.ok) {
                setStatus('Error: ' + res.status);
                if (listEl) listEl.innerHTML = '<li>Failed to load MCP tools (HTTP ' + res.status + ')</li>';
                return;
              }
              const j = await res.json();
              if (!j || !j.success) {
                setStatus('Unavailable');
                if (listEl) listEl.innerHTML = '<li>No MCP tools returned</li>';
                return;
              }
                  let tools = [];
                  if (Array.isArray(j.tools)) tools = j.tools;
                  else if (j.result && Array.isArray(j.result.tools)) tools = j.result.tools;
                  else if (j.raw && typeof j.raw === 'string') {
                    // fallback raw: present as a single informative tool entry
                    tools = [{ name: 'local_registry_fallback', description: 'Fallback discovery output', meta: { raw: j.raw } }];
                  } else if (Array.isArray(j.raw?.result?.tools)) tools = j.raw.result.tools;

                  if (!Array.isArray(tools) || tools.length === 0) {
                    setStatus('No tools');
                    if (listEl) listEl.innerHTML = '<li>No tools registered</li>';
                    return;
                  }
              setStatus(tools.length + ' tool(s)');
              if (listEl) listEl.innerHTML = '';
              for (const t of tools) {
                const name = t.name || t['tool'] || t['id'] || '(unnamed)';
                const desc = t.description || t.summary || (t.meta && t.meta.description) || '';
                const li = document.createElement('li');
                li.className = 'mb-1';
                const label = document.createElement('div');
                label.textContent = name + (desc ? ' — ' + desc : '');
                label.style.cursor = 'pointer';
                label.title = 'Click to view tool details';
                li.appendChild(label);
                const details = document.createElement('pre');
                details.style.display = 'none';
                details.style.whiteSpace = 'pre-wrap';
                details.style.maxHeight = '240px';
                details.style.overflow = 'auto';
                details.style.background = 'rgba(0,0,0,0.03)';
                details.style.padding = '8px';
                details.style.borderRadius = '4px';
                try { details.textContent = JSON.stringify(t, null, 2); } catch (e) { details.textContent = String(t); }
                label.addEventListener('click', () => {
                  details.style.display = details.style.display === 'none' ? 'block' : 'none';
                });
                li.appendChild(details);
                listEl.appendChild(li);
              }
            } catch (e) {
              setStatus('Error');
              if (listEl) listEl.innerHTML = '<li>Failed to fetch MCP tools: ' + (e?.message || e) + '</li>';
            }
          }
          try { document.addEventListener('DOMContentLoaded', loadMcpTools); } catch(e) { loadMcpTools(); }
          try {
            const btn = document.getElementById('mcp_tools_refresh');
            if (btn) btn.addEventListener('click', function(){ setStatus('Refreshing...'); loadMcpTools(); });
          } catch(e){}
          })();
        </script>
        <script src="/assets/js/editor-chat.js"></script>
</body>
</html>