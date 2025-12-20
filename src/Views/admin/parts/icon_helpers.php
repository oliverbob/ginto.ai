<?php
// Small helper for consistent icon colors across views
// Centralized configuration: change these to tweak icon defaults or the active color.
$currentPath = '/';
if (!empty($_SERVER['REQUEST_URI'])) {
    $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
}

// Active color used when a route is currently active. Change this to switch the highlight globally.
$ICON_ACTIVE_CLASS = 'text-yellow-500 dark:text-yellow-300';

// Ensure SVGs pick up the element color — append this to any returned icon class string so
// SVGs that use stroke="currentColor" / fill="currentColor" render correctly.
$ICON_SVG_BASE_CLASS = 'stroke-current fill-current';

// Default icon color mapping per route key. Use a colorful palette so icons are not muted by default.
// The keys are route prefixes — use the most specific match possible when looking up defaults.
$ICON_DEFAULTS = [
    // top-level admin/dashboard
    '/admin' => 'text-sky-500 dark:text-sky-400',
    '/dashboard' => 'text-yellow-600 dark:text-yellow-300',

    // Content
    '/admin/pages' => 'text-sky-500 dark:text-sky-400',
    '/admin/posts' => 'text-blue-500 dark:text-blue-400',
    '/admin/categories' => 'text-indigo-500 dark:text-indigo-400',
    '/admin/tags' => 'text-pink-500 dark:text-pink-400',

    // Media / Files
    '/admin/media' => 'text-violet-500 dark:text-violet-400',

    // Users and related — recolor to red for high-visibility per request
    '/admin/users' => 'text-red-500 dark:text-red-400',

    // Menus / Appearance / Plugins / Settings
    '/admin/menus' => 'text-emerald-500 dark:text-emerald-400',
    '/admin/themes' => 'text-cyan-500 dark:text-cyan-400',
    '/admin/plugins' => 'text-fuchsia-500 dark:text-fuchsia-400',
    '/admin/settings' => 'text-teal-500 dark:text-teal-400',
    
    // Payments / Finance
    '/admin/payments' => 'text-amber-500 dark:text-amber-400',
    
    // Database Migrations
    '/admin/migrate' => 'text-green-500 dark:text-green-400',

    // Notifications / Finance / Commissions / Network
    '/admin/notifications' => 'text-rose-500 dark:text-rose-400',
    '/admin/commissions' => 'text-lime-500 dark:text-lime-400',
    '/admin/finance' => 'text-orange-500 dark:text-orange-400',
    '/admin/network-tree' => 'text-sky-600 dark:text-sky-400',

    // Site-level / user actions
    '/logout' => 'text-red-500 dark:text-red-400',

    // User-facing routes
    '/downline' => 'text-emerald-600 dark:text-emerald-500',
    '/user/network-tree' => 'text-indigo-600 dark:text-indigo-400',
    '/user/commissions' => 'text-amber-400 dark:text-amber-300',
    '/user/settings' => 'text-teal-500 dark:text-teal-400',
];

// Map the same route keys to solid color hex values used as inline fallbacks.
// These ensure icons get a visible color even when Tailwind purging removes class-based colors.
$ICON_DEFAULT_HEX = [
    '/admin' => '#0ea5e9',        // sky-500
    '/dashboard' => '#d97706',    // yellow-600

    '/admin/pages' => '#0ea5e9',
    '/admin/posts' => '#3b82f6',
    '/admin/categories' => '#6366f1',
    '/admin/tags' => '#ec4899',

    '/admin/media' => '#8b5cf6',

    '/admin/users' => '#ef4444',

    '/admin/menus' => '#10b981',
    '/admin/themes' => '#06b6d4',
    '/admin/plugins' => '#d946ef',
    '/admin/settings' => '#14b8a6',
    
    '/admin/payments' => '#f59e0b',    // amber-500
    
    '/admin/migrate' => '#22c55e',     // green-500

    '/admin/notifications' => '#fb7185',
    '/admin/commissions' => '#84cc16',
    '/admin/finance' => '#fb923c',
    '/admin/network-tree' => '#0284c7',

    '/logout' => '#ef4444',

    '/downline' => '#059669',
    '/user/network-tree' => '#4f46e5',
    '/user/commissions' => '#f59e0b',
    '/user/settings' => '#14b8a6',
];

// Active color hex fallback too
$ICON_ACTIVE_HEX = '#f59e0b';

/**
 * Return icon classes for a given path. Behavior:
 * - If the current request path starts with $path, return the active class.
 * - Otherwise, if $fallback is provided, return it.
 * - Otherwise, try to find a default class from $ICON_DEFAULTS for $path.
 * - Fallback to muted gray if nothing is found.
 *
 * @param string $path Route prefix to consider active
 * @param string|null $fallback Explicit fallback classes when not active (optional)
 * @param string|null $active Optional explicit active classes (optional)
 * @return string CSS class string for the icon
 */
function activeIconClass(string $path, ?string $fallback = null, ?string $active = null) : string
{
    global $currentPath, $ICON_ACTIVE_CLASS, $ICON_DEFAULTS;

    $active = $active ?? $ICON_ACTIVE_CLASS;

    if (str_starts_with($currentPath, $path)) {
        return $active;
    }

    if ($fallback !== null) return $fallback;

    // Try to find a mapping match — prefer the most specific key (longest match)
    $best = '';
    foreach ($ICON_DEFAULTS as $k => $v) {
        if (str_starts_with($path, $k) && strlen($k) > strlen($best)) {
            $best = $k;
        }
    }

    if ($best !== '' && isset($ICON_DEFAULTS[$best])) {
        return $ICON_DEFAULTS[$best];
    }

    // final default: muted gray (return to the original subdued appearance for other icons)
    return 'text-gray-500 dark:text-gray-400';
}


/**
 * Return an inline style string for the element color (used by SVGs that rely on currentColor).
 * Produces e.g. style="color: #3b82f6;" so SVGs that use stroke="currentColor" will render correctly
 * even if the corresponding Tailwind class is missing from the compiled CSS.
 *
 * @param string $path Route prefix to consider
 * @param string|null $fallbackOptional optional fallback color hex (e.g. "#999")
 * @return string e.g. 'style="color: #abc123;"' or an empty string
 */
function iconColorAttr(string $path, ?string $fallbackHex = null) : string
{
    global $currentPath, $ICON_DEFAULTS, $ICON_DEFAULT_HEX, $ICON_ACTIVE_HEX;

    // Active -> use the active hex color
    if (str_starts_with($currentPath, $path)) {
        return 'style="color: ' . $ICON_ACTIVE_HEX . ';"';
    }

    // Find the most specific mapping key
    $best = '';
    foreach ($ICON_DEFAULTS as $k => $v) {
        if (str_starts_with($path, $k) && strlen($k) > strlen($best)) {
            $best = $k;
        }
    }

    if ($best !== '' && isset($ICON_DEFAULT_HEX[$best])) {
        return 'style="color: ' . $ICON_DEFAULT_HEX[$best] . ';"';
    }

    if ($fallbackHex !== null) return 'style="color: ' . $fallbackHex . ';"';

    // final default muted gray
    return 'style="color: #6b7280;"';
}

/**
 * Utility: return a safe icon class for admin sidebar icons.
 * Always prefers: active class (if active) -> mapped default class -> muted gray.
 * This is a small wrapper that guarantees the SVG base classes are appended.
 */
function iconClassForAdmin(string $path, ?string $fallback = null) : string
{
    global $currentPath, $ICON_ACTIVE_CLASS, $ICON_DEFAULTS, $ICON_SVG_BASE_CLASS;

    // Active -> explicit active classes
    if (str_starts_with($currentPath, $path)) {
        return $ICON_ACTIVE_CLASS . ' ' . $ICON_SVG_BASE_CLASS;
    }

    // explicit fallback passed by caller
    if ($fallback !== null) return $fallback . ' ' . $ICON_SVG_BASE_CLASS;

    // find most specific mapping for the provided path
    $best = '';
    foreach ($ICON_DEFAULTS as $k => $v) {
        if (str_starts_with($path, $k) && strlen($k) > strlen($best)) {
            $best = $k;
        }
    }

    if ($best !== '' && isset($ICON_DEFAULTS[$best])) {
        return $ICON_DEFAULTS[$best] . ' ' . $ICON_SVG_BASE_CLASS;
    }

    return 'text-gray-500 dark:text-gray-400 ' . $ICON_SVG_BASE_CLASS;
}

/**
 * Utility: return a safe inline style color for admin icons. Works like iconColorAttr
 * but kept as a distinct wrapper name so templates can call it without confusion.
 */
function iconStyleForAdmin(string $path, ?string $fallbackHex = null) : string
{
    return iconColorAttr($path, $fallbackHex);
}

// shorthand for legacy templates expecting snake_case
function active_icon_class(string $path, ?string $fallback = null, ?string $active = null) : string
{
    return activeIconClass($path, $fallback, $active);
}

?>
