<?php
// src/Routes/web.php
// Centralized route definitions for Ginto CMS

$router->req('/test', 'TestController@test');
$router->req('/chat/prompts/', 'PromptsController@getPrompts');

use Core\Router;
use Ginto\Helpers\TransactionHelper;

// Ensure $db exists for route closures (some contexts may not have it defined)
if (!isset($db)) {
    try {
        if (class_exists('\Ginto\\Core\\Database')) {
            $db = \Ginto\Core\Database::getInstance();
        } else {
            $db = null;
        }
    } catch (\Throwable $_) {
        $db = null;
    }
}

$router->req('/api/debug/ip-headers', 'DebugController@ipHeaders');
$router->req('/login', 'AuthController@login');
$router->req('/transcribe', 'AudioController@transcribe');
$router->req('/', 'AuthController@index');
$router->req('/user/network-tree', 'UserController@networkTree');
$router->req('/downline', 'AuthController@downline');
$router->req('/logout', 'AuthController@logout');
$router->req('/register', 'AuthController@register');

// Payment routes
$router->req('/bank-payments', 'PaymentController@bankPayments');
$router->req('/gcash-payments', 'PaymentController@gcashPayments');
$router->req('/crypto-payments', 'PaymentController@cryptoPayments');
$router->req('/api/payments/crypto-info', 'PaymentController@cryptoInfo');
$router->req('/api/user/payment-details', 'PaymentController@paymentDetails');
$router->req('/api/payment/check-status/{paymentId}', 'PaymentController@checkStatus');
$router->req('/api/payment/request-review/{paymentId}', 'PaymentController@requestReview');
$router->req('/receipt-image/{filename}', 'PaymentController@receiptImage');

$router->req('/dashboard', 'UserController@dashboard');
$router->req('/user/profile/{ident}', 'UserController@profile');
$router->req('/user/commissions', 'CommissionsController@index');
$router->req('/user/network-tree/compact-view', 'UserController@networkTreeCompact');
$router->req('/user', 'UserController@user');

// Webhooks
$router->req('/webhook', 'WebhookController@webhook');
$router->req('/webhook/status', 'WebhookController@saiCodeCheck');

// Editor routes
$router->req('/editor', 'EditorController@index');
$router->req('/editor/toggle_sandbox', 'EditorController@toggleSandbox', ['POST']);
$router->req('/editor/settings', 'EditorController@settings', ['POST']);
$router->req('/editor/tree', 'EditorController@tree');
$router->req('/editor/create', 'EditorController@create', ['POST']);
$router->req('/editor/rename', 'EditorController@rename', ['POST']);
$router->req('/editor/delete', 'EditorController@delete', ['POST']);
$router->req('/editor/paste', 'EditorController@paste', ['POST']);
$router->req('/editor/save', 'EditorController@save', ['POST']);
$router->req('/editor/file', 'EditorController@file');

// Chat routes
$router->get('/chat', 'ChatController@index');
$router->post('/chat', 'ChatController@stream');
$router->req('/chat/create_sandbox', 'ChatController@createSandbox', ['POST']);
$router->req('/chat/upload-image', 'ChatController@uploadImage', ['POST']);
$router->req('/storage/chat_images/{userId}/{filename}', 'ChatController@serveImage');
$router->req('/storage/generated_images/{userId}/{filename}', 'ChatController@serveGeneratedImage');
$router->req('/chat/conversations', 'ChatController@conversations');
$router->req('/chat/conversations/save', 'ChatController@saveConversation', ['POST']);
$router->req('/chat/conversations/delete', 'ChatController@deleteConversation', ['POST']);
$router->req('/chat/conversations/sync', 'ChatController@syncConversations', ['POST']);

// PandaSearch - Isolated web search test endpoint
$router->get('/pandasearch', 'ChatController@pandaSearchInfo');
$router->post('/pandasearch', 'ChatController@pandaSearch');

// ImageGen - LightPanda-based image generation via Raphael AI
$router->get('/imagegen', 'ChatController@imageGenInfo');
$router->post('/imagegen', 'ChatController@imageGen');
$router->req('/storage/imagegen/{filename}', 'ChatController@serveImageGenFile');

// Sandbox routes
$router->req('/sandbox/image-install-status', 'SandboxController@imageInstallStatus');
$router->req('/sandbox/status', 'SandboxController@status');
$router->req('/sandbox/install', 'SandboxController@install', ['POST']);
$router->req('/sandbox/start', 'SandboxController@start', ['POST']);
$router->req('/sandbox/call', 'SandboxController@call', ['POST']);
$router->req('/sandbox/vnc', 'SandboxController@vnc', ['POST']);
$router->req('/sandbox/destroy', 'SandboxController@destroy', ['POST']);

// LXC binary path helper
function getLxcBin(): ?string {
    static $lxcBin = null;
    static $checked = false;
    
    if (!$checked) {
        $checked = true;
        foreach (['/snap/bin/lxc', '/usr/bin/lxc', '/usr/local/bin/lxc'] as $path) {
            if (file_exists($path) && is_executable($path)) {
                $lxcBin = $path;
                break;
            }
        }
        if (!$lxcBin) {
            $which = trim(shell_exec('which lxc 2>/dev/null') ?? '');
            if (!empty($which) && file_exists($which)) {
                $lxcBin = $which;
            }
        }
    }
    return $lxcBin;
}

// Admin LXC Manager (Proxmox-style)
$router->req('/admin/lxc', 'AdminLxcController@index');
$router->req('/admin/api/lxc/containers', 'AdminLxcController@containers');
$router->req('/admin/api/lxc/containers/{name}/{action}', 'AdminLxcController@containerAction');
$router->req('/admin/api/lxc/containers/{name}', 'AdminLxcController@containerDetails');
$router->req('/admin/api/lxc/images', 'AdminLxcController@images');
$router->req('/admin/api/lxc/images/{fingerprint}', 'AdminLxcController@imageDelete');
$router->req('/admin/api/lxc/images/pull', 'AdminLxcController@imagePull', ['POST']);
$router->req('/admin/api/lxc/storage', 'AdminLxcController@storage');
$router->req('/admin/api/lxc/networks', 'AdminLxcController@networks');
$router->get('/admin/api/lxc/stats', 'AdminLxcController@stats');
$router->req('/admin/api/lxc/prune', 'AdminLxcController@prune', ['POST']);
$router->req('/admin/lxc/vnc/{name}', 'AdminLxcController@vncConnect', ['POST']);

// Client sandbox proxy routes
$router->get('/clients', 'ClientsController@proxyRoot');
$router->get('/clients/{path:.*}', 'ClientsController@proxy');
$router->post('/clients', 'ClientsController@proxyRootPost');
$router->post('/clients/{path:.*}', 'ClientsController@proxy');
$router->get('/sandbox-preview/{sandboxId}/{path:.*}', 'ClientsController@preview');
$router->get('/sandbox-preview/{sandboxId}', 'ClientsController@previewRoot');

$router->req('/rate-limits', 'ApiController@rateLimits');

// Courses
$router->req('/courses', 'CourseController@index');
$router->req('/courses/pricing', 'CourseController@pricing');
$router->req('/courses/{slug}', 'CourseController@detail');
$router->req('/courses/{courseSlug}/lesson/{lessonSlug}', 'CourseController@lesson');
$router->req('/api/courses/complete-lesson', 'CourseController@completeLesson');

// Subscriptions
$router->req('/upgrade', 'SubscriptionController@upgrade');
$router->req('/subscribe', 'SubscriptionController@subscribe');
$router->req('/subscribe/success', 'SubscriptionController@success');
$router->req('/api/subscription/activate', 'SubscriptionController@activate', ['POST']);
$router->req('/api/register/paypal-order', 'PaymentController@paypalOrder');
$router->req('/api/register/paypal-capture', 'PaymentController@paypalCapture');

// Masterclass
$router->req('/masterclass', 'MasterclassController@index');
$router->req('/masterclass/pricing', 'MasterclassController@pricing');
$router->req('/masterclass/{slug}', 'MasterclassController@detail');
$router->req('/masterclass/{masterclassSlug}/lesson/{lessonSlug}', 'MasterclassController@lesson');
$router->req('/api/masterclass/complete-lesson', 'MasterclassController@completeLesson');

$router->req('/websearch', 'ApiController@websearch');

// MCP routes (admin only)
$router->req('/mcp/call', 'McpController@call');
$router->req('/mcp/probe', 'McpController@probe');
$router->req('/mcp/chat', 'McpController@chat');
$router->req('/mcp/invoke', 'McpController@invoke');
$router->req('/mcp/discover', 'McpController@discover');
$router->req('/mcp/unified', 'McpController@unified');

// Debug/API routes
$router->req('/debug/llm', 'DebugController@llm');
$router->req('/api/models', 'ApiController@models');
$router->req('/api/models/set', 'ApiController@modelsSet');
$router->req('/api/provider-keys', 'ApiController@providerKeys');

// Audio routes
$router->req('/audio/tts', 'AudioController@tts', ['POST']);
$router->req('/audio/stt', 'AudioController@stt', ['POST']);

// Playground routes
$router->req('/playground', 'PlaygroundController@index');
$router->req('/playground/logs', 'PlaygroundController@logs');
$router->req('/playground/logs/create-sample', 'PlaygroundController@createSampleLog', ['POST']);
$router->req('/playground/logs/{id}', 'PlaygroundController@logDetail');
$router->req('/playground/editor/install_env', 'PlaygroundController@installEnv', ['POST']);
$router->req('/playground/editor/install_status', 'PlaygroundController@installStatus', ['GET']);
$router->req('/playground/editor/save', 'PlaygroundController@save', ['POST']);
$router->req('/playground/editor/toggle_sandbox', 'PlaygroundController@toggleSandbox', ['POST']);
$router->req('/playground/editor/session_debug', 'PlaygroundController@sessionDebug', ['GET']);
$router->req('/playground/editor/tree', 'PlaygroundController@tree', ['GET']);
$router->req('/playground/editor/create', 'PlaygroundController@create', ['POST']);
$router->req('/playground/editor/rename', 'PlaygroundController@rename', ['POST']);
$router->req('/playground/editor/delete', 'PlaygroundController@delete', ['POST']);
$router->req('/playground/editor/paste', 'PlaygroundController@paste', ['POST']);
$router->req('/playground/console/environment', 'PlaygroundController@consoleEnvironment', ['GET']);
$router->req('/playground/console/exec', 'PlaygroundController@consoleExec', ['POST']);
$router->req('/playground/console/logs', 'PlaygroundController@consoleLogs', ['GET']);
$router->req('/playground/{tool}', 'PlaygroundController@tool'); // Catch-all must be last
