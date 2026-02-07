<?php
/**
 * Sandbox Installation Wizard Modal
 */
?>
<!-- Sandbox Installation Wizard Modal -->
<div id="sandbox-wizard-modal" class="fixed inset-0 z-[100] hidden">
  <!-- Backdrop -->
  <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" onclick="closeSandboxWizard()"></div>
  <!-- Modal Content -->
  <div class="absolute inset-0 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl max-w-lg w-full transform transition-all" id="sandbox-wizard-content">
      
      <?php 
      // Check if we're on live/production (ginto.ai) - show sandbox type selector
      $isLive = (strpos($_SERVER['HTTP_HOST'] ?? '', 'ginto.ai') !== false) || 
                (($_ENV['APP_URL'] ?? '') === 'https://ginto.ai');
      ?>
      
      <!-- Step 1: Welcome/Introduction -->
      <div id="wizard-step-1" class="wizard-step">
        <!-- Header with icon -->
        <div class="p-6 pb-4 border-b border-gray-200 dark:border-gray-700">
          <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-violet-500 to-purple-600 flex items-center justify-center shadow-lg">
              <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
              </svg>
            </div>
            <div>
              <h2 class="text-xl font-bold text-gray-900 dark:text-white">Welcome to Your Sandbox</h2>
              <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Secure isolated environment for your files</p>
            </div>
          </div>
        </div>
        
        <!-- Content -->
        <div class="p-6 space-y-4">
          <p class="text-gray-600 dark:text-gray-300">
            A <strong>sandbox</strong> is your personal, isolated workspace where you can safely create, edit, and manage files without affecting other users or system files.
          </p>
          
          <div class="space-y-3">
            <div class="flex items-start gap-3 p-3 bg-violet-50 dark:bg-violet-900/20 rounded-xl">
              <svg class="w-5 h-5 text-violet-600 dark:text-violet-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
              </svg>
              <div>
                <div class="font-medium text-gray-900 dark:text-white">Isolated Environment</div>
                <div class="text-sm text-gray-600 dark:text-gray-400">Your files are stored in a secure container</div>
              </div>
            </div>
            
            <div class="flex items-start gap-3 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-xl">
              <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/>
              </svg>
              <div>
                <div class="font-medium text-gray-900 dark:text-white">Your Personal Space</div>
                <div class="text-sm text-gray-600 dark:text-gray-400">Store code, notes, and project files</div>
              </div>
            </div>
            
            <div class="flex items-start gap-3 p-3 bg-emerald-50 dark:bg-emerald-900/20 rounded-xl">
              <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
              </svg>
              <div>
                <div class="font-medium text-gray-900 dark:text-white">Run Code Safely</div>
                <div class="text-sm text-gray-600 dark:text-gray-400">Execute scripts without risk to the system</div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Actions -->
        <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-b-2xl flex justify-between items-center">
          <button onclick="closeSandboxWizard()" class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors">
            Maybe Later
          </button>
          <button onclick="showWizardStep(<?= $isLive ? '2' : '3' ?>)" class="px-5 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-500 hover:to-purple-500 rounded-xl shadow-lg shadow-violet-500/25 transition-all">
            Continue
            <svg class="w-4 h-4 inline-block ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
            </svg>
          </button>
        </div>
      </div>
      
      <?php if ($isLive): ?>
      <!-- Step 2: Choose Sandbox Type (Live Only) -->
      <div id="wizard-step-2" class="wizard-step hidden">
        <!-- Header -->
        <div class="p-6 pb-4 border-b border-gray-200 dark:border-gray-700">
          <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center shadow-lg">
              <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
              </svg>
            </div>
            <div>
              <h2 class="text-xl font-bold text-gray-900 dark:text-white">Choose Sandbox Type</h2>
              <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Select your preferred container technology</p>
            </div>
          </div>
        </div>
        
        <!-- Content -->
        <div class="p-6 space-y-4">
          <div class="space-y-3">
            <!-- Docker Option (Free) -->
            <label class="flex items-center gap-3 p-4 rounded-xl border-2 cursor-pointer transition-all sandbox-type-option border-violet-500 bg-violet-50 dark:bg-violet-900/20" data-type="docker">
              <input type="radio" name="sandbox_type" value="docker" checked class="w-5 h-5 text-violet-600 focus:ring-violet-500">
              <div class="w-12 h-12 rounded-lg bg-blue-100 dark:bg-blue-900/50 flex items-center justify-center flex-shrink-0">
                <svg class="w-7 h-7 text-blue-600 dark:text-blue-400" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M13.983 11.078h2.119a.186.186 0 00.186-.185V9.006a.186.186 0 00-.186-.186h-2.119a.186.186 0 00-.185.186v1.887c0 .102.083.185.185.185m-2.954-5.43h2.118a.186.186 0 00.186-.186V3.575a.186.186 0 00-.186-.185h-2.118a.186.186 0 00-.185.185v1.887c0 .103.082.186.185.186m0 2.716h2.118a.187.187 0 00.186-.186V6.29a.186.186 0 00-.186-.185h-2.118a.186.186 0 00-.185.185v1.887c0 .103.082.186.185.186m-2.93 0h2.12a.186.186 0 00.184-.186V6.29a.185.185 0 00-.185-.185H8.1a.185.185 0 00-.185.185v1.887c0 .103.083.186.185.186m-2.964 0h2.119a.186.186 0 00.185-.186V6.29a.186.186 0 00-.185-.185H5.136a.186.186 0 00-.186.185v1.887c0 .103.084.186.186.186m5.893 2.715h2.118a.186.186 0 00.186-.185V9.006a.186.186 0 00-.186-.186h-2.118a.186.186 0 00-.185.186v1.887c0 .102.082.185.185.185m-2.93 0h2.12a.185.185 0 00.184-.185V9.006a.185.185 0 00-.184-.186h-2.12a.185.185 0 00-.184.186v1.887c0 .102.083.185.185.185m-2.964 0h2.119a.185.185 0 00.185-.185V9.006a.185.185 0 00-.185-.186h-2.12a.186.186 0 00-.185.186v1.887c0 .102.084.185.186.185m-2.92 0h2.12a.185.185 0 00.184-.185V9.006a.185.185 0 00-.184-.186h-2.12a.185.185 0 00-.184.186v1.887c0 .102.083.185.185.185M23.763 9.89c-.065-.051-.672-.51-1.954-.51-.338.001-.676.03-1.01.087-.248-1.7-1.653-2.53-1.716-2.566l-.344-.199-.226.327c-.284.438-.49.922-.612 1.43-.23.97-.09 1.882.403 2.661-.595.332-1.55.413-1.744.42H.751a.751.751 0 00-.75.748 11.376 11.376 0 00.692 4.062c.545 1.428 1.355 2.48 2.41 3.124 1.18.723 3.1 1.137 5.275 1.137.983.003 1.963-.086 2.93-.266a12.248 12.248 0 003.823-1.389c.98-.567 1.86-1.288 2.61-2.136 1.252-1.418 1.998-2.997 2.553-4.4h.221c1.372 0 2.215-.549 2.68-1.009.309-.293.55-.65.707-1.046l.098-.288Z"/>
                </svg>
              </div>
              <div class="flex-1">
                <div class="font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                  Docker Sandbox
                  <span class="text-xs px-2 py-0.5 bg-green-100 dark:bg-green-900/50 text-green-700 dark:text-green-400 rounded-full">Free</span>
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">Lightweight container, quick to start. Perfect for most use cases.</div>
              </div>
            </label>
            
            <!-- LXC Option (Enterprise) -->
            <label class="flex items-center gap-3 p-4 rounded-xl border-2 cursor-pointer transition-all sandbox-type-option border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600" data-type="lxc">
              <input type="radio" name="sandbox_type" value="lxc" class="w-5 h-5 text-violet-600 focus:ring-violet-500">
              <div class="w-12 h-12 rounded-lg bg-orange-100 dark:bg-orange-900/50 flex items-center justify-center flex-shrink-0">
                <svg class="w-7 h-7 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/>
                </svg>
              </div>
              <div class="flex-1">
                <div class="font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                  LXC Sandbox
                  <span class="text-xs px-2 py-0.5 bg-amber-100 dark:bg-amber-900/50 text-amber-700 dark:text-amber-400 rounded-full">Enterprise</span>
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">Full Linux container with more resources & features.</div>
              </div>
            </label>
          </div>
          
          <!-- Enterprise Upsell (Hidden by default, shown when LXC selected) -->
          <div id="lxc-enterprise-notice" class="hidden p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-xl">
            <div class="flex items-start gap-3">
              <svg class="w-6 h-6 text-amber-600 dark:text-amber-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
              </svg>
              <div class="text-sm text-amber-800 dark:text-amber-300">
                <strong class="block">Enterprise Feature</strong>
                <span>LXC sandboxes require the Enterprise subscription. <a href="/register" class="underline hover:no-underline font-medium">Upgrade now</a> for full Linux containers with more resources.</span>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Actions -->
        <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-b-2xl flex justify-between items-center">
          <button onclick="showWizardStep(1)" class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/>
            </svg>
            Back
          </button>
          <button id="wizard-continue-btn" onclick="continueSandboxWizard()" class="px-5 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-500 hover:to-purple-500 rounded-xl shadow-lg shadow-violet-500/25 transition-all">
            Continue
            <svg class="w-4 h-4 inline-block ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
            </svg>
          </button>
        </div>
      </div>
      <?php endif; ?>
      
      <!-- Step 3: Terms & Conditions -->
      <div id="wizard-step-3" class="wizard-step hidden">
        <!-- Header -->
        <div class="p-6 pb-4 border-b border-gray-200 dark:border-gray-700">
          <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center shadow-lg">
              <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
              </svg>
            </div>
            <div>
              <h2 class="text-xl font-bold text-gray-900 dark:text-white">Terms & Privacy</h2>
              <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Please review before proceeding</p>
            </div>
          </div>
        </div>
        
        <!-- Content -->
        <div class="p-6 space-y-4">
          <div class="max-h-48 overflow-y-auto p-4 bg-gray-50 dark:bg-gray-800 rounded-xl text-sm text-gray-600 dark:text-gray-300 space-y-3 border border-gray-200 dark:border-gray-700">
            <p class="font-semibold text-gray-900 dark:text-white">Sandbox Usage Terms</p>
            <p>By creating a sandbox, you agree to the following:</p>
            <ul class="list-disc list-inside space-y-2 pl-2">
              <li><strong>Visitor Sessions:</strong> If you are not logged in, your sandbox session is limited to <strong>one hour</strong>. Register or log in to keep your sandbox permanently.</li>
              <li><strong>File Storage:</strong> Files stored in your sandbox are associated with your account. You are responsible for backing up important data.</li>
              <li><strong>Resource Limits:</strong> Your sandbox has limited CPU, memory, and storage. Excessive usage may result in throttling.</li>
              <li><strong>No Illegal Activity:</strong> You may not use the sandbox for illegal purposes, malware distribution, or attacks on other systems.</li>
              <li><strong>Data Retention:</strong> Inactive sandboxes may be paused or archived after extended periods of inactivity.</li>
              <li><strong>Privacy:</strong> We do not actively monitor your files, but may access them for security or legal compliance purposes.</li>
              <li><strong>No Warranty:</strong> The sandbox is provided "as is" without guarantees of uptime or data preservation.</li>
            </ul>
          </div>
          
          <label class="flex items-start gap-3 cursor-pointer group">
            <input type="checkbox" id="accept-sandbox-terms" class="mt-1 w-5 h-5 rounded border-gray-300 dark:border-gray-600 text-violet-600 focus:ring-violet-500 dark:bg-gray-700">
            <span class="text-sm text-gray-700 dark:text-gray-300 group-hover:text-gray-900 dark:group-hover:text-white transition-colors">
              I have read and agree to the <strong>Sandbox Terms of Use</strong> and understand my responsibilities regarding file storage and privacy.
            </span>
          </label>
          
          <?php if (!empty($isAdmin) && $sandboxBackend === 'lxd'): ?>
          <!-- LXD Nesting Warning (Admin only, LXD backend only) -->
          <div class="mt-4 p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl">
            <div class="flex items-start gap-2">
              <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
              </svg>
              <div class="text-xs text-amber-800 dark:text-amber-300">
                <strong>Running inside LXD/LXC?</strong> Enable nesting on the host:<br>
                <code class="bg-amber-100 dark:bg-amber-900/50 px-1 rounded text-[10px] block mt-1">lxc profile set default security.nesting=true</code>
                <span class="text-[10px] opacity-75">Or for specific container: <code>lxc config set &lt;name&gt; security.nesting=true</code></span>
              </div>
            </div>
          </div>
          <?php endif; ?>
        </div>
        
        <!-- Actions -->
        <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-b-2xl flex justify-between items-center">
          <button onclick="showWizardStep(<?= $isLive ? '2' : '1' ?>)" class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/>
            </svg>
            Back
          </button>
          <button id="wizard-install-btn" onclick="installSandbox()" disabled class="px-5 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-500 hover:to-purple-500 rounded-xl shadow-lg shadow-violet-500/25 transition-all disabled:opacity-50 disabled:cursor-not-allowed disabled:shadow-none">
            <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            Install My Sandbox
          </button>
        </div>
      </div>
      
      <!-- Step 4: Installing -->
      <div id="wizard-step-4" class="wizard-step hidden">
        <!-- Header -->
        <div class="p-6 pb-4 border-b border-gray-200 dark:border-gray-700">
          <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-blue-500 to-cyan-600 flex items-center justify-center shadow-lg">
              <svg class="w-7 h-7 text-white animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
            </div>
            <div>
              <h2 class="text-xl font-bold text-gray-900 dark:text-white">Installing Sandbox</h2>
              <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Please wait while we set up your environment</p>
            </div>
          </div>
        </div>
        
        <!-- Content -->
        <div class="p-6 space-y-6">
          <div class="space-y-3">
            <div id="install-step-1" class="flex items-center gap-3 p-3 rounded-xl bg-gray-50 dark:bg-gray-800 transition-all">
              <div class="install-icon w-8 h-8 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z"/>
                </svg>
              </div>
              <span class="text-sm text-gray-600 dark:text-gray-400">Creating sandbox directory...</span>
            </div>
            
            <div id="install-step-2" class="flex items-center gap-3 p-3 rounded-xl bg-gray-50 dark:bg-gray-800 transition-all">
              <div class="install-icon w-8 h-8 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z"/>
                </svg>
              </div>
              <span class="text-sm text-gray-600 dark:text-gray-400">Launching container...</span>
            </div>
            
            <div id="install-step-3" class="flex items-center gap-3 p-3 rounded-xl bg-gray-50 dark:bg-gray-800 transition-all">
              <div class="install-icon w-8 h-8 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z"/>
                </svg>
              </div>
              <span class="text-sm text-gray-600 dark:text-gray-400">Configuring environment...</span>
            </div>
            
            <div id="install-step-4" class="flex items-center gap-3 p-3 rounded-xl bg-gray-50 dark:bg-gray-800 transition-all">
              <div class="install-icon w-8 h-8 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z"/>
                </svg>
              </div>
              <span class="text-sm text-gray-600 dark:text-gray-400">Finalizing setup...</span>
            </div>
          </div>
          
          <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 overflow-hidden">
            <div id="install-progress-bar" class="bg-gradient-to-r from-violet-500 to-purple-500 h-full rounded-full transition-all duration-500" style="width: 0%"></div>
          </div>
          
          <p id="install-status-text" class="text-sm text-center text-gray-500 dark:text-gray-400">
            Preparing your sandbox environment...
          </p>
        </div>
      </div>
      
      <!-- Step 5: Success -->
      <div id="wizard-step-5" class="wizard-step hidden">
        <!-- Header -->
        <div class="p-6 pb-4 border-b border-gray-200 dark:border-gray-700">
          <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-emerald-500 to-green-600 flex items-center justify-center shadow-lg">
              <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
              </svg>
            </div>
            <div>
              <h2 class="text-xl font-bold text-gray-900 dark:text-white">Sandbox Ready!</h2>
              <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Your environment is set up and running</p>
            </div>
          </div>
        </div>
        
        <!-- Content -->
        <div class="p-6 space-y-4">
          <div class="p-4 bg-emerald-50 dark:bg-emerald-900/20 rounded-xl border border-emerald-200 dark:border-emerald-800">
            <div class="flex items-center gap-3">
              <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
              <div class="text-sm">
                <span class="text-emerald-800 dark:text-emerald-200 font-medium">Sandbox ID: </span>
                <code id="wizard-sandbox-id" class="px-2 py-0.5 bg-emerald-100 dark:bg-emerald-800 text-emerald-700 dark:text-emerald-300 rounded font-mono text-xs">---</code>
              </div>
            </div>
          </div>
          
          <p class="text-gray-600 dark:text-gray-300">
            Your sandbox is now ready to use. You can create, edit, and manage files in your personal workspace.
          </p>
          
          <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span>Click "Open Files" to start using your sandbox.</span>
          </div>
          
          <?php if (!empty($isAdmin)): ?>
          <div class="mt-3 p-3 bg-indigo-50 dark:bg-indigo-900/20 rounded-lg border border-indigo-200 dark:border-indigo-800">
            <div class="flex items-center gap-2 text-sm text-indigo-700 dark:text-indigo-300">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
              </svg>
              <span>Admin: To manage all user sandboxes, go to <a href="/admin/lxc" class="font-semibold underline hover:text-indigo-900 dark:hover:text-indigo-100">/admin/lxc</a></span>
            </div>
          </div>
          <?php endif; ?>
        </div>
        
        <!-- Actions -->
        <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-b-2xl flex justify-end">
          <button onclick="openSandboxAfterInstall()" class="px-5 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-emerald-600 to-green-600 hover:from-emerald-500 hover:to-green-500 rounded-xl shadow-lg shadow-emerald-500/25 transition-all">
            <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
            </svg>
            Open My Files
          </button>
        </div>
      </div>
      
      <!-- Error State -->
      <div id="wizard-step-error" class="wizard-step hidden">
        <!-- Header -->
        <div class="p-6 pb-4 border-b border-gray-200 dark:border-gray-700">
          <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-red-500 to-rose-600 flex items-center justify-center shadow-lg">
              <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
              </svg>
            </div>
            <div>
              <h2 class="text-xl font-bold text-gray-900 dark:text-white">Installation Failed</h2>
              <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Something went wrong</p>
            </div>
          </div>
        </div>
        
        <!-- Content -->
        <div class="p-6 space-y-4">
          <div class="p-4 bg-red-50 dark:bg-red-900/20 rounded-xl border border-red-200 dark:border-red-800">
            <p id="wizard-error-message" class="text-sm text-red-700 dark:text-red-300">
              An error occurred while creating your sandbox.
            </p>
          </div>
          
          <p class="text-gray-600 dark:text-gray-300 text-sm">
            This could be due to a temporary server issue. Please try again in a few moments.
          </p>
        </div>
        
        <!-- Actions -->
        <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-b-2xl flex justify-between items-center">
          <button onclick="closeSandboxWizard()" class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors">
            Close
          </button>
          <button onclick="showWizardStep(2)" class="px-5 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-500 hover:to-purple-500 rounded-xl shadow-lg shadow-violet-500/25 transition-all">
            Try Again
          </button>
        </div>
      </div>
      
      <!-- LXC Installation Required Step -->
      <div id="wizard-step-lxc-install" class="wizard-step hidden">
        <!-- Header -->
        <div class="p-6 pb-4 border-b border-gray-200 dark:border-gray-700">
          <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center shadow-lg">
              <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
              </svg>
            </div>
            <div>
              <h2 class="text-xl font-bold text-gray-900 dark:text-white">Setup Required</h2>
              <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">LXC/LXD needs to be installed</p>
            </div>
          </div>
        </div>
        
        <!-- Content -->
        <div class="p-6 space-y-4">
          <div class="p-4 bg-amber-50 dark:bg-amber-900/20 rounded-xl border border-amber-200 dark:border-amber-800">
            <p id="lxc-install-message" class="text-sm text-amber-700 dark:text-amber-300">
              The sandbox system requires LXC/LXD to be installed on the server.
            </p>
          </div>
          
          <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-xl border border-blue-200 dark:border-blue-800">
            <p class="text-sm text-blue-700 dark:text-blue-300">
              <strong>ginto.sh</strong> is the Ginto installer script that will:
            </p>
            <ul class="text-sm text-blue-600 dark:text-blue-400 mt-2 ml-4 list-disc space-y-1">
              <li>Install LXC/LXD container system</li>
              <li>Configure network bridges and storage</li>
              <li>Set up the Alpine Linux sandbox container</li>
              <li>Initialize all required permissions</li>
            </ul>
          </div>
          
          <p class="text-gray-600 dark:text-gray-300 text-sm font-medium">
            Run the installer in your server's SSH terminal:
          </p>
          
          <!-- Primary: Manual install command -->
          <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-xl border-2 border-violet-300 dark:border-violet-600">
            <div class="flex items-start gap-3">
              <div class="w-10 h-10 rounded-lg bg-violet-100 dark:bg-violet-800 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-violet-600 dark:text-violet-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
              </div>
              <div class="flex-1">
                <h4 class="font-semibold text-gray-900 dark:text-white">SSH into your server and run:</h4>
                <div class="flex items-center bg-gray-900 rounded-lg mt-2">
                  <code id="lxc-install-cmd" class="flex-1 text-green-400 p-3 text-sm font-mono select-all">sudo bash ~/ginto.ai/bin/ginto.sh install</code>
                  <button type="button" onclick="copyLxcInstallCmd()" class="p-3 text-gray-400 hover:text-white hover:bg-gray-700 rounded-r-lg transition-colors" title="Copy to clipboard">
                    <svg id="lxc-copy-icon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    <svg id="lxc-check-icon" class="w-5 h-5 hidden text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                  </button>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                  Follow the interactive prompts. Installation takes 2-5 minutes.
                </p>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Actions -->
        <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-b-2xl flex justify-between items-center">
          <button onclick="closeSandboxWizard()" class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors">
            Close
          </button>
          <button onclick="showWizardStep(2)" class="px-4 py-2 text-sm font-medium text-violet-600 dark:text-violet-400 hover:text-violet-800 dark:hover:text-violet-200 transition-colors">
            I've installed it, retry →
          </button>
        </div>
      </div>
      
      <!-- LXC Auto-Install Terminal Step -->
      <div id="wizard-step-lxc-terminal" class="wizard-step hidden">
        <!-- Header -->
        <div class="p-6 pb-4 border-b border-gray-200 dark:border-gray-700">
          <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center shadow-lg">
              <svg class="w-7 h-7 text-white animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
              </svg>
            </div>
            <div>
              <h2 class="text-xl font-bold text-gray-900 dark:text-white">Installing LXC/LXD</h2>
              <p id="lxc-terminal-status" class="text-sm text-gray-500 dark:text-gray-400 mt-1">Running setup script...</p>
            </div>
          </div>
        </div>
        
        <!-- Terminal Output -->
        <div class="p-4">
          <div id="lxc-terminal-output" class="bg-gray-900 rounded-xl p-4 h-64 overflow-y-auto font-mono text-sm text-green-400 whitespace-pre-wrap">
            <span class="text-gray-500">$ sudo bash ~/ginto.ai/bin/ginto.sh install</span>
            <br><span class="text-yellow-400">Starting LXC/LXD setup...</span>
            <br>
          </div>
        </div>
        
        <!-- Actions -->
        <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-b-2xl flex justify-between items-center">
          <button onclick="closeSandboxWizard()" class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors">
            Close
          </button>
          <button id="lxc-install-done-btn" onclick="showWizardStep(2)" disabled class="px-5 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-500 hover:to-purple-500 rounded-xl shadow-lg shadow-violet-500/25 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
            Continue to Sandbox Setup →
          </button>
        </div>
      </div>
      
    </div>
  </div>
</div>
