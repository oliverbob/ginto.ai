<?php

declare(strict_types=1);

namespace Ginto\Sites;

/**
 * Plans, paid monthly by QR Ph (PayMongo), and the Founding slots.
 *
 * A checkout is a pending subscription carrying its PaymentIntent. When the
 * intent is paid the subscription is active for one month from that moment,
 * the name is active, and the site is published. Founding: while fewer than
 * Config::foundingCap() founding subscriptions exist, a new one is founding and
 * keeps its price for as long as it never lapses ("the Founding price ends if
 * the subscription lapses").
 */
final class Subscriptions
{
    public const GRACE_DAYS = 3;

    public function __construct(private $db)
    {
    }

    public function current(int $userId): ?array
    {
        return $this->db->get('sites_subscriptions', '*', ['user_id' => $userId, 'status' => ['active', 'pending'], 'ORDER' => ['id' => 'DESC']]) ?: null;
    }

    public function foundingTaken(): int
    {
        return (int) $this->db->count('sites_subscriptions', ['founding' => 1, 'status' => ['active', 'lapsed', 'cancelled']]);
    }

    /**
     * Start paying for a plan: a QR Ph code for this month.
     * @return array{ok:bool, sub_id?:int, qr_image?:string, qr_string?:string, amount?:int, founding?:bool, error?:string}
     */
    public function checkout(int $userId, string $planId, string $email, string $displayName): array
    {
        $plan = Config::plan($planId);
        if ($plan === null) {
            return ['ok' => false, 'error' => 'Choose Silver, Gold or Crown.'];
        }
        $name = (new Names($this->db))->of($userId);
        if (!$name) {
            return ['ok' => false, 'error' => 'Claim your name first.'];
        }
        if (!\Ginto\Handlers\PayMongoHandler::isConfigured()) {
            return ['ok' => false, 'error' => 'QR Ph payments are not available right now.'];
        }

        // A renewal of an active founding subscription keeps its locked price; anything else pays today's price.
        $last = $this->db->get('sites_subscriptions', '*', ['user_id' => $userId, 'status' => ['active', 'lapsed', 'cancelled'], 'ORDER' => ['id' => 'DESC']]);
        $renewing = $last && $last['status'] === 'active';
        $founding = $renewing ? (bool) $last['founding'] : $this->foundingTaken() < Config::foundingCap();
        $price = $renewing && $last['plan'] === $planId && $last['founding'] ? (int) $last['price_php'] : (int) $plan['php'];

        // One pending checkout at a time: an older one is replaced.
        $this->db->update('sites_subscriptions', ['status' => 'cancelled'], ['user_id' => $userId, 'status' => 'pending']);

        $handler = new \Ginto\Handlers\PayMongoHandler();
        $r = $handler->initQrph($price, $email, $displayName ?: 'SilverQueen member', '', 'SilverQueen Sites ' . $plan['name'] . ' — ' . $name['name']);
        if (empty($r['success'])) {
            return ['ok' => false, 'error' => (string) ($r['message'] ?? 'The QR code could not be made. Try again.')];
        }

        $this->db->insert('sites_subscriptions', [
            'user_id' => $userId, 'name' => $name['name'], 'plan' => $planId, 'price_php' => $price,
            'founding' => $founding ? 1 : 0, 'status' => 'pending', 'payment_ref' => (string) $r['pi_id'],
        ]);

        return ['ok' => true, 'sub_id' => (int) $this->db->id(), 'qr_image' => (string) $r['qr_image'],
            'qr_string' => (string) ($r['qr_string'] ?? ''), 'amount' => $price, 'founding' => $founding];
    }

    /**
     * Has the pending checkout been paid? If so: active for a month, name active, site published.
     * @return array{status:string, published?:bool, url?:string, error?:string}
     */
    public function confirm(int $userId): array
    {
        $sub = $this->db->get('sites_subscriptions', '*', ['user_id' => $userId, 'status' => 'pending', 'ORDER' => ['id' => 'DESC']]);
        if (!$sub) {
            $active = $this->current($userId);

            return ['status' => $active && $active['status'] === 'active' ? 'active' : 'none'];
        }
        $st = (new \Ginto\Handlers\PayMongoHandler())->getPaymentIntentStatus((string) $sub['payment_ref']);
        if (empty($st['success']) || ($st['status'] ?? '') !== 'succeeded') {
            return ['status' => 'waiting'];
        }

        // Paid. A renewal extends from the end of the paid month; a new one starts now.
        $prev  = $this->db->get('sites_subscriptions', '*', ['user_id' => $userId, 'status' => 'active', 'ORDER' => ['id' => 'DESC']]);
        $start = $prev && strtotime((string) $prev['period_end']) > time() ? (string) $prev['period_end'] : date('Y-m-d H:i:s');
        if ($prev) {
            $this->db->update('sites_subscriptions', ['status' => 'cancelled'], ['id' => (int) $prev['id']]);
        }
        $this->db->update('sites_subscriptions', [
            'status' => 'active', 'period_start' => $start, 'period_end' => date('Y-m-d H:i:s', strtotime($start . ' +1 month')),
        ], ['id' => (int) $sub['id']]);
        $this->db->update('sites_names', ['status' => 'active'], ['user_id' => $userId]);
        self::log("PAID sub {$sub['id']} user {$userId} {$sub['plan']} PHP {$sub['price_php']}" . ($sub['founding'] ? ' founding' : ''));

        $pub = (new Publisher($this->db))->publish($userId);

        return ['status' => 'active', 'published' => $pub['ok'], 'url' => $pub['url'] ?? null, 'error' => $pub['error'] ?? null];
    }

    /** Daily: a month unpaid past its end plus the grace days lapses, and the site is taken down. */
    public function lapseDue(): array
    {
        $out = [];
        $due = $this->db->select('sites_subscriptions', '*', ['status' => 'active', 'period_end[<]' => date('Y-m-d H:i:s', time() - self::GRACE_DAYS * 86400)]) ?: [];
        foreach ($due as $s) {
            $this->db->update('sites_subscriptions', ['status' => 'lapsed'], ['id' => (int) $s['id']]);
            $this->db->update('sites_names', ['status' => 'lapsed'], ['user_id' => (int) $s['user_id']]);
            (new Publisher($this->db))->unpublish((string) $s['name']);
            self::log("LAPSED sub {$s['id']} {$s['name']}");
            $out[] = $s['name'];
        }
        // A QR nobody paid in a day is abandoned.
        $this->db->update('sites_subscriptions', ['status' => 'cancelled'], ['status' => 'pending', 'created_at[<]' => date('Y-m-d H:i:s', time() - 86400)]);

        return $out;
    }

    public static function log(string $line): void
    {
        @file_put_contents(STORAGE_PATH . '/logs/sites.log', date('c') . ' ' . $line . "\n", FILE_APPEND | LOCK_EX);
    }
}
