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

function ginto_env(string $key): string {
    $v = (string)(getenv($key) ?: ($_ENV[$key] ?? ''));
    if (trim($v) !== '') {
        return trim($v);
    }

    static $cache = null;
    if ($cache === null) {
        $cache = [];
        $envFile = dirname(__DIR__) . '/.env';
        if (file_exists($envFile) && is_readable($envFile)) {
            $lines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (is_array($lines)) {
                foreach ($lines as $line) {
                    $line = trim((string)$line);
                    if ($line === '' || str_starts_with($line, '#')) continue;
                    if (strpos($line, '=') === false) continue;
                    [$k, $val] = explode('=', $line, 2);
                    $k = trim((string)$k);
                    $val = trim((string)$val);
                    if ($k !== '') {
                        $cache[$k] = $val;
                    }
                }
            }
        }
    }

    $v2 = (string)($cache[$key] ?? '');
    return trim($v2);
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
    $appKey = ginto_env('APP_KEY');
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

function ginto_set_tunnel_auth_cookie(string $token, int $expiresAt): void {
    if ($token === '' || $expiresAt <= time()) {
        return;
    }
    $cookieName = 'ginto_tunnel_token';
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    @setcookie($cookieName, $token, [
        'expires' => $expiresAt,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function ginto_get_active_tunnel_key_row_for_subdomain(\Medoo\Medoo $db, string $subdomain): ?array {
    try {
        $row = $db->get('tunnel_access_keys', [
            'id',
            'user_id',
            'subdomain',
            'jti',
            'created_at',
            'expires_at',
            'revoked',
        ], [
            'subdomain' => $subdomain,
            'revoked' => 0,
            'ORDER' => ['id' => 'DESC'],
        ]);
        if (!is_array($row) || empty($row)) {
            return null;
        }
        if (!empty($row['expires_at']) && strtotime((string)$row['expires_at']) <= time()) {
            return null;
        }
        return $row;
    } catch (\Throwable $_) {
        return null;
    }
}

function ginto_build_tunnel_token_from_row(array $row): string {
    $secret = ginto_get_tunnel_jwt_secret();
    if ($secret === '') {
        return '';
    }

    $subdomain = strtolower(trim((string)($row['subdomain'] ?? '')));
    $jti = trim((string)($row['jti'] ?? ''));
    $userId = (int)($row['user_id'] ?? 0);
    $expTs = !empty($row['expires_at']) ? strtotime((string)$row['expires_at']) : false;
    if ($subdomain === '' || $jti === '' || $userId <= 0 || !is_int($expTs) || $expTs <= time()) {
        return '';
    }

    $iatTs = !empty($row['created_at']) ? strtotime((string)$row['created_at']) : false;
    if (!is_int($iatTs) || $iatTs <= 0) {
        $iatTs = max(1, $expTs - 60);
    }

    $header = ['alg' => 'HS256', 'typ' => 'JWT'];
    $payload = [
        'iss' => 'ginto.ai',
        'sub' => $userId,
        'sd' => $subdomain,
        'jti' => $jti,
        'iat' => $iatTs,
        'exp' => $expTs,
    ];

    $h = rtrim(strtr(base64_encode(json_encode($header, JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');
    $p = rtrim(strtr(base64_encode(json_encode($payload, JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');
    $sig = hash_hmac('sha256', $h . '.' . $p, $secret, true);
    $s = rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');

    return 'gtnl-' . $h . '.' . $p . '.' . $s;
}

function ginto_extract_tunnel_token(): string {
    $auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
    if ($auth === '' && function_exists('getallheaders')) {
        $headers = getallheaders();
        if (is_array($headers)) {
            foreach ($headers as $name => $value) {
                if (strtolower((string)$name) === 'authorization') {
                    $auth = (string)$value;
                    break;
                }
            }
        }
    }
    if (preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
        return trim((string)$m[1]);
    }
    if (!empty($_SERVER['HTTP_X_GINTO_TUNNEL_KEY'])) {
        return trim((string)$_SERVER['HTTP_X_GINTO_TUNNEL_KEY']);
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
    // Visible unauthorized form field (intentionally not named api_key to avoid autofill).
    if (!empty($_POST['ginto_key'])) {
        return trim((string)$_POST['ginto_key']);
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

function ginto_detect_tunnel_token_source(): string {
    $auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
    if ($auth === '' && function_exists('getallheaders')) {
        $headers = getallheaders();
        if (is_array($headers)) {
            foreach ($headers as $name => $value) {
                if (strtolower((string)$name) === 'authorization') {
                    $auth = (string)$value;
                    break;
                }
            }
        }
    }
    if ($auth !== '' && preg_match('/^Bearer\s+(.+)$/i', $auth)) {
        return 'bearer';
    }
    if (!empty($_SERVER['HTTP_X_GINTO_TUNNEL_KEY'])) {
        return 'header';
    }
    if (!empty($_SERVER['HTTP_X_GINTO_TUNNEL_TOKEN'])) {
        return 'header';
    }
    if (!empty($_COOKIE['ginto_tunnel_token'])) {
        return 'cookie';
    }
    if (!empty($_POST['api_key']) || !empty($_POST['ginto_key']) || !empty($_POST['tunnel_token'])) {
        return 'post';
    }
    if (!empty($_GET['token']) || !empty($_GET['t'])) {
        return 'get';
    }
    return 'none';
}

function ginto_clear_tunnel_auth_cookie(): void {
    $cookieName = 'ginto_tunnel_token';
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    @setcookie($cookieName, '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
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
        // Favicon: inline data URI so it only applies to the unauthorized page and doesn't require loading assets.
        .'<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg%20xmlns%3D%27http%3A//www.w3.org/2000/svg%27%20viewBox%3D%270%200%2032%2032%27%3E%3Crect%20width%3D%2732%27%20height%3D%2732%27%20rx%3D%278%27%20fill%3D%27%230f172a%27/%3E%3Ctext%20x%3D%2716%27%20y%3D%2721%27%20text-anchor%3D%27middle%27%20font-family%3D%27system-ui%2C%20-apple-system%2C%20Segoe%20UI%2C%20Roboto%2C%20sans-serif%27%20font-size%3D%2716%27%20font-weight%3D%27700%27%20fill%3D%27%23ffffff%27%3EG%3C/text%3E%3C/svg%3E">'
        .'<link rel="shortcut icon" href="data:image/svg+xml,%3Csvg%20xmlns%3D%27http%3A//www.w3.org/2000/svg%27%20viewBox%3D%270%200%2032%2032%27%3E%3Crect%20width%3D%2732%27%20height%3D%2732%27%20rx%3D%278%27%20fill%3D%27%230f172a%27/%3E%3Ctext%20x%3D%2716%27%20y%3D%2721%27%20text-anchor%3D%27middle%27%20font-family%3D%27system-ui%2C%20-apple-system%2C%20Segoe%20UI%2C%20Roboto%2C%20sans-serif%27%20font-size%3D%2716%27%20font-weight%3D%27700%27%20fill%3D%27%23ffffff%27%3EG%3C/text%3E%3C/svg%3E">'
        .'<title>Unauthorized</title>'
        .'</head>'
        .'<body style="font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#f1f5f9;min-height:100vh;margin:0;">'
        .'<div style="max-width:720px;margin:0 auto;padding:36px 20px;">'
        .'<div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;padding:24px;">'
        .'<div style="font-size:12px;letter-spacing:0.08em;text-transform:uppercase;color:#64748b;margin-bottom:10px;">Tunnel Access</div>'
        .'<h2 style="margin:0 0 8px 0;font-size:34px;line-height:1.1;color:#0f172a;">Unauthorized</h2>'
        .'<p style="margin:0 0 18px 0;color:#475569;font-size:15px;">'.$safeMessage.'</p>'
        .'<form id="gintoAuthForm" method="POST" action="'.$action.'" autocomplete="off" novalidate '
        .'style="display:flex;gap:10px;align-items:stretch;flex-wrap:wrap;">'
        // Decoy fields to discourage password managers from offering/saving autofill.
        .'<input type="text" name="username" autocomplete="username" style="position:absolute;left:-9999px;top:-9999px;height:0;width:0;opacity:0;" tabindex="-1" aria-hidden="true">'
        .'<input type="password" name="password" autocomplete="new-password" style="position:absolute;left:-9999px;top:-9999px;height:0;width:0;opacity:0;" tabindex="-1" aria-hidden="true">'
        .'<div style="flex:1;min-width:260px;">'
        // Real POST field (hidden) the server reads.
        .'<input id="gintoApiKeyHidden" name="api_key" type="hidden" value="">'
        // Visible field: intentionally NOT named api_key to avoid password manager autofill.
        // readonly until focus also prevents many managers from auto-filling.
        .'<div style="position:relative;">'
        // Use type="text" + CSS text-security masking to avoid triggering browser password autofill UI.
        .'<input id="gintoApiKey" name="ginto_key" type="text" '
        .'autocomplete="off" autocorrect="off" autocapitalize="none" spellcheck="false" inputmode="text" '
        .'data-lpignore="true" data-form-type="other" data-1p-ignore="true" '
        .'placeholder="Paste API key (gtnl-...)" '
        .'style="width:100%;height:44px;box-sizing:border-box;padding:10px 44px 10px 12px;border-radius:12px;border:1px solid #cbd5e1;background:#ffffff;color:#0f172a;font-size:15px;-webkit-text-security:disc;-moz-text-security:disc;" />'
        .'<button id="toggleEye" type="button" aria-label="Show/Hide" '
        .'style="position:absolute;top:50%;right:10px;transform:translateY(-50%);padding:6px;border-radius:8px;border:0;background:transparent;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#64748b;">'
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
        .'</div>'
        .'<button id="authorizeBtn" type="submit" disabled '
        .'style="height:44px;padding:10px 14px;border-radius:12px;border:1px solid #cbd5e1;background:#ffffff;color:#0f172a;cursor:pointer;opacity:0.6;font-weight:600;">Authorize</button>'
        .'</form>'
        .'<div id="timer" style="margin-top:10px;font-size:13px;color:#64748b;">Enter a key to enable Authorize (expires in <span id="sec">60</span>s).</div>'
        .'<p style="margin:14px 0 0 0;font-size:12px;color:#64748b;">This form submits via POST and sets a secure cookie for this subdomain.</p>'
        .'<script>(function(){'
        .'var input=document.getElementById("gintoApiKey");'
        .'var hidden=document.getElementById("gintoApiKeyHidden");'
        .'var form=document.getElementById("gintoAuthForm");'
        .'var btn=document.getElementById("authorizeBtn");'
        .'var secEl=document.getElementById("sec");'
        .'var eyeBtn=document.getElementById("toggleEye");'
        .'var eyeOpen=document.getElementById("eyeOpen");'
        .'var eyeClosed=document.getElementById("eyeClosed");'
        .'var ttl=60; var t=null; var remaining=ttl;'
        .'function setEnabled(on){btn.disabled=!on; btn.style.opacity=on?"1":"0.6";'
        .'btn.style.background=on?"#0f172a":"#ffffff"; btn.style.borderColor=on?"#0f172a":"#cbd5e1"; btn.style.color=on?"#ffffff":"#0f172a";}'
        .'function resetTimer(){remaining=ttl; if(secEl) secEl.textContent=String(remaining); if(t) clearInterval(t);'
        .'t=setInterval(function(){remaining--; if(secEl) secEl.textContent=String(Math.max(0,remaining));'
        .'if(remaining<=0){clearInterval(t); t=null; input.value=""; setEnabled(false);} },1000);}'
        .'function onChange(){var v=(input.value||"").trim(); if(v.length>0){setEnabled(true); resetTimer();} else {setEnabled(false);}}'
        .'input.addEventListener("focus", function(){ if((input.value||"").trim().length>0){resetTimer();}});'
        .'input.addEventListener("input", onChange);'
        .'form.addEventListener("submit", function(){ var v=(input.value||"").trim(); if(hidden){ hidden.value=v; } setTimeout(function(){ try{ input.value=""; }catch(e){} }, 50); });'
        .'eyeBtn.addEventListener("click", function(){'
        .'var masked=(input.style.webkitTextSecurity==="disc"||input.style.MozTextSecurity==="disc"||input.style.textSecurity==="disc");'
        .'if(masked){ input.style.webkitTextSecurity="none"; input.style.MozTextSecurity="none"; input.style.textSecurity="none";'
        .'eyeOpen.style.display="none"; eyeClosed.style.display="inline";'
        .'} else { input.style.webkitTextSecurity="disc"; input.style.MozTextSecurity="disc"; input.style.textSecurity="disc";'
        .'eyeOpen.style.display="inline"; eyeClosed.style.display="none";'
        .'} input.focus(); });'
        .'setEnabled(false);'
        .'})();</script>'
        .'</div>'
        .'</div>'
        .'</div>'
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

function ginto_frp_fetch_online_proxy_types(): array {
    $pwd = ginto_frp_dashboard_pwd();
    if ($pwd === '') return [];

    $out = [];
    $endpointTypeMap = [
        '/api/proxy/http' => 'http',
        '/api/proxy/https' => 'https',
    ];

    foreach ($endpointTypeMap as $endpoint => $type) {
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
            if (!isset($out[$sd])) {
                $out[$sd] = ['http' => false, 'https' => false];
            }
            $out[$sd][$type] = true;
        }
    }

    return $out;
}

function ginto_frp_read_proxy_type_cache(int $maxAgeSeconds): ?array {
    $file = '/tmp/ginto-frp-online-proxy-types.json';
    if (!file_exists($file)) return null;
    $mtime = @filemtime($file);
    if (!is_int($mtime) || (time() - $mtime) > $maxAgeSeconds) return null;
    $decoded = json_decode((string)@file_get_contents($file), true);
    if (!is_array($decoded)) return null;
    $types = $decoded['types'] ?? null;
    return is_array($types) ? $types : null;
}

function ginto_frp_write_proxy_type_cache(array $typesBySubdomain): void {
    @file_put_contents('/tmp/ginto-frp-online-proxy-types.json', json_encode([
        'updated_at' => time(),
        'types' => $typesBySubdomain,
    ]));
}

function ginto_frp_get_online_proxy_types_cached(int $ttlSeconds = 2): array {
    $cached = ginto_frp_read_proxy_type_cache($ttlSeconds);
    if ($cached !== null) return $cached;

    $lock = @fopen('/tmp/ginto-frp-online-proxy-types.lock', 'c');
    if ($lock === false) {
        return ginto_frp_fetch_online_proxy_types();
    }
    if (@flock($lock, LOCK_EX)) {
        $cached = ginto_frp_read_proxy_type_cache($ttlSeconds);
        if ($cached !== null) {
            @flock($lock, LOCK_UN);
            @fclose($lock);
            return $cached;
        }
        $fresh = ginto_frp_fetch_online_proxy_types();
        ginto_frp_write_proxy_type_cache($fresh);
        @flock($lock, LOCK_UN);
        @fclose($lock);
        return $fresh;
    }
    @fclose($lock);
    return ginto_frp_fetch_online_proxy_types();
}

function ginto_is_https_request(): bool {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    return strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

function ginto_get_frp_upstream_for_subdomain(string $subdomain): array {
    $frpHost = ginto_env('FRP_VHOST_HOST');
    if ($frpHost === '') {
        $frpHost = '127.0.0.1';
    }

    $httpPort = (int)(ginto_env('FRP_VHOST_HTTP_PORT') ?: '7080');
    if ($httpPort < 1 || $httpPort > 65535) {
        $httpPort = 7080;
    }

    $httpsPort = (int)(ginto_env('FRP_VHOST_HTTPS_PORT') ?: '7443');
    if ($httpsPort < 1 || $httpsPort > 65535) {
        $httpsPort = 7443;
    }

    $proxyTypes = ginto_frp_get_online_proxy_types_cached();
    $state = is_array($proxyTypes[$subdomain] ?? null)
        ? $proxyTypes[$subdomain]
        : ['http' => false, 'https' => false];

    $preferHttps = ginto_is_https_request();
    $hasHttps = !empty($state['https']);
    $hasHttp = !empty($state['http']);

    if ($preferHttps && $hasHttps) {
        return ['host' => $frpHost, 'port' => $httpPort, 'scheme' => 'http'];
    }
    if ($hasHttp) {
        return ['host' => $frpHost, 'port' => $httpPort, 'scheme' => 'http'];
    }
    if ($hasHttps) {
        return ['host' => $frpHost, 'port' => $httpPort, 'scheme' => 'http'];
    }

    return ['host' => $frpHost, 'port' => $httpPort, 'scheme' => 'http'];
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
    $frpUpstream = ginto_get_frp_upstream_for_subdomain($subdomain);
    $frpHost = (string)($frpUpstream['host'] ?? '127.0.0.1');
    $frpPort = (int)($frpUpstream['port'] ?? 7080);
    $frpScheme = (string)($frpUpstream['scheme'] ?? 'http');

    // Enforce MySQL-backed tunnel access key on every request.
    // Even if frpc is connected/authenticated to frps, do not serve until a valid token is presented.
    $db = ginto_bootstrap_db();
    if (!$db) {
        http_response_code(503);
        echo 'Tunnel key validation unavailable';
        return true;
    }

    $token = ginto_extract_tunnel_token();
    $tokenSource = ginto_detect_tunnel_token_source();
    if ($token === '' || !str_starts_with($token, 'gtnl-')) {
        // Cross-device / cross-client fallback: if a subdomain has an active key in DB,
        // issue a deterministic token from the DB record and authorize this request.
        $row = ginto_get_active_tunnel_key_row_for_subdomain($db, $subdomain);
        $dbToken = $row ? ginto_build_tunnel_token_from_row($row) : '';
        if ($dbToken !== '' && str_starts_with($dbToken, 'gtnl-')) {
            $token = $dbToken;
            $tokenSource = 'db';
            $expTs = !empty($row['expires_at']) ? strtotime((string)$row['expires_at']) : false;
            if (is_int($expTs) && $expTs > time()) {
                ginto_set_tunnel_auth_cookie($dbToken, $expTs);
            }
        }
    }

    if ($token === '' || !str_starts_with($token, 'gtnl-')) {
        if ($tokenSource === 'cookie') {
            ginto_clear_tunnel_auth_cookie();
        }
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method === 'POST' && (!empty($_POST['api_key']) || !empty($_POST['ginto_key']) || !empty($_POST['tunnel_token']))) {
            ginto_tunnel_access_denied_key('That key is not a tunnel key. Use a tunnel key that starts with gtnl- from /account/keys.');
        } else {
            ginto_tunnel_access_denied_key();
        }
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
        if ($tokenSource === 'cookie') {
            ginto_clear_tunnel_auth_cookie();
        }
        ginto_tunnel_access_denied_key('API key expired or not valid for this subdomain.');
        return true;
    }

    // Verify token exists in DB and is active.
    $hash = hash('sha256', $token);
    $jti = trim((string)($payload['jti'] ?? ''));
    $subClaimUserId = (int)($payload['sub'] ?? 0);
    try {
        $row = null;
        if ($jti !== '') {
            $where = [
                'subdomain' => $subdomain,
                'jti' => $jti,
                'revoked' => 0,
                'ORDER' => ['id' => 'DESC'],
            ];
            if ($subClaimUserId > 0) {
                $where['user_id'] = $subClaimUserId;
            }
            $byJti = $db->get('tunnel_access_keys', ['id', 'revoked', 'expires_at'], $where);
            if (is_array($byJti) && !empty($byJti)) {
                $row = $byJti;
            }
        }

        if (!$row) {
            $byHash = $db->get('tunnel_access_keys', ['id', 'revoked', 'expires_at'], [
                'subdomain' => $subdomain,
                'token_hash' => $hash,
                'revoked' => 0,
                'ORDER' => ['id' => 'DESC'],
            ]);
            if (is_array($byHash) && !empty($byHash)) {
                $row = $byHash;
            }
        }

        if (!$row) {
            if ($tokenSource === 'cookie') {
                ginto_clear_tunnel_auth_cookie();
            }
            ginto_tunnel_access_denied_key('API key not found or revoked.');
            return true;
        }
        // Also enforce DB expires_at if present.
        if (!empty($row['expires_at']) && strtotime((string)$row['expires_at']) < time()) {
            if ($tokenSource === 'cookie') {
                ginto_clear_tunnel_auth_cookie();
            }
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

    $effectiveExpiry = $exp;
    if (!empty($row['expires_at'])) {
        $dbExp = strtotime((string)$row['expires_at']);
        if (is_int($dbExp) && $dbExp > 0) {
            $effectiveExpiry = min($effectiveExpiry, $dbExp);
        }
    }

    // If a key was provided by GET/POST/header, persist it in a secure cookie for
    // the remaining key lifetime on this subdomain.
    if (in_array($tokenSource, ['get', 'post', 'bearer', 'header', 'db'], true) && $effectiveExpiry > time()) {
        ginto_set_tunnel_auth_cookie($token, $effectiveExpiry);
    }

    // If the key was submitted via POST form, treat it as an auth handshake:
    // redirect to GET so the original POST is not proxied upstream.
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method === 'POST' && (!empty($_POST['api_key']) || !empty($_POST['ginto_key']) || !empty($_POST['tunnel_token']))) {
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
    if ($frpScheme === 'https') {
        $upstreamHost = $hostNoPort;
        curl_setopt($ch, CURLOPT_URL, "https://{$upstreamHost}:{$frpPort}{$uri}");
        curl_setopt($ch, CURLOPT_RESOLVE, ["{$upstreamHost}:{$frpPort}:{$frpHost}"]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    } else {
        curl_setopt($ch, CURLOPT_URL, "http://{$frpHost}:{$frpPort}{$uri}");
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $requestMethod = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $requestMethod);
    if ($requestMethod === 'HEAD') {
        curl_setopt($ch, CURLOPT_NOBODY, true);
    }
    
    // Forward request body
    if (in_array($requestMethod, ['POST', 'PUT', 'PATCH'], true)) {
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