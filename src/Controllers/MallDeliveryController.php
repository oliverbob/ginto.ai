<?php
// src/Controllers/MallDeliveryController.php
namespace Ginto\Controllers;

use Core\Controller;
use Ginto\Core\Database;
use Ginto\Services\MallCommerceService;
use Ginto\Services\MallPushService;

/**
 * Delivery system for Ginto Mall.
 * Routes under /mall/delivery and /api/mall/delivery.
 */
class MallDeliveryController extends Controller
{
    private $db;
    private MallCommerceService $commerce;
    private MallPushService $push;

    public function __construct($db = null)
    {
        parent::__construct();
        $this->db      = $db ?? Database::getInstance();
        $this->commerce = new MallCommerceService($this->db);
        $this->push     = new MallPushService($this->db);
    }

    // ── Pages ─────────────────────────────────────────────────────────────────

    /**
     * GET /mall/delivery
     * Universal delivery hub: shows tracking, rider view, or admin panel
     * depending on session role.
     */
    public function index(): void
    {
        $userId   = $this->optionalUser();
        $isAdmin  = $this->isAdmin($userId);
        $isRider  = $userId ? $this->isRider($userId) : false;
        $isSeller = $userId ? $this->isSeller($userId) : false;

        if ($isAdmin) {
            $this->adminLogistics();
            return;
        }
        if ($isRider) {
            $this->riderDashboard();
            return;
        }

        // Buyer/seller/guest — show public tracking form
        $walletSummary = $userId > 0 ? $this->commerce->getWalletSummary($userId) : ['account' => []];
        $myShipments   = $userId > 0 ? $this->getMyShipments($userId) : [];

        $this->view('mall/delivery/index', [
            'title'                     => 'Delivery Tracking — Ginto Mall',
            'csrf_token'                => generateCsrfToken(),
            'mall_unread_notifications' => $userId > 0 ? $this->commerce->getMallUnreadNotificationCount($userId) : 0,
            'mall_notifications'        => $userId > 0 ? $this->commerce->getMallNotifications($userId) : [],
            'mall_wallet_balance'       => (float)($walletSummary['account']['balance'] ?? 0),
            'my_shipments'              => $myShipments,
            'is_seller'                 => $isSeller,
        ]);
    }

    /**
     * GET /mall/delivery/track/{token}
     * Universal live-tracking page. Role-based data exposure depends on who is logged in.
     */
    public function track(string $token = ''): void
    {
        $token = preg_replace('/[^a-zA-Z0-9]/', '', $token);
        if (strlen($token) < 8) {
            http_response_code(404);
            echo '<h1>Tracking token not found</h1>';
            return;
        }

        $shipment = $this->getShipmentByToken($token);
        if (!$shipment) {
            http_response_code(404);
            echo '<h1>Shipment not found</h1>';
            return;
        }

        $userId  = $this->optionalUser();
        $isAdmin = $this->isAdmin($userId);
        $role    = 'guest';

        if ($userId) {
            if ($isAdmin) {
                $role = 'admin';
            } elseif ((int)$shipment['buyer_id'] === $userId) {
                $role = 'buyer';
            } elseif ((int)$shipment['seller_id'] === $userId) {
                $role = 'seller';
            } elseif ((int)($shipment['rider_id'] ?? 0) === $userId) {
                $role = 'rider';
            }
        }

        // Get GPS history for the map
        $gpsHistory = $this->getGpsHistory((int)$shipment['id'], 100);

        // Status timeline
        $statusHistory = $this->getStatusHistory((int)$shipment['id']);

        $walletSummary = $userId > 0 ? $this->commerce->getWalletSummary($userId) : ['account' => []];

        $this->view('mall/delivery/track', [
            'title'                     => 'Track Shipment #' . htmlspecialchars($shipment['order_code'] ?? $token),
            'csrf_token'                => generateCsrfToken(),
            'shipment'                  => $shipment,
            'role'                      => $role,
            'viewer_user_id'            => $userId,
            'gps_history'               => $gpsHistory,
            'status_history'            => $statusHistory,
            'tracking_token'            => $token,
            'mall_unread_notifications' => $userId > 0 ? $this->commerce->getMallUnreadNotificationCount($userId) : 0,
            'mall_notifications'        => $userId > 0 ? $this->commerce->getMallNotifications($userId) : [],
            'mall_wallet_balance'       => (float)($walletSummary['account']['balance'] ?? 0),
            'is_admin'                  => $isAdmin,
        ]);
    }

    /**
     * GET /mall/delivery/rider
     * Rider active delivery dashboard with GPS sender.
     */
    public function riderDashboard(): void
    {
        $userId = $this->requireUserRedirect('/mall/delivery/rider');
        if (!$this->isRider($userId) && !$this->isAdmin($userId)) {
            http_response_code(403);
            echo '<p>Rider account required. Contact admin to enable rider access.</p>';
            return;
        }

        $walletSummary = $this->commerce->getWalletSummary($userId);
        $riderProfile  = $this->getRiderProfile($userId);
        $activeOrders  = $this->getRiderActiveDeliveries($userId);
        $allOrders     = $this->getRiderAllDeliveries($userId);

        $this->view('mall/delivery/rider', [
            'title'                     => 'Rider Dashboard — Ginto Mall',
            'csrf_token'                => generateCsrfToken(),
            'rider_profile'             => $riderProfile,
            'active_orders'             => $activeOrders,
            'all_orders'                => $allOrders,
            'mall_unread_notifications' => $this->commerce->getMallUnreadNotificationCount($userId),
            'mall_notifications'        => $this->commerce->getMallNotifications($userId),
            'mall_wallet_balance'       => (float)($walletSummary['account']['balance'] ?? 0),
            'rider_user_id'             => $userId,
        ]);
    }

    /**
     * GET /mall/delivery/admin
     * Admin logistics dashboard.
     */
    public function adminLogistics(): void
    {
        $userId = $this->requireUserRedirect('/mall/delivery/admin');
        if (!$this->isAdmin($userId)) {
            http_response_code(403);
            echo 'Admin access required.';
            return;
        }

        $walletSummary = $this->commerce->getWalletSummary($userId);
        $stats         = $this->getLogisticsStats();
        $recentShipments = $this->getRecentShipments(50);
        $riders          = $this->getAllRiders();

        $this->view('mall/delivery/admin_logistics', [
            'title'                     => 'Logistics Dashboard — Ginto Mall Admin',
            'csrf_token'                => generateCsrfToken(),
            'stats'                     => $stats,
            'recent_shipments'          => $recentShipments,
            'riders'                    => $riders,
            'mall_unread_notifications' => $this->commerce->getMallUnreadNotificationCount($userId),
            'mall_notifications'        => $this->commerce->getMallNotifications($userId),
            'mall_wallet_balance'       => (float)($walletSummary['account']['balance'] ?? 0),
            'admin_user_id'             => $userId,
        ]);
    }

    // ── API endpoints ─────────────────────────────────────────────────────────

    /**
     * POST /api/mall/delivery/gps
     * Rider submits GPS location update.
     */
    public function apiGpsUpdate(): void
    {
        header('Content-Type: application/json');
        $userId = $this->requireUserJsonId();
        if (!$this->isRider($userId) && !$this->isAdmin($userId)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Rider account required.']);
            return;
        }
        $this->requirePostJson();
        $input      = $this->jsonInput();
        $this->validateCsrfFromPayload($input);

        $lat        = (float)($input['lat'] ?? 0);
        $lng        = (float)($input['lng'] ?? 0);
        $accuracy   = isset($input['accuracy']) ? (float)$input['accuracy'] : null;
        $speed      = isset($input['speed']) ? (float)$input['speed'] : null;
        $bearing    = isset($input['bearing']) ? (float)$input['bearing'] : null;
        $shipmentId = isset($input['shipment_id']) ? (int)$input['shipment_id'] : null;

        if ($lat == 0 && $lng == 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid coordinates.']);
            return;
        }

        try {
            $this->db->insert('rider_gps_updates', [
                'rider_id'    => $userId,
                'shipment_id' => $shipmentId,
                'lat'         => $lat,
                'lng'         => $lng,
                'accuracy'    => $accuracy,
                'speed'       => $speed,
                'bearing'     => $bearing,
                'recorded_at' => date('Y-m-d H:i:s'),
            ]);
            $this->db->update('rider_profiles', [
                'last_lat'    => $lat,
                'last_lng'    => $lng,
                'last_seen_at'=> date('Y-m-d H:i:s'),
            ], ['user_id' => $userId]);

            echo json_encode(['success' => true]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to record GPS.']);
        }
    }

    /**
     * POST /api/mall/delivery/status
     * Update shipment status (seller can mark ready, rider marks in_transit/delivered, admin can set any).
     */
    public function apiStatusUpdate(): void
    {
        header('Content-Type: application/json');
        $userId = $this->requireUserJsonId();
        $this->requirePostJson();
        $input      = $this->jsonInput();
        $this->validateCsrfFromPayload($input);

        $shipmentId = (int)($input['shipment_id'] ?? 0);
        $newStatus  = trim(strip_tags((string)($input['status'] ?? '')));
        $note       = trim(strip_tags((string)($input['note'] ?? '')));

        $allowedStatuses = ['pending','ready_for_pickup','picked_up','in_transit','out_for_delivery','delivered','failed_delivery','returned'];
        if (!in_array($newStatus, $allowedStatuses, true)) {
            echo json_encode(['success' => false, 'message' => 'Invalid status.']);
            return;
        }

        try {
            $shipment = $this->db->get('delivery_shipments', '*', ['id' => $shipmentId]);
            if (!$shipment) throw new \RuntimeException('Shipment not found.');

            // Check authorization
            $order = $this->db->get('mall_orders', ['buyer_id','seller_id','id'], ['id' => (int)$shipment['order_id']]);
            $isAdmin  = $this->isAdmin($userId);
            $isRider  = (int)($shipment['rider_id'] ?? 0) === $userId;
            $isSeller = $order && (int)$order['seller_id'] === $userId;

            if (!$isAdmin && !$isRider && !$isSeller) {
                throw new \RuntimeException('You are not authorized to update this shipment.');
            }

            $oldStatus  = $shipment['status'];
            $actorRole  = $isAdmin ? 'admin' : ($isRider ? 'rider' : 'seller');
            $updateData = ['status' => $newStatus, 'updated_at' => date('Y-m-d H:i:s')];

            if ($newStatus === 'picked_up')  $updateData['picked_up_at'] = date('Y-m-d H:i:s');
            if ($newStatus === 'delivered')  $updateData['delivered_at'] = date('Y-m-d H:i:s');

            $this->db->update('delivery_shipments', $updateData, ['id' => $shipmentId]);

            // Log history
            $this->db->insert('delivery_status_history', [
                'shipment_id' => $shipmentId,
                'actor_id'    => $userId,
                'actor_role'  => $actorRole,
                'old_status'  => $oldStatus,
                'new_status'  => $newStatus,
                'note'        => $note ?: null,
                'created_at'  => date('Y-m-d H:i:s'),
            ]);

            // Push notifications
            if ($order) {
                $this->push->notifyShipmentStatus(
                    $shipmentId, $newStatus,
                    (int)$order['buyer_id'], (int)$order['seller_id'],
                    (int)($shipment['rider_id'] ?? 0) ?: null,
                    (string)$shipment['tracking_token']
                );
            }

            echo json_encode(['success' => true, 'status' => $newStatus]);
        } catch (\Throwable $e) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/mall/delivery/rider/profile
     * Upsert rider profile (vehicle type, plate, availability).
     */
    public function apiRiderProfile(): void
    {
        header('Content-Type: application/json');
        $userId = $this->requireUserJsonId();
        $this->requirePostJson();
        $input = $this->jsonInput();
        $this->validateCsrfFromPayload($input);

        $vehicleType  = in_array($input['vehicle_type'] ?? '', ['motorcycle','bicycle','car','van','tricycle','walk'])
            ? $input['vehicle_type'] : 'motorcycle';
        $plateNumber  = preg_replace('/[^a-zA-Z0-9\-\s]/', '', (string)($input['plate_number'] ?? ''));
        $isAvailable  = !empty($input['is_available']) ? 1 : 0;

        try {
            $existing = $this->db->get('rider_profiles', ['id'], ['user_id' => $userId]);
            if ($existing) {
                $this->db->update('rider_profiles', [
                    'vehicle_type' => $vehicleType,
                    'plate_number' => $plateNumber ?: null,
                    'is_available' => $isAvailable,
                    'updated_at'   => date('Y-m-d H:i:s'),
                ], ['user_id' => $userId]);
            } else {
                $this->db->insert('rider_profiles', [
                    'user_id'      => $userId,
                    'vehicle_type' => $vehicleType,
                    'plate_number' => $plateNumber ?: null,
                    'is_available' => $isAvailable,
                    'is_active'    => 1,
                    'created_at'   => date('Y-m-d H:i:s'),
                ]);
            }
            echo json_encode(['success' => true]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/mall/delivery/assign-rider
     * Admin assigns a rider to a shipment.
     */
    public function apiAssignRider(): void
    {
        header('Content-Type: application/json');
        $userId = $this->requireUserJsonId();
        if (!$this->isAdmin($userId)) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Admin only']); return; }
        $this->requirePostJson();
        $input = $this->jsonInput();
        $this->validateCsrfFromPayload($input);

        $shipmentId = (int)($input['shipment_id'] ?? 0);
        $riderId    = (int)($input['rider_id'] ?? 0);
        if ($shipmentId <= 0 || $riderId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
            return;
        }

        try {
            $this->db->update('delivery_shipments', [
                'rider_id'   => $riderId,
                'claimed_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['id' => $shipmentId]);

            // Notify the rider
            $shipment = $this->db->get('delivery_shipments', '*', ['id' => $shipmentId]);
            if ($shipment) {
                $order = $this->db->get('mall_orders', ['order_code', 'buyer_id', 'seller_id'], ['id' => (int)$shipment['order_id']]);
                $orderCode = $order['order_code'] ?? '#' . $shipmentId;
                $this->push->notify(
                    [$riderId],
                    "You've been assigned to deliver Order {$orderCode}. Tap to view tracking.",
                    'rider_assigned',
                    ['shipment_id' => $shipmentId, 'url' => '/mall/delivery/track/' . $shipment['tracking_token']]
                );
            }

            echo json_encode(['success' => true]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/mall/push/subscribe
     * Store a Web Push subscription for the current user.
     */
    public function apiPushSubscribe(): void
    {
        header('Content-Type: application/json');
        $this->requirePostJson();
        $input = $this->jsonInput();
        $this->validateCsrfFromPayload($input);

        $userId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        $sub    = is_array($input['subscription'] ?? null) ? $input['subscription'] : [];
        $device = in_array($input['device'] ?? '', ['android','ios','desktop']) ? $input['device'] : 'desktop';

        if ($this->push->saveSubscription($userId, $sub, 'mall', $device)) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid subscription data.']);
        }
    }

    /**
     * GET /api/mall/push/vapid-public-key
     * Returns VAPID public key for service worker registration.
     */
    public function apiVapidPublicKey(): void
    {
        header('Content-Type: application/json');
        $key = getenv('VAPID_PUBLIC_KEY') ?: '';
        echo json_encode(['success' => true, 'public_key' => $key]);
    }

    /**
     * POST /api/mall/push/register-fcm
     * Store an FCM device token for the authenticated user (Android app).
     */
    public function apiRegisterFcm(): void
    {
        header('Content-Type: application/json');
        $this->requirePostJson();
        $input = $this->jsonInput();

        $userId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        if (!$userId) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
            return;
        }

        $token      = trim((string)($input['fcm_token']    ?? ''));
        $deviceType = trim((string)($input['device_type']  ?? 'android'));
        $deviceType = in_array($deviceType, ['android', 'ios'], true) ? $deviceType : 'android';

        if ($token === '' || strlen($token) > 512) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid FCM token.']);
            return;
        }

        if ($this->push->saveFcmToken($userId, $token, $deviceType)) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to save token.']);
        }
    }

    /**
     * GET /api/mall/delivery/shipment/{id}
     * Returns shipment details + latest GPS. Access controlled.
     */
    public function apiShipmentInfo(string $idOrToken = ''): void
    {
        header('Content-Type: application/json');
        $userId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

        // Accept either numeric id or tracking token
        $shipment = null;
        if (ctype_digit($idOrToken)) {
            $shipment = $this->db->get('delivery_shipments', '*', ['id' => (int)$idOrToken]);
        } else {
            $token    = preg_replace('/[^a-zA-Z0-9]/', '', $idOrToken);
            $shipment = $this->db->get('delivery_shipments', '*', ['tracking_token' => $token]);
        }

        if (!$shipment) { http_response_code(404); echo json_encode(['success'=>false,'message'=>'Not found']); return; }

        $order   = $this->db->get('mall_orders', ['buyer_id','seller_id','order_code','status'], ['id' => (int)$shipment['order_id']]);
        $isAdmin = $this->isAdmin($userId);
        $hasAccess = $isAdmin
            || ($order && (int)$order['buyer_id'] === $userId)
            || ($order && (int)$order['seller_id'] === $userId)
            || (int)($shipment['rider_id'] ?? 0) === $userId;

        if (!$hasAccess) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Access denied']); return; }

        // Latest GPS
        $gps = $this->db->get('rider_gps_updates', ['lat','lng','bearing','speed','recorded_at'], [
            'shipment_id' => (int)$shipment['id'],
            'ORDER'       => ['id' => 'DESC'],
        ]);

        echo json_encode(['success' => true, 'shipment' => $shipment, 'order' => $order, 'latest_gps' => $gps ?: null]);
    }

    // ── Data helpers ──────────────────────────────────────────────────────────

    private function getShipmentByToken(string $token): ?array
    {
        try {
            $shipment = $this->db->get('delivery_shipments', '*', ['tracking_token' => $token]);
            if (!$shipment) return null;
            $order = $this->db->get('mall_orders', [
                'id','order_code','buyer_id','seller_id','status','payment_status',
                'shipping_address_json','buyer_total_amount','currency',
                'created_at','paid_at','delivered_at',
            ], ['id' => (int)$shipment['order_id']]);
            if (!$order) return null;

            // Buyer/seller names
            $buyer  = $this->db->get('users', ['id','fullname','username','phone'], ['id' => (int)$order['buyer_id']]);
            $seller = $this->db->get('users', ['id','fullname','username'],          ['id' => (int)$order['seller_id']]);
            $rider  = null;
            if (!empty($shipment['rider_id'])) {
                $rider = $this->db->get('users', ['id','fullname','username','phone'], ['id' => (int)$shipment['rider_id']]);
            }

            // Order items
            $items = $this->db->select('mall_order_items', ['product_title','quantity','unit_price','subtotal'], ['order_id' => (int)$order['id']]) ?: [];

            return array_merge($shipment, [
                'order'  => $order,
                'buyer'  => $buyer,
                'seller' => $seller,
                'rider'  => $rider,
                'items'  => $items,
                // for role checks
                'buyer_id'  => (int)$order['buyer_id'],
                'seller_id' => (int)$order['seller_id'],
                'order_code'=> $order['order_code'],
            ]);
        } catch (\Throwable $e) {
            error_log('[MallDeliveryController] getShipmentByToken: ' . $e->getMessage());
            return null;
        }
    }

    private function getGpsHistory(int $shipmentId, int $limit = 200): array
    {
        try {
            return $this->db->select('rider_gps_updates', ['lat','lng','bearing','speed','recorded_at'], [
                'shipment_id' => $shipmentId,
                'ORDER'       => ['id' => 'ASC'],
                'LIMIT'       => [0, $limit],
            ]) ?: [];
        } catch (\Throwable $e) { return []; }
    }

    private function getStatusHistory(int $shipmentId): array
    {
        try {
            $rows = $this->db->select('delivery_status_history', '*', [
                'shipment_id' => $shipmentId,
                'ORDER'       => ['id' => 'ASC'],
            ]) ?: [];
            foreach ($rows as &$row) {
                if ($row['actor_id']) {
                    $u = $this->db->get('users', ['fullname','username'], ['id' => (int)$row['actor_id']]);
                    $row['actor_name'] = $u ? ($u['fullname'] ?: $u['username']) : 'System';
                } else {
                    $row['actor_name'] = 'System';
                }
            }
            return $rows;
        } catch (\Throwable $e) { return []; }
    }

    private function getMyShipments(int $userId): array
    {
        try {
            $orders = $this->db->select('mall_orders', ['id'], ['buyer_id' => $userId]) ?: [];
            if (empty($orders)) return [];
            $orderIds = array_column($orders, 'id');
            $shipments = $this->db->select('delivery_shipments', '*', [
                'order_id'  => $orderIds,
                'ORDER'     => ['id' => 'DESC'],
                'LIMIT'     => [0, 10],
            ]) ?: [];
            foreach ($shipments as &$s) {
                $o = $this->db->get('mall_orders', ['order_code','buyer_total_amount','currency'], ['id' => (int)$s['order_id']]);
                $s['order_code'] = $o['order_code'] ?? '—';
                $s['total']      = $o['buyer_total_amount'] ?? 0;
                $s['currency']   = $o['currency'] ?? 'PHP';
            }
            return $shipments;
        } catch (\Throwable $e) { return []; }
    }

    private function getRiderProfile(int $userId): array
    {
        try {
            return $this->db->get('rider_profiles', '*', ['user_id' => $userId]) ?: [];
        } catch (\Throwable $e) { return []; }
    }

    private function getRiderActiveDeliveries(int $userId): array
    {
        try {
            return $this->db->select('delivery_shipments', '*', [
                'rider_id' => $userId,
                'status'   => ['picked_up', 'in_transit', 'out_for_delivery', 'ready_for_pickup'],
                'ORDER'    => ['id' => 'DESC'],
            ]) ?: [];
        } catch (\Throwable $e) { return []; }
    }

    private function getRiderAllDeliveries(int $userId): array
    {
        try {
            $rows = $this->db->select('delivery_shipments', '*', [
                'rider_id' => $userId,
                'ORDER'    => ['id' => 'DESC'],
                'LIMIT'    => [0, 30],
            ]) ?: [];
            foreach ($rows as &$r) {
                $o = $this->db->get('mall_orders', ['order_code','buyer_total_amount','currency'], ['id' => (int)$r['order_id']]);
                $r['order_code'] = $o['order_code'] ?? '—';
                $r['total']      = $o['buyer_total_amount'] ?? 0;
                $r['currency']   = $o['currency'] ?? 'PHP';
            }
            return $rows;
        } catch (\Throwable $e) { return []; }
    }

    private function getLogisticsStats(): array
    {
        try {
            $total     = $this->db->count('delivery_shipments', []);
            $pending   = $this->db->count('delivery_shipments', ['status' => 'pending']);
            $inTransit = $this->db->count('delivery_shipments', ['status' => ['in_transit','out_for_delivery','picked_up']]);
            $delivered = $this->db->count('delivery_shipments', ['status' => 'delivered']);
            $failed    = $this->db->count('delivery_shipments', ['status' => ['failed_delivery','returned']]);
            $riders    = $this->db->count('rider_profiles', ['is_active' => 1]);
            $available = $this->db->count('rider_profiles', ['is_active' => 1, 'is_available' => 1]);
            return compact('total','pending','inTransit','delivered','failed','riders','available');
        } catch (\Throwable $e) { return []; }
    }

    private function getRecentShipments(int $limit = 50): array
    {
        try {
            $rows = $this->db->select('delivery_shipments', '*', [
                'ORDER' => ['id' => 'DESC'],
                'LIMIT' => [0, $limit],
            ]) ?: [];
            foreach ($rows as &$r) {
                $o = $this->db->get('mall_orders', ['order_code','buyer_id','seller_id','buyer_total_amount','currency'], ['id' => (int)$r['order_id']]);
                if ($o) {
                    $r['order_code'] = $o['order_code'];
                    $r['total']      = $o['buyer_total_amount'];
                    $r['currency']   = $o['currency'];
                    $buyer  = $this->db->get('users', ['fullname','username'], ['id' => (int)$o['buyer_id']]);
                    $seller = $this->db->get('users', ['fullname','username'], ['id' => (int)$o['seller_id']]);
                    $r['buyer_name']  = $buyer  ? ($buyer['fullname']  ?: $buyer['username'])  : '—';
                    $r['seller_name'] = $seller ? ($seller['fullname'] ?: $seller['username']) : '—';
                }
                if (!empty($r['rider_id'])) {
                    $rider = $this->db->get('users', ['fullname','username'], ['id' => (int)$r['rider_id']]);
                    $r['rider_name'] = $rider ? ($rider['fullname'] ?: $rider['username']) : '—';
                }
            }
            return $rows;
        } catch (\Throwable $e) { return []; }
    }

    private function getAllRiders(): array
    {
        try {
            $profiles = $this->db->select('rider_profiles', '*', ['is_active' => 1]) ?: [];
            foreach ($profiles as &$p) {
                $u = $this->db->get('users', ['id','fullname','username','phone'], ['id' => (int)$p['user_id']]);
                $p['name']  = $u ? ($u['fullname'] ?: $u['username']) : '—';
                $p['phone'] = $u['phone'] ?? '';
            }
            return $profiles;
        } catch (\Throwable $e) { return []; }
    }

    // ── Auth helpers ──────────────────────────────────────────────────────────

    private function optionalUser(): int
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        return !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    }

    private function requireUserRedirect(string $redirect = '/login'): int
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        if (empty($_SESSION['user_id'])) {
            $_SESSION['login_redirect'] = $redirect;
            header('Location: /login');
            exit;
        }
        return (int)$_SESSION['user_id'];
    }

    private function requireUserJsonId(): int
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Login required.']);
            exit;
        }
        return (int)$_SESSION['user_id'];
    }

    private function isAdmin(int $userId): bool
    {
        if ($userId <= 0) return false;
        try {
            $u = $this->db->get('users', ['role_id'], ['id' => $userId]);
            return in_array((int)($u['role_id'] ?? 0), [1, 2]);
        } catch (\Throwable $e) { return false; }
    }

    private function isRider(int $userId): bool
    {
        if ($userId <= 0) return false;
        try {
            $u = $this->db->get('users', ['is_rider'], ['id' => $userId]);
            return (bool)($u['is_rider'] ?? false);
        } catch (\Throwable $e) { return false; }
    }

    private function isSeller(int $userId): bool
    {
        if ($userId <= 0) return false;
        try {
            return (bool)$this->db->get('seller_storefronts', ['id'], ['user_id' => $userId]);
        } catch (\Throwable $e) { return false; }
    }

    private function requirePostJson(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
            exit;
        }
    }

    private function jsonInput(): array
    {
        $raw = file_get_contents('php://input');
        return is_array($d = json_decode((string)$raw, true)) ? $d : [];
    }

    private function validateCsrfFromPayload(array $input): void
    {
        $token = (string)($input['csrf_token'] ?? '');
        if (!function_exists('validateCsrfToken') || !validateCsrfToken($token)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
            exit;
        }
    }
}
