<?php

namespace Ginto\Controllers;

use Ginto\Database;

/**
 * AudioController - Handles TTS and STT audio routes
 */
class AudioController
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? Database::getInstance();
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
    }

    /**
     * Text-to-Speech endpoint
     * POST /audio/tts
     */
    public function tts(): void
    {
        @ini_set('zlib.output_compression', false);
        while (ob_get_level()) @ob_end_clean();
        ignore_user_abort(true);

        // Determine TTS model early for rate limit checking
        $defaultModel = $_ENV['GROQ_TTS_MODEL'] ?? getenv('GROQ_TTS_MODEL') ?: null;
        $model = $_POST['model'] ?? $defaultModel ?? 'gpt-4o-mini-tts';

        // Get user info for rate limiting
        $userId = $_SESSION['user_id'] ?? null;
        $sessionId = session_id() ?: null;

        // Determine user role
        $isAdmin = !empty($_SESSION['is_admin']) 
            || (!empty($_SESSION['role']) && strtolower((string)$_SESSION['role']) === 'admin')
            || (!empty($_SESSION['user_role']) && strtolower((string)$_SESSION['user_role']) === 'admin');

        $userRole = $userId ? ($isAdmin ? 'admin' : 'user') : 'visitor';

        // TTS Rate Limiting
        try {
            $rateLimitService = new \App\Core\RateLimitService();
            $ttsCheck = $rateLimitService->canMakeTtsRequest($model, 'groq', $userRole, $userId, $sessionId);

            if (!$ttsCheck['allowed']) {
                $isSilentStop = !empty($ttsCheck['silent']);

                error_log("[TTS rate limit] Limit reached - reason: {$ttsCheck['reason']}, " .
                    "role: {$ttsCheck['user_role']}, silent: " . ($isSilentStop ? 'yes' : 'no') .
                    ", usage: " . json_encode($ttsCheck['usage']));

                if ($isSilentStop) {
                    http_response_code(204);
                    header('X-Ginto-TTS: rate-limited-silent');
                    header('X-Ginto-TTS-Reason: org-quota');
                    exit;
                }

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
            error_log("[TTS rate limit] Check failed: " . $e->getMessage());
        }

        $text = $_POST['text'] ?? trim(file_get_contents('php://input')) ?: '';

        // Sanitize text for TTS
        $text = preg_replace('/[\x{1F300}-\x{1F9FF}]/u', '', $text);
        $text = preg_replace('/[\x{2600}-\x{26FF}]/u', '', $text);
        $text = preg_replace('/[\x{2700}-\x{27BF}]/u', '', $text);
        $text = preg_replace('/[\x{1F600}-\x{1F64F}]/u', '', $text);
        $text = preg_replace('/[\x{1F680}-\x{1F6FF}]/u', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        if (strlen($text) < 5) {
            $text = 'Hello! This is a text-to-speech demo from Ginto.';
        }

        $defaultVoice = $_ENV['GROQ_TTS_VOICE'] ?? getenv('GROQ_TTS_VOICE') ?: null;
        $voice = $_POST['voice'] ?? $defaultVoice ?? null;

        if (empty($voice) && is_string($model) && stripos($model, 'playai') !== false) {
            $voice = 'Arista-PlayAI';
        }

        $payloadArr = ['model' => $model, 'input' => $text];
        if (!empty($voice)) $payloadArr['voice'] = $voice;
        $payload = json_encode($payloadArr);

        $groqKey = $_ENV['GROQ_API_KEY'] ?? getenv('GROQ_API_KEY');
        if (!$groqKey) {
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
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $res = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE) ?: 0;
        curl_close($ch);

        if ($err) {
            http_response_code(502);
            error_log('[TTS proxy] curl error: ' . $err);
            echo json_encode(['error' => 'TTS upstream error', 'detail' => substr((string)$err, 0, 2000)]);
            exit;
        }

        if (is_int($code) && $code >= 400) {
            $bodySnippet = is_string($res) ? substr($res, 0, 2000) : '';
            error_log("[TTS proxy] upstream returned HTTP $code body=" . $bodySnippet);
            http_response_code($code ?: 502);
            echo json_encode(['error' => 'TTS upstream returned HTTP error', 'code' => $code, 'body' => $bodySnippet]);
            exit;
        }

        $contentType = 'audio/mpeg';
        $envCt = getenv('GROQ_TTS_CONTENT_TYPE');
        if ($envCt !== false && $envCt !== null && $envCt !== '') {
            $contentType = $envCt;
        }

        if (!headers_sent()) {
            header('Content-Type: ' . $contentType);
        }
        header('Cache-Control: no-cache');
        header('X-Ginto-TTS: 1');

        // Log successful TTS request
        try {
            if (!isset($rateLimitService)) {
                $rateLimitService = new \App\Core\RateLimitService();
            }
            $rateLimitService->logTtsRequest($model, 'groq', $userId, $userRole, true, $sessionId);
        } catch (\Throwable $e) {
            error_log('[TTS logging] Failed to log request: ' . $e->getMessage());
        }

        while (ob_get_level()) { @ob_end_clean(); }
        echo $res;
        exit;
    }

    /**
     * Speech-to-Text endpoint
     * POST /audio/stt
     */
    public function stt(): void
    {
        // CSRF and session protection
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            http_response_code(502);
            echo json_encode(['error' => 'STT_API_KEY not configured']);
            exit;
        }

        // Accept either multipart file or raw body
        if (!empty($_FILES['file']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
            $tmp = $_FILES['file']['tmp_name'];
            $name = $_FILES['file']['name'] ?? 'upload';
            $ctype = $_FILES['file']['type'] ?? 'application/octet-stream';
            $cfile = new \CURLFile($tmp, $ctype, $name);
            $sttModel = $_POST['model'] ?? (getenv('GROQ_STT_MODEL') ?: 'whisper-large-v3-turbo');
            $post = ['file' => $cfile, 'model' => $sttModel];
        } else {
            $raw = file_get_contents('php://input');
            if ($raw === false || $raw === '') {
                http_response_code(400);
                echo json_encode(['error' => 'no file provided']);
                exit;
            }

            $tmp = tempnam(sys_get_temp_dir(), 'ginto_stt_');
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
            $name = 'upload';
            $cfile = new \CURLFile($tmp, $ctype, 'upload');
            $sttModel = $_POST['model'] ?? (getenv('GROQ_STT_MODEL') ?: 'whisper-large-v3-turbo');
            $post = ['file' => $cfile, 'model' => $sttModel];
        }

        // Check for Python STT wrapper
        $use_py = getenv('USE_PY_STT') ?: ($_ENV['USE_PY_STT'] ?? null);
        if ($use_py) {
            $this->handlePythonStt($tmp, $name ?? 'upload', $sttModel);
            exit;
        }

        // Ensure WAV format if needed
        $current_ct = strtolower($ctype ?? '');
        $need_wav = (strpos($current_ct, 'wav') === false && strpos($current_ct, 'wave') === false);
        if ($need_wav) {
            $result = $this->transcodeToWav($tmp, $ctype);
            if ($result['success']) {
                $cfile = new \CURLFile($result['file'], 'audio/wav', 'upload.wav');
                $post = ['file' => $cfile, 'model' => $sttModel];
                $tmp = $result['file'];
                $ctype = 'audio/wav';
            } elseif ($result['error']) {
                http_response_code(400);
                echo json_encode(['error' => $result['error'], 'hint' => $result['hint'] ?? '']);
                exit;
            }
        }

        $ch = curl_init('https://api.groq.com/openai/v1/audio/transcriptions');
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

        // Retry with alternate content types if needed
        if (($code >= 400) && is_string($res) && $this->isMediaFileError($res)) {
            $retryResult = $this->retryWithAlternateTypes($tmp, $name ?? 'upload', $sttModel, $groqKey);
            if ($retryResult) {
                $res = $retryResult['res'];
                $err = $retryResult['err'];
                $code = $retryResult['code'];
            }
        }

        if ($err) {
            http_response_code(502);
            error_log('[STT proxy] curl error: ' . $err);
            echo json_encode(['error' => $err]);
            exit;
        }

        $this->outputSttResponse($res, $code);
    }

    /**
     * Transcribe test page (GET) and handler (POST)
     * GET/POST /transcribe
     */
    public function transcribe(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $this->renderTranscribeTestPage();
            exit;
        }

        // POST handling
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');

        $use_py = $_ENV['USE_PY_STT'] ?? null;
        $groqKey = $_ENV['GROQ_API_KEY'] ?? null;
        if (!$groqKey && !$use_py) {
            http_response_code(502);
            echo json_encode(['error' => 'TTS_API_KEY not configured and USE_PY_STT not enabled']);
            exit;
        }

        // Accept file upload or raw body
        if (!empty($_FILES['file']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
            $tmp = $_FILES['file']['tmp_name'];
            $name = $_FILES['file']['name'] ?? 'upload';
            $ctype = $_FILES['file']['type'] ?? 'application/octet-stream';
            $cfile = new \CURLFile($tmp, $ctype, $name);
            $sttModel = $_POST['model'] ?? ($_ENV['GROQ_STT_MODEL'] ?? 'whisper-large-v3-turbo');
            $post = ['file' => $cfile, 'model' => $sttModel];
        } else {
            $raw = file_get_contents('php://input');
            if ($raw === false || $raw === '') {
                http_response_code(400);
                echo json_encode(['error' => 'no file provided']);
                exit;
            }

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
            $name = 'upload';
            $cfile = new \CURLFile($tmp, $ctype, 'upload');
            $sttModel = $_POST['model'] ?? ($_ENV['GROQ_STT_MODEL'] ?? 'whisper-large-v3-turbo');
            $post = ['file' => $cfile, 'model' => $sttModel];
        }

        // Python STT wrapper
        if ($use_py) {
            $this->handlePythonStt($tmp, $name, $sttModel);
            exit;
        }

        // Transcode if needed
        $current_ct = strtolower($ctype ?? '');
        $need_wav = (strpos($current_ct, 'wav') === false && strpos($current_ct, 'wave') === false);
        if ($need_wav) {
            $result = $this->transcodeToWav($tmp, $ctype);
            if ($result['success']) {
                $cfile = new \CURLFile($result['file'], 'audio/wav', 'upload.wav');
                $post = ['file' => $cfile, 'model' => $sttModel];
                $tmp = $result['file'];
            }
        }

        $ch = curl_init('https://api.groq.com/openai/v1/audio/transcriptions');
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

        if ($err) {
            http_response_code(502);
            echo json_encode(['error' => $err]);
            exit;
        }

        $this->outputSttResponse($res, $code);
    }

    /**
     * Handle Python-based STT
     */
    private function handlePythonStt(string $tmp, string $name, string $sttModel): void
    {
        $py = escapeshellcmd(getenv('PYTHON3_PATH') ?: 'python3');
        $srcPath = realpath(__DIR__ . '/../../tools/groq-mcp/src');

        $tmp_with_ext = $tmp;
        $orig_ext = pathinfo($name, PATHINFO_EXTENSION) ?: '';
        $copied_tmp = false;

        if ($orig_ext && pathinfo($tmp, PATHINFO_EXTENSION) === '') {
            $tmp_with_ext = $tmp . '.' . $orig_ext;
            @copy($tmp, $tmp_with_ext);
            $copied_tmp = true;
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

        $output = null;
        $code = 0;
        $err = null;

        try {
            $des = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
            $proc = proc_open($cmd, $des, $pipes);
            if (is_resource($proc)) {
                $output = stream_get_contents($pipes[1]);
                fclose($pipes[1]);
                $err = stream_get_contents($pipes[2]);
                fclose($pipes[2]);
                $code = proc_close($proc);
                if ($err) error_log('[STT PY CLI stderr] ' . substr($err, 0, 1200));
            } else {
                $output = shell_exec($cmd . ' 2>&1');
            }
        } catch (\Exception $e) {
            http_response_code(502);
            echo json_encode(['error' => 'STT CLI failed', 'detail' => $e->getMessage()]);
            exit;
        }

        if ($output) {
            $j = json_decode($output, true);
            if (is_array($j) && isset($j['success']) && $j['success']) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => true, 'text' => $j['text'] ?? '']);
                exit;
            } else {
                http_response_code(502);
                $errMsg = is_array($j) && isset($j['error']) ? $j['error'] : 'STT CLI error';
                echo json_encode(['error' => $errMsg]);
                exit;
            }
        }

        http_response_code(502);
        $dbgErr = is_string($err) && $err !== '' ? substr($err, 0, 1200) : null;
        $extra = [];
        if ($dbgErr) $extra['detail'] = $dbgErr;
        else $extra['exit_code'] = $code ?: null;

        if ($dbgErr && stripos($dbgErr, 'Audio file is too short') !== false) {
            echo json_encode(array_merge(['error' => 'audio_too_short'], $extra));
        } else {
            echo json_encode(array_merge(['error' => 'STT CLI produced no output'], $extra));
        }

        if (!empty($copied_tmp) && is_file($tmp_with_ext)) {
            @unlink($tmp_with_ext);
        }
        exit;
    }

    /**
     * Transcode audio to WAV using ffmpeg
     */
    private function transcodeToWav(string $tmp, string $ctype): array
    {
        $ffmpeg = null;
        try {
            $ffmpeg = trim((string)shell_exec('command -v ffmpeg 2>/dev/null'));
        } catch (\Throwable $_) {
            $ffmpeg = null;
        }

        if (!$ffmpeg) {
            return ['success' => false, 'error' => null, 'hint' => null];
        }

        $wavTmp = tempnam(sys_get_temp_dir(), 'ginto_wav_');
        $wavTmpNamed = $wavTmp . '.wav';
        $cmd = escapeshellcmd($ffmpeg) . ' -y -i ' . escapeshellarg($tmp) . ' -ar 16000 -ac 1 ' . escapeshellarg($wavTmpNamed) . ' 2>&1';

        $out = null;
        try {
            $out = shell_exec($cmd);
        } catch (\Throwable $_) {
            $out = null;
        }

        if (!file_exists($wavTmpNamed) || filesize($wavTmpNamed) < 32) {
            return [
                'success' => false,
                'error' => 'could not transcode to WAV',
                'hint' => 'ffmpeg conversion failed'
            ];
        }

        if (strpos($tmp, sys_get_temp_dir()) === 0 && is_file($tmp)) {
            @unlink($tmp);
        }

        return ['success' => true, 'file' => $wavTmpNamed];
    }

    /**
     * Check if error is media file related
     */
    private function isMediaFileError(string $res): bool
    {
        return stripos($res, 'could not process file') !== false
            || stripos($res, 'is not a valid media file') !== false
            || stripos($res, 'file must be one of') !== false;
    }

    /**
     * Retry STT with alternate content types
     */
    private function retryWithAlternateTypes(string $tmp, string $name, string $sttModel, string $groqKey): ?array
    {
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
                $altCfile = new \CURLFile($tmp, $at, $name);
                $altPost = ['file' => $altCfile, 'model' => $sttModel];

                $ch2 = curl_init('https://api.groq.com/openai/v1/audio/transcriptions');
                curl_setopt_array($ch2, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $altPost,
                    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $groqKey],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 60,
                    CURLOPT_CONNECTTIMEOUT => 10,
                ]);
                $res2 = curl_exec($ch2);
                $err2 = curl_error($ch2);
                $code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                curl_close($ch2);

                if (!$err2 && $code2 >= 200 && $code2 < 300) {
                    return ['res' => $res2, 'err' => $err2, 'code' => $code2];
                }
            } catch (\Throwable $_) {
                continue;
            }
        }

        return null;
    }

    /**
     * Output STT response
     */
    private function outputSttResponse(string $res, int $code): void
    {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($code ?: 200);

        $parsed = json_decode($res, true);
        if (is_array($parsed)) {
            if (isset($parsed['text'])) {
                echo json_encode(['success' => true, 'text' => $parsed['text']]);
                exit;
            }

            if (isset($parsed['data']) && is_array($parsed['data'])) {
                $extracted = '';
                foreach ($parsed['data'] as $item) {
                    if (is_string($item)) $extracted .= ($extracted ? ' ' : '') . $item;
                    elseif (is_array($item) && isset($item['text'])) $extracted .= ($extracted ? ' ' : '') . $item['text'];
                }
                if ($extracted !== '') {
                    echo json_encode(['success' => true, 'text' => $extracted]);
                    exit;
                }
            }

            if (isset($parsed['error']) || isset($parsed['message'])) {
                http_response_code(502);
                $errMsg = $parsed['error']['message'] ?? $parsed['message'] ?? 'STT upstream error';
                echo json_encode(['error' => $errMsg]);
                exit;
            }

            echo json_encode(['success' => true, 'text' => json_encode($parsed)]);
            exit;
        }

        $txt = trim((string)$res);
        echo json_encode(['success' => true, 'text' => $txt]);
        exit;
    }

    /**
     * Render the transcribe test page
     */
    private function renderTranscribeTestPage(): void
    {
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
        let mr = null, chunks = [], streamRef = null;
        let recordingStartMs = 0;
        const MIN_MS = 1000;

        start.addEventListener('click', async ()=>{
            debug.textContent = 'requesting mic...';
            try {
                streamRef = await navigator.mediaDevices.getUserMedia({ audio: true });
                mr = new MediaRecorder(streamRef);
                chunks = [];
                mr.ondataavailable = e => { if (e.data && e.data.size) { chunks.push(e.data); debug.textContent = 'recording... chunks=' + chunks.length; } };
                mr.onstop = () => { try { streamRef.getTracks().forEach(t=>t.stop()); } catch(e){} };
                recordingStartMs = Date.now();
                mr.start();
                start.disabled = true; stop.disabled = false; debug.textContent = 'recording...';
            } catch(e) { debug.textContent = 'mic error: ' + (e?.message || e); }
        });

        stop.addEventListener('click', async ()=>{
            try {
                debug.textContent = 'stopping...';
                stop.disabled = true;
                mr.requestData?.();
                mr.stop();
                const duration = Date.now() - recordingStartMs;
                if (duration < MIN_MS) {
                    out.textContent = '[error] audio is too short';
                    start.disabled = false; stop.disabled = true;
                    return;
                }
                await new Promise(r => setTimeout(r, 200));
                const blob = new Blob(chunks, { type: (chunks[0] && chunks[0].type) || 'audio/webm' });
                debug.textContent = 'uploading ' + blob.size + ' bytes...';
                const form = new FormData();
                form.append('file', blob, 'stt.webm');
                const csrf = await getCsrf();
                if (csrf) form.append('csrf_token', csrf);
                const res = await fetch('/transcribe', { method: 'POST', credentials: 'same-origin', body: form });
                const txt = await res.text();
                debug.textContent = 'response status ' + res.status;
                try {
                    const j = JSON.parse(txt);
                    if (j.success && j.text) out.textContent = j.text;
                    else if (j.error) out.textContent = '[error] ' + j.error;
                    else out.textContent = txt;
                } catch(e) { out.textContent = txt; }
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
}
