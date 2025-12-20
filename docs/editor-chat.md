Editor chat assistant (Kimi K2)

This project includes an in-editor chat assistant available on the admin view editor (Admin → Pages → Edit View Script).

How it works:
- Frontend uses a small widget and sends messages to: POST /admin/pages/editor/chat (requests plain text replies via Accept: text/plain)
   - NOTE: The server will now return only the assistant's plain-text reply when the client sends `Accept: text/plain`. It strips SSE framing and embedded JSON wrappers so the editor receives clean human-readable text.
- Frontend also supports speech features via two endpoints:
   - POST /admin/pages/editor/tts → converts assistant text to audio (returns base64 audio)
   - POST /admin/pages/editor/stt → accepts an uploaded audio file and returns transcription text
- The API handler (`ApiController::editorChat`) checks CSRF + admin role and forwards the request to an MCP proxy server configured via MCP_SERVER_URL.
- A local MCP proxy server is provided in `tools/mcp-server/`. It can operate in stub mode (no upstream) or forward requests to a real model endpoint.

Local setup (recommended for development):
1. Install MCP server locally (recommended: php-mcp/server)

   cd tools/php-mcp-server
   ./install.sh   # clones the upstream php-mcp/server into ./server

   cd ./server
   composer install

2. Start the local MCP server (default port 9010):

   cd tools/php-mcp-server
   ./start.sh

   The server listens on port 9010 by default and provides the streamable HTTP MCP endpoints under /mcp.

3. Configure your main app to forward to the MCP server. In your environment (copy `.env.example` to `.env`), set:

   MCP_SERVER_URL=http://127.0.0.1:9010

4. Open the editor page and interact with the assistant.

Connecting to a real provider

- Configure your MCP proxy to forward to a live upstream by providing `MCP_TARGET_URL` and `MCP_API_KEY` in `tools/mcp-server/.env` or as environment variables.
- The proxy will simply forward { model, input, context } as JSON to the upstream target.

Security note

- Never commit API keys to source control. Put them in environment variables or a secrets manager.
- The app's route requires admin authentication and CSRF tokens, but you should still secure your MCP server when exposing it to networks.

Allow MCP to write absolute/outside-project files

- If you want the assistant (via MCP responses / file directives) to be able to create or overwrite files anywhere on the host filesystem, you can enable that explicitly. This is extremely powerful and dangerous — only enable for trusted development or admins.
- To allow absolute paths to be written by the assistant, set the environment variable in your main app or service environment:

   MCP_ALLOW_ANY_FILE_WRITE=1

- Absolute path writes are only honored when the API caller is a full admin (role_id === 1). Non-admin roles and the default configuration will continue to be restricted to the project root.
- Commits via the editor assistant (auto-apply) will still only succeed for files inside the repository; attempting to commit files outside the repo will fail safely.

If you accidentally committed a key

- If an API key was committed (for example in `tools/mcp-server/.env`) it has been removed from the working copy files in this repository — however the key may still exist in prior commits. You should immediately revoke/rotate the exposed key with your provider and replace it in your local `.env`.
- To remove secrets from commit history (advanced/destructive): consider using git-filter-repo or the BFG Repo-Cleaner to purge the secret from history. These tools rewrite history and require force-pushing and coordination with all collaborators. See the project's main README for suggested steps and safety instructions.

Default behavior

 - If `MCP_SERVER_URL` is not set in your environment, the PHP handler will automatically fall back to `http://127.0.0.1:9010` so the editor chat will work with the local php-mcp server started from tools/php-mcp-server.
 - For production, set `MCP_SERVER_URL` explicitly (and `MCP_API_KEY` if required) in the service environment (/etc/default/ginto-mcp or via your process manager) before starting the container or systemd service.

Editor UI detection

 - The editor UI now checks the MCP server tools list on load and will show a helpful banner if the `chat_completion` tool is not registered or the MCP server is unreachable. If you see that banner, start or configure `tools/php-mcp-server` and ensure `chat_completion` is available (the repository now includes `src/Handlers/ChatMcp.php`).

Additional local repo context tools

- This project now exposes cursor-like repo context tools via `src/Handlers/CursorContext.php`.
- Tools available locally: `repo/describe`, `repo/list_files`, `repo/get_file`, `repo/git_status`, and `repo/create_or_update_file`.
- These allow the UI or any MCP client to request repository summaries, list files (with sensible defaults and vendor/storage filtered), fetch file contents (with size truncation), inspect `git status` output, and create/update files — enabling the assistant to build an internal understanding of the repo similar to Cursor's client regardless of which model is used.

## Public Chat UI (`/chat`)

The public-facing chat interface uses dedicated MCP endpoints that bypass admin authentication:

- **POST `/mcp/call`**: Executes a single tool by name. Request: `{ "tool": "...", "args": {...} }`. CSRF is skipped for this endpoint.
- **POST `/mcp/chat`**: Full chat endpoint with Groq API integration. Request: `{ "message": "...", "history": [...] }`.

The frontend JavaScript (`public/assets/js/chat.js`) calls these endpoints instead of the removed `/mcp_host.php`. Tool execution flows through `StandardMcpHost::runTool()` which tries:
1. Remote MCP servers (if configured)
2. Local handler lookup via `McpUnifier`
3. Direct invocation via `McpInvoker`

### File Creation from Chat

When users ask the assistant to create files, the model can call `repo/create_or_update_file` with parameters:
- `file_path`: Relative path within the project
- `content`: File contents

The handler validates paths (no traversal outside project root) and writes the file. Example prompt: "create a file named hello.txt with content 'Hello World'"


If you expose the MCP with Apache, use a reverse-proxy vhost as shown in `tools/mcp-server/README.md` and ensure the MCP process remains bound to localhost.

Vultr / production notes

- I included a `ginto-mcp.service` systemd template in `tools/mcp-server/ginto-mcp.service`. Copy it to `/etc/systemd/system/ginto-mcp.service` and point `EnvironmentFile=/etc/default/ginto-mcp` to actual environment variables. Example `/etc/default/ginto-mcp`:

   MCP_TARGET_URL="https://your-provider.example/api/v1"
   MCP_API_KEY="<YOUR_SECRET>"

   After creating the file run:

   sudo systemctl daemon-reload
   sudo systemctl enable ginto-mcp.service
   sudo systemctl start ginto-mcp.service

- Use the included `nginx-mcp.conf` (tools/mcp-server/nginx-mcp.conf) as a starting point if you'd like to reverse-proxy mcp.example.com to the MCP server bound to localhost.

Firewall notes (Vultr):

 - We're intentionally recommending binding MCP server to 127.0.0.1 and using nginx reverse-proxy. If you open a port directly in Vultr, ensure you have a firewall rule and TLS in place.

File proposal / safe create flow

- When the assistant suggests creating a file (via JSON, fenced blocks, or FILE: headers) the server will now return a file proposal instead of writing immediately. This shows a preview and requires the user to confirm the creation.
- The editor UI will show the proposed path and file contents and offer a "Create file" button. Clicking it calls the safe server endpoint `POST /admin/pages/editor/file` which performs the same validation and write logic as before (CSRF checks, role checks, whitelist and traversal protection).
- If you explicitly want programmatic creation, you can pass create_file=true to `/admin/pages/editor/chat` and the server will attempt to create the file immediately — this remains restricted to admin roles and is subject to server configuration (`MCP_ALLOW_ANY_FILE_WRITE`).

Automatic content extraction

- When the server writes a file based on a chat response (either via `create_file=true` or after the UI confirm), it will attempt to extract a sensible file body from the assistant's reply. This helps when the assistant returns a code block or a shell snippet such as:
   ```bash
   echo "Hello" > hello.txt
   ```

- The server extraction heuristics currently support:
   - structured file directives (JSON `file` objects, FILE: headers, or fenced `file:` blocks) via the existing `parseFileDirective` logic;
   - fenced code blocks: `text`/`txt` blocks are used verbatim; `bash`/`sh` blocks are analyzed for common redirection patterns like `echo ... > filename` and `cat > filename <<EOF ... EOF`, extracting the inner file content rather than saving the whole snippet;
   - fallback behavior: if no clear pattern is detected, the assistant reply will be written as-is.

This reduces accidental writes of shell wrappers and keeps created files clean. If you want different rules or stricter validation, edit `src/Controllers/ApiController.php`'s extraction helper.

This manual confirm flow prevents accidental writes from model responses and keeps file creation explicit, auditable, and safe.
