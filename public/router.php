<?php
/**
 * Router for PHP Built-in Server
 * This file routes all requests through index.php for proper handling
 * 
 * Also handles subdomain routing for cloud subdomains (*.ginto.ai)
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
$hostNoPort = preg_replace('/:\\d+$/', '', $host);

function ginto_resolve_tunnel_registry_read_path(): ?string {
    $primary = '/var/lib/ginto/tunnel-registry.json';
    $fallback = '/tmp/ginto-tunnel-registry.json';
    if (file_exists($primary)) return $primary;
    if (file_exists($fallback)) return $fallback;
    return null;
}

function ginto_tunnel_access_denied(): void {
    http_response_code(401);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Unauthorized</title></head><body style="font-family:sans-serif;padding:24px;"><h2>Unauthorized</h2><p>This tunnel requires an access key.</p></body></html>';
}

// Check if this is a subdomain request (*.ginto.ai but not ginto.ai itself)
if (preg_match('/^([a-z0-9]+)\.ginto\.ai$/', $hostNoPort, $matches)) {
    $subdomain = $matches[1];
    
    // Skip if it's oi.ginto.ai (handled by Caddy directly)
    if ($subdomain === 'oi') {
        // This shouldn't happen as Caddy handles it, but just in case
        require_once __DIR__ . '/index.php';
        return true;
    }
    
    // Check if this is a cloud subdomain
    $tunnelFile = "/tmp/ginto-cloud-tunnels/{$subdomain}.json";
    if (file_exists($tunnelFile)) {
        $tunnelData = json_decode(file_get_contents($tunnelFile), true);
        
        // Check if expired
        if (($tunnelData['expires_at'] ?? 0) > time()) {
            // Route to CloudProxyController
            $_SERVER['REQUEST_URI'] = '/cloud-proxy';
            $_GET['subdomain'] = $subdomain;
            require_once __DIR__ . '/index.php';
            return true;
        } else {
            // Expired - clean up and show error
            @unlink($tunnelFile);
        }
    }
    
    // Not a cloud subdomain - proxy to FRP server
    // FRP handles the tunnel routing
    $frpHost = '127.0.0.1';
    $frpPort = 7080;

    // Optional security layer: require access key if enabled for this subdomain.
    $regPath = ginto_resolve_tunnel_registry_read_path();
    if ($regPath) {
        $registry = json_decode((string)@file_get_contents($regPath), true);
        if (is_array($registry) && isset($registry[$subdomain]) && is_array($registry[$subdomain])) {
            $entry = $registry[$subdomain];
            $enabled = !empty($entry['access_key_enabled']);
            $hash = (string)($entry['access_key_hash'] ?? '');
            if ($enabled && $hash !== '') {
                $provided = '';
                if (!empty($_SERVER['HTTP_X_GINTO_TUNNEL_KEY'])) {
                    $provided = trim((string)$_SERVER['HTTP_X_GINTO_TUNNEL_KEY']);
                } elseif (!empty($_GET['key'])) {
                    $provided = trim((string)$_GET['key']);
                } elseif (!empty($_GET['k'])) {
                    $provided = trim((string)$_GET['k']);
                }

                if ($provided === '' || !hash_equals($hash, hash('sha256', $provided))) {
                    ginto_tunnel_access_denied();
                    return true;
                }
            }
        }
    }
    
    // Create a stream context for the proxy
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://{$frpHost}:{$frpPort}{$uri}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $_SERVER['REQUEST_METHOD'] ?? 'GET');
    
    // Forward request body
    if (in_array($_SERVER['REQUEST_METHOD'] ?? '', ['POST', 'PUT', 'PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents('php://input'));
    }
    
    // Forward headers
    $headers = [];
    foreach (getallheaders() as $name => $value) {
        if (strtolower($name) === 'host') {
            $headers[] = "Host: {$hostNoPort}";
        } else {
            $headers[] = "{$name}: {$value}";
        }
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    
    if ($response === false) {
        http_response_code(502);
        include __DIR__ . '/../tools/tunnel/frp/404.html';
        return true;
    }
    
    // Parse and output response
    $responseHeaders = substr($response, 0, $headerSize);
    $responseBody = substr($response, $headerSize);
    
    http_response_code($httpCode);
    foreach (explode("\r\n", $responseHeaders) as $line) {
        if (empty($line) || preg_match('/^HTTP\//', $line)) continue;
        $colonPos = strpos($line, ':');
        if ($colonPos === false) continue;
        $headerName = strtolower(trim(substr($line, 0, $colonPos)));
        if (in_array($headerName, ['transfer-encoding', 'connection'])) continue;
        header($line);
    }
    echo $responseBody;
    return true;
}

// Handle install requests
if (str_contains($uri, '/install/install.php')) {
    $_SERVER['REQUEST_URI'] = $uri;
    require_once __DIR__ . '/index.php';
    return true;
}

// Serve static files directly
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}

// Route everything else through index.php
require_once __DIR__ . '/index.php';