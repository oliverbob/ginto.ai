<?php
/**
 * Ginto CMS Installation Backend
 * Handles the installation process via AJAX
 */

// Only show errors for debugging, but not for AJAX requests
if (!isset($_POST['db_type']) && !isset($_POST['step'])) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    // For AJAX requests, log errors but don't display them to prevent JSON corruption
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

// Ensure ROOT_PATH is defined (set by public/index.php, fallback for direct access)
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

// Security check - prevent reinstallation if installation is complete
$envExists = file_exists(ROOT_PATH . '/.env');
$installedMarkerExists = file_exists(ROOT_PATH . '/.installed') || file_exists(dirname(ROOT_PATH) . '/storage/.installed');

// Block installation if .installed marker exists (complete installation)
// Exception: allow the 'get_env_values' action even when installed so the installer UI
// can prefill fields from an existing .env file for convenience.
$requestedAction = $_GET['action'] ?? '';
if ($installedMarkerExists && $requestedAction !== 'get_env_values') {
    // Installation is marked as complete, block access
    http_response_code(403);
    
    // Check if this is an AJAX/API request or a browser request
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    $acceptsJson = isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
    
    if ($isAjax || $acceptsJson || !empty($requestedAction)) {
        // API request - return JSON
        header('Content-Type: application/json');
        die(json_encode(['success' => false, 'message' => 'Ginto AI installation is already complete. The installer is disabled for security.']));
    } else {
        // Browser request - redirect to index.php which shows a nice page
        header('Location: /install/');
        exit;
    }
}

// If .env exists but .installed doesn't, this is a partial/resumed installation - allow it to continue
// No database validation needed since installation isn't complete yet

// Handle both GET and POST requests
$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if this is a form submission (test_database) or JSON (installation steps)
    if ($action === 'test_database') {
        // Handle form-based test database request
        testDatabaseConnection();
    } else {
        // Handle JSON-based installation step requests
        $rawInput = file_get_contents('php://input');
        error_log("Raw input: " . $rawInput);
        
        $input = json_decode($rawInput, true);
        if (!$input) {
            http_response_code(400);
            header('Content-Type: application/json');
            die(json_encode(['success' => false, 'message' => 'Invalid JSON data', 'raw_input' => $rawInput]));
        }
        
        $step = $input['step'] ?? '';
        $data = $input['data'] ?? [];
        
        error_log("Processing step: " . $step);
        error_log("Step data: " . print_r($data, true));
        
        handleInstallationStep($step, $data);
    }
} else {
    // Handle GET requests (legacy)
    error_log("DEBUG: GET request with action: " . $action);
    switch ($action) {
        case 'check_requirements':
            checkSystemRequirements();
            break;
        case 'check_status':
            checkInstallationStatus();
            break;
        case 'test_database':
            testDatabaseConnection();
            break;
        case 'install':
            performInstallation();
            break;
        case 'get_env_values':
            getEnvValues();
            break;
        default:
            http_response_code(400);
            error_log("DEBUG: Invalid action received: " . $action);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}

/**
 * Check installation status - whether .installed marker exists
 */
function checkInstallationStatus() {
    header('Content-Type: application/json');
    
    $envExists = file_exists('../.env');
    $installedMarkerExists = file_exists('../.installed') || file_exists('../../storage/.installed');
    $forceInstall = isset($_GET['force']);
    
    echo json_encode([
        'success' => true,
        'env_exists' => $envExists,
        'installed' => $installedMarkerExists,
        'force_mode' => $forceInstall,
        'can_install' => !$installedMarkerExists || $forceInstall,
        'message' => $installedMarkerExists 
            ? 'Installation is complete. Use ?force=1 to reinstall.' 
            : ($envExists ? 'Partial installation detected. You can resume.' : 'Fresh installation ready.')
    ]);
}

function handleInstallationStep($step, $data) {
    header('Content-Type: application/json');
    
    try {
        switch ($step) {
            case 'create-config':
                $result = createConfigurationFiles($data);
                break;
            case 'setup-database':
                $result = setupDatabaseStep($data);
                break;
            case 'run-migrations':
                $result = runDatabaseMigrations($data);
                break;
            case 'create-admin':
                $result = createAdminAccountStep($data);
                break;
            case 'import-demo':
                $result = importDemoContentStep($data);
                break;
            case 'finalize':
                $result = finalizeInstallationStep($data);
                break;
            default:
                throw new Exception("Unknown installation step: $step");
        }
        
        echo json_encode(['success' => true, 'message' => $result]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function createConfigurationFiles($data) {
    $envPath = ROOT_PATH . '/.env';

    // Load existing .env into a map so we can preserve values and avoid overwriting
    // user-provided or sensitive values. We only add keys that are missing.
    $existing = [];
    if (file_exists($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $l = trim($line);
            if ($l === '' || str_starts_with($l, '#')) continue;
            if (strpos($l, '=') !== false) {
                [$k, $v] = explode('=', $l, 2);
                $existing[trim($k)] = trim($v, '"');
            }
        }
    }

    // Build the desired keys we would like to persist
    $new = generateEnvPairs($data);

    // Keys that should always be updated if the user explicitly provided a new value
    // (passwords and other fields the user may want to change during reinstall)
    $alwaysUpdateIfProvided = [
        'ADMIN_PASSWORD',
        'DEFAULT_PASSWORD',
        'DB_PASS',
        'DB_GUEST_PASSWORD',
    ];

    // Merge, preferring existing values (do NOT overwrite). Only add keys missing from existing env.
    // Exception: password fields should be updated if the user provided a new value.
    $merged = $existing;
    foreach ($new as $k => $v) {
        if (!array_key_exists($k, $merged) || $merged[$k] === '') {
            $merged[$k] = $v;
        } elseif (in_array($k, $alwaysUpdateIfProvided, true) && $v !== '') {
            // Update password fields if user explicitly provided a new value
            $merged[$k] = $v;
        }
    }
    // Quote any value with whitespace (unless already quoted)
    foreach ($merged as $k => $v) {
        if (preg_match('/\s/', $v) && !(str_starts_with($v, '"') && str_ends_with($v, '"'))) {
            $merged[$k] = '"' . addcslashes($v, '"') . '"';
        }
    }

    // Render the merged map back to a .env file; add helpful section comments
    $out = "# Ginto CMS Configuration (auto-generated)\n";
    $out .= "# NOTE: This file stores non-sensitive configuration and identifiers used by the installer.\n";
    $out .= "# Sensitive secrets (DB passwords, API secrets, keys, tokens) are NOT automatically exposed to the installer UI.\n\n";

    // Helper to emit keys for a named section with an explanatory comment
    $emit_section = function(string $title, string $desc, array $keys) use (&$out, $merged) {
        $out .= "# -------------------------------------------------------\n";
        $out .= "# {$title}\n";
        if ($desc) $out .= "# {$desc}\n";
        $out .= "# -------------------------------------------------------\n";
        foreach ($keys as $k) {
            if (array_key_exists($k, $merged)) {
                $out .= $k . '=' . $merged[$k] . "\n";
            }
        }
        $out .= "\n";
    };

    // Site / app section
    $emit_section('Application', 'Basic site settings. Do not store passwords here.', ['APP_NAME','APP_DESCRIPTION','APP_URL','APP_ENV']);

    // Database section
    $emit_section('Database Configuration', 'DB connection details. DB_PASS is intentionally excluded from installer UI for safety.', ['DB_TYPE','DB_FILE','DB_HOST','DB_PORT','DB_NAME','DB_USER','DB_PASS']);

    // Admin & default user identifiers (no passwords written)
    $emit_section('Admin / Default User', 'Identifiers saved for convenience (usernames/emails). Passwords are not stored here.', ['ADMIN_EMAIL','ADMIN_USERNAME','DEFAULT_USERNAME','DEFAULT_EMAIL','ADMIN_PASSWORD','DEFAULT_PASSWORD']);

    // PayPal and Backblaze/Cdn sections — these may include API keys if provided; installer protects exposure where possible
    $emit_section('PayPal Configuration', 'Webhook and API credentials (if present). Avoid storing secrets in public repos.', ['PAYPAL_WEBHOOK_ID','PAYPAL_CLIENT_ID','PAYPAL_CLIENT_SECRET','PAYPAL_ENVIRONMENT','PAYPAL_INTERNAL_API_KEY']);
    $emit_section('Backblaze B2 / CDN', 'Backblaze / CDN configuration used for file uploads/serving.', ['B2_ACCOUNT_ID','B2_APP_KEY','B2_BUCKET_ID','B2_BUCKET_NAME','FILE_CDN_BASE_URL']);

    // MCP / Model Context Protocol section
    $emit_section('MCP / Model Context Protocol', 'MCP server configuration for AI/LLM integration.', ['MCP_SERVER_URL','USE_PY_STT','PYTHON3_PATH']);

    // GROQ API section
    $emit_section('GROQ API Configuration', 'GROQ API for TTS/STT services.', ['GROQ_API_KEY','GROQ_TTS_MODEL','GROQ_STT_MODEL']);

    // LLM Configuration section
    $emit_section('LLM Configuration', 'LLM provider and model settings.', ['LLM_PROVIDER','LLM_MODEL']);

    // Guest DB User section (MCP Clients)
    $emit_section('Guest Database User (MCP Clients)', 'Read-only guest user for MCP client database access.', ['DB_GUEST_USER','DB_GUEST_PASSWORD']);

    // Cerebras API section (Fallback Provider)
    $emit_section('Cerebras API Configuration', 'Cerebras as fallback LLM provider when rate limits are reached.', ['CEREBRAS_API_KEY','CEREBRAS_API_URL']);

    // Local LLM section (llama.cpp fallback)
    $emit_section('Local LLM Configuration', 'Local llama.cpp server as fallback when no cloud API keys are available.', ['LOCAL_LLM_URL','LOCAL_LLM_MODEL','LOCAL_LLM_PRIMARY']);

    // Vision Model section (llama.cpp compatible)
    $emit_section('Vision Model Configuration', 'Local vision model for image understanding (SmolVLM2, llava, etc).', ['VISION_MODEL_URL','VISION_MODEL_NAME','VISION_MAX_TOKENS','ENABLE_VISION']);

    // HuggingFace Models section (for auto-starting llama-server)
    $emit_section('HuggingFace Model Configuration', 'HuggingFace GGUF models for auto-starting llama-server.', ['REASONING_HF_MODEL','VISION_HF_MODEL']);

    // Rate Limiting section
    $emit_section('Rate Limiting Configuration', 'Controls API usage limits per user tier (% of org limit).', ['RATE_LIMIT_ADMIN_PERCENT','RATE_LIMIT_USER_PERCENT','RATE_LIMIT_VISITOR_PERCENT','RATE_LIMIT_FALLBACK_PROVIDER','RATE_LIMIT_FALLBACK_THRESHOLD']);

    // TTS Rate Limiting section
    $emit_section('TTS Rate Limiting Configuration', 'Controls Text-to-Speech usage limits per user role.', ['TTS_LIMIT_ADMIN_HOURLY','TTS_LIMIT_USER_HOURLY','TTS_LIMIT_VISITOR_SESSION','TTS_SILENT_STOP_PERCENT']);

    // Default Provider section
    $emit_section('Default Chat Provider', 'Primary LLM provider for chat. Vision/image requests always use Groq.', ['DEFAULT_PROVIDER','EXPECTED_USERS']);

    // Token Limits section
    $emit_section('Token Limits per User Tier', 'Maximum tokens per response based on user role.', ['MAX_TOKENS_BASE','MAX_TOKENS_ADMIN_PERCENT','MAX_TOKENS_USER_PERCENT','MAX_TOKENS_VISITOR_PERCENT']);

    // Security Settings section
    $emit_section('Security Settings', 'CSRF and visitor access configuration.', ['CSRF_BYPASS']);

    // Experimental Features section
    $emit_section('Experimental Features', 'Beta features that are still in development. Enable at your own risk.', ['ENABLE_DASHBOARD']);

    // Any remaining keys (catch-all)
    $otherKeys = array_diff(array_keys($merged), ['APP_NAME','APP_DESCRIPTION','APP_URL','APP_ENV','DB_TYPE','DB_FILE','DB_HOST','DB_PORT','DB_NAME','DB_USER','DB_PASS','ADMIN_EMAIL','ADMIN_USERNAME','DEFAULT_USERNAME','DEFAULT_EMAIL','ADMIN_PASSWORD','DEFAULT_PASSWORD','PAYPAL_SANDBOX_WEBHOOK_ID','PAYPAL_SANDBOX_CLIENT_ID','PAYPAL_SANDBOX_CLIENT_SECRET','PAYPAL_WEBHOOK_ID','PAYPAL_CLIENT_ID','PAYPAL_CLIENT_SECRET','PAYPAL_ENVIRONMENT','PAYPAL_INTERNAL_API_KEY','B2_ACCOUNT_ID','B2_APP_KEY','B2_BUCKET_ID','B2_BUCKET_NAME','FILE_CDN_BASE_URL','MCP_SERVER_URL','USE_PY_STT','PYTHON3_PATH','GROQ_API_KEY','GROQ_TTS_MODEL','GROQ_STT_MODEL','LLM_PROVIDER','LLM_MODEL','DB_GUEST_USER','DB_GUEST_PASSWORD','CEREBRAS_API_KEY','CEREBRAS_API_URL','LOCAL_LLM_URL','LOCAL_LLM_MODEL','LOCAL_LLM_PRIMARY','VISION_MODEL_URL','VISION_MODEL_NAME','VISION_MAX_TOKENS','ENABLE_VISION','RATE_LIMIT_ADMIN_PERCENT','RATE_LIMIT_USER_PERCENT','RATE_LIMIT_VISITOR_PERCENT','RATE_LIMIT_FALLBACK_PROVIDER','RATE_LIMIT_FALLBACK_THRESHOLD','TTS_LIMIT_ADMIN_HOURLY','TTS_LIMIT_USER_HOURLY','TTS_LIMIT_VISITOR_SESSION','TTS_SILENT_STOP_PERCENT','DEFAULT_PROVIDER','EXPECTED_USERS','MAX_TOKENS_BASE','MAX_TOKENS_ADMIN_PERCENT','MAX_TOKENS_USER_PERCENT','MAX_TOKENS_VISITOR_PERCENT','CSRF_BYPASS','ENABLE_DASHBOARD']);
    if (!empty($otherKeys)) {
        $out .= "# Misc / Other configuration\n";
        foreach ($otherKeys as $k) {
            $out .= $k . '=' . $merged[$k] . "\n";
        }
        $out .= "\n";
    }

    if (file_put_contents($envPath, $out) === false) {
        throw new Exception('Failed to create .env file');
    }

    // Ensure Backblaze B2 keys are present in .env (append if missing)
    $existing = file_exists($envPath) ? file_get_contents($envPath) : '';
    $b2_account = $data['b2_account_id'] ?? '';
    $b2_appkey = $data['b2_app_key'] ?? '';
    $b2_bucket_id = $data['b2_bucket_id'] ?? '';
    $b2_bucket_name = $data['b2_bucket_name'] ?? '';
    $b2_url = $data['file_cdn_base_url'] ?? '';

    if (strpos($existing, 'B2_ACCOUNT_ID=') === false) {
        $b2block = "\n# Backblaze B2 / CDN Configuration\n";
        $b2block .= "B2_ACCOUNT_ID={$b2_account}\n";
        $b2block .= "B2_APP_KEY={$b2_appkey}\n";
        $b2block .= "B2_BUCKET_ID={$b2_bucket_id}\n";
        $b2block .= "B2_BUCKET_NAME={$b2_bucket_name}\n";
        $b2block .= "FILE_CDN_BASE_URL=\"{$b2_url}\"\n";
        file_put_contents($envPath, $b2block, FILE_APPEND);
    }

    // Ensure PayPal keys are present in .env (append if missing)
    if (strpos($existing, 'PAYPAL_CLIENT_ID=') === false && strpos($existing, 'PAYPAL_WEBHOOK_ID=') === false) {
        $paypal_webhook = $data['paypal_webhook_id'] ?? '';
        $paypal_client_id = $data['paypal_client_id'] ?? '';
        $paypal_client_secret = $data['paypal_client_secret'] ?? '';
        $paypal_env = $data['paypal_environment'] ?? 'sandbox';
        $paypal_internal_key = $data['paypal_internal_api_key'] ?? bin2hex(random_bytes(32));

        $ppblock = "\n# PayPal Configuration\n";
        $ppblock .= "# sandbox\n";
        $ppblock .= "# PAYPAL_WEBHOOK_ID=08794558D67807946\n";
        $ppblock .= "# PAYPAL_CLIENT_ID=AbkMmX7_yIDH7NGmHaq-Ku_P0WJ6ganzc8KpYY8l6-WshSEt8B8Mz8KP7v_w86BKgP6b0svRRSy02pdU\n";
        $ppblock .= "# PAYPAL_CLIENT_SECRET=EA7odoi4VD2JSj7ngGuFbve6UOyFK0TL-Q-WxAIOpkBRy2nC5FtfJi-d5t_gKUSWdf2P0v4APXmgj3op\n";
        $ppblock .= "# PAYPAL_ENVIRONMENT=sandbox\n\n";
        $ppblock .= "# live\n";
        $ppblock .= "PAYPAL_WEBHOOK_ID={$paypal_webhook}\n";
        $ppblock .= "PAYPAL_CLIENT_ID={$paypal_client_id}\n";
        $ppblock .= "PAYPAL_CLIENT_SECRET={$paypal_client_secret}\n";
        $ppblock .= "PAYPAL_ENVIRONMENT={$paypal_env}\n";
        $ppblock .= "# Internal API key for cron jobs, webhooks, and internal service calls\n";
        $ppblock .= "PAYPAL_INTERNAL_API_KEY={$paypal_internal_key}\n";

        file_put_contents($envPath, $ppblock, FILE_APPEND);
    }

    return 'Configuration files created successfully';
}

function generateEnvContent($data) {
    $dbType = $data['db_type'] ?? 'sqlite';
    $siteUrl = $data['site_url'] ?? 'http://localhost:8000';
    $siteName = $data['site_name'] ?? 'Ginto CMS';
    $siteDescription = $data['site_description'] ?? '';
    
    $env = "# Ginto CMS Configuration\n";
    $env .= "APP_NAME=\"{$siteName}\"\n";
    $env .= "APP_DESCRIPTION=\"{$siteDescription}\"\n";
    $env .= "APP_URL={$siteUrl}\n";
    $env .= "APP_ENV=production\n\n";
    
    $env .= "# Database Configuration\n";
    $env .= "DB_TYPE={$dbType}\n";
    
    if ($dbType === 'mysql') {
        $dbPass = $data['db_pass'] ?? '';
        error_log("Writing to .env - DB_PASS: " . $dbPass . " (length: " . strlen($dbPass) . ")");
        
        $env .= "DB_HOST=" . ($data['db_host'] ?? 'localhost') . "\n";
        $env .= "DB_PORT=" . ($data['db_port'] ?? '3306') . "\n";
        $env .= "DB_NAME=" . ($data['db_name'] ?? 'ginto') . "\n";
        $env .= "DB_USER=" . ($data['db_user'] ?? '') . "\n";
        $env .= "DB_PASS=" . $dbPass . "\n";
    } else {
        $env .= "DB_FILE=" . ($data['db_file'] ?? 'database.sqlite') . "\n";
    }

    // Optional: include admin/default user identifiers for convenience (do NOT store passwords)
    if (!empty($data['admin_email'])) {
        $env .= "ADMIN_EMAIL=" . $data['admin_email'] . "\n";
    }
    if (!empty($data['admin_username'])) {
        $env .= "ADMIN_USERNAME=" . $data['admin_username'] . "\n";
    }
    if (!empty($data['default_username'])) {
        $env .= "DEFAULT_USERNAME=" . $data['default_username'] . "\n";
    }
    if (!empty($data['default_email'])) {
        $env .= "DEFAULT_EMAIL=" . $data['default_email'] . "\n";
    }
    
    // PayPal configuration - sandbox and live credentials
    $env .= "\n# PayPal Configuration\n";
    
    // Sandbox credentials
    $env .= "# Sandbox Credentials\n";
    $paypal_sandbox_webhook = $data['paypal_sandbox_webhook_id'] ?? '';
    $paypal_sandbox_client_id = $data['paypal_sandbox_client_id'] ?? '';
    $paypal_sandbox_client_secret = $data['paypal_sandbox_client_secret'] ?? '';
    $env .= "PAYPAL_SANDBOX_WEBHOOK_ID={$paypal_sandbox_webhook}\n";
    $env .= "PAYPAL_SANDBOX_CLIENT_ID={$paypal_sandbox_client_id}\n";
    $env .= "PAYPAL_SANDBOX_CLIENT_SECRET={$paypal_sandbox_client_secret}\n";
    
    // Live credentials
    $env .= "\n# Live Credentials\n";
    $paypal_webhook = $data['paypal_webhook_id'] ?? '';
    $paypal_client_id = $data['paypal_client_id'] ?? '';
    $paypal_client_secret = $data['paypal_client_secret'] ?? '';
    $paypal_env = $data['paypal_environment'] ?? 'sandbox';
    $env .= "PAYPAL_WEBHOOK_ID={$paypal_webhook}\n";
    $env .= "PAYPAL_CLIENT_ID={$paypal_client_id}\n";
    $env .= "PAYPAL_CLIENT_SECRET={$paypal_client_secret}\n";
    $env .= "PAYPAL_ENVIRONMENT={$paypal_env}\n";
    $env .= "# Internal API key for cron jobs, webhooks, and internal service calls\n";
    $paypal_internal_key = $data['paypal_internal_api_key'] ?? bin2hex(random_bytes(32));
    $env .= "PAYPAL_INTERNAL_API_KEY=" . $paypal_internal_key . "\n";

    return $env;
}

/**
 * Return env key=>value map for the installer-provided settings.
 * Used by createConfigurationFiles to merge values into an existing .env
 */
function generateEnvPairs($data) {
    $pairs = [];
    $pairs['APP_NAME'] = '"' . ($data['site_name'] ?? 'Ginto CMS') . '"';
    $pairs['APP_DESCRIPTION'] = '"' . ($data['site_description'] ?? '') . '"';
    $pairs['APP_URL'] = $data['site_url'] ?? 'http://localhost:8000';
    $pairs['APP_ENV'] = 'production';

    $dbType = $data['db_type'] ?? 'sqlite';
    $pairs['DB_TYPE'] = $dbType;
    if ($dbType === 'mysql') {
        if (isset($data['db_host'])) $pairs['DB_HOST'] = $data['db_host'];
        if (isset($data['db_port'])) $pairs['DB_PORT'] = $data['db_port'];
        if (isset($data['db_name'])) $pairs['DB_NAME'] = $data['db_name'];
        if (isset($data['db_user'])) $pairs['DB_USER'] = $data['db_user'];
        // DB_PASS is sensitive; allow writing it only if explicitly present and not already set
        if (isset($data['db_pass'])) $pairs['DB_PASS'] = $data['db_pass'];
    } else {
        $pairs['DB_FILE'] = $data['db_file'] ?? 'database.sqlite';
    }

    // Admin / default user identifiers + optional passwords
    if (!empty($data['admin_email'])) $pairs['ADMIN_EMAIL'] = $data['admin_email'];
    if (!empty($data['admin_username'])) $pairs['ADMIN_USERNAME'] = $data['admin_username'];
    if (!empty($data['admin_first_name'])) {
        $pairs['ADMIN_FIRST_NAME'] = (preg_match('/\s/', $data['admin_first_name']) ? '"' . addcslashes($data['admin_first_name'], '"') . '"' : $data['admin_first_name']);
    }
    if (!empty($data['admin_middle_name'])) {
        $pairs['ADMIN_MIDDLE_NAME'] = (preg_match('/\s/', $data['admin_middle_name']) ? '"' . addcslashes($data['admin_middle_name'], '"') . '"' : $data['admin_middle_name']);
    }
    if (!empty($data['admin_last_name'])) {
        $pairs['ADMIN_LAST_NAME'] = (preg_match('/\s/', $data['admin_last_name']) ? '"' . addcslashes($data['admin_last_name'], '"') . '"' : $data['admin_last_name']);
    }
    if (!empty($data['admin_password'])) $pairs['ADMIN_PASSWORD'] = $data['admin_password'];
    if (!empty($data['default_username'])) {
        $pairs['DEFAULT_USERNAME'] = (preg_match('/\s/', $data['default_username']) ? '"' . addcslashes($data['default_username'], '"') . '"' : $data['default_username']);
    }
    if (!empty($data['default_email'])) $pairs['DEFAULT_EMAIL'] = $data['default_email'];
    if (!empty($data['default_password'])) $pairs['DEFAULT_PASSWORD'] = $data['default_password'];

    // PayPal Configuration - Sandbox
    if (!empty($data['paypal_sandbox_webhook_id'])) $pairs['PAYPAL_SANDBOX_WEBHOOK_ID'] = $data['paypal_sandbox_webhook_id'];
    if (!empty($data['paypal_sandbox_client_id'])) $pairs['PAYPAL_SANDBOX_CLIENT_ID'] = $data['paypal_sandbox_client_id'];
    if (!empty($data['paypal_sandbox_client_secret'])) $pairs['PAYPAL_SANDBOX_CLIENT_SECRET'] = $data['paypal_sandbox_client_secret'];
    
    // PayPal Configuration - Live
    if (!empty($data['paypal_webhook_id'])) $pairs['PAYPAL_WEBHOOK_ID'] = $data['paypal_webhook_id'];
    if (!empty($data['paypal_client_id'])) $pairs['PAYPAL_CLIENT_ID'] = $data['paypal_client_id'];
    if (!empty($data['paypal_client_secret'])) $pairs['PAYPAL_CLIENT_SECRET'] = $data['paypal_client_secret'];
    if (!empty($data['paypal_environment'])) $pairs['PAYPAL_ENVIRONMENT'] = $data['paypal_environment'];
    // Internal API key - generate if not provided
    $pairs['PAYPAL_INTERNAL_API_KEY'] = $data['paypal_internal_api_key'] ?? bin2hex(random_bytes(32));

    // MCP / Model Context Protocol Configuration
    if (!empty($data['mcp_server_url'])) $pairs['MCP_SERVER_URL'] = $data['mcp_server_url'];
    if (isset($data['use_py_stt'])) $pairs['USE_PY_STT'] = $data['use_py_stt'];
    if (!empty($data['python3_path'])) $pairs['PYTHON3_PATH'] = $data['python3_path'];

    // GROQ API Configuration
    if (!empty($data['groq_api_key'])) $pairs['GROQ_API_KEY'] = $data['groq_api_key'];
    if (!empty($data['groq_tts_model'])) $pairs['GROQ_TTS_MODEL'] = $data['groq_tts_model'];
    if (!empty($data['groq_stt_model'])) $pairs['GROQ_STT_MODEL'] = $data['groq_stt_model'];

    // LLM Configuration
    if (!empty($data['llm_provider'])) $pairs['LLM_PROVIDER'] = $data['llm_provider'];
    if (!empty($data['llm_model'])) $pairs['LLM_MODEL'] = $data['llm_model'];

    // Guest DB User Configuration (MCP Clients)
    if (!empty($data['db_guest_user'])) $pairs['DB_GUEST_USER'] = $data['db_guest_user'];
    if (!empty($data['db_guest_password'])) $pairs['DB_GUEST_PASSWORD'] = $data['db_guest_password'];

    // Cerebras API Configuration (Fallback Provider)
    if (!empty($data['cerebras_api_key'])) $pairs['CEREBRAS_API_KEY'] = $data['cerebras_api_key'];
    if (!empty($data['cerebras_api_url'])) $pairs['CEREBRAS_API_URL'] = $data['cerebras_api_url'];

    // Local LLM Configuration (llama.cpp fallback)
    if (!empty($data['local_llm_url'])) $pairs['LOCAL_LLM_URL'] = $data['local_llm_url'];
    if (!empty($data['local_llm_model'])) $pairs['LOCAL_LLM_MODEL'] = $data['local_llm_model'];
    if (!empty($data['local_llm_primary'])) $pairs['LOCAL_LLM_PRIMARY'] = $data['local_llm_primary'];

    // Vision Model Configuration (llama.cpp compatible)
    if (!empty($data['vision_model_url'])) $pairs['VISION_MODEL_URL'] = $data['vision_model_url'];
    if (!empty($data['vision_model_name'])) $pairs['VISION_MODEL_NAME'] = $data['vision_model_name'];
    if (isset($data['vision_max_tokens'])) $pairs['VISION_MAX_TOKENS'] = $data['vision_max_tokens'];
    if (!empty($data['enable_vision'])) $pairs['ENABLE_VISION'] = $data['enable_vision'];

    // HuggingFace Model Configuration (for auto-starting llama-server)
    if (!empty($data['reasoning_hf_model'])) $pairs['REASONING_HF_MODEL'] = $data['reasoning_hf_model'];
    if (!empty($data['vision_hf_model'])) $pairs['VISION_HF_MODEL'] = $data['vision_hf_model'];

    // Rate Limiting Configuration
    if (isset($data['rate_limit_admin_percent'])) $pairs['RATE_LIMIT_ADMIN_PERCENT'] = $data['rate_limit_admin_percent'];
    if (isset($data['rate_limit_user_percent'])) $pairs['RATE_LIMIT_USER_PERCENT'] = $data['rate_limit_user_percent'];
    if (isset($data['rate_limit_visitor_percent'])) $pairs['RATE_LIMIT_VISITOR_PERCENT'] = $data['rate_limit_visitor_percent'];
    if (!empty($data['rate_limit_fallback_provider'])) $pairs['RATE_LIMIT_FALLBACK_PROVIDER'] = $data['rate_limit_fallback_provider'];
    if (isset($data['rate_limit_fallback_threshold'])) $pairs['RATE_LIMIT_FALLBACK_THRESHOLD'] = $data['rate_limit_fallback_threshold'];

    // TTS Rate Limiting Configuration
    if (isset($data['tts_limit_admin_hourly'])) $pairs['TTS_LIMIT_ADMIN_HOURLY'] = $data['tts_limit_admin_hourly'];
    if (isset($data['tts_limit_user_hourly'])) $pairs['TTS_LIMIT_USER_HOURLY'] = $data['tts_limit_user_hourly'];
    if (isset($data['tts_limit_visitor_session'])) $pairs['TTS_LIMIT_VISITOR_SESSION'] = $data['tts_limit_visitor_session'];
    if (isset($data['tts_silent_stop_percent'])) $pairs['TTS_SILENT_STOP_PERCENT'] = $data['tts_silent_stop_percent'];

    // Default Provider Configuration
    if (!empty($data['default_provider'])) $pairs['DEFAULT_PROVIDER'] = $data['default_provider'];
    if (isset($data['expected_users'])) $pairs['EXPECTED_USERS'] = $data['expected_users'];

    // Token Limits per User Tier
    if (isset($data['max_tokens_base'])) $pairs['MAX_TOKENS_BASE'] = $data['max_tokens_base'];
    if (isset($data['max_tokens_admin_percent'])) $pairs['MAX_TOKENS_ADMIN_PERCENT'] = $data['max_tokens_admin_percent'];
    if (isset($data['max_tokens_user_percent'])) $pairs['MAX_TOKENS_USER_PERCENT'] = $data['max_tokens_user_percent'];
    if (isset($data['max_tokens_visitor_percent'])) $pairs['MAX_TOKENS_VISITOR_PERCENT'] = $data['max_tokens_visitor_percent'];

    // Security Settings
    if (isset($data['csrf_bypass'])) $pairs['CSRF_BYPASS'] = $data['csrf_bypass'];

    // Experimental Features
    // Dashboard is an unfinished feature for repo invitations - disabled by default
    $pairs['ENABLE_DASHBOARD'] = isset($data['enable_dashboard']) && $data['enable_dashboard'] ? 'true' : 'false';

    return $pairs;
}

function setupDatabaseStep($data) {
    $dbType = $data['db_type'] ?? 'sqlite';
    
    if ($dbType === 'mysql') {
        return setupMySQLDatabase($data);
    } else {
        return setupSQLiteDatabase($data);
    }
}

function setupMySQLDatabase($data) {
    $host = $data['db_host'] ?? 'localhost';
    $port = $data['db_port'] ?? '3306';
    $dbname = $data['db_name'] ?? 'ginto_cms';
    $user = $data['db_user'] ?? '';
    $pass = $data['db_pass'] ?? '';
    
    // Validate required fields
    if (empty($user)) {
        throw new Exception('MySQL username is required');
    }
    
    if (empty($dbname)) {
        throw new Exception('Database name is required');
    }
    
    try {
        // First connect without database to test credentials
        $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Test if we can actually connect
        $pdo->query("SELECT 1");
        
        // Create database if it doesn't exist (PRESERVE existing data!)
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        
        // DO NOT drop the database - this would destroy all user data!
        // The migration system will handle schema updates safely
        
        // Test connection to the database
        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
        $testPdo = new PDO($dsn, $user, $pass);
        $testPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $testPdo->query("SELECT 1");
        
        return "MySQL database '{$dbname}' verified successfully (existing data preserved)";
        
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Access denied') !== false) {
            throw new Exception("Access denied: Invalid MySQL username or password");
        } elseif (strpos($e->getMessage(), 'Connection refused') !== false) {
            throw new Exception("Connection refused: Cannot connect to MySQL server at {$host}:{$port}");
        } elseif (strpos($e->getMessage(), 'Unknown database') !== false) {
            throw new Exception("Cannot create database '{$dbname}': Check permissions");
        } else {
            throw new Exception("MySQL connection failed: " . $e->getMessage());
        }
    }
}

function setupSQLiteDatabase($data) {
    $dbFile = $data['db_file'] ?? 'database.sqlite';
    $dbPath = '../' . $dbFile;
    
    // Create the database file if it doesn't exist
    $pdo = new PDO("sqlite:{$dbPath}");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    return "SQLite database '{$dbFile}' created successfully";
}

function runDatabaseMigrations($data) {
    // Load environment to get database config
    $envFile = '../.env';
    if (!file_exists($envFile)) {
        throw new Exception('.env file not found');
    }
    
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $config = [];
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            [$key, $value] = explode('=', $line, 2);
            $config[trim($key)] = trim($value, '"');
        }
    }
    
    $dbType = $config['DB_TYPE'] ?? 'sqlite';
    
    try {
        if ($dbType === 'mysql') {
            // First check if database exists by connecting without specifying dbname
            $testDsn = "mysql:host={$config['DB_HOST']};port={$config['DB_PORT']};charset=utf8mb4";
            $testPdo = new PDO($testDsn, $config['DB_USER'], $config['DB_PASS']);
            $testPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Check if database exists
            $stmt = $testPdo->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?");
            $stmt->execute([$config['DB_NAME']]);
            
            if (!$stmt->fetch()) {
                throw new Exception("Database '{$config['DB_NAME']}' does not exist. Please complete the database setup step first.");
            }
            
            // Now connect to the specific database
            $dsn = "mysql:host={$config['DB_HOST']};port={$config['DB_PORT']};dbname={$config['DB_NAME']};charset=utf8mb4";
            $pdo = new PDO($dsn, $config['DB_USER'], $config['DB_PASS']);
            $migrationFile = '../database/migrations/001_create_cms_core_tables_mysql.sql';
        } else {
            $dbPath = "../{$config['DB_FILE']}";
            if (!file_exists($dbPath)) {
                throw new Exception("SQLite database file does not exist. Please complete the database setup step first.");
            }
            $dsn = "sqlite:{$dbPath}";
            $pdo = new PDO($dsn);
            $migrationFile = '../database/migrations/001_create_cms_core_tables.sql';
        }
        
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Unknown database') !== false) {
            throw new Exception("Database does not exist yet. Please complete the database setup step first.");
        }
        throw new Exception("Database connection failed: " . $e->getMessage());
    }
    
    // Create migrations tracking table if it doesn't exist
    if ($dbType === 'mysql') {
        $pdo->exec("CREATE TABLE IF NOT EXISTS migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL UNIQUE,
            batch INT NOT NULL,
            executed_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    } else {
        $pdo->exec("CREATE TABLE IF NOT EXISTS migrations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            migration VARCHAR(255) NOT NULL UNIQUE,
            batch INTEGER NOT NULL,
            executed_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }
    
    // Get list of already executed migrations
    $executedMigrations = [];
    $stmt = $pdo->query("SELECT migration FROM migrations");
    if ($stmt) {
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $executedMigrations[] = $row['migration'];
        }
    }
    error_log("Already executed migrations: " . implode(', ', $executedMigrations));
    
    // IMPORTANT: Check if tables from migrations already exist (from a previous installation)
    // If they do, we need to record those migrations as executed WITHOUT running them
    // This prevents "duplicate key" and "table already exists" errors during reinstalls
    
    // Map of migration names to their indicator tables
    // These migrations have CREATE INDEX statements that will fail if tables already exist
    $migrationTableMap = $dbType === 'mysql' ? [
        '001_create_cms_core_tables_mysql' => 'users',
        '002_create_orders_table_mysql' => 'orders',
        '003_create_products_table_mysql' => 'products',
        '004_create_clients_table_mysql' => 'clients',
        '20251206_create_client_sandboxes_mysql' => 'client_sandboxes',
        '20251215_create_courses_tables_mysql' => 'subscription_plans',
        '20251215_create_rate_limit_config_mysql' => 'rate_limit_config',
        '20251215_create_provider_keys_mysql' => 'provider_keys',
        '20251215_create_user_rate_limits_mysql' => 'user_rate_limits',
    ] : [
        '001_create_cms_core_tables' => 'users',
        '002_create_orders_table' => 'orders',
        '003_create_products_table' => 'products',
        '004_create_clients_table' => 'clients',
    ];
    
    foreach ($migrationTableMap as $migrationName => $indicatorTable) {
        if (!in_array($migrationName, $executedMigrations)) {
            // Check if the indicator table exists
            $tableExists = false;
            try {
                if ($dbType === 'mysql') {
                    $checkStmt = $pdo->query("SHOW TABLES LIKE '{$indicatorTable}'");
                    $tableExists = ($checkStmt && $checkStmt->rowCount() > 0);
                } else {
                    $checkStmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='{$indicatorTable}'");
                    $tableExists = ($checkStmt && $checkStmt->fetch());
                }
            } catch (PDOException $e) {
                $tableExists = false;
            }
            
            if ($tableExists) {
                // Table already exists from previous installation
                // Record the migration as executed WITHOUT running it
                error_log("Table '{$indicatorTable}' already exists - recording migration '{$migrationName}' as executed without running it");
                $insertStmt = $pdo->prepare("INSERT INTO migrations (migration, batch) VALUES (?, ?)");
                $insertStmt->execute([$migrationName, 1]);
                $executedMigrations[] = $migrationName;
            }
        }
    }
    
    // Get next batch number
    $batchStmt = $pdo->query("SELECT MAX(batch) as max_batch FROM migrations");
    $batchRow = $batchStmt->fetch(PDO::FETCH_ASSOC);
    $nextBatch = ($batchRow['max_batch'] ?? 0) + 1;
    
    // Run SQL migration files in the database/migrations directory for the selected DB type, in alphabetical order
    $migrationsDir = __DIR__ . '/../database/migrations';
    if (!is_dir($migrationsDir)) {
        throw new Exception("Migrations directory not found: {$migrationsDir}");
    }

    $files = glob($migrationsDir . '/*.sql');
    if (!$files) {
        throw new Exception('No migration files found in migrations directory');
    }
    // Filter files by database type. For MySQL, only run files that end with _mysql.sql.
    // For SQLite (or generic), run files that do NOT end with _mysql.sql.
    $files = array_filter($files, function ($file) use ($dbType) {
        if ($dbType === 'mysql') {
            return preg_match('/_mysql\.sql$/', $file);
        }
        return !preg_match('/_mysql\.sql$/', $file);
    });

    if (count($files) === 0) {
        throw new Exception('No migration files found for DB type: ' . $dbType);
    }

    sort($files, SORT_STRING);
    
    // Filter out already executed migrations
    $pendingFiles = array_filter($files, function($file) use ($executedMigrations) {
        $migrationName = pathinfo($file, PATHINFO_FILENAME);
        return !in_array($migrationName, $executedMigrations);
    });
    
    if (count($pendingFiles) === 0) {
        error_log("No pending migrations to run - database is up to date");
        return 'Database is already up to date (no pending migrations)';
    }
    
    error_log("Running " . count($pendingFiles) . " pending migration(s) for DB type: " . $dbType . ": " . implode(', ', array_map(function($f){ return basename($f); }, $pendingFiles)));
    $executedCount = 0;
    foreach ($pendingFiles as $file) {
        $migrationName = pathinfo($file, PATHINFO_FILENAME);
        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new Exception("Failed to read migration file: {$file}");
        }
        // Normalize SQL for PDO execution: remove client-only DELIMITER directives
        // and replace custom terminators used with triggers (e.g., //, $$) with semicolons.
        $normalizedSql = preg_replace('/^\s*DELIMITER\s+\S+\s*$/mi', '', $sql);
        $normalizedSql = preg_replace('/END\s*;?\/\//i', 'END;', $normalizedSql);
        $normalizedSql = preg_replace('/END\s*;?\$\$/i', 'END;', $normalizedSql);
        // Also handle cases where the delimiter is on the same line as the statement terminator
        $normalizedSql = str_replace(["END;//", "END//", "END$$", "END $$"], 'END;', $normalizedSql);
        error_log("Executing SQL migration file: " . basename($file));
        $pdo->exec($normalizedSql);
        
        // Record migration as executed
        $insertStmt = $pdo->prepare("INSERT INTO migrations (migration, batch) VALUES (?, ?)");
        $insertStmt->execute([$migrationName, $nextBatch]);
        $executedCount++;
    }

    // Setup guest database user for AI agent (MySQL only)
    if ($dbType === 'mysql') {
        try {
            setupGuestDatabaseUser($config);
            error_log("Guest database user setup completed");
        } catch (Exception $e) {
            // Log but don't fail installation - guest user can be set up manually
            error_log("Warning: Could not set up guest database user: " . $e->getMessage());
        }
    }

    return 'Database migrations completed successfully';
}

/**
 * Setup the 'guest' MySQL user with limited access to the 'clients' table only.
 * This user is used by the AI agent for non-admin database operations.
 * 
 * The guest user can only:
 * - SELECT, INSERT, UPDATE, DELETE on the 'clients' table
 * - No DDL operations (CREATE, ALTER, DROP)
 * - No access to other tables (users, settings, etc.)
 */
function setupGuestDatabaseUser($config) {
    $host = $config['DB_HOST'] ?? 'localhost';
    $port = $config['DB_PORT'] ?? '3306';
    $dbname = $config['DB_NAME'] ?? 'ginto';
    $rootUser = $config['DB_USER'];
    $rootPass = $config['DB_PASS'];
    
    // Guest user credentials (can be customized via .env)
    $guestUser = $config['DB_GUEST_USER'] ?? 'guest';
    $guestPass = $config['DB_GUEST_PASSWORD'] ?? 'guest_password';
    $guestHost = $config['DB_GUEST_HOST'] ?? 'localhost';
    
    try {
        $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
        $pdo = new PDO($dsn, $rootUser, $rootPass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Check if user has GRANT privilege
        $stmt = $pdo->query("SHOW GRANTS FOR CURRENT_USER()");
        $grants = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $hasGrantPrivilege = false;
        foreach ($grants as $grant) {
            if (stripos($grant, 'GRANT OPTION') !== false || stripos($grant, 'ALL PRIVILEGES') !== false) {
                $hasGrantPrivilege = true;
                break;
            }
        }
        
        if (!$hasGrantPrivilege) {
            error_log("Warning: Current user does not have GRANT privilege. Guest user must be created manually.");
            return;
        }
        
        // Drop user if exists (MySQL 5.7+ syntax)
        try {
            $pdo->exec("DROP USER IF EXISTS '{$guestUser}'@'{$guestHost}'");
        } catch (PDOException $e) {
            // Ignore if user doesn't exist
        }
        
        // Create guest user
        $pdo->exec("CREATE USER '{$guestUser}'@'{$guestHost}' IDENTIFIED BY '{$guestPass}'");
        
        // Grant limited permissions - ONLY on 'clients' table
        $pdo->exec("GRANT SELECT, INSERT, UPDATE, DELETE ON `{$dbname}`.`clients` TO '{$guestUser}'@'{$guestHost}'");
        
        // Flush privileges
        $pdo->exec("FLUSH PRIVILEGES");
        
        error_log("Guest user '{$guestUser}'@'{$guestHost}' created with access to '{$dbname}.clients' table only");
        
        // Also update .env with guest credentials if not already set
        updateEnvWithGuestCredentials($guestUser, $guestPass);
        
    } catch (PDOException $e) {
        throw new Exception("Failed to create guest user: " . $e->getMessage());
    }
}

/**
 * Update .env file with guest database credentials if not already present.
 */
function updateEnvWithGuestCredentials($guestUser, $guestPass) {
    $envFile = '../.env';
    if (!file_exists($envFile)) {
        return;
    }
    
    $content = file_get_contents($envFile);
    
    // Check if guest credentials already exist
    if (strpos($content, 'DB_GUEST_USER') !== false) {
        return; // Already configured
    }
    
    // Append guest credentials
    $guestConfig = "\n# Guest database user for AI agent (limited to 'clients' table)\n";
    $guestConfig .= "DB_GUEST_USER={$guestUser}\n";
    $guestConfig .= "DB_GUEST_PASSWORD={$guestPass}\n";
    
    file_put_contents($envFile, $content . $guestConfig);
    error_log("Added guest database credentials to .env");
}

function createAdminAccountStep($data) {
    try {
        $email = $data['admin_email'] ?? 'admin@example.com';
        $username = $data['admin_username'] ?? 'admin';
        $password = $data['admin_password'] ?? '';
        
        // Validate required admin password
        if (empty($password) || strlen($password) < 6) {
            throw new Exception('Admin password is required and must be at least 6 characters long.');
        }
        
        // Load database config
        $envFile = '../.env';
        if (!file_exists($envFile)) {
            throw new Exception('.env file not found');
        }
        
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $config = [];
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                [$key, $value] = explode('=', $line, 2);
                $config[trim($key)] = trim($value, '"');
            }
        }
        
        $dbType = $config['DB_TYPE'] ?? 'sqlite';
        
        if ($dbType === 'mysql') {
            $host = $config['DB_HOST'];
            $port = $config['DB_PORT'];
            $dbname = $config['DB_NAME'];
            $user = $config['DB_USER'];
            $pass = $config['DB_PASS'];
            
            error_log("Attempting MySQL connection with user: " . $user . " and password: " . $pass);
            $options = [
                PDO::ATTR_TIMEOUT => 10, // 10 second timeout
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ];
            
            // First try to connect to the specific database
            try {
                $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
                $pdo = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                // If database doesn't exist, create it
                if (strpos($e->getMessage(), 'Unknown database') !== false) {
                    error_log("Database '{$dbname}' doesn't exist, creating it...");
                    
                    // Connect without database name to create it
                    $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
                    $tempPdo = new PDO($dsn, $user, $pass, $options);
                    $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    
                    // Now connect to the created database
                    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
                    $pdo = new PDO($dsn, $user, $pass, $options);
                } else {
                    throw $e; // Re-throw other connection errors
                }
            }
        } else {
            $dsn = "sqlite:../{$config['DB_FILE']}";
            $pdo = new PDO($dsn);
        }
    } catch (Exception $e) {
        error_log("Database connection failed in createAdminAccountStep: " . $e->getMessage());
        throw new Exception("Database connection failed: " . $e->getMessage() . ". Check your database credentials in .env file.");
    }
    
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    try {
        // Hash the password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        error_log("Admin password hashed successfully");
        
        // Check if users table exists
        if (!tableExists($pdo, $dbType, 'users')) {
            throw new Exception("Users table does not exist. Database migration may have failed.");
        }
        error_log("Users table exists");
        
        // Check if roles table exists and has admin role
        if (!tableExists($pdo, $dbType, 'roles')) {
            throw new Exception("Roles table does not exist. Database migration may have failed.");
        }
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM roles WHERE id = 2");
        $stmt->execute();
        $adminRoleExists = $stmt->fetchColumn();
        if (!$adminRoleExists) {
            throw new Exception("Admin role (ID: 2) does not exist in roles table. Database migration may have failed to insert default roles.");
        }
        error_log("Users and roles tables exist with proper data");
        
        // Check for existing admin user and handle duplicates
        error_log("Checking for existing admin user: $username, $email");
        
        // Check if username already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        $existingUser = $stmt->fetch();
        
        $adminCreated = false;
        if ($existingUser) {
            error_log("Admin user already exists: $username or $email - skipping creation (preserving existing data)");
            // Don't throw error - just note that user exists (preserves existing accounts on reinstall)
        } else {
                // Insert new admin user — use provided first/middle/last name if supplied, else fall back to username
                error_log("Inserting new admin user: $username, $email");
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, fullname, role_id, status, created_at) VALUES (?, ?, ?, ?, 2, 'active', NOW())");
                $first = trim($data['admin_first_name'] ?? '');
                $middle = trim($data['admin_middle_name'] ?? '');
                $last = trim($data['admin_last_name'] ?? '');
                $parts = array_filter([$first, $middle, $last]);
                $fullname = count($parts) ? implode(' ', $parts) : $username;
                $result = $stmt->execute([$username, $email, $hashedPassword, $fullname]);
            error_log("Admin user inserted: " . ($result ? "SUCCESS" : "FAILED"));
            $adminCreated = true;
        }
        
        // Ensure levels exist before attempting to create users (prevent FK constraint errors)
        seedLevelsIfMissing($pdo, $dbType);

        // Create the default user account (first user in the system)
        $defaultUsername = $data['default_username'] ?? '001';
        $defaultEmail = $data['default_email'] ?? '001@ginto.local';
        $defaultPasswordPlain = $data['default_password'] ?? '001default';
        $defaultPassword = password_hash($defaultPasswordPlain, PASSWORD_DEFAULT);
        
        error_log("Checking for existing default user: $defaultUsername, $defaultEmail");
        
        // Check if default user already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$defaultUsername, $defaultEmail]);
        $existingDefaultUser = $stmt->fetch();
        
        $defaultCreated = false;
        if ($existingDefaultUser) {
            error_log("Default user already exists: $defaultUsername or $defaultEmail - skipping creation (preserving existing data)");
            // Don't throw error - just note that user exists (preserves existing accounts on reinstall)
        } else {
            error_log("Inserting new default user: $defaultUsername, $defaultEmail");
            $stmt001 = $pdo->prepare("INSERT INTO users (username, email, password_hash, fullname, role_id, status, current_level_id, ginto_level, created_at) VALUES (?, ?, ?, ?, 5, 'active', 1, 0, NOW())");
            $result001 = $stmt001->execute([$defaultUsername, $defaultEmail, $defaultPassword, 'Default User - ' . $defaultUsername]);
            error_log("Default user inserted: " . ($result001 ? "SUCCESS" : "FAILED"));
            $defaultCreated = true;
        }
        
        // Build appropriate response message
        $messages = [];
        if ($adminCreated) {
            $messages[] = "Admin account created (Username: {$username})";
        } else {
            $messages[] = "Admin account '{$username}' already exists (preserved)";
        }
        if ($defaultCreated) {
            $messages[] = "Default user '{$defaultUsername}' created with password '{$defaultPasswordPlain}'";
        } else {
            $messages[] = "Default user '{$defaultUsername}' already exists (preserved)";
        }
        
        return implode('. ', $messages) . '.';
        
    } catch (Exception $e) {
        error_log("Error in createAdminAccountStep: " . $e->getMessage());
        // Pass through the specific error message without wrapping it
        throw $e;
    }
}

function importDemoContentStep($data) {
    // For now, just return success - demo content can be added later
    return 'Demo content imported successfully';
}

function tableExists($pdo, $dbType, $tableName) {
    if ($dbType === 'mysql') {
        $stmt = $pdo->query("SHOW TABLES LIKE '" . $tableName . "'");
        return $stmt && $stmt->fetch();
    }
    $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");
    $stmt->execute([$tableName]);
    return (bool)$stmt->fetchColumn();
}

/**
 * Ensure core tier plans exist in the database by running the corresponding seed migration
 * or executing an inline fallback if seed files are not present.
 */
function seedLevelsIfMissing($pdo, $dbType) {
    try {
        // Determine if tier_plans table exists
        if (!tableExists($pdo, $dbType, 'tier_plans')) {
            error_log("tier_plans table does not exist (create migration missing)");
            return false;
        }

        $countStmt = $pdo->query("SELECT COUNT(*) FROM tier_plans");
        $count = $countStmt ? (int)$countStmt->fetchColumn() : 0;
        if ($count > 0) {
            error_log("Tier plans already present in database (count={$count})");
            return true;
        }

        $seedFile = __DIR__ . '/../database/migrations/002_seed_tier_plans' . ($dbType === 'mysql' ? '_mysql.sql' : '.sql');
        if (file_exists($seedFile)) {
            $sql = file_get_contents($seedFile);
            $normalizedSql = preg_replace('/^\s*DELIMITER\s+\S+\s*$/mi', '', $sql);
            $normalizedSql = preg_replace('/END\s*;?\/\//i', 'END;', $normalizedSql);
            $normalizedSql = preg_replace('/END\s*;?\$\$/i', 'END;', $normalizedSql);
            $normalizedSql = str_replace(["END;//", "END//", "END$$", "END $$"], 'END;', $normalizedSql);
            $pdo->exec($normalizedSql);
            error_log("Seeded tier_plans using file: {$seedFile}");
            return true;
        }

        // Fallback: run prepared inserts directly
        error_log("Seed file not found. Inserting tier_plans fallback directly.");
        $now = date('Y-m-d H:i:s');
        $seedValues = [
            [1, 'Starter', 150.00, 'PHP', json_encode(['L1' => 0.01])],
            [2, 'Basic', 1000.00, 'PHP', json_encode(['L1' => 0.02])],
            [3, 'Silver', 5000.00, 'PHP', json_encode(['L1' => 0.05, 'L2' => 0.02])],
            [4, 'Gold', 10000.00, 'PHP', json_encode(['L1' => 0.07, 'L2' => 0.03, 'L3' => 0.02])],
            [5, 'Platinum', 50000.00, 'PHP', json_encode(['L1' => 0.10, 'L2' => 0.05, 'L3' => 0.03, 'L4' => 0.02, 'L5' => 0.01])],
        ];

        // Build and execute multi-insert statement
        $placeholders = [];
        $params = [];
        foreach ($seedValues as $row) {
            $placeholders[] = '(?, ?, ?, ?, ?)';
            $params[] = $row[0]; // id
            $params[] = $row[1]; // name
            $params[] = $row[2]; // amount
            $params[] = $row[3]; // currency
            $params[] = $row[4]; // json
        }
        if ($dbType === 'mysql') {
            $sql = "INSERT IGNORE INTO tier_plans (id, name, cost_amount, cost_currency, commission_rate_json) VALUES " . implode(', ', $placeholders);
        } else {
            $sql = "INSERT OR IGNORE INTO tier_plans (id, name, cost_amount, cost_currency, commission_rate_json) VALUES " . implode(', ', $placeholders);
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        error_log("Inserted fallback tier_plans rows (count: " . count($seedValues) . ")");
        return true;
    } catch (Exception $e) {
        error_log("seedLevelsIfMissing failed: " . $e->getMessage());
        return false;
    }
}

function finalizeInstallationStep($data) {
    // Create installation complete marker
    $markerFile = '../.installed';
    file_put_contents($markerFile, date('Y-m-d H:i:s'));
    
    // Get home directory for proper paths
    $homeDir = getenv('HOME') ?: ('/home/' . get_current_user());
    $gintoDir = realpath('..') ?: ($homeDir . '/ginto');
    
    // Save llama model configuration to a persistent config file
    $reasoningModel = trim($data['reasoning_hf_model'] ?? '');
    $visionModel = trim($data['vision_hf_model'] ?? '');
    $messages = ['Installation finalized successfully'];
    
    // Create llama models config file
    $llamaConfig = [
        'reasoning_model' => $reasoningModel,
        'reasoning_port' => 8034,
        'vision_model' => $visionModel,
        'vision_port' => 8033,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    $configPath = '../config/llama_models.json';
    file_put_contents($configPath, json_encode($llamaConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $messages[] = "Model config saved: config/llama_models.json";
    
    // Create start script for llama.cpp models
    $startScript = "#!/usr/bin/env bash\n";
    $startScript .= "# Ginto AI - llama.cpp Model Start Script\n";
    $startScript .= "# Auto-generated by installer\n\n";
    $startScript .= "set -e\n\n";
    
    // Source profile for PATH
    $startScript .= "# Source profile for llama.cpp PATH\n";
    $startScript .= "source /etc/profile.d/llamacpp.sh 2>/dev/null || true\n";
    $startScript .= "source ~/.bashrc 2>/dev/null || true\n\n";
    
    $hasModels = false;
    
    if (!empty($reasoningModel)) {
        $hasModels = true;
        $startScript .= "# Start Reasoning Model (port 8034)\n";
        $startScript .= "echo \"Starting reasoning model: {$reasoningModel}...\"\n";
        $startScript .= "nohup llama-server -hf {$reasoningModel} -c 0 --host 127.0.0.1 --port 8034 > /tmp/llama-reasoning.log 2>&1 &\n";
        $startScript .= "echo \"Reasoning model PID: \$!\"\n";
        $startScript .= "echo \$! > /tmp/llama-reasoning.pid\n\n";
        $messages[] = "Reasoning model configured: {$reasoningModel} (port 8034)";
    }
    
    if (!empty($visionModel)) {
        $hasModels = true;
        $startScript .= "# Start Vision Model (port 8033)\n";
        $startScript .= "echo \"Starting vision model: {$visionModel}...\"\n";
        $startScript .= "nohup llama-server -hf {$visionModel} --jinja -c 0 --host 127.0.0.1 --port 8033 > /tmp/llama-vision.log 2>&1 &\n";
        $startScript .= "echo \"Vision model PID: \$!\"\n";
        $startScript .= "echo \$! > /tmp/llama-vision.pid\n\n";
        $messages[] = "Vision model configured: {$visionModel} (port 8033)";
    }
    
    if ($hasModels) {
        $startScript .= "echo \"\"\n";
        $startScript .= "echo \"Models are starting in background. Check logs at:\"\n";
        if (!empty($reasoningModel)) $startScript .= "echo \"  Reasoning: /tmp/llama-reasoning.log\"\n";
        if (!empty($visionModel)) $startScript .= "echo \"  Vision: /tmp/llama-vision.log\"\n";
        $startScript .= "echo \"\"\n";
        $startScript .= "echo \"To stop models: kill \\$(cat /tmp/llama-*.pid 2>/dev/null)\"\n";
        
        // Save the start script
        $scriptPath = '../bin/start_llama_models.sh';
        file_put_contents($scriptPath, $startScript);
        chmod($scriptPath, 0755);
        $messages[] = "Start script created: bin/start_llama_models.sh";
        
        // Create the bootstrapper script that reads from config
        $bootstrapper = <<<'BOOTSTRAP'
#!/usr/bin/env bash
# Ginto AI - llama.cpp Model Bootstrapper
# Reads model config and starts llama-server instances
# This script is designed to be run by systemd on boot

set -e

GINTO_DIR="{{GINTO_DIR}}"
CONFIG_FILE="${GINTO_DIR}/config/llama_models.json"
LLAMA_SERVER=""

# Find llama-server binary
find_llama_server() {
    # Check PATH first
    if command -v llama-server &>/dev/null; then
        LLAMA_SERVER=$(command -v llama-server)
        return 0
    fi
    
    # Check common locations
    local paths=(
        "${HOME}/llama.cpp/build/bin/llama-server"
        "/usr/local/bin/llama-server"
        "${GINTO_DIR}/../llama.cpp/build/bin/llama-server"
    )
    
    for path in "${paths[@]}"; do
        if [[ -x "$path" ]]; then
            LLAMA_SERVER="$path"
            return 0
        fi
    done
    
    return 1
}

# Load config and start models
start_models() {
    if [[ ! -f "$CONFIG_FILE" ]]; then
        echo "Config not found: $CONFIG_FILE"
        exit 1
    fi
    
    # Source PATH if available
    source /etc/profile.d/llamacpp.sh 2>/dev/null || true
    
    if ! find_llama_server; then
        echo "llama-server not found in PATH or common locations"
        exit 1
    fi
    
    echo "Using llama-server: $LLAMA_SERVER"
    
    # Parse JSON config (using grep/sed for minimal dependencies)
    local reasoning_model=$(grep -o '"reasoning_model"[[:space:]]*:[[:space:]]*"[^"]*"' "$CONFIG_FILE" | sed 's/.*"\([^"]*\)"$/\1/')
    local reasoning_port=$(grep -o '"reasoning_port"[[:space:]]*:[[:space:]]*[0-9]*' "$CONFIG_FILE" | grep -o '[0-9]*$')
    local vision_model=$(grep -o '"vision_model"[[:space:]]*:[[:space:]]*"[^"]*"' "$CONFIG_FILE" | sed 's/.*"\([^"]*\)"$/\1/')
    local vision_port=$(grep -o '"vision_port"[[:space:]]*:[[:space:]]*[0-9]*' "$CONFIG_FILE" | grep -o '[0-9]*$')
    
    # Start reasoning model
    if [[ -n "$reasoning_model" && "$reasoning_model" != "" ]]; then
        echo "Starting reasoning model: $reasoning_model on port ${reasoning_port:-8034}"
        $LLAMA_SERVER -hf "$reasoning_model" -c 0 --host 127.0.0.1 --port "${reasoning_port:-8034}" &
        echo $! > /tmp/llama-reasoning.pid
    fi
    
    # Start vision model
    if [[ -n "$vision_model" && "$vision_model" != "" ]]; then
        echo "Starting vision model: $vision_model on port ${vision_port:-8033}"
        $LLAMA_SERVER -hf "$vision_model" --jinja -c 0 --host 127.0.0.1 --port "${vision_port:-8033}" &
        echo $! > /tmp/llama-vision.pid
    fi
    
    echo "Models started. PIDs saved to /tmp/llama-*.pid"
}

# Stop models
stop_models() {
    echo "Stopping llama models..."
    for pidfile in /tmp/llama-*.pid; do
        if [[ -f "$pidfile" ]]; then
            pid=$(cat "$pidfile")
            if kill -0 "$pid" 2>/dev/null; then
                kill "$pid"
                echo "Stopped PID $pid"
            fi
            rm -f "$pidfile"
        fi
    done
}

# Main
case "${1:-start}" in
    start)
        start_models
        ;;
    stop)
        stop_models
        ;;
    restart)
        stop_models
        sleep 2
        start_models
        ;;
    *)
        echo "Usage: $0 {start|stop|restart}"
        exit 1
        ;;
esac
BOOTSTRAP;
        
        // Replace placeholder with actual ginto directory
        $bootstrapper = str_replace('{{GINTO_DIR}}', $gintoDir, $bootstrapper);
        // Ensure Unix line endings (LF only, no CRLF)
        $bootstrapper = str_replace("\r\n", "\n", $bootstrapper);
        $bootstrapper = str_replace("\r", "\n", $bootstrapper);
        
        $bootstrapperPath = '../bin/llama_bootstrapper.sh';
        file_put_contents($bootstrapperPath, $bootstrapper);
        chmod($bootstrapperPath, 0755);
        $messages[] = "Bootstrapper created: bin/llama_bootstrapper.sh";
        
        // Get current user for systemd service
        $currentUser = get_current_user() ?: (getenv('USER') ?: posix_getpwuid(posix_geteuid())['name'] ?? 'root');
        
        // Create systemd service file
        $systemdService = <<<SYSTEMD
[Unit]
Description=Ginto AI - llama.cpp Models
After=network.target

[Service]
Type=forking
User={$currentUser}
WorkingDirectory={$gintoDir}
ExecStart={$gintoDir}/bin/llama_bootstrapper.sh start
ExecStop={$gintoDir}/bin/llama_bootstrapper.sh stop
ExecReload={$gintoDir}/bin/llama_bootstrapper.sh restart
Restart=on-failure
RestartSec=10

[Install]
WantedBy=multi-user.target
SYSTEMD;
        
        $servicePath = '../bin/ginto-llama.service';
        file_put_contents($servicePath, $systemdService);
        
        // Create a single setup script for enabling boot persistence
        $setupScript = <<<'SETUP'
#!/usr/bin/env bash
# Ginto AI - Enable llama.cpp Models on Boot
# Run this script ONCE to enable auto-start on system boot
# Usage: sudo bash ~/ginto/bin/setup_llama_boot.sh

set -e

GINTO_DIR="{{GINTO_DIR}}"
SERVICE_FILE="${GINTO_DIR}/bin/ginto-llama.service"

echo "🚀 Ginto AI - Setting up llama.cpp boot service..."
echo ""

# Check if running as root
if [[ $EUID -ne 0 ]]; then
   echo "⚠️  This script must be run with sudo"
   echo "   Usage: sudo bash ${GINTO_DIR}/bin/setup_llama_boot.sh"
   exit 1
fi

# Check if service file exists
if [[ ! -f "$SERVICE_FILE" ]]; then
    echo "❌ Service file not found: $SERVICE_FILE"
    echo "   Please run the Ginto installer first."
    exit 1
fi

# Copy service file
echo "📋 Copying service file to systemd..."
cp "$SERVICE_FILE" /etc/systemd/system/ginto-llama.service

# Reload systemd
echo "🔄 Reloading systemd daemon..."
systemctl daemon-reload

# Enable service
echo "✅ Enabling ginto-llama service..."
systemctl enable ginto-llama

# Start service
echo "▶️  Starting ginto-llama service..."
systemctl start ginto-llama

# Check status
sleep 2
echo ""
echo "📊 Service status:"
systemctl status ginto-llama --no-pager || true

echo ""
echo "✨ Done! llama.cpp models will now start automatically on boot."
echo ""
echo "📝 Useful commands:"
echo "   systemctl status ginto-llama    # Check status"
echo "   systemctl restart ginto-llama   # Restart models"
echo "   systemctl stop ginto-llama      # Stop models"
echo "   journalctl -u ginto-llama       # View logs"
echo ""
echo "🧠 Reasoning model: http://127.0.0.1:8034/v1"
echo "👁️  Vision model:    http://127.0.0.1:8033/v1"
SETUP;
        
        $setupScript = str_replace('{{GINTO_DIR}}', $gintoDir, $setupScript);
        // Ensure Unix line endings
        $setupScript = str_replace("\r\n", "\n", $setupScript);
        $setupScript = str_replace("\r", "\n", $setupScript);
        
        $setupScriptPath = '../bin/setup_llama_boot.sh';
        file_put_contents($setupScriptPath, $setupScript);
        chmod($setupScriptPath, 0755);
        
        $messages[] = "Systemd service created: bin/ginto-llama.service";
        $messages[] = "Setup script created: bin/setup_llama_boot.sh";
        $messages[] = "";
        $messages[] = "🚀 To enable auto-start on boot, run in terminal:";
        $messages[] = "   sudo bash ~/ginto/bin/setup_llama_boot.sh";
        $messages[] = "";
        $messages[] = "💡 Models will start when you run the setup script or reboot after enabling the service.";
    }
    
    return implode("\n", $messages);
}

function checkSystemRequirements() {
    $requirements = [
        'php' => checkPhpVersion(),
        'database' => checkDatabaseSupport(),
        'writable' => checkWritableDirectories(),
        'extensions' => checkPhpExtensions()
    ];
    
    header('Content-Type: application/json');
    echo json_encode($requirements);
}

function checkPhpVersion() {
    $version = PHP_VERSION;
    $required = '7.4.0';
    
    if (version_compare($version, $required, '>=')) {
        return [
            'status' => true,
            'message' => "PHP version {$version} (✓ {$required}+ required)"
        ];
    }
    
    return [
        'status' => false,
        'message' => "PHP version {$version} (✗ {$required}+ required)"
    ];
}

function checkDatabaseSupport() {
    $sqlite = extension_loaded('sqlite3') || extension_loaded('pdo_sqlite');
    $mysql = extension_loaded('mysqli') || extension_loaded('pdo_mysql');
    
    if ($sqlite || $mysql) {
        $supported = [];
        if ($sqlite) $supported[] = 'SQLite';
        if ($mysql) $supported[] = 'MySQL';
        
        return [
            'status' => true,
            'message' => 'Database support: ' . implode(', ', $supported) . ' ✓'
        ];
    }
    
    return [
        'status' => false,
        'message' => 'No database support found (SQLite or MySQL required) ✗'
    ];
}

function checkWritableDirectories() {
    $directories = [
        '../' => 'Root directory',
        '../src/' => 'Source directory',
        '../public/' => 'Public directory'
    ];
    
    $errors = [];
    foreach ($directories as $dir => $name) {
        if (!is_writable($dir)) {
            $errors[] = $name;
        }
    }
    
    if (empty($errors)) {
        return [
            'status' => true,
            'message' => 'All required directories are writable ✓'
        ];
    }
    
    return [
        'status' => false,
        'message' => 'Not writable: ' . implode(', ', $errors) . ' ✗'
    ];
}

function checkPhpExtensions() {
    $required = ['json', 'openssl', 'mbstring', 'curl'];
    $missing = [];
    
    foreach ($required as $ext) {
        if (!extension_loaded($ext)) {
            $missing[] = $ext;
        }
    }
    
    if (empty($missing)) {
        return [
            'status' => true,
            'message' => 'All required PHP extensions are installed ✓'
        ];
    }
    
    return [
        'status' => false,
        'message' => 'Missing extensions: ' . implode(', ', $missing) . ' ✗'
    ];
}

function testDatabaseConnection() {
    // Ensure we always return JSON, even on errors
    header('Content-Type: application/json');
    
    // Clear any previous output that might cause JSON parsing issues
    if (ob_get_level()) {
        ob_clean();
    }
    
    $type = $_POST['db_type'] ?? 'sqlite';
    
    try {
        if ($type === 'sqlite') {
            $file = $_POST['db_file'] ?? 'database.sqlite';
            $path = '../' . $file;
            
            // Test SQLite connection (will create file if doesn't exist)
            $pdo = new PDO("sqlite:$path");
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Test that we can create and drop a table
            $pdo->exec("CREATE TABLE IF NOT EXISTS test_connection_table (id INTEGER)");
            $pdo->exec("DROP TABLE IF EXISTS test_connection_table");
            
            echo json_encode(['success' => true, 'message' => 'SQLite connection successful']);
        } else {
            // Test MySQL connection with proper validation
            $host = $_POST['db_host'] ?? 'localhost';
            $port = $_POST['db_port'] ?? 3306;
            $name = $_POST['db_name'] ?? '';
            $user = $_POST['db_user'] ?? '';
            $pass = $_POST['db_pass'] ?? '';
            
            // Validate required fields
            if (empty($user)) {
                throw new Exception('MySQL username is required');
            }
            
            if (empty($name)) {
                throw new Exception('Database name is required');
            }
            
            // Test connection with timeout
            $dsn = "mysql:host=$host;port=$port;charset=utf8mb4";
            $options = [
                PDO::ATTR_TIMEOUT => 5,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ];
            
            $pdo = new PDO($dsn, $user, $pass, $options);
            
            // Test basic query
            $pdo->query("SELECT 1");
            
            // Test if we can create the database
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            // Test connection to the specific database
            $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";
            $testPdo = new PDO($dsn, $user, $pass, $options);
            $testPdo->query("SELECT 1");
            
            echo json_encode(['success' => true, 'message' => "MySQL connection successful. Database '$name' is ready."]);
        }
    } catch (PDOException $e) {
        $message = $e->getMessage();
        
        if (strpos($message, 'Access denied') !== false) {
            $error = 'Access denied: Invalid username or password';
        } elseif (strpos($message, 'Connection refused') !== false) {
            $error = "Cannot connect to MySQL server at $host:$port. Is MySQL running?";
        } elseif (strpos($message, 'timeout') !== false) {
            $error = "Connection timeout to MySQL server at $host:$port";
        } elseif (strpos($message, 'Unknown host') !== false) {
            $error = "Unknown host: Cannot resolve hostname '$host'";
        } else {
            $error = "Database connection failed: $message";
        }
        
        echo json_encode(['success' => false, 'message' => $error]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function performInstallation() {
    $step = $_POST['step'] ?? '';
    
    try {
        switch ($step) {
            case 'create-config':
                createConfigFiles();
                break;
            case 'setup-database':
                setupDatabase();
                break;
            case 'run-migrations':
                runMigrations();
                break;
            case 'create-admin':
                createAdminUser();
                break;
            case 'import-demo':
                importDemoContent();
                break;
            case 'finalize':
                finalizeInstallation();
                break;
            default:
                throw new Exception('Invalid installation step');
        }
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function createConfigFiles() {
    $dbType = $_POST['db_type'] ?? 'sqlite';
    
    // Create .env file
    $envContent = "# Ginto CMS Configuration\n";
    $envContent .= "# Generated on " . date('Y-m-d H:i:s') . "\n\n";
    $envContent .= "APP_ENV=production\n";
    $envContent .= "APP_DEBUG=false\n";
    $envContent .= "APP_URL=" . ($_POST['site_url'] ?? 'http://localhost:8000') . "\n\n";
    
    if ($dbType === 'sqlite') {
        $envContent .= "DB_TYPE=sqlite\n";
        $envContent .= "DB_FILE=" . ($_POST['db_file'] ?? 'database.sqlite') . "\n";
    } else {
        $envContent .= "DB_TYPE=mysql\n";
        $envContent .= "DB_HOST=" . ($_POST['db_host'] ?? 'localhost') . "\n";
        $envContent .= "DB_PORT=" . ($_POST['db_port'] ?? 3306) . "\n";
        $envContent .= "DB_NAME=" . ($_POST['db_name'] ?? '') . "\n";
        $envContent .= "DB_USER=" . ($_POST['db_user'] ?? '') . "\n";
        $envContent .= "DB_PASS=" . ($_POST['db_pass'] ?? '') . "\n";
    }
    
    $envContent .= "\n# Security\n";
    $envContent .= "APP_KEY=" . generateRandomKey() . "\n";
    $envContent .= "JWT_SECRET=" . generateRandomKey() . "\n";
    
    if (!file_put_contents('../.env', $envContent)) {
        throw new Exception('Failed to create .env file');
    }
    
    // Create database directory if using SQLite
    if ($dbType === 'sqlite') {
        $dbDir = dirname('../' . ($_POST['db_file'] ?? 'database.sqlite'));
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0755, true);
        }
    }
}

function setupDatabase() {
    $config = loadConfig();
    
    if ($config['DB_TYPE'] === 'sqlite') {
        $pdo = new PDO("sqlite:../" . $config['DB_FILE']);
    } else {
        // Create database if it doesn't exist
        try {
            // First connect without specifying database
            $dsn = "mysql:host={$config['DB_HOST']};port={$config['DB_PORT']};charset=utf8mb4";
            $pdo = new PDO($dsn, $config['DB_USER'], $config['DB_PASS']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Create database if it doesn't exist
            $dbName = $config['DB_NAME'];
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            // Now connect to the specific database
            $dsn = "mysql:host={$config['DB_HOST']};port={$config['DB_PORT']};dbname={$config['DB_NAME']};charset=utf8mb4";
            $pdo = new PDO($dsn, $config['DB_USER'], $config['DB_PASS']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (Exception $e) {
            throw new Exception("Failed to create/connect to database: " . $e->getMessage());
        }
    }
    
    // Test that we can create tables
    $pdo->exec("CREATE TABLE IF NOT EXISTS test_table (id INTEGER)");
    $pdo->exec("DROP TABLE test_table");
}

function runMigrations() {
    // Load the migration system
    require_once '../vendor/autoload.php';
    require_once '../src/Core/Database.php';
    require_once '../src/Core/Migration.php';
    
    $config = loadConfig();
    
    // Create database connection
    if ($config['DB_TYPE'] === 'sqlite') {
        $database = new Medoo\Medoo([
            'database_type' => 'sqlite',
            'database_file' => '../' . $config['DB_FILE']
        ]);
    } else {
        $database = new Medoo\Medoo([
            'database_type' => 'mysql',
            'database_name' => $config['DB_NAME'],
            'server' => $config['DB_HOST'],
            'username' => $config['DB_USER'],
            'password' => $config['DB_PASS'],
            'port' => $config['DB_PORT'],
            'charset' => 'utf8mb4'
        ]);
    }
    
    // Run migrations
    $migration = new Ginto\Core\Migration($database);
    $migration->migrate();
}

function createAdminUser() {
    require_once '../vendor/autoload.php';
    require_once '../src/Core/Database.php';
    
    $config = loadConfig();
    
    // Create database connection
    if ($config['DB_TYPE'] === 'sqlite') {
        $database = new Medoo\Medoo([
            'database_type' => 'sqlite',
            'database_file' => '../' . $config['DB_FILE']
        ]);
    } else {
        $database = new Medoo\Medoo([
            'database_type' => 'mysql',
            'database_name' => $config['DB_NAME'],
            'server' => $config['DB_HOST'],
            'username' => $config['DB_USER'],
            'password' => $config['DB_PASS'],
            'port' => $config['DB_PORT'],
            'charset' => 'utf8mb4'
        ]);
    }
    
    // Validate admin password
    if (empty($_POST['admin_password']) || strlen($_POST['admin_password']) < 6) {
        throw new Exception('Admin password is required and must be at least 6 characters long.');
    }
    
    // Create admin user
    $adminData = [
        'first_name' => $_POST['admin_first_name'] ?? 'Admin',
        'last_name' => $_POST['admin_last_name'] ?? 'User',
        'email' => $_POST['admin_email'] ?? 'admin@example.com',
        'username' => $_POST['admin_username'] ?? 'admin',
        'password' => password_hash($_POST['admin_password'], PASSWORD_DEFAULT),
        'role_id' => 1, // Super Admin
        'status' => 'active',
        'email_verified_at' => date('Y-m-d H:i:s'),
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    $database->insert('users', $adminData);
    
    // Update site settings
    $siteSettings = [
        ['key' => 'site_name', 'value' => $_POST['site_name'] ?? 'Ginto CMS'],
        ['key' => 'site_description', 'value' => $_POST['site_description'] ?? 'A modern CMS'],
        ['key' => 'site_url', 'value' => $_POST['site_url'] ?? 'http://localhost:8000'],
        ['key' => 'admin_email', 'value' => $_POST['admin_email'] ?? 'admin@example.com'],
        ['key' => 'timezone', 'value' => $_POST['timezone'] ?? 'UTC']
    ];
    
    foreach ($siteSettings as $setting) {
        $database->update('settings', 
            ['value' => $setting['value'], 'updated_at' => date('Y-m-d H:i:s')],
            ['key' => $setting['key']]
        );
    }
}

function importDemoContent() {
    if (!isset($_POST['import_demo_data']) || $_POST['import_demo_data'] !== 'on') {
        return; // Skip if demo data not requested
    }
    
    require_once '../vendor/autoload.php';
    require_once '../src/Core/Database.php';
    
    $config = loadConfig();
    
    // Create database connection (same as above)
    if ($config['DB_TYPE'] === 'sqlite') {
        $database = new Medoo\Medoo([
            'database_type' => 'sqlite',
            'database_file' => '../' . $config['DB_FILE']
        ]);
    } else {
        $database = new Medoo\Medoo([
            'database_type' => 'mysql',
            'database_name' => $config['DB_NAME'],
            'server' => $config['DB_HOST'],
            'username' => $config['DB_USER'],
            'password' => $config['DB_PASS'],
            'port' => $config['DB_PORT'],
            'charset' => 'utf8mb4'
        ]);
    }
    
    // Create demo content
    createDemoContent($database);
}

function createDemoContent($database) {
    // Create categories
    $categories = [
        ['name' => 'Technology', 'slug' => 'technology', 'description' => 'Latest tech news and tutorials'],
        ['name' => 'Business', 'slug' => 'business', 'description' => 'Business insights and strategies'],
        ['name' => 'Lifestyle', 'slug' => 'lifestyle', 'description' => 'Life, health, and wellness']
    ];
    
    foreach ($categories as $category) {
        $category['created_at'] = date('Y-m-d H:i:s');
        $category['updated_at'] = date('Y-m-d H:i:s');
        $database->insert('categories', $category);
    }
    
    // Create tags
    $tags = [
        ['name' => 'Web Development', 'slug' => 'web-development', 'color' => '#3b82f6'],
        ['name' => 'PHP', 'slug' => 'php', 'color' => '#8b5cf6'],
        ['name' => 'JavaScript', 'slug' => 'javascript', 'color' => '#f59e0b'],
        ['name' => 'Tutorial', 'slug' => 'tutorial', 'color' => '#10b981'],
        ['name' => 'News', 'slug' => 'news', 'color' => '#ef4444']
    ];
    
    foreach ($tags as $tag) {
        $tag['created_at'] = date('Y-m-d H:i:s');
        $database->insert('tags', $tag);
    }
    
    // Create sample pages
    $pages = [
        [
            'title' => 'Welcome to Ginto CMS',
            'slug' => 'home',
            'content' => '<h1>Welcome to Ginto CMS</h1><p>This is your new content management system. Start creating amazing content!</p>',
            'status' => 'published',
            'author_id' => 1,
            'published_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ],
        [
            'title' => 'About Us',
            'slug' => 'about',
            'content' => '<h1>About Us</h1><p>Learn more about our company and mission.</p>',
            'status' => 'published',
            'author_id' => 1,
            'published_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]
    ];
    
    foreach ($pages as $page) {
        $database->insert('pages', $page);
    }
    
    // Create sample posts
    $posts = [
        [
            'title' => 'Getting Started with Ginto CMS',
            'slug' => 'getting-started-with-ginto-cms',
            'content' => '<p>Welcome to Ginto CMS! This is your first blog post. You can edit or delete it from the admin panel.</p>',
            'excerpt' => 'Learn how to get started with your new CMS',
            'status' => 'published',
            'author_id' => 1,
            'category_id' => 1,
            'published_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]
    ];
    
    foreach ($posts as $post) {
        $postId = $database->insert('posts', $post);
        
        // Add tags to the first post
        if ($postId) {
            $database->insert('post_tags', ['post_id' => $postId, 'tag_id' => 1]);
            $database->insert('post_tags', ['post_id' => $postId, 'tag_id' => 4]);
        }
    }
}

function finalizeInstallation() {
    // Create additional directories (outside webroot)
    $directories = [
        '../../storage/logs',
        '../../storage/cache',
        '../../storage/uploads',
        '../../storage/backups',
        '../../storage/sessions',
        '../../storage/temp'
    ];
    
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
    
    // Create .htaccess for uploads directory
    $htaccessContent = "Options -Indexes\nDeny from all\n<FilesMatch \"\\.(jpg|jpeg|png|gif|pdf|doc|docx)$\">\nAllow from all\n</FilesMatch>";
    file_put_contents('../../storage/uploads/.htaccess', $htaccessContent);
    
    // Mark installation as complete
    touch('../../storage/.installed');
}

function loadConfig() {
    $config = [];
    $envFile = '../.env';
    
    if (!file_exists($envFile)) {
        throw new Exception('.env file not found');
    }
    
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $config[trim($key)] = trim($value);
        }
    }
    
    return $config;
}

/**
 * Get .env values for form pre-population
 * Returns sanitized environment values that are safe to expose to the frontend
 */
function getEnvValues() {
    error_log("DEBUG: getEnvValues() function called");
    header('Content-Type: application/json');
    
    try {
        $envFile = '../.env';
        
        if (!file_exists($envFile)) {
            echo json_encode(['success' => false, 'message' => '.env file not found', 'values' => []]);
            return;
        }
        
        $values = [];
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line && !str_starts_with($line, '#') && strpos($line, '=') !== false) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value, '"'); // Remove quotes
                // Only return safe values — exclude sensitive keys (secrets, tokens etc)
                // Allow admin/default password fields to be returned for convenience
                // but explicitly NEVER expose database password (DB_PASS).
                if (strtoupper($key) === 'DB_PASS') continue;
                // Allow ALL MCP-related fields through (including API keys, secrets, tokens)
                $allowedMcpFields = [
                    'MCP_SERVER_URL', 'USE_PY_STT', 'PYTHON3_PATH',
                    'GROQ_API_KEY', 'GROQ_TTS_MODEL', 'GROQ_STT_MODEL',
                    'LLM_PROVIDER', 'LLM_MODEL',
                    'DB_GUEST_USER', 'DB_GUEST_PASSWORD',
                    'CEREBRAS_API_KEY', 'CEREBRAS_API_URL',
                    'LOCAL_LLM_URL', 'LOCAL_LLM_MODEL', 'LOCAL_LLM_PRIMARY',
                    'VISION_MODEL_URL', 'VISION_MODEL_NAME', 'VISION_MAX_TOKENS', 'ENABLE_VISION',
                    'RATE_LIMIT_ADMIN_PERCENT', 'RATE_LIMIT_USER_PERCENT', 'RATE_LIMIT_VISITOR_PERCENT',
                    'RATE_LIMIT_FALLBACK_PROVIDER', 'RATE_LIMIT_FALLBACK_THRESHOLD',
                    'TTS_LIMIT_ADMIN_HOURLY', 'TTS_LIMIT_USER_HOURLY', 'TTS_LIMIT_VISITOR_SESSION', 'TTS_SILENT_STOP_PERCENT',
                    'ENABLE_DASHBOARD' // Experimental features
                ];
                if (in_array(strtoupper($key), $allowedMcpFields)) {
                    $values[$key] = $value;
                    continue;
                }
                // Block secrets/keys/tokens unless explicitly allowed above
                if (preg_match('/SECRET|KEY|TOKEN/i', $key)) continue;
                $values[$key] = $value;
            }
        }
        
        echo json_encode(['success' => true, 'values' => $values]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error reading .env file: ' . $e->getMessage(), 'values' => []]);
    }
}

function generateRandomKey($length = 32) {
    return bin2hex(random_bytes($length));
}
?>