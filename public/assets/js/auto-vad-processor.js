class AutoVadProcessor extends AudioWorkletProcessor {
  constructor() {
    super();
    // `sampleRate` should be provided in the AudioWorklet global scope.
    // Fallback to 48000 if not available to avoid NaN frame sizes.
    const sr = (typeof sampleRate === 'number' && isFinite(sampleRate)) ? sampleRate : 48000;
    this.sampleRate = sr;
    this.FRAME_MS = 25;
    this.frameSize = Math.round((this.FRAME_MS / 1000) * this.sampleRate);
    this._buf = new Float32Array(0);
  }

  process(inputs, outputs, parameters) {
    try {
      const input = inputs[0];
      if (!input || !input[0]) return true;
      const channel = input[0];
      if (!channel || channel.length === 0) return true;
      // append
      const newBuf = new Float32Array(this._buf.length + channel.length);
      newBuf.set(this._buf, 0);
      newBuf.set(channel, this._buf.length);
      this._buf = newBuf;
      while (this._buf.length >= this.frameSize) {
        const frame = this._buf.slice(0, this.frameSize);
        this._buf = this._buf.slice(this.frameSize);
        // transfer the frame to main thread
        try { this.port.postMessage(frame, [frame.buffer]); } catch (e) { this.port.postMessage(frame); }
      }
    } catch (e) {
      // swallow errors to avoid killing the audio rendering thread
    }
    return true;
  }
}

registerProcessor('auto-vad-processor', AutoVadProcessor);
