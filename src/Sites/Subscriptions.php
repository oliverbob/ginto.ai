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

    /**
     * A complimentary member's plan (Config::COMPLIMENTARY): once they hold a
     * name, an active ₱0 subscription with no end in practice, the name active
     * and the site published. Never a Founding slot. Safe to call often.
     */
    public function complimentary(int $userId): ?array
    {
        $identity = Members::identity($userId);
        $plan = $identity !== null ? (Config::COMPLIMENTARY[$identity] ?? null) : null;
        if ($plan === null || Config::testPrice($identity) !== null) {
            return null;
        }
        $cur = $this->current($userId);
        if ($cur && $cur['status'] === 'active' && $cur['plan'] === $plan && (int) $cur['price_php'] === 0) {
            return $cur;
        }
        $name = (new Names($this->db))->of($userId);
        if (!$name || !in_array($name['status'], ['held', 'active'], true)) {
            return null;
        }
        $this->db->update('sites_subscriptions', ['status' => 'cancelled'], ['user_id' => $userId, 'status' => ['pending', 'active']]);
        $this->db->insert('sites_subscriptions', [
            'user_id' => $userId, 'name' => $name['name'], 'plan' => $plan, 'price_php' => 0, 'founding' => 0,
            'status' => 'active', 'payment_ref' => 'complimentary', 'period_start' => date('Y-m-d H:i:s'),
            'period_end' => date('Y-m-d H:i:s', strtotime('+100 years')),
        ]);
        $this->db->update('sites_names', ['status' => 'active', 'plan_choice' => $plan], ['user_id' => $userId]);
        self::log("COMPLIMENTARY {$plan} user {$userId} {$name['name']}");
        if ($this->db->get('sites_previews', ['id'], ['id' => (int) $name['preview_id'], 'status' => 'ready'])) {
            (new Publisher($this->db))->publish($userId);
        }

        return $this->current($userId);
    }

    public static function isComplimentary(?array $sub): bool
    {
        return $sub !== null && ($sub['payment_ref'] ?? '') === 'complimentary';
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
        $test = Config::testPrice(Members::identity($userId));
        if ($test === null && $this->complimentary($userId)) {
            return ['ok' => true, 'paid' => true, 'qr_image' => '', 'amount' => 0, 'founding' => false];
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
        if ($test !== null) {
            // The owner's live test: the real road at a small amount, never a Founding slot.
            [$price, $founding] = [$test, false];
        }

        $handler = new \Ginto\Handlers\PayMongoHandler();

        // Pressed again for the same plan: the same code, never a second one. A member
        // may be scanning the first; a new code would leave that payment unclaimed.
        $open = $this->db->get('sites_subscriptions', '*', ['user_id' => $userId, 'status' => 'pending', 'plan' => $planId,
            'price_php' => $price, 'created_at[>]' => date('Y-m-d H:i:s', time() - 1800), 'ORDER' => ['id' => 'DESC']]);
        if ($open) {
            $st = $handler->getPaymentIntentStatus((string) $open['payment_ref']);
            if (!empty($st['success']) && ($st['status'] ?? '') === 'succeeded') {
                return ['ok' => true, 'sub_id' => (int) $open['id'], 'paid' => true, 'qr_image' => '', 'amount' => $price, 'founding' => (bool) $open['founding']];
            }
            $exp = strtotime((string) ($st['qr_expires_at'] ?? '')) ?: time() + 1800;
            if (!empty($st['success']) && !empty($st['qr_image']) && $exp > time() + 120) {
                return ['ok' => true, 'sub_id' => (int) $open['id'], 'qr_image' => (string) $st['qr_image'],
                    'qr_string' => (string) ($st['qr_string'] ?? ''), 'amount' => $price, 'founding' => (bool) $open['founding'],
                    'expires_at' => (string) ($st['qr_expires_at'] ?? '')];
            }
        }

        // One pending checkout at a time: an older one is replaced (and still honoured if it gets paid; see confirm()).
        $this->db->update('sites_subscriptions', ['status' => 'cancelled'], ['user_id' => $userId, 'status' => 'pending']);
        $r = $handler->initQrph($price, $email, $displayName ?: 'SilverQueen member', '', 'SilverQueen Premium ' . $plan['name'] . ' — ' . $name['name']);
        if (empty($r['success'])) {
            return ['ok' => false, 'error' => (string) ($r['message'] ?? 'The QR code could not be made. Try again.')];
        }

        $this->db->insert('sites_subscriptions', [
            'user_id' => $userId, 'name' => $name['name'], 'plan' => $planId, 'price_php' => $price,
            'founding' => $founding ? 1 : 0, 'status' => 'pending', 'payment_ref' => (string) $r['pi_id'],
        ]);

        return ['ok' => true, 'sub_id' => (int) $this->db->id(), 'qr_image' => (string) $r['qr_image'],
            'qr_string' => (string) ($r['qr_string'] ?? ''), 'amount' => $price, 'founding' => $founding,
            'expires_at' => (string) ($r['qr_expires_at'] ?? '')];
    }

    /**
     * Has the pending checkout been paid? If so: active for a month, name active, site published.
     * @return array{status:string, published?:bool, url?:string, error?:string}
     */
    public function confirm(int $userId): array
    {
        // The open checkout, and any replaced in the last day: whichever was actually paid counts,
        // so a member who scanned an older code is never left without what they paid for.
        $candidates = $this->db->select('sites_subscriptions', '*', ['user_id' => $userId, 'status' => ['pending', 'cancelled'],
            'period_start' => null, 'created_at[>]' => date('Y-m-d H:i:s', time() - 86400), 'ORDER' => ['id' => 'DESC']]) ?: [];
        $handler = new \Ginto\Handlers\PayMongoHandler();
        $sub = null;
        $waiting = false;
        $open = null; // the newest pending code: its expiry, and whether it can still be scanned
        foreach ($candidates as $c) {
            if (empty($c['payment_ref'])) {
                continue;
            }
            $st = $handler->getPaymentIntentStatus((string) $c['payment_ref']);
            if (!empty($st['success']) && ($st['status'] ?? '') === 'succeeded') {
                $sub = $c;
                break;
            }
            $waiting = $waiting || $c['status'] === 'pending';
            if ($open === null && $c['status'] === 'pending') {
                $open = ['sub_id' => (int) $c['id'], 'expires_at' => (string) ($st['qr_expires_at'] ?? ''),
                    'expired' => !empty($st['success']) && (empty($st['qr_image'])
                        || (($t = strtotime((string) ($st['qr_expires_at'] ?? ''))) && $t < time()))];
            }
        }
        if (!$sub) {
            $active = $this->current($userId);

            $status = $active && $active['status'] === 'active' ? 'active' : ($waiting ? 'waiting' : 'none');

            return ['status' => $status] + ($status === 'waiting' && $open ? $open : []);
        }
        // A paid code that was replaced: the replacement is moot.
        $this->db->update('sites_subscriptions', ['status' => 'cancelled'], ['user_id' => $userId, 'status' => 'pending', 'id[!]' => (int) $sub['id']]);

        // Paid. A renewal extends from the end of the paid month; a new one starts now.
        $prev  = $this->db->get('sites_subscriptions', '*', ['user_id' => $userId, 'status' => 'active', 'ORDER' => ['id' => 'DESC']]);
        // A complimentary plan never pushes a paid month into its own far-off end.
        $start = $prev && !self::isComplimentary($prev) && strtotime((string) $prev['period_end']) > time() ? (string) $prev['period_end'] : date('Y-m-d H:i:s');
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

    /**
     * Every few minutes: QR codes paid while nobody was watching the page are
     * confirmed here too, so a member who paid and closed the page still goes live.
     * @return list<string> names that went live
     */
    public function confirmPending(): array
    {
        $out = [];
        $seen = [];
        foreach ($this->db->select('sites_subscriptions', ['user_id', 'name'], ['status' => ['pending', 'cancelled'], 'period_start' => null,
            'created_at[>]' => date('Y-m-d H:i:s', time() - 86400)]) ?: [] as $p) {
            if (isset($seen[$p['user_id']]) || $this->current((int) $p['user_id']) && $this->current((int) $p['user_id'])['status'] === 'active') {
                continue;
            }
            $seen[$p['user_id']] = true;
            $r = $this->confirm((int) $p['user_id']);
            if (($r['status'] ?? '') === 'active') {
                $out[] = (string) $p['name'];
            }
        }

        return $out;
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
