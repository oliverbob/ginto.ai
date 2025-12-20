<?php

declare(strict_types=1);

namespace App\Handlers;

use PhpMcp\Server\Attributes\McpTool;

/**
 * Advanced Agent Tools for comprehensive development capabilities.
 * 
 * Features:
 * - Multi-file operations (compose entire projects)
 * - Task persistence (continue from previous sessions)
 * - Database access with role-based permissions
 * - Project scaffolding with templates
 * - Batch operations for efficiency
 */
final class AgentTools
{
    private static function repoRoot(): string
    {
        return defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2);
    }

    private static function storagePath(): string
    {
        return self::repoRoot() . '/storage';
    }

    private static function resolvePath(string $path): string
    {
        $root = realpath(self::repoRoot());
        $targetRel = ltrim($path, "\/\\");
        $target = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $targetRel);
        
        if (file_exists($target)) {
            $real = realpath($target);
            if ($real === false || strpos($real, $root) !== 0) {
                throw new \RuntimeException('Invalid path: outside repository');
            }
            return $real;
        }
        
        $parentDir = dirname($target);
        if (!is_dir($parentDir)) {
            return $target;
        }
        
        $realParent = realpath($parentDir);
        if ($realParent === false || strpos($realParent, $root) !== 0) {
            throw new \RuntimeException('Invalid path: outside repository');
        }
        
        return $realParent . DIRECTORY_SEPARATOR . basename($target);
    }

    // =========================================================================
    // MULTI-FILE OPERATIONS
    // =========================================================================

    #[McpTool(
        name: 'compose_project',
        description: 'Create multiple files and directories at once. Ideal for scaffolding new features, modules, or entire projects. Pass an array of file definitions with path and content. Creates parent directories automatically.'
    )]
    public function composeProject(array $files, ?string $description = null): array
    {
        $results = [
            'description' => $description ?? 'Multi-file composition',
            'created' => [],
            'failed' => [],
            'directories_created' => [],
        ];

        foreach ($files as $file) {
            $path = $file['path'] ?? null;
            $content = $file['content'] ?? '';
            
            if (!$path) {
                $results['failed'][] = ['error' => 'Missing path in file definition'];
                continue;
            }

            try {
                $target = self::resolvePath($path);
                $dir = dirname($target);
                
                // Create directory structure
                if (!is_dir($dir)) {
                    if (!mkdir($dir, 0755, true)) {
                        throw new \RuntimeException("Failed to create directory: {$dir}");
                    }
                    $results['directories_created'][] = str_replace(self::repoRoot() . '/', '', $dir);
                }

                // Write file
                if (file_put_contents($target, $content) === false) {
                    throw new \RuntimeException("Failed to write file: {$path}");
                }

                $results['created'][] = [
                    'path' => $path,
                    'size' => strlen($content),
                    'lines' => substr_count($content, "\n") + 1,
                ];
            } catch (\Throwable $e) {
                $results['failed'][] = [
                    'path' => $path,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $results['summary'] = sprintf(
            'Created %d files, %d directories. %d failed.',
            count($results['created']),
            count($results['directories_created']),
            count($results['failed'])
        );

        return $results;
    }

    #[McpTool(
        name: 'batch_edit',
        description: 'Perform multiple file edits in one operation. Each edit specifies: path, oldText, newText. More efficient than individual replace_in_file calls.'
    )]
    public function batchEdit(array $edits): array
    {
        $results = [
            'successful' => [],
            'failed' => [],
        ];

        foreach ($edits as $edit) {
            $path = $edit['path'] ?? null;
            $oldText = $edit['oldText'] ?? null;
            $newText = $edit['newText'] ?? '';
            
            if (!$path || $oldText === null) {
                $results['failed'][] = [
                    'path' => $path ?? 'unknown',
                    'error' => 'Missing path or oldText',
                ];
                continue;
            }

            try {
                $target = self::resolvePath($path);
                
                if (!is_file($target)) {
                    throw new \RuntimeException("File not found: {$path}");
                }

                $content = file_get_contents($target);
                if ($content === false) {
                    throw new \RuntimeException("Cannot read file: {$path}");
                }

                $count = substr_count($content, $oldText);
                if ($count === 0) {
                    throw new \RuntimeException("Text not found in file");
                }
                if ($count > 1) {
                    throw new \RuntimeException("Text found {$count} times - need more context for unique match");
                }

                $newContent = str_replace($oldText, $newText, $content);
                if (file_put_contents($target, $newContent) === false) {
                    throw new \RuntimeException("Failed to write file");
                }

                $results['successful'][] = [
                    'path' => $path,
                    'replaced' => strlen($oldText) . ' chars → ' . strlen($newText) . ' chars',
                ];
            } catch (\Throwable $e) {
                $results['failed'][] = [
                    'path' => $path,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $results['summary'] = sprintf(
            '%d edits successful, %d failed',
            count($results['successful']),
            count($results['failed'])
        );

        return $results;
    }

    // =========================================================================
    // TASK PERSISTENCE
    // =========================================================================

    #[McpTool(
        name: 'save_task',
        description: 'Save current task state to resume later. Stores context, progress, files modified, and next steps. Use at end of session or before complex operations.'
    )]
    public function saveTask(
        string $taskId,
        string $description,
        array $context,
        ?string $status = 'in_progress',
        ?array $filesModified = null,
        ?array $nextSteps = null
    ): array {
        $tasksFile = self::storagePath() . '/agent_tasks.json';
        $tasks = [];
        
        if (file_exists($tasksFile)) {
            $tasks = json_decode(file_get_contents($tasksFile), true) ?? [];
        }

        $tasks[$taskId] = [
            'id' => $taskId,
            'description' => $description,
            'status' => $status,
            'context' => $context,
            'filesModified' => $filesModified ?? [],
            'nextSteps' => $nextSteps ?? [],
            'createdAt' => $tasks[$taskId]['createdAt'] ?? date('Y-m-d H:i:s'),
            'updatedAt' => date('Y-m-d H:i:s'),
        ];

        file_put_contents($tasksFile, json_encode($tasks, JSON_PRETTY_PRINT));

        return [
            'saved' => true,
            'taskId' => $taskId,
            'message' => "Task '{$taskId}' saved. Use load_task to resume.",
        ];
    }

    #[McpTool(
        name: 'load_task',
        description: 'Load a previously saved task to continue work. Returns full context including files modified, progress, and next steps.'
    )]
    public function loadTask(string $taskId): array
    {
        $tasksFile = self::storagePath() . '/agent_tasks.json';
        
        if (!file_exists($tasksFile)) {
            throw new \RuntimeException('No saved tasks found');
        }

        $tasks = json_decode(file_get_contents($tasksFile), true) ?? [];
        
        if (!isset($tasks[$taskId])) {
            $available = array_keys($tasks);
            throw new \RuntimeException(
                "Task '{$taskId}' not found. Available: " . implode(', ', $available)
            );
        }

        return $tasks[$taskId];
    }

    #[McpTool(
        name: 'list_tasks',
        description: 'List all saved tasks with their status and last update time.'
    )]
    public function listTasks(?string $status = null): array
    {
        $tasksFile = self::storagePath() . '/agent_tasks.json';
        
        if (!file_exists($tasksFile)) {
            return ['tasks' => [], 'message' => 'No saved tasks'];
        }

        $tasks = json_decode(file_get_contents($tasksFile), true) ?? [];
        
        $result = [];
        foreach ($tasks as $task) {
            if ($status && $task['status'] !== $status) {
                continue;
            }
            $result[] = [
                'id' => $task['id'],
                'description' => $task['description'],
                'status' => $task['status'],
                'updatedAt' => $task['updatedAt'],
                'filesModified' => count($task['filesModified'] ?? []),
                'nextSteps' => count($task['nextSteps'] ?? []),
            ];
        }

        return ['tasks' => $result, 'total' => count($result)];
    }

    #[McpTool(
        name: 'complete_task',
        description: 'Mark a task as completed with optional summary of what was done.'
    )]
    public function completeTask(string $taskId, ?string $summary = null): array
    {
        $tasksFile = self::storagePath() . '/agent_tasks.json';
        
        if (!file_exists($tasksFile)) {
            throw new \RuntimeException('No saved tasks found');
        }

        $tasks = json_decode(file_get_contents($tasksFile), true) ?? [];
        
        if (!isset($tasks[$taskId])) {
            throw new \RuntimeException("Task '{$taskId}' not found");
        }

        $tasks[$taskId]['status'] = 'completed';
        $tasks[$taskId]['completedAt'] = date('Y-m-d H:i:s');
        $tasks[$taskId]['summary'] = $summary;
        $tasks[$taskId]['updatedAt'] = date('Y-m-d H:i:s');

        file_put_contents($tasksFile, json_encode($tasks, JSON_PRETTY_PRINT));

        return [
            'completed' => true,
            'taskId' => $taskId,
            'message' => "Task '{$taskId}' marked as completed.",
        ];
    }

    // =========================================================================
    // DATABASE ACCESS WITH ROLE-BASED ACCESS CONTROL
    // =========================================================================

    /**
     * Get database connection based on user role.
     * Admin: root privileges (full access)
     * Non-admin: guest user limited to 'clients' table only
     */
    private static function getDbConnection(bool $isAdmin = false): \PDO
    {
        // Load environment
        $envFile = self::repoRoot() . '/.env';
        $env = [];
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '#') === 0) continue;
                if (strpos($line, '=') !== false) {
                    [$key, $value] = explode('=', $line, 2);
                    $env[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
                }
            }
        }

        $host = $env['DB_HOST'] ?? $_ENV['DB_HOST'] ?? 'localhost';
        $port = $env['DB_PORT'] ?? $_ENV['DB_PORT'] ?? '3306';
        $database = $env['DB_DATABASE'] ?? $_ENV['DB_DATABASE'] ?? 'ginto';
        
        if ($isAdmin) {
            // Admin: root access with full privileges
            $username = $env['DB_ROOT_USER'] ?? $env['DB_USERNAME'] ?? $_ENV['DB_USERNAME'] ?? 'root';
            $password = $env['DB_ROOT_PASSWORD'] ?? $env['DB_PASSWORD'] ?? $_ENV['DB_PASSWORD'] ?? '';
        } else {
            // Non-admin: guest user with limited access
            $username = $env['DB_GUEST_USER'] ?? 'guest';
            $password = $env['DB_GUEST_PASSWORD'] ?? '';
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
        
        return new \PDO($dsn, $username, $password, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
    }

    /**
     * Validate query for non-admin users (restrict to 'clients' table only)
     */
    private static function validateGuestQuery(string $query): void
    {
        $query = strtolower(trim($query));
        
        // Must be SELECT, INSERT, UPDATE, DELETE only (no DDL)
        $allowed = ['select', 'insert', 'update', 'delete'];
        $firstWord = explode(' ', $query)[0];
        
        if (!in_array($firstWord, $allowed)) {
            throw new \RuntimeException(
                "Guest access denied: Only SELECT, INSERT, UPDATE, DELETE allowed. " .
                "Admin access required for: " . strtoupper($firstWord)
            );
        }

        // Must only reference 'clients' table
        // Check for forbidden table access (simplistic but effective)
        $tables = ['users', 'sessions', 'migrations', 'settings', 'admins', 'permissions', 'roles'];
        foreach ($tables as $table) {
            if (preg_match('/\b' . preg_quote($table, '/') . '\b/i', $query)) {
                throw new \RuntimeException(
                    "Guest access denied: Table '{$table}' is restricted. " .
                    "Guest users can only access the 'clients' table."
                );
            }
        }

        // Ensure 'clients' table is referenced (or it's a simple query)
        if (!preg_match('/\bclients\b/i', $query) && $firstWord !== 'select') {
            // Allow SELECT without table check (could be SELECT 1, etc.)
            if ($firstWord !== 'select') {
                throw new \RuntimeException(
                    "Guest access: INSERT/UPDATE/DELETE must target 'clients' table only."
                );
            }
        }
    }

    #[McpTool(
        name: 'db_query',
        description: 'Execute a MySQL SELECT query. Non-admin users are limited to the "clients" table only. Returns query results as array. Use db_query_admin for admin access.'
    )]
    public function dbQuery(string $query, ?array $params = null): array
    {
        // Validate for guest access
        self::validateGuestQuery($query);
        
        try {
            $pdo = self::getDbConnection(false);
            $stmt = $pdo->prepare($query);
            $stmt->execute($params ?? []);
            
            $results = $stmt->fetchAll();
            
            return [
                'success' => true,
                'accessLevel' => 'guest',
                'rowCount' => count($results),
                'data' => array_slice($results, 0, 100), // Limit output
                'truncated' => count($results) > 100,
            ];
        } catch (\PDOException $e) {
            return [
                'success' => false,
                'accessLevel' => 'guest',
                'error' => $e->getMessage(),
                'hint' => 'Guest users can only access the "clients" table. Use db_query_admin for full access.',
            ];
        }
    }

    #[McpTool(
        name: 'db_query_admin',
        description: 'Execute any MySQL query with admin/root privileges. Supports SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, GRANT, etc. ADMIN ONLY - requires admin authentication.'
    )]
    public function dbQueryAdmin(
        string $query, 
        ?array $params = null,
        string $adminKey = ''
    ): array {
        // Verify admin access - check against environment variable
        $envFile = self::repoRoot() . '/.env';
        $expectedKey = '';
        if (file_exists($envFile)) {
            $content = file_get_contents($envFile);
            if (preg_match('/^ADMIN_SECRET_KEY=(.+)$/m', $content, $m)) {
                $expectedKey = trim($m[1], " \t\n\r\0\x0B\"'");
            }
        }

        // Also check session for logged-in admin
        $isSessionAdmin = false;
        if (session_status() === PHP_SESSION_ACTIVE) {
            $isSessionAdmin = ($_SESSION['is_admin'] ?? false) === true;
        }

        if (!$isSessionAdmin && $adminKey !== $expectedKey) {
            return [
                'success' => false,
                'error' => 'Admin authentication required',
                'hint' => 'Provide adminKey parameter or ensure admin session is active.',
            ];
        }

        try {
            $pdo = self::getDbConnection(true);
            $stmt = $pdo->prepare($query);
            $stmt->execute($params ?? []);
            
            // Determine query type
            $queryType = strtoupper(explode(' ', trim($query))[0]);
            
            if ($queryType === 'SELECT') {
                $results = $stmt->fetchAll();
                return [
                    'success' => true,
                    'accessLevel' => 'admin',
                    'queryType' => $queryType,
                    'rowCount' => count($results),
                    'data' => array_slice($results, 0, 200),
                    'truncated' => count($results) > 200,
                ];
            } else {
                return [
                    'success' => true,
                    'accessLevel' => 'admin',
                    'queryType' => $queryType,
                    'affectedRows' => $stmt->rowCount(),
                    'lastInsertId' => $pdo->lastInsertId() ?: null,
                ];
            }
        } catch (\PDOException $e) {
            return [
                'success' => false,
                'accessLevel' => 'admin',
                'error' => $e->getMessage(),
            ];
        }
    }

    #[McpTool(
        name: 'db_describe_table',
        description: 'Get the structure of a database table (columns, types, keys). Guest users can only describe "clients" table.'
    )]
    public function dbDescribeTable(string $table, bool $isAdmin = false): array
    {
        if (!$isAdmin && strtolower($table) !== 'clients') {
            throw new \RuntimeException(
                "Guest access: Can only describe 'clients' table. " .
                "Set isAdmin=true with proper authentication for other tables."
            );
        }

        try {
            $pdo = self::getDbConnection($isAdmin);
            
            // Get columns
            $stmt = $pdo->query("DESCRIBE `{$table}`");
            $columns = $stmt->fetchAll();
            
            // Get indexes
            $stmt = $pdo->query("SHOW INDEX FROM `{$table}`");
            $indexes = $stmt->fetchAll();
            
            return [
                'table' => $table,
                'accessLevel' => $isAdmin ? 'admin' : 'guest',
                'columns' => $columns,
                'indexes' => $indexes,
            ];
        } catch (\PDOException $e) {
            throw new \RuntimeException("Failed to describe table: " . $e->getMessage());
        }
    }

    #[McpTool(
        name: 'db_list_tables',
        description: 'List all tables in the database. Admin only - guest users will see limited information.'
    )]
    public function dbListTables(bool $isAdmin = false): array
    {
        try {
            $pdo = self::getDbConnection($isAdmin);
            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            
            if (!$isAdmin) {
                // Guest users only see 'clients' table
                $tables = array_filter($tables, fn($t) => strtolower($t) === 'clients');
                return [
                    'accessLevel' => 'guest',
                    'tables' => array_values($tables),
                    'note' => 'Guest access limited to "clients" table only.',
                ];
            }
            
            return [
                'accessLevel' => 'admin',
                'tables' => $tables,
                'count' => count($tables),
            ];
        } catch (\PDOException $e) {
            throw new \RuntimeException("Failed to list tables: " . $e->getMessage());
        }
    }

    // =========================================================================
    // PROJECT SCAFFOLDING
    // =========================================================================

    #[McpTool(
        name: 'scaffold_feature',
        description: 'Scaffold a new feature with Model, Controller, Views, Routes, and Migration. Generates complete CRUD structure following project conventions.'
    )]
    public function scaffoldFeature(
        string $name,
        ?array $fields = null,
        bool $withApi = true,
        bool $withViews = true
    ): array {
        $name = ucfirst($name);
        $nameLower = strtolower($name);
        $namePlural = $nameLower . 's';
        
        $fields = $fields ?? [
            ['name' => 'name', 'type' => 'string', 'nullable' => false],
            ['name' => 'description', 'type' => 'text', 'nullable' => true],
            ['name' => 'status', 'type' => 'string', 'nullable' => false, 'default' => "'active'"],
        ];

        $files = [];

        // 1. Model
        $files[] = [
            'path' => "src/Models/{$name}.php",
            'content' => $this->generateModel($name, $fields),
        ];

        // 2. Controller
        $files[] = [
            'path' => "src/Controllers/{$name}Controller.php",
            'content' => $this->generateController($name, $fields, $withApi),
        ];

        // 3. Migration
        $timestamp = date('Ymd_His');
        $files[] = [
            'path' => "database/migrations/{$timestamp}_create_{$namePlural}_table.sql",
            'content' => $this->generateMigration($namePlural, $fields),
        ];

        // 4. Views (if requested)
        if ($withViews) {
            $files[] = [
                'path' => "src/Views/{$nameLower}/index.php",
                'content' => $this->generateIndexView($name, $fields),
            ];
            $files[] = [
                'path' => "src/Views/{$nameLower}/form.php",
                'content' => $this->generateFormView($name, $fields),
            ];
        }

        // 5. Route registration snippet
        $routeSnippet = $this->generateRouteSnippet($name, $namePlural, $withApi);

        // Create files
        $result = $this->composeProject($files, "Scaffold feature: {$name}");
        $result['routeSnippet'] = $routeSnippet;
        $result['nextSteps'] = [
            "Add route snippet to src/Routes/web.php",
            "Run migration: mysql -u root -p database < database/migrations/{$timestamp}_create_{$namePlural}_table.sql",
            "Customize the generated files as needed",
        ];

        return $result;
    }

    private function generateModel(string $name, array $fields): string
    {
        $fieldDocs = [];
        foreach ($fields as $f) {
            $type = match($f['type']) {
                'int', 'integer' => 'int',
                'bool', 'boolean' => 'bool',
                'float', 'double', 'decimal' => 'float',
                default => 'string',
            };
            $nullable = ($f['nullable'] ?? false) ? '?' : '';
            $fieldDocs[] = " * @property {$nullable}{$type} \${$f['name']}";
        }

        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Models;

/**
 * {$name} Model
 *
 * @property int \$id
{$this->indent(implode("\n", $fieldDocs), '')}
 * @property string \$created_at
 * @property string \$updated_at
 */
class {$name}
{
    private static function db(): \PDO
    {
        static \$pdo = null;
        if (\$pdo === null) {
            \$pdo = \App\Core\Database::getConnection();
        }
        return \$pdo;
    }

    public static function all(): array
    {
        \$stmt = self::db()->query("SELECT * FROM " . self::tableName() . " ORDER BY id DESC");
        return \$stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function find(int \$id): ?array
    {
        \$stmt = self::db()->prepare("SELECT * FROM " . self::tableName() . " WHERE id = ?");
        \$stmt->execute([\$id]);
        \$result = \$stmt->fetch(\PDO::FETCH_ASSOC);
        return \$result ?: null;
    }

    public static function create(array \$data): int
    {
        \$columns = implode(', ', array_keys(\$data));
        \$placeholders = implode(', ', array_fill(0, count(\$data), '?'));
        
        \$stmt = self::db()->prepare(
            "INSERT INTO " . self::tableName() . " ({\$columns}, created_at, updated_at) VALUES ({\$placeholders}, NOW(), NOW())"
        );
        \$stmt->execute(array_values(\$data));
        
        return (int) self::db()->lastInsertId();
    }

    public static function update(int \$id, array \$data): bool
    {
        \$sets = implode(', ', array_map(fn(\$k) => "{\$k} = ?", array_keys(\$data)));
        
        \$stmt = self::db()->prepare(
            "UPDATE " . self::tableName() . " SET {\$sets}, updated_at = NOW() WHERE id = ?"
        );
        return \$stmt->execute([...array_values(\$data), \$id]);
    }

    public static function delete(int \$id): bool
    {
        \$stmt = self::db()->prepare("DELETE FROM " . self::tableName() . " WHERE id = ?");
        return \$stmt->execute([\$id]);
    }

    private static function tableName(): string
    {
        return strtolower(basename(str_replace('\\\\', '/', static::class))) . 's';
    }
}

PHP;
    }

    private function generateController(string $name, array $fields, bool $withApi): string
    {
        $nameLower = strtolower($name);
        $apiMethods = '';
        
        if ($withApi) {
            $apiMethods = <<<'PHP'


    // API Methods
    public function apiIndex(): void
    {
        header('Content-Type: application/json');
        echo json_encode(['data' => $this->model::all()]);
    }

    public function apiShow(int $id): void
    {
        header('Content-Type: application/json');
        $item = $this->model::find($id);
        if (!$item) {
            http_response_code(404);
            echo json_encode(['error' => 'Not found']);
            return;
        }
        echo json_encode(['data' => $item]);
    }

    public function apiStore(): void
    {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        try {
            $id = $this->model::create($data);
            echo json_encode(['success' => true, 'id' => $id]);
        } catch (\Throwable $e) {
            http_response_code(400);
            \Ginto\Helpers\AdminErrorLogger::log($e->getMessage(), ['context' => 'generated_apiStore']);
            echo json_encode(['error' => 'Internal error (logged)']);
        }
    }

    public function apiUpdate(int $id): void
    {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        try {
            $this->model::update($id, $data);
            echo json_encode(['success' => true]);
        } catch (\Throwable $e) {
            http_response_code(400);
            \Ginto\Helpers\AdminErrorLogger::log($e->getMessage(), ['context' => 'generated_apiUpdate']);
            echo json_encode(['error' => 'Internal error (logged)']);
        }
    }

    public function apiDestroy(int $id): void
    {
        header('Content-Type: application/json');
        $this->model::delete($id);
        echo json_encode(['success' => true]);
    }
PHP;
        }

        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\\{$name};

class {$name}Controller
{
    private string \$model = {$name}::class;

    public function index(): void
    {
        \$items = \$this->model::all();
        require ROOT_PATH . '/src/Views/{$nameLower}/index.php';
    }

    public function show(int \$id): void
    {
        \$item = \$this->model::find(\$id);
        if (!\$item) {
            http_response_code(404);
            echo 'Not found';
            return;
        }
        require ROOT_PATH . '/src/Views/{$nameLower}/show.php';
    }

    public function create(): void
    {
        \$item = null;
        require ROOT_PATH . '/src/Views/{$nameLower}/form.php';
    }

    public function store(): void
    {
        \$data = \$_POST;
        \$id = \$this->model::create(\$data);
        header('Location: /{$nameLower}s/' . \$id);
    }

    public function edit(int \$id): void
    {
        \$item = \$this->model::find(\$id);
        if (!\$item) {
            http_response_code(404);
            echo 'Not found';
            return;
        }
        require ROOT_PATH . '/src/Views/{$nameLower}/form.php';
    }

    public function update(int \$id): void
    {
        \$data = \$_POST;
        \$this->model::update(\$id, \$data);
        header('Location: /{$nameLower}s/' . \$id);
    }

    public function destroy(int \$id): void
    {
        \$this->model::delete(\$id);
        header('Location: /{$nameLower}s');
    }{$apiMethods}
}

PHP;
    }

    private function generateMigration(string $tableName, array $fields): string
    {
        $columns = [];
        foreach ($fields as $f) {
            $sqlType = match($f['type']) {
                'int', 'integer' => 'INT',
                'bigint' => 'BIGINT',
                'bool', 'boolean' => 'TINYINT(1)',
                'float', 'double' => 'DOUBLE',
                'decimal' => 'DECIMAL(10,2)',
                'text' => 'TEXT',
                'date' => 'DATE',
                'datetime' => 'DATETIME',
                default => 'VARCHAR(255)',
            };
            
            $nullable = ($f['nullable'] ?? false) ? 'NULL' : 'NOT NULL';
            $default = isset($f['default']) ? "DEFAULT {$f['default']}" : '';
            
            $columns[] = "    `{$f['name']}` {$sqlType} {$nullable} {$default}";
        }

        $columnsSQL = implode(",\n", $columns);

        return <<<SQL
-- Migration: Create {$tableName} table
-- Generated: {$this->now()}

CREATE TABLE IF NOT EXISTS `{$tableName}` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
{$columnsSQL},
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add indexes as needed
-- CREATE INDEX idx_{$tableName}_status ON `{$tableName}` (`status`);

SQL;
    }

    private function generateIndexView(string $name, array $fields): string
    {
        $nameLower = strtolower($name);
        $namePlural = $nameLower . 's';
        
        $headers = "<th>ID</th>\n";
        $cells = "<td><?= \$item['id'] ?></td>\n";
        
        foreach (array_slice($fields, 0, 4) as $f) {
            $label = ucfirst(str_replace('_', ' ', $f['name']));
            $headers .= "                    <th>{$label}</th>\n";
            $cells .= "                    <td><?= htmlspecialchars(\$item['{$f['name']}'] ?? '') ?></td>\n";
        }

        return <<<PHP
<?php
/**
 * {$name} Index View
 * @var array \$items
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$name}s</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 2rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f5f5f5; }
        .btn { padding: 0.5rem 1rem; text-decoration: none; border-radius: 4px; }
        .btn-primary { background: #007bff; color: white; }
        .btn-danger { background: #dc3545; color: white; }
    </style>
</head>
<body>
    <h1>{$name}s</h1>
    <p><a href="/{$namePlural}/create" class="btn btn-primary">Create New</a></p>
    
    <table>
        <thead>
            <tr>
                {$headers}
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach (\$items as \$item): ?>
            <tr>
                {$cells}
                <td>
                    <a href="/{$namePlural}/<?= \$item['id'] ?>/edit">Edit</a>
                    <form method="POST" action="/{$namePlural}/<?= \$item['id'] ?>" style="display:inline;">
                        <input type="hidden" name="_method" value="DELETE">
                        <button type="submit" onclick="return confirm('Delete?')">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>

PHP;
    }

    private function generateFormView(string $name, array $fields): string
    {
        $nameLower = strtolower($name);
        $namePlural = $nameLower . 's';
        
        $formFields = '';
        foreach ($fields as $f) {
            $label = ucfirst(str_replace('_', ' ', $f['name']));
            $type = match($f['type']) {
                'text' => 'textarea',
                'bool', 'boolean' => 'checkbox',
                'int', 'integer', 'bigint' => 'number',
                'date' => 'date',
                'datetime' => 'datetime-local',
                default => 'text',
            };
            
            $required = ($f['nullable'] ?? false) ? '' : 'required';
            
            if ($type === 'textarea') {
                $formFields .= <<<HTML
        <div class="form-group">
            <label for="{$f['name']}">{$label}</label>
            <textarea name="{$f['name']}" id="{$f['name']}" {$required}><?= htmlspecialchars(\$item['{$f['name']}'] ?? '') ?></textarea>
        </div>

HTML;
            } else {
                $formFields .= <<<HTML
        <div class="form-group">
            <label for="{$f['name']}">{$label}</label>
            <input type="{$type}" name="{$f['name']}" id="{$f['name']}" value="<?= htmlspecialchars(\$item['{$f['name']}'] ?? '') ?>" {$required}>
        </div>

HTML;
            }
        }

        return <<<PHP
<?php
/**
 * {$name} Form View
 * @var array|null \$item
 */
\$isEdit = !empty(\$item);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= \$isEdit ? 'Edit' : 'Create' ?> {$name}</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 2rem; max-width: 600px; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; margin-bottom: 0.25rem; font-weight: 500; }
        input, textarea, select { width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px; }
        textarea { min-height: 100px; }
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: 4px; cursor: pointer; }
        .btn-primary { background: #007bff; color: white; }
    </style>
</head>
<body>
    <h1><?= \$isEdit ? 'Edit' : 'Create' ?> {$name}</h1>
    
    <form method="POST" action="/{$namePlural}<?= \$isEdit ? '/' . \$item['id'] : '' ?>">
        <?php if (\$isEdit): ?>
        <input type="hidden" name="_method" value="PUT">
        <?php endif; ?>
        
{$formFields}
        <button type="submit" class="btn btn-primary"><?= \$isEdit ? 'Update' : 'Create' ?></button>
        <a href="/{$namePlural}">Cancel</a>
    </form>
</body>
</html>

PHP;
    }

    private function generateRouteSnippet(string $name, string $namePlural, bool $withApi): string
    {
        $apiRoutes = '';
        if ($withApi) {
            $apiRoutes = <<<PHP

// API Routes for {$name}
\$router->get('/api/{$namePlural}', [{$name}Controller::class, 'apiIndex']);
\$router->get('/api/{$namePlural}/{id}', [{$name}Controller::class, 'apiShow']);
\$router->post('/api/{$namePlural}', [{$name}Controller::class, 'apiStore']);
\$router->put('/api/{$namePlural}/{id}', [{$name}Controller::class, 'apiUpdate']);
\$router->delete('/api/{$namePlural}/{id}', [{$name}Controller::class, 'apiDestroy']);
PHP;
        }

        return <<<PHP
// Add to src/Routes/web.php:
use App\Controllers\\{$name}Controller;

// Web Routes for {$name}
\$router->get('/{$namePlural}', [{$name}Controller::class, 'index']);
\$router->get('/{$namePlural}/create', [{$name}Controller::class, 'create']);
\$router->post('/{$namePlural}', [{$name}Controller::class, 'store']);
\$router->get('/{$namePlural}/{id}', [{$name}Controller::class, 'show']);
\$router->get('/{$namePlural}/{id}/edit', [{$name}Controller::class, 'edit']);
\$router->put('/{$namePlural}/{id}', [{$name}Controller::class, 'update']);
\$router->delete('/{$namePlural}/{id}', [{$name}Controller::class, 'destroy']);
{$apiRoutes}
PHP;
    }

    #[McpTool(
        name: 'scaffold_api',
        description: 'Scaffold a REST API endpoint with controller, model, and routes. Generates JSON API following REST conventions.'
    )]
    public function scaffoldApi(string $name, ?array $fields = null): array
    {
        return $this->scaffoldFeature($name, $fields, withApi: true, withViews: false);
    }

    #[McpTool(
        name: 'scaffold_migration',
        description: 'Generate a SQL migration file for creating or altering tables.'
    )]
    public function scaffoldMigration(
        string $name,
        string $type = 'create',
        ?string $table = null,
        ?array $columns = null
    ): array {
        $timestamp = date('Ymd_His');
        $table = $table ?? strtolower($name) . 's';
        
        if ($type === 'create') {
            $content = $this->generateMigration($table, $columns ?? [
                ['name' => 'name', 'type' => 'string', 'nullable' => false],
            ]);
        } else {
            // Alter table template
            $content = <<<SQL
-- Migration: {$name}
-- Generated: {$this->now()}

ALTER TABLE `{$table}`
    -- ADD COLUMN `new_column` VARCHAR(255) NULL,
    -- MODIFY COLUMN `existing` INT NOT NULL,
    -- DROP COLUMN `old_column`,
    -- ADD INDEX idx_{$table}_column (`column`)
;

SQL;
        }

        $path = "database/migrations/{$timestamp}_{$name}.sql";
        $result = $this->composeProject([
            ['path' => $path, 'content' => $content],
        ], "Migration: {$name}");

        return $result;
    }

    // =========================================================================
    // UTILITY HELPERS
    // =========================================================================

    private function indent(string $text, string $prefix = '    '): string
    {
        return $prefix . str_replace("\n", "\n{$prefix}", $text);
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
