/**
 * Chat Audio Module
 * TTS manager and STT recording functionality
 */

// ============ TTS MANAGER ============

/**
 * Create TTS audio manager
 */
export function createTTSManager() {
  return {
    enabled: false,
    queue: [],
    pendingText: '',
    currentAudio: null,
    playing: false,
    inFlight: false,
    stopRequested: false,
    stopWords: ['stop', 'cancel', 'quiet', 'silence', 'shut up', 'enough'],
    flushTimer: null,
    prefetchedBlob: null,
    prefetchingChunk: null,
    inCodeBlock: false,
    
    /**
     * Queue a text fragment for TTS
     */
    queueFragment(text) {
      if (!this.enabled || !text || this.stopRequested) return;
      
      // Skip code blocks for TTS
      const codeStart = (text.match(/```/g) || []).length;
      if (codeStart % 2 !== 0) {
        this.inCodeBlock = !this.inCodeBlock;
      }
      if (this.inCodeBlock) return;
      
      // Filter JSON and tool calls
      if (text.includes('"tool_call"') || text.includes('"query":')) return;
      
      this.pendingText += text;
      
      if (this.flushTimer) {
        clearTimeout(this.flushTimer);
        this.flushTimer = null;
      }
      
      // Split on sentence boundaries
      const sentenceRegex = /[^.!?]+[.!?]+/g;
      let match;
      let lastIndex = 0;
      
      while ((match = sentenceRegex.exec(this.pendingText)) !== null) {
        const sentence = match[0].trim();
        if (sentence.length >= 10) {
          this.queue.push(sentence);
          lastIndex = sentenceRegex.lastIndex;
        }
      }
      
      if (lastIndex > 0) {
        this.pendingText = this.pendingText.slice(lastIndex).trim();
      }
      
      if (!this.playing && this.queue.length > 0) {
        this.playNextChunk();
      }
      
      // Flush remaining text after 800ms
      this.flushTimer = setTimeout(() => {
        const remaining = this.pendingText.trim();
        if (remaining && remaining.length >= 5) {
          this.queue.push(remaining);
          this.pendingText = '';
          if (!this.playing && this.queue.length > 0) {
            this.playNextChunk();
          }
        }
        this.flushTimer = null;
      }, 800);
    },
    
    /**
     * Clean text for TTS
     */
    cleanForTTS(text) {
      if (!text) return '';
      let clean = text;
      clean = clean.replace(/```[\s\S]*?```/g, ' ');
      clean = clean.replace(/`[^`]+`/g, ' ');
      clean = clean.replace(/^\|.*\|$/gm, '');
      clean = clean.replace(/^[\s]*[-|:]+[\s]*$/gm, '');
      clean = clean.replace(/[\u{1F300}-\u{1F9FF}]|[\u{2600}-\u{26FF}]|[\u{2700}-\u{27BF}]/gu, '');
      clean = clean.replace(/[•→←↑↓…—–""''✓✗✨🎉🙏]/g, '');
      clean = clean.replace(/[*_`#\[\]]/g, '');
      clean = clean.replace(/\[\d+\]/g, '');
      clean = clean.replace(/https?:\/\/[^\s]+/g, '');
      clean = clean.replace(/\s+/g, ' ').trim();
      return clean;
    },
    
    /**
     * Fetch TTS audio for text
     */
    async fetchTTS(text) {
      const cleaned = this.cleanForTTS(text);
      if (!cleaned || cleaned.length < 3) return null;
      
      const res = await fetch('/audio/tts', {
        method: 'POST',
        credentials: 'same-origin',
        body: cleaned,
        headers: {
          'Content-Type': 'text/plain',
          'X-CSRF-Token': window.CSRF_TOKEN || document.getElementById('csrf_token')?.value || ''
        }
      });
      
      if (res.status === 429) {
        try {
          const data = await res.json();
          if (typeof window.showTtsLimitModal === 'function') {
            window.showTtsLimitModal(data);
          }
          this.enabled = false;
          this.stopRequested = true;
        } catch (e) {}
        return null;
      }
      
      if (!res.ok) throw new Error('TTS fetch failed: ' + res.status);
      const audioData = await res.arrayBuffer();
      return new Blob([audioData], { type: 'audio/mpeg' });
    },
    
    /**
     * Prefetch next chunk
     */
    async prefetchNext() {
      if (this.queue.length === 0 || this.prefetchedBlob) return;
      
      const nextChunk = this.queue[0];
      if (!nextChunk || this.prefetchingChunk === nextChunk) return;
      
      this.prefetchingChunk = nextChunk;
      
      try {
        const blob = await this.fetchTTS(nextChunk);
        if (this.queue[0] === nextChunk && blob) {
          this.prefetchedBlob = { chunk: nextChunk, blob };
        }
      } catch (e) {}
      this.prefetchingChunk = null;
    },
    
    /**
     * Play audio blob
     */
    playBlob(blob) {
      return new Promise((resolve) => {
        const url = URL.createObjectURL(blob);
        const audio = new Audio(url);
        audio._ginto_url = url;
        this.currentAudio = audio;
        
        const cleanup = () => {
          try { audio.pause(); } catch(_){}
          try { URL.revokeObjectURL(url); } catch(_){}
          this.currentAudio = null;
          resolve();
        };
        
        audio.onended = cleanup;
        audio.onerror = cleanup;
        
        const pollStop = () => {
          if (this.stopRequested) {
            cleanup();
            return;
          }
          if (this.currentAudio === audio) {
            setTimeout(pollStop, 100);
          }
        };
        
        audio.play().then(pollStop).catch(cleanup);
      });
    },
    
    /**
     * Main playback loop
     */
    async playNextChunk() {
      if (this.playing) return;
      this.playing = true;
      this.inFlight = true;
      
      while (this.queue.length > 0 && !this.stopRequested) {
        const chunk = this.queue.shift();
        
        if (this.stopWords.some(w => chunk.toLowerCase().includes(w.toLowerCase()))) {
          this.stopRequested = true;
          break;
        }
        
        try {
          let blob = null;
          
          if (this.prefetchedBlob && this.prefetchedBlob.chunk === chunk) {
            blob = this.prefetchedBlob.blob;
            this.prefetchedBlob = null;
          } else {
            blob = await this.fetchTTS(chunk);
          }
          
          if (this.stopRequested) break;
          
          if (blob) {
            this.prefetchNext();
            await this.playBlob(blob);
          }
        } catch (e) {
          console.error('[TTS] Playback error:', e);
        }
      }
      
      this.playing = false;
      this.inFlight = false;
      this.prefetchedBlob = null;
      
      if (this.pendingText.trim() && this.pendingText.trim().length >= 5) {
        this.queue.push(this.pendingText.trim());
        this.pendingText = '';
        if (this.queue.length > 0 && !this.stopRequested) {
          this.playNextChunk();
        }
      }
    },
    
    /**
     * Stop all playback
     */
    stop() {
      this.stopRequested = true;
      this.queue.length = 0;
      this.pendingText = '';
      this.prefetchedBlob = null;
      this.inCodeBlock = false;
      
      if (this.flushTimer) {
        clearTimeout(this.flushTimer);
        this.flushTimer = null;
      }
      
      if (this.currentAudio) {
        try { this.currentAudio.pause(); } catch(_){}
        try { 
          if (this.currentAudio._ginto_url) {
            URL.revokeObjectURL(this.currentAudio._ginto_url);
          }
        } catch(_){}
        this.currentAudio = null;
      }
      
      this.playing = false;
      this.inFlight = false;
    },
    
    /**
     * Reset stop state for new message
     */
    reset() {
      this.stopRequested = false;
      this.pendingText = '';
      this.queue.length = 0;
      this.inCodeBlock = false;
    }
  };
}

// ============ STT MANAGER ============

/**
 * Create STT (Speech-to-Text) manager
 */
export function createSTTManager() {
  let mediaRecorder = null;
  let recordedChunks = [];
  let stream = null;
  let audioCtx = null;
  let analyser = null;
  let silenceCheckId = null;
  let maxRecordTimer = null;
  let recordStartTime = 0;
  
  const config = {
    silenceThreshold: 0.010,
    silenceMs: 1200,
    minSpeechMs: 300,
    maxRecordMs: 30000
  };
  
  return {
    isRecording: false,
    config,
    
    /**
     * Update configuration
     */
    setConfig(newConfig) {
      Object.assign(config, newConfig);
    },
    
    /**
     * Start recording
     */
    async startRecording(onTranscript, onError, onStateChange) {
      if (this.isRecording) return;
      
      try {
        // Get microphone access
        stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        
        // Determine supported MIME type
        const mimeTypes = [
          'audio/webm;codecs=opus',
          'audio/webm',
          'audio/ogg;codecs=opus',
          'audio/ogg'
        ];
        let mimeType = '';
        for (const type of mimeTypes) {
          if (MediaRecorder.isTypeSupported(type)) {
            mimeType = type;
            break;
          }
        }
        
        // Create media recorder
        mediaRecorder = new MediaRecorder(stream, mimeType ? { mimeType } : {});
        recordedChunks = [];
        
        mediaRecorder.ondataavailable = (e) => {
          if (e.data.size > 0) {
            recordedChunks.push(e.data);
          }
        };
        
        mediaRecorder.onstop = async () => {
          this.isRecording = false;
          onStateChange?.(false);
          
          // Clean up silence detection
          if (silenceCheckId) {
            cancelAnimationFrame(silenceCheckId);
            silenceCheckId = null;
          }
          if (maxRecordTimer) {
            clearTimeout(maxRecordTimer);
            maxRecordTimer = null;
          }
          
          // Process recording
          if (recordedChunks.length > 0) {
            const blob = new Blob(recordedChunks, { type: mimeType || 'audio/webm' });
            
            try {
              const formData = new FormData();
              formData.append('audio', blob, 'recording.webm');
              
              const res = await fetch('/audio/stt', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                  'X-CSRF-Token': window.CSRF_TOKEN || document.getElementById('csrf_token')?.value || ''
                },
                body: formData
              });
              
              if (res.ok) {
                const data = await res.json();
                if (data.text) {
                  onTranscript?.(data.text);
                }
              } else {
                onError?.('Transcription failed: ' + res.status);
              }
            } catch (e) {
              onError?.('Transcription error: ' + e.message);
            }
          }
          
          // Clean up stream
          if (stream) {
            stream.getTracks().forEach(t => t.stop());
            stream = null;
          }
          
          // Clean up audio context
          if (audioCtx) {
            try { audioCtx.close(); } catch(_){}
            audioCtx = null;
            analyser = null;
          }
        };
        
        // Start recording
        mediaRecorder.start(100); // Collect data every 100ms
        this.isRecording = true;
        recordStartTime = Date.now();
        onStateChange?.(true);
        
        // Set up silence detection
        try {
          audioCtx = new (window.AudioContext || window.webkitAudioContext)();
          analyser = audioCtx.createAnalyser();
          analyser.fftSize = 512;
          
          const source = audioCtx.createMediaStreamSource(stream);
          source.connect(analyser);
          
          const dataArray = new Float32Array(analyser.fftSize);
          let silenceStart = null;
          
          const checkSilence = () => {
            if (!this.isRecording) return;
            
            analyser.getFloatTimeDomainData(dataArray);
            
            // Calculate RMS
            let sum = 0;
            for (let i = 0; i < dataArray.length; i++) {
              sum += dataArray[i] * dataArray[i];
            }
            const rms = Math.sqrt(sum / dataArray.length);
            
            const now = Date.now();
            const elapsedMs = now - recordStartTime;
            
            if (rms < config.silenceThreshold) {
              if (silenceStart === null) {
                silenceStart = now;
              } else if (elapsedMs > config.minSpeechMs && (now - silenceStart) > config.silenceMs) {
                // Auto-stop on silence
                this.stopRecording();
                return;
              }
            } else {
              silenceStart = null;
            }
            
            silenceCheckId = requestAnimationFrame(checkSilence);
          };
          
          silenceCheckId = requestAnimationFrame(checkSilence);
        } catch (e) {
          console.debug('[STT] Silence detection setup failed:', e);
        }
        
        // Set max recording timer
        maxRecordTimer = setTimeout(() => {
          if (this.isRecording) {
            this.stopRecording();
          }
        }, config.maxRecordMs);
        
      } catch (e) {
        onError?.('Microphone access denied: ' + e.message);
        this.isRecording = false;
        onStateChange?.(false);
      }
    },
    
    /**
     * Stop recording
     */
    stopRecording() {
      if (!this.isRecording || !mediaRecorder) return;
      
      try {
        if (mediaRecorder.state !== 'inactive') {
          mediaRecorder.stop();
        }
      } catch (e) {
        console.debug('[STT] Stop error:', e);
      }
    }
  };
}

// ============ AUTO-START AFTER TTS ============

/**
 * Schedule auto-start of STT after TTS finishes
 */
export function scheduleAutoStartSTT(ttsManager, startRecordingFn) {
  if (typeof startRecordingFn !== 'function') return;
  
  const MAX_TRIES = 6;
  const TRY_DELAY_MS = 150;
  let tries = 0;
  
  function isSpeakingComplete() {
    return !ttsManager || (
      !ttsManager.queue.length && 
      !ttsManager.inFlight && 
      !ttsManager.currentAudio
    );
  }
  
  (function tryStart() {
    tries++;
    if (isSpeakingComplete()) {
      try {
        startRecordingFn();
      } catch (e) {
        console.debug('[STT] Auto-start failed:', e);
      }
      return;
    }
    if (tries < MAX_TRIES) {
      setTimeout(tryStart, TRY_DELAY_MS);
    }
  })();
}

// ============ SETUP FUNCTIONS ============

/**
 * Set up TTS UI controls
 */
export function setupTTSControls(ttsManager) {
  const enableCheckbox = document.getElementById('enable_tts');
  const stopBtn = document.getElementById('stop_tts');
  
  if (enableCheckbox) {
    enableCheckbox.checked = ttsManager.enabled;
    enableCheckbox.addEventListener('change', (e) => {
      ttsManager.enabled = !!e.target.checked;
    });
  }
  
  if (stopBtn) {
    stopBtn.disabled = false;
    stopBtn.addEventListener('click', () => {
      ttsManager.stop();
    });
  }
  
  // Expose globally
  window.__gintoAudio = ttsManager;
}

/**
 * Set up STT UI controls
 */
export function setupSTTControls(sttManager, promptEl) {
  const startBtn = document.getElementById('start_stt');
  const stopBtn = document.getElementById('stop_stt');
  const transcriptEl = document.getElementById('stt_transcript');
  
  if (!startBtn || !stopBtn) return;
  
  startBtn.disabled = false;
  stopBtn.disabled = true;
  
  const onTranscript = (text) => {
    if (transcriptEl) transcriptEl.textContent = text;
    if (promptEl && text) {
      promptEl.value = (promptEl.value ? promptEl.value + ' ' : '') + text;
      promptEl.focus();
    }
  };
  
  const onError = (msg) => {
    console.error('[STT]', msg);
    if (transcriptEl) transcriptEl.textContent = 'Error: ' + msg;
  };
  
  const onStateChange = (recording) => {
    startBtn.disabled = recording;
    stopBtn.disabled = !recording;
    if (startBtn) {
      startBtn.classList.toggle('recording', recording);
    }
  };
  
  startBtn.addEventListener('click', () => {
    sttManager.startRecording(onTranscript, onError, onStateChange);
  });
  
  stopBtn.addEventListener('click', () => {
    sttManager.stopRecording();
  });
  
  // Keyboard shortcut: Ctrl+Shift+M
  document.addEventListener('keydown', (e) => {
    if (e.ctrlKey && e.shiftKey && e.key.toLowerCase() === 'm') {
      if (!startBtn.disabled) startBtn.click();
      else if (!stopBtn.disabled) stopBtn.click();
    }
  });
  
  // Expose globally
  window.__gintoStartRecording = () => sttManager.startRecording(onTranscript, onError, onStateChange);
  window.__gintoStopRecording = () => sttManager.stopRecording();
}
