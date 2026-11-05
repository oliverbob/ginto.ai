<?php
namespace Ginto\Support;

/**
 * Tiny universal accessor for .env / environment variables.
 * Mirrors how the rest of the codebase reads config: getenv()/$_ENV first
 * (populated by Dotenv at bootstrap), falling back to parsing the .env file
 * directly (same approach as Ginto\Core\Database::loadEnvConfig()).
 */
class Env
{
    private static ?array $fileCache = null;

    public static function get(string $key, ?string $default = null): ?string
    {
        // 1) Runtime environment (Dotenv safeLoad / real env vars)
        $val = getenv($key);
        if ($val !== false && $val !== '') {
            return $val;
        }
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return (string)$_ENV[$key];
        }

        // 2) Fallback: parse the .env file once and cache it
        if (self::$fileCache === null) {
            self::$fileCache = self::loadFile();
        }
        if (array_key_exists($key, self::$fileCache) && self::$fileCache[$key] !== '') {
            return self::$fileCache[$key];
        }

        return $default;
    }

    /** Convenience boolean reader: true/1/yes/on => true. */
    public static function bool(string $key, bool $default = false): bool
    {
        $v = self::get($key);
        if ($v === null) {
            return $default;
        }
        return in_array(strtolower(trim($v)), ['1', 'true', 'yes', 'on'], true);
    }

    private static function loadFile(): array
    {
        $envFile = dirname(__DIR__, 2) . '/.env';
        $config = [];
        if (!is_file($envFile) || !is_readable($envFile)) {
            return $config;
        }
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return $config;
        }
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            $config[trim($k)] = trim($v);
        }
        return $config;
    }
}
