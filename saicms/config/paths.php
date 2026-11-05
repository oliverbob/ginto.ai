<?php

// Base project root (e.g., /opt/lampp/htdocs/snet/php-cms)
define('ROOT_PATH', realpath(__DIR__ . '/../'));

// Core structure
define('APP_PATH', ROOT_PATH . '/app');             // Controllers, Models, Views
define('CORE_PATH', ROOT_PATH . '/core');           // Router, Controller base, View handler
define('CONFIG_PATH', ROOT_PATH . '/config');       // Env, bootstrap, captcha config
define('CONFV_PATH', CONFIG_PATH . '/vendor');       // Config Vendor Path
define('PUBLIC_PATH', ROOT_PATH . '/public');       // Web root for browser access
define('ROUTES_PATH', ROOT_PATH . '/routes');       // Route definitions
define('VENDOR_PATH', ROOT_PATH . '/vendor');       // Composer packages
define('IC_PATH', ROOT_PATH . '/vendor/iconcaptcha'); //IconCaptcha Public View
define('MEDOO_PATH', ROOT_PATH . '/config/vendor/medoo');

// Storage and runtime
define('STORAGE_PATH', ROOT_PATH . '/storage');     // Uploads, logs, cache
define('CACHE_PATH', STORAGE_PATH . '/cache');      // Cache data
define('LOGS_PATH', STORAGE_PATH . '/logs');        // Logs
define('SESSIONS_PATH', STORAGE_PATH . '/sessions');// Optional: if you store sessions here

// Public asset groups
define('ASSETS_PATH', PUBLIC_PATH . '/assets');             // Your own styles, JS, media
define('PUBLICV_PATH', PUBLIC_PATH . '/vendor');      // Third-party packages/assets
define('UPLOADS_PATH', PUBLIC_PATH . '/uploads');           // User-submitted files

// Views
define('VIEWS_PATH', APP_PATH . '/views');          // Blade-like templates or raw PHP views
define('LAYOUTS_PATH', VIEWS_PATH . '/layouts');    // Shared headers, footers, etc.
define('AUTH_PATH', VIEWS_PATH . '/auth');          // For Auth and captcha

########

// Relative web-accessible paths
define('REL_ASSETS_PATH', '/assets');                   // Your own styles, JS, media
define('REL_VENDOR_ASSETS_PATH', '/vendor');            // Third-party assets
define('REL_UPLOADS_PATH', '/uploads');                 // User files
define('REL_ICONCAPTCHA_PATH', '/vendor/iconcaptcha');  // Captcha asset folder
define('REL_FAVICON_PATH', '/assets/images/favicon.ico');

// Route-based paths (can be used for redirects or URL helpers)
define('REL_LOGIN_ROUTE', '/login');
define('REL_REGISTER_ROUTE', '/register');
define('REL_DASHBOARD_ROUTE', '/dashboard');

define('REL_ROOT', '/');
