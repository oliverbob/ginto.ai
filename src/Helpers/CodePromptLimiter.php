<?php
namespace Ginto\Helpers;

/**
 * CodePromptLimiter
 *
 * Tracks and enforces monthly prompt limits on the /code page.
 * Guests (no session user_id) are tracked by IP address.
 * Paid users bypass the limit entirely.
 *
 * FREE_LIMIT: 2 prompts per calendar month for non-upgraded users.
 */
class CodePromptLimiter
{
    const FREE_LIMIT = 2;

    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? \Ginto\Core\Database::getInstance();
    }

    /**
     * Returns the first day of the current calendar month as a DATE string.
     */
    private function currentMonth(): string
    {
        return date('Y-m-01');
    }

    /**
     * Get the client IP address (respects trusted reverse proxy headers).
     */
    private function clientIp(): string
    {
        // Prefer X-Forwarded-For when running behind a trusted proxy
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($parts[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Check how many prompts remain this month for the current user / visitor.
     *
     * @param int|null $userId  Logged-in user ID, or null for guests.
     * @return int  Remaining prompts (0 = limit reached).
     */
    public function remaining(?int $userId): int
    {
        $month = $this->currentMonth();
        $used  = $this->getUsed($userId);
        return max(0, self::FREE_LIMIT - $used);
    }

    /**
     * Returns how many prompts have been used this month.
     */
    public function getUsed(?int $userId): int
    {
        $month = $this->currentMonth();

        try {
            if ($userId !== null) {
                $row = $this->db->get('code_prompts', 'prompt_count', [
                    'user_id' => $userId,
                    'month'   => $month,
                ]);
            } else {
                $ip  = $this->clientIp();
                $row = $this->db->get('code_prompts', 'prompt_count', [
                    'visitor_ip' => $ip,
                    'month'      => $month,
                    'user_id'    => null,
                ]);
            }
            return (int)($row ?? 0);
        } catch (\Throwable $e) {
            error_log('[CodePromptLimiter] getUsed: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Attempt to consume one prompt.  Returns true if allowed, false if limit
     * has already been reached (prompt is NOT counted in that case).
     *
     * @param int|null $userId
     * @return bool
     */
    public function consume(?int $userId): bool
    {
        $month = $this->currentMonth();
        $used  = $this->getUsed($userId);

        if ($used >= self::FREE_LIMIT) {
            return false;
        }

        try {
            if ($userId !== null) {
                $existing = $this->db->get('code_prompts', 'id', [
                    'user_id' => $userId,
                    'month'   => $month,
                ]);
                if ($existing) {
                    $this->db->update('code_prompts', ['prompt_count[+]' => 1], [
                        'user_id' => $userId,
                        'month'   => $month,
                    ]);
                } else {
                    $this->db->insert('code_prompts', [
                        'user_id'      => $userId,
                        'visitor_ip'   => '',
                        'month'        => $month,
                        'prompt_count' => 1,
                    ]);
                }
            } else {
                $ip = $this->clientIp();
                $existing = $this->db->get('code_prompts', 'id', [
                    'visitor_ip' => $ip,
                    'month'      => $month,
                    'user_id'    => null,
                ]);
                if ($existing) {
                    $this->db->update('code_prompts', ['prompt_count[+]' => 1], [
                        'visitor_ip' => $ip,
                        'month'      => $month,
                        'user_id'    => null,
                    ]);
                } else {
                    $this->db->insert('code_prompts', [
                        'user_id'      => null,
                        'visitor_ip'   => $ip,
                        'month'        => $month,
                        'prompt_count' => 1,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            error_log('[CodePromptLimiter] consume: ' . $e->getMessage());
            // Allow on DB error to avoid blocking users unexpectedly
        }

        return true;
    }
}
