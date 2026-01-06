# MCP Tools Reference

This document describes all available MCP tools in the Ginto agent system. Tools are organized by handler class and capability.

## Overview

The agent has **44 tools** across multiple categories:

| Category | Handler | Tools | Description |
|----------|---------|-------|-------------|
| File Operations | `DevTools` | 10 | Read, write, edit, delete files |
| Code Analysis | `DevTools` | 8 | Analyze code structure, find usages |
| Multi-File | `AgentTools` | 2 | Batch operations, project composition |
| Task Persistence | `AgentTools` | 4 | Save/resume tasks across sessions |
| Database | `AgentTools` | 4 | MySQL access with RBAC |
| Scaffolding | `AgentTools` | 3 | Generate code from templates |
| Repository | `CursorContext` | 5 | Git and repo operations |
| External | `groq-mcp` | 3 | External MCP server tools |

---

## DevTools (`src/Handlers/DevTools.php`)

VS Code-like development tools for file manipulation and code understanding.

### File Operations

#### `read_file`
Read contents of a file with optional line range.

```json
{
  "path": "src/Controllers/UserController.php",
  "startLine": 1,
  "endLine": 50
}
```

**Returns**: File content with line numbers for precise editing.

---

#### `create_file`
Create a new file with content. Creates parent directories automatically.

```json
{
  "path": "src/Models/Product.php",
  "content": "<?php\n\nnamespace App\\Models;\n\nclass Product { }"
}
```

**Note**: Use only for NEW files. Use `replace_in_file` for existing files.

---

#### `replace_in_file`
Replace specific text in a file. The preferred way to make edits.

```json
{
  "path": "src/example.php",
  "oldText": "    public function old() {\n        return \"old\";\n    }",
  "newText": "    public function new() {\n        return \"new\";\n    }"
}
```

**Important**: Include 3+ lines of context before/after to uniquely identify the location.

---

#### `delete_file`
Delete a file from the repository.

```json
{
  "path": "src/deprecated/OldClass.php"
}
```

---

#### `move_file`
Move or rename a file.

```json
{
  "source": "src/Old/File.php",
  "destination": "src/New/File.php"
}
```

---

#### `get_file_info`
Get file metadata (size, modification time, permissions, type).

```json
{
  "path": "composer.json"
}
```

**Returns**: `{ exists, size, modified, permissions, type }`

---

### Search & Navigation

#### `list_directory`
List contents of a directory.

```json
{
  "path": "src/Controllers"
}
```

**Returns**: Files and subdirectories with type indicators (`/` for directories).

---

#### `search_files`
Search for text/pattern across files. Returns matching files with line numbers.

```json
{
  "pattern": "class.*Controller",
  "path": "src/",
  "regex": true
}
```

---

#### `find_definition`
Find where a function, class, or method is defined.

```json
{
  "symbol": "UserController"
}
```

**Returns**: File path and line number of definition.

---

#### `find_usages`
Find all usages/references of a symbol across the codebase.

```json
{
  "symbol": "getUserById",
  "path": "src/"
}
```

**Returns**: All files referencing the symbol with context.

---

#### `find_related_files`
Find files related to a given file (tests, configs, interfaces).

```json
{
  "path": "src/Controllers/UserController.php"
}
```

**Returns**: Related test files, interfaces, parent classes, etc.

---

### Code Analysis

#### `analyze_file`
Deep analysis of a source file structure.

```json
{
  "path": "src/Controllers/AuthController.php"
}
```

**Returns**:
```json
{
  "classes": ["AuthController"],
  "methods": ["login", "logout", "register"],
  "properties": ["$userService", "$sessionManager"],
  "imports": ["App\\Services\\UserService", "App\\Core\\Session"],
  "extends": "BaseController",
  "implements": ["AuthInterface"]
}
```

---

#### `get_file_symbols`
Quick list of all symbols defined in a file.

```json
{
  "path": "src/Helpers/StringHelper.php"
}
```

---

#### `get_dependencies`
Get all imports/requires of a file.

```json
{
  "path": "src/Controllers/UserController.php"
}
```

**Returns**: List of `use` statements and `require`/`include` calls.

---

#### `get_context`
Get expanded context around a specific line.

```json
{
  "path": "src/example.php",
  "line": 45,
  "contextLines": 10
}
```

---

#### `explain_code`
Get structured explanation of a code section.

```json
{
  "path": "src/Core/Router.php",
  "startLine": 100,
  "endLine": 150
}
```

---

#### `compare_files`
Compare two files and show differences.

```json
{
  "file1": "src/old/Handler.php",
  "file2": "src/new/Handler.php"
}
```

---

### Project Understanding

#### `get_project_structure`
High-level overview of the project.

```json
{}
```

**Returns**:
```json
{
  "directories": ["src/", "public/", "database/"],
  "languages": ["PHP", "JavaScript"],
  "frameworks": ["Slim"],
  "entryPoints": ["public/index.php"],
  "fileCount": 150
}
```

---

#### `run_command`
Execute a shell command in the repository directory.

```json
{
  "command": "php -l src/example.php"
}
```

**Returns**: `{ stdout, stderr, exitCode }`

**Security**: Commands run with timeout. Be cautious with user input.

---

## AgentTools (`src/Handlers/AgentTools.php`)

Advanced tools for multi-file operations, task persistence, database access, and scaffolding.

### Multi-File Operations

#### `compose_project`
Create multiple files and directories in one operation.

```json
{
  "files": [
    { "path": "src/Models/Order.php", "content": "<?php..." },
    { "path": "src/Controllers/OrderController.php", "content": "<?php..." },
    { "path": "database/migrations/create_orders.sql", "content": "CREATE TABLE..." }
  ],
  "description": "Add Order feature"
}
```

**Returns**: Summary of created files, directories, and any failures.

---

#### `batch_edit`
Perform multiple file edits in one operation.

```json
{
  "edits": [
    { "path": "src/A.php", "oldText": "old1", "newText": "new1" },
    { "path": "src/B.php", "oldText": "old2", "newText": "new2" }
  ]
}
```

---

### Task Persistence

Save and resume complex tasks across chat sessions.

#### `save_task`
Save current task state to resume later.

```json
{
  "taskId": "refactor-auth",
  "description": "Refactoring authentication system to use JWT",
  "status": "in_progress",
  "context": {
    "currentStep": 3,
    "totalSteps": 5,
    "currentFile": "src/Auth/JwtHandler.php"
  },
  "filesModified": [
    "src/Auth/JwtHandler.php",
    "src/Middleware/AuthMiddleware.php"
  ],
  "nextSteps": [
    "Update UserController to use new auth",
    "Add token refresh endpoint",
    "Write tests"
  ]
}
```

---

#### `load_task`
Load a previously saved task.

```json
{
  "taskId": "refactor-auth"
}
```

**Returns**: Full task context including files modified and next steps.

---

#### `list_tasks`
List all saved tasks.

```json
{
  "status": "in_progress"
}
```

**Returns**: Array of tasks with id, description, status, last update.

---

#### `complete_task`
Mark a task as completed.

```json
{
  "taskId": "refactor-auth",
  "summary": "Successfully migrated to JWT authentication"
}
```

---

### Database Access (RBAC)

Role-based access control for MySQL operations.

#### Access Levels

| Level | User | Permissions |
|-------|------|-------------|
| **Guest** | `guest` | SELECT, INSERT, UPDATE, DELETE on `clients` table only |
| **Admin** | `root` | Full access: CREATE, ALTER, DROP, GRANT, all tables |

#### `db_query`
Execute a query with **guest** permissions.

```json
{
  "query": "SELECT * FROM clients WHERE status = ?",
  "params": ["active"]
}
```

**Restrictions**:
- Only `clients` table accessible
- Only DML operations (SELECT, INSERT, UPDATE, DELETE)
- No DDL (CREATE, ALTER, DROP)

---

#### `db_query_admin`
Execute any query with **admin/root** permissions.

```json
{
  "query": "CREATE TABLE products (id INT PRIMARY KEY, name VARCHAR(255))",
  "adminKey": "your-secret-key"
}
```

**Authentication**: Requires either:
- `adminKey` matching `ADMIN_SECRET_KEY` in `.env`
- Active admin session (`$_SESSION['is_admin'] === true`)

---

#### `db_describe_table`
Get table structure (columns, types, keys).

```json
{
  "table": "clients",
  "isAdmin": false
}
```

**Guest**: Can only describe `clients` table.
**Admin**: Can describe any table.

---

#### `db_list_tables`
List all database tables.

```json
{
  "isAdmin": true
}
```

**Guest**: Only sees `clients` table.
**Admin**: Sees all tables.

---

### Scaffolding

Generate code from templates following project conventions.

#### `scaffold_feature`
Generate complete CRUD: Model, Controller, Views, Migration, Routes.

```json
{
  "name": "Product",
  "fields": [
    { "name": "title", "type": "string", "nullable": false },
    { "name": "price", "type": "decimal", "nullable": false },
    { "name": "description", "type": "text", "nullable": true },
    { "name": "status", "type": "string", "default": "'active'" }
  ],
  "withApi": true,
  "withViews": true
}
```

**Generates**:
- `src/Models/Product.php`
- `src/Controllers/ProductController.php`
- `src/Views/product/index.php`
- `src/Views/product/form.php`
- `database/migrations/{timestamp}_create_products_table.sql`
- Route snippet to add to `web.php`

---

#### `scaffold_api`
Generate REST API endpoint (no views).

```json
{
  "name": "Order",
  "fields": [
    { "name": "customer_id", "type": "int" },
    { "name": "total", "type": "decimal" },
    { "name": "status", "type": "string", "default": "'pending'" }
  ]
}
```

---

#### `scaffold_migration`
Generate a SQL migration file.

```json
{
  "name": "add_email_to_users",
  "type": "alter",
  "table": "users",
  "columns": [
    { "name": "email", "type": "string", "nullable": true }
  ]
}
```

---

## Configuration

### Environment Variables

Add to `.env`:

```env
# Database - Root access (for admin operations)
DB_ROOT_USER=root
DB_ROOT_PASSWORD=your_root_password

# Database - Guest access (limited to clients table)
# These are automatically added during installation
DB_GUEST_USER=guest
DB_GUEST_PASSWORD=guest_password

# Admin authentication for db_query_admin
ADMIN_SECRET_KEY=your_secret_admin_key
```

### MySQL Guest User Setup

The installer automatically creates the guest user during the migration step. If you need to set it up manually:

```sql
-- Create the clients table (if not exists)
CREATE TABLE IF NOT EXISTS `clients` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NULL,
    `phone` VARCHAR(50) NULL,
    `company` VARCHAR(255) NULL,
    `status` ENUM('active', 'inactive', 'pending') DEFAULT 'active',
    `notes` TEXT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create guest user with limited permissions
CREATE USER 'guest'@'localhost' IDENTIFIED BY 'guest_password';
GRANT SELECT, INSERT, UPDATE, DELETE ON ginto.clients TO 'guest'@'localhost';
FLUSH PRIVILEGES;
```

### Installation

The `clients` table and guest user are automatically set up when you run the installer:

1. Run the installer at `/install/`
2. During the "Run Migrations" step, the installer:
   - Creates all tables including `clients`
   - Creates the `guest` MySQL user
   - Grants limited permissions on `clients` table only
   - Updates `.env` with guest credentials

---

## Best Practices

### Before Making Changes
1. **Read first**: Use `read_file` to examine existing code
2. **Search**: Use `search_files` to find related code
3. **Analyze**: Use `analyze_file` for major changes
4. **Check impact**: Use `find_usages` before renaming/removing

### Making Edits
1. **Surgical edits**: Use `replace_in_file` with context, never rewrite entire files
2. **Verify**: Run `php -l` via `run_command` to check syntax
3. **Batch when possible**: Use `batch_edit` for multiple related changes

### Complex Tasks
1. **Save progress**: Use `save_task` for multi-step work
2. **Use scaffolding**: Don't write boilerplate manually
3. **Document next steps**: Include `nextSteps` when saving tasks

### Database Operations
1. **Start with guest**: Use `db_query` by default
2. **Elevate only when needed**: Use `db_query_admin` for DDL
3. **Validate queries**: Test with SELECT before INSERT/UPDATE

---

## Tool Discovery

Tools are discovered from:

1. **Local handlers** (`src/Handlers/*.php`): Classes with `#[McpTool]` attributes
2. **Tools folder** (`tools/*/server.json`): External MCP server definitions
3. **Runtime MCP servers**: Configured in `composer.json` under `config.mcp`

### Adding New Tools

Add a method with the `#[McpTool]` attribute:

```php
#[McpTool(
    name: 'my_tool',
    description: 'What this tool does'
)]
public function myTool(string $param1, ?int $param2 = null): array
{
    // Implementation
    return ['result' => 'success'];
}
```

Run `/mcp/unified?refresh=1` to discover new tools.

---

## Troubleshooting

### Tool not discovered
- Ensure `#[McpTool]` attribute is present
- Check PHP syntax: `php -l src/Handlers/YourHandler.php`
- Run `php bin/dump_discovered_tools.php` to debug

### Database connection failed
- Verify `.env` credentials
- Check MySQL is running: `systemctl status mysql`
- Ensure user has proper grants

### Task not loading
- Check `storage/agent_tasks.json` exists and is valid JSON
- Verify task ID matches exactly

---

## See Also

- [MCP Unifier](mcp-unifier.md) - Tool discovery and API endpoints
- [LLM Providers](llm-providers.md) - Provider configuration
- [Editor Chat](editor-chat.md) - Chat UI integration
