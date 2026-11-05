<?php
/**
 * Cloud Proxy Controller
 * 
 * Handles proxying requests from cloud subdomains (*.silverqueen.pro) to sandbox containers.
 * This is called by Caddy for cloud subdomains that are not FRP tunnels.
 */

declare(strict_types=1);

namespace Ginto\Controllers;

class CloudProxyController
{
    protected ?\Medoo\Medoo $db;
    
    public function __construct(?\Medoo\Medoo $db = null)
    {
        $this->db = $db;
    }
    
    /**
     * Get routing information for a cloud subdomain
     * Called by Caddy to determine where to proxy
     * GET /api/cloud/route?domain=subdomain.silverqueen.pro
     */
    public function getRoute(): void
    {
        header('Content-Type: application/json');
        
        $domain = $_GET['domain'] ?? $_SERVER['HTTP_X_ORIGINAL_HOST'] ?? '';
        
        if (!preg_match('/^([a-z0-9]+)\.ginto\.ai$/', $domain, $matches)) {
            http_response_code(404);
            echo json_encode(['found' => false, 'reason' => 'invalid_domain']);
            return;
        }
        
        $subdomain = $matches[1];
        
        // Check if this is a cloud subdomain
        $tunnelInfo = $this->getCloudTunnelInfo($subdomain);
        
        if (!$tunnelInfo) {
            http_response_code(404);
            echo json_encode(['found' => false, 'reason' => 'not_found']);
            return;
        }
        
        // Check expiry
        if (($tunnelInfo['expires_at'] ?? 0) < time()) {
            @unlink("/tmp/ginto-cloud-tunnels/{$subdomain}.json");
            http_response_code(410);
            echo json_encode(['found' => false, 'reason' => 'expired']);
            return;
        }
        
        // Return routing info
        echo json_encode([
            'found' => true,
            'subdomain' => $subdomain,
            'target_ip' => $tunnelInfo['target_ip'] ?? null,
            'target_port' => $tunnelInfo['target_port'] ?? 80,
            'expires_at' => $tunnelInfo['expires_at'] ?? 0,
            'type' => 'cloud'
        ]);
    }
    
    /**
     * Handle incoming request to a cloud subdomain
     * Routes to the appropriate sandbox container
     */
    public function proxy(): void
    {
        // Get the subdomain from the Host header or from the router
        $subdomain = $_GET['subdomain'] ?? null;
        
        if (!$subdomain) {
            $host = $_SERVER['HTTP_HOST'] ?? '';
            if (preg_match('/^([a-z0-9]+)\.ginto\.ai$/', $host, $matches)) {
                $subdomain = $matches[1];
            }
        }
        
        if (!$subdomain) {
            http_response_code(400);
            $this->renderError('Invalid cloud subdomain', 400);
            return;
        }
        
        // Check if this is a cloud subdomain (not FRP tunnel)
        $tunnelInfo = $this->getCloudTunnelInfo($subdomain);
        
        if (!$tunnelInfo) {
            http_response_code(404);
            $this->renderError('Cloud subdomain not found or expired', 404, $subdomain);
            return;
        }
        
        // Check expiry
        if ($tunnelInfo['expires_at'] < time()) {
            // Clean up expired tunnel
            @unlink("/tmp/ginto-cloud-tunnels/{$subdomain}.json");
            http_response_code(410);
            $this->renderError('Cloud subdomain has expired', 410, $subdomain);
            return;
        }
        
        // Get sandbox IP from tunnel info
        $sandboxIp = $tunnelInfo['target_ip'] ?? null;
        $sandboxPort = $tunnelInfo['target_port'] ?? 80;
        
        if (!$sandboxIp) {
            http_response_code(502);
            $this->renderError('Sandbox IP not available', 502, $subdomain);
            return;
        }
        
        // Proxy the request to the sandbox
        $this->proxyRequest($sandboxIp, $sandboxPort);
    }
    
    /**
     * Get cloud tunnel information from file
     */
    private function getCloudTunnelInfo(string $subdomain): ?array
    {
        $tunnelFile = "/tmp/ginto-cloud-tunnels/{$subdomain}.json";
        
        if (!file_exists($tunnelFile)) {
            return null;
        }
        
        $data = @file_get_contents($tunnelFile);
        if (!$data) {
            return null;
        }
        
        $info = json_decode($data, true);
        if (!is_array($info)) {
            return null;
        }
        
        return $info;
    }
    
    /**
     * Proxy the current HTTP request to the sandbox
     */
    private function proxyRequest(string $targetIp, int $targetPort): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = $_SERVER['REQUEST_URI'] ?? '/';
        $targetUrl = "http://{$targetIp}:{$targetPort}{$path}";
        
        // Initialize cURL
        $ch = curl_init();
        
        // Set URL
        curl_setopt($ch, CURLOPT_URL, $targetUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        
        // Set method
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        
        // Forward request body for POST/PUT/PATCH
        if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $body = file_get_contents('php://input');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        
        // Forward headers (with some filtering)
        $headers = [];
        foreach (getallheaders() as $name => $value) {
            $lowerName = strtolower($name);
            // Skip hop-by-hop headers and host
            if (in_array($lowerName, ['host', 'connection', 'keep-alive', 'transfer-encoding', 'te', 'trailer', 'upgrade', 'proxy-authorization', 'proxy-authenticate'])) {
                continue;
            }
            $headers[] = "{$name}: {$value}";
        }
        
        // Add forwarded headers
        $headers[] = "X-Forwarded-For: " . ($_SERVER['REMOTE_ADDR'] ?? '');
        $headers[] = "X-Forwarded-Host: " . ($_SERVER['HTTP_HOST'] ?? '');
        $headers[] = "X-Forwarded-Proto: https";
        $headers[] = "X-Real-IP: " . ($_SERVER['REMOTE_ADDR'] ?? '');
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        // Execute request
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        
        if ($response === false) {
            http_response_code(502);
            $this->renderError("Failed to connect to sandbox: {$error}", 502);
            return;
        }
        
        // Split headers and body
        $responseHeaders = substr($response, 0, $headerSize);
        $responseBody = substr($response, $headerSize);
        
        // Set response status
        http_response_code($httpCode);
        
        // Forward response headers
        $headerLines = explode("\r\n", $responseHeaders);
        foreach ($headerLines as $line) {
            if (empty($line)) continue;
            
            // Skip status line
            if (preg_match('/^HTTP\//', $line)) continue;
            
            // Parse header
            $colonPos = strpos($line, ':');
            if ($colonPos === false) continue;
            
            $headerName = trim(substr($line, 0, $colonPos));
            $headerValue = trim(substr($line, $colonPos + 1));
            
            // Skip hop-by-hop headers
            $lowerName = strtolower($headerName);
            if (in_array($lowerName, ['transfer-encoding', 'connection', 'keep-alive'])) {
                continue;
            }
            
            header("{$headerName}: {$headerValue}");
        }
        
        // Output response body
        echo $responseBody;
    }
    
    /**
     * Render a styled error page
     */
    private function renderError(string $message, int $code, ?string $subdomain = null): void
    {
        $title = match($code) {
            400 => 'Bad Request',
            404 => 'Not Found',
            410 => 'Gone',
            502 => 'Bad Gateway',
            default => 'Error'
        };
        
        $suggestion = match($code) {
            404 => 'The cloud subdomain you requested does not exist. It may have expired or never existed.',
            410 => 'The cloud subdomain has expired. Cloud subdomains are temporary (5 minutes). Please generate a new one from the Ginto Cloud tab.',
            502 => 'The sandbox is not responding. Please make sure your sandbox is running.',
            default => 'Please try again or contact support.'
        };
        
        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title} - Ginto Cloud</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: #fff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            text-align: center;
            max-width: 500px;
        }
        .icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        h1 {
            font-size: 2rem;
            margin-bottom: 10px;
            color: #f43f5e;
        }
        .code {
            font-size: 4rem;
            font-weight: bold;
            color: #38bdf8;
            margin-bottom: 20px;
        }
        p {
            color: #94a3b8;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .subdomain {
            font-family: monospace;
            background: rgba(56, 189, 248, 0.2);
            padding: 2px 8px;
            border-radius: 4px;
            color: #38bdf8;
        }
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: #fff;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(99, 102, 241, 0.4);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">☁️</div>
        <div class="code">{$code}</div>
        <h1>{$title}</h1>
        <p>{$message}</p>
        <p>{$suggestion}</p>
        <a href="https://silverqueen.pro/chat" class="btn">← Back to Ginto AI</a>
    </div>
</body>
</html>
HTML;
    }
}
