<?php
// src/Playground/MallNotifyServer.php
// Real-time WebSocket server for mall push notifications and rider GPS tracking.
namespace Playground;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

/**
 * MallNotifyServer — Ratchet WebSocket endpoint at /mall-notify
 *
 * Clients authenticate by sending:
 *   {"type":"auth","session_token":"<php_session_id>","device":"android|ios|desktop"}
 *
 * After auth the server assigns the connection a user_id and role.
 *
 * Message types sent BY clients:
 *   {"type":"gps","lat":14.5,"lng":121.0,"accuracy":10,"speed":5,"bearing":90,"shipment_id":42}
 *   {"type":"ping"}
 *
 * Message types broadcast BY server:
 *   {"type":"notification","notification":{...}}
 *   {"type":"gps_update","shipment_id":42,"lat":14.5,"lng":121.0,"bearing":90}
 *   {"type":"shipment_status","shipment_id":42,"status":"in_transit","message":"..."}
 *   {"type":"pong"}
 */
class MallNotifyServer implements MessageComponentInterface
{
    /** @var \SplObjectStorage */
    protected $clients;

    /** @var array conn->resourceId => ['user_id'=>int, 'role'=>string, 'auth'=>bool, 'device'=>string] */
    protected $connMeta = [];

    /** @var array user_id => ConnectionInterface[] */
    protected $userConns = [];

    /** @var array shipment_id => user_id[] (buyer, seller, rider, admins watching) */
    protected $shipmentWatchers = [];

    /** @var \PDO|null */
    protected $db;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage();
        $this->initDb();
    }

    // ── DB ────────────────────────────────────────────────────────────────────

    private function initDb(): void
    {
        try {
            $env = dirname(__DIR__, 2) . '/.env';
            if (file_exists($env)) {
                foreach (file($env, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                    if ($line[0] === '#' || strpos($line, '=') === false) continue;
                    [$k, $v] = explode('=', $line, 2);
                    putenv(trim($k) . '=' . trim($v, '"\''));
                }
            }
            $host   = getenv('DB_HOST') ?: '127.0.0.1';
            $dbname = getenv('DB_NAME') ?: 'ginto';
            $user   = getenv('DB_USER') ?: 'root';
            $pass   = getenv('DB_PASS') ?: '';
            $this->db = new \PDO(
                "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
                $user, $pass,
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
        } catch (\Throwable $e) {
            error_log('[MallNotifyServer] DB init failed: ' . $e->getMessage());
            $this->db = null;
        }
    }

    // ── Ratchet callbacks ─────────────────────────────────────────────────────

    public function onOpen(ConnectionInterface $conn): void
    {
        $this->clients->attach($conn);
        $this->connMeta[$conn->resourceId] = [
            'user_id' => null,
            'role'    => 'guest',
            'auth'    => false,
            'device'  => 'desktop',
        ];
        echo "[MallNotify] New connection #{$conn->resourceId}\n";
    }

    public function onMessage(ConnectionInterface $from, $msg): void
    {
        $data = json_decode($msg, true);
        if (!is_array($data)) return;
        $type = (string)($data['type'] ?? '');

        switch ($type) {
            case 'auth':
                $this->handleAuth($from, $data);
                break;
            case 'gps':
                $this->handleGps($from, $data);
                break;
            case 'watch_shipment':
                $this->handleWatchShipment($from, $data);
                break;
            case 'ping':
                $from->send(json_encode(['type' => 'pong']));
                break;
        }
    }

    public function onClose(ConnectionInterface $conn): void
    {
        $meta   = $this->connMeta[$conn->resourceId] ?? [];
        $userId = $meta['user_id'] ?? null;

        if ($userId && isset($this->userConns[$userId])) {
            $this->userConns[$userId] = array_filter(
                $this->userConns[$userId],
                fn($c) => $c->resourceId !== $conn->resourceId
            );
            if (empty($this->userConns[$userId])) {
                unset($this->userConns[$userId]);
            }
        }

        // Remove from shipment watcher lists
        foreach ($this->shipmentWatchers as $shipId => &$watchers) {
            $watchers = array_filter($watchers, fn($id) => $id !== $userId);
        }
        unset($watchers);

        unset($this->connMeta[$conn->resourceId]);
        $this->clients->detach($conn);
        echo "[MallNotify] Connection #{$conn->resourceId} closed\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        error_log('[MallNotifyServer] Error: ' . $e->getMessage());
        $conn->close();
    }

    // ── Auth ──────────────────────────────────────────────────────────────────

    private function handleAuth(ConnectionInterface $conn, array $data): void
    {
        $sessionToken = preg_replace('/[^a-zA-Z0-9,]/', '', (string)($data['session_token'] ?? ''));
        $device       = in_array($data['device'] ?? '', ['android','ios','desktop']) ? $data['device'] : 'desktop';

        if ($sessionToken === '' || !$this->db) {
            $conn->send(json_encode(['type' => 'auth_result', 'success' => false, 'message' => 'Auth failed']));
            return;
        }

        // Resolve user from PHP session file
        $userId = $this->resolveUserFromSession($sessionToken);
        if (!$userId) {
            $conn->send(json_encode(['type' => 'auth_result', 'success' => false, 'message' => 'Invalid session']));
            return;
        }

        $roleInfo = $this->getUserRole($userId);

        $this->connMeta[$conn->resourceId] = [
            'user_id' => $userId,
            'role'    => $roleInfo['role'],
            'auth'    => true,
            'device'  => $device,
        ];

        $this->userConns[$userId]   = $this->userConns[$userId] ?? [];
        $this->userConns[$userId][] = $conn;

        $conn->send(json_encode([
            'type'    => 'auth_result',
            'success' => true,
            'user_id' => $userId,
            'role'    => $roleInfo['role'],
            'is_rider'=> $roleInfo['is_rider'],
            'is_admin'=> $roleInfo['is_admin'],
        ]));
        echo "[MallNotify] Authenticated user #{$userId} ({$roleInfo['role']}) on conn #{$conn->resourceId}\n";
    }

    private function resolveUserFromSession(string $sessionId): ?int
    {
        // Read PHP session file
        $sessionPath = ini_get('session.save_path') ?: '/var/lib/php/sessions';
        $file        = $sessionPath . '/sess_' . $sessionId;
        if (!file_exists($file)) {
            // Try alternate common paths
            foreach (['/tmp', '/var/lib/php8.4/sessions', '/var/lib/php/8.4/sessions'] as $alt) {
                if (file_exists($alt . '/sess_' . $sessionId)) {
                    $file = $alt . '/sess_' . $sessionId;
                    break;
                }
            }
        }
        if (!file_exists($file)) return null;
        $raw     = @file_get_contents($file);
        if (!$raw) return null;
        // Basic PHP session unserialization for user_id
        if (preg_match('/user_id\|[isfd]:(\d+);/', $raw, $m)) {
            return (int)$m[1];
        }
        if (preg_match('/s:7:"user_id";i:(\d+);/', $raw, $m)) {
            return (int)$m[1];
        }
        return null;
    }

    private function getUserRole(int $userId): array
    {
        if (!$this->db) return ['role' => 'user', 'is_rider' => false, 'is_admin' => false];
        try {
            $stmt = $this->db->prepare('SELECT role_id, is_rider FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$row) return ['role' => 'user', 'is_rider' => false, 'is_admin' => false];
            $isAdmin = in_array((int)($row['role_id'] ?? 0), [1, 2]);
            $isRider = (bool)($row['is_rider'] ?? 0);
            return [
                'role'     => $isAdmin ? 'admin' : ($isRider ? 'rider' : 'user'),
                'is_rider' => $isRider,
                'is_admin' => $isAdmin,
            ];
        } catch (\Throwable $e) {
            return ['role' => 'user', 'is_rider' => false, 'is_admin' => false];
        }
    }

    // ── GPS ───────────────────────────────────────────────────────────────────

    private function handleGps(ConnectionInterface $conn, array $data): void
    {
        $meta = $this->connMeta[$conn->resourceId] ?? [];
        if (empty($meta['auth'])) return;
        if ($meta['role'] !== 'rider' && $meta['role'] !== 'admin') return;

        $userId     = (int)($meta['user_id'] ?? 0);
        $lat        = (float)($data['lat'] ?? 0);
        $lng        = (float)($data['lng'] ?? 0);
        $accuracy   = isset($data['accuracy']) ? (float)$data['accuracy'] : null;
        $speed      = isset($data['speed']) ? (float)$data['speed'] : null;
        $bearing    = isset($data['bearing']) ? (float)$data['bearing'] : null;
        $shipmentId = isset($data['shipment_id']) ? (int)$data['shipment_id'] : null;

        if ($lat == 0 && $lng == 0) return;

        // Persist GPS update
        if ($this->db) {
            try {
                $stmt = $this->db->prepare(
                    'INSERT INTO rider_gps_updates (rider_id, shipment_id, lat, lng, accuracy, speed, bearing, recorded_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
                );
                $stmt->execute([$userId, $shipmentId, $lat, $lng, $accuracy, $speed, $bearing]);
                // Update rider last known position
                $stmt2 = $this->db->prepare(
                    'UPDATE rider_profiles SET last_lat=?, last_lng=?, last_seen_at=NOW() WHERE user_id=?'
                );
                $stmt2->execute([$lat, $lng, $userId]);
            } catch (\Throwable $e) {
                error_log('[MallNotify] GPS persist error: ' . $e->getMessage());
            }
        }

        // Broadcast GPS update to watchers of this shipment
        if ($shipmentId) {
            $broadcast = json_encode([
                'type'        => 'gps_update',
                'shipment_id' => $shipmentId,
                'rider_id'    => $userId,
                'lat'         => $lat,
                'lng'         => $lng,
                'accuracy'    => $accuracy,
                'speed'       => $speed,
                'bearing'     => $bearing,
                'ts'          => time(),
            ]);
            foreach ($this->getShipmentWatcherConns($shipmentId, $userId) as $watcherConn) {
                try { $watcherConn->send($broadcast); } catch (\Throwable $e) {}
            }
        }
    }

    // ── Watch shipment ────────────────────────────────────────────────────────

    private function handleWatchShipment(ConnectionInterface $conn, array $data): void
    {
        $meta = $this->connMeta[$conn->resourceId] ?? [];
        if (empty($meta['auth'])) return;

        $userId     = (int)($meta['user_id'] ?? 0);
        $shipmentId = (int)($data['shipment_id'] ?? 0);
        $token      = preg_replace('/[^a-zA-Z0-9]/', '', (string)($data['token'] ?? ''));

        if ($shipmentId <= 0 || $token === '') return;

        // Verify the user has access to this shipment (buyer/seller/rider/admin)
        if (!$this->canWatchShipment($userId, $shipmentId, $token, $meta['role'])) {
            $conn->send(json_encode(['type' => 'error', 'message' => 'Access denied']));
            return;
        }

        $this->shipmentWatchers[$shipmentId]   = $this->shipmentWatchers[$shipmentId] ?? [];
        if (!in_array($userId, $this->shipmentWatchers[$shipmentId], true)) {
            $this->shipmentWatchers[$shipmentId][] = $userId;
        }

        // Send latest GPS if available
        if ($this->db) {
            try {
                $stmt = $this->db->prepare(
                    'SELECT g.lat, g.lng, g.bearing, g.speed FROM rider_gps_updates g
                     WHERE g.shipment_id = ? ORDER BY g.id DESC LIMIT 1'
                );
                $stmt->execute([$shipmentId]);
                $latest = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($latest) {
                    $conn->send(json_encode(array_merge(['type' => 'gps_update', 'shipment_id' => $shipmentId], $latest)));
                }
            } catch (\Throwable $e) {}
        }

        $conn->send(json_encode(['type' => 'watching', 'shipment_id' => $shipmentId]));
    }

    private function canWatchShipment(int $userId, int $shipmentId, string $token, string $role): bool
    {
        if ($role === 'admin') return true;
        if (!$this->db) return false;
        try {
            $stmt = $this->db->prepare(
                'SELECT ds.id, ds.rider_id, mo.buyer_id, mo.seller_id, ds.tracking_token
                 FROM delivery_shipments ds
                 JOIN mall_orders mo ON mo.id = ds.order_id
                 WHERE ds.id = ? AND ds.tracking_token = ?'
            );
            $stmt->execute([$shipmentId, $token]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$row) return false;
            return (int)$row['buyer_id']  === $userId
                || (int)$row['seller_id'] === $userId
                || (int)($row['rider_id'] ?? 0) === $userId;
        } catch (\Throwable $e) { return false; }
    }

    // ── Public broadcast API (called by PHP scripts) ──────────────────────────

    /**
     * Broadcast a notification to a specific user.
     * Callable from other PHP code via self::broadcastToUser().
     */
    public function broadcastToUserIds(array $userIds, array $payload): void
    {
        $msg = json_encode($payload);
        foreach ($userIds as $uid) {
            foreach ($this->userConns[$uid] ?? [] as $conn) {
                try { $conn->send($msg); } catch (\Throwable $e) {}
            }
        }
    }

    public function broadcastShipmentStatus(int $shipmentId, string $status, string $message = ''): void
    {
        $payload = json_encode([
            'type'        => 'shipment_status',
            'shipment_id' => $shipmentId,
            'status'      => $status,
            'message'     => $message,
            'ts'          => time(),
        ]);
        foreach ($this->shipmentWatchers[$shipmentId] ?? [] as $uid) {
            foreach ($this->userConns[$uid] ?? [] as $conn) {
                try { $conn->send($payload); } catch (\Throwable $e) {}
            }
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function getShipmentWatcherConns(int $shipmentId, int $excludeUserId = 0): array
    {
        $conns = [];
        foreach ($this->shipmentWatchers[$shipmentId] ?? [] as $uid) {
            if ($uid === $excludeUserId) continue;
            foreach ($this->userConns[$uid] ?? [] as $conn) {
                $conns[] = $conn;
            }
        }
        return $conns;
    }
}
