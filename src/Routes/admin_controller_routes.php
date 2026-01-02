<?php
// Controller-style admin routes (to be included within the '/admin' group).
// A compact $req('/path','Controller@method', $methods=null) helper is provided so routes are easy to read.

// This routes file expects a $req($path,'Controller@method',$methods=null) helper
// to be provided by the including script (public/index.php) — it registers only
// route entries below and does not redefine the helper.

// --- Pages -----------------------------------------------------------------
$req('/pages', 'PagesController@index');
$req('/pages/new', 'PagesController@create');
$req('/pages', 'PagesController@store');
// Routes listing for admins
$req('/pages/routes', 'PagesController@routes', ['GET']);
// View-scripts manager (src/Views/user) - register before parameter routes to avoid
// parameterized routes like /pages/{id} capturing "scripts" as an id.
$req('/pages/scripts', 'PagesController@scriptsIndex', ['GET']);
$req('/pages/scripts/edit', 'PagesController@editScript', ['GET']);
$req('/pages/scripts/edit', 'PagesController@saveScript', ['POST']);

// Editor-side chat endpoint for in-editor assistant (POST)
$req('/pages/editor/chat', 'ApiController@editorChat', ['POST']);
// Helper: list MCP server tools for the editor UI
$req('/pages/editor/mcp-tools', 'ApiController@editorMcpTools', ['GET']);
// Proxy endpoint to call MCP tools (POST { tool: string, arguments: object })
$req('/pages/editor/mcp-call', 'ApiController@editorMcpCall', ['POST']);
// Editor-side TTS & STT endpoints
$req('/pages/editor/tts', 'ApiController@editorTts', ['POST']);
$req('/pages/editor/stt', 'ApiController@editorStt', ['POST']);
// Editor-side file operations (Create / Commit) — used by chat UI quick actions
$req('/pages/editor/file', 'ApiController@editorFile', ['POST']);

// Parameter routes for pages (must come after static routes)
$req('/pages/{id}', 'PagesController@show');
$req('/pages/{id}/edit', 'PagesController@edit');
$req('/pages/{id}/edit', 'PagesController@update');

// Posts
$req('/posts', 'PostsController@index');
$req('/posts/new', 'PostsController@create');
$req('/posts', 'PostsController@store');

// Categories & Tags
$req('/categories', 'CategoriesController@index');
$req('/tags', 'TagsController@index');

// Media
$req('/media', 'MediaController@index');

// Users
$req('/users', 'UsersAdminController@dashboard');
$req('/users/{id}', 'UsersAdminController@userProfile');

// Payments (admin approval/management)
$req('/payments', 'PaymentsAdminController@index');
$req('/payments/{id}/approve', 'PaymentsAdminController@approve', ['POST']);
$req('/payments/{id}/reject', 'PaymentsAdminController@reject', ['POST']);

// Sandbox manager for playground sandboxes
$req('/sandboxes', 'UsersAdminController@sandboxes');
$req('/sandboxes/{id}', 'UsersAdminController@sandbox');
$req('/sandboxes/{id}/reset', 'UsersAdminController@resetSandbox', ['POST']);
$req('/sandboxes/{id}/delete', 'UsersAdminController@deleteSandbox', ['POST']);
$req('/sandboxes/{id}/quota', 'UsersAdminController@setQuota', ['POST']);

// Menus, Themes, Plugins, Settings
$req('/menus', 'MenusController@index');
$req('/themes', 'ThemesController@index');
$req('/plugins', 'PluginsController@index');
$req('/settings', 'SettingsController@index');

// Admin activity logs
$req('/logs', 'AdminController@logs');
$req('/logs/{id}', 'AdminController@log');

// API endpoints for icon-color mapping (persisted setting)
$req('/settings/icon-colors', 'SettingsController@getIconColors');
$req('/settings/routes', 'SettingsController@getRoutesSettings');
$req('/settings/save', 'SettingsController@save');

// Network Dashboard (LXD Container Manager)
$req('/network', 'NetworkController@index');
$req('/network/create', 'NetworkController@create', ['POST']);
$req('/network/cleanup', 'NetworkController@cleanup', ['POST']);
$req('/network/api/list', 'NetworkController@apiList', ['GET']);
$req('/network/api/stats', 'NetworkController@apiStats', ['GET']);
$req('/network/api/exec', 'NetworkController@apiExec', ['POST']);
$req('/network/api/network', 'NetworkController@apiNetworkStatus', ['GET']);
$req('/network/api/network/set', 'NetworkController@apiNetworkSet', ['POST']);
$req('/network/{name}', 'NetworkController@show');
$req('/network/{name}/start', 'NetworkController@start', ['POST']);
$req('/network/{name}/stop', 'NetworkController@stop', ['POST']);
$req('/network/{name}/restart', 'NetworkController@restart', ['POST']);
$req('/network/{name}/delete', 'NetworkController@delete', ['POST']);
$req('/network/{name}/exec', 'NetworkController@exec', ['POST']);

// Database Migrations
$req('/migrate', 'MigrationController@status', ['GET']);
$req('/migrate/run', 'MigrationController@index', ['POST']);
