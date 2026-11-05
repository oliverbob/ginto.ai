#!/usr/bin/env node
/**
 * Lightpanda WebSearch Script
 * 
 * Called by PHP WebSearchMcp handler to perform web operations.
 * 
 * Usage: node websearch.js <base64-encoded-json-payload>
 * 
 * Payload format:
 * {
 *   "operation": "search|fetch|extractLinks|screenshot|evaluate",
 *   "params": { ... operation-specific params ... }
 * }
 */

import { lightpanda } from '@lightpanda/browser';
import puppeteer from 'puppeteer-core';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const COOKIE_FILE = path.join(__dirname, '..', '.cookies.json');

const CONFIG = {
  host: '127.0.0.1',
  port: 9222,
  timeout: 30000,
  operationTimeout: 60000, // Max time for any single operation
};

let browserProcess = null;
let browser = null;

/**
 * Debug logger - outputs to stderr so it doesn't interfere with JSON output
 */
function debug(msg) {
  console.error(`[DEBUG] ${new Date().toISOString()} - ${msg}`);
}

/**
 * Load saved cookies for a domain
 */
function loadCookies(domain) {
  try {
    if (!fs.existsSync(COOKIE_FILE)) return [];
    const data = JSON.parse(fs.readFileSync(COOKIE_FILE, 'utf8'));
    return data[domain] || [];
  } catch {
    return [];
  }
}

/**
 * Save cookies for a domain
 */
function saveCookies(domain, cookies) {
  try {
    let data = {};
    if (fs.existsSync(COOKIE_FILE)) {
      data = JSON.parse(fs.readFileSync(COOKIE_FILE, 'utf8'));
    }
    data[domain] = cookies;
    fs.writeFileSync(COOKIE_FILE, JSON.stringify(data, null, 2));
    debug(`Saved ${cookies.length} cookies for ${domain}`);
  } catch (err) {
    debug(`Failed to save cookies: ${err.message}`);
  }
}

/**
 * Wrap a promise with a timeout
 */
function withTimeout(promise, ms, errorMsg = 'Operation timed out') {
  let timeoutId;
  const timeoutPromise = new Promise((_, reject) => {
    timeoutId = setTimeout(() => reject(new Error(errorMsg)), ms);
  });
  return Promise.race([promise, timeoutPromise]).finally(() => clearTimeout(timeoutId));
}

/**
 * Start Lightpanda CDP server
 */
async function ensureBrowser() {
  if (browser) {
    try {
      await browser.version();
      return browser;
    } catch {
      browser = null;
    }
  }

  // Try to connect to existing server
  try {
    debug('Trying to connect to existing LightPanda server...');
    browser = await withTimeout(
      puppeteer.connect({
        browserWSEndpoint: `ws://${CONFIG.host}:${CONFIG.port}`,
      }),
      5000,
      'Failed to connect to existing LightPanda server'
    );
    debug('Connected to existing server');
    return browser;
  } catch {
    debug('No existing server, starting new one...');
  }

  browserProcess = await lightpanda.serve({
    host: CONFIG.host,
    port: CONFIG.port,
  });

  await new Promise(r => setTimeout(r, 500));

  browser = await puppeteer.connect({
    browserWSEndpoint: `ws://${CONFIG.host}:${CONFIG.port}`,
  });
  debug('Started and connected to new LightPanda server');

  return browser;
}

/**
 * Create page with cleanup helper and optional cookie persistence
 */
async function withPage(fn, options = {}) {
  const b = await ensureBrowser();
  const context = await b.createBrowserContext();
  const page = await context.newPage();
  page.setDefaultTimeout(CONFIG.timeout);
  
  // Set a realistic viewport and user agent
  await page.setViewport({ width: 1920, height: 1080 });
  await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
  
  // Load saved cookies if domain is provided
  if (options.domain) {
    const cookies = loadCookies(options.domain);
    if (cookies.length > 0) {
      try {
        await page.setCookie(...cookies);
        debug(`Loaded ${cookies.length} saved cookies for ${options.domain}`);
      } catch (err) {
        debug(`Failed to set cookies: ${err.message}`);
      }
    }
  }
  
  try {
    const result = await fn(page);
    
    // Save cookies after successful operation
    if (options.domain && options.saveCookies !== false) {
      try {
        const cookies = await page.cookies();
        if (cookies.length > 0) {
          saveCookies(options.domain, cookies);
        }
      } catch (err) {
        debug(`Failed to get cookies: ${err.message}`);
      }
    }
    
    return result;
  } finally {
    try {
      await page.close();
      await context.close();
    } catch {}
  }
}

/**
 * Human-like delay with variance
 */
function humanDelay(baseMs = 500) {
  const variance = baseMs * 0.5;
  const delay = baseMs + (Math.random() * variance * 2 - variance);
  return new Promise(r => setTimeout(r, Math.max(100, delay)));
}

/**
 * Simulate human-like mouse movement to an element
 */
async function humanMove(page, selector) {
  try {
    const element = await page.$(selector);
    if (!element) return false;
    
    const box = await element.boundingBox();
    if (!box) return false;
    
    // Random point within the element
    const x = box.x + box.width * (0.3 + Math.random() * 0.4);
    const y = box.y + box.height * (0.3 + Math.random() * 0.4);
    
    // Move mouse in steps (simulate human movement)
    const steps = 5 + Math.floor(Math.random() * 5);
    await page.mouse.move(x, y, { steps });
    
    await humanDelay(100);
    return true;
  } catch {
    return false;
  }
}

/**
 * Human-like click with movement
 */
async function humanClick(page, selector) {
  await humanMove(page, selector);
  await humanDelay(50);
  await page.click(selector);
  await humanDelay(200);
}

/**
 * Try to solve Cloudflare Turnstile CAPTCHA
 */
async function solveTurnstile(page, maxAttempts = 3) {
  for (let attempt = 0; attempt < maxAttempts; attempt++) {
    // Check if Turnstile is present
    const hasTurnstile = await page.evaluate(() => {
      return !!document.querySelector('iframe[src*="turnstile"]') || 
             !!document.querySelector('[class*="turnstile"]') ||
             !!document.querySelector('input[name="cf-turnstile-response"]');
    });
    
    if (!hasTurnstile) {
      return { success: true, reason: 'no-turnstile' };
    }
    
    // Try to find and click the checkbox
    const clicked = await page.evaluate(() => {
      // Look for the Turnstile iframe
      const iframe = document.querySelector('iframe[src*="turnstile"]');
      if (iframe) {
        // Turnstile is in an iframe - we need to try clicking it
        const box = iframe.getBoundingClientRect();
        // The checkbox is typically in the left portion of the widget
        return { found: true, x: box.x + 25, y: box.y + box.height / 2 };
      }
      
      // Look for direct checkbox element
      const checkbox = document.querySelector('[class*="turnstile"] input[type="checkbox"]') ||
                      document.querySelector('input[name="cf-turnstile-response"]');
      if (checkbox) {
        const box = checkbox.getBoundingClientRect();
        return { found: true, x: box.x + box.width / 2, y: box.y + box.height / 2 };
      }
      
      return { found: false };
    });
    
    if (clicked.found) {
      // Human-like approach to the checkbox
      await humanDelay(500 + Math.random() * 1000);
      
      // Move to a random starting position first
      await page.mouse.move(
        100 + Math.random() * 300,
        100 + Math.random() * 200,
        { steps: 3 }
      );
      await humanDelay(200);
      
      // Move to the checkbox area with natural movement
      await page.mouse.move(clicked.x, clicked.y, { steps: 10 + Math.floor(Math.random() * 10) });
      await humanDelay(100 + Math.random() * 200);
      
      // Click
      await page.mouse.click(clicked.x, clicked.y);
      
      // Wait for verification
      await humanDelay(3000 + Math.random() * 2000);
      
      // Check if it worked
      const solved = await page.evaluate(() => {
        // Check for success indicators
        const response = document.querySelector('input[name="cf-turnstile-response"]');
        if (response && response.value && response.value.length > 10) {
          return true;
        }
        
        // Check if the Turnstile widget shows success
        const successIndicator = document.querySelector('[class*="success"]') ||
                                document.querySelector('[data-state="success"]');
        return !!successIndicator;
      });
      
      if (solved) {
        return { success: true, reason: 'solved', attempts: attempt + 1 };
      }
    }
    
    // Wait before retry
    await humanDelay(2000);
  }
  
  return { success: false, reason: 'max-attempts-reached' };
}

/**
 * Search the web
 */
async function search({ query, engine = 'duckduckgo', maxResults = 10 }) {
  const engines = {
    duckduckgo: `https://html.duckduckgo.com/html/?q=${encodeURIComponent(query)}`,
    google: `https://www.google.com/search?q=${encodeURIComponent(query)}`,
    bing: `https://www.bing.com/search?q=${encodeURIComponent(query)}`,
  };

  const url = engines[engine] || engines.duckduckgo;

  return withPage(async (page) => {
    await page.goto(url, { waitUntil: 'networkidle0' });

    const results = await page.evaluate((eng, max) => {
      const items = [];

      if (eng === 'duckduckgo') {
        document.querySelectorAll('.result').forEach(r => {
          if (items.length >= max) return;
          const title = r.querySelector('.result__title')?.innerText?.trim();
          // Get the actual URL from data-hostname or parse from redirect
          let link = r.querySelector('.result__a')?.href || r.querySelector('a')?.href;
          // DuckDuckGo uses redirect URLs, try to extract actual URL
          if (link && link.includes('uddg=')) {
            try {
              const url = new URL(link, window.location.origin);
              const actualUrl = url.searchParams.get('uddg');
              if (actualUrl) link = decodeURIComponent(actualUrl);
            } catch {}
          }
          const snippet = r.querySelector('.result__snippet')?.innerText?.trim();
          if (title && link && link.startsWith('http')) items.push({ title, link, snippet });
        });
      } else if (eng === 'google') {
        document.querySelectorAll('.g').forEach(r => {
          if (items.length >= max) return;
          const title = r.querySelector('h3')?.innerText?.trim();
          const link = r.querySelector('a')?.href;
          const snippet = r.querySelector('.VwiC3b')?.innerText?.trim() || 
                         r.querySelector('span')?.innerText?.trim();
          if (title && link && !link.includes('google.com')) {
            items.push({ title, link, snippet });
          }
        });
      } else if (eng === 'bing') {
        document.querySelectorAll('.b_algo').forEach(r => {
          if (items.length >= max) return;
          const title = r.querySelector('h2')?.innerText?.trim();
          const link = r.querySelector('a')?.href;
          const snippet = r.querySelector('.b_caption p')?.innerText?.trim();
          if (title && link) items.push({ title, link, snippet });
        });
      }

      return items;
    }, engine, maxResults);

    return {
      success: true,
      query,
      engine,
      count: results.length,
      results,
    };
  });
}

/**
 * Fetch page content
 */
async function fetch({ url, waitForSelector, waitTime = 0 }) {
  return withPage(async (page) => {
    await page.goto(url, { waitUntil: 'networkidle0' });

    if (waitForSelector) {
      await page.waitForSelector(waitForSelector, { timeout: CONFIG.timeout });
    }

    if (waitTime > 0) {
      await new Promise(r => setTimeout(r, Math.min(waitTime, 10000)));
    }

    const title = await page.title();
    const content = await page.evaluate(() => {
      // Remove scripts and styles
      document.querySelectorAll('script, style, noscript, nav, footer, header').forEach(el => el.remove());
      return document.body?.innerText?.trim() || '';
    });

    return {
      success: true,
      url,
      title,
      content: content.slice(0, 50000), // Limit content size
      contentLength: content.length,
    };
  });
}

/**
 * Extract links from page
 */
async function extractLinks({ url }) {
  return withPage(async (page) => {
    await page.goto(url, { waitUntil: 'networkidle0' });

    const links = await page.evaluate(() => {
      return Array.from(document.querySelectorAll('a[href]'))
        .map(a => ({
          text: a.innerText?.trim().slice(0, 100) || '',
          href: a.href,
        }))
        .filter(l => l.href && !l.href.startsWith('javascript:') && !l.href.startsWith('#'));
    });

    // Deduplicate by href
    const unique = [...new Map(links.map(l => [l.href, l])).values()];

    return {
      success: true,
      url,
      count: unique.length,
      links: unique.slice(0, 200),
    };
  });
}

/**
 * Screenshot page
 */
async function screenshot({ url, fullPage = false }) {
  return withPage(async (page) => {
    await page.setViewport({ width: 1280, height: 720 });
    await page.goto(url, { waitUntil: 'networkidle0' });

    const data = await page.screenshot({
      encoding: 'base64',
      fullPage,
      type: 'png',
    });

    return {
      success: true,
      url,
      format: 'png',
      encoding: 'base64',
      data,
    };
  });
}

/**
 * Evaluate JS on page
 */
async function evaluate({ url, script }) {
  return withPage(async (page) => {
    await page.goto(url, { waitUntil: 'networkidle0' });

    try {
      const result = await page.evaluate(script);
      return {
        success: true,
        url,
        result: typeof result === 'object' ? JSON.stringify(result, null, 2) : String(result),
      };
    } catch (err) {
      return {
        success: false,
        url,
        error: err.message,
      };
    }
  });
}

/**
 * Click an element on the page
 */
async function click({ url, selector, waitAfter = 2000 }) {
  return withPage(async (page) => {
    await page.goto(url, { waitUntil: 'networkidle0' });

    try {
      await page.waitForSelector(selector, { timeout: 10000 });
      await page.click(selector);
      
      // Wait for any resulting actions
      await new Promise(r => setTimeout(r, Math.min(waitAfter, 10000)));
      
      const title = await page.title();
      const content = await page.evaluate(() => {
        document.querySelectorAll('script, style, noscript').forEach(el => el.remove());
        return document.body?.innerText?.trim() || '';
      });

      return {
        success: true,
        url,
        title,
        clicked: selector,
        content: content.slice(0, 50000),
      };
    } catch (err) {
      return {
        success: false,
        url,
        error: `Failed to click "${selector}": ${err.message}`,
      };
    }
  });
}

/**
 * Fill form fields on a page
 */
async function fill({ url, fields, submitSelector = null, waitAfter = 2000 }) {
  return withPage(async (page) => {
    await page.goto(url, { waitUntil: 'networkidle0' });

    try {
      // Fill each field
      for (const { selector, value } of fields) {
        await page.waitForSelector(selector, { timeout: 10000 });
        await page.click(selector);
        await page.type(selector, value, { delay: 10 });
      }

      // Optionally submit
      if (submitSelector) {
        await page.waitForSelector(submitSelector, { timeout: 10000 });
        await page.click(submitSelector);
      }

      // Wait for response
      await new Promise(r => setTimeout(r, Math.min(waitAfter, 30000)));

      const title = await page.title();
      const content = await page.evaluate(() => {
        document.querySelectorAll('script, style, noscript').forEach(el => el.remove());
        return document.body?.innerText?.trim() || '';
      });

      // Try to extract any images
      const images = await page.evaluate(() => {
        return Array.from(document.querySelectorAll('img[src]'))
          .map(img => img.src)
          .filter(src => src && (src.startsWith('http') || src.startsWith('data:')));
      });

      return {
        success: true,
        url,
        title,
        filledFields: fields.length,
        submitted: !!submitSelector,
        content: content.slice(0, 50000),
        images,
      };
    } catch (err) {
      return {
        success: false,
        url,
        error: `Form interaction failed: ${err.message}`,
      };
    }
  });
}

/**
 * Interactive image generation - fill form, submit, wait for generated image
 * Uses smart element detection for React/dynamic sites
 * Includes Cloudflare Turnstile CAPTCHA handling and cookie persistence
 */
async function generateImage({ url, promptSelector, promptValue, submitSelector, imageSelector, maxWaitTime = 60000 }) {
  const domain = new URL(url).hostname;
  
  // Use withPage with cookie persistence for this domain
  return withPage(async (page) => {
    debug(`generateImage: Starting with URL ${url} (domain: ${domain})`);
    
    // Navigate with timeout
    debug('generateImage: Navigating to page...');
    await withTimeout(
      page.goto(url, { waitUntil: 'networkidle0' }),
      30000,
      'Page navigation timed out after 30 seconds'
    );
    debug('generateImage: Page loaded');
    
    // Check for Cloudflare challenge page (interstitial)
    const isCloudflareChallenge = await page.evaluate(() => {
      const title = document.title.toLowerCase();
      const body = document.body?.innerText?.toLowerCase() || '';
      return title.includes('just a moment') || 
             title.includes('checking your browser') ||
             body.includes('checking if the site connection is secure') ||
             body.includes('verify you are human');
    });
    
    if (isCloudflareChallenge) {
      debug('generateImage: Cloudflare challenge detected, waiting for it to complete...');
      
      // Wait for the challenge to complete (page should redirect)
      for (let i = 0; i < 30; i++) {
        await humanDelay(2000);
        
        const stillChallenge = await page.evaluate(() => {
          const title = document.title.toLowerCase();
          return title.includes('just a moment') || title.includes('checking');
        });
        
        if (!stillChallenge) {
          debug('generateImage: Cloudflare challenge passed!');
          break;
        }
        
        // Try clicking the Turnstile checkbox if visible
        const turnstileInfo = await page.evaluate(() => {
          const iframe = document.querySelector('iframe[src*="turnstile"]');
          if (iframe) {
            const rect = iframe.getBoundingClientRect();
            return { found: true, x: rect.x + 25, y: rect.y + rect.height / 2 };
          }
          return { found: false };
        });
        
        if (turnstileInfo.found) {
          debug('generateImage: Attempting to click Turnstile checkbox...');
          // Move mouse naturally then click
          await page.mouse.move(
            turnstileInfo.x + Math.random() * 10 - 5,
            turnstileInfo.y + Math.random() * 10 - 5,
            { steps: 15 }
          );
          await humanDelay(100 + Math.random() * 200);
          await page.mouse.click(turnstileInfo.x, turnstileInfo.y);
          await humanDelay(3000);
        }
      }
      
      // Check if still on challenge page
      const stillBlocked = await page.evaluate(() => {
        return document.title.toLowerCase().includes('just a moment');
      });
      
      if (stillBlocked) {
        return {
          success: false,
          url,
          error: 'Cloudflare challenge could not be bypassed. The site is blocking automated access.',
          challengeType: 'cloudflare-interstitial'
        };
      }
    }
    
    // Wait for React hydration with human-like delay
    await humanDelay(2000);
    debug('generateImage: Checking for CAPTCHA...');
    
    // CAPTCHA DETECTION - try to solve Turnstile if present
    const captchaCheck = await withTimeout(
      page.evaluate(() => {
        const hasTurnstile = !!document.querySelector('iframe[src*="turnstile"]') || 
                            !!document.querySelector('[class*="turnstile"]') ||
                            !!document.querySelector('input[name="cf-turnstile-response"]');
        const hasRecaptcha = !!document.querySelector('iframe[src*="recaptcha"]') ||
                            !!document.querySelector('.g-recaptcha') ||
                            !!document.querySelector('[class*="recaptcha"]');
        const hasHcaptcha = !!document.querySelector('iframe[src*="hcaptcha"]') ||
                           !!document.querySelector('.h-captcha');
        const hasGenericCaptcha = !!document.querySelector('[class*="captcha"]') ||
                                 !!document.querySelector('[id*="captcha"]');
        
        return {
          hasCaptcha: hasTurnstile || hasRecaptcha || hasHcaptcha || hasGenericCaptcha,
          type: hasTurnstile ? 'turnstile' : hasRecaptcha ? 'recaptcha' : hasHcaptcha ? 'hcaptcha' : hasGenericCaptcha ? 'unknown' : 'none'
        };
      }),
      10000,
      'CAPTCHA check timed out'
    );
    debug(`generateImage: CAPTCHA check result: ${JSON.stringify(captchaCheck)}`);
    
    // If Turnstile CAPTCHA is detected, attempt to solve it
    if (captchaCheck.hasCaptcha && captchaCheck.type === 'turnstile') {
      debug('generateImage: Attempting to solve Turnstile CAPTCHA...');
      
      const solveResult = await solveTurnstile(page, 5); // Try up to 5 times
      debug(`generateImage: Turnstile solve result: ${JSON.stringify(solveResult)}`);
      
      if (!solveResult.success) {
        // Save cookies anyway - they might help on next attempt
        const cookies = await page.cookies();
        saveCookies(domain, cookies);
        
        return {
          success: false,
          url,
          error: `Turnstile CAPTCHA could not be solved automatically. Try again - cookies have been saved for next attempt.`,
          captchaType: 'turnstile',
          solveAttempts: solveResult.attempts || 5
        };
      }
      
      debug('generateImage: Turnstile CAPTCHA solved! Saving cookies...');
      // Save the clearance cookies for future requests
      const cookies = await page.cookies();
      saveCookies(domain, cookies);
      
      // Wait for page to reload/update after CAPTCHA
      await humanDelay(2000);
    } else if (captchaCheck.hasCaptcha) {
      // Non-Turnstile CAPTCHA - cannot solve automatically
      return {
        success: false,
        url,
        error: `CAPTCHA detected (${captchaCheck.type}). This type cannot be solved automatically.`,
        captchaType: captchaCheck.type
      };
    }
    
    // Simulate some human browsing behavior first
    debug('generateImage: Moving mouse...');
    await page.mouse.move(400 + Math.random() * 200, 300 + Math.random() * 100, { steps: 5 });
    await humanDelay(500);

    try {
      debug('generateImage: Looking for prompt input...');
      // Smart prompt input detection - find textarea or text input
      const promptFound = await withTimeout(
        page.evaluate((selectors, value) => {
        // Try provided selectors first
        const selectorList = selectors.split(',').map(s => s.trim());
        for (const sel of selectorList) {
          try {
            const el = document.querySelector(sel);
            if (el && (el.tagName === 'TEXTAREA' || (el.tagName === 'INPUT' && el.type === 'text'))) {
              el.focus();
              el.value = value;
              el.dispatchEvent(new Event('input', { bubbles: true }));
              el.dispatchEvent(new Event('change', { bubbles: true }));
              return { success: true, selector: sel };
            }
          } catch {}
        }
        
        // Fallback: find any textarea or text input with placeholder containing 'prompt' or 'description'
        const inputs = document.querySelectorAll('textarea, input[type="text"]');
        for (const el of inputs) {
          const placeholder = (el.placeholder || '').toLowerCase();
          const label = (el.getAttribute('aria-label') || '').toLowerCase();
          if (placeholder.includes('prompt') || placeholder.includes('description') || 
              placeholder.includes('image') || placeholder.includes('describe') ||
              placeholder.includes('create') || placeholder.includes('want') ||
              label.includes('prompt')) {
            el.focus();
            el.value = value;
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.dispatchEvent(new Event('change', { bubbles: true }));
            return { success: true, selector: 'auto-detected' };
          }
        }
        
        // Last resort: first visible textarea
        for (const el of inputs) {
          if (el.offsetParent !== null && el.tagName === 'TEXTAREA') {
            el.focus();
            el.value = value;
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.dispatchEvent(new Event('change', { bubbles: true }));
            return { success: true, selector: 'first-textarea' };
          }
        }
        
        return { success: false, error: 'No prompt input found' };
      }, promptSelector, promptValue),
      10000,
      'Prompt input detection timed out'
    );
      
      debug(`generateImage: Prompt input result: ${JSON.stringify(promptFound)}`);

      if (!promptFound.success) {
        // Get page content for debugging
        const pageHtml = await page.evaluate(() => document.body?.innerHTML?.slice(0, 5000) || 'No content');
        return { 
          success: false, 
          url, 
          error: promptFound.error || 'Could not find prompt input',
          debugHtml: pageHtml
        };
      }

      // Human-like delay after typing
      await humanDelay(800);
      
      debug('generateImage: Looking for submit button...');

      // Smart submit button detection
      const submitClicked = await withTimeout(
        page.evaluate((selectors) => {
        // Try provided selectors first (excluding :contains which isn't valid CSS)
        const selectorList = selectors.split(',')
          .map(s => s.trim())
          .filter(s => !s.includes(':contains'));
        
        for (const sel of selectorList) {
          try {
            const el = document.querySelector(sel);
            if (el) {
              el.click();
              return { success: true, selector: sel };
            }
          } catch {}
        }
        
        // Find button by text content
        const buttons = document.querySelectorAll('button');
        for (const btn of buttons) {
          const text = (btn.textContent || '').toLowerCase().trim();
          if (text === 'generate' || text.includes('generate')) {
            btn.click();
            return { success: true, selector: 'text:generate' };
          }
        }
        
        // Find any submit button
        for (const btn of buttons) {
          if (btn.type === 'submit' || btn.getAttribute('type') === 'submit') {
            btn.click();
            return { success: true, selector: 'type:submit' };
          }
        }
        
        // Find primary/action button
        for (const btn of buttons) {
          const classes = btn.className || '';
          if (classes.includes('primary') || classes.includes('submit') || classes.includes('action')) {
            btn.click();
            return { success: true, selector: 'class:primary' };
          }
        }
        
        // Return list of all buttons found for debugging
        const allButtons = Array.from(buttons).map(b => ({
          text: (b.textContent || '').trim().slice(0, 50),
          type: b.type,
          classes: b.className
        }));
        return { success: false, error: 'No submit button found', buttons: allButtons };
      }, submitSelector),
      10000,
      'Submit button detection timed out'
    );
      
    debug(`generateImage: Submit button result: ${JSON.stringify(submitClicked)}`);

      if (!submitClicked.success) {
        const pageHtml = await page.evaluate(() => document.body?.innerHTML?.slice(0, 5000) || 'No content');
        return { 
          success: false, 
          url, 
          error: submitClicked.error || 'Could not find submit button',
          buttons: submitClicked.buttons,
          debugHtml: pageHtml
        };
      }
      
      debug('generateImage: Button clicked, waiting for image...');

      // Wait for image to appear with polling
      const startTime = Date.now();
      let imageUrl = null;
      let previousImages = new Set();
      
      // Capture initial images to detect new ones
      debug('generateImage: Capturing initial image list...');
      previousImages = new Set(await page.evaluate(() => {
        return Array.from(document.querySelectorAll('img[src]')).map(img => img.src);
      }));
      debug(`generateImage: Found ${previousImages.size} initial images`);
      
      while (Date.now() - startTime < maxWaitTime) {
        await new Promise(r => setTimeout(r, 3000));
        
        // Check for generated image
        imageUrl = await page.evaluate((sel, prevImages) => {
          const prevSet = new Set(prevImages);
          
          // Try specific selector first
          if (sel) {
            try {
              const img = document.querySelector(sel);
              if (img && img.src && (img.src.startsWith('http') || img.src.startsWith('data:'))) {
                if (!prevSet.has(img.src)) {
                  return img.src;
                }
              }
            } catch {}
          }
          
          // Look for any NEW images that appeared after submission
          const imgs = document.querySelectorAll('img[src]');
          for (const img of imgs) {
            const src = img.src;
            if (!src || prevSet.has(src)) continue;
            
            // Skip small icons/logos
            if (img.naturalWidth < 100 && img.naturalHeight < 100 && !src.startsWith('data:image')) continue;
            
            // Skip known static assets
            if (src.includes('logo') || src.includes('icon') || src.includes('avatar')) continue;
            if (src.includes('example-images') || src.includes('testimonials')) continue;
            
            // This is likely a generated image
            if (src.startsWith('http') || src.startsWith('data:image') || src.startsWith('blob:')) {
              return src;
            }
          }
          
          return null;
        }, imageSelector, Array.from(previousImages));

        if (imageUrl) break;
        
        // Log progress every poll
        const elapsed = Math.round((Date.now() - startTime) / 1000);
        const currentImages = await page.evaluate(() => document.querySelectorAll('img[src]').length);
        debug(`generateImage: Polling... ${elapsed}s elapsed, ${currentImages} images on page`);
      }

      if (!imageUrl) {
        // Get page state for debugging
        const debugInfo = await page.evaluate(() => {
          const imgs = Array.from(document.querySelectorAll('img[src]')).map(img => ({
            src: img.src.slice(0, 100),
            width: img.naturalWidth,
            height: img.naturalHeight
          }));
          const bodyText = document.body?.innerText?.slice(0, 2000) || '';
          return { images: imgs, bodyText };
        });
        
        debug(`generateImage: Timeout! Final state: ${debugInfo.images.length} images`);
        
        return {
          success: false,
          url,
          error: 'Timeout waiting for generated image. The site may have failed to generate or returned an error.',
          debugImages: debugInfo.images,
          debugText: debugInfo.bodyText.slice(0, 1000)
        };
      }

      return {
        success: true,
        url,
        imageUrl,
        elapsed: Date.now() - startTime,
      };
    } catch (err) {
      return {
        success: false,
        url,
        error: `Image generation failed: ${err.message}`,
      };
    }
  }, { domain, saveCookies: true });
}

// Main execution
async function main() {
  const arg = process.argv[2];
  if (!arg) {
    console.log(JSON.stringify({ success: false, error: 'No payload provided' }));
    process.exit(1);
  }

  let payload;
  try {
    payload = JSON.parse(Buffer.from(arg, 'base64').toString('utf8'));
  } catch {
    console.log(JSON.stringify({ success: false, error: 'Invalid payload' }));
    process.exit(1);
  }

  const { operation, params } = payload;

  try {
    let result;
    switch (operation) {
      case 'search':
        result = await search(params);
        break;
      case 'fetch':
        result = await fetch(params);
        break;
      case 'extractLinks':
        result = await extractLinks(params);
        break;
      case 'screenshot':
        result = await screenshot(params);
        break;
      case 'evaluate':
        result = await evaluate(params);
        break;
      case 'click':
        result = await click(params);
        break;
      case 'fill':
        result = await fill(params);
        break;
      case 'generateImage':
        result = await generateImage(params);
        break;
      default:
        result = { success: false, error: `Unknown operation: ${operation}` };
    }
    console.log(JSON.stringify(result));
  } catch (err) {
    console.log(JSON.stringify({ success: false, error: err.message }));
  } finally {
    browser?.disconnect();
    browserProcess?.stdout?.destroy();
    browserProcess?.stderr?.destroy();
    browserProcess?.kill();
  }
}

main();
