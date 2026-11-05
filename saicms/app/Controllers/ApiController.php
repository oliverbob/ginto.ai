<?php

namespace App\Controllers;

use Core\Controller;

class ApiController extends Controller
{
    private string $storageFile;

    public function __construct()
    {
        parent::__construct();
        $this->storageFile = __DIR__ . '/../../storage/api_messages.json';
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
                    'Authorization: Bearer ' . $novitaKey,
                ]);
                curl_setopt($ch, CURLOPT_TIMEOUT, 0);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

                $sseBuffer = '';
                curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($curl, $data) use (&$sseBuffer) {
                    $sseBuffer .= $data;
                    // Process complete SSE events separated by double newline
                    while (strpos($sseBuffer, "\n\n") !== false) {
                        $pos = strpos($sseBuffer, "\n\n");
                        $event = substr($sseBuffer, 0, $pos);
                        $sseBuffer = substr($sseBuffer, $pos + 2);

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
}
