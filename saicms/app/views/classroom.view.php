<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduZoom - Virtual Classroom</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .video-container {
            position: relative;
            padding-bottom: 56.25%;
            height: 0;
            overflow: hidden;
        }
        .video-container video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .participant-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
        }
        .participant {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            background: #2d3748;
        }
        .participant.active-speaker {
            box-shadow: 0 0 0 3px #4299e1;
        }
        .participant-info {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0, 0, 0, 0.6);
            color: white;
            padding: 0.5rem;
            font-size: 0.875rem;
        }
        .chat-message {
            max-height: 300px;
            overflow-y: auto;
        }
        .screen-share {
            background: #1a202c;
            border-radius: 8px;
            overflow: hidden;
        }
        .tooltip {
            position: relative;
            display: inline-block;
        }
        .tooltip .tooltiptext {
            visibility: hidden;
            width: 120px;
            background-color: #555;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 5px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -60px;
            opacity: 0;
            transition: opacity 0.3s;
        }
        .tooltip:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="flex flex-col h-screen">
        <!-- Header -->
        <header class="bg-blue-600 text-white p-4 shadow-md">
            <div class="container mx-auto flex justify-between items-center">
                <div class="flex items-center space-x-2">
                    <i class="fas fa-chalkboard-teacher text-2xl"></i>
                    <h1 class="text-xl font-bold">EduZoom Classroom</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-clock"></i>
                        <span id="meeting-time">00:00:00</span>
                    </div>
                    <div class="flex space-x-2">
                        <a href="/" class="bg-blue-500 hover:bg-blue-600 px-4 py-1 rounded-md flex items-center space-x-1">
                            <i class="fas fa-home"></i>
                            <span>Home</span>
                        </a>
                        <button id="leave-meeting" class="bg-red-500 hover:bg-red-600 px-4 py-1 rounded-md flex items-center space-x-1">
                            <i class="fas fa-phone-slash"></i>
                            <span>Leave</span>
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-1 container mx-auto p-4 flex flex-col lg:flex-row gap-4">
            <!-- Video/Screen Share Area -->
            <div class="flex-1 flex flex-col gap-4">
                <!-- Main Speaker/Screen Share -->
                <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                    <div class="video-container" id="main-video-container">
                        <video id="main-video" autoplay playsinline></video>
                        <div class="participant-info">
                            <span id="main-speaker-name">You (Host)</span>
                            <div class="flex items-center space-x-2">
                                <span id="connection-status" class="text-green-400">
                                    <i class="fas fa-circle"></i> Connected
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Participants Grid -->
                <div class="bg-white rounded-lg shadow-lg p-4">
                    <h2 class="text-lg font-semibold mb-3 flex items-center">
                        <i class="fas fa-users mr-2"></i>
                        Participants (5)
                    </h2>
                    <div class="participant-grid" id="participants-grid">
                        <!-- Participant cards will be added here dynamically -->
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="w-full lg:w-80 flex flex-col gap-4">
                <!-- Chat Panel -->
                <div class="bg-white rounded-lg shadow-lg flex-1 flex flex-col">
                    <div class="border-b p-3 flex justify-between items-center">
                        <h3 class="font-semibold flex items-center">
                            <i class="fas fa-comments mr-2"></i>
                            Chat
                        </h3>
                        <button class="text-gray-500 hover:text-gray-700">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                    </div>
                    <div class="flex-1 p-3 chat-message" id="chat-messages">
                        <!-- Chat messages will be added here -->
                    </div>
                    <div class="border-t p-3">
                        <div class="flex space-x-2">
                            <input type="text" placeholder="Type a message..." class="flex-1 border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <button class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-2 rounded">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Controls -->
                <div class="bg-white rounded-lg shadow-lg p-3">
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="font-semibold flex items-center">
                            <i class="fas fa-sliders-h mr-2"></i>
                            Controls
                        </h3>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <button id="toggle-mic" class="bg-gray-200 hover:bg-gray-300 p-3 rounded-lg flex flex-col items-center justify-center tooltip">
                            <i class="fas fa-microphone text-xl"></i>
                            <span class="text-xs mt-1">Mute</span>
                            <span class="tooltiptext">Toggle Microphone</span>
                        </button>
                        <button id="toggle-video" class="bg-gray-200 hover:bg-gray-300 p-3 rounded-lg flex flex-col items-center justify-center tooltip">
                            <i class="fas fa-video text-xl"></i>
                            <span class="text-xs mt-1">Stop Video</span>
                            <span class="tooltiptext">Toggle Video</span>
                        </button>
                        <button id="share-screen" class="bg-gray-200 hover:bg-gray-300 p-3 rounded-lg flex flex-col items-center justify-center tooltip">
                            <i class="fas fa-desktop text-xl"></i>
                            <span class="text-xs mt-1">Share Screen</span>
                            <span class="tooltiptext">Share Your Screen</span>
                        </button>
                        <button id="record-session" class="bg-gray-200 hover:bg-gray-300 p-3 rounded-lg flex flex-col items-center justify-center tooltip">
                            <i class="fas fa-circle text-xl text-red-500"></i>
                            <span class="text-xs mt-1">Record</span>
                            <span class="tooltiptext">Record Session</span>
                        </button>
                        <button id="raise-hand" class="bg-gray-200 hover:bg-gray-300 p-3 rounded-lg flex flex-col items-center justify-center tooltip">
                            <i class="fas fa-hand-paper text-xl"></i>
                            <span class="text-xs mt-1">Raise Hand</span>
                            <span class="tooltiptext">Raise Your Hand</span>
                        </button>
                        <button id="more-options" class="bg-gray-200 hover:bg-gray-300 p-3 rounded-lg flex flex-col items-center justify-center tooltip">
                            <i class="fas fa-ellipsis-h text-xl"></i>
                            <span class="text-xs mt-1">More</span>
                            <span class="tooltiptext">More Options</span>
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modals -->
    <div id="participant-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-lg shadow-xl w-11/12 max-w-2xl">
            <div class="border-b p-4 flex justify-between items-center">
                <h3 class="text-lg font-semibold">Participants</h3>
                <button id="close-participant-modal" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-4 max-h-96 overflow-y-auto">
                <div class="space-y-3">
                    <!-- Participant list items will be added here -->
                </div>
            </div>
        </div>
    </div>

    <script>
        // Simulate participants
        const participants = [
            { id: 1, name: "Alex Johnson", isHost: false, isMuted: false, isVideoOn: true, isSpeaking: false },
            { id: 2, name: "Maria Garcia", isHost: false, isMuted: true, isVideoOn: true, isSpeaking: false },
            { id: 3, name: "Sam Wilson", isHost: false, isMuted: false, isVideoOn: false, isSpeaking: false },
            { id: 4, name: "Priya Patel", isHost: false, isMuted: false, isVideoOn: true, isSpeaking: true },
            { id: 5, name: "You (Host)", isHost: true, isMuted: false, isVideoOn: true, isSpeaking: false }
        ];

        // Initialize meeting time
        let seconds = 0;
        const meetingTimeElement = document.getElementById('meeting-time');
        
        setInterval(() => {
            seconds++;
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            const secs = seconds % 60;
            meetingTimeElement.textContent = 
                `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        }, 1000);

        // Render participants
        const participantsGrid = document.getElementById('participants-grid');
        
        function renderParticipants() {
            participantsGrid.innerHTML = '';
            participants.forEach(participant => {
                const participantElement = document.createElement('div');
                participantElement.className = `participant ${participant.isSpeaking ? 'active-speaker' : ''}`;
                
                participantElement.innerHTML = `
                    <div class="video-container">
                        <video autoplay playsinline ${!participant.isVideoOn ? 'poster="https://via.placeholder.com/300x169?text=Video+Off"' : ''}></video>
                        <div class="participant-info">
                            <span>${participant.name}</span>
                            <div class="flex items-center space-x-2">
                                ${participant.isMuted ? '<i class="fas fa-microphone-slash text-red-400"></i>' : ''}
                                ${participant.isHost ? '<span class="text-xs bg-blue-500 text-white px-1 rounded">Host</span>' : ''}
                            </div>
                        </div>
                    </div>
                `;
                
                participantsGrid.appendChild(participantElement);
            });
        }

        // Simulate chat messages
        const chatMessages = [
            { sender: "System", message: "Welcome to the classroom! Session has started.", time: "10:00 AM" },
            { sender: "Alex Johnson", message: "Hello everyone!", time: "10:01 AM" },
            { sender: "Maria Garcia", message: "Hi Alex! Ready for today's lesson?", time: "10:02 AM" },
            { sender: "Priya Patel", message: "Can someone share the slides from last week?", time: "10:03 AM" }
        ];

        function renderChatMessages() {
            const chatContainer = document.getElementById('chat-messages');
            chatContainer.innerHTML = '';
            
            chatMessages.forEach(msg => {
                const messageElement = document.createElement('div');
                messageElement.className = 'mb-3';
                
                messageElement.innerHTML = `
                    <div class="font-semibold ${msg.sender === 'System' ? 'text-blue-600' : 'text-gray-800'}">
                        ${msg.sender} <span class="text-xs text-gray-500">${msg.time}</span>
                    </div>
                    <div class="text-sm text-gray-700">${msg.message}</div>
                `;
                
                chatContainer.appendChild(messageElement);
            });
            
            // Scroll to bottom
            chatContainer.scrollTop = chatContainer.scrollHeight;
        }

        // Control button handlers
        document.getElementById('toggle-mic').addEventListener('click', function() {
            const icon = this.querySelector('i');
            const label = this.querySelector('span');
            
            if (icon.classList.contains('fa-microphone')) {
                icon.classList.remove('fa-microphone');
                icon.classList.add('fa-microphone-slash');
                label.textContent = 'Unmute';
            } else {
                icon.classList.remove('fa-microphone-slash');
                icon.classList.add('fa-microphone');
                label.textContent = 'Mute';
            }
        });

        document.getElementById('toggle-video').addEventListener('click', function() {
            const icon = this.querySelector('i');
            const label = this.querySelector('span');
            
            if (icon.classList.contains('fa-video')) {
                icon.classList.remove('fa-video');
                icon.classList.add('fa-video-slash');
                label.textContent = 'Start Video';
            } else {
                icon.classList.remove('fa-video-slash');
                icon.classList.add('fa-video');
                label.textContent = 'Stop Video';
            }
        });

        document.getElementById('raise-hand').addEventListener('click', function() {
            // Simulate raising hand
            const chatContainer = document.getElementById('chat-messages');
            const messageElement = document.createElement('div');
            messageElement.className = 'mb-3';
            
            messageElement.innerHTML = `
                <div class="font-semibold text-blue-600">
                    System <span class="text-xs text-gray-500">${new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</span>
                </div>
                <div class="text-sm text-gray-700">You raised your hand</div>
            `;
            
            chatContainer.appendChild(messageElement);
            chatContainer.scrollTop = chatContainer.scrollHeight;
        });

        document.getElementById('leave-meeting').addEventListener('click', function() {
            if (confirm('Are you sure you want to leave the meeting?')) {
                // In a real app, this would disconnect from the meeting
                alert('You have left the meeting.');
                window.location.href = '/';
            }
        });

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            renderParticipants();
            renderChatMessages();
            
            // Simulate changing active speaker
            setInterval(() => {
                const randomIndex = Math.floor(Math.random() * participants.length);
                participants.forEach(p => p.isSpeaking = false);
                participants[randomIndex].isSpeaking = true;
                renderParticipants();
            }, 5000);
            
            // Try to get user media (in a real app, this would be more sophisticated)
            navigator.mediaDevices.getUserMedia({ video: true, audio: true })
                .then(stream => {
                    const mainVideo = document.getElementById('main-video');
                    mainVideo.srcObject = stream;
                })
                .catch(err => {
                    console.error("Error accessing media devices:", err);
                });
        });
    </script>
</body>
</html>