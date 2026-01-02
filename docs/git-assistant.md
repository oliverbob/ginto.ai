# Using Git with the Assistant (notes)

Repository path: `/home/oliverbob/ginto`

This project is in the `oliverbob/ginto` workspace. The assistant can run git commands in this repository by executing shell commands in the workspace root (for example via `run_in_terminal`).

Helper script
- `bin/git-helper.sh` runs `git` from the repository root so callers don't need to `cd` manually. Example:

```
./bin/git-helper.sh status
./bin/git-helper.sh add public/assets/js/chat.js
./bin/git-helper.sh commit -m "Describe changes"
./bin/git-helper.sh push origin main
```

Notes about pushing
- Remote pushes require valid credentials (SSH key or HTTPS credentials) configured on this machine for the remote URL. If `git push` prompts for credentials or fails due to auth, set up an SSH key or credential helper.

Assistant usage
- If you want the assistant to run git commands, ask it to run a specific command and confirm any potentially destructive action (commits, pushes, force-pushes).
- The assistant will run commands in the repository root. It will not expose secrets; you should ensure remote credentials are configured in the environment.

Security
- The assistant can execute git commands, but pushing changes to remotes is a sensitive action — review diffs and commits before asking the assistant to push.

Current assistant / client / MCP state and capabilities
------------------------------------------------------

- Local assistant integration: a browser-based editor chat UI is included (served from the app). The UI can call the app's `/mcp/unified` endpoint to discover available MCP tools and will surface capabilities in the editor.
- MCP unifier: `src/Core/McpUnifier.php` aggregates discovery from three sources:
	- static discovery script (`scripts/dump_discovered_tools.php`) if present
	- attribute-based handlers in `src/Handlers` (server-side PHP handlers annotated with `#[McpTool(...)]`)
	- `tools/` folders containing `server.json` for tool-based discovery and configured runtime servers (previously via `composer.json` or `MCP_SERVER_URL`)
- Local handler tools: the project exposes several handler-based MCP tools under `mcp: app` (these are available to the editor without any external MCP server):
	- `repo/describe` — returns repository summary (name, description, path, PHP version, file count)
	- `repo/list_files` — list files under the repo (filters vendor/storage/node_modules by default)
	- `repo/get_file` — returns file contents (with truncation) and metadata
	- `repo/git_status` — returns git porcelain status entries
- Tools from `tools/` folders: this repo includes `tools/groq-mcp` (and others) with `server.json` that define additional tools such as `chat_completion`, `format_text`, and `validate_profile` (these appear as `source: tools-folder` and `mcp: groq-mcp` in discovery).
- Runtime MCP servers: the project can be configured to use a separate MCP server (local or remote). Historically `composer.json` or the `.env` value `MCP_SERVER_URL` pointed at a runtime server (e.g. `http://127.0.0.1:9010`). If that server is unreachable you'll see discovery errors in the UI; the unifier will include `errors` in its output.

How discovery works in the UI
- On editor load the front-end JS (`public/assets/js/chat.js`) requests `GET /mcp/unified`. The response contains `tools`, `mcps`, `counts`, and `errors`. The UI renders a capabilities badge and a detailed tools panel including any discovery errors (e.g., failed HTTP fetch to a runtime MCP).

What I changed recently (useful for debugging)
- Removed the default local MCP URL from `composer.json` to avoid noisy discovery errors when a local Go-based MCP (on port 9010) is not running.
- Added a new handler `src/Handlers/CursorContext.php` which exposes the `repo/*` tools listed above so the UI and MCP clients get repo-aware capabilities similar to Cursor's client behavior.

Recommendations / next steps
- If you prefer not to run a runtime server, the included handler-based tools (`mcp: app`) provide useful local capabilities that the UI can call when you are authenticated as admin.
- To improve client integration, we can add JSON `input_schema` definitions to the new `repo/*` tools to help the UI render argument forms and validate inputs.

If you'd like, I can:
- Add compact `repo/summary_for_model` that returns a brief, model-friendly repository summary (README + top files) for prompt prep.
- Add JSON schemas to the handler tools for better UI integration.
- Re-enable a runtime MCP URL (or start the local MCP server) and show you how to access streaming endpoints.

---

