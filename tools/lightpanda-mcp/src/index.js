#!/usr/bin/env node
/**
 * Lightpanda MCP Server
 * 
 * Provides web automation tools for AI agents using Lightpanda headless browser.
 * 11x faster and 9x less memory than Chrome.
 * 
 * Tools:
 * - web_fetch: Fetch page content with JS execution
 * - web_extract_links: Extract all links from a page
 * - web_screenshot: Capture page screenshot (base64)
 * - web_evaluate: Run custom JavaScript on a page
 * - web_click: Click an element
 * - web_fill: Fill form inputs
 * - web_search: Search the web (via search engine)
 */

import { lightpanda } from '@lightpanda/browser';
import puppeteer from 'puppeteer-core';
import { Server } from '@modelcontextprotocol/sdk/server/index.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import {
  CallToolRequestSchema,
  ListToolsRequestSchema,
} from '@modelcontextprotocol/sdk/types.js';

// Configuration
const CONFIG = {
  host: process.env.LIGHTPANDA_HOST || '127.0.0.1',
  port: parseInt(process.env.LIGHTPANDA_PORT || '9222', 10),
  timeout: parseInt(process.env.LIGHTPANDA_TIMEOUT || '30000', 10),
};

let browserProcess = null;
let browser = null;

/**
 * Start Lightpanda CDP server using the official npm package
 */
async function ensureBrowserRunning() {
  if (browser) {
    try {
      // Check if still connected
      await browser.version();
      return browser;
    } catch {
      browser = null;
    }
  }

  // Try to connect to existing server first
  try {
    browser = await puppeteer.connect({
      browserWSEndpoint: `ws://${CONFIG.host}:${CONFIG.port}`,
    });
    return browser;
  } catch {
    // Need to start server
  }

  // Start Lightpanda server using the official npm package
  // The @lightpanda/browser package handles binary download automatically
  browserProcess = await lightpanda.serve({
    host: CONFIG.host,
    port: CONFIG.port,
  });

  // Wait a moment for server to be ready
  await new Promise(r => setTimeout(r, 500));

  // Connect via Puppeteer
  browser = await puppeteer.connect({
    browserWSEndpoint: `ws://${CONFIG.host}:${CONFIG.port}`,
  });

  return browser;
}

/**
 * Create a new page with standard settings
 */
async function createPage() {
  const b = await ensureBrowserRunning();
  const context = await b.createBrowserContext();
  const page = await context.newPage();
  page.setDefaultTimeout(CONFIG.timeout);
  return { page, context };
}

/**
 * Clean up page and context
 */
async function cleanupPage(page, context) {
  try {
    await page?.close();
    await context?.close();
  } catch {
    // Ignore cleanup errors
  }
}

// ============================================================================
// TOOL IMPLEMENTATIONS
// ============================================================================

async function webFetch(url, waitForSelector = null, waitTime = 0) {
  const { page, context } = await createPage();
  try {
    await page.goto(url, { waitUntil: 'networkidle0' });
    
    if (waitForSelector) {
      await page.waitForSelector(waitForSelector, { timeout: CONFIG.timeout });
    }
    
    if (waitTime > 0) {
      await new Promise(r => setTimeout(r, Math.min(waitTime, 10000)));
    }

    const title = await page.title();
    const content = await page.evaluate(() => {
      // Remove scripts and styles for cleaner content
      const scripts = document.querySelectorAll('script, style, noscript');
      scripts.forEach(s => s.remove());
      return document.body?.innerText || document.documentElement?.innerText || '';
    });

    const html = await page.content();

    return {
      success: true,
      url,
      title,
      content: content.slice(0, 50000), // Limit content size
      htmlLength: html.length,
    };
  } finally {
    await cleanupPage(page, context);
  }
}

async function webExtractLinks(url) {
  const { page, context } = await createPage();
  try {
    await page.goto(url, { waitUntil: 'networkidle0' });

    const links = await page.evaluate(() => {
      return Array.from(document.querySelectorAll('a[href]')).map(a => ({
        text: a.innerText?.trim().slice(0, 100) || '',
        href: a.href,
      })).filter(l => l.href && !l.href.startsWith('javascript:'));
    });

    return {
      success: true,
      url,
      count: links.length,
      links: links.slice(0, 200), // Limit number of links
    };
  } finally {
    await cleanupPage(page, context);
  }
}

async function webScreenshot(url, fullPage = false) {
  const { page, context } = await createPage();
  try {
    await page.setViewport({ width: 1280, height: 720 });
    await page.goto(url, { waitUntil: 'networkidle0' });

    const screenshot = await page.screenshot({
      encoding: 'base64',
      fullPage,
      type: 'png',
    });

    return {
      success: true,
      url,
      format: 'png',
      encoding: 'base64',
      data: screenshot,
    };
  } finally {
    await cleanupPage(page, context);
  }
}

async function webEvaluate(url, script) {
  const { page, context } = await createPage();
  try {
    await page.goto(url, { waitUntil: 'networkidle0' });

    const result = await page.evaluate(script);

    return {
      success: true,
      url,
      result: JSON.stringify(result, null, 2),
    };
  } catch (err) {
    return {
      success: false,
      url,
      error: err.message,
    };
  } finally {
    await cleanupPage(page, context);
  }
}

async function webClick(url, selector) {
  const { page, context } = await createPage();
  try {
    await page.goto(url, { waitUntil: 'networkidle0' });
    await page.waitForSelector(selector);
    await page.click(selector);
    
    // Wait for navigation or content change
    await new Promise(r => setTimeout(r, 1000));

    const newUrl = page.url();
    const content = await page.evaluate(() => document.body?.innerText?.slice(0, 10000) || '');

    return {
      success: true,
      originalUrl: url,
      clickedSelector: selector,
      currentUrl: newUrl,
      navigated: newUrl !== url,
      contentPreview: content.slice(0, 2000),
    };
  } finally {
    await cleanupPage(page, context);
  }
}

async function webFill(url, fields) {
  const { page, context } = await createPage();
  try {
    await page.goto(url, { waitUntil: 'networkidle0' });

    const results = [];
    for (const field of fields) {
      try {
        await page.waitForSelector(field.selector);
        await page.type(field.selector, field.value, { delay: 50 });
        results.push({ selector: field.selector, success: true });
      } catch (err) {
        results.push({ selector: field.selector, success: false, error: err.message });
      }
    }

    return {
      success: true,
      url,
      fields: results,
    };
  } finally {
    await cleanupPage(page, context);
  }
}

async function webSearch(query, engine = 'duckduckgo') {
  const engines = {
    duckduckgo: `https://html.duckduckgo.com/html/?q=${encodeURIComponent(query)}`,
    google: `https://www.google.com/search?q=${encodeURIComponent(query)}`,
    bing: `https://www.bing.com/search?q=${encodeURIComponent(query)}`,
  };

  const url = engines[engine] || engines.duckduckgo;
  const { page, context } = await createPage();
  
  try {
    await page.goto(url, { waitUntil: 'networkidle0' });

    const results = await page.evaluate((eng) => {
      const items = [];
      
      if (eng === 'duckduckgo') {
        document.querySelectorAll('.result').forEach(r => {
          const title = r.querySelector('.result__title')?.innerText;
          const link = r.querySelector('.result__url')?.href || r.querySelector('a')?.href;
          const snippet = r.querySelector('.result__snippet')?.innerText;
          if (title && link) items.push({ title, link, snippet });
        });
      } else {
        // Generic extraction for other engines
        document.querySelectorAll('a').forEach(a => {
          const href = a.href;
          if (href && !href.includes('google.com') && !href.includes('bing.com') && 
              href.startsWith('http') && a.innerText?.length > 10) {
            items.push({ title: a.innerText.slice(0, 100), link: href });
          }
        });
      }
      
      return items.slice(0, 10);
    }, engine);

    return {
      success: true,
      query,
      engine,
      results,
    };
  } finally {
    await cleanupPage(page, context);
  }
}

// ============================================================================
// MCP SERVER SETUP
// ============================================================================

const TOOLS = [
  {
    name: 'web_fetch',
    description: 'Fetch a webpage with full JavaScript execution. Returns the page title and text content. Use this to read articles, documentation, or any web content that requires JS rendering.',
    inputSchema: {
      type: 'object',
      properties: {
        url: { type: 'string', description: 'The URL to fetch' },
        waitForSelector: { type: 'string', description: 'Optional CSS selector to wait for before extracting content' },
        waitTime: { type: 'number', description: 'Optional additional wait time in ms (max 10000)' },
      },
      required: ['url'],
    },
  },
  {
    name: 'web_extract_links',
    description: 'Extract all links from a webpage. Returns list of URLs with their link text. Useful for crawling, finding resources, or navigation.',
    inputSchema: {
      type: 'object',
      properties: {
        url: { type: 'string', description: 'The URL to extract links from' },
      },
      required: ['url'],
    },
  },
  {
    name: 'web_screenshot',
    description: 'Capture a screenshot of a webpage. Returns base64-encoded PNG image. Useful for visual verification or when page layout matters.',
    inputSchema: {
      type: 'object',
      properties: {
        url: { type: 'string', description: 'The URL to screenshot' },
        fullPage: { type: 'boolean', description: 'Capture full scrollable page (default: false)' },
      },
      required: ['url'],
    },
  },
  {
    name: 'web_evaluate',
    description: 'Execute custom JavaScript on a webpage and return the result. The script runs in the page context with full DOM access.',
    inputSchema: {
      type: 'object',
      properties: {
        url: { type: 'string', description: 'The URL to load' },
        script: { type: 'string', description: 'JavaScript code to execute (must return a value)' },
      },
      required: ['url', 'script'],
    },
  },
  {
    name: 'web_click',
    description: 'Click an element on a webpage and return the resulting page state. Useful for navigation, button clicks, or triggering actions.',
    inputSchema: {
      type: 'object',
      properties: {
        url: { type: 'string', description: 'The URL to load' },
        selector: { type: 'string', description: 'CSS selector of element to click' },
      },
      required: ['url', 'selector'],
    },
  },
  {
    name: 'web_fill',
    description: 'Fill form fields on a webpage. Provide an array of selector/value pairs to populate inputs, textareas, etc.',
    inputSchema: {
      type: 'object',
      properties: {
        url: { type: 'string', description: 'The URL with the form' },
        fields: {
          type: 'array',
          items: {
            type: 'object',
            properties: {
              selector: { type: 'string', description: 'CSS selector of the input' },
              value: { type: 'string', description: 'Value to type into the input' },
            },
            required: ['selector', 'value'],
          },
          description: 'Array of {selector, value} objects',
        },
      },
      required: ['url', 'fields'],
    },
  },
  {
    name: 'web_search',
    description: 'Search the web using a search engine. Returns top results with titles, links, and snippets.',
    inputSchema: {
      type: 'object',
      properties: {
        query: { type: 'string', description: 'Search query' },
        engine: { type: 'string', enum: ['duckduckgo', 'google', 'bing'], description: 'Search engine (default: duckduckgo)' },
      },
      required: ['query'],
    },
  },
];

const server = new Server(
  { name: 'lightpanda-mcp', version: '1.0.0' },
  { capabilities: { tools: {} } }
);

server.setRequestHandler(ListToolsRequestSchema, async () => ({
  tools: TOOLS,
}));

server.setRequestHandler(CallToolRequestSchema, async (request) => {
  const { name, arguments: args } = request.params;

  try {
    let result;

    switch (name) {
      case 'web_fetch':
        result = await webFetch(args.url, args.waitForSelector, args.waitTime || 0);
        break;
      case 'web_extract_links':
        result = await webExtractLinks(args.url);
        break;
      case 'web_screenshot':
        result = await webScreenshot(args.url, args.fullPage || false);
        break;
      case 'web_evaluate':
        result = await webEvaluate(args.url, args.script);
        break;
      case 'web_click':
        result = await webClick(args.url, args.selector);
        break;
      case 'web_fill':
        result = await webFill(args.url, args.fields);
        break;
      case 'web_search':
        result = await webSearch(args.query, args.engine || 'duckduckgo');
        break;
      default:
        return {
          content: [{ type: 'text', text: `Unknown tool: ${name}` }],
          isError: true,
        };
    }

    return {
      content: [{ type: 'text', text: JSON.stringify(result, null, 2) }],
    };
  } catch (error) {
    return {
      content: [{ type: 'text', text: `Error: ${error.message}` }],
      isError: true,
    };
  }
});

// Cleanup on exit
process.on('SIGINT', () => {
  browser?.disconnect();
  browserProcess?.stdout?.destroy();
  browserProcess?.stderr?.destroy();
  browserProcess?.kill();
  process.exit(0);
});

process.on('SIGTERM', () => {
  browser?.disconnect();
  browserProcess?.stdout?.destroy();
  browserProcess?.stderr?.destroy();
  browserProcess?.kill();
  process.exit(0);
});

// Start server
const transport = new StdioServerTransport();
await server.connect(transport);
console.error('Lightpanda MCP server running');
