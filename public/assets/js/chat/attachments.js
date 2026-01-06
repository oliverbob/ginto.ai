/**
 * Chat Attachments Module
 * Image paste/drag-drop/upload handling
 */

// ============ STATE ============
let currentAttachment = null;

// ============ ELEMENTS ============
let attachBtn = null;
let attachInput = null;
let attachPreview = null;
let attachPreviewImg = null;
let attachFilename = null;
let attachRemove = null;

/**
 * Initialize attachment elements
 */
export function initAttachmentElements() {
  attachBtn = document.getElementById('attach-btn');
  attachInput = document.getElementById('attach-input');
  attachPreview = document.getElementById('attach-preview');
  attachPreviewImg = document.getElementById('attach-preview-img');
  attachFilename = document.getElementById('attach-filename');
  attachRemove = document.getElementById('attach-remove');
  
  return {
    attachBtn,
    attachInput,
    attachPreview,
    attachPreviewImg,
    attachFilename,
    attachRemove
  };
}

/**
 * Get current attachment
 */
export function getCurrentAttachment() {
  return currentAttachment;
}

/**
 * Set current attachment
 */
export function setCurrentAttachment(attachment) {
  currentAttachment = attachment;
}

/**
 * Clear current attachment
 */
export function clearAttachment() {
  currentAttachment = null;
  if (attachInput) attachInput.value = '';
  if (attachPreview) attachPreview.classList.add('hidden');
  if (attachPreviewImg) attachPreviewImg.src = '';
  if (attachFilename) attachFilename.textContent = '';
}

/**
 * Process an image file (used by file input, drag/drop, and paste)
 */
export function processImageFile(file, promptEl) {
  if (!file) return;
  
  // Validate it's an image
  if (!file.type.startsWith('image/')) {
    alert('Please select an image file');
    return;
  }
  
  // Check file size (max 20MB for Groq vision)
  if (file.size > 20 * 1024 * 1024) {
    alert('Image too large. Maximum size is 20MB.');
    return;
  }
  
  // Read as base64
  const reader = new FileReader();
  reader.onload = (evt) => {
    currentAttachment = {
      dataUrl: evt.target.result,
      filename: file.name || 'pasted-image.png',
      type: file.type
    };
    
    // Show preview
    if (attachPreviewImg) attachPreviewImg.src = evt.target.result;
    if (attachFilename) attachFilename.textContent = currentAttachment.filename;
    if (attachPreview) attachPreview.classList.remove('hidden');
    
    // Focus prompt for user to type
    promptEl?.focus();
  };
  reader.readAsDataURL(file);
}

/**
 * Upload image to server for persistence
 */
export async function uploadImageToServer(dataUrl) {
  try {
    const res = await fetch('/chat/upload-image', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': window.CSRF_TOKEN || document.getElementById('csrf_token')?.value || ''
      },
      body: JSON.stringify({ image: dataUrl })
    });
    
    if (!res.ok) return null;
    
    const data = await res.json();
    return data?.url || null;
  } catch (e) {
    console.warn('[uploadImageToServer] Failed:', e);
    return null;
  }
}

/**
 * Set up attachment event handlers
 */
export function setupAttachmentHandlers(promptEl) {
  initAttachmentElements();
  
  // Open file picker when attach button is clicked
  attachBtn?.addEventListener('click', () => {
    attachInput?.click();
  });
  
  // Handle file selection from input
  attachInput?.addEventListener('change', (e) => {
    const file = e.target.files?.[0];
    processImageFile(file, promptEl);
  });
  
  // Handle paste (Ctrl+V / Cmd+V) for images
  document.addEventListener('paste', (e) => {
    const activeEl = document.activeElement;
    const isPromptFocused = activeEl === promptEl;
    const isInputFocused = activeEl?.tagName === 'INPUT' || activeEl?.tagName === 'TEXTAREA';
    
    // If an input other than prompt is focused, don't intercept
    if (isInputFocused && !isPromptFocused) return;
    
    const items = e.clipboardData?.items;
    if (!items) return;
    
    for (const item of items) {
      if (item.type.startsWith('image/')) {
        e.preventDefault();
        const file = item.getAsFile();
        if (file) processImageFile(file, promptEl);
        return;
      }
    }
  });
  
  // Handle drag and drop
  const composerEl = document.getElementById('composer');
  
  // Prevent default drag behaviors on the whole document
  ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    document.addEventListener(eventName, (e) => {
      e.preventDefault();
      e.stopPropagation();
    }, false);
  });
  
  // Highlight drop zone when dragging over composer
  if (composerEl) {
    composerEl.addEventListener('dragenter', () => {
      composerEl.classList.add('drag-over');
    });
    
    composerEl.addEventListener('dragleave', (e) => {
      if (!composerEl.contains(e.relatedTarget)) {
        composerEl.classList.remove('drag-over');
      }
    });
    
    composerEl.addEventListener('dragover', (e) => {
      e.dataTransfer.dropEffect = 'copy';
    });
    
    composerEl.addEventListener('drop', (e) => {
      composerEl.classList.remove('drag-over');
      
      const files = e.dataTransfer?.files;
      if (files && files.length > 0) {
        for (const file of files) {
          if (file.type.startsWith('image/')) {
            processImageFile(file, promptEl);
            return;
          }
        }
        alert('Please drop an image file');
      }
    });
  }
  
  // Remove attachment button
  attachRemove?.addEventListener('click', () => {
    clearAttachment();
  });
}
