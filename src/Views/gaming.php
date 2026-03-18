<?php
/** @var array $user */
$game = $_GET['game'] ?? null;
$title = 'Ginto Gaming';

if ($game === 'typing') {
    // Full-screen typing game — no layout chrome
    $backUrl = '/gaming';
    include __DIR__ . '/layout/header.php';
    echo '<style>html,body{margin:0;padding:0;overflow:auto;background:#31363f;}</style>';
    include __DIR__ . '/games/typing.php';
    echo '</body></html>';
    return;
}

require_once __DIR__ . '/layout/header.php';
?>
<style>
html, body {
    height: 100%;
    margin: 0;
    padding: 0;
    overflow: hidden;
}
.sidebar {
    height: 100vh;
    overflow: auto;
    -webkit-overflow-scrolling: touch;
}
#gamingContentWrapper {
    height: 100vh;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
}
.game-card {
    transition: transform 0.2s, box-shadow 0.2s;
}
.game-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 32px rgba(0,0,0,0.18);
}
.game-card .play-btn {
    transition: background-color 0.2s;
}
.badge-live {
    animation: pulse-badge 2s infinite;
}
@keyframes pulse-badge {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.6; }
}
</style>

<div class="flex h-screen">
    <?php include __DIR__ . '/layout/sidebar.php'; ?>

    <!-- Main Content -->
    <div id="gamingContentWrapper" class="flex-1 lg:ml-64">
        <div class="p-4 sm:p-6 max-w-6xl mx-auto">

            <!-- Page Header -->
            <div class="mb-8">
                <div class="flex items-center gap-3 mb-1">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-purple-600 to-indigo-600 flex items-center justify-center shadow-md">
                        <i class="fas fa-gamepad text-white text-lg"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold themed-text">Ginto Gaming</h1>
                        <p class="text-sm themed-text-secondary">Sharpen your skills. Level up.</p>
                    </div>
                </div>
            </div>

            <!-- Games Grid -->
            <section>
                <h2 class="text-xs font-semibold themed-text-secondary uppercase tracking-widest mb-4">Available Games</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">

                    <!-- Keyboard Typing Game -->
                    <div class="game-card rounded-2xl overflow-hidden shadow-md bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 flex flex-col">
                        <!-- Card visual -->
                        <div class="h-36 bg-gradient-to-br from-gray-800 to-gray-900 flex items-center justify-center relative overflow-hidden">
                            <!-- mini keyboard visual -->
                            <div class="flex flex-col gap-1 opacity-70 scale-75">
                                <div class="flex gap-1">
                                    <?php foreach(['Q','W','E','R','T','Y','U','I','O','P'] as $k): ?>
                                    <div class="w-6 h-6 bg-gray-600 rounded text-gray-300 text-xs flex items-center justify-center font-mono"><?= $k ?></div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="flex gap-1 ml-2">
                                    <?php foreach(['A','S','D','F','G','H','J','K','L'] as $k): ?>
                                    <div class="w-6 h-6 bg-gray-600 rounded text-gray-300 text-xs flex items-center justify-center font-mono"><?= $k ?></div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="flex gap-1 ml-4">
                                    <?php foreach(['Z','X','C','V','B','N','M'] as $k): ?>
                                    <div class="w-6 h-6 bg-gray-600 rounded text-gray-300 text-xs flex items-center justify-center font-mono"><?= $k ?></div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <span class="badge-live absolute top-3 right-3 bg-green-500 text-white text-xs font-bold px-2 py-0.5 rounded-full">LIVE</span>
                        </div>
                        <!-- Card info -->
                        <div class="p-4 flex flex-col flex-1">
                            <div class="flex items-start justify-between mb-2">
                                <div>
                                    <h3 class="font-bold text-gray-900 dark:text-white text-base">Keyboard Typing</h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Speed &amp; accuracy trainer</p>
                                </div>
                                <div class="flex items-center gap-1 text-yellow-400 text-xs mt-0.5">
                                    <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star-half-alt"></i>
                                </div>
                            </div>
                            <ul class="text-xs text-gray-500 dark:text-gray-400 space-y-1 mb-4 flex-1">
                                <li><i class="fas fa-check text-green-500 mr-1"></i>7 progressive lessons</li>
                                <li><i class="fas fa-check text-green-500 mr-1"></i>Live WPM &amp; accuracy tracking</li>
                                <li><i class="fas fa-check text-green-500 mr-1"></i>Animated keyboard display</li>
                                <li><i class="fas fa-check text-green-500 mr-1"></i>Mechanical key click sounds</li>
                            </ul>
                            <button id="typing-play-btn"
                               class="play-btn w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-sm py-2 px-4 rounded-lg text-center flex items-center justify-center gap-2">
                                <i class="fas fa-play"></i> Play Now
                            </button>
                            <!-- No-keyboard warning (shown by JS when no physical keyboard detected) -->
                            <div id="typing-no-keyboard-msg" class="hidden mt-3 text-xs text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-700 rounded-lg px-3 py-2 flex items-start gap-2">
                                <i class="fas fa-keyboard mt-0.5 shrink-0"></i>
                                <span>You must connect a physical or Bluetooth keyboard to learn typing skills.</span>
                            </div>
                        </div>
                    </div>

                    <!-- Coming Soon — Memory Match -->
                    <div class="game-card rounded-2xl overflow-hidden shadow-md bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 flex flex-col opacity-75">
                        <div class="h-36 bg-gradient-to-br from-emerald-700 to-teal-900 flex items-center justify-center relative">
                            <i class="fas fa-brain text-5xl text-emerald-300 opacity-60"></i>
                            <span class="absolute top-3 right-3 bg-gray-600 text-gray-300 text-xs font-bold px-2 py-0.5 rounded-full">SOON</span>
                        </div>
                        <div class="p-4 flex flex-col flex-1">
                            <div class="mb-2">
                                <h3 class="font-bold text-gray-900 dark:text-white text-base">Memory Match</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Flip &amp; match cards</p>
                            </div>
                            <ul class="text-xs text-gray-500 dark:text-gray-400 space-y-1 mb-4 flex-1">
                                <li><i class="fas fa-lock text-gray-400 mr-1"></i>Multiple difficulty levels</li>
                                <li><i class="fas fa-lock text-gray-400 mr-1"></i>Leaderboard &amp; scoring</li>
                            </ul>
                            <button disabled class="w-full bg-gray-200 dark:bg-gray-700 text-gray-400 dark:text-gray-500 font-semibold text-sm py-2 px-4 rounded-lg cursor-not-allowed flex items-center justify-center gap-2">
                                <i class="fas fa-lock"></i> Coming Soon
                            </button>
                        </div>
                    </div>

                    <!-- Coming Soon — Word Scramble -->
                    <div class="game-card rounded-2xl overflow-hidden shadow-md bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 flex flex-col opacity-75">
                        <div class="h-36 bg-gradient-to-br from-orange-600 to-rose-800 flex items-center justify-center relative">
                            <i class="fas fa-font text-5xl text-orange-200 opacity-60"></i>
                            <span class="absolute top-3 right-3 bg-gray-600 text-gray-300 text-xs font-bold px-2 py-0.5 rounded-full">SOON</span>
                        </div>
                        <div class="p-4 flex flex-col flex-1">
                            <div class="mb-2">
                                <h3 class="font-bold text-gray-900 dark:text-white text-base">Word Scramble</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Unscramble tech words</p>
                            </div>
                            <ul class="text-xs text-gray-500 dark:text-gray-400 space-y-1 mb-4 flex-1">
                                <li><i class="fas fa-lock text-gray-400 mr-1"></i>Tech &amp; crypto vocabulary</li>
                                <li><i class="fas fa-lock text-gray-400 mr-1"></i>Timed challenges</li>
                            </ul>
                            <button disabled class="w-full bg-gray-200 dark:bg-gray-700 text-gray-400 dark:text-gray-500 font-semibold text-sm py-2 px-4 rounded-lg cursor-not-allowed flex items-center justify-center gap-2">
                                <i class="fas fa-lock"></i> Coming Soon
                            </button>
                        </div>
                    </div>

                    <!-- Coming Soon — Math Sprint -->
                    <div class="game-card rounded-2xl overflow-hidden shadow-md bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 flex flex-col opacity-75">
                        <div class="h-36 bg-gradient-to-br from-blue-600 to-violet-900 flex items-center justify-center relative">
                            <i class="fas fa-calculator text-5xl text-blue-200 opacity-60"></i>
                            <span class="absolute top-3 right-3 bg-gray-600 text-gray-300 text-xs font-bold px-2 py-0.5 rounded-full">SOON</span>
                        </div>
                        <div class="p-4 flex flex-col flex-1">
                            <div class="mb-2">
                                <h3 class="font-bold text-gray-900 dark:text-white text-base">Math Sprint</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Mental arithmetic race</p>
                            </div>
                            <ul class="text-xs text-gray-500 dark:text-gray-400 space-y-1 mb-4 flex-1">
                                <li><i class="fas fa-lock text-gray-400 mr-1"></i>60-second challenges</li>
                                <li><i class="fas fa-lock text-gray-400 mr-1"></i>Global leaderboard</li>
                            </ul>
                            <button disabled class="w-full bg-gray-200 dark:bg-gray-700 text-gray-400 dark:text-gray-500 font-semibold text-sm py-2 px-4 rounded-lg cursor-not-allowed flex items-center justify-center gap-2">
                                <i class="fas fa-lock"></i> Coming Soon
                            </button>
                        </div>
                    </div>

                    <!-- Coming Soon — Crypto Quiz -->
                    <div class="game-card rounded-2xl overflow-hidden shadow-md bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 flex flex-col opacity-75">
                        <div class="h-36 bg-gradient-to-br from-yellow-500 to-amber-700 flex items-center justify-center relative">
                            <i class="fab fa-bitcoin text-5xl text-yellow-100 opacity-60"></i>
                            <span class="absolute top-3 right-3 bg-gray-600 text-gray-300 text-xs font-bold px-2 py-0.5 rounded-full">SOON</span>
                        </div>
                        <div class="p-4 flex flex-col flex-1">
                            <div class="mb-2">
                                <h3 class="font-bold text-gray-900 dark:text-white text-base">Crypto Quiz</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Test your crypto knowledge</p>
                            </div>
                            <ul class="text-xs text-gray-500 dark:text-gray-400 space-y-1 mb-4 flex-1">
                                <li><i class="fas fa-lock text-gray-400 mr-1"></i>Earn points &amp; badges</li>
                                <li><i class="fas fa-lock text-gray-400 mr-1"></i>Daily question packs</li>
                            </ul>
                            <button disabled class="w-full bg-gray-200 dark:bg-gray-700 text-gray-400 dark:text-gray-500 font-semibold text-sm py-2 px-4 rounded-lg cursor-not-allowed flex items-center justify-center gap-2">
                                <i class="fas fa-lock"></i> Coming Soon
                            </button>
                        </div>
                    </div>

                    <!-- Suggestion Card -->
                    <div class="game-card rounded-2xl overflow-hidden shadow-md bg-gradient-to-br from-indigo-50 to-purple-50 dark:from-gray-800 dark:to-gray-900 border-2 border-dashed border-indigo-300 dark:border-indigo-700 flex flex-col">
                        <div class="h-36 flex items-center justify-center">
                            <i class="fas fa-lightbulb text-5xl text-indigo-300 dark:text-indigo-500"></i>
                        </div>
                        <div class="p-4 flex flex-col flex-1 items-center text-center">
                            <h3 class="font-bold text-gray-700 dark:text-gray-300 text-base mb-1">Suggest a Game</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-4 flex-1">Have a game idea? Let us know what you'd love to play!</p>
                            <a href="/social" class="w-full bg-indigo-100 dark:bg-indigo-900/40 hover:bg-indigo-200 dark:hover:bg-indigo-900/70 text-indigo-700 dark:text-indigo-300 font-semibold text-sm py-2 px-4 rounded-lg text-center flex items-center justify-center gap-2 transition-colors">
                                <i class="fas fa-comment-alt"></i> Post a Suggestion
                            </a>
                        </div>
                    </div>

                </div>
            </section>

            <!-- Leaderboard placeholder -->
            <section class="mt-10">
                <h2 class="text-xs font-semibold themed-text-secondary uppercase tracking-widest mb-4">Top Typists This Week</h2>
                <div class="rounded-2xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="flex items-center justify-center py-10 text-gray-400 dark:text-gray-600 gap-3">
                        <i class="fas fa-trophy text-3xl"></i>
                        <div>
                            <p class="text-sm font-medium">Leaderboard coming soon</p>
                            <p class="text-xs">Play Keyboard Typing to be the first on the board!</p>
                        </div>
                    </div>
                </div>
            </section>

        </div>
    </div>
</div>

<script>
(function () {
    /**
     * Heuristic: a device has a physical keyboard if it has a fine pointer (mouse)
     * OR if it is not a touch-only device.
     * A connected Bluetooth keyboard cannot be detected by the browser directly.
     * We fall back to asking the user to proceed anyway via the button itself.
     */
    function likelyHasKeyboard() {
        // Fine pointer almost always means a physical keyboard (desktop/laptop)
        if (window.matchMedia('(pointer: fine)').matches) return true;
        // Coarse-only pointer with no hover = phone/tablet without keyboard
        if (window.matchMedia('(pointer: coarse) and (hover: none)').matches) return false;
        // Anything else (hybrid devices, etc.) — optimistically allow
        return true;
    }

    var btn = document.getElementById('typing-play-btn');
    var noKbMsg = document.getElementById('typing-no-keyboard-msg');

    // Show the warning immediately on page-load for touch-only devices
    if (!likelyHasKeyboard()) {
        noKbMsg.classList.remove('hidden');
        // dim the button to signal unavailability
        btn.classList.remove('bg-indigo-600', 'hover:bg-indigo-700');
        btn.classList.add('bg-gray-400', 'dark:bg-gray-600', 'cursor-not-allowed');
        btn.setAttribute('aria-disabled', 'true');
    }

    btn.addEventListener('click', function () {
        if (!likelyHasKeyboard()) {
            // Re-show the message (in case it was hidden) and stop
            noKbMsg.classList.remove('hidden');
            return;
        }

        // Attempt to lock orientation to landscape before navigating
        var locked = false;
        if (screen.orientation && typeof screen.orientation.lock === 'function') {
            screen.orientation.lock('landscape').then(function () {
                locked = true;
            }).catch(function () {
                // Lock failed (desktop browsers reject this — that's fine)
            }).finally(function () {
                window.location.href = '/gaming?game=typing';
            });
        } else {
            window.location.href = '/gaming?game=typing';
        }
    });
})();
</script>
</body>
</html>
