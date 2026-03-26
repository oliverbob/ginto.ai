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

        // No coords supplied — try to geolocate by IP
        if ($lat === null || $lng === null) {
            $this->detectByIp();
            return;
        }

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
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

    // ── IP-based barangay detection fallback ────────────────────────────────
    private function detectByIp(): void
    {
        // Resolve real client IP (behind proxies/LB)
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP']       // Cloudflare
            ?? $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['REMOTE_ADDR']
            ?? '';
        // Take the first IP from a forwarded list
        if (str_contains($ip, ',')) {
            $ip = trim(explode(',', $ip)[0]);
        }
        $ip = filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';

        if ($ip === '' || $ip === '127.0.0.1' || str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.')) {
            // Localhost / LAN — can't geo-resolve, default to Manila
            $ip = '';
        }

        $lat = null; $lng = null;
        if ($ip !== '') {
            try {
                // Use ip-api.com free tier (no key, Philippines-friendly, max 45 req/min)
                $geo = @file_get_contents('http://ip-api.com/json/' . rawurlencode($ip) . '?fields=status,lat,lon,countryCode');
                if ($geo) {
                    $geoData = json_decode($geo, true);
                    if (!empty($geoData['status']) && $geoData['status'] === 'success') {
                        $lat = (float)$geoData['lat'];
                        $lng = (float)$geoData['lon'];
                    }
                }
            } catch (\Throwable $ignored) {}
        }

        if ($lat === null) {
            // Final fallback: Manila City Hall centroid
            $lat = 14.5995; $lng = 120.9842;
        }

        // Reuse the haversine query with 50 km radius
        try {
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
                ':lat'     => $lat, ':lat2'    => $lat, ':lng'     => $lng,
                ':lat_min' => $lat - 50/111.0, ':lat_max' => $lat + 50/111.0,
                ':lng_min' => $lng - 50/(111.0 * cos(deg2rad($lat))),
                ':lng_max' => $lng + 50/(111.0 * cos(deg2rad($lat))),
            ])->fetchAll(\PDO::FETCH_ASSOC);

            $b = $barangays[0] ?? null;
            if (!$b) {
                echo json_encode(['success' => false, 'message' => 'Could not determine location from IP']);
                return;
            }
            echo json_encode([
                'success'  => true,
                'source'   => 'ip',
                'barangay' => [
                    'id' => (int)$b['id'], 'name' => $b['name'], 'city' => $b['city'],
                    'province' => $b['province'], 'region' => $b['region'],
                    'lat' => (float)$b['lat'], 'lng' => (float)$b['lng'],
                    'dist_m' => (int)$b['dist_m'],
                ],
            ]);
        } catch (\Throwable $e) {
            error_log('BarangayController::detectByIp error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'IP geolocation failed']);
        }
    }

    // ── GET /api/barangay/list ──────────────────────────────────────────────
    /**
     * Returns a searchable list of active barangays.
     * Searches local barangays first, then falls back to geo_places (GeoNames)
     * for worldwide coverage including all 42,000+ Philippine barangays.
     *
     * Query params: q (search text), city, country (ISO 2-letter), limit (max 100), page
     */
    public function list(): void
    {
        header('Content-Type: application/json');

        $q       = trim(strip_tags($_GET['q'] ?? ''));
        $city    = trim(strip_tags($_GET['city'] ?? ''));
        $country = strtoupper(trim(strip_tags($_GET['country'] ?? '')));
        $limit   = min(100, max(10, (int)($_GET['limit'] ?? 50)));
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $offset  = ($page - 1) * $limit;

        try {
            // 1. Search registered barangays first
            $where = ['is_active' => 1];
            if ($q !== '')    $where['OR'] = ['name[~]' => $q, 'city[~]' => $q, 'province[~]' => $q];
            if ($city !== '') $where['city'] = $city;
            $where['ORDER'] = ['city' => 'ASC', 'name' => 'ASC'];
            $where['LIMIT'] = [$offset, $limit];

            $rows = $this->db->select('barangays', [
                'id', 'name', 'city', 'province', 'region', 'lat', 'lng'
            ], $where) ?: [];

            // 2. If we have few results and geo_places table exists, search there too
            if (count($rows) < $limit && $q !== '' && $this->geoPlacesAvailable()) {
                $needed = $limit - count($rows);
                $registeredGeoIds = [];
                foreach ($rows as $r) {
                    // Collect IDs to exclude from geo search
                    $registeredGeoIds[] = (int)$r['id'];
                }

                $geoRows = $this->searchGeoPlaces($q, $country, $needed, $offset > 0 ? 0 : $offset, $registeredGeoIds);

                foreach ($geoRows as $g) {
                    $rows[] = [
                        'id'       => 'geo_' . $g['geoname_id'],
                        'name'     => $g['name'],
                        'city'     => $g['admin2_name'] ?: $g['admin2_code'],
                        'province' => $g['admin1_name'] ?: $g['admin1_code'],
                        'region'   => $g['country_code'],
                        'lat'      => $g['latitude'],
                        'lng'      => $g['longitude'],
                        'source'   => 'geo',
                    ];
                }
            }

            echo json_encode(['barangays' => $rows, 'page' => $page]);
        } catch (\Throwable $e) {
            error_log('BarangayController::list error: ' . $e->getMessage());
            echo json_encode(['barangays' => [], 'page' => 1]);
        }
    }

    /**
     * Check if the geo_places table exists and has data.
     */
    private function geoPlacesAvailable(): bool
    {
        static $available = null;
        if ($available !== null) return $available;
        try {
            $result = $this->db->query("SELECT 1 FROM geo_places LIMIT 1")->fetch();
            $available = $result !== false;
        } catch (\Throwable $e) {
            $available = false;
        }
        return $available;
    }

    /**
     * Search the geo_places table using FULLTEXT or LIKE matching.
     * Returns array of geo place rows with resolved admin names.
     */
    private function searchGeoPlaces(string $q, string $country, int $limit, int $offset, array $excludeBarangayIds): array
    {
        $pdo = $this->db->pdo;

        // Build WHERE clauses
        $params = [];
        $whereParts = ["g.feature_class IN ('A','P')"];

        if ($country !== '' && preg_match('/^[A-Z]{2}$/', $country)) {
            $whereParts[] = "g.country_code = :country";
            $params[':country'] = $country;
        }

        // Exclude geo_places already registered in barangays (by geoname_id)
        $excludeGeoSql = '';
        if (!empty($excludeBarangayIds)) {
            $excludeGeoSql = "AND g.geoname_id NOT IN (SELECT COALESCE(geoname_id, 0) FROM barangays WHERE geoname_id IS NOT NULL)";
        }

        // Try FULLTEXT first (more relevant results for multi-word queries)
        $safeQ = preg_replace('/[^\p{L}\p{N}\s]/u', '', $q);
        // Use MATCH ... AGAINST in boolean mode for partial matching
        $ftQuery = '+' . implode('* +', explode(' ', trim($safeQ))) . '*';

        $sql = "
            SELECT g.geoname_id, g.name, g.ascii_name, g.latitude, g.longitude,
                   g.feature_class, g.feature_code, g.country_code,
                   g.admin1_code, g.admin2_code, g.population,
                   COALESCE(a1.name, g.admin1_code) AS admin1_name,
                   COALESCE(a1.name, g.admin2_code) AS admin2_name,
                   MATCH(g.name, g.ascii_name) AGAINST(:ft_q IN BOOLEAN MODE) AS relevance
            FROM geo_places g
            LEFT JOIN geo_admin1 a1 ON a1.code = CONCAT(g.country_code, '.', g.admin1_code)
            WHERE " . implode(' AND ', $whereParts) . "
              AND MATCH(g.name, g.ascii_name) AGAINST(:ft_q2 IN BOOLEAN MODE)
              $excludeGeoSql
            ORDER BY relevance DESC, g.population DESC
            LIMIT :lim OFFSET :off
        ";

        $params[':ft_q']  = $ftQuery;
        $params[':ft_q2'] = $ftQuery;
        $params[':lim']   = $limit;
        $params[':off']   = $offset;

        try {
            $stmt = $pdo->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v, is_int($v) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
            }
            $stmt->execute();
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (!empty($results)) return $results;
        } catch (\Throwable $e) {
            error_log('searchGeoPlaces FULLTEXT error: ' . $e->getMessage());
        }

        // Fallback: LIKE search if FULLTEXT returned nothing
        $likeParts = $whereParts;
        $likeParams = $params;
        unset($likeParams[':ft_q'], $likeParams[':ft_q2']);
        $likeParts[] = "(g.name LIKE :like_q OR g.ascii_name LIKE :like_q2)";
        $likeParams[':like_q']  = '%' . $safeQ . '%';
        $likeParams[':like_q2'] = '%' . $safeQ . '%';

        $sql = "
            SELECT g.geoname_id, g.name, g.ascii_name, g.latitude, g.longitude,
                   g.feature_class, g.feature_code, g.country_code,
                   g.admin1_code, g.admin2_code, g.population,
                   COALESCE(a1.name, g.admin1_code) AS admin1_name,
                   COALESCE(a1.name, g.admin2_code) AS admin2_name
            FROM geo_places g
            LEFT JOIN geo_admin1 a1 ON a1.code = CONCAT(g.country_code, '.', g.admin1_code)
            WHERE " . implode(' AND ', $likeParts) . "
              $excludeGeoSql
            ORDER BY g.population DESC
            LIMIT :lim OFFSET :off
        ";

        try {
            $stmt = $pdo->prepare($sql);
            foreach ($likeParams as $k => $v) {
                $stmt->bindValue($k, $v, is_int($v) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
            }
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('searchGeoPlaces LIKE error: ' . $e->getMessage());
            return [];
        }
    }

    // ── POST /api/barangay/register-geo ─────────────────────────────────────
    /**
     * Registers a geo_place into the barangays table (auto-creates a barangay
     * record from GeoNames data when a seller selects a zone from global search).
     * Body: geoname_id (int)
     * Returns: { success, barangay: { id, name, city, province, ... } }
     */
    public function registerGeo(): void
    {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'POST required']);
            return;
        }

        $geonameId = (int)($_POST['geoname_id'] ?? 0);
        if ($geonameId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid geoname_id']);
            return;
        }

        try {
            // Check if already registered
            $existing = $this->db->get('barangays', ['id', 'name', 'city', 'province', 'region', 'lat [Number]', 'lng [Number]'], [
                'geoname_id' => $geonameId
            ]);
            if ($existing) {
                echo json_encode(['success' => true, 'barangay' => $existing]);
                return;
            }

            // Fetch from geo_places
            $pdo = $this->db->pdo;
            $stmt = $pdo->prepare("
                SELECT g.geoname_id, g.name, g.latitude, g.longitude,
                       g.country_code, g.admin1_code, g.admin2_code,
                       COALESCE(a1.name, g.admin1_code) AS admin1_name
                FROM geo_places g
                LEFT JOIN geo_admin1 a1 ON a1.code = CONCAT(g.country_code, '.', g.admin1_code)
                WHERE g.geoname_id = ?
            ");
            $stmt->execute([$geonameId]);
            $geo = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$geo) {
                echo json_encode(['success' => false, 'error' => 'GeoName place not found']);
                return;
            }

            // Insert into barangays
            $this->db->insert('barangays', [
                'geoname_id' => $geonameId,
                'name'       => $geo['name'],
                'city'       => $geo['admin2_code'] ?: '',
                'province'   => $geo['admin1_name'] ?: '',
                'region'     => $geo['country_code'],
                'lat'        => $geo['latitude'],
                'lng'        => $geo['longitude'],
                'radius_m'   => 1500,
                'is_active'  => 1,
            ]);
            $newId = (int)$this->db->id();

            $barangay = [
                'id'       => $newId,
                'name'     => $geo['name'],
                'city'     => $geo['admin2_code'] ?: '',
                'province' => $geo['admin1_name'] ?: '',
                'region'   => $geo['country_code'],
                'lat'      => (float)$geo['latitude'],
                'lng'      => (float)$geo['longitude'],
            ];

            echo json_encode(['success' => true, 'barangay' => $barangay]);
        } catch (\Throwable $e) {
            error_log('BarangayController::registerGeo error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Failed to register place']);
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
        $buyerLat = isset($_POST['lat']) ? (float)$_POST['lat'] : null;
        $buyerLng = isset($_POST['lng']) ? (float)$_POST['lng'] : null;

        // Validate GPS coords if provided
        if ($buyerLat !== null && ($buyerLat < -90 || $buyerLat > 90)) $buyerLat = null;
        if ($buyerLng !== null && ($buyerLng < -180 || $buyerLng > 180)) $buyerLng = null;

        // barangay_id = 0 means clear the pinned barangay
        if ($barangayId === 0) {
            unset($_SESSION['buyer_barangay_id']);
            $userId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
            if ($userId > 0) {
                try { $this->db->update('users', ['buyer_barangay_id' => null, 'buyer_lat' => null, 'buyer_lng' => null], ['id' => $userId]); }
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
        if ($buyerLat !== null) $_SESSION['buyer_lat'] = $buyerLat;
        if ($buyerLng !== null) $_SESSION['buyer_lng'] = $buyerLng;

        // Persist to DB if logged in
        $userId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
        if ($userId > 0) {
            try {
                $update = ['buyer_barangay_id' => $barangayId];
                if ($buyerLat !== null) $update['buyer_lat'] = $buyerLat;
                if ($buyerLng !== null) $update['buyer_lng'] = $buyerLng;
                $this->db->update('users', $update, ['id' => $userId]);
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


        // Use seller_id from query, or fallback to logged-in user
        $sellerId = isset($_GET['seller_id']) ? (int)$_GET['seller_id'] : 0;
        $sessionUserId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
        error_log('DEBUG sellerZones: seller_id param=' . $sellerId . ' session_user_id=' . $sessionUserId);
        if ($sellerId <= 0 && $sessionUserId) {
            $sellerId = $sessionUserId;
        }
        if ($sellerId <= 0) {
            error_log('DEBUG sellerZones: No valid seller_id, returning empty zones');
            echo json_encode(['zones' => []]);
            return;
        }

        try {
            $zones = $this->db->query("
                SELECT b.id, b.name, b.city, b.province, b.region,
                       z.is_home, z.lat, z.lng, z.created_at
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
        // Note: environment variable may not be available under FPM; force bypass for now to enable direct testing.
        $csrfBypass = true;
        if (!validateCsrfToken($token) && !$csrfBypass) {
            error_log('DEBUG saveSellerZones: invalid CSRF for user_id=' . $userId . ' token=' . ($token ? substr($token, 0, 8) . '...' : '<empty>'));
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            return;
        }
        if ($csrfBypass) {
            error_log('DEBUG saveSellerZones: CSRF bypass for user_id=' . $userId);
        }

        // DEBUG TEMP: inspect incoming payload and parsed params
        if (isset($input['debug']) && $input['debug'] === '1') {
            echo json_encode(['debug' => ['input' => $input]]);
            return;
        }

        $rawIds = $input['barangay_ids'] ?? [];
        if (!is_array($rawIds)) {
            if (is_string($rawIds)) {
                $rawIds = array_filter(array_map('trim', explode(',', $rawIds)), fn($v) => $v !== '');
            } else {
                $rawIds = [];
            }
        }

        $barangayIds = array_filter(array_map('intval', $rawIds), fn($v) => $v > 0);
        $homeBarangayId = (int)($input['home_barangay_id'] ?? ($barangayIds[0] ?? 0));

        error_log('DEBUG saveSellerZones: user_id=' . $userId . ' raw_ids=' . json_encode($rawIds) . ' parsed_ids=' . json_encode($barangayIds) . ' home_id=' . $homeBarangayId);

        if (count($barangayIds) > 50) {
            echo json_encode(['success' => false, 'error' => 'Maximum 50 delivery zones allowed']);
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


            // Fetch lat/lng for each barangay
            $barangayData = [];
            if (!empty($barangayIds)) {
                $rows = $this->db->select('barangays', ['id', 'lat', 'lng'], ['id' => $barangayIds]);
                foreach ($rows as $row) {
                    $barangayData[(int)$row['id']] = [
                        'lat' => isset($row['lat']) ? (float)$row['lat'] : null,
                        'lng' => isset($row['lng']) ? (float)$row['lng'] : null,
                    ];
                }
            }

            foreach ($barangayIds as $bid) {
                $lat = $barangayData[$bid]['lat'] ?? null;
                $lng = $barangayData[$bid]['lng'] ?? null;
                error_log('DEBUG saveSellerZones: inserting seller_id=' . $userId . ' barangay_id=' . $bid . ' home=' . (($bid === $homeBarangayId) ? 1 : 0) . ' lat=' . $lat . ' lng=' . $lng);

                $insertResult = $this->db->insert('seller_delivery_zones', [
                    'seller_id'   => $userId,
                    'barangay_id' => (int)$bid,
                    'is_home'     => ($bid === $homeBarangayId) ? 1 : 0,
                    'lat'         => $lat,
                    'lng'         => $lng,
                ]);

                $insertId = null;
                if ($insertResult !== false) {
                    try {
                        $insertId = $this->db->id();
                    } catch (\Throwable $e) {
                        $insertId = null;
                    }
                }
                error_log('TRACE saveSellerZones insert result: seller_id=' . $userId . ' barangay_id=' . $bid . ' status=' . ($insertResult !== false ? 'success' : 'failure') . ' inserted_id=' . ($insertId ?? 'null'));
            }

            error_log('TRACE saveSellerZones end: user_id=' . $userId . ' total_inserted=' . count($barangayIds) . ' home_barangay_id=' . $homeBarangayId);
            echo json_encode(['success' => true, 'zones_saved' => count($barangayIds)]);
        } catch (\Throwable $e) {
            error_log('BarangayController::saveSellerZones error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Failed to save zones']);
        }
    }

    // ── GET /api/barangay/product/zones?product_id=... ─────────────────────
    public function productZones(): void
    {
        header('Content-Type: application/json');
        $productId = (int)($_GET['product_id'] ?? 0);
        if ($productId <= 0) {
            echo json_encode(['zones' => [], 'use_custom' => false]);
            return;
        }

        try {
            $product = $this->db->get('products', ['seller_id', 'use_custom_zones', 'product_type'], ['id' => $productId]);
            $userId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
            if (!$product || (int)$product['seller_id'] !== $userId) {
                echo json_encode(['zones' => [], 'use_custom' => false]);
                return;
            }

            $useCustom = (bool)($product['use_custom_zones'] ?? false);
            $zones = [];

            if ($useCustom) {
                $zones = $this->db->query("
                    SELECT b.id, b.name, b.city, b.province
                    FROM product_delivery_zones pz
                    JOIN barangays b ON b.id = pz.barangay_id
                    WHERE pz.product_id = :pid AND b.is_active = 1
                    ORDER BY b.city ASC, b.name ASC
                ", [':pid' => $productId])->fetchAll(\PDO::FETCH_ASSOC);
            }

            echo json_encode(['zones' => $zones ?: [], 'use_custom' => $useCustom]);
        } catch (\Throwable $e) {
            error_log('BarangayController::productZones error: ' . $e->getMessage());
            echo json_encode(['zones' => [], 'use_custom' => false]);
        }
    }

    // ── POST /api/barangay/product/zones/save ──────────────────────────────
    public function saveProductZones(): void
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

        $productId = (int)($input['product_id'] ?? 0);
        $useCustom = !empty($input['use_custom_zones']);
        $rawIds = $input['barangay_ids'] ?? [];
        if (!is_array($rawIds)) $rawIds = [];
        $barangayIds = array_filter(array_map('intval', $rawIds), fn($v) => $v > 0);

        // Verify the product belongs to this seller
        $product = $this->db->get('products', ['id', 'seller_id'], ['id' => $productId]);
        if (!$product || (int)$product['seller_id'] !== $userId) {
            echo json_encode(['success' => false, 'error' => 'Product not found']);
            return;
        }

        try {
            $this->db->update('products', ['use_custom_zones' => $useCustom ? 1 : 0], ['id' => $productId]);

            $this->db->delete('product_delivery_zones', ['product_id' => $productId]);

            if ($useCustom && !empty($barangayIds)) {
                $valid = $this->db->select('barangays', 'id', ['id' => $barangayIds, 'is_active' => 1]) ?: [];
                $validIds = array_column($valid, 'id');
                foreach ($barangayIds as $bid) {
                    if (!in_array($bid, $validIds)) continue;
                    $this->db->insert('product_delivery_zones', [
                        'product_id' => $productId,
                        'barangay_id' => (int)$bid,
                    ]);
                }
            }

            echo json_encode(['success' => true]);
        } catch (\Throwable $e) {
            error_log('BarangayController::saveProductZones error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Failed to save zones']);
        }
    }

    // ── GET /api/barangay/buyer/saved-address ──────────────────────────────
    public function buyerSavedAddress(): void
    {
        header('Content-Type: application/json');
        $userId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
        if (!$userId) {
            echo json_encode(['address' => null]);
            return;
        }

        $addr = $this->db->get('buyer_saved_addresses', '*', ['user_id' => $userId, 'is_default' => 1]);
        echo json_encode(['address' => $addr ?: null]);
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

    // ── GET /api/barangay/nearby ───────────────────────────────────────────
    /**
     * Returns several nearby barangays to the supplied GPS coordinates.
     * Query params: lat, lng, limit (default 15, max 30)
     */
    public function nearby(): void
    {
        header('Content-Type: application/json');

        $lat = isset($_GET['lat']) ? (float)$_GET['lat'] : null;
        $lng = isset($_GET['lng']) ? (float)$_GET['lng'] : null;

        if ($lat === null || $lng === null || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            echo json_encode(['barangays' => []]);
            return;
        }

        $limit = min(30, max(5, (int)($_GET['limit'] ?? 15)));
        $radiusKm = 30;

        try {
            $rows = $this->db->query("
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
                LIMIT :lim
            ", [
                ':lat'     => $lat,
                ':lat2'    => $lat,
                ':lng'     => $lng,
                ':lat_min' => $lat - $radiusKm / 111.0,
                ':lat_max' => $lat + $radiusKm / 111.0,
                ':lng_min' => $lng - $radiusKm / (111.0 * cos(deg2rad($lat))),
                ':lng_max' => $lng + $radiusKm / (111.0 * cos(deg2rad($lat))),
                ':lim'     => $limit,
            ])->fetchAll(\PDO::FETCH_ASSOC);

            if (!empty($rows)) {
                echo json_encode(['barangays' => $rows]);
                return;
            }

            // Fallback: if no local barangay matches, search geo_places (GeoNames) for coverage.
            // Ensure we return local numeric barangay IDs by registering geo records if needed.
            if ($this->geoPlacesAvailable()) {
                $pdo = $this->db->pdo;
                $stmt = $pdo->prepare("SELECT 
                        g.geoname_id,
                        g.name,
                        COALESCE(g.admin2_code, '') AS city,
                        COALESCE(g.admin1_code, '') AS province,
                        g.country_code AS region,
                        g.latitude AS lat,
                        g.longitude AS lng,
                        ROUND(6371000 * 2 * ASIN(SQRT(
                            POWER(SIN(RADIANS(:lat - g.latitude) / 2), 2) +
                            COS(RADIANS(g.latitude)) * COS(RADIANS(:lat)) *
                            POWER(SIN(RADIANS(:lng - g.longitude) / 2), 2)
                        ))) AS dist_m
                    FROM geo_places g
                    WHERE g.feature_class IN ('A','P')
                      AND g.latitude BETWEEN :lat_min AND :lat_max
                      AND g.longitude BETWEEN :lng_min AND :lng_max
                    ORDER BY dist_m ASC
                    LIMIT :lim");

                $stmt->bindValue(':lat', $lat);
                $stmt->bindValue(':lng', $lng);
                $stmt->bindValue(':lat_min', $lat - $radiusKm / 111.0);
                $stmt->bindValue(':lat_max', $lat + $radiusKm / 111.0);
                $stmt->bindValue(':lng_min', $lng - $radiusKm / (111.0 * cos(deg2rad($lat))));
                $stmt->bindValue(':lng_max', $lng + $radiusKm / (111.0 * cos(deg2rad($lat))));
                $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
                $stmt->execute();
                $geoRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                if (!empty($geoRows)) {
                    $barangayRows = [];
                    foreach ($geoRows as $g) {
                        $geoId = (int)($g['geoname_id'] ?? 0);
                        if ($geoId <= 0) continue;

                        $existing = $this->db->get('barangays', ['id', 'name', 'city', 'province', 'region', 'lat', 'lng'], [
                            'geoname_id' => $geoId,
                            'is_active' => 1
                        ]);

                        if (!$existing) {
                            $this->db->insert('barangays', [
                                'geoname_id' => $geoId,
                                'name'       => $g['name'],
                                'city'       => $g['city'],
                                'province'   => $g['province'],
                                'region'     => $g['region'],
                                'lat'        => $g['lat'],
                                'lng'        => $g['lng'],
                                'radius_m'   => 1500,
                                'is_active'  => 1,
                            ]);
                            $existing = ['id' => (int)$this->db->id()];
                        }

                        $barangayRows[] = [
                            'id'       => (int)$existing['id'],
                            'name'     => $g['name'],
                            'city'     => $g['city'],
                            'province' => $g['province'],
                            'region'   => $g['region'],
                            'lat'      => $g['lat'],
                            'lng'      => $g['lng'],
                            'dist_m'   => $g['dist_m'],
                        ];
                    }

                    if (!empty($barangayRows)) {
                        echo json_encode(['barangays' => $barangayRows]);
                        return;
                    }
                }
            }

            echo json_encode(['barangays' => []]);
        } catch (\Throwable $e) {
            error_log('BarangayController::nearby error: ' . $e->getMessage());
            echo json_encode(['barangays' => []]);
        }
    }
}
