<?php

namespace Ginto\Controllers;

use Ginto\Database;

/**
 * ClientsController - Handle client sandbox proxy routes
 * Proxies requests to user sandbox containers
 */
class ClientsController
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Proxy GET /clients or /clients/
     */
    public function proxyRoot(): void
    {
        $this->handleClientProxy('/', $this->db);
    }

    /**
     * Proxy GET/POST /clients/{path}
     */
    public function proxy(string $path = ''): void
    {
        $this->handleClientProxy('/' . $path, $this->db);
    }

    /**
     * Proxy POST /clients or /clients/
     */
    public function proxyRootPost(): void
    {
        $this->handleClientProxy('/', $this->db);
    }

    /**
     * Handle sandbox preview GET /sandbox-preview/{sandboxId}/{path}
     */
    public function preview(string $sandboxId, string $path = ''): void
    {
        $this->handleSandboxPreview($sandboxId, '/' . ($path ?: 'index.php'), $this->db);
    }

    /**
     * Handle sandbox preview GET /sandbox-preview/{sandboxId} (default to index.php)
     */
    public function previewRoot(string $sandboxId): void
    {
        $this->handleSandboxPreview($sandboxId, '/index.php', $this->db);
    }

    /**
     * Handle client proxy requests
     * Gets sandbox ID from URL path (first segment) or session, validates, ensures container is running, then proxies
     * 
     * Architecture:
     *   Browser → /clients/{sandboxId}/path → PHP → container:80
     *   Browser → /clients/path → (PHP gets sandbox from session) → container:80
     */
    private function handleClientProxy(string $path, $db): void
    {
        // Start session if not started
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        
        $sandboxId = null;
        $actualPath = $path;
        
        // Check if first path segment is a valid sandbox ID (alphanumeric, 8-16 chars)
        // Path format: /sandboxId/rest/of/path or /rest/of/path
        $pathParts = explode('/', trim($path, '/'), 2);
        $firstSegment = $pathParts[0] ?? '';
        
        // Sandbox IDs are typically 12 chars alphanumeric (nanoid format)
        if (!empty($firstSegment) && preg_match('/^[a-z0-9]{8,20}$/i', $firstSegment)) {
            // Check if this looks like a sandbox ID (validate it exists or is valid format)
            if (\Ginto\Helpers\SandboxProxy::isValidSandboxId($firstSegment)) {
                $sandboxId = $firstSegment;
                // Remaining path after sandbox ID
                $actualPath = '/' . ($pathParts[1] ?? '');
            }
        }
        
        // If no sandbox ID from URL, try session
        if (empty($sandboxId)) {
            $sandboxId = $_SESSION['sandbox_id'] ?? null;
        }
        
        // If no sandbox in session, check if user is logged in and get their sandbox
        if (empty($sandboxId) && !empty($_SESSION['user_id'])) {
            // Try to get sandbox from client_sandboxes table for this user
            try {
                $stmt = $db->prepare('SELECT sandbox_id FROM client_sandboxes WHERE user_id = ? ORDER BY id DESC LIMIT 1');
                $stmt->execute([$_SESSION['user_id']]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($row && !empty($row['sandbox_id'])) {
                    $sandboxId = $row['sandbox_id'];
                    $_SESSION['sandbox_id'] = $sandboxId;
                }
            } catch (\Throwable $e) {
                // Log error but continue
                error_log("Failed to get sandbox from DB: " . $e->getMessage());
            }
        }
        
        // If still no sandbox, show helpful message
        if (empty($sandboxId)) {
            http_response_code(404);
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html><head><title>No Sandbox</title>';
            echo '<style>body{font-family:system-ui;max-width:600px;margin:50px auto;padding:20px;text-align:center;}';
            echo 'h1{color:#6366f1;}a{color:#6366f1;}</style></head><body>';
            echo '<h1>No Sandbox Available</h1>';
            echo '<p>You don\'t have a sandbox yet. Go to the <a href="/chat">Chat</a> and click "My Files" to create one.</p>';
            echo '</body></html>';
            exit;
        }
        
        // Validate sandbox ID format (security)
        if (!\Ginto\Helpers\SandboxProxy::isValidSandboxId($sandboxId)) {
            http_response_code(404);
            header('Content-Type: text/html; charset=utf-8');
            echo '<h1>404 - Not Found</h1><p>Invalid sandbox identifier.</p>';
            exit;
        }
        
        // Use UnifiedSandbox to support both Docker and LXD backends
        $backend = \Ginto\Helpers\UnifiedSandbox::getBackend();
        
        // Ensure container is running
        if (!\Ginto\Helpers\UnifiedSandbox::isRunning($sandboxId)) {
            // Try starting it
            $startResult = \Ginto\Helpers\UnifiedSandbox::ensureRunning($sandboxId);
            
            if (!$startResult['success']) {
                http_response_code(503);
                header('Content-Type: text/html; charset=utf-8');
                echo '<h1>503 - Sandbox Unavailable</h1><p>Your sandbox could not be started. Please try again.</p>';
                exit;
            }
            
            // Wait a moment for container to fully initialize
            usleep(500000); // 0.5 seconds
        }
        
        // For LXD, also ensure network routes are set up
        if ($backend === 'lxd') {
            \Ginto\Helpers\LxdSandboxManager::ensureSandboxAccessible($sandboxId);
        }
        
        // Get container IP - uses UnifiedSandbox which auto-selects backend
        $containerIp = \Ginto\Helpers\UnifiedSandbox::getIp($sandboxId);
        
        if (!$containerIp) {
            http_response_code(503);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Sandbox unavailable']);
            exit;
        }
        
        // Proxy directly to container (use actualPath which excludes sandbox ID prefix)
        $proxyUrl = 'http://' . $containerIp . ':80' . $actualPath;
        
        // Forward the request using cURL
        $ch = curl_init($proxyUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $_SERVER['REQUEST_METHOD']);
        
        // Forward headers
        $headers = [];
        foreach (getallheaders() as $name => $value) {
            if (!in_array(strtolower($name), ['host', 'connection', 'content-length'], true)) {
                $headers[] = "$name: $value";
            }
        }
        $headers[] = 'X-Forwarded-For: ' . ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $headers[] = 'X-Forwarded-Host: ' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $headers[] = 'X-Original-URI: /clients' . $path;
        $headers[] = 'X-Sandbox-ID: ' . $sandboxId;
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        // Forward body for POST/PUT/PATCH
        if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'PATCH'], true)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents('php://input'));
        }
        
        $response = curl_exec($ch);
        
        if ($response === false) {
            http_response_code(502);
            header('Content-Type: text/html; charset=utf-8');
            echo '<h1>502 - Bad Gateway</h1><p>Sandbox proxy unavailable: ' . htmlspecialchars(curl_error($ch)) . '</p>';
            curl_close($ch);
            exit;
        }
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        
        // Parse and forward response headers
        $headerText = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        
        http_response_code($httpCode);
        
        foreach (explode("\r\n", $headerText) as $line) {
            if (strpos($line, ':') !== false) {
                list($name, $value) = explode(':', $line, 2);
                $name = trim($name);
                // Skip headers that shouldn't be forwarded
                if (!in_array(strtolower($name), ['transfer-encoding', 'connection'], true)) {
                    header("$name: " . trim($value));
                }
            }
        }

        // If the proxied path looks like a downloadable document (pdf/docx/doc/odt/rtf/etc),
        // force a Content-Disposition: attachment and ensure a sane Content-Type so browsers
        // (especially mobile Safari/Chrome and iOS/Android clients) will download the file
        // instead of trying to render it inline or ignoring the `download` attribute.
        $requestedPath = $actualPath;
        $basename = basename($requestedPath);
        $ext = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
        $attachmentMimes = [
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'doc' => 'application/msword',
            'odt' => 'application/vnd.oasis.opendocument.text',
            'rtf' => 'application/rtf',
            'txt' => 'text/plain'
        ];

        if ($ext && array_key_exists($ext, $attachmentMimes)) {
            // sanitize filename
            $safeFilename = preg_replace('/[^A-Za-z0-9._-]/', '_', $basename) ?: $basename;
            // Override or set Content-Disposition as attachment (replace existing header)
            header("Content-Disposition: attachment; filename=\"{$safeFilename}\"", true);
            // Ensure correct Content-Type for the extension (replace existing header)
            header("Content-Type: " . $attachmentMimes[$ext], true);
            // Helpful cache headers for downloads
            header('Pragma: public', true);
            header('Cache-Control: private, max-age=60', true);
        }

        echo $body;
        exit;
    }

    /**
     * Handle sandbox preview requests
     * Like handleClientProxy but takes sandbox ID from URL instead of session
     * Validates that the requesting user owns this sandbox
     */
    private function handleSandboxPreview(string $sandboxId, string $path, $db): void
    {
        // Start session if not started
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        
        // Security: Validate sandbox ID belongs to current user or user is admin
        $isAdmin = !empty($_SESSION['is_admin']) ||
            (!empty($_SESSION['role_id']) && in_array((int)$_SESSION['role_id'], [1,2], true)) ||
            (!empty($_SESSION['user']['is_admin']) && $_SESSION['user']['is_admin']);
        
        $isOwner = false;
        
        // Check if user owns this sandbox - multiple checks for flexibility
        if (!empty($_SESSION['sandbox_id']) && $_SESSION['sandbox_id'] === $sandboxId) {
            $isOwner = true;
        }
        
        // Also check if we can find in database
        if (!$isOwner && !empty($_SESSION['user_id']) && $db) {
            try {
                // Check client_sandboxes table
                $stmt = $db->prepare('SELECT sandbox_id FROM client_sandboxes WHERE user_id = ? AND sandbox_id = ?');
                $stmt->execute([$_SESSION['user_id'], $sandboxId]);
                if ($stmt->fetch()) {
                    $isOwner = true;
                }
            } catch (\Throwable $e) {
                // Silently continue
            }
            
            // Also check users table (some sandboxes linked there)
            if (!$isOwner) {
                try {
                    $stmt = $db->prepare('SELECT id FROM users WHERE id = ? AND sandbox_id = ?');
                    $stmt->execute([$_SESSION['user_id'], $sandboxId]);
                    if ($stmt->fetch()) {
                        $isOwner = true;
                    }
                } catch (\Throwable $e) {
                    // Silently continue
                }
            }
        }
        
        // For development/localhost, be more lenient if the sandbox exists
        // This allows preview to work even if session tracking has issues
        $isDev = ($_SERVER['HTTP_HOST'] ?? '') === 'localhost:8000' || 
                 ($_SERVER['HTTP_HOST'] ?? '') === 'localhost';
        
        if (!$isOwner && $isDev) {
            // Check if the sandbox actually exists in the system (works for both Docker and LXD)
            try {
                if (\Ginto\Helpers\UnifiedSandbox::isRunning($sandboxId)) {
                    $isOwner = true; // Allow access in dev mode if container exists
                }
            } catch (\Throwable $e) {
                // Silently continue
            }
        }
        
        if (!$isAdmin && !$isOwner) {
            http_response_code(403);
            header('Content-Type: text/html; charset=utf-8');
            echo '<h1>403 - Forbidden</h1><p>You do not have access to this sandbox.</p>';
            exit;
        }
        
        // Validate sandbox ID format
        if (!\Ginto\Helpers\SandboxProxy::isValidSandboxId($sandboxId)) {
            http_response_code(404);
            header('Content-Type: text/html; charset=utf-8');
            echo '<h1>404 - Not Found</h1><p>Invalid sandbox identifier.</p>';
            exit;
        }
        
        // Use UnifiedSandbox to support both Docker and LXD backends
        $backend = \Ginto\Helpers\UnifiedSandbox::getBackend();
        
        // Ensure container is running
        if (!\Ginto\Helpers\UnifiedSandbox::isRunning($sandboxId)) {
            // Try starting it
            $startResult = \Ginto\Helpers\UnifiedSandbox::ensureRunning($sandboxId);
            
            if (!$startResult['success']) {
                http_response_code(503);
                header('Content-Type: text/html; charset=utf-8');
                echo '<h1>503 - Sandbox Unavailable</h1><p>Could not start sandbox. Please try again.</p>';
                exit;
            }
            
            // Wait for container to initialize
            usleep(500000); // 0.5 seconds
        }
        
        // For LXD, also ensure network routes are set up
        if ($backend === 'lxd') {
            \Ginto\Helpers\LxdSandboxManager::ensureSandboxAccessible($sandboxId);
        }
        
        // Get container IP - uses UnifiedSandbox which auto-selects backend
        $containerIp = \Ginto\Helpers\UnifiedSandbox::getIp($sandboxId);
        
        if (!$containerIp) {
            http_response_code(503);
            header('Content-Type: text/html; charset=utf-8');
            echo '<h1>503 - Sandbox Unavailable</h1><p>Could not connect to sandbox. Please try again.</p>';
            exit;
        }
        
        // Proxy directly to container
        $proxyUrl = 'http://' . $containerIp . ':80' . $path;
        
        // Forward the request using cURL
        $ch = curl_init($proxyUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $_SERVER['REQUEST_METHOD']);
        
        // Forward headers
        $headers = [];
        foreach (getallheaders() as $name => $value) {
            if (!in_array(strtolower($name), ['host', 'connection', 'content-length'], true)) {
                $headers[] = "$name: $value";
            }
        }
        $headers[] = 'X-Forwarded-For: ' . ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $headers[] = 'X-Forwarded-Host: ' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $headers[] = 'X-Original-URI: /sandbox-preview/' . $sandboxId . $path;
        $headers[] = 'X-Sandbox-ID: ' . $sandboxId;
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        // Forward body for POST/PUT/PATCH
        if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'PATCH'], true)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents('php://input'));
        }
        
        $response = curl_exec($ch);
        
        if ($response === false) {
            http_response_code(502);
            header('Content-Type: text/html; charset=utf-8');
            echo '<h1>502 - Bad Gateway</h1><p>Sandbox proxy unavailable: ' . htmlspecialchars(curl_error($ch)) . '</p>';
            curl_close($ch);
            exit;
        }
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        
        // Parse and forward response headers
        $headerText = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        
        http_response_code($httpCode);
        
        foreach (explode("\r\n", $headerText) as $line) {
            if (strpos($line, ':') !== false) {
                list($name, $value) = explode(':', $line, 2);
                $name = trim($name);
                // Skip headers that shouldn't be forwarded from the container
                // Also skip Content-Disposition so we can control inline vs attachment for previews
                if (!in_array(strtolower($name), ['transfer-encoding', 'connection', 'content-disposition'], true)) {
                    header("$name: " . trim($value));
                }
            }
        }

        // For sandbox preview we prefer inline display for common previewable types
        $previewInlineMimes = [
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml'
        ];

        $requestedExt = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($requestedExt && array_key_exists($requestedExt, $previewInlineMimes)) {
            // Force inline Content-Disposition so browsers will render in iframe
            header('Content-Disposition: inline; filename="' . basename($path) . '"', true);
            header('Content-Type: ' . $previewInlineMimes[$requestedExt], true);
        }

        echo $body;
        exit;
    }
}
