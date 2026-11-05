<?php
$csrfToken = getCsrfToken();
$isPremium = $data['isPremium'] ?? false;
$startLessonIndex = $data['startLessonIndex'] ?? 0;
$isAdmin = $data['isAdmin'] ?? false; // Get the new admin flag
$profilePicture = $_SESSION['user_profile_picture'] ?? '';
$fullName = $_SESSION['user_full_name'] ?? 'User';
$initial = strtoupper(substr($fullName, 0, 1));
if (empty($profilePicture)) {
    $svg = '
    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32">
        <circle cx="16" cy="16" r="16" fill="#4B5563"/>
        <text x="50%" y="50%" text-anchor="middle" dy=".35em"
              font-family="Arial, sans-serif" font-size="16"
              fill="#ffffff">' . htmlspecialchars($initial) . '</text>
    </svg>';
    $profilePicture = 'data:image/svg+xml;base64,' . base64_encode($svg);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
    <title>Elegant Typing Engine</title>
    <link href="https://fonts.googleapis.com/css2?family=Source+Code+Pro:wght@400;500&family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="/assets/gaming/typing/css/typing.css" rel="stylesheet">
    <link rel="icon" href="data:image/svg+xml,%3csvg width='32' height='32' viewBox='0 0 32 32' xmlns='http://www.w3.org/2000/svg' fill='%23dcdfe4'%3e%3crect x='3' y='3' width='26' height='26' rx='6' ry='6' fill='%233b4048'/%3e%3crect x='3' y='5' width='26' height='24' rx='6' ry='6' fill='%2331363f'/%3e%3ctext x='50%25' y='50%25' text-anchor='middle' dy='.35em' font-family='Source Code Pro, monospace' font-size='16' font-weight='600'%3eT%3c/text%3e%3c/svg%3e">
</head>
<body>
<?php if ($isAdmin): ?>
<!-- ============== ELEGANT ADMIN HEADER ============== -->
<header class="admin-header">
    <div class="admin-header-title">Admin Controls</div>
    
    <div class="typeahead-container">
        <!-- The new wrapper for icon + input -->
        <div class="search-wrapper">
            <i class="fas fa-search"></i>
            <input type="search" id="admin-user-search" placeholder="Search users by name or email...">
        </div>
        <div id="typeahead-results" class="hidden"></div>
    </div>

    <!-- NEW: Admin Avatar Element -->
    <div class="admin-avatar-container">
        <img id="admin-avatar-img" src="<?=$profilePicture?>" alt="Admin Avatar">
        <!-- Dropdown menu can be added here later -->
    </div>
    
    <div><!-- You can add other admin links here in the future --></div>
</header>
<?php endif; ?>



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

    <!-- All your existing game HTML here -->
    <!-- The .container should have its top margin adjusted if admin bar is present -->
    <div class="container" style="<?php if ($isAdmin) echo 'padding-top: 80px;'; ?>">
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
            <a href="/" id="home-btn" title="Return to Homepage">
                <i class="fas fa-home"></i>
            </a>
            <button id="restart-btn">Restart Lesson</button>
        </div>
        
        <!-- =================== KEYBOARD HTML UPDATED START =================== -->
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
                    <div class="key" data-key="Digit7"><span class="symbol">&</span><span class="main">7</span></div>
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
                    <div class="key" data-key="Comma"><span class="symbol"><</span><span class="main">,</span></div>
                    <div class="key" data-key="Period"><span class="symbol">></span><span class="main">.</span></div>
                    <div class="key" data-key="Slash"><span class="symbol">?</span><span class="main">/</span></div>
                    <div class="key key-shift-r" data-key="ShiftRight">shift</div>
                </div>
                <div class="keyboard-row"><div class="key key-mod" data-key="ControlLeft">ctrl</div><div class="key key-mod" data-key="MetaLeft">win</div><div class="key key-mod" data-key="AltLeft">alt</div><div class="key key-space" data-key="Space"></div><div class="key key-mod" data-key="AltRight">alt</div><div class="key key-mod" data-key="MetaRight">win</div><div class="key key-mod" data-key="ContextMenu">menu</div><div class="key key-mod" data-key="ControlRight">ctrl</div></div>
            </div>
            <div id="nav-block">
                <div class="keyboard-row"><div class="key" data-key="PrintScreen">prt<br>sc</div><div class="key" data-key="ScrollLock">scr<br>lk</div><div class="key" data-key="Pause">pause</div></div>
                <div class="keyboard-row"><div class="key" data-key="Insert">ins</div><div class="key" data-key="Home">home</div><div class="key" data-key="PageUp">pg up</div></div>
                <div class="keyboard-row"><div class="key" data-key="Delete">del</div><div class="key" data-key="End">end</div><div class="key" data-key="PageDown">pg dn</div></div>
                <div class="arrow-cluster"><div class="keyboard-row" style="justify-content: center;"><div class="key" data-key="ArrowUp">▲</div></div><div class="keyboard-row"><div class="key" data-key="ArrowLeft">◀</div><div class="key" data-key="ArrowDown">▼</div><div class="key" data-key="ArrowRight">▶</div></div></div>
            </div>
            <div id="numpad-block">
                <div class="status-lights"><div id="num-light" class="light"></div><div id="caps-light" class="light"></div><div id="scroll-light" class="light"></div></div>
                <div class="keyboard-row"><div class="key" data-key="NumLock">num<br>lk</div><div class="key" data-key="NumpadDivide">/</div><div class="key" data-key="NumpadMultiply">*</div><div class="key" data-key="NumpadSubtract">-</div></div>
                <div class="numpad-layout">
                    <div class="numpad-main-col"><div class="keyboard-row"><div class="key" data-key="Numpad7">7</div><div class="key" data-key="Numpad8">8</div><div class="key" data-key="Numpad9">9</div></div><div class="keyboard-row"><div class="key" data-key="Numpad4">4</div><div class="key" data-key="Numpad5">5</div><div class="key" data-key="Numpad6">6</div></div><div class="keyboard-row"><div class="key" data-key="Numpad1">1</div><div class="key" data-key="Numpad2">2</div><div class="key" data-key="Numpad3">3</div></div><div class="keyboard-row"><div class="key numpad-zero" data-key="Numpad0">0</div><div class="key" data-key="NumpadDecimal">.</div></div></div>
                    <div class="numpad-side-col"><div class="key numpad-plus" data-key="NumpadAdd">+</div><div class="key numpad-enter" data-key="NumpadEnter">enter</div></div>
                </div>
            </div>
        </div>
        <!-- =================== KEYBOARD HTML UPDATED END =================== -->
    </div>
    
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

    <?php if ($isAdmin): ?>
    <!-- ============== ADMIN MODAL ============== -->
    <div id="admin-user-modal" class="modal-overlay hidden">
        <div class="modal-content">
            <h2 id="admin-modal-username">User Settings</h2>
            <div class="settings-list mt-4">
                <div class="admin-toggle-row">
                    <label for="premium-toggle">Premium Account</label>
                    <label class="toggle-switch">
                        <input type="checkbox" id="premium-toggle">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                <div class="admin-toggle-row">
                    <label for="admin-toggle">Administrator Role</label>
                    <label class="toggle-switch">
                        <input type="checkbox" id="admin-toggle">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>
            <div class="modal-buttons">
                <button id="admin-modal-close-btn">Cancel</button>
                <button id="admin-modal-save-btn">Save Changes</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div id="toast-notification">
        <i id="toast-icon"></i>
        <p id="toast-message"></p>
    </div>

    <script>
        // Create a single, structured configuration object for the game,
        // just like the pattern you provided.
        const gameConfig = {
            isPremium: <?php echo json_encode($isPremium); ?>,
            startLessonIndex: <?php echo json_encode($startLessonIndex); ?>,
            isAdmin: <?php echo json_encode($isAdmin); ?>,
            profile_picture: <?php echo json_encode($profilePicture); ?>,
            currentUser: <?php echo json_encode($data['currentUser']); ?>
        };
    </script>
    <script src="/assets/client/js/typeahead.js"></script>
    <script src="/assets/gaming/typing/js/typing.js"></script>
</body>
</html>