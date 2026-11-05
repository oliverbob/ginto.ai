# MCP Unifier

Overview

The `McpUnifier` aggregates and normalizes MCP tool discovery across multiple sources so clients (UI, scripts) can call a single endpoint and get a consistent list of all available tools.

What it discovers

- Static attribute-based handlers discovered by the existing `scripts/dump_discovered_tools.php` script (app-provided handlers in `src/Handlers`).
- `tools/` folders: looks for `server.json` files under `tools/<name>/server.json` and includes declared tools.
- Runtime MCP servers: configured via `composer.json` under `config.mcp.servers` or the `config.mcp.default_server_url` entry. The unifier will call the server's `/mcp/discover` to include its tools.

API

- GET `/mcp/unified`
  - Response: JSON payload with keys: `tools`, `mcps`, `counts`, `generated_at`, `errors`.
  - Query params:
    - `refresh=1` — force re-discovery (bypasses cache)
  - Security: this endpoint is admin-only by default. Access is allowed when either:
    - the current PHP session has `$_SESSION['role'] === 'admin'`, or
    - the request includes header `X-GINTO-ADMIN-TOKEN` (or `X-ADMIN-TOKEN`) matching the environment variable `GINTO_ADMIN_TOKEN` or `ADMIN_TOKEN`.
    - Example curl using token:

```bash
curl -H "X-GINTO-ADMIN-TOKEN: $GINTO_ADMIN_TOKEN" http://127.0.0.1:8000/mcp/unified
```

  Protect this endpoint appropriately on production (IP allowlist, authentication middleware, etc.).

Normalized tool shape

Each tool is normalized to a consistent shape for UI consumption, for example:

```
{
  "id": "github/get_file",
  "name": "github/get_file",
  "title": "github/get_file",
  "description": "Get file from GitHub",
  "input_schema": { ... },
  "source": "static|tools-folder|runtime",
  "mcp": "app|groq-mcp|my-remote-mcp",
  "handler": "App\\Handlers\\GithubMcp::getFile",
  "raw": { ... }
}
```

Caching

The unifier caches combined discovery results in `storage/mcp_unifier_cache.json` (default TTL 60 seconds). Use `?refresh=1` to force an immediate refresh.

## Chat UI Integration

The `/chat` page uses the following MCP endpoints for tool execution:

### POST `/mcp/call`

Lightweight session-based endpoint for executing individual tools from the chat UI.

- **Request body**: `{ "tool": "namespace/name", "args": { ... } }`
- **Response**: `{ "success": true, "result": { ... } }` or `{ "error": "message" }`
- **Security**: CSRF validation is skipped for this endpoint to allow JSON API calls. Session cookies are still used.
- **Example**:

```bash
curl -X POST http://localhost:8000/mcp/call \
  -H 'Content-Type: application/json' \
  -d '{"tool":"repo/create_or_update_file","args":{"file_path":"test.txt","content":"Hello"}}'
```

### POST `/mcp/chat`

Chat endpoint that handles conversation with the Groq API and automatic tool execution.

- **Request body**: `{ "message": "user message", "history": [...] }`
- **Response**: `{ "success": true, "response": "assistant reply", "history": [...] }`
- **Features**: Automatically discovers local handler tools and includes them in the Groq API request. When the model returns tool_calls, they are executed via `McpInvoker` or `StandardMcpHost::runTool()`.

### Tool Discovery Chain

1. **Local handlers** (`src/Handlers/*.php`): Classes with `#[McpTool]` attributes are discovered and their parameter schemas are built from method signatures.
2. **MCP servers**: Remote servers configured via `MCP_SERVER_URL` or `127.0.0.1:9010` are queried for additional tools.
3. **McpInvoker fallback**: If a tool isn't found on remote servers, `App\Core\McpInvoker` scans both `App\Handlers\*` and `Ginto\Handlers\*` namespaces.

How to add a new MCP

- Static handler inside this project: add `#[McpTool(...)]` attributes to methods in `src/Handlers/*`. Run discovery or request `/mcp/unified?refresh=1`.
- Tools folder package: drop a folder into `tools/<name>/` that contains a `server.json` describing `tools` or contains a discoverable server — the unifier will pick it up on next refresh.
- Remote runtime MCP server: register a runtime server URL in `composer.json` under `config.mcp.servers` (array of objects with `url` and optional `name`) or set `config.mcp.default_server_url`.

Security & notes

- The `/mcp/unified` endpoint is intended for development and admin UIs. Consider adding access controls before exposing it publicly.
- The unifier will include the `raw` discovery objects — be mindful that this may include default values or metadata; do not expose secrets.

Troubleshooting

- If a runtime server fails to respond, the unifier will include a placeholder tool with error details rather than failing the entire response.
- If you add a new handler but discovery doesn't show it, ensure `scripts/dump_discovered_tools.php` can run successfully and that attributes are present (not commented out).

Reverting changes

- To revert the `McpUnifier` implementation, restore files from git or remove `src/Core/McpUnifier.php` and the route in `src/Routes/web.php` and revert `public/assets/js/chat.js` to call `/mcp/discover`.

License

This document is part of the repository and follows the project's licensing.
