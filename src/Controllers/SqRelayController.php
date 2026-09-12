<?php

namespace Ginto\Controllers;

use Ginto\Handlers\PayMongoHandler;

/**
 * The payments relay for sq.silverqueen.pro.
 *
 * PayMongo is configured against this domain and only this domain: the secret
 * key lives here, the webhook is registered here, and the merchant account
 * behind it is this company's. The wallet at sq.silverqueen.pro needs QR PH
 * payments for classroom tuition and teachers' subscriptions, and the honest
 * way to give it those is not to copy the key across — it is to let it ask.
 *
 * So there are two directions:
 *
 *   **Asking** — sq posts here for a QR to show somebody. This calls PayMongo
 *   and hands back the QR image and the intent id, and nothing else. The key
 *   never leaves this machine.
 *
 *   **Telling** — when PayMongo says a QR was paid, WebhookController relays
 *   the event on to sq, because sq is the only side that knows what the
 *   payment was *for*.
 *
 * ## How the two sides know each other
 *
 * A shared secret in SQ_RELAY_SECRET, and an HMAC over the exact body being
 * sent. Not an IP allowlist: this is behind a proxy, so the address in
 * REMOTE_ADDR belongs to the proxy rather than the caller, and an allowlist
 * built on it would either be trivially spoofed by a forwarded header or would
 * lock out the real caller. A signature over the body is checked against what
 * actually arrived and cannot be lied about.
 *
 * The timestamp is signed with the body and must be recent, so a request
 * captured off the wire cannot be replayed tomorrow.
 */
class SqRelayController
{
    /** How far out of step the two clocks may be. */
    private const SKEW_SECONDS = 300;

    /**
     * POST /relay/paymongo/qr
     *
     * Body: {"amount_php":500,"email":"...","name":"...","description":"..."}
     * Returns the QR to show, and the intent id sq should wait to hear about.
     */
    public function createQr()
    {
        header('Content-Type: application/json');

        $raw = file_get_contents('php://input') ?: '';

        if (!$this->authentic($raw)) {
            http_response_code(401);
            echo json_encode(['error' => 'unauthorised']);
            exit();
        }

        $in = json_decode($raw, true);

        if (!is_array($in)) {
            http_response_code(400);
            echo json_encode(['error' => 'bad_body']);
            exit();
        }

        // Pesos, whole. PayMongo works in centavos and initQrph multiplies, so
        // a fractional peso here would silently become a different amount.
        $amountPhp = (int) ($in['amount_php'] ?? 0);

        if ($amountPhp < 1) {
            http_response_code(422);
            echo json_encode(['error' => 'bad_amount']);
            exit();
        }

        if (!PayMongoHandler::isConfigured()) {
            http_response_code(503);
            echo json_encode(['error' => 'paymongo_not_configured']);
            exit();
        }

        try {
            $handler = new PayMongoHandler();

            $result = $handler->initQrph(
                $amountPhp,
                (string) ($in['email'] ?? 'payer@silverqueen.pro'),
                (string) ($in['name'] ?? 'SilverQueen'),
                (string) ($in['phone'] ?? ''),
                substr((string) ($in['description'] ?? 'SilverQueen'), 0, 120),
            );

            if (empty($result['success'])) {
                // PayMongo's own words go to this server's log, not across the
                // wire: sq shows this to a member, and a gateway's internals
                // are not something a member should be reading.
                error_log('sq relay: initQrph failed — ' . json_encode($result));

                http_response_code(502);
                echo json_encode(['error' => 'gateway_refused']);
                exit();
            }

            http_response_code(200);
            echo json_encode([
                'pi_id'     => $result['pi_id'],
                'qr_image'  => $result['qr_image'],
                'qr_string' => $result['qr_string'],
                'status'    => $result['status'],
            ]);
            exit();
        } catch (\Throwable $e) {
            error_log('sq relay: ' . $e->getMessage());

            http_response_code(502);
            echo json_encode(['error' => 'gateway_error']);
            exit();
        }
    }

    /**
     * What PayMongo says about one intent sq is still waiting on.
     *
     * The other half of createQr, and it exists because a webhook is not a
     * guarantee. sq settles a top-up when a `payment.paid` delivery arrives;
     * if one is never sent, arrives while sq is restarting, or is refused for a
     * signature it cannot check, the money is with PayMongo and the row is
     * `awaiting` for ever — somebody paid and nothing on the platform knows.
     *
     * There is a second reason, found on 2026-09-12. Our QR is a PaymentIntent
     * with a qrph payment method attached, not PayMongo's standalone QR
     * resource, so `qr.expired` is never about one of ours — those deliveries
     * come from the In-Store QR product on the same account. Nothing tells us
     * one of our codes lapsed. Asking is the only way to find out.
     *
     * Read-only, and it answers with a status and nothing else: no amounts, no
     * payer, no card. sq already knows what the payment was for and how much —
     * it minted the intent — and the one thing it cannot know is what the
     * gateway thinks now.
     */
    public function intentStatus()
    {
        header('Content-Type: application/json');

        $raw = file_get_contents('php://input') ?: '';

        if (!$this->authentic($raw)) {
            http_response_code(401);
            echo json_encode(['error' => 'unauthorised']);
            exit();
        }

        $in    = json_decode($raw, true);
        $piId  = is_array($in) ? trim((string) ($in['pi_id'] ?? '')) : '';

        // Shape-checked before it reaches the gateway. Anything else is either
        // a bug on sq's side or somebody probing, and neither is worth an
        // outbound request on our PayMongo key.
        if ($piId === '' || !preg_match('/^pi_[A-Za-z0-9]+$/', $piId)) {
            http_response_code(422);
            echo json_encode(['error' => 'bad_intent']);
            exit();
        }

        if (!PayMongoHandler::isConfigured()) {
            http_response_code(503);
            echo json_encode(['error' => 'paymongo_not_configured']);
            exit();
        }

        try {
            $result = (new PayMongoHandler())->getPaymentIntentStatus($piId);

            if (empty($result['success'])) {
                error_log('sq relay: intent status failed — ' . json_encode($result));

                http_response_code(502);
                echo json_encode(['error' => 'gateway_refused']);
                exit();
            }

            http_response_code(200);
            echo json_encode([
                'pi_id'      => $piId,
                'status'     => (string) $result['status'],
                'payment_id' => $result['payment_id'] ?? null,
                /*
                 * The code, when the intent still carries one.
                 *
                 * So sq can hand a member back the QR it already minted for
                 * them instead of a second one for the same money. This is the
                 * payer's own code for their own pending payment, and it is
                 * already on their screen — returning it discloses nothing they
                 * were not just shown.
                 */
                'qr_image'   => (string) ($result['qr_image'] ?? ''),
                'qr_string'  => (string) ($result['qr_string'] ?? ''),
            ]);
            exit();
        } catch (\Throwable $e) {
            error_log('sq relay: ' . $e->getMessage());

            http_response_code(502);
            echo json_encode(['error' => 'gateway_error']);
            exit();
        }
    }

    /**
     * Whether this request really came from sq, and recently.
     *
     * Signature is HMAC-SHA256 over "timestamp.body" so neither can be changed
     * without the other, and hash_equals compares in constant time — a plain
     * === leaks how much of a guess was right, one byte at a time.
     */
    private function authentic(string $raw): bool
    {
        $secret = (string) (getenv('SQ_RELAY_SECRET') ?: ($_ENV['SQ_RELAY_SECRET'] ?? ''));

        if ($secret === '') {
            error_log('sq relay: SQ_RELAY_SECRET is not set — refusing every call');

            return false;
        }

        $given = (string) ($_SERVER['HTTP_X_SQ_SIGNATURE'] ?? '');
        $when  = (string) ($_SERVER['HTTP_X_SQ_TIMESTAMP'] ?? '');

        if ($given === '' || $when === '' || !ctype_digit($when)) {
            return false;
        }

        if (abs(time() - (int) $when) > self::SKEW_SECONDS) {
            return false;
        }

        $expected = hash_hmac('sha256', $when . '.' . $raw, $secret);

        return hash_equals($expected, $given);
    }
}
