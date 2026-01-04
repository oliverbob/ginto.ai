<?php
/**
 * Example Prompts Loading and Rendering Scripts
 */
?>
<script>
  // ========================================
  // Example Prompts for Welcome Screen
  // ========================================
  const examplePromptsContainer = document.getElementById('example-prompts');
  
  async function renderPrompts(prompts) {
    if (!examplePromptsContainer) return;
    
    if (!prompts || prompts.length === 0) {
      examplePromptsContainer.innerHTML = '<p class="text-gray-500 dark:text-gray-400 text-sm">No example prompts available.</p>';
      return;
    }
    
    // Clear and render
    examplePromptsContainer.innerHTML = '';
    
    prompts.forEach(prompt => {
      const card = document.createElement('div');
      card.className = 'prompt-card bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4 cursor-pointer hover:shadow-md transition-shadow';
      card.innerHTML = `
        <div class="flex items-start gap-3">
          <div class="flex-shrink-0 w-10 h-10 bg-indigo-100 dark:bg-indigo-900/30 rounded-lg flex items-center justify-center">
            <i class="${prompt.icon || 'fas fa-lightbulb'} text-indigo-600 dark:text-indigo-400"></i>
          </div>
          <div class="flex-1 min-w-0">
            <h4 class="text-sm font-medium text-gray-900 dark:text-white truncate">${escapeHtml(prompt.title || 'Untitled')}</h4>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 line-clamp-2">${escapeHtml(prompt.description || '')}</p>
          </div>
        </div>
      `;
      
      card.addEventListener('click', () => {
        // Set the prompt text in the input
        const chatInput = document.getElementById('chat-input');
        if (chatInput) {
          chatInput.value = prompt.prompt || prompt.title || '';
          chatInput.focus();
          // Trigger input event for auto-resize
          chatInput.dispatchEvent(new Event('input', { bubbles: true }));
        }
      });
      
      examplePromptsContainer.appendChild(card);
    });
  }
  
  async function loadPrompts() {
    try {
      // Use config endpoint or static prompts
      const response = await fetch('/api/config/prompts');
      if (response.ok) {
        const data = await response.json();
        if (data.prompts && Array.isArray(data.prompts)) {
          renderPrompts(data.prompts);
          return;
        }
      }
    } catch (e) {
      console.log('[Prompts] Could not load from API, using defaults');
    }
    
    // Default prompts if API fails
    const defaultPrompts = [
      {
        icon: 'fas fa-code',
        title: 'Write a Python script',
        description: 'Create a Python script with functions and classes',
        prompt: 'Write a Python script that...'
      },
      {
        icon: 'fas fa-bug',
        title: 'Debug my code',
        description: 'Help me find and fix bugs in my code',
        prompt: 'Help me debug this code:\n\n```\n// paste your code here\n```'
      },
      {
        icon: 'fas fa-book',
        title: 'Explain a concept',
        description: 'Get a clear explanation of programming concepts',
        prompt: 'Explain the concept of...'
      },
      {
        icon: 'fas fa-rocket',
        title: 'Build a project',
        description: 'Get step-by-step guidance for building something',
        prompt: 'Help me build a project that...'
      }
    ];
    renderPrompts(defaultPrompts);
  }
  
  // Helper function to escape HTML
  function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }
  
  // Load prompts on page load if container exists
  if (examplePromptsContainer) {
    loadPrompts();
  }
</script>
