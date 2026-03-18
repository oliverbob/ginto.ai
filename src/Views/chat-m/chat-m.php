<?php
/**
 * Ginto Chat Interface — Mobile WebView Embed (/chat-m)
 *
 * This is a stripped-down version of /chat designed to be embedded inside
 * Android and iOS WebViews. Key differences from the standard /chat view:
 *
 * - No mobile header (no hamburger icon)
 * - No sidebar / drawer
 * - No sidebar or drawer JavaScript
 * - Full-screen chat area with no top/left offsets
 *
 * Shared includes (head, styles, modals, etc.) are reused from chat/includes/.
 * Only main-content and scripts are overridden in this directory.
 */

// Detect sandbox backend (docker or lxd)
$sandboxBackend = \Ginto\Helpers\UnifiedSandbox::getBackend();
?>
<!doctype html>
<html lang="en" class="dark">

<?php include dirname(__DIR__) . '/chat/includes/head.php'; ?>

<body class="bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100 min-h-screen transition-colors duration-200">

  <?php include __DIR__ . '/includes/main-content.php'; ?>

  <!-- Modals -->
  <?php include dirname(__DIR__) . '/chat/includes/modals.php'; ?>
  <?php include dirname(__DIR__) . '/chat/includes/editor-modal.php'; ?>
  <?php include dirname(__DIR__) . '/chat/includes/console-modal.php'; ?>
  <?php include dirname(__DIR__) . '/chat/includes/iframe-modal.php'; ?>
  <?php include dirname(__DIR__) . '/chat/includes/settings-panel.php'; ?>

  <!-- Scripts (sidebar/drawer JS stripped) -->
  <?php include __DIR__ . '/includes/scripts.php'; ?>

</body>
</html>
