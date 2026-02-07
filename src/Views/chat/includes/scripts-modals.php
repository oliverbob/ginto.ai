<?php
/**
 * Modal Scripts - Confirm, Upgrade, Register, Agentic, Image, TTS Limit, Session Expired
 */
?>
<script>
  // ============= Universal Confirmation Modal =============
  let confirmModalCallback = null;
  const AUTO_APPROVE_TOOLS_KEY = 'ginto_auto_approve_tools';
  
  function showConfirmModal(options) {
    // Check for auto-approve for tool execution
    if (options.showAutoApprove && localStorage.getItem(AUTO_APPROVE_TOOLS_KEY) === '1') {
      return Promise.resolve(true);
    }
    
    const modal = document.getElementById('confirm-modal');
    const content = document.getElementById('confirm-modal-content');
    const title = document.getElementById('confirm-modal-title');
    const message = document.getElementById('confirm-modal-message');
    const actionBtn = document.getElementById('confirm-modal-action');
    const iconContainer = document.getElementById('confirm-modal-icon');
    const autoApproveContainer = document.getElementById('confirm-modal-auto-approve');
    const autoApproveCheckbox = document.getElementById('confirm-modal-auto-approve-checkbox');
    
    if (!modal) return Promise.resolve(false);
    
    // Set content
    title.textContent = options.title || 'Confirm Action';
    message.textContent = options.message || 'Are you sure you want to proceed?';
    actionBtn.textContent = options.confirmText || 'Confirm';
    
    // Show/hide auto-approve checkbox
    if (options.showAutoApprove && autoApproveContainer && autoApproveCheckbox) {
      autoApproveContainer.classList.remove('hidden');
      autoApproveCheckbox.checked = false;
    } else if (autoApproveContainer) {
      autoApproveContainer.classList.add('hidden');
    }
    
    // Set button color based on type
    const type = options.type || 'danger';
    actionBtn.className = `px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors ${
      type === 'danger' ? 'bg-red-600 hover:bg-red-700' :
      type === 'warning' ? 'bg-amber-600 hover:bg-amber-700' :
      'bg-indigo-600 hover:bg-indigo-700'
    }`;
    
    // Set icon based on type
    iconContainer.className = `w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 ${
      type === 'danger' ? 'bg-red-100 dark:bg-red-900/30' :
      type === 'warning' ? 'bg-amber-100 dark:bg-amber-900/30' :
      'bg-indigo-100 dark:bg-indigo-900/30'
    }`;
    iconContainer.innerHTML = type === 'danger' 
      ? `<svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
           <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
         </svg>`
      : type === 'warning'
        ? `<svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
             <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
           </svg>`
        : `<svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
             <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
           </svg>`;
    
    // Show modal
    modal.classList.remove('hidden');
    requestAnimationFrame(() => {
      content.classList.remove('scale-95', 'opacity-0');
      content.classList.add('scale-100', 'opacity-100');
    });
    
    // Return promise that resolves when user makes a choice
    return new Promise((resolve) => {
      confirmModalCallback = resolve;
      
      // Set up action button click
      actionBtn.onclick = () => {
        // Save auto-approve preference if checkbox is checked
        if (options.showAutoApprove && autoApproveCheckbox && autoApproveCheckbox.checked) {
          localStorage.setItem(AUTO_APPROVE_TOOLS_KEY, '1');
        }
        confirmModalCallback = null; // Clear callback before closing to prevent double-resolve
        closeConfirmModal();
        resolve(true);
      };
    });
  }
  
  function closeConfirmModal() {
    const modal = document.getElementById('confirm-modal');
    const content = document.getElementById('confirm-modal-content');
    
    if (!modal) return;
    
    content.classList.remove('scale-100', 'opacity-100');
    content.classList.add('scale-95', 'opacity-0');
    
    setTimeout(() => {
      modal.classList.add('hidden');
      if (confirmModalCallback) {
        confirmModalCallback(false);
        confirmModalCallback = null;
      }
    }, 150);
  }
  
  // ============= Premium Upgrade Modal =============
  function showUpgradeModal(message) {
    const modal = document.getElementById('upgrade-modal');
    const content = document.getElementById('upgrade-modal-content');
    const messageEl = document.getElementById('upgrade-modal-message');
    
    if (!modal) return;
    
    if (message && messageEl) {
      messageEl.textContent = message;
    }
    
    modal.classList.remove('hidden');
    requestAnimationFrame(() => {
      content.classList.remove('scale-95', 'opacity-0');
      content.classList.add('scale-100', 'opacity-100');
    });
  }
  
  function closeUpgradeModal() {
    const modal = document.getElementById('upgrade-modal');
    const content = document.getElementById('upgrade-modal-content');
    
    if (!modal) return;
    
    content.classList.remove('scale-100', 'opacity-100');
    content.classList.add('scale-95', 'opacity-0');
    
    setTimeout(() => {
      modal.classList.add('hidden');
    }, 150);
  }
  
  // Make showUpgradeModal available globally for chat.js
  window.showUpgradeModal = showUpgradeModal;
  
  // ============= Register Required Modal (Visitor Limit) =============
  function showRegisterModal(message) {
    const modal = document.getElementById('register-modal');
    const content = document.getElementById('register-modal-content');
    const messageEl = document.getElementById('register-modal-message');
    
    if (!modal) return;
    
    if (message && messageEl) {
      messageEl.textContent = message;
    }
    
    modal.classList.remove('hidden');
    requestAnimationFrame(() => {
      content.classList.remove('scale-95', 'opacity-0');
      content.classList.add('scale-100', 'opacity-100');
    });
  }
  
  function closeRegisterModal() {
    const modal = document.getElementById('register-modal');
    const content = document.getElementById('register-modal-content');
    
    if (!modal) return;
    
    content.classList.remove('scale-100', 'opacity-100');
    content.classList.add('scale-95', 'opacity-0');
    
    setTimeout(() => {
      modal.classList.add('hidden');
    }, 150);
  }
  
  // Make showRegisterModal available globally for chat.js
  window.showRegisterModal = showRegisterModal;
  
  // ============= Agentic Features Modal (Visitor Sandbox) =============
  function showAgenticModal(message) {
    const modal = document.getElementById('agentic-modal');
    const content = document.getElementById('agentic-modal-content');
    const messageEl = document.getElementById('agentic-modal-message');
    
    if (!modal) return;
    
    if (message && messageEl) {
      messageEl.textContent = message;
    }
    
    modal.classList.remove('hidden');
    requestAnimationFrame(() => {
      content.classList.remove('scale-95', 'opacity-0');
      content.classList.add('scale-100', 'opacity-100');
    });
  }
  
  function closeAgenticModal() {
    const modal = document.getElementById('agentic-modal');
    const content = document.getElementById('agentic-modal-content');
    
    if (!modal) return;
    
    content.classList.remove('scale-100', 'opacity-100');
    content.classList.add('scale-95', 'opacity-0');
    
    setTimeout(() => {
      modal.classList.add('hidden');
    }, 150);
  }
  
  // Make showAgenticModal available globally for chat.js
  window.showAgenticModal = showAgenticModal;
  
  // ============= Image Viewer Modal =============
  function showImageModal(imageSrc) {
    const modal = document.getElementById('image-modal');
    const content = document.getElementById('image-modal-content');
    const img = document.getElementById('image-modal-img');
    const downloadLink = document.getElementById('image-modal-download');
    const newtabLink = document.getElementById('image-modal-newtab');
    
    if (!modal || !img) return;
    
    img.src = imageSrc;
    if (downloadLink) downloadLink.href = imageSrc;
    if (newtabLink) newtabLink.href = imageSrc;
    
    modal.classList.remove('hidden');
    requestAnimationFrame(() => {
      content.classList.remove('scale-95', 'opacity-0');
      content.classList.add('scale-100', 'opacity-100');
    });
  }
  
  // Image zoom state
  let currentZoom = 1;
  
  function closeImageModal() {
    const modal = document.getElementById('image-modal');
    const content = document.getElementById('image-modal-content');
    
    if (!modal) return;
    
    content.classList.remove('scale-100', 'opacity-100');
    content.classList.add('scale-95', 'opacity-0');
    
    setTimeout(() => {
      modal.classList.add('hidden');
      document.getElementById('image-modal-img').src = '';
      resetZoom();
    }, 150);
  }
  
  function zoomImage(delta) {
    const img = document.getElementById('image-modal-img');
    const zoomLabel = document.getElementById('image-zoom-level');
    if (!img) return;
    
    currentZoom = Math.max(0.25, Math.min(4, currentZoom + delta));
    img.style.transform = `scale(${currentZoom})`;
    zoomLabel.textContent = Math.round(currentZoom * 100) + '%';
  }
  
  function resetZoom() {
    const img = document.getElementById('image-modal-img');
    const zoomLabel = document.getElementById('image-zoom-level');
    if (!img) return;
    
    currentZoom = 1;
    img.style.transform = 'scale(1)';
    zoomLabel.textContent = '100%';
  }
  
  // Make it globally accessible
  window.showImageModal = showImageModal;
  window.closeImageModal = closeImageModal;
  window.zoomImage = zoomImage;
  window.resetZoom = resetZoom;
  
  // Close modal on Escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !document.getElementById('confirm-modal').classList.contains('hidden')) {
      closeConfirmModal();
    }
    if (e.key === 'Escape' && !document.getElementById('tts-limit-modal').classList.contains('hidden')) {
      closeTtsLimitModal();
    }
    if (e.key === 'Escape' && !document.getElementById('image-modal').classList.contains('hidden')) {
      closeImageModal();
    }
  });
  
  // ============= TTS Rate Limit Modal =============
  // Global flag to track if TTS limit modal has been shown this session
  window.ttsLimitModalShown = false;
  
  /**
   * Show the TTS rate limit modal with appropriate message/actions based on user role
   * @param {object} data - Rate limit data from server
   *   data.user_role: 'visitor', 'user', or 'admin'
   *   data.reason: 'visitor_session_limit', 'user_hourly_limit', 'org_rpm_threshold', etc.
   *   data.message: Server-provided message
   */
  function showTtsLimitModal(data) {
    // Only show once per session to avoid spamming
    if (window.ttsLimitModalShown) return;
    window.ttsLimitModalShown = true;
    
    const modal = document.getElementById('tts-limit-modal');
    const content = document.getElementById('tts-limit-modal-content');
    const title = document.getElementById('tts-limit-modal-title');
    const message = document.getElementById('tts-limit-modal-message');
    const extra = document.getElementById('tts-limit-modal-extra');
    const actions = document.getElementById('tts-limit-modal-actions');
    
    if (!modal) return;
    
    const userRole = data.user_role || 'visitor';
    const reason = data.reason || 'unknown';
    
    // Set title and message
    title.textContent = 'Text-to-Speech Limit Reached';
    message.textContent = data.message || 'You\'ve reached your TTS limit.';
    
    // Build actions based on user role
    if (userRole === 'visitor') {
      // Visitor: prompt to register
      extra.innerHTML = `
        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 mt-2 mb-2">
          <p class="text-sm text-gray-600 dark:text-gray-300">💬 You can still continue chatting with AI - only voice playback is paused.</p>
        </div>
        <div class="bg-indigo-50 dark:bg-indigo-900/20 rounded-lg p-3">
          <p class="font-medium text-indigo-700 dark:text-indigo-300">🎁 Get more with a free account!</p>
          <p class="text-xs mt-1 text-indigo-600 dark:text-indigo-400">Registered users get 30 TTS requests per hour, plus more features.</p>
        </div>
      `;
      extra.classList.remove('hidden');
      
      actions.innerHTML = `
        <button onclick="closeTtsLimitModal()" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
          Maybe Later
        </button>
        <a href="/register" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition-colors inline-flex items-center gap-2">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
          </svg>
          Register Free
        </a>
      `;
    } else if (userRole === 'user') {
      // Logged-in user: prompt to upgrade or collaborate
      extra.innerHTML = `
        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 mt-2 mb-2">
          <p class="text-sm text-gray-600 dark:text-gray-300">💬 You can still continue chatting with AI - only voice playback is paused.</p>
        </div>
        <div class="bg-amber-50 dark:bg-amber-900/20 rounded-lg p-3">
          <p class="font-medium text-amber-700 dark:text-amber-300">⚡ Need more TTS capacity?</p>
          <p class="text-xs mt-1 text-amber-600 dark:text-amber-400">Upgrade your plan or contact us about custom limits as collaborators or partners.</p>
        </div>
      `;
      extra.classList.remove('hidden');
      
      actions.innerHTML = `
        <button onclick="closeTtsLimitModal()" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
          Got it
        </button>
        <a href="/contact?subject=TTS%20Upgrade" class="px-4 py-2 text-sm font-medium text-white bg-amber-600 hover:bg-amber-700 rounded-lg transition-colors inline-flex items-center gap-2">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
          </svg>
          Contact Us
        </a>
      `;
    } else {
      // Admin or org limit: just informational
      extra.innerHTML = `
        <div class="bg-gray-100 dark:bg-gray-700/50 rounded-lg p-3 mt-2">
          <p class="text-xs text-gray-500 dark:text-gray-400">TTS will automatically resume when capacity is available. You can still use text chat normally.</p>
        </div>
      `;
      extra.classList.remove('hidden');
      
      actions.innerHTML = `
        <button onclick="closeTtsLimitModal()" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition-colors">
          Got it
        </button>
      `;
    }
    
    // Show modal
    modal.classList.remove('hidden');
    requestAnimationFrame(() => {
      content.classList.remove('scale-95', 'opacity-0');
      content.classList.add('scale-100', 'opacity-100');
    });
  }
  
  function closeTtsLimitModal() {
    const modal = document.getElementById('tts-limit-modal');
    const content = document.getElementById('tts-limit-modal-content');
    
    if (!modal) return;
    
    content.classList.remove('scale-100', 'opacity-100');
    content.classList.add('scale-95', 'opacity-0');
    
    setTimeout(() => {
      modal.classList.add('hidden');
    }, 150);
  }
  
  // Expose globally so chat.js can call it
  window.showTtsLimitModal = showTtsLimitModal;
  window.closeTtsLimitModal = closeTtsLimitModal;
  
  // ============= Session Expired Modal (Visitors) =============
  // Global flag to track if session expired modal has been shown
  window.sessionExpiredModalShown = false;
  
  /**
   * Show the session expired modal to visitors when their CSRF token is about to expire
   */
  function showSessionExpiredModal() {
    // Only show once per session
    if (window.sessionExpiredModalShown) return;
    // Only show to visitors (non-logged-in users)
    if (window.GINTO_AUTH && window.GINTO_AUTH.userId) return;
    
    window.sessionExpiredModalShown = true;
    
    const modal = document.getElementById('session-expired-modal');
    const content = document.getElementById('session-expired-modal-content');
    
    if (!modal) return;
    
    // Show modal
    modal.classList.remove('hidden');
    requestAnimationFrame(() => {
      content.classList.remove('scale-95', 'opacity-0');
      content.classList.add('scale-100', 'opacity-100');
    });
  }
  
  function closeSessionExpiredModal() {
    const modal = document.getElementById('session-expired-modal');
    const content = document.getElementById('session-expired-modal-content');
    
    if (!modal) return;
    
    content.classList.remove('scale-100', 'opacity-100');
    content.classList.add('scale-95', 'opacity-0');
    
    setTimeout(() => {
      modal.classList.add('hidden');
    }, 150);
  }
  
  // Expose globally
  window.showSessionExpiredModal = showSessionExpiredModal;
  window.closeSessionExpiredModal = closeSessionExpiredModal;

  // ============= Login Required Modal =============
  function createConfetti() {
    const container = document.getElementById('confetti-container');
    if (!container) return;
    container.innerHTML = '';
    const colors = ['#a855f7', '#8b5cf6', '#6366f1', '#ec4899', '#f472b6', '#fbbf24', '#34d399'];
    for (let i = 0; i < 50; i++) {
      const confetti = document.createElement('div');
      const color = colors[Math.floor(Math.random() * colors.length)];
      const size = Math.random() * 10 + 5;
      const left = Math.random() * 100;
      const delay = Math.random() * 2;
      const duration = Math.random() * 2 + 2;
      confetti.style.cssText = `
        position: absolute;
        width: ${size}px;
        height: ${size}px;
        background: ${color};
        left: ${left}%;
        top: -20px;
        border-radius: ${Math.random() > 0.5 ? '50%' : '2px'};
        animation: confettiFall ${duration}s ease-out ${delay}s forwards;
        opacity: 0;
      `;
      container.appendChild(confetti);
    }
  }

  // Add confetti animation styles
  if (!document.getElementById('confetti-styles')) {
    const style = document.createElement('style');
    style.id = 'confetti-styles';
    style.textContent = `
      @keyframes confettiFall {
        0% { transform: translateY(0) rotate(0deg); opacity: 1; }
        100% { transform: translateY(100vh) rotate(720deg); opacity: 0; }
      }
    `;
    document.head.appendChild(style);
  }

  // Create rain animation for Sign In variant
  function createRain() {
    const container = document.getElementById('rain-container');
    if (!container) return;
    
    container.innerHTML = '';
    const numDrops = 100;
    
    for (let i = 0; i < numDrops; i++) {
      const drop = document.createElement('div');
      drop.className = 'rain-drop';
      drop.style.cssText = `
        position: absolute;
        width: 2px;
        height: ${15 + Math.random() * 20}px;
        background: linear-gradient(to bottom, rgba(100, 200, 255, 0), rgba(100, 200, 255, 0.6));
        left: ${Math.random() * 100}%;
        top: -30px;
        animation: rain-fall ${0.5 + Math.random() * 0.5}s linear infinite;
        animation-delay: ${Math.random() * 2}s;
        opacity: ${0.3 + Math.random() * 0.4};
      `;
      container.appendChild(drop);
    }
    
    // Add rain animation style if not exists
    if (!document.getElementById('rain-style')) {
      const style = document.createElement('style');
      style.id = 'rain-style';
      style.textContent = `
        @keyframes rain-fall {
          0% { transform: translateY(-30px); }
          100% { transform: translateY(100vh); }
        }
      `;
      document.head.appendChild(style);
    }
  }

  function showLoginRequiredModal() {
    const modal = document.getElementById('login-required-modal');
    const headerCongrats = document.getElementById('login-header-congrats');
    const headerSignin = document.getElementById('login-header-signin');
    const confettiContainer = document.getElementById('confetti-container');
    const rainContainer = document.getElementById('rain-container');
    
    if (!modal) return;
    
    // Check if anyone has ever logged in before
    // Congrats = first-time login after fresh activation (no one logged in yet)
    // Sign In = returning user (someone has logged in before)
    const anyoneLoggedIn = window.GINTO_SETUP?.anyoneLoggedIn || false;
    
    if (anyoneLoggedIn) {
      // Show Sign In variant with rain (returning users)
      headerCongrats?.classList.add('hidden');
      headerSignin?.classList.remove('hidden');
      confettiContainer?.classList.add('hidden');
      rainContainer?.classList.remove('hidden');
      createRain();
    } else {
      // Show Congrats variant with confetti (first-time login after fresh install)
      headerCongrats?.classList.remove('hidden');
      headerSignin?.classList.add('hidden');
      confettiContainer?.classList.remove('hidden');
      rainContainer?.classList.add('hidden');
      createConfetti();
    }
    
    modal.classList.remove('hidden');
    
    // Focus on identifier field
    setTimeout(() => {
      document.getElementById('login-modal-identifier')?.focus();
    }, 100);
  }
  
  function closeLoginRequiredModal() {
    const modal = document.getElementById('login-required-modal');
    if (modal) {
      modal.classList.add('hidden');
      // Clear form and effects
      document.getElementById('login-modal-form')?.reset();
      document.getElementById('login-modal-error')?.classList.add('hidden');
      const confettiContainer = document.getElementById('confetti-container');
      if (confettiContainer) confettiContainer.innerHTML = '';
      const rainContainer = document.getElementById('rain-container');
      if (rainContainer) rainContainer.innerHTML = '';
    }
  }
  
  function toggleLoginPasswordVisibility() {
    const input = document.getElementById('login-modal-password');
    const eyeOpen = document.getElementById('login-eye-open');
    const eyeClosed = document.getElementById('login-eye-closed');
    if (input && eyeOpen && eyeClosed) {
      if (input.type === 'password') {
        input.type = 'text';
        eyeOpen.classList.add('hidden');
        eyeClosed.classList.remove('hidden');
      } else {
        input.type = 'password';
        eyeOpen.classList.remove('hidden');
        eyeClosed.classList.add('hidden');
      }
    }
  }
  
  // AJAX Login Handler
  document.getElementById('login-modal-form')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const form = e.target;
    const submitBtn = document.getElementById('login-modal-submit');
    const errorDiv = document.getElementById('login-modal-error');
    const identifier = document.getElementById('login-modal-identifier')?.value?.trim();
    const password = document.getElementById('login-modal-password')?.value;
    const csrfToken = form.querySelector('input[name="csrf_token"]')?.value;
    
    // Clear previous errors
    errorDiv?.classList.add('hidden');
    
    // Validate
    if (!identifier || !password) {
      errorDiv.textContent = 'Please enter your email/username and password.';
      errorDiv?.classList.remove('hidden');
      return;
    }
    
    // Disable button during request
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<svg class="animate-spin h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Signing in...';
    }
    
    try {
      const formData = new FormData();
      formData.append('identifier', identifier);
      formData.append('password', password);
      formData.append('csrf_token', csrfToken);
      formData.append('ajax', '1'); // Signal AJAX request
      
      const response = await fetch('/login', {
        method: 'POST',
        body: formData
      });
      
      const data = await response.json();
      
      if (data.success) {
        // Login successful - reload to get fresh session state
        closeLoginRequiredModal();
        window.location.reload();
      } else {
        // Show error
        errorDiv.textContent = data.error || 'Invalid credentials. Please try again.';
        errorDiv?.classList.remove('hidden');
      }
    } catch (err) {
      errorDiv.textContent = 'An error occurred. Please try again.';
      errorDiv?.classList.remove('hidden');
    } finally {
      // Re-enable button
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<span>Sign In</span><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>';
      }
    }
  });
  
  // Expose globally
  window.showLoginRequiredModal = showLoginRequiredModal;
  window.closeLoginRequiredModal = closeLoginRequiredModal;
  window.toggleLoginPasswordVisibility = toggleLoginPasswordVisibility;

  // ============= Setup Required Modal =============
  function showSetupRequiredModal() {
    const modal = document.getElementById('setup-required-modal');
    if (modal) {
      modal.classList.remove('hidden');
    }
  }
  
  function closeSetupRequiredModal() {
    const modal = document.getElementById('setup-required-modal');
    if (modal) {
      modal.classList.add('hidden');
    }
  }
  
  // Expose globally
  window.showSetupRequiredModal = showSetupRequiredModal;
  window.closeSetupRequiredModal = closeSetupRequiredModal;
</script>
