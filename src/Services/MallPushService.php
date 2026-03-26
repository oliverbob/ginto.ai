<?php
// src/Services/MallPushService.php
// Handles Web Push (VAPID) + DB notification fanout for mall events.
namespace Ginto\Services;

use Ginto\Core\Database;

class MallPushService
{
    private $db;

    // VAPID subject — must be mailto: or https:
    private string $vapidSubject;
    private string $vapidPublicKey;
    private string $vapidPrivateKey;

    public function __construct($db = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->vapidSubject    = getenv('VAPID_SUBJECT')     ?: 'mailto:admin@ginto.ai';
        $this->vapidPublicKey  = getenv('VAPID_PUBLIC_KEY')  ?: '';
        $this->vapidPrivateKey = getenv('VAPID_PRIVATE_KEY') ?: '';
    }

    // ── Subscription management ───────────────────────────────────────────────

    /**
     * Store or refresh a push subscription for a user.
     * Input: the JSON payload from navigator.serviceWorker.pushManager.subscribe().toJSON()
     */
    public function saveSubscription(?int $userId, array $sub, string $scope = 'mall', string $deviceHint = ''): bool
    {
        $endpoint = trim((string)($sub['endpoint'] ?? ''));
        $p256dh   = trim((string)($sub['keys']['p256dh'] ?? ''));
        $auth     = trim((string)($sub['keys']['auth']   ?? ''));

        if ($endpoint === '' || $p256dh === '' || $auth === '') return false;

        // Validate endpoint looks like a URL (no script injection etc.)
        if (!filter_var($endpoint, FILTER_VALIDATE_URL)) return false;

        try {
            $existing = $this->db->get('push_subscriptions', ['id'], ['endpoint[~]' => substr($endpoint, 0, 200)]);
            if ($existing) {
                $this->db->update('push_subscriptions', [
                    'user_id'    => $userId,
                    'p256dh_key' => $p256dh,
                    'auth_key'   => $auth,
                    'scope'      => $scope,
                    'device_hint'=> $deviceHint ?: null,
                    'updated_at' => date('Y-m-d H:i:s'),
                ], ['id' => (int)$existing['id']]);
            } else {
                $this->db->insert('push_subscriptions', [
                    'user_id'    => $userId,
                    'endpoint'   => $endpoint,
                    'p256dh_key' => $p256dh,
                    'auth_key'   => $auth,
                    'scope'      => $scope,
                    'device_hint'=> $deviceHint ?: null,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
            return true;
        } catch (\Throwable $e) {
            error_log('[MallPushService] saveSubscription error: ' . $e->getMessage());
            return false;
        }
    }

    public function removeSubscription(string $endpoint): void
    {
        try { $this->db->delete('push_subscriptions', ['endpoint[~]' => substr($endpoint, 0, 200)]); }
        catch (\Throwable $e) {}
    }

    /**
     * Upsert an FCM device token for a user.
     * Called by MallDeliveryController::apiRegisterFcm() when the Android app registers.
     */
    public function saveFcmToken(int $userId, string $token, string $deviceType = 'android'): bool
    {
        if ($token === '') return false;
        try {
            $now  = date('Y-m-d H:i:s');
            $stmt = $this->db->pdo()->prepare(
                "INSERT INTO device_fcm_tokens (user_id, fcm_token, device_type, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), device_type = VALUES(device_type), updated_at = VALUES(updated_at)"
            );
            $stmt->execute([$userId, $token, $deviceType, $now, $now]);
            return true;
        } catch (\Throwable $e) {
            error_log('[MallPushService] saveFcmToken error: ' . $e->getMessage());
            return false;
        }
    }

    // ── DB notification fanout ────────────────────────────────────────────────

    /**
     * Insert a notification record into the notifications table and also
     * trigger a Web Push to all subscriptions for those userIds.
     */
    public function notify(array $userIds, string $message, string $type = 'mall', array $meta = []): void
    {
        $userIds = array_unique(array_filter(array_map('intval', $userIds)));
        if (empty($userIds)) return;
        $now  = date('Y-m-d H:i:s');

        // DB notifications
        foreach ($userIds as $uid) {
            try {
                $this->db->insert('notifications', [
                    'user_id'    => $uid,
                    'type'       => $type,
                    'message'    => $message,
                    'meta'       => !empty($meta) ? json_encode($meta) : null,
                    'is_read'    => 0,
                    'created_at' => $now,
                ]);
            } catch (\Throwable $e) {}
        }

        // Web Push (best-effort)
        if ($this->vapidPublicKey !== '' && $this->vapidPrivateKey !== '') {
            $payload = json_encode([
                'title'   => $this->titleForType($type),
                'body'    => $message,
                'icon'    => '/assets/images/mall-favicon.svg',
                'badge'   => '/assets/images/mall-favicon.svg',
                'tag'     => $type . '-' . ($meta['order_id'] ?? $meta['shipment_id'] ?? 'general'),
                'data'    => array_merge($meta, ['url' => $meta['url'] ?? '/mall/orders']),
            ]);
            $subs = $this->getSubscriptionsForUsers($userIds);
            foreach ($subs as $sub) {
                $this->sendWebPush($sub, $payload);
            }
        }

        // FCM push — Android devices (best-effort), personalized per recipient
        foreach ($userIds as $uid) {
            $tokens = $this->getFcmTokensForUsers([$uid]);
            if (empty($tokens)) continue;

            $notifData = array_merge($meta, [
                'url'         => $meta['url'] ?? '/mall/orders',
                'type'        => $type,
                'event_key'   => $meta['event_key'] ?? $type,
                'notif_count' => (string)$this->countUnreadForUser((int)$uid),
            ]);

            $this->sendFcmNotifications(
                $tokens,
                $this->titleForType($type),
                $message,
                $notifData
            );
        }
    }

    // ── Specific notification events ──────────────────────────────────────────

    /** Notify seller when their product listing goes live / is successfully created. */
    public function notifyProductListed(int $sellerId, string $productTitle): void
    {
        $this->notify(
            [$sellerId],
            "Product saved: \"{$productTitle}\". Complete details and publish when ready.",
            'product_listed',
            [
                'url' => '/marketplace/sellers/products',
                'event_key' => 'product_listed',
                'product_title' => $productTitle,
            ]
        );
    }

    /** Notify seller when a product is explicitly published live. */
    public function notifyProductPublished(int $sellerId, string $productTitle, int $productId = 0): void
    {
        $meta = [
            'url' => '/marketplace/sellers/products',
            'event_key' => 'product_published',
            'product_title' => $productTitle,
        ];
        if ($productId > 0) $meta['product_id'] = $productId;

        $this->notify(
            [$sellerId],
            "Live now: \"{$productTitle}\" is published and visible to buyers.",
            'product_published',
            $meta
        );
    }

    /** Notify seller when a product is moved back to draft / paused visibility. */
    public function notifyProductUnpublished(int $sellerId, string $productTitle, int $productId = 0): void
    {
        $meta = [
            'url' => '/marketplace/sellers/products',
            'event_key' => 'product_unpublished',
            'product_title' => $productTitle,
        ];
        if ($productId > 0) $meta['product_id'] = $productId;

        $this->notify(
            [$sellerId],
            "Visibility paused: \"{$productTitle}\" is now in draft mode.",
            'product_unpublished',
            $meta
        );
    }

    /**
     * Send a silent FCM data message to all the user's OTHER devices when cart count changes.
     * No visible notification is shown — the device simply updates the cart badge.
     */
    public function sendSilentCartUpdate(int $userId, int $count): void
    {
        $tokens = $this->getFcmTokensForUsers([$userId]);
        if (empty($tokens)) return;

        $saSource  = getenv('FCM_SERVICE_ACCOUNT_JSON') ?: '';
        $projectId = getenv('FCM_PROJECT_ID') ?: '';
        if ($saSource === '' || $projectId === '') return;

        $saJson = file_exists($saSource) ? file_get_contents($saSource) : $saSource;
        $sa     = json_decode($saJson, true);
        if (!$sa || empty($sa['private_key'])) return;

        $accessToken = $this->getFcmAccessToken($sa);
        if (!$accessToken) return;

        $endpoint = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        foreach ($tokens as $token) {
            $payload = json_encode([
                'message' => [
                    'token' => $token,
                    // Data-only message (no notification block) → silent, no banner/sound
                    'data'  => ['type' => 'cart_update', 'count' => (string)$count],
                    'android' => ['priority' => 'normal'],
                ],
            ]);
            try {
                $conn = curl_init($endpoint);
                curl_setopt_array($conn, [
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => $payload,
                    CURLOPT_HTTPHEADER     => [
                        'Authorization: Bearer ' . $accessToken,
                        'Content-Type: application/json',
                    ],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 5,
                ]);
                curl_exec($conn);
                curl_close($conn);
            } catch (\Throwable $e) {}
        }
    }

    /** Notify seller when a new order is placed on their product. */
    public function notifyNewOrder(int $sellerId, array $order): void
    {
        $productTitle = (string)($order['product_title'] ?? 'your product');
        $orderId      = (int)($order['id'] ?? 0);
        $this->notify(
            [$sellerId],
            "New order placed for \"{$productTitle}\"" . ($orderId > 0 ? " (Order #{$orderId})" : ''),
            'new_order',
            ['order_id' => $orderId, 'url' => '/mall/seller-orders']
        );
    }

    /** Notify buyer when order is confirmed/paid. */
    public function notifyOrderConfirmed(int $buyerId, array $order): void
    {
        $orderId = (int)($order['id'] ?? 0);
        $this->notify(
            [$buyerId],
            "Your order #{$orderId} has been confirmed! We're preparing your items.",
            'order_confirmed',
            ['order_id' => $orderId, 'url' => '/mall/orders']
        );
    }

    /** Notify seller, buyer, and rider when shipment status changes. */
    public function notifyShipmentStatus(
        int $shipmentId,
        string $status,
        int $buyerId,
        int $sellerId,
        ?int $riderId,
        string $trackingToken,
        array $extraMeta = []
    ): void {
        $msg       = $this->shipmentStatusMessage($status);
        $trackUrl  = '/mall/delivery/track/' . $trackingToken;
        $meta      = array_merge($extraMeta, ['shipment_id' => $shipmentId, 'status' => $status, 'url' => $trackUrl]);

        $recipients = [$buyerId, $sellerId];
        if ($riderId) $recipients[] = $riderId;
        $recipients = array_unique($recipients);

        $this->notify($recipients, $msg, 'shipment_' . $status, $meta);
    }

    /** Notify seller when visitor registers after checking out (not paid yet). */
    public function notifySellerVisitorRegistered(int $sellerId, string $visitorName, ?int $pendingOrderId = null): void
    {
        $msg  = "New buyer \"$visitorName\" just registered via your storefront!";
        $meta = ['url' => '/mall/seller-orders'];
        if ($pendingOrderId) $meta['order_id'] = $pendingOrderId;
        $this->notify([$sellerId], $msg, 'visitor_registered', $meta);
    }

    /**
     * Notify seller(s) about a successful purchase. Each seller gets notified only about
     * their own products from the checkout.
     */
    public function notifySellerPurchase(array $ordersBySellerIds, array $orderDetails): void
    {
        foreach ($ordersBySellerIds as $sellerId => $items) {
            $productNames = array_map(fn($i) => $i['title_snapshot'] ?? $i['title'] ?? 'Product', $items);
            $itemCount = count($productNames);
            $summaryNames = $itemCount > 2
                ? implode(', ', array_slice($productNames, 0, 2)) . " +{$itemCount}" . " more"
                : implode(', ', $productNames);
            $orderId = (int)($orderDetails['order_id'] ?? $items[0]['order_id'] ?? 0);
            $buyerName = htmlspecialchars(strip_tags((string)($orderDetails['buyer_name'] ?? 'A buyer')), ENT_QUOTES, 'UTF-8');

            $this->notify(
                [(int)$sellerId],
                "{$buyerName} purchased: {$summaryNames}" . ($orderId > 0 ? " (Order #{$orderId})" : ''),
                'purchase_completed',
                [
                    'order_id' => $orderId,
                    'url' => '/marketplace/sellers/orders',
                    'event_key' => 'purchase_completed',
                    'buyer_name' => $buyerName,
                    'product_titles' => $productNames,
                    'item_count' => $itemCount,
                ]
            );
        }
    }

    /** Notify buyer about successful purchase confirmation. */
    public function notifyBuyerPurchaseSuccess(int $buyerId, int $orderId, string $totalAmount): void
    {
        $this->notify(
            [$buyerId],
            "Purchase successful! Order #{$orderId} (₱{$totalAmount}) is being processed. Track your delivery in the Delivery section.",
            'purchase_completed',
            ['order_id' => $orderId, 'url' => '/mall/orders', 'event_key' => 'purchase_completed']
        );
    }

    /** Notify admin about purchase activity for ledgering. */
    public function notifyAdminPurchase(int $orderId, int $buyerId, string $buyerName, float $totalAmount, int $itemCount): void
    {
        $adminIds = $this->getAdminUserIds();
        if (empty($adminIds)) return;
        $this->notify(
            $adminIds,
            "[ADMIN] Order #{$orderId}: {$buyerName} purchased {$itemCount} item(s) for ₱" . number_format($totalAmount, 2),
            'admin_purchase',
            [
                'order_id' => $orderId, 'buyer_id' => $buyerId,
                'url' => '/admin/mall/orders',
                'event_key' => 'admin_purchase',
            ]
        );
    }

    /** Notify admin about delivery status changes for monitoring. */
    public function notifyAdminDeliveryStatus(int $orderId, string $status, string $details): void
    {
        $adminIds = $this->getAdminUserIds();
        if (empty($adminIds)) return;
        $this->notify(
            $adminIds,
            "[ADMIN] Order #{$orderId} delivery: {$status} — {$details}",
            'admin_delivery',
            ['order_id' => $orderId, 'url' => '/mall/delivery/admin', 'event_key' => 'admin_delivery']
        );
    }

    /** Notify seller that payment will be deposited after delivery. */
    public function notifySellerPaymentPending(int $sellerId, int $orderId, float $netAmount): void
    {
        $this->notify(
            [$sellerId],
            "Order #{$orderId} delivered! ₱" . number_format($netAmount, 2) . " will be deposited to your account within 7-12 business days.",
            'payment_pending_deposit',
            [
                'order_id' => $orderId,
                'url' => '/wallet/earnings',
                'event_key' => 'payment_pending_deposit',
                'net_amount' => $netAmount,
            ]
        );
    }

    /** Get admin user IDs (role_id 1 or 2). */
    private function getAdminUserIds(): array
    {
        try {
            $admins = $this->db->select('users', ['id'], ['role_id' => [1, 2], 'LIMIT' => 20]);
            return array_map(fn($a) => (int)$a['id'], $admins ?: []);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Push realtime update to a user's active FCM devices without creating new DB rows.
     * Intended for notifications that are already persisted by another service layer.
     */
    public function pushRealtimeNotification(int $userId, string $type, string $message, array $meta = []): void
    {
        if ($userId <= 0) return;

        $tokens = $this->getFcmTokensForUsers([$userId]);
        if (empty($tokens)) return;

        $defaultUrl = '/mall/notifications';
        if (!empty($meta['product_link'])) {
            $defaultUrl = (string)$meta['product_link'];
        } elseif (!empty($meta['buyer_link'])) {
            $defaultUrl = (string)$meta['buyer_link'];
        }

        $payload = array_merge($meta, [
            'type'        => $type,
            'event_key'   => $meta['event_key'] ?? $type,
            'url'         => $meta['url'] ?? $meta['link'] ?? $defaultUrl,
            'notif_count' => (string)$this->countUnreadForUser($userId),
        ]);

        $this->sendFcmNotifications(
            $tokens,
            $this->titleForType($type),
            $message,
            $payload
        );
    }

    // ── FCM (Android) Push ────────────────────────────────────────────────────

    private function getFcmTokensForUsers(array $userIds): array
    {
        try {
            $placeholders = implode(',', array_fill(0, count($userIds), '?'));
            $stmt = $this->db->pdo()->prepare(
                "SELECT fcm_token FROM device_fcm_tokens WHERE user_id IN ($placeholders)"
            );
            $stmt->execute($userIds);
            return array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'fcm_token');
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Send a notification to a list of FCM device tokens via the FCM HTTP v1 API.
     *
     * Required env vars:
     *   FCM_SERVICE_ACCOUNT_JSON — path to service-account JSON file, OR the raw JSON string
     *   FCM_PROJECT_ID           — Firebase project ID
     */
    private function sendFcmNotifications(array $tokens, string $title, string $body, array $data): void
    {
        $saSource  = getenv('FCM_SERVICE_ACCOUNT_JSON') ?: '';
        $projectId = getenv('FCM_PROJECT_ID') ?: '';

        if ($saSource === '' || $projectId === '' || empty($tokens)) return;

        $saJson = file_exists($saSource) ? file_get_contents($saSource) : $saSource;
        $sa     = json_decode($saJson, true);

        if (!$sa || empty($sa['private_key']) || empty($sa['client_email'])) {
            error_log('[MallPushService] FCM: invalid service account credential');
            return;
        }

        $accessToken = $this->getFcmAccessToken($sa);
        if (!$accessToken) return;

        $endpoint = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
        $strData  = array_map('strval', $data);

        foreach ($tokens as $token) {
            $payload = json_encode([
                'message' => [
                    'token'        => $token,
                    'notification' => ['title' => $title, 'body' => $body],
                    'data'         => $strData,
                    'android'      => [
                        'priority'     => 'high',
                        'notification' => [
                            'channel_id'    => 'ginto_mall',
                            'default_sound' => true,
                        ],
                    ],
                ],
            ]);

            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $accessToken,
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code !== 200) {
                error_log('[MallPushService] FCM send error HTTP ' . $code . ': ' . $resp);
                // 404/410 → stale token; silently discard for now
            }
        }
    }

    /**
     * Exchange a service-account private key for a short-lived OAuth2 Bearer token
     * using the Google JWT grant flow.
     */
    private function getFcmAccessToken(array $sa): ?string
    {
        try {
            $now    = time();
            $header = $this->b64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claims = $this->b64url(json_encode([
                'iss'   => $sa['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud'   => 'https://oauth2.googleapis.com/token',
                'iat'   => $now,
                'exp'   => $now + 3600,
            ]));

            $signing = $header . '.' . $claims;
            openssl_sign($signing, $sig, $sa['private_key'], 'SHA256');
            $jwt = $signing . '.' . $this->b64url($sig);

            $ch = curl_init('https://oauth2.googleapis.com/token');
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => http_build_query([
                    'grant_type' => 'urn:ietf:params:oauth2:grant-type:jwt-bearer',
                    'assertion'  => $jwt,
                ]),
                CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $resp = json_decode(curl_exec($ch), true);
            curl_close($ch);

            return $resp['access_token'] ?? null;
        } catch (\Throwable $e) {
            error_log('[MallPushService] FCM auth error: ' . $e->getMessage());
            return null;
        }
    }

    // ── VAPID Web Push ────────────────────────────────────────────────────────

    private function getSubscriptionsForUsers(array $userIds): array
    {
        try {
            $placeholders = implode(',', array_fill(0, count($userIds), '?'));
            $stmt = $this->db->pdo()->prepare(
                "SELECT endpoint, p256dh_key, auth_key FROM push_subscriptions WHERE user_id IN ($placeholders) AND scope='mall'"
            );
            $stmt->execute($userIds);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function sendWebPush(array $sub, string $payload): void
    {
        try {
            $endpoint = $sub['endpoint'];
            $p256dh   = $sub['p256dh_key'];
            $auth     = $sub['auth_key'];

            // Build VAPID JWT
            $jwt = $this->buildVapidJwt($endpoint);

            // Encrypt payload (ECDH-AES128GCM)
            $encrypted = $this->encryptPayload($payload, $p256dh, $auth);
            if (!$encrypted) return;

            $headers = [
                'Content-Type: application/octet-stream',
                'Content-Encoding: aes128gcm',
                'Authorization: vapid t=' . $jwt . ',k=' . $this->vapidPublicKey,
                'TTL: 86400',
                'Content-Length: ' . strlen($encrypted['ciphertext']),
            ];

            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $encrypted['ciphertext'],
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code === 410 || $code === 404) {
                // Subscription gone — clean up
                $this->removeSubscription($endpoint);
            }
        } catch (\Throwable $e) {
            error_log('[MallPushService] sendWebPush error: ' . $e->getMessage());
        }
    }

    /**
     * Build a VAPID JWT for the given subscription endpoint.
     */
    private function buildVapidJwt(string $endpoint): string
    {
        $parts    = parse_url($endpoint);
        $audience = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');

        $header  = $this->b64url(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $payload = $this->b64url(json_encode([
            'aud' => $audience,
            'exp' => time() + 3600,
            'sub' => $this->vapidSubject,
        ]));

        $sigInput = $header . '.' . $payload;

        // Sign with ECDSA P-256
        $pem     = $this->vapidPrivateKeyToPem($this->vapidPrivateKey);
        $privKey = openssl_pkey_get_private($pem);
        openssl_sign($sigInput, $der, $privKey, 'SHA256');

        // Convert DER to raw R||S
        $sig = $this->derToRawSignature($der);
        return $sigInput . '.' . $this->b64url($sig);
    }

    /**
     * Encrypt the push payload using aes128gcm content encoding (RFC 8188).
     * Returns ['ciphertext' => binary string] or null on failure.
     */
    private function encryptPayload(string $plaintext, string $receiverPublicKeyBase64, string $authBase64): ?array
    {
        try {
            $receiverPublicKey = base64_decode(strtr($receiverPublicKeyBase64, '-_', '+/'));
            $authSecret        = base64_decode(strtr($authBase64, '-_', '+/'));

            // Generate sender ephemeral EC P-256 key pair
            $senderKey = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
            $senderDetails = openssl_pkey_get_details($senderKey);

            // Build uncompressed sender public key (04 || x || y)
            $x = $senderDetails['ec']['x'];
            $y = $senderDetails['ec']['y'];
            // Pad to 32 bytes each
            $x = str_pad($x, 32, "\x00", STR_PAD_LEFT);
            $y = str_pad($y, 32, "\x00", STR_PAD_LEFT);
            $senderPublicKey = "\x04" . $x . $y;

            // ECDH shared secret
            $receiverKey = openssl_pkey_get_public([
                'curve_name' => 'prime256v1',
                'ecdh_curve' => 'prime256v1',
            ]);
            // Reconstruct the receiver public key from raw bytes
            $receiverKey = $this->rawPublicKeyToOpenSSL($receiverPublicKey);
            if (!$receiverKey) return null;

            openssl_pkey_export($senderKey, $privPem);
            $sharedSecret = $this->ecdhSharedSecret($privPem, $receiverPublicKey);
            if (!$sharedSecret) return null;

            // Salt (16 random bytes)
            $salt = random_bytes(16);

            // Build pseudorandom key (PRK) for HKDF
            // ikm = HKDF-Extract(auth, ecdh-secret)
            $ikm   = $this->hkdf($authSecret, $sharedSecret, "WebPush: info\x00" . $receiverPublicKey . $senderPublicKey, 32);
            // content encryption key and nonce
            $cek   = $this->hkdf($salt, $ikm, "Content-Encoding: aes128gcm\x00", 16);
            $nonce = $this->hkdf($salt, $ikm, "Content-Encoding: nonce\x00", 12);

            // Pad record with 0x02 delimiter
            $record = $plaintext . "\x02";

            // AES-128-GCM encrypt
            $ciphertext = openssl_encrypt($record, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
            if ($ciphertext === false) return null;

            // Build content encoding header (salt + record size + key len + sender key)
            $rs     = strlen($record) + 16 + 1; // record size
            $header = $salt . pack('N', $rs) . chr(65) . $senderPublicKey;

            return ['ciphertext' => $header . $ciphertext . $tag];
        } catch (\Throwable $e) {
            error_log('[MallPushService] encryptPayload error: ' . $e->getMessage());
            return null;
        }
    }

    private function ecdhSharedSecret(string $privateKeyPem, string $receiverRawPublicKey): ?string
    {
        try {
            // Reconstruct DER-encoded SubjectPublicKeyInfo for the receiver's raw EC key
            $receiverPubKeyObj = $this->rawPublicKeyToOpenSSL($receiverRawPublicKey);
            if (!$receiverPubKeyObj) return null;

            // Use OpenSSL ECDH
            $privKey = openssl_pkey_get_private($privateKeyPem);
            // Get the raw shared secret via a manual DH computation
            // We'll use openssl_dh_compute_key compatible approach via pkey details
            $privDetails = openssl_pkey_get_details($privKey);
            $d = $privDetails['ec']['d'];

            // Receiver key point
            if (strlen($receiverRawPublicKey) !== 65 || $receiverRawPublicKey[0] !== "\x04") return null;
            $pubX = substr($receiverRawPublicKey, 1, 32);
            $pubY = substr($receiverRawPublicKey, 33, 32);

            // Compute shared secret via EC scalar multiplication using GMP
            // P-256 parameters
            $p  = gmp_init('FFFFFFFF00000001000000000000000000000000FFFFFFFFFFFFFFFFFFFFFFFF', 16);
            $a  = gmp_init('FFFFFFFF00000001000000000000000000000000FFFFFFFFFFFFFFFFFFFFFFFC', 16);
            $Gx = gmp_init('6B17D1F2E12C4247F8BCE6E563A440F277037D812DEB33A0F4A13945D898C296', 16);
            $Gy = gmp_init('4FE342E2FE1A7F9B8EE7EB4A7C0F9E162BCE33576B315ECECBB6406837BF51F5', 16);
            $n  = gmp_init('FFFFFFFF00000000FFFFFFFFFFFFFFFFBCE6FAADA7179E84F3B9CAC2FC632551', 16);

            $d_gmp = gmp_import($d);
            $Px    = gmp_import($pubX);
            $Py    = gmp_import($pubY);

            // Scalar multiplication
            $shared = $this->ecPointMul($d_gmp, [$Px, $Py], $p, $a);
            if (!$shared) return null;

            $sharedX = $shared[0];
            $rawX    = str_pad(gmp_export($sharedX), 32, "\x00", STR_PAD_LEFT);
            return $rawX;
        } catch (\Throwable $e) {
            error_log('[MallPushService] ECDH error: ' . $e->getMessage());
            return null;
        }
    }

    // EC point addition (affine coordinates, P-256)
    private function ecPointAdd($P, $Q, $p, $a): ?array
    {
        if ($P === null) return $Q;
        if ($Q === null) return $P;
        [$Px, $Py] = $P;
        [$Qx, $Qy] = $Q;

        if (gmp_cmp($Px, $Qx) === 0) {
            if (gmp_cmp($Py, $Qy) !== 0) return null; // point at infinity
            // Point doubling
            $m = gmp_mod(
                gmp_mul(
                    gmp_add(gmp_mul(3, gmp_powm($Px, 2, $p)), $a),
                    gmp_invert(gmp_mul(2, $Py), $p)
                ),
                $p
            );
        } else {
            $m = gmp_mod(
                gmp_mul(
                    gmp_sub($Qy, $Py),
                    gmp_invert(gmp_sub($Qx, $Px), $p)
                ),
                $p
            );
        }
        $Rx = gmp_mod(gmp_sub(gmp_sub(gmp_powm($m, 2, $p), $Px), $Qx), $p);
        $Ry = gmp_mod(gmp_sub(gmp_mul($m, gmp_sub($Px, $Rx)), $Py), $p);
        return [gmp_mod($Rx, $p), gmp_mod($Ry, $p)];
    }

    private function ecPointMul($k, $P, $p, $a): ?array
    {
        $result = null;
        $addend = $P;
        while (gmp_cmp($k, 0) > 0) {
            if (gmp_testbit($k, 0)) {
                $result = $this->ecPointAdd($result, $addend, $p, $a);
            }
            $addend = $this->ecPointAdd($addend, $addend, $p, $a);
            $k = gmp_div_q($k, 2);
        }
        return $result;
    }

    private function hkdf(string $salt, string $ikm, string $info, int $length): string
    {
        $prk  = hash_hmac('sha256', $ikm, $salt, true);
        $t    = '';
        $okm  = '';
        for ($i = 1; strlen($okm) < $length; $i++) {
            $t   = hash_hmac('sha256', $t . $info . chr($i), $prk, true);
            $okm .= $t;
        }
        return substr($okm, 0, $length);
    }

    private function rawPublicKeyToOpenSSL(string $rawKey): mixed
    {
        // DER-encoded SubjectPublicKeyInfo for P-256 uncompressed point
        $derPrefix = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200');
        $der       = $derPrefix . $rawKey;
        $pem       = "-----BEGIN PUBLIC KEY-----\n"
                   . chunk_split(base64_encode($der), 64, "\n")
                   . "-----END PUBLIC KEY-----\n";
        return openssl_pkey_get_public($pem) ?: null;
    }

    private function vapidPrivateKeyToPem(string $base64urlKey): string
    {
        $raw       = base64_decode(strtr($base64urlKey, '-_', '+/'));
        // DER for SEC1 ECPrivateKey wrapped in PKCS#8
        $derPrefix = hex2bin('308187020100301306072a8648ce3d020106082a8648ce3d030107046d306b0201010420');
        $derSuffix = hex2bin('a144034200');
        // For PKCS#8 format without public key embedding, use simpler approach
        $ecDer  = hex2bin('3077020101') . "\x04\x20" . $raw
                . hex2bin('a00a06082a8648ce3d030107');
        $pem    = "-----BEGIN EC PRIVATE KEY-----\n"
                . chunk_split(base64_encode($ecDer), 64, "\n")
                . "-----END EC PRIVATE KEY-----\n";
        return $pem;
    }

    private function derToRawSignature(string $der): string
    {
        // Parse DER SEQUENCE { INTEGER r, INTEGER s }
        $offset = 2; // skip SEQUENCE + length
        $offset += 1; // skip INTEGER tag
        $rLen    = ord($der[$offset++]);
        if ($rLen > 32) { $offset++; $rLen--; } // leading 0x00 byte
        $r = substr($der, $offset, $rLen);
        $offset += $rLen;
        $offset += 1; // skip INTEGER tag
        $sLen    = ord($der[$offset++]);
        if ($sLen > 32) { $offset++; $sLen--; }
        $s = substr($der, $offset, $sLen);

        return str_pad($r, 32, "\x00", STR_PAD_LEFT) . str_pad($s, 32, "\x00", STR_PAD_LEFT);
    }

    private function b64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function titleForType(string $type): string
    {
        $map = [
            'product_listed'      => '🧾 Product Saved',
            'product_published'   => '🚀 Product Live',
            'product_unpublished' => '⏸️ Product Paused',
            'mall_seller_impression' => '👀 Buyer Activity',
            'new_order'          => '🛍️ New Order!',
            'order_confirmed'    => '✅ Order Confirmed',
            'purchase_completed' => '🛒 Purchase Completed!',
            'payment_pending_deposit' => '💰 Payment Incoming',
            'admin_purchase'     => '📊 [Admin] New Purchase',
            'admin_delivery'     => '📊 [Admin] Delivery Update',
            'visitor_registered' => '👤 New Buyer Registered',
            'shipment_pending'   => '📦 Shipment Queued',
            'shipment_ready_for_pickup' => '📦 Ready for Pickup',
            'shipment_picked_up' => '🛵 Order Picked Up',
            'shipment_in_transit'=> '🛵 On the Way',
            'shipment_out_for_delivery' => '📍 Out for Delivery',
            'shipment_delivered' => '✅ Delivered!',
            'shipment_failed_delivery' => '⚠️ Delivery Failed',
            'delivery_proof'     => '📸 Delivery Proof Uploaded',
            'product_rating'     => '⭐ New Product Review',
            'seller_shipping'    => '🚚 Seller Shipped Order',
        ];
        return $map[$type] ?? '🔔 Ginto Mall';
    }

    private function countUnreadForUser(int $userId): int
    {
        try {
            return (int)$this->db->count('notifications', [
                'user_id' => $userId,
                'is_read' => 0,
            ]);
        } catch (
            \Throwable $e
        ) {
            return 0;
        }
    }

    private function shipmentStatusMessage(string $status): string
    {
        $map = [
            'pending'           => 'Your order has been placed and is awaiting pickup.',
            'ready_for_pickup'  => 'Your order is ready for pickup by the rider.',
            'picked_up'         => 'Your order has been picked up by the rider.',
            'in_transit'        => 'Your order is on the way to you!',
            'out_for_delivery'  => 'Your order is out for delivery. Almost there!',
            'delivered'         => 'Your order has been delivered. Enjoy!',
            'failed_delivery'   => 'Delivery attempt failed. Rider will retry.',
            'returned'          => 'Your order has been returned to the seller.',
        ];
        return $map[$status] ?? 'Your shipment status has been updated.';
    }
}
