<?php
/**
 * Modal dialogs for chat interface
 * Includes: confirm, upgrade, register, agentic, image, TTS limit, session expired, sandbox wizard
 */
?>

<!-- Toast Container -->
<div id="toast-container" class="fixed top-4 right-4 z-50 flex flex-col gap-2 pointer-events-none"></div>

<!-- Confirmation Modal -->
<div id="confirm-modal" class="fixed inset-0 z-[110] hidden">
  <!-- Backdrop -->
  <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeConfirmModal()"></div>
  <!-- Modal Content -->
  <div class="absolute inset-0 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-md w-full transform transition-all scale-95 opacity-0" id="confirm-modal-content">
      <!-- Header -->
      <div class="p-5 border-b border-gray-200 dark:border-gray-700">
        <div class="flex items-center gap-3">
          <div id="confirm-modal-icon" class="w-10 h-10 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
          </div>
          <div>
            <h3 id="confirm-modal-title" class="text-lg font-semibold text-gray-900 dark:text-white">Confirm Action</h3>
            <p id="confirm-modal-message" class="text-sm text-gray-500 dark:text-gray-400 mt-1">Are you sure you want to proceed?</p>
          </div>
        </div>
      </div>
      <!-- Auto-approve checkbox (hidden by default, shown for tool execution) -->
      <div id="confirm-modal-auto-approve" class="hidden px-5 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
        <label class="flex items-center gap-3 cursor-pointer group">
          <input type="checkbox" id="confirm-modal-auto-approve-checkbox" class="w-4 h-4 text-indigo-600 bg-gray-100 dark:bg-gray-700 border-gray-300 dark:border-gray-600 rounded focus:ring-indigo-500 focus:ring-2">
          <span class="text-sm text-gray-600 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-gray-200">Don't ask again (auto-approve tool execution)</span>
        </label>
      </div>
      <!-- Actions -->
      <div class="p-4 flex justify-end gap-3 bg-gray-50 dark:bg-gray-800/50 rounded-b-xl">
        <button onclick="closeConfirmModal()" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
          Cancel
        </button>
        <button id="confirm-modal-action" class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors">
          Delete
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Premium Upgrade Modal -->
<div id="upgrade-modal" class="fixed inset-0 z-[110] hidden">
  <!-- Backdrop -->
  <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeUpgradeModal()"></div>
  <!-- Modal Content -->
  <div class="absolute inset-0 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-md w-full transform transition-all scale-95 opacity-0" id="upgrade-modal-content">
      <!-- Header with gradient -->
      <div class="p-6 bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-500 rounded-t-xl">
        <div class="flex items-center gap-4">
          <div class="w-14 h-14 rounded-full bg-white/20 flex items-center justify-center flex-shrink-0">
            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
            </svg>
          </div>
          <div>
            <h3 class="text-xl font-bold text-white">Upgrade to Premium</h3>
            <p class="text-white/80 text-sm mt-1">Unlock full sandbox capabilities</p>
          </div>
        </div>
      </div>
      <!-- Content -->
      <div class="p-5">
        <p id="upgrade-modal-message" class="text-gray-600 dark:text-gray-300 mb-4">
          Command execution requires a Premium subscription to keep our sandbox infrastructure secure and sustainable.
        </p>
        <!-- Pricing -->
        <div class="bg-gradient-to-r from-indigo-50 to-purple-50 dark:from-indigo-900/20 dark:to-purple-900/20 rounded-lg p-4 mb-4 border border-indigo-200 dark:border-indigo-800">
          <div class="flex items-center justify-between">
            <div>
              <span class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">₱200</span>
              <span class="text-gray-500 dark:text-gray-400">/week</span>
            </div>
            <span class="bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 text-xs font-semibold px-2.5 py-1 rounded-full">BEST VALUE</span>
          </div>
          <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">That's less than ₱30/day for unlimited AI-powered development!</p>
        </div>
        <!-- Features -->
        <ul class="space-y-2 mb-5">
          <li class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
            <svg class="w-5 h-5 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            <span>Full tool-calling capabilities on sandbox</span>
          </li>
          <li class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
            <svg class="w-5 h-5 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            <span>Execute commands (npm, pip, composer, etc.)</span>
          </li>
          <li class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
            <svg class="w-5 h-5 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            <span>Run scripts and build projects</span>
          </li>
          <li class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
            <svg class="w-5 h-5 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            <span>Priority support &amp; faster responses</span>
          </li>
        </ul>
      </div>
      <!-- Actions -->
      <div class="p-4 flex justify-end gap-3 bg-gray-50 dark:bg-gray-800/50 rounded-b-xl border-t border-gray-200 dark:border-gray-700">
        <button onclick="closeUpgradeModal()" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
          Maybe Later
        </button>
        <a href="/upgrade" class="px-5 py-2 text-sm font-medium text-white bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 rounded-lg transition-all shadow-lg shadow-indigo-500/25 flex items-center gap-2">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
          </svg>
          Upgrade Now
        </a>
      </div>
    </div>
  </div>
</div>

<!-- Register Required Modal (Visitor Limit) -->
<div id="register-modal" class="fixed inset-0 z-[110] hidden">
  <!-- Backdrop -->
  <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeRegisterModal()"></div>
  <!-- Modal Content -->
  <div class="absolute inset-0 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-md w-full transform transition-all scale-95 opacity-0" id="register-modal-content">
      <!-- Header with gradient -->
      <div class="p-6 bg-gradient-to-r from-teal-500 via-cyan-500 to-blue-500 rounded-t-xl">
        <div class="flex items-center gap-4">
          <div class="w-14 h-14 rounded-full bg-white/20 flex items-center justify-center flex-shrink-0">
            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
            </svg>
          </div>
          <div>
            <h3 class="text-xl font-bold text-white">Free Limit Reached</h3>
            <p class="text-white/80 text-sm mt-1">Create an account to continue</p>
          </div>
        </div>
      </div>
      <!-- Content -->
      <div class="p-5">
        <p id="register-modal-message" class="text-gray-600 dark:text-gray-300 mb-4">
          You've used all 5 free messages this hour. Register for free to unlock unlimited conversations with Ginto!
        </p>
        <!-- Benefits -->
        <div class="bg-gradient-to-r from-teal-50 to-cyan-50 dark:from-teal-900/20 dark:to-cyan-900/20 rounded-lg p-4 mb-4 border border-teal-200 dark:border-teal-800">
          <div class="flex items-center gap-2 mb-2">
            <span class="text-lg font-bold text-teal-600 dark:text-teal-400">FREE</span>
            <span class="bg-teal-100 dark:bg-teal-900/50 text-teal-700 dark:text-teal-300 text-xs font-semibold px-2.5 py-1 rounded-full">NO CREDIT CARD</span>
          </div>
          <p class="text-sm text-gray-500 dark:text-gray-400">Create an account in seconds and continue chatting!</p>
        </div>
        <!-- Features -->
        <ul class="space-y-2 mb-5">
          <li class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
            <svg class="w-5 h-5 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            <span>Long conversations with Ginto AI</span>
          </li>
          <li class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
            <svg class="w-5 h-5 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            <span>Save and sync your chat history</span>
          </li>
          <li class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
            <svg class="w-5 h-5 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            <span>Agentic & tools feature</span>
          </li>
          <li class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
            <svg class="w-5 h-5 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            <span>Personal sandbox environment</span>
          </li>
          <li class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
            <svg class="w-5 h-5 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            <span>Access to all free features</span>
          </li>
        </ul>
      </div>
      <!-- Actions -->
      <div class="p-4 flex justify-end gap-3 bg-gray-50 dark:bg-gray-800/50 rounded-b-xl border-t border-gray-200 dark:border-gray-700">
        <button onclick="closeRegisterModal()" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
          Maybe Later
        </button>
        <a href="/register" class="px-5 py-2 text-sm font-medium text-white bg-gradient-to-r from-teal-500 to-cyan-500 hover:from-teal-600 hover:to-cyan-600 rounded-lg transition-all shadow-lg shadow-teal-500/25 flex items-center gap-2">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
          </svg>
          Register Free
        </a>
      </div>
    </div>
  </div>
</div>

<!-- Agentic Features Modal (Visitor trying sandbox features) -->
<div id="agentic-modal" class="fixed inset-0 z-[110] hidden">
  <!-- Backdrop -->
  <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeAgenticModal()"></div>
  <!-- Modal Content -->
  <div class="absolute inset-0 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-lg w-full transform transition-all scale-95 opacity-0" id="agentic-modal-content">
      <!-- Header with gradient -->
      <div class="p-6 bg-gradient-to-r from-purple-600 via-indigo-600 to-blue-600 rounded-t-xl">
        <div class="flex items-center gap-4">
          <div class="w-14 h-14 rounded-full bg-white/20 flex items-center justify-center flex-shrink-0">
            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
          </div>
          <div>
            <h3 class="text-xl font-bold text-white">Unlock Your Personal Workspace</h3>
            <p class="text-white/80 text-sm mt-1">Create a free account to get started</p>
          </div>
        </div>
      </div>
      <!-- Content -->
      <div class="p-5">
        <p id="agentic-modal-message" class="text-gray-600 dark:text-gray-300 mb-4">
          To use file management and agentic features, you'll need a free account. Here's what you'll unlock:
        </p>
        <!-- Agentic Features -->
        <div class="space-y-3 mb-5">
          <div class="flex items-start gap-3 p-3 bg-gradient-to-r from-purple-50 to-indigo-50 dark:from-purple-900/20 dark:to-indigo-900/20 rounded-lg border border-purple-200 dark:border-purple-800">
            <div class="w-10 h-10 rounded-lg bg-purple-100 dark:bg-purple-900/50 flex items-center justify-center flex-shrink-0">
              <svg class="w-5 h-5 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
              </svg>
            </div>
            <div>
              <h4 class="font-semibold text-gray-900 dark:text-white text-sm">Personal File System</h4>
              <p class="text-xs text-gray-500 dark:text-gray-400">Create, edit, and manage files in your own private sandbox environment</p>
            </div>
          </div>
          <div class="flex items-start gap-3 p-3 bg-gradient-to-r from-blue-50 to-cyan-50 dark:from-blue-900/20 dark:to-cyan-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
            <div class="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/50 flex items-center justify-center flex-shrink-0">
              <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
              </svg>
            </div>
            <div>
              <h4 class="font-semibold text-gray-900 dark:text-white text-sm">Document Creation</h4>
              <p class="text-xs text-gray-500 dark:text-gray-400">Generate PDFs, Word docs, and other documents with AI assistance</p>
            </div>
          </div>
          <div class="flex items-start gap-3 p-3 bg-gradient-to-r from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20 rounded-lg border border-green-200 dark:border-green-800">
            <div class="w-10 h-10 rounded-lg bg-green-100 dark:bg-green-900/50 flex items-center justify-center flex-shrink-0">
              <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
              </svg>
            </div>
            <div>
              <h4 class="font-semibold text-gray-900 dark:text-white text-sm">Project Scaffolding</h4>
              <p class="text-xs text-gray-500 dark:text-gray-400">Instantly create React, Vue, PHP, Python, and other project templates</p>
            </div>
          </div>
          <div class="flex items-start gap-3 p-3 bg-gradient-to-r from-amber-50 to-orange-50 dark:from-amber-900/20 dark:to-orange-900/20 rounded-lg border border-amber-200 dark:border-amber-800">
            <div class="w-10 h-10 rounded-lg bg-amber-100 dark:bg-amber-900/50 flex items-center justify-center flex-shrink-0">
              <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
              </svg>
            </div>
            <div>
              <h4 class="font-semibold text-gray-900 dark:text-white text-sm">Code Execution</h4>
              <p class="text-xs text-gray-500 dark:text-gray-400">Run scripts, preview websites, and execute commands in your sandbox</p>
            </div>
          </div>
        </div>
        <!-- Free badge -->
        <div class="bg-gradient-to-r from-teal-50 to-cyan-50 dark:from-teal-900/20 dark:to-cyan-900/20 rounded-lg p-3 mb-4 border border-teal-200 dark:border-teal-800 flex items-center justify-between">
          <div class="flex items-center gap-2">
            <span class="text-lg font-bold text-teal-600 dark:text-teal-400">100% FREE</span>
            <span class="bg-teal-100 dark:bg-teal-900/50 text-teal-700 dark:text-teal-300 text-xs font-semibold px-2.5 py-1 rounded-full">NO CREDIT CARD</span>
          </div>
          <span class="text-xs text-gray-500 dark:text-gray-400">Takes 30 seconds</span>
        </div>
      </div>
      <!-- Actions -->
      <div class="p-4 flex justify-end gap-3 bg-gray-50 dark:bg-gray-800/50 rounded-b-xl border-t border-gray-200 dark:border-gray-700">
        <button onclick="closeAgenticModal()" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
          Maybe Later
        </button>
        <a href="/register" class="px-5 py-2 text-sm font-medium text-white bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 rounded-lg transition-all shadow-lg shadow-purple-500/25 flex items-center gap-2">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
          </svg>
          Get Started Free
        </a>
      </div>
    </div>
  </div>
</div>

<!-- Image Viewer Modal -->
<div id="image-modal" class="fixed inset-0 z-[115] hidden">
  <!-- Backdrop -->
  <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" onclick="closeImageModal()"></div>
  <!-- Modal Content -->
  <div class="absolute inset-0 flex items-center justify-center p-4">
    <div class="relative max-w-4xl max-h-[90vh] w-full transform transition-all scale-95 opacity-0" id="image-modal-content">
      <!-- Top controls -->
      <div class="absolute -top-10 right-0 flex items-center gap-3">
        <!-- Zoom controls -->
        <div class="flex items-center gap-1 bg-black/50 rounded-lg px-2 py-1">
          <button onclick="zoomImage(-0.25)" class="text-white/80 hover:text-white transition-colors p-1" title="Zoom out">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM13 10H7"/>
            </svg>
          </button>
          <span id="image-zoom-level" class="text-white/80 text-sm min-w-[3rem] text-center">100%</span>
          <button onclick="zoomImage(0.25)" class="text-white/80 hover:text-white transition-colors p-1" title="Zoom in">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m3-3H7"/>
            </svg>
          </button>
          <button onclick="resetZoom()" class="text-white/80 hover:text-white transition-colors p-1 ml-1 border-l border-white/20 pl-2" title="Reset zoom">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
          </button>
        </div>
        <!-- Download button -->
        <a id="image-modal-download" href="" download="generated-image.png" class="flex items-center gap-1 bg-black/50 hover:bg-black/70 rounded-lg px-3 py-1.5 text-white/80 hover:text-white transition-colors" title="Download image">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
          </svg>
          <span class="text-sm">Download</span>
        </a>
        <!-- Open in new tab button -->
        <a id="image-modal-newtab" href="" target="_blank" class="flex items-center gap-1 bg-black/50 hover:bg-black/70 rounded-lg px-3 py-1.5 text-white/80 hover:text-white transition-colors" title="Open in new tab">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
          </svg>
          <span class="text-sm">Open</span>
        </a>
        <!-- Close button -->
        <button onclick="closeImageModal()" class="text-white/80 hover:text-white transition-colors">
          <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>
      <!-- Image container -->
      <div class="bg-gray-900 rounded-xl overflow-auto shadow-2xl max-h-[85vh]" id="image-modal-container">
        <img id="image-modal-img" src="" alt="Full size image" class="mx-auto object-contain transition-transform duration-150" style="transform-origin: center center;">
      </div>
    </div>
  </div>
</div>

<!-- TTS Rate Limit Info Modal -->
<div id="tts-limit-modal" class="fixed inset-0 z-[110] hidden">
  <!-- Backdrop -->
  <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeTtsLimitModal()"></div>
  <!-- Modal Content -->
  <div class="absolute inset-0 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-md w-full transform transition-all scale-95 opacity-0" id="tts-limit-modal-content">
      <!-- Header -->
      <div class="p-5 border-b border-gray-200 dark:border-gray-700">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/>
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2"/>
            </svg>
          </div>
          <div>
            <h3 id="tts-limit-modal-title" class="text-lg font-semibold text-gray-900 dark:text-white">Text-to-Speech Limit Reached</h3>
            <p id="tts-limit-modal-message" class="text-sm text-gray-500 dark:text-gray-400 mt-1">You've reached your TTS limit for this session.</p>
          </div>
        </div>
      </div>
      <!-- Content area for extra message -->
      <div id="tts-limit-modal-extra" class="px-5 py-3 text-sm text-gray-600 dark:text-gray-300 hidden"></div>
      <!-- Actions - will be filled dynamically -->
      <div id="tts-limit-modal-actions" class="p-4 flex justify-end gap-3 bg-gray-50 dark:bg-gray-800/50 rounded-b-xl">
        <button onclick="closeTtsLimitModal()" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
          Got it
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Session Expired Modal (for visitors) -->
<div id="session-expired-modal" class="fixed inset-0 z-[120] hidden">
  <!-- Backdrop -->
  <div class="absolute inset-0 bg-black/70 backdrop-blur-sm"></div>
  <!-- Modal Content -->
  <div class="absolute inset-0 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-md w-full transform transition-all scale-95 opacity-0" id="session-expired-modal-content">
      <!-- Header -->
      <div class="p-5 border-b border-gray-200 dark:border-gray-700">
        <div class="flex items-center gap-3">
          <div class="w-12 h-12 rounded-full bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center flex-shrink-0">
            <svg class="w-6 h-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
          </div>
          <div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Session Expiring Soon</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">To avoid losing your session, please register or login.</p>
          </div>
        </div>
      </div>
      <!-- Content -->
      <div class="p-5 space-y-4">
        <div class="bg-amber-50 dark:bg-amber-900/20 rounded-lg p-4 border border-amber-200 dark:border-amber-800">
          <p class="text-sm text-amber-800 dark:text-amber-200">
            <strong>⏰ Important:</strong> Your sandbox will remain active only for <strong>one hour</strong> while you're not logged in. 
            Register or log in to keep your sandbox and files permanently.
          </p>
        </div>
        
        <div class="bg-indigo-50 dark:bg-indigo-900/20 rounded-lg p-4">
          <p class="font-medium text-indigo-700 dark:text-indigo-300">🎁 Benefits of a free account:</p>
          <ul class="text-sm mt-2 text-indigo-600 dark:text-indigo-400 space-y-1">
            <li>• Your sandbox is saved permanently</li>
            <li>• Access your files from any device</li>
            <li>• More AI requests and features</li>
          </ul>
        </div>
      </div>
      <!-- Actions -->
      <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-b-xl flex justify-end gap-3">
        <button onclick="closeSessionExpiredModal()" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
          Maybe Later
        </button>
        <a href="/login" class="px-4 py-2 text-sm font-medium text-indigo-600 dark:text-indigo-400 bg-white dark:bg-gray-700 border border-indigo-300 dark:border-indigo-600 rounded-lg hover:bg-indigo-50 dark:hover:bg-gray-600 transition-colors inline-flex items-center gap-2">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
          </svg>
          Login
        </a>
        <a href="/register" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition-colors inline-flex items-center gap-2">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
          </svg>
          Register Free
        </a>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/sandbox-wizard-modal.php'; ?>
