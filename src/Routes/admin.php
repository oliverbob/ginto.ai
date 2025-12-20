<?php
// Legacy admin routes have been disabled. Use admin_controller_routes.php or admin_prefixed.php instead.
// This file formerly registered closure-based admin routes; it remains for reference but will not execute.
return; // disable route registration from this file
// Admin (CMS) routes - this file is intended to be required by public/index.php
// It uses the $router and $db variables from the including file's scope.

// Pages
$router->get('/pages', function() use ($db) {
    if (class_exists('Ginto\\Controllers\\PagesController')) {
        try {
            $ctrl = new \Ginto\Controllers\PagesController($db);
            if (method_exists($ctrl, 'index')) return $ctrl->index();
        } catch (\Throwable $e) {}
    }
    $viewPath = ROOT_PATH . '/src/Views/admin/pages/index.php';
    if (file_exists($viewPath)) { include $viewPath; exit; }
    http_response_code(404); echo 'Pages list not implemented'; exit;
});
$router->get('/pages/new', function() use ($db) {
    if (class_exists('Ginto\\Controllers\\PagesController')) {
        try {
            $ctrl = new \Ginto\Controllers\PagesController($db);
            if (method_exists($ctrl, 'create')) return $ctrl->create();
        } catch (\Throwable $e) {}
    }
    $viewPath = ROOT_PATH . '/src/Views/admin/pages/new.php';
    if (file_exists($viewPath)) { include $viewPath; exit; }
    http_response_code(404); echo 'Create page view not implemented'; exit;
});
$router->post('/pages', function() use ($db) {
    if (class_exists('Ginto\\Controllers\\PagesController')) {
        try {
            $ctrl = new \Ginto\Controllers\PagesController($db);
            if (method_exists($ctrl, 'store')) return $ctrl->store($_POST);
        } catch (\Throwable $e) {}
    }
    http_response_code(501); echo 'Page create not implemented'; exit;
});
$router->get('/pages/{id}', function($id) use ($db) {
    if (class_exists('Ginto\\Controllers\\PagesController')) {
        try {
            $ctrl = new \Ginto\Controllers\PagesController($db);
            if (method_exists($ctrl, 'show')) return $ctrl->show($id);
        } catch (\Throwable $e) {}
    }
    $viewPath = ROOT_PATH . '/src/Views/admin/pages/show.php';
    if (file_exists($viewPath)) { include $viewPath; exit; }
    http_response_code(404); echo 'Page not found'; exit;
});
$router->get('/pages/{id}/edit', function($id) use ($db) {
    if (class_exists('Ginto\\Controllers\\PagesController')) {
        try {
            $ctrl = new \Ginto\Controllers\PagesController($db);
            if (method_exists($ctrl, 'edit')) return $ctrl->edit($id);
        } catch (\Throwable $e) {}
    }
    $viewPath = ROOT_PATH . '/src/Views/admin/pages/edit.php';
    if (file_exists($viewPath)) { include $viewPath; exit; }
    http_response_code(404); echo 'Edit page view not implemented'; exit;
});
$router->post('/pages/{id}/edit', function($id) use ($db) {
    if (class_exists('Ginto\\Controllers\\PagesController')) {
        try {
            $ctrl = new \Ginto\Controllers\PagesController($db);
            if (method_exists($ctrl, 'update')) return $ctrl->update($id, $_POST);
        } catch (\Throwable $e) {}
    }
    http_response_code(501); echo 'Page update not implemented'; exit;
});

// Posts
$router->get('/posts', function() use ($db) {
    if (class_exists('Ginto\\Controllers\\PostsController')) {
        try { $ctrl = new \Ginto\Controllers\PostsController($db); if (method_exists($ctrl, 'index')) return $ctrl->index(); } catch (\Throwable $e) {}
    }
    $viewPath = ROOT_PATH . '/src/Views/admin/posts/index.php';
    if (file_exists($viewPath)) { include $viewPath; exit; }
    http_response_code(404); echo 'Posts list not implemented'; exit;
});
$router->get('/posts/new', function() use ($db) {
    if (class_exists('Ginto\\Controllers\\PostsController')) {
        try { $ctrl = new \Ginto\Controllers\PostsController($db); if (method_exists($ctrl, 'create')) return $ctrl->create(); } catch (\Throwable $e) {}
    }
    $viewPath = ROOT_PATH . '/src/Views/admin/posts/new.php';
    if (file_exists($viewPath)) { include $viewPath; exit; }
    http_response_code(404); echo 'Create post view not implemented'; exit;
});

// Categories and Tags
$router->get('/categories', function() use ($db) {
    if (class_exists('Ginto\\Controllers\\CategoriesController')) {
        try { $ctrl = new \Ginto\Controllers\CategoriesController($db); if (method_exists($ctrl, 'index')) return $ctrl->index(); } catch (\Throwable $e) {}
    }
    $viewPath = ROOT_PATH . '/src/Views/admin/categories/index.php';
    if (file_exists($viewPath)) { include $viewPath; exit; }
    http_response_code(404); echo 'Categories not implemented'; exit;
});
$router->get('/tags', function() use ($db) {
    if (class_exists('Ginto\\Controllers\\TagsController')) {
        try { $ctrl = new \Ginto\Controllers\TagsController($db); if (method_exists($ctrl, 'index')) return $ctrl->index(); } catch (\Throwable $e) {}
    }
    $viewPath = ROOT_PATH . '/src/Views/admin/tags/index.php';
    if (file_exists($viewPath)) { include $viewPath; exit; }
    http_response_code(404); echo 'Tags not implemented'; exit;
});

// Media
$router->get('/media', function() use ($db) {
    if (class_exists('Ginto\\Controllers\\MediaController')) {
        try { $ctrl = new \Ginto\Controllers\MediaController($db); if (method_exists($ctrl, 'index')) return $ctrl->index(); } catch (\Throwable $e) {}
    }
    $viewPath = ROOT_PATH . '/src/Views/admin/media/index.php';
    if (file_exists($viewPath)) { include $viewPath; exit; }
    http_response_code(404); echo 'Media not implemented'; exit;
});

// Users (admin)
$router->get('/users', function() use ($db) {
    if (class_exists('Ginto\\Controllers\\UsersAdminController')) {
        try { $ctrl = new \Ginto\Controllers\UsersAdminController($db); if (method_exists($ctrl, 'dashboard')) return $ctrl->dashboard(); } catch (\Throwable $e) {}
    }
    $viewPath = ROOT_PATH . '/src/Views/admin/users/index.php';
    if (file_exists($viewPath)) { include $viewPath; exit; }
    http_response_code(404); echo 'Users admin not implemented'; exit;
});
$router->get('/users/{id}', function($id) use ($db) {
    if (class_exists('Ginto\\Controllers\\UsersAdminController')) {
        try { $ctrl = new \Ginto\Controllers\UsersAdminController($db); if (method_exists($ctrl, 'userProfile')) return $ctrl->userProfile($id); } catch (\Throwable $e) {}
    }
    $viewPath = ROOT_PATH . '/src/Views/admin/users/profile.php';
    if (file_exists($viewPath)) { include $viewPath; exit; }
    http_response_code(404); echo 'User profile not implemented'; exit;
});

// Menus, Themes, Plugins, Settings
$router->get('/menus', function() use ($db) {
    if (class_exists('Ginto\\Controllers\\MenusController')) {
        try { $ctrl = new \Ginto\Controllers\MenusController($db); if (method_exists($ctrl, 'index')) return $ctrl->index(); } catch (\Throwable $e) {}
    }
    $viewPath = ROOT_PATH . '/src/Views/admin/menus/index.php';
    if (file_exists($viewPath)) { include $viewPath; exit; }
    http_response_code(404); echo 'Menus not implemented'; exit;
});
$router->get('/themes', function() use ($db) {
    if (class_exists('Ginto\\Controllers\\ThemesController')) {
        try { $ctrl = new \Ginto\Controllers\ThemesController($db); if (method_exists($ctrl, 'index')) return $ctrl->index(); } catch (\Throwable $e) {}
    }
    $viewPath = ROOT_PATH . '/src/Views/admin/themes/index.php';
    if (file_exists($viewPath)) { include $viewPath; exit; }
    http_response_code(404); echo 'Themes not implemented'; exit;
});
$router->get('/plugins', function() use ($db) {
    if (class_exists('Ginto\\Controllers\\PluginsController')) {
        try { $ctrl = new \Ginto\Controllers\PluginsController($db); if (method_exists($ctrl, 'index')) return $ctrl->index(); } catch (\Throwable $e) {}
    }
    $viewPath = ROOT_PATH . '/src/Views/admin/plugins/index.php';
    if (file_exists($viewPath)) { include $viewPath; exit; }
    http_response_code(404); echo 'Plugins not implemented'; exit;
});
$router->get('/settings', function() use ($db) {
    if (class_exists('Ginto\\Controllers\\SettingsController')) {
        try { $ctrl = new \Ginto\Controllers\SettingsController($db); if (method_exists($ctrl, 'index')) return $ctrl->index(); } catch (\Throwable $e) {}
    }
    $viewPath = ROOT_PATH . '/src/Views/admin/settings/index.php';
    if (file_exists($viewPath)) { include $viewPath; exit; }
    http_response_code(404); echo 'Settings not implemented'; exit;
});
