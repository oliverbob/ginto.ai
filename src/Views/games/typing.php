<?php
/**
 * Typing Game Partial — PHP include for the gaming hub.
 * Extracted from public/typing.html.
 *
 * Expected variables:
 *   $backUrl (string) — URL for the "back" button (default: /gaming)
 */
$backUrl = $backUrl ?? '/gaming';
?>
<!-- Google Fonts for typing game -->
<link href="https://fonts.googleapis.com/css2?family=Source+Code+Pro:wght@400;500&family=Poppins:wght@400;600&display=swap" rel="stylesheet">

<style>
/* ===== Typing Game — scoped to #typing-game-root ===== */
#typing-game-root {
    --tg-bg: #31363f;
    --tg-text: #dcdfe4;
    --tg-main: #61afef;
    --tg-error: #e06c75;
    --tg-correct: #98c379;
    --tg-light-on: #98c379;
    --tg-light-off: #21252b;
    --tg-font-body: 'Poppins', sans-serif;
    --tg-font-mono: 'Source Code Pro', monospace;
    --tg-key-gap: 8px;
    --tg-key-size: 50px;

    background-color: var(--tg-bg);
    color: var(--tg-text);
    font-family: var(--tg-font-body);
    width: 100%;
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: flex-start;
    padding: 20px;
    box-sizing: border-box;
}

#typing-game-root .tg-container {
    width: 100%;
    max-width: 1200px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
}

#typing-game-root #lesson-info {
    text-align: center;
    margin-bottom: 1rem;
    width: 100%;
    max-width: 950px;
}
#typing-game-root #lesson-title {
    margin: 0;
    font-size: 1.8rem;
    color: var(--tg-main);
}
#typing-game-root #lesson-targets {
    font-size: 1rem;
    color: var(--tg-text);
    opacity: 0.8;
}
#typing-game-root .stats {
    display: flex;
    justify-content: space-around;
    font-size: 1.5rem;
    font-weight: 600;
    width: 100%;
    max-width: 950px;
}
#typing-game-root #text-display {
    font-family: var(--tg-font-mono);
    font-size: 1.5rem;
    line-height: 1.8;
    background-color: #21252b;
    padding: 1.5rem;
    border-radius: 8px;
    text-align: left;
    user-select: none;
    width: 100%;
    max-width: 950px;
    box-sizing: border-box;
    min-height: 70px;
}
#typing-game-root #input-field {
    opacity: 0;
    position: absolute;
    z-index: -1;
}
#typing-game-root .correct { color: var(--tg-correct); }
#typing-game-root .incorrect {
    color: #dcdfe4;
    background-color: var(--tg-error);
    border-radius: 3px;
    padding: 2px 0;
}
#typing-game-root .cursor {
    border-left: 2px solid var(--tg-main);
    animation: tg-blink 1s infinite;
    margin-left: -1px;
    margin-right: -1px;
}
@keyframes tg-blink { 50% { border-left-color: transparent; } }

#typing-game-root .controls {
    margin-top: 1rem;
    display: flex;
    gap: 1rem;
    align-items: center;
    justify-content: center;
}
#typing-game-root #restart-btn {
    background-color: var(--tg-main);
    color: #21252b;
    border: none;
    padding: 0.8rem 2rem;
    border-radius: 5px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: transform 0.2s;
}
#typing-game-root #restart-btn:hover { transform: scale(1.05); }

#typing-game-root #home-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background-color: #4a5058;
    color: var(--tg-text);
    border: none;
    padding: 0;
    width: 46px;
    height: 46px;
    border-radius: 5px;
    font-size: 1.2rem;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
}
#typing-game-root #home-btn:hover {
    background-color: #5f6672;
    transform: scale(1.05);
}

#typing-game-root #keyboard-container {
    display: flex;
    justify-content: center;
    gap: 25px;
    align-items: flex-start;
    margin-top: 1rem;
}
#typing-game-root .keyboard-row { display: flex; gap: var(--tg-key-gap); }
#typing-game-root .key {
    background-color: #3b4048;
    color: var(--tg-text);
    height: var(--tg-key-size);
    width: var(--tg-key-size);
    display: inline-flex;
    justify-content: center;
    align-items: center;
    border-radius: 5px;
    font-family: var(--tg-font-mono);
    font-size: 0.9rem;
    border-bottom: 3px solid #21252b;
    transition: all 0.1s ease-out;
    text-transform: none;
    text-align: center;
    line-height: 1.2;
    box-sizing: border-box;
}
#typing-game-root .key-pressed {
    background-color: var(--tg-main);
    color: #21252b;
    transform: translateY(2px) scale(0.98);
    border-bottom-width: 1px;
}
#typing-game-root #main-keyboard-block { display: flex; flex-direction: column; gap: var(--tg-key-gap); }
#typing-game-root .f-group { display: flex; gap: var(--tg-key-gap); }
#typing-game-root .f-group-spacer { width: 30px; }
#typing-game-root .key-bs { width: calc(var(--tg-key-size) * 2 + var(--tg-key-gap)); }
#typing-game-root .key-tab { width: calc(var(--tg-key-size) * 1.5); }
#typing-game-root .key-caps { width: calc(var(--tg-key-size) * 1.75); }
#typing-game-root .key-enter { width: calc(var(--tg-key-size) * 2.25); }
#typing-game-root .key-shift-l { width: calc(var(--tg-key-size) * 2.25); }
#typing-game-root .key-shift-r { width: calc(var(--tg-key-size) * 2.75); }
#typing-game-root .key-backslash { width: calc(var(--tg-key-size) * 1.5); }
#typing-game-root .key-mod { width: 60px; }
#typing-game-root .key-space { flex-grow: 1; }

#typing-game-root .key:has(.symbol) {
    flex-direction: column;
    justify-content: space-between;
    align-items: flex-start;
    padding: 5px;
}
#typing-game-root .key .symbol { font-size: 0.8rem; color: #a0a4a8; line-height: 1; }
#typing-game-root .key .main { font-size: 1.1rem; font-weight: 500; line-height: 1; }

/* Side widgets (fixed) */
#typing-game-root #side-widgets {
    position: fixed;
    top: 50%;
    left: 15px;
    transform: translateY(-50%);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2rem;
    z-index: 10;
}
#typing-game-root #analog-clock {
    position: relative;
    width: 150px;
    height: 150px;
    background-color: #21252b;
    border: 4px solid #3b4048;
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
}
#typing-game-root #analog-clock::before {
    content: '';
    position: absolute;
    width: 10px;
    height: 10px;
    background: var(--tg-main);
    border-radius: 50%;
    z-index: 12;
    border: 2px solid #21252b;
}
#typing-game-root .clock-hand {
    position: absolute;
    bottom: 50%;
    left: 50%;
    transform-origin: bottom;
    border-radius: 5px;
}
#typing-game-root #hour-hand { width: 6px; height: 40px; background-color: var(--tg-text); transform: translateX(-50%); z-index: 9; }
#typing-game-root #minute-hand { width: 4px; height: 60px; background-color: var(--tg-text); transform: translateX(-50%); z-index: 10; }
#typing-game-root #second-hand { width: 2px; height: 65px; background-color: var(--tg-main); transform: translateX(-50%); z-index: 11; }

#typing-game-root #progress-pie-chart {
    position: relative;
    width: 150px;
    height: 150px;
    border-radius: 50%;
    background: conic-gradient(var(--tg-correct) 0deg, #3b4048 0deg);
    display: grid;
    place-items: center;
    transition: background 0.2s ease-out;
}
#typing-game-root #progress-pie-chart::before {
    content: "";
    position: absolute;
    width: 80%;
    height: 80%;
    background: #21252b;
    border-radius: 50%;
}
#typing-game-root #progress-pie-text {
    position: relative;
    font-family: var(--tg-font-mono);
    font-size: 1.8rem;
    font-weight: 600;
    color: var(--tg-text);
}

@media (max-width: 1500px) {
    #typing-game-root #side-widgets { display: none; }
}

/* Modal */
#typing-game-root .modal-overlay {
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background-color: rgba(0, 0, 0, 0.7);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1000;
}
#typing-game-root .modal-content {
    background-color: var(--tg-bg);
    padding: 2rem 3rem;
    border-radius: 10px;
    text-align: center;
    border: 1px solid var(--tg-main);
    box-shadow: 0 5px 25px rgba(0,0,0,0.5);
}
#typing-game-root #results-summary { font-size: 1.5rem; margin: 1.5rem 0; }
#typing-game-root #results-message { font-size: 1.2rem; font-weight: 600; }
#typing-game-root .modal-buttons {
    margin-top: 2rem;
    display: flex;
    gap: 1rem;
    justify-content: center;
}
#typing-game-root .modal-buttons button {
    background-color: var(--tg-main);
    color: #21252b;
    border: none;
    padding: 0.8rem 2rem;
    border-radius: 5px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: transform 0.2s;
}
#typing-game-root .modal-buttons button:hover { transform: scale(1.05); }
#typing-game-root .hidden { display: none !important; }
#typing-game-root .pass { color: var(--tg-correct); }
#typing-game-root .fail { color: var(--tg-error); }

/* Toast */
#typing-game-root #toast-notification {
    position: fixed;
    top: 20px;
    right: -400px;
    min-width: 300px;
    max-width: 400px;
    background-color: #21252b;
    color: var(--tg-text);
    padding: 1rem 1.5rem;
    border-radius: 8px;
    border-left: 5px solid;
    box-shadow: 0 5px 15px rgba(0,0,0,0.4);
    transition: right 0.5s cubic-bezier(0.68, -0.55, 0.27, 1.55);
    z-index: 2000;
    display: flex;
    align-items: center;
    gap: 1rem;
}
#typing-game-root #toast-notification.show { right: 20px; }
#typing-game-root #toast-notification.success { border-left-color: var(--tg-correct); }
#typing-game-root #toast-notification.error { border-left-color: var(--tg-error); }
#typing-game-root #toast-icon.success { color: var(--tg-correct); }
#typing-game-root #toast-icon.error { color: var(--tg-error); }

/* Overflow scrolling for keyboard on small screens */
@media (max-width: 900px) {
    #typing-game-root #keyboard-container {
        overflow-x: auto;
        width: 100%;
        justify-content: flex-start;
        padding-bottom: 8px;
    }
}
</style>

<div id="typing-game-root">
    <div class="tg-container">

        <div id="side-widgets">
            <div id="analog-clock">
                <div id="hour-hand" class="clock-hand"></div>
                <div id="minute-hand" class="clock-hand"></div>
                <div id="second-hand" class="clock-hand"></div>
            </div>
            <div id="progress-pie-chart">
                <span id="progress-pie-text">0%</span>
            </div>
        </div>

        <div id="lesson-info">
            <h2 id="lesson-title"></h2>
            <div id="lesson-targets"></div>
        </div>

        <div class="stats">
            <div><span id="wpm">0</span> WPM</div>
            <div><span id="accuracy">100</span>% ACC</div>
        </div>

        <div id="text-display"></div>
        <input type="text" id="input-field" autofocus>

        <div class="controls">
            <a href="<?= htmlspecialchars($backUrl) ?>" id="home-btn" title="Back to Gaming Hub">
                <i class="fas fa-arrow-left"></i>
            </a>
            <button id="restart-btn">Restart Lesson</button>
        </div>

        <!-- Keyboard UI -->
        <div id="keyboard-container">
            <div id="main-keyboard-block">
                <div class="keyboard-row f-group"><div class="key" data-key="Escape">esc</div><div class="f-group-spacer"></div><div class="key" data-key="F1">F1</div><div class="key" data-key="F2">F2</div><div class="key" data-key="F3">F3</div><div class="key" data-key="F4">F4</div><div class="f-group-spacer"></div><div class="key" data-key="F5">F5</div><div class="key" data-key="F6">F6</div><div class="key" data-key="F7">F7</div><div class="key" data-key="F8">F8</div><div class="f-group-spacer"></div><div class="key" data-key="F9">F9</div><div class="key" data-key="F10">F10</div><div class="key" data-key="F11">F11</div><div class="key" data-key="F12">F12</div></div>
                <div class="keyboard-row">
                    <div class="key" data-key="Backquote"><span class="symbol">~</span><span class="main">`</span></div>
                    <div class="key" data-key="Digit1"><span class="symbol">!</span><span class="main">1</span></div>
                    <div class="key" data-key="Digit2"><span class="symbol">@</span><span class="main">2</span></div>
                    <div class="key" data-key="Digit3"><span class="symbol">#</span><span class="main">3</span></div>
                    <div class="key" data-key="Digit4"><span class="symbol">$</span><span class="main">4</span></div>
                    <div class="key" data-key="Digit5"><span class="symbol">%</span><span class="main">5</span></div>
                    <div class="key" data-key="Digit6"><span class="symbol">^</span><span class="main">6</span></div>
                    <div class="key" data-key="Digit7"><span class="symbol">&amp;</span><span class="main">7</span></div>
                    <div class="key" data-key="Digit8"><span class="symbol">*</span><span class="main">8</span></div>
                    <div class="key" data-key="Digit9"><span class="symbol">(</span><span class="main">9</span></div>
                    <div class="key" data-key="Digit0"><span class="symbol">)</span><span class="main">0</span></div>
                    <div class="key" data-key="Minus"><span class="symbol">_</span><span class="main">-</span></div>
                    <div class="key" data-key="Equal"><span class="symbol">+</span><span class="main">=</span></div>
                    <div class="key key-bs" data-key="Backspace">backspace</div>
                </div>
                <div class="keyboard-row">
                    <div class="key key-tab" data-key="Tab">tab</div>
                    <div class="key" data-key="KeyQ">Q</div><div class="key" data-key="KeyW">W</div><div class="key" data-key="KeyE">E</div><div class="key" data-key="KeyR">R</div><div class="key" data-key="KeyT">T</div><div class="key" data-key="KeyY">Y</div><div class="key" data-key="KeyU">U</div><div class="key" data-key="KeyI">I</div><div class="key" data-key="KeyO">O</div><div class="key" data-key="KeyP">P</div>
                    <div class="key" data-key="BracketLeft"><span class="symbol">{</span><span class="main">[</span></div>
                    <div class="key" data-key="BracketRight"><span class="symbol">}</span><span class="main">]</span></div>
                    <div class="key key-backslash" data-key="Backslash"><span class="symbol">|</span><span class="main">\</span></div>
                </div>
                <div class="keyboard-row">
                    <div class="key key-caps" data-key="CapsLock">caps lock</div>
                    <div class="key" data-key="KeyA">A</div><div class="key" data-key="KeyS">S</div><div class="key" data-key="KeyD">D</div><div class="key" data-key="KeyF">F</div><div class="key" data-key="KeyG">G</div><div class="key" data-key="KeyH">H</div><div class="key" data-key="KeyJ">J</div><div class="key" data-key="KeyK">K</div><div class="key" data-key="KeyL">L</div>
                    <div class="key" data-key="Semicolon"><span class="symbol">:</span><span class="main">;</span></div>
                    <div class="key" data-key="Quote"><span class="symbol">"</span><span class="main">'</span></div>
                    <div class="key key-enter" data-key="Enter">enter</div>
                </div>
                <div class="keyboard-row">
                    <div class="key key-shift-l" data-key="ShiftLeft">shift</div>
                    <div class="key" data-key="KeyZ">Z</div><div class="key" data-key="KeyX">X</div><div class="key" data-key="KeyC">C</div><div class="key" data-key="KeyV">V</div><div class="key" data-key="KeyB">B</div><div class="key" data-key="KeyN">N</div><div class="key" data-key="KeyM">M</div>
                    <div class="key" data-key="Comma"><span class="symbol">&lt;</span><span class="main">,</span></div>
                    <div class="key" data-key="Period"><span class="symbol">&gt;</span><span class="main">.</span></div>
                    <div class="key" data-key="Slash"><span class="symbol">?</span><span class="main">/</span></div>
                    <div class="key key-shift-r" data-key="ShiftRight">shift</div>
                </div>
                <div class="keyboard-row"><div class="key key-mod" data-key="ControlLeft">ctrl</div><div class="key key-mod" data-key="MetaLeft">win</div><div class="key key-mod" data-key="AltLeft">alt</div><div class="key key-space" data-key="Space"></div><div class="key key-mod" data-key="AltRight">alt</div><div class="key key-mod" data-key="MetaRight">win</div><div class="key key-mod" data-key="ContextMenu">menu</div><div class="key key-mod" data-key="ControlRight">ctrl</div></div>
            </div>
        </div>

        <!-- Results Modal -->
        <div id="results-modal" class="modal-overlay hidden">
            <div class="modal-content">
                <h2>Lesson Complete</h2>
                <div id="results-summary"></div>
                <p id="results-message"></p>
                <div class="modal-buttons">
                    <button id="try-again-btn">Try Again</button>
                    <button id="next-lesson-btn">Next Lesson</button>
                </div>
            </div>
        </div>

        <div id="toast-notification">
            <i id="toast-icon"></i>
            <p id="toast-message"></p>
        </div>

    </div><!-- /.tg-container -->
</div><!-- /#typing-game-root -->

<script>
const gameConfig = {
    isPremium: <?= json_encode(isset($user['tier']) && $user['tier'] > 0) ?>,
    startLessonIndex: 0,
    isAdmin: false,
    profile_picture: '',
    currentUser: {
        id: <?= json_encode((int)($user['id'] ?? 0)) ?>,
        name: <?= json_encode(htmlspecialchars($user['full_name'] ?? $user['username'] ?? 'Player')) ?>
    }
};
</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const textDisplayEl = document.getElementById('text-display');
    const inputFieldEl = document.getElementById('input-field');
    const wpmEl = document.getElementById('wpm');
    const accuracyEl = document.getElementById('accuracy');
    const lessonTitleEl = document.getElementById('lesson-title');
    const lessonTargetsEl = document.getElementById('lesson-targets');
    const restartBtn = document.getElementById('restart-btn');
    const resultsModal = document.getElementById('results-modal');
    const resultsSummaryEl = document.getElementById('results-summary');
    const resultsMessageEl = document.getElementById('results-message');
    const tryAgainBtn = document.getElementById('try-again-btn');
    const nextLessonBtn = document.getElementById('next-lesson-btn');
    const hourHand = document.getElementById('hour-hand');
    const minuteHand = document.getElementById('minute-hand');
    const secondHand = document.getElementById('second-hand');
    const progressPieChartEl = document.getElementById('progress-pie-chart');
    const progressPieTextEl = document.getElementById('progress-pie-text');
    const toastNotification = document.getElementById('toast-notification');
    const toastIcon = document.getElementById('toast-icon');
    const toastMessage = document.getElementById('toast-message');

    let state = { currentLessonIndex: 0, text: "", input: "", startTime: null, isTestRunning: false };
    let isGamePaused = false;
    let audioContext;
    let isAudioInitialized = false;
    let toastTimeout;
    let updateClock;

    function showToast(message, type = 'success') {
        if (!toastNotification) return;
        clearTimeout(toastTimeout);
        toastMessage.textContent = message;
        toastNotification.className = '';
        toastIcon.className = '';
        toastNotification.classList.add(type);
        toastIcon.classList.add('fas', type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle', type);
        toastNotification.classList.add('show');
        toastTimeout = setTimeout(() => { toastNotification.classList.remove('show'); }, 4000);
    }

    function initAudioContext() {
        try {
            if (!audioContext) audioContext = new (window.AudioContext || window.webkitAudioContext)();
            if (audioContext.state === 'suspended') audioContext.resume();
            isAudioInitialized = true;
        } catch (e) { console.warn('Could not initialize audio context:', e); }
    }

    function playKeySound(keyType = 'normal') {
        if (!isAudioInitialized) initAudioContext();
        if (!audioContext) return;
        try {
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            let freq = 800, dur = 0.08, vol = 0.12;
            switch(keyType) {
                case 'backspace': freq = 350; dur = 0.12; vol = 0.2; break;
                case 'space':    freq = 250; dur = 0.08; vol = 0.1; break;
                case 'enter':    freq = 500; dur = 0.15; vol = 0.18; break;
                case 'letter':   freq = 750 + Math.random() * 200; dur = 0.06; vol = 0.12; break;
            }
            oscillator.frequency.setValueAtTime(freq, audioContext.currentTime);
            oscillator.type = 'square';
            const now = audioContext.currentTime;
            gainNode.gain.setValueAtTime(0, now);
            gainNode.gain.linearRampToValueAtTime(vol, now + 0.01);
            gainNode.gain.exponentialRampToValueAtTime(0.001, now + dur);
            oscillator.start(now);
            oscillator.stop(now + dur);
            oscillator.onended = () => { oscillator.disconnect(); gainNode.disconnect(); };
        } catch (e) { console.warn('Error playing key sound:', e); }
    }

    const lessons = [
        { "title": "Lesson 1: The Home Row", "text": "asdf jkl; asdf jkl; a sad lad; a fall; ask a fad; adds a flash; a lass;", "targetWpm": 10, "targetAcc": 90 },
        { "title": "Lesson 2: E and I Keys", "text": "eid fie eke elf ski lid lie die kid did did fed fee led led fled fled field field;", "targetWpm": 15, "targetAcc": 92 },
        { "title": "Lesson 3: T, G and Y Keys", "text": "get tag get fly the fly the try the jot the got the jet flay flay stay stay;", "targetWpm": 18, "targetAcc": 93 },
        { "title": "Lesson 4: Common Words", "text": "the and for are but not you all can her was one our out day get has him his how its let man new now old see two way who boy did its let man old put say she too use", "targetWpm": 22, "targetAcc": 95 },
        { "title": "Lesson 12: Alphabet Review", "text": "The quick brown fox jumps over the lazy dog. Pack my box with five dozen liquor jugs.", "targetWpm": 25, "targetAcc": 98 },
        { "title": "Lesson 24: Numbers Row", "text": "The year 2025 marks a turning point. There are 26 letters and 10 digits. Call 123-4567 for details.", "targetWpm": 30, "targetAcc": 96 },
        { "title": "Lesson 36: Speed Test", "text": "The ability to type quickly and accurately is a valuable asset in today's digital world.", "targetWpm": 54, "targetAcc": 99 }
    ];

    function loadLesson(lessonIndex) {
        state.currentLessonIndex = lessonIndex;
        if (lessonIndex >= lessons.length) {
            lessonTitleEl.textContent = "Course Complete!";
            textDisplayEl.innerHTML = "Congratulations! You have finished all lessons.";
            lessonTargetsEl.innerHTML = "";
            inputFieldEl.blur();
            inputFieldEl.classList.add('hidden');
            restartBtn.textContent = "Start Over";
            resultsModal.classList.add('hidden');
            return;
        }
        const lesson = lessons[lessonIndex];
        state.text = lesson.text;
        state.input = "";
        state.startTime = null;
        state.isTestRunning = false;
        lessonTitleEl.textContent = lesson.title;
        lessonTargetsEl.innerHTML = `Targets: <b>${lesson.targetWpm} WPM</b> | <b>${lesson.targetAcc}% ACC</b>`;
        wpmEl.textContent = "0";
        accuracyEl.textContent = "100";
        inputFieldEl.value = "";
        inputFieldEl.classList.remove('hidden');
        inputFieldEl.focus();
        restartBtn.textContent = "Restart Lesson";
        renderText();
        resultsModal.classList.add('hidden');
        updateProgressPie(0);
    }

    function renderText() {
        const textChars = state.text.split('');
        const inputChars = state.input.split('');
        const htmlElements = [];
        function escapeHtml(char) {
            if (char === '<') return '&lt;';
            if (char === '>') return '&gt;';
            if (char === '&') return '&amp;';
            return char;
        }
        textChars.forEach((char, index) => {
            if (index === inputChars.length) htmlElements.push('<span class="cursor"></span>');
            let className = (index < inputChars.length) ? (inputChars[index] === char ? 'correct' : 'incorrect') : '';
            htmlElements.push(`<span class="${className}">${escapeHtml(char)}</span>`);
        });
        if (inputChars.length === textChars.length && !textDisplayEl.querySelector('.cursor')) {
            htmlElements.push('<span class="cursor"></span>');
        }
        textDisplayEl.innerHTML = htmlElements.join('');
    }

    function handleInput() {
        if (!state.isTestRunning && inputFieldEl.value.length > 0) {
            state.isTestRunning = true;
            state.startTime = new Date();
        }
        state.input = inputFieldEl.value;
        renderText();
        updateStats();
        if (state.text.length > 0) updateProgressPie((state.input.length / state.text.length) * 100);
        if (state.input.length >= state.text.length) {
            state.isTestRunning = false;
            inputFieldEl.blur();
            showResults();
        }
    }

    function updateStats() {
        if (!state.isTestRunning || !state.startTime) return;
        const timeElapsed = (new Date() - state.startTime) / 1000 / 60;
        if (timeElapsed === 0) return;
        wpmEl.textContent = Math.round((state.input.length / 5) / timeElapsed);
        let errors = state.input.split('').reduce((acc, char, index) =>
            (index < state.text.length && char !== state.text[index]) ? acc + 1 : acc, 0);
        accuracyEl.textContent = state.input.length > 0
            ? Math.round(((state.input.length - errors) / state.input.length) * 100)
            : 100;
    }

    function showResults() {
        const lesson = lessons[state.currentLessonIndex];
        const finalWpm = parseInt(wpmEl.textContent);
        const finalAcc = parseInt(accuracyEl.textContent);
        resultsSummaryEl.innerHTML = `Your Speed: <b>${finalWpm} WPM</b> | Your Accuracy: <b>${finalAcc}%</b>`;
        if (finalWpm >= lesson.targetWpm && finalAcc >= lesson.targetAcc) {
            resultsMessageEl.textContent = "Excellent! You passed the target.";
            resultsMessageEl.className = 'pass';
            nextLessonBtn.classList.remove('hidden');
        } else {
            resultsMessageEl.textContent = "You can do better. Try again to pass.";
            resultsMessageEl.className = 'fail';
            nextLessonBtn.classList.add('hidden');
        }
        nextLessonBtn.textContent = (state.currentLessonIndex >= lessons.length - 1) ? "Finish Course" : "Next Lesson";
        resultsModal.classList.remove('hidden');
    }

    function handleKeyDown(e) {
        if (isGamePaused) return;
        if (!resultsModal.classList.contains('hidden')) {
            e.preventDefault();
            if (e.key === 'Enter' && !nextLessonBtn.classList.contains('hidden')) nextLessonBtn.click();
            else if (e.key === ' ' || e.code === 'Space' || e.key === 'Tab') tryAgainBtn.click();
            return;
        }
        if (e.key === 'Tab' && document.activeElement === inputFieldEl) {
            e.preventDefault();
            const start = inputFieldEl.selectionStart;
            inputFieldEl.value = inputFieldEl.value.substring(0, start) + ' ' + inputFieldEl.value.substring(start);
            inputFieldEl.selectionStart = inputFieldEl.selectionEnd = start + 1;
            inputFieldEl.dispatchEvent(new Event('input', { bubbles: true }));
            playKeySound('space');
            return;
        }
        if (document.activeElement === inputFieldEl) {
            let keyType = 'normal';
            if (e.key === 'Backspace') keyType = 'backspace';
            else if (e.key === ' ' || e.code === 'Space') keyType = 'space';
            else if (e.key === 'Enter') keyType = 'enter';
            else if (e.key.length === 1) keyType = 'letter';
            if (keyType !== 'normal' || (e.key.length === 1 && !e.ctrlKey && !e.altKey)) playKeySound(keyType);
        }
        const keyElement = document.querySelector(`#typing-game-root .key[data-key="${e.code}"]`);
        if (keyElement) keyElement.classList.add('key-pressed');
    }

    function handleKeyUp(e) {
        const keyElement = document.querySelector(`#typing-game-root .key[data-key="${e.code}"]`);
        if (keyElement) keyElement.classList.remove('key-pressed');
    }

    function updateProgressPie(percentage) {
        if (!progressPieChartEl || !progressPieTextEl) return;
        const degrees = percentage * 3.6;
        progressPieChartEl.style.background =
            `conic-gradient(var(--tg-correct) ${degrees}deg, #3b4048 ${degrees}deg)`;
        progressPieTextEl.textContent = `${Math.round(percentage)}%`;
    }

    inputFieldEl.addEventListener('input', handleInput);
    document.addEventListener('keydown', handleKeyDown);
    document.addEventListener('keyup', handleKeyUp);

    restartBtn.addEventListener('click', () =>
        loadLesson(restartBtn.textContent === "Start Over" ? 0 : state.currentLessonIndex));
    tryAgainBtn.addEventListener('click', () => loadLesson(state.currentLessonIndex));
    nextLessonBtn.addEventListener('click', () => loadLesson(state.currentLessonIndex + 1));

    const initAudioOnce = () => {
        initAudioContext();
        document.removeEventListener('click', initAudioOnce);
        document.removeEventListener('keydown', initAudioOnce);
    };
    document.addEventListener('click', initAudioOnce);
    document.addEventListener('keydown', initAudioOnce);

    updateClock = () => {
        const now = new Date();
        const s = now.getSeconds() * 6;
        const m = now.getMinutes() * 6 + now.getSeconds() * 0.1;
        const h = (now.getHours() % 12) * 30 + now.getMinutes() * 0.5;
        if (hourHand && minuteHand && secondHand) {
            secondHand.style.transform = `translateX(-50%) rotate(${s}deg)`;
            minuteHand.style.transform = `translateX(-50%) rotate(${m}deg)`;
            hourHand.style.transform = `translateX(-50%) rotate(${h}deg)`;
        }
    };
    updateClock();
    setInterval(updateClock, 1000);

    loadLesson(gameConfig.startLessonIndex || 0);
});
</script>
