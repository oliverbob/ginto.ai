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

// LXD Container Manager
$req('/lxcs', 'LxcController@index');
$req('/lxcs/create', 'LxcController@create', ['POST']);
$req('/lxcs/cleanup', 'LxcController@cleanup', ['POST']);
$req('/lxcs/api/list', 'LxcController@apiList', ['GET']);
$req('/lxcs/api/stats', 'LxcController@apiStats', ['GET']);
$req('/lxcs/api/exec', 'LxcController@apiExec', ['POST']);
$req('/lxcs/{name}', 'LxcController@show');
$req('/lxcs/{name}/start', 'LxcController@start', ['POST']);
$req('/lxcs/{name}/stop', 'LxcController@stop', ['POST']);
$req('/lxcs/{name}/restart', 'LxcController@restart', ['POST']);
$req('/lxcs/{name}/delete', 'LxcController@delete', ['POST']);
$req('/lxcs/{name}/exec', 'LxcController@exec', ['POST']);

// Database Migrations
$req('/migrate', 'MigrationController@status', ['GET']);
$req('/migrate/run', 'MigrationController@index', ['POST']);
