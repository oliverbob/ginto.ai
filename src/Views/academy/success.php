<?php // academy/success.php — post-checkout landing. ?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Welcome to the Academy') ?></title>
    <script>(function(){const t=localStorage.getItem('theme');document.documentElement.classList.toggle('dark',t==='dark'||(t!=='light'&&true));})();</script>
    <script src="/assets/js/tailwindcss.js"></script>
    <script>tailwind.config={darkMode:'class',theme:{extend:{colors:{primary:'#6366f1'}}}};</script>
    <link rel="stylesheet" href="/assets/fontawesome/css/all.min.css">
    <style>.dark{color-scheme:dark}</style>
</head>
<body class="bg-white dark:bg-[#0b1020] text-gray-900 dark:text-gray-100 min-h-screen flex items-center justify-center px-4">
    <div class="max-w-md w-full text-center rounded-2xl border border-gray-200 dark:border-gray-800 p-8">
        <div class="w-16 h-16 mx-auto rounded-full bg-green-100 dark:bg-green-500/20 text-green-600 dark:text-green-400 flex items-center justify-center text-3xl mb-4"><i class="fas fa-circle-check"></i></div>
        <h1 class="text-2xl font-bold">Payment received 🎉</h1>
        <p class="mt-3 text-gray-600 dark:text-gray-300">Thank you for joining the Ginto Trading Academy. Your membership activates the moment we confirm the payment (usually seconds).</p>
        <div class="mt-6 flex flex-col gap-2">
            <a href="/academy/enter" class="px-5 py-2.5 rounded-lg font-semibold bg-primary text-white hover:bg-primary/90">Enter the Academy</a>
            <a href="/academy" class="text-sm text-gray-500 hover:text-primary">Back to the Academy</a>
        </div>
        <p class="mt-5 text-xs text-gray-400">If access doesn't appear right away, refresh in a minute — the confirmation webhook is finalizing.</p>
    </div>
</body>
</html>
