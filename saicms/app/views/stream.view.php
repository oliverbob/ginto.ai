<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Use $pageTitle from $viewData if passed -->
    <title><?php echo $pageTitle ?? 'Direct Webcam to Cloudflare Stream'; ?></title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 0; padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; color: #333; }
        .container { max-width: 1200px; margin: 0 auto; background: white; border-radius: 15px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); overflow: hidden; }
        .header { background: linear-gradient(135deg, #ff6b6b, #ff8e8e); color: white; padding: 30px; text-align: center; }
        .header h1 { margin: 0; font-size: 2.5em; font-weight: 300; }
        .content { padding: 30px; }
        .video-section { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px; }
        .video-container { background: #f8f9fa; border-radius: 10px; padding: 20px; text-align: center; }
        .video-container h3 { margin-top: 0; color: #555; }
        video#localVideo { width: 100%; max-width: 400px; height: auto; border-radius: 8px; background: #000; box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
        .controls { text-align: center; margin: 30px 0; }
        button { background: linear-gradient(135deg, #667eea, #764ba2); color: white; border: none; padding: 15px 30px; margin: 0 10px; font-size: 16px; border-radius: 25px; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3); }
        button:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4); }
        button:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
        .status-base { padding: 15px; margin: 20px 0; border-radius: 4px; border-left-width: 4px; border-left-style: solid; }
        .status-info { background: #e3f2fd; border-left-color: #2196f3; }
        .status-success { background: #e8f5e8; border-left-color: #4caf50; color: #2e7d32; }
        .status-warning { background: #fff3e0; border-left-color: #ff9800; color: #e65100;}
        .status-error { background: #ffebee; border-left-color: #f44336; color: #c62828; }
        .stream-info { background: #f5f5f5; padding: 20px; border-radius: 8px; margin: 20px 0; display: none; }
        .stream-info.show { display: block; }
        .loading { display: inline-block; width: 20px; height: 20px; border: 3px solid #f3f3f3; border-top: 3px solid #3498db; border-radius: 50%; animation: spin 1s linear infinite; margin-right: 10px; vertical-align: middle; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        /* Styles for the iframe player container */
        #cfStreamPlayer { position: relative; /* For the iframe's absolute positioning */ width: 100%; /* Will be contained by .video-container */ /* padding-top: 56.25%; /* 16:9 Aspect Ratio - Handled by child div */ }
        #cfStreamPlayer .iframe-responsive-container { position: relative; padding-top: 56.25%; /* 16:9 Aspect Ratio */ height: 0; overflow: hidden; }
        #cfStreamPlayer .iframe-responsive-container iframe { position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: none; }

        @media (max-width: 768px) { .video-section { grid-template-columns: 1fr; } .header h1 { font-size: 1.8em; } button { display: block; width: 100%; margin: 10px 0; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎥 Webcam to Cloudflare Stream</h1>
            <p>Stream your webcam directly to Cloudflare using WebRTC</p>
        </div>
        
        <div class="content">
            <!-- ... (Your HTML structure for video, controls, status, etc. remains the same) ... -->
            <div class="video-section">
                <div class="video-container">
                    <h3>📹 Your Webcam</h3>
                    <video id="localVideo" autoplay muted playsinline></video>
                </div>
                <div class="video-container">
                    <h3>🌐 Live Stream</h3>
                    <div id="remotePlayerContainer">
                        <div id="streamPlaceholder" style="width: 100%; height: 300px; background: #eee; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #999;">
                            Stream will appear here when live
                        </div>
                        <div id="cfStreamPlayer" style="display: none;"></div>
                    </div>
                </div>
            </div>
            <div class="controls">
                <button id="startStreamBtn"><span id="startBtnTextEl">🚀 Start Streaming</span></button>
                <button id="stopStreamBtn" disabled>⏹️ Stop Stream</button>
                <button id="refreshPlayerBtn" style="display: none;">🔄 Refresh Player</button>
            </div>
            <div id="statusContainer"><div id="statusDivEl" class="status-base status-info">Ready...</div></div>
            <div id="errorLogContainerEl"></div>
            <div id="streamInfoEl" class="stream-info"><h4>Stream Information</h4><div id="streamDetailsEl"></div></div>
        </div>
    </div>

    <script data-cfasync="false" defer type="text/javascript" src="https://embed.videodelivery.net/embed/r4xu.fla9.latest.js?preload=true"></script>

    <script>
        // The entire WebcamStreamer class JavaScript from the previous example goes here.
        // Make ONE crucial change in the JavaScript:
        // The URL used in the fetch call within WebcamStreamer.startStreamingProcess()

        class WebcamStreamer {
            constructor() {
                // ... (all constructor element grabbing as before)
                this.localVideoEl = document.getElementById('localVideo');
                this.startBtnEl = document.getElementById('startStreamBtn');
                this.stopBtnEl = document.getElementById('stopStreamBtn');
                this.refreshPlayerBtnEl = document.getElementById('refreshPlayerBtn');
                this.startBtnTextEl = document.getElementById('startBtnTextEl'); 
                this.statusDivEl = document.getElementById('statusDivEl'); 
                this.errorLogContainerEl = document.getElementById('errorLogContainerEl'); 
                this.streamInfoEl = document.getElementById('streamInfoEl'); 
                this.streamDetailsEl = document.getElementById('streamDetailsEl'); 
                this.cfStreamPlayerEl = document.getElementById('cfStreamPlayer');
                this.streamPlaceholderEl = document.getElementById('streamPlaceholder');
                
                this.localStream = null;
                this.peerConnection = null;
                this.streamData = null; 
                this.isStreaming = false;
                this.playerInitialized = false;

                // This URL is now passed from the PHP controller to the view
                this.getStreamDetailsUrl = '<?php echo $getStreamDetailsUrl ?? "/post/stream"; ?>';
                
                this.bindEventListeners();
            }
            
            bindEventListeners() { /* ... as before ... */
                this.startBtnEl.onclick = () => this.startStreamingProcess();
                this.stopBtnEl.onclick = () => this.stopStreamingProcess();
                this.refreshPlayerBtnEl.onclick = () => this.showLivePlayer();
            }

            log(message, type = 'info') { /* ... as before ... */
                console.log(`[${type.toUpperCase()}] ${message}`);
                this.statusDivEl.textContent = message;
                this.statusDivEl.className = `status-base status-${type}`;
                if (type === 'error') {
                    const errorP = document.createElement('p');
                    errorP.className = 'status-base status-error';
                    errorP.textContent = `${new Date().toLocaleTimeString()}: ${message}`;
                    this.errorLogContainerEl.insertBefore(errorP, this.errorLogContainerEl.firstChild);
                    if (this.errorLogContainerEl.children.length > 5) {
                        this.errorLogContainerEl.removeChild(this.errorLogContainerEl.lastChild);
                    }
                }
            }

            setLoadingState(isLoading, message = "Starting...") { /* ... as before ... */
                if (isLoading) {
                    this.startBtnTextEl.innerHTML = `<span class="loading"></span> ${message}`;
                    this.startBtnEl.disabled = true;
                } else {
                    this.startBtnTextEl.innerHTML = '🚀 Start Streaming';
                    this.startBtnEl.disabled = this.isStreaming;
                }
            }
            
            async startStreamingProcess() {
                this.setLoadingState(true, "Initializing...");
                this.clearErrorLog();
                this.hideLivePlayer();
                this.playerInitialized = false;

                try {
                    this.log('Fetching stream details from server...', 'info');
                    // VVVVVV  MODIFIED FETCH URL VVVVVV
                    const response = await fetch(this.getStreamDetailsUrl); // Use the URL passed from PHP
                    // ^^^^^^  MODIFIED FETCH URL ^^^^^^
                    if (!response.ok) {
                        const errorText = await response.text();
                        let errorDetail = `HTTP error ${response.status}: ${response.statusText}`;
                        try {
                            const errorJson = JSON.parse(errorText);
                            errorDetail = errorJson.error || errorDetail;
                        } catch (e) { /* ignore */ }
                        throw new Error(errorDetail);
                    }
                    this.streamData = await response.json();

                    if (!this.streamData.success || !this.streamData.whipUrl || !this.streamData.apiTokenForWhip) { // Crucially check for apiTokenForWhip
                        throw new Error(this.streamData.error || 'Invalid stream details received. Missing WHIP URL or Auth Token.');
                    }
                    this.updateStreamInfoDisplay();
                    this.log('Stream details acquired. Requesting webcam...', 'info');

                    this.localStream = await navigator.mediaDevices.getUserMedia({
                        video: { width: { ideal: 1280 }, height: { ideal: 720 }, frameRate: { ideal: 30 } },
                        audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true }
                    });
                    this.localVideoEl.srcObject = this.localStream;
                    this.log('Webcam access granted. Initializing WebRTC...', 'info');

                    await this.initiateWebRTCConnection();
                    
                    this.isStreaming = true;
                    this.stopBtnEl.disabled = false;
                    this.refreshPlayerBtnEl.style.display = 'inline-block';

                } catch (error) {
                    this.log(`Error starting stream: ${error.message}`, 'error');
                    this.stopStreamingProcess(); 
                } finally {
                    this.setLoadingState(false);
                }
            }

            async initiateWebRTCConnection() { /* ... as before, ensure it uses this.streamData.apiTokenForWhip ... */
                if (!this.streamData || !this.streamData.whipUrl || !this.streamData.apiTokenForWhip) {
                    throw new Error("Cannot initiate WebRTC: Missing WHIP URL or API Token from stream data.");
                }

                this.peerConnection = new RTCPeerConnection({
                    iceServers: [ { urls: 'stun:stun.l.google.com:19302' } ]
                });

                this.peerConnection.onicecandidate = event => { /* ... */ };
                this.peerConnection.oniceconnectionstatechange = () => { /* ... as before, calls this.showLivePlayer() ... */
                    const state = this.peerConnection.iceConnectionState;
                    this.log(`ICE Connection State: ${state}`, 'info');
                    if (state === 'connected' || state === 'completed') {
                        this.log('Stream connected successfully via WebRTC!', 'success');
                        if (!this.playerInitialized) {
                           setTimeout(() => this.showLivePlayer(), 5000);
                           this.playerInitialized = true;
                        }
                    } else if (['failed', 'disconnected', 'closed'].includes(state)) {
                        this.log(`Stream connection issue: ${state}.`, 'error');
                    }
                };
                
                this.localStream.getTracks().forEach(track => this.peerConnection.addTrack(track, this.localStream));

                const offer = await this.peerConnection.createOffer();
                await this.peerConnection.setLocalDescription(offer);
                this.log('SDP Offer created. Sending to WHIP endpoint...', 'info');

                const whipResponse = await fetch(this.streamData.whipUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/sdp',
                        'Authorization': `Bearer ${this.streamData.apiTokenForWhip}` // Using the token
                    },
                    body: this.peerConnection.localDescription.sdp
                });

                if (!whipResponse.ok) {
                    if (whipResponse.status === 204 && whipResponse.headers.get('Location')) {
                        this.log('WHIP resource endpoint created: ' + whipResponse.headers.get('Location'), 'info');
                        return; 
                    }
                    const errorText = await whipResponse.text();
                    throw new Error(`WHIP server error (${whipResponse.status}): ${errorText}`);
                }
                
                const answerSdp = await whipResponse.text();
                if (!answerSdp && whipResponse.status !== 201 && whipResponse.status !== 200) { // 201 Created can also return an empty body with Location header for SDP answer
                     throw new Error('WHIP server returned an empty SDP answer and not a redirect/created status.');
                }
                if (answerSdp) { // Only set remote description if SDP answer is present
                    await this.peerConnection.setRemoteDescription(new RTCSessionDescription({ type: 'answer', sdp: answerSdp }));
                    this.log('SDP Answer received and set. WebRTC handshake complete.', 'info');
                } else {
                    this.log('SDP Answer was empty, relying on ICE connection state for success.', 'warning');
                }
            }
            
            stopStreamingProcess() { /* ... as before ... */
                this.log('Stopping stream...', 'info');
                this.isStreaming = false;
                this.playerInitialized = false;

                if (this.peerConnection) {
                    this.peerConnection.getSenders().forEach(sender => {
                        if (sender.track && sender.track.readyState === 'live') sender.track.stop();
                    });
                    this.peerConnection.close();
                    this.peerConnection = null;
                }
                if (this.localStream) {
                    this.localStream.getTracks().forEach(track => track.stop());
                    this.localVideoEl.srcObject = null;
                    this.localStream = null;
                }
                
                this.startBtnEl.disabled = false;
                this.stopBtnEl.disabled = true;
                this.refreshPlayerBtnEl.style.display = 'none';
                this.setLoadingState(false);
                this.hideLivePlayer();
                this.streamInfoEl.classList.remove('show');
                this.log('Stream stopped.', 'info');
            }
            
            showLivePlayer() { /* ... as before, using iframe ... */
                if (this.streamData && this.streamData.playbackUid) {
                    const uid = this.streamData.playbackUid;
                    this.log(`Refreshing player for UID: ${uid}`, 'info');
                    
                    this.streamPlaceholderEl.style.display = 'none';
                    this.cfStreamPlayerEl.style.display = 'block';
                    this.cfStreamPlayerEl.innerHTML = ''; 

                    const iframeContainer = document.createElement('div');
                    iframeContainer.className = 'iframe-responsive-container';

                    const iframe = document.createElement('iframe');
                    iframe.src = `https://iframe.videodelivery.net/${uid}?autoplay=true&muted=true&preload=true`;
                    iframe.allow = "accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture;";
                    iframe.allowFullscreen = true;

                    iframeContainer.appendChild(iframe);
                    this.cfStreamPlayerEl.appendChild(iframeContainer);

                    this.log('Player iframe embedded.', 'info');
                } else {
                    this.log('No playback UID available to show iframe player.', 'warning');
                }
            }

            hideLivePlayer() { /* ... as before ... */
                this.cfStreamPlayerEl.style.display = 'none';
                this.cfStreamPlayerEl.innerHTML = '';
                this.streamPlaceholderEl.style.display = 'flex';
            }

            updateStreamInfoDisplay() { /* ... as before ... */
                if (this.streamData && this.streamData.success) {
                    this.streamDetailsEl.innerHTML = `
                        <p><strong>Stream ID (Live Input):</strong> ${this.streamData.liveInputId || 'N/A'}</p>
                        <p><strong>Playback UID:</strong> ${this.streamData.playbackUid || 'N/A'}</p>
                        <p><strong>Status:</strong> <span class="status-base status-success" style="padding: 2px 5px; border-left-width: 2px;">Endpoint Created</span></p>
                        <p><strong>WHIP URL:</strong> ${(this.streamData.whipUrl || 'N/A').substring(0, 70)}...</p>
                    `;
                    this.streamInfoEl.classList.add('show');
                } else {
                    this.streamInfoEl.classList.remove('show');
                }
            }
            
            clearErrorLog() { /* ... as before ... */
                 this.errorLogContainerEl.innerHTML = '';
            }
        }
        
        document.addEventListener('DOMContentLoaded', () => {
            window.webcamStreamer = new WebcamStreamer();
        });
    </script>
</body>
</html>