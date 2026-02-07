<?php
$csrfToken = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken); ?>">
  <title>SmartFed Login</title>
  <link href="/assets/favicon.ico" rel="shortcut icon" type="image/x-icon" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet" />
  <script src="https://cdn.tailwindcss.com"></script>
  <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <link rel="stylesheet" href="<?= vendor_assets("iconcaptcha/assets/client/css/iconcaptcha.min.css"); ?>">
  <script src="<?= vendor_assets("iconcaptcha/assets/client/js/iconcaptcha.min.js"); ?>"></script>
  <script>

    function showCaptcha(){
        document.querySelector('.iconcaptcha-widget').style.display = 'block';
        // document.querySelectorAll('.iconcaptcha-widget').forEach(function(el) {
        //     el.style.display = 'block';
        // });
    }
  </script>
  <style>
    [x-cloak] { display: none !important; }
    body { font-family: 'Inter', sans-serif; }
    input::placeholder { color: rgba(255, 255, 255, 0.6); }
    .iconcaptcha-modal__footer>span,
    .iconcaptcha-modal__body-info {
        display: none !important;
    }
    .login-toggle, #login-btn{
        display: none;
    }
  </style>
</head>
<body class="bg-gradient-to-tr from-slate-950 via-indigo-900 to-fuchsia-900 min-h-screen flex items-center justify-center text-white">
  <div x-data="{ mode: 'login', loading: false }" class="w-full max-w-md px-6 sm:px-8 py-10 mx-auto">
    <div class="flex justify-center mb-6 bg-white/10 rounded-full p-1 border border-white/30">
      <button @click="mode = 'login'" :aria-pressed="mode === 'login'" onclick="showCaptcha();"; role="tab"
              :class="mode === 'login' ? 'bg-blue-600 text-white' : 'text-white/70'"
              class="w-1/2 px-4 py-2 rounded-full font-semibold transition-all duration-200">Login</button>
      <button @click="mode = 'register'" :aria-pressed="mode === 'register'" role="tab"
              :class="mode === 'register' ? 'bg-blue-600 text-white' : 'text-white/70'"
              class="w-1/2 px-4 py-2 rounded-full font-semibold transition-all duration-200">Register</button>
    </div>

    <h1 class="text-2xl font-bold mb-1 text-center">Welcome to <span class="text-blue-200">SmartFed</span></h1>
    <h2 class="text-lg font-medium mb-4 text-center">Brilliant men interact with <span class="text-blue-300">AI</span></h2>
    <h3 class="login-toggle text-base font-semibold mb-6 text-center" x-text="mode === 'login' ? 'Sign in to your account' : 'Create a new account'"></h3>

    <div class="relative">
      <!-- Login Form -->
      <form x-show="mode === 'login'" x-transition x-cloak method="post" action="/login" class="space-y-4" :class="mode !== 'login' ? 'hidden' : ''">
        <!-- CSRF Token Added -->
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
        
        <div class="flex flex-col items-center space-y-2">
          <?= \IconCaptcha\Token\IconCaptchaToken::render() ?>
          <div class="iconcaptcha-widget" data-theme="dark"></div>
        </div>
      
        <input type="text" name="email" placeholder="Username" class="login-toggle w-full bg-white/10 text-white text-sm py-3 px-4 rounded-lg border border-white/20 focus:outline-none focus:ring-2 focus:ring-blue-400" required />
        <input type="password" name="password" placeholder="Password" class="login-toggle w-full bg-white/10 text-white text-sm py-3 px-4 rounded-lg border border-white/20 focus:outline-none focus:ring-2 focus:ring-blue-400" required />
        <button type="submit" id="login-btn" class="login-toggle w-full bg-blue-600 hover:bg-blue-700 transition py-3 rounded-lg font-semibold shadow-lg flex justify-center items-center">
          <template x-if="loading">
            <svg class="animate-spin h-5 w-5 mr-2 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4l3-3-3-3v4a8 8 0 100 16v-4l-3 3 3 3v-4a8 8 0 01-8-8z"></path>
            </svg>
          </template>
          <span x-text="loading ? 'Signing in...' : 'Login'"></span>
        </button>
        <div class="flex justify-between gap-3 mt-4">
          <button class="w-1/2 flex items-center justify-center gap-2 py-3 px-4 bg-white text-black font-semibold rounded-lg shadow hover:shadow-md transition group">
            <img src="https://www.svgrepo.com/show/475656/google-color.svg" alt="Google" class="w-5 h-5 group-hover:scale-110 transition" />
            <span class="text-sm">Google</span>
          </button>
          <button class="w-1/2 flex items-center justify-center gap-2 py-3 px-4 bg-[#1877F2] text-white font-semibold rounded-lg shadow hover:bg-[#165cdb] transition group">
            <img src="https://www.svgrepo.com/show/475647/facebook-color.svg" alt="Facebook" class="w-5 h-5 group-hover:scale-110 transition" />
            <span class="text-sm">Facebook</span>
          </button>
        </div>
        <div class="mt-3 text-center">
          <a href="#" class="text-sm text-blue-200 hover:underline">Forgot Password?</a>
        </div>
        <div class="mt-6 flex flex-col items-center">
          <img src="/assets/favicon/apple-touch-icon.png" alt="Logo" class="w-20 h-20 object-contain" />
          <p class="mt-2 text-xs text-white/50 text-center px-2">
            © 2025 | All Rights Reserved - The Philippine AI.<br />
            Powered by <span class="font-semibold text-white">AI CONNECT SOLUTIONS INC., in cooperation with AI HQ Corp., Mindanao News Daily</span> & Bob Reyes Group of Companies.
          </p>
        </div>
      </form>

      <!-- Registration Form -->
      <form x-show="mode === 'register'" x-transition x-cloak method="post" action="/register" class="space-y-4" :class="mode !== 'register' ? 'hidden' : ''">
        <!-- CSRF Token Added -->
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

        <input type="text" name="name" placeholder="Full Name" class="w-full bg-white/10 text-white text-sm py-3 px-4 rounded-lg border border-white/20 focus:outline-none focus:ring-2 focus:ring-purple-400" required />
        <input type="email" name="email" placeholder="Email" class="w-full bg-white/10 text-white text-sm py-3 px-4 rounded-lg border border-white/20 focus:outline-none focus:ring-2 focus:ring-purple-400" required />
        <input type="password" name="password" placeholder="Password" class="w-full bg-white/10 text-white text-sm py-3 px-4 rounded-lg border border-white/20 focus:outline-none focus:ring-2 focus:ring-purple-400" required />
        <input type="password" name="confirm_password" placeholder="Confirm Password" class="w-full bg-white/10 text-white text-sm py-3 px-4 rounded-lg border border-white/20 focus:outline-none focus:ring-2 focus:ring-purple-400" required />
        <button type="submit" class="w-full bg-purple-500 hover:bg-purple-600 transition py-3 rounded-lg font-semibold shadow-lg">Register</button>
        <div class="mt-6 flex flex-col items-center">
          <img src="/assets/favicon/apple-touch-icon.png" alt="Logo" class="w-20 h-20 object-contain" />
          <p class="mt-2 text-xs text-white/50 text-center px-2">
            © 2023 | All Rights Reserved - The Philippine AI.<br />
            Powered by <span class="font-semibold text-white">AI CONNECT SOLUTIONS INC</span>, Bob Reyes.
          </p>
        </div>
      </form>
    </div>
  </div>
        <script type="text/javascript">

            // Note: jQuery can be used as well. Check the README.md for more information.

            document.addEventListener('DOMContentLoaded', function () {

                // Check the README.md for information about the options.
                IconCaptcha.init('.iconcaptcha-widget', {
                    general: {
                        endpoint: '/vendor/iconcaptcha/ic_req.view.php',
                        fontFamily: 'inherit',
                    },
                    security: {
                        interactionDelay: 1000,
                        hoverProtection: true,
                        displayInitialMessage: true,
                        initializationDelay: 500,
                        incorrectSelectionResetDelay: 3000,
                        loadingAnimationDuration: 1000,
                    },
                    locale: {
                        initialization: {
                            verify: "<div style='text-align: center;'>Verify you're human!</div>",
                            loading: 'Loading challenge...',
                        },
                        header: 'Select the image displayed the <u>least</u> amount of times',
                        correct: 'Verification complete.',
                        incorrect: {
                            title: 'Uh oh.',
                            subtitle: "You've selected the wrong image."
                        },
                        timeout: {
                            title: 'Please wait.',
                            subtitle: 'You made too many incorrect selections.'
                        }
                    }
                })
                .bind('init', function(e) { // You can bind to custom events, in case you want to execute custom code.
                    console.log('Event: Captcha initialized', e.detail.captchaId);
                })
                // .bind('selected', function(e) {
                //     console.log('Event: Icon selected', e.detail.captchaId);
                // }).bind('refreshed', function(e) {
                //     console.log('Event: Captcha refreshed', e.detail.captchaId);
                // }).bind('invalidated', function(e) {
                //     console.log('Event: Invalidated', e.detail.captchaId);
                // }).bind('reset', function(e) {
                //     console.log('Event: Reset', e.detail.captchaId);
                .bind('success', function(e) {
                     console.log('Event: Correct input', e.detail.captchaId);
                     document.querySelectorAll('.login-toggle').forEach(function(el) {
                        el.style.display = 'block';
                     });
                     document.querySelector('.iconcaptcha-widget').style.display = 'none';
                })
                //.bind('error', function(e) {
                //     console.log('Event: Wrong input', e.detail.captchaId);
                // });
            });
        </script>

</body>
</html>