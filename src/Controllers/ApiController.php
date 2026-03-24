<?php

namespace Ginto\Controllers;

use Core\Controller;

class ApiController extends Controller
{
    protected $storageFile;
    protected $db;

    public function __construct($db = null)
    {
        parent::__construct();

        if ($db === null) {
            if (class_exists('Ginto\\Core\\Database')) {
                $db = \Ginto\Core\Database::getInstance();
            } else {
                $db = null;
            }
        }

        $this->db = $db;
        $this->storageFile = __DIR__ . '/../../storage/api_messages.json';

        if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
    }

    private function isCurrentUserAdminStrict(): bool
    {
        $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
        if ($userId <= 0 || !$this->db) {
            return false;
        }

        try {
            $user = $this->db->get('users', ['role_id', 'is_admin'], ['id' => $userId]);
            if (!is_array($user)) {
                return false;
            }

            $roleId = (int)($user['role_id'] ?? 0);
            $isAdminFlag = (int)($user['is_admin'] ?? 0) === 1;

            return $isAdminFlag || in_array($roleId, [1, 2], true);
        } catch (\Throwable $_) {
            return false;
        }
    }

    private function ensureStorage(): void
    {
        $dir = dirname($this->storageFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (!file_exists($this->storageFile)) {
            @file_put_contents($this->storageFile, "[]");
        }
    }

    /**
     * Attempt to write a file safely under project root.
     * Returns ['success'=>bool,'message'=>string,'path'=>string]
     */
    private function attemptWriteFile(string $filename, string $content, bool $overwrite = false, bool $allowOutside = false): array
    {
        if (!$filename) return ['success' => false, 'message' => 'Missing filename', 'path' => ''];

        if (strpos($filename, '..') !== false) {
            return ['success' => false, 'message' => 'Invalid filename (contains traversal)', 'path' => ''];
        }

        $isAbsolute = str_starts_with($filename, '/') || preg_match('/^[A-Za-z]:\\\\/', $filename);
        $allowAny = (!empty($_ENV['MCP_ALLOW_ANY_FILE_WRITE']) && ($_ENV['MCP_ALLOW_ANY_FILE_WRITE'] === '1')) || (!empty($_ENV['ALLOW_MCP_ANY_FILE_WRITE']) && ($_ENV['ALLOW_MCP_ANY_FILE_WRITE'] === '1'));
        if ($isAbsolute && !$allowOutside) {
            return ['success' => false, 'message' => 'Absolute paths not permitted from this caller', 'path' => ''];
        }
        if ($isAbsolute && !$allowAny) {
            return ['success' => false, 'message' => 'Absolute paths are disabled by server configuration', 'path' => ''];
        }

        $normalized = $isAbsolute ? $filename : ltrim(str_replace('\\', '/', $filename), '/');

        if (!preg_match('/^[A-Za-z0-9._\\/\-]+$/', $normalized)) {
            return ['success' => false, 'message' => 'Filename contains disallowed characters', 'path' => ''];
        }

        $root = realpath(dirname(dirname(__DIR__))) ?: rtrim(dirname(dirname(__DIR__)), DIRECTORY_SEPARATOR);
        if ($isAbsolute) {
            $target = $normalized;
        } else {
            $target = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
        }

        $parent = dirname($target);
        if (!is_dir($parent)) {
            @mkdir($parent, 0775, true);
        }

        if (!$isAbsolute) {
            $parentReal = realpath($parent);
            if (!$parentReal || str_starts_with($parentReal, $root) === false) {
                return ['success' => false, 'message' => 'Invalid path - outside project root', 'path' => ''];
            }
        }

        if (is_file($target) && !$overwrite) {
            return ['success' => false, 'message' => 'File already exists (set overwrite to true to replace)', 'path' => $target];
        }

        try {
            $written = @file_put_contents($target, $content);
            if ($written === false) {
                $err = error_get_last();
                $errMsg = $err ? ($err['message'] ?? json_encode($err)) : 'unknown error';
                error_log("attemptWriteFile: write failed for $target — $errMsg");
                return ['success' => false, 'message' => 'Failed to write file: ' . $errMsg, 'path' => $target];
            }
            return ['success' => true, 'message' => 'File written', 'path' => $target];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Write failed: ' . $e->getMessage(), 'path' => $target];
        }
    }

    /**
     * Sanitize an absolute/internal path for returning to clients.
     */
    private function sanitizeClientPath(?string $requestedPath, ?string $internalPath): string
    {
        $req = (string)($requestedPath ?? '');
        if ($req !== '') {
            if (!str_starts_with($req, '/') && !preg_match('/^[A-Za-z]:\\\\/', $req)) {
                return ltrim(str_replace('\\', '/', $req), '/');
            }
        }

        $root = realpath(dirname(dirname(__DIR__))) ?: rtrim(dirname(dirname(__DIR__)), DIRECTORY_SEPARATOR);
        $abs = (string)($internalPath ?? '');
        $real = realpath($abs) ?: $abs;
        if ($real && $root && str_starts_with($real, $root)) {
            $rel = substr($real, strlen($root));
            return ltrim(str_replace('\\', '/', $rel), '/');
        }

        return basename($abs ?: $req);
    }

    /**
     * Parse file directive blocks from assistant text.
     */
    private function parseFileDirective(string $text): ?array
    {
        $t = trim($text);
        if ($t === '') return null;

        if (str_starts_with($t, '{')) {
            $j = json_decode($t, true);
            if (is_array($j)) {
                if (!empty($j['file']) && is_array($j['file'])) {
                    $p = trim($j['file']['path'] ?? '');
                    $c = $j['file']['content'] ?? ($j['file']['text'] ?? '');
                    $commit = !empty($j['file']['commit']) ? true : false;
                    $commitMsg = (string)($j['file']['commit_message'] ?? $j['file']['commitMessage'] ?? '');
                    if ($p !== '') return ['path' => $p, 'content' => (string)$c, 'commit' => $commit, 'commit_message' => ($commitMsg ?: null)];
                }
                if (!empty($j['path']) && isset($j['content'])) {
                    $commit = !empty($j['commit']) ? true : false;
                    $commitMsg = (string)($j['commit_message'] ?? $j['commitMessage'] ?? '');
                    return ['path' => trim($j['path']), 'content' => (string)$j['content'], 'commit' => $commit, 'commit_message' => ($commitMsg ?: null)];
                }
            }
        }

        if (preg_match('/```file\s*:?\\s*([^\n\r]+)\r?\n([\s\S]*?)```/i', $t, $m)) {
            $header = trim($m[1]);
            $path = '';
            $commit = false;
            $commitMsg = null;

            if (preg_match('/^\s*"([^"]+)"\s*(.*)$/', $header, $hmatch)) {
                $path = $hmatch[1];
                $meta = trim($hmatch[2]);
            } elseif (preg_match('/^\s*([^\s]+)/u', $header, $hmatch)) {
                $path = $hmatch[1];
                $meta = trim(substr($header, strlen($path)));
            } else {
                $path = trim($header);
                $meta = '';
            }

            if ($meta) {
                if (preg_match('/commit\s*[:=]\s*(true|1|yes)/i', $meta)) { $commit = true; }
                if (preg_match('/commit[_\- ]?message\s*[:=]\s*"([^"]+)"/i', $meta, $cm)) { $commitMsg = $cm[1]; }
                elseif (preg_match('/commit[_\- ]?message\s*[:=]\s*([^\s].*)/i', $meta, $cm)) { $commitMsg = trim($cm[1]); }
            }

            $content = $m[2];
            return ['path' => $path, 'content' => $content, 'commit' => $commit, 'commit_message' => $commitMsg];
        }

        if (preg_match('/^FILE:\s*([^\r\n]+)\r?\n(?:-+\r?\n)?([\s\S]*)/i', $t, $m)) {
            $pathRaw = trim($m[1]);
            $commit = false; $commitMsg = null;
            $path = $pathRaw;
            $content = $m[2];
            return ['path' => $path, 'content' => $content, 'commit' => $commit, 'commit_message' => $commitMsg];
        }

        return null;
    }

    /**
     * Extract likely file content from arbitrary assistant text.
     */
    private function extractFileContentFromText(string $text, ?string $expectedPath = null): string
    {
        $t = trim($text);
        if ($t === '') return '';

        try {
            $parsed = $this->parseFileDirective($t);
            if (is_array($parsed) && isset($parsed['content'])) {
                return (string)$parsed['content'];
            }
        } catch (\Throwable $_) { }

        if (preg_match('/```\s*([a-zA-Z0-9_-]*)\s*\n([\s\S]*?)```/m', $t, $m)) {
            $lang = strtolower(trim($m[1] ?? ''));
            $body = rtrim($m[2]);
            if ($lang === '' || in_array($lang, ['text','txt','text/plain'])) {
                return $body;
            }
            if (in_array($lang, ['bash','sh','shell'])) {
                if (preg_match('/^\s*echo\s+["\']?(.*?)["\']?\s*>\s*([^\s;]+)\s*$/m', trim($body), $em)) {
                    $candidate = $em[1]; $target = $em[2];
                    if (!$expectedPath || basename($expectedPath) === basename($target)) return $candidate;
                }
                if (preg_match('/cat\s*>\s*([^\s]+)\s*<<\(?["\']?([A-Za-z0-9_]+)["\']?\)?\r?\n([\s\S]*?)\r?\n\2/m', $body, $cm)) {
                    $target = $cm[1]; $content = $cm[3];
                    if (!$expectedPath || basename($expectedPath) === basename($target)) return rtrim($content);
                }
                if (preg_match('/^["\'](.*)["\']$/s', trim($body), $q)) { return $q[1]; }
                return $body;
            }
            return $body;
        }

        if (preg_match('/echo\s+["\']?(.*?)["\']?\s*>\s*([^\s;]+)/i', $t, $em2)) {
            $candidate = $em2[1]; $target = $em2[2];
            if (!$expectedPath || basename($expectedPath) === basename($target)) return $candidate;
        }

        return $t;
    }

    /**
     * Parse simple task lists from text.
     */
    private function parseTasksFromText(string $text): ?array
    {
        $t = trim($text);
        if ($t === '') return null;

        try {
            $decoded = json_decode($t, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $_) { $decoded = null; }

        if (is_array($decoded)) {
            if (!empty($decoded['tasks']) && is_array($decoded['tasks'])) return array_values($decoded['tasks']);
            $isList = true;
            foreach ($decoded as $item) {
                if (!is_array($item)) { $isList = false; break; }
                if (!isset($item['task']) && !isset($item['title']) && !isset($item['description'])) { $isList = false; break; }
            }
            if ($isList) return array_values($decoded);
        }

        if (preg_match_all('/^\s*[-*+]\s*(.+?)(?:[:\-]\s*(.+))?$/m', $t, $ms, PREG_SET_ORDER)) {
            $tasks = [];
            foreach ($ms as $row) {
                $title = trim($row[1]); $desc = isset($row[2]) ? trim($row[2]) : '';
                $tasks[] = ['task' => $title, 'description' => $desc];
            }
            if (!empty($tasks)) return $tasks;
        }

        return null;
    }

    /**
     * Add files to git index and create commit.
     */
    private function safeGitCommit(array $paths, string $message = ''): array
    {
        $msg = trim($message ?: 'Automated file creation via API');
        $root = realpath(dirname(dirname(__DIR__))) ?: dirname(dirname(__DIR__));
        foreach ($paths as $p) {
            $real = realpath($p);
            if (!$real || !str_starts_with($real, $root)) {
                return ['success' => false, 'message' => 'Invalid commit paths: ' . $p, 'commit' => null];
            }
        }

        try {
            $esc = function($s) { return str_replace("'", "'\\\'", $s); };
            $quoted = array_map(function($p) use ($esc) { return "'" . $esc($p) . "'"; }, $paths);
            $cmdAdd = "git -C '" . addslashes($root) . "' add " . implode(' ', $quoted) . " 2>&1";
            $outAdd = []; $code = 0; exec($cmdAdd, $outAdd, $code);
            if ($code !== 0) {
                return ['success' => false, 'message' => 'git add failed: ' . implode("\n", $outAdd), 'commit' => null];
            }
            $msgEsc = addslashes($msg);
            $cmdCommit = "git -C '" . addslashes($root) . "' commit -m '" . $msgEsc . "' 2>&1";
            $outCommit = []; $code = 0; exec($cmdCommit, $outCommit, $code);
            if ($code !== 0) {
                $joined = implode('\n', $outCommit);
                if (stripos($joined, 'nothing to commit') !== false) {
                    return ['success' => true, 'message' => 'Nothing to commit', 'commit' => null];
                }
                return ['success' => false, 'message' => 'git commit failed: ' . $joined, 'commit' => null];
            }
            return ['success' => true, 'message' => 'Committed', 'commit' => null];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Git commit exception: ' . $e->getMessage(), 'commit' => null];
        }
    }

    public function index()
    {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'PHP API endpoint active',
            'endpoints' => [
                'GET /api' => 'this info',
                'POST /api' => 'send message payload {"message":...,"channel":...}',
                'GET /api/messages' => 'list stored messages',
            ],
        ]);
        exit();
    }

    public function post()
    {
        header('Content-Type: application/json');
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method Not Allowed. Use POST.']);
            exit();
        }

        $this->ensureStorage();

        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid JSON body']);
            exit();
        }

        // Support two payload shapes:
        // 1) { message, channel }
        // 2) { prompt, html, userID } used by /code frontend
        $message = '';
        $channel = 'default';
        $isPromptRequest = false;

        if (isset($input['message'])) {
            $message = trim($input['message']);
            $channel = trim($input['channel'] ?? 'default');
        } elseif (isset($input['prompt'])) {
            $isPromptRequest = true;
            $prompt = trim($input['prompt']);
            $html = $input['html'] ?? '';
            $userID = $input['userID'] ?? ($_SESSION['user'] ?? 'guest');
            $message = $prompt; // store prompt as message
            $channel = 'code';
        }

        if ($message === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing "message" field']);
            exit();
        }

        $entry = [
            'timestamp' => time(),
            'channel' => $channel,
            'message' => $message,
            'sender' => $_SESSION['user_id'] ?? null,
        ];

        // Append to JSON array safely
        $fp = fopen($this->storageFile, 'c+');
        if (!$fp) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Could not open storage file']);
            exit();
        }
        flock($fp, LOCK_EX);
        $contents = stream_get_contents($fp);
        $items = json_decode($contents, true);
        if (!is_array($items)) {
            $items = [];
        }
        $items[] = $entry;
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($items, JSON_PRETTY_PRINT));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        $responsePayload = ['success' => true, 'entry' => $entry];

        // Optionally forward to Novita if credentials are present
        $novitaKey = $this->getEnvVar('NOVITA_API_KEY');
        $novitaUrl = $this->getEnvVar('NOVITA_API_URL') ?: 'https://api.novita.ai/v3/openai/chat/completions';
        $novitaResult = null;

        if ($novitaKey && $novitaUrl) {
            // If this is a prompt request from /code, attempt streaming response back to client
            if ($isPromptRequest) {
                // Build messages array similar to Node implementation
                $messages = [];
                $systemPrompt = $this->getDynamicSystemPrompt();
                $messages[] = ['role' => 'system', 'content' => $systemPrompt];
                if (!empty($input['previousPrompt'])) {
                    $messages[] = ['role' => 'user', 'content' => $input['previousPrompt']];
                }
                if (!empty($html)) {
                    $messages[] = ['role' => 'assistant', 'content' => $html];
                }
                $messages[] = ['role' => 'user', 'content' => $prompt];

                $payload = [
                    'model' => $this->getEnvVar('NOVITA_MODEL') ?: 'deepseek/deepseek-v3-0324',
                    'stream' => true,
                    'messages' => $messages,
                    'temperature' => 1,
                    'max_tokens' => 16000,
                    'top_p' => 1,
                ];

                // Prepare client response headers for streaming plain text (HTML)
                if (!headers_sent()) {
                    header('Content-Type: text/plain');
                    header('Cache-Control: no-cache');
                    header('Connection: keep-alive');
                    http_response_code(200);
                }
                // Disable output buffering
                while (ob_get_level() > 0) { ob_end_flush(); }
                @ob_implicit_flush(true);
                @ignore_user_abort(true);

                // Use curl to stream and echo chunks as they arrive
                $ch = curl_init($novitaUrl);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Accept: text/event-stream',
                    'Authorization: Bearer ' . $novitaKey,
                ]);
                curl_setopt($ch, CURLOPT_TIMEOUT, 0);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

                $sseBuffer = '';
                curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($curl, $data) use (&$sseBuffer) {
                    $sseBuffer .= $data;
                    // Process complete SSE events separated by a blank line.
                    // Accept both CRLF (\r\n\r\n) and LF (\n\n) boundaries.
                    while (preg_match('/\r?\n\r?\n/', $sseBuffer)) {
                        // find first blank-line boundary (either \r\n\r\n or \n\n)
                        if (($pos = strpos($sseBuffer, "\r\n\r\n")) !== false) {
                            $event = substr($sseBuffer, 0, $pos);
                            $sseBuffer = substr($sseBuffer, $pos + 4);
                        } else {
                            $pos = strpos($sseBuffer, "\n\n");
                            $event = substr($sseBuffer, 0, $pos);
                            $sseBuffer = substr($sseBuffer, $pos + 2);
                        }

                        $lines = preg_split('/\r?\n/', $event);
                        $dataPayload = '';
                        foreach ($lines as $line) {
                            if (strpos($line, 'data:') === 0) {
                                $dataPayload .= substr($line, 5);
                            }
                        }

                        $dataPayload = trim($dataPayload);
                        if ($dataPayload === '' ) {
                            continue;
                        }
                        if ($dataPayload === '[DONE]') {
                            // End of stream signal
                            if (function_exists('fastcgi_finish_request')) {
                                @fastcgi_finish_request();
                            }
                            return strlen($data);
                        }

                        $decoded = json_decode($dataPayload, true);
                        if (is_array($decoded)) {
                            // Try to extract delta content like Node's SDK does
                            $part = null;
                            if (isset($decoded['choices'][0]['delta']['content'])) {
                                $part = $decoded['choices'][0]['delta']['content'];
                            } elseif (isset($decoded['choices'][0]['delta'])) {
                                // In some responses delta may be a string
                                $delta = $decoded['choices'][0]['delta'];
                                if (is_string($delta)) $part = $delta;
                            } elseif (isset($decoded['choices'][0]['text'])) {
                                $part = $decoded['choices'][0]['text'];
                            }

                            if ($part !== null) {
                                echo $part;
                                if (function_exists('fastcgi_finish_request')) {
                                    @flush();
                                } else {
                                    @ob_flush(); @flush();
                                }
                            }
                        } else {
                            // Not JSON: emit raw payload (fallback)
                            echo $dataPayload;
                            if (function_exists('fastcgi_finish_request')) {
                                @flush();
                            } else {
                                @ob_flush(); @flush();
                            }
                        }
                    }
                    return strlen($data);
                });

                // Execute streaming request
                $execResult = curl_exec($ch);
                $curlErr = curl_error($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($curlErr) {
                    // If streaming failed, include error in response payload fallback
                    $responsePayload['novita'] = ['ok' => false, 'error' => 'curl_error', 'message' => $curlErr];
                } else {
                    $responsePayload['novita'] = ['ok' => true, 'http_code' => $httpCode, 'streamed' => true];
                }

                // We already streamed output to client; end execution.
                exit();
            } else {
                // Non-prompt requests: simple non-streaming forward
                $novitaResult = $this->forwardToNovita($entry, $novitaUrl, $novitaKey);
                $responsePayload['novita'] = $novitaResult;
            }
        }

        // If this was a prompt request, prefer returning Novita's body directly
        if ($isPromptRequest) {
            if ($novitaResult && isset($novitaResult['ok']) && $novitaResult['ok'] === true) {
                $body = $novitaResult['body'] ?? '';
                if (is_array($body)) {
                    // If Novita returned structured JSON, try common fields
                    if (isset($body['content'])) {
                        echo is_string($body['content']) ? $body['content'] : json_encode($body['content']);
                    } elseif (isset($body['result'])) {
                        echo is_string($body['result']) ? $body['result'] : json_encode($body['result']);
                    } else {
                        echo json_encode($body);
                    }
                } else {
                    echo $body;
                }
                exit();
            }
            // If Novita not configured or failed, fall back to returning stored entry JSON
            echo json_encode($responsePayload);
            exit();
        }

        echo json_encode($responsePayload);
        exit();
    }

    /**
     * Read environment variable from getenv/$_ENV or fall back to parsing .env
     */
    private function getEnvVar(string $name): ?string
    {
        $val = getenv($name);
        if ($val !== false && $val !== '') {
            return $val;
        }
        if (isset($_ENV[$name]) && $_ENV[$name] !== '') {
            return $_ENV[$name];
        }
        // Try parsing root .env
        $envPath = __DIR__ . '/../../.env';
        if (file_exists($envPath)) {
            $pairs = $this->parseDotEnv($envPath);
            if (isset($pairs[$name]) && $pairs[$name] !== '') {
                return $pairs[$name];
            }
        }
        return null;
    }

    private function sanitizeProviderName(?string $provider): string
    {
        $provider = strtolower(trim((string)$provider));
        return preg_replace('/[^a-z0-9_\-]/', '', $provider) ?: '';
    }

    /**
     * Normalize a model name for a given provider.
     * Groq requires the "openai/" prefix for gpt-oss-120b; Cerebras does not.
     */
    private function normalizeModelForProvider(string $provider, string $model): string
    {
        if ($provider === 'groq' && $model === 'gpt-oss-120b') {
            return 'openai/gpt-oss-120b';
        }
        if ($provider === 'cerebras' && $model === 'openai/gpt-oss-120b') {
            return 'gpt-oss-120b';
        }
        return $model;
    }

    private function sanitizeOpenAiCompatibleBaseUrl(?string $baseUrl): ?string
    {
        $raw = trim(strip_tags((string)$baseUrl));
        if ($raw === '') {
            return null;
        }

        $raw = preg_replace('#^https?://#i', '', $raw) ?? $raw;
        $raw = ltrim($raw, '/');
        $domain = preg_split('/[\/?#]/', $raw, 2)[0] ?? '';
        $domain = strtolower(trim($domain));

        if ($domain === '' || !preg_match('/^[a-z0-9.-]+(?::\d{2,5})?$/i', $domain) || !str_contains($domain, '.')) {
            return null;
        }

        return 'https://' . $domain . '/v1/';
    }

    private function getTunnelBaseUrlSettingKey(?int $userId, bool $isAdmin): string
    {
        if ($isAdmin || empty($userId)) {
            return 'llm_ginto_tunnel_base_url';
        }
        return 'llm_ginto_tunnel_base_url_user_' . (int)$userId;
    }

    private function getTunnelBaseUrlPerKeySettingKey(int $keyId): string
    {
        return 'llm_ginto_tunnel_base_url_key_' . (int)$keyId;
    }

    private function saveTunnelBaseUrl(?string $baseUrl, ?int $userId, bool $isAdmin): void
    {
        if (!$this->db) {
            return;
        }

        $value = $this->sanitizeOpenAiCompatibleBaseUrl($baseUrl);
        if (empty($value)) {
            $value = $this->sanitizeOpenAiCompatibleBaseUrl($this->getEnvVar('GINTO_TUNNEL_BASE_URL') ?: 'https://ollama.ginto.ai/v1/');
        }
        if (empty($value)) {
            return;
        }

        $key = $this->getTunnelBaseUrlSettingKey($userId, $isAdmin);
        $exists = $this->db->get('settings', 'id', ['key' => $key]);
        if ($exists) {
            $this->db->update('settings', [
                'value' => $value,
                'type' => 'string',
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['key' => $key]);
        } else {
            $this->db->insert('settings', [
                'key' => $key,
                'value' => $value,
                'type' => 'string',
                'group_name' => 'system',
                'description' => 'OpenAI-compatible base URL for Ginto Tunnel provider',
                'is_public' => 0,
            ]);
        }
    }

    private function saveTunnelBaseUrlForKey(?string $baseUrl, int $keyId): void
    {
        if (!$this->db || $keyId <= 0) {
            return;
        }

        $value = $this->sanitizeOpenAiCompatibleBaseUrl($baseUrl);
        if (empty($value)) {
            $value = $this->sanitizeOpenAiCompatibleBaseUrl($this->getEnvVar('GINTO_TUNNEL_BASE_URL') ?: 'https://ollama.ginto.ai/v1/');
        }
        if (empty($value)) {
            return;
        }

        $key = $this->getTunnelBaseUrlPerKeySettingKey($keyId);
        $exists = $this->db->get('settings', 'id', ['key' => $key]);
        if ($exists) {
            $this->db->update('settings', [
                'value' => $value,
                'type' => 'string',
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['key' => $key]);
        } else {
            $this->db->insert('settings', [
                'key' => $key,
                'value' => $value,
                'type' => 'string',
                'group_name' => 'system',
                'description' => 'OpenAI-compatible base URL for a specific Ginto Tunnel key',
                'is_public' => 0,
            ]);
        }
    }

    private function resolveTunnelBaseUrl(?int $userId, bool $isAdmin): string
    {
        $fallback = $this->sanitizeOpenAiCompatibleBaseUrl($this->getEnvVar('GINTO_TUNNEL_BASE_URL') ?: 'https://ollama.ginto.ai/v1/')
            ?: 'https://ollama.ginto.ai/v1/';

        if (!$this->db) {
            return $fallback;
        }

        $keys = [];
        if (!$isAdmin && !empty($userId)) {
            $keys[] = $this->getTunnelBaseUrlSettingKey($userId, false);
        }
        $keys[] = $this->getTunnelBaseUrlSettingKey(null, true);

        foreach ($keys as $k) {
            $row = $this->db->get('settings', ['value'], ['key' => $k]);
            if (!empty($row['value'])) {
                $candidate = $this->sanitizeOpenAiCompatibleBaseUrl((string)$row['value']);
                if (!empty($candidate)) {
                    return $candidate;
                }
            }
        }

        return $fallback;
    }

    private function resolveTunnelBaseUrlForKey(int $keyId, ?int $userId, bool $isAdmin): string
    {
        $fallback = $this->resolveTunnelBaseUrl($userId, $isAdmin);
        if (!$this->db || $keyId <= 0) {
            return $fallback;
        }

        $row = $this->db->get('settings', ['value'], ['key' => $this->getTunnelBaseUrlPerKeySettingKey($keyId)]);
        $candidate = $this->sanitizeOpenAiCompatibleBaseUrl((string)($row['value'] ?? ''));
        return !empty($candidate) ? $candidate : $fallback;
    }

    private function fetchOpenAiCompatibleModels(string $baseUrl, string $apiKey, string $provider = 'ginto_tunnel'): array
    {
        if (empty($apiKey) || empty($baseUrl)) {
            return [];
        }

        $modelsUrl = rtrim($baseUrl, '/') . '/models';
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ];
        if ($provider === 'ginto_tunnel') {
            $headers[] = 'X-Ginto-Tunnel-Key: ' . $apiKey;
        }

        try {
            $ch = curl_init($modelsUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_HTTPHEADER => $headers,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200 || !$response) {
                return [];
            }

            $decoded = json_decode((string)$response, true);
            $items = [];

            if (is_array($decoded) && array_is_list($decoded)) {
                $items = $decoded;
            } elseif (isset($decoded['data']) && is_array($decoded['data'])) {
                $items = $decoded['data'];
            } elseif (isset($decoded['models']) && is_array($decoded['models'])) {
                $items = $decoded['models'];
            } elseif (isset($decoded['data']['models']) && is_array($decoded['data']['models'])) {
                $items = $decoded['data']['models'];
            } elseif (isset($decoded['result']['data']) && is_array($decoded['result']['data'])) {
                $items = $decoded['result']['data'];
            }

            if (empty($items)) {
                return [];
            }

            $models = [];
            $registry = class_exists('\\App\\Core\\LLM\\ProviderRegistry')
                ? \App\Core\LLM\ProviderRegistry::getInstance()->setDatabase($this->db)
                : null;

            foreach ($items as $item) {
                if (is_string($item)) {
                    $mid = trim($item);
                    if ($mid === '') {
                        continue;
                    }
                    $models[] = [
                        'id' => $mid,
                        'name' => $mid,
                        'capabilities' => $registry ? $registry->detectCapabilities($mid) : [],
                    ];
                    continue;
                }

                if (!is_array($item)) {
                    continue;
                }

                $mid = (string)($item['id'] ?? ($item['name'] ?? ($item['model'] ?? '')));
                if ($mid === '') {
                    continue;
                }
                $models[] = [
                    'id' => $mid,
                    'name' => (string)($item['name'] ?? $mid),
                    'capabilities' => $registry ? $registry->detectCapabilities($mid) : [],
                ];
            }

            return $models;
        } catch (\Throwable $_) {
            return [];
        }
    }

    private function isDuplicateKeyError(\Throwable $e): bool
    {
        $msg = strtolower($e->getMessage());
        return str_contains($msg, 'sqlstate[23000]')
            || str_contains($msg, 'duplicate entry')
            || str_contains($msg, '1062');
    }

    private function upsertExistingProviderKeyOnDuplicate(string $provider, string $apiKey, ?string $keyName, string $tier, int $isDefault, ?int $currentUserId): ?int
    {
        if (!$this->db) {
            return null;
        }

        $row = $this->db->get('provider_keys', ['id'], [
            'provider' => $provider,
            'api_key' => $apiKey,
        ]);

        if (empty($row['id'])) {
            return null;
        }

        $id = (int)$row['id'];

        if ($isDefault) {
            $this->db->update('provider_keys', ['is_default' => 0], ['provider' => $provider]);
        }

        $update = [
            'key_name' => $keyName,
            'tier' => $tier,
            'is_active' => 1,
        ];
        if ($isDefault) {
            $update['is_default'] = 1;
        }
        if ($currentUserId !== null) {
            $update['user_id'] = $currentUserId;
        }

        $this->db->update('provider_keys', $update, ['id' => $id]);
        return $id;
    }

    private function parseDotEnv(string $path): array
    {
        $data = [];
        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) {
            return $data;
        }
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }
            if (strpos($line, '=') === false) {
                continue;
            }
            [$k, $v] = array_map('trim', explode('=', $line, 2));
            $v = trim($v, "\"'");
            $data[$k] = $v;
        }
        return $data;
    }

    private static function maskKey(string $key): string
    {
        $k = trim($key);
        if ($k === '') return '';
        $len = strlen($k);
        if ($len <= 8) return substr($k, 0, 2) . str_repeat('*', max(0, $len - 4)) . substr($k, -2);
        return substr($k, 0, 4) . str_repeat('*', max(0, $len - 8)) . substr($k, -4);
    }

    private function getDynamicSystemPrompt(): string
    {
        // Extremely strict system prompt: the model MUST output only a single HTML document
        // starting with <!DOCTYPE html> and ending with </html>. No explanations, no
        // headings, no code fences, no preface, and no trailing commentary of any kind.
        // If the model cannot comply, it must output exactly: [ERROR: cannot comply]
        $currentDate = date('l, F j, Y');
        $finalSystemPrompt = "You are an assistant that MUST output exactly one HTML document and nothing else. " .
            "Start the output with '<!DOCTYPE html>' and end the output with '</html>'. " .
            "Do NOT include any explanation, summary, preface, headings, code fences, or any text outside the HTML document. " .
            "Do NOT apologize, do NOT state 'Here's', 'Below is', or similar. " .
            "The HTML should be ready to copy-paste into an editor. " .
            "When styling, you may use TailwindCSS or a single consistent CSS approach, but include only what is necessary inside the HTML. " .
            "Do not include any JSON wrappers or streaming markers. " .
            "If you cannot produce only the HTML document for any reason, output exactly the following single line and nothing else: [ERROR: cannot comply]. " .
            "For reference, today is " . $currentDate . ".";
        return $finalSystemPrompt;
    }

    private function forwardToNovita(array $entry, string $url, string $apiKey): array
    {
        $payload = [
            'message' => $entry['message'] ?? '',
            'channel' => $entry['channel'] ?? 'default',
            'timestamp' => $entry['timestamp'] ?? time(),
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ]);

        $resp = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err) {
            return ['ok' => false, 'error' => 'curl_error', 'message' => $err];
        }
        $decoded = json_decode($resp, true);
        return ['ok' => true, 'http_code' => $code, 'body' => $decoded ?? $resp];
    }

    public function getMessages()
    {
        header('Content-Type: application/json');
        $this->ensureStorage();
        $contents = @file_get_contents($this->storageFile);
        $items = json_decode($contents, true);
        if (!is_array($items)) {
            $items = [];
        }
        // return last 100 messages
        $last = array_slice($items, -100);
        echo json_encode(['success' => true, 'count' => count($last), 'messages' => $last]);
        exit();
    }

    /**
     * Return available providers and models for the UI.
     * Minimal safe implementation: return empty providers if none configured.
     */
    public function models()
    {
        header('Content-Type: application/json');

        $isAdmin = $this->isCurrentUserAdminStrict();

        $currentUserId = $_SESSION['user_id'] ?? null;

        // Build providers list from DB-backed keys and .env keys so UI can show configured providers
        $providers = [];
        $db = $this->db;

        // 1) DB-backed keys
        if ($db) {
            try {
                if (class_exists('App\\Core\\ProviderKeyManager')) {
                    $manager = new \App\Core\ProviderKeyManager($db);
                    $rows = $manager->getAllKeys();
                } else {
                    $rows = $db->select('provider_keys', '*');
                }

                if (is_array($rows)) {
                    foreach ($rows as $r) {
                        $p = $r['provider'] ?? 'unknown';
                        if (!isset($providers[$p])) {
                            $providers[$p] = [
                                'provider' => $p,
                                'configured' => true,
                                'db_keys' => [],
                                'env_key' => false,
                            ];
                        }
                        $providers[$p]['db_keys'][] = [
                            'id' => $r['id'] ?? null,
                            'key_name' => $r['key_name'] ?? null,
                            'api_key_masked' => self::maskKey($r['api_key'] ?? ''),
                            'tier' => $r['tier'] ?? 'basic',
                            'user_id' => $r['user_id'] ?? null,
                        ];
                        // Mark whether current session user has at least one key for this provider
                        if (!empty($_SESSION['user_id']) && ($r['user_id'] ?? null) == ($_SESSION['user_id'] ?? null)) {
                            $providers[$p]['has_user_key'] = true;
                        }
                    }
                }
            } catch (\Throwable $e) {
                // ignore DB errors
            }
        }

        // 2) .env keys
        $envCandidates = [
            'GROQ_API_KEY' => 'groq',
            'CEREBRAS_API_KEY' => 'cerebras',
            'OPENAI_API_KEY' => 'openai',
            'ANTHROPIC_API_KEY' => 'anthropic',
            'TOGETHER_API_KEY' => 'together',
            'FIREWORKS_API_KEY' => 'fireworks',
            'OLLAMA_API_KEY' => 'ollama',
            'GINTO_TUNNEL_API_KEY' => 'ginto_tunnel',
            'NOVITA_API_KEY' => 'novita'
        ];

        foreach ($envCandidates as $envKey => $providerName) {
            $val = $this->getEnvVar($envKey);
            if (!empty($val)) {
                if (!isset($providers[$providerName])) {
                    $providers[$providerName] = [
                        'provider' => $providerName,
                        'configured' => true,
                        'db_keys' => [],
                        'env_key' => true,
                    ];
                } else {
                    $providers[$providerName]['env_key'] = true;
                    $providers[$providerName]['configured'] = true;
                }
                $providers[$providerName]['env_key_masked'] = self::maskKey($val);
            }
        }

        if (isset($providers['ginto_tunnel'])) {
            $providers['ginto_tunnel']['display_name'] = 'Ginto Tunnel';
            $providers['ginto_tunnel']['base_url'] = $this->resolveTunnelBaseUrl(isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null, $isAdmin);
        }

        // Admin-only local Ollama detection toggle from /live MCP Configuration
        // When enabled, expose Ollama in provider list even without OLLAMA_API_KEY.
        $detectOllamaRaw = $this->getEnvVar('DETECT_OLLAMA');
        $detectOllamaEnabled = in_array(strtoupper((string)$detectOllamaRaw), ['TRUE', '1', 'YES', 'ON'], true);
        if ($isAdmin && $detectOllamaEnabled) {
            if (!isset($providers['ollama'])) {
                $providers['ollama'] = [
                    'provider' => 'ollama',
                    'configured' => true,
                    'db_keys' => [],
                    'env_key' => false,
                ];
            } else {
                $providers['ollama']['configured'] = true;
            }
        }

        // Attempt to enrich each configured provider with available models and capabilities
        if (class_exists('\App\Core\LLM\ProviderRegistry')) {
            try {
                $registry = \App\Core\LLM\ProviderRegistry::getInstance()->setDatabase($this->db);
                foreach ($providers as $pname => &$pdata) {
                    // Set a friendly display name if registry knows one
                    $cfg = $registry->getProviderConfig($pname);
                    if (is_array($cfg) && !empty($cfg['display_name'])) {
                        $pdata['display_name'] = $cfg['display_name'];
                    }

                    // Fetch models (may be empty if provider not reachable or not configured)
                    $models = $registry->getModels($pname, false);
                    if (is_array($models) && count($models) > 0) {
                        // Provide simple model id list for UI; also provide capabilities map
                        $pdata['models'] = array_map(function($m) { return $m['id'] ?? (is_string($m) ? $m : null); }, $models);
                        $caps = [];
                        foreach ($models as $m) {
                            $mid = $m['id'] ?? (is_string($m) ? $m : null);
                            if ($mid) {
                                $caps[$mid] = $m['capabilities'] ?? $m['capability'] ?? [];
                            }
                        }
                        $pdata['capabilities'] = $caps;

                        // For providers that return nice display names, surface them
                        $named = [];
                        foreach ($models as $m) {
                            $mid = $m['id'] ?? (is_string($m) ? $m : null);
                            $mname = $m['name'] ?? $mid;
                            if ($mid) $named[] = ['id' => $mid, 'name' => $mname];
                        }
                        if (!empty($named)) {
                            $pdata['models_with_names'] = $named;
                        }
                    } else {
                        // Ensure keys exist to avoid frontend errors
                        if (!isset($pdata['models'])) $pdata['models'] = [];
                        if (!isset($pdata['capabilities'])) $pdata['capabilities'] = [];
                    }

                    if ($pname === 'ginto_tunnel' && $db && !empty($pdata['db_keys']) && is_array($pdata['db_keys'])) {
                        $composedModels = [];
                        $composedCaps = [];

                        foreach ($pdata['db_keys'] as $keyInfo) {
                            $keyId = (int)($keyInfo['id'] ?? 0);
                            $ownerId = isset($keyInfo['user_id']) ? (int)$keyInfo['user_id'] : null;

                            if ($keyId <= 0) {
                                continue;
                            }

                            if (!$isAdmin) {
                                if ($currentUserId === null || $ownerId === null || $ownerId !== (int)$currentUserId) {
                                    continue;
                                }
                            }

                            $row = $db->get('provider_keys', ['id', 'api_key', 'provider', 'is_active', 'user_id'], ['id' => $keyId]);
                            if (empty($row) || ($row['provider'] ?? '') !== 'ginto_tunnel' || (int)($row['is_active'] ?? 0) !== 1) {
                                continue;
                            }

                            if (!$isAdmin && $currentUserId !== null && isset($row['user_id']) && (int)$row['user_id'] !== (int)$currentUserId) {
                                continue;
                            }

                            $endpointBaseUrl = $this->resolveTunnelBaseUrlForKey($keyId, $currentUserId !== null ? (int)$currentUserId : null, $isAdmin);
                            $domainLabel = preg_replace('#^https?://#i', '', rtrim((string)$endpointBaseUrl, '/'));
                            $domainLabel = preg_replace('#/v1$#i', '', (string)$domainLabel) ?: 'ginto_tunnel';

                            $perKeyModels = $this->fetchOpenAiCompatibleModels((string)$endpointBaseUrl, (string)($row['api_key'] ?? ''), 'ginto_tunnel');
                            foreach ($perKeyModels as $modelEntry) {
                                $rawModelId = (string)($modelEntry['id'] ?? '');
                                if ($rawModelId === '') {
                                    continue;
                                }

                                $encodedModelId = 'gtk_' . $keyId . '::' . $rawModelId;
                                $displayName = (string)($modelEntry['name'] ?? $rawModelId) . ' (' . $domainLabel . ')';

                                $composedModels[$encodedModelId] = [
                                    'id' => $encodedModelId,
                                    'name' => $displayName,
                                ];
                                $composedCaps[$encodedModelId] = $modelEntry['capabilities'] ?? [];
                            }
                        }

                        if (!empty($composedModels)) {
                            $pdata['models_with_names'] = array_values($composedModels);
                            $pdata['models'] = array_map(static fn($m) => $m['id'], array_values($composedModels));
                            $pdata['capabilities'] = $composedCaps;
                        }
                    }
                }
                unset($pdata);
            } catch (\Throwable $_) {
                // ignore registry errors and fall back to minimal provider info
            }
        }

        // Only admins may restore session values from cookies; non-admin users always
        // use the admin's global selection and should not inherit stale cookie values.
        if ($isAdmin) {
            if (empty($_SESSION['current_provider']) && !empty($_COOKIE['current_provider'])) {
                $_SESSION['current_provider'] = $_COOKIE['current_provider'];
            }
            if (empty($_SESSION['current_model']) && !empty($_COOKIE['current_model'])) {
                $_SESSION['current_model'] = $_COOKIE['current_model'];
            }
        }

        // Load any global selection (admin-set) from settings table
        $globalSelection = null;
        try {
            if ($this->db) {
                $row = $this->db->get('settings', ['value'], ['key' => 'llm_global_selection']);
                if ($row && !empty($row['value'])) {
                    $decoded = json_decode($row['value'], true);
                    if (is_array($decoded)) {
                        $globalSelection = $decoded;
                    }
                }
            }
        } catch (\Throwable $_) { /* ignore */ }

        // Determine per-provider visibility server-side.
        // Behavior:
        // - admins see everything
        // - if the requesting user has at least one user-scoped key, show only providers
        //   where they have a key or an env key (do NOT surface admin DB keys)
        // - if the requesting user has no user-scoped keys, preserve existing behavior
        //   (allow models provided by env keys or any DB-backed keys)
        $currentUserId = $_SESSION['user_id'] ?? null;
        // If the requester is not logged in and not admin, do not expose any providers
        if (!$isAdmin && $currentUserId === null) {
            $response = [
                'success' => true,
                'providers' => new \stdClass(),
                'is_admin' => false,
                'current_user_id' => null,
                'user_has_keys' => false,
                'current_provider' => null,
                'current_model' => null,
                'global_selection' => $globalSelection,
                'running_models' => [],
                'current_capabilities' => null
            ];
            echo json_encode($response);
            exit();
        }
        $userHasAnyKey = false;
        // First pass: detect whether user has any DB key at all
        foreach ($providers as $pdata) {
            if (!empty($pdata['db_keys']) && is_array($pdata['db_keys'])) {
                foreach ($pdata['db_keys'] as $k) {
                    if (isset($k['user_id']) && $k['user_id'] !== null && $currentUserId !== null && ($k['user_id'] == $currentUserId)) {
                        $userHasAnyKey = true;
                        break 2;
                    }
                }
            }
        }

        // Second pass: filter providers according to policy
        foreach ($providers as $pname => $pdata) {
            $hasDbUserKey = false;
            if (!empty($pdata['db_keys']) && is_array($pdata['db_keys'])) {
                foreach ($pdata['db_keys'] as $k) {
                    if (isset($k['user_id']) && $k['user_id'] !== null && $currentUserId !== null && ($k['user_id'] == $currentUserId)) {
                        $hasDbUserKey = true;
                        break;
                    }
                }
            }
            $providers[$pname]['has_user_key'] = $hasDbUserKey;

            $hasEnvKey = !empty($pdata['env_key']);

            if ($isAdmin) {
                // Admin sees everything
                continue;
            }

            // For non-admin users, only show providers they explicitly own (have DB keys for).
            // Admins still see everything.
            if ($isAdmin) {
                $visible = true;
            } else {
                $visible = $hasDbUserKey;
            }

            if (!$visible) {
                unset($providers[$pname]);
            }
        }

        // Non-admin users always use the admin's global selection regardless of
        // session state or whether they have their own API keys.
        if (!$isAdmin) {
            $effectiveCurrentProvider = !empty($globalSelection['provider']) ? $globalSelection['provider'] : null;
            $effectiveCurrentModel = !empty($globalSelection['model']) ? $globalSelection['model'] : null;
        } else {
            $effectiveCurrentProvider = $_SESSION['current_provider'] ?? null;
            $effectiveCurrentModel = $_SESSION['current_model'] ?? null;

            if ((empty($effectiveCurrentProvider) || empty($effectiveCurrentModel)) && !empty($globalSelection['provider'])) {
                $effectiveCurrentProvider = $globalSelection['provider'];
                $effectiveCurrentModel = $globalSelection['model'] ?? null;
            }
        }

        // Normalize model name to ensure correct provider prefix (e.g. groq needs openai/gpt-oss-120b)
        if ($effectiveCurrentProvider && $effectiveCurrentModel) {
            $effectiveCurrentModel = $this->normalizeModelForProvider($effectiveCurrentProvider, $effectiveCurrentModel);
        }

        $currentCapabilities = null;
        if (!empty($effectiveCurrentProvider) && !empty($effectiveCurrentModel)) {
            $providerCaps = $providers[$effectiveCurrentProvider]['capabilities'] ?? [];
            $currentCapabilities = $providerCaps[$effectiveCurrentModel] ?? null;

            // Fallback: derive capabilities heuristically when model wasn't in fetched list
            if ($currentCapabilities === null && class_exists('\App\Core\LLM\ProviderRegistry')) {
                try {
                    $registry = \App\Core\LLM\ProviderRegistry::getInstance()->setDatabase($this->db);
                    $currentCapabilities = $registry->detectCapabilities((string)$effectiveCurrentModel);
                } catch (\Throwable $_) {
                    $currentCapabilities = null;
                }
            }
        }

        $response = [
            'success' => true,
            // Return associative providers keyed by provider name (frontend expects an object)
            'providers' => $providers,
            'is_admin' => $isAdmin,
            'current_user_id' => $_SESSION['user_id'] ?? null,
            'user_has_keys' => isset($userHasAnyKey) ? (bool)$userHasAnyKey : false,
            'current_provider' => $effectiveCurrentProvider,
            'current_model' => $effectiveCurrentModel,
            'global_selection' => $globalSelection,
            'running_models' => [],
            'current_capabilities' => $currentCapabilities
        ];

        echo json_encode($response);
        exit();
    }

    /**
     * Return per-user console logs and usage for the current logged-in user.
     * GET /api/console/logs
     */
    public function consoleLogs()
    {
        header('Content-Type: application/json');
        $userId = $_SESSION['user_id'] ?? null;
        if (empty($userId)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Not authenticated']);
            exit;
        }

        $db = $this->db;
        $provider = null;
        $model = null;
        $masked_key = null;
        $tokens_left = null;

        try {
            $keyManager = new \App\Core\ProviderKeyManager($db);
            // Prefer session-selected provider if set
            $sessionProvider = $_SESSION['llm_provider_name'] ?? ($_SESSION['current_provider'] ?? null);
            $sessionModel = $_SESSION['llm_model'] ?? ($_SESSION['current_model'] ?? null);

            $userKey = null;
            if (!empty($sessionProvider)) {
                $userKey = $keyManager->getUserKey($sessionProvider, (int)$userId);
            }
            if (!$userKey) {
                $userKey = $keyManager->getUserFirstKey((int)$userId);
            }

            if ($userKey) {
                $provider = $userKey['provider'];
                $masked_key = \App\Core\ProviderKeyManager::maskKey($userKey['api_key']);
            } else {
                // Fallback to session provider or default
                $provider = $sessionProvider ?? strtolower(getenv('DEFAULT_PROVIDER') ?: ($_ENV['DEFAULT_PROVIDER'] ?? 'cerebras'));
            }

            $model = $sessionModel ?? null;

            // Compute usage and remaining tokens using UserRateLimiter
            $rateLimiter = new \App\Core\UserRateLimiter($db, $provider);
            $usage = $rateLimiter->getCurrentUsage((int)$userId, null);
            $limits = $rateLimiter->getUserLimits('user');
            $tokens_left = isset($limits['tpd']) ? max(0, $limits['tpd'] - ($usage['tpd'] ?? 0)) : null;

                    // Get recent usage rows for display (per-user aggregated counters)
                    $recent = [];
                    try {
                        $recent = $db->select('user_rate_limits', ['date','minute_bucket','requests_count','tokens_used','provider'], [
                            'user_id' => (int)$userId,
                            'ORDER' => ['id' => 'DESC'],
                            'LIMIT' => 20,
                        ]);
                    } catch (\Throwable $_) { $recent = []; }

                    // Also fetch recent per-key usage snapshots (local copy)
                    $keyRecent = [];
                    try {
                        $keyRecent = $db->select('provider_key_usage', ['key_id','date','minute_bucket','requests_count','tokens_used','provider'], [
                            'user_id' => (int)$userId,
                            'ORDER' => ['id' => 'DESC'],
                            'LIMIT' => 20,
                        ]);
                    } catch (\Throwable $_) { $keyRecent = []; }

                    // Compute per-key totals (sum of requests/tokens) for this user
                    $keyTotals = [];
                    try {
                        $rows = $db->select('provider_key_usage', [
                            'key_id',
                            'SUM(requests_count) as requests_total',
                            'SUM(tokens_used) as tokens_total',
                        ], [
                            'user_id' => (int)$userId,
                            'GROUP' => ['key_id'],
                        ]);
                        if (!empty($rows)) {
                            foreach ($rows as $r) {
                                $kid = $r['key_id'] ?? null;
                                if ($kid === null) continue;
                                $keyTotals[] = [
                                    'key_id' => (int)$kid,
                                    'requests_total' => (int)($r['requests_total'] ?? 0),
                                    'tokens_total' => (int)($r['tokens_total'] ?? 0),
                                ];
                            }
                        }
                    } catch (\Throwable $_) { $keyTotals = []; }

            echo json_encode([
                'success' => true,
                'provider' => $provider,
                'model' => $model,
                'masked_key' => $masked_key,
                'tokens_left' => $tokens_left,
                'usage' => $usage,
                'recent' => $recent,
                'key_recent' => $keyRecent,
                'key_totals' => $keyTotals,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            exit;
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * Set the currently selected provider/model (stores in session)
     */
    public function modelsSet()
    {
        header('Content-Type: application/json');
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
            exit();
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = $_POST;
        }

        $provider = $this->sanitizeProviderName($input['provider'] ?? null);
        $model = trim(strip_tags((string)($input['model'] ?? '')));
        $setGlobalDefault = !empty($input['set_global_default']) || !empty($input['set_default']);

        // Normalize model name based on provider to avoid prefix mismatches
        $model = $this->normalizeModelForProvider((string)$provider, $model);

        // Validate that the requesting user may select this provider.
        // Only admins are allowed to change the active model/provider.
        // Non-admin users always use the admin-configured global selection.
        $currentUserId = $_SESSION['user_id'] ?? null;
        $isAdmin = $this->isCurrentUserAdminStrict();

        if (!$isAdmin) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'forbidden',
                'message' => 'Only admins can change the active model'
            ]);
            exit();
        }

        if ($provider) {
            $_SESSION['current_provider'] = $provider;
            $_SESSION['llm_provider_name'] = $provider;
        }
        if ($model) {
            $_SESSION['current_model'] = $model;
            $_SESSION['llm_model'] = $model;
        }

        // Also persist selection to a cookie so it remains available across requests
        // even if PHP session isn't being preserved by the client for any reason.
        if (!headers_sent()) {
            $cookieSecureFlag = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https'));
            if ($provider) {
                if (defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 70300) {
                    setcookie('current_provider', $provider, [
                        'expires' => 0,
                        'path' => '/',
                        'domain' => '',
                        'secure' => $cookieSecureFlag,
                        'httponly' => true,
                        'samesite' => 'Lax'
                    ]);
                } else {
                    // Fallback for older PHP versions: emit Set-Cookie header with SameSite
                    $cookieStr = 'current_provider=' . rawurlencode((string)$provider) . '; Path=/; HttpOnly; SameSite=Lax' . ($cookieSecureFlag ? '; Secure' : '');
                    header('Set-Cookie: ' . $cookieStr, false);
                }
            }
            if ($model) {
                if (defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 70300) {
                    setcookie('current_model', $model, [
                        'expires' => 0,
                        'path' => '/',
                        'domain' => '',
                        'secure' => $cookieSecureFlag,
                        'httponly' => true,
                        'samesite' => 'Lax'
                    ]);
                } else {
                    $cookieStr = 'current_model=' . rawurlencode((string)$model) . '; Path=/; HttpOnly; SameSite=Lax' . ($cookieSecureFlag ? '; Secure' : '');
                    header('Set-Cookie: ' . $cookieStr, false);
                }
            }
        }

        $globalDefaultUpdated = false;

        // Admin model changes always persist as the global default for all users.
        // Non-admin users with their own keys only update their own session.
        if ($isAdmin && $provider && $model) {
            $setGlobalDefault = true;
        }

        // Persist global default for admin selections.
        try {
                if ($setGlobalDefault && $isAdmin) {
                $val = json_encode(['provider' => $provider, 'model' => $model]);
                if ($this->db) {
                    $exists = $this->db->get('settings', 'id', ['key' => 'llm_global_selection']);
                    if ($exists) {
                        $this->db->update('settings', ['value' => $val, 'type' => 'json', 'updated_at' => date('Y-m-d H:i:s')], ['key' => 'llm_global_selection']);
                    } else {
                        $this->db->insert('settings', ['key' => 'llm_global_selection', 'value' => $val, 'type' => 'json', 'group_name' => 'system', 'description' => 'Global LLM provider/model selection', 'is_public' => 1]);
                    }
                    $globalDefaultUpdated = true;
                }
            }
        } catch (\Throwable $_) { /* ignore errors */ }

        $capabilities = null;
        if (!empty($provider) && !empty($model) && class_exists('\App\Core\LLM\ProviderRegistry')) {
            try {
                $registry = \App\Core\LLM\ProviderRegistry::getInstance()->setDatabase($this->db);
                $capabilities = $registry->getModelCapabilities((string)$provider, (string)$model);
            } catch (\Throwable $_) {
                $capabilities = null;
            }
        }

        echo json_encode([
            'success' => true,
            'provider' => $_SESSION['current_provider'] ?? null,
            'model' => $_SESSION['current_model'] ?? null,
            'global_default_updated' => $globalDefaultUpdated,
            'capabilities' => $capabilities,
        ]);
        exit();
    }

    /**
     * Return provider API key status (admin UI uses this to show configured providers)
     */
    public function providerKeys()
    {
        header('Content-Type: application/json');

        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        // Ensure DB is available for DB-backed keys
        $db = $this->db;

        if ($method === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $action = $input['action'] ?? $input['a'] ?? null;

            if ($action === 'add') {
                $provider = $this->sanitizeProviderName($input['provider'] ?? null);
                $api_key = $input['api_key'] ?? null;
                $key_name = $input['key_name'] ?? null;
                $tier = $input['tier'] ?? 'basic';
                $is_default = !empty($input['is_default']) ? 1 : 0;
                $base_url = $input['base_url'] ?? null;

                if (empty($provider) || empty($api_key)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Missing provider or api_key']);
                    exit();
                }

                // Disallow visitors from adding keys; require login or admin
                $currentUserId = $_SESSION['user_id'] ?? null;
                $isAdmin = false;
                try {
                    if (class_exists('Ginto\\Controllers\\UserController') && \Ginto\Controllers\UserController::isAdmin()) {
                        $isAdmin = true;
                    }
                } catch (\Throwable $_) { /* ignore */ }

                if (!$isAdmin && $currentUserId === null) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'forbidden', 'message' => 'Authentication required to add key']);
                    exit();
                }

                if ($provider === 'ginto_tunnel') {
                    $sanitizedBaseUrl = $this->sanitizeOpenAiCompatibleBaseUrl($base_url);
                    if (empty($sanitizedBaseUrl)) {
                        $sanitizedBaseUrl = $this->sanitizeOpenAiCompatibleBaseUrl($this->getEnvVar('GINTO_TUNNEL_BASE_URL') ?: 'https://ollama.ginto.ai/v1/');
                    }
                    if (empty($sanitizedBaseUrl)) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Invalid endpoint URL for Ginto Tunnel']);
                        exit();
                    }
                    $base_url = $sanitizedBaseUrl;
                }

                try {
                    if ($db) {
                        $currentUserId = $_SESSION['user_id'] ?? null;
                        // Prefer ProviderKeyManager if available (handles defaults/rotation)
                        if (class_exists('App\\Core\\ProviderKeyManager')) {
                            $manager = new \App\Core\ProviderKeyManager($db);
                            $payload = [
                                'provider' => $provider,
                                'api_key' => $api_key,
                                'key_name' => $key_name,
                                'tier' => $tier,
                                'is_default' => $is_default,
                            ];
                            if ($currentUserId !== null) $payload['user_id'] = $currentUserId;
                            try {
                                $id = $manager->addKey($payload);
                            } catch (\Throwable $addErr) {
                                if ($this->isDuplicateKeyError($addErr)) {
                                    $id = $this->upsertExistingProviderKeyOnDuplicate($provider, $api_key, $key_name, $tier, $is_default, $currentUserId !== null ? (int)$currentUserId : null);
                                    if ($id) {
                                        if ($provider === 'ginto_tunnel') {
                                            try {
                                                $this->saveTunnelBaseUrl($base_url, $currentUserId !== null ? (int)$currentUserId : null, $isAdmin);
                                                $this->saveTunnelBaseUrlForKey((string)$base_url, (int)$id);
                                            } catch (\Throwable $_) { /* ignore */ }
                                        }
                                        echo json_encode(['success' => true, 'id' => $id, 'upserted' => true]);
                                        exit();
                                    }
                                }
                                throw $addErr;
                            }
                            if ($provider === 'ginto_tunnel') {
                                try {
                                    $this->saveTunnelBaseUrl($base_url, $currentUserId !== null ? (int)$currentUserId : null, $isAdmin);
                                    if (!empty($id)) {
                                        $this->saveTunnelBaseUrlForKey((string)$base_url, (int)$id);
                                    }
                                } catch (\Throwable $_) { /* ignore */ }
                            }
                            // Ensure owner recorded (fallback in case manager didn't persist user_id)
                            try {
                                if ($currentUserId !== null && $this->db && $id) {
                                    $this->db->update('provider_keys', ['user_id' => $currentUserId], ['id' => $id]);
                                }
                            } catch (\Throwable $_) { /* ignore */ }
                            echo json_encode(['success' => true, 'id' => $id]);
                            exit();
                        }

                        $insert = [
                            'provider' => $provider,
                            'api_key' => $api_key,
                            'key_name' => $key_name,
                            'tier' => $tier,
                            'is_default' => $is_default,
                            'is_active' => 1,
                        ];
                        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] !== null) {
                            $insert['user_id'] = $_SESSION['user_id'];
                        }

                        try {
                            $res = $db->insert('provider_keys', $insert);
                            $id = $db->id() ?? null;
                        } catch (\Throwable $insertErr) {
                            if ($this->isDuplicateKeyError($insertErr)) {
                                $id = $this->upsertExistingProviderKeyOnDuplicate($provider, $api_key, $key_name, $tier, $is_default, isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null);
                                if ($id) {
                                    if ($provider === 'ginto_tunnel') {
                                        try {
                                            $this->saveTunnelBaseUrl($base_url, isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null, $isAdmin);
                                            $this->saveTunnelBaseUrlForKey((string)$base_url, (int)$id);
                                        } catch (\Throwable $_) { /* ignore */ }
                                    }
                                    echo json_encode(['success' => true, 'id' => $id, 'upserted' => true]);
                                    exit();
                                }
                            }
                            throw $insertErr;
                        }
                        if ($provider === 'ginto_tunnel') {
                            try {
                                $this->saveTunnelBaseUrl($base_url, isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null, $isAdmin);
                                if (!empty($id)) {
                                    $this->saveTunnelBaseUrlForKey((string)$base_url, (int)$id);
                                }
                            } catch (\Throwable $_) { /* ignore */ }
                        }
                        // Fallback: ensure user_id is set on the inserted row
                        try {
                            if (isset($_SESSION['user_id']) && $_SESSION['user_id'] !== null && $id) {
                                $db->update('provider_keys', ['user_id' => $_SESSION['user_id']], ['id' => $id]);
                            }
                        } catch (\Throwable $_) { /* ignore */ }
                        echo json_encode(['success' => true, 'id' => $id]);
                        exit();
                    }

                    // No DB available
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Database not configured']);
                    exit();
                } catch (\Throwable $e) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                    exit();
                }
            }

            if ($action === 'delete') {
                $id = $input['id'] ?? null;
                if (!$id) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Missing id']);
                    exit();
                }

                try {
                    if ($db) {
                        $currentUserId = $_SESSION['user_id'] ?? null;
                        $isAdmin = false;
                        try {
                            if (class_exists('Ginto\\Controllers\\UserController') && \Ginto\Controllers\UserController::isAdmin()) {
                                $isAdmin = true;
                            }
                        } catch (\Throwable $_) { /* ignore */ }

                        // Ensure non-admin users can only delete their own keys
                        $row = $db->get('provider_keys', '*', ['id' => $id]);
                        if (!$isAdmin) {
                            $ownerId = $row['user_id'] ?? null;
                            if ($ownerId === null || $currentUserId === null || ($ownerId != $currentUserId)) {
                                http_response_code(403);
                                echo json_encode(['success' => false, 'error' => 'forbidden', 'message' => 'Not allowed to delete this key']);
                                exit();
                            }
                        }

                        $db->delete('provider_keys', ['id' => $id]);
                        echo json_encode(['success' => true]);
                        exit();
                    }
                } catch (\Throwable $e) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                    exit();
                }
            }

            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
            exit();
        }

        // GET: list keys (both .env keys and DB keys)
        $keys = [];

        $currentUserId = $_SESSION['user_id'] ?? null;
        $isAdmin = false;
        try {
            if (class_exists('Ginto\\Controllers\\UserController') && \Ginto\Controllers\UserController::isAdmin()) {
                $isAdmin = true;
            }
        } catch (\Throwable $_) { /* ignore */ }

        // If requester is a visitor (not logged in) and not admin, do not return any keys
        if (!$isAdmin && $currentUserId === null) {
            echo json_encode(['success' => true, 'keys' => []]);
            exit();
        }

        // 1) DB-backed keys
        if ($db) {
            try {
                // Prefer ProviderKeyManager if available
                if (class_exists('App\\Core\\ProviderKeyManager')) {
                    $manager = new \App\Core\ProviderKeyManager($db);
                    $rows = $manager->getAllKeys();
                } else {
                    $rows = $db->select('provider_keys', '*');
                }

                if (is_array($rows)) {
                    foreach ($rows as $r) {
                        // If caller is not admin, require they be logged in and only show keys owned by them
                        if (!$isAdmin) {
                            if ($currentUserId === null) {
                                continue;
                            }
                            $owner = $r['user_id'] ?? null;
                            if ($owner === null || $owner != $currentUserId) {
                                continue;
                            }
                        }

                        $keys[] = [
                            'id' => $r['id'],
                            'provider' => $r['provider'],
                            'key_name' => $r['key_name'] ?? null,
                            'api_key_masked' => self::maskKey($r['api_key'] ?? ''),
                            'tier' => $r['tier'] ?? 'basic',
                            'is_default' => !empty($r['is_default']) ? true : false,
                            'is_active' => !empty($r['is_active']) ? true : false,
                            'last_used_at' => $r['last_used_at'] ?? null,
                            'error_count' => intval($r['error_count'] ?? 0),
                            'rate_limit_reset_at' => $r['rate_limit_reset_at'] ?? null,
                            'created_at' => $r['created_at'] ?? null,
                            'updated_at' => $r['updated_at'] ?? null,
                        ];
                        if (($r['provider'] ?? '') === 'ginto_tunnel') {
                            $keys[count($keys) - 1]['base_url'] = $this->resolveTunnelBaseUrlForKey((int)($r['id'] ?? 0), $currentUserId !== null ? (int)$currentUserId : null, $isAdmin);
                        }
                    }
                }
            } catch (\Throwable $e) {
                // ignore DB errors, fall through to env keys
            }
        }

        // 2) .env keys (primary in environment variables)
        // Only surface environment (.env) keys to admin users. Non-admin users
        // should not see global keys stored in environment variables to avoid
        // exposing keys they do not own.
        $envCandidates = [
            'GROQ_API_KEY' => 'groq',
            'CEREBRAS_API_KEY' => 'cerebras',
            'OPENAI_API_KEY' => 'openai',
            'ANTHROPIC_API_KEY' => 'anthropic',
            'TOGETHER_API_KEY' => 'together',
            'FIREWORKS_API_KEY' => 'fireworks',
            'OLLAMA_API_KEY' => 'ollama',
            'GINTO_TUNNEL_API_KEY' => 'ginto_tunnel',
            'NOVITA_API_KEY' => 'novita'
        ];
        // Only include env keys in the response for admin users.
        if ($isAdmin) {
            foreach ($envCandidates as $envKey => $providerName) {
                $val = $this->getEnvVar($envKey);
                if (!empty($val)) {
                    $keys[] = [
                        'id' => 'env:' . $envKey,
                        'provider' => $providerName,
                        'key_name' => $envKey,
                        'api_key_masked' => self::maskKey($val),
                        'tier' => 'production',
                        'is_default' => true,
                        'is_active' => true,
                        'last_used_at' => null,
                        'error_count' => 0,
                        'rate_limit_reset_at' => null,
                    ];
                    if ($providerName === 'ginto_tunnel') {
                        $keys[count($keys) - 1]['base_url'] = $this->resolveTunnelBaseUrl($currentUserId !== null ? (int)$currentUserId : null, $isAdmin);
                    }
                }
            }
        }

        echo json_encode(['success' => true, 'keys' => $keys]);
        exit();
    }
}
