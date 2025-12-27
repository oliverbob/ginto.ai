<?php
namespace Ginto\Controllers;

use Ginto\Models\User;
use Ginto\Core\View;
use Medoo\Medoo;

class ApiController
{
    private Medoo $db;
    private User $userModel;

    public function __construct(Medoo $db)
    {
        $this->db = $db;
        $this->userModel = new User();
    }

    /**
     * Attempt to write a file into the project root.
     * Returns ['success'=>bool,'message'=>string,'path'=>string]
     */
    /**
     * Try to write a file. If $allowOutside is TRUE then absolute paths are permitted
     * (this must also be enabled via environment and callers should only pass TRUE
     * for trusted admin contexts). By default only project-relative writes are allowed.
     */
    private function attemptWriteFile(string $filename, string $content, bool $overwrite = false, bool $allowOutside = false): array
    {
        if (!$filename) return ['success' => false, 'message' => 'Missing filename', 'path' => ''];

        // reject obvious traversal patterns unless explicitly allowed
        if (strpos($filename, '..') !== false) {
            return ['success' => false, 'message' => 'Invalid filename (contains traversal)', 'path' => ''];
        }

        // Allow absolute paths only when caller explicitly allows outside writes and env enables it
        $isAbsolute = str_starts_with($filename, '/') || preg_match('/^[A-Za-z]:\\\\/', $filename);
        $allowAny = (!empty($_ENV['MCP_ALLOW_ANY_FILE_WRITE']) && ($_ENV['MCP_ALLOW_ANY_FILE_WRITE'] === '1')) || (!empty($_ENV['ALLOW_MCP_ANY_FILE_WRITE']) && ($_ENV['ALLOW_MCP_ANY_FILE_WRITE'] === '1'));
        if ($isAbsolute && !$allowOutside) {
            return ['success' => false, 'message' => 'Absolute paths not permitted from this caller', 'path' => ''];
        }
        if ($isAbsolute && !$allowAny) {
            return ['success' => false, 'message' => 'Absolute paths are disabled by server configuration (MCP_ALLOW_ANY_FILE_WRITE)', 'path' => ''];
        }

        // normalize and remove any leading slashes (relative case) for normal writes
        $normalized = $isAbsolute ? $filename : ltrim(str_replace('\\', '/', $filename), '/');

        // whitelist common characters in names and slashes for subpaths
        if (!preg_match('/^[A-Za-z0-9._\/-]+$/', $normalized)) {
            return ['success' => false, 'message' => 'Filename contains disallowed characters', 'path' => ''];
        }

        $root = realpath(ROOT_PATH) ?: rtrim(ROOT_PATH, DIRECTORY_SEPARATOR);
        // If this is an absolute path, use it directly as the target. Otherwise resolve under project root
        if ($isAbsolute) {
            $target = $normalized;
        } else {
            $target = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
        }

        // ensure parent directory exists
        $parent = dirname($target);
        if (!is_dir($parent)) {
            // create directory tree
            try { mkdir($parent, 0775, true); } catch (\Throwable $_) { /* ignore */ }
        }
        // protect: if target is not absolute, ensure parent path is inside project root after normalization
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
            // Attempt write and capture PHP error details if it fails
            $written = @file_put_contents($target, $content);
            if ($written === false) {
                $err = error_get_last();
                $errMsg = $err ? ($err['message'] ?? json_encode($err)) : 'unknown error';
                error_log("attemptWriteFile: write failed for $target — $errMsg");
                return ['success' => false, 'message' => 'Failed to write file (permission or filesystem error): ' . $errMsg, 'path' => $target];
            }
            error_log("attemptWriteFile: successfully wrote $target (bytes=$written)");
            return ['success' => true, 'message' => 'File written', 'path' => $target];
        } catch (\Throwable $e) {
            error_log('attemptWriteFile: exception while writing ' . $target . ' — ' . $e->getMessage());
            return ['success' => false, 'message' => 'Write failed: ' . $e->getMessage(), 'path' => $target];
        }
    }

    /**
     * Convert an internal/absolute path (or a requested path) into a safe path
     * suitable to return to clients. If the requested path was relative, prefer
     * returning it unchanged. If the resolved absolute path lives inside the
     * repository root, return a repo-relative path. Otherwise return just the
     * basename to avoid leaking directory information.
     */
    private function sanitizeClientPath(?string $requestedPath, ?string $internalPath): string
    {
        // Normalise requested path if present and not absolute
        $req = (string)($requestedPath ?? '');
        if ($req !== '') {
            // If it's not an absolute path (Unix/Windows), return the normalized requested path
            if (!str_starts_with($req, '/') && !preg_match('/^[A-Za-z]:\\\\/', $req)) {
                return ltrim(str_replace('\\', '/', $req), '/');
            }
        }

        // Try to convert the internal absolute path into a repo-relative one
        $root = realpath(ROOT_PATH) ?: rtrim(ROOT_PATH, DIRECTORY_SEPARATOR);
        $abs = (string)($internalPath ?? '');
        $real = realpath($abs) ?: $abs;
        if ($real && $root && str_starts_with($real, $root)) {
            $rel = substr($real, strlen($root));
            return ltrim(str_replace('\\', '/', $rel), '/');
        }

        // Fallback: return only the basename to avoid leaking absolute dirs
        return basename($abs ?: $req);
    }

    /**
     * Parse a file-create directive from a text reply.
     * Supported formats:
     *  - JSON: { "file": { "path": "path/to/file.txt", "content": "..." } }
     *  - Fenced block: ```file: path/to/file.txt\n...content...\n```
     *  - Plain header: FILE: path/to/file.txt\n---\n...content...
     */
    private function parseFileDirective(string $text): ?array
    {
        $t = trim($text);
        if ($t === '') return null;

        // 1) JSON object
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

        // 2) fenced file block ```file: path\nCONTENT```
        if (preg_match('/```file\s*:?\s*([^\n\r]+)\r?\n([\s\S]*?)```/i', $t, $m)) {
            $header = trim($m[1]);
            $path = '';
            $commit = false;
            $commitMsg = null;

            // header may include flags e.g. "path commit:true commit_message:My message"
            // first token is the path (allow spaces if quoted)
            if (preg_match('/^\s*"([^"]+)"\s*(.*)$/', $header, $hmatch)) {
                $path = $hmatch[1];
                $meta = trim($hmatch[2]);
            } elseif (preg_match('/^\s*' . "([^\s]+)" . '/u', $header, $hmatch)) {
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

        // 3) header style: FILE: path\n---\nCONTENT
        if (preg_match('/^FILE:\s*([^\r\n]+)\r?\n(?:-+\r?\n)?([\s\S]*)/i', $t, $m)) {
            $pathRaw = trim($m[1]);
            $commit = false; $commitMsg = null;
            // allow header like: FILE: path\nCOMMIT: true\nCOMMIT MESSAGE: ...\n---\nCONTENT
            if (preg_match('/^([^\r\n]+)\r?\nCOMMIT\s*:\s*(true|1|yes)\r?\n(.*)$/is', $t, $m2)) {
                $pathRaw = trim($m2[1]);
                $commit = true;
                if (preg_match('/COMMIT\s*MESSAGE\s*:\s*(.+?)\r?\n\-+\r?\n/s', $t, $m3)) {
                    $commitMsg = trim($m3[1]);
                }
            }

            $path = $pathRaw;
            $content = $m[2];
            return ['path' => $path, 'content' => $content, 'commit' => $commit, 'commit_message' => $commitMsg];
        }

        return null;
    }

    /**
     * Extract file content from arbitrary assistant text.
     * This attempts to be helpful when the assistant returns a code block
     * (for example a bash snippet that writes a file, or a raw text fenced
     * block). For 'bash' blocks we look for common patterns like:
     *   echo "text" > filename
     *   cat > filename <<'EOF'\n...\nEOF
     * If the text includes a fenced 'file:' block or JSON that parseFileDirective
     * already understands, defer to that parser before trying heuristic extraction.
     *
     * Returns extracted content string (may be identical to $text) or empty string.
     */
    private function extractFileContentFromText(string $text, ?string $expectedPath = null): string
    {
        $t = trim($text);
        if ($t === '') return '';

        // 1) If parseFileDirective handles this, prefer that content
        try {
            $parsed = $this->parseFileDirective($t);
            if (is_array($parsed) && isset($parsed['content'])) {
                return (string)$parsed['content'];
            }
        } catch (\Throwable $_) { /* ignore and continue heuristics */ }

        // 2) Look for fenced code blocks — pick the first useful one
        if (preg_match('/```\s*([a-zA-Z0-9_-]*)\s*\n([\s\S]*?)```/m', $t, $m)) {
            $lang = strtolower(trim($m[1] ?? ''));
            $body = rtrim($m[2]);

            // If it's plain text-like, return the block content as file body
            if ($lang === '' || in_array($lang, ['text', 'txt', 'text/plain'])) {
                return $body;
            }

            // If it's bash/sh - try to parse common redirection patterns
            if (in_array($lang, ['bash', 'sh', 'shell'])) {
                // 2a) echo "..." > filename
                if (preg_match('/^\s*echo\s+["\']?(.*?)["\']?\s*>\s*([^\s;]+)\s*$/m', trim($body), $em)) {
                    $candidate = $em[1];
                    $target = $em[2];
                    // if expected path matches or absent, return the echoed text
                    if (!$expectedPath || basename($expectedPath) === basename($target)) {
                        return $candidate;
                    }
                }

                // 2b) cat > filename <<EOF ... EOF
                if (preg_match('/cat\s*>\s*([^\s]+)\s*<<\(?["\']?([A-Za-z0-9_]+)["\']?\)?\r?\n([\s\S]*?)\r?\n\2/m', $body, $cm)) {
                    $target = $cm[1]; $tag = $cm[2]; $content = $cm[3];
                    if (!$expectedPath || basename($expectedPath) === basename($target)) return rtrim($content);
                }

                // 2c) here-doc without parens but simple: <<'EOF' will be detected above
                // 2d) fallback for shell block: if it contains a single quoted string write that
                if (preg_match('/^["\'](.*)["\']$/s', trim($body), $q)) {
                    return $q[1];
                }

                // Default: treat whole block as file content
                return $body;
            }

            // For other language blocks (json, xml etc) assume the block itself is file content
            return $body;
        }

        // 3) If response contains 'FILE: path' header style — parseFileDirective already handled that

        // 4) No fenced blocks — attempt to extract echo redirection patterns from plain text
        if (preg_match('/echo\s+["\']?(.*?)["\']?\s*>\s*([^\s;]+)/i', $t, $em2)) {
            $candidate = $em2[1]; $target = $em2[2];
            if (!$expectedPath || basename($expectedPath) === basename($target)) return $candidate;
        }

        // 5) As last resort, return the full body text (server will write verbatim)
        return $t;
    }

    /**
     * Attempt to parse an assistant reply into a structured list of tasks.
     * Returns an array of task objects (eg: [ ['task'=>'...', 'description'=>'...'], ... ])
     * or null when no structured tasks could be detected.
     */
    private function parseTasksFromText(string $text): ?array
    {
        $t = trim($text);
        if ($t === '') return null;

        // 1) If the whole text is valid JSON, prefer that
        try {
            $decoded = json_decode($t, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $_) {
            $decoded = null;
        }

        if (is_array($decoded)) {
            // Common wrapper: { "tasks": [...] }
            if (!empty($decoded['tasks']) && is_array($decoded['tasks'])) {
                return array_values($decoded['tasks']);
            }

            // If the decoded value is a numeric-indexed array of objects,
            // and each element looks like a task, return it directly.
            $isList = true;
            foreach ($decoded as $item) {
                if (!is_array($item)) { $isList = false; break; }
                if (!isset($item['task']) && !isset($item['title']) && !isset($item['description'])) { $isList = false; break; }
            }
            if ($isList) return array_values($decoded);
        }

        // 2) Search for the first JSON object/array substring inside the text
        if (preg_match('/(\{[\s\S]*\}|\[[\s\S]*\])/', $t, $m)) {
            try {
                $inner = json_decode($m[1], true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $_) { $inner = null; }
            if (is_array($inner)) {
                if (!empty($inner['tasks']) && is_array($inner['tasks'])) return array_values($inner['tasks']);
                $isList = true;
                foreach ($inner as $item) {
                    if (!is_array($item)) { $isList = false; break; }
                    if (!isset($item['task']) && !isset($item['title']) && !isset($item['description'])) { $isList = false; break; }
                }
                if ($isList) return array_values($inner);
            }
        }

        // 3) Fallback: parse simple markdown/list lines like "- Do X: details"
        $tasks = [];
        if (preg_match_all('/^\s*[-*+]\s*(.+?)(?:[:\-]\s*(.+))?$/m', $t, $ms, PREG_SET_ORDER)) {
            foreach ($ms as $row) {
                $title = trim($row[1]);
                $desc = isset($row[2]) ? trim($row[2]) : '';
                $tasks[] = ['task' => $title, 'description' => $desc];
            }
            if (!empty($tasks)) return $tasks;
        }

        return null;
    }

    /**
     * Add a file to git index and create a commit (if requested)
     * Returns ['success'=>bool, 'message'=>string, 'commit'=>string|null]
     */
    private function safeGitCommit(array $paths, string $message = ''): array
    {
        // require a non-empty commit message or set a default
        $msg = trim($message ?: "Automated file creation via editor assistant");

        // Ensure git is available and the current directory is inside the repo
        $root = realpath(ROOT_PATH) ?: ROOT_PATH;
        // Sanity: make sure paths are inside the repo and files exist
        foreach ($paths as $p) {
            $real = realpath($p);
            if (!$real || !str_starts_with($real, $root)) {
                return ['success' => false, 'message' => 'Invalid commit paths: ' . $p, 'commit' => null];
            }
        }

        // run git add and commit safely
        try {
              // use a safe escape for single quotes (for quoting in single-quoted shell args)
              $esc = function($s) { return str_replace("'", "'\\\'", $s); };
            // Build a quoted paths string
            $quoted = array_map(function($p) use ($esc) { return "'" . $esc($p) . "'"; }, $paths);
            $cmdAdd = "git -C '" . addslashes($root) . "' add " . implode(' ', $quoted) . " 2>&1";
            $outAdd = [];
            $code = 0;
            exec($cmdAdd, $outAdd, $code);
            if ($code !== 0) {
                return ['success' => false, 'message' => 'git add failed: ' . implode("\n", $outAdd), 'commit' => null];
            }

            // create commit
            $msgEsc = addslashes($msg);
            $cmdCommit = "git -C '" . addslashes($root) . "' commit -m '" . $msgEsc . "' 2>&1";
            $outCommit = [];
            $code = 0;
            exec($cmdCommit, $outCommit, $code);
            if ($code !== 0) {
                // If no changes to commit, git returns non-zero. Check output for that case.
                $joined = implode('\n', $outCommit);
                if (stripos($joined, 'nothing to commit') !== false) {
                    return ['success' => true, 'message' => 'Nothing to commit', 'commit' => null];
                }
                return ['success' => false, 'message' => 'git commit failed: ' . $joined, 'commit' => null];
            }

            // read the commit hash of HEAD
            $outHash = [];
            exec("git -C '" . addslashes($root) . "' rev-parse --short HEAD 2>&1", $outHash, $code);
            $commitHash = $outHash[0] ?? null;
            return ['success' => true, 'message' => 'Committed: ' . $commitHash, 'commit' => $commitHash];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Commit failed: ' . $e->getMessage(), 'commit' => null];
        }
    }

    /**
     * Record an assistant action into .assistant_audit/ as JSON and return the path.
     * Returns the relative audit path or null on failure.
     */
    private function recordAssistantAudit(int $userId, string $prompt, array $files, ?string $rawResponse = null, ?string $commitHash = null): ?string
    {
        try {
            $root = realpath(ROOT_PATH) ?: rtrim(ROOT_PATH, DIRECTORY_SEPARATOR);
            $auditDir = $root . DIRECTORY_SEPARATOR . '.assistant_audit';
            if (!is_dir($auditDir)) { @mkdir($auditDir, 0775, true); }

            $time = date('Ymd_His');
            $id = uniqid('assistant_', true);
            $fn = "$time-{$userId}-{$id}.json";
            $path = $auditDir . DIRECTORY_SEPARATOR . $fn;

            $payload = [
                'timestamp' => date(DATE_ATOM),
                'user_id' => $userId,
                'prompt' => $prompt,
                'files' => $files,
                'raw_response' => $rawResponse,
                'commit' => $commitHash
            ];

            $written = @file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            if ($written === false) {
                error_log('recordAssistantAudit: failed to write audit file ' . $path);
                return null;
            }

            // return relative path from repo root
            $rel = '.assistant_audit/' . $fn;
            return $rel;
        } catch (\Throwable $e) {
            error_log('recordAssistantAudit: exception ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Commit given file paths and include an assistant audit entry in the same commit.
     * Returns the same shape as safeGitCommit.
     */
    private function commitPathsWithAudit(array $paths, string $message, int $userId, string $prompt, $rawResponse = null): array
    {
        try {
            $auditRel = $this->recordAssistantAudit($userId, $prompt, $paths, is_string($rawResponse) ? $rawResponse : json_encode($rawResponse));
            if ($auditRel) { $paths[] = $auditRel; }
            return $this->safeGitCommit($paths, $message);
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Commit with audit failed: ' . $e->getMessage(), 'commit' => null];
        }
    }

    /**
     * Search users for typeahead/autocomplete (with CSRF validation)
     */
    public function searchUsers(): void
    {
        session_start();
        $csrfToken = $_GET['csrf_token'] ?? '';
        if (!validateCsrfToken($csrfToken)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            return;
        }
        $query = $_GET['q'] ?? '';
        $limit = (int)($_GET['limit'] ?? 10);
        if (strlen($query) < 2) {
            echo json_encode(['success' => false, 'message' => 'Query too short']);
            return;
        }
        $users = $this->db->select('users', [
            'id', 'username', 'email', 'fullname', 'ginto_level'
        ], [
            'OR' => [
                'username[~]' => $query,
                'email[~]' => $query,
                'fullname[~]' => $query
            ],
            'LIMIT' => $limit
        ]);
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'users' => $users]);
    }

    /**
     * Example: Get user profile by ID
     */
    public function userProfile(): void
    {
        session_start();
        // Accept either `user_id` (legacy) or `username` (preferred)
        $userId = (int)($_GET['user_id'] ?? 0);
        if ($userId <= 0) {
            $username = trim($_GET['username'] ?? '');
            if ($username !== '') {
                // resolve username -> id
                try {
                    $uid = $this->db->get('users', 'id', ['username' => $username]);
                    $userId = $uid ? intval($uid) : 0;
                } catch (\Throwable $_) { $userId = 0; }
            }
        }

        if ($userId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid user identifier']);
            return;
        }

        $user = $this->userModel->find($userId);
        if (!$user) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'User not found']);
            return;
        }
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $user]);
    }

    /**
     * POST /admin/pages/editor/chat
     * Accepts JSON: { message: string, file: encodedPath|null, model: string }
     * Forwards request to a configured MCP server (MCP_SERVER_URL) and returns the response.
     */
    public function editorChat(): void
    {
        session_start();

        // Basic CSRF check — accept X-CSRF-TOKEN header or POST field
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
        if (!function_exists('validateCsrfToken') || !validateCsrfToken($csrf)) {
            http_response_code(403);
            $clientAccept = $_SERVER['HTTP_ACCEPT'] ?? '';
            if (stripos($clientAccept, 'text/plain') !== false) {
                header('Content-Type: text/plain; charset=utf-8');
                echo 'Invalid CSRF token';
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            }
            return;
        }

        // Only admin/editor roles should access editor chat
        $uid = (int)($_SESSION['user_id'] ?? 0);
        if ($uid <= 0) {
            http_response_code(403);
            $clientAccept = $_SERVER['HTTP_ACCEPT'] ?? '';
            if (stripos($clientAccept, 'text/plain') !== false) {
                header('Content-Type: text/plain; charset=utf-8');
                echo 'Not authenticated';
            } else {
                echo json_encode(['success' => false, 'message' => 'Not authenticated']);
            }
            return;
        }
        try { $u = $this->db->get('users', ['role_id'], ['id' => $uid]); } catch (\Throwable $_) { $u = null; }
        $role = $u['role_id'] ?? 0;
        if (!in_array($role, [1,2])) {
            http_response_code(403);
            $clientAccept = $_SERVER['HTTP_ACCEPT'] ?? '';
            if (stripos($clientAccept, 'text/plain') !== false) {
                header('Content-Type: text/plain; charset=utf-8');
                echo 'Forbidden';
            } else {
                echo json_encode(['success' => false, 'message' => 'Forbidden']);
            }
            return;
        }


        // DEBUG: Echo the raw POST body for inspection
        $rawBody = file_get_contents('php://input');
        file_put_contents('/tmp/editor_chat_debug.log', $rawBody . "\n", FILE_APPEND);

        // Enforce a configurable maximum input size to avoid accidental huge payloads
        $maxInput = (int)($_ENV['EDITOR_CHAT_MAX_INPUT'] ?? 20000);
        if ($maxInput > 0 && strlen($rawBody) > $maxInput) {
            http_response_code(413);
            echo json_encode(['success' => false, 'message' => 'Request payload too large']);
            return;
        }
        // Optionally, echo to response for immediate feedback (remove in production)
        // echo $rawBody;

        $input = json_decode($rawBody, true);
        if (!$input || !isset($input['message'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing message', 'debug_raw' => $rawBody]);
            return;
        }

        $message = trim((string)$input['message']);
        if ($message === '') { http_response_code(400); echo json_encode(['success' => false, 'message' => 'Empty message']); return; }

        $model = isset($input['model']) ? trim((string)$input['model']) : 'kimi_k2';
        // Respect an allowlist for models in production (comma-separated env var)
        $allowedModels = array_filter(array_map('trim', explode(',', $_ENV['EDITOR_ALLOWED_MODELS'] ?? 'kimi_k2')));
        if (!in_array($model, $allowedModels, true)) {
            $model = $allowedModels[0] ?? 'kimi_k2';
        }
        // Optional file-creation params from frontend
        $createFile = !empty($input['create_file']);
        $createFilename = isset($input['filename']) ? trim((string)$input['filename']) : '';
        $createOverwrite = !empty($input['overwrite']);
        // Allow clients to ask the server to auto-apply assistant edits directly to main.
        // Only allow for full admin (role_id === 1) — editors must opt-in and are not allowed
        // to auto-apply directly to main by default.
        $autoApplyMain = !empty($input['auto_apply_main']) && ($role === 1);
        $allowOutsideWrites = ($role === 1) && (!empty($_ENV['MCP_ALLOW_ANY_FILE_WRITE']) && $_ENV['MCP_ALLOW_ANY_FILE_WRITE'] === '1' || !empty($_ENV['ALLOW_MCP_ANY_FILE_WRITE']) && $_ENV['ALLOW_MCP_ANY_FILE_WRITE'] === '1');
            $allowOutsideWrites = ($role === 1) && (!empty($_ENV['MCP_ALLOW_ANY_FILE_WRITE']) && $_ENV['MCP_ALLOW_ANY_FILE_WRITE'] === '1' || !empty($_ENV['ALLOW_MCP_ANY_FILE_WRITE']) && $_ENV['ALLOW_MCP_ANY_FILE_WRITE'] === '1');
        $fileEnc = isset($input['file']) ? trim((string)$input['file']) : null;

        // If the client sent the repo file via headers (e.g. X-Repo-File or
        // X-Repo-File-Base64), accept those as alternative transports so
        // non-browser or header-forwarding proxies can attach file context.
        // Prefer the Base64 header if present.
        try {
            if (empty($fileEnc)) {
                $hdrB64 = $_SERVER['HTTP_X_REPO_FILE_BASE64'] ?? null;
                $hdrRaw = $_SERVER['HTTP_X_REPO_FILE'] ?? null;
                if (!empty($hdrB64)) {
                    $fileEnc = trim((string)$hdrB64);
                    @file_put_contents('/tmp/editor_chat_debug.log', "Detected X-Repo-File-Base64 header\n", FILE_APPEND);
                } elseif (!empty($hdrRaw)) {
                    // Received raw path in header — encode to the same format
                    // the frontend uses (base64 of path).
                    $enc = base64_encode((string)$hdrRaw);
                    $fileEnc = $enc;
                    @file_put_contents('/tmp/editor_chat_debug.log', "Detected X-Repo-File header; encoded to base64\n", FILE_APPEND);
                }
            }
        } catch (\Throwable $_) { /* non-fatal header parsing */ }

        $fileContext = null;
        if ($fileEnc) {
            try {
                $decoded = base64_decode(urldecode($fileEnc));
                // Basic safety: only allow editing files inside the project (no absolute paths)
                if ($decoded && is_string($decoded)) {
                    $normalized = str_replace('..', '', $decoded);
                    $fullPath = realpath(ROOT_PATH . '/' . $normalized);
                    if ($fullPath && str_starts_with($fullPath, realpath(ROOT_PATH))) {
                        if (is_file($fullPath)) { $fileContext = ['path' => $normalized, 'content' => file_get_contents($fullPath)]; }
                    }
                }
            } catch (\Throwable $_) { $fileContext = null; }
        }

        // Forward the request to MCP. The MCP response may include a file
        // directive (JSON, fenced file block or FILE: header) — in that case
        // the server will parse it and create the file on behalf of the MCP
        // (this is the desired behavior: MCP -> server file writes).

        // Route to an MCP server if configured. Use env but allow admin override
        // when required for testing (`MCP_ALLOW_URL_OVERRIDE=1`) or if the
        // current user is a full admin (role_id === 1).
        $mcpUrl = $_ENV['MCP_SERVER_URL'] ?? ($_ENV['MCP_SERVER'] ?? 'http://127.0.0.1:9010');
        if (!empty($input['mcp_url'])) {
            $candidate = trim((string)$input['mcp_url']);
            if ($candidate !== '' && ($role === 1 || (!empty($_ENV['MCP_ALLOW_URL_OVERRIDE']) && $_ENV['MCP_ALLOW_URL_OVERRIDE'] === '1'))) {
                $mcpUrl = $candidate;
            }
        }

        // Toggle to include raw MCP payloads in responses for debugging.
        $includeRawDebug = (!empty($_ENV['MCP_DEBUG_RAW']) && $_ENV['MCP_DEBUG_RAW'] === '1');

        // Build payload for MCP
        // For chat-style providers (Kimi K2 / groq) send a 'messages' array
        // that includes a short system prompt and the user message. For
        // other providers we keep the simple {model,input,context} shape.
        if ($model === 'kimi_k2') {
            $messages = [];
            // Build a small repo summary so the assistant has default context
            $repoSummary = '';
            try {
                $root = realpath(ROOT_PATH) ?: rtrim(ROOT_PATH, DIRECTORY_SEPARATOR);
                $summaryPath = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'repo_summary.json';
                if (is_file($summaryPath)) {
                    $j = json_decode(file_get_contents($summaryPath), true);
                    if (is_array($j) && !empty($j['summary'])) $repoSummary = trim($j['summary']);
                }

                // Fallback: build a short summary from README and composer.json
                if ($repoSummary === '') {
                    $parts = [];
                    $readme = $root . DIRECTORY_SEPARATOR . 'README.md';
                    if (is_file($readme)) {
                        $rm = file_get_contents($readme);
                        $rm = preg_replace('/\s+/u', ' ', trim($rm));
                        $parts[] = 'README excerpt: ' . substr($rm, 0, 1500);
                    }
                    $composer = $root . DIRECTORY_SEPARATOR . 'composer.json';
                    if (is_file($composer)) {
                        $c = json_decode(file_get_contents($composer), true);
                        if (is_array($c)) {
                            $name = $c['name'] ?? null;
                            $desc = $c['description'] ?? null;
                            if ($name) $parts[] = 'Package: ' . $name . ($desc ? ' — ' . $desc : '');
                            if (!empty($c['require'])) {
                                $req = array_keys($c['require']);
                                $parts[] = 'Requires: ' . implode(', ', array_slice($req, 0, 8));
                            }
                        }
                    }
                    // list top-level folders of interest
                    $tops = [];
                    foreach (['src', 'public', 'docs', 'scripts', 'install', 'storage'] as $d) {
                        if (is_dir($root . DIRECTORY_SEPARATOR . $d)) $tops[] = $d;
                    }
                    if (!empty($tops)) $parts[] = 'Top-level dirs: ' . implode(', ', $tops);
                    $repoSummary = trim(implode(' | ', $parts));
                }
            } catch (\Throwable $_) { $repoSummary = ''; }

            $sys = 'You are Ginto — a helpful in-editor assistant. Be concise and helpful for code and file edits.';
            if ($repoSummary !== '') {
                $sys .= "\nRepository context: " . $repoSummary;
            }
            
            // Add sandbox tools info if user has an active sandbox
            $sandboxId = $_SESSION['sandbox_id'] ?? null;
            if (!empty($sandboxId) && class_exists('\Ginto\Helpers\LxdSandboxManager') && \Ginto\Helpers\LxdSandboxManager::sandboxExists($sandboxId)) {
                $sys .= "\n\n## SANDBOX ACCESS - AGENTIC MODE\n"
                    . "The user has an active sandbox (ID: {$sandboxId}). You have DIRECT ACCESS to their files.\n\n"
                    . "### AGENTIC WORKFLOW\n"
                    . "1. **Plan**: State your plan in 2-4 bullet points\n"
                    . "2. **Execute ONE tool**: Output exactly ONE tool_call JSON per response\n"
                    . "3. **Wait for result**: After the tool runs, you'll receive the result\n"
                    . "4. **Continue or Summarize**: If more steps remain, execute the next tool. If done, summarize.\n\n"
                    . "### Available Sandbox Tools:\n"
                    . "- `sandbox_list_files` - List files (args: path optional)\n"
                    . "- `sandbox_read_file` - Read a file (args: path)\n"
                    . "- `sandbox_write_file` - Write a file (args: path, content)\n"
                    . "- `sandbox_exec` - Run command (args: command)\n"
                    . "- `sandbox_create_project` - Create project (args: project_type, project_name)\n"
                    . "- `sandbox_delete_file` - Delete file/folder (args: path)\n\n"
                    . "### Tool Call Format:\n"
                    . "{\"tool_call\": {\"name\": \"tool_name\", \"arguments\": {\"arg1\": \"value1\"}}}\n\n"
                    . "### Project Types: html, php, react, vue, node, python, tailwind\n";
            }
            
            // Include any user-defined tool/task templates so the assistant
            // can map intents to multi-step tool sequences. These are stored
            // in storage/tool_tasks.json as an array of task definitions.
            try {
                $taskFile = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'tool_tasks.json';
                if (is_file($taskFile)) {
                    $tt = json_decode(file_get_contents($taskFile), true);
                    if (is_array($tt) && !empty($tt)) {
                        $sys .= "\nPredefined task templates (you may reference by taskId):\n";
                        foreach ($tt as $task) {
                            $id = $task['taskId'] ?? ($task['id'] ?? null);
                            $desc = $task['description'] ?? ($task['desc'] ?? '');
                            if ($id) {
                                $sys .= "- " . $id . ": " . trim(preg_replace('/\s+/u',' ', $desc)) . "\n";
                            }
                        }
                        $sys .= "When asked to execute a predefined task, respond with a single JSON object: {\"execute_task\": \"<taskId>\", \"confirm\": true|false}.";
                    }
                }
            } catch (\Throwable $_) { /* non-fatal */ }
            if ($fileContext && is_array($fileContext)) {
                $fileSnippet = substr((string)($fileContext['content'] ?? ''), 0, 2000);
                $sys .= "\nFile: " . ($fileContext['path'] ?? '') . "\n" . $fileSnippet;
            }
            $messages[] = ['role' => 'system', 'content' => $sys];
            $messages[] = ['role' => 'user', 'content' => $message];

            $payload = [
                'model' => $model,
                'messages' => $messages,
                // lightweight tuning defaults
                'temperature' => 0.6,
                'max_completion_tokens' => 512,
                'top_p' => 1
            ];
        } else {
            $payload = [
                'model' => $model,
                'input' => $message,
                'context' => [ 'file' => $fileContext ]
            ];
        }

        // Forward the request to MCP server
        try {
            $ch = curl_init(rtrim($mcpUrl, '/') . '/mcp');
            // detect if the client wants a streaming response (SSE)
            $clientAccept = $_SERVER['HTTP_ACCEPT'] ?? '';
            // If the client explicitly wants plain text, prefer a plain
            // non-SSE text response. Otherwise, we will allow text/event-stream
            // streaming when requested.
            $wantsPlainText = stripos($clientAccept, 'text/plain') !== false;
            $isStreamRequest = !$wantsPlainText && stripos($clientAccept, 'text/event-stream') !== false;

            if ($isStreamRequest) {
                // For streaming responses we don't want to buffer the response in PHP
                // — forward chunks to the client as they arrive. Increase timeouts.
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
                // allow a long-running streaming connection (0 = unlimited)
                curl_setopt($ch, CURLOPT_TIMEOUT, 0);
                // ensure the client sees streaming content and we don't let intermediaries buffer
                header('Content-Type: text/event-stream');
                header('Cache-Control: no-cache');
                // If running behind nginx with proxy buffering, this header helps disable buffering
                header('X-Accel-Buffering: no');
                // Write function will parse SSE frames from the upstream stream and
                // forward only the useful text deltas to the client — this avoids
                // dumping raw 'data: {...}' JSON into the chat UI.
                $sseBuf = '';
                curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) use (&$sseBuf) {
                        // Debug: append raw incoming chunk to a trace log so we can
                        // inspect exact SSE fragments and embedded JSON coming
                        // from the MCP server during streaming sessions.
                        try { @file_put_contents('/tmp/editor_chat_stream_raw.log', date('c') . ' ' . $data . "\n----\n", FILE_APPEND); } catch (\Throwable $_) { }
                    $sseBuf .= $data;

                    // Process each complete SSE event block (separated by \n\n)
                    while (strpos($sseBuf, "\n\n") !== false) {
                        [$block, $sseBuf] = explode("\n\n", $sseBuf, 2) + [1 => ''];

                        // Collect 'data:' payload lines
                        $lines = preg_split('/\r?\n/', trim($block));
                        $payload = '';
                        foreach ($lines as $line) {
                            if (str_starts_with($line, 'data:')) {
                                $payload .= preg_replace('/^data:\s?/', '', $line) . "\n";
                            }
                        }
                        $payload = trim($payload);
                        if ($payload === '') continue;

                        // Try decode JSON payload and extract a textual delta. Upstream
                        // SSE frames can include more than one JSON fragment in a
                        // single 'data' block (eg: metadata object then an array of
                        // deltas). Instead of blindly echoing the raw payload back
                        // to the client (which results in unreadable 'data: {...}'
                        // showing up in the assistant), split the payload into
                        // newline-separated fragments and decode each one, then
                        // extract only human-readable text fragments.

                        $extractTextParts = function (string $payload): array {
                            $payload = trim($payload);
                            if ($payload === '') return [];

                            // Try whole payload first
                            try { $whole = json_decode($payload, true, 512, JSON_THROW_ON_ERROR); } catch (\JsonException $_) { $whole = null; }

                            $candidates = [];
                            if (is_array($whole)) {
                                $candidates[] = $whole;
                            } else {
                                // Split on newline boundaries — SSE concatenates 'data:'
                                // lines into the payload separated by newlines. Try
                                // to decode each line; if a line isn't JSON try to
                                // split concatenated JSON tokens (e.g. {..}[..]) into
                                // separate fragments and decode each.
                                $lines = preg_split('/\r?\n/', $payload);
                                foreach ($lines as $ln) {
                                    $ln = trim(preg_replace('/^data:\s*/m', '', $ln));
                                    if ($ln === '') continue;

                                    // Split concatenated JSON tokens (curly/bracket-started)
                                    $frags = [];
                                    $s = $ln; $len = strlen($s); $i = 0;
                                    while ($i < $len) {
                                        // skip whitespace
                                        while ($i < $len && ctype_space($s[$i])) $i++;
                                        if ($i >= $len) break;
                                        $ch = $s[$i];
                                        if ($ch === '{' || $ch === '[') {
                                            $start = $i; $depth = 0; $inStr = false;
                                            for (; $i < $len; $i++) {
                                                $c = $s[$i];
                                                if ($c === '"') {
                                                    // count preceding backslashes
                                                    $j = $i - 1; $bs = 0;
                                                    while ($j >= 0 && $s[$j] === '\\') { $bs++; $j--; }
                                                    if ($bs % 2 === 0) $inStr = !$inStr;
                                                }
                                                if (!$inStr) {
                                                    if ($c === '{' || $c === '[') $depth++;
                                                    elseif ($c === '}' || $c === ']') $depth--;
                                                    if ($depth === 0) { $frags[] = substr($s, $start, $i - $start + 1); $i++; break; }
                                                }
                                            }
                                            continue;
                                        }
                                        // plain text until next JSON token
                                        $start = $i; while ($i < $len && $s[$i] !== '{' && $s[$i] !== '[') $i++;
                                        $frags[] = substr($s, $start, $i - $start);
                                    }

                                    foreach ($frags as $frag) {
                                        $frag = trim($frag);
                                        if ($frag === '') continue;
                                        try { $d = json_decode($frag, true, 512, JSON_THROW_ON_ERROR); } catch (\JsonException $_) { $d = null; }
                                        if (is_array($d)) $candidates[] = $d; else $candidates[] = $frag;
                                    }
                                }
                            }

                            $out = [];
                            foreach ($candidates as $cand) {
                                // If this candidate is a list of fragments (eg: an
                                // array of delta objects) iterate and extract each
                                // sub-piece rather than treating the whole list as
                                // a single associative payload.
                                if (is_array($cand) && array_values($cand) === $cand) {
                                    foreach ($cand as $sub) {
                                        if (!is_array($sub)) continue;
                                        if (isset($sub['delta']['content'])) $out[] = (string)$sub['delta']['content'];
                                        elseif (isset($sub['delta']['text'])) $out[] = (string)$sub['delta']['text'];
                                        // Some providers put content in a "reasoning" key
                                        // (e.g. partial reasoning HTML/text). Treat it as a
                                        // text fragment so it isn't shown as raw JSON.
                                        elseif (isset($sub['delta']['reasoning'])) $out[] = (string)$sub['delta']['reasoning'];
                                        elseif (isset($sub['text'])) $out[] = (string)$sub['text'];
                                    }
                                    continue;
                                }
                                $textPart = null;
                                if (is_array($cand)) {
                                    if (isset($cand['result']['structuredContent']['text']) && is_string($cand['result']['structuredContent']['text'])) {
                                        $textPart = $cand['result']['structuredContent']['text'];
                                    } elseif (isset($cand['result']['content'])) {
                                        if (is_array($cand['result']['content'])) {
                                            $textPart = implode('', array_map(fn($c) => (string)($c['text'] ?? $c['value'] ?? $c['content'] ?? ''), $cand['result']['content']));
                                        } elseif (is_string($cand['result']['content'])) {
                                            $textPart = $cand['result']['content'];
                                        }
                                    } elseif (isset($cand['choices'][0])) {
                                        $choice = $cand['choices'][0];
                                        if (isset($choice['message']['content']) && is_string($choice['message']['content'])) $textPart = $choice['message']['content'];
                                        elseif (isset($choice['delta']['content'])) $textPart = (string)$choice['delta']['content'];
                                        elseif (isset($choice['delta']['text'])) $textPart = (string)$choice['delta']['text'];
                                        elseif (isset($choice['delta']['reasoning'])) $textPart = (string)$choice['delta']['reasoning'];
                                        elseif (isset($choice['text'])) $textPart = (string)$choice['text'];
                                    } elseif (isset($cand['delta']) && is_array($cand['delta'])) {
                                        if (isset($cand['delta']['content'])) $textPart = (string)$cand['delta']['content'];
                                        elseif (isset($cand['delta']['text'])) $textPart = (string)$cand['delta']['text'];
                                        elseif (isset($cand['delta']['reasoning'])) $textPart = (string)$cand['delta']['reasoning'];
                                    } elseif (isset($cand['text']) && is_string($cand['text'])) {
                                        $textPart = $cand['text'];
                                    }
                                } else {
                                    // plain text fragment
                                    $textPart = (string)$cand;
                                }

                                if ($textPart !== null && $textPart !== '') $out[] = $textPart;
                            }

                            // Filter out common sentinel-only tokens emitted by some providers
                            $out = array_values(array_filter($out, function($s) {
                                if (!is_string($s)) return true;
                                $t = trim($s);
                                if ($t === '') return false;
                                $up = mb_strtoupper($t);
                                if ($up === '[DONE]' || $up === '[END]' || $up === '<DONE>') return false;
                                // also ignore single-character control markers
                                if (preg_match('/^\[?DONE\]?$/i', $t)) return false;
                                return true;
                            }));

                            return $out;
                        };

                        $textPieces = $extractTextParts($payload);
                        

                        // If we got some readable text fragments, emit them as
                        // incremental SSE 'data:' events to the client.
                        if (!empty($textPieces)) {
                                foreach ($textPieces as $textPart) {
                                        // Remove any accidental embedded 'data:' tokens that
                                        // may be present inside a fragment (sometimes
                                        // upstream producers put literal "data: ..." into
                                        // text content). This prevents repeated "data: "
                                        // being sent to the client.
                                        $textPart = preg_replace('/^data:\s*/mi', '', $textPart);
                                        $textPart = preg_replace('/\s*data:\s*/mi', ' ', $textPart);
                                        // Note: Do NOT use strip_tags() here as it removes
                                        // PHP opening/closing tags from code blocks in responses.
                                        // Normalize internal whitespace but preserve leading
                                        // spaces so fragments concatenate naturally in the
                                        // client. Replace runs of whitespace with a single
                                        // space but do not trim leading spaces which may be
                                        // meaningful for token boundaries.
                                        $textPart = preg_replace('/\s+/u', ' ', $textPart);

                                        // If this fragment itself is a serialized JSON
                                        // object (common when upstream embeds a JSON
                                        // string inside a text field), try decoding it
                                        // and extracting a readable assistant text field
                                        // such as choices[0].message.content or result.content.
                                        try {
                                            $maybe = null;
                                            if (is_string($textPart) && preg_match('/^[\s\[{]/', $textPart)) {
                                                $maybe = json_decode($textPart, true);
                                            }
                                            if (is_array($maybe)) {
                                                $innerText = null;
                                                if (isset($maybe['choices'][0]['message']['content']) && is_string($maybe['choices'][0]['message']['content'])) {
                                                    $innerText = $maybe['choices'][0]['message']['content'];
                                                } elseif (isset($maybe['choices'][0]['text']) && is_string($maybe['choices'][0]['text'])) {
                                                    $innerText = $maybe['choices'][0]['text'];
                                                } elseif (isset($maybe['result']['content']) && is_array($maybe['result']['content'])) {
                                                    // collect text parts inside result.content
                                                    $parts = [];
                                                    foreach ($maybe['result']['content'] as $c) {
                                                        if (is_array($c) && isset($c['text'])) $parts[] = $c['text'];
                                                        elseif (is_string($c)) $parts[] = $c;
                                                    }
                                                    if (!empty($parts)) $innerText = implode('', $parts);
                                                } elseif (isset($maybe['result']['content']) && is_string($maybe['result']['content'])) {
                                                    $innerText = $maybe['result']['content'];
                                                }

                                                if ($innerText !== null) {
                                                    $textPart = $innerText;
                                                }
                                            }
                                        } catch (\Throwable $_) { /* ignore decode errors */ }

                                        // Emit well-formed JSON event payloads. The client
                                        // will parse `event.data` as JSON and read the
                                        // `text` property. Using JSON prevents accidental
                                        // 'data:' tokens embedded in text from leaking into
                                        // the UI.
                                        $payloadJson = json_encode(['text' => $textPart]);
                                        echo "data: " . $payloadJson . "\n\n";
                                        if (function_exists('fastcgi_finish_request')) {
                                            @fastcgi_finish_request();
                                        } else {
                                            @ob_flush(); @flush();
                                        }
                                    }
                        }
                    }

                        // If we didn't find real newline-separated SSE frames but the
                        // upstream returned concatenated JSON tokens (no newline
                        // separators) we still need to process any complete top-level
                        // JSON fragments that were appended to the buffer. Some
                        // upstreams emit a stream of objects separated by spaces or
                        // directly concatenated; in these cases we attempt to peel
                        // complete JSON objects off the start of the buffer and
                        // process them immediately rather than waiting for \n\n.
                    // This happens when upstream embeds SSE payloads as a JSON
                    // string (escaped newlines) inside the result content.
                    // Always attempt to peel complete JSON fragments from the
                    // buffer — even when there aren't any \n\n separators.
                    // This prevents raw JSON objects concatenated inline from
                    // leaking into the UI.
                    while (true) {
                        $sseBuf = ltrim($sseBuf);
                        if ($sseBuf === '') break;

                        $first = $sseBuf[0];
                        if ($first !== '{' && $first !== '[') break;

                        // Attempt to find a complete top-level JSON fragment
                        $len = strlen($sseBuf);
                        $depth = 0; $inStr = false; $endIndex = null;
                        for ($i = 0; $i < $len; $i++) {
                            $c = $sseBuf[$i];
                            if ($c === '"') {
                                $j = $i - 1; $bs = 0; while ($j >= 0 && $sseBuf[$j] === '\\') { $bs++; $j--; }
                                if ($bs % 2 === 0) $inStr = !$inStr;
                            }
                            if (!$inStr) {
                                if ($c === '{' || $c === '[') $depth++;
                                elseif ($c === '}' || $c === ']') $depth--;
                                if ($depth === 0) { $endIndex = $i; break; }
                            }
                        }

                        if ($endIndex === null) break; // nothing complete yet

                        $frag = substr($sseBuf, 0, $endIndex + 1);
                        $sseBuf = substr($sseBuf, $endIndex + 1);

                        // Process the fragment as if it had arrived as its own data payload
                        $textPieces = $extractTextParts($frag);
                        if (!empty($textPieces)) {
                            foreach ($textPieces as $textPart) {
                                $textPart = preg_replace('/^data:\s*/mi', '', $textPart);
                                $textPart = preg_replace('/\s*data:\s*/mi', ' ', $textPart);
                                // Note: Do NOT use strip_tags() here as it removes
                                // PHP opening/closing tags from code blocks in responses.
                                $textPart = trim($textPart);
                                $payloadJson = json_encode(['text' => $textPart]);
                                echo "data: " . $payloadJson . "\n\n";
                                if (function_exists('fastcgi_finish_request')) { @fastcgi_finish_request(); } else { @ob_flush(); @flush(); }
                            }
                        }
                        // continue peeling more fragments
                    }

                    if (strpos($sseBuf, "\n\n") === false) {
                        $maybe = null;
                        try { $maybe = json_decode($sseBuf, true, 512, JSON_THROW_ON_ERROR); } catch (\JsonException $_) { $maybe = null; }
                        if (is_array($maybe)) {
                            // Try common locations for embedded stream text
                            $candidates = [];
                            if (isset($maybe['result']['content']) && is_array($maybe['result']['content'])) {
                                foreach ($maybe['result']['content'] as $c) {
                                    if (is_array($c) && isset($c['text'])) $candidates[] = $c['text'];
                                    elseif (is_string($c)) $candidates[] = $c;
                                }
                            }
                            if (isset($maybe['result']['content']) && is_string($maybe['result']['content'])) $candidates[] = $maybe['result']['content'];
                            if (isset($maybe['choices'][0]['message']['content']) && is_string($maybe['choices'][0]['message']['content'])) $candidates[] = $maybe['choices'][0]['message']['content'];

                            foreach ($candidates as $embedded) {
                                // The embedded content may contain literal "data: ...\n\n"
                                // sequences (already unescaped by json_decode) so we can
                                // split on real newlines and process frames.
                                if (!is_string($embedded) || trim($embedded) === '') continue;

                                $parts = preg_split('/\r?\n\r?\n/', $embedded);
                                foreach ($parts as $part) {
                                    $part = trim(preg_replace('/^data:\s*/m', '', $part));
                                    if ($part === '') continue;

                                    // Try to decode delta JSON inside this part
                                    $decodedInner = null;
                                    try { $decodedInner = json_decode($part, true, 512, JSON_THROW_ON_ERROR); } catch (\JsonException $_) { $decodedInner = null; }

                                    $textPart = null;
                                    if (is_array($decodedInner)) {
                                        if (isset($decodedInner['choices'][0]['message']['content']) && is_string($decodedInner['choices'][0]['message']['content'])) $textPart = $decodedInner['choices'][0]['message']['content'];
                                        elseif (isset($decodedInner['choices'][0]['delta']['content'])) $textPart = (string)$decodedInner['choices'][0]['delta']['content'];
                                        elseif (isset($decodedInner['choices'][0]['delta']['reasoning'])) $textPart = (string)$decodedInner['choices'][0]['delta']['reasoning'];
                                        elseif (isset($decodedInner['result']['content']) && is_string($decodedInner['result']['content'])) $textPart = $decodedInner['result']['content'];
                                    } else {
                                        // not JSON content — use raw part
                                        $textPart = $part;
                                    }

                                        if ($textPart !== null && $textPart !== '') {
                                        // Similarly strip embedded 'data:' from this inner piece
                                        $textPart = preg_replace('/^data:\s*/mi', '', $textPart);
                                        $textPart = preg_replace('/\s*data:\s*/mi', ' ', $textPart);
                                        // Note: Do NOT use strip_tags() here as it removes
                                        // PHP opening/closing tags from code blocks in responses.
                                        $textPart = preg_replace('/\s+/u', ' ', $textPart);
                                        $payloadJson = json_encode(['text' => $textPart]);
                                        echo "data: " . $payloadJson . "\n\n";
                                        if (function_exists('fastcgi_finish_request')) {
                                            @fastcgi_finish_request();
                                        } else {
                                            @ob_flush(); @flush();
                                        }
                                    }
                                }
                            }

                            // Clear buffer after processing single JSON response to
                            // avoid re-processing it on subsequent chunks.
                            $sseBuf = '';
                        }
                    }

                    return strlen($data);
                });
            } else {
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
                $nonStreamTimeout = (int)($_ENV['EDITOR_CHAT_TIMEOUT'] ?? 30);
                curl_setopt($ch, CURLOPT_TIMEOUT, max(5, $nonStreamTimeout));
            }
            curl_setopt($ch, CURLOPT_POST, true);
            // Respect client's Accept header when forwarding to the MCP
            // so the MCP proxy can decide whether to stream (text/event-stream)
            // or return a JSON payload. For non-streaming, only accept JSON.
            $incomingAccept = $_SERVER['HTTP_ACCEPT'] ?? 'application/json, text/event-stream';
            $forwardHeaders = [
                'Content-Type: application/json',
            ];
            
            if ($isStreamRequest) {
                $forwardHeaders[] = 'Accept: ' . $incomingAccept;
            } else {
                $forwardHeaders[] = 'Accept: application/json';
            }

            // If the incoming client provided Authorization, forward it to MCP.
            // Otherwise fall back to an MCP_API_KEY configured in the environment.
            $incomingAuth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
            if ($incomingAuth) {
                // ensure we forward exactly what the client sent
                array_unshift($forwardHeaders, 'Authorization: ' . $incomingAuth);
            } else {
                $mcpKey = $_ENV['MCP_API_KEY'] ?? '';
                if ($mcpKey) {
                    // prepend Authorization so downstream MCP gets the key
                    array_unshift($forwardHeaders, 'Authorization: Bearer ' . $mcpKey);
                }
            }

            // If the client presented an MCP session id header, forward that to the MCP
            // so the downstream transport can correlate with an existing session.
            $incomingMcpSession = $_SERVER['HTTP_MCP_SESSION_ID'] ?? '';
            if ($incomingMcpSession) {
                $forwardHeaders[] = 'mcp-session-id: ' . $incomingMcpSession;
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $forwardHeaders);

            // The local MCProxy (mcp-server2) expects JSON-RPC shaped requests.
            // Wrap the provider-specific payload inside a JSON-RPC envelope so
            // downstream servers like our local groq-mcp-server accept it.
            // Wrap as a JSON-RPC 'invoke' call where params specify tool & args
            // This matches the groq-mcp-server's JSONRPC 'invoke' shape used by
            // our local MCP which runs tools registered under @mcp.tool.
            // Use the exact RPC shape expected by the local MCP server's streamable
            // HTTP endpoint: method "tools/call" with params.name and params.argument
            // (server validates `CallToolRequest` with .params.name and .params.argument)
            $rpcBody = [
                'jsonrpc' => '2.0',
                'id' => uniqid('rpc_', true),
                'method' => 'tools/call',
                // MCP spec: CallToolRequest.params.arguments (plural)
                'params' => [ 'name' => 'chat_completion', 'arguments' => $payload ],
            ];
            // Debug: log the outgoing RPC payload so we can inspect what
            // the server forwarded to the MCP for troubleshooting.
            try {
                @file_put_contents('/tmp/editor_chat_sent.log', date('c') . ' ' . json_encode($rpcBody, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND);
            } catch (\Throwable $_) { /* non-fatal */ }
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($rpcBody));
            
            if ($isStreamRequest) {
                // For streaming, execute and exit - data is echoed directly
                $res = curl_exec($ch);
                $err = curl_error($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($res === false || $err) {
                    http_response_code(502);
                    if ($wantsPlainText) {
                        header('Content-Type: text/plain; charset=utf-8');
                        echo 'MCP request failed: ' . ($err ?: 'empty');
                    } else {
                        echo json_encode(['success' => false, 'message' => 'MCP request failed: ' . ($err ?: 'empty')]);
                    }
                }
                // Streaming response has already been echoed, so just return
                return;
            }
            
            // Non-streaming: get the response
            $res = curl_exec($ch);
            $err = curl_error($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // Debug: log MCP raw response for troubleshooting (trim to 1MB)
            try {
                $logPath = '/tmp/editor_chat_mcp_response.log';
                $toLog = is_string($res) ? $res : json_encode($res);
                if (is_string($toLog)) {
                    $snippet = strlen($toLog) > 1048576 ? substr($toLog, 0, 1048576) . "\n...[truncated]..." : $toLog;
                    @file_put_contents($logPath, date('c') . ' HTTP_CODE:' . $code . "\n" . $snippet . "\n---\n", FILE_APPEND);
                }
            } catch (\Throwable $_) { /* non-fatal */ }

                if ($res === false || $err) {
                    http_response_code(502);
                    if ($wantsPlainText) {
                        header('Content-Type: text/plain; charset=utf-8');
                        echo 'MCP request failed: ' . ($err ?: 'empty');
                    } else {
                        echo json_encode(['success' => false, 'message' => 'MCP request failed: ' . ($err ?: 'empty')]);
                    }
                    return;
                }

            // Fix: If MCP returns multiple JSON objects concatenated, SSE frames,
            // or embeds JSON inside a textual "data:" block, attempt to peel
            // a single well-formed JSON fragment for parsing. This helps when
            // upstream returns stream-like payloads that include `event:`/`data:`
            // framing even though the client asked for plain text.
            $m = null;
            // If the body looks like SSE or contains 'data:' lines, try to
            // extract the first/last embedded JSON object and decode it.
            if ($wantsPlainText && (strpos($res, "event:") !== false || strpos($res, "data:") !== false)) {
                // Heuristic: find the earliest '{' that begins a JSON object
                // after the first 'data:' token and try to decode from there,
                // trimming trailing characters until valid JSON is found.
                $dataPos = stripos($res, 'data:');
                $searchStart = $dataPos !== false ? $dataPos : 0;
                $firstBrace = strpos($res, '{', $searchStart);
                if ($firstBrace === false) $firstBrace = strpos($res, '[', $searchStart);
                if ($firstBrace !== false) {
                    $candidate = substr($res, $firstBrace);
                    // Try progressively trimming suffix until json_decode succeeds
                    while ($candidate !== '') {
                        $decoded = json_decode($candidate, true);
                        if (is_array($decoded)) { $m = $decoded; break; }
                        // Trim trailing characters (but not more than 10% each loop)
                        $len = strlen($candidate);
                        if ($len <= 2) break;
                        $trim = max(1, intval($len * 0.02));
                        $candidate = substr($candidate, 0, $len - $trim);
                    }
                }
            }

            // If we didn't extract via SSE heuristic above, attempt to parse
            // concatenated JSON objects by splitting on newlines and using the
            // last valid JSON object. Fallback to decoding the whole body.
            if ($m === null) {
                $jsonObj = null;
                $lines = preg_split('/[\r\n]+/', $res);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '') continue;
                    $maybe = json_decode($line, true);
                    if (is_array($maybe)) {
                        $jsonObj = $maybe;
                    }
                }
                $m = $jsonObj ?: json_decode($res, true);
            }

            if (is_array($m)) {
                // Minimal proxy behavior: parse whatever the MCP returned and
                // return a simple 'reply' string plus the raw payload. Do NOT
                // perform any automatic file writes from the server side. If the
                // MCP tool returned a file directive, present it as a proposal to
                // the client via 'file_proposed' and let the client decide how to
                // apply it (via MCP tools or editorFile endpoint).

                $replyText = null;

                // 1) JSON-RPC / tool result (result.content / structuredContent)
                if (isset($m['result'])) {
                    $r = $m['result'];
                    if (isset($r['structuredContent']['text'])) {
                        $replyText = (string)$r['structuredContent']['text'];
                    } elseif (isset($r['content']) && is_array($r['content'])) {
                        $replyText = implode('', array_map(fn($c) => $c['text'] ?? '', $r['content']));
                    } elseif (isset($r['content']) && is_string($r['content'])) {
                        $replyText = (string)$r['content'];
                    }

                    // If the tool returned an explicit file: { path, content } shape,
                    // return that as a proposal instead of creating it here.
                        if (!empty($r['file']) && is_array($r['file']) && !empty($r['file']['path'])) {
                            $proposal = ['path' => $r['file']['path'], 'content' => $r['file']['content'] ?? ''];
                            if ($wantsPlainText) {
                                // Return the textual reply but include a header so the
                                // client can detect an attached file proposal if desired.
                                header('Content-Type: text/plain; charset=utf-8');
                                header('X-Assistant-File-Proposed: ' . base64_encode(json_encode($proposal)));
                                echo $replyText ?: '';
                            } else {
                                $out = ['success' => true, 'reply' => $replyText, 'file_proposed' => $proposal];
                                if ($includeRawDebug) $out['raw'] = $m;
                                echo json_encode($out);
                            }
                            return;
                        }
                }

                // 2) OpenAI / Groq choices
                if ($replyText === null) {
                    // Prefer the common 'choices[0].message.content' shape but
                    // ignore sentinel-only values like "[DONE]".
                    $candidate = null;
                    if (isset($m['choices'][0]['message']['content'])) $candidate = (string)$m['choices'][0]['message']['content'];
                    elseif (isset($m['choices'][0]['text'])) $candidate = (string)$m['choices'][0]['text'];

                    if ($candidate !== null) {
                        $ct = trim($candidate);
                        $up = mb_strtoupper($ct);
                        if ($ct === '' || $up === '[DONE]' || $up === '[END]' || $up === '<DONE>') {
                            // sentinel only — ignore and keep replyText null so
                            // other fields may be examined below.
                        } else {
                            // If candidate contains JSON-encoded content, try
                            // to decode it and extract inner text (some upstreams
                            // embed a serialized JSON in the content field).
                            $inner = null;
                            if (preg_match('/^[\s\[{]/', $ct)) {
                                $d = json_decode($ct, true);
                                if (is_array($d)) {
                                    if (isset($d['choices'][0]['message']['content'])) $inner = (string)$d['choices'][0]['message']['content'];
                                    elseif (isset($d['result']['content']) && is_string($d['result']['content'])) $inner = $d['result']['content'];
                                }
                            }
                            $replyText = $inner ?: $candidate;
                        }
                    }
                }

                // 3) Simple shapes
                if ($replyText === null && isset($m['reply']) && is_string($m['reply'])) $replyText = $m['reply'];

                // Also attempt to detect a file directive inside the textual reply
                if ($replyText !== null) {
                    $parsed = $this->parseFileDirective((string)$replyText);
                    if ($parsed && !empty($parsed['path'])) {
                        $proposal = ['path' => $parsed['path'], 'content' => $parsed['content'] ?? '', 'commit' => !empty($parsed['commit']) ? true : false, 'commit_message' => $parsed['commit_message'] ?? null];
                                if ($wantsPlainText) {
                                    header('Content-Type: text/plain; charset=utf-8');
                                    header('X-Assistant-File-Proposed: ' . base64_encode(json_encode($proposal)));
                                    echo $replyText ?: '';
                                } else {
                                    $out = ['success' => true, 'reply' => $replyText, 'file_proposed' => $proposal];
                                    if ($includeRawDebug) $out['raw'] = $m;
                                    // Attempt to extract structured tasks from the assistant reply
                                    try {
                                        $tasks = $this->parseTasksFromText((string)($replyText ?? ''));
                                        if ($tasks !== null) $out['tasks'] = $tasks;
                                    } catch (\Throwable $_) { /* non-fatal */ }
                                    echo json_encode($out);
                                }
                        return;
                    }
                }

                // If the replyText itself is a JSON string (some upstreams
                // embed a serialized JSON blob inside a textual field), try
                // to decode it and extract the inner assistant message.
                if ($wantsPlainText && is_string($replyText) && preg_match('/^[\s\[{]/', $replyText)) {
                    $inner = null;
                    $try = trim($replyText);
                    $decodedInner = json_decode($try, true);
                    if (is_array($decodedInner)) {
                        $inner = $decodedInner;
                    } else {
                        // If direct decode failed, attempt to find a JSON
                        // substring inside and decode that.
                        $first = strpos($try, '{');
                        if ($first !== false) {
                            $cand = substr($try, $first);
                            while ($cand !== '') {
                                $d = json_decode($cand, true);
                                if (is_array($d)) { $inner = $d; break; }
                                $len = strlen($cand);
                                if ($len <= 2) break;
                                $cand = substr($cand, 0, $len - 1);
                            }
                        }
                    }

                    if (is_array($inner)) {
                        // Attempt to find a canonical assistant text field
                        $innerText = null;
                        if (isset($inner['choices'][0]['message']['content'])) $innerText = (string)$inner['choices'][0]['message']['content'];
                        elseif (isset($inner['result']['content']) && is_string($inner['result']['content'])) $innerText = (string)$inner['result']['content'];
                        elseif (isset($inner['result']['content']) && is_array($inner['result']['content'])) $innerText = implode('', array_map(fn($c) => is_array($c) ? ($c['text'] ?? '') : (string)$c, $inner['result']['content']));
                        if ($innerText !== null) $replyText = $innerText;
                    }
                }

                // Final sanitization and live-file-request workflow.
                try {
                    $safeReply = $replyText;
                    if (is_string($safeReply)) {
                        $trim = trim($safeReply);
                        $up = mb_strtoupper($trim);
                        if ($trim === '' || $up === '[DONE]' || $up === '[END]' || $up === '<DONE>') {
                            $safeReply = null;
                        }
                    }

                    // If the assistant returned a JSON object requesting files
                    // to be attached (live workflow), handle it here by fetching
                    // the requested files and re-invoking the MCP up to N rounds.
                    $maxRounds = 3;
                    $round = 0;
                    $finalDecoded = $m;
                    $finalReplyText = $safeReply;

                    while ($round < $maxRounds) {
                        $round++;
                        $maybeReq = null;
                        try { $maybeReq = json_decode((string)($finalReplyText ?? ''), true, 512, JSON_THROW_ON_ERROR); } catch (\JsonException $_) { $maybeReq = null; }
                        if (!is_array($maybeReq)) break;

                        $filesRequested = [];
                        if (!empty($maybeReq['request_file']) && is_string($maybeReq['request_file'])) $filesRequested[] = $maybeReq['request_file'];
                        if (!empty($maybeReq['request_files']) && is_array($maybeReq['request_files'])) {
                            foreach ($maybeReq['request_files'] as $f) { if (is_string($f)) $filesRequested[] = $f; }
                        }
                        if (empty($filesRequested)) break;

                        // limit count and sanitize
                        $filesRequested = array_slice(array_map(function($p){ return trim((string)$p); }, $filesRequested), 0, 6);
                        $attachments = [];
                        $root = realpath(ROOT_PATH) ?: rtrim(ROOT_PATH, DIRECTORY_SEPARATOR);
                        foreach ($filesRequested as $fp) {
                            $fpNorm = ltrim(str_replace('\\','/',$fp), '/');
                            $full = realpath($root . DIRECTORY_SEPARATOR . $fpNorm);
                            if (!$full) continue;
                            if (!str_starts_with($full, $root)) continue; // outside repo
                            if (!is_file($full)) continue;
                            $content = @file_get_contents($full);
                            if ($content === false) continue;
                            // truncate large files to keep prompt reasonable
                            $attachments[] = ['path' => $fpNorm, 'content' => substr($content, 0, 20000)];
                        }
                        if (empty($attachments)) break;

                        // Build a new payload: include a system message that attaches
                        // the requested files, then re-call the MCP tool 'chat_completion'.
                        $attachText = "Attached files (round {$round}):\n";
                        foreach ($attachments as $a) {
                            $attachText .= "FILE: " . $a['path'] . "\n" . substr($a['content'], 0, 2000) . "\n---\n";
                        }

                        // Build messages array: preserve previous system context if available
                        $newMessages = [];
                        // include original system message if present
                        if (!empty($messages) && is_array($messages) && isset($messages[0]) && $messages[0]['role'] === 'system') {
                            $newMessages[] = $messages[0];
                        }
                        $newMessages[] = ['role' => 'system', 'content' => $attachText];
                        // re-send the original user message as context
                        $newMessages[] = ['role' => 'user', 'content' => $message];

                        $newPayload = [ 'model' => $model, 'messages' => $newMessages, 'temperature' => 0.6, 'max_completion_tokens' => 512, 'top_p' => 1 ];

                        // perform a synchronous non-streaming RPC call to MCP
                        $rpc2 = [ 'jsonrpc' => '2.0', 'id' => uniqid('rpc_', true), 'method' => 'tools/call', 'params' => ['name' => 'chat_completion', 'arguments' => $newPayload] ];
                        $ch2 = curl_init(rtrim($mcpUrl, '/') . '/mcp');
                        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch2, CURLOPT_POST, true);
                        curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode($rpc2));
                        $headers2 = [ 'Content-Type: application/json', 'Accept: application/json' ];
                        $mcpKey = $_ENV['GROQ_API_KEY'] ?? '';
                        if ($mcpKey) $headers2[] = 'Authorization: Bearer ' . $mcpKey;
                        curl_setopt($ch2, CURLOPT_HTTPHEADER, $headers2);
                        curl_setopt($ch2, CURLOPT_TIMEOUT, max(5, (int)($_ENV['EDITOR_CHAT_TIMEOUT'] ?? 30)));
                        $res2 = curl_exec($ch2);
                        $err2 = curl_error($ch2);
                        $code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                        curl_close($ch2);
                        if ($res2 === false || $err2) break;
                        $decoded2 = json_decode($res2, true);
                        if (!is_array($decoded2)) break;
                        $finalDecoded = $decoded2;
                        // attempt to extract textual reply again
                        $newReply = null;
                        if (isset($finalDecoded['result'])) {
                            $r2 = $finalDecoded['result'];
                            if (isset($r2['structuredContent']['text'])) $newReply = (string)$r2['structuredContent']['text'];
                            elseif (isset($r2['content']) && is_string($r2['content'])) $newReply = (string)$r2['content'];
                            elseif (isset($r2['content']) && is_array($r2['content'])) $newReply = implode('', array_map(fn($c)=>(string)($c['text'] ?? $c['value'] ?? $c['content'] ?? ''), $r2['content']));
                        }
                        if ($newReply === null && isset($finalDecoded['choices'][0])) {
                            $ch = $finalDecoded['choices'][0];
                            if (isset($ch['message']['content'])) $newReply = (string)$ch['message']['content'];
                            elseif (isset($ch['text'])) $newReply = (string)$ch['text'];
                        }
                        if ($newReply === null) {
                            // fallback: try raw result -> reply
                            $newReply = is_string($res2) ? $res2 : json_encode($res2);
                        }

                        $finalReplyText = $newReply;
                        // loop - the assistant may again request more files
                        $finalReplyText = trim((string)$finalReplyText);
                        // prepare for next iteration
                        $finalReplyText = $finalReplyText;
                        // Set messages to newMessages so any further attachments preserve
                        $messages = $newMessages;
                        // continue loop
                    }

                    // choose what to return to client
                    $outReply = $finalReplyText ?? $safeReply;
                    if ($wantsPlainText) {
                        header('Content-Type: text/plain; charset=utf-8');
                        echo $outReply;
                    } else {
                        $out = ['success' => true, 'reply' => $outReply];
                        if ($includeRawDebug) $out['raw'] = $finalDecoded ?? $m;

                        // Detect assistant-issued tool-call JSON (canonical shape)
                        // or an execute_task request. If present and authorized,
                        // validate and optionally execute via the MCP proxy.
                        try {
                            $maybeJson = null;
                            if (is_string($outReply) && preg_match('/^[\s\[{]/', trim($outReply))) {
                                $maybeJson = json_decode(trim($outReply), true);
                            }

                            // Helper to perform an MCP call and return decoded result
                            $callMcp = function(string $toolName, $arguments) use ($mcpUrl) {
                                $rpc = [ 'jsonrpc' => '2.0', 'id' => uniqid('rpc_', true), 'method' => 'tools/call', 'params' => ['name' => $toolName, 'arguments' => $arguments] ];
                                $chx = curl_init(rtrim($mcpUrl, '/') . '/mcp');
                                curl_setopt($chx, CURLOPT_RETURNTRANSFER, true);
                                curl_setopt($chx, CURLOPT_POST, true);
                                curl_setopt($chx, CURLOPT_POSTFIELDS, json_encode($rpc));
                                $headersx = [ 'Content-Type: application/json', 'Accept: application/json' ];
                                $mcpKey = $_ENV['MCP_API_KEY'] ?? '';
                                if ($mcpKey) $headersx[] = 'Authorization: Bearer ' . $mcpKey;
                                curl_setopt($chx, CURLOPT_HTTPHEADER, $headersx);
                                curl_setopt($chx, CURLOPT_TIMEOUT, 20);
                                $resx = curl_exec($chx);
                                $errx = curl_error($chx);
                                $codex = curl_getinfo($chx, CURLINFO_HTTP_CODE);
                                curl_close($chx);
                                if ($resx === false || $errx) return ['ok' => false, 'error' => 'MCP call failed: ' . ($errx ?: 'empty')];
                                $decoded = json_decode($resx, true);
                                return ['ok' => true, 'result' => $decoded ?? $resx, 'http_code' => $codex];
                            };

                            // If assistant asked to execute a predefined task
                            if (is_array($maybeJson) && !empty($maybeJson['execute_task'])) {
                                $taskId = trim((string)$maybeJson['execute_task']);
                                $confirm = !empty($maybeJson['confirm']);
                                $root = realpath(ROOT_PATH) ?: rtrim(ROOT_PATH, DIRECTORY_SEPARATOR);
                                $taskFile = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'tool_tasks.json';
                                $foundTask = null;
                                if (is_file($taskFile)) {
                                    $all = json_decode(file_get_contents($taskFile), true) ?: [];
                                    foreach ($all as $t) { if (!empty($t['taskId']) && $t['taskId'] === $taskId) { $foundTask = $t; break; } }
                                }
                                if ($foundTask === null) {
                                    $out['execute_task'] = ['status' => 'not_found', 'taskId' => $taskId];
                                    echo json_encode($out);
                                    return;
                                }

                                // Prefer an explicit example JSON to run or a steps array
                                $exampleJson = null;
                                if (!empty($foundTask['examples']) && is_array($foundTask['examples'])) {
                                    foreach ($foundTask['examples'] as $ex) {
                                        if (!empty($ex['json']) && is_array($ex['json'])) { $exampleJson = $ex['json']; break; }
                                    }
                                }

                                $steps = $foundTask['steps'] ?? ($exampleJson['steps'] ?? null);

                                // If neither example JSON nor steps exist, fail with helpful message
                                if ($exampleJson === null && $steps === null) {
                                    $out['execute_task'] = ['status' => 'no_example', 'taskId' => $taskId, 'message' => 'Task found but no runnable example.json or steps provided'];
                                    echo json_encode($out);
                                    return;
                                }

                                $autoApprove = false;
                                if (is_array($exampleJson)) $autoApprove = $autoApprove || !empty($exampleJson['autoApprove']) || !empty($exampleJson['auto_approve']);
                                $autoApprove = $autoApprove || $confirm;

                                if (!$autoApprove) {
                                    $out['execute_task'] = ['status' => 'requires_confirmation', 'taskId' => $taskId, 'example' => $exampleJson, 'steps' => $steps];
                                    echo json_encode($out);
                                    return;
                                }

                                // Execute either a single example tool or a steps array.
                                $stepResults = [];
                                $writtenPaths = [];
                                $root = realpath(ROOT_PATH) ?: rtrim(ROOT_PATH, DIRECTORY_SEPARATOR);
                                $isAdmin = ($role === 1);

                                // Helper: run a single step (mcp/tool/file/delete/git)
                                $runStep = function($s) use (&$callMcp, &$stepResults, &$writtenPaths, $root, $isAdmin, $uid, $message) {
                                    $type = $s['type'] ?? null;
                                    // Allow legacy shapes: if 'tool' provided treat as MCP step
                                    if (!$type && !empty($s['tool'])) $type = 'mcp';

                                    if ($type === 'mcp') {
                                        $toolName = $s['tool'] ?? $s['tool_name'] ?? null;
                                        $params = $s['params'] ?? ($s['arguments'] ?? new \stdClass());
                                        $res = $callMcp($toolName, $params);
                                        $stepResults[] = ['type' => 'mcp', 'tool' => $toolName, 'result' => $res];
                                        return $res['ok'];
                                    }

                                    if ($type === 'file' || isset($s['file'])) {
                                        $file = $s['file'] ?? $s;
                                        $path = trim((string)($file['path'] ?? ($file['filename'] ?? '')));
                                        $content = isset($file['content']) ? (string)$file['content'] : '';
                                        $overwrite = !empty($file['overwrite']);
                                        $commit = !empty($file['commit']);
                                        $commit_message = isset($file['commit_message']) ? (string)$file['commit_message'] : '';

                                        if ($path === '') {
                                            $stepResults[] = ['type' => 'file', 'status' => 'error', 'message' => 'Missing path'];
                                            return false;
                                        }

                                        // Only allow file writes that resolve inside repo
                                        $norm = ltrim(str_replace('\\','/',$path), '/');
                                        $target = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $norm);
                                        if (!str_starts_with(realpath(dirname($target)) ?: dirname($target), $root)) {
                                            $stepResults[] = ['type' => 'file', 'status' => 'error', 'message' => 'Path outside repo not allowed', 'path'=>$path];
                                            return false;
                                        }

                                        // perform write using existing helper
                                        $writeRes = $this->attemptWriteFile($path, $content, $overwrite, false);
                                        $stepResults[] = ['type'=>'file','path'=>$path,'write'=>$writeRes];
                                        if ($writeRes['success']) {
                                            $writtenPaths[] = $writeRes['path'];
                                            // If commit requested, only admins may commit by default
                                            if ($commit) {
                                                if (!$isAdmin) {
                                                    $stepResults[] = ['type'=>'file','status'=>'error','message'=>'Commit requires admin privileges','path'=>$path];
                                                    return false;
                                                }
                                                $commitRes = $this->commitPathsWithAudit([$writeRes['path']], $commit_message ?: 'Assistant file update', $uid, $message, json_encode($s));
                                                $stepResults[] = ['type'=>'file','path'=>$path,'commit'=>$commitRes];
                                                if (!$commitRes['success']) return false;
                                            }
                                        } else {
                                            return false;
                                        }
                                        return true;
                                    }

                                    if ($type === 'delete' || !empty($s['delete'])) {
                                        $file = $s['file'] ?? $s;
                                        $path = trim((string)($file['path'] ?? ($file['filename'] ?? $file['path'] ?? '')));
                                        $commit = !empty($file['commit']);
                                        $commit_message = isset($file['commit_message']) ? (string)$file['commit_message'] : '';

                                        if ($path === '') {
                                            $stepResults[] = ['type' => 'delete', 'status' => 'error', 'message' => 'Missing path'];
                                            return false;
                                        }

                                        $norm = ltrim(str_replace('\\','/',$path), '/');
                                        $target = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $norm);
                                        $realTarget = realpath($target) ?: $target;
                                        if (!str_starts_with(dirname($realTarget), $root) && !str_starts_with($realTarget, $root)) {
                                            $stepResults[] = ['type' => 'delete', 'status' => 'error', 'message' => 'Path outside repo not allowed', 'path'=>$path];
                                            return false;
                                        }

                                        if (!is_file($realTarget)) {
                                            $stepResults[] = ['type' => 'delete', 'status' => 'error', 'message' => 'File not found', 'path'=>$path];
                                            return false;
                                        }

                                        try {
                                            $ok = @unlink($realTarget);
                                        } catch (\Throwable $e) { $ok = false; }
                                        if (!$ok) {
                                            $stepResults[] = ['type' => 'delete', 'status' => 'error', 'message' => 'Failed to delete file', 'path'=>$path];
                                            return false;
                                        }
                                        $stepResults[] = ['type' => 'delete', 'path' => $path, 'status' => 'deleted'];
                                        $writtenPaths[] = $realTarget;

                                        if ($commit) {
                                            if (!$isAdmin) { $stepResults[] = ['type'=>'delete','status'=>'error','message'=>'Commit requires admin']; return false; }
                                            $commitRes = $this->commitPathsWithAudit([$realTarget], $commit_message ?: 'Assistant delete file', $uid, $message, json_encode($s));
                                            $stepResults[] = ['type'=>'delete','path'=>$path,'commit'=>$commitRes];
                                            if (!$commitRes['success']) return false;
                                        }
                                        return true;
                                    }

                                    if ($type === 'git') {
                                        // Support simple git commit action via safeGitCommit
                                        $action = $s['action'] ?? '';
                                        if ($action === 'commit') {
                                            $paths = $s['paths'] ?? [];
                                            if (!is_array($paths)) $paths = [$paths];
                                            if (!$isAdmin) { $stepResults[] = ['type'=>'git','status'=>'error','message'=>'Commit requires admin']; return false; }
                                            $res = $this->commitPathsWithAudit($paths, $s['message'] ?? 'Assistant commit', $uid, $message, json_encode($s));
                                            $stepResults[] = ['type'=>'git','action'=>'commit','result'=>$res];
                                            return $res['success'];
                                        }
                                        // Unknown git action
                                        $stepResults[] = ['type'=>'git','status'=>'error','message'=>'Unknown git action'];
                                        return false;
                                    }

                                    $stepResults[] = ['status'=>'skipped','reason'=>'unknown step type','step'=>$s];
                                    return false;
                                };

                                // If exampleJson provided and it contains a single tool, run that as before
                                if (is_array($exampleJson) && empty($steps)) {
                                    $toolName = $exampleJson['tool'] ?? ($exampleJson['tool_name'] ?? null);
                                    if ($toolName) {
                                        $resCall = $callMcp($toolName, $exampleJson['params'] ?? new \stdClass());
                                        try { $this->recordAssistantAudit($uid, $message, [], json_encode(['taskId'=>$taskId,'example'=>$exampleJson,'mcp_result'=>$resCall])); } catch (\Throwable $_) { }
                                        if (!$resCall['ok']) { $out['execute_task'] = ['status' => 'failed', 'error' => $resCall['error']]; echo json_encode($out); return; }
                                        $out['execute_task'] = ['status' => 'executed', 'taskId' => $taskId, 'tool' => $toolName, 'result' => $resCall['result']];
                                        echo json_encode($out);
                                        return;
                                    }
                                }

                                // If steps are present, execute each in order
                                if (is_array($steps)) {
                                    foreach ($steps as $s) {
                                        $ok = $runStep($s);
                                        if (!$ok) {
                                            // stop execution on first failed step
                                            $out['execute_task'] = ['status' => 'partial_failure', 'taskId' => $taskId, 'results' => $stepResults];
                                            try { $this->recordAssistantAudit($uid, $message, $writtenPaths, json_encode(['taskId'=>$taskId,'steps'=>$steps,'results'=>$stepResults])); } catch (\Throwable $_) { }
                                            echo json_encode($out);
                                            return;
                                        }
                                    }
                                    // All steps succeeded
                                    $out['execute_task'] = ['status' => 'executed_steps', 'taskId' => $taskId, 'results' => $stepResults];
                                    try { $this->recordAssistantAudit($uid, $message, $writtenPaths, json_encode(['taskId'=>$taskId,'steps'=>$steps,'results'=>$stepResults])); } catch (\Throwable $_) { }
                                    echo json_encode($out);
                                    return;
                                }

                                // Fallback: nothing run
                                $out['execute_task'] = ['status' => 'no_action', 'taskId' => $taskId];
                                echo json_encode($out);
                                return;
                            }

                            // If assistant returned a direct tool-call JSON
                            if (is_array($maybeJson) && !empty($maybeJson['tool']) && isset($maybeJson['params'])) {
                                $toolName = trim((string)$maybeJson['tool']);
                                $params = $maybeJson['params'];
                                $auto = !empty($maybeJson['autoApprove']) || !empty($maybeJson['auto_approve']) || !empty($maybeJson['auto']) ;
                                $confirmExecute = !empty($maybeJson['confirm_execute']) || !empty($maybeJson['confirm']);

                                // If not explicitly approved, require confirm_execute
                                if (!$auto && !$confirmExecute) {
                                    $out['execute_pending'] = ['tool' => $toolName, 'params' => $params, 'message' => 'Execution requires confirmation. Reply with {"confirm_execute":true} to proceed.'];
                                    echo json_encode($out);
                                    return;
                                }

                                // Perform the MCP call
                                $resCall = $callMcp($toolName, $params);
                                // record audit
                                try { $this->recordAssistantAudit($uid, $message, [], json_encode(['tool'=>$toolName,'params'=>$params,'mcp_result'=>$resCall])); } catch (\Throwable $_) { }

                                if (!$resCall['ok']) {
                                    $out['execute_result'] = ['status' => 'error', 'error' => $resCall['error']];
                                    echo json_encode($out);
                                    return;
                                }

                                $out['execute_result'] = ['status' => 'ok', 'tool' => $toolName, 'result' => $resCall['result']];
                                echo json_encode($out);
                                return;
                            }

                            // Not a tool-call shape; attempt to parse structured tasks
                            $tasks = $this->parseTasksFromText((string)($outReply ?? ''));
                            if ($tasks !== null) $out['tasks'] = $tasks;
                        } catch (\Throwable $_) {
                            // best-effort only — if detection crashes, fall back to returning the reply
                        }

                        echo json_encode($out);
                    }
                } catch (\Throwable $e) {
                    if ($wantsPlainText) { header('Content-Type: text/plain; charset=utf-8'); echo $replyText ?: ''; }
                    else { $out = ['success' => true, 'reply' => $replyText]; if ($includeRawDebug) $out['raw'] = $m; echo json_encode($out); }
                }
                return;
            }

            // Nothing useful — treat raw response as a textual reply or a proposal
            $text = trim((string)$res);
            if ($text !== '') {
                // If the text contains a file directive, return it as a proposal
                $parsed = $this->parseFileDirective($text);
                if ($parsed && !empty($parsed['path'])) {
                    $proposal = ['path' => $parsed['path'], 'content' => $parsed['content'] ?? '', 'commit' => !empty($parsed['commit']) ? true : false, 'commit_message' => $parsed['commit_message'] ?? null];
                    if ($wantsPlainText) {
                        header('Content-Type: text/plain; charset=utf-8');
                        header('X-Assistant-File-Proposed: ' . base64_encode(json_encode($proposal)));
                        echo $text;
                    } else {
                        $out = ['success' => true, 'reply' => $text, 'file_proposed' => $proposal];
                        if ($includeRawDebug) $out['raw'] = $res;
                        echo json_encode($out);
                    }
                    return;
                }

                // If the raw text looks like JSON (often the MCP returns a
                // serialized JSON string), attempt to decode and extract a
                // human-readable field such as choices[0].message.content or
                // result.content. If only sentinel tokens are found, replace
                // with a friendly fallback so the UI doesn't display raw JSON.
                $safeReply = null;
                $maybeJson = null;
                if (strlen($text) > 0 && ($text[0] === '{' || $text[0] === '[')) {
                    try { $maybeJson = json_decode($text, true); } catch (\Throwable $_) { $maybeJson = null; }
                }
                if (is_array($maybeJson)) {
                    // try common shapes
                    if (isset($maybeJson['choices'][0]['message']['content'])) $candidate = (string)$maybeJson['choices'][0]['message']['content'];
                    elseif (isset($maybeJson['choices'][0]['text'])) $candidate = (string)$maybeJson['choices'][0]['text'];
                    elseif (isset($maybeJson['result']['content']) && is_string($maybeJson['result']['content'])) $candidate = $maybeJson['result']['content'];
                    else $candidate = null;

                    if (!empty($candidate) || $candidate === '') {
                        $ct = trim((string)$candidate);
                        $up = mb_strtoupper($ct);
                        if ($ct === '' || $up === '[DONE]' || $up === '[END]' || $up === '<DONE>') {
                            $safeReply = 'No assistant output (upstream returned control tokens).';
                        } else {
                            $safeReply = $candidate;
                        }
                    }
                }

                if ($safeReply === null) {
                    // If the text itself appears to be JSON but we couldn't
                    // extract a friendly field, avoid returning the raw JSON
                    // to the client; instead present the fallback message.
                    if (is_array($maybeJson)) {
                        $safeReply = 'No assistant output (upstream returned JSON wrapper).';
                    } else {
                        $safeReply = $text;
                    }
                }

                        if ($wantsPlainText) {
                            header('Content-Type: text/plain; charset=utf-8');
                            echo $safeReply;
                        } else {
                            $out = ['success' => true, 'reply' => $safeReply];
                            if ($includeRawDebug) $out['raw'] = $m;
                            try {
                                $tasks = $this->parseTasksFromText((string)($safeReply ?? ''));
                                if ($tasks !== null) $out['tasks'] = $tasks;
                            } catch (\Throwable $_) { /* non-fatal */ }
                            echo json_encode($out);
                        }
                return;
            }

            if ($wantsPlainText) {
                header('Content-Type: text/plain; charset=utf-8');
                echo '';
            } else {
                $out = ['success' => true, 'reply' => null];
                if ($includeRawDebug) $out['raw'] = $res;
                echo json_encode($out);
            }
            return;

        } catch (\Throwable $e) {
            http_response_code(500);
            $clientAccept = $_SERVER['HTTP_ACCEPT'] ?? '';
            if (stripos($clientAccept, 'text/plain') !== false) {
                header('Content-Type: text/plain; charset=utf-8');
                echo 'Server error: ' . $e->getMessage();
            } else {
                echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
            }
            return;
        }
    }

    /**
     * POST /admin/pages/editor/tts
     * Request body: { text: string, voice?: string, model?: string }
     * Returns { success: true, mime: 'audio/wav', data: '<base64>' } on success.
     */
    public function editorTts(): void
    {
        session_start();
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
        if (!function_exists('validateCsrfToken') || !validateCsrfToken($csrf)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            return;
        }
        $uid = (int)($_SESSION['user_id'] ?? 0);
        if ($uid <= 0) { http_response_code(403); echo json_encode(['success' => false, 'message' => 'Not authenticated']); return; }
        try { $u = $this->db->get('users', ['role_id'], ['id' => $uid]); } catch (\Throwable $_) { $u = null; }
        $role = $u['role_id'] ?? 0; if (!in_array($role, [1,2])) { http_response_code(403); echo json_encode(['success' => false, 'message' => 'Forbidden']); return; }

        $raw = file_get_contents('php://input');
        // Debugging hook: store request payload for investigation
        @file_put_contents('/tmp/editor_file_debug.log', date('c') . ' ' . json_encode([ 'session_uid' => $_SESSION['user_id'] ?? null, 'raw' => $raw ]) . "\n", FILE_APPEND);
        $input = json_decode($raw, true);
        if (!$input || !isset($input['text'])) { http_response_code(400); echo json_encode(['success' => false, 'message' => 'Missing text']); return; }
        $text = trim((string)$input['text']); if ($text === '') { http_response_code(400); echo json_encode(['success' => false, 'message' => 'Empty text']); return; }

        $voice = isset($input['voice']) ? trim((string)$input['voice']) : 'Arista-PlayAI';
        $model = isset($input['model']) ? trim((string)$input['model']) : 'playai-tts';

        $apiKey = $_ENV['GROQ_API_KEY'];
        if (!$apiKey) { http_response_code(500); echo json_encode(['success' => false, 'message' => 'No API key available']); return; }

        $payload = json_encode(['model' => $model, 'input' => $text, 'voice' => $voice, 'response_format' => 'wav']);
        $ch = curl_init('https://api.groq.com/openai/v1/audio/speech');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [ 'Content-Type: application/json', 'Authorization: Bearer ' . $apiKey ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($res === false || !$res) { http_response_code(502); echo json_encode(['success' => false, 'message' => 'TTS request failed: ' . ($err ?: 'empty')]); return; }
        if ($code >= 400) {
            $decoded = json_decode($res, true);
            $message = is_array($decoded) && isset($decoded['error']) ? ($decoded['error']['message'] ?? json_encode($decoded)) : ('HTTP ' . $code);
            // Special handling for TTS model terms acceptance
            if (str_contains($message, 'requires terms acceptance') && str_contains($message, 'console.groq.com')) {
                $message = 'TTS model requires terms acceptance. Please visit the Groq console playground for the TTS model and accept the terms: ' . $message;
            }
            http_response_code(502);
            echo json_encode(['success' => false, 'message' => 'TTS provider error: ' . $message, 'raw' => $decoded ?? $res]);
            return;
        }

        $b64 = base64_encode($res);
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'mime' => 'audio/wav', 'data' => $b64]);
        return;
    }

    /**
     * GET /admin/pages/editor/mcp-tools
     * Returns the MCP server tools list (proxied) so the editor UI can adapt
     * to the available tools (e.g., check if chat_completion is registered).
     */
    public function editorMcpTools(): void
    {
        session_start();
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_GET['csrf_token'] ?? '');
        if (!function_exists('validateCsrfToken') || !validateCsrfToken($csrf)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            return;
        }

        $uid = (int)($_SESSION['user_id'] ?? 0);
        if ($uid <= 0) { http_response_code(403); echo json_encode(['success' => false, 'message' => 'Not authenticated']); return; }
        try { $u = $this->db->get('users', ['role_id'], ['id' => $uid]); } catch (\Throwable $_) { $u = null; }
        $role = $u['role_id'] ?? 0; if (!in_array($role, [1,2])) { http_response_code(403); echo json_encode(['success' => false, 'message' => 'Forbidden']); return; }

        $mcpUrl = $_ENV['MCP_SERVER_URL'] ?? ($_ENV['MCP_SERVER'] ?? 'http://127.0.0.1:9010');

        $rpcBody = [ 'jsonrpc' => '2.0', 'id' => uniqid('rpc_', true), 'method' => 'tools/list', 'params' => new \stdClass() ];

        $ch = curl_init(rtrim($mcpUrl, '/') . '/mcp');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($rpcBody));
        // Build headers and include an MCP session id so the local MCP proxy
        // accepts this POST (some MCP servers require an mcp-session-id header).
        $headers = [ 'Content-Type: application/json', 'Accept: application/json' ];
        try {
            $headers[] = 'mcp-session-id: ' . uniqid('mcp_', true);
        } catch (\Throwable $_) { /* ignore */ }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $res = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($res === false || $err) {
                // Attempt a local fallback: run scripts/dump_discovered_tools.php to
                // discover project MCP handlers and return their tool schemas so
                // the UI can show available tools even when the live MCP session
                // is not yet initialized.
                try {
                    $root = realpath(ROOT_PATH) ?: rtrim(ROOT_PATH, DIRECTORY_SEPARATOR);
                    $script = $root . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'dump_discovered_tools.php';
                    if (is_file($script)) {
                        $cmd = 'php ' . escapeshellarg($script) . ' 2>&1';
                        @exec($cmd, $outLines, $outCode);
                        $outText = is_array($outLines) ? implode("\n", $outLines) : (string)($outLines ?? '');
                        // Try to decode JSON output from the dump script (it prints JSON on stdout)
                        // Try to locate any JSON blob inside the script output. The
                        // dump script may print informational logs before the JSON
                        // payload; attempt to find the first/last JSON array/object
                        // and decode it so the UI can receive a clean `tools` array.
                        $maybeJson = null;
                        // Quick attempt: full-string decode
                        $tryFull = @json_decode($outText, true);
                        if (is_array($tryFull)) {
                            $maybeJson = $tryFull;
                        } else {
                            // Heuristic: search for the first '[' or '{' that starts a
                            // valid JSON value when decoding the suffix. Try from the
                            // last 32768 characters backwards to be robust when logs
                            // precede the JSON block.
                            $len = strlen($outText);
                            $startCandidates = [];
                            // collect positions of '{' and '['
                            for ($i = max(0, $len - 32768); $i < $len; $i++) {
                                $ch = $outText[$i];
                                if ($ch === '{' || $ch === '[') $startCandidates[] = $i;
                            }
                            // try each candidate (prefer earlier ones so we capture
                            // a full JSON payload) — stop on first valid decode
                            foreach ($startCandidates as $pos) {
                                $cand = substr($outText, $pos);
                                $decodedCand = @json_decode($cand, true);
                                if (is_array($decodedCand)) { $maybeJson = $decodedCand; break; }
                                // also try trimming trailing garbage
                                $trimmed = rtrim($cand);
                                $decodedTrim = @json_decode($trimmed, true);
                                if (is_array($decodedTrim)) { $maybeJson = $decodedTrim; break; }
                            }
                        }

                        if (is_array($maybeJson)) {
                            echo json_encode(['success' => true, 'tools' => $maybeJson]);
                            return;
                        }

                        // If we couldn't extract JSON, return raw text as a single
                        // fallback tool entry so the UI still shows something useful.
                        $fallbackTool = [
                            'name' => 'local_registry_fallback',
                            'description' => 'Fallback: raw discovery output (see raw)',
                            'meta' => ['raw' => substr($outText, 0, 20000)]
                        ];
                        echo json_encode(['success' => true, 'tools' => [$fallbackTool], 'raw' => $outText]);
                        return;
                    }
                } catch (\Throwable $_) {
                    // ignore and fall through to error response
                }

                http_response_code(502);
                echo json_encode(['success' => false, 'message' => 'MCP request failed: ' . ($err ?: 'empty')]);
                return;
        }

        // Try to decode direct JSON first
        $decoded = json_decode($res, true);

        // If decoding failed but response looks like SSE (event:/data: framing)
        // attempt to extract the JSON payload embedded inside the first `data:` block.
        if ($decoded === null && (strpos($res, "data:") !== false || strpos($res, "event:") !== false)) {
            $dataPos = stripos($res, 'data:');
            $searchStart = $dataPos !== false ? $dataPos : 0;
            $firstBrace = strpos($res, '{', $searchStart);
            if ($firstBrace === false) $firstBrace = strpos($res, '[', $searchStart);
            if ($firstBrace !== false) {
                $candidate = substr($res, $firstBrace);
                // Try progressively trimming suffix until json_decode succeeds
                while ($candidate !== '') {
                    $try = json_decode($candidate, true);
                    if (is_array($try)) { $decoded = $try; break; }
                    $len = strlen($candidate);
                    if ($len <= 2) break;
                    $trim = max(1, intval($len * 0.02));
                    $candidate = substr($candidate, 0, $len - $trim);
                }
            }
        }

        if ($decoded === null) {
            // return raw payload as reply for client debugging
            echo json_encode(['success' => true, 'raw' => $res]);
            return;
        }

        echo json_encode(['success' => true, 'tools' => $decoded['result']['tools'] ?? []]);
        return;
    }

    /**
     * POST /admin/pages/editor/mcp-call
     * Proxy a call to the configured MCP server tools/call endpoint.
     * Request body: { tool: string, arguments: object }
     */
    public function editorMcpCall(): void
    {
        session_start();
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
        if (!function_exists('validateCsrfToken') || !validateCsrfToken($csrf)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            return;
        }

        $uid = (int)($_SESSION['user_id'] ?? 0);
        if ($uid <= 0) { http_response_code(403); echo json_encode(['success' => false, 'message' => 'Not authenticated']); return; }
        try { $u = $this->db->get('users', ['role_id'], ['id' => $uid]); } catch (\Throwable $_) { $u = null; }
        $role = $u['role_id'] ?? 0; if (!in_array($role, [1,2])) { http_response_code(403); echo json_encode(['success' => false, 'message' => 'Forbidden']); return; }

        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true);
        if (!is_array($input) || empty($input['tool'])) { http_response_code(400); echo json_encode(['success' => false, 'message' => 'Missing tool name']); return; }

        $tool = trim((string)$input['tool']);
            $arguments = $input['arguments'] ?? new \stdClass();

        $mcpUrl = $_ENV['MCP_SERVER_URL'] ?? ($_ENV['MCP_SERVER'] ?? 'http://127.0.0.1:9010');
        $rpcBody = [ 'jsonrpc' => '2.0', 'id' => uniqid('rpc_', true), 'method' => 'tools/call', 'params' => ['name' => $tool, 'arguments' => $arguments] ];

        $ch = curl_init(rtrim($mcpUrl, '/') . '/mcp');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($rpcBody));
        $headers = [ 'Content-Type: application/json', 'Accept: application/json' ];

        // If MCP api key is configured server side, pass it through
        $mcpKey = $_ENV['MCP_API_KEY'] ?? '';
        if ($mcpKey) $headers[] = 'Authorization: Bearer ' . $mcpKey;

        // Pass MCP session id through if present from the client
        $incomingMcpSession = $_SERVER['HTTP_MCP_SESSION_ID'] ?? '';
        if ($incomingMcpSession) $headers[] = 'mcp-session-id: ' . $incomingMcpSession;

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);

        $res = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($res === false || $err) { http_response_code(502); echo json_encode(['success' => false, 'message' => 'MCP request failed: ' . ($err ?: 'empty')]); return; }

        $decoded = json_decode($res, true);
        if ($decoded === null) { echo json_encode(['success' => true, 'raw' => $res]); return; }

        echo json_encode(['success' => true, 'result' => $decoded['result'] ?? $decoded]);
        return;
    }

    /**
     * POST /admin/pages/editor/stt
     * Accepts a multipart upload with input file in `file` and optional `model` form value.
     * Returns { success: true, text: '...' }
     */
    public function editorStt(): void
    {
        session_start();
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
        if (!function_exists('validateCsrfToken') || !validateCsrfToken($csrf)) { http_response_code(403); echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']); return; }
        $uid = (int)($_SESSION['user_id'] ?? 0); if ($uid <= 0) { http_response_code(403); echo json_encode(['success' => false, 'message' => 'Not authenticated']); return; }
        try { $u = $this->db->get('users', ['role_id'], ['id' => $uid]); } catch (\Throwable $_) { $u = null; }
        $role = $u['role_id'] ?? 0; if (!in_array($role, [1,2])) { http_response_code(403); echo json_encode(['success' => false, 'message' => 'Forbidden']); return; }

        if (empty($_FILES) || !isset($_FILES['file'])) { http_response_code(400); echo json_encode(['success' => false, 'message' => 'Missing file upload']); return; }
        $file = $_FILES['file']; if ($file['error'] !== UPLOAD_ERR_OK) { http_response_code(400); echo json_encode(['success' => false, 'message' => 'Upload error']); return; }

        $tmp = $file['tmp_name']; 
        $model = trim((string)($_POST['model'] ?? 'whisper-large-v3-turbo'));
        $apiKey = $_ENV['GROQ_API_KEY'] ?? ($_ENV['KIMI_API_KEY'] ?? ''); if (!$apiKey) { http_response_code(500); echo json_encode(['success' => false, 'message' => 'No API key available']); return; }

        // Debug: Log file info
        error_log("STT Debug: File received - name: {$file['name']}, size: {$file['size']}, type: {$file['type']}, tmp: $tmp");
        error_log("STT Debug: MIME type from file: " . mime_content_type($tmp));
        error_log("STT Debug: File exists: " . (file_exists($tmp) ? 'yes' : 'no'));
        if (file_exists($tmp)) {
            error_log("STT Debug: File size on disk: " . filesize($tmp));
        }

        // Debug helper: save an inspectable copy of the uploaded file so we can
        // audit what the STT provider received (useful when transcription is
        // repeatedly wrong or returns a specific phrase). Saved in /tmp.
        try {
            if (file_exists($tmp)) {
                $saveName = '/tmp/stt_saved_' . date('Ymd_His') . '_' . uniqid() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', basename($file['name']));
                @copy($tmp, $saveName);
                error_log("STT Debug: saved uploaded file to $saveName (size=" . (file_exists($saveName) ? filesize($saveName) : 0) . ")");
            }
        } catch (\Throwable $_) { /* non-fatal */ }

        $cfile = new \CURLFile($tmp, mime_content_type($tmp), basename($file['name']));
        $post = [ 'file' => $cfile, 'model' => $model, 'response_format' => 'verbose_json' ];

        $ch = curl_init('https://api.groq.com/openai/v1/audio/transcriptions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [ 'Authorization: Bearer ' . $apiKey ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);

        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        // Debug: Log API response
        error_log("STT Debug: API response code: $code");
        error_log("STT Debug: API response error: $err");
        error_log("STT Debug: API response body: " . substr($res, 0, 500));

        if ($res === false || !$res) { http_response_code(502); echo json_encode(['success' => false, 'message' => 'STT request failed: ' . ($err ?: 'empty')]); return; }
        if ($code >= 400) { $decoded = json_decode($res, true); $message = is_array($decoded) && isset($decoded['error']) ? ($decoded['error']['message'] ?? json_encode($decoded)) : ('HTTP ' . $code); http_response_code(502); echo json_encode(['success' => false, 'message' => 'STT provider error: ' . $message, 'raw' => $decoded ?? $res]); return; }

        $decoded = json_decode($res, true); 
        $text = '';
        if (is_array($decoded)) { if (isset($decoded['text'])) $text = $decoded['text']; elseif (isset($decoded['data'][0]['text'])) $text = $decoded['data'][0]['text'] ?? ''; }
        
        // Debug: Log extracted text
        error_log("STT Debug: Extracted text: '$text'");
        
        echo json_encode(['success' => true, 'text' => $text, 'raw' => $decoded]);
        return;
    }

    /**
     * POST /admin/pages/editor/file
     * Request body: { filename: string, content: string, overwrite?: bool, commit?: bool, commit_message?: string }
     * Creates or overwrites a file inside the project and optionally commits it.
     */
    public function editorFile(): void
    {
        session_start();
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
        if (!function_exists('validateCsrfToken') || !validateCsrfToken($csrf)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            return;
        }
        $uid = (int)($_SESSION['user_id'] ?? 0);
        if ($uid <= 0) { http_response_code(403); echo json_encode(['success' => false, 'message' => 'Not authenticated']); return; }
        try { $u = $this->db->get('users', ['role_id'], ['id' => $uid]); } catch (\Throwable $_) { $u = null; }
        $role = $u['role_id'] ?? 0; if (!in_array($role, [1,2])) { http_response_code(403); echo json_encode(['success' => false, 'message' => 'Forbidden']); return; }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) { http_response_code(400); echo json_encode(['success' => false, 'message' => 'Invalid input']); return; }

        $filename = isset($input['filename']) ? trim((string)$input['filename']) : '';
        $content = isset($input['content']) ? (string)$input['content'] : '';
        $overwrite = !empty($input['overwrite']);
        $commit = !empty($input['commit']);
        // allow an explicit auto-apply-to-main flag here (only full admins)
        $autoApplyMain = !empty($input['auto_apply_main']) && ($role === 1);
        $commit_message = isset($input['commit_message']) ? trim((string)$input['commit_message']) : '';

        if ($filename === '') { http_response_code(400); echo json_encode(['success' => false, 'message' => 'Missing filename']); return; }
        if ($content === '') { http_response_code(400); echo json_encode(['success' => false, 'message' => 'Missing content']); return; }

        $writeResult = $this->attemptWriteFile($filename, $content, $overwrite, $allowOutsideWrites);
        // log result for troubleshooting (only safe client path, avoid leaking absolute paths)
        $logEntry = ['path_requested' => $filename, 'success' => $writeResult['success'], 'message' => $writeResult['message'], 'client_path' => $this->sanitizeClientPath($filename ?? null, $writeResult['path'] ?? null)];
        @file_put_contents('/tmp/editor_file_debug.log', date('c') . ' attemptWriteFile -> ' . json_encode($logEntry) . "\n", FILE_APPEND);
        if (!$writeResult['success']) { http_response_code(500); echo json_encode(['success' => false, 'message' => $writeResult['message']]); return; }

        $out = ['success' => true, 'message' => 'File written', 'file_path' => $this->sanitizeClientPath($filename ?? null, $writeResult['path'] ?? null)];
                if ($commit || $autoApplyMain) {
                    $commitMessage = $commit_message ?: 'Commit from editor assistant';
                    $commitRes = $this->commitPathsWithAudit([$writeResult['path']], $commitMessage, $uid, $commitMessage, $content);
                    $out['commit'] = $commitRes;
                }
        header('Content-Type: application/json');
        echo json_encode($out);
        return;
    }
}
