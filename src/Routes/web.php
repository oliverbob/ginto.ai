<?php
// src/Routes/web.php
// Centralized route definitions for Ginto CMS

// ============================================
// TEST ROUTE - Using new simplified req() syntax
// This demonstrates both syntaxes:
//   1. $router->req() - direct method on router
//   2. req($router, ...) - helper function
// ============================================
$router->req('/test', 'TestController@test');

// Serve role-based prompts for chat UI
$router->req('/chat/prompts/', 'PromptsController@getPrompts');

use Core\Router;
use Ginto\Helpers\TransactionHelper;

// Global registry for all routes
global $ROUTE_REGISTRY;
if (!isset($ROUTE_REGISTRY)) {
    $ROUTE_REGISTRY = [];
}

// Ensure a `$db` variable exists for route closures. Some callers include
// this file from contexts where $db may not be defined (CLI, tests). Try to
// obtain a Database instance when possible; otherwise leave as null so
// closures can detect and handle the absence safely.
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

// Unified req() helper for all HTTP methods
// Now delegates to $router->req() which handles GET, POST, PUT, PATCH, DELETE
function req($router, $path, $handler) {
    global $ROUTE_REGISTRY;
    $ROUTE_REGISTRY[] = [
        'methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
        'path' => $path,
        'handler' => $handler
    ];
    $router->req($path, $handler);
}

// Debug endpoint to check IP detection (remove after testing)
$router->req('/api/debug/ip-headers', function() {
    if (session_status() !== PHP_SESSION_ACTIVE) {@session_start();}
    
    // Only allow admins
    if (empty($_SESSION['is_admin'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Admin only']);
        exit;
    }
    
    header('Content-Type: application/json');
    
    $relevantHeaders = [];
    $headerKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 
                   'HTTP_CLIENT_IP', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    
    foreach ($headerKeys as $key) {
        $relevantHeaders[$key] = $_SERVER[$key] ?? null;
    }
    
    echo json_encode([
        'detected_ip' => \Ginto\Helpers\TransactionHelper::getClientIp(),
        'display_ip' => \Ginto\Helpers\TransactionHelper::getDisplayIp(),
        'is_private' => \Ginto\Helpers\TransactionHelper::isPrivateIp(\Ginto\Helpers\TransactionHelper::getClientIp()),
        'headers' => $relevantHeaders
    ], JSON_PRETTY_PRINT);
    exit;
});

// Login route
$router->req('/login', 'AuthController@login');

// Lightweight transcribe endpoint for quick client testing.
// Accepts a multipart file upload 'file' and returns a simplified JSON
// { success: true, text: 'transcribed text' } to make client integration easier.
$router->req('/transcribe', function() {
    // Provide a simple GET test page that lets you record audio client-side
    // and POST it back to this same endpoint for transcription. This helps
    // isolate listening behavior in the browser and exercise the POST
    // handler without the full chat UI.
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                if (!headers_sent()) header('Content-Type: text/html; charset=utf-8');
                echo <<<'HTML'
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Transcribe Test</title>
    <style>
        body{font-family:system-ui,Arial;margin:18px}
        button{margin:6px}
        #debug{white-space:pre-wrap;background:#111;color:#0f0;padding:8px;border-radius:6px}
        #transcript{padding:8px;border:1px solid #ccc;border-radius:6px;min-height:36px}
    </style>
</head>
<body>
    <h1>/transcribe — test recorder</h1>
    <p>Click <strong>Start</strong>, speak, then <strong>Stop</strong> to upload to this endpoint.</p>
    <div><button id="start">Start</button><button id="stop" disabled>Stop</button></div>
    <div style="margin-top:12px"><strong>Transcript</strong><div id="transcript"></div></div>
    <div style="margin-top:12px"><strong>Debug</strong><div id="debug">(idle)</div></div>
    <div style="margin-top:12px"><strong>Record log</strong><div id="record_log" style="white-space:pre-wrap;background:#111;color:#7ff;padding:8px;border-radius:6px;min-height:36px;font-family:monospace;font-size:12px">(no records yet)</div></div>
    <script>
        async function getCsrf(){
            try{
                const r = await fetch('/dev/csrf', { credentials: 'same-origin' });
                const j = await r.json().catch(()=>null);
                return (j && j.csrf_token) ? j.csrf_token : '';
            } catch(e) { return ''; }
        }

            (function(){
            const start = document.getElementById('start');
            const stop = document.getElementById('stop');
            const debug = document.getElementById('debug');
            const out = document.getElementById('transcript');
            // recLog should be visible to both the start and stop handlers
            // so declare it in the outer closure and assign from start.
            let mr = null, chunks = [], streamRef = null, recLog = null;

            let recordingStartMs = 0;
            const MIN_MS = 1000; // require at least 1000ms of audio before upload (reduce tiny uploads)

            start.addEventListener('click', async ()=>{
                debug.textContent = 'requesting mic...';
                try {
                    streamRef = await navigator.mediaDevices.getUserMedia({ audio: true });
                    mr = new MediaRecorder(streamRef);
                    chunks = [];
                    recLog = (m) => {
                        try {
                            const el = document.getElementById('record_log');
                            const ts = new Date().toISOString().split('T')[1].replace('Z','');
                            if (el) el.textContent = (el.textContent === '(no records yet)' ? '' : el.textContent + '\n') + ts + ' ' + m;
                        } catch (_) {}
                        try { console.debug('[rec-log]', m); } catch(_){}
                    };

                    mr.ondataavailable = e => {
                        const ok = e.data && e.data.size;
                        if (ok) {
                            chunks.push(e.data);
                            recLog('dataavailable - chunk size ' + e.data.size + ' bytes; total chunks=' + chunks.length);
                            debug.textContent = 'recording... chunks=' + chunks.length;
                        } else {
                            recLog('dataavailable - empty chunk');
                        }
                    };
                    mr.onstop = () => { try { streamRef.getTracks().forEach(t=>t.stop()); } catch(e){} recLog('stop'); };
                    recordingStartMs = Date.now();
                    try {
                        mr.start();
                        recLog('start -> state=' + (mr.state || 'n/a') + ' mime=' + (mr.mimeType || 'unknown'));
                    } catch (err) {
                        recLog('start error: ' + (err?.message || String(err)));
                        debug.textContent = 'start error: ' + (err?.message || err);
                    }
                    start.disabled = true; stop.disabled = false; debug.textContent = 'recording...';
                } catch(e) { debug.textContent = 'mic error: ' + (e?.message || e); }
            });

            stop.addEventListener('click', async ()=>{
                try {
                    debug.textContent = 'stopping...';
                    stop.disabled = true;
                    mr.requestData?.();
                    mr.stop();
                    const recordingEndMs = Date.now();
                    const duration = Math.max(0, recordingEndMs - recordingStartMs);
                    if (duration < MIN_MS) {
                        out.textContent = '[error] audio is too short — try recording a longer sample';
                        debug.textContent = 'recording length ' + duration + 'ms < ' + MIN_MS + 'ms — aborting upload';
                        start.disabled = false; stop.disabled = true;
                        return;
                    }
                    await new Promise(r => setTimeout(r, 200));
                    const blob = new Blob(chunks, { type: (chunks[0] && chunks[0].type) || 'audio/webm' });
                    debug.textContent = 'uploading ' + blob.size + ' bytes...';
                    recLog('constructed blob size=' + blob.size + ' type=' + (blob.type || 'unknown'));
                    const form = new FormData();
                    form.append('file', blob, 'stt.webm');
                    const csrf = await getCsrf();
                    if (csrf) form.append('csrf_token', csrf);
                    // Debug: enumerate form entries so we can see what's being sent in browsers
                    try {
                        const entries = [];
                        for (const e of form.entries()) entries.push([e[0], e[1] && e[1].name ? ('<file:' + e[1].name + '>') : e[1]]);
                        console.debug('transcribe: form entries', entries);
                        debug.textContent = 'uploading ' + blob.size + ' bytes... (form entries: ' + entries.map(x=>x[0]).join(',') + ')';
                    } catch (e) {}
                    let res, txt;
                    try {
                        res = await fetch('/transcribe', { method: 'POST', credentials: 'same-origin', body: form });
                        txt = await res.text();
                        debug.textContent = 'response status ' + res.status;
                        recLog('upload finished -> status=' + res.status + ' response_len=' + (txt?.length || 0));
                    } catch (e) {
                        recLog('fetch error: ' + (e?.message || String(e)));
                        debug.textContent = 'stop error: ' + (e?.message || e);
                        start.disabled = false; stop.disabled = true;
                        return;
                    }
                    try {
                        const j = JSON.parse(txt);
                        if (j.success && j.text) {
                            out.textContent = j.text;
                            try {
                                // If this test page was opened from the main chat page (via window.open),
                                // forward the transcript back to the opener so it can be inserted into
                                // the main composer. The chat page listens for `ginto_transcript` messages.
                                if (window.opener && !window.opener.closed) {
                                    window.opener.postMessage({ type: 'ginto_transcript', text: j.text }, '*');
                                }
                            } catch (e) {}
                        } else if (j.error) {
                            if (j.error === 'audio_too_short') {
                                out.textContent = '[error] audio is too short — try recording a longer sample';
                            } else {
                                out.textContent = '[error] ' + (j.error || JSON.stringify(j));
                            }
                            if (j.detail) {
                                // also write the detailed error to the debug panel so
                                // developers can see CLI stderr / hints without opening
                                // server logs.
                                debug.textContent += '\n[detail] ' + j.detail;
                            }
                        }
                        else out.textContent = txt;
                    } catch(e) { out.textContent = txt; }
                    // If the server returned a non-JSON body (some hosts return an
                    // HTML page or other text), putting that raw body into the
                    // transcript harms UX (users see page markup/instructions).
                    // Detect obvious HTML and show it in Debug instead while
                    // presenting a helpful error in the transcript box.
                    try {
                        JSON.parse(txt);
                    } catch (_e) {
                        const ct = (res.headers && res.headers.get ? res.headers.get('content-type') : '') || '';
                        const looksLikeHtml = /<\/?(html|!doctype|body|head|div|script)/i.test(txt) || ct.indexOf('html') !== -1;
                        if (looksLikeHtml) {
                            out.textContent = '[error] invalid server response — see Debug';
                            debug.textContent += '\n[non-json response] ' + txt.slice(0, 200);
                        }
                    }
                    start.disabled = false; stop.disabled = true;
                } catch(e) {
                    debug.textContent = 'stop error: ' + (e?.message || e);
                    start.disabled = false; stop.disabled = true;
                }
            });
        })();
    </script>
</body>
</html>
HTML;
                exit;
        }

    // continue to POST handling for /transcribe
    // sending HTML pages back to JS clients. Specific downstream handlers
    // may override content-type as needed, but default to JSON here.
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');

    $use_py = $_ENV['USE_PY_STT'] ?? null;
    $groqKey = $_ENV['GROQ_API_KEY'] ?? null;
    if (!$groqKey && !$use_py) {
        http_response_code(502); echo json_encode(['error' => 'TTS_API_KEY not configured and USE_PY_STT not enabled']); exit;
    }

    // Debug: log request headers and basic info to help diagnose missing uploads
    try {
        $dbg = sprintf("[transcribe_request] ts=%s method=%s remote=%s content_type=%s content_length=%s\n", date('c'), $_SERVER['REQUEST_METHOD'] ?? '', $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['CONTENT_TYPE'] ?? '', $_SERVER['CONTENT_LENGTH'] ?? '');
        @file_put_contents('/tmp/transcribe_debug.log', $dbg, FILE_APPEND);
        @file_put_contents('/tmp/transcribe_debug.log', "_FILES_KEYS=" . print_r(array_keys($_FILES), true) . "\n", FILE_APPEND);
        @file_put_contents('/tmp/transcribe_debug.log', "_FILES_FULL=" . print_r($_FILES, true) . "\n", FILE_APPEND);
        @file_put_contents('/tmp/transcribe_debug.log', "_POST=" . print_r($_POST, true) . "\n", FILE_APPEND);
    } catch (Exception $_) { /* ignore logging errors */ }

    // Accept either multipart file upload or raw audio body (some clients post raw blobs)
    if (!empty($_FILES['file']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
        $tmp = $_FILES['file']['tmp_name'];
        $name = $_FILES['file']['name'] ?? 'upload';
        $ctype = $_FILES['file']['type'] ?? 'application/octet-stream';
        $cfile = new CURLFile($tmp, $ctype, $name);
        $sttModel = $_POST['model'] ?? ($_ENV['GROQ_STT_MODEL'] ?? 'whisper-large-v3-turbo');
        $post = ['file' => $cfile, 'model' => $sttModel];
    } else {
        // Try raw request body fallback (some clients send audio as raw body)
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            @file_put_contents('/tmp/transcribe_debug.log', "NO_FILE: raw_input_length=" . strlen((string)$raw) . "\n", FILE_APPEND);
            http_response_code(400); echo json_encode(['error' => 'no file provided']); exit;
        }

        // Write raw bytes to a temp file and attempt to detect MIME type
        $tmp = tempnam(sys_get_temp_dir(), 'ginto_trans_');
        file_put_contents($tmp, $raw);

        $detectedType = null;
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $detected = finfo_file($finfo, $tmp);
                if ($detected && $detected !== 'application/octet-stream') {
                    $detectedType = $detected;
                }
                finfo_close($finfo);
            }
        }

        $ctype = $detectedType ?: ($_SERVER['CONTENT_TYPE'] ?? 'application/octet-stream');
        $cfile = new CURLFile($tmp, $ctype, 'upload');
        $sttModel = $_POST['model'] ?? ($_ENV['GROQ_STT_MODEL'] ?? 'whisper-large-v3-turbo');
        $post = ['file' => $cfile, 'model' => $sttModel];
    }

    // If configured, prefer calling the local Python STT wrapper so the
    // application uses the tools/groq-mcp/src groq_stt implementation and
    // inherits the project's .env configuration (preferred for local dev).
    $use_py = $_ENV['USE_PY_STT'] ?? null;
    if ($use_py) {
        $py = escapeshellcmd($_ENV['PYTHON3_PATH'] ?? 'python3');
        $srcPath = realpath(__DIR__ . '/../../tools/groq-mcp/src');
        // Ensure temp file has a sensible extension so the Python helper's
        // audio content check (which looks at suffix) recognizes it. If the
        // uploaded tmp file has no extension but the original filename does,
        // copy to a temp path with the original extension.
        $tmp_with_ext = $tmp;
        $orig_ext = pathinfo($name, PATHINFO_EXTENSION) ?: '';
        if ($orig_ext && pathinfo($tmp, PATHINFO_EXTENSION) === '') {
            $tmp_with_ext = $tmp . '.' . $orig_ext;
            @copy($tmp, $tmp_with_ext);
            // We'll try to remove this copied file after the CLI call when
            // appropriate so long-lived server storage is not polluted.
            $copied_tmp = true;
        } else {
            $copied_tmp = false;
        }
        // If the uploaded file is not WAV, try to transcode to WAV using ffmpeg
        // so the local Python STT wrapper gets a widely-compatible file.
        $current_ct = strtolower($ctype ?? '');
        $need_wav = (strpos($current_ct, 'wav') === false && strpos($current_ct, 'wave') === false);
        if ($need_wav) {
            $ffmpeg = null;
            try { $ffmpeg = trim((string)shell_exec('command -v ffmpeg 2>/dev/null')); } catch (\Throwable $_) { $ffmpeg = null; }
            if ($ffmpeg) {
                $wavTmp = tempnam(sys_get_temp_dir(), 'ginto_trans_wav_');
                $wavTmpNamed = $wavTmp . '.wav';
                $cmd = escapeshellcmd($ffmpeg) . ' -y -i ' . escapeshellarg($tmp_with_ext) . ' -ar 16000 -ac 1 ' . escapeshellarg($wavTmpNamed) . ' 2>&1';
                $out = null;
                try { $out = shell_exec($cmd); } catch (\Throwable $_) { $out = null; }
                if (file_exists($wavTmpNamed) && filesize($wavTmpNamed) > 32) {
                    // Use the transcoded WAV for the Python caller
                    if (strpos($tmp_with_ext, sys_get_temp_dir()) === 0 && is_file($tmp_with_ext)) { @unlink($tmp_with_ext); }
                    $tmp_with_ext = $wavTmpNamed;
                    $ctype = 'audio/wav';
                    $copied_tmp = true; // ensure cleanup later
                } else {
                    @file_put_contents('/tmp/transcribe_debug.log', "[transcode_failed_before_py] cmd=" . $cmd . " out=" . substr((string)$out,0,2000) . "\n", FILE_APPEND);
                    // If conversion failed we'll keep original file (let python wrapper attempt it)
                }
            } else {
                @file_put_contents('/tmp/transcribe_debug.log', "[transcode_skipped_before_py] ffmpeg not found; sending original file tmp_with_ext=" . $tmp_with_ext . " ctype=" . ($ctype ?? 'unknown') . "\n", FILE_APPEND);
            }
        }

        $fileArg = escapeshellarg($tmp_with_ext);
        $modelArg = escapeshellarg($sttModel);
        $pyCode = sprintf(
            "import sys, json, os; sys.path.insert(0,%s); import groq_stt as gs; res = gs.transcribe_audio(%s, model=%s, response_format='json', save_to_file=False); text = getattr(res,'text', getattr(res,'content', str(res))); print(json.dumps({'success':True,'text': text}))",
            escapeshellarg($srcPath),
            $fileArg,
            $modelArg
        );
        $cmd = $py . ' -c ' . escapeshellarg($pyCode);

        $output = null; $code = 0; $err = null;
        try {
            $des = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
            $proc = proc_open($cmd, $des, $pipes);
            if (is_resource($proc)) {
                $output = stream_get_contents($pipes[1]); fclose($pipes[1]);
                $err = stream_get_contents($pipes[2]); fclose($pipes[2]);
                $code = proc_close($proc);
                if ($err) error_log('[TRANSCRIBE PY CLI stderr] ' . substr($err,0,1200));
            } else {
                $output = shell_exec($cmd . ' 2>&1');
            }
        } catch (Exception $e) {
            http_response_code(502);
            echo json_encode(['error' => 'STT CLI failed', 'detail' => $e->getMessage()]);
            exit;
        }

        if ($output) {
            $j = json_decode($output, true);
            if (is_array($j) && isset($j['success']) && $j['success']) {
                if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => true, 'text' => isset($j['text']) ? $j['text'] : '']);
                exit;
            } else {
                http_response_code(502);
                $errMsg = is_array($j) && isset($j['error']) ? $j['error'] : 'STT CLI error';
                echo json_encode(['error' => $errMsg]);
                exit;
            }
        }
        http_response_code(502);
        $dbgErr = is_string($err) && $err !== '' ? substr($err,0,1200) : null;
        $extra = [];
        if ($dbgErr) $extra['detail'] = $dbgErr;
        else $extra['exit_code'] = $code ?: null;
        // If the CLI stderr indicates a too-short audio file, return a clearer
        // error code so the UI can react with a helpful message.
        if ($dbgErr && stripos($dbgErr, 'Audio file is too short') !== false) {
            echo json_encode(array_merge(['error' => 'audio_too_short'], $extra));
        } else {
            echo json_encode(array_merge(['error' => 'STT CLI produced no output'], $extra));
        }
        // cleanup our copied temp file if we created one
        if (!empty($copied_tmp) && is_file($tmp_with_ext)) { @unlink($tmp_with_ext); }
        exit;
    }

    $ch = curl_init('https://api.groq.com/openai/v1/audio/transcriptions');
    // If the uploaded file is not WAV, try to transcode to WAV using ffmpeg
    // so upstream accepts it reliably. This mirrors the logic used in
    // `/audio/stt` for broader client compatibility.
    $current_ct = strtolower($ctype ?? '');
    $need_wav = (strpos($current_ct, 'wav') === false && strpos($current_ct, 'wave') === false);
    if ($need_wav) {
        $ffmpeg = null;
        try { $ffmpeg = trim((string)shell_exec('command -v ffmpeg 2>/dev/null')); } catch (\Throwable $_) { $ffmpeg = null; }
        if ($ffmpeg) {
            $wavTmp = tempnam(sys_get_temp_dir(), 'ginto_trans_wav_');
            $wavTmpNamed = $wavTmp . '.wav';
            $cmd = escapeshellcmd($ffmpeg) . ' -y -i ' . escapeshellarg($tmp) . ' -ar 16000 -ac 1 ' . escapeshellarg($wavTmpNamed) . ' 2>&1';
            $out = null;
            try { $out = shell_exec($cmd); } catch (\Throwable $_) { $out = null; }
            if (!file_exists($wavTmpNamed) || filesize($wavTmpNamed) < 32) {
                @file_put_contents('/tmp/transcribe_debug.log', "[transcode_failed] cmd=" . $cmd . " out=" . substr((string)$out,0,2000) . "\n", FILE_APPEND);
                http_response_code(400); echo json_encode(['error' => 'could not transcode to WAV', 'hint' => 'ffmpeg conversion failed (see /tmp/transcribe_debug.log)']); exit;
            }
            // Replace CURLFile and post payload with WAV
            $cfile = new CURLFile($wavTmpNamed, 'audio/wav', 'upload.wav');
            $post = ['file' => $cfile, 'model' => $sttModel];
            // cleanup original temp if created here
            if (strpos($tmp, sys_get_temp_dir()) === 0 && is_file($tmp)) { @unlink($tmp); }
            $tmp = $wavTmpNamed; $ctype = 'audio/wav';
        } else {
            // If ffmpeg is not available, don't fail the request — many upstream
            // transcription providers (including Groq) accept WebM/Opus and
            // other container formats directly. Log a helpful warning and
            // continue sending the original uploaded file as-is.
            @file_put_contents('/tmp/transcribe_debug.log', "[transcode_skipped] ffmpeg not found; sending original file tmp=" . $tmp . " ctype=" . ($ctype ?? 'unknown') . "\n", FILE_APPEND);
            // keep $post as previously prepared (no changes)
        }
    }
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $post,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $groqKey],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);

    $res = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    // NOTE: a previous erroneous edit injected unrelated chat/tool logic here.
    // We've removed that block and only keep upstream error checks below.

    if ($err) {
        http_response_code(502);
        echo json_encode(['error' => $err]);
        exit;
    }

    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
    if ($code >= 400) {
        http_response_code($code ?: 502);
        echo json_encode(['error' => 'Upstream error', 'body' => substr($res,0,2000)]);
        exit;
    }

    $parsed = json_decode($res, true);
    if (is_array($parsed) && isset($parsed['text'])) {
        echo json_encode(['success' => true, 'text' => $parsed['text']]);
        exit;
    }

    // Try other shapes
    if (is_array($parsed)) {
        if (isset($parsed['data']) && is_array($parsed['data'])) {
            $ex = '';
            foreach ($parsed['data'] as $it) {
                if (is_string($it)) $ex .= ($ex ? ' ' : '') . $it;
                elseif (is_array($it) && isset($it['text'])) $ex .= ($ex ? ' ' : '') . $it['text'];
            }
            if ($ex !== '') { echo json_encode(['success' => true, 'text' => $ex]); exit; }
        }
        // fallback: return JSON-encoded body as text
        echo json_encode(['success' => true, 'text' => json_encode($parsed)]);
        exit;
    }

    // Non-JSON: return raw as text
    echo json_encode(['success' => true, 'text' => trim((string)$res)]);
    exit;
});

// Custom root route: redirect based on session role
$router->req('/', 'AuthController@index');

// Full user network tree view
$router->req('/user/network-tree', 'UserController@networkTree');

// Downline view (legacy route)
$router->req('/downline', 'AuthController@downline');

// Logout route: destroy session and redirect to login
$router->req('/logout', 'AuthController@logout');

// Register route
$router->req('/register', 'AuthController@register');

// Bank Transfer Payment Registration
$router->req('/bank-payments', 'PaymentController@bankPayments');
// GCash Payment Registration
$router->req('/gcash-payments', 'PaymentController@gcashPayments');

// Crypto Payment Info API
$router->req('/api/payments/crypto-info', 'PaymentController@cryptoInfo');

// Get user's pending payment details (for transaction details modal)
$router->req('/api/user/payment-details', 'PaymentController@paymentDetails');

// Check/Sync Payment Status (for PayPal, checks API; for others, returns DB status)
$router->req('/api/payment/check-status/{paymentId}', 'PaymentController@checkStatus');


// Request Admin Review for Payment
$router->req('/api/payment/request-review/{paymentId}', 'PaymentController@requestReview');
// Serve receipt images securely
$router->req('/receipt-image/{filename}', 'PaymentController@receiptImage');

// Crypto Payment Registration (USDT BEP20)
$router->req('/crypto-payments', 'PaymentController@cryptoPayments');

// Dashboard page
$router->req('/dashboard', 'UserController@dashboard');

// Public profile route by numeric id, username, or public_id
$router->req('/user/profile/{ident}', 'UserController@profile');

// User commissions page
$router->req('/user/commissions', 'CommissionsController@index');

// Compact-only user network view (dev route)
$router->req('/user/network-tree/compact-view', 'UserController@networkTreeCompact');

// Webhook endpoint (PayPal and status view)
// Webhook endpoint (PayPal)
$router->req('/webhook', 'WebhookController@webhook');

// Webhook status endpoint
$router->req('/webhook/status', 'WebhookController@saiCodeCheck');

// User info endpoint - returns user data with CSRF token
// Usage: GET http://localhost/user
$router->req('/user', 'UserController@user');

// Standalone Editor Object - Monaco editor with file management
// Usage: GET http://localhost/editor
// NOTE: This route intentionally uses the lookup-only helper
// Editor - main page (view sandbox files)
$router->req('/editor', 'EditorController@index');

// Standalone editor - toggle sandbox/repo mode
$router->req('/editor/toggle_sandbox', 'EditorController@toggleSandbox', ['POST']);

// Chat API: create a sandbox for the current session (POST only)
$router->req('/chat/create_sandbox', 'ChatController@createSandbox', ['POST']);
// ============ CHAT IMAGE UPLOAD API ============

// POST /chat/upload-image - Upload an image for chat and return a URL
$router->req('/chat/upload-image', 'ChatController@uploadImage', ['POST']);

// Serve chat images from storage
$router->req('/storage/chat_images/{userId}/{filename}', 'ChatController@serveImage');

// ============ CHAT CONVERSATIONS API (Database-backed for logged-in users) ============

// GET /chat/conversations - Load all conversations for logged-in user
$router->req('/chat/conversations', 'ChatController@conversations');

// POST /chat/conversations/save - Save/update a single conversation
$router->req('/chat/conversations/save', 'ChatController@saveConversation', ['POST']);

// POST /chat/conversations/delete - Delete a single conversation
$router->req('/chat/conversations/delete', 'ChatController@deleteConversation', ['POST']);
// POST /chat/conversations/sync - Bulk sync all conversations from client
$router->req('/chat/conversations/sync', 'ChatController@syncConversations', ['POST']);


// Sandbox API: Check LXD installation progress (admin only)
$router->req('/sandbox/image-install-status', 'SandboxController@imageInstallStatus');

// ============================================================================
// Admin LXC/LXD Manager - Proxmox-style interface
// ============================================================================

// Helper function to get the LXC binary path
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

// Admin LXC Manager View (Proxmox-style datacenter interface)
$router->req('/admin/lxc', 'AdminLxcController@index');

// Admin LXC API: List containers (GET) or Create container (POST)
$router->req('/admin/api/lxc/containers', 'AdminLxcController@containers');

// Admin LXC API: Container actions (start/stop/restart/delete) - MUST BE BEFORE {name} route
$router->req('/admin/api/lxc/containers/{name}/{action}', 'AdminLxcController@containerAction');

// Admin LXC API: Get single container details
$router->req('/admin/api/lxc/containers/{name}', 'AdminLxcController@containerDetails');

// Admin LXC API: List images
$router->req('/admin/api/lxc/images', 'AdminLxcController@images');

// Admin LXC API: Delete image
$router->req('/admin/api/lxc/images/{fingerprint}', 'AdminLxcController@imageDelete');

// Admin LXC API: Pull image
$router->req('/admin/api/lxc/images/pull', 'AdminLxcController@imagePull', ['POST']);

// Admin LXC API: List storage pools
$router->req('/admin/api/lxc/storage', 'AdminLxcController@storage');

// Admin LXC API: List networks
$router->req('/admin/api/lxc/networks', 'AdminLxcController@networks');

// Admin LXC API: Get host system stats (CPU/Memory)
$router->get('/admin/api/lxc/stats', 'AdminLxcController@stats');

// Admin LXC API: Prune unused resources
$router->req('/admin/api/lxc/prune', 'AdminLxcController@prune', ['POST']);
// Sandbox API: Call sandbox-scoped MCP tools
$router->req('/sandbox/call', 'SandboxController@call', ['POST']);
// Routes requests to user's LXD container web server based on session
// The sandbox ID comes from the user's session, not the URL
// ============================================================================

// /clients/ - routes to user's sandbox root
$router->get('/clients', function() use ($db) {
    handleClientProxy('/', $db);
});

$router->get('/clients/', function() use ($db) {
    handleClientProxy('/', $db);
});

// /clients/{path} - routes to user's sandbox with path
$router->get('/clients/{path:.*}', function($path) use ($db) {
    handleClientProxy('/' . $path, $db);
});

$router->post('/clients', function() use ($db) {
    handleClientProxy('/', $db);
});

$router->post('/clients/', function() use ($db) {
    handleClientProxy('/', $db);
});

$router->post('/clients/{path:.*}', function($path) use ($db) {
    handleClientProxy('/' . $path, $db);
});

/**
 * Handle client proxy requests
 * Gets sandbox ID from URL path (first segment) or session, validates, ensures container is running, then proxies
 * 
 * Architecture:
 *   Browser → /clients/{sandboxId}/path → PHP → container:80
 *   Browser → /clients/path → (PHP gets sandbox from session) → container:80
 */
function handleClientProxy(string $path, $db): void
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
    
    // Ensure container is running, network is migrated if needed, routes and services ready
    if (!\Ginto\Helpers\LxdSandboxManager::ensureSandboxAccessible($sandboxId)) {
        // Try starting if it doesn't exist yet
        $started = \Ginto\Helpers\LxdSandboxManager::ensureSandboxRunning($sandboxId);
        
        if (!$started) {
            http_response_code(503);
            header('Content-Type: text/html; charset=utf-8');
            echo '<h1>503 - Sandbox Unavailable</h1><p>Your sandbox could not be started. Please try again.</p>';
            exit;
        }
        
        // Try again after starting
        \Ginto\Helpers\LxdSandboxManager::ensureSandboxAccessible($sandboxId);
        
        // Wait a moment for container to fully initialize
        usleep(500000); // 0.5 seconds
    }
    
    // Get container IP - queries LXD for actual IP in bridge/nat mode
    $containerIp = \Ginto\Helpers\LxdSandboxManager::getSandboxIp($sandboxId);
    
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
    
    echo $body;
    exit;
}

// ============================================================================
// SANDBOX PREVIEW ROUTE: /sandbox-preview/{sandboxId}/{path}
// Similar to /clients/ but takes sandbox ID from URL for editor preview
// Used by the editor's preview pane to execute PHP files in containers
// ============================================================================
$router->get('/sandbox-preview/{sandboxId}/{path:.*}', function($sandboxId, $path) use ($db) {
    handleSandboxPreview($sandboxId, '/' . $path, $db);
});

$router->get('/sandbox-preview/{sandboxId}', function($sandboxId) use ($db) {
    handleSandboxPreview($sandboxId, '/index.php', $db);
});

/**
 * Handle sandbox preview requests
 * Like handleClientProxy but takes sandbox ID from URL instead of session
 * Validates that the requesting user owns this sandbox
 */
function handleSandboxPreview(string $sandboxId, string $path, $db): void
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
        // Check if the sandbox actually exists in the system
        try {
            if (\Ginto\Helpers\LxdSandboxManager::sandboxRunning($sandboxId)) {
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
    
    // Ensure container is running, routes are set up, and services are ready
    if (!\Ginto\Helpers\LxdSandboxManager::ensureSandboxAccessible($sandboxId)) {
        // Try harder - maybe it just needs to be started
        $started = \Ginto\Helpers\LxdSandboxManager::ensureSandboxRunning($sandboxId);
        
        if (!$started) {
            http_response_code(503);
            header('Content-Type: text/html; charset=utf-8');
            echo '<h1>503 - Sandbox Unavailable</h1><p>Could not start sandbox. Please try again.</p>';
            exit;
        }
        
        // Try again to ensure accessibility (routes, caddy)
        \Ginto\Helpers\LxdSandboxManager::ensureSandboxAccessible($sandboxId);
        
        // Wait for container to initialize
        usleep(500000); // 0.5 seconds
    }
    
    // Get container IP - queries LXD for actual IP in bridge/nat mode
    $containerIp = \Ginto\Helpers\LxdSandboxManager::getSandboxIp($sandboxId);
    
    if (!$containerIp) {
        http_response_code(503);
        header('Content-Type: text/html; charset=utf-8');
        echo '<h1>503 - Sandbox Unavailable</h1><p>Could not get sandbox IP address.</p>';
        exit;
    }
    
    // Proxy directly to container's web server on port 80
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
            if (!in_array(strtolower($name), ['transfer-encoding', 'connection'], true)) {
                header("$name: " . trim($value));
            }
        }
    }
    
    echo $body;
    exit;
}

// Standalone editor - tree endpoint
// Standalone editor - get file tree
$router->req('/editor/tree', 'EditorController@tree');

// Standalone editor - create file/folder
$router->req('/editor/create', 'EditorController@create', ['POST']);
// Standalone editor - rename file/folder
$router->req('/editor/rename', 'EditorController@rename', ['POST']);

// Standalone editor - delete file/folder
$router->req('/editor/delete', 'EditorController@delete', ['POST']);

// Standalone editor - paste (copy/move) file/folder
$router->req('/editor/paste', 'EditorController@paste', ['POST']);

// Standalone editor - save file
$router->req('/editor/save', 'EditorController@save', ['POST']);

// Standalone editor - read file content
$router->req('/editor/file', 'EditorController@file');

// Rate limit status endpoint - shows current usage and limits
// Usage: GET /rate-limits
// Rate limit status endpoint
$router->req('/rate-limits', 'ApiController@rateLimits');

// Simple streaming test route for Groq API.
// GET will render the test page; POST streams provider output.
// Usage (GET): open http://localhost/chat
// Usage (POST): curl -X POST -F 'prompt=Your prompt here' http://localhost/chat
$router->req('/chat', function() use ($db) {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Check if user is logged in
        $isLoggedIn = !empty($_SESSION['user_id']);
        
        // Check if user is admin using the centralized helper
        $isAdmin = \Ginto\Controllers\UserController::isAdmin();
        
        // Determine a safe sandbox id (basename only) for the UI if available
        $sandboxId = null;
        try {
            // First try lookup-only so we don't accidentally create a sandbox
            // for users who already have one or for admin flows.
            $editorRoot = \Ginto\Helpers\ClientSandboxHelper::getSandboxRootIfExists($db ?? null, $_SESSION ?? null);
            if (!empty($editorRoot)) {
                $sandboxId = basename($editorRoot);
            } else {
                // If no sandbox exists and the user is not logged in, create
                // a per-session sandbox so visitors see their sandbox id.
                $sandboxId = $_SESSION['sandbox_id'] ?? null;
                if (empty($sandboxId) && empty($_SESSION['user_id'])) {
                    try {
                        $createdPath = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($db ?? null, $_SESSION ?? null);
                        if (!empty($createdPath)) {
                            $sandboxId = basename($createdPath);
                            // Persist to session for subsequent requests
                            $_SESSION['sandbox_id'] = $sandboxId;
                        }
                    } catch (\Throwable $_e) {
                        // Fall back to whatever session value might exist
                        $sandboxId = $_SESSION['sandbox_id'] ?? null;
                    }
                }
            }
        } catch (\Throwable $_) {
            $sandboxId = $_SESSION['sandbox_id'] ?? null;
        }

        // Generate CSRF token for the view (use global helper if available)
        if (function_exists('generateCsrfToken')) {
            $csrf_token = generateCsrfToken();
        } else {
            if (empty($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
            $csrf_token = $_SESSION['csrf_token'];
        }

        // Get payment status for logged in users - always check DB for current status
        $paymentStatus = null;
        if ($isLoggedIn) {
            $paymentStatus = $db->get('users', 'payment_status', ['id' => $_SESSION['user_id']]);
            // Update session to match DB
            $_SESSION['payment_status'] = $paymentStatus;
        }

        \Ginto\Core\View::view('chat/chat', [
            'title' => 'Ginto AI - agentic chat',
            'isLoggedIn' => $isLoggedIn,
            'isAdmin' => $isAdmin,
            'userId' => $isLoggedIn ? $_SESSION['user_id'] : null,
            'sandboxId' => $sandboxId,
            'csrf_token' => $csrf_token,
            'paymentStatus' => $paymentStatus
        ]);
        exit;
    }

    // Ensure session is active for CSRF validation
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }

    // CSRF validation for POST requests
    // Allow bypass via .env CSRF_BYPASS=true or session flag in development
    $csrfValid = false;
    $envCsrfBypass = filter_var(getenv('CSRF_BYPASS') ?: ($_ENV['CSRF_BYPASS'] ?? false), FILTER_VALIDATE_BOOLEAN);
    $devCsrfBypass = !empty($_SESSION['dev_csrf_bypass']) && ($_ENV['APP_ENV'] ?? 'production') !== 'production';
    
    if ($envCsrfBypass || $devCsrfBypass) {
        $csrfValid = true; // CSRF bypass enabled via .env or dev session
    } else {
        $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
            $csrfValid = true;
        }
    }
    
    if (!$csrfValid) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['error' => 'Invalid or missing CSRF token']);
        exit;
    }

    // --- Visitor Prompt Limit (configurable via config/chat.json) ---
    // Logged-in users bypass this limit
    $isLoggedInUser = !empty($_SESSION['user_id']);
    if (!$isLoggedInUser) {
        $currentHour = date('Y-m-d-H');
        $visitorLimitKey = 'visitor_prompts_' . $currentHour;
        $visitorPromptCount = (int)($_SESSION[$visitorLimitKey] ?? 0);
        $visitorMaxPrompts = (int)\Ginto\Helpers\ChatConfig::get('visitor.maxPromptsPerHour', 5);
        
        if ($visitorPromptCount >= $visitorMaxPrompts) {
            // Return SSE-formatted response for visitor limit
            header('Content-Type: text/event-stream; charset=utf-8');
            header('Cache-Control: no-cache');
            echo str_repeat(' ', 1024);
            flush();
            echo "data: " . json_encode([
                'error' => true,
                'action' => 'register',
                'text' => "You've reached the free limit of {$visitorMaxPrompts} messages per hour. Create a free account to continue chatting with Ginto!",
                'prompts_used' => $visitorPromptCount,
                'prompts_limit' => $visitorMaxPrompts,
                'register_url' => '/register'
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
            flush();
            echo "data: " . json_encode([
                'final' => true, 
                'action' => 'register',
                'html' => '<div class="text-amber-400"><p>You\'ve used all ' . $visitorMaxPrompts . ' free messages this hour.</p><p class="mt-2"><a href="/register" class="text-indigo-400 hover:text-indigo-300 underline font-semibold">Create a free account</a> to continue chatting!</p></div>'
            ]) . "\n\n";
            flush();
            exit;
        }
        
        // Increment visitor prompt count
        $_SESSION[$visitorLimitKey] = $visitorPromptCount + 1;
        
        // Clean up old hourly keys (keep session tidy)
        foreach ($_SESSION as $key => $val) {
            if (str_starts_with($key, 'visitor_prompts_') && $key !== $visitorLimitKey) {
                unset($_SESSION[$key]);
            }
        }
    }

    // --- Rate Limiting & Provider Selection ---
    // Initialize rate limiting service and determine which provider to use
    $rateLimitService = new \App\Core\RateLimitService($db ?? null);
    $userIdSession = $_SESSION['user_id'] ?? null;
    $userId = $userIdSession ?? ($_SESSION['sandbox_id'] ?? session_id());
    
    // Determine user role - check multiple session keys for admin status
    $isAdminUser = !empty($_SESSION['is_admin']) 
        || (!empty($_SESSION['role']) && strtolower((string)$_SESSION['role']) === 'admin')
        || (!empty($_SESSION['user_role']) && strtolower((string)$_SESSION['user_role']) === 'admin');
    $userRole = !empty($_SESSION['user_id']) ? ($isAdminUser ? 'admin' : 'user') : 'visitor';
    
    $visitorIp = $userIdSession === null ? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1') : null;
    
    // Get primary provider from environment (default: cerebras for text, groq for vision)
    $primaryProvider = strtolower(getenv('DEFAULT_PROVIDER') ?: ($_ENV['DEFAULT_PROVIDER'] ?? 'groq'));
    if (!in_array($primaryProvider, ['groq', 'cerebras'], true)) {
        $primaryProvider = 'groq';
    }
    $defaultModel = 'openai/gpt-oss-120b';
    
    // Initialize per-user rate limiter to protect against hitting provider limits
    $userRateLimiter = new \App\Core\UserRateLimiter($db, $primaryProvider);
    
    // Check per-user limits FIRST (protects us from hitting provider billing)
    $userLimitCheck = $userRateLimiter->checkLimit(
        $userIdSession ? (int)$userIdSession : null,
        $visitorIp,
        $userRole
    );
    if (!$userLimitCheck['allowed']) {
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache');
        echo str_repeat(' ', 1024);
        flush();
        echo "data: " . json_encode([
            'error' => true,
            'text' => $userLimitCheck['message'],
            'reason' => $userLimitCheck['reason'],
            'usage' => $userLimitCheck['usage'],
            'limits' => $userLimitCheck['limits'],
            'retry_after' => $userLimitCheck['retry_after'] ?? 60,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
        flush();
        echo "data: " . json_encode(['final' => true, 'html' => '<p class="text-amber-500">' . htmlspecialchars($userLimitCheck['message']) . '</p>']) . "\n\n";
        flush();
        exit;
    }
    
    // Also check provider-level limits (for fallback selection)
    $providerLimitCheck = $rateLimitService->canMakeRequest($userId, $userRole, $primaryProvider, $defaultModel);
    if (!$providerLimitCheck['allowed']) {
        header('Content-Type: application/json');
        http_response_code(429);
        echo json_encode([
            'error' => 'Rate limit exceeded',
            'reason' => $providerLimitCheck['reason'],
            'limit' => $providerLimitCheck['limit'],
            'current' => $providerLimitCheck['current'],
            'retry_after' => $providerLimitCheck['retry_after'] ?? 60,
        ]);
        exit;
    }
    
    // Select provider (may fallback to cerebras if org limits approached)
    $providerSelection = $rateLimitService->selectProvider($primaryProvider, $defaultModel);
    $selectedProvider = $providerSelection['provider'];
    $usingFallback = $providerSelection['is_fallback'] ?? false;
    $requestStartTime = microtime(true);

    // Read the incoming prompt
    $prompt = $_POST['prompt'] ?? trim(file_get_contents('php://input')) ?: 'Hello, how can you help me today?';
    $lower = mb_strtolower($prompt);

    // Handle explicit repository description requests (fast path, no LLM needed)
    $describeKeywords = ['describe this repo', 'about this repo', 'about this repository'];
    $shouldDescribe = false;
    foreach ($describeKeywords as $kw) {
        if (strpos($lower, $kw) !== false) { $shouldDescribe = true; break; }
    }
    if (!$shouldDescribe && isset($_POST['describe_repo'])) {
        $v = strtolower((string)$_POST['describe_repo']);
        if (in_array($v, ['1', 'true', 'yes'], true)) { $shouldDescribe = true; }
    }

    if ($shouldDescribe) {
        // If this is a sandbox session, do not expose repository-level summaries
        try {
            $dbForSandboxCheck = $db ?? null;
            $editorRootCheck = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($dbForSandboxCheck, $_SESSION ?? null);
            $isSandboxSession = (realpath($editorRootCheck) !== (realpath(defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2)) ?: (defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2))));
            if ($isSandboxSession) {
                _chatStreamResponse("Repository description is not available for sandboxed sessions. Please provide admin access or run this request from an admin session.");
                exit;
            }
        } catch (\Throwable $_) {
            // If sandbox detection fails, be conservative and deny the describe action
            _chatStreamResponse("Repository description is not available at this time.");
            exit;
        }

        // Build deterministic repo summary without LLM (admin-only)
        $root = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2);
        $readme = file_exists($root . '/README.md') ? file_get_contents($root . '/README.md') : '';
        $composer = file_exists($root . '/composer.json') ? @json_decode(file_get_contents($root . '/composer.json'), true) : null;

        // Filter top-level files and avoid exposing VCS or env files
        $rawFiles = @scandir($root) ?: [];
        $skip = ['vendor', 'node_modules', '.git', '.idea', 'storage', '.env'];
        $files = [];
        foreach ($rawFiles as $f) {
            if ($f === '.' || $f === '..') continue;
            if (isset($f[0]) && $f[0] === '.') continue; // hidden files
            if (in_array($f, $skip, true)) continue;
            $files[] = $f;
            if (count($files) >= 40) break;
        }

        $summary = "Repository summary:\n\n";
        if ($composer && !empty($composer['name'])) {
            $summary .= "Package: " . ($composer['name'] ?? '') . "\n";
            if (!empty($composer['description'])) $summary .= "Description: " . substr($composer['description'], 0, 800) . "\n";
            if (!empty($composer['require'])) $summary .= "Requires: " . implode(', ', array_keys($composer['require'])) . "\n\n";
        }
        if ($readme) {
            $summary .= "README (first 2000 chars):\n" . substr($readme, 0, 2000) . "\n\n";
        }
        $summary .= "Top-level files and folders:\n" . implode("\n", $files) . "\n";

        // Stream the response
        _chatStreamResponse($summary);
        exit;
    }

    // If the current session is using a sandbox, do not enable repository
    // description or any behavior that would expose the host repository
    // structure. This ensures sandboxed users don't get project-level hints.
    try {
        $dbForSandboxCheck = $db ?? null;
        $editorRootCheck = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($dbForSandboxCheck, $_SESSION ?? null);
        $isSandboxSession = (realpath($editorRootCheck) !== (realpath(defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2)) ?: (defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2))));
        if ($isSandboxSession) {
            $shouldDescribe = false;
        }
    } catch (\Throwable $_) {
        // ignore sandbox detection failures and keep existing behavior
    }

    // Build conversation history from client-supplied history (no system message - UnifiedMcpClient adds it)
    $history = [];
    $hadImageInHistory = false;

    $historyJson = $_POST['history'] ?? null;
    if ($historyJson) {
        $h = json_decode($historyJson, true);
        if (is_array($h)) {
            foreach ($h as $hm) {
                if (!empty($hm['role']) && isset($hm['content'])) {
                    // Skip any client-provided system messages - UnifiedMcpClient provides repo context
                    if ($hm['role'] === 'system') continue;
                    
                    // Track if user previously shared an image
                    if ($hm['role'] === 'user' && !empty($hm['hasImage'])) {
                        $hadImageInHistory = true;
                        // Add context that user shared an image with their message
                        $history[] = [
                            'role' => 'user', 
                            'content' => '[User shared an image] ' . (string)$hm['content']
                        ];
                    } else {
                        $history[] = ['role' => $hm['role'], 'content' => (string)$hm['content']];
                    }
                }
            }
        }
    }

    // Check if this request has an attached image
    $hasImage = !empty($_POST['hasImage']) && $_POST['hasImage'] === '1';
    $imageDataUrl = $_POST['image'] ?? null;

    // =====================================================================
    // SESSION-SELECTED PROVIDER (e.g., Ollama from model dropdown)
    // =====================================================================
    // Check if user has selected a specific provider via the model dropdown.
    // This takes priority over the default cloud provider selection.
    // =====================================================================
    $sessionProvider = $_SESSION['llm_provider_name'] ?? null;
    $sessionModel = $_SESSION['llm_model'] ?? null;
    $useSessionProvider = false;
    
    if ($sessionProvider && $sessionModel && $sessionProvider === 'ollama') {
        // User selected Ollama - use it directly without cloud API logic
        try {
            // First check if Ollama server is online
            $ollamaHost = getenv('OLLAMA_HOST') ?: 'http://localhost:11434';
            $checkCtx = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]]);
            $versionCheck = @file_get_contents($ollamaHost . '/api/version', false, $checkCtx);
            
            if ($versionCheck === false) {
                // Ollama server is not running
                @header('Content-Type: text/event-stream; charset=utf-8');
                echo "data: " . json_encode(['error' => 'Ollama server is offline. Please start Ollama with: ollama serve']) . "\n\n";
                exit;
            }
            
            $ollamaProvider = \App\Core\LLM\LLMProviderFactory::create('ollama', [
                'model' => $sessionModel,
            ]);
            
            if ($ollamaProvider->isConfigured()) {
                $useSessionProvider = true;
                
                // Prepare streaming headers
                @ini_set('output_buffering', 'off');
                @ini_set('zlib.output_compression', false);
                while (ob_get_level()) ob_end_flush();
                ignore_user_abort(true);

                if (!headers_sent()) header('Content-Type: text/event-stream; charset=utf-8');
                if (!headers_sent()) header('Cache-Control: no-cache');
                if (!headers_sent()) header('X-Accel-Buffering: no');
                if (!headers_sent()) header('Connection: keep-alive');

                // Send padding to prevent proxy buffering, followed by newlines to separate from data
                echo str_repeat(' ', 1024) . "\n\n";
                flush();

                // Build messages array
                $messages = [];
                $systemPrompt = 'You are Ginto, a helpful AI assistant created by Oliver Bob. Be concise and direct.';
                $messages[] = ['role' => 'system', 'content' => $systemPrompt];
                
                // Add history
                $historyJson = $_POST['history'] ?? null;
                if ($historyJson) {
                    $h = json_decode($historyJson, true);
                    if (is_array($h)) {
                        foreach ($h as $hm) {
                            if (!empty($hm['role']) && isset($hm['content']) && $hm['role'] !== 'system') {
                                $messages[] = ['role' => $hm['role'], 'content' => (string)$hm['content']];
                            }
                        }
                    }
                }
                
                // Add current prompt
                $messages[] = ['role' => 'user', 'content' => $prompt];
                
                // Stream response from Ollama using chatStream method
                $fullResponse = '';
                $accumulatedReasoning = '';
                $streamError = null;
                $onChunk = function($chunk, $toolCall = null) use (&$fullResponse, &$accumulatedReasoning) {
                    // Handle reasoning/thinking events (qwen3 and other reasoning models)
                    if ($toolCall !== null && isset($toolCall['reasoning'])) {
                        $reasoningText = $toolCall['text'] ?? '';
                        $accumulatedReasoning .= $reasoningText;
                        echo "data: " . json_encode(['reasoning' => $reasoningText], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
                        flush();
                        return;
                    }
                    
                    // Handle regular content
                    if ($chunk !== '' && $chunk !== null) {
                        $fullResponse .= $chunk;
                        echo "data: " . json_encode(['text' => $chunk]) . "\n\n";
                        flush();
                    }
                };
                
                try {
                    $ollamaProvider->chatStream($messages, [], [], $onChunk);
                } catch (\Throwable $streamEx) {
                    $streamError = $streamEx->getMessage();
                }
                
                // If streaming failed and no response received, send error
                if ($streamError && empty($fullResponse)) {
                    echo "data: " . json_encode(['error' => 'Model not responding: ' . $streamError]) . "\n\n";
                    flush();
                    exit;
                }
                
                // Update Ollama status cache (model is now loaded)
                $cacheDir = (defined('STORAGE_PATH') ? STORAGE_PATH : dirname(ROOT_PATH)) . '/cache';
                $cacheFile = $cacheDir . '/ollama_ps.json';
                if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
                @file_put_contents($cacheFile, json_encode([
                    'models' => [$sessionModel],
                    'updated_at' => time(),
                    'updated_at_iso' => date('c'),
                ], JSON_PRETTY_PRINT));
                
                // Final message with rendered HTML
                $parsedown = null;
                if (class_exists('\ParsedownExtra')) {
                    try { $parsedown = new \ParsedownExtra(); } catch (\Throwable $_) {}
                } elseif (class_exists('\Parsedown')) {
                    try { $parsedown = new \Parsedown(); } catch (\Throwable $_) {}
                }
                // Note: setSafeMode(true) strips PHP tags from code blocks, so we disable it
                // if ($parsedown && method_exists($parsedown, 'setSafeMode')) {
                //     try { $parsedown->setSafeMode(true); } catch (\Throwable $_) {}
                // }
                
                // Fix malformed code blocks: ensure newline after language identifier
                // Streaming chunks may produce ```php<?php without newline, breaking Parsedown
                $fixedResponse = preg_replace('/```([a-zA-Z0-9+#]+)(?!\n)/', "```$1\n", $fullResponse);
                
                $html = $parsedown ? $parsedown->text($fixedResponse) : nl2br(htmlspecialchars($fixedResponse));
                
                // Format reasoning as Groq-style timeline HTML with dot + line per item
                $reasoningHtml = '';
                if ($accumulatedReasoning) {
                    $createReasoningItem = fn($content) => '<div class="reasoning-item"><div class="reasoning-item-indicator"><div class="reasoning-item-dot"></div><div class="reasoning-item-line"></div></div><div class="reasoning-item-text"><p>' . htmlspecialchars(trim(preg_replace('/\n/', ' ', $content))) . '</p></div></div>';
                    
                    // Split by sentences or newlines
                    $paragraphs = array_filter(preg_split('/\n\n+/', $accumulatedReasoning), fn($p) => trim($p));
                    if (count($paragraphs) <= 1) {
                        $paragraphs = array_filter(preg_split('/\n/', $accumulatedReasoning), fn($p) => trim($p));
                    }
                    if (count($paragraphs) <= 1 && strlen(trim($accumulatedReasoning)) > 100) {
                        $text = preg_replace('/\s+/', ' ', trim($accumulatedReasoning));
                        $parts = preg_split('/([.!?])\s+(?=[A-Z])/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
                        $sentences = [];
                        $current = '';
                        foreach ($parts as $part) {
                            if (preg_match('/^[.!?]$/', $part)) {
                                $current .= $part;
                            } else {
                                if ($current) {
                                    $sentences[] = trim($current);
                                    $current = $part;
                                } else {
                                    $current = $part;
                                }
                            }
                        }
                        if (trim($current)) $sentences[] = trim($current);
                        $paragraphs = array_filter($sentences);
                    }
                    $reasoningHtml = implode('', array_map($createReasoningItem, $paragraphs));
                }
                
                echo "data: " . json_encode([
                    'html' => $html,
                    'reasoningHtml' => $reasoningHtml,
                    'contentEmpty' => empty(trim($fullResponse)),
                    'final' => true
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
                flush();
                exit;
            }
        } catch (\Throwable $e) {
            // Ollama failed, fall through to cloud providers
            error_log("Ollama provider failed: " . $e->getMessage());
        }
    }

    // =====================================================================
    // SESSION-SELECTED PROVIDER: Ginto AI (Local)
    // =====================================================================
    // Check if user has selected the local/ginto provider with "Ginto AI - Default".
    // This sets a flag to prefer local LLM; the existing vision logic will
    // automatically route to the vision server (port 8033) for image requests
    // and reasoning server (port 8034) for text requests.
    // =====================================================================
    $forceLocalLlm = false;
    if ($sessionProvider && $sessionModel && ($sessionProvider === 'local' || $sessionProvider === 'ginto')) {
        if (\App\Core\LLM\Providers\OpenAICompatibleProvider::isGintoDefault($sessionModel)) {
            $forceLocalLlm = true;
        }
    }

    // =====================================================================
    // SESSION-SELECTED CLOUD PROVIDER (Cerebras, Groq, OpenAI, etc.)
    // =====================================================================
    // If user has explicitly selected a cloud provider from the model dropdown,
    // use that provider and model instead of automatic selection.
    // =====================================================================
    $sessionCloudProvider = null;
    $sessionCloudModel = null;
    $cloudProviders = ['cerebras', 'groq', 'openai', 'anthropic', 'together', 'fireworks'];
    if ($sessionProvider && $sessionModel && in_array($sessionProvider, $cloudProviders, true)) {
        $sessionCloudProvider = $sessionProvider;
        $sessionCloudModel = $sessionModel;
    }

    // Use OpenAICompatibleProvider with rate-limit-aware provider selection
    try {
        // Initialize ProviderKeyManager for multi-key rotation
        $keyManager = new \App\Core\ProviderKeyManager($db);
        $currentKeyId = null;
        $usingFallback = false;
        
        // Detect if this query likely needs web search
        $searchKeywords = [
            'search', 'google', 'find', 'look up', 'lookup', 'what is the latest',
            'current', 'today', 'news', 'recent', 'now', '2024', '2025',
            'price of', 'weather', 'stock', 'how much is', 'who won',
            'what happened', 'when did', 'where is', 'latest version',
            'release date', 'update', 'announced', 'breaking',
            'is it true', 'studies show', 'research', 'health', 'cause', 'effect'
        ];
        $needsWebSearch = false;
        foreach ($searchKeywords as $kw) {
            if (stripos($prompt, $kw) !== false) {
                $needsWebSearch = true;
                break;
            }
        }
        
        // =====================================================================
        // LOCAL VISION MODEL SUPPORT
        // =====================================================================
        // Check if local vision model is available for image requests.
        // Local vision models (SmolVLM2, llava, etc.) can handle images without cloud API.
        // See: src/Core/LLM/LocalLLMConfig.php for configuration details
        // =====================================================================
        $localLlmConfig = \App\Core\LLM\LocalLLMConfig::getInstance();
        $canUseLocalVision = $hasImage && $localLlmConfig->isEnabled() && $localLlmConfig->isVisionServerHealthy();
        
        // Check if request requires a specific provider
        // Vision can now use local model if available, web search still needs Groq
        $requiresGroq = $needsWebSearch; // Only web search requires Groq now
        $requiresCloudVision = $hasImage && !$canUseLocalVision; // Vision needs cloud only if local not available
        
        // =====================================================================
        // SESSION-SELECTED CLOUD PROVIDER OVERRIDE
        // =====================================================================
        // If user explicitly selected a cloud provider from the dropdown, use it.
        // This takes priority over automatic provider selection.
        // Exception: Web search requires Groq (tool calling support needed)
        // =====================================================================
        $selectedProvider = null;
        $apiKey = null;
        
        // Optional logging for debugging provider selection (set ENABLE_LOGGING=true in .env)
        $enableLogging = strtolower(getenv('ENABLE_LOGGING') ?: ($_ENV['ENABLE_LOGGING'] ?? 'false')) === 'true';
        $logFile = null;
        $logMsg = '';
        if ($enableLogging) {
            $logFile = dirname(ROOT_PATH) . '/storage/logs/ginto.log';
            $logDir = dirname($logFile);
            if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
            $logMsg = "[" . date('Y-m-d H:i:s') . "] Provider Selection Debug:\n";
            $logMsg .= "  sessionProvider: " . ($sessionProvider ?? 'null') . "\n";
            $logMsg .= "  sessionModel: " . ($sessionModel ?? 'null') . "\n";
            $logMsg .= "  sessionCloudProvider: " . ($sessionCloudProvider ?? 'null') . "\n";
            $logMsg .= "  sessionCloudModel: " . ($sessionCloudModel ?? 'null') . "\n";
            $logMsg .= "  requiresGroq: " . ($requiresGroq ? 'true' : 'false') . "\n";
            $logMsg .= "  requiresCloudVision: " . ($requiresCloudVision ? 'true' : 'false') . "\n";
        }
        
        if ($sessionCloudProvider && !$requiresGroq && !$requiresCloudVision) {
            // User explicitly selected a cloud provider - get key from database
            $sessionKeyData = $keyManager->getAvailableKey($sessionCloudProvider);
            if ($sessionKeyData) {
                $apiKey = $sessionKeyData['api_key'];
                $currentKeyId = $sessionKeyData['id'];
                $selectedProvider = $sessionCloudProvider;
                if ($enableLogging) $logMsg .= "  USING SESSION PROVIDER from DB: $selectedProvider (key_id: $currentKeyId)\n";
            } else {
                if ($enableLogging) $logMsg .= "  No DB key found for: $sessionCloudProvider - clearing session selection\n";
                // Clear session cloud provider so we don't use mismatched model
                $sessionCloudProvider = null;
                $sessionCloudModel = null;
            }
        }
        
        // If no session provider override, use automatic selection
        if (!$selectedProvider) {
            // Try to get a key from the database (unified rotation across all providers)
            $keyData = null;
            if ($requiresGroq || $requiresCloudVision) {
                // Must use Groq for web search or cloud vision
                $keyData = $keyManager->getAvailableKey('groq');
            } else {
                // Get first available key from any provider
                $keyData = $keyManager->getFirstAvailableKey();
            }
            
            if ($keyData) {
                $apiKey = $keyData['api_key'];
                $currentKeyId = $keyData['id'];
                $selectedProvider = $keyData['provider'];
            } else {
                // Fallback to environment variables
                if ($requiresGroq) {
                    // Must use Groq
                    $apiKey = getenv('GROQ_API_KEY') ?: ($_ENV['GROQ_API_KEY'] ?? '');
                    $selectedProvider = 'groq';
                } else {
                    // Try default provider from env, then fallback
                    $defaultProvider = strtolower(getenv('DEFAULT_PROVIDER') ?: ($_ENV['DEFAULT_PROVIDER'] ?? 'cerebras'));
                    $envVarPrimary = ($defaultProvider === 'cerebras') ? 'CEREBRAS_API_KEY' : 'GROQ_API_KEY';
                    $envVarFallback = ($defaultProvider === 'cerebras') ? 'GROQ_API_KEY' : 'CEREBRAS_API_KEY';
                    
                    $apiKey = getenv($envVarPrimary) ?: ($_ENV[$envVarPrimary] ?? '');
                    $selectedProvider = $defaultProvider;
                    
                    if (empty($apiKey)) {
                        $apiKey = getenv($envVarFallback) ?: ($_ENV[$envVarFallback] ?? '');
                        $selectedProvider = ($defaultProvider === 'cerebras') ? 'groq' : 'cerebras';
                        $usingFallback = true;
                    }
                }
            }
        }
        
        // =====================================================================
        // LOCAL LLM FALLBACK (REASONING + VISION)
        // =====================================================================
        // If no cloud API key is available, try local LLM as fallback.
        // Local LLMs are fundamentally different from cloud providers:
        // - No API key required (runs on your machine)
        // - No rate limits (you control the hardware)
        // - No costs (free, unlimited usage)
        // - Privacy (data never leaves your machine)
        // See: src/Core/LLM/LocalLLMConfig.php for full documentation
        // =====================================================================
        $useLocalLlm = false;
        $useLocalVision = false;
        
        // Use local LLM for reasoning if:
        // 1. User selected "Ginto AI - Default" from dropdown, OR
        // 2. No cloud API key is available, OR
        // 3. Local LLM is set as primary provider
        // Exception: Web search still needs Groq (requires tool calling)
        if (!$requiresGroq && !$hasImage && ($forceLocalLlm || $localLlmConfig->isPrimary() || empty($apiKey))) {
            if ($localLlmConfig->isEnabled() && $localLlmConfig->isReasoningServerHealthy()) {
                $useLocalLlm = true;
                $selectedProvider = 'local';
                $apiKey = 'local'; // Placeholder - local LLM doesn't need an API key
            }
        }
        
        // Use local vision model for image requests if:
        // 1. Has an image attached, AND
        // 2. Local vision server is healthy, AND
        // 3. Either user selected Ginto AI - Default, no cloud API key, OR local is set as primary
        if ($hasImage && $canUseLocalVision && ($forceLocalLlm || $localLlmConfig->isPrimary() || empty($apiKey))) {
            $useLocalVision = true;
            $useLocalLlm = true; // Vision also uses local provider
            $selectedProvider = 'local';
            $apiKey = 'local';
        }
        
        if (empty($apiKey)) {
            @header('Content-Type: text/event-stream; charset=utf-8');
            echo "data: " . json_encode(['error' => 'No API keys available. Please configure API keys or enable local LLM.']) . "\n\n";
            exit;
        }

        // Model name mapping between providers
        // Each provider may use different naming conventions for the same model
        $modelMapping = [
            'groq' => [
                'gpt-oss-120b' => 'openai/gpt-oss-120b',
                'llama-3.3-70b' => 'llama-3.3-70b-versatile',
                'vision' => 'meta-llama/llama-4-scout-17b-16e-instruct',
            ],
            'cerebras' => [
                'gpt-oss-120b' => 'gpt-oss-120b',
                'llama-3.3-70b' => 'llama-3.3-70b',
                'vision' => null, // Cerebras doesn't support vision
            ],
        ];

        // Choose model based on request type
        if ($useLocalVision && $hasImage && $imageDataUrl) {
            // =====================================================================
            // LOCAL VISION MODEL
            // =====================================================================
            // Using local vision model (SmolVLM2, llava, etc.) for image understanding.
            // This runs entirely on your machine - no cloud API needed!
            // =====================================================================
            $modelName = $localLlmConfig->getVisionModel();
        } elseif ($hasImage && $imageDataUrl) {
            // Cloud vision requests use Groq's vision model
            $modelName = $modelMapping['groq']['vision'];
        } elseif ($useLocalLlm) {
            // Local LLM uses configuration from LocalLLMConfig
            $modelName = $localLlmConfig->getReasoningModel();
        } elseif ($sessionCloudProvider && $sessionCloudModel) {
            // User explicitly selected a cloud provider/model - use their selection
            $modelName = $sessionCloudModel;
        } else {
            // Text/web search requests use provider-specific model name
            $modelName = $modelMapping[$selectedProvider]['gpt-oss-120b'] ?? 'gpt-oss-120b';
        }

        // Final logging of selected provider and model
        if ($enableLogging && $logFile) {
            $logMsg .= "  FINAL selectedProvider: $selectedProvider\n";
            $logMsg .= "  FINAL modelName: $modelName\n";
            $logMsg .= "  useLocalLlm: " . ($useLocalLlm ? 'true' : 'false') . "\n";
            $logMsg .= "  useLocalVision: " . ($useLocalVision ? 'true' : 'false') . "\n";
            $logMsg .= "---\n";
            @file_put_contents($logFile, $logMsg, FILE_APPEND);
        }

        // =====================================================================
        // CREATE PROVIDER INSTANCE
        // =====================================================================
        // Local LLM uses separate configuration class (src/Core/LLM/LocalLLMConfig.php)
        // - Reasoning model: port 8034 (text generation)
        // - Vision model: port 8033 (image understanding)
        // Cloud providers use the standard OpenAICompatibleProvider
        // =====================================================================
        if ($useLocalVision && $hasImage) {
            // Local vision model (separate server on port 8033)
            $config = $localLlmConfig->getVisionProviderConfig();
            $provider = new \App\Core\LLM\Providers\OpenAICompatibleProvider('local', [
                'api_key' => $config['api_key'],
                'model' => $config['model'],
                'base_url' => $config['base_url'],
            ]);
        } elseif ($useLocalLlm) {
            // Local reasoning model (port 8034)
            $config = $localLlmConfig->getReasoningProviderConfig();
            $provider = new \App\Core\LLM\Providers\OpenAICompatibleProvider('local', [
                'api_key' => $config['api_key'],
                'model' => $config['model'],
                'base_url' => $config['base_url'],
            ]);
        } else {
            // Cloud provider
            $provider = new \App\Core\LLM\Providers\OpenAICompatibleProvider($selectedProvider, [
                'api_key' => $apiKey,
                'model' => $modelName,
            ]);
        }

        // Prepare streaming headers
        @ini_set('output_buffering', 'off');
        @ini_set('zlib.output_compression', false);
        while (ob_get_level()) ob_end_flush();
        ignore_user_abort(true);

        // SSE headers
        if (!headers_sent()) header('Content-Type: text/event-stream; charset=utf-8');
        if (!headers_sent()) header('Cache-Control: no-cache');
        if (!headers_sent()) header('X-Accel-Buffering: no');
        if (!headers_sent()) header('Connection: keep-alive');

        // Flush initial padding
        echo str_repeat(' ', 1024);
        flush();

        // Get Parsedown for markdown rendering
        $parsedown = null;
        if (class_exists('\ParsedownExtra')) {
            try { $parsedown = new \ParsedownExtra(); } catch (\Throwable $_) {}
        } elseif (class_exists('\Parsedown')) {
            try { $parsedown = new \Parsedown(); } catch (\Throwable $_) {}
        }
        // Note: setSafeMode(true) strips PHP tags from code blocks, so we disable it
        // if ($parsedown && method_exists($parsedown, 'setSafeMode')) {
        //     try { $parsedown->setSafeMode(true); } catch (\Throwable $_) {}
        // }

        // Detect if this is a continuation request (tool result follow-up)
        $isContinuation = str_starts_with(trim($prompt), '[TOOL RESULT]') || str_contains($prompt, '=== COMPLETED STEPS ===');

        // System message with model identity and web search guidance
        $systemPrompt = 'You are Ginto, an AI assistant created by Oliver Bob. '
            . 'You are powered by advanced language models and have web search capability. '
            . 'When asked about your identity, say you are Ginto, created by Oliver Bob. But when you\'re not asked about your identity, focus on answering the user\'s questions helpfully and accurately. '
            . 'RESPONSE STYLE: Be concise and direct. Use short, clear sentences. Avoid unnecessary filler words, lengthy introductions, or verbose explanations. '
            . 'Exception: When providing code, technical explanations, or when the user explicitly asks for detailed/comprehensive responses, give thorough answers. ';
        
        // Load agent instructions module
        $agentInstructions = require __DIR__ . '/Includes/agent_instruct.php';
        
        // First check if LXC is even available on the system
        $lxcStatus = \Ginto\Helpers\LxdSandboxManager::checkLxcAvailability();
        $lxcAvailable = $lxcStatus['available'] ?? false;
        
        // Check if user has an active sandbox and add sandbox tools to system prompt
        $sandboxId = $_SESSION['sandbox_id'] ?? null;
        $hasSandbox = $lxcAvailable && !empty($sandboxId) && \Ginto\Helpers\LxdSandboxManager::sandboxExists($sandboxId);
        
        if (!$lxcAvailable) {
            // LXC not installed - guide user to install Ginto
            $systemPrompt .= $agentInstructions['lxcNotInstalled']();
        } elseif ($hasSandbox) {
            // Check premium status for sandbox_exec access
            $isPremiumUser = false;
            if (!$isAdminUser && !empty($_SESSION['user_id']) && $db) {
                try {
                    $activeSub = $db->get('subscriptions', ['id'], [
                        'user_id' => $_SESSION['user_id'],
                        'status' => 'active'
                    ]);
                    $isPremiumUser = !empty($activeSub);
                } catch (\Throwable $_) {}
            }
            
            // User has active sandbox - give full agentic instructions
            // Non-admin/non-premium users won't see sandbox_exec in the prompt
            $systemPrompt .= $agentInstructions['withSandbox']($sandboxId, $isContinuation, $isAdminUser, $isPremiumUser);
        } else {
            // No sandbox - agent can offer to install one
            $systemPrompt .= $agentInstructions['noSandbox']();
        }
        
        // Add web search guidance only for text model (vision model doesn't have browser_search)
        if (!$hasImage) {
            $systemPrompt .= 'When the user asks about current events, news, or information that would benefit from web search, use your browser_search tool. '
                . 'Be efficient: search only 3-5 most relevant sources, not more. '
                . 'Keep your reasoning concise and focused. ';
            
            // If there was an image earlier in the conversation, remind the model
            if ($hadImageInHistory) {
                $systemPrompt .= 'Note: Earlier in this conversation, the user shared an image which you analyzed. '
                    . 'When they ask follow-up questions, refer to your previous analysis of that image. '
                    . 'Messages marked with [User shared an image] indicate when an image was attached. ';
            }
        } else {
            $systemPrompt .= 'You have vision capabilities. Analyze the image carefully and provide helpful, detailed responses about what you see. ';
        }
        $systemPrompt .= 'IMPORTANT: Always reserve enough tokens to provide a complete, well-formatted final answer.';

        // Build messages with history
        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach ($history as $hm) {
            if (!empty($hm['role']) && isset($hm['content'])) {
                $messages[] = ['role' => $hm['role'], 'content' => $hm['content']];
            }
        }
        
        // Build user message - with image content if attached
        if ($hasImage && $imageDataUrl) {
            // Vision format: content is an array with text and image_url
            $userContent = [
                ['type' => 'text', 'text' => $prompt],
                ['type' => 'image_url', 'image_url' => ['url' => $imageDataUrl]]
            ];
            $messages[] = ['role' => 'user', 'content' => $userContent];
            error_log("[Vision Debug] hasImage=true, imageDataUrl length=" . strlen($imageDataUrl) . ", model=$modelName, provider=$selectedProvider");
        } else {
            // Text-only format
            $messages[] = ['role' => 'user', 'content' => $prompt];
        }

        // Track accumulated content and reasoning
        $accumulatedContent = '';
        $accumulatedReasoning = '';

        // Calculate tier-based max tokens from environment configuration
        $maxTokensBase = (int)(getenv('MAX_TOKENS_BASE') ?: ($_ENV['MAX_TOKENS_BASE'] ?? 8192));
        $tokenPercentages = [
            'admin' => (int)(getenv('MAX_TOKENS_ADMIN_PERCENT') ?: ($_ENV['MAX_TOKENS_ADMIN_PERCENT'] ?? 100)),
            'user' => (int)(getenv('MAX_TOKENS_USER_PERCENT') ?: ($_ENV['MAX_TOKENS_USER_PERCENT'] ?? 25)),
            'visitor' => (int)(getenv('MAX_TOKENS_VISITOR_PERCENT') ?: ($_ENV['MAX_TOKENS_VISITOR_PERCENT'] ?? 10)),
        ];
        $tierTokenPercent = $tokenPercentages[strtolower($userRole)] ?? $tokenPercentages['visitor'];
        $maxTokens = (int)floor($maxTokensBase * ($tierTokenPercent / 100));
        $maxTokens = max(512, $maxTokens); // Minimum 512 tokens to ensure usable responses

        // Vision model has lower max_tokens limit due to image context usage
        if ($hasImage && $imageDataUrl) {
            if ($useLocalVision) {
                // Local vision model uses configured max_tokens
                $maxTokens = min($maxTokens, $localLlmConfig->getVisionMaxTokens());
            } else {
                // Cloud vision model limit
                $maxTokens = min($maxTokens, 4096);
            }
        }

        // Apply configurable delay before contacting the model (non-blocking rate limit)
        $delayMs = (int)\Ginto\Helpers\ChatConfig::get('rateLimit.delayBetweenRequests', 0);
        if ($delayMs > 0) {
            usleep($delayMs * 1000); // Convert milliseconds to microseconds
        }

        // Stream directly from provider - browser_search is auto-added for GPT-OSS models only
        $response = $provider->chatStream(
            messages: $messages,
            tools: [], // Empty - browser_search is auto-added for GPT-OSS models (not for vision model)
            options: ['max_tokens' => $maxTokens],
            onChunk: function($chunk, $toolCall = null) use (&$accumulatedContent, &$accumulatedReasoning, $parsedown) {
                if ($toolCall !== null) {
                    // Handle activity events (websearch)
                    if (isset($toolCall['activity'])) {
                        $payload = [
                            'activity' => $toolCall['activity'],
                            'type' => $toolCall['type'] ?? null,
                            'query' => $toolCall['query'] ?? null,
                            'url' => $toolCall['url'] ?? null,
                            'domain' => $toolCall['domain'] ?? null,
                            'status' => $toolCall['status'] ?? 'running',
                        ];
                        $payload = array_filter($payload, fn($v) => $v !== null);
                        echo "data: " . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
                        flush();
                        return;
                    }
                    
                    // Handle reasoning events (thinking/analysis)
                    if (isset($toolCall['reasoning'])) {
                        $reasoningText = $toolCall['text'] ?? '';
                        // Strip internal line reference markers
                        $reasoningText = preg_replace('/【\d*†L\d+(?:-L\d+)?】/', '', $reasoningText);
                        $accumulatedReasoning .= $reasoningText;
                        echo "data: " . json_encode(['reasoning' => $reasoningText], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
                        flush();
                        return;
                    }
                    return;
                }

                if ($chunk !== '' && $chunk !== null) {
                    // Strip internal line reference markers like 【2†L31-L38】 or 【†L29-L33】
                    $chunk = preg_replace('/【\d*†L\d+(?:-L\d+)?】/', '', $chunk);
                    // Strip model's leaked tool call intent patterns like "Search web.{...}"
                    $chunk = preg_replace('/Search web\.\{[^}]+\}\.{0,3}/i', '', $chunk);
                    // Skip empty chunks after filtering
                    if (trim($chunk) === '') return;
                    $accumulatedContent .= $chunk;
                    
                    // Client now uses markdown-it + KaTeX for rendering
                    // Server just sends raw text chunks for better streaming performance
                    $renderOnServer = \Ginto\Helpers\ChatConfig::get('streaming.renderMarkdownOnServer', false);
                    if ($renderOnServer && $parsedown) {
                        // Legacy: Render accumulated content as HTML on server
                        // Fix malformed code blocks before parsing
                        $html = $parsedown->text(_fixCodeBlockNewlines($accumulatedContent));
                        echo "data: " . json_encode(['html' => $html, 'text' => $chunk], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
                    } else {
                        // Preferred: Send raw text, let client render with markdown-it
                        echo "data: " . json_encode(['text' => $chunk], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
                    }
                    flush();
                }
            }
        );

        // Send final properly-rendered Markdown as HTML
        $finalContent = $accumulatedContent ?: ($response->getContent() ?? '');
        // Strip any remaining line reference markers
        $finalContent = preg_replace('/【\d*†L\d+(?:-L\d+)?】/', '', $finalContent);
        // Strip model's leaked tool call intent patterns like "Search web.{...}"
        $finalContent = preg_replace('/Search web\.\{[^}]+\}\.{0,3}/i', '', $finalContent);
        $accumulatedReasoning = preg_replace('/【\d*†L\d+(?:-L\d+)?】/', '', $accumulatedReasoning);
        
        // Log full response for debugging LaTeX rendering
        $logDir = dirname(__DIR__, 2) . '/../storage/logs';
        if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
        $logEntry = date('Y-m-d H:i:s') . " [CHAT RESPONSE]\n";
        $logEntry .= "=== RAW CONTENT ===\n" . $finalContent . "\n";
        $logEntry .= "=== REASONING ===\n" . $accumulatedReasoning . "\n";
        $logEntry .= "==================\n\n";
        @file_put_contents($logDir . '/ginto.log', $logEntry, FILE_APPEND | LOCK_EX);
        
        // Send final HTML-rendered version with reasoning
        if ($finalContent || $accumulatedReasoning) {
            // Fix malformed code blocks before parsing
            $html = $finalContent ? ($parsedown ? $parsedown->text(_fixCodeBlockNewlines($finalContent)) : '<pre>' . htmlspecialchars($finalContent) . '</pre>') : '';
            
            // Format reasoning as Groq-style timeline HTML with dot + line per item
            $reasoningHtml = '';
            if ($accumulatedReasoning) {
                // Helper to create a reasoning item with Groq-style structure
                $createReasoningItem = fn($content) => '<div class="reasoning-item"><div class="reasoning-item-indicator"><div class="reasoning-item-dot"></div><div class="reasoning-item-line"></div></div><div class="reasoning-item-text"><p>' . htmlspecialchars(trim(preg_replace('/\n/', ' ', $content))) . '</p></div></div>';
                
                // First try double newlines
                $paragraphs = array_filter(preg_split('/\n\n+/', $accumulatedReasoning), fn($p) => trim($p));
                
                // If only one paragraph, try single newlines
                if (count($paragraphs) <= 1) {
                    $paragraphs = array_filter(preg_split('/\n/', $accumulatedReasoning), fn($p) => trim($p));
                }
                
                // If still one paragraph and text is long, split by sentence patterns
                if (count($paragraphs) <= 1 && strlen(trim($accumulatedReasoning)) > 100) {
                    $text = preg_replace('/\s+/', ' ', trim($accumulatedReasoning));
                    $parts = preg_split('/([.!?])\s+(?=(?:The |User |But |However |Now |Let\'s |Let us |We |I |Need |Should |Could |Open |Search |Find |Check |Read |Visit |Look |Get |Try |Maybe |Also |Next |Then |First |Second |Third |Finally |Result|Found |Using |Based |According |After |Before |From |Provide |Use |This |That |It |Access|Blocked|Seems|Likely|Possibly|Could be|So ))/i', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
                    
                    $sentences = [];
                    $current = '';
                    foreach ($parts as $part) {
                        if (preg_match('/^[.!?]$/', $part)) {
                            $current .= $part;
                        } else {
                            if ($current && preg_match('/^(The |User |But |However |Now |Let\'s |Let us |We |I |Need |Should |Could |Open |Search |Find |Check |Read |Visit |Look |Get |Try |Maybe |Also |Next |Then |First |Second |Third |Finally |Result|Found |Using |Based |According |After |Before |From |Provide |Use |This |That |It |Access|Blocked|Seems|Likely|Possibly|Could be|So )/i', $part)) {
                                $sentences[] = trim($current);
                                $current = $part;
                            } else {
                                $current .= ($current && !preg_match('/[.!?]$/', $current) ? ' ' : '') . $part;
                            }
                        }
                    }
                    if (trim($current)) {
                        $sentences[] = trim($current);
                    }
                    $paragraphs = array_filter($sentences);
                }
                
                $reasoningHtml = implode('', array_map($createReasoningItem, $paragraphs));
            }
            
            echo "data: " . json_encode([
                'html' => $html,
                'reasoningHtml' => $reasoningHtml,
                'contentEmpty' => empty(trim($finalContent)),
                'final' => true
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
            flush();
        }

        // Log successful request for rate limiting tracking
        $requestLatency = (int)((microtime(true) - $requestStartTime) * 1000);
        $tokensEstimate = (int)(strlen($prompt) / 4) + (int)(strlen($accumulatedContent) / 4); // Rough estimate
        
        // Mark the API key as successfully used
        if (isset($keyManager) && isset($currentKeyId) && $currentKeyId) {
            $keyManager->markKeyUsed($currentKeyId);
        }
        
        // Record per-user usage (protects against hitting provider limits)
        if (isset($userRateLimiter)) {
            $userRateLimiter->recordUsage(
                $userIdSession ? (int)$userIdSession : null,
                $visitorIp,
                $tokensEstimate
            );
        }
        
        $rateLimitService->logRequest([
            'user_id' => $userId,
            'user_role' => $userRole,
            'provider' => $selectedProvider,
            'model' => $modelName,
            'tokens_input' => (int)(strlen($prompt) / 4),
            'tokens_output' => (int)(strlen($accumulatedContent) / 4),
            'request_type' => $hasImage ? 'vision' : 'chat',
            'response_status' => 'success',
            'fallback_used' => $usingFallback ? 1 : 0,
            'latency_ms' => $requestLatency,
        ]);

    } catch (\Throwable $e) {
        // Check if this is a rate limit error and mark the key accordingly
        $errorMessage = $e->getMessage();
        $isRateLimitError = (
            stripos($errorMessage, 'rate limit') !== false ||
            stripos($errorMessage, 'rate_limit') !== false ||
            stripos($errorMessage, '429') !== false ||
            stripos($errorMessage, 'too many requests') !== false
        );
        
        if ($isRateLimitError && isset($keyManager) && isset($currentKeyId) && $currentKeyId) {
            // Mark current key as rate-limited and try to get next key
            $keyManager->markKeyRateLimited($currentKeyId, 60);
            
            // Try to get next available key for retry hint
            $nextKey = $keyManager->getNextAvailableKey($currentKeyId);
            if ($nextKey) {
                // There's another key available - could retry with it
                error_log("Rate limit hit on key {$currentKeyId}, next available: {$nextKey['id']} ({$nextKey['provider']})");
            } else {
                // No more DB keys, will fall back to .env keys on next request
                error_log("Rate limit hit on key {$currentKeyId}, no more DB keys - will use .env on next request");
            }
        }
        
        // Log failed request
        if (isset($rateLimitService)) {
            $requestLatency = isset($requestStartTime) ? (int)((microtime(true) - $requestStartTime) * 1000) : 0;
            $rateLimitService->logRequest([
                'user_id' => $userId ?? session_id(),
                'user_role' => $userRole ?? 'visitor',
                'provider' => $selectedProvider ?? 'groq',
                'model' => $modelName ?? 'openai/gpt-oss-120b',
                'tokens_input' => isset($prompt) ? (int)(strlen($prompt) / 4) : 0,
                'tokens_output' => 0,
                'request_type' => ($hasImage ?? false) ? 'vision' : 'chat',
                'response_status' => 'error',
                'fallback_used' => ($usingFallback ?? false) ? 1 : 0,
                'latency_ms' => $requestLatency,
            ]);
        }
        
        // Fallback error handling
        @ini_set('output_buffering', 'off');
        while (ob_get_level()) ob_end_flush();
        
        // Determine user-friendly error message
        $userError = 'An internal error occurred while processing your request.';
        if (stripos($errorMessage, 'connection refused') !== false || 
            stripos($errorMessage, 'could not connect') !== false ||
            stripos($errorMessage, 'connection timed out') !== false ||
            stripos($errorMessage, 'curl error') !== false) {
            $userError = 'Unable to connect to the AI model. The service may be temporarily unavailable.';
        } elseif (stripos($errorMessage, 'timeout') !== false) {
            $userError = 'The AI model took too long to respond. Please try again.';
        } elseif ($isRateLimitError) {
            $userError = 'Rate limit exceeded. Please wait a moment and try again.';
        }
        
        // Send error as SSE so frontend can display it properly
        if (!headers_sent()) {
            header('Content-Type: text/event-stream; charset=utf-8');
            header('Cache-Control: no-cache');
        }
        echo str_repeat(' ', 1024);
        flush();

        // Log full details for administrators, but don't expose provider/raw error text to clients
        \Ginto\Helpers\AdminErrorLogger::log($e->getMessage(), ['route' => '/chat', 'trace' => $e->getTraceAsString()]);
        echo "data: " . json_encode(['error' => $userError]) . "\n\n";
        flush();
    }

    exit;
});

/**
 * Helper: Fix malformed code blocks for Parsedown.
 * Streaming chunks may produce ```php<?php without newline, breaking markdown parsing.
 * Also fixes <?php immediately followed by code (no newline).
 * Also adds missing closing ?> tag to PHP code blocks.
 */
function _fixCodeBlockNewlines(string $content): string
{
    // Fix missing newline after language identifier (```php + opening tag -> ```php\n + opening tag)
    $content = preg_replace('/```([a-zA-Z0-9+#]+)(?!\n)/', "```$1\n", $content);
    
    $phpOpen = '<' . '?php';
    $phpClose = '?' . '>';
    
    // Fix opening tag immediately followed by comment - add space
    $content = preg_replace('/' . preg_quote($phpOpen, '/') . '(?=\/\/)/', $phpOpen . ' ', $content);
    
    // Fix opening tag immediately followed by code (not comment/space) - add newline
    $content = preg_replace('/' . preg_quote($phpOpen, '/') . '(?=[^\s\/])/', $phpOpen . "\n", $content);
    
    // Add missing closing tag to PHP code blocks
    // Match ```php ... ``` blocks that start with opening PHP tag but don't end with closing tag
    $content = preg_replace_callback(
        '/```php\s*(' . preg_quote($phpOpen, '/') . '[\s\S]*?)```/i',
        function($matches) use ($phpClose) {
            $code = $matches[1];
            // Check if it already ends with closing tag
            if (!preg_match('/' . preg_quote($phpClose, '/') . '\s*$/', trim($code))) {
                // Add closing tag before closing fence
                $code = rtrim($code) . "\n" . $phpClose;
            }
            return "```php\n" . $code . "\n```";
        },
        $content
    );
    
    return $content;
}

/**
 * Helper: Send SSE data chunk for chat streaming.
 */
function _chatSendSSE(string $content, $parsedown): void
{
    if ($content === '') return;

    try {
        // Fix malformed code blocks before parsing
        $content = _fixCodeBlockNewlines($content);
        if ($parsedown !== null) {
            $html = $parsedown->text($content);
        } else {
            $html = '<pre>' . htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>';
        }
        echo "data: " . json_encode(['html' => $html], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
        flush();
    } catch (\Throwable $_) {
        echo $content;
        flush();
    }
}

// ============================================================================
// Courses Route - Educational courses listing
// ============================================================================
$router->req('/courses', 'CourseController@index');

// Pricing Page (must be before /courses/{slug} to avoid slug matching "pricing")
$router->req('/courses/pricing', 'CourseController@pricing');

// Upgrade Page (standalone upgrade/pricing page)
$router->req('/upgrade', 'SubscriptionController@upgrade');

// Subscribe Page - PayPal Checkout
$router->req('/subscribe', 'SubscriptionController@subscribe');

// Subscribe Success Page
$router->req('/subscribe/success', 'SubscriptionController@success');

// API: Subscription Activation (called after PayPal approval)
$router->req('/api/subscription/activate', 'SubscriptionController@activate', ['POST']);

// PayPal Order Creation for Registration (one-time membership payment)
$router->req('/api/register/paypal-order', 'PaymentController@paypalOrder');

// PayPal Order Capture for Registration (one-time membership payment)
// PayPal Order Capture for Registration (one-time membership payment)
$router->req('/api/register/paypal-capture', 'PaymentController@paypalCapture');

// Course Detail Page
$router->req('/courses/{slug}', 'CourseController@detail');

// Lesson Page
// Lesson Page
$router->req('/courses/{courseSlug}/lesson/{lessonSlug}', 'CourseController@lesson');

// Mark lesson complete API
// Mark lesson complete API
$router->req('/api/courses/complete-lesson', 'CourseController@completeLesson');

// ============================================================================
// Masterclass Routes - In-depth technical training
// ============================================================================

// Masterclass Listing Page
$router->req('/masterclass', 'MasterclassController@index');

// Masterclass Pricing Page (must be before /masterclass/{slug} to avoid slug matching "pricing")
$router->req('/masterclass/pricing', 'MasterclassController@pricing');

// Masterclass Detail Page
$router->req('/masterclass/{slug}', 'MasterclassController@detail');

// Masterclass Lesson Page
// Masterclass Lesson Page
$router->req('/masterclass/{masterclassSlug}/lesson/{lessonSlug}', 'MasterclassController@lesson');

// Mark masterclass lesson complete API
// Mark masterclass lesson complete API
$router->req('/api/masterclass/complete-lesson', 'MasterclassController@completeLesson');

// ============================================================================
// Web Search Test Route - Isolated test for GPT-OSS browser_search
// ============================================================================
$router->req('/websearch', function() {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        \Ginto\Core\View::view('chat/websearch', ['title' => 'Web Search Test']);
        exit;
    }

    // POST: Stream GPT-OSS browser_search response
    $prompt = $_POST['prompt'] ?? trim(file_get_contents('php://input')) ?: 'What is the weather today?';

    // Set up streaming headers
    @ini_set('output_buffering', 'off');
    @ini_set('zlib.output_compression', false);
    while (ob_get_level()) ob_end_flush();
    ignore_user_abort(true);

    if (!headers_sent()) header('Content-Type: text/event-stream; charset=utf-8');
    if (!headers_sent()) header('Cache-Control: no-cache');
    if (!headers_sent()) header('X-Accel-Buffering: no');
    if (!headers_sent()) header('Connection: keep-alive');

    echo str_repeat(' ', 1024);
    flush();

    try {
        // Use OpenAICompatibleProvider directly for Groq GPT-OSS
        $apiKey = getenv('GROQ_API_KEY') ?: ($_ENV['GROQ_API_KEY'] ?? '');
        if (empty($apiKey)) {
            echo "data: " . json_encode(['error' => 'GROQ_API_KEY not configured']) . "\n\n";
            exit;
        }

        $provider = new \App\Core\LLM\Providers\OpenAICompatibleProvider('groq', [
            'api_key' => $apiKey,
            'model' => 'openai/gpt-oss-120b',
        ]);

        // System message with model identity and web search guidance
        $systemPrompt = 'You are Ginto, an AI assistant created by Oliver Bob. '
            . 'You are powered by advanced language models and have web search capability. '
            . 'When asked about your identity, say you are Ginto, created by Oliver Bob. '
            . 'When searching the web, be efficient: search only 3-5 most relevant sources, not more. '
            . 'Keep your reasoning concise and focused. '
            . 'IMPORTANT: Always reserve enough tokens to provide a complete, well-formatted final answer. '
            . 'Prioritize the final response over exhaustive reasoning.';

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $prompt]
        ];

        $accumulatedContent = '';
        $accumulatedReasoning = '';
        
        // Apply configurable delay before contacting the model (non-blocking rate limit)
        $delayMs = (int)\Ginto\Helpers\ChatConfig::get('rateLimit.delayBetweenRequests', 0);
        if ($delayMs > 0) {
            usleep($delayMs * 1000); // Convert milliseconds to microseconds
        }
        
        $response = $provider->chatStream(
            messages: $messages,
            tools: [], // browser_search is auto-added for GPT-OSS
            options: [],
            onChunk: function($chunk, $toolCall = null) use (&$accumulatedContent, &$accumulatedReasoning) {
                if ($toolCall !== null) {
                    // Handle activity events (websearch)
                    if (isset($toolCall['activity'])) {
                        $payload = [
                            'activity' => $toolCall['activity'],
                            'type' => $toolCall['type'] ?? null,
                            'query' => $toolCall['query'] ?? null,
                            'url' => $toolCall['url'] ?? null,
                            'domain' => $toolCall['domain'] ?? null,
                            'status' => $toolCall['status'] ?? 'running',
                        ];
                        $payload = array_filter($payload, fn($v) => $v !== null);
                        echo "data: " . json_encode($payload, JSON_UNESCAPED_SLASHES) . "\n\n";
                        flush();
                        return;
                    }
                    
                    // Handle reasoning events (thinking/analysis)
                    if (isset($toolCall['reasoning'])) {
                        $reasoningText = $toolCall['text'] ?? '';
                        // Strip internal line reference markers
                        $reasoningText = preg_replace('/【\d*†L\d+(?:-L\d+)?】/', '', $reasoningText);
                        $accumulatedReasoning .= $reasoningText;
                        echo "data: " . json_encode(['reasoning' => $reasoningText], JSON_UNESCAPED_SLASHES) . "\n\n";
                        flush();
                        return;
                    }
                    return;
                }

                if ($chunk !== '' && $chunk !== null) {
                    // Strip internal line reference markers like 【2†L31-L38】 or 【†L29-L33】
                    $chunk = preg_replace('/【\d*†L\d+(?:-L\d+)?】/', '', $chunk);
                    $accumulatedContent .= $chunk;
                    echo "data: " . json_encode(['text' => $chunk], JSON_UNESCAPED_SLASHES) . "\n\n";
                    flush();
                }
            }
        );

        // Send final response with separate reasoning and content
        $finalContent = $accumulatedContent ?: ($response->content ?? '');
        // Strip any remaining line reference markers
        $finalContent = preg_replace('/【\d*†L\d+(?:-L\d+)?】/', '', $finalContent);
        $accumulatedReasoning = preg_replace('/【\d*†L\d+(?:-L\d+)?】/', '', $accumulatedReasoning);
        
        // If content is empty but we have reasoning, extract the useful answer from reasoning
        // The model sometimes puts the answer in reasoning when it runs out of tokens
        $contentWasEmpty = empty(trim($finalContent));
        
        if ($finalContent || $accumulatedReasoning) {
            $parsedown = class_exists('\Parsedown') ? new \Parsedown() : null;
            // Note: setSafeMode(true) strips PHP tags from code blocks, so we disable it
            // if ($parsedown && method_exists($parsedown, 'setSafeMode')) {
            //     $parsedown->setSafeMode(true);
            // }
            
            // If content is empty, use a summary message pointing to reasoning
            if ($contentWasEmpty && !empty($accumulatedReasoning)) {
                $finalContent = "*The model's analysis is shown in the reasoning section above. The response exceeded the token limit before generating a final summary.*";
            }
            
            // Fix malformed code blocks before parsing
            $html = $parsedown ? $parsedown->text(_fixCodeBlockNewlines($finalContent)) : nl2br(htmlspecialchars($finalContent));
            
            // Format reasoning as timeline HTML - split into paragraphs for dot indicators
            $reasoningHtml = '';
            if ($accumulatedReasoning) {
                // First try double newlines
                $paragraphs = array_filter(preg_split('/\n\n+/', $accumulatedReasoning), fn($p) => trim($p));
                
                // If only one paragraph, try single newlines
                if (count($paragraphs) <= 1) {
                    $paragraphs = array_filter(preg_split('/\n/', $accumulatedReasoning), fn($p) => trim($p));
                }
                
                // If still one paragraph and text is long, split by sentence patterns
                if (count($paragraphs) <= 1 && strlen(trim($accumulatedReasoning)) > 100) {
                    $text = preg_replace('/\s+/', ' ', trim($accumulatedReasoning));
                    $parts = preg_split('/([.!?])\s+(?=(?:The |User |But |However |Now |Let\'s |Let us |We |I |Need |Should |Could |Open |Search |Find |Check |Read |Visit |Look |Get |Try |Maybe |Also |Next |Then |First |Second |Third |Finally |Result|Found |Using |Based |According |After |Before |From |Provide |Use |This |That |It |Access|Blocked|Seems|Likely|Possibly|Could be|So ))/i', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
                    
                    $sentences = [];
                    $current = '';
                    foreach ($parts as $part) {
                        if (preg_match('/^[.!?]$/', $part)) {
                            $current .= $part;
                        } else {
                            if ($current && preg_match('/^(The |User |But |However |Now |Let\'s |Let us |We |I |Need |Should |Could |Open |Search |Find |Check |Read |Visit |Look |Get |Try |Maybe |Also |Next |Then |First |Second |Third |Finally |Result|Found |Using |Based |According |After |Before |From |Provide |Use |This |That |It |Access|Blocked|Seems|Likely|Possibly|Could be|So )/i', $part)) {
                                $sentences[] = trim($current);
                                $current = $part;
                            } else {
                                $current .= ($current && !preg_match('/[.!?]$/', $current) ? ' ' : '') . $part;
                            }
                        }
                    }
                    if (trim($current)) {
                        $sentences[] = trim($current);
                    }
                    $paragraphs = array_filter($sentences);
                }
                
                $reasoningHtml = implode('', array_map(fn($p) => '<div class="reasoning-item"><div class="reasoning-item-indicator"><div class="reasoning-item-dot"></div><div class="reasoning-item-line"></div></div><div class="reasoning-item-text"><p>' . htmlspecialchars(trim(preg_replace('/\n/', ' ', $p))) . '</p></div></div>', $paragraphs));
            }
            
            echo "data: " . json_encode([
                'html' => $html, 
                'reasoningHtml' => $reasoningHtml,
                'contentEmpty' => $contentWasEmpty,
                'final' => true
            ], JSON_UNESCAPED_SLASHES) . "\n\n";
            flush();
        }

    } catch (\Throwable $e) {
        error_log('[websearch] Error: ' . $e->getMessage());
        echo "data: " . json_encode(['error' => $e->getMessage()]) . "\n\n";
        flush();
    }

    exit;
});

/**
 * Helper: Stream a complete response as SSE.
 */
function _chatStreamResponse(string $content): void
{
    @ini_set('output_buffering', 'off');
    @ini_set('zlib.output_compression', false);
    while (ob_get_level()) ob_end_flush();
    ignore_user_abort(true);

    if (!headers_sent()) header('Content-Type: text/plain; charset=utf-8');
    if (!headers_sent()) header('Cache-Control: no-cache');
    if (!headers_sent()) header('X-Accel-Buffering: no');
    echo str_repeat(' ', 1024);
    flush();

    $parsedown = null;
    if (class_exists('\Parsedown')) {
        // Note: setSafeMode(true) strips PHP tags from code blocks, so we disable it
        try { $parsedown = new \Parsedown(); /* $parsedown->setSafeMode(true); */ } catch (\Throwable $_) {}
    }

    // Fix malformed code blocks before parsing
    $fixedContent = _fixCodeBlockNewlines($content);
    $html = $parsedown ? $parsedown->text($fixedContent) : '<pre>' . htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>';
    echo "data: " . json_encode(['html' => $html], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
    flush();
}

// MCP tool call endpoint - lightweight session-based tool execution for chat UI (admin only)
$router->req('/mcp/call', function() {
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
    
    // Admin check - require login and admin role
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    $isAdmin = (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin');
    $tokenHeader = $_SERVER['HTTP_X_GINTO_ADMIN_TOKEN'] ?? $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? null;
    $expected = getenv('GINTO_ADMIN_TOKEN') ?: getenv('ADMIN_TOKEN');
    if (!$isAdmin && $expected && $tokenHeader && hash_equals((string)$expected, (string)$tokenHeader)) $isAdmin = true;
    if (!$isAdmin) {
        http_response_code(403);
        echo json_encode(['error' => 'Admin access required']);
        exit;
    }
    
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
});

// Lightweight MCP probe endpoint for client UI to check local MCP availability (admin only).
$router->req('/mcp/probe', function() {
    header('Content-Type: application/json; charset=utf-8');
    
    // Admin check - require login and admin role
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    $isAdmin = (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin');
    $tokenHeader = $_SERVER['HTTP_X_GINTO_ADMIN_TOKEN'] ?? $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? null;
    $expected = getenv('GINTO_ADMIN_TOKEN') ?: getenv('ADMIN_TOKEN');
    if (!$isAdmin && $expected && $tokenHeader && hash_equals((string)$expected, (string)$tokenHeader)) $isAdmin = true;
    if (!$isAdmin) {
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
});

// Debug endpoint to inspect LLM env visibility to PHP (admin-protected)
$router->req('/debug/llm', function() {
    header('Content-Type: application/json; charset=utf-8');
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    $isAdmin = (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin');
    $tokenHeader = $_SERVER['HTTP_X_GINTO_ADMIN_TOKEN'] ?? $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? null;
    $expected = getenv('GINTO_ADMIN_TOKEN') ?: getenv('ADMIN_TOKEN');
    if (!$isAdmin && $expected && $tokenHeader && hash_equals((string)$expected, (string)$tokenHeader)) $isAdmin = true;

    // Allow localhost requests for debugging when not authenticated
    $remote = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!$isAdmin && $remote !== '127.0.0.1' && $remote !== '::1') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'unauthorized']);
        exit;
    }

    // Gather safe diagnostics (do NOT echo secret values)
    $vars = [
        'LLM_PROVIDER' => getenv('LLM_PROVIDER') ?: null,
        'LLM_MODEL' => getenv('LLM_MODEL') ?: null,
    ];
    $keys = [
        'GROQ_API_KEY' => (getenv('GROQ_API_KEY') ? true : false),
        'OPENAI_API_KEY' => (getenv('OPENAI_API_KEY') ? true : false),
        'ANTHROPIC_API_KEY' => (getenv('ANTHROPIC_API_KEY') ? true : false),
    ];

    // Which providers appear configured according to LLMProviderFactory
    $configured = [];
    try { $configured = \App\Core\LLM\LLMProviderFactory::getConfiguredProviders(); } catch (\Throwable $_) { $configured = []; }

    // Try to create a provider instance from env - this reveals which provider
    // will be used for requests and which model is active. Don't expose secrets.
    $provider_info = null;
    try {
        $prov = \App\Core\LLM\LLMProviderFactory::fromEnv();
        $provider_info = [
            'name' => $prov->getName(),
            'configured' => $prov->isConfigured(),
            'default_model' => $prov->getDefaultModel(),
            'available_models' => $prov->getModels(),
        ];

        // If caller requested, persist provider/model into the session so it's
        // easy to inspect for a user session. Use ?save_session=1 to set.
        if (!empty($_GET['save_session'])) {
            $_SESSION['llm_provider_name'] = $provider_info['name'];
            $_SESSION['llm_model'] = $provider_info['default_model'];
        }
    } catch (\Throwable $e) {
        $provider_info = ['error' => $e->getMessage()];
    }

    echo json_encode([
        'success' => true,
        'remote_addr' => $remote,
        'vars' => $vars,
        'api_keys_present' => $keys,
        'providers_configured' => $configured,
        'active_provider' => $provider_info,
        'session' => [
            'llm_provider_name' => $_SESSION['llm_provider_name'] ?? null,
            'llm_model' => $_SESSION['llm_model'] ?? null,
        ],
        'php_sapi' => php_sapi_name(),
    ], JSON_PRETTY_PRINT);
    exit;
});

// Admin API: Get available models for all configured providers (including Ollama)
$router->req('/api/models', function() {
    header('Content-Type: application/json; charset=utf-8');
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    
    // Admin check
    $isAdmin = (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin');
    $tokenHeader = $_SERVER['HTTP_X_GINTO_ADMIN_TOKEN'] ?? $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? null;
    $expected = getenv('GINTO_ADMIN_TOKEN') ?: getenv('ADMIN_TOKEN');
    if (!$isAdmin && $expected && $tokenHeader && hash_equals((string)$expected, (string)$tokenHeader)) $isAdmin = true;
    if (!$isAdmin) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Admin access required']);
        exit;
    }

    // Use ProviderRegistry for unified model management
    $db = \Ginto\Core\Database::getInstance();
    $registry = \App\Core\LLM\ProviderRegistry::getInstance()->setDatabase($db);
    
    $forceRefresh = !empty($_GET['refresh']);
    
    $result = [
        'success' => true,
        'current_provider' => $_SESSION['llm_provider_name'] ?? (getenv('LLM_PROVIDER') ?: 'groq'),
        'current_model' => $_SESSION['llm_model'] ?? (getenv('LLM_MODEL') ?: null),
        'current_capabilities' => null,
        'providers' => [],
        'running_models' => [], // Ollama models currently loaded in memory
    ];
    
    // Get current model capabilities
    if ($result['current_provider'] && $result['current_model']) {
        $result['current_capabilities'] = $registry->getModelCapabilities(
            $result['current_provider'], 
            $result['current_model']
        );
    }

    // Check Ollama running models
    $cacheDir = (defined('STORAGE_PATH') ? STORAGE_PATH : dirname(ROOT_PATH)) . '/cache';
    $cacheFile = $cacheDir . '/ollama_ps.json';
    $runningModels = [];
    
    if (file_exists($cacheFile)) {
        $cacheData = @json_decode(file_get_contents($cacheFile), true);
        if (!empty($cacheData['updated_at']) && (time() - $cacheData['updated_at']) < 60) {
            $runningModels = $cacheData['models'] ?? [];
        }
    }
    
    // Live check if cache stale
    if (empty($runningModels)) {
        try {
            $ch = curl_init('http://localhost:11434/api/ps');
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 2]);
            $psResponse = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200 && $psResponse) {
                $psData = json_decode($psResponse, true);
                foreach ($psData['models'] ?? [] as $m) {
                    if (!empty($m['name'])) $runningModels[] = $m['name'];
                }
                if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
                @file_put_contents($cacheFile, json_encode([
                    'models' => $runningModels, 'updated_at' => time()
                ], JSON_PRETTY_PRINT));
            }
        } catch (\Throwable $e) {}
    }
    $result['running_models'] = $runningModels;

    // Get all configured providers with live models
    $priority = \App\Core\LLM\ProviderRegistry::getProviderPriority();
    
    foreach ($priority as $providerName) {
        if (!$registry->isConfigured($providerName)) {
            continue;
        }
        
        try {
            $models = $registry->getModels($providerName, $forceRefresh);
            $config = $registry->getProviderConfig($providerName);
            
            // Extract model IDs and capabilities
            $modelIds = [];
            $modelCapabilities = [];
            foreach ($models as $model) {
                $modelId = is_array($model) ? ($model['id'] ?? '') : $model;
                if ($modelId) {
                    $modelIds[] = $modelId;
                    $modelCapabilities[$modelId] = is_array($model) && isset($model['capabilities']) 
                        ? $model['capabilities'] 
                        : $registry->getModelCapabilities($providerName, $modelId);
                }
            }
            
            $result['providers'][$providerName] = [
                'display_name' => $config['display_name'] ?? $providerName,
                'configured' => true,
                'supports_tools' => $config['supports_tools'] ?? true,
                'models' => $modelIds,
                'capabilities' => $modelCapabilities,
            ];
            
            // Add local server health info
            if ($providerName === 'local') {
                try {
                    $localConfig = \App\Core\LLM\LocalLLMConfig::getInstance();
                    $result['providers'][$providerName]['servers'] = [
                        'reasoning' => ['healthy' => $localConfig->isReasoningServerHealthy()],
                        'vision' => ['healthy' => $localConfig->isVisionServerHealthy()],
                    ];
                } catch (\Throwable $e) {}
            }
        } catch (\Throwable $e) {
            // Skip on error
        }
    }

    echo json_encode($result, JSON_PRETTY_PRINT);
    exit;
});

// Admin API: Set active provider and model for the session
$router->req('/api/models/set', function() {
    header('Content-Type: application/json; charset=utf-8');
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    
    // Admin check
    $isAdmin = (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin');
    $tokenHeader = $_SERVER['HTTP_X_GINTO_ADMIN_TOKEN'] ?? $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? null;
    $expected = getenv('GINTO_ADMIN_TOKEN') ?: getenv('ADMIN_TOKEN');
    if (!$isAdmin && $expected && $tokenHeader && hash_equals((string)$expected, (string)$tokenHeader)) $isAdmin = true;
    if (!$isAdmin) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Admin access required']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $provider = $input['provider'] ?? null;
    $model = $input['model'] ?? null;

    if (!$provider || !$model) {
        echo json_encode(['success' => false, 'error' => 'Missing provider or model']);
        exit;
    }

    // Use ProviderRegistry for unified configuration
    $db = \Ginto\Core\Database::getInstance();
    $registry = \App\Core\LLM\ProviderRegistry::getInstance()->setDatabase($db);
    
    // Validate provider is configured
    if (!$registry->isConfigured($provider)) {
        echo json_encode(['success' => false, 'error' => "Provider '$provider' is not configured"]);
        exit;
    }
    
    // Get model capabilities
    $capabilities = $registry->getModelCapabilities($provider, $model);

    // Store in session
    $_SESSION['llm_provider_name'] = $provider;
    $_SESSION['llm_model'] = $model;
    $_SESSION['llm_capabilities'] = $capabilities;

    echo json_encode([
        'success' => true,
        'provider' => $provider,
        'model' => $model,
        'capabilities' => $capabilities,
        'message' => "Switched to $provider / $model",
    ]);
    exit;
});

// Standard MCP chat endpoint: calls the local MCP server's `chat_completion` tool (admin only).
// Returns JSON: { success: bool, reply: string, raw: mixed }
$router->req('/mcp/chat', function() {
    header('Content-Type: application/json; charset=utf-8');
    
    // Admin check - require login and admin role
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    $isAdmin = (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin');
    $tokenHeader = $_SERVER['HTTP_X_GINTO_ADMIN_TOKEN'] ?? $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? null;
    $expected = getenv('GINTO_ADMIN_TOKEN') ?: getenv('ADMIN_TOKEN');
    if (!$isAdmin && $expected && $tokenHeader && hash_equals((string)$expected, (string)$tokenHeader)) $isAdmin = true;
    if (!$isAdmin) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Admin access required']);
        exit;
    }
    
    // Accept both 'message' (from chat.js) and 'prompt' (legacy)
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $message = $input['message'] ?? $input['prompt'] ?? $_POST['prompt'] ?? '';
    $history = $input['history'] ?? [];
    
    if (empty($message)) {
        echo json_encode(['success' => false, 'error' => 'Missing message']);
        exit;
    }
    
    // Use StandardMcpHost for proper tool execution loop
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

        // Server-side: detect free-form tool call text (fallback for poorly-structured responses)
        $toolCall = null;
        if (is_string($response)) {
            require_once __DIR__ . '/../Core/LLM/ToolCallParser.php';
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
            // Add more tool-specific validations here as needed

            // Generic check: arguments must be an array
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
});

// Admin-protected in-process MCP invoke endpoint. Accepts JSON body:
// { "tool": "namespace/name", "args": { ... } }
$router->req('/mcp/invoke', function() {
    header('Content-Type: application/json; charset=utf-8');
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    $isAdmin = (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin');
    $tokenHeader = $_SERVER['HTTP_X_GINTO_ADMIN_TOKEN'] ?? $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? null;
    $expected = getenv('GINTO_ADMIN_TOKEN') ?: getenv('ADMIN_TOKEN');
    if (!$isAdmin && $expected && $tokenHeader && hash_equals((string)$expected, (string)$tokenHeader)) $isAdmin = true;
    if (!$isAdmin) { http_response_code(403); echo json_encode(['success' => false, 'error' => 'unauthorized']); exit; }

    // Load .env into getenv() if vlucas/phpdotenv is present and .env exists
    try {
        $root = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 1);
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
        http_response_code(400); echo json_encode(['success' => false, 'error' => 'invalid_json']); exit;
    }
    $tool = $json['tool'] ?? null;
    $args = $json['args'] ?? [];
    if (!$tool) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'missing_tool']); exit; }

    try {
        $res = \App\Core\McpInvoker::invoke($tool, $args);
        echo json_encode(['success' => true, 'result' => $res]);
        exit;
    } catch (\Throwable $e) {
        \Ginto\Helpers\AdminErrorLogger::log($e->getMessage(), ['route' => '/mcp/invoke', 'tool' => $tool]);
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Internal server error (logged)']);
        exit;
    }
});

// Dev-friendly discovery endpoint: run local discovery script and return tool schemas (admin only).
$router->req('/mcp/discover', function() {
    header('Content-Type: application/json; charset=utf-8');
    
    // Admin check - require login and admin role
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    $isAdmin = (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin');
    $tokenHeader = $_SERVER['HTTP_X_GINTO_ADMIN_TOKEN'] ?? $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? null;
    $expected = getenv('GINTO_ADMIN_TOKEN') ?: getenv('ADMIN_TOKEN');
    if (!$isAdmin && $expected && $tokenHeader && hash_equals((string)$expected, (string)$tokenHeader)) $isAdmin = true;
    if (!$isAdmin) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Admin access required']);
        exit;
    }
    
    $root = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 1);
    $script = $root . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'dump_discovered_tools.php';
    if (!is_file($script)) {
        echo json_encode(['success' => false, 'message' => 'Discovery script not found', 'script' => $script]);
        exit;
    }

    // Execute the discovery script and capture stdout/stderr. The script prints JSON
    // to stdout and may print warnings to stderr; combine output and attempt to
    // extract a JSON blob similar to editorMcpTools fallback logic.
    $cmd = 'php ' . escapeshellarg($script) . ' 2>&1';
    @exec($cmd, $outLines, $outCode);
    $outText = is_array($outLines) ? implode("\n", $outLines) : (string)($outLines ?? '');

    // Try direct decode first
    $decoded = @json_decode($outText, true);

    // Detect local `tools/` packages and installed composer MCP packages
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

    // Detect Handler files under src/Handlers as potential MCP handlers
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

    // Heuristic: scan for a JSON substring starting at any '{' or '[' position
    $maybeJson = null;
    $len = strlen($outText);
    for ($i = max(0, $len - 32768); $i < $len; $i++) {
        $ch = $outText[$i] ?? '';
        if ($ch === '{' || $ch === '[') {
            $cand = substr($outText, $i);
            $try = @json_decode($cand, true);
            if (is_array($try)) { $maybeJson = $try; break; }
            // try trimming a bit and re-trying
            $trimmed = rtrim($cand);
            $try2 = @json_decode($trimmed, true);
            if (is_array($try2)) { $maybeJson = $try2; break; }
        }
    }

    if (is_array($maybeJson)) {
        echo json_encode(['success' => true, 'tools' => $maybeJson, 'mcps' => $mcps, 'mcps_count' => $mcps_count, 'handlers' => $handlers, 'handlers_count' => $handlers_count]);
        exit;
    }

    // Fallback: return raw output as a single fallback entry so clients still see something
    $fallback = [ 'name' => 'local_discovery_fallback', 'description' => 'Fallback: raw discovery output', 'meta' => ['raw' => substr($outText, 0, 20000)] ];
    echo json_encode(['success' => true, 'tools' => [$fallback], 'raw' => $outText, 'mcps' => $mcps, 'mcps_count' => $mcps_count, 'handlers' => $handlers, 'handlers_count' => $handlers_count]);
    exit;
});

// Unified MCP discovery endpoint: returns normalized discovery across sources (admin only)
$router->req('/mcp/unified', function() {
    header('Content-Type: application/json; charset=utf-8');
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    
    // Admin check - require login and admin role
    $isAdmin = (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin');
    $tokenHeader = $_SERVER['HTTP_X_GINTO_ADMIN_TOKEN'] ?? $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? null;
    $expected = getenv('GINTO_ADMIN_TOKEN') ?: getenv('ADMIN_TOKEN');
    if (!$isAdmin && $expected && $tokenHeader && hash_equals((string)$expected, (string)$tokenHeader)) $isAdmin = true;
    if (!$isAdmin) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Admin access required']);
        exit;
    }

    $force = isset($_GET['refresh']) && ($_GET['refresh'] === '1' || strtolower($_GET['refresh']) === 'true');
        try {
        $u = new \App\Core\McpUnifier();
        $data = $u->getAllTools($force);
        $out = array_merge(['success' => true], $data);
        // Support human-friendly pretty JSON for interactive inspection.
        // Enable when `?pretty=1` OR when the request comes from a browser
        // (Accept header contains text/html) so developers get readable output.
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $prettyParam = isset($_GET['pretty']) && (strtolower($_GET['pretty']) === '1' || strtolower($_GET['pretty']) === 'true');
        $isBrowser = stripos($accept, 'text/html') !== false;
        $pretty = $prettyParam || $isBrowser;
        if ($pretty) {
            echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode($out);
        }
        exit;
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
});

// Stream Groq TTS audio: POST 'text' or raw body. Streams audio/mpeg bytes.
// Accessible to all users (including guests) for the chat experience
$router->req('/audio/tts', function() {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    
    @ini_set('zlib.output_compression', false);
    // Clean all output buffers to avoid stray whitespace corrupting audio
    while (ob_get_level()) @ob_end_clean();
    ignore_user_abort(true);

    // Determine TTS model early for rate limit checking
    $defaultModel = $_ENV['GROQ_TTS_MODEL'] ?? getenv('GROQ_TTS_MODEL') ?: null;
    $model = $_POST['model'] ?? $defaultModel ?? 'gpt-4o-mini-tts';

    // Get user info for rate limiting
    $userId = $_SESSION['user_id'] ?? null;
    $sessionId = session_id() ?: null;
    
    // Determine user role - check multiple session keys
    $isAdmin = !empty($_SESSION['is_admin']) 
        || (!empty($_SESSION['role']) && strtolower((string)$_SESSION['role']) === 'admin')
        || (!empty($_SESSION['user_role']) && strtolower((string)$_SESSION['user_role']) === 'admin');
    
    if ($userId) {
        $userRole = $isAdmin ? 'admin' : 'user';
    } else {
        $userRole = 'visitor';
    }

    // =====================================================
    // TTS Rate Limiting (Tiered Approach)
    // - Org-wide limit (90%): SILENT stop - no error, no modal
    // - Admin: 50% of org quota (SHOW modal when hit)
    // - User: 30 requests per hour (SHOW modal when hit)
    // - Visitor: 10 requests per session (SHOW modal when hit)
    // =====================================================
    try {
        $rateLimitService = new \App\Core\RateLimitService();
        $ttsCheck = $rateLimitService->canMakeTtsRequest($model, 'groq', $userRole, $userId, $sessionId);
        
        if (!$ttsCheck['allowed']) {
            // Check if this is a "silent" stop (org-level) vs "show modal" (personal limit)
            $isSilentStop = !empty($ttsCheck['silent']);
            
            error_log("[TTS rate limit] Limit reached - reason: {$ttsCheck['reason']}, " .
                "role: {$ttsCheck['user_role']}, silent: " . ($isSilentStop ? 'yes' : 'no') . 
                ", usage: " . json_encode($ttsCheck['usage']));
            
            if ($isSilentStop) {
                // Org-level limit hit - silently stop TTS (like when disabled)
                // Returns HTTP 204 No Content so client just skips playback
                http_response_code(204);
                header('X-Ginto-TTS: rate-limited-silent');
                header('X-Ginto-TTS-Reason: org-quota');
                exit;
            }
            
            // Personal limit hit - return 429 with info for client to show modal
            http_response_code(429);
            header('Content-Type: application/json');
            header('X-Ginto-TTS: rate-limited');
            header('X-Ginto-TTS-Reason: ' . ($ttsCheck['reason'] ?? 'limit'));
            
            echo json_encode([
                'error' => 'tts_rate_limit',
                'reason' => $ttsCheck['reason'],
                'limit_type' => $ttsCheck['limit_type'] ?? 'unknown',
                'user_role' => $ttsCheck['user_role'] ?? $userRole,
                'usage' => $ttsCheck['usage'] ?? [],
                'message' => match($ttsCheck['reason'] ?? '') {
                    'visitor_session_limit' => 'You\'ve reached the TTS limit for guests. Register for higher limits!',
                    'user_hourly_limit' => 'You\'ve reached the hourly TTS limit. Upgrade for higher limits!',
                    'admin_hourly_limit' => 'You\'ve reached the admin hourly TTS limit. TTS will resume shortly.',
                    default => 'TTS rate limit reached.',
                },
            ]);
            exit;
        }
    } catch (\Throwable $e) {
        // If rate limit check fails, proceed with TTS (fail open)
        error_log("[TTS rate limit] Check failed: " . $e->getMessage());
    }

    $text = $_POST['text'] ?? trim(file_get_contents('php://input')) ?: '';
    
    // Sanitize text for TTS - remove emoji and problematic characters
    // Remove common emoji ranges
    $text = preg_replace('/[\x{1F300}-\x{1F9FF}]/u', '', $text);
    $text = preg_replace('/[\x{2600}-\x{26FF}]/u', '', $text);
    $text = preg_replace('/[\x{2700}-\x{27BF}]/u', '', $text);
    $text = preg_replace('/[\x{1F600}-\x{1F64F}]/u', '', $text);
    $text = preg_replace('/[\x{1F680}-\x{1F6FF}]/u', '', $text);
    // Collapse whitespace
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text);
    
    // Ensure minimum text length to avoid API errors
    if (strlen($text) < 5) {
        $text = 'Hello! This is a text-to-speech demo from Ginto.';
    }

    // Allow operator to set a default voice via env `GROQ_TTS_VOICE` or
    // allow the client to pass a `voice` POST param. Some Groq TTS models
    // require an explicit `voice` field.
    $defaultVoice = $_ENV['GROQ_TTS_VOICE'] ?? getenv('GROQ_TTS_VOICE') ?: null;
    $voice = $_POST['voice'] ?? $defaultVoice ?? null;

    // If no voice is supplied, and the selected model appears to be a
    // PlayAI model (e.g. 'playai-tts'), pick a safe default voice so
    // upstream Groq call doesn't fail with "voice is required".
    if (empty($voice)) {
        if (is_string($model) && stripos($model, 'playai') !== false) {
            $voice = 'Arista-PlayAI';
        }
    }

    $payloadArr = [ 'model' => $model, 'input' => $text ];
    if (!empty($voice)) $payloadArr['voice'] = $voice;
    $payload = json_encode($payloadArr);

    $groqKey = $_ENV['GROQ_API_KEY'] ?? getenv('GROQ_API_KEY');
    if (!$groqKey) {
        // No TTS provider configured — return 204 No Content so clients can
        // silently skip playback instead of receiving a 502 error.
        http_response_code(204);
        header('X-Ginto-TTS: disabled');
        exit;
    }

    header('Content-Type: audio/mpeg');
    header('Cache-Control: no-cache');

    $ch = curl_init('https://api.groq.com/openai/v1/audio/speech');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $groqKey,
            'Content-Type: application/json'
        ],
        // Return the entire response so we can inspect HTTP status and body
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);

    $res = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE) ?: 0;
    curl_close($ch);

    // Basic upstream error checking — if cURL had an error or the upstream
    // returned an HTTP error code, surface a safe JSON error and log details.
    if ($err) {
        http_response_code(502);
        error_log('[TTS proxy] curl error: ' . $err);
        echo json_encode(['error' => 'TTS upstream error', 'detail' => substr((string)$err, 0, 2000)]);
        exit;
    }

    if (is_int($code) && $code >= 400) {
        // Upstream returned an error status — include a small body snippet for diagnostics
        $bodySnippet = is_string($res) ? substr($res, 0, 2000) : '';
        error_log("[TTS proxy] upstream returned HTTP $code body=" . $bodySnippet);
        http_response_code($code ?: 502);
        echo json_encode(['error' => 'TTS upstream returned HTTP error', 'code' => $code, 'body' => $bodySnippet]);
        exit;
    }
    
    // Success: return audio bytes. We try to mirror an appropriate Content-Type
    // if upstream included one; otherwise default to audio/mpeg. Make this
    // robust against stray output or PHP notices by ensuring headers are set
    // as late as possible and clearing output buffers before echoing bytes.
    $contentType = 'audio/mpeg';
    // Allow operator override
    $envCt = getenv('GROQ_TTS_CONTENT_TYPE');
    if ($envCt !== false && $envCt !== null && $envCt !== '') {
        $contentType = $envCt;
    }

    // Try to replace or set Content-Type header. If headers were already sent,
    // log for diagnosis and still attempt to send the body.
    if (!headers_sent()) {
        header('Content-Type: ' . $contentType);
    } else {
        error_log('[TTS proxy] headers already sent before setting Content-Type');
    }
    header('Cache-Control: no-cache');
    header('X-Ginto-TTS: 1');

    // Log successful TTS request for rate limiting tracking
    try {
        if (!isset($rateLimitService)) {
            $rateLimitService = new \App\Core\RateLimitService();
        }
        // Use variables already set at top of function
        $rateLimitService->logTtsRequest($model, 'groq', $userId, $userRole, true, $sessionId);
    } catch (\Throwable $e) {
        // Don't fail the request if logging fails
        error_log('[TTS logging] Failed to log request: ' . $e->getMessage());
    }

    // Clear any existing output buffers to avoid prepended HTML/text corrupting audio
    while (ob_get_level()) { @ob_end_clean(); }
    // Finally echo the raw audio bytes
    echo $res;
    exit;
});

// Speech-to-text: accept a multipart file upload 'file' and forward to Groq
$router->req('/audio/stt', function() {
    // CSRF and session protection
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        if (empty($_SESSION['user_id'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Not authenticated']);
            exit;
        }
        $csrf = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!$csrf || !isset($_SESSION['csrf_token']) || $csrf !== $_SESSION['csrf_token']) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF token']);
            exit;
        }
    }
    $groqKey = getenv('GROQ_API_KEY');
    if (!$groqKey) {
        http_response_code(502); echo json_encode(['error' => 'STT_API_KEY not configured']); exit;
    }

    // Accept either multipart file or raw body.
        if (!empty($_FILES['file']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
        $tmp = $_FILES['file']['tmp_name'];
        $name = $_FILES['file']['name'] ?? 'upload';
        $ctype = $_FILES['file']['type'] ?? 'application/octet-stream';
        $cfile = new CURLFile($tmp, $ctype, $name);
        $sttModel = $_POST['model'] ?? (getenv('GROQ_STT_MODEL') ?: 'whisper-large-v3-turbo');
        $post = ['file' => $cfile, 'model' => $sttModel];
    } else {
        // raw audio body
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') { http_response_code(400); echo json_encode(['error'=>'no file provided']); exit; }
        // write to temp file
        $tmp = tempnam(sys_get_temp_dir(), 'ginto_stt_');
        file_put_contents($tmp, $raw);

        // Try to detect MIME type for the temp file so we can send a correct
        // Content-Type to the upstream transcription API. Browsers sometimes
        // post raw bodies without a helpful content-type.
        $detectedType = null;
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $detected = finfo_file($finfo, $tmp);
                if ($detected && $detected !== 'application/octet-stream') {
                    $detectedType = $detected;
                }
                finfo_close($finfo);
            }
        }

        $ctype = $detectedType ?: ($_SERVER['CONTENT_TYPE'] ?? 'application/octet-stream');
        $cfile = new CURLFile($tmp, $ctype, 'upload');
        $sttModel = $_POST['model'] ?? (getenv('GROQ_STT_MODEL') ?: 'whisper-large-v3-turbo');
        $post = ['file' => $cfile, 'model' => $sttModel];
    }

    // If configured to use a server-side Python STT wrapper, call it so
    // the frontend never learns about the upstream provider details.
    $use_py = getenv('USE_PY_STT') ?: ($_ENV['USE_PY_STT'] ?? null);
    if ($use_py) {
        $py = escapeshellcmd(getenv('PYTHON3_PATH') ?: 'python3');
        // Use the existing groq_stt module in tools/groq-mcp/src — don't create any extra script
        $srcPath = realpath(__DIR__ . '/../../tools/groq-mcp/src');
        // Ensure temp file has a sensible extension so the Python helper's
        // audio content check (which looks at suffix) recognizes it. If the
        // uploaded tmp file has no extension but the original filename does,
        // copy to a temp path with the original extension.
        $tmp_with_ext = $tmp;
        $orig_ext = pathinfo($name, PATHINFO_EXTENSION) ?: '';
        if ($orig_ext && pathinfo($tmp, PATHINFO_EXTENSION) === '') {
            $tmp_with_ext = $tmp . '.' . $orig_ext;
            @copy($tmp, $tmp_with_ext);
            $copied_tmp = true;
        } else {
            $copied_tmp = false;
        }
        $fileArg = escapeshellarg($tmp_with_ext);
        $modelArg = escapeshellarg($sttModel);
        // Build an inline python -c command that imports the existing groq_stt module
        $pyCode = sprintf(
            "import sys, json, os; sys.path.insert(0,%s); import groq_stt as gs; res = gs.transcribe_audio(%s, model=%s, response_format='json', save_to_file=False); text = getattr(res,'text', getattr(res,'content', str(res))); print(json.dumps({'success':True,'text': text}))",
            escapeshellarg($srcPath),
            $fileArg,
            $modelArg
        );
        $cmd = $py . ' -c ' . escapeshellarg($pyCode);

        // Run the CLI with a bounded timeout using proc_open where possible.
        $output = null; $code = 0; $err = null;
        try {
            $des = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
            $proc = proc_open($cmd, $des, $pipes);
            if (is_resource($proc)) {
                // read, close and collect
                $output = stream_get_contents($pipes[1]); fclose($pipes[1]);
                $err = stream_get_contents($pipes[2]); fclose($pipes[2]);
                $code = proc_close($proc);
                if ($err) error_log('[STT PY CLI stderr] ' . substr($err,0,1200));
            } else {
                $output = shell_exec($cmd . ' 2>&1');
            }
        } catch (Exception $e) {
            http_response_code(502);
            echo json_encode(['error' => 'STT CLI failed', 'detail' => $e->getMessage()]);
            exit;
        }

        // When the CLI returned something, attempt to parse JSON and return
        if ($output) {
            $j = json_decode($output, true);
            if (is_array($j) && isset($j['success']) && $j['success']) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => true, 'text' => isset($j['text']) ? $j['text'] : '']);
                exit;
            } else {
                // CLI reported an error — return sanitized message
                http_response_code(502);
                $errMsg = is_array($j) && isset($j['error']) ? $j['error'] : 'STT CLI error';
                echo json_encode(['error' => $errMsg]);
                exit;
            }
        }
        // Fallback to failure: include stderr to help debugging and cleanup
        http_response_code(502);
        $dbgErr = is_string($err) && $err !== '' ? substr($err,0,1200) : null;
        $extra = [];
        if ($dbgErr) $extra['detail'] = $dbgErr;
        else $extra['exit_code'] = $code ?: null;
        if ($dbgErr && stripos($dbgErr, 'Audio file is too short') !== false) {
            echo json_encode(array_merge(['error' => 'audio_too_short'], $extra));
        } else {
            echo json_encode(array_merge(['error' => 'STT CLI produced no output'], $extra));
        }
        if (!empty($copied_tmp) && is_file($tmp_with_ext)) { @unlink($tmp_with_ext); }
        exit;

    } else {
        // Ensure we send a WAV file: Groq transcription is most reliable with WAV.
        // If the uploaded file is not WAV, try to transcode it to WAV using ffmpeg
        // (if available). If ffmpeg is not present, return a helpful error so the
        // client can either send WAV or the operator can install ffmpeg.
        $current_ct = strtolower($ctype ?? '');
        $need_wav = (strpos($current_ct, 'wav') === false && strpos($current_ct, 'wave') === false);
        if ($need_wav) {
            // Attempt to find ffmpeg
            $ffmpeg = null;
            try { $ffmpeg = trim((string)shell_exec('command -v ffmpeg 2>/dev/null')); } catch (\Throwable $_) { $ffmpeg = null; }
            if ($ffmpeg) {
                $wavTmp = tempnam(sys_get_temp_dir(), 'ginto_wav_');
                // ensure proper extension for detection upstream
                $wavTmpNamed = $wavTmp . '.wav';
                // Convert to mono 16kHz WAV which is commonly accepted
                $cmd = escapeshellcmd($ffmpeg) . ' -y -i ' . escapeshellarg($tmp) . ' -ar 16000 -ac 1 ' . escapeshellarg($wavTmpNamed) . ' 2>&1';
                $out = null;
                try { $out = shell_exec($cmd); } catch (\Throwable $_) { $out = null; }
                if (!file_exists($wavTmpNamed) || filesize($wavTmpNamed) < 32) {
                    @file_put_contents('/tmp/transcribe_debug.log', "[transcode_failed] cmd=" . $cmd . " out=" . substr((string)$out,0,2000) . "\n", FILE_APPEND);
                    http_response_code(400); echo json_encode(['error' => 'could not transcode to WAV', 'hint' => 'ffmpeg conversion failed (see /tmp/transcribe_debug.log)']); exit;
                }
                // Replace CURL file with WAV file
                $cfile = new CURLFile($wavTmpNamed, 'audio/wav', 'upload.wav');
                $post = ['file' => $cfile, 'model' => $sttModel];
                // remember to cleanup original temp if it was a raw-body temp file
                if (strpos($tmp, sys_get_temp_dir()) === 0 && is_file($tmp)) {
                    @unlink($tmp);
                }
                // update tmp pointer for debug logs
                $tmp = $wavTmpNamed;
                $ctype = 'audio/wav';
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Only WAV uploads are accepted by this endpoint unless server-side ffmpeg is installed.', 'hint' => 'Install ffmpeg or send audio as WAV (Content-Type: audio/wav).']);
                exit;
            }
        }

        $ch = curl_init('https://api.groq.com/openai/v1/audio/transcriptions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $post,
            CURLOPT_HTTPHEADER => [ 'Authorization: Bearer ' . $groqKey ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $res = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        // Retry with alternate audio Content-Types if upstream rejected the
        // file as an invalid media file. Some providers require specific
        // Content-Type strings (e.g. 'audio/webm;codecs=opus'). Try common
        // variants before surfacing an error to the client.
        if (($code >= 400) && is_string($res) && (stripos($res, 'could not process file') !== false || stripos($res, 'is not a valid media file') !== false || stripos($res, 'file must be one of') !== false)) {
            @file_put_contents('/tmp/ginto_stt_debug.log', "[stt_retry] upstream rejected file tmp={$tmp} ctype={$ctype} code={$code} body=" . substr((string)$res,0,1000) . "\n", FILE_APPEND);
            $alt_types = [
                'audio/webm;codecs=opus',
                'audio/webm',
                'audio/ogg;codecs=opus',
                'audio/ogg',
                'audio/mpeg',
                'audio/mp4'
            ];

            foreach ($alt_types as $at) {
                try {
                    $altName = isset($name) ? $name : (basename((string)$tmp) ?: 'upload');
                    $altCfile = new CURLFile($tmp, $at, $altName);
                    $altPost = ['file' => $altCfile, 'model' => $sttModel];

                    $ch2 = curl_init('https://api.groq.com/openai/v1/audio/transcriptions');
                    curl_setopt_array($ch2, [
                        CURLOPT_POST => true,
                        CURLOPT_POSTFIELDS => $altPost,
                        CURLOPT_HTTPHEADER => [ 'Authorization: Bearer ' . $groqKey ],
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT => 60,
                        CURLOPT_CONNECTTIMEOUT => 10,
                    ]);
                    $res2 = curl_exec($ch2);
                    $err2 = curl_error($ch2);
                    $code2 = curl_getinfo($ch2, CURLINFO_RESPONSE_CODE);
                    curl_close($ch2);

                    @file_put_contents('/tmp/ginto_stt_debug.log', "[stt_retry] tried ctype={$at} code={$code2} err=" . substr((string)$err2,0,400) . "\n", FILE_APPEND);

                    if (!$err2 && $code2 >= 200 && $code2 < 300) {
                        $res = $res2;
                        $err = $err2;
                        $code = $code2;
                        break;
                    }
                } catch (\Throwable $_) {
                    @file_put_contents('/tmp/ginto_stt_debug.log', "[stt_retry_error] exception trying ctype={$at}\n", FILE_APPEND);
                }
            }
        }

        // If upstream rejected the file as an invalid media file, attempt
        // a few retries with alternative common audio MIME types. Some
        // providers are picky about the Content-Type header (for example
        // requiring 'audio/webm; codecs=opus'). Try a small set of fallbacks
        // before returning an error to the client.
        if (($code >= 400) && is_string($res) && (stripos($res, 'could not process file') !== false || stripos($res, 'is not a valid media file') !== false || stripos($res, 'file must be one of') !== false)) {
            @file_put_contents('/tmp/transcribe_debug.log', "[transcribe_retry] upstream rejected file tmp={$tmp} ctype={$ctype} code={$code} body=" . substr((string)$res,0,1000) . "\n", FILE_APPEND);
            $alt_types = [
                'audio/webm;codecs=opus',
                'audio/webm',
                'audio/ogg;codecs=opus',
                'audio/ogg',
                'audio/mpeg',
                'audio/mp4'
            ];
            foreach ($alt_types as $at) {
                // Rebuild CURLFile and post payload using same temp file
                try {
                    $altName = isset($name) ? $name : (basename((string)$tmp) ?: 'upload');
                    $altCfile = new CURLFile($tmp, $at, $altName);
                    $altPost = ['file' => $altCfile, 'model' => $sttModel];

                    $ch2 = curl_init('https://api.groq.com/openai/v1/audio/transcriptions');
                    curl_setopt_array($ch2, [
                        CURLOPT_POST => true,
                        CURLOPT_POSTFIELDS => $altPost,
                        CURLOPT_HTTPHEADER => [ 'Authorization: Bearer ' . $groqKey ],
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT => 60,
                        CURLOPT_CONNECTTIMEOUT => 10,
                    ]);
                    $res2 = curl_exec($ch2);
                    $err2 = curl_error($ch2);
                    $code2 = curl_getinfo($ch2, CURLINFO_RESPONSE_CODE);
                    curl_close($ch2);

                    @file_put_contents('/tmp/transcribe_debug.log', "[transcribe_retry] tried ctype={$at} code={$code2} err=" . substr((string)$err2,0,400) . "\n", FILE_APPEND);

                    if (!$err2 && $code2 >= 200 && $code2 < 300) {
                        // success — replace response and break out
                        $res = $res2;
                        $err = $err2;
                        $code = $code2;
                        break;
                    }
                } catch (\Throwable $_) {
                    @file_put_contents('/tmp/transcribe_debug.log', "[transcribe_retry_error] exception trying ctype={$at}\n", FILE_APPEND);
                }
            }
        }
    }

    if ($err) {
        http_response_code(502);
        error_log('[STT proxy] curl error: ' . $err);
        echo json_encode(['error' => $err]);
        exit;
    }

    // If upstream returned an error (e.g. invalid media file), write debug
    // info to a temporary log to help identify mismatched container/codec.
    if ($code >= 400) {
        try {
            $dbg = sprintf("[ginto_stt_debug] code=%d model=%s ctype=%s tmp=%s body=%s\n", $code, $sttModel ?? '(unknown)', $ctype ?? 'unknown', $tmp, substr($res,0,2000));
            @file_put_contents('/tmp/ginto_stt_debug.log', $dbg, FILE_APPEND);
            $fileContents = @file_get_contents($tmp);
            if ($fileContents !== false) {
                $hex = @substr(bin2hex($fileContents), 0, 400);
                if ($hex) @file_put_contents('/tmp/ginto_stt_debug.log', "[file-hex-preview] " . $hex . "\n", FILE_APPEND);
            }
        } catch (Exception $_) { /* ignore logging errors */ }
    }

    // Normalize upstream response and only return a sanitized JSON object
    // so the client cannot see provider-specific details.
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($code ?: 200);
    $parsed = json_decode($res, true);
    if (is_array($parsed)) {
        // Upstream returned JSON — try to extract text content.
        if (isset($parsed['text'])) {
            echo json_encode(['success' => true, 'text' => $parsed['text']]);
            exit;
        }
        // Groq style verbose JSON may embed the transcript in other fields
        if (isset($parsed['data']) && is_array($parsed['data'])) {
            // try common shape
            $extracted = '';
            foreach ($parsed['data'] as $item) {
                if (is_string($item)) $extracted .= ($extracted ? ' ' : '') . $item;
                elseif (is_array($item) && isset($item['text'])) $extracted .= ($extracted ? ' ' : '') . $item['text'];
            }
            if ($extracted !== '') { echo json_encode(['success' => true, 'text' => $extracted]); exit; }
        }
        // If it looks like an error, return a sanitized error
        if (isset($parsed['error']) || isset($parsed['message'])) {
            http_response_code(502);
            $errMsg = $parsed['error']['message'] ?? $parsed['message'] ?? 'STT upstream error';
            echo json_encode(['error' => $errMsg]);
            exit;
        }
        // Fall back to returning the whole JSON as text (safe)
        echo json_encode(['success' => true, 'text' => json_encode($parsed)]);
        exit;
    } else {
        // Non-JSON response; treat body as transcription text
        $txt = trim((string)$res);
        echo json_encode(['success' => true, 'text' => $txt]);
        exit;
    }
    exit;
});

// =====================================================
// PLAYGROUND ROUTES - Developer Tools
// =====================================================

// Playground main route (accessible to both users and admins)
// Playground main route
$router->req('/playground', 'PlaygroundController@index');

// NOTE: The playground catch-all route was intentionally moved below the
// more-specific /playground/* routes (e.g. /playground/logs and editor endpoints)
// so it doesn't shadow those routes.

// Playground admin-style logs (exposed under /playground/logs) — admin-only
// Playground admin-style logs
$router->req('/playground/logs', 'PlaygroundController@logs');

// Admin-only helper to create a sample playground log entry (POST)
// Create sample log entry
$router->req('/playground/logs/create-sample', 'PlaygroundController@createSampleLog', ['POST']);
// Playground editor - install env (POST)
$router->req('/playground/editor/install_env', 'PlaygroundController@installEnv', ['POST']);
// Playground editor - install status (GET)
$router->req('/playground/editor/install_status', 'PlaygroundController@installStatus', ['GET']);

// Playground sub-routes (catch-all for playground tools)
// Playground sub-routes (catch-all for playground tools)
$router->req('/playground/{tool}', 'PlaygroundController@tool');

// Playground log detail view
$router->req('/playground/logs/{id}', 'PlaygroundController@logDetail');
// Playground editor save endpoint
$router->req('/playground/editor/save', 'PlaygroundController@save', ['POST']);

// Playground editor - toggle admin sandbox mode (admins can opt into per-user sandbox)
$router->req('/playground/editor/toggle_sandbox', 'PlaygroundController@toggleSandbox', ['POST']);

// Dev endpoint: return a filtered view of the current session for debugging in the editor
$router->req('/playground/editor/session_debug', 'PlaygroundController@sessionDebug', ['GET']);

// Playground editor - refresh tree
$router->req('/playground/editor/tree', 'PlaygroundController@tree', ['GET']);

// Playground console - environment info
$router->req('/playground/console/environment', 'PlaygroundController@consoleEnvironment', ['GET']);

// Playground console - execute a command (CSRF-protected)
$router->req('/playground/console/exec', 'PlaygroundController@consoleExec', ['POST']);

// Playground console - tail logs (safe read-only)
$router->req('/playground/console/logs', 'PlaygroundController@consoleLogs', ['GET']);

// Playground editor - create file/folder
$router->req('/playground/editor/create', 'PlaygroundController@create', ['POST']);

// Playground editor - rename
$router->req('/playground/editor/rename', 'PlaygroundController@rename', ['POST']);

// Playground editor - delete
$router->req('/playground/editor/delete', 'PlaygroundController@delete', ['POST']);

// Playground editor - paste (copy/move)
$router->req('/playground/editor/paste', 'PlaygroundController@paste', ['POST']);

// =====================================================================
// Provider API Keys Management (Admin Only)
// =====================================================================
// Provider API Keys Management (Admin Only)
$router->req('/api/provider-keys', 'ApiController@providerKeys');
