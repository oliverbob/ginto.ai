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
    /** Whether the bot may open NEW positions (false while winding down). */
    public function isOpeningNew(): bool { return (int) ($this->row()['open_new'] ?? 1) === 1; }

    /** When the current trading session began (set on each Start). Null if never started. */
    public function sessionStartedAt(): ?string
    {
        $v = $this->row()['session_started_at'] ?? null;
        return $v ?: null;
    }

    private function upsert(array $fields): void
    {
        try {
            if ($this->db->has($this->table, ['id' => 1])) {
                $this->db->update($this->table, $fields, ['id' => 1]);
            } else {
                $this->db->insert($this->table, ['id' => 1] + $fields);
            }
        } catch (\Throwable $e) {}
    }

    /** Start: run + open new trades, fresh session clock. */
    public function start(bool $armLive): void
    {
        $this->upsert(['enabled' => 1, 'open_new' => 1, 'arm_live' => $armLive ? 1 : 0,
                       'session_started_at' => date('Y-m-d H:i:s')]);
    }

    /** Wind down: keep running/managing open positions but stop opening new ones. */
    public function windDown(): void { $this->upsert(['open_new' => 0]); }

    /** Full stop: runner halts. Live positions keep their resting exchange stops. */
    public function stop(): void { $this->upsert(['enabled' => 0, 'open_new' => 0]); }

    /** Arm/disarm real-money trading independently of run state (takes effect next step). */
    public function setArmLive(bool $armLive): void { $this->upsert(['arm_live' => $armLive ? 1 : 0]); }

    /** Back-compat: true => start, false => wind down (graceful). */
    public function set(bool $enabled, bool $armLive): void
    {
        $enabled ? $this->start($armLive) : $this->windDown();
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
            'open_new'    => (int) ($r['open_new'] ?? 1) === 1,
            'arm_live'    => (int) ($r['arm_live'] ?? 0) === 1,
            'last_run_at' => $r['last_run_at'] ?? null,
            'last_action' => $r['last_action'] ?? null,
        ];
    }
}
