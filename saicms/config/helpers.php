<?php

if (!function_exists('config')) {
    function config(string $path): string {
        return realpath(__DIR__ . '/' . ltrim($path, '/'));
    }
}

if (!function_exists('assets')) {
    function assets(string $path): string {
        return "/assets/" . ltrim($path, '/');
    }
}

if (!function_exists('vendor_assets')) {
    function vendor_assets(string $path): string {
        return "/vendor/" . ltrim($path, '/');
    }
}

if (!function_exists('route_to')) {
    function route_to(string $uri): string {
        return "/" . ltrim($uri, '/');
    }
}

if (!function_exists('is_post')) {
    function is_post(): bool {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url): void {
        header("Location: " . $url);
        exit;
    }
}

if (!function_exists('old')) {
    function old(string $key, $default = ''): string {
        $value = $_SESSION["old_$key"] ?? $default;
        unset($_SESSION["old_$key"]);
        return htmlspecialchars($value);
    }
}

if (!function_exists('error')) {
    function error(string $key): ?string {
        $value = $_SESSION["error_$key"] ?? null;
        unset($_SESSION["error_$key"]);
        return $value;
    }
}

if (!function_exists('list_dir_contents')) {
    /**
     * Echoes the contents of a given directory, or an error if it doesn't exist.
     *
     * @param string $directory
     * @return void
     */
    function list_dir_contents(string $directory): void
    {
        $realPath = realpath($directory);

        if (!$realPath || !is_dir($realPath)) {
            echo "❌ Directory not found: $directory\n";
            return;
        }

        echo "✅ Path exists: $realPath\n";
        echo "📁 Contents:\n";

        $items = array_diff(scandir($realPath), ['.', '..']);

        foreach ($items as $item) {
            echo " - $item\n";
        }
    }
}

// controlling the active/incactive icons in the navbar
function isActiveNav(string $path, bool $exactMatch = true): bool
{
    $currentUriPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $currentUriPath = rtrim($currentUriPath ?: '/', '/');
    if (empty($currentUriPath)) $currentUriPath = '/';

    $pathToCompare = rtrim($path, '/');
    if (empty($pathToCompare)) $pathToCompare = '/';

    if ($exactMatch) {
        return $currentUriPath === $pathToCompare;
    } else {
        if (strpos($currentUriPath, $pathToCompare) === 0) {
            $pathLength = strlen($pathToCompare);
            if (strlen($currentUriPath) == $pathLength) return true;
            if (isset($currentUriPath[$pathLength]) && $currentUriPath[$pathLength] === '/') return true;
            if ($pathToCompare === '/' && $pathLength === 1) return true;
        }
        return false;
    }
}