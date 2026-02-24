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

function ginto_frp_dashboard_pwd(): string {
    $pwd = trim((string)(getenv('FRP_DASHBOARD_PWD') ?: ($_ENV['FRP_DASHBOARD_PWD'] ?? '')));
    if ($pwd !== '') return $pwd;

    $envFile = '/etc/frp/frps.env';
    if (!file_exists($envFile) || !is_readable($envFile)) return '';
    $lines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) return '';
    foreach ($lines as $line) {
        $line = trim((string)$line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (!str_starts_with($line, 'FRP_DASHBOARD_PWD=')) continue;
        return trim(substr($line, strlen('FRP_DASHBOARD_PWD=')));
    }
    return '';
}

function ginto_frp_fetch_online_key_hashes(): array {
    $pwd = ginto_frp_dashboard_pwd();
    if ($pwd === '') return [];

    $out = [];
    foreach (['/api/proxy/http', '/api/proxy/https'] as $endpoint) {
        $ch = curl_init('http://127.0.0.1:7500' . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
        curl_setopt($ch, CURLOPT_USERPWD, 'admin:' . $pwd);
        $resp = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($resp === false || $code < 200 || $code >= 300) continue;

        $decoded = json_decode((string)$resp, true);
        $proxies = is_array($decoded) ? ($decoded['proxies'] ?? null) : null;
        if (!is_array($proxies)) continue;
        foreach ($proxies as $proxy) {
            if (!is_array($proxy) || (string)($proxy['status'] ?? '') !== 'online') continue;
            $conf = is_array($proxy['conf'] ?? null) ? $proxy['conf'] : [];
            $sd = strtolower(trim((string)($conf['subdomain'] ?? '')));
            if ($sd === '') continue;
            $rawKey = '';
            $metas = $conf['metas'] ?? null;
            if (is_array($metas)) {
                $rawKey = trim((string)($metas['ginto_key'] ?? $metas['ginto-key'] ?? ''));
            }
            if ($rawKey === '' && isset($conf['meta_ginto_key'])) {
                $rawKey = trim((string)$conf['meta_ginto_key']);
            }
            if ($rawKey !== '') {
                $out[$sd] = hash('sha256', $rawKey);
            }
        }
    }
    return $out;
}

function ginto_frp_read_key_cache(int $maxAgeSeconds): ?array {
    $file = '/tmp/ginto-frp-online-keys.json';
    if (!file_exists($file)) return null;
    $mtime = @filemtime($file);
    if (!is_int($mtime) || (time() - $mtime) > $maxAgeSeconds) return null;
    $decoded = json_decode((string)@file_get_contents($file), true);
    if (!is_array($decoded)) return null;
    $keys = $decoded['keys'] ?? null;
    return is_array($keys) ? $keys : null;
}

function ginto_frp_write_key_cache(array $keysBySubdomain): void {
    @file_put_contents('/tmp/ginto-frp-online-keys.json', json_encode([
        'updated_at' => time(),
        'keys' => $keysBySubdomain,
    ]));
}

function ginto_frp_get_online_key_hashes_cached(int $ttlSeconds = 2): array {
    $cached = ginto_frp_read_key_cache($ttlSeconds);
    if ($cached !== null) return $cached;

    $lock = @fopen('/tmp/ginto-frp-online-keys.lock', 'c');
    if ($lock === false) {
        return ginto_frp_fetch_online_key_hashes();
    }
    if (@flock($lock, LOCK_EX)) {
        $cached = ginto_frp_read_key_cache($ttlSeconds);
        if ($cached !== null) {
            @flock($lock, LOCK_UN);
            @fclose($lock);
            return $cached;
        }
        $fresh = ginto_frp_fetch_online_key_hashes();
        ginto_frp_write_key_cache($fresh);
        @flock($lock, LOCK_UN);
        @fclose($lock);
        return $fresh;
    }
    @fclose($lock);
    return ginto_frp_fetch_online_key_hashes();
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

    // Operator-key security layer: if enabled, only proxy when the *connected FRP proxy*
    // presents the matching key via proxy metas in frpc.toml.
    $regPath = ginto_resolve_tunnel_registry_read_path();
    if ($regPath) {
        $registry = json_decode((string)@file_get_contents($regPath), true);
        if (is_array($registry) && isset($registry[$subdomain]) && is_array($registry[$subdomain])) {
            $entry = $registry[$subdomain];
            $enabled = !empty($entry['access_key_enabled']);
            $hash = (string)($entry['access_key_hash'] ?? '');
            if ($enabled && $hash !== '') {
                $keys = ginto_frp_get_online_key_hashes_cached(2);
                $seen = (string)($keys[$subdomain] ?? '');
                if ($seen === '' || !hash_equals($hash, $seen)) {
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