<?php

declare(strict_types=1);

namespace App\Handlers;

/**
 * Project Templates
 * 
 * Contains all project scaffolding templates for the sandbox.
 * Separated from SandboxMcp for better readability and maintainability.
 */
final class ProjectTemplates
{
    /**
     * Get available project templates
     */
    public static function getTemplates(): array
    {
        return [
            'html' => [
                'name' => 'Static HTML Website',
                'description' => 'Basic HTML/CSS/JS website',
                'files' => [
                    ['path' => 'index.html', 'template' => 'html_index'],
                    ['path' => 'css/style.css', 'template' => 'html_css'],
                    ['path' => 'js/main.js', 'template' => 'html_js'],
                ],
            ],
            'php' => [
                'name' => 'PHP Website',
                'description' => 'PHP website with routing',
                'files' => [
                    ['path' => 'index.php', 'template' => 'php_index'],
                    ['path' => 'includes/header.php', 'template' => 'php_header'],
                    ['path' => 'includes/footer.php', 'template' => 'php_footer'],
                    ['path' => 'css/style.css', 'template' => 'html_css'],
                    ['path' => 'js/main.js', 'template' => 'html_js'],
                ],
            ],
            'react' => [
                'name' => 'React App',
                'description' => 'React application with Vite',
                'files' => [
                    ['path' => 'package.json', 'template' => 'react_package'],
                    ['path' => 'vite.config.js', 'template' => 'react_vite_config'],
                    ['path' => 'index.html', 'template' => 'react_index_html'],
                    ['path' => 'src/main.jsx', 'template' => 'react_main'],
                    ['path' => 'src/App.jsx', 'template' => 'react_app'],
                    ['path' => 'src/App.css', 'template' => 'react_app_css'],
                    ['path' => 'src/index.css', 'template' => 'react_index_css'],
                ],
                'post_commands' => ['npm install'],
            ],
            'vue' => [
                'name' => 'Vue App',
                'description' => 'Vue 3 application with Vite',
                'files' => [
                    ['path' => 'package.json', 'template' => 'vue_package'],
                    ['path' => 'vite.config.js', 'template' => 'vue_vite_config'],
                    ['path' => 'index.html', 'template' => 'vue_index_html'],
                    ['path' => 'src/main.js', 'template' => 'vue_main'],
                    ['path' => 'src/App.vue', 'template' => 'vue_app'],
                    ['path' => 'src/style.css', 'template' => 'vue_style_css'],
                ],
                'post_commands' => ['npm install'],
            ],
            'node' => [
                'name' => 'Node.js API',
                'description' => 'Express.js REST API',
                'files' => [
                    ['path' => 'package.json', 'template' => 'node_package'],
                    ['path' => 'index.js', 'template' => 'node_index'],
                    ['path' => 'routes/api.js', 'template' => 'node_routes'],
                    ['path' => '.env.example', 'template' => 'node_env'],
                ],
                'post_commands' => ['npm install'],
            ],
            'python' => [
                'name' => 'Python Flask API',
                'description' => 'Flask web application',
                'files' => [
                    ['path' => 'app.py', 'template' => 'python_app'],
                    ['path' => 'requirements.txt', 'template' => 'python_requirements'],
                    ['path' => 'templates/index.html', 'template' => 'python_template'],
                    ['path' => 'static/style.css', 'template' => 'html_css'],
                ],
                'post_commands' => ['pip install -r requirements.txt'],
            ],
            'tailwind' => [
                'name' => 'Tailwind CSS Website',
                'description' => 'Static site with Tailwind CSS',
                'files' => [
                    ['path' => 'package.json', 'template' => 'tailwind_package'],
                    ['path' => 'tailwind.config.js', 'template' => 'tailwind_config'],
                    ['path' => 'postcss.config.js', 'template' => 'tailwind_postcss'],
                    ['path' => 'src/input.css', 'template' => 'tailwind_input_css'],
                    ['path' => 'src/index.html', 'template' => 'tailwind_index'],
                ],
                'post_commands' => ['npm install'],
            ],
        ];
    }

    /**
     * Get template content by name
     */
    public static function getContent(string $template, string $projectName, string $description): string
    {
        return match($template) {
            // HTML templates
            'html_index' => self::htmlIndex($projectName, $description),
            'html_css' => self::htmlCss(),
            'html_js' => self::htmlJs($projectName),
            
            // PHP templates
            'php_index' => self::phpIndex($projectName, $description),
            'php_header' => self::phpHeader($projectName),
            'php_footer' => self::phpFooter($projectName),
            
            // React templates
            'react_package' => self::reactPackage($projectName),
            'react_vite_config' => self::reactViteConfig(),
            'react_index_html' => self::reactIndexHtml($projectName),
            'react_main' => self::reactMain(),
            'react_app' => self::reactApp($projectName, $description),
            'react_app_css' => self::reactAppCss(),
            'react_index_css' => self::reactIndexCss(),
            
            // Vue templates
            'vue_package' => self::vuePackage($projectName),
            'vue_vite_config' => self::vueViteConfig(),
            'vue_index_html' => self::vueIndexHtml($projectName),
            'vue_main' => self::vueMain(),
            'vue_app' => self::vueApp($projectName, $description),
            'vue_style_css' => self::vueStyleCss(),
            
            // Node.js templates
            'node_package' => self::nodePackage($projectName, $description),
            'node_index' => self::nodeIndex($projectName, $description),
            'node_routes' => self::nodeRoutes(),
            'node_env' => self::nodeEnv(),
            
            // Python templates
            'python_app' => self::pythonApp($projectName, $description),
            'python_requirements' => self::pythonRequirements(),
            'python_template' => self::pythonTemplate(),
            
            // Tailwind templates
            'tailwind_package' => self::tailwindPackage($projectName),
            'tailwind_config' => self::tailwindConfig(),
            'tailwind_postcss' => self::tailwindPostcss(),
            'tailwind_input_css' => self::tailwindInputCss(),
            'tailwind_index' => self::tailwindIndex($projectName, $description),
            
            default => "// Template not found: {$template}"
        };
    }

    /**
     * Get run command hint for a project type
     */
    public static function getRunHint(string $projectType, string $projectPath): string
    {
        return match($projectType) {
            'html' => 'Open index.html in a browser, or use a simple HTTP server',
            'php' => 'Run with: php -S 0.0.0.0:8000',
            'react' => 'Run with: cd ' . $projectPath . ' && npm run dev',
            'vue' => 'Run with: cd ' . $projectPath . ' && npm run dev',
            'node' => 'Run with: cd ' . $projectPath . ' && npm start',
            'python' => 'Run with: cd ' . $projectPath . ' && python app.py',
            'tailwind' => 'Build CSS: cd ' . $projectPath . ' && npm run build',
            default => 'Check project documentation'
        };
    }

    // =========================================================================
    // HTML TEMPLATES
    // =========================================================================

    private static function htmlIndex(string $projectName, string $description): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$projectName}</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <nav>
            <h1>{$projectName}</h1>
        </nav>
    </header>
    
    <main>
        <section class="hero">
            <h2>Welcome to {$projectName}</h2>
            <p>{$description}</p>
        </section>
    </main>
    
    <footer>
        <p>&copy; 2024 {$projectName}. All rights reserved.</p>
    </footer>
    
    <script src="js/main.js"></script>
</body>
</html>
HTML;
    }

    private static function htmlCss(): string
    {
        return <<<CSS
/* Reset and base styles */
*, *::before, *::after {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
    line-height: 1.6;
    color: #333;
    background-color: #f5f5f5;
}

/* Header */
header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 1rem 2rem;
}

nav h1 {
    font-size: 1.5rem;
}

/* Main content */
main {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem;
}

.hero {
    text-align: center;
    padding: 4rem 2rem;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.hero h2 {
    font-size: 2.5rem;
    margin-bottom: 1rem;
    color: #667eea;
}

/* Footer */
footer {
    text-align: center;
    padding: 2rem;
    background: #333;
    color: white;
    margin-top: 2rem;
}
CSS;
    }

    private static function htmlJs(string $projectName): string
    {
        return <<<JS
// Main JavaScript file
document.addEventListener('DOMContentLoaded', function() {
    console.log('{$projectName} loaded successfully!');
    
    // Add your JavaScript code here
});
JS;
    }

    // =========================================================================
    // PHP TEMPLATES
    // =========================================================================

    private static function phpIndex(string $projectName, string $description): string
    {
        return <<<PHP
<?php
require_once 'includes/header.php';
?>

<main>
    <section class="hero">
        <h2>Welcome to <?= htmlspecialchars('{$projectName}') ?></h2>
        <p><?= htmlspecialchars('{$description}') ?></p>
    </section>
</main>

<?php require_once 'includes/footer.php'; ?>
PHP;
    }

    private static function phpHeader(string $projectName): string
    {
        return <<<PHP
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= \$pageTitle ?? '{$projectName}' ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <nav>
            <h1>{$projectName}</h1>
        </nav>
    </header>
PHP;
    }

    private static function phpFooter(string $projectName): string
    {
        return <<<PHP
    <footer>
        <p>&copy; <?= date('Y') ?> {$projectName}. All rights reserved.</p>
    </footer>
    
    <script src="js/main.js"></script>
</body>
</html>
PHP;
    }

    // =========================================================================
    // REACT TEMPLATES
    // =========================================================================

    private static function reactPackage(string $projectName): string
    {
        return <<<JSON
{
  "name": "{$projectName}",
  "private": true,
  "version": "0.1.0",
  "type": "module",
  "scripts": {
    "dev": "vite",
    "build": "vite build",
    "preview": "vite preview"
  },
  "dependencies": {
    "react": "^18.2.0",
    "react-dom": "^18.2.0"
  },
  "devDependencies": {
    "@vitejs/plugin-react": "^4.2.0",
    "vite": "^5.0.0"
  }
}
JSON;
    }

    private static function reactViteConfig(): string
    {
        return <<<JS
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  server: {
    host: '0.0.0.0',
    port: 1800
  }
})
JS;
    }

    private static function reactIndexHtml(string $projectName): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{$projectName}</title>
  </head>
  <body>
    <div id="root"></div>
    <script type="module" src="/src/main.jsx"></script>
  </body>
</html>
HTML;
    }

    private static function reactMain(): string
    {
        return <<<JSX
import React from 'react'
import ReactDOM from 'react-dom/client'
import App from './App.jsx'
import './index.css'

ReactDOM.createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <App />
  </React.StrictMode>,
)
JSX;
    }

    private static function reactApp(string $projectName, string $description): string
    {
        return <<<JSX
import { useState } from 'react'
import './App.css'

function App() {
  const [count, setCount] = useState(0)

  return (
    <div className="app">
      <header className="app-header">
        <h1>{$projectName}</h1>
        <p>{$description}</p>
      </header>
      
      <main>
        <div className="card">
          <button onClick={() => setCount((count) => count + 1)}>
            Count is {count}
          </button>
          <p>Edit <code>src/App.jsx</code> and save to test HMR</p>
        </div>
      </main>
    </div>
  )
}

export default App
JSX;
    }

    private static function reactAppCss(): string
    {
        return <<<CSS
.app {
  text-align: center;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

.app-header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  padding: 2rem;
  color: white;
}

.app-header h1 {
  margin: 0 0 0.5rem 0;
  font-size: 2.5rem;
}

main {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem;
}

.card {
  padding: 2rem;
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.card button {
  background: #667eea;
  color: white;
  border: none;
  padding: 0.75rem 1.5rem;
  border-radius: 4px;
  font-size: 1rem;
  cursor: pointer;
  transition: background 0.2s;
}

.card button:hover {
  background: #764ba2;
}
CSS;
    }

    private static function reactIndexCss(): string
    {
        return <<<CSS
:root {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
  line-height: 1.6;
  color: #333;
  background-color: #f5f5f5;
}

*, *::before, *::after {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
}

body {
  min-width: 320px;
  min-height: 100vh;
}
CSS;
    }

    // =========================================================================
    // VUE TEMPLATES
    // =========================================================================

    private static function vuePackage(string $projectName): string
    {
        return <<<JSON
{
  "name": "{$projectName}",
  "private": true,
  "version": "0.1.0",
  "type": "module",
  "scripts": {
    "dev": "vite",
    "build": "vite build",
    "preview": "vite preview"
  },
  "dependencies": {
    "vue": "^3.4.0"
  },
  "devDependencies": {
    "@vitejs/plugin-vue": "^5.0.0",
    "vite": "^5.0.0"
  }
}
JSON;
    }

    private static function vueViteConfig(): string
    {
        return <<<JS
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],
  server: {
    host: '0.0.0.0',
    port: 1800
  }
})
JS;
    }

    private static function vueIndexHtml(string $projectName): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{$projectName}</title>
  </head>
  <body>
    <div id="app"></div>
    <script type="module" src="/src/main.js"></script>
  </body>
</html>
HTML;
    }

    private static function vueMain(): string
    {
        return <<<JS
import { createApp } from 'vue'
import './style.css'
import App from './App.vue'

createApp(App).mount('#app')
JS;
    }

    private static function vueApp(string $projectName, string $description): string
    {
        return <<<VUE
<script setup>
import { ref } from 'vue'

const count = ref(0)
</script>

<template>
  <div class="app">
    <header class="app-header">
      <h1>{$projectName}</h1>
      <p>{$description}</p>
    </header>
    
    <main>
      <div class="card">
        <button @click="count++">Count is {{ count }}</button>
        <p>Edit <code>src/App.vue</code> to test HMR</p>
      </div>
    </main>
  </div>
</template>

<style scoped>
.app {
  text-align: center;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

.app-header {
  background: linear-gradient(135deg, #42b883 0%, #35495e 100%);
  padding: 2rem;
  color: white;
}

.app-header h1 {
  margin: 0 0 0.5rem 0;
  font-size: 2.5rem;
}

main {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem;
}

.card {
  padding: 2rem;
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.card button {
  background: #42b883;
  color: white;
  border: none;
  padding: 0.75rem 1.5rem;
  border-radius: 4px;
  font-size: 1rem;
  cursor: pointer;
  transition: background 0.2s;
}

.card button:hover {
  background: #35495e;
}
</style>
VUE;
    }

    private static function vueStyleCss(): string
    {
        return <<<CSS
:root {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
  line-height: 1.6;
  color: #333;
  background-color: #f5f5f5;
}

*, *::before, *::after {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
}

body {
  min-width: 320px;
  min-height: 100vh;
}
CSS;
    }

    // =========================================================================
    // NODE.JS TEMPLATES
    // =========================================================================

    private static function nodePackage(string $projectName, string $description): string
    {
        return <<<JSON
{
  "name": "{$projectName}",
  "version": "1.0.0",
  "description": "{$description}",
  "main": "index.js",
  "scripts": {
    "start": "node index.js",
    "dev": "node --watch index.js"
  },
  "dependencies": {
    "express": "^4.18.2",
    "cors": "^2.8.5",
    "dotenv": "^16.3.1"
  }
}
JSON;
    }

    private static function nodeIndex(string $projectName, string $description): string
    {
        return <<<JS
const express = require('express');
const cors = require('cors');
require('dotenv').config();

const apiRoutes = require('./routes/api');

const app = express();
const PORT = process.env.PORT || 1800;

// Middleware
app.use(cors());
app.use(express.json());

// Routes
app.use('/api', apiRoutes);

// Health check
app.get('/', (req, res) => {
  res.json({ 
    name: '{$projectName}',
    status: 'running',
    message: '{$description}'
  });
});

// Start server
app.listen(PORT, '0.0.0.0', () => {
  console.log(`{$projectName} running on http://0.0.0.0:\${PORT}`);
});
JS;
    }

    private static function nodeRoutes(): string
    {
        return <<<JS
const express = require('express');
const router = express.Router();

// GET /api/items
router.get('/items', (req, res) => {
  res.json([
    { id: 1, name: 'Item 1' },
    { id: 2, name: 'Item 2' },
    { id: 3, name: 'Item 3' }
  ]);
});

// GET /api/items/:id
router.get('/items/:id', (req, res) => {
  const { id } = req.params;
  res.json({ id: parseInt(id), name: `Item \${id}` });
});

// POST /api/items
router.post('/items', (req, res) => {
  const { name } = req.body;
  res.status(201).json({ id: Date.now(), name });
});

module.exports = router;
JS;
    }

    private static function nodeEnv(): string
    {
        return <<<ENV
PORT=1800
NODE_ENV=development
ENV;
    }

    // =========================================================================
    // PYTHON TEMPLATES
    // =========================================================================

    private static function pythonApp(string $projectName, string $description): string
    {
        return <<<PYTHON
from flask import Flask, render_template, jsonify

app = Flask(__name__)

@app.route('/')
def index():
    return render_template('index.html', 
                          project_name='{$projectName}',
                          description='{$description}')

@app.route('/api/health')
def health():
    return jsonify({
        'status': 'healthy',
        'name': '{$projectName}'
    })

@app.route('/api/items')
def get_items():
    return jsonify([
        {'id': 1, 'name': 'Item 1'},
        {'id': 2, 'name': 'Item 2'},
        {'id': 3, 'name': 'Item 3'}
    ])

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=True)
PYTHON;
    }

    private static function pythonRequirements(): string
    {
        return <<<TXT
flask>=2.0.0
python-dotenv>=1.0.0
TXT;
    }

    private static function pythonTemplate(): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ project_name }}</title>
    <link rel="stylesheet" href="{{ url_for('static', filename='style.css') }}">
</head>
<body>
    <header>
        <nav><h1>{{ project_name }}</h1></nav>
    </header>
    <main>
        <section class="hero">
            <h2>Welcome to {{ project_name }}</h2>
            <p>{{ description }}</p>
        </section>
    </main>
    <footer>
        <p>&copy; 2024 {{ project_name }}</p>
    </footer>
</body>
</html>
HTML;
    }

    // =========================================================================
    // TAILWIND TEMPLATES
    // =========================================================================

    private static function tailwindPackage(string $projectName): string
    {
        return <<<JSON
{
  "name": "{$projectName}",
  "version": "1.0.0",
  "scripts": {
    "build": "npx tailwindcss -i ./src/input.css -o ./dist/output.css",
    "watch": "npx tailwindcss -i ./src/input.css -o ./dist/output.css --watch"
  },
  "devDependencies": {
    "tailwindcss": "^3.4.0",
    "autoprefixer": "^10.4.16",
    "postcss": "^8.4.32"
  }
}
JSON;
    }

    private static function tailwindConfig(): string
    {
        return <<<JS
/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ["./src/**/*.{html,js}"],
  theme: {
    extend: {},
  },
  plugins: [],
}
JS;
    }

    private static function tailwindPostcss(): string
    {
        return <<<JS
module.exports = {
  plugins: {
    tailwindcss: {},
    autoprefixer: {},
  }
}
JS;
    }

    private static function tailwindInputCss(): string
    {
        return <<<CSS
@tailwind base;
@tailwind components;
@tailwind utilities;
CSS;
    }

    private static function tailwindIndex(string $projectName, string $description): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$projectName}</title>
    <link href="../dist/output.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen">
    <header class="bg-gradient-to-r from-indigo-500 to-purple-600 text-white p-6">
        <nav class="max-w-6xl mx-auto">
            <h1 class="text-2xl font-bold">{$projectName}</h1>
        </nav>
    </header>
    
    <main class="max-w-6xl mx-auto p-8">
        <section class="bg-white rounded-lg shadow-lg p-8 text-center">
            <h2 class="text-4xl font-bold text-indigo-600 mb-4">Welcome to {$projectName}</h2>
            <p class="text-gray-600 text-lg">{$description}</p>
            <button class="mt-6 bg-indigo-500 hover:bg-indigo-600 text-white px-6 py-3 rounded-lg transition">
                Get Started
            </button>
        </section>
    </main>
    
    <footer class="bg-gray-800 text-white text-center p-6 mt-8">
        <p>&copy; 2024 {$projectName}</p>
    </footer>
</body>
</html>
HTML;
    }

    /**
     * Get HTML document wrapper for markdown-to-HTML conversion
     */
    public static function getDocumentHtml(string $title, string $body): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            line-height: 1.6;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            color: #333;
        }
        h1, h2, h3 { color: #2c3e50; margin-top: 1.5em; }
        h1 { border-bottom: 2px solid #3498db; padding-bottom: 0.3em; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
        pre { background: #f4f4f4; padding: 15px; border-radius: 5px; overflow-x: auto; }
        pre code { background: none; padding: 0; }
        a { color: #3498db; text-decoration: none; }
        a:hover { text-decoration: underline; }
        ul, ol { padding-left: 2em; }
        li { margin: 0.5em 0; }
        hr { border: none; border-top: 1px solid #ddd; margin: 2em 0; }
        table { border-collapse: collapse; width: 100%; margin: 1em 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f4f4f4; }
        blockquote { border-left: 4px solid #3498db; margin: 1em 0; padding-left: 1em; color: #666; }
        @media print {
            body { max-width: none; padding: 0; }
        }
    </style>
</head>
<body>
{$body}
</body>
</html>
HTML;
    }
}
