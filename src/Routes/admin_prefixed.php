<?php
// Admin (CMS) routes - absolute '/admin/*' paths so they can be included anywhere.
// It uses the $router and $db variables from the including file's scope.

// Pages
$router->get('/admin/pages', 'PagesController@index');
$router->get('/admin/pages/new', 'PagesController@create');
$router->post('/admin/pages', 'PagesController@store');
$router->get('/admin/pages/{id}', 'PagesController@show');
$router->get('/admin/pages/{id}/edit', 'PagesController@edit');
$router->post('/admin/pages/{id}/edit', 'PagesController@update');

// Posts
$router->get('/admin/posts', 'PostsController@index');
$router->get('/admin/posts/new', 'PostsController@create');

// Categories and Tags
$router->get('/admin/categories', 'CategoriesController@index');
$router->get('/admin/tags', 'TagsController@index');

// Media
$router->get('/admin/media', 'MediaController@index');

// Users (admin)
$router->get('/admin/users', 'UsersAdminController@dashboard');
$router->get('/admin/users/{id}', 'UsersAdminController@userProfile');

// Payments (admin approval/management)
$router->get('/admin/payments', 'PaymentsAdminController@index');
$router->post('/admin/payments/{id}/approve', 'PaymentsAdminController@approve');
$router->post('/admin/payments/{id}/reject', 'PaymentsAdminController@reject');

// Menus, Themes, Plugins, Settings
$router->get('/admin/menus', 'MenusController@index');
$router->get('/admin/themes', 'ThemesController@index');
$router->get('/admin/plugins', 'PluginsController@index');
$router->get('/admin/settings', 'SettingsController@index');
// API: icon color mapping (server-side persistence for the temporary picker)
$router->get('/admin/settings/icon-colors', 'SettingsController@getIconColors');
$router->get('/admin/settings/routes', 'SettingsController@getRoutesSettings');
$router->post('/admin/settings/save', 'SettingsController@save');

// Database Migrations
$router->get('/admin/migrate', 'MigrationController@status');
$router->post('/admin/migrate/run', 'MigrationController@index');
