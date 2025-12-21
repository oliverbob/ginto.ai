<?php
// src/Routes/web.php
// Centralized route definitions for Ginto CMS

// Serve role-based prompts for chat UI
req($router, '/chat/prompts/', function() {
    require_once __DIR__ . '/../Controllers/PromptsController.php';
    \Controllers\PromptsController::getPrompts();
});

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
function req($router, $path, $handler) {
    global $ROUTE_REGISTRY;
    $ROUTE_REGISTRY[] = [
        'methods' => ['GET', 'POST'],
        'path' => $path,
        'handler' => $handler
    ];
    $router->get($path, $handler);
    $router->post($path, $handler);
    // Add other HTTP methods if needed
}

// Debug endpoint to check IP detection (remove after testing)
$router->get('/api/debug/ip-headers', function() {
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
req($router, '/login', function() use ($db, $countries) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller = new \Ginto\Controllers\UserController($db, $countries);
        $controller->loginAction($_POST);
    } else {
        \Ginto\Core\View::view('user/login', [
            'title' => 'Login'
        ]);
    }
});

// Lightweight transcribe endpoint for quick client testing.
// Accepts a multipart file upload 'file' and returns a simplified JSON
// { success: true, text: 'transcribed text' } to make client integration easier.
req($router, '/transcribe', function() {
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
req($router, '/', function() {
    if (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        if (!headers_sent()) header('Location: /admin');
        exit;
    }
    if (!headers_sent()) header('Location: /chat');
    exit;
});

// Full user network tree view
req($router, '/user/network-tree', function() use ($db) {
    if (empty($_SESSION['user_id'])) {
        if (!headers_sent()) header('Location: /login');
        exit;
    }
    $userId = $_SESSION['user_id'];
    $userModel = new \Ginto\Models\User();
    $user_data = $userModel->find($userId);
    $stats = [
        'direct_referrals' => $userModel->countDirectReferrals($userId),
    ];
    \Ginto\Core\View::view('user/network-tree', [
        'title' => 'Network Tree',
        'user_data' => $user_data,
        'current_user_id' => $userId,
        'stats' => $stats
    ]);
});

// Downline view (legacy route)
req($router, '/downline', function() use ($db, $countries) {
    if (empty($_SESSION['user_id'])) {
        if (!headers_sent()) header('Location: /login');
        exit;
    }
    $controller = new \Ginto\Controllers\UserController($db, $countries);
    return $controller->downlineAction();
});

// Logout route: destroy session and redirect to login
req($router, '/logout', function() {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    // Unset all session variables
    $_SESSION = [];
    // Delete session cookie
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'] ?? '/',
            $params['domain'] ?? '',
            $params['secure'] ?? false,
            $params['httponly'] ?? true
        );
    }
    // Destroy the session
    session_unset();
    session_destroy();
    if (!headers_sent()) header('Location: /');
    exit;
});

req($router, '/register', function() use ($db, $countries) {
    if (session_status() !== PHP_SESSION_ACTIVE) {@session_start();}
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller = new \Ginto\Controllers\UserController($db, $countries);
        $controller->registerAction($_POST);
    } else {
        $refId = $_GET['ref'] ?? ($_SESSION['referral_code'] ?? null);
        if (isset($_GET['ref'])) {
            $_SESSION['referral_code'] = $_GET['ref'];
        }
        $detectedCountryCode = null;
        $levels = [];
        try {
            $levels = $db->select('tier_plans', ['id','name','cost_amount','cost_currency','commission_rate_json'], ['ORDER' => ['id' => 'ASC']]);
        } catch (Exception $e) {
            error_log('Warning: Could not load levels for register view: ' . $e->getMessage());
        }
        \Ginto\Core\View::view('user/register/register', [
            'title' => 'Register for Ginto',
            'ref_id' => $refId,
            'error' => null,
            'old' => [],
            'countries' => $countries,
            'default_country_code' => $detectedCountryCode,
            'levels' => $levels,
            'csrf_token' => generateCsrfToken(true)
        ]);
    }
});

// Bank Transfer Payment Registration
req($router, '/bank-payments', function() use ($db, $countries) {
    if (session_status() !== PHP_SESSION_ACTIVE) {@session_start();}
    
    // Only accept POST requests via AJAX
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Content-Type: application/json');
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }
    
    // Verify AJAX request
    if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit;
    }
    
    header('Content-Type: application/json');
    
    try {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token. Please refresh the page.']);
            exit;
        }
        
        // Validate required fields
        $required = ['username', 'email', 'password', 'country', 'phone', 'bank_reference'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => "Field '$field' is required."]);
                exit;
            }
        }
        
        // Validate file upload
        if (!isset($_FILES['bank_receipt']) || $_FILES['bank_receipt']['error'] !== UPLOAD_ERR_OK) {
            $uploadError = $_FILES['bank_receipt']['error'] ?? 'No file uploaded';
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Bank receipt upload is required.']);
            exit;
        }
        
        $file = $_FILES['bank_receipt'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
        $maxSize = 10 * 1024 * 1024; // 10MB
        
        // Validate file type
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        if (!in_array($mimeType, $allowedTypes)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid file type. Please upload an image (JPG, PNG, GIF, WebP) or PDF.']);
            exit;
        }
        
        // Validate file size
        if ($file['size'] > $maxSize) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'File too large. Maximum size is 10MB.']);
            exit;
        }
        
        // Check for existing user by email
        $existingUser = $db->get('users', 'id', ['email' => $_POST['email']]);
        if ($existingUser) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'User with this email already exists.']);
            exit;
        }
        
        // Check for existing username
        $existingUsername = $db->get('users', 'id', ['username' => $_POST['username']]);
        if ($existingUsername) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Username already taken.']);
            exit;
        }
        
        // Check for existing phone
        $existingPhone = $db->get('users', 'id', ['phone' => $_POST['phone']]);
        if ($existingPhone) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Phone number already registered.']);
            exit;
        }
        
        // Check for pending bank payment with same email (in subscription_payments)
        $pendingPayment = $db->get('subscription_payments', 'id', [
            'payment_method' => 'bank_transfer',
            'status' => 'pending',
            'notes[~]' => '%"email":"' . $_POST['email'] . '"%'
        ]);
        if ($pendingPayment) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'A pending registration with this email already exists. Please wait for verification or contact support.']);
            exit;
        }
        
        // Calculate upload directory outside of ginto directory (../storage relative to project root)
        $projectRoot = dirname(dirname(__DIR__)); // Goes up from src/Routes to project root (ginto)
        $uploadDir = dirname($projectRoot) . '/storage/payments/bank-transfer/receipts/'; // Goes one level up from ginto
        
        // Create upload directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                error_log('Failed to create upload directory: ' . $uploadDir);
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Server error. Please try again later.']);
                exit;
            }
        }
        
        // Generate random secure filename (no username for security)
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = bin2hex(random_bytes(16)) . '.' . $extension; // 32 char random hex
        $filepath = $uploadDir . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            error_log('Failed to move uploaded file to: ' . $filepath);
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to save receipt. Please try again.']);
            exit;
        }
        
        // Resolve referrer ID
        $referrerId = 2; // Default sponsor
        $refSource = $_POST['sponsor_id'] ?? ($_SESSION['referral_code'] ?? null);
        if (!empty($refSource)) {
            if (is_numeric($refSource)) {
                $referrerId = (int)$refSource;
            } else {
                $resolvedId = $db->get('users', 'id', ['username' => $refSource]);
                if (!$resolvedId) {
                    $resolvedId = $db->get('users', 'id', ['public_id' => $refSource]);
                }
                if ($resolvedId) {
                    $referrerId = (int)$resolvedId;
                }
            }
        }
        
        // Combine name fields
        $fullname = '';
        $first = $_POST['firstname'] ?? $_POST['firstName'] ?? '';
        $middle = $_POST['middlename'] ?? $_POST['middleName'] ?? '';
        $last = $_POST['lastname'] ?? $_POST['lastName'] ?? '';
        if ($first || $middle || $last) {
            $fullname = trim(implode(' ', array_filter([$first, $middle, $last])));
        } else {
            $fullname = $_POST['fullname'] ?? '';
        }
        
        // Map package to plan_id (1=free, 2=go, 3=plus, 4=pro)
        $packageName = strtolower($_POST['package'] ?? 'go');
        $planIdMap = ['free' => 1, 'go' => 2, 'plus' => 3, 'pro' => 4];
        $planId = $planIdMap[$packageName] ?? 2;
        
        // Create user FIRST with pending payment status
        $passwordHash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $publicId = substr(md5(uniqid(mt_rand(), true)), 0, 12);
        
        $db->insert('users', [
            'email' => $_POST['email'],
            'username' => $_POST['username'],
            'password_hash' => $passwordHash,
            'fullname' => $fullname,
            'phone' => $_POST['phone'],
            'country' => $_POST['country'],
            'referrer_id' => $referrerId,
            'public_id' => $publicId,
            'payment_status' => 'pending',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        $userId = $db->id();
        
        if (!$userId) {
            @unlink($filepath);
            error_log('Failed to create user account for bank payment');
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to create account. Please try again.']);
            exit;
        }
        
        // Store registration metadata in notes as JSON
        $paymentNotes = json_encode([
            'email' => $_POST['email'],
            'username' => $_POST['username'],
            'fullname' => $fullname,
            'phone' => $_POST['phone'],
            'country' => $_POST['country'],
            'referrer_id' => $referrerId,
            'original_filename' => $file['name'],
            'mime_type' => $mimeType,
            'file_size' => $file['size']
        ]);
        
        // Insert into unified subscription_payments table with user_id
        $transactionId = TransactionHelper::generateTransactionId($db);
        $auditData = TransactionHelper::captureAuditData();
        $db->insert('subscription_payments', array_merge([
            'user_id' => $userId,
            'subscription_id' => null,
            'plan_id' => $planId,
            'type' => 'registration',
            'amount' => !empty($_POST['package_amount']) ? floatval($_POST['package_amount']) : 0,
            'currency' => $_POST['package_currency'] ?? 'PHP',
            'payment_method' => 'bank_transfer',
            'payment_reference' => $_POST['bank_reference'],
            'status' => 'pending',
            'notes' => $paymentNotes,
            'receipt_filename' => $filename,
            'receipt_path' => $filepath,
            'transaction_id' => $transactionId
        ], $auditData));
        
        $paymentId = $db->id();
        
        if (!$paymentId) {
            // Payment insert failed - cleanup user
            $db->delete('users', ['id' => $userId]);
            @unlink($filepath);
            error_log('Failed to insert subscription_payment record');
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to save registration. Please try again.']);
            exit;
        }
        
        // Log in the user
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $_POST['username'];
        $_SESSION['email'] = $_POST['email'];
        $_SESSION['payment_status'] = 'pending';
        
        // Log the registration
        error_log("Bank payment registration: User ID=$userId, Payment ID=$paymentId, Email={$_POST['email']}, Username={$_POST['username']}, Reference={$_POST['bank_reference']}");
        
        echo json_encode([
            'success' => true,
            'message' => 'Account created! Your premium status will be activated once we verify your payment.',
            'payment_id' => $paymentId,
            'user_id' => $userId,
            'redirect' => '/chat'
        ]);
        exit;
        
    } catch (Exception $e) {
        error_log('Bank payment registration error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again later.']);
        exit;
    }
});

// GCash Payment Registration
req($router, '/gcash-payments', function() use ($db, $countries) {
    if (session_status() !== PHP_SESSION_ACTIVE) {@session_start();}
    
    // Only accept POST requests via AJAX
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Content-Type: application/json');
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }
    
    // Verify AJAX request
    if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit;
    }
    
    header('Content-Type: application/json');
    
    try {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token. Please refresh the page.']);
            exit;
        }
        
        // Validate required fields
        $required = ['username', 'email', 'password', 'country', 'phone', 'gcash_reference'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => "Field '$field' is required."]);
                exit;
            }
        }
        
        // Validate file upload
        if (!isset($_FILES['gcash_receipt']) || $_FILES['gcash_receipt']['error'] !== UPLOAD_ERR_OK) {
            $uploadError = $_FILES['gcash_receipt']['error'] ?? 'No file uploaded';
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'GCash receipt upload is required.']);
            exit;
        }
        
        $file = $_FILES['gcash_receipt'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
        $maxSize = 10 * 1024 * 1024; // 10MB
        
        // Validate file type
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        if (!in_array($mimeType, $allowedTypes)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid file type. Please upload an image (JPG, PNG, GIF, WebP) or PDF.']);
            exit;
        }
        
        // Validate file size
        if ($file['size'] > $maxSize) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'File too large. Maximum size is 10MB.']);
            exit;
        }
        
        // Check for existing user by email
        $existingUser = $db->get('users', 'id', ['email' => $_POST['email']]);
        if ($existingUser) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'User with this email already exists.']);
            exit;
        }
        
        // Check for existing username
        $existingUsername = $db->get('users', 'id', ['username' => $_POST['username']]);
        if ($existingUsername) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Username already taken.']);
            exit;
        }
        
        // Check for existing phone
        $existingPhone = $db->get('users', 'id', ['phone' => $_POST['phone']]);
        if ($existingPhone) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Phone number already registered.']);
            exit;
        }
        
        // Check for pending GCash payment with same email
        $pendingPayment = $db->get('subscription_payments', 'id', [
            'payment_method' => 'gcash',
            'status' => 'pending',
            'notes[~]' => '%"email":"' . $_POST['email'] . '"%'
        ]);
        if ($pendingPayment) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'A pending registration with this email already exists. Please wait for verification or contact support.']);
            exit;
        }
        
        // Calculate upload directory outside of ginto directory (../storage relative to project root)
        $projectRoot = dirname(dirname(__DIR__)); // Goes up from src/Routes to project root (ginto)
        $uploadDir = dirname($projectRoot) . '/storage/payments/gcash/receipts/'; // Goes one level up from ginto
        
        // Create upload directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                error_log('Failed to create upload directory: ' . $uploadDir);
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Server error. Please try again later.']);
                exit;
            }
        }
        
        // Generate random secure filename (no username for security)
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = bin2hex(random_bytes(16)) . '.' . $extension; // 32 char random hex
        $filepath = $uploadDir . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            error_log('Failed to move uploaded file to: ' . $filepath);
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to save receipt. Please try again.']);
            exit;
        }
        
        // Resolve referrer ID
        $referrerId = 2; // Default sponsor
        $refSource = $_POST['sponsor_id'] ?? ($_SESSION['referral_code'] ?? null);
        if (!empty($refSource)) {
            if (is_numeric($refSource)) {
                $referrerId = (int)$refSource;
            } else {
                $resolvedId = $db->get('users', 'id', ['username' => $refSource]);
                if (!$resolvedId) {
                    $resolvedId = $db->get('users', 'id', ['public_id' => $refSource]);
                }
                if ($resolvedId) {
                    $referrerId = (int)$resolvedId;
                }
            }
        }
        
        // Combine name fields
        $fullname = '';
        $first = $_POST['firstname'] ?? $_POST['firstName'] ?? '';
        $middle = $_POST['middlename'] ?? $_POST['middleName'] ?? '';
        $last = $_POST['lastname'] ?? $_POST['lastName'] ?? '';
        if ($first || $middle || $last) {
            $fullname = trim(implode(' ', array_filter([$first, $middle, $last])));
        } else {
            $fullname = $_POST['fullname'] ?? '';
        }
        
        // Map package to plan_id (1=free, 2=go, 3=plus, 4=pro)
        $packageName = strtolower($_POST['package'] ?? 'go');
        $planIdMap = ['free' => 1, 'go' => 2, 'plus' => 3, 'pro' => 4];
        $planId = $planIdMap[$packageName] ?? 2;
        
        // Create user FIRST with pending payment status
        $passwordHash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $publicId = substr(md5(uniqid(mt_rand(), true)), 0, 12);
        
        $db->insert('users', [
            'email' => $_POST['email'],
            'username' => $_POST['username'],
            'password_hash' => $passwordHash,
            'fullname' => $fullname,
            'phone' => $_POST['phone'],
            'country' => $_POST['country'],
            'referrer_id' => $referrerId,
            'public_id' => $publicId,
            'payment_status' => 'pending',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        $userId = $db->id();
        
        if (!$userId) {
            @unlink($filepath);
            error_log('Failed to create user account for GCash payment');
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to create account. Please try again.']);
            exit;
        }
        
        // Store registration metadata in notes as JSON
        $paymentNotes = json_encode([
            'email' => $_POST['email'],
            'username' => $_POST['username'],
            'fullname' => $fullname,
            'phone' => $_POST['phone'],
            'country' => $_POST['country'],
            'referrer_id' => $referrerId,
            'original_filename' => $file['name'],
            'mime_type' => $mimeType,
            'file_size' => $file['size']
        ]);
        
        // Insert into unified subscription_payments table with user_id
        $transactionId = TransactionHelper::generateTransactionId($db);
        $auditData = TransactionHelper::captureAuditData();
        $db->insert('subscription_payments', array_merge([
            'user_id' => $userId,
            'subscription_id' => null,
            'plan_id' => $planId,
            'type' => 'registration',
            'amount' => !empty($_POST['package_amount']) ? floatval($_POST['package_amount']) : 0,
            'currency' => $_POST['package_currency'] ?? 'PHP',
            'payment_method' => 'gcash',
            'payment_reference' => $_POST['gcash_reference'],
            'status' => 'pending',
            'notes' => $paymentNotes,
            'receipt_filename' => $filename,
            'receipt_path' => $filepath,
            'transaction_id' => $transactionId
        ], $auditData));
        
        $paymentId = $db->id();
        
        if (!$paymentId) {
            // Payment insert failed - cleanup user
            $db->delete('users', ['id' => $userId]);
            @unlink($filepath);
            error_log('Failed to insert subscription_payment record for GCash');
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to save registration. Please try again.']);
            exit;
        }
        
        // Log in the user
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $_POST['username'];
        $_SESSION['email'] = $_POST['email'];
        $_SESSION['payment_status'] = 'pending';
        
        // Log the registration
        error_log("GCash payment registration: User ID=$userId, Payment ID=$paymentId, Email={$_POST['email']}, Username={$_POST['username']}, Reference={$_POST['gcash_reference']}");
        
        echo json_encode([
            'success' => true,
            'message' => 'Account created! Your premium status will be activated once we verify your GCash payment.',
            'payment_id' => $paymentId,
            'user_id' => $userId,
            'redirect' => '/chat'
        ]);
        exit;
        
    } catch (Exception $e) {
        error_log('GCash payment registration error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again later.']);
        exit;
    }
});

// Crypto Payment Info API - serves USDT BEP20 wallet info dynamically via AJAX
// The QR code is served with slight pixel variation to prevent direct hotlinking
req($router, '/api/payments/crypto-info', function() {
    // Only accept AJAX requests
    if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Direct access not allowed']);
        exit;
    }
    
    header('Content-Type: application/json');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    
    // Load wallet address from secure config file
    $cryptoConfig = require __DIR__ . '/../Views/payments/address.php';
    $walletAddress = $cryptoConfig['usdt_bep20']['address'] ?? null;
    
    if (!$walletAddress) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Crypto wallet not configured']);
        exit;
    }
    
    // Load QR code image and add slight variation
    $qrPath = __DIR__ . '/../Views/payments/usdt_qr.png';
    if (!file_exists($qrPath)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Payment QR not configured']);
        exit;
    }
    
    // Read and slightly modify the image to prevent direct scraping
    $imageData = file_get_contents($qrPath);
    $image = imagecreatefromstring($imageData);
    
    if ($image) {
        // Add invisible timestamp watermark (single pixel variations)
        $width = imagesx($image);
        $height = imagesy($image);
        
        // Modify a few edge pixels with timestamp-based values (invisible to eye, changes each request)
        $timestamp = time();
        $seed = $timestamp % 1000;
        
        // Change corner pixels slightly (imperceptible but makes each response unique)
        for ($i = 0; $i < 3; $i++) {
            $x = ($seed + $i) % max(1, $width - 10);
            $y = ($seed + $i * 2) % max(1, $height - 10);
            $existingColor = imagecolorat($image, $x, $y);
            $r = ($existingColor >> 16) & 0xFF;
            $g = ($existingColor >> 8) & 0xFF;
            $b = $existingColor & 0xFF;
            // Tiny variation that won't affect QR readability
            $newColor = imagecolorallocate($image, $r, $g, min(255, $b + ($i % 2)));
            imagesetpixel($image, $x, $y, $newColor);
        }
        
        // Output to buffer
        ob_start();
        imagepng($image);
        $modifiedImageData = ob_get_clean();
        imagedestroy($image);
        
        $base64Image = base64_encode($modifiedImageData);
    } else {
        // Fallback to original if GD fails
        $base64Image = base64_encode($imageData);
    }
    
    echo json_encode([
        'success' => true,
        'network' => 'BNB Smart Chain (BEP20)',
        'token' => 'USDT',
        'address' => $walletAddress,
        'qr_image' => 'data:image/png;base64,' . $base64Image,
        'warning' => 'Only send USDT via BNB Smart Chain (BEP20). Other networks will result in permanent loss.',
        'verification_api' => 'https://bscscan.com/address/' . $walletAddress
    ]);
    exit;
});

// Get user's pending payment details (for transaction details modal)
$router->get('/api/user/payment-details', function() use ($db) {
    if (session_status() !== PHP_SESSION_ACTIVE) {@session_start();}
    
    header('Content-Type: application/json');
    
    // Must be logged in
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        exit;
    }
    
    $userId = (int)$_SESSION['user_id'];
    
    // Get the most recent pending payment for this user
    $payment = $db->get('subscription_payments', [
        'id',
        'transaction_id',
        'plan_id',
        'type',
        'amount',
        'currency',
        'payment_method',
        'payment_reference',
        'status',
        'receipt_filename',
        'admin_review_requested',
        'admin_review_requested_at',
        'created_at',
        'ip_address',
        'user_agent',
        'device_info',
        'geo_country',
        'geo_city',
        'session_id'
    ], [
        'user_id' => $userId,
        'ORDER' => ['created_at' => 'DESC'],
        'LIMIT' => 1
    ]);
    
    if ($payment) {
        // Count total pending admin reviews (queue info)
        $totalPendingReviews = $db->count('subscription_payments', [
            'status' => 'pending',
            'admin_review_requested' => 1
        ]);
        
        // If this user requested review, get their position in queue
        $queuePosition = null;
        if ($payment['admin_review_requested']) {
            $queuePosition = $db->count('subscription_payments', [
                'status' => 'pending',
                'admin_review_requested' => 1,
                'admin_review_requested_at[<=]' => $payment['admin_review_requested_at']
            ]);
        }
        
        echo json_encode([
            'success' => true,
            'payment' => $payment,
            'pending_reviews_count' => $totalPendingReviews,
            'queue_position' => $queuePosition
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No payment record found'
        ]);
    }
    exit;
});

// Check/Sync Payment Status (for PayPal, checks API; for others, returns DB status)
$router->post('/api/payment/check-status/{paymentId}', function($paymentId) use ($db) {
    if (session_status() !== PHP_SESSION_ACTIVE) {@session_start();}
    
    header('Content-Type: application/json');
    
    // Debug logging
    error_log("check-status called for paymentId: $paymentId, session_id: " . session_id() . ", user_id: " . ($_SESSION['user_id'] ?? 'none'));
    
    if (empty($_SESSION['user_id'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Unauthorized - no session user_id', 'session_id' => session_id()]);
        exit;
    }
    
    $userId = $_SESSION['user_id'];
    
    // Get payment record
    $payment = $db->get('subscription_payments', [
        'id', 'user_id', 'payment_method', 'payment_reference', 'status', 'admin_review_requested'
    ], ['id' => $paymentId]);
    
    if (!$payment || $payment['user_id'] != $userId) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Payment not found']);
        exit;
    }
    
    $currentStatus = $payment['status'];
    $newStatus = $currentStatus;
    $message = '';
    $syncedFromPaypal = false;
    
    // For PayPal payments, check the API for current status
    if (in_array($payment['payment_method'], ['paypal', 'credit_card']) && $currentStatus === 'pending') {
        $orderId = $payment['paypal_order_id'] ?? $payment['payment_reference'];
        
        if ($orderId) {
            try {
                // Get PayPal credentials
                $paypalEnv = $_ENV['PAYPAL_ENVIRONMENT'] ?? getenv('PAYPAL_ENVIRONMENT') ?? 'sandbox';
                $clientId = $paypalEnv === 'sandbox' 
                    ? ($_ENV['PAYPAL_CLIENT_ID_SANDBOX'] ?? getenv('PAYPAL_CLIENT_ID_SANDBOX'))
                    : ($_ENV['PAYPAL_CLIENT_ID'] ?? getenv('PAYPAL_CLIENT_ID'));
                $clientSecret = $paypalEnv === 'sandbox'
                    ? ($_ENV['PAYPAL_SECRET_SANDBOX'] ?? getenv('PAYPAL_SECRET_SANDBOX'))
                    : ($_ENV['PAYPAL_SECRET'] ?? getenv('PAYPAL_SECRET'));
                
                $baseUrl = $paypalEnv === 'sandbox' 
                    ? 'https://api-m.sandbox.paypal.com'
                    : 'https://api-m.paypal.com';
                
                // Get access token
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $baseUrl . '/v1/oauth2/token');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
                curl_setopt($ch, CURLOPT_USERPWD, $clientId . ':' . $clientSecret);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                
                $tokenResponse = curl_exec($ch);
                $tokenData = json_decode($tokenResponse, true);
                curl_close($ch);
                
                if (isset($tokenData['access_token'])) {
                    // Get order details
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $baseUrl . '/v2/checkout/orders/' . $orderId);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Content-Type: application/json',
                        'Authorization: Bearer ' . $tokenData['access_token']
                    ]);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                    
                    $orderResponse = curl_exec($ch);
                    $order = json_decode($orderResponse, true);
                    curl_close($ch);
                    
                    if (isset($order['status'])) {
                        $paypalStatus = $order['status'];
                        $syncedFromPaypal = true;
                        
                        // Map PayPal status
                        switch ($paypalStatus) {
                            case 'COMPLETED':
                                $newStatus = 'completed';
                                $message = 'PayPal payment has been completed!';
                                break;
                            case 'APPROVED':
                            case 'PAYER_ACTION_REQUIRED':
                                $newStatus = 'pending';
                                $message = 'Payment requires additional action from PayPal.';
                                break;
                            case 'VOIDED':
                                $newStatus = 'failed';
                                $message = 'Payment was voided.';
                                break;
                            default:
                                $message = 'PayPal status: ' . $paypalStatus;
                        }
                        
                        // Update DB if status changed
                        if ($newStatus !== $currentStatus) {
                            $db->update('subscription_payments', [
                                'status' => $newStatus
                            ], ['id' => $paymentId]);
                            
                            // If completed, also update user status
                            if ($newStatus === 'completed') {
                                $db->update('users', [
                                    'payment_status' => 'completed'
                                ], ['id' => $userId]);
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                error_log('PayPal status check error: ' . $e->getMessage());
                $message = 'Unable to check PayPal status. Please try again later.';
            }
        }
    } else {
        // Non-PayPal payments or already completed
        switch ($currentStatus) {
            case 'completed':
                $message = 'Payment has been approved.';
                break;
            case 'pending':
                $message = 'Payment is pending admin verification.';
                break;
            case 'failed':
                $message = 'Payment was rejected.';
                break;
            default:
                $message = 'Status: ' . $currentStatus;
        }
    }
    
    echo json_encode([
        'success' => true,
        'payment_id' => $paymentId,
        'previous_status' => $currentStatus,
        'current_status' => $newStatus,
        'new_status' => $newStatus,  // Alias for frontend
        'status_changed' => $newStatus !== $currentStatus,
        'synced_from_paypal' => $syncedFromPaypal,
        'admin_review_requested' => (bool)($payment['admin_review_requested'] ?? false),
        'message' => $message
    ]);
    exit;
});

// Request Admin Review for Payment
$router->post('/api/payment/request-review/{paymentId}', function($paymentId) use ($db) {
    if (session_status() !== PHP_SESSION_ACTIVE) {@session_start();}
    
    header('Content-Type: application/json');
    
    if (empty($_SESSION['user_id'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    
    $userId = $_SESSION['user_id'];
    
    // Get payment record
    $payment = $db->get('subscription_payments', ['id', 'user_id', 'status', 'admin_review_requested'], ['id' => $paymentId]);
    
    if (!$payment || $payment['user_id'] != $userId) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Payment not found']);
        exit;
    }
    
    if ($payment['status'] === 'completed') {
        echo json_encode(['success' => false, 'message' => 'Payment is already approved']);
        exit;
    }
    
    if ($payment['admin_review_requested']) {
        echo json_encode(['success' => false, 'message' => 'Admin review already requested']);
        exit;
    }
    
    // Update payment to request admin review
    $db->update('subscription_payments', [
        'admin_review_requested' => 1,
        'admin_review_requested_at' => date('Y-m-d H:i:s')
    ], ['id' => $paymentId]);
    
    // Log for admin notification (could be enhanced with email/notification system)
    error_log("Admin review requested for payment ID: $paymentId by user ID: $userId");
    
    echo json_encode([
        'success' => true,
        'message' => 'Admin review has been requested. You will be notified once reviewed.'
    ]);
    exit;
});

// Serve receipt images securely (only for authenticated users viewing their own receipts or admins)
req($router, '/receipt-image/{filename}', function($filename) use ($db) {
    if (session_status() !== PHP_SESSION_ACTIVE) {@session_start();}
    
    // Must be logged in
    if (empty($_SESSION['user_id'])) {
        http_response_code(403);
        exit('Unauthorized');
    }
    
    $userId = $_SESSION['user_id'];
    
    // Check if user is admin
    $isAdmin = false;
    $user = $db->get('users', ['role_id'], ['id' => $userId]);
    if ($user && in_array($user['role_id'], [1, 2])) {
        $isAdmin = true;
    }
    
    // Sanitize filename to prevent directory traversal
    $filename = basename($filename);
    
    // Check if user owns this receipt and get payment method (admins can view any)
    if ($isAdmin) {
        $payment = $db->get('subscription_payments', ['id', 'payment_method'], ['receipt_filename' => $filename]);
    } else {
        $payment = $db->get('subscription_payments', ['id', 'payment_method'], [
            'user_id' => $userId,
            'receipt_filename' => $filename
        ]);
    }
    
    if (!$payment) {
        http_response_code(404);
        exit('Not found');
    }
    
    // Predetermined receipt directories based on payment method
    $projectRoot = dirname(__DIR__, 2);
    $receiptDirs = [
        'bank_transfer' => dirname($projectRoot) . '/storage/payments/bank-transfer/receipts/',
        'gcash'         => dirname($projectRoot) . '/storage/payments/gcash/receipts/',
        'crypto_usdt_bep20' => dirname($projectRoot) . '/storage/payments/crypto/transfer/receipts/',
    ];
    
    $method = $payment['payment_method'];
    $dir = $receiptDirs[$method] ?? null;
    
    if (!$dir) {
        http_response_code(404);
        exit('Unknown payment method');
    }
    
    $receiptPath = $dir . $filename;
    
    if (!file_exists($receiptPath)) {
        http_response_code(404);
        exit('File not found');
    }
    
    // Determine content type
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $contentTypes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'pdf' => 'application/pdf'
    ];
    
    $contentType = $contentTypes[$ext] ?? 'application/octet-stream';
    
    header('Content-Type: ' . $contentType);
    header('Content-Length: ' . filesize($receiptPath));
    header('Cache-Control: private, max-age=3600');
    readfile($receiptPath);
    exit;
});

// Crypto Payment Registration (USDT BEP20)
req($router, '/crypto-payments', function() use ($db, $countries) {
    if (session_status() !== PHP_SESSION_ACTIVE) {@session_start();}
    
    // Only accept POST requests via AJAX
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Content-Type: application/json');
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }
    
    // Verify AJAX request
    if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit;
    }
    
    header('Content-Type: application/json');
    
    try {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token. Please refresh the page.']);
            exit;
        }
        
        // Validate required fields
        $required = ['username', 'email', 'password', 'country', 'phone', 'crypto_txhash'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => "Field '$field' is required."]);
                exit;
            }
        }
        
        // Validate transaction hash format (should be 66 chars starting with 0x)
        $txHash = trim($_POST['crypto_txhash']);
        if (!preg_match('/^0x[a-fA-F0-9]{64}$/', $txHash)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid transaction hash format. Should be 66 characters starting with 0x.']);
            exit;
        }
        
        // Validate file upload (optional but recommended)
        $receiptFilename = null;
        $receiptPath = null;
        $mimeType = null;
        
        if (isset($_FILES['crypto_receipt']) && $_FILES['crypto_receipt']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['crypto_receipt'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
            $maxSize = 10 * 1024 * 1024; // 10MB
            
            // Validate file type
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);
            if (!in_array($mimeType, $allowedTypes)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid file type. Please upload an image (JPG, PNG, GIF, WebP) or PDF.']);
                exit;
            }
            
            // Validate file size
            if ($file['size'] > $maxSize) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'File too large. Maximum size is 10MB.']);
                exit;
            }
            
            // Calculate upload directory (app creates it if needed)
            $projectRoot = dirname(dirname(__DIR__));
            $uploadDir = dirname($projectRoot) . '/storage/payments/crypto/transfer/receipts/';
            
            // Create upload directory if it doesn't exist
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    error_log('Failed to create crypto upload directory: ' . $uploadDir);
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Server error. Please try again later.']);
                    exit;
                }
            }
            
            // Generate random secure filename
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $receiptFilename = bin2hex(random_bytes(16)) . '.' . $extension;
            $receiptPath = $uploadDir . $receiptFilename;
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $receiptPath)) {
                error_log('Failed to move crypto receipt to: ' . $receiptPath);
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to save receipt. Please try again.']);
                exit;
            }
        }
        
        // Check for existing user by email
        $existingUser = $db->get('users', 'id', ['email' => $_POST['email']]);
        if ($existingUser) {
            if ($receiptPath) @unlink($receiptPath);
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'User with this email already exists.']);
            exit;
        }
        
        // Check for existing username
        $existingUsername = $db->get('users', 'id', ['username' => $_POST['username']]);
        if ($existingUsername) {
            if ($receiptPath) @unlink($receiptPath);
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Username already taken.']);
            exit;
        }
        
        // Check for existing phone
        $existingPhone = $db->get('users', 'id', ['phone' => $_POST['phone']]);
        if ($existingPhone) {
            if ($receiptPath) @unlink($receiptPath);
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Phone number already registered.']);
            exit;
        }
        
        // Check for pending crypto payment with same tx hash
        $pendingPayment = $db->get('subscription_payments', 'id', [
            'payment_method' => 'crypto_usdt_bep20',
            'payment_reference' => $txHash
        ]);
        if ($pendingPayment) {
            if ($receiptPath) @unlink($receiptPath);
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'This transaction has already been submitted.']);
            exit;
        }
        
        // Resolve referrer ID
        $referrerId = 2; // Default sponsor
        $refSource = $_POST['sponsor_id'] ?? ($_SESSION['referral_code'] ?? null);
        if (!empty($refSource)) {
            if (is_numeric($refSource)) {
                $referrerId = (int)$refSource;
            } else {
                $resolvedId = $db->get('users', 'id', ['username' => $refSource]);
                if (!$resolvedId) {
                    $resolvedId = $db->get('users', 'id', ['public_id' => $refSource]);
                }
                if ($resolvedId) {
                    $referrerId = (int)$resolvedId;
                }
            }
        }
        
        // Combine name fields
        $fullname = '';
        $first = $_POST['firstname'] ?? $_POST['firstName'] ?? '';
        $middle = $_POST['middlename'] ?? $_POST['middleName'] ?? '';
        $last = $_POST['lastname'] ?? $_POST['lastName'] ?? '';
        if ($first || $middle || $last) {
            $fullname = trim(implode(' ', array_filter([$first, $middle, $last])));
        } else {
            $fullname = $_POST['fullname'] ?? '';
        }
        
        // Map package to plan_id (1=free, 2=go, 3=plus, 4=pro)
        $packageName = strtolower($_POST['package'] ?? 'go');
        $planIdMap = ['free' => 1, 'go' => 2, 'plus' => 3, 'pro' => 4];
        $planId = $planIdMap[$packageName] ?? 2;
        
        // Create user FIRST with pending payment status
        $passwordHash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $publicId = substr(md5(uniqid(mt_rand(), true)), 0, 12);
        
        $db->insert('users', [
            'email' => $_POST['email'],
            'username' => $_POST['username'],
            'password_hash' => $passwordHash,
            'fullname' => $fullname,
            'phone' => $_POST['phone'],
            'country' => $_POST['country'],
            'referrer_id' => $referrerId,
            'public_id' => $publicId,
            'payment_status' => 'pending',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        $userId = $db->id();
        
        if (!$userId) {
            if ($receiptPath) @unlink($receiptPath);
            error_log('Failed to create user account for crypto payment');
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to create account. Please try again.']);
            exit;
        }
        
        // Load wallet address from config
        $cryptoConfig = require __DIR__ . '/../Views/payments/address.php';
        $walletAddress = $cryptoConfig['usdt_bep20']['address'] ?? '';
        
        // Store registration metadata in notes as JSON
        $paymentNotes = json_encode([
            'email' => $_POST['email'],
            'username' => $_POST['username'],
            'fullname' => $fullname,
            'phone' => $_POST['phone'],
            'country' => $_POST['country'],
            'referrer_id' => $referrerId,
            'network' => 'BNB Smart Chain (BEP20)',
            'token' => 'USDT',
            'wallet_address' => $walletAddress,
            'original_filename' => isset($file) ? $file['name'] : null,
            'mime_type' => $mimeType,
            'file_size' => isset($file) ? $file['size'] : null,
            'bscscan_url' => 'https://bscscan.com/tx/' . $txHash
        ]);
        
        // Insert into unified subscription_payments table
        $transactionId = TransactionHelper::generateTransactionId($db);
        $auditData = TransactionHelper::captureAuditData();
        $db->insert('subscription_payments', array_merge([
            'user_id' => $userId,
            'subscription_id' => null,
            'plan_id' => $planId,
            'type' => 'registration',
            'amount' => !empty($_POST['package_amount']) ? floatval($_POST['package_amount']) : 0,
            'currency' => 'USDT',
            'payment_method' => 'crypto_usdt_bep20',
            'payment_reference' => $txHash,
            'status' => 'pending',
            'notes' => $paymentNotes,
            'receipt_filename' => $receiptFilename,
            'receipt_path' => $receiptPath,
            'transaction_id' => $transactionId
        ], $auditData));
        
        $paymentId = $db->id();
        
        if (!$paymentId) {
            // Payment insert failed - cleanup user
            $db->delete('users', ['id' => $userId]);
            if ($receiptPath) @unlink($receiptPath);
            error_log('Failed to insert crypto subscription_payment record');
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to save registration. Please try again.']);
            exit;
        }
        
        // Log in the user
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $_POST['username'];
        $_SESSION['email'] = $_POST['email'];
        $_SESSION['payment_status'] = 'pending';
        
        // Log the registration
        error_log("Crypto payment registration: User ID=$userId, Payment ID=$paymentId, Email={$_POST['email']}, TxHash=$txHash");
        
        echo json_encode([
            'success' => true,
            'message' => 'Account created! Your premium status will be activated once we verify your USDT payment on the blockchain.',
            'payment_id' => $paymentId,
            'user_id' => $userId,
            'verification_url' => 'https://bscscan.com/tx/' . $txHash,
            'redirect' => '/chat'
        ]);
        exit;
        
    } catch (Exception $e) {
        error_log('Crypto payment registration error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again later.']);
        exit;
    }
});

req($router, '/dashboard', function() use ($db, $countries) {
    // Only allow access if logged in
    if (empty($_SESSION['user_id'])) {
        if (!headers_sent()) header('Location: /login');
        exit;
    }

    $controller = new \Ginto\Controllers\UserController($db, $countries);
    $controller->dashboardAction($_SESSION['user_id']);
});

// Public profile route by numeric id, username, or public_id
req($router, '/user/profile/{ident}', function($ident) use ($db) {
    // Resolve identifier: numeric id, public_id (alphanumeric), or username
    $userId = null;
    if (ctype_digit($ident)) {
        $userId = intval($ident);
    } else {
        try {
            $uid = $db->get('users', 'id', ['public_id' => $ident]);
            if ($uid) $userId = intval($uid);
            else {
                $uid2 = $db->get('users', 'id', ['username' => $ident]);
                if ($uid2) $userId = intval($uid2);
            }
        } catch (\Throwable $_) {
            // ignore
        }
    }

    if (!$userId) {
        http_response_code(404);
        echo '<h1>User not found</h1>';
        exit;
    }

    // Prefer controller/view rendering if available
        try {
            $userModel = new \Ginto\Models\User();
            $user = $userModel->find($userId);
            if ($user) {
                // Render dedicated profile view to keep presentation separate
                \Ginto\Core\View::view('user/profile', ['user' => $user]);
                exit;
            }
        } catch (\Throwable $e) {
            http_response_code(502);
            // When the Python CLI produced no stdout, include stderr contents
            // in the response to aid debugging (trim to avoid extremely large
            // payloads). This improves visibility during dev.
            $dbgErr = is_string($err) && $err !== '' ? substr($err,0,1200) : null;
            $extra = [];
            if ($dbgErr) $extra['detail'] = $dbgErr;
            else $extra['exit_code'] = $code ?: null;
            // Map common stderr cases to clearer error names
            if ($dbgErr && stripos($dbgErr, 'Audio file is too short') !== false) {
                echo json_encode(array_merge(['error' => 'audio_too_short'], $extra));
            } else {
                echo json_encode(array_merge(['error' => 'STT CLI produced no output'], $extra));
            }
            exit;
        }
});

// User commissions page (renders `src/Views/user/commissions.php` via controller)
req($router, '/user/commissions', function() use ($db) {
    if (empty($_SESSION['user_id'])) {
        if (!headers_sent()) header('Location: /login');
        exit;
    }

    try {
        $ctrl = new \Ginto\Controllers\CommissionsController();
        return $ctrl->index();
    } catch (\Throwable $e) {
        // Fallback: attempt to include view directly if controller fails
        $viewPath = ROOT_PATH . '/src/Views/user/commissions.php';
        if (file_exists($viewPath)) {
            include $viewPath;
            exit;
        }
        http_response_code(500);
        echo 'Commissions page not available: ' . $e->getMessage();
        exit;
    }
});

// Compact-only user network view (dev route)
req($router, '/user/network-tree/compact-view', function() use ($db) {
    // Dev convenience: if no session user, try to auto-login user 'oliverbob'
    if (empty($_SESSION['user_id'])) {
        try {
            $userId = $db->get('users', 'id', ['username' => 'oliverbob']);
            if ($userId) {
                $_SESSION['user_id'] = (int)$userId;
            }
        } catch (\Throwable $_) {
            // ignore - proceed without login if DB not available
        }
    }
    // Include the compact view file at `src/Views/user/network-tree/compact-view.php`
    // (previously lived at `Views/...`)
    $viewPath = ROOT_PATH . '/src/Views/user/network-tree/compact-view.php';
    if (file_exists($viewPath)) {
        include $viewPath;
        exit;
    }

    // Fallback: check for `src/Views/users/...` (older layout) to be tolerant
    $fallback = ROOT_PATH . '/src/Views/users/network-tree/compact-view.php';
    if (file_exists($fallback)) {
        include $fallback;
        exit;
    }

    http_response_code(500);
    echo "Compact view not found. Expected: $viewPath (or fallback: $fallback)";
});

// Webhook endpoint (PayPal and status view)
req($router, '/webhook', function() use ($db) {
    try {
        // Prefer the dedicated controller if available
        if (class_exists('\\App\\Controllers\\WebhookController')) {
            try {
                $ctrl = new \App\Controllers\WebhookController($db);
                return $ctrl->webhook();
            } catch (\Throwable $e) {
                error_log('WebhookController init failed: ' . $e->getMessage());
                // If it's a POST (webhook delivery) return 500 so sender can retry.
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    http_response_code(500);
                    echo json_encode(['error' => 'Webhook controller not configured']);
                    exit;
                }
                // For GET or OPTIONS, fall back to the static view to show status/info.
            }
        }

        // Fallback: include the view file directly
        $viewPath = ROOT_PATH . '/src/Views/webhook/webhook.php';
        if (file_exists($viewPath)) { include $viewPath; exit; }

        http_response_code(500); echo 'Webhook handler not available'; exit;
    } catch (\Throwable $e) {
        http_response_code(500); error_log('Webhook route error: ' . $e->getMessage()); echo 'Webhook route error'; exit;
    }
});

req($router, '/webhook/status', function() use ($db) {
    try {
        if (class_exists('\\App\\Controllers\\WebhookController')) {
            try {
                $ctrl = new \App\Controllers\WebhookController($db);
                return $ctrl->saiCodeCheck();
            } catch (\Throwable $e) {
                error_log('WebhookController init failed (status): ' . $e->getMessage());
                // Fall back to view below
            }
        }
        // ...
    } catch (\Throwable $e) {
        http_response_code(500); error_log('Webhook status route error: ' . $e->getMessage()); echo 'Webhook status route error'; exit;
    }
});

// User info endpoint - returns user data with CSRF token
// Usage: GET http://localhost/user
req($router, '/user', function() use ($db) {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
        exit;
    }
    
    $userController = new \Ginto\Controllers\UserController($db);
    $userController->getUserInfoAction();
});

// Standalone Editor Object - Monaco editor with file management
// Usage: GET http://localhost/editor
// NOTE: This route intentionally uses the lookup-only helper
// `getSandboxRootIfExists()` and MUST NOT call `getOrCreateSandboxRoot()`
// so that a simple page render of `/editor` never creates a sandbox.
req($router, '/editor', function() use ($db) {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        exit;
    }
    
    // Check if user is logged in
    $isLoggedIn = !empty($_SESSION['user_id']);
    
    // Get existing sandbox root for this user (with validation to clear stale session data)
    $sandboxRoot = null;
    $sandboxId = 'unavailable';
    try {
        $sandboxRoot = \Ginto\Helpers\ClientSandboxHelper::getSandboxRootIfExists($db ?? null, $_SESSION ?? null, true);
        if (!empty($sandboxRoot)) {
            $sandboxId = basename($sandboxRoot);
        }
    } catch (\Throwable $e) {
        $sandboxRoot = null;
        $sandboxId = 'unavailable';
    }
    
    // Generate CSRF token
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    \Ginto\Core\View::view('editor/editor', [
        'title' => 'My Files',
        'isLoggedIn' => $isLoggedIn,
        'userId' => $isLoggedIn ? $_SESSION['user_id'] : null,
        'sandboxRoot' => $sandboxRoot,
        'sandboxId' => $sandboxId,
        'csrfToken' => $_SESSION['csrf_token']
    ]);
});

// Standalone editor - toggle sandbox/repo mode
req($router, '/editor/toggle_sandbox', function() use ($db) {
    header('Content-Type: application/json; charset=utf-8');
    
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    
    // Check if user is admin
    $isAdmin = (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin') || (!empty($_SESSION['is_admin']));
    if (!$isAdmin && $db && !empty($_SESSION['user_id'])) {
        try {
            $ur = $db->get('users', ['role_id'], ['id' => $_SESSION['user_id']]);
            if (!empty($ur) && !empty($ur['role_id'])) {
                $rr = $db->get('roles', ['name', 'display_name'], ['id' => $ur['role_id']]);
                $rname = strtolower((string)($rr['display_name'] ?? $rr['name'] ?? ''));
                if (in_array($rname, ['administrator', 'admin'], true)) $isAdmin = true;
            }
        } catch (\Throwable $_) {}
    }
    
    if (!$isAdmin) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Forbidden - admin only']);
        exit;
    }
    
    // CSRF validation
    $token = $_POST['csrf_token'] ?? '';
    if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
    
    // Toggle sandbox mode
    $val = ($_POST['use_sandbox'] ?? '') === '1';
    $_SESSION['editor_use_sandbox'] = $val ? true : false;
    
    // Also set playground flag for compatibility
    $_SESSION['playground_use_sandbox'] = $_SESSION['editor_use_sandbox'];
    
    // Persist to DB
    try {
        $db->update('users', ['playground_use_sandbox' => $val], ['id' => $_SESSION['user_id']]);
    } catch (\Throwable $_) {}
    
    // Ensure sandbox exists when enabling
    if ($val) {
        try {
            putenv('GINTO_SKIP_SANDBOX_START=1');
            \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($db ?? null, $_SESSION ?? null);
            putenv('GINTO_SKIP_SANDBOX_START');
        } catch (\Throwable $_) {}
    }
    
    // Get current sandbox id
    $sandboxId = null;
    try {
        putenv('GINTO_SKIP_SANDBOX_START=1');
        $editorRoot = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($db ?? null, $_SESSION ?? null);
        putenv('GINTO_SKIP_SANDBOX_START');
        $realRoot = realpath($editorRoot) ?: rtrim($editorRoot, '/');
        $isAdminRoot = $realRoot === (realpath(ROOT_PATH) ?: rtrim(ROOT_PATH, '/'));
        if (!$isAdminRoot) $sandboxId = basename($editorRoot);
    } catch (\Throwable $_) {}
    
    echo json_encode([
        'success' => true,
        'csrf_ok' => true,
        'use_sandbox' => $_SESSION['editor_use_sandbox'] ?? false,
        'sandbox_id' => $sandboxId,
        'csrf_token' => $_SESSION['csrf_token'] ?? null
    ]);
    exit;
}, ['POST']);

// Chat API: create a sandbox for the current session (POST only)
req($router, '/chat/create_sandbox', function() use ($db) {
    header('Content-Type: application/json; charset=utf-8');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
        exit;
    }

    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    // CSRF validation
    $token = $_POST['csrf_token'] ?? '';
    if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    try {
        putenv('GINTO_SKIP_SANDBOX_START=1');
        $editorRoot = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($db ?? null, $_SESSION ?? null);
        putenv('GINTO_SKIP_SANDBOX_START');
        $sandboxId = basename($editorRoot);
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        $_SESSION['sandbox_id'] = $sandboxId;
        echo json_encode(['success' => true, 'sandbox_id' => $sandboxId]);
        exit;
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to create sandbox']);
        exit;
    }
}, ['POST']);

// ============ CHAT CONVERSATIONS API (Database-backed for logged-in users) ============

// GET /chat/conversations - Load all conversations for logged-in user
// Also cleans up expired conversations (24 hours after creation)
req($router, '/chat/conversations', function() use ($db) {
    header('Content-Type: application/json; charset=utf-8');
    
    // Only for logged-in users
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    
    $userId = (int)$_SESSION['user_id'];
    
    try {
        // First, clean up expired conversations for this user
        $db->delete('chat_conversations', [
            'user_id' => $userId,
            'expires_at[<]' => date('Y-m-d H:i:s')
        ]);
        
        // Load remaining conversations
        $rows = $db->select('chat_conversations', [
            'convo_id',
            'title',
            'messages',
            'created_at',
            'expires_at',
            'updated_at'
        ], [
            'user_id' => $userId,
            'ORDER' => ['updated_at' => 'DESC']
        ]);
        
        $convos = [];
        foreach ($rows as $row) {
            $messages = json_decode($row['messages'], true) ?: [];
            // Convert expires_at to Unix timestamp (ms) for proper JS timezone handling
            $expiresAtTs = strtotime($row['expires_at']) * 1000;
            $convos[$row['convo_id']] = [
                'id' => $row['convo_id'],
                'title' => $row['title'],
                'messages' => $messages,
                'ts' => strtotime($row['updated_at']) * 1000,
                'created_at' => $row['created_at'],
                'expires_at' => $expiresAtTs  // Unix timestamp in milliseconds
            ];
        }
        
        echo json_encode(['success' => true, 'convos' => $convos]);
        exit;
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to load conversations']);
        exit;
    }
});

// POST /chat/conversations/save - Save/update a single conversation
req($router, '/chat/conversations/save', function() use ($db) {
    header('Content-Type: application/json; charset=utf-8');
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
        exit;
    }
    
    // Only for logged-in users
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    
    // CSRF validation
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
    
    $userId = (int)$_SESSION['user_id'];
    
    // Get conversation data from POST body
    $convoId = $_POST['convo_id'] ?? '';
    $title = $_POST['title'] ?? 'New chat';
    $messagesJson = $_POST['messages'] ?? '[]';
    
    if (empty($convoId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing convo_id']);
        exit;
    }
    
    try {
        $messages = json_decode($messagesJson, true);
        if (!is_array($messages)) {
            $messages = [];
        }
        
        // Check if conversation exists
        $existing = $db->get('chat_conversations', 'id', [
            'user_id' => $userId,
            'convo_id' => $convoId
        ]);
        
        $now = date('Y-m-d H:i:s');
        
        if ($existing) {
            // Update existing conversation (don't change expires_at - keep original countdown)
            $db->update('chat_conversations', [
                'title' => $title,
                'messages' => json_encode($messages),
                'updated_at' => $now
            ], [
                'user_id' => $userId,
                'convo_id' => $convoId
            ]);
        } else {
            // Create new conversation with 24-hour expiration from now
            $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
            $db->insert('chat_conversations', [
                'user_id' => $userId,
                'convo_id' => $convoId,
                'title' => $title,
                'messages' => json_encode($messages),
                'created_at' => $now,
                'expires_at' => $expiresAt,
                'updated_at' => $now
            ]);
        }
        
        echo json_encode(['success' => true]);
        exit;
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to save conversation']);
        exit;
    }
});

// POST /chat/conversations/delete - Delete a single conversation
req($router, '/chat/conversations/delete', function() use ($db) {
    header('Content-Type: application/json; charset=utf-8');
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
        exit;
    }
    
    // Only for logged-in users
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    
    // CSRF validation
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
    
    $userId = (int)$_SESSION['user_id'];
    $convoId = $_POST['convo_id'] ?? '';
    
    if (empty($convoId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing convo_id']);
        exit;
    }
    
    try {
        $db->delete('chat_conversations', [
            'user_id' => $userId,
            'convo_id' => $convoId
        ]);
        
        echo json_encode(['success' => true]);
        exit;
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to delete conversation']);
        exit;
    }
});

// POST /chat/conversations/sync - Bulk sync all conversations from client
req($router, '/chat/conversations/sync', function() use ($db) {
    header('Content-Type: application/json; charset=utf-8');
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
        exit;
    }
    
    // Only for logged-in users
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    
    // CSRF validation
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
    
    $userId = (int)$_SESSION['user_id'];
    $convosJson = $_POST['convos'] ?? '{}';
    $activeId = $_POST['active_id'] ?? null;
    
    try {
        $clientConvos = json_decode($convosJson, true);
        if (!is_array($clientConvos)) {
            $clientConvos = [];
        }
        
        $now = date('Y-m-d H:i:s');
        
        foreach ($clientConvos as $convoId => $convo) {
            if (empty($convoId) || !is_array($convo)) continue;
            
            $title = $convo['title'] ?? 'New chat';
            $messages = $convo['messages'] ?? [];
            
            // Check if exists
            $existing = $db->get('chat_conversations', 'id', [
                'user_id' => $userId,
                'convo_id' => $convoId
            ]);
            
            if ($existing) {
                // Update (keep original expiration)
                $db->update('chat_conversations', [
                    'title' => $title,
                    'messages' => json_encode($messages),
                    'updated_at' => $now
                ], [
                    'user_id' => $userId,
                    'convo_id' => $convoId
                ]);
            } else {
                // Insert with 24-hour expiration
                $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
                $db->insert('chat_conversations', [
                    'user_id' => $userId,
                    'convo_id' => $convoId,
                    'title' => $title,
                    'messages' => json_encode($messages),
                    'created_at' => $now,
                    'expires_at' => $expiresAt,
                    'updated_at' => $now
                ]);
            }
        }
        
        echo json_encode(['success' => true]);
        exit;
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to sync conversations']);
        exit;
    }
});

// Sandbox API: Get sandbox status (LXC container status)
req($router, '/sandbox/status', function() use ($db) {
    header('Content-Type: application/json; charset=utf-8');
    
    // Allow both logged-in users (user_id) and visitors (public_id)
    if (empty($_SESSION['user_id']) && empty($_SESSION['public_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized', 'status' => 'unauthorized']);
        exit;
    }
    
    try {
        // Check if user has a sandbox ID - WITH VALIDATION (clears stale session if sandbox gone)
        $sandboxId = \Ginto\Helpers\ClientSandboxHelper::getSandboxIdIfExists($db ?? null, $_SESSION ?? null, true);
        
        if (empty($sandboxId)) {
            // No sandbox or stale sandbox was cleared
            echo json_encode([
                'success' => true,
                'status' => 'not_created',
                'sandbox_id' => null,
                'container_status' => null,
                'message' => 'No sandbox has been created for your account.'
            ]);
            exit;
        }
        
        // Check LXC container status
        $containerExists = \Ginto\Helpers\LxdSandboxManager::sandboxExists($sandboxId);
        $containerRunning = $containerExists ? \Ginto\Helpers\LxdSandboxManager::sandboxRunning($sandboxId) : false;
        $containerIp = $containerRunning ? \Ginto\Helpers\LxdSandboxManager::getSandboxIp($sandboxId) : null;
        
        // Double-check: if container doesn't exist, clear session and return not_created
        if (!$containerExists) {
            // Sandbox ID in DB but no container - cleanup stale data
            unset($_SESSION['sandbox_id']);
            echo json_encode([
                'success' => true,
                'status' => 'not_created',
                'sandbox_id' => null,
                'container_status' => null,
                'message' => 'Your sandbox session expired. Click "My Files" to create a new one.'
            ]);
            exit;
        }
        
        $containerStatus = 'not_installed';
        if ($containerExists && $containerRunning) {
            $containerStatus = 'running';
        } elseif ($containerExists) {
            $containerStatus = 'stopped';
        }
        
        echo json_encode([
            'success' => true,
            'status' => $containerStatus === 'running' ? 'ready' : ($containerExists ? 'installed' : 'not_installed'),
            'sandbox_id' => $sandboxId,
            'container_status' => $containerStatus,
            'container_ip' => $containerIp,
            'sandbox_path' => $sandboxId,
            'message' => $containerStatus === 'running' 
                ? 'Your sandbox is running and ready to use.'
                : ($containerExists ? 'Your sandbox is installed but not running.' : 'Sandbox directory exists but LXC container not installed.')
        ]);
        exit;
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to check sandbox status: ' . $e->getMessage(),
            'status' => 'error'
        ]);
        exit;
    }
});

// Sandbox API: Destroy sandbox completely (container + DB + Redis + session)
req($router, '/sandbox/destroy', function() use ($db) {
    header('Content-Type: application/json; charset=utf-8');
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
        exit;
    }
    
    // Parse JSON body if Content-Type is application/json
    $data = $_POST;
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $rawBody = file_get_contents('php://input');
        $jsonData = json_decode($rawBody, true);
        if (is_array($jsonData)) {
            $data = $jsonData;
        }
    }
    
    // CSRF validation
    $token = $data['csrf_token'] ?? '';
    if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
    
    try {
        // Get sandbox ID from session
        $sandboxId = $_SESSION['sandbox_id'] ?? null;
        
        if (empty($sandboxId)) {
            echo json_encode(['success' => true, 'message' => 'No sandbox to destroy']);
            exit;
        }
        
        // Delete sandbox completely (container + DB + Redis + directory)
        $result = \Ginto\Helpers\LxdSandboxManager::deleteSandboxCompletely($sandboxId, $db);
        
        // Clear session data
        unset($_SESSION['sandbox_id']);
        unset($_SESSION['sandbox_created_at']);
        
        // For visitors, also clear the session timestamp to give a fresh start
        if (empty($_SESSION['user_id'])) {
            unset($_SESSION['session_created_at']);
        }
        
        error_log("[/sandbox/destroy] Destroyed sandbox: {$sandboxId}");
        
        echo json_encode([
            'success' => true,
            'message' => 'Sandbox destroyed completely',
            'sandbox_id' => $sandboxId
        ]);
        exit;
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to destroy sandbox: ' . $e->getMessage()
        ]);
        exit;
    }
});

// Sandbox API: Install/Create LXC sandbox container
req($router, '/sandbox/install', function() use ($db) {
    header('Content-Type: application/json; charset=utf-8');
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
        exit;
    }
    
    // Parse JSON body if Content-Type is application/json
    $data = $_POST;
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $rawBody = file_get_contents('php://input');
        $jsonData = json_decode($rawBody, true);
        if (is_array($jsonData)) {
            $data = $jsonData;
        }
    }
    
    // Allow both logged-in users (user_id) and visitors (public_id)
    if (empty($_SESSION['user_id']) && empty($_SESSION['public_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    
    // CSRF validation
    $token = $data['csrf_token'] ?? '';
    if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
    
    // Check if user accepted terms
    $acceptedTerms = !empty($data['accept_terms']) && ($data['accept_terms'] === '1' || $data['accept_terms'] === true || $data['accept_terms'] === 1);
    if (!$acceptedTerms) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'You must accept the terms and conditions to create a sandbox.']);
        exit;
    }
    
    try {
        // =================================================================
        // PRE-FLIGHT CHECK: Is LXC/LXD installed and configured?
        // =================================================================
        // This check runs BEFORE any sandbox creation to give users
        // a clear path to install LXC if it's missing.
        // =================================================================
        $lxcStatus = \Ginto\Helpers\LxdSandboxManager::checkLxcAvailability();
        if (!$lxcStatus['available']) {
            echo json_encode([
                'success' => false,
                'error' => $lxcStatus['message'],
                'error_code' => $lxcStatus['error'],
                'install_required' => true,
                'install_command' => $lxcStatus['install_command'],
                'step' => 'lxc_check'
            ]);
            exit;
        }
        
        // Step 1: Create sandbox directory and database entry (without starting container)
        putenv('GINTO_SKIP_SANDBOX_START=1');
        
        // Force sandbox mode for this session (including admins who click "My Files")
        // This ensures admin users who create a sandbox stay in sandbox mode
        $_SESSION['playground_use_sandbox'] = true;
        
        $sandboxRoot = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($db ?? null, $_SESSION ?? null);
        putenv('GINTO_SKIP_SANDBOX_START');
        
        $sandboxId = basename($sandboxRoot);
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        $_SESSION['sandbox_id'] = $sandboxId;
        
        // Step 2: Create LXC container
        $result = \Ginto\Helpers\LxdSandboxManager::createSandbox($sandboxId, [
            'cpu' => '1',
            'memory' => '256MB',
            'packages' => ['php82', 'php82-fpm', 'caddy', 'mysql-client', 'git', 'nodejs', 'npm']
        ]);
        
        if (!$result['success']) {
            $errorMessage = $result['error'] ?? 'Failed to create LXC container';
            
            // Add nesting hint if it looks like a nesting/forkstart error
            if (stripos($errorMessage, 'forkstart') !== false || stripos($errorMessage, 'failed to run') !== false) {
                $errorMessage .= ' (Nesting may not be enabled. Run on HOST: lxc profile set default security.nesting=true OR lxc config set <container-name> security.nesting=true)';
            }
            
            echo json_encode([
                'success' => false,
                'error' => $errorMessage,
                'sandbox_id' => $sandboxId,
                'step' => 'container_creation'
            ]);
            exit;
        }
        
        // Step 3: Get container name
        // Note: Container files live in /home/ inside the container (no host bind mounts)
        // The ginto-sandbox template already has starter files in /home/
        // IP is computed deterministically via SHA256(sandboxId) - no Redis needed
        $containerName = \Ginto\Helpers\LxdSandboxManager::containerName($sandboxId);
        
        // Record acceptance of terms in database
        if ($db) {
            try {
                $db->update('client_sandboxes', [
                    'terms_accepted_at' => date('Y-m-d H:i:s'),
                    'container_created_at' => date('Y-m-d H:i:s'),
                    'container_name' => $containerName,
                    'container_status' => 'running',
                    'last_accessed_at' => date('Y-m-d H:i:s')
                ], ['sandbox_id' => $sandboxId]);
                
                // Persist sandbox mode preference for logged-in users
                if (!empty($_SESSION['user_id'])) {
                    $db->update('users', ['playground_use_sandbox' => 1], ['id' => $_SESSION['user_id']]);
                }
            } catch (\Throwable $_) {}
        }
        
        echo json_encode([
            'success' => true,
            'sandbox_id' => $sandboxId,
            'container_name' => $result['sandboxId'],
            'container_ip' => $result['ip'],
            'status' => 'running',
            'message' => 'Your sandbox has been created and is now running!'
        ]);
        exit;
        
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to install sandbox: ' . $e->getMessage()
        ]);
        exit;
    }
}, ['POST']);

// Sandbox API: Start an existing sandbox
req($router, '/sandbox/start', function() use ($db) {
    header('Content-Type: application/json; charset=utf-8');
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
        exit;
    }
    
    // Allow both logged-in users (user_id) and visitors (public_id)
    if (empty($_SESSION['user_id']) && empty($_SESSION['public_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    
    // CSRF validation
    $token = $_POST['csrf_token'] ?? '';
    if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
    
    try {
        $sandboxRoot = \Ginto\Helpers\ClientSandboxHelper::getSandboxRootIfExists($db ?? null, $_SESSION ?? null, true);
        if (empty($sandboxRoot)) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'No sandbox found. Your session may have expired.', 'needs_setup' => true]);
            exit;
        }
        
        $sandboxId = basename($sandboxRoot);
        $started = \Ginto\Helpers\LxdSandboxManager::ensureSandboxRunning($sandboxId, $sandboxRoot);
        
        if ($started) {
            $ip = \Ginto\Helpers\LxdSandboxManager::getSandboxIp($sandboxId);
            echo json_encode([
                'success' => true,
                'sandbox_id' => $sandboxId,
                'container_ip' => $ip,
                'status' => 'running',
                'message' => 'Sandbox started successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Failed to start sandbox',
                'sandbox_id' => $sandboxId
            ]);
        }
        exit;
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error starting sandbox: ' . $e->getMessage()]);
        exit;
    }
}, ['POST']);

// Sandbox API: Call sandbox-scoped MCP tools
// This endpoint allows users with active sandboxes to call sandbox_* tools
// Tools are restricted to sandbox-prefixed tools for security
// Security restrictions:
// - Logged-in users only (no visitors)
// - sandbox_exec requires premium subscription (or admin)
// - Admin users have no restrictions
req($router, '/sandbox/call', function() use ($db) {
    header('Content-Type: application/json; charset=utf-8');
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit;
    }
    
    // Ensure session is active
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    
    // Check if user is admin (admins bypass all restrictions)
    $isAdmin = \Ginto\Controllers\UserController::isAdmin($_SESSION);
    
    // SECURITY: Require logged-in user for all sandbox tools (unless admin)
    if (!$isAdmin && empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false, 
            'error' => 'Please log in to use sandbox tools. Create a free account to get started!',
            'action' => 'login'
        ]);
        exit;
    }
    
    // Parse input early to check for special wizard tool
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid JSON body']);
        exit;
    }
    
    $tool = $input['tool'] ?? null;
    $args = $input['args'] ?? [];
    
    // SPECIAL CASE: ginto_install runs the server-side installation script
    // This installs LXC/LXD and sets up the sandbox infrastructure
    if ($tool === 'ginto_install') {
        echo json_encode([
            'success' => true,
            'action' => 'ginto_install',
            'message' => 'Starting Ginto installation...',
            'result' => [
                'action' => 'ginto_install',
                'command' => 'sudo bash ~/ginto/bin/ginto.sh install',
                'message' => 'I\'ll start the Ginto installation for you. This will install LXC/LXD and set up the sandbox system. Please run this command in your server\'s SSH terminal: sudo bash ~/ginto/bin/ginto.sh install'
            ]
        ]);
        exit;
    }
    
    // SPECIAL CASE: sandbox_install_wizard doesn't require existing sandbox
    // It triggers the client-side installation wizard
    if ($tool === 'sandbox_install_wizard') {
        echo json_encode([
            'success' => true,
            'action' => 'install_sandbox',
            'message' => 'Opening sandbox installation wizard...',
            'result' => [
                'action' => 'install_sandbox',
                'message' => 'I\'ll open the sandbox installation wizard for you now.'
            ]
        ]);
        exit;
    }
    
    // Get sandbox ID
    $sandboxId = $_SESSION['sandbox_id'] ?? null;
    if (empty($sandboxId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No active sandbox. Please create a sandbox first by clicking "My Files".']);
        exit;
    }
    
    // Verify sandbox exists
    if (!\Ginto\Helpers\LxdSandboxManager::sandboxExists($sandboxId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Sandbox not found. It may have been destroyed. Please create a new one.']);
        exit;
    }
    
    if (empty($tool)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing tool parameter']);
        exit;
    }
    
    // Security: Only allow sandbox-prefixed tools
    if (!str_starts_with($tool, 'sandbox_')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied. Only sandbox tools are allowed for non-admin users.']);
        exit;
    }
    
    // SECURITY: sandbox_exec requires premium subscription (unless admin)
    if (!$isAdmin && $tool === 'sandbox_exec') {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $isPremium = false;
        
        if ($userId > 0) {
            // Check if user has active subscription
            $activeSub = $db->get('user_subscriptions', ['id', 'plan_id'], [
                'user_id' => $userId,
                'status' => 'active',
                'OR' => [
                    'expires_at' => null,
                    'expires_at[>]' => date('Y-m-d H:i:s')
                ]
            ]);
            $isPremium = !empty($activeSub);
        }
        
        if (!$isPremium) {
            http_response_code(403);
            echo json_encode([
                'success' => false, 
                'error' => 'Command execution (sandbox_exec) requires a Premium subscription. Upgrade to unlock this powerful feature!',
                'action' => 'upgrade',
                'upgrade_url' => '/upgrade'
            ]);
            exit;
        }
    }
    
    // Ensure handlers are loaded
    $root = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2);
    foreach (glob($root . '/src/Handlers/*.php') as $f) {
        require_once $f;
    }
    
    try {
        $result = \App\Core\McpInvoker::invoke($tool, $args);
        echo json_encode(['success' => true, 'result' => $result]);
    } catch (\Throwable $e) {
        \Ginto\Helpers\AdminErrorLogger::log($e->getMessage(), ['route' => '/sandbox/call', 'tool' => $tool, 'sandbox_id' => $sandboxId]);
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Tool execution failed: ' . $e->getMessage()]);
    }
    exit;
}, ['POST']);

// ============================================================================
// SANDBOX PROXY ROUTE: /clients/*
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
 * Gets sandbox ID from session, validates, ensures container is running, then proxies via port 1800
 * 
 * Architecture:
 *   Browser → /clients/path → (PHP gets sandbox from session) → localhost:1800 → container:80
 */
function handleClientProxy(string $path, $db): void
{
    // Start session if not started
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
    
    // Get sandbox ID from session
    $sandboxId = $_SESSION['sandbox_id'] ?? null;
    
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
    
    // Ensure container is running (auto-start if stopped)
    if (!\Ginto\Helpers\LxdSandboxManager::sandboxRunning($sandboxId)) {
        $started = \Ginto\Helpers\LxdSandboxManager::ensureSandboxRunning($sandboxId);
        
        if (!$started) {
            http_response_code(503);
            header('Content-Type: text/html; charset=utf-8');
            echo '<h1>503 - Sandbox Unavailable</h1><p>Your sandbox could not be started. Please try again.</p>';
            exit;
        }
        
        // Wait a moment for container to fully initialize
        // IP is computed deterministically via SHA256(sandboxId) - no Redis caching needed
        usleep(500000); // 0.5 seconds
    }
    
    // Get container IP directly from LXD (no Node proxy needed)
    $containerIp = \Ginto\Helpers\LxdSandboxManager::sandboxToIp($sandboxId);
    
    if (!$containerIp) {
        http_response_code(503);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Sandbox unavailable']);
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
    
    // Ensure container is running
    if (!\Ginto\Helpers\LxdSandboxManager::sandboxRunning($sandboxId)) {
        $started = \Ginto\Helpers\LxdSandboxManager::ensureSandboxRunning($sandboxId);
        
        if (!$started) {
            http_response_code(503);
            header('Content-Type: text/html; charset=utf-8');
            echo '<h1>503 - Sandbox Unavailable</h1><p>Could not start sandbox. Please try again.</p>';
            exit;
        }
        
        // IP is computed deterministically via SHA256(sandboxId) - no Redis caching needed
        usleep(500000); // Wait 0.5s for container to initialize
    }
    
    // Proxy via port 1800 (Node.js sandbox proxy)
    $proxyUrl = 'http://127.0.0.1:1800' . $path . '?sandbox=' . urlencode($sandboxId);
    
    // Forward the request using cURL
    $ch = curl_init($proxyUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    
    $headers = [];
    $headers[] = 'X-Forwarded-For: ' . ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
    $headers[] = 'X-Forwarded-Host: ' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $headers[] = 'X-Original-URI: /sandbox-preview/' . $sandboxId . $path;
    $headers[] = 'X-Sandbox-ID: ' . $sandboxId;
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
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
req($router, '/editor/tree', function() use ($db) {
    header('Content-Type: application/json; charset=utf-8');
    
    // Get sandbox ID or root path
    $editorRoot = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2);
    $sandboxId = null;
    $isLoggedIn = !empty($_SESSION['user_id']);
    
    try {
        putenv('GINTO_SKIP_SANDBOX_START=1');
        $result = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($db, $_SESSION ?? null);
        putenv('GINTO_SKIP_SANDBOX_START');
        
        // Check if result is a sandbox ID (short alphanumeric) or a filesystem path
        if ($result && !is_dir($result) && preg_match('/^[a-z0-9]{8,20}$/i', $result)) {
            $sandboxId = $result;
        } else {
            $editorRoot = $result ?: $editorRoot;
        }
    } catch (\Throwable $e) {}
    
    // If we have a sandbox ID, validate it exists before using
    if ($sandboxId) {
        $sandboxValid = \Ginto\Helpers\ClientSandboxHelper::validateSandboxExists($sandboxId, $db);
        
        if (!$sandboxValid) {
            // Sandbox is stale - clean up and create a new one
            \Ginto\Helpers\LxdSandboxManager::deleteSandboxCompletely($sandboxId, $db);
            unset($_SESSION['sandbox_id']);
            
            // Create fresh sandbox
            $newSandboxId = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxId($db, $_SESSION);
            if ($newSandboxId) {
                $createResult = \Ginto\Helpers\LxdSandboxManager::createSandbox($newSandboxId);
                if ($createResult['success']) {
                    \Ginto\Helpers\LxdSandboxManager::ensureSandboxRunning($newSandboxId);
                    $_SESSION['sandbox_id'] = $newSandboxId;
                    $sandboxId = $newSandboxId;
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to create sandbox', 'tree' => []]);
                    exit;
                }
            }
        }
        
        $listResult = \Ginto\Helpers\LxdSandboxManager::listFiles($sandboxId, '/home', 5);
        if ($listResult['success']) {
            echo json_encode(['success' => true, 'tree' => $listResult['tree'], 'sandbox_id' => $sandboxId]);
        } else {
            echo json_encode(['success' => false, 'error' => $listResult['error'] ?? 'Failed to list files', 'tree' => []]);
        }
        exit;
    }
    
    // Build tree recursively for local filesystem (admin mode)
    function buildEditorTree($dir, $maxDepth = 10, $depth = 0, $base = '') {
        if ($depth > $maxDepth || !is_dir($dir)) return [];
        
        $tree = [];
        $items = @scandir($dir);
        if (!$items) return [];
        
        // Filter and sort - folders first, then files
        $folders = [];
        $files = [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            if (in_array($item, ['vendor', 'node_modules', '.git', '__pycache__', '.cache', '.idea'], true)) continue;
            
            $path = $dir . '/' . $item;
            $relPath = $base ? $base . '/' . $item : $item;
            
            if (is_dir($path)) {
                $folders[] = ['name' => $item, 'path' => $path, 'relPath' => $relPath];
            } else {
                $files[] = ['name' => $item, 'path' => $path, 'relPath' => $relPath];
            }
        }
        
        // Sort alphabetically
        usort($folders, fn($a, $b) => strcasecmp($a['name'], $b['name']));
        usort($files, fn($a, $b) => strcasecmp($a['name'], $b['name']));
        
        // Add folders
        foreach ($folders as $f) {
            $tree[$f['name']] = [
                'type' => 'folder',
                'path' => $f['relPath'],
                'encoded' => base64_encode($f['relPath']),
                'children' => buildEditorTree($f['path'], $maxDepth, $depth + 1, $f['relPath'])
            ];
        }
        
        // Add files
        foreach ($files as $f) {
            $tree[$f['name']] = [
                'type' => 'file',
                'path' => $f['relPath'],
                'encoded' => base64_encode($f['relPath'])
            ];
        }
        
        return $tree;
    }
    
    $tree = buildEditorTree($editorRoot);
    echo json_encode(['success' => true, 'tree' => $tree]);
    exit;
});

// Standalone editor - create file/folder
req($router, '/editor/create', function() use ($db) {
    header('Content-Type: application/json; charset=utf-8');
    
    // Get sandbox ID or root path
    $editorRoot = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2);
    $sandboxId = null;
    try {
        putenv('GINTO_SKIP_SANDBOX_START=1');
        $result = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($db, $_SESSION ?? null);
        putenv('GINTO_SKIP_SANDBOX_START');
        
        // Check if result is a sandbox ID or a filesystem path
        if ($result && !is_dir($result) && preg_match('/^[a-z0-9]{8,20}$/i', $result)) {
            $sandboxId = $result;
        } else {
            $editorRoot = $result ?: $editorRoot;
        }
    } catch (\Throwable $e) {}
    
    $path = $_POST['path'] ?? '';
    $type = $_POST['type'] ?? 'file';
    
    if (empty($path)) {
        echo json_encode(['success' => false, 'error' => 'Path is required']);
        exit;
    }
    
    // Security: prevent path traversal
    $path = str_replace(['../', '..\\'], '', $path);
    
    // If we have a sandbox ID, use LXD to create item
    if ($sandboxId) {
        // Check if already exists
        if (\Ginto\Helpers\LxdSandboxManager::pathExists($sandboxId, $path)) {
            echo json_encode(['success' => false, 'error' => ($type === 'folder' ? 'Folder' : 'File') . ' already exists']);
            exit;
        }
        
        $createResult = \Ginto\Helpers\LxdSandboxManager::createItem($sandboxId, $path, $type);
        if ($createResult['success']) {
            echo json_encode([
                'success' => true,
                'path' => $path,
                'encoded' => base64_encode($path)
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => $createResult['error'] ?? 'Failed to create']);
        }
        exit;
    }
    
    // Local filesystem (admin mode)
    $fullPath = rtrim($editorRoot, '/') . '/' . ltrim($path, '/');
    
    // Ensure parent directory exists
    $parentDir = dirname($fullPath);
    if (!is_dir($parentDir)) {
        mkdir($parentDir, 0755, true);
    }
    
    if ($type === 'folder') {
        if (is_dir($fullPath)) {
            echo json_encode(['success' => false, 'error' => 'Folder already exists']);
            exit;
        }
        mkdir($fullPath, 0755, true);
    } else {
        if (file_exists($fullPath)) {
            echo json_encode(['success' => false, 'error' => 'File already exists']);
            exit;
        }
        file_put_contents($fullPath, '');
    }
    
    echo json_encode([
        'success' => true,
        'path' => $path,
        'encoded' => base64_encode($path)
    ]);
    exit;
}, ['POST']);

// Standalone editor - rename file/folder
req($router, '/editor/rename', function() use ($db) {
    header('Content-Type: application/json; charset=utf-8');
    
    // Get sandbox ID or root path
    $editorRoot = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2);
    $sandboxId = null;
    try {
        putenv('GINTO_SKIP_SANDBOX_START=1');
        $result = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($db, $_SESSION ?? null);
        putenv('GINTO_SKIP_SANDBOX_START');
        
        // Check if result is a sandbox ID or a filesystem path
        if ($result && !is_dir($result) && preg_match('/^[a-z0-9]{8,20}$/i', $result)) {
            $sandboxId = $result;
        } else {
            $editorRoot = $result ?: $editorRoot;
        }
    } catch (\Throwable $e) {}
    
    $oldPath = $_POST['oldPath'] ?? '';
    $newPath = $_POST['newPath'] ?? '';
    
    if (empty($oldPath) || empty($newPath)) {
        echo json_encode(['success' => false, 'error' => 'Both old and new paths required']);
        exit;
    }
    
    // Security: prevent path traversal
    $oldPath = str_replace(['../', '..\\'], '', $oldPath);
    $newPath = str_replace(['../', '..\\'], '', $newPath);
    
    // If we have a sandbox ID, use LXD to rename item
    if ($sandboxId) {
        // Check if destination already exists
        if (\Ginto\Helpers\LxdSandboxManager::pathExists($sandboxId, $newPath)) {
            echo json_encode(['success' => false, 'error' => 'Destination already exists']);
            exit;
        }
        
        $renameResult = \Ginto\Helpers\LxdSandboxManager::renameItem($sandboxId, $oldPath, $newPath);
        if ($renameResult['success']) {
            echo json_encode([
                'success' => true,
                'path' => $newPath,
                'encoded' => base64_encode($newPath)
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => $renameResult['error'] ?? 'Failed to rename']);
        }
        exit;
    }
    
    // Local filesystem (admin mode)
    $oldFullPath = rtrim($editorRoot, '/') . '/' . ltrim($oldPath, '/');
    $newFullPath = rtrim($editorRoot, '/') . '/' . ltrim($newPath, '/');
    
    if (!file_exists($oldFullPath)) {
        echo json_encode(['success' => false, 'error' => 'Source does not exist']);
        exit;
    }
    
    if (file_exists($newFullPath)) {
        echo json_encode(['success' => false, 'error' => 'Destination already exists']);
        exit;
    }
    
    // Ensure parent directory exists
    $parentDir = dirname($newFullPath);
    if (!is_dir($parentDir)) {
        mkdir($parentDir, 0755, true);
    }
    
    rename($oldFullPath, $newFullPath);
    
    echo json_encode([
        'success' => true,
        'path' => $newPath,
        'encoded' => base64_encode($newPath)
    ]);
    exit;
}, ['POST']);

// Standalone editor - delete file/folder
req($router, '/editor/delete', function() use ($db) {
    header('Content-Type: application/json; charset=utf-8');
    
    // Get sandbox ID or root path
    $editorRoot = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2);
    $sandboxId = null;
    try {
        putenv('GINTO_SKIP_SANDBOX_START=1');
        $result = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($db, $_SESSION ?? null);
        putenv('GINTO_SKIP_SANDBOX_START');
        
        // Check if result is a sandbox ID or a filesystem path
        if ($result && !is_dir($result) && preg_match('/^[a-z0-9]{8,20}$/i', $result)) {
            $sandboxId = $result;
        } else {
            $editorRoot = $result ?: $editorRoot;
        }
    } catch (\Throwable $e) {}
    
    $path = $_POST['path'] ?? '';
    
    if (empty($path)) {
        echo json_encode(['success' => false, 'error' => 'Path is required']);
        exit;
    }
    
    // Security: prevent path traversal
    $path = str_replace(['../', '..\\'], '', $path);
    
    // If we have a sandbox ID, use LXD to delete item
    if ($sandboxId) {
        $deleteResult = \Ginto\Helpers\LxdSandboxManager::deleteItem($sandboxId, $path);
        if ($deleteResult['success']) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $deleteResult['error'] ?? 'Failed to delete']);
        }
        exit;
    }
    
    // Local filesystem (admin mode)
    $fullPath = rtrim($editorRoot, '/') . '/' . ltrim($path, '/');
    
    if (!file_exists($fullPath)) {
        echo json_encode(['success' => false, 'error' => 'Path does not exist']);
        exit;
    }
    
    // Recursive delete for directories
    function deleteRecursive($path) {
        if (is_dir($path)) {
            $items = scandir($path);
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                deleteRecursive($path . '/' . $item);
            }
            rmdir($path);
        } else {
            unlink($path);
        }
    }
    
    deleteRecursive($fullPath);
    
    echo json_encode(['success' => true]);
    exit;
}, ['POST']);

// Standalone editor - paste (copy/move) file/folder
req($router, '/editor/paste', function() use ($db) {
    header('Content-Type: application/json; charset=utf-8');
    
    // Get sandbox ID or root path
    $editorRoot = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2);
    $sandboxId = null;
    try {
        putenv('GINTO_SKIP_SANDBOX_START=1');
        $result = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($db, $_SESSION ?? null);
        putenv('GINTO_SKIP_SANDBOX_START');
        
        // Check if result is a sandbox ID or a filesystem path
        if ($result && !is_dir($result) && preg_match('/^[a-z0-9]{8,20}$/i', $result)) {
            $sandboxId = $result;
        } else {
            $editorRoot = $result ?: $editorRoot;
        }
    } catch (\Throwable $e) {}
    
    $source = $_POST['source'] ?? '';
    $destination = $_POST['destination'] ?? '';
    $action = $_POST['action'] ?? 'copy';
    
    if (empty($source)) {
        echo json_encode(['success' => false, 'error' => 'Source path is required']);
        exit;
    }
    
    // Security: prevent path traversal
    $source = str_replace(['../', '..\\'], '', $source);
    $destination = str_replace(['../', '..\\'], '', $destination);
    
    // If we have a sandbox ID, use LXD operations
    if ($sandboxId) {
        $sourceName = basename($source);
        $destPath = $destination ? rtrim($destination, '/') . '/' . $sourceName : $sourceName;
        
        // Handle naming conflicts
        if (\Ginto\Helpers\LxdSandboxManager::pathExists($sandboxId, $destPath) && $source !== $destPath) {
            $i = 1;
            $ext = pathinfo($sourceName, PATHINFO_EXTENSION);
            $base = pathinfo($sourceName, PATHINFO_FILENAME);
            while (\Ginto\Helpers\LxdSandboxManager::pathExists($sandboxId, $destPath)) {
                $newName = $ext ? "$base ($i).$ext" : "$base ($i)";
                $destPath = $destination ? rtrim($destination, '/') . '/' . $newName : $newName;
                $i++;
            }
        }
        
        if ($action === 'cut') {
            $result = \Ginto\Helpers\LxdSandboxManager::renameItem($sandboxId, $source, $destPath);
        } else {
            $result = \Ginto\Helpers\LxdSandboxManager::copyItem($sandboxId, $source, $destPath);
        }
        
        echo json_encode($result['success'] ? ['success' => true] : ['success' => false, 'error' => $result['error'] ?? 'Operation failed']);
        exit;
    }
    
    // Local filesystem (admin mode)
    $sourceFullPath = rtrim($editorRoot, '/') . '/' . ltrim($source, '/');
    $sourceName = basename($source);
    $destDir = $destination ? rtrim($editorRoot, '/') . '/' . ltrim($destination, '/') : $editorRoot;
    $destFullPath = rtrim($destDir, '/') . '/' . $sourceName;
    
    if (!file_exists($sourceFullPath)) {
        echo json_encode(['success' => false, 'error' => 'Source does not exist']);
        exit;
    }
    
    // Handle naming conflicts
    if (file_exists($destFullPath) && $sourceFullPath !== $destFullPath) {
        $i = 1;
        $ext = pathinfo($sourceName, PATHINFO_EXTENSION);
        $base = pathinfo($sourceName, PATHINFO_FILENAME);
        while (file_exists($destFullPath)) {
            $newName = $ext ? "$base ($i).$ext" : "$base ($i)";
            $destFullPath = rtrim($destDir, '/') . '/' . $newName;
            $i++;
        }
    }
    
    // Ensure destination directory exists
    if (!is_dir($destDir)) {
        mkdir($destDir, 0755, true);
    }
    
    // Recursive copy for directories
    function copyRecursive($src, $dst) {
        if (is_dir($src)) {
            mkdir($dst, 0755, true);
            $items = scandir($src);
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                copyRecursive($src . '/' . $item, $dst . '/' . $item);
            }
        } else {
            copy($src, $dst);
        }
    }
    
    if ($action === 'cut') {
        rename($sourceFullPath, $destFullPath);
    } else {
        copyRecursive($sourceFullPath, $destFullPath);
    }
    
    echo json_encode(['success' => true]);
    exit;
}, ['POST']);

// Standalone editor - save file
req($router, '/editor/save', function() use ($db) {
    header('Content-Type: application/json; charset=utf-8');
    
    // Only allow POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit;
    }
    
    // CSRF validation - check both 'csrf_token' and 'file' param names for compatibility
    $token = $_POST['csrf_token'] ?? '';
    if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
    
    // Get sandbox ID or root path
    $editorRoot = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2);
    $sandboxId = null;
    try {
        putenv('GINTO_SKIP_SANDBOX_START=1');
        $result = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($db, $_SESSION ?? null);
        putenv('GINTO_SKIP_SANDBOX_START');
        
        // Check if result is a sandbox ID or a filesystem path
        if ($result && !is_dir($result) && preg_match('/^[a-z0-9]{8,20}$/i', $result)) {
            $sandboxId = $result;
        } else {
            $editorRoot = $result ?: $editorRoot;
        }
    } catch (\Throwable $e) {}
    
    // Support both 'file' and 'encoded' param names for compatibility
    $encoded = $_POST['file'] ?? $_POST['encoded'] ?? '';
    $content = $_POST['content'] ?? '';
    
    if (empty($encoded)) {
        echo json_encode(['success' => false, 'error' => 'File path is required']);
        exit;
    }
    
    $path = base64_decode($encoded);
    if ($path === false) {
        echo json_encode(['success' => false, 'error' => 'Invalid file encoding']);
        exit;
    }
    
    // Security: prevent path traversal
    $path = str_replace(['../', '..\\'], '', $path);
    
    // If we have a sandbox ID, use LXD to write file
    if ($sandboxId) {
        $writeResult = \Ginto\Helpers\LxdSandboxManager::writeFile($sandboxId, $path, $content);
        if ($writeResult['success']) {
            echo json_encode(['success' => true, 'bytes' => $writeResult['bytes'] ?? strlen($content)]);
        } else {
            echo json_encode(['success' => false, 'error' => $writeResult['error'] ?? 'Failed to write file']);
        }
        exit;
    }
    
    // Local filesystem (admin mode)
    $fullPath = rtrim($editorRoot, '/') . '/' . ltrim($path, '/');
    
    // Ensure parent directory exists
    $parentDir = dirname($fullPath);
    if (!is_dir($parentDir)) {
        mkdir($parentDir, 0755, true);
    }
    
    $result = file_put_contents($fullPath, $content);
    
    if ($result === false) {
        echo json_encode(['success' => false, 'error' => 'Failed to write file']);
        exit;
    }
    
    echo json_encode(['success' => true, 'bytes' => $result]);
    exit;
}, ['POST']);

// Standalone editor - read file content
req($router, '/editor/file', function() use ($db) {
    header('Content-Type: application/json; charset=utf-8');
    
    // Get sandbox ID or root path
    $editorRoot = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2);
    $sandboxId = null;
    try {
        putenv('GINTO_SKIP_SANDBOX_START=1');
        $result = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($db, $_SESSION ?? null);
        putenv('GINTO_SKIP_SANDBOX_START');
        
        // Check if result is a sandbox ID or a filesystem path
        if ($result && !is_dir($result) && preg_match('/^[a-z0-9]{8,20}$/i', $result)) {
            $sandboxId = $result;
        } else {
            $editorRoot = $result ?: $editorRoot;
        }
    } catch (\Throwable $e) {}
    
    $encoded = $_GET['file'] ?? $_POST['file'] ?? '';
    
    if (empty($encoded)) {
        echo json_encode(['success' => false, 'error' => 'File path is required']);
        exit;
    }
    
    $path = base64_decode($encoded);
    if ($path === false) {
        echo json_encode(['success' => false, 'error' => 'Invalid file encoding']);
        exit;
    }
    
    // Security: prevent path traversal
    $path = str_replace(['../', '..\\'], '', $path);
    
    // If we have a sandbox ID, use LXD to read file
    if ($sandboxId) {
        $readResult = \Ginto\Helpers\LxdSandboxManager::readFile($sandboxId, $path);
        if ($readResult['success']) {
            echo json_encode([
                'success' => true,
                'content' => $readResult['content'],
                'path' => $path,
                'encoded' => $encoded
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => $readResult['error'] ?? 'Failed to read file']);
        }
        exit;
    }
    
    // Local filesystem (admin mode)
    $fullPath = rtrim($editorRoot, '/') . '/' . ltrim($path, '/');
    
    if (!file_exists($fullPath) || !is_file($fullPath)) {
        echo json_encode(['success' => false, 'error' => 'File not found']);
        exit;
    }
    
    $content = file_get_contents($fullPath);
    
    echo json_encode([
        'success' => true,
        'content' => $content,
        'path' => $path,
        'encoded' => $encoded
    ]);
    exit;
});

// Rate limit status endpoint - shows current usage and limits
// Usage: GET /rate-limits
req($router, '/rate-limits', function() use ($db) {
    header('Content-Type: application/json');
    
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
    
    try {
        $rateLimitService = new \App\Core\RateLimitService($db ?? null);
        $userId = $_SESSION['user_id'] ?? ($_SESSION['sandbox_id'] ?? session_id());
        $userRole = !empty($_SESSION['user_id']) ? ($_SESSION['is_admin'] ?? false ? 'admin' : 'user') : 'visitor';
        
        $provider = 'groq';
        $model = 'openai/gpt-oss-120b';
        
        // Get user's current limits and usage
        $canMakeRequest = $rateLimitService->canMakeRequest($userId, $userRole, $provider, $model);
        $shouldFallback = $rateLimitService->shouldUseFallback($provider, $model);
        $providerSelection = $rateLimitService->selectProvider($provider, $model);
        
        // Get org-level usage
        $orgMinute = $rateLimitService->getOrgUsage($provider, $model, 'minute');
        $orgDay = $rateLimitService->getOrgUsage($provider, $model, 'day');
        
        // Get rate limits from config
        $limits = $rateLimitService->getRateLimits($provider, $model);
        
        // Calculate tier percentage
        $tierPercentages = ['admin' => 50, 'user' => 10, 'visitor' => 5];
        $tierPercent = $tierPercentages[strtolower($userRole)] ?? 5;
        
        echo json_encode([
            'success' => true,
            'user' => [
                'id' => $userId,
                'role' => $userRole,
                'tier_percent' => $tierPercent,
            ],
            'can_make_request' => $canMakeRequest['allowed'],
            'limit_reason' => $canMakeRequest['reason'] ?? null,
            'usage' => $canMakeRequest['usage'] ?? null,
            'organization' => [
                'minute' => $orgMinute,
                'day' => $orgDay,
                'limits' => $limits,
            ],
            'fallback' => [
                'should_use' => $shouldFallback['use_fallback'],
                'reason' => $shouldFallback['reason'] ?? null,
                'provider' => $providerSelection['provider'],
                'is_fallback' => $providerSelection['is_fallback'],
            ],
            'providers' => [
                'primary' => $provider,
                'fallback' => getenv('RATE_LIMIT_FALLBACK_PROVIDER') ?: 'cerebras',
                'threshold' => (int)(getenv('RATE_LIMIT_FALLBACK_THRESHOLD') ?: 80),
            ],
        ], JSON_PRETTY_PRINT);
    } catch (\Throwable $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to retrieve rate limit status',
            'message' => $e->getMessage(),
        ]);
    }
    exit;
});

// Simple streaming test route for Groq API.
// GET will render the test page; POST streams provider output.
// Usage (GET): open http://localhost/chat
// Usage (POST): curl -X POST -F 'prompt=Your prompt here' http://localhost/chat
req($router, '/chat', function() use ($db) {
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

                echo str_repeat(' ', 1024);
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
                
                // Stream response from Ollama
                $fullResponse = '';
                $ollamaProvider->streamChat($messages, function($chunk) use (&$fullResponse) {
                    $fullResponse .= $chunk;
                    echo "data: " . json_encode(['text' => $chunk]) . "\n\n";
                    flush();
                });
                
                // Final message with rendered HTML
                $parsedown = null;
                if (class_exists('\ParsedownExtra')) {
                    try { $parsedown = new \ParsedownExtra(); } catch (\Throwable $_) {}
                } elseif (class_exists('\Parsedown')) {
                    try { $parsedown = new \Parsedown(); } catch (\Throwable $_) {}
                }
                if ($parsedown && method_exists($parsedown, 'setSafeMode')) {
                    try { $parsedown->setSafeMode(true); } catch (\Throwable $_) {}
                }
                
                $html = $parsedown ? $parsedown->text($fullResponse) : nl2br(htmlspecialchars($fullResponse));
                echo "data: " . json_encode(['final' => true, 'html' => $html]) . "\n\n";
                flush();
                exit;
            }
        } catch (\Throwable $e) {
            // Ollama failed, fall through to cloud providers
            error_log("Ollama provider failed: " . $e->getMessage());
        }
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
        // 1. No cloud API key is available, OR
        // 2. Local LLM is set as primary provider
        // Exception: Web search still needs Groq (requires tool calling)
        if (!$requiresGroq && !$hasImage && ($localLlmConfig->isPrimary() || empty($apiKey))) {
            if ($localLlmConfig->isEnabled() && $localLlmConfig->isReasoningServerHealthy()) {
                $useLocalLlm = true;
                $selectedProvider = 'local';
                $apiKey = 'local'; // Placeholder - local LLM doesn't need an API key
            }
        }
        
        // Use local vision model for image requests if:
        // 1. Has an image attached, AND
        // 2. Local vision server is healthy, AND
        // 3. Either no cloud API key OR local is set as primary
        if ($hasImage && $canUseLocalVision && ($localLlmConfig->isPrimary() || empty($apiKey))) {
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
        } else {
            // Text/web search requests use provider-specific model name
            $modelName = $modelMapping[$selectedProvider]['gpt-oss-120b'] ?? 'gpt-oss-120b';
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
        if ($parsedown && method_exists($parsedown, 'setSafeMode')) {
            try { $parsedown->setSafeMode(true); } catch (\Throwable $_) {}
        }

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
                    
                    // Render markdown on server if configured (provides consistent rendering)
                    $renderOnServer = \Ginto\Helpers\ChatConfig::get('streaming.renderMarkdownOnServer', true);
                    if ($renderOnServer && $parsedown) {
                        // Render accumulated content as HTML for consistent markdown
                        $html = $parsedown->text($accumulatedContent);
                        echo "data: " . json_encode(['html' => $html, 'text' => $chunk], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
                    } else {
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
        
        // Send final HTML-rendered version with reasoning
        if ($finalContent || $accumulatedReasoning) {
            $html = $finalContent ? ($parsedown ? $parsedown->text($finalContent) : '<pre>' . htmlspecialchars($finalContent) . '</pre>') : '';
            
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
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-cache');
        echo str_repeat(' ', 1024);
        flush();

        // Log full details for administrators, but don't expose provider/raw error text to clients
        \Ginto\Helpers\AdminErrorLogger::log($e->getMessage(), ['route' => '/chat', 'trace' => $e->getTraceAsString()]);
        _chatSendSSE('An internal error occurred while processing your request. Administrators have been notified.', null);
    }

    exit;
});

/**
 * Helper: Send SSE data chunk for chat streaming.
 */
function _chatSendSSE(string $content, $parsedown): void
{
    if ($content === '') return;

    try {
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
req($router, '/courses', function() use ($db) {
    $isLoggedIn = !empty($_SESSION['user_id']);
    $isAdmin = \Ginto\Controllers\UserController::isAdmin();
    $username = $_SESSION['username'] ?? null;
    $userId = $_SESSION['user_id'] ?? null;
    $userFullname = $_SESSION['fullname'] ?? $_SESSION['username'] ?? null;
    
    $courseController = new \Ginto\Controllers\CourseController($db);
    $courses = $courseController->getAllCourses();
    $categories = $courseController->getCategories();
    $userPlan = $isLoggedIn ? $courseController->getUserPlanName($userId) : 'free';
    
    // Handle category filter
    $categoryFilter = $_GET['category'] ?? null;
    if ($categoryFilter) {
        $courses = $courseController->getCoursesByCategory($categoryFilter);
    }
    
    // Handle user learning status filter
    $statusFilter = $_GET['status'] ?? null;
    $enrolledCourses = [];
    if ($isLoggedIn && $statusFilter) {
        $enrolledCourses = $courseController->getUserEnrolledCourses($userId, $statusFilter);
    }
    
    \Ginto\Core\View::view('courses/courses', [
        'title' => 'Courses',
        'isLoggedIn' => $isLoggedIn,
        'isAdmin' => $isAdmin,
        'username' => $username,
        'userId' => $userId,
        'userFullname' => $userFullname,
        'courses' => $courses,
        'categories' => $categories,
        'userPlan' => $userPlan,
        'categoryFilter' => $categoryFilter,
        'statusFilter' => $statusFilter,
        'enrolledCourses' => $enrolledCourses,
    ]);
});

// Pricing Page (must be before /courses/{slug} to avoid slug matching "pricing")
req($router, '/courses/pricing', function() use ($db) {
    $isLoggedIn = !empty($_SESSION['user_id']);
    $userId = $_SESSION['user_id'] ?? 0;
    
    $courseController = new \Ginto\Controllers\CourseController($db);
    $plans = $courseController->getSubscriptionPlans('courses');
    $currentPlan = $isLoggedIn ? $courseController->getUserPlanName($userId) : 'free';
    
    \Ginto\Core\View::view('courses/pricing', [
        'title' => 'Pricing | Ginto Courses',
        'isLoggedIn' => $isLoggedIn,
        'plans' => $plans,
        'currentPlan' => $currentPlan,
    ]);
});

// Upgrade Page (standalone upgrade/pricing page)
req($router, '/upgrade', function() use ($db) {
    $isLoggedIn = !empty($_SESSION['user_id']);
    $userId = $_SESSION['user_id'] ?? 0;
    $isAdmin = \Ginto\Controllers\UserController::isAdmin();
    $username = $_SESSION['username'] ?? null;
    $userFullname = $_SESSION['fullname'] ?? $_SESSION['username'] ?? null;
    
    // Get subscription type from query param (masterclass, course, other)
    $subscriptionType = $_GET['type'] ?? 'other';
    if (!in_array($subscriptionType, ['registration', 'course', 'masterclass', 'other'])) {
        $subscriptionType = 'other';
    }
    
    // Get plans based on subscription type (masterclass plans are 2x courses)
    $courseController = new \Ginto\Controllers\CourseController($db);
    if ($subscriptionType === 'masterclass') {
        $masterclassController = new \Ginto\Controllers\MasterclassController($db);
        $plans = $masterclassController->getSubscriptionPlans();
    } else {
        $plans = $courseController->getSubscriptionPlans('courses');
    }
    $currentPlan = $isLoggedIn ? $courseController->getUserPlanName($userId) : 'free';
    
    \Ginto\Core\View::view('upgrade', [
        'title' => 'Upgrade | Ginto',
        'isLoggedIn' => $isLoggedIn,
        'isAdmin' => $isAdmin,
        'username' => $username,
        'userId' => $userId,
        'userFullname' => $userFullname,
        'plans' => $plans,
        'currentPlan' => $currentPlan,
        'subscriptionType' => $subscriptionType,
    ]);
});

// Subscribe Page - PayPal Checkout
req($router, '/subscribe', function() use ($db) {
    $isLoggedIn = !empty($_SESSION['user_id']);
    $userId = $_SESSION['user_id'] ?? 0;
    
    $planName = $_GET['plan'] ?? null;
    $plan = null;
    $currentPlan = 'free';
    
    // Get subscription type from query param (masterclass, course, other)
    $subscriptionType = $_GET['type'] ?? 'other';
    if (!in_array($subscriptionType, ['registration', 'course', 'masterclass', 'other'])) {
        $subscriptionType = 'other';
    }
    
    // Map subscription type to plan_type in database
    $planType = ($subscriptionType === 'masterclass') ? 'masterclass' : 'courses';
    
    if ($planName) {
        $plan = $db->get('subscription_plans', '*', [
            'name' => $planName, 
            'plan_type' => $planType,
            'is_active' => 1
        ]);
    }
    
    if ($isLoggedIn) {
        $courseController = new \Ginto\Controllers\CourseController($db);
        $currentPlan = $courseController->getUserPlanName($userId);
    }
    
    // PayPal Plan IDs from .env or config
    $paypalPlanIds = [
        'go' => $_ENV['PAYPAL_PLAN_GO'] ?? getenv('PAYPAL_PLAN_GO') ?? '',
        'plus' => $_ENV['PAYPAL_PLAN_PLUS'] ?? getenv('PAYPAL_PLAN_PLUS') ?? '',
        'pro' => $_ENV['PAYPAL_PLAN_PRO'] ?? getenv('PAYPAL_PLAN_PRO') ?? '',
    ];
    
    // Use sandbox or live credentials based on environment
    $paypalEnv = $_ENV['PAYPAL_ENVIRONMENT'] ?? getenv('PAYPAL_ENVIRONMENT') ?? 'sandbox';
    if ($paypalEnv === 'sandbox') {
        $paypalClientId = $_ENV['PAYPAL_CLIENT_ID_SANDBOX'] ?? getenv('PAYPAL_CLIENT_ID_SANDBOX') ?? '';
    } else {
        $paypalClientId = $_ENV['PAYPAL_CLIENT_ID'] ?? getenv('PAYPAL_CLIENT_ID') ?? '';
    }
    
    \Ginto\Core\View::view('subscribe', [
        'title' => 'Subscribe | Ginto',
        'isLoggedIn' => $isLoggedIn,
        'userId' => $userId,
        'plan' => $plan,
        'currentPlan' => $currentPlan,
        'paypalClientId' => $paypalClientId,
        'paypalPlanIds' => $paypalPlanIds,
        'subscriptionType' => $subscriptionType,
    ]);
});

// Subscribe Success Page
req($router, '/subscribe/success', function() use ($db) {
    $subscriptionId = $_GET['subscription'] ?? null;
    $planName = 'Plus'; // Default
    
    if ($subscriptionId && !empty($_SESSION['user_id'])) {
        // Get subscription details from our database
        $subscription = $db->get('user_subscriptions', '*', ['paypal_subscription_id' => $subscriptionId]);
        if ($subscription) {
            $plan = $db->get('subscription_plans', ['display_name'], ['id' => $subscription['plan_id']]);
            $planName = $plan['display_name'] ?? 'Plus';
        }
    }
    
    \Ginto\Core\View::view('subscribe_success', [
        'title' => 'Subscription Successful | Ginto',
        'subscriptionId' => $subscriptionId,
        'planName' => $planName,
    ]);
});

// API: Subscription Activation (called after PayPal approval)
req($router, '/api/subscription/activate', function() use ($db) {
    header('Content-Type: application/json');
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }
    
    // Origin validation for CSRF protection (production security)
    $appUrl = $_ENV['APP_URL'] ?? 'https://ginto.app';
    $prodUrl = $_ENV['PRODUCTION_URL'] ?? 'https://ginto.ai';
    $allowedOrigins = [
        $appUrl, 
        rtrim($appUrl, '/'),
        $prodUrl,
        rtrim($prodUrl, '/'),
        'https://ginto.ai',
        'https://www.ginto.ai',
        'http://localhost', 
        'http://localhost:8000', 
        'http://127.0.0.1:8000'
    ];
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $referer = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_HOST);
    $appHost = parse_url($appUrl, PHP_URL_HOST);
    $prodHost = parse_url($prodUrl, PHP_URL_HOST);
    
    $originAllowed = in_array($origin, $allowedOrigins) || $origin === '';
    $refererAllowed = $referer === $appHost || $referer === $prodHost || $referer === 'ginto.ai' || $referer === 'www.ginto.ai' || $referer === 'localhost' || $referer === '127.0.0.1' || empty($referer);
    
    if (!$originAllowed && !$refererAllowed) {
        error_log("CSRF blocked: origin=$origin, referer=$referer, allowed_host=$appHost");
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden - invalid origin']);
        exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $subscriptionId = $input['subscription_id'] ?? null;
    $planName = $input['plan'] ?? null;
    $userId = $input['user_id'] ?? ($_SESSION['user_id'] ?? null);
    
    if (!$subscriptionId || !$planName || !$userId) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields', 'received' => $input]);
        exit;
    }
    
    try {
        // Get plan details
        $plan = $db->get('subscription_plans', '*', ['name' => $planName, 'is_active' => 1]);
        if (!$plan) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid plan']);
            exit;
        }
        
        // Check if subscription already exists
        $existing = $db->get('user_subscriptions', 'id', ['paypal_subscription_id' => $subscriptionId]);
        if ($existing) {
            echo json_encode(['success' => true, 'message' => 'Subscription already activated']);
            exit;
        }
        
        // Cancel any existing active subscriptions for this user
        $db->update('user_subscriptions', [
            'status' => 'cancelled',
            'cancelled_at' => date('Y-m-d H:i:s')
        ], [
            'user_id' => $userId,
            'status' => 'active'
        ]);
        
        // Create new subscription
        $now = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 month'));
        
        $db->insert('user_subscriptions', [
            'user_id' => $userId,
            'plan_id' => $plan['id'],
            'status' => 'active',
            'started_at' => $now,
            'expires_at' => $expiresAt,
            'payment_method' => 'paypal',
            'paypal_subscription_id' => $subscriptionId,
            'paypal_plan_id' => $input['paypal_plan_id'] ?? null,
            'amount_paid' => $plan['price_monthly'],
            'currency' => $plan['price_currency'] ?? 'PHP',
            'auto_renew' => 1,
            'created_at' => $now,
            'updated_at' => $now
        ]);
        
        $newSubId = $db->id();
        
        // Determine subscription type from context
        // Types: registration, course, masterclass, other
        $subscriptionType = $input['type'] ?? 'other';
        if (!in_array($subscriptionType, ['registration', 'course', 'masterclass', 'other'])) {
            $subscriptionType = 'other';
        }
        
        // Log the payment with type
        $transactionId = TransactionHelper::generateTransactionId($db);
        $auditData = TransactionHelper::captureAuditData();
        $db->insert('subscription_payments', array_merge([
            'user_id' => $userId,
            'subscription_id' => $newSubId,
            'plan_id' => $plan['id'],
            'type' => $subscriptionType,
            'amount' => $plan['price_monthly'],
            'currency' => $plan['price_currency'] ?? 'PHP',
            'payment_method' => 'paypal',
            'payment_reference' => $subscriptionId,
            'status' => 'completed',
            'paid_at' => $now,
            'notes' => 'PayPal subscription activated' . ($subscriptionType !== 'other' ? " ($subscriptionType)" : ''),
            'created_at' => $now,
            'transaction_id' => $transactionId
        ], $auditData));
        
        // Update user's plan in users table
        $db->update('users', [
            'subscription_plan' => $planName
        ], ['id' => $userId]);
        
        error_log("PayPal subscription activated: user=$userId, plan=$planName, subscription=$subscriptionId");
        
        echo json_encode([
            'success' => true,
            'subscription_id' => $newSubId,
            'plan' => $planName,
            'expires_at' => $expiresAt
        ]);
        
    } catch (\Throwable $e) {
        error_log('Subscription activation error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to activate subscription', 'details' => $e->getMessage()]);
    }
    exit;
});

// PayPal Order Creation for Registration (one-time membership payment)
req($router, '/api/register/paypal-order', function() use ($db) {
    header('Content-Type: application/json');
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }
    
    // Origin validation for CSRF protection (production security)
    $appUrl = $_ENV['APP_URL'] ?? 'https://ginto.app';
    $allowedOrigins = [$appUrl, rtrim($appUrl, '/'), 'http://localhost', 'http://localhost:8000', 'http://127.0.0.1:8000'];
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $referer = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_HOST);
    $appHost = parse_url($appUrl, PHP_URL_HOST);
    
    $originAllowed = in_array($origin, $allowedOrigins) || $origin === '';
    $refererAllowed = $referer === $appHost || $referer === 'localhost' || $referer === '127.0.0.1' || empty($referer);
    
    if (!$originAllowed && !$refererAllowed) {
        error_log("CSRF blocked: origin=$origin, referer=$referer, allowed_host=$appHost");
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden - invalid origin']);
        exit;
    }
    
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $levelId = $input['level_id'] ?? null;
        $amount = $input['amount'] ?? null;
        $currency = $input['currency'] ?? 'PHP';
        
        if (!$levelId || !$amount) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing level_id or amount']);
            exit;
        }
        
        // Validate level exists
        $level = $db->get('tier_plans', '*', ['id' => $levelId]);
        if (!$level) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid membership level']);
            exit;
        }
        
        // Validate amount matches level price
        $expectedAmount = floatval($level['price']);
        if (abs(floatval($amount) - $expectedAmount) > 0.01) {
            http_response_code(400);
            echo json_encode(['error' => 'Amount mismatch']);
            exit;
        }
        
        // Get PayPal credentials based on environment
        $paypalEnv = $_ENV['PAYPAL_ENVIRONMENT'] ?? 'sandbox';
        if ($paypalEnv === 'sandbox') {
            $clientId = $_ENV['PAYPAL_CLIENT_ID_SANDBOX'] ?? '';
            $clientSecret = $_ENV['PAYPAL_CLIENT_SECRET_SANDBOX'] ?? '';
            $baseUrl = 'https://api-m.sandbox.paypal.com';
        } else {
            $clientId = $_ENV['PAYPAL_CLIENT_ID'] ?? '';
            $clientSecret = $_ENV['PAYPAL_CLIENT_SECRET'] ?? '';
            $baseUrl = 'https://api-m.paypal.com';
        }
        
        if (!$clientId || !$clientSecret) {
            throw new \Exception('PayPal credentials not configured');
        }
        
        // Get access token
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseUrl . '/v1/oauth2/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_USERPWD, $clientId . ':' . $clientSecret);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
        
        $tokenResponse = curl_exec($ch);
        $tokenData = json_decode($tokenResponse, true);
        curl_close($ch);
        
        if (!isset($tokenData['access_token'])) {
            throw new \Exception('Failed to get PayPal access token');
        }
        
        $accessToken = $tokenData['access_token'];
        
        // Create PayPal order
        $orderData = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => 'REG-' . $levelId . '-' . time(),
                    'description' => $level['name'] . ' Membership',
                    'amount' => [
                        'currency_code' => $currency,
                        'value' => number_format($amount, 2, '.', '')
                    ]
                ]
            ],
            'application_context' => [
                'brand_name' => 'Ginto',
                'landing_page' => 'NO_PREFERENCE',
                'user_action' => 'PAY_NOW',
                'return_url' => ($_ENV['APP_URL'] ?? 'http://localhost') . '/register/paypal-success',
                'cancel_url' => ($_ENV['APP_URL'] ?? 'http://localhost') . '/register'
            ]
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseUrl . '/v2/checkout/orders');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($orderData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
            'PayPal-Request-Id: ' . uniqid('order-', true)
        ]);
        
        $orderResponse = curl_exec($ch);
        $order = json_decode($orderResponse, true);
        curl_close($ch);
        
        if (!isset($order['id'])) {
            error_log('PayPal order creation failed: ' . $orderResponse);
            throw new \Exception('Failed to create PayPal order');
        }
        
        error_log("PayPal order created: " . $order['id'] . " for level $levelId, amount $amount $currency");
        
        echo json_encode([
            'id' => $order['id'],
            'status' => $order['status']
        ]);
        
    } catch (\Throwable $e) {
        error_log('PayPal order creation error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create order', 'details' => $e->getMessage()]);
    }
    exit;
});

// PayPal Order Capture for Registration (one-time membership payment)
req($router, '/api/register/paypal-capture', function() use ($db) {
    header('Content-Type: application/json');
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }
    
    // Origin validation for CSRF protection (production security)
    $appUrl = $_ENV['APP_URL'] ?? 'https://ginto.app';
    $prodUrl = $_ENV['PRODUCTION_URL'] ?? 'https://ginto.ai';
    $allowedOrigins = [
        $appUrl, 
        rtrim($appUrl, '/'),
        $prodUrl,
        rtrim($prodUrl, '/'),
        'https://ginto.ai',
        'https://www.ginto.ai',
        'http://localhost', 
        'http://localhost:8000', 
        'http://127.0.0.1:8000'
    ];
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $referer = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_HOST);
    $appHost = parse_url($appUrl, PHP_URL_HOST);
    $prodHost = parse_url($prodUrl, PHP_URL_HOST);
    
    $originAllowed = in_array($origin, $allowedOrigins) || $origin === '';
    $refererAllowed = $referer === $appHost || $referer === $prodHost || $referer === 'ginto.ai' || $referer === 'www.ginto.ai' || $referer === 'localhost' || $referer === '127.0.0.1' || empty($referer);
    
    if (!$originAllowed && !$refererAllowed) {
        error_log("CSRF blocked: origin=$origin, referer=$referer, allowed_host=$appHost");
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden - invalid origin']);
        exit;
    }
    
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $orderId = $input['order_id'] ?? null;
        $levelId = $input['level_id'] ?? null;
        $registrationData = $input['registration_data'] ?? null;
        
        if (!$orderId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing order_id']);
            exit;
        }
        
        // Get PayPal credentials based on environment
        $paypalEnv = $_ENV['PAYPAL_ENVIRONMENT'] ?? 'sandbox';
        if ($paypalEnv === 'sandbox') {
            $clientId = $_ENV['PAYPAL_CLIENT_ID_SANDBOX'] ?? '';
            $clientSecret = $_ENV['PAYPAL_CLIENT_SECRET_SANDBOX'] ?? '';
            $baseUrl = 'https://api-m.sandbox.paypal.com';
        } else {
            $clientId = $_ENV['PAYPAL_CLIENT_ID'] ?? '';
            $clientSecret = $_ENV['PAYPAL_CLIENT_SECRET'] ?? '';
            $baseUrl = 'https://api-m.paypal.com';
        }
        
        if (!$clientId || !$clientSecret) {
            throw new \Exception('PayPal credentials not configured');
        }
        
        // Get access token
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseUrl . '/v1/oauth2/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_USERPWD, $clientId . ':' . $clientSecret);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
        
        $tokenResponse = curl_exec($ch);
        $tokenData = json_decode($tokenResponse, true);
        curl_close($ch);
        
        if (!isset($tokenData['access_token'])) {
            throw new \Exception('Failed to get PayPal access token');
        }
        
        $accessToken = $tokenData['access_token'];
        
        // Capture the order
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseUrl . '/v2/checkout/orders/' . $orderId . '/capture');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken
        ]);
        
        $captureResponse = curl_exec($ch);
        $capture = json_decode($captureResponse, true);
        curl_close($ch);
        
        // Handle different PayPal statuses
        $paypalStatus = $capture['status'] ?? 'UNKNOWN';
        $captureDetails = $capture['purchase_units'][0]['payments']['captures'][0] ?? [];
        $paymentId = $captureDetails['id'] ?? $orderId;
        $amount = $captureDetails['amount']['value'] ?? '0.00';
        $currency = $captureDetails['amount']['currency_code'] ?? 'PHP';
        
        // Map PayPal status to our internal status
        $internalStatus = 'pending';
        $statusMessage = '';
        
        switch ($paypalStatus) {
            case 'COMPLETED':
                $internalStatus = 'completed';
                $statusMessage = 'Payment completed successfully';
                break;
            case 'PENDING':
            case 'APPROVED':
                $internalStatus = 'pending';
                $statusMessage = 'Payment is pending review by PayPal. This may take 24-48 hours.';
                break;
            case 'VOIDED':
            case 'DECLINED':
                $internalStatus = 'failed';
                $statusMessage = 'Payment was declined or voided';
                error_log('PayPal payment failed: ' . $captureResponse);
                throw new \Exception('Payment was declined: ' . ($capture['message'] ?? $paypalStatus));
            default:
                if (!isset($capture['status'])) {
                    error_log('PayPal capture failed - no status: ' . $captureResponse);
                    throw new \Exception('Payment capture failed: ' . ($capture['message'] ?? 'Unknown error'));
                }
                $internalStatus = 'pending';
                $statusMessage = 'Payment status: ' . $paypalStatus;
        }
        
        error_log("PayPal payment captured: $paymentId for $amount $currency - Status: $paypalStatus -> $internalStatus");
        
        // Store payment in session for registration completion
        $_SESSION['paypal_payment'] = [
            'order_id' => $orderId,
            'payment_id' => $paymentId,
            'amount' => $amount,
            'currency' => $currency,
            'level_id' => $levelId,
            'captured_at' => date('Y-m-d H:i:s'),
            'status' => $internalStatus,
            'paypal_status' => $paypalStatus
        ];
        
        echo json_encode([
            'success' => true,
            'payment_id' => $paymentId,
            'order_id' => $orderId,
            'amount' => $amount,
            'currency' => $currency,
            'status' => $internalStatus,
            'paypal_status' => $paypalStatus,
            'message' => $statusMessage
        ]);
        
    } catch (\Throwable $e) {
        error_log('PayPal capture error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to capture payment', 'details' => $e->getMessage()]);
    }
    exit;
});

// Course Detail Page
req($router, '/courses/{slug}', function($slug) use ($db) {
    $isLoggedIn = !empty($_SESSION['user_id']);
    $isAdmin = \Ginto\Controllers\UserController::isAdmin();
    $username = $_SESSION['username'] ?? null;
    $userId = $_SESSION['user_id'] ?? (int)0;
    $userFullname = $_SESSION['fullname'] ?? $_SESSION['username'] ?? null;
    
    $courseController = new \Ginto\Controllers\CourseController($db);
    $course = $courseController->getCourseBySlug($slug);
    
    if (!$course) {
        http_response_code(404);
        echo "Course not found";
        return;
    }
    
    $lessons = $courseController->getAccessibleLessons($userId, $course['id']);
    $userPlan = $isLoggedIn ? $courseController->getUserPlanName($userId) : 'free';
    $enrollment = $isLoggedIn ? $courseController->getUserEnrollment($userId, $course['id']) : null;
    $progressDetails = $isLoggedIn ? $courseController->getCourseProgressDetails($userId, $course['id']) : [];
    $stats = $courseController->getCourseStats($course['id']);
    
    \Ginto\Core\View::view('courses/detail', [
        'title' => $course['title'] . ' | Ginto Courses',
        'isLoggedIn' => $isLoggedIn,
        'isAdmin' => $isAdmin,
        'username' => $username,
        'userId' => $userId,
        'userFullname' => $userFullname,
        'course' => $course,
        'lessons' => $lessons,
        'userPlan' => $userPlan,
        'enrollment' => $enrollment,
        'progressDetails' => $progressDetails,
        'stats' => $stats,
    ]);
});

// Lesson Page
req($router, '/courses/{courseSlug}/lesson/{lessonSlug}', function($courseSlug, $lessonSlug) use ($db) {
    $isLoggedIn = !empty($_SESSION['user_id']);
    $isAdmin = \Ginto\Controllers\UserController::isAdmin();
    $username = $_SESSION['username'] ?? null;
    $userId = $_SESSION['user_id'] ?? (int)0;
    $userFullname = $_SESSION['fullname'] ?? $_SESSION['username'] ?? null;
    
    $courseController = new \Ginto\Controllers\CourseController($db);
    $course = $courseController->getCourseBySlug($courseSlug);
    
    if (!$course) {
        http_response_code(404);
        echo "Course not found";
        return;
    }
    
    $lesson = $courseController->getLessonBySlug($course['id'], $lessonSlug);
    if (!$lesson) {
        http_response_code(404);
        echo "Lesson not found";
        return;
    }
    
    $userPlan = $isLoggedIn ? $courseController->getUserPlanName($userId) : 'free';
    
    // Check access
    if (!$courseController->canAccessLesson($userId, $lesson, $userPlan)) {
        // Redirect to upgrade page or show access denied
        \Ginto\Core\View::view('courses/upgrade', [
            'title' => 'Upgrade Required | Ginto Courses',
            'isLoggedIn' => $isLoggedIn,
            'course' => $course,
            'lesson' => $lesson,
            'userPlan' => $userPlan,
            'plans' => $courseController->getSubscriptionPlans('courses'),
        ]);
        return;
    }
    
    // Enroll user if logged in
    if ($isLoggedIn) {
        $courseController->enrollUser($userId, $course['id']);
        $courseController->updateLessonProgress($userId, $lesson['id'], $course['id'], 'in_progress');
        $courseController->updateStudyStreak($userId);
    }
    
    $allLessons = $courseController->getAccessibleLessons($userId, $course['id']);
    $nextLesson = $courseController->getNextLesson($course['id'], $lesson['lesson_order']);
    $prevLesson = $courseController->getPreviousLesson($course['id'], $lesson['lesson_order']);
    
    \Ginto\Core\View::view('courses/lesson', [
        'title' => $lesson['title'] . ' | ' . $course['title'],
        'isLoggedIn' => $isLoggedIn,
        'isAdmin' => $isAdmin,
        'username' => $username,
        'userId' => $userId,
        'userFullname' => $userFullname,
        'course' => $course,
        'lesson' => $lesson,
        'allLessons' => $allLessons,
        'nextLesson' => $nextLesson,
        'prevLesson' => $prevLesson,
        'userPlan' => $userPlan,
    ]);
});

// Mark lesson complete API
req($router, '/api/courses/complete-lesson', function() use ($db) {
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $lessonId = $data['lesson_id'] ?? 0;
    $courseId = $data['course_id'] ?? 0;
    
    $courseController = new \Ginto\Controllers\CourseController($db);
    $result = $courseController->updateLessonProgress($_SESSION['user_id'], $lessonId, $courseId, 'completed');
    
    echo json_encode(['success' => $result]);
});

// ============================================================================
// Masterclass Routes - In-depth technical training
// ============================================================================

// Masterclass Listing Page
req($router, '/masterclass', function() use ($db) {
    $isLoggedIn = !empty($_SESSION['user_id']);
    $isAdmin = \Ginto\Controllers\UserController::isAdmin();
    $username = $_SESSION['username'] ?? null;
    $userId = $_SESSION['user_id'] ?? null;
    $userFullname = $_SESSION['fullname'] ?? $_SESSION['username'] ?? null;
    
    $masterclassController = new \Ginto\Controllers\MasterclassController($db);
    $masterclasses = $masterclassController->getAllMasterclasses();
    $categories = $masterclassController->getCategories();
    $userPlan = $isLoggedIn ? $masterclassController->getUserPlanName($userId) : 'free';
    
    // Handle category filter
    $categoryFilter = $_GET['category'] ?? null;
    if ($categoryFilter) {
        $masterclasses = $masterclassController->getMasterclassesByCategory($categoryFilter);
    }
    
    // Handle user learning status filter
    $statusFilter = $_GET['status'] ?? null;
    $enrolledMasterclasses = [];
    if ($isLoggedIn && $statusFilter) {
        $enrolledMasterclasses = $masterclassController->getUserEnrolledMasterclasses($userId, $statusFilter);
    }
    
    \Ginto\Core\View::view('masterclass/masterclass', [
        'title' => 'Masterclasses | Ginto AI',
        'isLoggedIn' => $isLoggedIn,
        'isAdmin' => $isAdmin,
        'username' => $username,
        'userId' => $userId,
        'userFullname' => $userFullname,
        'masterclasses' => $masterclasses,
        'categories' => $categories,
        'userPlan' => $userPlan,
        'categoryFilter' => $categoryFilter,
        'statusFilter' => $statusFilter,
        'enrolledMasterclasses' => $enrolledMasterclasses,
    ]);
});

// Masterclass Pricing Page (must be before /masterclass/{slug} to avoid slug matching "pricing")
req($router, '/masterclass/pricing', function() use ($db) {
    $isLoggedIn = !empty($_SESSION['user_id']);
    $userId = $_SESSION['user_id'] ?? 0;
    $isAdmin = \Ginto\Controllers\UserController::isAdmin();
    $username = $_SESSION['username'] ?? null;
    $userFullname = $_SESSION['fullname'] ?? $_SESSION['username'] ?? null;
    
    $masterclassController = new \Ginto\Controllers\MasterclassController($db);
    $plans = $masterclassController->getSubscriptionPlans();
    $currentPlan = $isLoggedIn ? $masterclassController->getUserPlanName($userId) : 'free';
    
    \Ginto\Core\View::view('masterclass/pricing', [
        'title' => 'Pricing | Ginto Masterclasses',
        'isLoggedIn' => $isLoggedIn,
        'isAdmin' => $isAdmin,
        'username' => $username,
        'userId' => $userId,
        'userFullname' => $userFullname,
        'plans' => $plans,
        'currentPlan' => $currentPlan,
    ]);
});

// Masterclass Detail Page
req($router, '/masterclass/{slug}', function($slug) use ($db) {
    $isLoggedIn = !empty($_SESSION['user_id']);
    $isAdmin = \Ginto\Controllers\UserController::isAdmin();
    $username = $_SESSION['username'] ?? null;
    $userId = $_SESSION['user_id'] ?? (int)0;
    $userFullname = $_SESSION['fullname'] ?? $_SESSION['username'] ?? null;
    
    $masterclassController = new \Ginto\Controllers\MasterclassController($db);
    $masterclass = $masterclassController->getMasterclassBySlug($slug);
    
    if (!$masterclass) {
        http_response_code(404);
        echo "Masterclass not found";
        return;
    }
    
    $lessons = $masterclassController->getAccessibleLessons($userId, $masterclass['id']);
    $userPlan = $isLoggedIn ? $masterclassController->getUserPlanName($userId) : 'free';
    $enrollment = $isLoggedIn ? $masterclassController->getUserEnrollment($userId, $masterclass['id']) : null;
    $progressDetails = $isLoggedIn ? $masterclassController->getMasterclassProgressDetails($userId, $masterclass['id']) : [];
    $stats = $masterclassController->getMasterclassStats($masterclass['id']);
    
    \Ginto\Core\View::view('masterclass/detail', [
        'title' => $masterclass['title'] . ' | Ginto Masterclasses',
        'isLoggedIn' => $isLoggedIn,
        'isAdmin' => $isAdmin,
        'username' => $username,
        'userId' => $userId,
        'userFullname' => $userFullname,
        'masterclass' => $masterclass,
        'lessons' => $lessons,
        'userPlan' => $userPlan,
        'enrollment' => $enrollment,
        'progressDetails' => $progressDetails,
        'stats' => $stats,
    ]);
});

// Masterclass Lesson Page
req($router, '/masterclass/{masterclassSlug}/lesson/{lessonSlug}', function($masterclassSlug, $lessonSlug) use ($db) {
    $isLoggedIn = !empty($_SESSION['user_id']);
    $isAdmin = \Ginto\Controllers\UserController::isAdmin();
    $username = $_SESSION['username'] ?? null;
    $userId = $_SESSION['user_id'] ?? (int)0;
    $userFullname = $_SESSION['fullname'] ?? $_SESSION['username'] ?? null;
    
    $masterclassController = new \Ginto\Controllers\MasterclassController($db);
    $masterclass = $masterclassController->getMasterclassBySlug($masterclassSlug);
    
    if (!$masterclass) {
        http_response_code(404);
        echo "Masterclass not found";
        return;
    }
    
    $lesson = $masterclassController->getLessonBySlug($masterclass['id'], $lessonSlug);
    if (!$lesson) {
        http_response_code(404);
        echo "Lesson not found";
        return;
    }
    
    $userPlan = $isLoggedIn ? $masterclassController->getUserPlanName($userId) : 'free';
    
    // Check access
    if (!$masterclassController->canAccessLesson($userId, $lesson, $userPlan)) {
        // Redirect to upgrade page
        \Ginto\Core\View::view('masterclass/upgrade', [
            'title' => 'Upgrade Required | Ginto Masterclasses',
            'isLoggedIn' => $isLoggedIn,
            'masterclass' => $masterclass,
            'lesson' => $lesson,
            'userPlan' => $userPlan,
        ]);
        return;
    }
    
    // Enroll user if logged in
    if ($isLoggedIn) {
        $masterclassController->enrollUser($userId, $masterclass['id']);
        $masterclassController->updateLessonProgress($userId, $lesson['id'], $masterclass['id'], 'in_progress');
    }
    
    $allLessons = $masterclassController->getAccessibleLessons($userId, $masterclass['id']);
    $nextLesson = $masterclassController->getNextLesson($masterclass['id'], $lesson['lesson_order']);
    $prevLesson = $masterclassController->getPreviousLesson($masterclass['id'], $lesson['lesson_order']);
    
    // Calculate current lesson index (0-based)
    $currentIndex = 0;
    foreach ($allLessons as $i => $l) {
        if ($l['id'] == $lesson['id']) {
            $currentIndex = $i;
            break;
        }
    }
    
    \Ginto\Core\View::view('masterclass/lesson', [
        'title' => $lesson['title'] . ' | ' . $masterclass['title'],
        'isLoggedIn' => $isLoggedIn,
        'isAdmin' => $isAdmin,
        'username' => $username,
        'userId' => $userId,
        'userFullname' => $userFullname,
        'masterclass' => $masterclass,
        'lesson' => $lesson,
        'allLessons' => $allLessons,
        'currentIndex' => $currentIndex,
        'nextLesson' => $nextLesson,
        'prevLesson' => $prevLesson,
        'userPlan' => $userPlan,
    ]);
});

// Mark masterclass lesson complete API
req($router, '/api/masterclass/complete-lesson', function() use ($db) {
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $lessonId = $data['lesson_id'] ?? 0;
    $masterclassId = $data['masterclass_id'] ?? 0;
    
    $masterclassController = new \Ginto\Controllers\MasterclassController($db);
    $result = $masterclassController->updateLessonProgress($_SESSION['user_id'], $lessonId, $masterclassId, 'completed');
    
    echo json_encode(['success' => $result]);
});

// ============================================================================
// Web Search Test Route - Isolated test for GPT-OSS browser_search
// ============================================================================
req($router, '/websearch', function() {
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
            if ($parsedown && method_exists($parsedown, 'setSafeMode')) {
                $parsedown->setSafeMode(true);
            }
            
            // If content is empty, use a summary message pointing to reasoning
            if ($contentWasEmpty && !empty($accumulatedReasoning)) {
                $finalContent = "*The model's analysis is shown in the reasoning section above. The response exceeded the token limit before generating a final summary.*";
            }
            
            $html = $parsedown ? $parsedown->text($finalContent) : nl2br(htmlspecialchars($finalContent));
            
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
        try { $parsedown = new \Parsedown(); $parsedown->setSafeMode(true); } catch (\Throwable $_) {}
    }

    $html = $parsedown ? $parsedown->text($content) : '<pre>' . htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>';
    echo "data: " . json_encode(['html' => $html], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
    flush();
}

// MCP tool call endpoint - lightweight session-based tool execution for chat UI (admin only)
req($router, '/mcp/call', function() {
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
req($router, '/mcp/probe', function() {
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
req($router, '/debug/llm', function() {
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
req($router, '/api/models', function() {
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

    $providers = \App\Core\LLM\LLMProviderFactory::getAvailableProviders();
    $result = [
        'success' => true,
        'current_provider' => $_SESSION['llm_provider_name'] ?? (getenv('LLM_PROVIDER') ?: 'groq'),
        'current_model' => $_SESSION['llm_model'] ?? (getenv('LLM_MODEL') ?: null),
        'providers' => [],
    ];

    foreach ($providers as $providerName) {
        try {
            $provider = \App\Core\LLM\LLMProviderFactory::create($providerName);
            if ($provider->isConfigured()) {
                $result['providers'][$providerName] = [
                    'configured' => true,
                    'default_model' => $provider->getDefaultModel(),
                    'models' => $provider->getModels(),
                ];
            }
        } catch (\Throwable $e) {
            // Skip unconfigured providers
        }
    }

    echo json_encode($result, JSON_PRETTY_PRINT);
    exit;
});

// Admin API: Set active provider and model for the session
req($router, '/api/models/set', function() {
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

    // Validate provider exists
    try {
        $providerInstance = \App\Core\LLM\LLMProviderFactory::create($provider);
        if (!$providerInstance->isConfigured()) {
            echo json_encode(['success' => false, 'error' => "Provider '$provider' is not configured"]);
            exit;
        }
    } catch (\Throwable $e) {
        echo json_encode(['success' => false, 'error' => "Invalid provider: " . $e->getMessage()]);
        exit;
    }

    // Store in session
    $_SESSION['llm_provider_name'] = $provider;
    $_SESSION['llm_model'] = $model;

    echo json_encode([
        'success' => true,
        'provider' => $provider,
        'model' => $model,
        'message' => "Switched to $provider / $model",
    ]);
    exit;
});

// Standard MCP chat endpoint: calls the local MCP server's `chat_completion` tool (admin only).
// Returns JSON: { success: bool, reply: string, raw: mixed }
req($router, '/mcp/chat', function() {
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
req($router, '/mcp/invoke', function() {
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
req($router, '/mcp/discover', function() {
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
req($router, '/mcp/unified', function() {
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
req($router, '/audio/tts', function() {
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
req($router, '/audio/stt', function() {
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
req($router, '/playground', function() use ($db) {
    // Require login
    if (empty($_SESSION['user_id'])) {
        header('Location: /login?redirect=/playground');
        exit;
    }
    
    $pageTitle = 'Playground - Ginto CMS';

    // Fetch recent non-playground application activity logs for dashboard
    $recentActivity = [];
    try {
        $rows = $db->select('activity_logs', ['id','user_id','action','model_type','model_id','description','created_at'], [
            // exclude playground-specific entries here (they belong on /playground/logs)
            'AND' => [
                'action[!~]' => 'playground.%',
                'model_type[!]' => 'playground.editor'
            ],
            'ORDER' => ['created_at' => 'DESC'],
            'LIMIT' => 6
        ]) ?: [];

        foreach ($rows as $r) {
            // Try to get a human-friendly user name
            $userLabel = $r['user_id'] ? (string)$r['user_id'] : '(system)';
            try {
                if (!empty($r['user_id'])) {
                    $u = $db->get('users', ['firstname','lastname','fullname','username'], ['id' => $r['user_id']]);
                    if ($u) {
                        $userLabel = trim(($u['fullname'] ?? ($u['firstname'] . ' ' . $u['lastname'])) ?: ($u['username'] ?? $userLabel));
                    }
                }
            } catch (\Throwable $_) {}

            $desc = $r['description'] ?? '';
            // short message: try to use action or first line of description
            $message = $r['action'] ?: strtok($desc, "\n");

            $recentActivity[] = [
                'id' => $r['id'],
                'user' => $userLabel,
                'action' => $r['action'],
                'message' => $message,
                'details' => $desc,
                'created_at' => $r['created_at']
            ];
        }
    } catch (\Throwable $_) {
        $recentActivity = [];
    }

    include ROOT_PATH . '/src/Views/playground/index.php';
    exit;
});

// NOTE: The playground catch-all route was intentionally moved below the
// more-specific /playground/* routes (e.g. /playground/logs and editor endpoints)
// so it doesn't shadow those routes.

// Playground admin-style logs (exposed under /playground/logs) — admin-only
req($router, '/playground/logs', function() use ($db) {
    // require login
    if (empty($_SESSION['user_id'])) {
        header('Location: /login?redirect=/playground/logs');
        exit;
    }

    // require admin role
    $user = null;
    try { $user = $db->get('users', ['role_id'], ['id' => $_SESSION['user_id']]); } catch (\Throwable $_) { $user = null; }
    if (!$user || !in_array($user['role_id'] ?? null, [1,2])) {
        header('Location: /playground'); exit;
    }

    $page = (int)($_GET['page'] ?? 1);
    $perPage = 50;
    $offset = ($page - 1) * $perPage;

    // optional search query
    $q = trim((string)($_GET['q'] ?? ''));

    // Filter for playground-related events only
    $where = [
        'OR' => [
            'action[~]' => 'playground.%',
            'model_type' => 'playground.editor'
        ],
    ];

    // if search query provided, add more filters
    if ($q !== '') {
        $where['AND'] = [
            'OR' => [
                'action[~]' => $q,
                'description[~]' => $q,
                'model_type[~]' => $q
            ]
        ];
    }

    $where['ORDER'] = ['created_at' => 'DESC'];
    $where['LIMIT'] = [$offset, $perPage];

    $logs = [];
    try {
        // Select core fields and join users so we can show usernames instead of raw user_id values
        $cols = [
            'activity_logs.id',
            'activity_logs.user_id',
            'users.username(user_name)',
            'activity_logs.action',
            'activity_logs.model_type',
            'activity_logs.model_id',
            'activity_logs.description',
            'activity_logs.created_at'
        ];

        $logs = $db->select('activity_logs', [
            '[>]users' => ['user_id' => 'id']
        ], $cols, $where) ?: [];

        // Normalize each row and add a short "summary" for nicer UI rendering
        foreach ($logs as &$r) {
            // prefer human username where available
            $r['username'] = $r['user_name'] ?? ($r['user_id'] ? (string)$r['user_id'] : '(system)');

            // create a short summary from description
            $desc = (string)($r['description'] ?? '');
            $summary = '';
            // If description looks like JSON, try to decode and extract meaningful fields
            $trim = ltrim($desc);
            if ($trim !== '' && ($trim[0] === '{' || $trim[0] === '[')) {
                $json = json_decode($desc, true);
                if (is_array($json)) {
                    // common keys we care about
                    $keys = ['error', 'message', 'msg', 'file', 'path', 'input', 'prompt', 'reason'];
                    foreach ($keys as $k) {
                        if (isset($json[$k]) && is_scalar($json[$k]) && trim((string)$json[$k]) !== '') { $summary = (string)$json[$k]; break; }
                    }
                    // if still empty, flatten small arrays
                    if ($summary === '') {
                        $flat = [];
                        foreach ($json as $k => $v) {
                            if (is_scalar($v)) $flat[] = $k . ': ' . mb_strimwidth((string)$v, 0, 50, '…');
                        }
                        $summary = implode(' | ', array_slice($flat, 0, 3));
                    }
                }
            }

            if ($summary === '') {
                // default: use first line or truncated description
                $first = strtok($desc, "\n");
                $summary = mb_strimwidth((string)$first, 0, 140, '…');
            }

            $r['summary'] = $summary;
        }
        unset($r);
    } catch (\Throwable $_) { $logs = []; }

    try { $total = (int)$db->count('activity_logs', [ 'OR' => ['action[~]' => 'playground.%', 'model_type' => 'playground.editor'] ]) ?: 0; } catch (\Throwable $_) { $total = count($logs); }
    $totalPages = max(1, ceil($total / $perPage));
    $pagination = ['current' => $page, 'total' => $totalPages];
    $pageTitle = 'Playground Logs';
    include ROOT_PATH . '/src/Views/playground/logs/index.php';
    exit;
});

// Admin-only helper to create a sample playground log entry (POST)
req($router, '/playground/logs/create-sample', function() use ($db) {
    if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }
    // admin only
    try { $user = $db->get('users', ['role_id'], ['id' => $_SESSION['user_id']]); } catch (\Throwable $_) { $user = null; }
    if (!$user || !in_array($user['role_id'] ?? null, [1,2])) { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }

    // CSRF
    $token = $_POST['csrf_token'] ?? ($_GET['csrf_token'] ?? '');
    if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) { http_response_code(403); echo json_encode(['error' => 'Invalid CSRF token']); exit; }

    $msg = $_POST['message'] ?? 'Sample playground log — developer action';
    try {
        $db->insert('activity_logs', [
            'user_id' => $_SESSION['user_id'],
            'action' => 'playground.sample',
            'model_type' => 'playground.editor',
            'model_id' => 0,
            'description' => $msg . "\n\nGenerated at: " . date('c'),
            'created_at' => date('Y-m-d H:i:s')
        ]);
        echo json_encode(['success' => true]);
    } catch (\Throwable $e) {
        error_log('[playground/logs/create-sample] ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create sample entry']);
    }
    exit;
}, ['POST']);

// Playground editor - start working environment install (background)
req($router, '/playground/editor/install_env', function() use ($db) {
    header('Content-Type: application/json');
    if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit; }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'error'=>'Method not allowed']); exit; }

    // CSRF
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid CSRF token']); exit; }
    try {
        $editorRoot = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($db ?? null, $_SESSION ?? null);
        $sandboxId = basename($editorRoot);
        if (empty($sandboxId)) { echo json_encode(['success'=>false,'error'=>'No sandbox available']); exit; }
        $riskless = false;
        if (isset($_POST['riskless']) && ($_POST['riskless'] === '1' || $_POST['riskless'] === 'true')) $riskless = true;
        $started = \Ginto\Helpers\SandboxManager::ensureSandboxRunning($sandboxId, $editorRoot, 6, $riskless);
        $storagePath = defined('STORAGE_PATH') ? STORAGE_PATH : dirname(dirname(dirname(__DIR__))) . '/storage';
        $logFile = $storagePath . '/backups/install_' . preg_replace('/[^a-zA-Z0-9_\-]/','_', $sandboxId) . '.log';
        echo json_encode(['success'=>true,'started'=>boolval($started),'log'=>$logFile,'riskless'=>boolval($riskless)]);
        exit;
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['success'=>false,'error'=>'install_failed','message'=>$e->getMessage()]);
        exit;
    }
}, ['POST']);

// Playground editor - poll working environment install status
req($router, '/playground/editor/install_status', function() use ($db) {
    header('Content-Type: application/json');
    if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit; }
    try {
        $editorRoot = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($db ?? null, $_SESSION ?? null);
        $sandboxId = basename($editorRoot);
        if (empty($sandboxId)) { echo json_encode(['success'=>false,'error'=>'No sandbox available']); exit; }
        $exists = false;
        try { $exists = \Ginto\Helpers\SandboxManager::sandboxExists($sandboxId); } catch (\Throwable $_) { $exists = false; }
        echo json_encode(['success'=>true,'sandbox_exists'=>$exists,'sandbox_id'=>$sandboxId,'editor_root'=>$editorRoot]);
        exit;
    } catch (\Throwable $e) {
        http_response_code(500); echo json_encode(['success'=>false,'error'=>'status_failed']); exit; }
}, ['GET']);

// Playground sub-routes (catch-all for playground tools)
req($router, '/playground/{tool}', function($tool = 'index') {
    // Require login
    if (empty($_SESSION['user_id'])) {
        header('Location: /login?redirect=/playground/' . urlencode($tool));
        exit;
    }
    
    $pageTitle = ucfirst($tool) . ' - Playground';
    $toolView = ROOT_PATH . '/src/Views/playground/' . basename($tool) . '.php';
    
    // Check if specific tool view exists, otherwise show main playground
    if (file_exists($toolView)) {
        include $toolView;
    } else {
        // Show main playground with tool context
        $currentTool = $tool;
        include ROOT_PATH . '/src/Views/playground/index.php';
    }
    exit;
});

req($router, '/playground/logs/{id}', function($id = null) use ($db) {
    if (empty($_SESSION['user_id'])) { header('Location: /login?redirect=/playground/logs'); exit; }
    $user = null; try { $user = $db->get('users', ['role_id'], ['id' => $_SESSION['user_id']]); } catch (\Throwable $_) { $user = null; }
    if (!$user || !in_array($user['role_id'] ?? null, [1,2])) { header('Location: /playground'); exit; }

    if (!$id) { http_response_code(404); echo 'Not found'; exit; }
    // join to users so we can display username
    $log = $db->get('activity_logs', [
        '[>]users' => ['user_id' => 'id']
    ], [
        'activity_logs.id', 'activity_logs.user_id', 'users.username(user_name)', 'activity_logs.action', 'activity_logs.model_type', 'activity_logs.model_id', 'activity_logs.description', 'activity_logs.created_at'
    ], ['activity_logs.id' => (int)$id]);

    if ($log) {
        $log['username'] = $log['user_name'] ?? ($log['user_id'] ? (string)$log['user_id'] : '(system)');

        // Try to detect JSON descriptions for nicer display
        $desc = (string)($log['description'] ?? '');
        $trim = ltrim($desc);
        if ($trim !== '' && ($trim[0] === '{' || $trim[0] === '[')) {
            $json = json_decode($desc, true);
            if (is_array($json)) {
                // store pretty JSON for the view
                $log['description_json'] = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            }
        }
    }
    if (!$log) { http_response_code(404); echo 'Log not found'; exit; }

    $pageTitle = 'Playground Log #' . $log['id'];
    include ROOT_PATH . '/src/Views/playground/logs/show.php';
    exit;
});

// Playground editor save endpoint
req($router, '/playground/editor/save', function() use ($db) {
    // Wrap handler in try/catch so we can log internal errors and avoid leaking details
    try {
    // Ensure a session sandbox exists for visitors (allow editing for visitor sandboxes)
    if (empty($_SESSION['user_id'])) {
        if (empty($_SESSION['sandbox_id'])) {
            try {
                $created = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($db ?? null, $_SESSION ?? null);
                if (!empty($created)) {
                    $_SESSION['sandbox_id'] = basename($created);
                    // Ensure a CSRF token exists for the visitor session so editor POSTs succeed
                    if (empty($_SESSION['csrf_token'])) {
                        try { $_SESSION['csrf_token'] = bin2hex(random_bytes(16)); } catch (\Throwable $_) { $_SESSION['csrf_token'] = md5(uniqid('', true)); }
                    }
                }
            } catch (\Throwable $_) {
                // If sandbox creation fails, fall through and return unauthorized
            }
        }
        if (empty($_SESSION['sandbox_id'])) {
            http_response_code(401);
            echo 'Unauthorized';
            exit;
        }
    }
    
    // Only allow POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo 'Method not allowed';
        exit;
    }
    
    // CSRF validation
    $token = $_POST['csrf_token'] ?? '';
    if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) {
        http_response_code(403);
        echo 'Invalid CSRF token';
        exit;
    }
    
    $enc = $_POST['file'] ?? '';
    $content = $_POST['content'] ?? '';
    
    if (!$enc) {
        http_response_code(400);
        echo 'Missing file token';
        exit;
    }
    
    $decoded = base64_decode(rawurldecode($enc));
    if (!$decoded) {
        http_response_code(400);
        echo 'Invalid file token';
        exit;
    }
    
    $safePath = str_replace(['..', '\\'], ['', '/'], $decoded);

    // Helper: normalize a path (works even if the target doesn't exist)
    $normalizePath = function($p) {
        $p = str_replace('\\', '/', $p);
        $parts = array_filter(explode('/', $p), function($x){ return $x !== ''; });
        $stack = [];
        foreach ($parts as $part) {
            if ($part === '.') continue;
            if ($part === '..') { array_pop($stack); continue; }
            $stack[] = $part;
        }
        $out = implode('/', $stack);
        if ($p !== '' && $p[0] === '/') $out = '/' . $out;
        return $out;
    };
    // determine editor root depending on user/admin
    $editorRoot = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($db ?? null, $_SESSION ?? null);
    $fullPath = rtrim($editorRoot, '/') . '/' . ltrim($safePath, '/');

    // Verify path is within editor root
    // Verify the requested path stays within the editor root using path normalization.
    $realRoot = realpath($editorRoot) ?: rtrim($editorRoot, '/');
    $normalizedRoot = rtrim($normalizePath($realRoot), '/');
    $normalizedFull = $normalizePath($fullPath);

    // If the normalized full path doesn't start with the normalized root, deny access.
    if (strpos($normalizedFull, $normalizedRoot) !== 0) {
        http_response_code(403);
        echo 'Access denied';
        exit;
    }
    
    // Determine admin vs sandbox
    $isAdminSession = (realpath($editorRoot) === (realpath(ROOT_PATH) ?: ROOT_PATH));

    // Disallow certain extensions in sandboxes
    $forbiddenExt = ['php','phtml','pl','py','sh','exe','jar','bat','cmd','run','cgi','asp','aspx'];
    $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
    if (!$isAdminSession && in_array($ext, $forbiddenExt)) {
        http_response_code(403);
        echo 'Forbidden file type';
        exit;
    }

    // Quota enforcement (non-admins)
    if (!$isAdminSession) {
        $currentUsed = 0;
        try {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($editorRoot, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) { $currentUsed += $f->getSize(); }
        } catch (\Throwable $_) { $currentUsed = 0; }

        $oldSize = file_exists($fullPath) ? filesize($fullPath) : 0;
        $newSize = strlen($content);
        $newUsed = $currentUsed - $oldSize + $newSize;

        $sandboxId = basename($editorRoot);
        $quota = 104857600;
        if ($db) {
            $row = $db->get('client_sandboxes', ['quota_bytes'], ['sandbox_id' => $sandboxId]);
            if (!empty($row['quota_bytes'])) $quota = (int)$row['quota_bytes'];
        }
        if ($newUsed > $quota) {
            http_response_code(413);
            echo 'Quota exceeded';
            exit;
        }
    }

    // Ensure parent directory exists (allow new files to be created in sandbox)
    $parentDir = dirname($fullPath);
    if (!is_dir($parentDir)) @mkdir($parentDir, 0755, true);

    // Save file — handle errors and provide detailed internal logging on failure
    $saved = @file_put_contents($fullPath, $content);
    if ($saved !== false) {
        if (!$isAdminSession && $db) {
            // update used_bytes
            $sizeAfter = 0;
            try { $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($editorRoot, \FilesystemIterator::SKIP_DOTS)); foreach ($it as $f) { $sizeAfter += $f->getSize(); } } catch (\Throwable $_) { $sizeAfter = 0; }
            $db->update('client_sandboxes', ['used_bytes' => $sizeAfter, 'updated_at' => date('Y-m-d H:i:s')], ['sandbox_id' => $sandboxId]);
        }
        echo 'OK';
    } else {
        // Log error details for admins
        $err = error_get_last();
        $payload = json_encode([
            'event' => 'playground_save_failed',
            'user_id' => $_SESSION['user_id'] ?? null,
            'editor_root' => $editorRoot,
            'full_path' => $fullPath,
            'normalized_root' => $normalizedRoot,
            'normalized_full' => $normalizedFull,
            'content_length' => strlen($content),
            'php_error' => $err
        ]);
        error_log('[playground/save] Save failed: ' . $payload);
        try {
            if ($db) $db->insert('activity_logs', [
                'user_id' => $_SESSION['user_id'] ?? null,
                'action' => 'playground.save_failed',
                'model_type' => 'playground.editor',
                'description' => $payload,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (\Throwable $_) {}

        http_response_code(500);
        echo 'Failed to save file';
    }
    exit;
    } catch (\Throwable $e) {
        // Unexpected exception — log and return generic error
        $payload = '[' . date('c') . '] playground/save exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
        error_log($payload . "\n" . $e->getTraceAsString());
        try {
            if ($db) $db->insert('activity_logs', [
                'user_id' => $_SESSION['user_id'] ?? null,
                'action' => 'playground.save_exception',
                'model_type' => 'playground.editor',
                'description' => $payload,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (\Throwable $_) {}
        http_response_code(500);
        echo 'Internal server error';
        exit;
    }
    exit;
}, ['POST']);

// Playground editor - toggle admin sandbox mode (admins can opt into per-user sandbox)
req($router, '/playground/editor/toggle_sandbox', function() use ($db) {
    if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit; }
    // Only admins can toggle sandbox mode
    $isAdmin = (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin') || (!empty($_SESSION['is_admin']));
    // DB fallback: check users/roles table when session flags are missing
    if (!$isAdmin && $db && !empty($_SESSION['user_id'])) {
        try {
            $ur = $db->get('users', ['role_id'], ['id' => $_SESSION['user_id']]);
            if (!empty($ur) && !empty($ur['role_id'])) {
                $rr = $db->get('roles', ['name', 'display_name'], ['id' => $ur['role_id']]);
                $rname = strtolower((string)($rr['display_name'] ?? $rr['name'] ?? ''));
                if (in_array($rname, ['administrator', 'admin'], true)) $isAdmin = true;
            }
        } catch (\Throwable $_) {}
    }
    if (!$isAdmin) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Forbidden']); exit; }

    // CSRF validation
    $token = $_POST['csrf_token'] ?? '';
    if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid CSRF token']); exit; }

    $val = ($_POST['use_sandbox'] ?? '') === '1';
    $_SESSION['playground_use_sandbox'] = $val ? true : false;

    // Persist to DB
    try {
        $db->update('users', ['playground_use_sandbox' => $val], ['id' => $_SESSION['user_id']]);
    } catch (\Throwable $_) {}

    // Ensure a sandbox exists for this admin when enabling sandbox mode
    if ($val) {
        try { \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($db ?? null, $_SESSION ?? null); } catch (\Throwable $_) {}
    }

    // Return the current sandbox id (if any) so the front-end can update without a full reload
    $editorRoot = null;
    $sandboxId = null;
    try {
        $editorRoot = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($db ?? null, $_SESSION ?? null);
        $realRoot = realpath($editorRoot) ?: rtrim($editorRoot, '/');
        $isAdminRoot = $realRoot === (realpath(ROOT_PATH) ?: rtrim(ROOT_PATH, '/'));
        if (!$isAdminRoot) $sandboxId = basename($editorRoot);
    } catch (\Throwable $_) { $sandboxId = null; }

    // Log toggle into activity_logs when DB is available for traceability
    try {
        if ($db) {
            $db->insert('activity_logs', [
                'user_id' => $_SESSION['user_id'] ?? null,
                'action' => 'playground.toggle_admin_sandbox',
                'model_type' => 'playground.editor',
                'description' => json_encode(['use_sandbox' => $_SESSION['playground_use_sandbox'] ?? false], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
    } catch (\Throwable $_) {}

    header('Content-Type: application/json; charset=utf-8');
    // Include an explicit csrf_ok flag so clients can verify the server
    // accepted the provided CSRF token and that the action was authorized.
    echo json_encode([
        'success' => true,
        'csrf_ok' => true,
        'use_sandbox' => $_SESSION['playground_use_sandbox'] ?? false,
        'sandbox_id' => $sandboxId,
        'csrf_token' => $_SESSION['csrf_token'] ?? null
    ]);
    exit;
}, ['POST']);

// Dev endpoint: return a filtered view of the current session for debugging in the editor
req($router, '/playground/editor/session_debug', function() use ($db) {
    header('Content-Type: application/json');
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    // Build a filtered session view to avoid leaking sensitive values
    $out = [];
    $out['user_id'] = $_SESSION['user_id'] ?? null;
    $out['username'] = $_SESSION['username'] ?? null;
    $out['fullname'] = $_SESSION['fullname'] ?? null;
    $out['role_id'] = $_SESSION['role_id'] ?? null;
    $out['role'] = $_SESSION['role'] ?? null;
    $out['is_admin'] = $_SESSION['is_admin'] ?? ($_SESSION['user']['is_admin'] ?? false);
    $out['playground_use_sandbox'] = $_SESSION['playground_use_sandbox'] ?? false;
    $out['playground_admin_sandbox'] = $_SESSION['playground_admin_sandbox'] ?? false;
    $out['public_id'] = $_SESSION['public_id'] ?? ($_SESSION['user']['public_id'] ?? null);

    if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) {
        $u = $_SESSION['user'];
        $out['user'] = [
            'id' => $u['id'] ?? null,
            'username' => $u['username'] ?? null,
            'role' => $u['role'] ?? null,
            'is_admin' => $u['is_admin'] ?? null,
            'public_id' => $u['public_id'] ?? null
        ];
    }

    // Compute a server-side detected admin flag to make it explicit how
    // the application currently classifies the logged-in session. This
    // mirrors the detection used across views/helpers (role, role_id,
    // is_admin flags, and fallback DB lookup) so developers can see the
    // effective decision without having to reconstruct it client-side.
    $detected = false;
    try {
        if (!empty($_SESSION['is_admin'])) $detected = true;
        if (!$detected && !empty($_SESSION['role']) && strtolower((string)$_SESSION['role']) === 'admin') $detected = true;
        if (!$detected && !empty($_SESSION['role_id']) && in_array((int)$_SESSION['role_id'], [1,2], true)) $detected = true;
        if (!$detected && !empty($_SESSION['user']) && is_array($_SESSION['user']) && !empty($_SESSION['user']['is_admin'])) $detected = true;

        // DB fallback: if still not detected, and a DB handle is available,
        // try to resolve the user's role from the database.
        if (!$detected && isset($db) && $db && !empty($_SESSION['user_id'])) {
            try {
                $u = $db->get('users', ['role_id'], ['id' => $_SESSION['user_id']]);
                if (!empty($u) && !empty($u['role_id'])) {
                    $roleRow = $db->get('roles', ['name','display_name'], ['id' => $u['role_id']]);
                    $roleName = strtolower((string)($roleRow['display_name'] ?? $roleRow['name'] ?? ''));
                    if (in_array($roleName, ['administrator','admin'], true)) $detected = true;
                }
            } catch (\Throwable $_) { /* ignore DB lookup failures */ }
        }
    } catch (\Throwable $_) { /* defensive: ensure endpoint never throws */ }

    $out['detected_is_admin'] = $detected;

    echo json_encode(['success' => true, 'session' => $out]);
    exit;
}, ['GET']);

// Playground editor - refresh tree
req($router, '/playground/editor/tree', function() use ($db) {
    // Ensure visitor sessions have a sandbox so they can view/edit files.
    if (empty($_SESSION['user_id'])) {
        if (empty($_SESSION['sandbox_id'])) {
            try {
                $created = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($db ?? null, $_SESSION ?? null);
                if (!empty($created)) {
                    $_SESSION['sandbox_id'] = basename($created);
                    if (empty($_SESSION['csrf_token'])) {
                        try { $_SESSION['csrf_token'] = bin2hex(random_bytes(16)); } catch (\Throwable $_) { $_SESSION['csrf_token'] = md5(uniqid('', true)); }
                    }
                }
            } catch (\Throwable $_) {}
        }
        if (empty($_SESSION['sandbox_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
    }
    
    header('Content-Type: application/json');
    
    // Rebuild the tree using the editor root for current user
    $rootPath = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($db ?? null, $_SESSION ?? null);
    
    function buildTree($dir, $base = '') {
        $tree = [];
        $items = @scandir($dir);
        if (!$items) return $tree;
        
        // Exclusion list
        $exclude = ['.', '..', '.git', 'node_modules', 'vendor', '.idea', '.vscode', '__pycache__', '.DS_Store'];
        
        foreach ($items as $item) {
            if (in_array($item, $exclude)) continue;
            
            $path = $dir . '/' . $item;
            $relPath = $base ? $base . '/' . $item : $item;
            
            if (is_dir($path)) {
                $tree[$item] = [
                    'type' => 'dir',
                    'path' => $relPath,
                    'children' => buildTree($path, $relPath)
                ];
            } else {
                $tree[$item] = [
                    'type' => 'file',
                    'path' => $relPath,
                    'encoded' => rawurlencode(base64_encode($relPath))
                ];
            }
        }
        
        return $tree;
    }
    
    echo json_encode(['tree' => buildTree($rootPath)]);
    exit;
});

// Playground console - environment (safe read-only)
req($router, '/playground/console/environment', function() use ($db) {
    header('Content-Type: application/json');
    if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['error'=>'Unauthorized']); exit; }

    $editorRoot = null; $sandboxId = null; $isAdmin = false;
    try {
        $editorRoot = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($db ?? null, $_SESSION ?? null);
        $realRoot = realpath($editorRoot) ?: rtrim($editorRoot, '/');
        $isAdmin = $realRoot === (realpath(ROOT_PATH) ?: rtrim(ROOT_PATH, '/'));
        if (!$isAdmin) $sandboxId = basename($editorRoot);
    } catch (\Throwable $_) { $editorRoot = null; }

    // Build safe environment summary. For non-admins mask filesystem paths so the
    // client sees a normalized view (e.g. /home/<sandboxId>) instead of the
    // real server layout which may include usernames and repo paths.
    $isDetectedAdmin = (!empty($_SESSION['is_admin']) || !empty($_SESSION['role']) && strtolower((string)$_SESSION['role']) === 'admin');

    // Default display values (may be masked below)
    $displayRoot = ROOT_PATH;
    $displayEditorRoot = $editorRoot;

    if (!$isDetectedAdmin) {
        // Mask root and editor root for non-admins
        $displayRoot = '/home';
        if (!empty($sandboxId)) {
            $displayEditorRoot = '/home/' . $sandboxId;
        } else {
            // If no sandbox id, hide specifics
            $displayEditorRoot = '/home';
        }
    }

    $out = [
        'php_version' => phpversion(),
        'php_sapi' => php_sapi_name(),
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? null,
        // These fields are safe to expose; they are masked for non-admins as above
        'root_path' => $displayRoot,
        'editor_root' => $displayEditorRoot,
        'sandbox_id' => $sandboxId,
        'playground_use_sandbox' => $_SESSION['playground_use_sandbox'] ?? false,
        'detected_is_admin' => $isDetectedAdmin
    ];

    // Only include real (unmasked) paths for admins to avoid leaking server layout
    if ($isDetectedAdmin) {
        $out['real_root_path'] = ROOT_PATH;
        $out['real_editor_root'] = $editorRoot;
    }

    echo json_encode($out);
    exit;
}, ['GET']);

// Playground console - execute a command (CSRF-protected)
req($router, '/playground/console/exec', function() use ($db) {
    header('Content-Type: application/json');
    if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit; }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'error'=>'Method not allowed']); exit; }

    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid CSRF token']); exit; }

    $command = trim((string)($_POST['command'] ?? ''));
    if ($command === '') { echo json_encode(['success'=>false,'error'=>'Empty command']); exit; }

    // Determine admin vs sandbox user
    $editorRoot = null; $sandboxId = null; $isAdmin = false;
    try {
        $editorRoot = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($db ?? null, $_SESSION ?? null);
        $realRoot = realpath($editorRoot) ?: rtrim($editorRoot, '/');
        $isAdmin = $realRoot === (realpath(ROOT_PATH) ?: rtrim(ROOT_PATH, '/')) || !empty($_SESSION['is_admin']) || (!empty($_SESSION['role']) && strtolower((string)$_SESSION['role']) === 'admin');
        if (!$isAdmin) $sandboxId = basename($editorRoot);
    } catch (\Throwable $_) { $editorRoot = null; }

    // For non-admin users enforce a conservative whitelist and disallow shell metacharacters
    if (!$isAdmin) {
        // quick reject of dangerous metacharacters
        if (preg_match('/[;&|<>`$()\\]/', $command)) {
            http_response_code(403); echo json_encode(['success'=>false,'error'=>'Forbidden characters in command']); exit;
        }
        // whitelist allowed base commands
        $allowed = ['ls','pwd','cat','tail','head','php','node','whoami','id','grep','find','wc'];
        $parts = preg_split('/\s+/', $command);
        $base = $parts[0] ?? '';
        if (!in_array($base, $allowed, true)) {
            http_response_code(403); echo json_encode(['success'=>false,'error'=>'Command not allowed for sandbox users']); exit;
        }
    }

    // Execute command with timeout and output limits
    $cwd = $editorRoot ?: getcwd();
    $descriptors = [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']];
    $process = null; $output = ''; $err = '';
    $start = microtime(true);
    $timeout = 10; // seconds
    $maxBytes = 200000; // 200KB
    try {
        // For admin allow full shell with /bin/sh -lc so shell features are available;
        // for sandbox users run the command directly (no shell expansion) from the sandbox cwd.
        if ($isAdmin) {
            $cmdSpec = ['/bin/sh', '-lc', $command];
        } else {
            // For non-admin users run the command inside a rootless Podman container
            // using the repository's runner script. The runner validates the sandbox
            // and executes the requested command inside the container.
            $script = rtrim(ROOT_PATH, '/') . '/scripts/sandbox-run.sh';
            // Use array form to avoid an extra shell layer and pass arguments safely.
            $cmdSpec = ['/usr/bin/env', 'bash', $script, $sandboxId, $command];
        }
        $process = proc_open($cmdSpec, $descriptors, $pipes, $cwd);
        if (!is_resource($process)) { throw new \RuntimeException('Failed to start process'); }
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = ''; $stderr = '';
        while (true) {
            $status = proc_get_status($process);
            $read = [$pipes[1], $pipes[2]];
            $write = null; $except = null;
            if (stream_select($read, $write, $except, 0, 200000)) {
                foreach ($read as $r) {
                    $chunk = stream_get_contents($r);
                    if ($r === $pipes[1]) $stdout .= $chunk; else $stderr .= $chunk;
                    if (strlen($stdout) + strlen($stderr) > $maxBytes) break 2;
                }
            }
            if (!$status['running']) break;
            if ((microtime(true) - $start) > $timeout) {
                // timeout: terminate
                proc_terminate($process);
                break;
            }
            usleep(100000);
        }

        // collect any remaining
        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);
        foreach ($pipes as $p) { @fclose($p); }
        $code = proc_close($process);
        $out = trim($stdout . (strlen($stderr) ? "\nERR:\n".$stderr : ''));

        // Mask filesystem paths in output for non-admin users so the client
        // doesn't see server-specific layout. This is a presentation-only
        // safeguard — real isolation requires OS-level namespacing or containers.
        if (!$isAdmin) {
            $displayRoot = '/home';
            $displayEditorRoot = !empty($sandboxId) ? ('/home/' . $sandboxId) : '/home';
            // Replace editor root if present
            if (!empty($editorRoot)) {
                $realEditorRoot = rtrim($editorRoot, '/');
                if ($realEditorRoot !== '') {
                    $out = str_replace($realEditorRoot, $displayEditorRoot, $out);
                }
            }
            // Replace configured ROOT_PATH occurrences
            try { $out = str_replace(rtrim(ROOT_PATH, '/'), $displayRoot, $out); } catch (\Throwable $_) {}
            // Replace common $HOME env var if present
            try { $homeEnv = getenv('HOME'); if ($homeEnv) $out = str_replace(rtrim($homeEnv, '/'), $displayRoot, $out); } catch (\Throwable $_) {}
        }

        $truncated = strlen($out) > $maxBytes;
        if ($truncated) $out = substr($out, 0, $maxBytes) . "\n...[truncated]";

        // Log execution for audit
        try { if ($db) $db->insert('activity_logs', ['user_id'=>$_SESSION['user_id'] ?? null,'action'=>'playground.exec','model_type'=>'playground.console','description'=>json_encode(['cmd'=>$command,'cwd'=>$cwd,'admin'=>$isAdmin],'JSON_UNESCAPED_UNICODE'),'created_at'=>date('Y-m-d H:i:s')]); } catch (\Throwable $_) {}

        echo json_encode(['success'=>true,'output'=>$out,'exit_code'=>$code,'truncated'=>$truncated]);
        exit;
    } catch (\Throwable $e) {
        if (is_resource($process)) { @proc_terminate($process); }
        http_response_code(500);
        echo json_encode(['success'=>false,'error'=>'Execution failed','message'=>$e->getMessage()]);
        exit;
    }
}, ['POST']);

// Playground console - tail logs (safe read-only)
req($router, '/playground/console/logs', function() use ($db) {
    if (empty($_SESSION['user_id'])) { http_response_code(401); echo 'Unauthorized'; exit; }
    $lines = isset($_GET['lines']) ? (int)$_GET['lines'] : 200;
    if ($lines < 1) $lines = 200;
    // Only allow reading log files from storage/ (no path arg)
    $storagePath = defined('STORAGE_PATH') ? STORAGE_PATH : dirname(ROOT_PATH) . '/storage';
    $logFile = $storagePath . '/pma_debug_cli_output.txt';
    if (!file_exists($logFile)) { http_response_code(200); echo "(log file not found: pma_debug_cli_output.txt)"; exit; }
    // Tail last N lines safely
    $data = '';
    try {
        $fp = fopen($logFile, 'rb');
        if ($fp) {
            $pos = -1; $linesFound = 0; $chunk = '';
            fseek($fp, 0, SEEK_END);
            $end = ftell($fp);
            $buffer = '';
            while ($linesFound < $lines && ftell($fp) > 0) {
                $seek = max(0, ftell($fp) - 4096);
                $readLen = ftell($fp) - $seek;
                fseek($fp, $seek);
                $chunk = fread($fp, $readLen) . $buffer;
                $parts = preg_split('/\r?\n/', $chunk);
                $linesFound = count($parts) - 1;
                $buffer = $chunk;
                if ($seek === 0) break;
                fseek($fp, $seek);
                if ($seek === 0) break;
            }
            fclose($fp);
            $parts = preg_split('/\r?\n/', $buffer);
            $parts = array_filter($parts, function($r){ return $r !== ''; });
            $last = array_slice($parts, -$lines);
            echo implode("\n", $last);
            exit;
        }
    } catch (\Throwable $e) {}
    http_response_code(500); echo '(failed to read logs)'; exit;
}, ['GET']);

// Playground editor - create file/folder
req($router, '/playground/editor/create', function() use ($db) {
    // Allow visitor sandboxes by ensuring a session sandbox exists.
    if (empty($_SESSION['user_id'])) {
        if (empty($_SESSION['sandbox_id'])) {
            try {
                $created = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($db ?? null, $_SESSION ?? null);
                if (!empty($created)) {
                    $_SESSION['sandbox_id'] = basename($created);
                    if (empty($_SESSION['csrf_token'])) {
                        try { $_SESSION['csrf_token'] = bin2hex(random_bytes(16)); } catch (\Throwable $_) { $_SESSION['csrf_token'] = md5(uniqid('', true)); }
                    }
                }
            } catch (\Throwable $_) {}
        }
        if (empty($_SESSION['sandbox_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
    }
    
    header('Content-Type: application/json');
    
    // CSRF validation
    $token = $_POST['csrf_token'] ?? '';
    if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
    
    $path = $_POST['path'] ?? '';
    $type = $_POST['type'] ?? 'file';
    
    if (!$path) {
        echo json_encode(['success' => false, 'error' => 'Path required']);
        exit;
    }
    
    // Sanitize path
    $safePath = str_replace(['..', '\\'], ['', '/'], $path);
    $editorRoot = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($db ?? null, $_SESSION ?? null);
    $fullPath = rtrim($editorRoot, '/') . '/' . ltrim($safePath, '/');

    // Verify within sandbox root using normalization (allows creating new subfolders)
    $normalizePath = function($p) {
        $p = str_replace('\\', '/', $p);
        $parts = array_filter(explode('/', $p), function($x){ return $x !== ''; });
        $stack = [];
        foreach ($parts as $part) {
            if ($part === '.') continue;
            if ($part === '..') { array_pop($stack); continue; }
            $stack[] = $part;
        }
        $out = implode('/', $stack);
        if ($p !== '' && $p[0] === '/') $out = '/' . $out;
        return $out;
    };

    $realRoot = realpath($editorRoot) ?: rtrim($editorRoot, '/');
    $normalizedRoot = rtrim($normalizePath($realRoot), '/');
    $parentDir = dirname($fullPath);
    if (!is_dir($parentDir)) {
        @mkdir($parentDir, 0755, true);
    }
    $normalizedParent = $normalizePath($parentDir);
    if (strpos($normalizedParent, $normalizedRoot) !== 0) {
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }
    
    if (file_exists($fullPath)) {
        echo json_encode(['success' => false, 'error' => 'Already exists']);
        exit;
    }
    
    $isAdminSession = (realpath($editorRoot) === (realpath(ROOT_PATH) ?: ROOT_PATH));
    $forbiddenExt = ['php','phtml','pl','py','sh','exe','jar','bat','cmd','run','cgi','asp','aspx'];

    if ($type === 'folder') {
        if (@mkdir($fullPath, 0755, true)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to create folder']);
        }
    } else {
        // extension check
        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        if (!$isAdminSession && in_array($ext, $forbiddenExt)) {
            echo json_encode(['success' => false, 'error' => 'Forbidden file type']);
            exit;
        }

        if (@file_put_contents($fullPath, '') !== false) {
            echo json_encode(['success' => true, 'encoded' => rawurlencode(base64_encode($safePath))]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to create file']);
        }
    }
    exit;
}, ['POST']);

// Playground editor - rename
req($router, '/playground/editor/rename', function() use ($db) {
    // Allow visitors with a session sandbox to rename within their sandbox
    if (empty($_SESSION['user_id'])) {
        if (empty($_SESSION['sandbox_id'])) {
            try {
                $created = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($db ?? null, $_SESSION ?? null);
                if (!empty($created)) {
                    $_SESSION['sandbox_id'] = basename($created);
                    if (empty($_SESSION['csrf_token'])) {
                        try { $_SESSION['csrf_token'] = bin2hex(random_bytes(16)); } catch (\Throwable $_) { $_SESSION['csrf_token'] = md5(uniqid('', true)); }
                    }
                }
            } catch (\Throwable $_) {}
        }
        if (empty($_SESSION['sandbox_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
    }
    
    header('Content-Type: application/json');
    
    $token = $_POST['csrf_token'] ?? '';
    if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
    
    $oldPath = str_replace(['..', '\\'], ['', '/'], $_POST['oldPath'] ?? '');
    $newPath = str_replace(['..', '\\'], ['', '/'], $_POST['newPath'] ?? '');
    
    if (!$oldPath || !$newPath) {
        echo json_encode(['success' => false, 'error' => 'Paths required']);
        exit;
    }
    
    $editorRoot = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($db ?? null, $_SESSION ?? null);
    $oldFull = rtrim($editorRoot, '/') . '/' . ltrim($oldPath, '/');
    $newFull = rtrim($editorRoot, '/') . '/' . ltrim($newPath, '/');
    
    $normalizePath = function($p) {
        $p = str_replace('\\', '/', $p);
        $parts = array_filter(explode('/', $p), function($x){ return $x !== ''; });
        $stack = [];
        foreach ($parts as $part) {
            if ($part === '.') continue;
            if ($part === '..') { array_pop($stack); continue; }
            $stack[] = $part;
        }
        $out = implode('/', $stack);
        if ($p !== '' && $p[0] === '/') $out = '/' . $out;
        return $out;
    };

    $realRoot = realpath($editorRoot) ?: rtrim($editorRoot, '/');
    $realOld = realpath($oldFull);
    if (!$realOld || strpos($realOld, $realRoot) !== 0) {
        echo json_encode(['success' => false, 'error' => 'Source not found']);
        exit;
    }
    
    // ensure new parent is writable and within root
    $newParent = dirname($newFull);
    if (!is_dir($newParent)) @mkdir($newParent, 0755, true);
    $normalizedRoot = rtrim($normalizePath($realRoot), '/');
    $normalizedNew = $normalizePath($newFull);
    if (strpos($normalizedNew, $normalizedRoot) !== 0) {
        echo json_encode(['success' => false, 'error' => 'Destination invalid']);
        exit;
    }

    if (file_exists($newFull)) {
        echo json_encode(['success' => false, 'error' => 'Destination already exists']);
        exit;
    }
    // For sandbox users, disallow renaming into forbidden extensions
    $isAdminSession = (realpath($editorRoot) === (realpath(ROOT_PATH) ?: ROOT_PATH));
    $forbiddenExt = ['php','phtml','pl','py','sh','exe','jar','bat','cmd','run','cgi','asp','aspx'];
    $newExt = strtolower(pathinfo($newFull, PATHINFO_EXTENSION));
    if (!$isAdminSession && in_array($newExt, $forbiddenExt)) {
        echo json_encode(['success' => false, 'error' => 'Forbidden file type']); exit;
    }

    if (@rename($oldFull, $newFull)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to rename']);
    }
    exit;
}, ['POST']);

// Playground editor - delete
req($router, '/playground/editor/delete', function() use ($db) {
    // Allow visitors with session sandboxes to delete within their sandbox
    if (empty($_SESSION['user_id'])) {
        if (empty($_SESSION['sandbox_id'])) {
            try {
                $created = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($db ?? null, $_SESSION ?? null);
                if (!empty($created)) {
                    $_SESSION['sandbox_id'] = basename($created);
                    if (empty($_SESSION['csrf_token'])) {
                        try { $_SESSION['csrf_token'] = bin2hex(random_bytes(16)); } catch (\Throwable $_) { $_SESSION['csrf_token'] = md5(uniqid('', true)); }
                    }
                }
            } catch (\Throwable $_) {}
        }
        if (empty($_SESSION['sandbox_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
    }
    
    header('Content-Type: application/json');
    
    $token = $_POST['csrf_token'] ?? '';
    if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
    
    $path = str_replace(['..', '\\'], ['', '/'], $_POST['path'] ?? '');
    if (!$path) {
        echo json_encode(['success' => false, 'error' => 'Path required']);
        exit;
    }
    
    $editorRoot = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($db ?? null, $_SESSION ?? null);
    $fullPath = rtrim($editorRoot, '/') . '/' . ltrim($path, '/');
    $realRoot = realpath($editorRoot);
    $realPath = realpath($fullPath);
    
    if (!$realPath || strpos($realPath, $realRoot) !== 0) {
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }
    
    // Prevent deleting critical files/folders
    $protected = ['composer.json', 'composer.lock', 'src', 'public', 'vendor', '.env'];
    if (in_array(basename($path), $protected) && substr_count($path, '/') === 0) {
        echo json_encode(['success' => false, 'error' => 'Cannot delete protected item']);
        exit;
    }
    
    function deleteRecursive($path) {
        if (is_dir($path)) {
            $items = array_diff(scandir($path), ['.', '..']);
            foreach ($items as $item) {
                deleteRecursive($path . '/' . $item);
            }
            return @rmdir($path);
        }
        return @unlink($path);
    }
    
    if (deleteRecursive($fullPath)) {
        try {
            // recalc used_bytes for sandbox
            $sandboxId = basename($editorRoot);
            if ($db) {
                $sizeAfter = 0;
                $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($editorRoot, \FilesystemIterator::SKIP_DOTS));
                foreach ($it as $f) $sizeAfter += $f->getSize();
                $db->update('client_sandboxes', ['used_bytes' => $sizeAfter, 'updated_at' => date('Y-m-d H:i:s')], ['sandbox_id' => $sandboxId]);
            }
        } catch (\Throwable $_) {}
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to delete']);
    }
    exit;
}, ['POST']);

// Playground editor - paste (copy/move)
req($router, '/playground/editor/paste', function() use ($db) {
    // Allow visitors with session sandboxes to paste/copy within their sandbox
    if (empty($_SESSION['user_id'])) {
        if (empty($_SESSION['sandbox_id'])) {
            try {
                $created = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($db ?? null, $_SESSION ?? null);
                if (!empty($created)) {
                    $_SESSION['sandbox_id'] = basename($created);
                    if (empty($_SESSION['csrf_token'])) {
                        try { $_SESSION['csrf_token'] = bin2hex(random_bytes(16)); } catch (\Throwable $_) { $_SESSION['csrf_token'] = md5(uniqid('', true)); }
                    }
                }
            } catch (\Throwable $_) {}
        }
        if (empty($_SESSION['sandbox_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
    }
    
    header('Content-Type: application/json');
    
    $token = $_POST['csrf_token'] ?? '';
    if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
    
    $source = str_replace(['..', '\\'], ['', '/'], $_POST['source'] ?? '');
    $destination = str_replace(['..', '\\'], ['', '/'], $_POST['destination'] ?? '');
    $action = $_POST['action'] ?? 'copy';
    
    if (!$source) {
        echo json_encode(['success' => false, 'error' => 'Source required']);
        exit;
    }
    
    $editorRoot = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($db ?? null, $_SESSION ?? null);
    $srcFull = rtrim($editorRoot, '/') . '/' . ltrim($source, '/');
    $srcName = basename($source);
    $destDir = $destination ? rtrim($editorRoot, '/') . '/' . ltrim($destination, '/') : rtrim($editorRoot, '/');
    $destFull = $destDir . '/' . $srcName;
    
    $realRoot = realpath($editorRoot);
    $realSrc = realpath($srcFull);
    
    if (!$realSrc || strpos($realSrc, $realRoot) !== 0) {
        echo json_encode(['success' => false, 'error' => 'Source not found']);
        exit;
    }
    
    if (!is_dir($destDir)) {
        echo json_encode(['success' => false, 'error' => 'Destination folder not found']);
        exit;
    }
    
    // Handle name collision
    if (file_exists($destFull)) {
        $ext = pathinfo($srcName, PATHINFO_EXTENSION);
        $name = pathinfo($srcName, PATHINFO_FILENAME);
        $i = 1;
        while (file_exists($destFull)) {
            $newName = $ext ? "{$name}_copy{$i}.{$ext}" : "{$name}_copy{$i}";
            $destFull = $destDir . '/' . $newName;
            $i++;
        }
    }
    
    function copyRecursive($src, $dst) {
        if (is_dir($src)) {
            @mkdir($dst, 0755, true);
            $items = array_diff(scandir($src), ['.', '..']);
            foreach ($items as $item) {
                copyRecursive($src . '/' . $item, $dst . '/' . $item);
            }
            return is_dir($dst);
        }
        return @copy($src, $dst);
    }
    
    // Enforce forbidden extensions and quota for non-admins when copying
    $isAdminSession = (realpath($editorRoot) === (realpath(ROOT_PATH) ?: ROOT_PATH));
    $forbiddenExt = ['php','phtml','pl','py','sh','exe','jar','bat','cmd','run','cgi','asp','aspx'];

    $srcSize = 0;
    if (is_dir($srcFull)) {
        try {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($srcFull, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) $srcSize += $f->getSize();
        } catch (\Throwable $_) { $srcSize = 0; }
    } elseif (is_file($srcFull)) {
        $srcSize = filesize($srcFull);
        $srcExt = strtolower(pathinfo($srcFull, PATHINFO_EXTENSION));
    }

    if (!$isAdminSession && $action !== 'cut') {
        if (is_file($srcFull) && in_array($srcExt ?? '', $forbiddenExt)) { echo json_encode(['success' => false, 'error' => 'Forbidden file type']); exit; }
        // check quota
        if ($db) {
            $sandboxId = basename($editorRoot);
            $row = $db->get('client_sandboxes', ['quota_bytes'], ['sandbox_id' => $sandboxId]);
            $quota = $row['quota_bytes'] ?? 104857600;
            $cur = 0; try { $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($editorRoot, \FilesystemIterator::SKIP_DOTS)); foreach ($it as $f) $cur += $f->getSize(); } catch (\Throwable $_) { $cur = 0; }
            if (($cur + $srcSize) > $quota) { echo json_encode(['success' => false, 'error' => 'Quota exceeded']); exit; }
        }
    }

    if ($action === 'cut') {
        if (@rename($srcFull, $destFull)) {
            // update DB used_bytes for sandbox
            try { if ($db) { $sandboxId = basename($editorRoot); $sizeAfter = 0; $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($editorRoot, \FilesystemIterator::SKIP_DOTS)); foreach ($it as $f) $sizeAfter += $f->getSize(); $db->update('client_sandboxes', ['used_bytes' => $sizeAfter, 'updated_at' => date('Y-m-d H:i:s')], ['sandbox_id' => $sandboxId]); } } catch (\Throwable $_) {}
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to move']);
        }
    } else {
        if (copyRecursive($srcFull, $destFull)) {
            try { if ($db) { $sandboxId = basename($editorRoot); $sizeAfter = 0; $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($editorRoot, \FilesystemIterator::SKIP_DOTS)); foreach ($it as $f) $sizeAfter += $f->getSize(); $db->update('client_sandboxes', ['used_bytes' => $sizeAfter, 'updated_at' => date('Y-m-d H:i:s')], ['sandbox_id' => $sandboxId]); } } catch (\Throwable $_) {}
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to copy']);
        }
    }
    exit;
}, ['POST']);

// =====================================================================
// Provider API Keys Management (Admin Only)
// =====================================================================
req($router, '/api/provider-keys', function() use ($db) {
    header('Content-Type: application/json');
    
    // Require admin authentication
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    
    // Check if user is logged in
    if (empty($_SESSION['user_id'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Login required']);
        exit;
    }
    
    // CSRF validation for POST requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        if (empty($csrfToken) || empty($sessionToken) || !hash_equals($sessionToken, $csrfToken)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }
    }
    
    // Check admin status - first try session, then database
    $isAdmin = \Ginto\Controllers\UserController::isAdmin();
    if (!$isAdmin && $db) {
        // Fallback: check database directly
        try {
            $user = $db->get('users', ['role_id', 'is_admin'], ['id' => $_SESSION['user_id']]);
            if ($user) {
                $isAdmin = !empty($user['is_admin']) || in_array((int)($user['role_id'] ?? 0), [1, 2], true);
            }
        } catch (\Throwable $_) {}
    }
    
    if (!$isAdmin) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Admin access required']);
        exit;
    }
    
    $keyManager = new \App\Core\ProviderKeyManager($db);
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // List all keys
        $keys = $keyManager->getAllKeys();
        // Mask API keys for security
        foreach ($keys as &$key) {
            $key['api_key_masked'] = \App\Core\ProviderKeyManager::maskKey($key['api_key']);
            unset($key['api_key']); // Don't send full key to frontend
        }
        echo json_encode(['success' => true, 'keys' => $keys]);
        exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $action = $input['action'] ?? 'add';
        
        switch ($action) {
            case 'add':
                if (empty($input['provider']) || empty($input['api_key'])) {
                    echo json_encode(['success' => false, 'error' => 'Provider and API key are required']);
                    exit;
                }
                $id = $keyManager->addKey([
                    'provider' => $input['provider'],
                    'api_key' => $input['api_key'],
                    'key_name' => $input['key_name'] ?? null,
                    'tier' => $input['tier'] ?? 'basic',
                    'is_default' => !empty($input['is_default']),
                    'priority' => $input['priority'] ?? null,
                ]);
                echo json_encode(['success' => true, 'id' => $id, 'message' => 'API key added successfully']);
                break;
                
            case 'update':
                if (empty($input['id'])) {
                    echo json_encode(['success' => false, 'error' => 'Key ID is required']);
                    exit;
                }
                $success = $keyManager->updateKey((int)$input['id'], $input);
                echo json_encode(['success' => $success, 'message' => $success ? 'Key updated' : 'Update failed']);
                break;
                
            case 'delete':
                if (empty($input['id'])) {
                    echo json_encode(['success' => false, 'error' => 'Key ID is required']);
                    exit;
                }
                $success = $keyManager->deleteKey((int)$input['id']);
                echo json_encode(['success' => $success, 'message' => $success ? 'Key deleted' : 'Delete failed']);
                break;
                
            case 'clear_rate_limit':
                if (empty($input['id'])) {
                    echo json_encode(['success' => false, 'error' => 'Key ID is required']);
                    exit;
                }
                $keyManager->clearRateLimit((int)$input['id']);
                echo json_encode(['success' => true, 'message' => 'Rate limit cleared']);
                break;
                
            default:
                echo json_encode(['success' => false, 'error' => 'Unknown action']);
        }
        exit;
    }
    
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
});
