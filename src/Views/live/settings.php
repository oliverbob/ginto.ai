<?php
/**
 * Ginto AI - Live Settings Panel
 * 
 * Admin-only configuration page for managing environment settings
 * after initial installation. Provides a user-friendly interface
 * for API keys, LLM providers, rate limits, and other settings.
 */
$htmlDark = (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark') ? ' class="dark"' : '';
?>
<!DOCTYPE html>
<html lang="en"<?php echo $htmlDark; ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Ginto AI - Live Settings') ?></title>
    <link rel="stylesheet" href="/assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/tailwind.css">
    <link rel="stylesheet" href="/assets/css/dark-fallback.css">
    <script>
        (function () {
            try {
                var saved = localStorage.getItem('theme') || (document.cookie.match(/(?:^|; )theme=(dark|light)(?:;|$)/) || [])[1];
                if (saved === 'dark') document.documentElement.classList.add('dark');
                else if (saved === 'light') document.documentElement.classList.remove('dark');
            } catch (e) {}
        })();
    </script>
    <style>
        /* Section cards */
        .section-card {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            border: 1px solid #e5e7eb;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .dark .section-card {
            background: #1f2937;
            border-color: #374151;
        }
        /* Input fields */
        .input-field {
            width: 100%;
            padding: 0.625rem 0.75rem;
            border-radius: 0.5rem;
            border: 1px solid #d1d5db;
            background: #f9fafb;
            color: #111827;
            font-size: 0.875rem;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        /* Select dropdowns need extra right padding for chevron */
        select.input-field {
            padding-right: 2.5rem;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            cursor: pointer;
            transition: none !important;
            background-color: #f9fafb !important;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e") !important;
            background-position: right 0.75rem center !important;
            background-repeat: no-repeat !important;
            background-size: 1.25rem 1.25rem !important;
        }
        .dark select.input-field {
            transition: none !important;
            background-color: #374151 !important;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%239ca3af' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e") !important;
            background-position: right 0.75rem center !important;
            background-repeat: no-repeat !important;
            background-size: 1.25rem 1.25rem !important;
        }
        .input-field::placeholder {
            color: #9ca3af;
        }
        .input-field:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
            background: white;
        }
        .dark .input-field {
            background: #374151;
            border-color: #4b5563;
            color: white;
        }
        .dark .input-field::placeholder {
            color: #6b7280;
        }
        .dark .input-field:focus {
            background: #1f2937;
            border-color: #3b82f6;
        }
        /* Labels */
        .label-text {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.375rem;
        }
        .dark .label-text {
            color: #d1d5db;
        }
        /* Help text */
        .help-text {
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }
        .dark .help-text {
            color: #9ca3af;
        }
        /* Tabs - plain text and icons */
        .tab-btn {
            padding: 0.5rem 0;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.15s;
            background: transparent;
            color: #6b7280;
            border: none;
        }
        .tab-btn:hover {
            color: #374151;
        }
        .tab-btn.active {
            color: #2563eb;
            background: transparent;
        }
        .dark .tab-btn {
            background: transparent;
            color: #9ca3af;
        }
        .dark .tab-btn:hover {
            color: #d1d5db;
        }
        .dark .tab-btn.active {
            color: #60a5fa;
            background: transparent;
        }
    </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900 min-h-screen">
    <!-- Header -->
    <header class="sticky top-0 z-50 bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800">
        <div class="flex items-center justify-between px-4 h-14">
            <div class="flex items-center gap-3">
                <a href="/chat" class="admin-btn flex items-center gap-2 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-white text-sm transition-colors">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back to Chat</span>
                </a>
                <span class="text-gray-300 dark:text-gray-600">|</span>
                <h1 class="text-base font-semibold text-gray-900 dark:text-white">
                    <i class="fas fa-cog mr-1.5 text-blue-600"></i>Live Settings
                </h1>
            </div>
            
            <div class="flex items-center gap-2">
                <a href="https://github.com/oliverbob/ginto.ai" target="_blank" 
                   class="admin-btn flex items-center gap-2 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-white transition-colors text-sm"
                   title="Star us on GitHub">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg>
                    <span>Star us</span>
                </a>
                <button id="theme-toggle" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-white transition-colors" title="Toggle theme">
                    <!-- Sun icon (shown in dark mode) -->
                    <svg class="w-5 h-5 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    <!-- Moon icon (shown in light mode) -->
                    <svg class="w-5 h-5 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                    </svg>
                </button>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <?php if (!empty($success)): ?>
        <div class="mb-6 p-4 bg-green-100 dark:bg-green-900/30 border border-green-300 dark:border-green-700 rounded-lg">
            <div class="flex items-center gap-2 text-green-800 dark:text-green-300">
                <i class="fas fa-check-circle"></i>
                <span><?= htmlspecialchars($success) ?></span>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
        <div class="mb-6 p-4 bg-red-100 dark:bg-red-900/30 border border-red-300 dark:border-red-700 rounded-lg">
            <div class="flex items-center gap-2 text-red-800 dark:text-red-300">
                <i class="fas fa-exclamation-circle"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        </div>
        <?php endif; ?>

        <!-- Tab Navigation -->
        <div class="flex flex-wrap items-center gap-1 mb-6">
            <button class="tab-btn active" data-tab="users">
                <i class="fas fa-users mr-1.5"></i>Users
            </button>
            <i class="fas fa-chevron-right text-gray-400 dark:text-gray-500 text-xs mx-1"></i>
            <button class="tab-btn" data-tab="providers">
                <i class="fas fa-robot mr-1.5"></i>LLM Providers
            </button>
            <i class="fas fa-chevron-right text-gray-400 dark:text-gray-500 text-xs mx-1"></i>
            <button class="tab-btn" data-tab="api-keys">
                <i class="fas fa-key mr-1.5"></i>API Keys
            </button>
            <i class="fas fa-chevron-right text-gray-400 dark:text-gray-500 text-xs mx-1"></i>
            <button class="tab-btn" data-tab="local-llm">
                <i class="fas fa-server mr-1.5"></i>Local LLM
            </button>
            <i class="fas fa-chevron-right text-gray-400 dark:text-gray-500 text-xs mx-1"></i>
            <button class="tab-btn" data-tab="rate-limits">
                <i class="fas fa-tachometer-alt mr-1.5"></i>Rate Limits
            </button>
            <i class="fas fa-chevron-right text-gray-400 dark:text-gray-500 text-xs mx-1"></i>
            <button class="tab-btn" data-tab="ecommerce">
                <i class="fas fa-shopping-cart mr-1.5"></i>Ecommerce
            </button>
            <i class="fas fa-chevron-right text-gray-400 dark:text-gray-500 text-xs mx-1"></i>
            <button class="tab-btn" data-tab="datacenter">
                <i class="fas fa-database mr-1.5"></i>Datacenter
            </button>
            <i class="fas fa-chevron-right text-gray-400 dark:text-gray-500 text-xs mx-1"></i>
            <button class="tab-btn" data-tab="site">
                <i class="fas fa-globe mr-1.5"></i>Site Config
            </button>
        </div>

        <form id="settings-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

            <!-- Users Tab -->
            <div id="tab-users" class="tab-content">
                <!-- Admin Account Section -->
                <!-- NOVITA_FIELD_PRESENT: added for debugging -->
                <div class="section-card">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        <i class="fas fa-user-shield mr-2 text-purple-500"></i>Admin Account
                    </h2>
                    <p class="help-text mb-4">Set up the administrator account for managing Ginto.AI</p>
                    
                    <div class="grid md:grid-cols-2 gap-6">
                        <div>
                            <label class="label-text">Admin Username</label>
                            <input type="text" name="admin_username" class="input-field" 
                                   placeholder="admin" value="<?= htmlspecialchars($envValues['ADMIN_USERNAME'] ?? 'admin') ?>">
                        </div>
                        <div>
                            <label class="label-text">Admin Password</label>
                            <div class="relative">
                                <input type="password" name="admin_password" id="admin_password" class="input-field pr-10" 
                                       placeholder="••••••••" value="<?= htmlspecialchars($envValues['ADMIN_PASSWORD'] ?? '') ?>">
                                <button type="button" onclick="togglePassword('admin_password')" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                    <i class="fas fa-eye" id="admin_password_icon"></i>
                                </button>
                            </div>
                            <p class="help-text">Leave empty to keep existing password</p>
                        </div>
                        <div>
                            <label class="label-text">Admin Email</label>
                            <input type="email" name="admin_email" class="input-field" 
                                   placeholder="admin@example.com" value="<?= htmlspecialchars($envValues['ADMIN_EMAIL'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <!-- Default User Section -->
                <div class="section-card">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        <i class="fas fa-user mr-2 text-blue-500"></i>Default User
                    </h2>
                    <p class="help-text mb-4">Create a default user account for quick access</p>
                    
                    <div class="grid md:grid-cols-2 gap-6">
                        <div>
                            <label class="label-text">Username</label>
                            <input type="text" name="default_username" class="input-field" 
                                   placeholder="user" value="<?= htmlspecialchars($envValues['DEFAULT_USERNAME'] ?? '') ?>">
                        </div>
                        <div>
                            <label class="label-text">Password</label>
                            <div class="relative">
                                <input type="password" name="default_password" id="default_password" class="input-field pr-10" 
                                       placeholder="••••••••" value="<?= htmlspecialchars($envValues['DEFAULT_PASSWORD'] ?? '') ?>">
                                <button type="button" onclick="togglePassword('default_password')" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                    <i class="fas fa-eye" id="default_password_icon"></i>
                                </button>
                            </div>
                            <p class="help-text">Leave empty to keep existing password</p>
                        </div>
                        <div>
                            <label class="label-text">Email</label>
                            <input type="email" name="default_email" class="input-field" 
                                   placeholder="user@example.com" value="<?= htmlspecialchars($envValues['DEFAULT_EMAIL'] ?? '') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- LLM Providers Tab -->
            <div id="tab-providers" class="tab-content hidden">
                <div class="section-card">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        <i class="fas fa-robot mr-2 text-blue-500"></i>LLM Provider Configuration
                    </h2>
                    
                    <div class="grid md:grid-cols-2 gap-6">
                        <div>
                            <label class="label-text">Default Provider</label>
                            <select name="default_provider" class="input-field">
                                <option value="cerebras" <?= ($envValues['DEFAULT_PROVIDER'] ?? '') === 'cerebras' ? 'selected' : '' ?>>Cerebras (Fast, Free Tier)</option>
                                <option value="groq" <?= ($envValues['DEFAULT_PROVIDER'] ?? '') === 'groq' ? 'selected' : '' ?>>GROQ (Fast, Free Tier)</option>
                                <option value="openrouter" <?= ($envValues['DEFAULT_PROVIDER'] ?? '') === 'openrouter' ? 'selected' : '' ?>>OpenRouter (Multiple Models)</option>
                                <option value="local" <?= ($envValues['DEFAULT_PROVIDER'] ?? '') === 'local' ? 'selected' : '' ?>>Local LLM (Self-hosted)</option>
                            </select>
                            <p class="help-text">Primary LLM provider for chat</p>
                        </div>

                        <div>
                            <label class="label-text">Default Model</label>
                            <select name="llm_model" class="input-field">
                                <optgroup label="GROQ">
                                    <option value="llama-3.3-70b-versatile" <?= ($envValues['LLM_MODEL'] ?? '') === 'llama-3.3-70b-versatile' ? 'selected' : '' ?>>llama-3.3-70b-versatile</option>
                                    <option value="llama-3.3-70b-specdec" <?= ($envValues['LLM_MODEL'] ?? '') === 'llama-3.3-70b-specdec' ? 'selected' : '' ?>>llama-3.3-70b-specdec</option>
                                    <option value="llama-3.2-90b-vision-preview" <?= ($envValues['LLM_MODEL'] ?? '') === 'llama-3.2-90b-vision-preview' ? 'selected' : '' ?>>llama-3.2-90b-vision-preview</option>
                                    <option value="llama-3.2-11b-vision-preview" <?= ($envValues['LLM_MODEL'] ?? '') === 'llama-3.2-11b-vision-preview' ? 'selected' : '' ?>>llama-3.2-11b-vision-preview</option>
                                    <option value="llama-3.1-70b-versatile" <?= ($envValues['LLM_MODEL'] ?? '') === 'llama-3.1-70b-versatile' ? 'selected' : '' ?>>llama-3.1-70b-versatile</option>
                                    <option value="llama-3.1-8b-instant" <?= ($envValues['LLM_MODEL'] ?? '') === 'llama-3.1-8b-instant' ? 'selected' : '' ?>>llama-3.1-8b-instant</option>
                                    <option value="llama3-70b-8192" <?= ($envValues['LLM_MODEL'] ?? '') === 'llama3-70b-8192' ? 'selected' : '' ?>>llama3-70b-8192</option>
                                    <option value="llama3-8b-8192" <?= ($envValues['LLM_MODEL'] ?? '') === 'llama3-8b-8192' ? 'selected' : '' ?>>llama3-8b-8192</option>
                                    <option value="mixtral-8x7b-32768" <?= ($envValues['LLM_MODEL'] ?? '') === 'mixtral-8x7b-32768' ? 'selected' : '' ?>>mixtral-8x7b-32768</option>
                                    <option value="gemma2-9b-it" <?= ($envValues['LLM_MODEL'] ?? '') === 'gemma2-9b-it' ? 'selected' : '' ?>>gemma2-9b-it</option>
                                    <option value="qwen-qwq-32b" <?= ($envValues['LLM_MODEL'] ?? '') === 'qwen-qwq-32b' ? 'selected' : '' ?>>qwen-qwq-32b</option>
                                    <option value="deepseek-r1-distill-llama-70b" <?= ($envValues['LLM_MODEL'] ?? '') === 'deepseek-r1-distill-llama-70b' ? 'selected' : '' ?>>deepseek-r1-distill-llama-70b</option>
                                    <option value="whisper-large-v3" <?= ($envValues['LLM_MODEL'] ?? '') === 'whisper-large-v3' ? 'selected' : '' ?>>whisper-large-v3</option>
                                    <option value="whisper-large-v3-turbo" <?= ($envValues['LLM_MODEL'] ?? '') === 'whisper-large-v3-turbo' ? 'selected' : '' ?>>whisper-large-v3-turbo</option>
                                </optgroup>
                                <optgroup label="Cerebras">
                                    <option value="llama3.1-8b" <?= ($envValues['LLM_MODEL'] ?? '') === 'llama3.1-8b' ? 'selected' : '' ?>>llama3.1-8b</option>
                                    <option value="llama3.1-70b" <?= ($envValues['LLM_MODEL'] ?? '') === 'llama3.1-70b' ? 'selected' : '' ?>>llama3.1-70b</option>
                                    <option value="llama-3.3-70b" <?= ($envValues['LLM_MODEL'] ?? '') === 'llama-3.3-70b' ? 'selected' : '' ?>>llama-3.3-70b</option>
                                    <option value="qwen-2.5-32b" <?= ($envValues['LLM_MODEL'] ?? '') === 'qwen-2.5-32b' ? 'selected' : '' ?>>qwen-2.5-32b</option>
                                    <option value="qwen-2.5-coder-32b" <?= ($envValues['LLM_MODEL'] ?? '') === 'qwen-2.5-coder-32b' ? 'selected' : '' ?>>qwen-2.5-coder-32b</option>
                                    <option value="deepseek-r1-distill-llama-70b" <?= ($envValues['LLM_MODEL'] ?? '') === 'deepseek-r1-distill-llama-70b' ? 'selected' : '' ?>>deepseek-r1-distill-llama-70b</option>
                                </optgroup>
                                <optgroup label="OpenRouter">
                                    <option value="anthropic/claude-3.5-sonnet" <?= ($envValues['LLM_MODEL'] ?? '') === 'anthropic/claude-3.5-sonnet' ? 'selected' : '' ?>>anthropic/claude-3.5-sonnet</option>
                                    <option value="anthropic/claude-3-opus" <?= ($envValues['LLM_MODEL'] ?? '') === 'anthropic/claude-3-opus' ? 'selected' : '' ?>>anthropic/claude-3-opus</option>
                                    <option value="openai/gpt-4o" <?= ($envValues['LLM_MODEL'] ?? '') === 'openai/gpt-4o' ? 'selected' : '' ?>>openai/gpt-4o</option>
                                    <option value="openai/gpt-4-turbo" <?= ($envValues['LLM_MODEL'] ?? '') === 'openai/gpt-4-turbo' ? 'selected' : '' ?>>openai/gpt-4-turbo</option>
                                    <option value="google/gemini-2.0-flash-exp:free" <?= ($envValues['LLM_MODEL'] ?? '') === 'google/gemini-2.0-flash-exp:free' ? 'selected' : '' ?>>google/gemini-2.0-flash-exp:free</option>
                                    <option value="google/gemini-pro-1.5" <?= ($envValues['LLM_MODEL'] ?? '') === 'google/gemini-pro-1.5' ? 'selected' : '' ?>>google/gemini-pro-1.5</option>
                                    <option value="meta-llama/llama-3.3-70b-instruct" <?= ($envValues['LLM_MODEL'] ?? '') === 'meta-llama/llama-3.3-70b-instruct' ? 'selected' : '' ?>>meta-llama/llama-3.3-70b-instruct</option>
                                    <option value="deepseek/deepseek-r1" <?= ($envValues['LLM_MODEL'] ?? '') === 'deepseek/deepseek-r1' ? 'selected' : '' ?>>deepseek/deepseek-r1</option>
                                </optgroup>
                            </select>
                            <p class="help-text">Default model for the selected provider</p>
                        </div>
                    </div>

                    <!-- Hidden field for LLM_PROVIDER (synced with default_provider) -->
                    <input type="hidden" name="llm_provider" id="llm_provider_hidden" value="<?= htmlspecialchars($envValues['LLM_PROVIDER'] ?? 'groq') ?>">
                </div>
            </div>

            <!-- API Keys Tab -->
            <div id="tab-api-keys" class="tab-content hidden">
                <div class="section-card">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        <i class="fas fa-key mr-2 text-yellow-500"></i>GROQ API
                    </h2>
                    
                    <div class="grid md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <label class="label-text">GROQ API Key</label>
                            <div class="relative">
                                <input type="password" id="groq_api_key" name="groq_api_key" class="input-field pr-10" 
                                       value="<?= htmlspecialchars($envValues['GROQ_API_KEY'] ?? '') ?>"
                                       placeholder="gsk_...">
                                <button type="button" onclick="togglePassword('groq_api_key')" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                    <i id="groq_api_key_icon" class="fas fa-eye"></i>
                                </button>
                            </div>
                            <p class="help-text">Get your API key from <a href="https://console.groq.com" target="_blank" class="text-blue-500 hover:underline">console.groq.com</a></p>
                        </div>

                        <div>
                            <label class="label-text">TTS Model</label>
                            <input type="text" name="groq_tts_model" class="input-field" 
                                   value="<?= htmlspecialchars($envValues['GROQ_TTS_MODEL'] ?? 'playai-tts') ?>"
                                   placeholder="playai-tts">
                        </div>

                        <div>
                            <label class="label-text">STT Model</label>
                            <input type="text" name="groq_stt_model" class="input-field" 
                                   value="<?= htmlspecialchars($envValues['GROQ_STT_MODEL'] ?? 'whisper-large-v3') ?>"
                                   placeholder="whisper-large-v3">
                        </div>
                    </div>
                </div>

                <div class="section-card">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        <i class="fas fa-brain mr-2 text-purple-500"></i>Cerebras API
                    </h2>
                    
                    <div class="grid md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <label class="label-text">Cerebras API Key</label>
                            <div class="relative">
                                <input type="password" id="cerebras_api_key" name="cerebras_api_key" class="input-field pr-10" 
                                       value="<?= htmlspecialchars($envValues['CEREBRAS_API_KEY'] ?? '') ?>"
                                       placeholder="csk-...">
                                <button type="button" onclick="togglePassword('cerebras_api_key')" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                    <i id="cerebras_api_key_icon" class="fas fa-eye"></i>
                                </button>
                            </div>
                            <p class="help-text">Get your API key from <a href="https://cloud.cerebras.ai" target="_blank" class="text-blue-500 hover:underline">cloud.cerebras.ai</a></p>
                        </div>

                        <div class="md:col-span-2">
                            <label class="label-text">Cerebras API URL</label>
                            <input type="text" name="cerebras_api_url" class="input-field" 
                                   value="<?= htmlspecialchars($envValues['CEREBRAS_API_URL'] ?? 'https://api.cerebras.ai/v1') ?>"
                                   placeholder="https://api.cerebras.ai/v1">
                        </div>
                    </div>
                </div>

                <div class="section-card">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        <i class="fas fa-route mr-2 text-green-500"></i>OpenRouter API
                    </h2>
                    
                    <div class="grid md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <label class="label-text">OpenRouter API Key</label>
                            <div class="relative">
                                <input type="password" id="openrouter_api_key" name="openrouter_api_key" class="input-field pr-10" 
                                       value="<?= htmlspecialchars($envValues['OPENROUTER_API_KEY'] ?? '') ?>"
                                       placeholder="sk-or-...">
                                <button type="button" onclick="togglePassword('openrouter_api_key')" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                    <i id="openrouter_api_key_icon" class="fas fa-eye"></i>
                                </button>
                            </div>
                            <p class="help-text">Get your API key from <a href="https://openrouter.ai/keys" target="_blank" class="text-blue-500 hover:underline">openrouter.ai/keys</a></p>
                        </div>
                    </div>
                </div>

                <div class="section-card">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        <i class="fas fa-stream mr-2 text-pink-500"></i>Novita API
                    </h2>

                    <div class="grid md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <label class="label-text">Novita API Key</label>
                            <div class="relative">
                                <input type="password" id="novita_api_key" name="novita_api_key" class="input-field pr-10"
                                       value="<?= htmlspecialchars($envValues['NOVITA_API_KEY'] ?? '') ?>"
                                       placeholder="novita_...">
                                <button type="button" onclick="togglePassword('novita_api_key')" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                    <i id="novita_api_key_icon" class="fas fa-eye"></i>
                                </button>
                            </div>
                            <p class="help-text">Optional: Novita API key for streaming responses (if provided).</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Local LLM Tab -->
            <div id="tab-local-llm" class="tab-content hidden">
                <div class="section-card">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        <i class="fas fa-server mr-2 text-green-500"></i>Local LLM Configuration
                    </h2>
                    
                    <div class="grid md:grid-cols-2 gap-6">
                        <div>
                            <label class="label-text">Local LLM URL</label>
                            <input type="text" name="local_llm_url" class="input-field" 
                                   value="<?= htmlspecialchars($envValues['LOCAL_LLM_URL'] ?? 'http://127.0.0.1:8034/v1') ?>"
                                   placeholder="http://127.0.0.1:8034/v1">
                            <p class="help-text">URL for local llama.cpp or Ollama server</p>
                        </div>

                        <div>
                            <label class="label-text">Local LLM Model</label>
                            <input type="text" name="local_llm_model" class="input-field" 
                                   value="<?= htmlspecialchars($envValues['LOCAL_LLM_MODEL'] ?? 'local') ?>"
                                   placeholder="local">
                        </div>

                        <div>
                            <label class="label-text">Vision Model URL</label>
                            <input type="text" name="vision_model_url" class="input-field" 
                                   value="<?= htmlspecialchars($envValues['VISION_MODEL_URL'] ?? 'http://127.0.0.1:8033/v1') ?>"
                                   placeholder="http://127.0.0.1:8033/v1">
                            <p class="help-text">URL for vision/multimodal model</p>
                        </div>

                        <div>
                            <label class="label-text">Vision Model Name</label>
                            <input type="text" name="vision_model_name" class="input-field" 
                                   value="<?= htmlspecialchars($envValues['VISION_MODEL_NAME'] ?? 'SmolVLM2') ?>"
                                   placeholder="SmolVLM2">
                        </div>
                    </div>
                </div>

                <div class="section-card">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        <i class="fab fa-hubspot mr-2 text-orange-500"></i>HuggingFace Models
                    </h2>
                    
                    <div class="grid md:grid-cols-2 gap-6">
                        <div>
                            <label class="label-text">Reasoning Model</label>
                            <input type="text" name="reasoning_hf_model" class="input-field" 
                                   value="<?= htmlspecialchars($envValues['REASONING_HF_MODEL'] ?? 'lm-kit/qwen-3-0.6b-instruct-gguf') ?>"
                                   placeholder="lm-kit/qwen-3-0.6b-instruct-gguf">
                            <p class="help-text">HuggingFace model for reasoning tasks</p>
                        </div>

                        <div>
                            <label class="label-text">Vision Model</label>
                            <input type="text" name="vision_hf_model" class="input-field" 
                                   value="<?= htmlspecialchars($envValues['VISION_HF_MODEL'] ?? 'ggml-org/SmolVLM2-500M-Video-Instruct-GGUF') ?>"
                                   placeholder="ggml-org/SmolVLM2-500M-Video-Instruct-GGUF">
                        </div>
                    </div>
                </div>

                <div class="section-card">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        <i class="fas fa-terminal mr-2 text-cyan-500"></i>MCP Configuration
                    </h2>
                    
                    <div class="grid md:grid-cols-2 gap-6">
                        <div>
                            <label class="label-text">MCP Server URL</label>
                            <input type="text" name="mcp_server_url" class="input-field" 
                                   value="<?= htmlspecialchars($envValues['MCP_SERVER_URL'] ?? 'http://127.0.0.1:9010') ?>"
                                   placeholder="http://127.0.0.1:9010">
                        </div>

                        <div>
                            <label class="label-text">Python3 Path</label>
                            <input type="text" name="python3_path" class="input-field" 
                                   value="<?= htmlspecialchars($envValues['PYTHON3_PATH'] ?? '') ?>"
                                   placeholder="/usr/bin/python3">
                            <p class="help-text">Leave blank for auto-detect</p>
                        </div>

                        <div>
                            <label class="label-text">Use Python STT</label>
                            <select name="use_py_stt" class="input-field">
                                <option value="1" <?= ($envValues['USE_PY_STT'] ?? '1') === '1' ? 'selected' : '' ?>>Yes</option>
                                <option value="0" <?= ($envValues['USE_PY_STT'] ?? '1') === '0' ? 'selected' : '' ?>>No</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rate Limits Tab -->
            <div id="tab-rate-limits" class="tab-content hidden">
                <div class="section-card">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        <i class="fas fa-percentage mr-2 text-red-500"></i>API Rate Limits
                    </h2>
                    
                    <div class="grid md:grid-cols-3 gap-6">
                        <div>
                            <label class="label-text">Admin Rate %</label>
                            <input type="number" name="rate_limit_admin_percent" class="input-field" 
                                   value="<?= htmlspecialchars($envValues['RATE_LIMIT_ADMIN_PERCENT'] ?? '50') ?>"
                                   min="1" max="100">
                            <p class="help-text">% of rate limit for admins</p>
                        </div>

                        <div>
                            <label class="label-text">User Rate %</label>
                            <input type="number" name="rate_limit_user_percent" class="input-field" 
                                   value="<?= htmlspecialchars($envValues['RATE_LIMIT_USER_PERCENT'] ?? '10') ?>"
                                   min="1" max="100">
                            <p class="help-text">% of rate limit for users</p>
                        </div>

                        <div>
                            <label class="label-text">Visitor Rate %</label>
                            <input type="number" name="rate_limit_visitor_percent" class="input-field" 
                                   value="<?= htmlspecialchars($envValues['RATE_LIMIT_VISITOR_PERCENT'] ?? '5') ?>"
                                   min="1" max="100">
                            <p class="help-text">% of rate limit for visitors</p>
                        </div>

                        <div>
                            <label class="label-text">Fallback Provider</label>
                            <input type="text" name="rate_limit_fallback_provider" class="input-field" 
                                   value="<?= htmlspecialchars($envValues['RATE_LIMIT_FALLBACK_PROVIDER'] ?? 'cerebras') ?>"
                                   placeholder="cerebras">
                        </div>

                        <div>
                            <label class="label-text">Fallback Threshold %</label>
                            <input type="number" name="rate_limit_fallback_threshold" class="input-field" 
                                   value="<?= htmlspecialchars($envValues['RATE_LIMIT_FALLBACK_THRESHOLD'] ?? '80') ?>"
                                   min="1" max="100">
                        </div>

                        <div>
                            <label class="label-text">Expected Users</label>
                            <input type="number" name="expected_users" class="input-field" 
                                   value="<?= htmlspecialchars($envValues['EXPECTED_USERS'] ?? '200') ?>"
                                   min="1">
                        </div>
                    </div>
                </div>

                <div class="section-card">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        <i class="fas fa-coins mr-2 text-yellow-500"></i>Token Limits
                    </h2>
                    
                    <div class="grid md:grid-cols-2 gap-6">
                        <div>
                            <label class="label-text">Base Max Tokens</label>
                            <input type="number" name="max_tokens_base" class="input-field" 
                                   value="<?= htmlspecialchars($envValues['MAX_TOKENS_BASE'] ?? '32768') ?>"
                                   min="1000">
                        </div>

                        <div>
                            <label class="label-text">Admin Token %</label>
                            <input type="number" name="max_tokens_admin_percent" class="input-field" 
                                   value="<?= htmlspecialchars($envValues['MAX_TOKENS_ADMIN_PERCENT'] ?? '100') ?>"
                                   min="1" max="100">
                        </div>

                        <div>
                            <label class="label-text">User Token %</label>
                            <input type="number" name="max_tokens_user_percent" class="input-field" 
                                   value="<?= htmlspecialchars($envValues['MAX_TOKENS_USER_PERCENT'] ?? '25') ?>"
                                   min="1" max="100">
                        </div>

                        <div>
                            <label class="label-text">Visitor Token %</label>
                            <input type="number" name="max_tokens_visitor_percent" class="input-field" 
                                   value="<?= htmlspecialchars($envValues['MAX_TOKENS_VISITOR_PERCENT'] ?? '10') ?>"
                                   min="1" max="100">
                        </div>
                    </div>
                </div>

                <div class="section-card">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        <i class="fas fa-volume-up mr-2 text-indigo-500"></i>TTS Limits
                    </h2>
                    
                    <div class="grid md:grid-cols-2 gap-6">
                        <div>
                            <label class="label-text">Admin Hourly TTS</label>
                            <input type="number" name="tts_limit_admin_hourly" class="input-field" 
                                   value="<?= htmlspecialchars($envValues['TTS_LIMIT_ADMIN_HOURLY'] ?? '100') ?>"
                                   min="1">
                        </div>

                        <div>
                            <label class="label-text">User Hourly TTS</label>
                            <input type="number" name="tts_limit_user_hourly" class="input-field" 
                                   value="<?= htmlspecialchars($envValues['TTS_LIMIT_USER_HOURLY'] ?? '30') ?>"
                                   min="1">
                        </div>

                        <div>
                            <label class="label-text">Visitor Session TTS</label>
                            <input type="number" name="tts_limit_visitor_session" class="input-field" 
                                   value="<?= htmlspecialchars($envValues['TTS_LIMIT_VISITOR_SESSION'] ?? '10') ?>"
                                   min="1">
                        </div>

                        <div>
                            <label class="label-text">Silent Stop %</label>
                            <input type="number" name="tts_silent_stop_percent" class="input-field" 
                                   value="<?= htmlspecialchars($envValues['TTS_SILENT_STOP_PERCENT'] ?? '90') ?>"
                                   min="1" max="100">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ecommerce Tab -->
            <div id="tab-ecommerce" class="tab-content hidden">
                <div class="section-card">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        <i class="fas fa-shopping-cart mr-2 text-green-500"></i>Ecommerce Configuration
                    </h2>
                    <p class="help-text mb-4">Add PayPal configuration. Webhook and API credentials (if present). Avoid storing secrets in public repos.</p>

                    <div class="grid md:grid-cols-2 gap-6">
                        <div>
                            <label class="label-text">PayPal Webhook ID</label>
                            <input type="text" name="paypal_webhook_id" class="input-field" value="<?= htmlspecialchars($envValues['PAYPAL_WEBHOOK_ID'] ?? '') ?>" placeholder="">
                        </div>
                        <div>
                            <label class="label-text">PayPal Client ID</label>
                            <input type="text" name="paypal_client_id" class="input-field" value="<?= htmlspecialchars($envValues['PAYPAL_CLIENT_ID'] ?? '') ?>" placeholder="">
                        </div>
                        <div>
                            <label class="label-text">PayPal Client Secret</label>
                            <div class="relative">
                                <input type="password" id="paypal_client_secret" name="paypal_client_secret" class="input-field pr-10" value="<?= htmlspecialchars($envValues['PAYPAL_CLIENT_SECRET'] ?? '') ?>" placeholder="">
                                <button type="button" onclick="togglePassword('paypal_client_secret')" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                    <i id="paypal_client_secret_icon" class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="label-text">PayPal Environment</label>
                            <select name="paypal_environment" class="input-field">
                                <option value="sandbox" <?= (isset($envValues['PAYPAL_ENVIRONMENT']) && $envValues['PAYPAL_ENVIRONMENT'] === 'sandbox') ? 'selected' : '' ?>>Sandbox</option>
                                <option value="live" <?= (isset($envValues['PAYPAL_ENVIRONMENT']) && $envValues['PAYPAL_ENVIRONMENT'] === 'live') ? 'selected' : '' ?>>Live</option>
                            </select>
                        </div>
                        <div>
                            <label class="label-text">PayPal Internal API Key</label>
                            <div class="relative">
                                <input type="password" id="paypal_internal_api_key" name="paypal_internal_api_key" class="input-field pr-10" value="<?= htmlspecialchars($envValues['PAYPAL_INTERNAL_API_KEY'] ?? '') ?>" placeholder="">
                                <button type="button" onclick="togglePassword('paypal_internal_api_key')" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                    <i id="paypal_internal_api_key_icon" class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="label-text">Sandbox Client ID</label>
                            <input type="text" name="paypal_client_id_sandbox" class="input-field" value="<?= htmlspecialchars($envValues['PAYPAL_CLIENT_ID_SANDBOX'] ?? '') ?>" placeholder="">
                        </div>
                        <div>
                            <label class="label-text">Sandbox Client Secret</label>
                            <div class="relative">
                                <input type="password" id="paypal_client_secret_sandbox" name="paypal_client_secret_sandbox" class="input-field pr-10" value="<?= htmlspecialchars($envValues['PAYPAL_CLIENT_SECRET_SANDBOX'] ?? '') ?>" placeholder="">
                                <button type="button" onclick="togglePassword('paypal_client_secret_sandbox')" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                    <i id="paypal_client_secret_sandbox_icon" class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <p class="help-text mt-4">Note: Storing secrets in the .env is convenient for local/sandbox environments; consider secure secret storage for production.</p>
                </div>
            </div>

            <!-- Datacenter Tab -->
            <div id="tab-datacenter" class="tab-content hidden">
                <div class="section-card">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        <i class="fas fa-database mr-2 text-indigo-500"></i>Datacenter / CDN Configuration
                    </h2>
                    <p class="help-text mb-4">Backblaze B2 / CDN configuration used for file uploads/serving. Avoid storing secrets in public repos.</p>

                    <div class="grid md:grid-cols-2 gap-6">
                        <div>
                            <label class="label-text">B2 Account ID</label>
                            <input type="text" name="b2_account_id" class="input-field" value="<?= htmlspecialchars($envValues['B2_ACCOUNT_ID'] ?? '') ?>" placeholder="">
                        </div>

                        <div>
                            <label class="label-text">B2 App Key</label>
                            <div class="relative">
                                <input type="password" id="b2_app_key" name="b2_app_key" class="input-field pr-10" value="<?= htmlspecialchars($envValues['B2_APP_KEY'] ?? '') ?>" placeholder="">
                                <button type="button" onclick="togglePassword('b2_app_key')" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                    <i id="b2_app_key_icon" class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="label-text">B2 Bucket ID</label>
                            <input type="text" name="b2_bucket_id" class="input-field" value="<?= htmlspecialchars($envValues['B2_BUCKET_ID'] ?? '') ?>" placeholder="">
                        </div>

                        <div>
                            <label class="label-text">B2 Bucket Name</label>
                            <input type="text" name="b2_bucket_name" class="input-field" value="<?= htmlspecialchars($envValues['B2_BUCKET_NAME'] ?? '') ?>" placeholder="">
                        </div>

                        <div class="md:col-span-2">
                            <label class="label-text">File CDN Base URL</label>
                            <input type="text" name="file_cdn_base_url" class="input-field" value="<?= htmlspecialchars($envValues['FILE_CDN_BASE_URL'] ?? '') ?>" placeholder="https://f000.backblazeb2.com/file/my-bucket">
                            <p class="help-text">Public base URL used for serving uploaded files (optional)</p>
                        </div>

                        <div>
                            <label class="label-text">Datacenter</label>
                            <select name="datacenter" class="input-field">
                                <option value="local" <?= (isset($envValues['DATACENTER']) && $envValues['DATACENTER'] === 'local') ? 'selected' : '' ?>>Local</option>
                                <option value="b2b" <?= (isset($envValues['DATACENTER']) && $envValues['DATACENTER'] === 'b2b') ? 'selected' : '' ?>>Backblaze B2</option>
                            </select>
                        </div>
                    </div>
                    <p class="help-text mt-4">Note: Storing secrets in the .env is convenient for local/sandbox environments; consider secure secret storage for production.</p>
                </div>
            </div>

            <!-- Site Config Tab -->
            <div id="tab-site" class="tab-content hidden">
                <div class="section-card">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        <i class="fas fa-globe mr-2 text-blue-500"></i>Site Configuration
                    </h2>
                    
                    <div class="grid md:grid-cols-2 gap-6">
                        <div>
                            <label class="label-text">Site Name</label>
                            <input type="text" name="site_name" class="input-field" 
                                   value="<?= htmlspecialchars($envValues['APP_NAME'] ?? 'Ginto AI') ?>"
                                   placeholder="Ginto AI">
                        </div>

                        <div>
                            <label class="label-text">Timezone</label>
                            <input type="text" name="timezone" class="input-field" 
                                   value="<?= htmlspecialchars($envValues['TIMEZONE'] ?? 'Asia/Manila') ?>"
                                   placeholder="Asia/Manila">
                        </div>

                        <div class="md:col-span-2">
                            <label class="label-text">Site Description</label>
                            <input type="text" name="site_description" class="input-field" 
                                   value="<?= htmlspecialchars($envValues['APP_DESCRIPTION'] ?? 'Agentic Chat Assistant') ?>"
                                   placeholder="Agentic Chat Assistant">
                        </div>

                        <div class="md:col-span-2">
                            <label class="label-text">Site URL</label>
                            <input type="text" name="site_url" class="input-field" 
                                   value="<?= htmlspecialchars($envValues['APP_URL'] ?? '') ?>"
                                   placeholder="https://ginto.ai">
                        </div>
                    </div>
                </div>

                <div class="section-card mt-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        <i class="fas fa-image mr-2 text-indigo-500"></i>Image Generation Settings
                    </h2>

                    <div class="grid md:grid-cols-2 gap-6">
                        <div>
                            <label class="label-text">Image Quality Profile</label>
                            <select name="imagegen_profile" class="input-field">
                                <option value="startup" <?= (($envValues['IMAGEGEN_PROFILE'] ?? 'balanced') === 'startup') ? 'selected' : '' ?>>Startup low-cost (Minimal infra usage)</option>
                                <option value="fast" <?= (($envValues['IMAGEGEN_PROFILE'] ?? 'balanced') === 'fast') ? 'selected' : '' ?>>Fast (Lower quality, quickest response)</option>
                                <option value="balanced" <?= (($envValues['IMAGEGEN_PROFILE'] ?? 'balanced') === 'balanced') ? 'selected' : '' ?>>Balanced (Default)</option>
                                <option value="quality" <?= (($envValues['IMAGEGEN_PROFILE'] ?? 'balanced') === 'quality') ? 'selected' : '' ?>>Quality (Higher detail)</option>
                                <option value="ultra" <?= (($envValues['IMAGEGEN_PROFILE'] ?? 'balanced') === 'ultra') ? 'selected' : '' ?>>Ultra (Maximum detail, slowest)</option>
                            </select>
                            <p class="help-text">Controls inference steps, output size, and guidance settings used by image generation.</p>
                        </div>

                        <div>
                            <label class="label-text">Image Compute Mode</label>
                            <select name="imagegen_compute_mode" class="input-field">
                                <option value="auto" <?= (($envValues['IMAGEGEN_COMPUTE_MODE'] ?? 'auto') === 'auto') ? 'selected' : '' ?>>Auto (Use existing SDCPU/SDCPU_TUNNEL logic)</option>
                                <option value="cpu" <?= (($envValues['IMAGEGEN_COMPUTE_MODE'] ?? 'auto') === 'cpu') ? 'selected' : '' ?>>CPU (Force local CPU endpoint)</option>
                                <option value="gpu" <?= (($envValues['IMAGEGEN_COMPUTE_MODE'] ?? 'auto') === 'gpu') ? 'selected' : '' ?>>GPU/Tunnel (Force vision.ginto.ai endpoint)</option>
                            </select>
                            <p class="help-text">Controls whether image generation prefers local CPU or GPU tunnel endpoint.</p>
                        </div>

                        <div class="md:col-span-2">
                            <div class="flex items-center justify-between gap-3 mb-2">
                                <label class="label-text !mb-0">Loaded Image Model</label>
                                <button type="button" id="refresh-imagegen-model-status" class="px-3 py-1.5 text-xs rounded border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                    Refresh
                                </button>
                            </div>
                            <div id="imagegen-model-status" class="px-3 py-2 rounded border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 text-sm text-gray-700 dark:text-gray-300">
                                Checking model status...
                            </div>
                            <p class="help-text">Shows the currently loaded model reported by the image generation service.</p>
                        </div>

                        <div>
                            <label class="label-text">Downloaded Models</label>
                            <select id="imagegen-available-models" class="input-field">
                                <option value="">(Select detected model)</option>
                            </select>
                            <p class="help-text">Detected from imagegen service model list (if supported by backend).</p>
                        </div>

                        <div>
                            <label class="label-text">Image Model (HuggingFace ID)</label>
                            <input type="text" name="imagegen_model_id" id="imagegen-model-id" class="input-field"
                                   value="<?= htmlspecialchars($envValues['IMAGEGEN_MODEL_ID'] ?? '') ?>"
                                   placeholder="e.g. stabilityai/sd-turbo">
                            <p class="help-text">Set a model repo ID. This model will be used on subsequent image generation requests.</p>
                        </div>

                        <div>
                            <label class="label-text">Inference Steps (override)</label>
                            <input type="number" name="imagegen_steps" class="input-field" min="1" max="50"
                                   value="<?= htmlspecialchars($envValues['IMAGEGEN_STEPS'] ?? '') ?>"
                                   placeholder="Blank = profile default">
                            <p class="help-text">Lower = faster and cheaper. Higher = better quality but slower.</p>
                        </div>

                        <div>
                            <label class="label-text">Guidance Scale (override)</label>
                            <input type="number" step="0.1" name="imagegen_guidance_scale" class="input-field" min="0.1" max="20"
                                   value="<?= htmlspecialchars($envValues['IMAGEGEN_GUIDANCE_SCALE'] ?? '') ?>"
                                   placeholder="Blank = profile default">
                            <p class="help-text">Higher guidance follows prompt more strictly but can reduce naturalness.</p>
                        </div>

                        <div>
                            <label class="label-text">Output Width (override)</label>
                            <input type="number" name="imagegen_width" class="input-field" min="256" max="1536"
                                   value="<?= htmlspecialchars($envValues['IMAGEGEN_WIDTH'] ?? '') ?>"
                                   placeholder="Blank = profile default">
                            <p class="help-text">Lower resolutions reduce GPU/CPU load and latency.</p>
                        </div>

                        <div>
                            <label class="label-text">Output Height (override)</label>
                            <input type="number" name="imagegen_height" class="input-field" min="256" max="1536"
                                   value="<?= htmlspecialchars($envValues['IMAGEGEN_HEIGHT'] ?? '') ?>"
                                   placeholder="Blank = profile default">
                            <p class="help-text">Set together with width for custom output dimensions.</p>
                        </div>
                    </div>
                </div>

                <!-- Feature Toggles Section -->
                <div class="section-card mt-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        <i class="fas fa-toggle-on mr-2 text-green-500"></i>Feature Toggles
                    </h2>
                    
                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-gray-200 dark:border-gray-700">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-orange-100 dark:bg-orange-900/30 rounded-lg flex items-center justify-center">
                                    <svg class="w-5 h-5 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418"/>
                                    </svg>
                                </div>
                                <div>
                                    <label class="font-medium text-gray-900 dark:text-white">OpenWebUI Installation</label>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Allow users to install OpenWebUI in their sandbox</p>
                                </div>
                            </div>
                            <?php $owEnabled = ($envValues['OPENWEBUI_ENABLED'] ?? 'false') === 'true'; ?>
                            <button type="button" id="openwebui-toggle" 
                                    onclick="toggleOpenWebUI(this)"
                                    data-enabled="<?= $owEnabled ? 'true' : 'false' ?>"
                                    style="width: 56px; height: 32px; border-radius: 16px; position: relative; cursor: pointer; transition: background-color 0.2s; background-color: <?= $owEnabled ? '#f97316' : '#d1d5db' ?>;">
                                <span id="openwebui-knob" style="position: absolute; top: 4px; width: 24px; height: 24px; border-radius: 50%; background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.2); transition: left 0.2s; left: <?= $owEnabled ? '28px' : '4px' ?>;"></span>
                            </button>
                            <input type="hidden" name="openwebui_enabled" id="openwebui-enabled-input" value="<?= $owEnabled ? '1' : '0' ?>">
                        </div>

                        <!-- SDCPU Active Toggle -->
                        <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-gray-200 dark:border-gray-700">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-purple-100 dark:bg-purple-900/30 rounded-lg flex items-center justify-center">
                                    <svg class="w-5 h-5 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/>
                                    </svg>
                                </div>
                                <div>
                                    <label class="font-medium text-gray-900 dark:text-white">Image Generation (SDCPU)</label>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Enable image generation in /chat for all users without a subscription requirement</p>
                                </div>
                            </div>
                            <?php $sdcpuActive = ($envValues['SDCPU_ACTIVE'] ?? 'false') === 'true'; ?>
                            <button type="button" id="sdcpu-toggle"
                                    onclick="toggleSdcpu(this)"
                                    data-enabled="<?= $sdcpuActive ? 'true' : 'false' ?>"
                                    style="width: 56px; height: 32px; border-radius: 16px; position: relative; cursor: pointer; transition: background-color 0.2s; background-color: <?= $sdcpuActive ? '#7c3aed' : '#d1d5db' ?>;">
                                <span id="sdcpu-knob" style="position: absolute; top: 4px; width: 24px; height: 24px; border-radius: 50%; background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.2); transition: left 0.2s; left: <?= $sdcpuActive ? '28px' : '4px' ?>;"></span>
                            </button>
                            <input type="hidden" name="sdcpu_active" id="sdcpu-active-input" value="<?= $sdcpuActive ? '1' : '0' ?>">
                        </div>

                        <!-- SDCPU Tunnel Toggle -->
                        <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-gray-200 dark:border-gray-700">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-indigo-100 dark:bg-indigo-900/30 rounded-lg flex items-center justify-center">
                                    <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-.837.836m0 0a2.25 2.25 0 01-3.182 0l-.836-.836a4.5 4.5 0 010-6.364l1.757-1.757a4.5 4.5 0 016.364 0l.836.836a4.5 4.5 0 010 6.364l-1.757 1.757m-3.182-3.182l4.5-4.5" />
                                    </svg>
                                </div>
                                <div>
                                    <label class="font-medium text-gray-900 dark:text-white">SDCPU Tunnel</label>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Enable tunnel-aware SDCPU pipeline via SDCPU_TUNNEL in .env (for future image processor integration)</p>
                                </div>
                            </div>
                            <?php $sdcpuTunnel = ($envValues['SDCPU_TUNNEL'] ?? 'false') === 'true'; ?>
                            <button type="button" id="sdcpu-tunnel-toggle"
                                    onclick="toggleSdcpuTunnel(this)"
                                    data-enabled="<?= $sdcpuTunnel ? 'true' : 'false' ?>"
                                    style="width: 56px; height: 32px; border-radius: 16px; position: relative; cursor: pointer; transition: background-color 0.2s; background-color: <?= $sdcpuTunnel ? '#4f46e5' : '#d1d5db' ?>;">
                                <span id="sdcpu-tunnel-knob" style="position: absolute; top: 4px; width: 24px; height: 24px; border-radius: 50%; background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.2); transition: left 0.2s; left: <?= $sdcpuTunnel ? '28px' : '4px' ?>;"></span>
                            </button>
                            <input type="hidden" name="sdcpu_tunnel" id="sdcpu-tunnel-input" value="<?= $sdcpuTunnel ? '1' : '0' ?>">
                        </div>

                        <!-- Groq Vision for All Models Toggle -->
                        <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-gray-200 dark:border-gray-700">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-emerald-100 dark:bg-emerald-900/30 rounded-lg flex items-center justify-center">
                                    <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/>
                                    </svg>
                                </div>
                                <div>
                                    <label class="font-medium text-gray-900 dark:text-white">Groq Vision for All Models</label>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Always show image attachment and process image attachments with Groq vision before main model response</p>
                                </div>
                            </div>
                            <?php $groqVisionForAllModels = ($envValues['GROQ_VISION_FOR_ALL_MODELS'] ?? 'false') === 'true'; ?>
                            <button type="button" id="groq-vision-toggle"
                                    onclick="toggleGroqVisionForAllModels(this)"
                                    data-enabled="<?= $groqVisionForAllModels ? 'true' : 'false' ?>"
                                    style="width: 56px; height: 32px; border-radius: 16px; position: relative; cursor: pointer; transition: background-color 0.2s; background-color: <?= $groqVisionForAllModels ? '#10b981' : '#d1d5db' ?>;">
                                <span id="groq-vision-knob" style="position: absolute; top: 4px; width: 24px; height: 24px; border-radius: 50%; background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.2); transition: left 0.2s; left: <?= $groqVisionForAllModels ? '28px' : '4px' ?>;"></span>
                            </button>
                            <input type="hidden" name="groq_vision_for_all_models" id="groq-vision-for-all-models-input" value="<?= $groqVisionForAllModels ? '1' : '0' ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Save Button -->
            <div class="flex justify-end gap-4 mt-6">
                <a href="/chat" class="px-6 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                    Cancel
                </a>
                <button type="button" id="prev-btn" onclick="navigateTab(-1)" class="hidden px-6 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors flex items-center gap-2">
                    <i class="fas fa-chevron-left"></i>
                    Previous
                </button>
                <button type="button" id="next-btn" onclick="navigateTab(1)" class="px-6 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors flex items-center gap-2">
                    Next
                    <i class="fas fa-chevron-right"></i>
                </button>
                <button type="submit" id="save-btn" class="px-6 py-2.5 rounded-lg bg-blue-600 text-white font-medium hover:bg-blue-700 transition-colors flex items-center gap-2">
                    <i class="fas fa-save"></i>
                    Save Settings
                </button>
                <button type="button" id="make-live-btn" onclick="showMakeLiveModal()" class="px-6 py-2.5 rounded-lg bg-green-600 text-white font-medium hover:bg-green-700 transition-colors flex items-center gap-2">
                    <i class="fas fa-rocket"></i>
                    Make Live
                </button>
            </div>
        </form>
    </main>

    <!-- Make Live Confirmation Modal -->
    <div id="make-live-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-md w-full mx-4 overflow-hidden">
            <!-- Header -->
            <div class="p-6 bg-gradient-to-r from-green-600 to-emerald-600 text-white">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-rocket text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold">Make Site Live</h3>
                        <p class="text-white/80 text-sm">Complete your installation</p>
                    </div>
                </div>
            </div>
            
            <!-- Body -->
            <div class="p-6">
                <div class="space-y-4">
                    <div class="flex items-start gap-3 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-lg">
                        <i class="fas fa-exclamation-triangle text-amber-500 mt-0.5"></i>
                        <div class="text-sm text-amber-800 dark:text-amber-200">
                            <p class="font-medium mb-1">Important Notice</p>
                            <p>This action will finalize your "live" installation for production ready setup.</p>
                        </div>
                    </div>
                    
                    <ul class="space-y-3 text-gray-700 dark:text-gray-300">
                        <li class="flex items-center gap-3">
                            <i class="fas fa-lock text-blue-500 w-5"></i>
                            <span>Require admin login to access <code class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-700 rounded text-sm">/live</code> settings</span>
                        </li>
                        <li class="flex items-center gap-3">
                            <i class="fas fa-eye-slash text-purple-500 w-5"></i>
                            <span>Hide the Live button from non-admin users</span>
                        </li>
                        <li class="flex items-center gap-3">
                            <i class="fas fa-shield-alt text-green-500 w-5"></i>
                            <span>Enable production security settings</span>
                        </li>
                    </ul>
                    
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-4">
                        Your current settings will be saved before making the site live.
                    </p>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900/50 flex justify-end gap-3">
                <button type="button" onclick="closeMakeLiveModal()" class="px-5 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                    Cancel
                </button>
                <button type="button" id="confirm-make-live-btn" onclick="confirmMakeLive()" class="px-5 py-2 rounded-lg bg-green-600 text-white font-medium hover:bg-green-700 transition-colors flex items-center gap-2">
                    <i class="fas fa-check"></i>
                    Understood!
                </button>
            </div>
        </div>
    </div>

    <script src="/assets/js/theme.js"></script>
    <script src="/assets/js/ui-components.js"></script>
    <script>
        // Toggle password visibility
        function togglePassword(fieldId) {
            const input = document.getElementById(fieldId);
            const icon = document.getElementById(fieldId + '_icon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Tab switching
        const tabOrder = ['users', 'providers', 'api-keys', 'local-llm', 'rate-limits', 'ecommerce', 'datacenter', 'site'];
        let currentTabIndex = 0;

        function updateNavButtons() {
            const prevBtn = document.getElementById('prev-btn');
            const nextBtn = document.getElementById('next-btn');
            
            if (currentTabIndex === 0) {
                prevBtn.classList.add('hidden');
            } else {
                prevBtn.classList.remove('hidden');
            }
            
            if (currentTabIndex === tabOrder.length - 1) {
                nextBtn.classList.add('hidden');
            } else {
                nextBtn.classList.remove('hidden');
            }
        }

        function switchToTab(index) {
            currentTabIndex = index;
            const tabName = tabOrder[index];
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.add('hidden'));
            document.querySelector(`.tab-btn[data-tab="${tabName}"]`).classList.add('active');
            document.getElementById('tab-' + tabName).classList.remove('hidden');
            updateNavButtons();
            saveCurrentTab();
        }

        function navigateTab(direction) {
            const newIndex = currentTabIndex + direction;
            if (newIndex >= 0 && newIndex < tabOrder.length) {
                switchToTab(newIndex);
            }
        }

        async function refreshImagegenModelStatus() {
            const statusEl = document.getElementById('imagegen-model-status');
            const selectEl = document.getElementById('imagegen-available-models');
            if (!statusEl || !selectEl) return;

            statusEl.textContent = 'Checking model status...';

            try {
                const res = await fetch('/live/imagegen/model-status', {
                    method: 'GET',
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin'
                });
                const data = await res.json();

                if (!data.success) {
                    throw new Error(data.error || 'Failed to fetch model status');
                }

                const endpoint = data.endpoint || '-';
                const selectedModel = data.selected_model || '(default)';
                const loadedModel = data.loaded_model || '(unknown)';
                const loadedModelSource = data.loaded_model_source || 'service';
                const defaultModel = data.default_model || '(none)';
                const isUp = !!data.service_up;
                statusEl.innerHTML = `
                    <div><strong>Endpoint:</strong> ${endpoint}</div>
                    <div><strong>Service:</strong> ${isUp ? 'Online' : 'Offline/Unavailable'}</div>
                    <div><strong>Selected Model:</strong> ${selectedModel}</div>
                    <div><strong>Loaded Model:</strong> ${loadedModel} <span class="text-xs text-gray-500 dark:text-gray-400">(${loadedModelSource})</span></div>
                    <div><strong>Install Default:</strong> ${defaultModel}</div>
                `;

                const models = Array.isArray(data.available_models) ? data.available_models : [];
                selectEl.innerHTML = '<option value="">(Select detected model)</option>';
                for (const model of models) {
                    const opt = document.createElement('option');
                    opt.value = model;
                    opt.textContent = model;
                    selectEl.appendChild(opt);
                }

                const modelInputEl = document.getElementById('imagegen-model-id');
                const preferredModel = (data.selected_model && data.selected_model.trim())
                    ? data.selected_model.trim()
                    : ((data.loaded_model && data.loaded_model.trim()) ? data.loaded_model.trim() : '');
                if (preferredModel && Array.from(selectEl.options).some(o => o.value === preferredModel)) {
                    selectEl.value = preferredModel;
                }
                if (modelInputEl && !modelInputEl.value && preferredModel) {
                    modelInputEl.value = preferredModel;
                }
            } catch (err) {
                statusEl.textContent = `Unable to fetch model status: ${err.message}`;
                selectEl.innerHTML = '<option value="">(Unavailable)</option>';
            }
        }

        // Toggle OpenWebUI enabled/disabled
        function toggleOpenWebUI(btn) {
            const isEnabled = btn.dataset.enabled === 'true';
            const newState = !isEnabled;
            const knob = document.getElementById('openwebui-knob');
            btn.dataset.enabled = newState ? 'true' : 'false';
            btn.style.backgroundColor = newState ? '#f97316' : '#d1d5db';
            knob.style.left = newState ? '28px' : '4px';
            document.getElementById('openwebui-enabled-input').value = newState ? '1' : '0';
        }

        // Toggle SDCPU image generation active/inactive
        function toggleSdcpu(btn) {
            const isEnabled = btn.dataset.enabled === 'true';
            const newState = !isEnabled;
            const knob = document.getElementById('sdcpu-knob');
            btn.dataset.enabled = newState ? 'true' : 'false';
            btn.style.backgroundColor = newState ? '#7c3aed' : '#d1d5db';
            knob.style.left = newState ? '28px' : '4px';
            document.getElementById('sdcpu-active-input').value = newState ? '1' : '0';
        }

        // Toggle SDCPU tunnel mode
        function toggleSdcpuTunnel(btn) {
            const isEnabled = btn.dataset.enabled === 'true';
            const newState = !isEnabled;
            const knob = document.getElementById('sdcpu-tunnel-knob');
            btn.dataset.enabled = newState ? 'true' : 'false';
            btn.style.backgroundColor = newState ? '#4f46e5' : '#d1d5db';
            knob.style.left = newState ? '28px' : '4px';
            document.getElementById('sdcpu-tunnel-input').value = newState ? '1' : '0';
        }

        // Toggle Groq vision pre-analysis for all models
        function toggleGroqVisionForAllModels(btn) {
            const isEnabled = btn.dataset.enabled === 'true';
            const newState = !isEnabled;
            const knob = document.getElementById('groq-vision-knob');
            btn.dataset.enabled = newState ? 'true' : 'false';
            btn.style.backgroundColor = newState ? '#10b981' : '#d1d5db';
            knob.style.left = newState ? '28px' : '4px';
            document.getElementById('groq-vision-for-all-models-input').value = newState ? '1' : '0';
        }

        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const tabName = btn.dataset.tab;
                currentTabIndex = tabOrder.indexOf(tabName);
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.add('hidden'));
                btn.classList.add('active');
                document.getElementById('tab-' + btn.dataset.tab).classList.remove('hidden');
                updateNavButtons();
                saveCurrentTab();
            });
        });

        // Initialize nav buttons on load
        updateNavButtons();

        const refreshModelBtn = document.getElementById('refresh-imagegen-model-status');
        if (refreshModelBtn) {
            refreshModelBtn.addEventListener('click', refreshImagegenModelStatus);
        }

        const imagegenAvailableModels = document.getElementById('imagegen-available-models');
        const imagegenModelInput = document.getElementById('imagegen-model-id');
        if (imagegenAvailableModels && imagegenModelInput) {
            imagegenAvailableModels.addEventListener('change', () => {
                if (imagegenAvailableModels.value) {
                    imagegenModelInput.value = imagegenAvailableModels.value;
                }
            });
        }

        refreshImagegenModelStatus();

        // ============================================
        // LocalStorage Persistence for Setup Wizard
        // ============================================
        const STORAGE_KEY = 'ginto_live_setup_draft';
        const TAB_STORAGE_KEY = 'ginto_live_setup_tab';

        // Load saved form data from localStorage
        function loadSavedFormData() {
            try {
                const saved = localStorage.getItem(STORAGE_KEY);
                if (saved) {
                    const data = JSON.parse(saved);
                    const form = document.getElementById('settings-form');
                    Object.keys(data).forEach(key => {
                        const field = form.querySelector(`[name="${key}"]`);
                        if (field && key !== 'csrf_token') {
                            field.value = data[key];
                        }
                    });
                }
            } catch (e) {
                console.warn('Failed to load saved form data:', e);
            }
        }

        // Save form data to localStorage on any input change
        function saveFormData() {
            try {
                const form = document.getElementById('settings-form');
                const formData = new FormData(form);
                const data = Object.fromEntries(formData.entries());
                delete data.csrf_token; // Don't store CSRF token
                localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
            } catch (e) {
                console.warn('Failed to save form data:', e);
            }
        }

        // Load saved tab position
        function loadSavedTab() {
            try {
                const savedTab = localStorage.getItem(TAB_STORAGE_KEY);
                if (savedTab) {
                    const index = tabOrder.indexOf(savedTab);
                    if (index >= 0) {
                        switchToTab(index);
                    }
                }
            } catch (e) {
                console.warn('Failed to load saved tab:', e);
            }
        }

        // Save current tab to localStorage
        function saveCurrentTab() {
            try {
                localStorage.setItem(TAB_STORAGE_KEY, tabOrder[currentTabIndex]);
            } catch (e) {
                console.warn('Failed to save tab:', e);
            }
        }

        // Clear all saved data (called on successful Make Live)
        function clearSavedData() {
            localStorage.removeItem(STORAGE_KEY);
            localStorage.removeItem(TAB_STORAGE_KEY);
        }

        // Attach listeners to save on input
        document.getElementById('settings-form').addEventListener('input', saveFormData);
        document.getElementById('settings-form').addEventListener('change', saveFormData);

        // Load saved data on page load
        loadSavedFormData();
        loadSavedTab();

        // Sync Default Provider with hidden LLM_PROVIDER field
        const providerSelect = document.querySelector('select[name="default_provider"]');
        const llmProviderHidden = document.getElementById('llm_provider_hidden');
        if (providerSelect && llmProviderHidden) {
            providerSelect.addEventListener('change', () => {
                llmProviderHidden.value = providerSelect.value;
            });
            // Sync on load
            llmProviderHidden.value = providerSelect.value;
        }

        // Form submission with GintoUI toasts
        document.getElementById('settings-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const btn = document.getElementById('save-btn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            btn.disabled = true;
            
            try {
                const formData = new FormData(e.target);
                const data = Object.fromEntries(formData.entries());
                const csrfToken = data.csrf_token;
                
                const response = await fetch('/live', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ data, csrf_token: csrfToken })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    if (typeof GintoUI !== 'undefined') {
                        GintoUI.success('Settings saved successfully!');
                    }
                    btn.innerHTML = '<i class="fas fa-check"></i> Saved!';
                    btn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
                    btn.classList.add('bg-green-600');
                    
                    setTimeout(() => {
                        btn.innerHTML = originalText;
                        btn.classList.remove('bg-green-600');
                        btn.classList.add('bg-blue-600', 'hover:bg-blue-700');
                        btn.disabled = false;
                    }, 2000);
                } else {
                    throw new Error(result.error || 'Failed to save settings');
                }
            } catch (error) {
                if (typeof GintoUI !== 'undefined') {
                    GintoUI.error(error.message);
                } else {
                    alert('Error: ' + error.message);
                }
                btn.innerHTML = '<i class="fas fa-exclamation-circle"></i> Error';
                btn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
                btn.classList.add('bg-red-600');
                
                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.classList.remove('bg-red-600');
                    btn.classList.add('bg-blue-600', 'hover:bg-blue-700');
                    btn.disabled = false;
                }, 2000);
            }
        });

        // Password toggle
        function togglePassword(fieldId) {
            const el = document.getElementById(fieldId);
            if (!el) return;
            el.type = el.type === 'password' ? 'text' : 'password';
            const btn = el.parentElement.querySelector('button i');
            if (btn) {
                btn.classList.toggle('fa-eye');
                btn.classList.toggle('fa-eye-slash');
            }
        }

        // Make Live Modal functions
        function showMakeLiveModal() {
            const modal = document.getElementById('make-live-modal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeMakeLiveModal() {
            const modal = document.getElementById('make-live-modal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        async function confirmMakeLive() {
            const btn = document.getElementById('confirm-make-live-btn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Please wait...';
            btn.disabled = true;

            try {
                // First, save the current settings
                const form = document.getElementById('settings-form');
                const formData = new FormData(form);
                const data = Object.fromEntries(formData.entries());
                const csrfToken = data.csrf_token;

                const saveResponse = await fetch('/live', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ data, csrf_token: csrfToken })
                });

                const saveResult = await saveResponse.json();

                if (!saveResult.success) {
                    throw new Error(saveResult.error || 'Failed to save settings');
                }

                // Now call make-live endpoint to create .installed marker
                const liveResponse = await fetch('/live/activate', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ csrf_token: csrfToken })
                });

                const liveResult = await liveResponse.json();

                if (liveResult.success) {
                    // Clear localStorage draft data on successful activation
                    clearSavedData();
                    
                    if (typeof GintoUI !== 'undefined') {
                        GintoUI.success('Site is now live! Redirecting...');
                    }
                    btn.innerHTML = '<i class="fas fa-check"></i> Success!';
                    btn.classList.remove('bg-green-600', 'hover:bg-green-700');
                    btn.classList.add('bg-emerald-600');
                    
                    // Redirect to /chat after a short delay
                    setTimeout(() => {
                        window.location.href = '/chat';
                    }, 1500);
                } else {
                    throw new Error(liveResult.error || 'Failed to activate site');
                }
            } catch (error) {
                if (typeof GintoUI !== 'undefined') {
                    GintoUI.error(error.message);
                } else {
                    alert('Error: ' + error.message);
                }
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        }

        // Close modal on backdrop click
        document.getElementById('make-live-modal').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) {
                closeMakeLiveModal();
            }
        });

        // Close modal on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeMakeLiveModal();
            }
        });
    </script>
</body>
</html>
