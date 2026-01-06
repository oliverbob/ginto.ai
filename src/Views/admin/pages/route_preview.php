<?php
// Admin route preview: show all view files and mark those with active routes
global $ROUTE_REGISTRY;
$viewDir = __DIR__ . '/../../user/';
$baseUrl = '/user/';
$files = scandir($viewDir);

// Collect all route paths for /user/
$userRoutes = [];
if (isset($ROUTE_REGISTRY)) {
    foreach ($ROUTE_REGISTRY as $route) {
        if (strpos($route['path'], '/user/') === 0) {
            $userRoutes[] = $route['path'];
        }
    }
}

function fileToRoute($filename) {
    // Remove .php extension and map to /user/filename
    $name = basename($filename, '.php');
    return '/user/' . $name;
}

echo '<h2>User View Files (with Route Preview)</h2>';
echo '<ul>';
foreach ($files as $file) {
    if ($file === '.' || $file === '..' || is_dir($viewDir . $file)) continue;
    $route = fileToRoute($file);
    $hasRoute = in_array($route, $userRoutes);
    $icon = $hasRoute ? ' <span title="Viewable route" style="color:#4caf50;font-size:1.1em;vertical-align:middle;">👁️</span>' : '';
    echo '<li style="display:flex;align-items:center;gap:4px;">'
        . '<span>' . htmlspecialchars($file) . '</span>'
        . $icon
        . '</li>';
}
echo '</ul>';