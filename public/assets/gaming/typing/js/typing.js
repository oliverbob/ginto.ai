document.addEventListener('DOMContentLoaded', () => {
    // --- UI ELEMENTS ---
    const textDisplayEl = document.getElementById('text-display');
    const inputFieldEl = document.getElementById('input-field');
    const adminHeader = document.querySelector('.admin-header');
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
    const numLight = document.getElementById('num-light');
    const capsLight = document.getElementById('caps-light');
    const scrollLight = document.getElementById('scroll-light');
    const hourHand = document.getElementById('hour-hand');
    const minuteHand = document.getElementById('minute-hand');
    const secondHand = document.getElementById('second-hand');
    const progressPieChartEl = document.getElementById('progress-pie-chart');
    const progressPieTextEl = document.getElementById('progress-pie-text');
    const adminModal = document.getElementById('admin-user-modal');
    const adminModalUsername = document.getElementById('admin-modal-username');
    const premiumToggle = document.getElementById('premium-toggle');
    const adminToggle = document.getElementById('admin-toggle');
    const adminModalCloseBtn = document.getElementById('admin-modal-close-btn');
    const adminModalSaveBtn = document.getElementById('admin-modal-save-btn');
    const toastNotification = document.getElementById('toast-notification');
    const toastIcon = document.getElementById('toast-icon');
    const toastMessage = document.getElementById('toast-message');
    const adminAvatarImg = document.getElementById('admin-avatar-img');


    // --- STATE MANAGEMENT ---
    let state = { currentLessonIndex: 0, text: "", input: "", startTime: null, isTestRunning: false };
    let isGamePaused = false;
    let audioContext;
    let isAudioInitialized = false;
    let toastTimeout;

    // --- NOTIFICATION FUNCTION ---
    function showToast(message, type = 'success') {
        if (!toastNotification) return;
        clearTimeout(toastTimeout);
        toastMessage.textContent = message;
        toastNotification.className = '';
        toastIcon.className = '';
        toastNotification.classList.add(type);
        toastIcon.classList.add('fas', type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle', type);
        toastNotification.classList.add('show');
        toastTimeout = setTimeout(() => {
            toastNotification.classList.remove('show');
        }, 4000);
    }

    // --- PAUSE & ADMIN FUNCTIONS ---
    window.pauseGame = () => {
        isGamePaused = true;
        console.log('Game Paused. Admin input active.');
    };
    window.resumeGame = () => {
        isGamePaused = false;
        if (resultsModal.classList.contains('hidden') && (!adminModal || adminModal.classList.contains('hidden'))) {
           inputFieldEl.focus();
        }
        console.log('Game Resumed.');
    };
    window.openAdminModal = (user) => {
        if (!adminModal) return;
        window.pauseGame();
        adminModal.dataset.userId = user.id;
        adminModalUsername.textContent = `Settings for ${user.full_name}`;
        premiumToggle.checked = user.is_premium;
        adminToggle.checked = user.role === 'admin';
        adminModal.classList.remove('hidden');
    };
    function closeAdminModal() {
        if (adminModal) {
            adminModal.classList.add('hidden');
            window.resumeGame();
        }
    }
    async function saveAdminChanges() {
        if (!adminModalSaveBtn) return;
        const originalButtonText = adminModalSaveBtn.textContent;
        adminModalSaveBtn.textContent = 'Saving...';
        adminModalSaveBtn.disabled = true;
        const userId = adminModal.dataset.userId;
        const payload = { userId: userId, setAsPremium: premiumToggle.checked, setAsAdmin: adminToggle.checked };
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        try {
            const response = await fetch('/admin/update-user-status', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify(payload)
            });
            const result = await response.json();
            if (response.ok) {
                showToast('User updated successfully!', 'success');
                closeAdminModal();
            } else {
                showToast(`Error: ${result.message}`, 'error');
            }
        } catch (error) {
            console.error("Failed to save admin changes:", error);
            showToast('An unexpected network error occurred.', 'error');
        } finally {
            adminModalSaveBtn.textContent = originalButtonText;
            adminModalSaveBtn.disabled = false;
        }
    }

    // --- AUDIO SYSTEM ---
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
                case 'space': freq = 250; dur = 0.08; vol = 0.1; break;
                case 'enter': freq = 500; dur = 0.15; vol = 0.18; break;
                case 'letter': freq = 750 + Math.random() * 200; dur = 0.06; vol = 0.12; break;
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

    // --- LESSON DATA ---
    const lessons = [
        { "title": "Lesson 1: The Home Row", "text": "asdf jkl; asdf jkl; a sad lad; a fall; ask a fad; adds a flash; a lass;", "targetWpm": 10, "targetAcc": 90 },
        { "title": "Lesson 2: Keys 'e' and 'i'", "text": "asdf jkl; ei ei deki deki; a life; a lie; see a fee; she is; he did; a sad life;", "targetWpm": 12, "targetAcc": 92 },
        { "title": "Lesson 3: Keys 'r' and 'u'", "text": "fr ju fr ju ru ru; fur fur; jar jar; red red; true true; far; a user; a ruler; an idea;", "targetWpm": 14, "targetAcc": 92 },
        { "title": "Lesson 4: Keys 't' and 'y'", "text": "ft jy ft jy ty ty; try try; a lit flask; a flirty lad; stay; that; they; the jury;", "targetWpm": 15, "targetAcc": 93 },
        { "title": "Lesson 5: Keys 'g' and 'h'", "text": "fg jh fg jh gh hg; hag; gas; high; huge; fight; a high flag; he had a fresh fish;", "targetWpm": 16, "targetAcc": 94 },
        { "title": "Lesson 6: Keys 'o' and 'w'", "text": "sl wo sl wo ow ow; old; low; who; how; two; follow; a slow dog; a good show; a world;", "targetWpm": 17, "targetAcc": 94 },
        { "title": "Lesson 7: Keys 'c' and ','", "text": "dc ,l dc ,l c, c,; cat, cow, city, code; a cool car, a classic case, a clear choice;", "targetWpm": 18, "targetAcc": 95 },
        { "title": "Lesson 8: Keys 'b' and 'n'", "text": "fb jn fb jn bn nb; ban; bun; nab; brain; a big brown bag; a number of new books;", "targetWpm": 19, "targetAcc": 95 },
        { "title": "Lesson 9: Keys 'v' and 'm'", "text": "fv jm fv jm vm mv; have; move; very; jam; a vivid memory; a movie gave him a view;", "targetWpm": 20, "targetAcc": 96 },
        { "title": "Lesson 10: Keys 'x', 'z' and '.'", "text": "sx .l sx .l z. xz; box. fix. jazz. zoo. exit. lazy. a lazy fox. a complex puzzle.", "targetWpm": 21, "targetAcc": 96 },
        { "title": "Lesson 11: Keys 'q' and 'p'", "text": "aq ;p aq ;p qp pq; quit; part; pool; happy; a quick quiz; a popular public place;", "targetWpm": 22, "targetAcc": 97 },
        { "title": "Lesson 12: Alphabet Review", "text": "The quick brown fox jumps over the lazy dog. Pack my box with five dozen liquor jugs.", "targetWpm": 25, "targetAcc": 98 },
        { "title": "Lesson 13: Shift Key and Capitals", "text": "The dog's name is Max. He lives in New York. My friend, John, is from Paris, France.", "targetWpm": 26, "targetAcc": 98 },
        { "title": "Lesson 14: Question Mark '?'", "text": "What is your name? Where are you going? Did you finish the task? How old are you?", "targetWpm": 27, "targetAcc": 98 },
        { "title": "Lesson 15: Apostrophe ' and Quotes \"", "text": "It's a beautiful day, isn't it? He said, \"I'll be right back.\" She asked, \"Can't we go?\"", "targetWpm": 28, "targetAcc": 98 },
        { "title": "Lesson 16: Sentence Practice", "text": "The early bird catches the worm. Success is not final, failure is not fatal: it is the courage to continue that counts.", "targetWpm": 30, "targetAcc": 98 },
        { "title": "Lesson 17: Paragraph Drill 1", "text": "Technology has revolutionized the way we live and work. From communication to transportation, its impact is undeniable. The pace of innovation continues to accelerate, promising an even more connected future.", "targetWpm": 32, "targetAcc": 98 },
        { "title": "Lesson 18: Numbers 1-5", "text": "12345 54321; I need 1 apple, 2 bananas, 3 carrots, 4 dates, and 5 eggs. The code is 51423.", "targetWpm": 30, "targetAcc": 97 },
        { "title": "Lesson 19: Numbers 6-0", "text": "67890 09876; My flight 789 departs at 6 PM. The address is 100 Main Street. Call me at 555-0199.", "targetWpm": 31, "targetAcc": 97 },
        { "title": "Lesson 20: Full Number Row Practice", "text": "The year 1998 was a great one for science. The project budget is $4,567,890 for the 2024 fiscal year.", "targetWpm": 33, "targetAcc": 98 },
        { "title": "Lesson 21: Symbols ! @ # $ %", "text": "Wow! My email is user@example.com. That shirt costs $25. It's a 50% discount! #trending", "targetWpm": 34, "targetAcc": 97 },
        { "title": "Lesson 22: Symbols ^ & * ( ) - _ + =", "text": "The formula is (a + b) * c = d. You & I should work together. Use Ctrl+Alt_Del. The answer is 6^2 = 36.", "targetWpm": 35, "targetAcc": 97 },
        { "title": "Lesson 23: Mixed Symbol Practice", "text": "Please confirm your booking (Ref: #A8C-23_B) for $150.00. Your new password is 'P@ssw0rd!'. Contact support@web.com for help.", "targetWpm": 36, "targetAcc": 98 },
        { "title": "Lesson 24: Common English Words", "text": "the of and a to in is you that it he was for on are as with his they I at be this have from or one had by words but not what", "targetWpm": 38, "targetAcc": 98 },
        { "title": "Lesson 25: Speed Building 1", "text": "The journey of a thousand miles begins with a single step. Do not wait to strike till the iron is hot; but make it hot by striking.", "targetWpm": 40, "targetAcc": 98 },
        { "title": "Lesson 26: Speed Building 2", "text": "Whether you think you can, or you think you can't, you're right. The only way to do great work is to love what you do. Stay hungry, stay foolish.", "targetWpm": 42, "targetAcc": 99 },
        { "title": "Lesson 27: Accuracy Drill (Tricky Words)", "text": "It is necessary to accommodate their separate requests. I guarantee you'll find their weird behavior quite embarrassing. Definitely receive the schedule.", "targetWpm": 40, "targetAcc": 99 },
        { "title": "Lesson 28: Paragraph Drill 2", "text": "The sun dipped below the horizon, painting the sky in shades of orange and purple. A gentle breeze rustled the leaves in the trees, creating a soothing sound that calmed the soul. It was a perfect end to a long day.", "targetWpm": 44, "targetAcc": 99 },
        { "title": "Lesson 29: Paragraph Drill 3", "text": "Effective communication is a critical skill in both personal and professional life. It involves not just speaking clearly, but also listening actively and understanding non-verbal cues. Mastering this skill can lead to better relationships and greater success.", "targetWpm": 46, "targetAcc": 99 },
        { "title": "Lesson 30: Advanced Punctuation", "text": "The company's goals--which are ambitious--include expanding into three new markets. The options are as follows: first, we can approve the budget; second, we can request a revision; third, we can reject it outright.", "targetWpm": 45, "targetAcc": 98 },
        { "title": "Lesson 31: Business Correspondence", "text": "Dear Mr. Harrison, Thank you for your email regarding invoice #7402-B. The payment of $1,250.50 was received on June 15th, 2024. Please let me know if you require any further information. Best regards, Samantha Chen, Accounts Department.", "targetWpm": 48, "targetAcc": 99 },
        { "title": "Lesson 32: Technical Text", "text": "The new CPU architecture utilizes a 7nm process, resulting in a 15% increase in instructions per clock (IPC). The integrated GPU supports DirectX 12 Ultimate and real-time ray tracing, offering unprecedented graphical fidelity for next-gen applications.", "targetWpm": 49, "targetAcc": 99 },
        { "title": "Lesson 33: Code Snippet (JavaScript)", "text": "const fetchData = async (url) => { try { const response = await fetch(url); if (!response.ok) { throw new Error(`HTTP error! status: ${response.status}`); } const data = await response.json(); console.log(data); } catch (e) { console.error('Fetch error:', e); } };", "targetWpm": 45, "targetAcc": 98 },
        { "title": "Lesson 34: Literary Passage 1", "text": "It was the best of times, it was the worst of times, it was the age of wisdom, it was the age of foolishness, it was the epoch of belief, it was the epoch of incredulity, it was the season of Light, it was the season of Darkness, it was the spring of hope, it was the winter of despair.", "targetWpm": 50, "targetAcc": 99 },
        { "title": "Lesson 35: Literary Passage 2", "text": "There is no greater agony than bearing an untold story inside you. The caged bird sings with a fearful trill of things unknown but longed for still and his tune is heard on the distant hill for the caged bird sings of freedom.", "targetWpm": 52, "targetAcc": 99 },
        { "title": "Lesson 36: Speed Test 1", "text": "The ability to type quickly and accurately is a valuable asset in today's digital world. It improves productivity, facilitates communication, and opens up new opportunities. Consistent practice is the key to improvement. Set realistic goals, focus on maintaining a steady rhythm, and don't be discouraged by mistakes. Over time, your fingers will learn the keyboard, and typing will become second nature.", "targetWpm": 54, "targetAcc": 99 },
        { "title": "Lesson 37: Speed Test 2", "text": "Climate change represents one of the greatest challenges of our time. Its effects are far-reaching, from rising sea levels to more extreme weather events. Addressing this issue requires a concerted global effort, involving governments, industries, and individuals. Transitioning to renewable energy sources, improving energy efficiency, and adopting sustainable practices are all crucial steps in mitigating its impact.", "targetWpm": 56, "targetAcc": 99 },
        { "title": "Lesson 38: Speed Test 3 (Mixed Content)", "text": "The final report (Project ID: Q4-2024_A) is due by 5:00 PM on Friday. Please ensure all data has been verified and that the summary includes the key performance indicators (KPIs). The budget overrun was approximately 12.5%, which must be addressed in the final analysis. Email the document to management@corp.com with the subject \"Final Report - Q4\".", "targetWpm": 58, "targetAcc": 99 },
        { "title": "Lesson 39: Final Challenge 1", "text": "To be, or not to be, that is the question: Whether 'tis nobler in the mind to suffer The slings and arrows of outrageous fortune, Or to take arms against a sea of troubles And by opposing end them. To die—to sleep, No more; and by a sleep to say we end The heart-ache and the thousand natural shocks That flesh is heir to: 'tis a consummation Devoutly to be wish'd.", "targetWpm": 60, "targetAcc": 99 },
        { "title": "Lesson 40: Final Challenge 2 (Expert)", "text": "The intersection of artificial intelligence and human creativity is a fascinating and rapidly evolving field. While AI can generate art, music, and text with remarkable proficiency, it currently lacks genuine consciousness and subjective experience. The true potential may lie not in replacement, but in collaboration, where AI acts as a powerful tool to augment and expand the horizons of human artists. This symbiotic relationship could redefine what we consider to be art itself.", "targetWpm": 65, "targetAcc": 99 }
    ];

    // --- CORE TYPING FUNCTIONS ---
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
            if (char === '<') return '<';
            if (char === '>') return '>';
            if (char === '&') return '&';
            return char;
        }

        textChars.forEach((char, index) => {
            if (index === inputChars.length) {
                htmlElements.push('<span class="cursor"></span>');
            }
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
            scrollLight.classList.add('active');
        }
        state.input = inputFieldEl.value;
        renderText();
        updateStats();

        if (state.text.length > 0) {
            const progress = (state.input.length / state.text.length) * 100;
            updateProgressPie(progress > 100 ? 100 : progress);
        }
        
        if (state.input.length >= state.text.length) {
            state.isTestRunning = false;
            scrollLight.classList.remove('active');
            inputFieldEl.blur();
            showResults();
        }
    }

    function updateStats() {
        if (!state.isTestRunning || !state.startTime) return;
        const timeElapsed = (new Date() - state.startTime) / 1000 / 60;
        if (timeElapsed === 0) return;
        wpmEl.textContent = Math.round((state.input.length / 5) / timeElapsed);
        let errors = state.input.split('').reduce((acc, char, index) => {
            return (index < state.text.length && char !== state.text[index]) ? acc + 1 : acc;
        }, 0);
        accuracyEl.textContent = state.input.length > 0 ? Math.round(((state.input.length - errors) / state.input.length) * 100) : 100;
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
            if (typeof gameConfig !== 'undefined' && gameConfig.isPremium) {
                saveProgressToServer({
                    lessonIndex: state.currentLessonIndex,
                    wpm: finalWpm,
                    accuracy: finalAcc
                });
            }
        } else {
            resultsMessageEl.textContent = "You can do better. Try again to pass.";
            resultsMessageEl.className = 'fail';
            nextLessonBtn.classList.add('hidden');
        }
        nextLessonBtn.textContent = (state.currentLessonIndex >= lessons.length - 1) ? "Finish Course" : "Next Lesson";
        resultsModal.classList.remove('hidden');
    }
    
    async function saveProgressToServer(payload) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        if (!csrfToken) {
            showToast('Security token missing. Please refresh.', 'error');
            return;
        }
        try {
            const response = await fetch('/gaming/save-progress', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify(payload)
            });
            const result = await response.json();
            if (response.ok) {
                console.log('Progress saved successfully:', result.message);
            } else {
                showToast(`Failed to save progress: ${result.message}`, 'error');
                console.error(`Failed to save progress (Status: ${response.status}):`, result.message);
            }
        } catch (error) {
            showToast('A network error occurred while saving progress.', 'error');
            console.error('Network or other error sending progress to server:', error);
        }
    }
    
    function handleKeyDown(e) {
        if (isGamePaused) {
            return;
        }
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
        const keyElement = document.querySelector(`.key[data-key="${e.code}"]`);
        if (keyElement) keyElement.classList.add('key-pressed');
    }

    function handleKeyUp(e) {
        if (e.key === 'CapsLock') capsLight.classList.toggle('active');
        if (e.key === 'NumLock') numLight.classList.toggle('active');
        const keyElement = document.querySelector(`.key[data-key="${e.code}"]`);
        if (keyElement) keyElement.classList.remove('key-pressed');
    }
    
    function updateClock() {
        const now = new Date();
        const secondDegrees = now.getSeconds() * 6;
        const minuteDegrees = now.getMinutes() * 6 + now.getSeconds() * 0.1;
        const hourDegrees = (now.getHours() % 12) * 30 + now.getMinutes() * 0.5;
        if(hourHand && minuteHand && secondHand) {
            secondHand.style.transform = `translateX(-50%) rotate(${secondDegrees}deg)`;
            minuteHand.style.transform = `translateX(-50%) rotate(${minuteDegrees}deg)`;
            hourHand.style.transform = `translateX(-50%) rotate(${hourDegrees}deg)`;
        }
    }

    function updateProgressPie(percentage) {
        if(!progressPieChartEl || !progressPieTextEl) return;
        const degrees = percentage * 3.6;
        const correctColor = 'var(--correct-color)';
        const inactiveColor = '#3b4048';
        progressPieChartEl.style.background = `conic-gradient(${correctColor} ${degrees}deg, ${inactiveColor} ${degrees}deg)`;
        progressPieTextEl.textContent = `${Math.round(percentage)}%`;
    }

    // --- INITIALIZATION ---
    inputFieldEl.addEventListener('input', handleInput);
    document.addEventListener('keydown', handleKeyDown);
    document.addEventListener('keyup', handleKeyUp);
    
    document.body.addEventListener('click', (e) => {
        if (
            (adminHeader && adminHeader.contains(e.target)) || 
            (adminModal && adminModal.contains(e.target)) || 
            (resultsModal && resultsModal.contains(e.target)) || 
            e.target.closest('button') ||
            e.target.closest('a')
        ) {
            return;
        }
        inputFieldEl.focus();
    });

    restartBtn.addEventListener('click', () => loadLesson(restartBtn.textContent === "Start Over" ? 0 : state.currentLessonIndex));
    tryAgainBtn.addEventListener('click', () => loadLesson(state.currentLessonIndex));
    nextLessonBtn.addEventListener('click', () => loadLesson(state.currentLessonIndex + 1));
    
    const initAudioOnce = () => { initAudioContext(); document.removeEventListener('click', initAudioOnce); document.removeEventListener('keydown', initAudioOnce); };
    document.addEventListener('click', initAudioOnce);
    document.addEventListener('keydown', initAudioOnce);
    
    if (typeof gameConfig !== 'undefined' && gameConfig.isAdmin) {
        if(adminModalCloseBtn) adminModalCloseBtn.addEventListener('click', closeAdminModal);
        if(adminModalSaveBtn) adminModalSaveBtn.addEventListener('click', saveAdminChanges);
        if (adminAvatarImg) {
            const currentUser = gameConfig.currentUser;
            if (window.createInitialAvatar) {
                adminAvatarImg.src = currentUser.profile_picture || window.createInitialAvatar(currentUser.full_name);
            }
        }
    }

    updateClock();
    setInterval(updateClock, 1000);
    
    loadLesson(typeof gameConfig !== 'undefined' ? gameConfig.startLessonIndex : 0);

    console.log('Typing tutor initialized with toast notifications.');
});