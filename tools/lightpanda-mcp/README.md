# Lightpanda MCP Server

Web automation tools for AI agents using [Lightpanda](https://lightpanda.io/) headless browser.

## Benefits

- **11x faster** than Chrome
- **9x less memory** (24MB vs 207MB)
- **Instant startup** - no cold start delays
- Full JavaScript execution
- CDP/Puppeteer compatible

## Installation

The `@lightpanda/browser` npm package automatically downloads the correct binary for your platform.

```bash
cd tools/lightpanda-mcp
npm install
```

That's it! The Lightpanda binary is installed automatically via postinstall.

### Upgrade Browser

To upgrade to the latest Lightpanda version:

```bash
npm run upgrade-browser
# or
npx @lightpanda/browser upgrade
```

## Usage

### Standalone

```bash
npm start
```

### With MCP Client

Add to your MCP configuration:

```json
{
  "mcpServers": {
    "lightpanda": {
      "command": "node",
      "args": ["tools/lightpanda-mcp/src/index.js"],
      "env": {
        "LIGHTPANDA_DISABLE_TELEMETRY": "true"
      }
    }
  }
}
```

## Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `LIGHTPANDA_HOST` | `127.0.0.1` | CDP server host |
| `LIGHTPANDA_PORT` | `9222` | CDP server port |
| `LIGHTPANDA_TIMEOUT` | `30000` | Page operation timeout (ms) |
| `LIGHTPANDA_DISABLE_TELEMETRY` | `false` | Disable Lightpanda telemetry |

## Tools

### `web_fetch`
Fetch a webpage with JavaScript execution. Returns page title and text content.

```json
{
  "url": "https://example.com",
  "waitForSelector": "#content",
  "waitTime": 1000
}
```

### `web_extract_links`
Extract all links from a webpage.

```json
{
  "url": "https://example.com"
}
```

### `web_screenshot`
Capture a screenshot (base64 PNG).

```json
{
  "url": "https://example.com",
  "fullPage": true
}
```

### `web_evaluate`
Run custom JavaScript on a page.

```json
{
  "url": "https://example.com",
  "script": "document.querySelectorAll('h1').length"
}
```

### `web_click`
Click an element and return resulting state.

```json
{
  "url": "https://example.com",
  "selector": "button.submit"
}
```

### `web_fill`
Fill form fields.

```json
{
  "url": "https://example.com/login",
  "fields": [
    { "selector": "#username", "value": "user@example.com" },
    { "selector": "#password", "value": "secret123" }
  ]
}
```

### `web_search`
Search the web via search engine.

```json
{
  "query": "AI agents automation",
  "engine": "duckduckgo"
}
```

## Integration with Ginto Sandbox

The npm package handles everything. To add to sandbox containers, just run:

```bash
npm install @lightpanda/browser
```

## License

MIT
