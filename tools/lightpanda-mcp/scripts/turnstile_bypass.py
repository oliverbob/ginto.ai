#!/usr/bin/env python3
"""
Turnstile Bypass using undetected-chromedriver

This script uses undetected-chromedriver to bypass Cloudflare Turnstile
which blocks standard Puppeteer/Selenium automation.

Usage:
    python turnstile_bypass.py analyze <url> [wait_time]
    python turnstile_bypass.py generate <prompt>

The script outputs JSON to stdout.
"""

import sys
import json
import time
import base64
import os

# Use Xvfb display if available
if not os.environ.get('DISPLAY'):
    os.environ['DISPLAY'] = ':99'

try:
    import undetected_chromedriver as uc
    from selenium.webdriver.common.by import By
    from selenium.webdriver.support.ui import WebDriverWait
    from selenium.webdriver.support import expected_conditions as EC
except ImportError as e:
    print(json.dumps({"success": False, "error": f"Import error: {e}"}))
    sys.exit(1)


def create_driver(headless=False):
    """Create undetected Chrome driver with stealth settings"""
    options = uc.ChromeOptions()
    options.add_argument('--no-sandbox')
    options.add_argument('--disable-dev-shm-usage')
    options.add_argument('--disable-gpu')
    options.add_argument('--window-size=1920,1080')
    options.add_argument('--lang=en-US,en')
    
    # Headless mode can be detected more easily
    # Use non-headless with Xvfb for better evasion
    if headless:
        options.add_argument('--headless=new')
    
    driver = uc.Chrome(options=options, use_subprocess=True, headless=headless)
    return driver


def analyze_page(url, wait_time=30, headless=False):
    """Analyze a page for Turnstile widget"""
    driver = None
    try:
        driver = create_driver(headless=headless)
        driver.get(url)
        
        start_time = time.time()
        turnstile_found = False
        button_enabled = False
        snapshots = []
        
        while time.time() - start_time < wait_time:
            time.sleep(2)
            
            # Check for Turnstile iframe
            iframes = driver.find_elements(By.TAG_NAME, 'iframe')
            turnstile_iframes = []
            for iframe in iframes:
                src = iframe.get_attribute('src') or ''
                if 'turnstile' in src or 'challenges.cloudflare.com' in src:
                    rect = iframe.rect
                    turnstile_iframes.append({
                        'src': src[:200],
                        'x': rect['x'],
                        'y': rect['y'],
                        'width': rect['width'],
                        'height': rect['height']
                    })
            
            if turnstile_iframes and not turnstile_found:
                turnstile_found = True
                snapshots.append({
                    'event': 'turnstile_detected',
                    'time': time.time() - start_time,
                    'iframes': turnstile_iframes
                })
            
            # Check for cf-turnstile-response
            try:
                response_input = driver.find_element(By.NAME, 'cf-turnstile-response')
                response_value = response_input.get_attribute('value')
                if response_value:
                    snapshots.append({
                        'event': 'turnstile_solved',
                        'time': time.time() - start_time
                    })
                    button_enabled = True
                    break
            except:
                pass
            
            # Check if Generate button is enabled
            try:
                buttons = driver.find_elements(By.CSS_SELECTOR, 'button[type="submit"]')
                for btn in buttons:
                    if not btn.get_attribute('disabled'):
                        button_enabled = True
                        snapshots.append({
                            'event': 'button_enabled',
                            'time': time.time() - start_time
                        })
                        break
            except:
                pass
            
            if button_enabled:
                break
        
        # Final analysis
        analysis = {
            'title': driver.title,
            'url': driver.current_url,
            'frames': [],
            'turnstile': {
                'iframes': turnstile_iframes if turnstile_found else [],
                'responseValue': 'present' if button_enabled else 'empty'
            },
            'buttons': []
        }
        
        # Get all iframes
        for iframe in driver.find_elements(By.TAG_NAME, 'iframe'):
            analysis['frames'].append({
                'src': (iframe.get_attribute('src') or '')[:200],
                'id': iframe.get_attribute('id'),
                'width': iframe.rect['width'],
                'height': iframe.rect['height']
            })
        
        # Get buttons
        for btn in driver.find_elements(By.CSS_SELECTOR, 'button, [type="submit"]'):
            analysis['buttons'].append({
                'text': btn.text[:50] if btn.text else '',
                'disabled': btn.get_attribute('disabled') == 'true' or btn.get_attribute('disabled') == '',
                'type': btn.get_attribute('type')
            })
        
        # Take screenshot
        screenshot = driver.get_screenshot_as_base64()
        
        return {
            'success': True,
            'analysis': analysis,
            'screenshot': screenshot,
            'pollDuration': time.time() - start_time,
            'turnstileFound': turnstile_found,
            'buttonEnabled': button_enabled,
            'snapshots': snapshots
        }
        
    except Exception as e:
        return {'success': False, 'error': str(e)}
    finally:
        if driver:
            driver.quit()


def generate_raphael_image(prompt, max_wait=120, headless=False):
    """Generate image using Raphael AI"""
    driver = None
    try:
        driver = create_driver(headless=headless)
        driver.get('https://raphaelai.org')
        
        # Wait for textarea
        wait = WebDriverWait(driver, 30)
        textarea = wait.until(EC.presence_of_element_located((By.TAG_NAME, 'textarea')))
        
        # Enter prompt
        textarea.clear()
        textarea.send_keys(prompt)
        
        # Wait for Turnstile to complete (button becomes enabled)
        start_time = time.time()
        button_enabled = False
        
        while time.time() - start_time < 60:  # 60s for Turnstile
            try:
                buttons = driver.find_elements(By.CSS_SELECTOR, 'button[type="submit"]')
                for btn in buttons:
                    disabled = btn.get_attribute('disabled')
                    if disabled is None or disabled == 'false':
                        button_enabled = True
                        break
                if button_enabled:
                    break
            except:
                pass
            time.sleep(2)
        
        if not button_enabled:
            screenshot = driver.get_screenshot_as_base64()
            return {
                'success': False,
                'error': 'Turnstile CAPTCHA did not complete in time',
                'needsCaptcha': True,
                'screenshot': screenshot
            }
        
        # Click Generate button
        submit_btn = driver.find_element(By.CSS_SELECTOR, 'button[type="submit"]')
        submit_btn.click()
        
        # Wait for image generation
        image_url = None
        gen_start = time.time()
        
        while time.time() - gen_start < max_wait:
            time.sleep(3)
            
            # Look for generated image
            images = driver.find_elements(By.TAG_NAME, 'img')
            for img in images:
                src = img.get_attribute('src') or ''
                # Skip placeholder images
                if any(skip in src for skip in ['placeholder', 'logo', 'inspiration', 'picsum']):
                    continue
                # Look for CDN or blob URLs
                if 'cdn.raphaelai.org' in src or src.startswith('blob:') or src.startswith('data:image'):
                    image_url = src
                    break
            
            if image_url:
                break
        
        if not image_url:
            screenshot = driver.get_screenshot_as_base64()
            return {
                'success': False,
                'error': 'Image generation timed out',
                'screenshot': screenshot
            }
        
        # Get image as base64
        image_base64 = None
        if image_url.startswith('data:image'):
            image_base64 = image_url.split(',')[1]
        else:
            # Download image via JavaScript
            script = """
            return fetch(arguments[0])
                .then(r => r.blob())
                .then(blob => new Promise((resolve, reject) => {
                    const reader = new FileReader();
                    reader.onloadend = () => resolve(reader.result.split(',')[1]);
                    reader.onerror = reject;
                    reader.readAsDataURL(blob);
                }));
            """
            try:
                image_base64 = driver.execute_script(script, image_url)
            except:
                pass
        
        return {
            'success': True,
            'imageUrl': image_url,
            'imageBase64': image_base64,
            'prompt': prompt,
            'source': 'raphaelai.org'
        }
        
    except Exception as e:
        return {'success': False, 'error': str(e)}
    finally:
        if driver:
            driver.quit()


def main():
    if len(sys.argv) < 2:
        print(json.dumps({'success': False, 'error': 'Usage: turnstile_bypass.py <operation> [args] [--headless]'}))
        sys.exit(1)
    
    operation = sys.argv[1]
    headless = '--headless' in sys.argv
    
    if operation == 'analyze':
        url = sys.argv[2] if len(sys.argv) > 2 and not sys.argv[2].startswith('--') else 'https://raphaelai.org'
        wait_time = 30
        for arg in sys.argv[3:]:
            if arg.isdigit():
                wait_time = int(arg)
        result = analyze_page(url, wait_time, headless=headless)
    elif operation == 'generate':
        prompt = sys.argv[2] if len(sys.argv) > 2 and not sys.argv[2].startswith('--') else 'a beautiful sunset'
        result = generate_raphael_image(prompt, headless=headless)
    else:
        result = {'success': False, 'error': f'Unknown operation: {operation}'}
    
    print(json.dumps(result))


if __name__ == '__main__':
    main()
