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

function ginto_bootstrap_db(): ?\Medoo\Medoo {
    static $db = null;
    static $did = false;
    if ($did) return $db;
    $did = true;

    $root = dirname(__DIR__);
    $autoload = $root . '/vendor/autoload.php';
    if (!file_exists($autoload)) {
        return null;
    }
    require_once $autoload;

    try {
        if (class_exists('Dotenv\\Dotenv')) {
            $dotenv = Dotenv\Dotenv::createImmutable($root);
            $dotenv->safeLoad();
        }
    } catch (\Exception $e) {
        // ignore
    }

    try {
        $db = \Ginto\Core\Database::getInstance();
    } catch (\Exception $e) {
        $db = null;
    }
    return $db;
}

function ginto_base64url_decode(string $s): string {
    $s = strtr($s, '-_', '+/');
    $pad = strlen($s) % 4;
    if ($pad) {
        $s .= str_repeat('=', 4 - $pad);
    }
    $out = base64_decode($s, true);
    return $out === false ? '' : $out;
}

function ginto_get_tunnel_jwt_secret(): string {
    $appKey = (string)(getenv('APP_KEY') ?: ($_ENV['APP_KEY'] ?? ''));
    if ($appKey === '') {
        return '';
    }
    return hash('sha256', $appKey . '|tunnel_access_keys', true);
}

function ginto_verify_jwt_hs256(string $jwt, string $secret): ?array {
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) return null;
    [$h64, $p64, $s64] = $parts;
    $sig = ginto_base64url_decode($s64);
    if ($sig === '') return null;
    $expected = hash_hmac('sha256', $h64 . '.' . $p64, $secret, true);
    if (!hash_equals($expected, $sig)) return null;
    $payloadJson = ginto_base64url_decode($p64);
    if ($payloadJson === '') return null;
    $payload = json_decode($payloadJson, true);
    return is_array($payload) ? $payload : null;
}

function ginto_extract_tunnel_token(): string {
    $auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if (preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
        return trim((string)$m[1]);
    }
    if (!empty($_SERVER['HTTP_X_GINTO_TUNNEL_TOKEN'])) {
        return trim((string)$_SERVER['HTTP_X_GINTO_TUNNEL_TOKEN']);
    }
    if (!empty($_COOKIE['ginto_tunnel_token'])) {
        return trim((string)$_COOKIE['ginto_tunnel_token']);
    }
    // Allow HTML form POST auth (the unauthorized page submits via POST).
    if (!empty($_POST['api_key'])) {
        return trim((string)$_POST['api_key']);
    }
    if (!empty($_POST['tunnel_token'])) {
        return trim((string)$_POST['tunnel_token']);
    }
    if (!empty($_GET['token'])) {
        return trim((string)$_GET['token']);
    }
    if (!empty($_GET['t'])) {
        return trim((string)$_GET['t']);
    }
    return '';
}

function ginto_tunnel_access_denied_key(string $message = 'This tunnel requires a valid access token.'): void {
    http_response_code(401);
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Content-Type: text/html; charset=utf-8');
    $action = htmlspecialchars((string)($_SERVER['REQUEST_URI'] ?? '/'), ENT_QUOTES, 'UTF-8');
    $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><html><head>'
        .'<meta charset="utf-8">'
        .'<meta name="viewport" content="width=device-width, initial-scale=1">'
        .'<meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">'
        .'<meta http-equiv="Pragma" content="no-cache">'
        .'<meta http-equiv="Expires" content="0">'
        .'<title>Unauthorized</title>'
        .'</head>'
        .'<body style="font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;padding:24px;max-width:720px;margin:0 auto;">'
        .'<h2 style="margin:0 0 8px 0;">Unauthorized</h2>'
        .'<p style="margin:0 0 18px 0;color:#475569;">'.$safeMessage.'</p>'
        .'<form method="POST" action="'.$action.'" autocomplete="off" '
        .'style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">'
        // Decoy fields to discourage password managers from offering/saving autofill.
        .'<input type="text" name="username" autocomplete="username" style="position:absolute;left:-9999px;top:-9999px;height:0;width:0;opacity:0;" tabindex="-1" aria-hidden="true">'
        .'<input type="password" name="password" autocomplete="new-password" style="position:absolute;left:-9999px;top:-9999px;height:0;width:0;opacity:0;" tabindex="-1" aria-hidden="true">'
        .'<div style="flex:1;min-width:240px;display:flex;align-items:center;gap:6px;">'
        .'<input id="gintoApiKey" name="api_key" type="password" autocomplete="new-password" autocorrect="off" autocapitalize="none" spellcheck="false" '
        .'data-lpignore="true" data-form-type="other" placeholder="Paste API key (gtnl-...)" '
        .'style="flex:1;padding:10px 12px;border-radius:8px;border:1px solid #cbd5e1;background:#f8fafc;" />'
        .'<button id="toggleEye" type="button" aria-label="Show/Hide" '
        .'style="padding:10px 10px;border-radius:8px;border:1px solid #cbd5e1;background:#ffffff;cursor:pointer;display:flex;align-items:center;justify-content:center;">'
        .'<svg id="eyeOpen" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
        .'<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/>'
        .'<circle cx="12" cy="12" r="3"/>'
        .'</svg>'
        .'<svg id="eyeClosed" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">'
        .'<path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.77 21.77 0 0 1 5.06-6.94"/>'
        .'<path d="M1 1l22 22"/>'
        .'<path d="M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 8 11 8a21.77 21.77 0 0 1-2.12 3.19"/>'
        .'<path d="M14.12 14.12a3 3 0 0 1-4.24-4.24"/>'
        .'</svg>'
        .'</button>'
        .'</div>'
        .'<button id="authorizeBtn" type="submit" disabled '
        .'style="padding:10px 12px;border-radius:8px;border:1px solid #cbd5e1;background:#ffffff;cursor:pointer;opacity:0.6;">Authorize</button>'
        .'</form>'
        .'<div id="timer" style="margin-top:10px;font-size:12px;color:#64748b;">Enter a key to enable Authorize (expires in <span id="sec">60</span>s).</div>'
        .'<p style="margin:14px 0 0 0;font-size:12px;color:#64748b;">This form submits via POST and sets a secure cookie for this subdomain.</p>'
        .'<script>(function(){'
        .'var input=document.getElementById("gintoApiKey");'
        .'var btn=document.getElementById("authorizeBtn");'
        .'var secEl=document.getElementById("sec");'
        .'var eyeBtn=document.getElementById("toggleEye");'
        .'var eyeOpen=document.getElementById("eyeOpen");'
        .'var eyeClosed=document.getElementById("eyeClosed");'
        .'var ttl=60; var t=null; var remaining=ttl;'
        .'function setEnabled(on){btn.disabled=!on; btn.style.opacity=on?"1":"0.6";}'
        .'function resetTimer(){remaining=ttl; if(secEl) secEl.textContent=String(remaining); if(t) clearInterval(t);'
        .'t=setInterval(function(){remaining--; if(secEl) secEl.textContent=String(Math.max(0,remaining));'
        .'if(remaining<=0){clearInterval(t); t=null; input.value=""; setEnabled(false);} },1000);}'
        .'function onChange(){var v=(input.value||"").trim(); if(v.length>0){setEnabled(true); resetTimer();} else {setEnabled(false);}}'
        .'input.addEventListener("input", onChange);'
        .'input.addEventListener("focus", function(){ if((input.value||"").trim().length>0){resetTimer();}});'
        .'eyeBtn.addEventListener("click", function(){ var isPw=input.type==="password"; input.type=isPw?"text":"password";'
        .'eyeOpen.style.display=isPw?"none":"inline"; eyeClosed.style.display=isPw?"inline":"none"; input.focus(); });'
        .'setEnabled(false);'
        .'})();</script>'
        .'</body></html>';
}

function ginto_resolve_tunnel_registry_read_path(): ?string {
    $primary = '/var/lib/ginto/tunnel-registry.json';
    $fallback = '/tmp/ginto-tunnel-registry.json';
    if (file_exists($primary)) return $primary;
    if (file_exists($fallback)) return $fallback;
    return null;
}

function ginto_tunnel_access_denied(): void { ginto_tunnel_access_denied_key(); }

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

    // Enforce MySQL-backed tunnel access key on every request.
    // Even if frpc is connected/authenticated to frps, do not serve until a valid token is presented.
    $token = ginto_extract_tunnel_token();
    if ($token === '' || !str_starts_with($token, 'gtnl-')) {
        ginto_tunnel_access_denied_key();
        return true;
    }

    $secret = ginto_get_tunnel_jwt_secret();
    if ($secret === '') {
        http_response_code(503);
        echo 'Tunnel key secret not configured';
        return true;
    }

    $jwt = substr($token, 5);
    $payload = ginto_verify_jwt_hs256($jwt, $secret);
    if (!is_array($payload)) {
        ginto_tunnel_access_denied_key('Invalid API key.');
        return true;
    }

    $sd = strtolower((string)($payload['sd'] ?? ''));
    $exp = (int)($payload['exp'] ?? 0);
    if ($sd !== $subdomain || $exp <= time()) {
        ginto_tunnel_access_denied_key('API key expired or not valid for this subdomain.');
        return true;
    }

    $db = ginto_bootstrap_db();
    if (!$db) {
        http_response_code(503);
        echo 'Tunnel key validation unavailable';
        return true;
    }

    // Verify token exists in DB (hashed) and is active.
    $hash = hash('sha256', $token);
    try {
        $row = $db->get('tunnel_access_keys', ['id', 'revoked', 'expires_at'], [
            'subdomain' => $subdomain,
            'token_hash' => $hash,
            'revoked' => 0,
        ]);
        if (!$row) {
            ginto_tunnel_access_denied_key('API key not found or revoked.');
            return true;
        }
        // Also enforce DB expires_at if present.
        if (!empty($row['expires_at']) && strtotime((string)$row['expires_at']) < time()) {
            ginto_tunnel_access_denied_key('API key expired.');
            return true;
        }
        // Update last_used_at best-effort.
        $db->update('tunnel_access_keys', ['last_used_at' => date('Y-m-d H:i:s')], ['id' => (int)$row['id']]);
    } catch (\Exception $e) {
        http_response_code(503);
        echo 'Tunnel key validation error';
        return true;
    }

    // If the key was submitted via POST form, treat it as an auth handshake:
    // set an HttpOnly cookie and redirect to GET so the original POST is not proxied upstream.
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method === 'POST' && (!empty($_POST['api_key']) || !empty($_POST['tunnel_token']))) {
        $cookieValue = $token;
        $cookieName = 'ginto_tunnel_token';
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $cookieParams = [
            'expires' => time() + 86400 * 7,
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        @setcookie($cookieName, $cookieValue, $cookieParams);

        // Redirect back to the same path (without leaking token in query strings).
        $redirectTo = (string)($_SERVER['REQUEST_URI'] ?? '/');
        // If someone posted to a URL that already had token=..., strip it.
        $parts = parse_url($redirectTo);
        $path = $parts['path'] ?? '/';
        $query = $parts['query'] ?? '';
        if ($query !== '') {
            parse_str($query, $qs);
            unset($qs['token'], $qs['t']);
            $query = http_build_query($qs);
        }
        $location = $path . ($query !== '' ? ('?' . $query) : '');

        http_response_code(303);
        header('Location: ' . $location);
        return true;
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
        } elseif (strtolower($name) === 'cookie') {
            // Do not leak the tunnel auth cookie to the user's backend.
            $cookie = (string)$value;
            $cookie = preg_replace('/(?:^|;\s*)ginto_tunnel_token=[^;]*/', '', $cookie);
            $cookie = trim((string)preg_replace('/^;+|;+$/', '', $cookie));
            if ($cookie !== '') {
                $headers[] = 'Cookie: ' . $cookie;
            }
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