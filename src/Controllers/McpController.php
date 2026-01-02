<?php

namespace Ginto\Controllers;

use Ginto\Database;

/**
 * McpController - Handles MCP (Model Context Protocol) routes
 */
class McpController
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? Database::getInstance();
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    }

    /**
     * Check if current request is from an admin
     */
    private function isAdmin(): bool
    {
        $isAdmin = (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin');
        $tokenHeader = $_SERVER['HTTP_X_GINTO_ADMIN_TOKEN'] ?? $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? null;
        $expected = getenv('GINTO_ADMIN_TOKEN') ?: getenv('ADMIN_TOKEN');
        if (!$isAdmin && $expected && $tokenHeader && hash_equals((string)$expected, (string)$tokenHeader)) {
            $isAdmin = true;
        }
        return $isAdmin;
    }

    /**
     * Return 403 if not admin
     */
    private function requireAdmin(): void
    {
        if (!$this->isAdmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Admin access required']);
            exit;
        }
    }

    public function call(): void
    {
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $tool = $input['tool'] ?? null;
        $args = $input['args'] ?? [];

        if (!$tool) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing tool parameter']);
            exit;
        }

        // Ensure Ginto\Handlers classes are loaded
        foreach (glob((defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2)) . '/src/Handlers/*.php') as $f) {
            require_once $f;
        }

        try {
            $result = \App\Core\McpInvoker::invoke($tool, $args);
            echo json_encode(['success' => true, 'result' => $result]);
        } catch (\Throwable $e) {
            \Ginto\Helpers\AdminErrorLogger::log($e->getMessage(), ['route' => '/mcp/call']);
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error (logged)']);
        }
        exit;
    }

    public function probe(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        if (!$this->isAdmin()) {
            http_response_code(403);
            echo json_encode(['available' => false, 'detail' => 'Admin access required']);
            exit;
        }

        $mcpUrl = $_ENV['MCP_SERVER_URL'] ?? 'http://127.0.0.1:9010';
        $available = false;
        $detail = '';

        if (class_exists('\PhpMcp\Client\Client')) {
            try {
                $sc = \PhpMcp\Client\ServerConfig::fromArray('probe', ['transport' => 'http', 'url' => $mcpUrl, 'timeout' => 5]);
                $client = \PhpMcp\Client\Client::make()->withServerConfig($sc)->build();
                try { $client->initialize(); } catch (\Throwable $_) { }
                if ($client->isReady()) {
                    try { $client->listTools(); $available = true; } catch (\Throwable $e) { $detail = (string)$e->getMessage(); }
                }
            } catch (\Throwable $e) {
                $detail = (string)$e->getMessage();
            }
        } else {
            // Fallback: basic HTTP probe
            $ch = curl_init(rtrim($mcpUrl, '/') . '/');
            curl_setopt_array($ch, [CURLOPT_NOBODY => true, CURLOPT_TIMEOUT => 3, CURLOPT_RETURNTRANSFER => true, CURLOPT_FAILONERROR => false]);
            curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            if ($code >= 200 && $code < 500) $available = true; else $detail = $err ?: ('HTTP ' . $code);
        }

        echo json_encode(['available' => $available, 'detail' => $detail]);
        exit;
    }

    public function chat(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $this->requireAdmin();

        // Accept both 'message' (from chat.js) and 'prompt' (legacy)
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $message = $input['message'] ?? $input['prompt'] ?? $_POST['prompt'] ?? '';
        $history = $input['history'] ?? [];

        if (empty($message)) {
            echo json_encode(['success' => false, 'error' => 'Missing message']);
            exit;
        }

        try {
            // Ensure handlers are loaded
            $root = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2);
            foreach (glob($root . '/src/Handlers/*.php') as $f) {
                require_once $f;
            }

            $host = new \App\Core\StandardMcpHost();

            // Prepopulate history if provided
            if (!empty($history) && is_array($history)) {
                foreach ($history as $h) {
                    if (!empty($h['role']) && isset($h['content'])) {
                        $host->addToHistory($h['role'], $h['content']);
                    }
                }
            }

            $response = $host->chat($message);

            // Server-side: detect free-form tool call text
            $toolCall = null;
            if (is_string($response)) {
                require_once $root . '/src/Core/LLM/ToolCallParser.php';
                $toolCall = \App\Core\LLM\ToolCallParser::extract($response);
            }

            if ($toolCall && !empty($toolCall['name'])) {
                $args = is_array($toolCall['arguments']) ? $toolCall['arguments'] : [];
                $toolName = $toolCall['name'];
                $validationError = null;

                // Strict validation for known tools
                if ($toolName === 'repo/create_or_update_file') {
                    if (!isset($args['file_path']) || !is_string($args['file_path']) || $args['file_path'] === '') {
                        $validationError = "repo/create_or_update_file requires a non-empty string 'file_path' argument.";
                    }
                } elseif ($toolName === 'compose_project') {
                    if (!isset($args['files']) || !is_array($args['files'])) {
                        $validationError = "compose_project requires an array 'files' argument.";
                    }
                }

                if ($validationError === null && !is_array($args)) {
                    $validationError = "Tool arguments must be an array.";
                }

                if ($validationError !== null) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Tool call argument validation failed',
                        'detail' => $validationError,
                        'tool_call' => $toolCall,
                        'response' => $response,
                        'history' => $host->getHistory()
                    ]);
                    exit;
                }

                try {
                    $toolResult = \App\Core\McpInvoker::invoke($toolName, $args);
                    echo json_encode(['success' => true, 'response' => $response, 'tool_call' => $toolCall, 'tool_result' => $toolResult, 'history' => $host->getHistory()]);
                    exit;
                } catch (\Throwable $e) {
                    \Ginto\Helpers\AdminErrorLogger::log($e->getMessage(), ['route' => '/mcp/chat', 'user_message' => $message, 'tool_call' => $toolCall]);
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Tool execution failed (logged)']);
                    exit;
                }
            }

            echo json_encode(['success' => true, 'response' => $response, 'history' => $host->getHistory()]);
        } catch (\Throwable $e) {
            \Ginto\Helpers\AdminErrorLogger::log($e->getMessage(), ['route' => '/mcp/chat', 'user_message' => $message]);
            echo json_encode(['success' => false, 'error' => 'Internal server error (logged)']);
        }
        exit;
    }

    public function invoke(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $this->requireAdmin();

        // Load .env into getenv() if vlucas/phpdotenv is present
        try {
            $root = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2);
            if ((class_exists(\Dotenv\Dotenv::class) || class_exists('Dotenv\\Dotenv')) && is_file($root . '/.env')) {
                try {
                    $d = \Dotenv\Dotenv::createImmutable($root);
                    $d->safeLoad();
                } catch (\Throwable $_) { /* ignore */ }
            }
        } catch (\Throwable $_) { /* ignore */ }

        $body = file_get_contents('php://input');
        $json = json_decode($body, true);

        if (!is_array($json)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'invalid_json']);
            exit;
        }

        $tool = $json['tool'] ?? null;
        $args = $json['args'] ?? [];

        if (!$tool) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'missing_tool']);
            exit;
        }

        try {
            $res = \App\Core\McpInvoker::invoke($tool, $args);
            echo json_encode(['success' => true, 'result' => $res]);
        } catch (\Throwable $e) {
            \Ginto\Helpers\AdminErrorLogger::log($e->getMessage(), ['route' => '/mcp/invoke', 'tool' => $tool]);
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Internal server error (logged)']);
        }
        exit;
    }

    public function discover(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $this->requireAdmin();

        $root = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2);
        $script = $root . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'dump_discovered_tools.php';

        if (!is_file($script)) {
            echo json_encode(['success' => false, 'message' => 'Discovery script not found', 'script' => $script]);
            exit;
        }

        $cmd = 'php ' . escapeshellarg($script) . ' 2>&1';
        @exec($cmd, $outLines, $outCode);
        $outText = is_array($outLines) ? implode("\n", $outLines) : (string)($outLines ?? '');

        $decoded = @json_decode($outText, true);

        // Detect local tools/ packages
        $toolDirs = [];
        try {
            $td = $root . DIRECTORY_SEPARATOR . 'tools';
            if (is_dir($td)) {
                $entries = scandir($td);
                foreach ($entries as $e) {
                    if ($e === '.' || $e === '..') continue;
                    $full = $td . DIRECTORY_SEPARATOR . $e;
                    if (is_dir($full) && (str_ends_with($e, '-mcp') || stripos($e, 'mcp') !== false)) {
                        $toolDirs[] = $e;
                    }
                }
            }
        } catch (\Throwable $_) { $toolDirs = []; }

        // Detect Handler files
        $handlers = [];
        try {
            $hd = $root . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Handlers';
            if (is_dir($hd)) {
                foreach (scandir($hd) as $f) {
                    if ($f === '.' || $f === '..') continue;
                    $full = $hd . DIRECTORY_SEPARATOR . $f;
                    if (is_file($full) && str_ends_with($f, '.php')) {
                        $handlers[] = pathinfo($f, PATHINFO_FILENAME);
                    }
                }
            }
        } catch (\Throwable $_) { $handlers = []; }

        $mcps = array_values(array_unique($toolDirs));
        $mcps_count = count($mcps);
        $handlers = array_values(array_unique($handlers));
        $handlers_count = count($handlers);

        if (is_array($decoded)) {
            echo json_encode(['success' => true, 'tools' => $decoded, 'mcps' => $mcps, 'mcps_count' => $mcps_count, 'handlers' => $handlers, 'handlers_count' => $handlers_count]);
            exit;
        }

        // Heuristic: scan for JSON substring
        $maybeJson = null;
        $len = strlen($outText);
        for ($i = max(0, $len - 32768); $i < $len; $i++) {
            $ch = $outText[$i] ?? '';
            if ($ch === '{' || $ch === '[') {
                $cand = substr($outText, $i);
                $try = @json_decode($cand, true);
                if (is_array($try)) { $maybeJson = $try; break; }
                $trimmed = rtrim($cand);
                $try2 = @json_decode($trimmed, true);
                if (is_array($try2)) { $maybeJson = $try2; break; }
            }
        }

        if (is_array($maybeJson)) {
            echo json_encode(['success' => true, 'tools' => $maybeJson, 'mcps' => $mcps, 'mcps_count' => $mcps_count, 'handlers' => $handlers, 'handlers_count' => $handlers_count]);
            exit;
        }

        $fallback = ['name' => 'local_discovery_fallback', 'description' => 'Fallback: raw discovery output', 'meta' => ['raw' => substr($outText, 0, 20000)]];
        echo json_encode(['success' => true, 'tools' => [$fallback], 'raw' => $outText, 'mcps' => $mcps, 'mcps_count' => $mcps_count, 'handlers' => $handlers, 'handlers_count' => $handlers_count]);
        exit;
    }

    public function unified(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $this->requireAdmin();

        $force = isset($_GET['refresh']) && ($_GET['refresh'] === '1' || strtolower($_GET['refresh']) === 'true');

        try {
            $u = new \App\Core\McpUnifier();
            $data = $u->getAllTools($force);
            $out = array_merge(['success' => true], $data);

            $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
            $prettyParam = isset($_GET['pretty']) && (strtolower($_GET['pretty']) === '1' || strtolower($_GET['pretty']) === 'true');
            $isBrowser = stripos($accept, 'text/html') !== false;
            $pretty = $prettyParam || $isBrowser;

            if ($pretty) {
                echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode($out);
            }
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}
