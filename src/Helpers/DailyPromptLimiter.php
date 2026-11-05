<?php
namespace Ginto\Helpers;

/**
 * DailyPromptLimiter
 *
 * Enforces a daily prompt limit for logged-in non-paid users across
 * /chat, /chat-m, and the home-page AI.
 *
 * - Paid users & admins: unlimited (bypass entirely).
 * - Non-paid logged-in users: DAILY_LIMIT prompts per calendar day.
 * - Guests (no user_id): handled separately by visitor session limits.
 */
class DailyPromptLimiter
{
    const DAILY_LIMIT = 10;

    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? \Ginto\Core\Database::getInstance();
    }

    private function today(): string
    {
        return date('Y-m-d');
    }

    /**
     * How many prompts has this user used today?
     */
    public function getUsed(int $userId): int
    {
        try {
            $row = $this->db->get('chat_prompts', 'prompt_count', [
                'user_id' => $userId,
                'day'     => $this->today(),
            ]);
            return (int)($row ?? 0);
        } catch (\Throwable $e) {
            error_log('[DailyPromptLimiter] getUsed: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * How many prompts remain today?
     */
    public function remaining(int $userId): int
    {
        return max(0, self::DAILY_LIMIT - $this->getUsed($userId));
    }

    /**
     * Consume one prompt.  Returns true if within limit, false if exhausted.
     */
    public function consume(int $userId): bool
    {
        $day  = $this->today();
        $used = $this->getUsed($userId);

        if ($used >= self::DAILY_LIMIT) {
            return false;
        }

        try {
            $existing = $this->db->get('chat_prompts', 'id', [
                'user_id' => $userId,
                'day'     => $day,
            ]);
            if ($existing) {
                $this->db->update('chat_prompts', ['prompt_count[+]' => 1], [
                    'user_id' => $userId,
                    'day'     => $day,
                ]);
            } else {
                $this->db->insert('chat_prompts', [
                    'user_id'      => $userId,
                    'day'          => $day,
                    'prompt_count' => 1,
                ]);
            }
        } catch (\Throwable $e) {
            error_log('[DailyPromptLimiter] consume: ' . $e->getMessage());
            // Allow on DB error to avoid blocking users
        }

        return true;
    }
}
