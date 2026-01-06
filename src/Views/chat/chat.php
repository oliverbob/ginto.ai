<?php
/**
 * Ginto Chat Interface
 * 
 * This is the main chat page that includes all modular components.
 * The page has been refactored from a single 6400+ line file into 
 * smaller, maintainable include files.
 * 
 * Structure:
 * - head.php: Document head, meta tags, global JS config
 * - styles.php: All CSS styles (includes styles-codeblock.php)
 * - mobile-header.php: Mobile fixed header with hamburger menu
 * - sidebar.php: Collapsible navigation sidebar
 * - main-content.php: Main chat area with header and messages
 * - modals.php: Modal dialogs (includes sandbox-wizard-modal.php)
 * - editor-modal.php: Full-screen editor with file browser
 * - console-modal.php: Admin console terminal
 * - settings-panel.php: Settings slide-over panel
 * - scripts.php: All JavaScript (includes multiple sub-scripts)
 */

// Detect sandbox backend (docker or lxd)
$sandboxBackend = \Ginto\Helpers\UnifiedSandbox::getBackend();
?>
<!doctype html>
<html lang="en" class="dark">

<?php include __DIR__ . '/includes/head.php'; ?>

<body class="bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100 min-h-screen transition-colors duration-200">

  <?php include __DIR__ . '/includes/mobile-header.php'; ?>

  <?php include __DIR__ . '/includes/sidebar.php'; ?>

  <?php include __DIR__ . '/includes/main-content.php'; ?>

  <!-- Modals -->
  <?php include __DIR__ . '/includes/modals.php'; ?>
  <?php include __DIR__ . '/includes/editor-modal.php'; ?>
  <?php include __DIR__ . '/includes/console-modal.php'; ?>
  <?php include __DIR__ . '/includes/settings-panel.php'; ?>

  <!-- Scripts -->
  <?php include __DIR__ . '/includes/scripts.php'; ?>

</body>
</html>
