<?php
namespace Ginto\Models;

use Ginto\Core\Database;
use Medoo\Medoo;

/**
 * Persistent on/off state for the trading bot's server-side runner.
 * Survives process/server restarts so the bot resumes where it left off.
 */
class GtbBotState
{
    private Medoo $db;
    private string $table = 'gtb_bot_state';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    private function row(): array
    {
        try {
            $r = $this->db->get($this->table, '*', ['id' => 1]);
            return is_array($r) ? $r : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function isEnabled(): bool { return (int) ($this->row()['enabled'] ?? 0) === 1; }
    public function isArmLive(): bool { return (int) ($this->row()['arm_live'] ?? 0) === 1; }

    public function set(bool $enabled, bool $armLive): void
    {
        try {
            $data = ['id' => 1, 'enabled' => $enabled ? 1 : 0, 'arm_live' => $armLive ? 1 : 0];
            if ($this->db->has($this->table, ['id' => 1])) {
                $this->db->update($this->table, ['enabled' => $data['enabled'], 'arm_live' => $data['arm_live']], ['id' => 1]);
            } else {
                $this->db->insert($this->table, $data);
            }
        } catch (\Throwable $e) {}
    }

    public function markRun(string $action): void
    {
        try {
            $this->db->update($this->table, ['last_run_at' => date('Y-m-d H:i:s'), 'last_action' => mb_substr($action, 0, 180)], ['id' => 1]);
        } catch (\Throwable $e) {}
    }

    public function status(): array
    {
        $r = $this->row();
        return [
            'enabled'     => (int) ($r['enabled'] ?? 0) === 1,
            'arm_live'    => (int) ($r['arm_live'] ?? 0) === 1,
            'last_run_at' => $r['last_run_at'] ?? null,
            'last_action' => $r['last_action'] ?? null,
        ];
    }
}
