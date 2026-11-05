/**
 * Chat Attachments Module
 * Image paste/drag-drop/upload handling + Document RAG upload
 */

// ============ STATE ============
let currentAttachment = null;
let uploadedDocuments = []; // RAG documents for context

// ============ ELEMENTS ============
let attachBtn = null;
let attachInput = null;
let attachPreview = null;
let attachPreviewImg = null;
let attachFilename = null;
let attachRemove = null;
let attachType = null; // Shows "image" or "document" type indicator

// Supported document types for RAG
const DOCUMENT_TYPES = [
  'application/pdf',
  'text/plain',
  'text/markdown',
  'text/x-markdown',
  'application/msword',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  'application/rtf',
  'text/rtf',
  'text/html',
  'application/xhtml+xml',
];

const DOCUMENT_EXTENSIONS = ['pdf', 'txt', 'md', 'doc', 'docx', 'rtf', 'html', 'htm'];

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
  attachType = document.getElementById('attach-type');
  
  return {
    attachBtn,
    attachInput,
    attachPreview,
    attachPreviewImg,
    attachFilename,
    attachRemove,
    attachType
  };
}

/**
 * Get current attachment
 */
export function getCurrentAttachment() {
  return currentAttachment;
}

/**
 * Get uploaded RAG documents
 */
export function getUploadedDocuments() {
  return uploadedDocuments;
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
  if (attachPreviewImg) {
    attachPreviewImg.src = '';
    attachPreviewImg.classList.remove('hidden');
  }
  if (attachFilename) attachFilename.textContent = '';
  if (attachType) attachType.textContent = '';
}

/**
 * Check if file is a document (for RAG)
 */
export function isDocumentFile(file) {
  if (DOCUMENT_TYPES.includes(file.type)) return true;
  const ext = file.name?.split('.').pop()?.toLowerCase();
  return ext && DOCUMENT_EXTENSIONS.includes(ext);
}

/**
 * Check if file is an image
 */
export function isImageFile(file) {
  return file.type.startsWith('image/');
}

/**
 * Process a file (image or document)
 */
export function processFile(file, promptEl) {
  if (!file) return;
  
  if (isImageFile(file)) {
    processImageFile(file, promptEl);
  } else if (isDocumentFile(file)) {
    processDocumentFile(file, promptEl);
  } else {
    showToast('Unsupported file type. Please upload an image or document (PDF, TXT, MD, DOC, DOCX).', 'error');
  }
}

/**
 * Process an image file (used by file input, drag/drop, and paste)
 */
export function processImageFile(file, promptEl) {
  if (!file) return;
  
  // Validate it's an image
  if (!file.type.startsWith('image/')) {
    showToast('Please select an image file', 'error');
    return;
  }
  
  // Check file size (max 20MB for Groq vision)
  if (file.size > 20 * 1024 * 1024) {
    showToast('Image too large. Maximum size is 20MB.', 'error');
    return;
  }
  
  // Read as base64
  const reader = new FileReader();
  reader.onload = (evt) => {
    currentAttachment = {
      dataUrl: evt.target.result,
      filename: file.name || 'pasted-image.png',
      type: file.type,
      attachmentType: 'image'
    };
    
    // Show preview
    if (attachPreviewImg) {
      attachPreviewImg.src = evt.target.result;
      attachPreviewImg.classList.remove('hidden');
    }
    if (attachFilename) attachFilename.textContent = currentAttachment.filename;
    if (attachType) {
      attachType.textContent = 'Image will be analyzed with vision model';
      attachType.className = 'text-xs text-indigo-500';
    }
    if (attachPreview) attachPreview.classList.remove('hidden');
    
    // Focus prompt for user to type
    promptEl?.focus();
  };
  reader.readAsDataURL(file);
}

/**
 * Process a document file for RAG
 */
export function processDocumentFile(file, promptEl) {
  if (!file) return;
  
  // Check file size (max 10MB for documents)
  if (file.size > 10 * 1024 * 1024) {
    showToast('Document too large. Maximum size is 10MB.', 'error');
    return;
  }
  
  // Show loading state
  if (attachPreview) attachPreview.classList.remove('hidden');
  if (attachPreviewImg) attachPreviewImg.classList.add('hidden');
  if (attachFilename) attachFilename.textContent = file.name;
  if (attachType) {
    attachType.innerHTML = '<span class="inline-flex items-center"><svg class="animate-spin -ml-1 mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Uploading document...</span>';
    attachType.className = 'text-xs text-amber-600 dark:text-amber-400';
  }
  
  // Read as base64
  const reader = new FileReader();
  reader.onload = async (evt) => {
    const dataUrl = evt.target.result;
    
    // Upload to server for RAG processing
    try {
      const result = await uploadDocumentToServer(dataUrl, file.name);
      
      if (result && result.success) {
        currentAttachment = {
          dataUrl: null, // Don't send raw data, we have document ID
          filename: file.name,
          type: file.type,
          attachmentType: 'document',
          documentId: result.document_id,
          textLength: result.text_length,
          preview: result.preview
        };
        
        // Update UI to show success
        if (attachType) {
          const ext = file.name.split('.').pop()?.toUpperCase() || 'DOC';
          attachType.innerHTML = `<span class="inline-flex items-center"><svg class="w-4 h-4 mr-1 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>${ext} ready for AI analysis (${formatBytes(result.text_length)} text extracted)</span>`;
          attachType.className = 'text-xs text-green-600 dark:text-green-400';
        }
        
        // Add to uploaded documents list
        uploadedDocuments.push({
          id: result.document_id,
          filename: file.name,
          textLength: result.text_length
        });
        
        showToast(`Document "${file.name}" uploaded and ready for AI analysis`, 'success');
        promptEl?.focus();
        
      } else {
        clearAttachment();
        showToast(result?.error || 'Failed to process document', 'error');
      }
    } catch (e) {
      console.error('[processDocumentFile] Error:', e);
      clearAttachment();
      showToast('Failed to upload document. Please try again.', 'error');
    }
  };
  reader.readAsDataURL(file);
}

/**
 * Upload document to server for RAG
 */
export async function uploadDocumentToServer(dataUrl, filename) {
  try {
    const formData = new FormData();
    formData.append('document', dataUrl);
    formData.append('filename', filename);
    formData.append('csrf_token', window.CSRF_TOKEN || document.getElementById('csrf_token')?.value || '');
    
    const res = await fetch('/chat/upload-document', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'X-CSRF-Token': window.CSRF_TOKEN || document.getElementById('csrf_token')?.value || ''
      },
      body: formData
    });
    
    const data = await res.json();
    return data;
  } catch (e) {
    console.warn('[uploadDocumentToServer] Failed:', e);
    return { success: false, error: e.message };
  }
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
 * Format bytes to human readable
 */
function formatBytes(bytes) {
  if (bytes < 1024) return bytes + ' B';
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

/**
 * Show toast notification
 */
function showToast(message, type = 'info') {
  if (typeof window.showToast === 'function') {
    window.showToast(message, type);
    return;
  }
  // Fallback
  if (type === 'error') {
    alert(message);
  } else {
    console.log('[Toast]', message);
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
    processFile(file, promptEl);
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
        const file = files[0];
        processFile(file, promptEl);
      }
    });
  }
  
  // Remove attachment button
  attachRemove?.addEventListener('click', () => {
    clearAttachment();
  });
}
