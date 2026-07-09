# Routes & Views Documentation

This document describes how to create routes and views in Ginto CMS.

## Route Structure

All routes are defined in `src/Routes/web.php` using the `req()` helper function which registers both GET and POST handlers.

### Basic Route Pattern

```php
req($router, '/your-route', function() use ($db) {
    // Check if user is logged in
    $isLoggedIn = !empty($_SESSION['user_id']);
    
    // Check if user is admin
    $isAdmin = \Ginto\Controllers\UserController::isAdmin();
    
    // Get user info from session
    $username = $_SESSION['username'] ?? null;
    $userId = $_SESSION['user_id'] ?? null;
    $userFullname = $_SESSION['fullname'] ?? $_SESSION['username'] ?? null;
    
    \Ginto\Core\View::view('your-view/index', [
        'title' => 'Page Title',
        'isLoggedIn' => $isLoggedIn,
        'isAdmin' => $isAdmin,
        'username' => $username,
        'userId' => $userId,
        'userFullname' => $userFullname,
    ]);
});
```

---

## View Structure

Every view **must** be organized with `parts/` and `pages/` subdirectories.

### Required Directory Structure

```
src/Views/
└── {route-name}/
    ├── {route-name}.php    # Main view file (named after the route)
    ├── parts/              # Reusable layout components
    │   ├── head.php        # <head> content (meta, scripts, styles)
    │   ├── header.php      # Navigation/header bar
    │   ├── sidebar.php     # Sidebar navigation (if needed)
    │   ├── content.php     # Content wrapper (includes pages)
    │   ├── footer.php      # Footer section
    │   └── body.php        # Body wrapper (optional)
    └── pages/              # Page-specific content
        ├── home.php        # Default/index content
        ├── list.php        # List view content
        ├── detail.php      # Detail view content
        └── ...             # Other page contents
```

**Example:** For route `/courses`:
```
src/Views/
└── courses/
    ├── courses.php         # Main view (View::view('courses/courses', ...))
    ├── parts/
    │   ├── head.php
    │   ├── header.php
    │   ├── content.php     # Includes pages/home.php by default
    │   └── footer.php
    └── pages/
        ├── home.php        # Course listing grid
        ├── detail.php      # Single course detail (future)
        └── lesson.php      # Lesson content (future)
```

---

## Part File Descriptions

### `parts/head.php`

Contains the `<head>` section content:
- Theme detection script (runs FIRST to prevent flash)
- Tailwind CSS via local `/assets/js/tailwindcss.js`
- Meta tags (charset, viewport, SEO)
- Page title
- Alpine.js include
- FontAwesome icons
- Custom styles for sidebar and dark mode

**Expected Variables:**
```php
$title    // Page title
```

**Example:**
```php
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Ginto') ?></title>
    
    <!-- Theme Detection Script (runs FIRST before any CSS to prevent flash) -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme');
            const systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            let shouldBeDark = savedTheme === 'dark' || (savedTheme !== 'light' && (systemDark || true));
            if (shouldBeDark) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        })();
    </script>
    
    <!-- Tailwind CSS (local, same as /chat) -->
    <script src="/assets/js/tailwindcss.js"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: '#6366f1',
                        secondary: '#8b5cf6',
                        dark: {
                            bg: '#1a1a2e',
                            surface: '#16213e',
                            card: '#1f2937',
                            border: '#374151'
                        }
                    }
                }
            }
        };
    </script>
    
    <!-- Local FontAwesome -->
    <link rel="stylesheet" href="/assets/fontawesome/css/all.min.css">
    
    <!-- Alpine.js for interactive components -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <style>
        /* Sidebar widths - match /chat */
        .sidebar-expanded { width: 256px; }
        .sidebar-collapsed { width: 44px; }
        
        /* Sidebar collapsed state - hide text, center icons */
        .sidebar-collapsed .sidebar-text { display: none !important; }
        .sidebar-collapsed .nav-item { justify-content: center; padding-left: 0; padding-right: 0; }
        .sidebar-collapsed .section-header { display: none !important; }
        
        /* Main content area - responsive to sidebar */
        @media (min-width: 1024px) {
            .main-content { margin-left: 256px; transition: margin-left 0.3s ease; }
            .main-content.collapsed { margin-left: 44px; }
        }
        @media (max-width: 1023px) {
            .main-content { margin-left: 0; }
        }
        
        /* Dark mode support */
        .dark { color-scheme: dark; }
    </style>
</head>
```

---

### `parts/header.php`

Contains the navigation bar:
- Back/home link
- Page title
- User menu (login/register for guests, dropdown for logged-in users)

**Expected Variables:**
```php
$isLoggedIn   // Boolean - user login status
$isAdmin      // Boolean - admin status
$username     // String - current user's username
$userFullname // String - user's full name
```

**Example:**
```php
<nav class="bg-gray-800 border-b border-gray-700">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <div class="flex items-center gap-6">
                <a href="/chat" class="flex items-center gap-2 text-gray-300 hover:text-white">
                    <!-- Back icon -->
                    <span>Ginto Chat</span>
                </a>
                <h1 class="text-xl font-semibold text-white">Page Title</h1>
            </div>
            <div class="flex items-center gap-4">
                <?php if ($isLoggedIn): ?>
                    <!-- User dropdown menu -->
                <?php else: ?>
                    <a href="/login">Login</a>
                    <a href="/register">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
```

---

### `parts/sidebar.php`

Optional sidebar for secondary navigation:
- Category filters
- Quick links
- Secondary actions

**Expected Variables:** Same as header.php

---

### `parts/content.php`

The content wrapper that includes page-specific content from `pages/` directory:
- Acts as a container/wrapper for page content
- Includes the appropriate page file based on current view

**Example:**
```php
<?php
// parts/content.php - Content wrapper
// Include the appropriate page content
$page = $page ?? 'home';
$pagePath = __DIR__ . '/../pages/' . $page . '.php';

if (file_exists($pagePath)) {
    include $pagePath;
} else {
    include __DIR__ . '/../pages/home.php';
}
?>
```

---

### `pages/` Directory

Contains the actual page content. Each file represents a different view/state:

| File | Purpose |
|------|---------|
| `home.php` | Default landing content (grids, hero sections) |
| `list.php` | List/table view of items |
| `detail.php` | Single item detail view |
| `create.php` | Create/add new item form |
| `edit.php` | Edit existing item form |

**Example:** `pages/home.php`
```php
<!-- Hero Section -->
<div class="bg-gradient-to-r from-primary-900 to-indigo-900 py-16">
    <div class="max-w-7xl mx-auto px-4 text-center">
        <h2 class="text-4xl font-bold text-white mb-4">Page Title</h2>
        <p class="text-xl text-gray-300">Description text here.</p>
    </div>
</div>

<!-- Main Content -->
<div class="max-w-7xl mx-auto px-4 py-12">
    <!-- Cards, grids, forms, etc. -->
</div>
```

---

### `parts/footer.php`

Footer section:
- Copyright notice
- Links
- Additional info

**Example:**
```php
<footer class="bg-gray-800 border-t border-gray-700 mt-12 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-gray-400 text-sm">
        <p>&copy; <?= date('Y') ?> Ginto AI. All rights reserved.</p>
    </div>
</footer>
```

---

### `parts/body.php` (Optional)

A wrapper that combines multiple parts into a layout. Useful when you have complex layouts with sidebars.

---

## Main View File: {route-name}.php

The main view file (named after the route) assembles all parts together:

```php
<?php
// {route-name}/{route-name}.php
$isLoggedIn = $isLoggedIn ?? false;
$isAdmin = $isAdmin ?? false;
$username = $username ?? null;
$userId = $userId ?? null;
$userFullname = $userFullname ?? null;
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<?php include __DIR__ . '/parts/head.php'; ?>
<body class="bg-gray-900 text-gray-100 min-h-screen">
    <?php include __DIR__ . '/parts/header.php'; ?>
    <?php include __DIR__ . '/parts/content.php'; ?>
    <?php include __DIR__ . '/parts/footer.php'; ?>
</body>
</html>
```

---

## Step-by-Step: Creating a New Route + View

### Step 1: Create the View Directory Structure

```bash
mkdir -p src/Views/{route-name}/parts
```

### Step 2: Create the Part Files

Create each file in `src/Views/{route-name}/parts/`:

| File | Purpose |
|------|---------|
| `head.php` | Meta tags, scripts, styles |
| `header.php` | Navigation bar with user menu |
| `content.php` | Main page content |
| `footer.php` | Footer section |
| `sidebar.php` | Sidebar (if needed) |

### Step 3: Create the Main View File

Create `src/Views/{route-name}/{route-name}.php` that includes all parts (see example above).

### Step 4: Add the Route

Add to `src/Routes/web.php`:

```php
// ============================================================================
// {Route Name} - {Description}
// ============================================================================
req($router, '/{route-name}', function() use ($db) {
    $isLoggedIn = !empty($_SESSION['user_id']);
    $isAdmin = \Ginto\Controllers\UserController::isAdmin();
    $username = $_SESSION['username'] ?? null;
    $userId = $_SESSION['user_id'] ?? null;
    $userFullname = $_SESSION['fullname'] ?? $_SESSION['username'] ?? null;
    
    \Ginto\Core\View::view('{route-name}/{route-name}', [
        'title' => 'Page Title',
        'isLoggedIn' => $isLoggedIn,
        'isAdmin' => $isAdmin,
        'username' => $username,
        'userId' => $userId,
        'userFullname' => $userFullname,
        // Add page-specific data here
    ]);
});
```

### Step 5: Test

Visit `http://localhost:8000/{route-path}` to test.

---

## Example: /courses

### Directory Structure

```
src/Views/courses/
├── courses.php
└── parts/
    ├── head.php
    ├── header.php
    ├── content.php
    └── footer.php
```

### Route Definition

```php
req($router, '/courses', function() use ($db) {
    $isLoggedIn = !empty($_SESSION['user_id']);
    $isAdmin = \Ginto\Controllers\UserController::isAdmin();
    $username = $_SESSION['username'] ?? null;
    $userId = $_SESSION['user_id'] ?? null;
    $userFullname = $_SESSION['fullname'] ?? $_SESSION['username'] ?? null;
    
    \Ginto\Core\View::view('courses/courses', [
        'title' => 'Courses',
        'isLoggedIn' => $isLoggedIn,
        'isAdmin' => $isAdmin,
        'username' => $username,
        'userId' => $userId,
        'userFullname' => $userFullname,
    ]);
});
```

---

## Existing Routes Reference

| Route | View Directory | Description |
|-------|---------------|-------------|
| `/` | - | Home/redirect |
| `/login` | `user/login` | Login page |
| `/register` | `user/register` | Registration |
| `/dashboard` | `user/dashboard` | User dashboard |
| `/chat` | `chat/chat` | AI Chat interface |
| `/courses` | `courses/index` | Courses listing |
| `/editor` | `editor/index` | Code editor |
| `/playground` | `playground/index` | AI Playground |
| `/gtb` | `gtb/gtb` | Ginto Trading Bot dashboard |
| `/gtb-settings` | `gtb/gtb` (page `settings`) | Binance API config form (writes `BINANCE_*` to `.env`) |

---

## Example: /gtb (Ginto Trading Bot)

A full MVC route: controller + models + view, backed by MySQL. Renders a Binance
Spot trading dashboard. Demonstrates the controller form of a route.

### Files

```
src/Controllers/GtbController.php   # index() gathers data, renders the view
src/Models/GtbSettings.php          # API credentials (.env default, DB override; secret encrypted)
src/Models/GtbTrade.php             # recorded trades + realized P&L
src/Models/GtbLog.php               # activity / trading log
src/Support/Env.php                 # universal .env accessor (getenv → $_ENV → .env file)
src/Support/Crypto.php              # AES-256-CBC encrypt/decrypt for the API secret
database/gtb_schema.sql             # gtb_settings, gtb_trades, gtb_logs
src/Views/gtb/
├── gtb.php                         # Main view (View::view('gtb/gtb', ...))
├── parts/{head,header,content,footer}.php
└── pages/home.php                  # Dashboard content (controller-fed, empty states)
```

### Route Definition

Registered in `src/Routes/web.php` using the controller form:

```php
$router->req('/gtb', 'GtbController@index');
```

### Configuration (.env)

Binance settings are read like every other config value (via `.env`):

```
BINANCE_API_KEY=
BINANCE_API_SECRET=
BINANCE_TESTNET=false
BINANCE_BASE_URL=https://api.binance.com
GTB_ENCRYPTION_KEY=<generated 32-byte hex>   # encrypts the secret when saved to MySQL
```

### Setup & Test

```bash
mysql -u ginto -p ginto < database/gtb_schema.sql   # create the 3 tables
```

Then visit `http://localhost:8000/gtb`. The page renders even before the schema
is loaded (models degrade to empty states).

> V1 status: foundation only — dashboard shell, DB schema, encrypted-credential
> plumbing. Live balances/holdings and trading (manual buy/sell) come in later
> build steps.

---

## Best Practices

1. **Always use parts/** - Every view must have a `parts/` subdirectory
2. **Pass session data** - Always include `isLoggedIn`, `isAdmin`, `username`, `userFullname`
3. **Consistent styling** - Follow Tailwind dark theme patterns (`bg-gray-900`, `text-gray-100`)
4. **Include Alpine.js** - For interactive components (dropdowns, modals)
5. **Validate CSRF** - For POST requests, validate CSRF tokens
6. **Handle errors** - Wrap database calls in try/catch
7. **Keep parts reusable** - Parts should work across different pages with minimal changes
