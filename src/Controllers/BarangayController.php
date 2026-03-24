<?php
namespace Ginto\Controllers;

use Ginto\Core\Database;

/**
 * BarangayController
 *
 * Provides GPS-based barangay detection and management for Ginto Mall.
 * All endpoints used by the Android app and the web marketplace.
 *
 * Routes:
 *   GET  /api/barangay/detect   – find nearest barangay to lat/lng
 *   GET  /api/barangay/list     – searchable list of active barangays
 *   POST /api/barangay/set      – save buyer's pinned barangay (session + DB)
 *   GET  /api/barangay/current  – return the session/profile barangay
 *   GET  /api/barangay/seller/zones – get delivery zones for a seller
 *   POST /api/barangay/seller/zones/save – save delivery zones for logged-in seller
 *   POST /api/barangay/runner/register  – register current user as a Ginto Runner
 */
class BarangayController extends \Core\Controller
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    // ── GET /api/barangay/detect ────────────────────────────────────────────
    /**
     * Returns the nearest barangay to the supplied GPS coordinates.
     * Query params: lat, lng, radius_km (optional, default 20)
     * Response: { barangay: { id, name, city, province, lat, lng, dist_m } }
     */
    public function detect(): void
    {
        header('Content-Type: application/json');

        $lat = isset($_GET['lat']) ? (float)$_GET['lat'] : null;
        $lng = isset($_GET['lng']) ? (float)$_GET['lng'] : null;

        if ($lat === null || $lng === null || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            echo json_encode(['error' => 'Invalid coordinates']);
            return;
        }

        // Clamp search radius – never trust client values
        $radiusKm = min(50, max(1, (float)($_GET['radius_km'] ?? 20)));

        try {
            // Haversine distance in metres (MySQL, only need to search nearby rows)
            $barangays = $this->db->query("
                SELECT id, name, city, province, region, lat, lng,
                       ROUND(6371000 * 2 * ASIN(SQRT(
                           POWER(SIN(RADIANS(:lat - lat) / 2), 2) +
                           COS(RADIANS(lat)) * COS(RADIANS(:lat2)) *
                           POWER(SIN(RADIANS(:lng - lng) / 2), 2)
                       ))) AS dist_m
                FROM barangays
                WHERE is_active = 1
                  AND lat BETWEEN :lat_min AND :lat_max
                  AND lng BETWEEN :lng_min AND :lng_max
                ORDER BY dist_m ASC
                LIMIT 1
            ", [
                ':lat'     => $lat,
                ':lat2'    => $lat,
                ':lng'     => $lng,
                ':lat_min' => $lat - $radiusKm / 111.0,
                ':lat_max' => $lat + $radiusKm / 111.0,
                ':lng_min' => $lng - $radiusKm / (111.0 * cos(deg2rad($lat))),
                ':lng_max' => $lng + $radiusKm / (111.0 * cos(deg2rad($lat))),
            ])->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($barangays)) {
                echo json_encode(['barangay' => null, 'message' => 'No barangay found within ' . $radiusKm . ' km']);
                return;
            }

            $b = $barangays[0];
            echo json_encode([
                'success'  => true,
                'barangay' => [
                    'id'       => (int)$b['id'],
                    'name'     => $b['name'],
                    'city'     => $b['city'],
                    'province' => $b['province'],
                    'region'   => $b['region'],
                    'lat'      => (float)$b['lat'],
                    'lng'      => (float)$b['lng'],
                    'dist_m'   => (int)$b['dist_m'],
                ],
            ]);
        } catch (\Throwable $e) {
            error_log('BarangayController::detect error: ' . $e->getMessage());
            echo json_encode(['error' => 'Could not detect barangay']);
        }
    }

    // ── GET /api/barangay/list ──────────────────────────────────────────────
    /**
     * Returns a searchable list of active barangays.
     * Query params: q (search text), city, limit (max 100), page
     */
    public function list(): void
    {
        header('Content-Type: application/json');

        $q      = trim(strip_tags($_GET['q'] ?? ''));
        $city   = trim(strip_tags($_GET['city'] ?? ''));
        $limit  = min(100, max(10, (int)($_GET['limit'] ?? 50)));
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $offset = ($page - 1) * $limit;

        try {
            $where = ['is_active' => 1];
            if ($q !== '')   $where['OR'] = ['name[~]' => $q, 'city[~]' => $q, 'province[~]' => $q];
            if ($city !== '') $where['city'] = $city;
            $where['ORDER'] = ['city' => 'ASC', 'name' => 'ASC'];
            $where['LIMIT'] = [$offset, $limit];

            $rows = $this->db->select('barangays', [
                'id', 'name', 'city', 'province', 'region', 'lat', 'lng'
            ], $where) ?: [];

            echo json_encode(['barangays' => $rows, 'page' => $page]);
        } catch (\Throwable $e) {
            error_log('BarangayController::list error: ' . $e->getMessage());
            echo json_encode(['barangays' => [], 'page' => 1]);
        }
    }

    // ── POST /api/barangay/set ──────────────────────────────────────────────
    /**
     * Pins a barangay for the current user (session + DB if logged in).
     * Body: barangay_id (int)
     */
    public function set(): void
    {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'POST required']);
            return;
        }

        $barangayId = (int)($_POST['barangay_id'] ?? -1);

        // barangay_id = 0 means clear the pinned barangay
        if ($barangayId === 0) {
            unset($_SESSION['buyer_barangay_id']);
            $userId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
            if ($userId > 0) {
                try { $this->db->update('users', ['buyer_barangay_id' => null], ['id' => $userId]); }
                catch (\Throwable $ignored) {}
            }
            echo json_encode(['success' => true, 'barangay' => null]);
            return;
        }

        if ($barangayId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid barangay_id']);
            return;
        }

        // Validate barangay exists
        $brow = $this->db->get('barangays', ['id', 'name', 'city', 'province', 'region'], [
            'id' => $barangayId, 'is_active' => 1
        ]);
        if (!$brow) {
            echo json_encode(['success' => false, 'error' => 'Barangay not found']);
            return;
        }

        // Store in session (works for both logged-in and guests)
        $_SESSION['buyer_barangay_id'] = $barangayId;

        // Persist to DB if logged in
        $userId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
        if ($userId > 0) {
            try {
                $this->db->update('users', ['buyer_barangay_id' => $barangayId], ['id' => $userId]);
            } catch (\Throwable $ignored) {}
        }

        echo json_encode(['success' => true, 'barangay' => $brow]);
    }

    // ── GET /api/barangay/current ───────────────────────────────────────────
    /**
     * Returns the currently-pinned barangay for this session.
     */
    public function current(): void
    {
        header('Content-Type: application/json');

        $barangayId = (int)($_SESSION['buyer_barangay_id'] ?? 0);

        // Fallback: load from user profile if logged in
        if ($barangayId <= 0 && !empty($_SESSION['user_id'])) {
            $user = $this->db->get('users', ['buyer_barangay_id'], ['id' => (int)$_SESSION['user_id']]);
            $barangayId = (int)($user['buyer_barangay_id'] ?? 0);
            if ($barangayId > 0) {
                $_SESSION['buyer_barangay_id'] = $barangayId;
            }
        }

        if ($barangayId <= 0) {
            echo json_encode(['barangay' => null]);
            return;
        }

        $brow = $this->db->get('barangays', ['id', 'name', 'city', 'province', 'region', 'lat', 'lng'], [
            'id' => $barangayId, 'is_active' => 1
        ]);

        echo json_encode(['barangay' => $brow ?: null]);
    }

    // ── GET /api/barangay/seller/zones ─────────────────────────────────────
    /**
     * Returns all delivery zones for a seller.
     * Query params: seller_id (required)
     */
    public function sellerZones(): void
    {
        header('Content-Type: application/json');

        $sellerId = isset($_GET['seller_id']) ? (int)$_GET['seller_id'] : 0;
        if ($sellerId <= 0) {
            echo json_encode(['zones' => []]);
            return;
        }

        try {
            $zones = $this->db->query("
                SELECT b.id, b.name, b.city, b.province, b.region,
                       z.is_home, z.created_at
                FROM seller_delivery_zones z
                JOIN barangays b ON b.id = z.barangay_id
                WHERE z.seller_id = :sid AND b.is_active = 1
                ORDER BY z.is_home DESC, b.city ASC, b.name ASC
            ", [':sid' => $sellerId])->fetchAll(\PDO::FETCH_ASSOC);

            echo json_encode(['zones' => $zones ?: []]);
        } catch (\Throwable $e) {
            error_log('BarangayController::sellerZones error: ' . $e->getMessage());
            echo json_encode(['zones' => []]);
        }
    }

    // ── POST /api/barangay/seller/zones/save ───────────────────────────────
    /**
     * Saves delivery zones for the logged-in seller.
     * Body JSON (application/json) or form: barangay_ids[] (array of ints), home_barangay_id (int)
     */
    public function saveSellerZones(): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'POST required']);
            return;
        }

        $userId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
        if (!$userId) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Login required']);
            return;
        }

        // Support both JSON body and form-encoded
        $input = [];
        $rawBody = file_get_contents('php://input');
        if ($rawBody) {
            $decoded = json_decode($rawBody, true);
            if (is_array($decoded)) $input = $decoded;
        }
        if (empty($input)) $input = $_POST;

        $token = $input['csrf_token'] ?? '';
        if (!validateCsrfToken($token)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            return;
        }

        $rawIds = $input['barangay_ids'] ?? [];
        if (!is_array($rawIds)) $rawIds = [];
        $barangayIds    = array_filter(array_map('intval', $rawIds), fn($v) => $v > 0);
        $homeBarangayId = (int)($input['home_barangay_id'] ?? ($barangayIds[0] ?? 0));

        if (count($barangayIds) > 10) {
            echo json_encode(['success' => false, 'error' => 'Maximum 10 delivery zones allowed']);
            return;
        }

        try {
            // Validate all barangay IDs exist
            if (!empty($barangayIds)) {
                $valid = $this->db->select('barangays', 'id', ['id' => $barangayIds, 'is_active' => 1]) ?: [];
                $validIds = array_column($valid, 'id');
                $barangayIds = array_values(array_filter($barangayIds, fn($id) => in_array($id, $validIds)));
            }

            // Replace all zones for this seller
            $this->db->delete('seller_delivery_zones', ['seller_id' => $userId]);

            foreach ($barangayIds as $bid) {
                $this->db->insert('seller_delivery_zones', [
                    'seller_id'   => $userId,
                    'barangay_id' => (int)$bid,
                    'is_home'     => ($bid === $homeBarangayId) ? 1 : 0,
                ]);
            }

            echo json_encode(['success' => true, 'zones_saved' => count($barangayIds)]);
        } catch (\Throwable $e) {
            error_log('BarangayController::saveSellerZones error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Failed to save zones']);
        }
    }

    // ── POST /api/barangay/runner/register ─────────────────────────────────
    /**
     * Registers the logged-in user as a Ginto Runner in their barangay.
     */
    public function runnerRegister(): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'POST required']);
            return;
        }

        $userId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
        if (!$userId) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Login required']);
            return;
        }

        $token = $_POST['csrf_token'] ?? '';
        if (!validateCsrfToken($token)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            return;
        }

        $barangayId = (int)($_POST['barangay_id'] ?? 0);
        if ($barangayId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Barangay required']);
            return;
        }

        $brow = $this->db->get('barangays', 'id', ['id' => $barangayId, 'is_active' => 1]);
        if (!$brow) {
            echo json_encode(['success' => false, 'error' => 'Barangay not found']);
            return;
        }

        $bio         = strip_tags(trim($_POST['bio'] ?? ''));
        $hasVehicle  = !empty($_POST['has_vehicle']) ? 1 : 0;
        $vehicleType = preg_replace('/[^a-zA-Z0-9 _\-]/', '', trim($_POST['vehicle_type'] ?? ''));

        try {
            $existing = $this->db->get('ginto_runners', 'id', ['user_id' => $userId]);
            if ($existing) {
                $this->db->update('ginto_runners', [
                    'barangay_id'  => $barangayId,
                    'bio'          => $bio ?: null,
                    'has_vehicle'  => $hasVehicle,
                    'vehicle_type' => $vehicleType ?: null,
                    'status'       => 'active',
                ], ['user_id' => $userId]);
                echo json_encode(['success' => true, 'action' => 'updated']);
            } else {
                $this->db->insert('ginto_runners', [
                    'user_id'      => $userId,
                    'barangay_id'  => $barangayId,
                    'bio'          => $bio ?: null,
                    'has_vehicle'  => $hasVehicle,
                    'vehicle_type' => $vehicleType ?: null,
                ]);
                echo json_encode(['success' => true, 'action' => 'registered']);
            }
        } catch (\Throwable $e) {
            error_log('BarangayController::runnerRegister error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Failed to register runner']);
        }
    }

    // ── GET /api/barangay/runners ───────────────────────────────────────────
    /**
     * Lists active runners in a barangay.
     * Query params: barangay_id (required)
     */
    public function listRunners(): void
    {
        header('Content-Type: application/json');

        $barangayId = (int)($_GET['barangay_id'] ?? 0);
        if ($barangayId <= 0) {
            echo json_encode(['runners' => []]);
            return;
        }

        try {
            $runners = $this->db->query("
                SELECT r.id, u.username, u.fullname, r.bio, r.has_vehicle,
                       r.vehicle_type, r.rating, r.deliveries
                FROM ginto_runners r
                JOIN users u ON u.id = r.user_id
                WHERE r.barangay_id = :bid AND r.status = 'active'
                ORDER BY r.rating DESC, r.deliveries DESC
                LIMIT 20
            ", [':bid' => $barangayId])->fetchAll(\PDO::FETCH_ASSOC);

            echo json_encode(['runners' => $runners ?: []]);
        } catch (\Throwable $e) {
            error_log('BarangayController::listRunners error: ' . $e->getMessage());
            echo json_encode(['runners' => []]);
        }
    }
}
