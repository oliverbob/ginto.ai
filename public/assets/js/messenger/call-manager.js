/* Call manager extracted from GintoMessenger
   Encapsulates WebRTC and signalling logic. Operates on a messenger
   instance passed to the constructor (this.messenger).
*/
(function () {
    class GintoCallManager {
        constructor(messenger) {
            this.messenger = messenger;
        }

        /* =========================
           🔧 FIX HELPERS
        ========================= */

        async ensureCallerPeerAndUI(userId) {
            const M = this.messenger;
            const uid = String(userId);

            if (!M.currentCall) return null;

            // Ensure peerConnections container exists
            if (!M.currentCall.peerConnections) M.currentCall.peerConnections = {};

            // If pc already exists, return it
            if (M.currentCall.peerConnections[uid]) return M.currentCall.peerConnections[uid];

            // Create peer connection and attach local tracks if available
            try {
                const pc = await this.createPeerConnectionFor(uid);
                M.currentCall.peerConnections[uid] = pc;
                try {
                    if (M.currentCall.localStream) {
                        M.currentCall.localStream.getTracks().forEach(t => pc.addTrack(t, M.currentCall.localStream));
                    }
                } catch (e) { console.debug('Failed to attach local tracks to new pc', e); }
            } catch (e) {
                console.debug('Failed to create peer connection for', uid, e);
            }

            // Ensure UI tile exists (synchronous)
            const vid = document.getElementById(`call-remote-video-${uid}`);
            const aud = document.getElementById(`call-remote-audio-${uid}`);
            if (vid || aud) return M.currentCall.peerConnections[uid] || null;

            const modal = document.getElementById('call-modal');
            if (!modal) return M.currentCall.peerConnections[uid] || null;

            const container = modal.querySelector('.w-full') || modal;

            if (M.currentCall.callType === 'video') {
                const div = document.createElement('div');
                div.className = 'relative rounded-lg bg-black overflow-hidden';
                div.dataset.userId = uid;
                div.innerHTML = `
                    <video id="call-remote-video-${uid}"
                           class="w-full h-40 object-cover bg-black"
                           autoplay playsinline></video>
                    <div class="absolute inset-0 flex items-center justify-center call-waiting">
                        <div class="text-sm text-white">Connecting…</div>
                    </div>
                    <div class="call-play-overlay absolute inset-0 flex items-center justify-center hidden">
                        <button class="play-btn bg-white/10 text-white py-2 px-4 rounded">Play Video</button>
                    </div>
                `;
                container.appendChild(div);
                try {
                    const v = document.getElementById(`call-remote-video-${uid}`);
                    const placeholder = this.createPlaceholderStream(uid);
                    if (v && placeholder) {
                        try { v.srcObject = placeholder; } catch (e) {}
                    }
                } catch (e) { /* ignore */ }
            } else {
                const div = document.createElement('div');
                div.className = 'flex items-center p-2 bg-[#1f1f1f] rounded-lg';
                div.dataset.userId = uid;
                div.innerHTML = `
                    <audio id="call-remote-audio-${uid}" autoplay playsinline></audio>
                    <span class="text-white text-sm ml-2">Participant ${uid}</span>
                `;
                container.appendChild(div);
            }

            return M.currentCall.peerConnections[uid] || null;
        }

        // Ensure the remote UI tile is visible and attempt to start playback.
        async revealRemoteTile(userId) {
            const M = this.messenger;
            const uid = String(userId);
            if (!M.currentCall) return false;

            try { await this.ensureCallerPeerAndUI(uid); } catch (e) { /* ignore */ }

            const videoEl = document.getElementById(`call-remote-video-${uid}`);
            const audioEl = document.getElementById(`call-remote-audio-${uid}`);
            const el = videoEl || audioEl;
            if (!el) return false;

            const parent = el.closest && el.closest('div') ? el.closest('div') : el.parentElement;
            const overlay = parent?.querySelector('.call-play-overlay');
            const placeholder = parent?.querySelector('.call-waiting');

            try { el.muted = true; } catch (e) {}
            try { el.autoplay = true; } catch (e) {}
            try { el.playsInline = true; } catch (e) {}

            const stream = (M.currentCall.remoteStreams || {})[uid];
            try { if (stream) { if (el.srcObject && el.srcObject._isPlaceholder) this.stopPlaceholder(uid); el.srcObject = stream; } } catch (e) {}

            try { if (overlay) overlay.classList.remove('hidden'); } catch (e) {}
            try { if (placeholder) placeholder.classList.add('hidden'); } catch (e) {}

            try {
                const btn = overlay?.querySelector('.play-btn');
                if (btn) btn.addEventListener('click', (ev) => {
                    ev.preventDefault();
                    try { el.muted = false; } catch (e) {}
                        try { const p = el.play && el.play(); if (p && typeof p.then === 'function') p.catch(() => {}); } catch (e) {}
                });
            } catch (e) { /* ignore */ }

            // Attempt to start playback once (muted). If it succeeds hide overlay/placeholder.
            try {
                const p = el.play && el.play();
                if (p && typeof p.then === 'function') {
                    p.then(() => {
                        try { if (placeholder) { placeholder.remove(); } } catch (e) {}
                        try { if (overlay) overlay.classList.add('hidden'); } catch (e) {}
                    }).catch(() => {});
                }
            } catch (e) { /* ignore */ }

            return true;
        }

        // Add a global always-visible debug button so testers can force a user-gesture
        // playback attempt across all remote tiles. This helps determine whether the
        // issue is autoplay-policy related or a rendering/stream problem.
        ensureDebugPlayButton() {
            try {
                if (document.getElementById('call-debug-play-all')) return;
                const btn = document.createElement('button');
                btn.id = 'call-debug-play-all';
                btn.className = 'fixed bottom-4 right-4 bg-white/10 text-white py-2 px-3 rounded z-50';
                btn.textContent = 'Debug Play';
                btn.addEventListener('click', (ev) => {
                    ev.preventDefault();
                    try {
                        const M = this.messenger;
                        if (!M || !M.currentCall) return;
                        const ids = Object.keys(M.currentCall.remoteStreams || {});
                        ids.forEach(uid => {
                            try {
                                const videoEl = document.getElementById(`call-remote-video-${uid}`);
                                const audioEl = document.getElementById(`call-remote-audio-${uid}`);
                                const el = videoEl || audioEl;
                                if (!el) return;
                                try { el.muted = false; } catch (e) {}
                                try { const p = el.play && el.play(); if (p && typeof p.then === 'function') p.catch(err => console.debug('📞 Debug play failed for', uid, err)); } catch (e) { console.debug('📞 Debug play error', e); }
                            } catch (e) { console.debug('Error during debug-play per-id', uid, e); }
                        });
                    } catch (e) { console.debug('Failed to run debug play all', e); }
                });
                document.body.appendChild(btn);
            } catch (e) { /* ignore */ }
        }

        // Create a lightweight placeholder MediaStream from a canvas for a user.
        // This lets the caller see a static/prepopulated video until the real
        // remote MediaStream arrives. Stream has `_isPlaceholder = true`.
        createPlaceholderStream(uid) {
            try {
                const M = this.messenger;
                if (!M.currentCall) return null;
                if (!M.currentCall._placeholders) M.currentCall._placeholders = {};
                if (M.currentCall._placeholders[uid]) return M.currentCall._placeholders[uid].stream;

                const canvas = document.createElement('canvas');
                canvas.width = 640; canvas.height = 360;
                const ctx = canvas.getContext('2d');

                // draw background and large user id as fallback avatar
                ctx.fillStyle = '#111827'; ctx.fillRect(0, 0, canvas.width, canvas.height);
                ctx.fillStyle = '#9CA3AF'; ctx.font = 'bold 72px sans-serif'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
                const label = String(uid).slice(0, 2).toUpperCase();
                ctx.fillText(label, canvas.width/2, canvas.height/2);

                const stream = canvas.captureStream ? canvas.captureStream(10) : null;
                if (stream) stream._isPlaceholder = true;

                M.currentCall._placeholders[uid] = { canvas, ctx, stream };
                return stream;
            } catch (e) { return null; }
        }

        stopPlaceholder(uid) {
            try {
                const M = this.messenger;
                if (!M.currentCall || !M.currentCall._placeholders) return;
                const res = M.currentCall._placeholders[uid];
                if (!res) return;
                try {
                    if (res.stream && res.stream.getTracks) res.stream.getTracks().forEach(t => { try{ t.stop(); }catch(e){} });
                } catch (e) {}
                try { if (res.canvas && res.canvas.remove) res.canvas.remove(); } catch (e) {}
                delete M.currentCall._placeholders[uid];
            } catch (e) { /* ignore */ }
        }

        // Create a visible candidate UI tile for a target user with a placeholder
        // stream, without creating a PeerConnection yet. This shows callers who
        // they've invited and provides a visual preview slot for the incoming
        // participant's video.
        ensureCandidateTile(uid) {
            try {
                const M = this.messenger;
                const id = String(uid);
                if (!M.currentCall) return;
                const modal = document.getElementById('call-modal');
                if (!modal) return;
                const existing = document.getElementById(`call-remote-video-${id}`) || document.getElementById(`call-remote-audio-${id}`);
                if (existing) return;
                const container = modal.querySelector('.w-full') || modal;
                const div = document.createElement('div');
                div.className = 'relative rounded-lg bg-black overflow-hidden';
                div.dataset.userId = id;
                div.dataset.candidate = '1';
                div.innerHTML = `
                    <video id="call-remote-video-${id}" class="w-full h-40 object-cover bg-black" autoplay playsinline></video>
                    <div class="absolute inset-0 flex items-center justify-center call-waiting"><div class="text-sm text-white">Waiting for video...</div></div>
                    <div class="call-play-overlay absolute inset-0 flex items-center justify-center hidden"><button class="play-btn bg-white/10 text-white py-2 px-4 rounded">Play Video</button></div>
                `;
                container.appendChild(div);
                try {
                    const v = document.getElementById(`call-remote-video-${id}`);
                    const placeholder = this.createPlaceholderStream(id);
                    if (v && placeholder) {
                        try { v.srcObject = placeholder; } catch (e) {}
                    }
                } catch (e) { /* ignore */ }
            } catch (e) { /* ignore */ }
        }

        // Ensure the caller's UI and local media are bootstrapped for a
        // specific remote user. This mirrors the 1:1 caller UI/workflow so
        // group-call callers get the same candidate tile, local stream, and
        // modal setup before we attach peer connections or answers.
        async ensureCallerUIForUser(userId) {
            const M = this.messenger;
            try {
                if (!M.currentCall) return;

                // Create call UI modal if missing (use existing call params)
                if (!document.getElementById('call-modal')) {
                    M.createCallUI(M.currentCall.displayName, M.currentCall.callType, !!M.currentCall.isOutgoing, !!M.currentCall.isGroup, M.currentCall.participants || []);
                    try { this.ensureOneGesturePlayAll(); } catch (e) { /* ignore */ }
                }

                // Ensure local media is available (same as 1:1 flow)
                if (!M.currentCall.localStream) {
                    const constraints = { audio: true, video: M.currentCall.callType === 'video' };
                    try {
                        M.currentCall.localStream = await navigator.mediaDevices.getUserMedia(constraints);
                        if (M.currentCall.callType === 'video') {
                            const lv = document.getElementById('call-local-video'); if (lv) lv.srcObject = M.currentCall.localStream;
                        }
                    } catch (e) {
                        console.debug('Failed to obtain local media while bootstrapping caller UI', e);
                    }
                }

                // Ensure there's a visual candidate tile for this user so the
                // caller has a place to attach the incoming remote stream.
                try { this.ensureCandidateTile(String(userId)); } catch (e) { /* ignore */ }
            } catch (e) {
                console.debug('ensureCallerUIForUser error', e);
            }
        }

        // When autoplay blocks playback, the next user gesture can be used
        // to play/unmute all remote video tiles. Install a one-time click
        // handler that attempts to unmute & play all remote videos.
        ensureOneGesturePlayAll() {
            try {
                const M = this.messenger;
                if (!M || !M.currentCall) return;
                if (M._oneGesturePlayInstalled) return;
                const handler = (ev) => {
                    try {
                        const ids = Object.keys(M.currentCall.remoteStreams || {});
                        ids.forEach(uid => {
                            try {
                                const v = document.getElementById(`call-remote-video-${uid}`);
                                if (v) {
                                    try { v.muted = false; } catch (e) {}
                                    try { const p = v.play && v.play(); if (p && typeof p.then === 'function') p.catch(err => console.debug('📞 one-gesture play failed for', uid, err)); } catch (e) { console.debug('📞 one-gesture play error', e); }
                                }
                            } catch (e) { console.debug('Error during one-gesture per-id', uid, e); }
                        });
                    } catch (e) { console.debug('one-gesture handler failed', e); }
                };
                document.addEventListener('click', handler, { once: true, capture: true });
                M._oneGesturePlayInstalled = true;
                console.debug('📞 One-gesture play-all handler installed');
            } catch (e) { console.debug('Failed to install one-gesture play-all', e); }
        }

        // Add a visible "Play all videos" button to the call modal
        ensurePlayAllButton() {
            try {
                const modal = document.getElementById('call-modal');
                if (!modal || document.getElementById('call-play-all-btn')) return;

                const btn = document.createElement('button');
                btn.id = 'call-play-all-btn';
                btn.className = 'absolute bottom-4 right-4 bg-blue-500 text-white py-2 px-4 rounded z-50';
                btn.textContent = 'Play All Videos';
                btn.addEventListener('click', (ev) => {
                    ev.preventDefault();
                    try {
                        const M = this.messenger;
                        if (!M || !M.currentCall) return;
                        const ids = Object.keys(M.currentCall.remoteStreams || {});
                        ids.forEach(uid => {
                            try {
                                const videoEl = document.getElementById(`call-remote-video-${uid}`);
                                if (videoEl) {
                                    videoEl.muted = false;
                                    videoEl.play().catch(err => console.debug('Play failed for', uid, err));
                                }
                            } catch (e) { console.debug('Error during play-all for', uid, e); }
                        });
                    } catch (e) { console.debug('Failed to play all videos', e); }
                });

                modal.appendChild(btn);
            } catch (e) { console.debug('Failed to add Play All button', e); }
        }

        async startCall(conversationId, otherUserId, displayName, callType = 'audio') {
            return await this._startCall(conversationId, otherUserId, displayName, callType);
        }

        async _startCall(conversationId, otherUserId, displayName, callType = 'audio') {
            const M = this.messenger;
            if (M.currentCall) {
                alert('Already in a call');
                return;
            }

            let conv = M.currentConversation || {};

            if (conversationId && (!conv.participants || conv.participants.length === 0)) {
                try {
                    // attempt to refresh conversation metadata if available
                    await M.loadMessages(conversationId, true);
                    conv = M.currentConversation || conv;
                } catch (e) {
                    console.debug('Could not refresh conversation metadata', e);
                }
            }

            const isGroup = conv.type === 'group' || (conv.participants && conv.participants.length > 1);

            let targets = [];
            if (isGroup && conv.participants) {
                targets = conv.participants.map(p => p.id).filter(id => id && id !== M.config.userId);
            } else if (otherUserId) {
                targets = [otherUserId];
            }

            const MAX_PARTICIPANTS = 10;
            if (targets.length + 1 > MAX_PARTICIPANTS) {
                targets = targets.slice(0, MAX_PARTICIPANTS - 1);
            }

            if (targets.length === 0 && otherUserId) targets = [otherUserId];
            if (targets.length === 0) { alert('No participants available to call in this conversation'); return; }

            M.currentCall = {
                conversationId,
                isGroup: !!isGroup,
                participants: targets,
                displayName,
                callType,
                isOutgoing: true,
                peerConnections: {},
                localStream: null,
                remoteStreams: {}
            };

            M.createCallUI(displayName, callType, true, isGroup, targets);
            try { this.ensureOneGesturePlayAll(); } catch (e) {}
            M.updateCallStatus('Requesting media...');

            // Pre-populate caller UI with candidate tiles (placeholders) for targets
            try {
                for (const t of targets) {
                    try { this.ensureCandidateTile(String(t)); } catch (e) { /* ignore */ }
                }
            } catch (e) { /* ignore */ }

            try {
                const constraints = { audio: true, video: callType === 'video' };
                M.currentCall.localStream = await navigator.mediaDevices.getUserMedia(constraints);

                if (callType === 'video') {
                    const lv = document.getElementById('call-local-video'); if (lv) lv.srcObject = M.currentCall.localStream;
                }

                M.updateCallStatus('Calling...');

                for (const targetId of targets) {
                    const tid = String(targetId);
                    const pc = await this.createPeerConnectionFor(tid);
                    if (!M.currentCall.peerConnections) M.currentCall.peerConnections = {};
                    M.currentCall.peerConnections[tid] = pc;
                    if (M.currentCall.localStream) M.currentCall.localStream.getTracks().forEach(track => pc.addTrack(track, M.currentCall.localStream));

                    const offer = await pc.createOffer();
                    await pc.setLocalDescription(offer);

                    this.messenger.wsSend({ type: 'call_offer', targetUserId: tid, conversationId: M.currentCall.conversationId, offer: offer, callType: M.currentCall.callType, callerName: M.config.userName || 'Someone', isGroup: !!M.currentCall.isGroup });
                }

                M.playRingtone();

                M.connectionTimeout = setTimeout(() => {
                    if (M.currentCall) {
                        this.messenger.updateCallStatus('No answer');
                        try { this.endCall(); } catch (e) {}
                    }
                }, 45000);

            } catch (error) {
                console.error('Error starting group call:', error);
                M.updateCallStatus('Failed to access media');
                setTimeout(() => this.endCall(), 2000);
            }
        }

        async createPeerConnectionFor(targetUserId) {
            const M = this.messenger;
            const self = this;
            const tid = String(targetUserId);
            const hostname = window.location.hostname;
            const turnHost = (hostname === 'localhost' || hostname === '127.0.0.1') ? '149.28.145.52' : hostname.replace(/^www\./, '');

            const configuration = {
                iceServers: [
                    { urls: 'stun:stun.l.google.com:19302' },
                    { urls: 'stun:stun1.l.google.com:19302' },
                    { urls: `turn:${turnHost}:3478`, username: 'ginto', credential: 'ginto-turn-2026' },
                    { urls: `turn:${turnHost}:3478?transport=tcp`, username: 'ginto', credential: 'ginto-turn-2026' }
                ],
                iceCandidatePoolSize: 10
            };

            const pc = new RTCPeerConnection(configuration);

            pc.onicecandidate = (event) => {
                if (event.candidate) {
                    try {
                        M.wsSend({ type: 'call_ice', targetUserId: tid, candidate: event.candidate });
                    } catch (e) { console.debug('wsSend failed for ICE', e); }
                }
            };

            pc.oniceconnectionstatechange = () => {
                const state = pc.iceConnectionState;
                console.log('📞 ICE Connection state for', tid, state);
                if (state === 'connected') {
                    if (M.connectionTimeout) { clearTimeout(M.connectionTimeout); M.connectionTimeout = null; }
                    M.stopRingtone();
                    M.updateCallStatus('Connected');
                    M.startCallTimer();
                    try { self.revealRemoteTile(tid).catch(() => {}); } catch (e) { /* ignore */ }
                }
                if (state === 'disconnected' || state === 'failed') {
                    try { pc.close(); } catch (e) {}
                    if (M.currentCall && M.currentCall.peerConnections && M.currentCall.peerConnections[tid]) delete M.currentCall.peerConnections[tid];
                }
            };

            pc.ontrack = (event) => {
                console.log('📞 Remote track received from', tid, event.track.kind);
                const remoteStream = event.streams[0];
                try { console.debug('📞 ontrack stream tracks:', remoteStream && remoteStream.getTracks ? remoteStream.getTracks().map(t=>t.kind+"("+t.readyState+")") : null); } catch(e){}
                M.currentCall.remoteStreams[tid] = remoteStream;

                // Ensure the caller UI exists and attempt to attach/reveal immediately.
                try { this.revealRemoteTile(tid).catch(() => {}); } catch (e) { console.debug('revealRemoteTile call in ontrack failed', e); }
                const remoteEl = document.getElementById(`call-remote-video-${tid}`) || document.getElementById(`call-remote-audio-${tid}`) || null;
                console.debug('📞 ontrack: remoteEl=', remoteEl, 'for tid=', tid);
                const parentDiv = remoteEl ? (remoteEl.closest && remoteEl.closest('div') ? remoteEl.closest('div') : remoteEl.parentElement) : null;
                console.debug('📞 ontrack: parentDiv=', parentDiv, 'placeholder?', parentDiv?.querySelector && parentDiv.querySelector('.call-waiting'));

                const attachAndPlay = (videoEl, parent) => {
                    if (!videoEl) return;
                    try {
                        // Set autoplay-friendly attributes before attaching stream
                        try { videoEl.muted = true; } catch (e) {}
                        try { videoEl.autoplay = true; } catch (e) {}
                        try { videoEl.playsInline = true; } catch (e) {}
                        // If a placeholder was present, stop it before attaching real stream
                        try { if (videoEl.srcObject && videoEl.srcObject._isPlaceholder) this.stopPlaceholder(tid); } catch (e) {}
                        try { videoEl.srcObject = remoteStream; } catch (e) {}
                        // Debug: report element state immediately after attaching stream
                        try { console.debug('📞 debug state for', tid, { readyState: videoEl.readyState, paused: videoEl.paused, muted: videoEl.muted, srcObject: (videoEl.srcObject && (videoEl.srcObject._isPlaceholder ? 'placeholder' : 'stream')) }); } catch (e) {}
                        // Try to start playback immediately; log result for debugging autoplay issues
                        try {
                            const p = videoEl.play && videoEl.play();
                            if (p && typeof p.then === 'function') {
                                p.then(() => console.debug('📞 Immediate play succeeded for', tid)).catch(err => console.debug('📞 Immediate play rejected for', tid, err));
                            } else if (p === undefined) {
                                console.debug('📞 play() did not return a Promise for', tid);
                            }
                        } catch (e) { console.debug('📞 Immediate play attempt threw for', tid, e); }
                        try { console.debug('📞 set srcObject for', tid, 'videoEl.srcObject=', videoEl.srcObject && (videoEl.srcObject._isPlaceholder ? 'placeholder' : 'stream')); } catch(e) {}
                        try { if (parent && parent.dataset && parent.dataset.candidate) delete parent.dataset.candidate; } catch (e) {}
                        console.debug('📞 Attached srcObject for', tid, 'muted=', videoEl.muted);

                        const placeholder = parent?.querySelector('.call-waiting');
                        const overlay = parent?.querySelector('.call-play-overlay');

                        try { console.debug('📞 UI elements for', tid, { hasPlaceholder: !!placeholder, hasOverlay: !!overlay }); } catch (e) {}

                        // Make play overlay visible immediately so user can start playback
                        // manually if autoplay is blocked. Also defensively hide/remove the
                        // visual 'Connecting…' / 'Waiting for video…' placeholder so it
                        // cannot remain stuck over the real video element.
                        try { if (overlay) overlay.classList.remove('hidden'); } catch (e) {}
                        try { if (placeholder) placeholder.classList.add('hidden'); } catch (e) {}
                        // Defensive: if autoplay prevented play, ensure overlay is visible but placeholder removed
                        try {
                            if (placeholder) { placeholder.style.display = 'none'; try { placeholder.remove(); } catch (e) {} }
                            if (overlay) overlay.classList.remove('hidden');
                        } catch (e) {}
                        try {
                            if (placeholder) {
                                try { placeholder.style.display = 'none'; } catch (e) {}
                                try { placeholder.remove(); } catch (e) {}
                                console.debug('📞 Hidden/removed placeholder for', tid);
                            }
                        } catch (e) { /* ignore */ }

                        // Handle autoplay success
                        videoEl.addEventListener('play', () => {
                            console.debug('📞 video play event for', tid);
                            try { if (placeholder) { placeholder.remove(); console.debug('📞 Removed placeholder for', tid); } } catch (e) {}
                            try { M.updateCallStatus('Video playing'); } catch (e) {}
                            try {
                                const grid = document.querySelector('#call-modal .grid');
                                if (grid && parent) grid.prepend(parent);
                            } catch (e) { /* ignore */ }
                        });

                        // Handle playback errors
                        videoEl.addEventListener('error', (ev) => {
                            console.debug('📞 video error event for', tid, ev);
                            try { if (overlay) overlay.classList.remove('hidden'); } catch (e) {}
                            try { M.updateCallStatus('Playback failed — try again'); } catch (e) {}
                        });

                        // Try playback on metadata ready - often allows autoplay when muted
                        videoEl.addEventListener('loadedmetadata', () => {
                            console.debug('📞 loadedmetadata for', tid);
                            try { tryPlay(); } catch (e) { console.debug('Immediate tryPlay after loadedmetadata failed', e); }
                        });

                        const tryPlay = () => {
                            console.debug('📞 tryPlay invoked for', tid, 'paused=', videoEl.paused, 'muted=', videoEl.muted);
                                return videoEl.play().then(() => {
                                console.debug('📞 tryPlay success for', tid);
                                try { if (placeholder) { placeholder.style.display = 'none'; placeholder.remove(); console.debug('📞 Removed placeholder after play for', tid); } } catch (e) {}
                                try { if (overlay) overlay.classList.add('hidden'); } catch (e) {}
                                try { M.updateCallStatus('Video playing'); } catch (e) {}
                                try {
                                    const grid = document.querySelector('#call-modal .grid');
                                    if (grid && parent) grid.prepend(parent);
                                } catch (e) { /* ignore */ }
                            }).catch(err => {
                                console.warn('📞 Playback failed for', tid, err);
                                try { console.debug('📞 tryPlay rejection for', tid, 'videoEl.readyState=', videoEl.readyState, 'videoEl.paused=', videoEl.paused); } catch(e) {}
                                try { M.updateCallStatus('Playback failed — try again'); } catch (e) {}
                                try { if (overlay) overlay.classList.remove('hidden'); } catch (e) {}
                                throw err;
                            });
                        };

                        // Wire play overlay button: unmute on user interaction then play
                        try {
                            overlay?.querySelector('.play-btn')?.addEventListener('click', (ev) => {
                                    ev.preventDefault();
                                    try { videoEl.muted = false; } catch (e) {}
                                    try { tryPlay(); } catch (e) { console.debug('tryPlay after click failed', e); }
                                });
                            try {
                                parent?.querySelector('.debug-play')?.addEventListener('click', (ev) => {
                                    ev.preventDefault();
                                    try { videoEl.muted = false; } catch (e) {}
                                    try { videoEl.play().catch(err => console.debug('Debug play failed', err)); } catch (e) {}
                                });
                            } catch (e) { console.debug('Failed to attach debug-play handler', e); }
                        } catch (e) { console.debug('Failed to attach play overlay handler', e); }

                        // Also attempt immediate play; if it fails loadedmetadata handler or catch will show overlay
                        try { tryPlay().catch(() => {}); } catch (e) { console.debug('Immediate tryPlay failed', e); }
                        try { this.ensureDebugPlayButton(); } catch (e) {}

                    } catch (e) { console.debug('Error during attachAndPlay', e); }
                };

                if (remoteEl) {
                    attachAndPlay(remoteEl, parentDiv);
                    console.debug('📞 Attached stream for', tid);
                } else {
                    console.warn('📞 No remote element found for', tid, '- creating UI tile and attaching stream');
                    try {
                        const modal = document.getElementById('call-modal');
                        if (modal) {
                            const container = modal.querySelector('.w-full') || modal;
                            const div = document.createElement('div');
                            div.className = 'relative rounded-lg bg-black overflow-hidden';
                            div.dataset.userId = tid;
                            div.innerHTML = `<video id="call-remote-video-${tid}" class="w-full h-40 object-cover bg-black" autoplay playsinline></video>` +
                                            `<div class="absolute inset-0 flex items-center justify-center call-waiting"><div class="text-sm text-white">Connecting…</div></div>` +
                                            `<div class="call-play-overlay absolute inset-0 flex items-center justify-center hidden"><button class="play-btn bg-white/10 text-white py-2 px-4 rounded">Play Video</button></div>` +
                                            `<div class="absolute top-2 right-2 flex gap-2"><button class="remote-expand-btn bg-black/50 text-white p-1 rounded" data-user-id="${tid}" title="Expand"><i class="fas fa-expand"></i></button><button class="remote-mute-btn bg-black/50 text-white p-1 rounded" data-user-id="${tid}" title="Mute"><i class="fas fa-volume-up"></i></button></div>`;
                            container.appendChild(div);
                            const videoEl = document.getElementById(`call-remote-video-${tid}`);
                            attachAndPlay(videoEl, div);
                        }
                    } catch (e) { console.debug('Failed dynamic tile creation', e); }
                }
            };

            return pc;
        }

        async handleIncomingCall(data) {
            const M = this.messenger;
            console.log('📞 Incoming call received:', data);
            const { fromUserId, callerName, offer, callType, isGroup, participants, conversationId } = data;

            if (M.currentCall && M.currentCall.isGroup && isGroup && conversationId && M.currentCall.conversationId === conversationId && offer && fromUserId) {
                console.log('📞 Incoming participant offer during group call from', fromUserId);
                try {
                    if (!M.currentCall.localStream) {
                        const constraints = { audio: true, video: M.currentCall.callType === 'video' };
                        M.currentCall.localStream = await navigator.mediaDevices.getUserMedia(constraints);
                        if (M.currentCall.callType === 'video') {
                            const lv = document.getElementById('call-local-video'); if (lv) lv.srcObject = M.currentCall.localStream;
                        }
                    }

                    const tid = String(fromUserId);
                    M.currentCall.offersReceived = M.currentCall.offersReceived || new Set();
                    M.currentCall.offersReceived.add(tid);

                    // Ensure the caller UI & local media are bootstrapped (mirror 1:1 flow)
                    try {
                        await this.ensureCallerUIForUser(tid);
                    } catch (e) { console.debug('ensureCallerUIForUser failed for incoming participant', tid, e); }

                    // Ensure a PeerConnection exists for this participant
                    let pc = (M.currentCall.peerConnections || {})[tid] || null;
                    if (!pc) {
                        pc = await this.createPeerConnectionFor(tid);
                        if (!M.currentCall.peerConnections) M.currentCall.peerConnections = {};
                        M.currentCall.peerConnections[tid] = pc;
                        try { if (M.currentCall.localStream) M.currentCall.localStream.getTracks().forEach(track => pc.addTrack(track, M.currentCall.localStream)); } catch (e) { /* ignore */ }
                    }

                    await pc.setRemoteDescription(new RTCSessionDescription(offer));
                    await this.processPendingIceCandidates(tid);
                    const answer = await pc.createAnswer();
                    await pc.setLocalDescription(answer);

                    M.wsSend({ type: 'call_answer', targetUserId: tid, answer: answer });
                    console.log('📞 Sent answer to new participant', tid);
                    try { this.revealRemoteTile(tid).catch(() => {}); } catch (e) { /* ignore */ }

                    // Defensive retries: sometimes the remote `ontrack` and stream
                    // attachment arrive slightly after we answer. Retry reveal a few
                    // times to ensure the caller UI attaches the stream.
                    try {
                        setTimeout(() => { try { this.revealRemoteTile(tid).catch(() => {}); } catch (e) {} }, 250);
                        setTimeout(() => { try { this.revealRemoteTile(tid).catch(() => {}); } catch (e) {} }, 1000);
                        setTimeout(() => { try { this.revealRemoteTile(tid).catch(() => {}); } catch (e) {} }, 3000);
                    } catch (e) { /* ignore */ }
                } catch (e) { console.error('Error handling incoming participant offer:', e); }
                return;
            }

            // Handle race: both sides may create outgoing offers simultaneously.
            // If we already have an outgoing call to the same participant and
            // we receive their offer, treat it as a normal participant offer
            // (create/ensure PC, set remote desc and send answer) instead of
            // immediately rejecting with 'busy'. This lets simultaneous callers
            // connect rather than aborting each other.
            if (M.currentCall && M.currentCall.isOutgoing && offer && fromUserId) {
                const tid = String(fromUserId);
                console.log('📞 Incoming offer matches existing outgoing call — answering for', tid);
                try {
                    if (!M.currentCall.localStream) {
                        const constraints = { audio: true, video: M.currentCall.callType === 'video' };
                        try { M.currentCall.localStream = await navigator.mediaDevices.getUserMedia(constraints); } catch (e) { console.debug('Failed to get local media during race handling', e); }
                        if (M.currentCall.callType === 'video') {
                            const lv = document.getElementById('call-local-video'); if (lv) lv.srcObject = M.currentCall.localStream;
                        }
                    }

                    M.currentCall.offersReceived = M.currentCall.offersReceived || new Set();
                    M.currentCall.offersReceived.add(tid);

                    try { await this.ensureCallerPeerAndUI(tid); } catch (e) { console.debug('ensureCallerPeerAndUI failed during race handling', e); }

                    let pc = (M.currentCall.peerConnections || {})[tid] || null;
                    if (!pc) {
                        pc = await this.createPeerConnectionFor(tid);
                        if (!M.currentCall.peerConnections) M.currentCall.peerConnections = {};
                        M.currentCall.peerConnections[tid] = pc;
                        try { if (M.currentCall.localStream) M.currentCall.localStream.getTracks().forEach(track => pc.addTrack(track, M.currentCall.localStream)); } catch (e) { /* ignore */ }
                    }

                    await pc.setRemoteDescription(new RTCSessionDescription(offer));
                    await this.processPendingIceCandidates(tid);
                    const answer = await pc.createAnswer();
                    await pc.setLocalDescription(answer);

                    M.wsSend({ type: 'call_answer', targetUserId: tid, answer: answer });
                    console.log('📞 Sent answer (race) to', tid);
                    try { this.revealRemoteTile(tid).catch(() => {}); } catch (e) { /* ignore */ }

                    // Defensive retries as in group handling
                    try {
                        setTimeout(() => { try { this.revealRemoteTile(tid).catch(() => {}); } catch (e) {} }, 250);
                        setTimeout(() => { try { this.revealRemoteTile(tid).catch(() => {}); } catch (e) {} }, 1000);
                        setTimeout(() => { try { this.revealRemoteTile(tid).catch(() => {}); } catch (e) {} }, 3000);
                    } catch (e) { /* ignore */ }
                } catch (e) {
                    console.error('Error handling incoming offer during race:', e);
                }
                return;
            }

            if (M.currentCall) {
                M.wsSend({ type: 'call_end', targetUserId: fromUserId, reason: 'busy' });
                return;
            }

            M.currentCall = {
                conversationId: conversationId || null,
                otherUserId: fromUserId,
                displayName: callerName,
                callType,
                isOutgoing: false,
                isGroup: !!isGroup,
                participants: Array.isArray(participants) ? participants.map(p => p.id || p).filter(id => id !== undefined && id !== null && String(id) !== String(M.config.userId)) : [],
                peerConnection: null,
                peerConnections: {},
                localStream: null,
                remoteStream: null,
                remoteStreams: {},
                offer: offer
            };

            if (document.hidden || !document.hasFocus()) M.showCallNotification(callerName, callType);

            const selfVid = document.getElementById(`call-remote-video-${M.config.userId}`);
            const selfAud = document.getElementById(`call-remote-audio-${M.config.userId}`);
            if (selfVid) selfVid.remove(); if (selfAud) selfAud.remove();

            M.createIncomingCallUI(callerName, callType);
            M.playRingtone();
        }

        async acceptCall() {
            const M = this.messenger;
            if (!M.currentCall || !M.currentCall.offer || !M.currentCall.otherUserId) return;

            const callerId = M.currentCall.otherUserId;
            M.closeCallNotification();
            const modal = document.getElementById('call-modal'); if (modal) modal.remove();

            // Build targets list for UI (attempt server canonical list)
            let targetsForUI = [];
            if (M.currentCall && M.currentCall.isGroup) {
                try {
                    const resp = await fetch(`/messenger/group-members/${M.currentCall.conversationId}`);
                    const data = await resp.json();
                    if (data && data.success && Array.isArray(data.members)) {
                        const me = String(M.config.userId || (window.GINTO_CONFIG && window.GINTO_CONFIG.userId) || '');
                        const set = new Set();
                        data.members.forEach(m => { if (m && (m.id || m.user_id || m.userId)) set.add(String(m.id || m.user_id || m.userId || m)); });
                        if (callerId) set.add(String(callerId));
                        set.delete(me);
                        targetsForUI = Array.from(set);
                    } else {
                        const set = new Set(); if (Array.isArray(M.currentCall.participants)) M.currentCall.participants.forEach(p => set.add(String(p))); if (callerId) set.add(String(callerId)); set.delete(String(M.config.userId)); targetsForUI = Array.from(set);
                    }
                } catch (e) {
                    console.warn('Failed to fetch group members for acceptor UI, falling back to local metadata', e);
                    const set = new Set(); if (Array.isArray(M.currentCall.participants)) M.currentCall.participants.forEach(p => set.add(String(p))); if (callerId) set.add(String(callerId)); set.delete(String(M.config.userId)); targetsForUI = Array.from(set);
                }
            } else {
                targetsForUI = [callerId];
            }

            M.currentCall.participants = targetsForUI;
            M.createCallUI(M.currentCall.displayName, M.currentCall.callType, false, M.currentCall.isGroup, targetsForUI);
            try { this.ensureOneGesturePlayAll(); } catch (e) {}
            M.updateCallStatus('Connecting...');

            M.connectionTimeout = setTimeout(() => {
                const pc = (M.currentCall.peerConnections || {})[String(callerId)];
                const state = pc?.connectionState || pc?.iceConnectionState;
                if (state && state !== 'connected') { M.updateCallStatus('Connection failed'); setTimeout(() => this.endCall(), 1500); }
            }, 30000);

            try {
                const constraints = { audio: true, video: M.currentCall.callType === 'video' };
                M.currentCall.localStream = await navigator.mediaDevices.getUserMedia(constraints);
                if (M.currentCall.callType === 'video') { const lv = document.getElementById('call-local-video'); if (lv) lv.srcObject = M.currentCall.localStream; }

                const cid = String(callerId);
                const pc = await this.createPeerConnectionFor(cid);
                if (!M.currentCall.peerConnections) M.currentCall.peerConnections = {};
                M.currentCall.peerConnections[cid] = pc;

                M.currentCall.localStream.getTracks().forEach(track => pc.addTrack(track, M.currentCall.localStream));

                // Facebook-style workflow: instead of setting the remote description
                // and sending an answer, create an offer from the acceptor and send
                // it to the caller. The caller (who already has an active
                // M.currentCall) will receive this `call_offer`, create an answer
                // and send back `call_answer`. This avoids races where the
                // caller's peer connection hasn't been fully bootstrapped yet.
                try {
                    const offer = await pc.createOffer();
                    await pc.setLocalDescription(offer);

                    console.log('📞 Sending call_offer (reverse) to:', cid);
                    M.wsSend({ type: 'call_offer', targetUserId: cid, conversationId: M.currentCall.conversationId, offer: offer, callType: M.currentCall.callType, callerName: M.config.userName || 'Someone', isGroup: !!M.currentCall.isGroup });
                } catch (e) {
                    console.error('Error creating/sending reverse offer during acceptCall:', e);
                }

                if (M.currentCall?.conversationId) {
                    M.wsSend({ type: 'call_join', conversationId: M.currentCall.conversationId, joiningUserId: M.config.userId });
                } else {
                    M.wsSend({ type: 'call_join', joiningUserId: M.config.userId });
                }

                M.stopRingtone();
            } catch (error) {
                console.error('Error accepting call:', error);
                M.updateCallStatus('Failed to connect');
                setTimeout(() => this.endCall(), 2000);
            }
        }

        /* =========================
           📞 CALL ANSWER (FIXED)
        ========================= */

        async handleCallAnswer(data) {
            const M = this.messenger;
            const from = String(data.fromUserId || data.sourceUserId);
            if (!M.currentCall) return;

            // ✅ FIX: Ensure caller always has peer + UI (wait until pc is ready)
            let pc = null;
            try {
                pc = await this.ensureCallerPeerAndUI(from);
            } catch (e) { console.debug('ensureCallerPeerAndUI failed', e); }

            if (!pc) {
                console.log('📞 No peer connection available for answer from', from);
                return;
            }

            try {
                await pc.setRemoteDescription(new RTCSessionDescription(data.answer));
                await this.processPendingIceCandidates(from);
                // Ensure the remote tile is visible and attempt playback immediately
                try { this.revealRemoteTile(from).catch(() => {}); } catch (e) {}
                M.stopRingtone();
                M.updateCallStatus('Connected');
            } catch (e) { console.error('Error setting remote description for', from, e); }
        }

        /* =========================
           📞 CALL JOIN (FIXED)
        ========================= */

        async handleCallJoin(data) {
            const M = this.messenger;
            const uid = String(data.joiningUserId || data.userId);
            if (!M.currentCall || uid === String(M.config.userId)) return;

            // ✅ FIX: caller must bootstrap peer + UI immediately
            let pc = null;
            try {
                pc = await this.ensureCallerPeerAndUI(uid);
            } catch (e) { console.debug('ensureCallerPeerAndUI failed for join', e); }

            // Force reveal the remote tile/UI for the joining participant so caller
            // can see the incoming video tile immediately (attempt playback).
            try { this.revealRemoteTile(uid).catch(() => {}); } catch (e) { }

            if (!M.currentCall.isGroup) return;

            // Proactively send offer if missing
            if (!pc) return;

            if (pc.signalingState !== 'stable') return;

            try {
                const offer = await pc.createOffer();
                await pc.setLocalDescription(offer);

                M.wsSend({
                    type: 'call_offer',
                    targetUserId: uid,
                    conversationId: M.currentCall.conversationId,
                    offer,
                    callType: M.currentCall.callType,
                    isGroup: true
                });
            } catch (e) { console.debug('Failed to create/send proactive offer to', uid, e); }
        }

        async handleCallIce(data) {
            const M = this.messenger;
            const from = data.fromUserId || data.sourceUserId || null;
            const fid = from != null ? String(from) : from;
            console.log('📞 Received ICE candidate from:', fid);
            if (!M.currentCall) { console.log('📞 No current call for ICE candidate - ignoring'); return; }

            const pc = (M.currentCall.peerConnections || {})[fid] || M.currentCall.peerConnection;

            if (!pc || !pc.remoteDescription) {
                console.log('📞 Queuing ICE candidate for', fid, '- remote description not set yet');
                if (!M.pendingIceCandidates) M.pendingIceCandidates = {};
                if (!M.pendingIceCandidates[fid]) M.pendingIceCandidates[fid] = [];
                M.pendingIceCandidates[fid].push(data.candidate);
                return;
            }

            try {
                await pc.addIceCandidate(new RTCIceCandidate(data.candidate));
                console.log('📞 ICE candidate added successfully for', from);
            } catch (error) { console.error('Error adding ICE candidate:', error); }
        }

        async processPendingIceCandidates(forUserId = null) {
            const M = this.messenger;
            if (!M.currentCall) return;
            if (!M.pendingIceCandidates) M.pendingIceCandidates = {};

            if (forUserId) {
                const list = M.pendingIceCandidates[forUserId] || [];
                if (!list.length) return;
                const pc = (M.currentCall.peerConnections || {})[forUserId] || M.currentCall.peerConnection;
                while (list.length > 0) {
                    const candidate = list.shift();
                    try { await pc.addIceCandidate(new RTCIceCandidate(candidate)); console.log('📞 Queued ICE candidate added for', forUserId); } catch (error) { console.error('Error adding queued ICE candidate for', forUserId, error); }
                }
                return;
            }

            for (const uid of Object.keys(M.pendingIceCandidates)) {
                await this.processPendingIceCandidates(uid);
            }
        }

        async endCall() {
            const M = this.messenger;
            M.stopRingtone();
            if (!M.currentCall) return;
            try {
                if (M.currentCall.peerConnections) {
                    for (const k of Object.keys(M.currentCall.peerConnections)) {
                        try { M.currentCall.peerConnections[k].close(); } catch (e) {}
                    }
                }
            } catch (e) { console.debug('Error closing peer connections', e); }

            try { if (M.currentCall.localStream) { M.currentCall.localStream.getTracks().forEach(t => t.stop()); } } catch (e) {}
            M.currentCall = null;
            if (document.getElementById('call-modal')) document.getElementById('call-modal').remove();
            M.updateCallStatus('Call ended');
            try { M.closeCallNotification(); } catch (e) {}
        }
    }

    window.GintoCallManager = GintoCallManager;
})();