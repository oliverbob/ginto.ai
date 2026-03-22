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
    }

    // ── Specific notification events ──────────────────────────────────────────

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
            'new_order'          => '🛍️ New Order!',
            'order_confirmed'    => '✅ Order Confirmed',
            'visitor_registered' => '👤 New Buyer Registered',
            'shipment_pending'   => '📦 Shipment Queued',
            'shipment_ready_for_pickup' => '📦 Ready for Pickup',
            'shipment_picked_up' => '🛵 Order Picked Up',
            'shipment_in_transit'=> '🛵 On the Way',
            'shipment_out_for_delivery' => '📍 Out for Delivery',
            'shipment_delivered' => '✅ Delivered!',
            'shipment_failed_delivery' => '⚠️ Delivery Failed',
        ];
        return $map[$type] ?? '🔔 Ginto Mall';
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
