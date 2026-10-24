<?php

declare(strict_types=1);

namespace Ginto\Support;

/**
 * Changes single lines of .env in place, and nothing else.
 *
 * LiveController::updateEnvFile rewrites the whole file into its own groups,
 * which is right for the /live setup form and wrong for one API key: here a
 * line is replaced where it stands (or appended), every other byte is kept,
 * the old file is copied to storage/backups first, and the new one replaces it
 * by rename so a reader never sees half a file.
 */
final class EnvFile
{
    /** @param array<string,string> $pairs KEY => value ('' clears the value but keeps the line) */
    public static function set(array $pairs): void
    {
        $path = ROOT_PATH . '/.env';
        $old  = is_file($path) ? (string) file_get_contents($path) : '';
        $text = $old;

        foreach ($pairs as $key => $value) {
            if (preg_match('/^[A-Z][A-Z0-9_]*$/', $key) !== 1) {
                throw new \InvalidArgumentException('Bad env key: ' . $key);
            }
            if (preg_match('/[\r\n]/', $value) === 1) {
                throw new \InvalidArgumentException('An env value cannot hold a line break.');
            }
            $line = $key . '=' . (preg_match('/[\s#"\'$]/', $value) === 1 ? '"' . addcslashes($value, '"\\$') . '"' : $value);
            $re   = '/^' . preg_quote($key, '/') . '=.*$/m';
            $text = preg_match($re, $text) === 1
                ? preg_replace_callback($re, static fn (): string => $line, $text, 1)
                : rtrim($text, "\n") . ($text === '' ? '' : "\n") . $line . "\n";
        }

        if ($text === $old) {
            return;
        }

        if ($old !== '') {
            $dir = STORAGE_PATH . '/backups';
            if (!is_dir($dir)) {
                @mkdir($dir, 0700, true);
            }
            if (@file_put_contents($dir . '/.env.' . date('Ymd_His'), $old) !== false) {
                @chmod($dir . '/.env.' . date('Ymd_His'), 0600);
            }
        }

        $tmp = $path . '.tmp.' . getmypid();
        if (file_put_contents($tmp, $text) === false) {
            throw new \RuntimeException('Could not write .env');
        }
        @chmod($tmp, is_file($path) ? (fileperms($path) & 0777) : 0600);
        if (!rename($tmp, $path)) {
            @unlink($tmp);
            throw new \RuntimeException('Could not replace .env');
        }

        foreach ($pairs as $key => $value) {
            $_ENV[$key] = $value;
            putenv($key . '=' . $value);
        }
    }
}
