<?php
namespace Ginto\Helpers;

/**
 * Centralized admin error logging helper.
 * Writes errors into the activity_logs table and to PHP error_log.
 */
class AdminErrorLogger
{
    /**
     * Log an error message with optional structured meta information.
     * This writes to the activity_logs table when available and always to error_log.
     */
    public static function log(string $message, array $meta = []): void
    {
        $payload = json_encode(['message' => $message, 'meta' => $meta], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $payload = substr($payload, 0, 4096);

        try {
            $db = null;
            try { $db = \Ginto\Core\Database::getInstance(); } catch (\Throwable $_) { $db = null; }

            $userId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? null;
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;

            if ($db) {
                $db->insert('activity_logs', [
                    'user_id' => $userId,
                    'action' => 'internal_error',
                    'model_type' => $meta['model_type'] ?? ($meta['route'] ?? 'system'),
                    'model_id' => $meta['model_id'] ?? null,
                    'description' => $payload,
                    'ip_address' => $ip,
                    'user_agent' => $ua,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
        } catch (\Throwable $e) {
            // If DB logging fails, ensure the message is still in server logs.
            error_log('[AdminErrorLogger] failed to write activity_logs: ' . $e->getMessage());
        }

        // Always write to primary error log
        error_log('[AdminErrorLogger] ' . $payload);
    }
}
