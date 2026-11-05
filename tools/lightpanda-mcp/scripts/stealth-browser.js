#!/usr/bin/env node
/**
 * Stealth Browser Script - Cloudflare bypass using puppeteer-extra-stealth
 * 
 * This is a fallback for when LightPanda gets blocked by Cloudflare.
 * Uses real Chromium with stealth patches to evade bot detection.
 * 
 * Usage: node stealth-browser.js <base64-encoded-json-payload>
 */

import puppeteer from 'puppeteer-extra';
import StealthPlugin from 'puppeteer-extra-plugin-stealth';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

// Apply stealth plugin
puppeteer.use(StealthPlugin());

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const COOKIE_FILE = path.join(__dirname, '..', '.stealth-cookies.json');

const CONFIG = {
  timeout: 30000,
  operationTimeout: 60000,
};

let browser = null;

/**
 * Debug logger
 */
function debug(msg) {
  console.error(`[STEALTH] ${new Date().toISOString()} - ${msg}`);
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
 * Get browser instance with stealth settings
 */
async function getBrowser() {
  if (browser) {
    try {
      await browser.version();
      return browser;
    } catch {
      browser = null;
    }
  }

  debug('Launching stealth Chrome...');
  
  // Random viewport dimensions to look more human
  const viewportSizes = [
    { width: 1920, height: 1080 },
    { width: 1366, height: 768 },
    { width: 1536, height: 864 },
    { width: 1440, height: 900 },
    { width: 1280, height: 720 },
  ];
  const viewport = viewportSizes[Math.floor(Math.random() * viewportSizes.length)];
  
  browser = await puppeteer.launch({
    headless: 'new',  // Use new headless mode (better than old)
    args: [
      '--no-sandbox',
      '--disable-setuid-sandbox',
      '--disable-dev-shm-usage',
      '--disable-accelerated-2d-canvas',
      '--no-first-run',
      '--no-zygote',
      '--disable-gpu',
      '--disable-blink-features=AutomationControlled',
      `--window-size=${viewport.width},${viewport.height}`,
      // Additional anti-detection args
      '--disable-extensions',
      '--disable-default-apps',
      '--disable-component-update',
      '--disable-domain-reliability',
      '--disable-sync',
      '--disable-background-networking',
      '--metrics-recording-only',
      '--mute-audio',
      '--lang=en-US,en',
      // Pretend we're not automated
      '--enable-features=NetworkService,NetworkServiceInProcess',
    ],
    ignoreDefaultArgs: ['--enable-automation'],  // Remove automation flag
  });

  debug('Stealth Chrome launched');
  return browser;
}

/**
 * Create page with stealth settings
 */
async function withPage(fn, options = {}) {
  const b = await getBrowser();
  const page = await b.newPage();
  
  // Set realistic viewport and user agent
  const viewportWidth = 1920 + Math.floor(Math.random() * 100);
  const viewportHeight = 1080 + Math.floor(Math.random() * 100);
  await page.setViewport({ 
    width: viewportWidth, 
    height: viewportHeight,
    deviceScaleFactor: 1,
    hasTouch: false,
    isLandscape: true,
    isMobile: false,
  });
  
  // Randomize user agent slightly
  const userAgents = [
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
  ];
  await page.setUserAgent(userAgents[Math.floor(Math.random() * userAgents.length)]);
  
  // Additional evasions via page.evaluateOnNewDocument
  await page.evaluateOnNewDocument(() => {
    // Override webdriver detection
    Object.defineProperty(navigator, 'webdriver', { get: () => undefined });
    
    // Override plugins to look real
    Object.defineProperty(navigator, 'plugins', {
      get: () => [
        { name: 'Chrome PDF Plugin', filename: 'internal-pdf-viewer' },
        { name: 'Chrome PDF Viewer', filename: 'mhjfbmdgcfjbbpaeojofohoefgiehjai' },
        { name: 'Native Client', filename: 'internal-nacl-plugin' },
      ],
    });
    
    // Override languages
    Object.defineProperty(navigator, 'languages', { get: () => ['en-US', 'en'] });
    
    // Mock permissions API
    const originalQuery = window.navigator.permissions?.query;
    if (originalQuery) {
      window.navigator.permissions.query = (parameters) => (
        parameters.name === 'notifications' ?
          Promise.resolve({ state: Notification.permission }) :
          originalQuery(parameters)
      );
    }
    
    // Override chrome runtime
    window.chrome = {
      runtime: {},
      loadTimes: function() {},
      csi: function() {},
      app: {},
    };
    
    // Add realistic screen properties
    Object.defineProperty(screen, 'availWidth', { get: () => window.innerWidth });
    Object.defineProperty(screen, 'availHeight', { get: () => window.innerHeight });
    
    // Override connection rtt to look real
    if (navigator.connection) {
      Object.defineProperty(navigator.connection, 'rtt', { get: () => 100 });
    }
  });

  // Load saved cookies if domain provided
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
    
    // Save cookies after operation
    if (options.domain) {
      try {
        const cookies = await page.cookies();
        saveCookies(options.domain, cookies);
      } catch {}
    }
    
    return result;
  } finally {
    await page.close();
  }
}

/**
 * Wait for Cloudflare challenge to complete
 */
async function waitForCloudflare(page, timeout = 15000) {
  debug('Checking for Cloudflare challenge...');
  
  const startTime = Date.now();
  
  while (Date.now() - startTime < timeout) {
    // Check for Cloudflare challenge elements
    const hasCfChallenge = await page.evaluate(() => {
      // Check for various Cloudflare indicators
      const cfRay = document.querySelector('meta[name="cf-ray"]');
      const cfChallenge = document.getElementById('cf-challenge-running');
      const cfTurnstile = document.querySelector('iframe[src*="turnstile"]');
      const cfWait = document.querySelector('.cf-browser-verification');
      const rayId = document.body?.innerText?.includes('Ray ID:');
      
      return cfChallenge || cfTurnstile || cfWait || 
             (rayId && document.body.innerText.includes('Checking your browser'));
    });
    
    if (!hasCfChallenge) {
      debug('No Cloudflare challenge detected or challenge passed');
      return true;
    }
    
    debug('Cloudflare challenge in progress, waiting...');
    await new Promise(r => setTimeout(r, 1000));
  }
  
  debug('Cloudflare challenge timeout');
  return false;
}

/**
 * Try to click Turnstile checkbox/widget
 * Turnstile is embedded in an iframe and often requires a click
 */
async function tryClickTurnstile(page) {
  try {
    // Find Turnstile iframe
    const turnstileFrame = await page.evaluate(() => {
      const iframes = document.querySelectorAll('iframe');
      for (const iframe of iframes) {
        const src = iframe.src || '';
        if (src.includes('turnstile') || src.includes('challenges.cloudflare.com')) {
          const rect = iframe.getBoundingClientRect();
          return { 
            found: true, 
            x: rect.x + rect.width / 2, 
            y: rect.y + rect.height / 2,
            width: rect.width,
            height: rect.height
          };
        }
      }
      return { found: false };
    });
    
    if (turnstileFrame.found) {
      debug(`Found Turnstile iframe at (${turnstileFrame.x}, ${turnstileFrame.y}), size: ${turnstileFrame.width}x${turnstileFrame.height}`);
      
      // Move mouse naturally to the center of the iframe
      await page.mouse.move(
        turnstileFrame.x + (Math.random() - 0.5) * 20,
        turnstileFrame.y + (Math.random() - 0.5) * 10
      );
      
      // Small pause like a human would
      await new Promise(r => setTimeout(r, 200 + Math.random() * 300));
      
      // Click
      await page.mouse.click(turnstileFrame.x, turnstileFrame.y);
      debug('Clicked on Turnstile iframe');
      
      // Wait a bit for it to process
      await new Promise(r => setTimeout(r, 1000 + Math.random() * 500));
      return true;
    }
    
    // Also try clicking on common Turnstile container classes
    const clicked = await page.evaluate(() => {
      // Common Turnstile widget selectors
      const selectors = [
        '.cf-turnstile',
        '[class*="turnstile"]',
        '.cf-chl-widget',
        'div[data-sitekey]',
        '#cf-turnstile',
      ];
      
      for (const sel of selectors) {
        const el = document.querySelector(sel);
        if (el) {
          el.click();
          return true;
        }
      }
      return false;
    });
    
    if (clicked) {
      debug('Clicked on Turnstile container element');
      return true;
    }
    
    debug('No Turnstile widget found to click');
    return false;
  } catch (err) {
    debug(`Error clicking Turnstile: ${err.message}`);
    return false;
  }
}

/**
 * Fetch a URL with stealth mode
 */
async function stealthFetch(url, options = {}) {
  const domain = new URL(url).hostname;
  
  return withPage(async (page) => {
    debug(`Fetching: ${url}`);
    
    // Navigate with timeout
    await page.goto(url, { 
      waitUntil: 'networkidle2',
      timeout: CONFIG.timeout,
    });
    
    // Wait for any Cloudflare challenge
    await waitForCloudflare(page);
    
    // Additional wait if specified
    if (options.waitTime) {
      await new Promise(r => setTimeout(r, options.waitTime));
    }
    
    // Get page content
    const title = await page.title();
    const content = await page.evaluate(() => {
      // Remove scripts and styles
      const clone = document.cloneNode(true);
      clone.querySelectorAll('script, style, noscript').forEach(el => el.remove());
      return clone.body?.innerText || '';
    });
    
    const html = await page.content();
    
    return {
      success: true,
      url,
      title,
      content: content.substring(0, 50000),
      html: html.substring(0, 100000),
    };
  }, { domain });
}

/**
 * Click an element and wait
 */
async function stealthClick(url, selector, options = {}) {
  const domain = new URL(url).hostname;
  
  return withPage(async (page) => {
    debug(`Navigating to ${url} and clicking ${selector}`);
    
    await page.goto(url, { 
      waitUntil: 'networkidle2',
      timeout: CONFIG.timeout,
    });
    
    await waitForCloudflare(page);
    
    // Wait for selector
    await page.waitForSelector(selector, { timeout: 10000 });
    
    // Click with human-like behavior
    await page.click(selector);
    
    // Wait after click
    const waitAfter = options.waitAfter || 2000;
    await new Promise(r => setTimeout(r, waitAfter));
    
    // Wait for navigation if expected
    if (options.waitForNavigation) {
      await page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 10000 }).catch(() => {});
    }
    
    const title = await page.title();
    const content = await page.evaluate(() => {
      const clone = document.cloneNode(true);
      clone.querySelectorAll('script, style, noscript').forEach(el => el.remove());
      return clone.body?.innerText || '';
    });
    
    return {
      success: true,
      url: page.url(),
      title,
      content: content.substring(0, 50000),
    };
  }, { domain });
}

/**
 * Fill a form and submit
 */
async function stealthFillForm(url, formData, options = {}) {
  const domain = new URL(url).hostname;
  
  return withPage(async (page) => {
    debug(`Navigating to ${url} and filling form`);
    
    await page.goto(url, { 
      waitUntil: 'networkidle2',
      timeout: CONFIG.timeout,
    });
    
    await waitForCloudflare(page);
    
    // Fill form fields
    for (const [selector, value] of Object.entries(formData)) {
      await page.waitForSelector(selector, { timeout: 5000 });
      await page.type(selector, value, { delay: 50 + Math.random() * 50 });
    }
    
    // Click submit if specified
    if (options.submitSelector) {
      await page.click(options.submitSelector);
      await new Promise(r => setTimeout(r, options.waitAfter || 3000));
    }
    
    const title = await page.title();
    const content = await page.evaluate(() => {
      const clone = document.cloneNode(true);
      clone.querySelectorAll('script, style, noscript').forEach(el => el.remove());
      return clone.body?.innerText || '';
    });
    
    return {
      success: true,
      url: page.url(),
      title,
      content: content.substring(0, 50000),
    };
  }, { domain });
}

/**
 * Take a screenshot
 */
async function stealthScreenshot(url, options = {}) {
  const domain = new URL(url).hostname;
  
  return withPage(async (page) => {
    debug(`Taking screenshot of ${url}`);
    
    await page.goto(url, { 
      waitUntil: 'networkidle2',
      timeout: CONFIG.timeout,
    });
    
    await waitForCloudflare(page);
    
    if (options.waitTime) {
      await new Promise(r => setTimeout(r, options.waitTime));
    }
    
    const screenshot = await page.screenshot({
      encoding: 'base64',
      fullPage: options.fullPage || false,
    });
    
    return {
      success: true,
      url,
      screenshot,
      title: await page.title(),
    };
  }, { domain });
}

/**
 * Generate image using Raphael AI (raphaelai.org)
 */
async function generateRaphaelImage(prompt, options = {}) {
  const url = 'https://raphaelai.org';
  const domain = 'raphaelai.org';
  
  return withPage(async (page) => {
    debug(`Generating image on Raphael AI: "${prompt.substring(0, 50)}..."`);
    
    // Navigate to the page
    await page.goto(url, { 
      waitUntil: 'networkidle2',
      timeout: CONFIG.timeout,
    });
    
    // Wait for Cloudflare challenge
    await waitForCloudflare(page, 20000);
    
    // Wait for the textarea to be available
    debug('Waiting for prompt textarea...');
    await page.waitForSelector('textarea', { timeout: 10000 });
    
    // Clear and fill the prompt
    await page.evaluate(() => {
      const textarea = document.querySelector('textarea');
      if (textarea) textarea.value = '';
    });
    await page.type('textarea', prompt, { delay: 30 + Math.random() * 20 });
    
    debug('Prompt entered, waiting for Turnstile...');
    
    // Try to interact with Turnstile widget
    // Turnstile is usually in an iframe - we need to click inside it
    await tryClickTurnstile(page);
    
    // Wait for Turnstile to complete (the button should become enabled)
    // Turnstile typically auto-solves for legitimate-looking browsers
    let buttonEnabled = false;
    const turnstileTimeout = options.turnstileTimeout || 30000;
    const startTime = Date.now();
    
    while (Date.now() - startTime < turnstileTimeout) {
      buttonEnabled = await page.evaluate(() => {
        const btn = document.querySelector('button[type="submit"]');
        return btn && !btn.disabled;
      });
      
      if (buttonEnabled) {
        debug('Turnstile passed, Generate button enabled!');
        break;
      }
      
      // Try clicking Turnstile again periodically
      if ((Date.now() - startTime) % 6000 < 2000) {
        await tryClickTurnstile(page);
      }
      
      debug('Waiting for Turnstile to complete...');
      await new Promise(r => setTimeout(r, 2000));
    }
    
    if (!buttonEnabled) {
      return {
        success: false,
        error: 'Turnstile CAPTCHA did not complete in time',
        needsCaptcha: true,
      };
    }
    
    // Click the Generate button
    debug('Clicking Generate button...');
    await page.click('button[type="submit"]');
    
    // Wait for the image to be generated (look for an img tag in the result area)
    debug('Waiting for image generation...');
    const maxWait = options.maxWait || 120000; // 2 minutes max
    const pollInterval = 3000;
    let imageUrl = null;
    const genStartTime = Date.now();
    
    while (Date.now() - genStartTime < maxWait) {
      // Check for the generated image
      imageUrl = await page.evaluate(() => {
        // Look for images in the result container (not the placeholder)
        const images = document.querySelectorAll('img');
        for (const img of images) {
          const src = img.src || '';
          // Skip placeholder and logo images
          if (src.includes('placeholder') || src.includes('logo') || 
              src.includes('inspiration') || src.includes('picsum')) {
            continue;
          }
          // Look for generated image URLs (usually from CDN or blob)
          if (src.includes('cdn.raphaelai.org') || src.startsWith('blob:') || 
              src.includes('data:image') || src.includes('generated')) {
            return src;
          }
        }
        return null;
      });
      
      if (imageUrl) {
        debug(`Image generated: ${imageUrl.substring(0, 100)}...`);
        break;
      }
      
      // Check for any error messages
      const hasError = await page.evaluate(() => {
        const text = document.body.innerText;
        return text.includes('error') || text.includes('failed') || text.includes('limit');
      });
      
      if (hasError) {
        const errorText = await page.evaluate(() => {
          // Try to find error message
          const alerts = document.querySelectorAll('[class*="error"], [class*="alert"]');
          for (const el of alerts) {
            if (el.innerText) return el.innerText;
          }
          return null;
        });
        
        if (errorText) {
          return {
            success: false,
            error: errorText,
          };
        }
      }
      
      debug(`Still generating... (${Math.round((Date.now() - genStartTime) / 1000)}s)`);
      await new Promise(r => setTimeout(r, pollInterval));
    }
    
    if (!imageUrl) {
      // Take a screenshot for debugging
      const screenshot = await page.screenshot({ encoding: 'base64' });
      return {
        success: false,
        error: 'Image generation timed out',
        screenshot,
      };
    }
    
    // If it's a blob URL or data URL, we already have the image
    let imageBase64 = null;
    if (imageUrl.startsWith('data:image')) {
      // Extract base64 from data URL
      imageBase64 = imageUrl.split(',')[1];
    } else if (imageUrl.startsWith('blob:')) {
      // For blob URLs, we need to get the image from the page
      imageBase64 = await page.evaluate(async (blobUrl) => {
        try {
          const response = await fetch(blobUrl);
          const blob = await response.blob();
          return new Promise((resolve) => {
            const reader = new FileReader();
            reader.onloadend = () => {
              const base64 = reader.result.split(',')[1];
              resolve(base64);
            };
            reader.readAsDataURL(blob);
          });
        } catch {
          return null;
        }
      }, imageUrl);
    } else {
      // For regular URLs, download the image
      try {
        const imageResponse = await page.evaluate(async (url) => {
          try {
            const response = await fetch(url);
            const blob = await response.blob();
            return new Promise((resolve) => {
              const reader = new FileReader();
              reader.onloadend = () => {
                const base64 = reader.result.split(',')[1];
                resolve(base64);
              };
              reader.readAsDataURL(blob);
            });
          } catch {
            return null;
          }
        }, imageUrl);
        imageBase64 = imageResponse;
      } catch (err) {
        debug(`Failed to download image: ${err.message}`);
      }
    }
    
    return {
      success: true,
      imageUrl,
      imageBase64,
      prompt,
      source: 'raphaelai.org',
    };
  }, { domain });
}

/**
 * Analyze page structure - useful for debugging Turnstile/Cloudflare issues
 */
async function analyzePage(url, options = {}) {
  const domain = new URL(url).hostname;
  
  return withPage(async (page) => {
    debug(`Analyzing page: ${url}`);
    
    await page.goto(url, { 
      waitUntil: 'networkidle2',
      timeout: CONFIG.timeout,
    });
    
    // Wait for page to fully load
    await new Promise(r => setTimeout(r, options.waitTime || 5000));
    
    // Collect page information
    const analysis = await page.evaluate(() => {
      const results = {
        title: document.title,
        url: window.location.href,
        frames: [],
        turnstile: {
          iframes: [],
          containers: [],
          dataAttributes: [],
        },
        forms: [],
        buttons: [],
      };
      
      // Find all frames
      document.querySelectorAll('iframe').forEach(iframe => {
        results.frames.push({
          src: (iframe.src || '').substring(0, 200),
          id: iframe.id,
          className: iframe.className,
          width: iframe.offsetWidth,
          height: iframe.offsetHeight,
        });
        
        // Check for Turnstile
        if (iframe.src && (iframe.src.includes('turnstile') || iframe.src.includes('challenges.cloudflare.com'))) {
          const rect = iframe.getBoundingClientRect();
          results.turnstile.iframes.push({
            src: iframe.src.substring(0, 200),
            x: rect.x,
            y: rect.y,
            width: rect.width,
            height: rect.height,
          });
        }
      });
      
      // Find Turnstile containers
      const turnstileSelectors = [
        '.cf-turnstile',
        '[class*="turnstile"]',
        '.cf-chl-widget',
        'div[data-sitekey]',
        '#cf-turnstile',
      ];
      
      turnstileSelectors.forEach(sel => {
        document.querySelectorAll(sel).forEach(el => {
          results.turnstile.containers.push({
            selector: sel,
            tagName: el.tagName,
            id: el.id,
            className: el.className,
            innerHTML: el.innerHTML.substring(0, 500),
          });
        });
      });
      
      // Find elements with data-sitekey
      document.querySelectorAll('[data-sitekey]').forEach(el => {
        results.turnstile.dataAttributes.push({
          tagName: el.tagName,
          id: el.id,
          className: el.className,
          sitekey: el.getAttribute('data-sitekey'),
        });
      });
      
      // Find forms
      document.querySelectorAll('form').forEach(form => {
        results.forms.push({
          id: form.id,
          action: form.action,
          method: form.method,
          inputs: Array.from(form.querySelectorAll('input, textarea, select')).map(inp => ({
            type: inp.type || inp.tagName.toLowerCase(),
            name: inp.name,
            id: inp.id,
            placeholder: inp.placeholder,
          })),
        });
      });
      
      // Find buttons
      document.querySelectorAll('button, [type="submit"], [role="button"]').forEach(btn => {
        results.buttons.push({
          tagName: btn.tagName,
          type: btn.type,
          text: btn.innerText?.substring(0, 50),
          disabled: btn.disabled,
          className: btn.className,
        });
      });
      
      return results;
    });
    
    // Take screenshot for reference
    const screenshot = await page.screenshot({ encoding: 'base64', fullPage: false });
    
    return {
      success: true,
      analysis,
      screenshot,
    };
  }, { domain });
}

// Main execution
async function main() {
  try {
    const payloadArg = process.argv[2];
    if (!payloadArg) {
      console.log(JSON.stringify({ success: false, error: 'No payload provided' }));
      process.exit(1);
    }

    const payload = JSON.parse(Buffer.from(payloadArg, 'base64').toString('utf8'));
    const { operation, params } = payload;

    debug(`Operation: ${operation}`);

    let result;
    switch (operation) {
      case 'fetch':
        result = await stealthFetch(params.url, params);
        break;
      case 'click':
        result = await stealthClick(params.url, params.selector, params);
        break;
      case 'fillForm':
        result = await stealthFillForm(params.url, params.formData, params);
        break;
      case 'screenshot':
        result = await stealthScreenshot(params.url, params);
        break;
      case 'generateRaphael':
        result = await generateRaphaelImage(params.prompt, params);
        break;
      case 'analyze':
        result = await analyzePage(params.url, params);
        break;
      default:
        result = { success: false, error: `Unknown operation: ${operation}` };
    }

    console.log(JSON.stringify(result));
  } catch (err) {
    console.log(JSON.stringify({ success: false, error: err.message }));
  } finally {
    if (browser) {
      await browser.close();
    }
  }
}

main();
