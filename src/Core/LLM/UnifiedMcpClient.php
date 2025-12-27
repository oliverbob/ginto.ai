<?php

declare(strict_types=1);

namespace App\Core\LLM;

use App\Core\McpUnifier;
use App\Core\McpInvoker;
use Ginto\Core\Database as GintoDatabase;

/**
 * Unified MCP Client that works with any LLM provider.
 * 
 * This replaces the provider-specific logic in StandardMcpHost with a
 * provider-agnostic implementation. It handles:
 * 
 * - Tool discovery from local handlers and MCP servers
 * - Conversation management
 * - Tool execution with automatic fallbacks
 * - Provider-agnostic chat with tool calling loop
 * 
 * Tool calling is standardized using:
 * - ToolDefinition: Normalized tool schemas
 * - ToolCall: Normalized tool invocation requests
 * - ToolResult: Normalized tool execution results
 */
class UnifiedMcpClient
{
    private LLMProviderInterface $provider;
    private array $conversationHistory = [];
    /** @var ToolDefinition[] */
    private array $cachedTools = [];
    private string $projectPath;
    private int $maxIterations;
    private bool $systemPromptInjected = false;

    /**
     * Ensure PHP session is started so we can persist sandbox_id safely.
     */
    private function ensureSessionStarted(): void
    {
        try {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                @session_start();
            }
        } catch (\Throwable $_) {
            // If session cannot be started, silently continue; writes will be skipped.
        }
    }

    public function __construct(
        ?LLMProviderInterface $provider = null,
        ?string $projectPath = null,
        array $initialHistory = [],
        int $maxIterations = 10
    ) {
        $this->provider = $provider ?? LLMProviderFactory::fromEnv();
        // Determine project path according to session role and sandbox state.
        if ($projectPath !== null) {
            $this->projectPath = $projectPath;
        } else {
            $projectRoot = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2);
            $user = $_SESSION['user'] ?? null;
            $isAdmin = ($user && isset($user['role_id']) && $user['role_id'] == 1);

            if ($isAdmin) {
                // Admins operate on the repository root
                $this->projectPath = $projectRoot;
            } else {
                // Non-admins: Sandbox mode - files are stored inside LXD containers
                // The projectPath for sandbox users is a virtual marker; actual file
                // operations are routed through the sandbox proxy to LXD containers.
                $this->ensureSessionStarted();
                $db = null;
                try { $db = GintoDatabase::getInstance(); } catch (\Throwable $_) { $db = null; }
                
                // Get sandbox ID from session or database (with validation to clear stale data)
                $sid = $_SESSION['sandbox_id'] ?? null;
                if (!empty($sid)) {
                    // Validate the sandbox still exists
                    if (!\Ginto\Helpers\ClientSandboxHelper::validateSandboxExists($sid, $db)) {
                        unset($_SESSION['sandbox_id']);
                        $sid = null;
                    }
                }
                if (empty($sid)) {
                    $sid = \Ginto\Helpers\ClientSandboxHelper::getSandboxIdIfExists($db, $_SESSION ?? null, true);
                    if (!empty($sid)) {
                        $_SESSION['sandbox_id'] = $sid;
                    }
                }
                
                // For sandbox users, projectPath is a virtual marker (not a host filesystem path)
                // File operations go through the LXD container proxy
                if (!empty($sid)) {
                    $this->projectPath = 'sandbox:' . $sid;
                } else {
                    // No sandbox assigned - use project root in read-only mode
                    $this->projectPath = $projectRoot;
                }
            }
        }
        $this->conversationHistory = $initialHistory;
        $this->maxIterations = $maxIterations;
        
        // Inject repository context system prompt if history is empty or lacks system message
        $this->injectRepositoryContext();
    }

    /**
     * Generate and inject repository structure context into conversation.
     */
    private function injectRepositoryContext(): void
    {
        if ($this->systemPromptInjected) {
            return;
        }
        
        // Check if there's already a system message
        foreach ($this->conversationHistory as $msg) {
            if (($msg['role'] ?? '') === 'system') {
                $this->systemPromptInjected = true;
                return;
            }
        }
        
        // Determine user role and project context
        $user = $_SESSION['user'] ?? null;
        $isAdmin = ($user && isset($user['role_id']) && $user['role_id'] == 1);
        
        try {
            $realRoot = realpath(defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2));
            $realProj = realpath($this->projectPath) ?: $this->projectPath;
            $isRepoRoot = $realRoot && $realProj && $realProj === $realRoot;
            
            if ($isAdmin && $isRepoRoot) {
                // Full repository context for admin at repo root
                $context = $this->buildRepositoryContext();
            } else {
                // Basic system prompt for all other users (non-admin or sandbox)
                $context = $this->buildBasicSystemPrompt();
            }
            
            array_unshift($this->conversationHistory, [
                'role' => 'system',
                'content' => $context
            ]);
        } catch (\Throwable $_) {
            // Fallback: inject minimal system prompt even on failure
            array_unshift($this->conversationHistory, [
                'role' => 'system',
                'content' => $this->buildBasicSystemPrompt()
            ]);
        }
        $this->systemPromptInjected = true;
    }
    
    /**
     * Build a basic system prompt for non-admin users or sandboxed sessions.
     */
    private function buildBasicSystemPrompt(): string
    {
        $lines = [];
        
        // Check if user has an active sandbox
        $sandboxId = $_SESSION['sandbox_id'] ?? null;
        $hasSandbox = !empty($sandboxId);
        
        $lines[] = "# You are Sai";
        $lines[] = "An expert full-stack web developer created by Bob Reyes.";
        $lines[] = "";
        $lines[] = "## Your Capabilities";
        $lines[] = "You are a helpful AI assistant that can:";
        $lines[] = "- Answer questions about programming, web development, and technology";
        $lines[] = "- Write code in various programming languages";
        $lines[] = "- Help with debugging and problem-solving";
        $lines[] = "- Explain concepts clearly and concisely";
        $lines[] = "";
        
        if ($hasSandbox) {
            // Sandbox-specific tools
            $lines[] = "## Sandbox Environment";
            $lines[] = "The user has an active sandbox (isolated container environment).";
            $lines[] = "Sandbox ID: `{$sandboxId}`";
            $lines[] = "All file operations are scoped to `/home/` inside the sandbox container.";
            $lines[] = "";
            $lines[] = "## Agentic Workflow - CRITICAL";
            $lines[] = "You are an agentic AI that executes multi-step plans:";
            $lines[] = "1. **Plan**: State your plan in 2-4 bullet points";
            $lines[] = "2. **Execute ONE tool**: Call exactly ONE tool per response";
            $lines[] = "3. **Wait for result**: After the tool runs, you'll receive the result";
            $lines[] = "4. **Continue or Summarize**: If more steps remain, execute the next tool. If done, summarize.";
            $lines[] = "";
            $lines[] = "IMPORTANT: Only call ONE tool per response. The system will send you the result and you can then call the next tool.";
            $lines[] = "";
            $lines[] = "## Available Sandbox Tools";
            $lines[] = "You have direct access to the user's sandbox. Use these tools to manage files:";
            $lines[] = "";
            $lines[] = "### File Operations (paths are relative to /home/)";
            $lines[] = "- `sandbox_list_files` - List files and directories. Parameters: `path` (optional, relative to /home/)";
            $lines[] = "- `sandbox_read_file` - Read a file's content. Parameters: `path` (required)";
            $lines[] = "- `sandbox_write_file` - Create or update a file. Parameters: `path` (required), `content` (required)";
            $lines[] = "- `sandbox_create_file` - Create empty file or folder. Parameters: `path` (required), `type` ('file' or 'folder')";
            $lines[] = "- `sandbox_delete_file` - Delete a file or folder. Parameters: `path` (required)";
            $lines[] = "- `sandbox_rename_file` - Rename/move a file. Parameters: `old_path` (required), `new_path` (required)";
            $lines[] = "- `sandbox_copy_file` - Copy a file. Parameters: `source_path` (required), `dest_path` (required)";
            $lines[] = "- `sandbox_file_exists` - Check if a path exists. Parameters: `path` (required)";
            $lines[] = "";
            $lines[] = "### Command Execution";
            $lines[] = "- `sandbox_exec` - Run shell commands in the sandbox. Parameters: `command` (required), `cwd` (optional, default '/home')";
            $lines[] = "";
            $lines[] = "### Project Scaffolding";
            $lines[] = "- `sandbox_create_project` - Create a new project from a template. Parameters: `project_type` (required: html, php, react, vue, node, python, tailwind), `project_name` (optional), `description` (optional), `run_install` (optional, default true)";
            $lines[] = "- `sandbox_list_project_types` - List all available project templates with descriptions";
            $lines[] = "";
            $lines[] = "### Multi-file Operations";
            $lines[] = "- `sandbox_compose_project` - Create multiple files at once. Parameters: `files` (array of {path, content}), `description` (optional)";
            $lines[] = "";
            $lines[] = "### Status";
            $lines[] = "- `sandbox_status` - Get sandbox container status";
            $lines[] = "";
            $lines[] = "## Path Examples";
            $lines[] = "- To create `/home/index.php`, use path: `index.php`";
            $lines[] = "- To create `/home/project/src/app.js`, use path: `project/src/app.js`";
            $lines[] = "";
        } else {
            // No sandbox - generic tools
            $lines[] = "## Available Tools";
            $lines[] = "You have access to tools for:";
            $lines[] = "- **File Operations**: Read, write, and list files in your workspace";
            $lines[] = "- **Web Search**: Search the web for current information";
            $lines[] = "- **Code Execution**: Run code snippets when needed";
            $lines[] = "";
            $lines[] = "Note: The user does not have a sandbox environment set up yet. They can click 'My Files' to create one.";
            $lines[] = "";
        }
        
        $lines[] = "## How to Respond";
        $lines[] = "- Be helpful, clear, and concise";
        $lines[] = "- When asked to write code, provide complete, production-quality code";
        $lines[] = "- Use modern best practices and clean code principles";
        $lines[] = "- For web development, prefer TailwindCSS for styling";
        $lines[] = "";
        $lines[] = "## Code Quality Standards";
        $lines[] = "- Responsive design (mobile-first)";
        $lines[] = "- Modern ES6+ JavaScript";
        $lines[] = "- Semantic HTML5";
        $lines[] = "- Accessibility (ARIA labels, keyboard navigation)";
        $lines[] = "";
        $lines[] = "Current date: " . date('Y-m-d H:i:s');
        
        return implode("\n", $lines);
    }

    /**
     * Build a comprehensive repository context string.
     */
    private function buildRepositoryContext(): string
    {
        $root = $this->projectPath;
        $lines = [];
        
        // Simplified system prompt for better model compliance
        $lines[] = "# You are Sai";
        $lines[] = "An expert full-stack web developer created by Bob Reyes.";
        $lines[] = "";
        $lines[] = "## Available Tools";
        $lines[] = "You have access to the following tools. Use EXACT parameter names as shown:";
        $lines[] = "";
        $lines[] = "### File Operations";
        $lines[] = "- `repo/list_files`: List files in the project. Parameters: `path` (optional, default '.')";
        $lines[] = "- `repo/get_file`: Read a file's content. Parameters: `path` (required - the file path)";
        $lines[] = "- `repo/create_or_update_file`: Create or update a file. Parameters: `file_path` (required), `content` (required)";
        $lines[] = "- `write_file`: Write content to a file. Parameters: `path` (required), `content` (required)";
        $lines[] = "";
        $lines[] = "### Web & Search";
        $lines[] = "- Web browsing: Search the web and visit websites for current information";
        $lines[] = "- When asked about current events, weather, news, or real-time info, use web search";
        $lines[] = "";
        $lines[] = "## How to Respond";
        $lines[] = "When the user asks you to build something:";
        $lines[] = "1. Use the write_file tool to create/update the file but only when asked. Unless specified, assume that the single file you are writing to is the opened file on the editor";
        $lines[] = "2. Write complete, production-quality code";
        $lines[] = "3. Use TailwindCSS via: <script src=\"https://cdn.tailwindcss.com\"></script>";
        $lines[] = "";
        $lines[] = "## File Rules";
        $lines[] = "- .html files: HTML/CSS/JS only, NO PHP";
        $lines[] = "- .php files: PHP allowed";
        $lines[] = "- Only write to the currently open file unless asked otherwise";
        $lines[] = "";
        $lines[] = "## Code Quality Standards";
        $lines[] = "- Responsive design (mobile-first)";
        $lines[] = "- Modern ES6+ JavaScript";
        $lines[] = "- Semantic HTML5";
        $lines[] = "- Smooth animations and micro-interactions";
        $lines[] = "- Dark mode support when appropriate";
        $lines[] = "- Accessibility (ARIA labels, keyboard nav)";
        $lines[] = "";
        
        // Project context (admin vs sandbox scope)
        $lines[] = "# Project Information";
        $lines[] = "";
        // Check if this is a sandbox context (using sandbox: prefix or session)
        $safeSandboxNote = null;
        $isSandboxContext = str_starts_with((string)$root, 'sandbox:');
        if ($isSandboxContext) {
            // Extract sandbox ID from sandbox:xxx format
            $sid = substr((string)$root, 8); // Remove 'sandbox:' prefix
            if ($sid) {
                $safeSandboxNote = "Your sandbox ID: `{$sid}` (isolated container environment)";
                $lines[] = "**Scope:** Sandbox environment (your files are stored in an isolated container)";
                $lines[] = "";
                $lines[] = $safeSandboxNote;
                $lines[] = "";
            }
        }
        if ($safeSandboxNote === null) {
            $lines[] = "**Scope:** Admin-only repository context (absolute paths and VCS internals are redacted).";
            $lines[] = "";
        }
        $lines[] = "Current date: " . date('Y-m-d H:i:s');
        $lines[] = "";
        
        // Key directories explained
        $lines[] = "## Key Directories";
        $lines[] = "";
        $lines[] = "| Directory | Purpose |";
        $lines[] = "|-----------|---------|";
        $lines[] = "| `public/` | Web-accessible files |";
        $lines[] = "| `src/` | PHP source code |";
        $lines[] = "| `src/Handlers/` | MCP tool handlers |";
        $lines[] = "| `src/Views/` | Templates |";
        $lines[] = "| `database/` | SQL migrations |";
        $lines[] = "| `tools/` | MCP server packages |";
        $lines[] = "";
        
        // Simplified directory listing
        $lines[] = "## Top-Level Structure";
        $lines[] = "";
        $topLevel = @scandir($root);
        if ($topLevel) {
            $skip = ['vendor', 'node_modules', '.git', '.idea', 'storage', '.', '..'];
            foreach ($topLevel as $item) {
                if (in_array($item, $skip) || $item[0] === '.') continue;
                $isDir = is_dir($root . '/' . $item);
                $lines[] = "- " . ($isDir ? "**{$item}/**" : "`{$item}`");
            }
        }
        $lines[] = "";
        
        // Development workflow instructions
        $lines[] = "# Development Workflow";
        $lines[] = "";
        $lines[] = "You are an expert AI programming assistant. Follow these practices:";
        $lines[] = "";
        $lines[] = "## Before Making Changes";
        $lines[] = "1. **Understand the task** - Break down what needs to be done";
        $lines[] = "2. **Gather context** - Use `read_file` to examine existing code";
        $lines[] = "3. **Search if needed** - Use `search_files` to find related code";
        $lines[] = "4. **Check structure** - Use `list_directory` or `get_project_structure`";
        $lines[] = "";
        $lines[] = "## Making Edits";
        $lines[] = "1. **For existing files**: Use `replace_in_file` with exact text to find/replace";
        $lines[] = "   - Include 3+ lines of context before and after the change";
        $lines[] = "   - Never rewrite entire files - make surgical edits";
        $lines[] = "2. **For new files**: Use `create_file` with complete content";
        $lines[] = "3. **Always verify paths** - Use `get_file_info` to check if file exists";
        $lines[] = "";
        $lines[] = "## Tool Usage Examples";
        $lines[] = "";
        $lines[] = "### Reading a file:";
        $lines[] = "```";
        $lines[] = "read_file(path: 'src/Controllers/UserController.php', startLine: 1, endLine: 50)";
        $lines[] = "```";
        $lines[] = "";
        $lines[] = "### Making an edit:";
        $lines[] = "```";
        $lines[] = "replace_in_file(";
        $lines[] = "  path: 'src/example.php',";
        $lines[] = "  oldText: '    public function old() {\\n        return \"old\";\\n    }',";
        $lines[] = "  newText: '    public function new() {\\n        return \"new\";\\n    }'";
        $lines[] = ")";
        $lines[] = "```";
        $lines[] = "";
        $lines[] = "### Running commands:";
        $lines[] = "```";
        $lines[] = "run_command(command: 'php -l src/example.php')  # Check syntax";
        $lines[] = "run_command(command: 'composer test')           # Run tests";
        $lines[] = "```";
        $lines[] = "";
        $lines[] = "## Code Analysis (Before Major Changes)";
        $lines[] = "";
        $lines[] = "Use these tools to deeply understand code before making changes:";
        $lines[] = "";
        $lines[] = "- **`analyze_file`** - Get classes, methods, properties of a file";
        $lines[] = "- **`find_usages`** - Find all references to a symbol before renaming/removing";
        $lines[] = "- **`find_definition`** - Locate where a class/function is defined";
        $lines[] = "- **`get_dependencies`** - See what a file imports/requires";
        $lines[] = "- **`find_related_files`** - Find tests, interfaces, related files";
        $lines[] = "- **`get_project_structure`** - High-level project overview";
        $lines[] = "";
        $lines[] = "### Example: Understanding a class before refactoring";
        $lines[] = "```";
        $lines[] = "1. analyze_file('src/Controllers/UserController.php')   # What's in it?";
        $lines[] = "2. find_usages('UserController')                         # Who uses it?";
        $lines[] = "3. get_dependencies('src/Controllers/UserController.php') # What does it need?";
        $lines[] = "4. find_related_files('src/Controllers/UserController.php') # Tests? Interfaces?";
        $lines[] = "```";
        $lines[] = "";
        $lines[] = "## Multi-File Operations & Scaffolding";
        $lines[] = "";
        $lines[] = "For creating multiple files at once (features, modules, projects):";
        $lines[] = "";
        $lines[] = "- **`compose_project`** - Create multiple files/directories in one operation";
        $lines[] = "- **`batch_edit`** - Make multiple edits across files efficiently";
        $lines[] = "- **`scaffold_feature`** - Generate complete CRUD (Model, Controller, Views, Migration, Routes)";
        $lines[] = "- **`scaffold_api`** - Generate REST API endpoints";
        $lines[] = "- **`scaffold_migration`** - Create database migration files";
        $lines[] = "";
        $lines[] = "### Example: Create a new feature";
        $lines[] = "```";
        $lines[] = "scaffold_feature(name: 'Product', fields: [";
        $lines[] = "  {name: 'title', type: 'string'},";
        $lines[] = "  {name: 'price', type: 'decimal'},";
        $lines[] = "  {name: 'description', type: 'text', nullable: true}";
        $lines[] = "])";
        $lines[] = "```";
        $lines[] = "";
        $lines[] = "## Task Persistence (Continue Later)";
        $lines[] = "";
        $lines[] = "Save and resume complex tasks across sessions:";
        $lines[] = "";
        $lines[] = "- **`save_task`** - Save current progress with context and next steps";
        $lines[] = "- **`load_task`** - Resume a saved task";
        $lines[] = "- **`list_tasks`** - See all saved tasks";
        $lines[] = "- **`complete_task`** - Mark task as done";
        $lines[] = "";
        $lines[] = "### Example: Save progress before stopping";
        $lines[] = "```";
        $lines[] = "save_task(";
        $lines[] = "  taskId: 'refactor-auth',";
        $lines[] = "  description: 'Refactoring authentication system',";
        $lines[] = "  context: {currentFile: 'AuthController.php', step: 3},";
        $lines[] = "  filesModified: ['AuthController.php', 'User.php'],";
        $lines[] = "  nextSteps: ['Add password reset', 'Update tests']";
        $lines[] = ")";
        $lines[] = "```";
        $lines[] = "";
        $lines[] = "## Database Access";
        $lines[] = "";
        $lines[] = "**Role-Based Access Control:**";
        $lines[] = "- **Guest users**: Can only access `clients` table with SELECT/INSERT/UPDATE/DELETE";
        $lines[] = "- **Admin users**: Full root access to all tables and DDL operations";
        $lines[] = "";
        $lines[] = "### Guest Access (default):";
        $lines[] = "```";
        $lines[] = "db_query(query: 'SELECT * FROM clients WHERE status = ?', params: ['active'])";
        $lines[] = "db_describe_table(table: 'clients')";
        $lines[] = "```";
        $lines[] = "";
        $lines[] = "### Admin Access (requires authentication):";
        $lines[] = "```";
        $lines[] = "db_query_admin(query: 'CREATE TABLE new_table (...)', adminKey: 'secret')";
        $lines[] = "db_list_tables(isAdmin: true)";
        $lines[] = "```";
        $lines[] = "";
        $lines[] = "## Key Principles";
        $lines[] = "- **Read before editing** - Always examine the file first";
        $lines[] = "- **Analyze before refactoring** - Use analysis tools for major changes";
        $lines[] = "- **Small, focused changes** - Don't rewrite what doesn't need changing";
        $lines[] = "- **Preserve style** - Match existing code formatting and conventions";
        $lines[] = "- **Verify changes** - Check syntax after edits when possible";
        $lines[] = "- **Understand impact** - Use `find_usages` before renaming/removing code";
        $lines[] = "- **Save progress** - Use `save_task` for complex multi-step work";
        $lines[] = "- **Scaffold, don't repeat** - Use scaffolding tools for common patterns";
        $lines[] = "";
        $lines[] = "## CRITICAL: Verify Your Work";
        $lines[] = "";
        $lines[] = "**After every file modification, you MUST verify the change was applied:**";
        $lines[] = "";
        $lines[] = "1. After `write_file` or `replace_in_file`, use `read_file` to confirm the content is correct";
        $lines[] = "2. Or use `verify_file_content` to check for expected content";
        $lines[] = "3. If verification fails, use `think` to analyze what went wrong";
        $lines[] = "4. Then retry with a corrected approach";
        $lines[] = "";
        $lines[] = "### Example: Write and Verify";
        $lines[] = "```";
        $lines[] = "1. write_file(path: 'public/test.php', content: '<?php echo \"Hello\"; ?>')";
        $lines[] = "2. read_file(path: 'public/test.php')  # Verify content is correct";
        $lines[] = "3. If wrong: think('The file was empty because...') then retry";
        $lines[] = "```";
        $lines[] = "";
        $lines[] = "### Self-Correction Workflow";
        $lines[] = "If something goes wrong:";
        $lines[] = "1. Use `think` to reason about the failure";
        $lines[] = "2. Use `read_file` to see actual state";
        $lines[] = "3. Try a different approach (e.g., different path, escape content differently)";
        $lines[] = "4. Verify again until successful";
        $lines[] = "";
        
        // Image element guidance for models: prefer picsum.photos with responsive
        // srcsets, lazy loading, meaningful alt text and optional picture/webp.
        $lines[] = "## Image Element Guidelines";
        $lines[] = "When generating HTML that includes images, follow these rules:";
        $lines[] = "- Use `https://picsum.photos` as the image source for placeholders";
        $lines[] = "- Prefer predictable seeds so images are consistent across renders: `https://picsum.photos/seed/{seed}/{width}/{height}`";
        $lines[] = "- Include a `srcset` with at least `400w`, `800w`, and `1200w` variants";
        $lines[] = "- Add `loading=\"lazy\"` and provide a meaningful `alt` attribute";
        $lines[] = "- Optionally use a `<picture>` element with WebP sources when producing optimized output";
        $lines[] = "- Use grayscale (`?grayscale`) or blur (`?blur=10`) query params when stylistically appropriate";
        $lines[] = "";
        $lines[] = "### Example (img with srcset)";
        $lines[] = "```html";
        $lines[] = "<img";
        $lines[] = "  src=\"https://picsum.photos/seed/responsive1/800/600\"";
        $lines[] = "  srcset=\"https://picsum.photos/seed/responsive1/400/300 400w, https://picsum.photos/seed/responsive1/800/600 800w, https://picsum.photos/seed/responsive1/1200/900 1200w\"";
        $lines[] = "  sizes=\"(max-width: 768px) 100vw, 50vw\"";
        $lines[] = "  alt=\"Responsive placeholder image\"";
        $lines[] = "  loading=\"lazy\"";
        $lines[] = "  style=\"width:100%;height:auto;\"";
        $lines[] = "/>";
        $lines[] = "```";
        $lines[] = "";
        $lines[] = "### Example (picture with WebP)";
        $lines[] = "```html";
        $lines[] = "<picture>";
        $lines[] = "  <source type=\"image/webp\" srcset=\"https://picsum.photos/seed/responsive1/800/600.webp 800w, https://picsum.photos/seed/responsive1/1200/900.webp 1200w\" sizes=\"(max-width: 768px) 100vw, 50vw\">";
        $lines[] = "  <img src=\"https://picsum.photos/seed/responsive1/800/600\" srcset=\"https://picsum.photos/seed/responsive1/400/300 400w, https://picsum.photos/seed/responsive1/800/600 800w, https://picsum.photos/seed/responsive1/1200/900 1200w\" sizes=\"(max-width: 768px) 100vw, 50vw\" alt=\"Responsive placeholder image\" loading=\"lazy\" style=\"width:100%;height:auto;\">";
        $lines[] = "</picture>";
        $lines[] = "```";
        $lines[] = "";
        return implode("\n", $lines);
    }

    /**
     * Get a simple directory tree representation using plain text indentation.
     * Avoids box-drawing characters for better HTML rendering.
     */
    private function getDirectoryTree(string $dir, int $maxDepth, int $currentDepth = 0): string
    {
        if ($currentDepth >= $maxDepth || !is_dir($dir)) {
            return '';
        }
        
        $output = '';
        $items = @scandir($dir);
        if (!$items) return '';
        
        // Filter and sort
        $items = array_filter($items, fn($i) => $i !== '.' && $i !== '..' && $i[0] !== '.');
        $items = array_values($items);
        sort($items);
        
        // Skip vendor, node_modules, .git, storage
        $skip = ['vendor', 'node_modules', '.git', '.idea', 'storage'];
        
        $indent = str_repeat('  ', $currentDepth);
        
        foreach ($items as $item) {
            if (in_array($item, $skip)) continue;
            
            $path = $dir . '/' . $item;
            
            if (is_dir($path)) {
                $output .= $indent . "- " . $item . "/\n";
                $output .= $this->getDirectoryTree($path, $maxDepth, $currentDepth + 1);
            } else {
                $output .= $indent . "- " . $item . "\n";
            }
        }
        
        return $output;
    }

    /**
     * Get the current LLM provider.
     */
    public function getProvider(): LLMProviderInterface
    {
        return $this->provider;
    }

    /**
     * Set a new LLM provider.
     */
    public function setProvider(LLMProviderInterface $provider): void
    {
        $this->provider = $provider;
    }

    /**
     * Sanitize tool results to remove sensitive information before sending to LLM or client.
     * This prevents exposure of internal paths, git objects, and system details.
     */
    private function sanitizeToolResult(array $result): array
    {
        // List of patterns to filter out
        $sensitivePatterns = [
            '/\.git\//',           // Git internals
            '/\.env/',             // Environment files
            '/vendor\//',          // Vendor directory
            '/node_modules\//',    // Node modules
            '/\/home\/[^\/]+\//',  // Absolute home paths - replace with relative
        ];
        
        // If result contains a list of files/paths, filter them
        if (isset($result['files']) && is_array($result['files'])) {
            $result['files'] = array_values(array_filter($result['files'], function($file) use ($sensitivePatterns) {
                foreach ($sensitivePatterns as $pattern) {
                    if (preg_match($pattern, $file)) {
                        return false;
                    }
                }
                return true;
            }));
            // Limit file list size to prevent overwhelming output
            if (count($result['files']) > 50) {
                $result['files'] = array_slice($result['files'], 0, 50);
                $result['truncated'] = true;
                $result['message'] = 'File list truncated to 50 items.';
            }
        }
        
        // Sanitize path fields - convert absolute to relative
        $pathFields = ['path', 'file_path', 'filePath'];
        foreach ($pathFields as $field) {
            if (isset($result[$field]) && is_string($result[$field])) {
                // Remove absolute path prefixes, keep relative path
                $result[$field] = preg_replace('/^\/home\/[^\/]+\/[^\/]+\//', '', $result[$field]);
                // Handle any legacy clients/ paths - show as sandbox relative (for backwards compatibility)
                $result[$field] = preg_replace('/^.*\/clients\/([^\/]+)\//', 'sandbox/', $result[$field]);
            }
        }
        
        // If the result is just a raw array of paths (like git objects), summarize it
        if (is_array($result) && !isset($result['success']) && !isset($result['message'])) {
            $firstKey = array_key_first($result);
            if (is_int($firstKey) && is_string($result[$firstKey] ?? null)) {
                // This looks like a raw array of strings
                $filtered = array_filter($result, function($item) use ($sensitivePatterns) {
                    if (!is_string($item)) return true;
                    foreach ($sensitivePatterns as $pattern) {
                        if (preg_match($pattern, $item)) {
                            return false;
                        }
                    }
                    return true;
                });
                if (count($filtered) < count($result)) {
                    return ['message' => 'Operation completed.', 'items_processed' => count($result)];
                }
            }
        }
        
        return $result;
    }

    /**
     * Get all available tools from handlers and MCP servers.
     *
     * @param bool $refresh Force refresh of cached tools
     * @return ToolDefinition[] Normalized tool definitions
     */
    public function getAllTools(bool $refresh = false): array
    {
        if (!empty($this->cachedTools) && !$refresh) {
            return $this->cachedTools;
        }

        $tools = [];

        // Get tools from McpUnifier (handlers + MCP servers + tools folders)
        try {
            $unifier = new McpUnifier();
            $discovered = $unifier->getAllTools($refresh);
            
            foreach ($discovered['tools'] ?? [] as $t) {
                $tools[] = new ToolDefinition(
                    name: $t['name'] ?? $t['id'] ?? uniqid('tool_'),
                    description: $t['description'] ?? '',
                    parameters: $t['input_schema'] ?? $t['schema'] ?? $t['parameters'] ?? [],
                    source: $t['source'] ?? 'unknown',
                    handler: $t['handler'] ?? null
                );
            }
        } catch (\Throwable $e) {
            error_log('[UnifiedMcpClient] Tool discovery error: ' . $e->getMessage());
        }

        $this->cachedTools = $tools;
        return $tools;
    }

    /**
     * Get tools as array format (for backward compatibility).
     */
    public function getToolsAsArray(bool $refresh = false): array
    {
        return array_map(fn($t) => $t->toArray(), $this->getAllTools($refresh));
    }

    /**
     * Find a tool by name.
     */
    public function findTool(string $name): ?ToolDefinition
    {
        foreach ($this->getAllTools() as $tool) {
            if ($tool->getName() === $name) {
                return $tool;
            }
        }
        return null;
    }

    /**
     * Execute a tool call.
     *
     * @param ToolCall $toolCall The tool call to execute
     * @return ToolResult The execution result
     */
    public function executeToolCall(ToolCall $toolCall): ToolResult
    {
        $toolName = $toolCall->getName();
        $arguments = $toolCall->getArguments();
        $toolCallId = $toolCall->getId();

        // Restriction logic for file operations
        $user = $_SESSION['user'] ?? null;
        $isAdmin = ($user && isset($user['role_id']) && $user['role_id'] == 1);
        $sandboxId = $_SESSION['sandbox_id'] ?? null;
        // If non-admin and no sandbox id in session, attempt to resolve via ClientSandboxHelper
        if (!$isAdmin && empty($sandboxId)) {
            try {
                $db = null;
                try { $db = GintoDatabase::getInstance(); } catch (\Throwable $_) { $db = null; }
                // Use getOrCreateSandboxId which returns just the sandbox ID string
                $sandboxId = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxId($db ?? null, $_SESSION ?? null);
                if (!empty($sandboxId)) {
                    // Persist to session for subsequent calls in this request
                    $this->ensureSessionStarted();
                    $_SESSION['sandbox_id'] = $sandboxId;
                }
            } catch (\Throwable $_) {
                // ignore resolution failures; later checks will block writes without sandbox id
            }
        }
        // Note: File operations for sandboxed users are handled via LXD containers,
        // not through host filesystem paths. The sandbox proxy routes requests to containers.

        // Try to find the tool to get handler info
        $tool = $this->findTool($toolName);

        $fileWriteTools = ['create_file', 'write_file', 'replace_in_file'];
        $isFileWrite = in_array($toolName, $fileWriteTools);
        $writePath = $arguments['path'] ?? $arguments['file_path'] ?? null;
        $writeContent = $arguments['content'] ?? null;
        $writeResult = null;
        $verification = null;

        // If handler is specified, try direct invocation
        if ($tool && $tool->getHandler()) {
            $handler = $tool->getHandler();
            if (is_string($handler) && strpos($handler, '::') !== false) {
                try {
                    [$class, $method] = explode('::', $handler, 2);
                    if (class_exists($class) && method_exists($class, $method)) {
                        $instance = new $class();

                        // Make a copy of arguments and normalize common aliases so reflection can match parameter names
                        $handlerArgs = $arguments;
                        if (is_array($handlerArgs)) {
                            // path -> file_path
                            if (array_key_exists('path', $handlerArgs) && !array_key_exists('file_path', $handlerArgs)) {
                                $handlerArgs['file_path'] = $handlerArgs['path'];
                            }
                            if (array_key_exists('filepath', $handlerArgs) && !array_key_exists('file_path', $handlerArgs)) {
                                $handlerArgs['file_path'] = $handlerArgs['filepath'];
                            }
                            if (array_key_exists('contents', $handlerArgs) && !array_key_exists('content', $handlerArgs)) {
                                $handlerArgs['content'] = $handlerArgs['contents'];
                            }
                        }

                        // Validate required parameters for the handler before invoking
                        try {
                            $ref = new \ReflectionMethod($instance, $method);
                            foreach ($ref->getParameters() as $p) {
                                $pname = $p->getName();
                                if (!array_key_exists($pname, $handlerArgs) && !$p->isDefaultValueAvailable()) {
                                    return ToolResult::error($toolCallId, 'Missing required argument for handler: ' . $pname);
                                }
                            }
                        } catch (\ReflectionException $_) {
                            // If reflection fails, proceed to attempt invocation and let it surface
                        }

                        $writeResult = $this->invokeWithReflection($instance, $method, $handlerArgs);
                        // If this was a file write, verify the result
                        if ($isFileWrite && $writePath) {
                            try {
                                if (is_string($writeContent) && strlen($writeContent) > 0) {
                                    // Use verify_file_content tool
                                    $verifyArgs = [
                                        'path' => $writePath,
                                        'expectedSubstring' => mb_substr($writeContent, 0, 128),
                                        'checkLine' => 0
                                    ];
                                    $verification = McpInvoker::invoke('verify_file_content', $verifyArgs);
                                    // If verification failed, return error
                                    if (empty($verification['verified'])) {
                                        return ToolResult::error($toolCallId, 'File verification failed: expected content not found or file missing.', ['write_result' => $writeResult, 'verification' => $verification]);
                                    }
                                } else {
                                    // Use read_file tool to check if file exists and is not empty
                                    $verifyArgs = [
                                        'path' => $writePath,
                                        'startLine' => 1,
                                        'endLine' => 10
                                    ];
                                    $verification = McpInvoker::invoke('read_file', $verifyArgs);
                                    if (empty($verification['content'])) {
                                        return ToolResult::error($toolCallId, 'File verification failed: file missing or empty.', ['write_result' => $writeResult, 'verification' => $verification]);
                                    }
                                }
                            } catch (\Throwable $e) {
                                return ToolResult::error($toolCallId, 'Verification failed: ' . $e->getMessage(), ['write_result' => $writeResult]);
                            }
                        }
                        $resultPayload = [
                            'write_result' => $writeResult,
                        ];
                        if ($verification !== null) {
                            $resultPayload['verification'] = $verification;
                        }
                        // Trace successful handler execution
                        try {
                            $this->trace('execute_tool_call', [
                                'tool' => $toolName,
                                'handler' => $handler,
                                'arguments' => $handlerArgs ?? $arguments,
                                'result' => $resultPayload,
                                'sandbox_id' => $sandboxId ?? null,
                            ]);
                        } catch (\Throwable $_) {}
                        return ToolResult::success($toolCallId, $resultPayload);
                    }
                } catch (\Throwable $e) {
                    // Log the internal handler error for admin review, do not leak internals to users
                    $this->logProviderError($e->getMessage(), ['type' => 'handler_invocation', 'handler' => $handler, 'tool' => $toolName]);
                    return ToolResult::error($toolCallId, 'Tool execution failed (internal error)');
                }
            }
        }

        // Fallback to McpInvoker
        try {
            $writeResult = McpInvoker::invoke($toolName, $arguments);
            // If this was a file write, verify the result
            if ($isFileWrite && $writePath) {
                try {
                    if (is_string($writeContent) && strlen($writeContent) > 0) {
                        $verifyArgs = [
                            'path' => $writePath,
                            'expectedSubstring' => mb_substr($writeContent, 0, 128),
                            'checkLine' => 0
                        ];
                        $verification = McpInvoker::invoke('verify_file_content', $verifyArgs);
                        if (empty($verification['verified'])) {
                            return ToolResult::error($toolCallId, 'File verification failed: expected content not found or file missing.', ['write_result' => $writeResult, 'verification' => $verification]);
                        }
                    } else {
                        $verifyArgs = [
                            'path' => $writePath,
                            'startLine' => 1,
                            'endLine' => 10
                        ];
                        $verification = McpInvoker::invoke('read_file', $verifyArgs);
                        if (empty($verification['content'])) {
                            return ToolResult::error($toolCallId, 'File verification failed: file missing or empty.', ['write_result' => $writeResult, 'verification' => $verification]);
                        }
                    }
                } catch (\Throwable $e) {
                    return ToolResult::error($toolCallId, 'Verification failed: ' . $e->getMessage(), ['write_result' => $writeResult]);
                }
            }
            $resultPayload = [
                'write_result' => $writeResult,
            ];
            if ($verification !== null) {
                $resultPayload['verification'] = $verification;
            }
            // Trace successful McpInvoker execution
            try {
                $this->trace('execute_tool_call', [
                    'tool' => $toolName,
                    'arguments' => $arguments,
                    'result' => $resultPayload,
                    'sandbox_id' => $sandboxId ?? null,
                ]);
            } catch (\Throwable $_) {}
            return ToolResult::success($toolCallId, $resultPayload);
        } catch (\Throwable $e) {
            // Log details and return a safe error to the caller
            $this->logProviderError($e->getMessage(), ['type' => 'mcp_invoke', 'tool' => $toolName, 'arguments' => $arguments]);
            return ToolResult::error($toolCallId, 'Tool execution failed (internal error)');
        }
    }

    /**
     * Execute a tool by name (backward compatible method).
     *
     * @param string $toolName Tool name
     * @param array $arguments Tool arguments
     * @return array {success: bool, result: mixed, error?: string}
     */
    public function executeTool(string $toolName, array $arguments): array
    {
        $toolCall = new ToolCall(uniqid('tc_'), $toolName, $arguments);
        $result = $this->executeToolCall($toolCall);
        
        if ($result->isError()) {
            return ['success' => false, 'error' => $result->getResultString()];
        }
        return ['success' => true, 'result' => $result->getResult(), 'source' => 'unified'];
    }

    /**
     * Invoke a method with reflection-based argument matching.
     */
    private function invokeWithReflection(object $instance, string $method, array $arguments): mixed
    {
        $ref = new \ReflectionMethod($instance, $method);
        $params = $ref->getParameters();
        $args = [];

        foreach ($params as $p) {
            $pname = $p->getName();
            if (array_key_exists($pname, $arguments)) {
                $args[] = $arguments[$pname];
            } elseif ($p->isDefaultValueAvailable()) {
                $args[] = $p->getDefaultValue();
            } else {
                $args[] = null;
            }
        }

        return $ref->invokeArgs($instance, $args);
    }

    /**
     * Log provider/internal errors safely into activity_logs for admin review.
     * This prevents leaking provider or low-level exceptions to end-users.
     */
    private function logProviderError(string $message, array $meta = []): void
    {
        // Ensure the message is reasonably short for DB storage
        $payload = json_encode(['message' => $message, 'meta' => $meta], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $payload = substr($payload, 0, 4096);

        // Try to insert into activity_logs
        try {
            $db = null;
            try { $db = \Ginto\Core\Database::getInstance(); } catch (\Throwable $_) { $db = null; }

            $userId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? null;
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;

            if ($db) {
                $db->insert('activity_logs', [
                    'user_id' => $userId,
                    'action' => 'llm_provider_error',
                    'model_type' => 'llm',
                    'model_id' => null,
                    'description' => $payload,
                    'ip_address' => $ip,
                    'user_agent' => $ua,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
        } catch (\Throwable $e) {
            // If DB logging fails, still write to the main error log for admin debugging
            error_log('[UnifiedMcpClient] Failed to write activity_log: ' . $e->getMessage());
        }

        // Always write to standard error log as well (does not expose details to users)
        error_log('[UnifiedMcpClient] Provider/Internal error logged: ' . $payload);

        // Also write a compact trace file to help debugging tool-call extraction issues
        try {
            $tracePath = '/tmp/mcp_tool_trace.log';
            $entry = [
                'ts' => date('c'),
                'type' => 'provider_error',
                'payload' => json_decode($payload, true) ?: ['raw' => $payload]
            ];
            @file_put_contents($tracePath, json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
        } catch (\Throwable $_) {}
    }

    /**
     * Lightweight tracing helper for provider content and tool-call parsing.
     */
    private function trace(string $label, $data): void
    {
        try {
            $tracePath = '/tmp/mcp_tool_trace.log';
            $entry = [
                'ts' => date('c'),
                'label' => $label,
                'data' => $data,
            ];
            @file_put_contents($tracePath, json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
        } catch (\Throwable $_) {}
    }

    /**
     * Send a chat message and get a response.
     * 
     * This handles the full tool-calling loop:
     * 1. Send message to LLM with available tools
     * 2. If LLM requests tool calls, execute them
     * 3. Add tool results to conversation
     * 4. Repeat until LLM returns final response or max iterations
     *
     * @param string $userMessage User's message
     * @param array $options Chat options (temperature, max_tokens, etc.)
     * @return string Final response content
     */
    public function chat(string $userMessage, array $options = []): string
    {
        // Check if this exact user message already exists in history (avoid duplicates)
        $alreadyExists = false;
        foreach ($this->conversationHistory as $msg) {
            if (($msg['role'] ?? '') === 'user' && ($msg['content'] ?? '') === $userMessage) {
                $alreadyExists = true;
                break;
            }
        }
        
        if (!$alreadyExists) {
            $this->conversationHistory[] = [
                'role' => 'user',
                'content' => $userMessage,
            ];
        }

        // Convert ToolDefinition objects to arrays for the provider
        $toolDefs = $this->getAllTools();
        $tools = array_map(fn($t) => $t->toArray(), $toolDefs);
        $iteration = 0;

        while ($iteration < $this->maxIterations) {
            $iteration++;

            // Get response from LLM
            try {
                $response = $this->provider->chat(
                    $this->conversationHistory,
                    $tools,
                    $options
                );
            } catch (\Throwable $e) {
                // Provider or HTTP client threw an exception. Log full details for admins and return a safe message to the user.
                $this->logProviderError((string)$e->getMessage(), [
                    'user_message' => $userMessage,
                    'provider' => $this->provider->getName()
                ]);
                return 'An internal error occurred while contacting the language model. The incident has been logged.';
            }

            if ($response->isError()) {
                // Provider returned an error payload. Log details for admins and return a generic user message.
                $this->logProviderError((string)$response->getContent(), [
                    'user_message' => $userMessage,
                    'provider' => $this->provider->getName()
                ]);
                return 'An internal error occurred while communicating with the language model. The incident has been logged.';
            }

            // Trace raw provider response for debugging
            try {
                $this->trace('provider_response', [
                    'provider' => $this->provider->getName(),
                    'content' => mb_substr((string)$response->getContent(), 0, 4096),
                    'iteration' => $iteration
                ]);
            } catch (\Throwable $_) {}

            // Add assistant response to history
            // Trace raw provider response for streaming flow
            try {
                $this->trace('provider_response_stream', [
                    'provider' => $this->provider->getName(),
                    'content' => mb_substr((string)$response->getContent(), 0, 4096),
                    'iteration' => $iteration
                ]);
            } catch (\Throwable $_) {}

            $this->conversationHistory[] = $response->toAssistantMessage();

            // Check for tool calls
            if ($response->hasToolCalls()) {
                foreach ($response->getToolCalls() as $rawToolCall) {
                    // Trace the raw tool call payload received from provider
                    try {
                        $this->trace('provider_raw_toolcall', [
                            'provider' => $this->provider->getName(),
                            'raw' => $rawToolCall,
                            'iteration' => $iteration
                        ]);
                    } catch (\Throwable $_) {}

                    // Convert to ToolCall object for standardization
                    $toolCall = ToolCall::fromProvider(
                        $rawToolCall['id'],
                        $rawToolCall['name'],
                        $rawToolCall['arguments']
                    );

                    // Trace the normalized ToolCall (to observe null/missing args)
                    try {
                        $this->trace('normalized_toolcall', [
                            'id' => $toolCall->getId(),
                            'name' => $toolCall->getName(),
                            'arguments' => $toolCall->getArguments(),
                            'iteration' => $iteration
                        ]);
                    } catch (\Throwable $_) {}

                    error_log("[UnifiedMcpClient] Executing tool: {$toolCall->getName()}");
                    
                    // Execute and get ToolResult
                    $toolResult = $this->executeToolCall($toolCall);
                    
                    // Sanitize before logging and adding to history
                    $sanitizedResult = $this->sanitizeToolResult($toolResult->getResult());
                    error_log("[UnifiedMcpClient] Tool result: " . json_encode($sanitizedResult));

                    // Create a sanitized tool result for the LLM history
                    $sanitizedToolResult = ToolResult::success(
                        $toolResult->getToolCallId(),
                        $sanitizedResult
                    );
                    
                    // Add sanitized tool result to history in provider-specific format
                    $this->conversationHistory[] = $sanitizedToolResult->toAssistantMessage($this->provider);
                }
                continue;
            }

            // No tool calls - return the content
            return $response->getContent();
        }

        return 'Request too complex; exceeded maximum tool iterations.';
    }

    /**
     * Send a streaming chat message with tool execution support.
     *
     * The callback receives two arguments:
     * - $chunk: Content chunk from the LLM (string or null)
     * - $toolCall: Tool call info when a tool is executed (array with 'name', 'arguments', 'result' or null)
     *
     * @param string $userMessage User's message
     * @param callable $onChunk Callback: function(string $chunk, ?array $toolCall): void
     * @param array $options Chat options
     * @return string Final response content
     */
    public function chatStream(string $userMessage, callable $onChunk, array $options = []): string
    {
        // Check if this exact user message already exists in history (avoid duplicates)
        $alreadyExists = false;
        foreach ($this->conversationHistory as $msg) {
            if (($msg['role'] ?? '') === 'user' && ($msg['content'] ?? '') === $userMessage) {
                $alreadyExists = true;
                break;
            }
        }
        
        if (!$alreadyExists) {
            $this->conversationHistory[] = [
                'role' => 'user',
                'content' => $userMessage,
            ];
        }

        // Convert ToolDefinition objects to arrays for the provider
        $toolDefs = $this->getAllTools();
        $tools = array_map(fn($t) => $t->toArray(), $toolDefs);
        $iteration = 0;

        while ($iteration < $this->maxIterations) {
            $iteration++;

            try {
                $response = $this->provider->chatStream(
                    $this->conversationHistory,
                    $tools,
                    $options,
                    function($chunk, $providerEvent = null) use ($onChunk) {
                        // Forward content chunks to the callback
                        if ($chunk !== null && $chunk !== '') {
                            $onChunk($chunk, null);
                        }
                        // Forward provider events (reasoning, activity, etc.) to the route callback
                        if ($providerEvent !== null) {
                            $onChunk(null, $providerEvent);
                        }
                    }
                );
            } catch (\Throwable $e) {
                // Log provider-level exception for admins; send generic error to client stream
                $this->logProviderError((string)$e->getMessage(), [
                    'user_message' => $userMessage,
                    'provider' => $this->provider->getName(),
                    'streaming' => true
                ]);

                $error = 'An internal error occurred while streaming from the language model. The incident has been logged.';
                $onChunk($error, null);
                return $error;
            }

            if ($response->isError()) {
                // Provider returned error contents (e.g., HTTP 4xx/5xx) — log details and show generic message
                $this->logProviderError((string)$response->getContent(), [
                    'user_message' => $userMessage,
                    'provider' => $this->provider->getName(),
                    'streaming' => true
                ]);

                $error = 'An internal error occurred while communicating with the language model. The incident has been logged.';
                $onChunk($error, null);
                return $error;
            }

            $this->conversationHistory[] = $response->toAssistantMessage();

            if ($response->hasToolCalls()) {
                foreach ($response->getToolCalls() as $rawToolCall) {
                    // Trace the raw tool call payload from streaming provider
                    try {
                        $this->trace('provider_raw_toolcall_stream', [
                            'provider' => $this->provider->getName(),
                            'raw' => $rawToolCall,
                            'iteration' => $iteration
                        ]);
                    } catch (\Throwable $_) {}

                    // Convert to ToolCall object for standardization
                    $toolCall = ToolCall::fromProvider(
                        $rawToolCall['id'],
                        $rawToolCall['name'],
                        $rawToolCall['arguments']
                    );

                    // Trace normalized toolcall in streaming flow
                    try {
                        $this->trace('normalized_toolcall_stream', [
                            'id' => $toolCall->getId(),
                            'name' => $toolCall->getName(),
                            'arguments' => $toolCall->getArguments(),
                            'iteration' => $iteration
                        ]);
                    } catch (\Throwable $_) {}

                    // Notify callback that a tool is being executed
                    $onChunk(null, [
                        'name' => $toolCall->getName(),
                        'arguments' => $this->sanitizeToolResult($toolCall->getArguments()),
                        'status' => 'executing'
                    ]);
                    
                    // Execute and get ToolResult
                    $toolResult = $this->executeToolCall($toolCall);
                    
                    // Sanitize the result before sending to client
                    $sanitizedResult = $this->sanitizeToolResult($toolResult->getResult());
                    
                    // Notify callback of tool result (sanitized)
                    $onChunk(null, [
                        'name' => $toolCall->getName(),
                        'arguments' => $this->sanitizeToolResult($toolCall->getArguments()),
                        'result' => $sanitizedResult,
                        'isError' => $toolResult->isError(),
                        'status' => 'completed'
                    ]);
                    
                    // Create a sanitized tool result for the LLM history
                    // This prevents the LLM from echoing sensitive paths back to users
                    $sanitizedToolResult = ToolResult::success(
                        $toolResult->getToolCallId(),
                        $sanitizedResult
                    );
                    
                    // Add sanitized tool result to history in provider-specific format
                    $this->conversationHistory[] = $sanitizedToolResult->toAssistantMessage($this->provider);
                }
                continue;
            }

            return $response->getContent();
        }

        return 'Request too complex; exceeded maximum tool iterations.';
    }

    /**
     * Get conversation history.
     */
    public function getHistory(): array
    {
        return $this->conversationHistory;
    }

    /**
     * Set conversation history.
     */
    public function setHistory(array $history): void
    {
        $this->conversationHistory = $history;
        $this->systemPromptInjected = false;
        $this->injectRepositoryContext();
    }

    /**
     * Clear conversation history.
     */
    public function reset(): void
    {
        $this->conversationHistory = [];
        $this->systemPromptInjected = false;
        $this->injectRepositoryContext();
    }

    /**
     * Add a system message to the conversation.
     */
    public function addSystemMessage(string $content): void
    {
        // Prepend system message at the beginning
        array_unshift($this->conversationHistory, [
            'role' => 'system',
            'content' => $content,
        ]);
    }

    /**
     * Get provider info for debugging/display.
     */
    public function getProviderInfo(): array
    {
        return [
            'name' => $this->provider->getName(),
            'style' => $this->provider->getStyle(),
            'configured' => $this->provider->isConfigured(),
            'default_model' => $this->provider->getDefaultModel(),
            'available_models' => $this->provider->getModels(),
        ];
    }
}
