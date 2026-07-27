<?php
namespace Ginto\Services;

use Ginto\Core\Database;

/**
 * SilverQueen — the resource-allocation and yield simulation behind /silverqueen.
 *
 * Three membership cards (Virtual $120, Physical $240, NFT Tracker $567) qualify a
 * member to rent SQB engine units. Each unit's principal accrues 0.5%/day for 365
 * days, starting 24 hours after purchase (so Day 1 pays nothing, Day 2 onwards pays).
 * Accrued yield sits on the allocation until the member transfers it into their
 * internal wallet; whatever sits in that wallet is then re-rated every 24 hours,
 * compounding at the same daily rate.
 *
 * Everything here is idempotent and time-anchored, never "once per cron tick":
 *   - allocation accrual is keyed UNIQUE(allocation_id, day_index)
 *   - compounding advances last_compound_at by whole 24h cycles only
 * so the same result falls out whether sync() runs once a day from cron or on every
 * page load, and a missed day is caught up rather than lost.
 *
 * Money is settled through the sq_wallet_txns ledger — balance_after is recorded on
 * every movement so the account can be replayed.
 */
class SilverQueenEngine
{
    /** Daily yield on an SQB unit's principal — also the wallet compounding rate. */
    public const DAILY_RATE = 0.005;      // 0.5%/day
    public const TERM_DAYS  = 365;
    public const DAY_SECS   = 86400;

    /** AntFun unilevel payout: level 1 = 15%, level 2 = 5%. */
    public const LEVEL_RATES = [1 => 0.15, 2 => 0.05];

    /** The three cards a member must hold before SQB units unlock. */
    public const REQUIRED_CARDS = ['card_virtual', 'card_physical', 'card_nft'];

    /** Invoice states that are still live — raised or paid, but not yet settled. */
    public const OPEN_STATUSES = ['pending', 'awaiting_confirmation'];

    /** confirmed_by for orders settled by the on-chain verifier rather than a person. */
    public const SYSTEM_VERIFIER_ID = 0;

    /** How long a submitted hash may stay invisible to the nodes before we call it fake. */
    private const UNKNOWN_TX_GRACE_SECS = 1800; // 30 minutes

    /** Safety rail so a bogus/ancient anchor can't produce an absurd exponent. */
    private const MAX_COMPOUND_CYCLES = 3650;

    private \Medoo\Medoo $db;
    private static bool $schemaReady = false;

    public function __construct(?\Medoo\Medoo $db = null)
    {
        $this->db = $db ?: Database::getInstance();
        $this->ensureSchema();
    }

    // ---------------------------------------------------------------- schema

    /**
     * Create the SilverQueen tables on first touch. The canonical definition lives in
     * database/migrations/20260727_create_silverqueen_tables_mysql.sql; this mirror
     * (same pattern the Academy uses for its settings table) means the route works on
     * an install where migrations haven't been run yet.
     */
    private function ensureSchema(): void
    {
        if (self::$schemaReady) return;
        self::$schemaReady = true;

        $pdo = $this->db->pdo;
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS sq_products (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(40) NOT NULL, name VARCHAR(100) NOT NULL,
                kind ENUM('card','engine') NOT NULL DEFAULT 'card',
                price DECIMAL(20,8) NOT NULL, currency VARCHAR(10) NOT NULL DEFAULT 'USD',
                daily_rate DECIMAL(10,6) NOT NULL DEFAULT 0, term_days INT UNSIGNED NOT NULL DEFAULT 0,
                max_per_user INT UNSIGNED NOT NULL DEFAULT 0, description VARCHAR(255) NULL,
                sort_order INT NOT NULL DEFAULT 0, is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_sq_products_code (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->exec("CREATE TABLE IF NOT EXISTS sq_purchases (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL, product_id INT UNSIGNED NOT NULL,
                product_code VARCHAR(40) NOT NULL, units INT UNSIGNED NOT NULL DEFAULT 1,
                unit_price DECIMAL(20,8) NOT NULL, total DECIMAL(20,8) NOT NULL,
                currency VARCHAR(10) NOT NULL DEFAULT 'USDT',
                status ENUM('pending','awaiting_confirmation','completed','cancelled','rejected') NOT NULL DEFAULT 'pending',
                source VARCHAR(30) NOT NULL DEFAULT 'usdt_bep20',
                payment_method VARCHAR(20) NOT NULL DEFAULT 'usdt_bep20',
                wallet_address VARCHAR(100) NULL,
                tx_hash VARCHAR(100) NULL,
                paid_at DATETIME NULL,
                confirmed_at DATETIME NULL,
                confirmed_by INT UNSIGNED NULL,
                rejection_reason VARCHAR(255) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_sq_purchases_tx (tx_hash),
                KEY idx_sq_purchases_user (user_id, status),
                KEY idx_sq_purchases_code (user_id, product_code, status),
                KEY idx_sq_purchases_review (status, paid_at),
                KEY idx_sq_purchases_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            // Upgrade path for installs created before USDT-only payments.
            foreach ([
                "MODIFY COLUMN status ENUM('pending','awaiting_confirmation','completed','cancelled','rejected') NOT NULL DEFAULT 'pending'",
                "ADD COLUMN IF NOT EXISTS payment_method VARCHAR(20) NOT NULL DEFAULT 'usdt_bep20'",
                "ADD COLUMN IF NOT EXISTS wallet_address VARCHAR(100) NULL",
                "ADD COLUMN IF NOT EXISTS tx_hash VARCHAR(100) NULL",
                "ADD COLUMN IF NOT EXISTS paid_at DATETIME NULL",
                "ADD COLUMN IF NOT EXISTS confirmed_at DATETIME NULL",
                "ADD COLUMN IF NOT EXISTS confirmed_by INT UNSIGNED NULL",
                "ADD COLUMN IF NOT EXISTS rejection_reason VARCHAR(255) NULL",
                "ADD UNIQUE KEY IF NOT EXISTS uq_sq_purchases_tx (tx_hash)",
                "ADD KEY IF NOT EXISTS idx_sq_purchases_review (status, paid_at)",
                "ADD COLUMN IF NOT EXISTS chain_from VARCHAR(64) NULL",
                "ADD COLUMN IF NOT EXISTS chain_amount DECIMAL(30,8) NULL",
                "ADD COLUMN IF NOT EXISTS confirmations INT UNSIGNED NULL",
                "ADD COLUMN IF NOT EXISTS verify_verdict VARCHAR(20) NULL",
                "ADD COLUMN IF NOT EXISTS verify_note VARCHAR(255) NULL",
                "ADD COLUMN IF NOT EXISTS verify_checked_at DATETIME NULL",
                "ADD KEY IF NOT EXISTS idx_sq_purchases_verify (status, verify_checked_at)",
            ] as $change) {
                try { $pdo->exec("ALTER TABLE sq_purchases $change"); } catch (\Throwable $e) {}
            }

            $pdo->exec("CREATE TABLE IF NOT EXISTS sq_allocations (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL, purchase_id BIGINT UNSIGNED NULL,
                units INT UNSIGNED NOT NULL DEFAULT 1, principal DECIMAL(20,8) NOT NULL,
                daily_rate DECIMAL(10,6) NOT NULL DEFAULT 0.005000,
                term_days INT UNSIGNED NOT NULL DEFAULT 365,
                days_accrued INT UNSIGNED NOT NULL DEFAULT 0,
                accrued DECIMAL(20,8) NOT NULL DEFAULT 0,
                claimed_total DECIMAL(20,8) NOT NULL DEFAULT 0,
                status ENUM('active','completed') NOT NULL DEFAULT 'active',
                start_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                matures_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_sq_alloc_user (user_id, status),
                KEY idx_sq_alloc_status (status, start_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->exec("CREATE TABLE IF NOT EXISTS sq_accruals (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                allocation_id BIGINT UNSIGNED NOT NULL, user_id INT UNSIGNED NOT NULL,
                day_index INT UNSIGNED NOT NULL, amount DECIMAL(20,8) NOT NULL,
                principal DECIMAL(20,8) NOT NULL, accrued_for DATETIME NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_sq_accruals_day (allocation_id, day_index),
                KEY idx_sq_accruals_user (user_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->exec("CREATE TABLE IF NOT EXISTS sq_wallets (
                user_id INT UNSIGNED NOT NULL PRIMARY KEY,
                balance DECIMAL(20,8) NOT NULL DEFAULT 0,
                total_claimed DECIMAL(20,8) NOT NULL DEFAULT 0,
                total_compounded DECIMAL(20,8) NOT NULL DEFAULT 0,
                total_commission DECIMAL(20,8) NOT NULL DEFAULT 0,
                last_compound_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->exec("CREATE TABLE IF NOT EXISTS sq_wallet_txns (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                type ENUM('yield_claim','compound','commission','purchase','adjustment') NOT NULL,
                amount DECIMAL(20,8) NOT NULL, balance_after DECIMAL(20,8) NOT NULL,
                note VARCHAR(255) NULL, ref_id BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_sq_txns_user (user_id, id), KEY idx_sq_txns_type (type, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->exec("CREATE TABLE IF NOT EXISTS sq_referrals (
                user_id INT UNSIGNED NOT NULL PRIMARY KEY,
                sponsor_id INT UNSIGNED NULL, depth INT UNSIGNED NOT NULL DEFAULT 0,
                path VARCHAR(255) NOT NULL DEFAULT '/', code VARCHAR(32) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_sq_referrals_code (code),
                KEY idx_sq_referrals_sponsor (sponsor_id), KEY idx_sq_referrals_path (path)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->exec("CREATE TABLE IF NOT EXISTS sq_commissions (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                earner_id INT UNSIGNED NOT NULL, source_user_id INT UNSIGNED NOT NULL,
                purchase_id BIGINT UNSIGNED NOT NULL, level TINYINT UNSIGNED NOT NULL,
                rate DECIMAL(10,6) NOT NULL, amount DECIMAL(20,8) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_sq_comm_purchase_level (purchase_id, level),
                KEY idx_sq_comm_earner (earner_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $this->seedCatalog();
        } catch (\Throwable $e) {
            error_log('SilverQueen ensureSchema: ' . $e->getMessage());
        }
    }

    /** Seed the four catalogue rows once. Prices are the spec's, in USD. */
    private function seedCatalog(): void
    {
        $seed = [
            ['card_virtual',  'Virtual Membership',        'card',   120.0, 0,                0,               1,
             'Entry card. Unlocks the SilverQueen console and your AntFun invite code.', 1],
            ['card_physical', 'Physical Membership',       'card',   240.0, 0,                0,               1,
             'Shipped hardware card. Required for physical rack attestation.', 2],
            ['card_nft',      'NFT Tracker Membership',    'card',   567.0, 0,                0,               1,
             'On-chain tracker that binds your engine units to a verifiable identity.', 3],
            ['sqb_engine',    'SQB Engine Unit',           'engine', 100.0, self::DAILY_RATE, self::TERM_DAYS, 0,
             'One unit of allocated cloud compute. Yields 0.5% of its principal per day for 365 days.', 4],
        ];
        foreach ($seed as [$code, $name, $kind, $price, $rate, $term, $max, $desc, $sort]) {
            if ($this->db->count('sq_products', ['code' => $code]) > 0) continue;
            $this->db->insert('sq_products', [
                'code' => $code, 'name' => $name, 'kind' => $kind, 'price' => $price,
                'currency' => 'USDT', 'daily_rate' => $rate, 'term_days' => $term,
                'max_per_user' => $max, 'description' => $desc, 'sort_order' => $sort, 'is_active' => 1,
            ]);
        }
    }

    // -------------------------------------------------------------- catalogue

    /** The active catalogue, cards first, then the engine. */
    public function products(): array
    {
        $rows = $this->db->select('sq_products', '*', ['is_active' => 1, 'ORDER' => ['sort_order' => 'ASC']]);
        return is_array($rows) ? $rows : [];
    }

    public function product(string $code): ?array
    {
        $r = $this->db->get('sq_products', '*', ['code' => $code, 'is_active' => 1]);
        return is_array($r) ? $r : null;
    }

    /** Product codes this member already owns (completed purchases only). */
    public function ownedCards(int $userId): array
    {
        $rows = $this->db->select('sq_purchases', 'product_code', [
            'user_id' => $userId, 'status' => 'completed', 'product_code' => self::REQUIRED_CARDS,
        ]);
        return is_array($rows) ? array_values(array_unique($rows)) : [];
    }

    /**
     * Hardware qualification: all three base cards must be held before SQB engine
     * units can be purchased. This is the only gate on engine purchases.
     */
    public function isQualified(int $userId): bool
    {
        return count(array_intersect(self::REQUIRED_CARDS, $this->ownedCards($userId))) === count(self::REQUIRED_CARDS);
    }

    // ---------------------------------------------------------------- payment

    /**
     * The one and only way to pay for SilverQueen: a USDT transfer on BNB Smart
     * Chain to the SilverQueen wallet. Address and QR come from the shared crypto
     * config so there is a single place to rotate them.
     */
    public function paymentConfig(): array
    {
        $fallback = [
            'network' => 'BNB Smart Chain (BEP20)',
            'token'   => 'USDT',
            'address' => '',
            'qr'      => '/assets/images/pay_usdt.jpeg',
        ];
        try {
            $cfg = require dirname(__DIR__) . '/Views/payments/address.php';
            $sq  = is_array($cfg) ? ($cfg['silverqueen_usdt_bep20'] ?? null) : null;
            return is_array($sq) ? $sq + $fallback : $fallback;
        } catch (\Throwable $e) {
            error_log('SilverQueen paymentConfig: ' . $e->getMessage());
            return $fallback;
        }
    }

    // --------------------------------------------------------------- purchase

    /**
     * Raise an invoice. Nothing is granted here — the row lands as 'pending' with
     * the exact USDT amount and the destination wallet, and the buyer settles it
     * on-chain. Allocations and referral overrides happen in confirmPurchase(),
     * once an admin has verified the transfer.
     */
    public function createInvoice(int $userId, string $code, int $units = 1): array
    {
        $product = $this->product($code);
        if (!$product) return ['ok' => false, 'error' => 'Unknown product.'];

        $units = max(1, min(1000, $units));
        $kind  = (string) $product['kind'];

        if ($kind === 'card') {
            $units = 1; // cards are one-per-user, never bought in bulk
            if ($this->db->count('sq_purchases', ['user_id' => $userId, 'product_code' => $code, 'status' => 'completed']) > 0) {
                return ['ok' => false, 'error' => 'You already hold this card.'];
            }
            // An open invoice for the same card would let one member pay twice.
            if ($this->db->count('sq_purchases', ['user_id' => $userId, 'product_code' => $code, 'status' => self::OPEN_STATUSES]) > 0) {
                return ['ok' => false, 'error' => 'You already have an open invoice for this card — settle or cancel it first.'];
            }
        }
        // Qualification is judged on cards actually paid for, never on open invoices.
        if ($kind === 'engine' && !$this->isQualified($userId)) {
            return ['ok' => false, 'error' => 'SQB engines unlock once you hold all three membership cards.'];
        }

        $pay       = $this->paymentConfig();
        $unitPrice = (float) $product['price'];
        $total     = round($unitPrice * $units, 8);

        try {
            $this->db->insert('sq_purchases', [
                'user_id'        => $userId,
                'product_id'     => (int) $product['id'],
                'product_code'   => $code,
                'units'          => $units,
                'unit_price'     => $unitPrice,
                'total'          => $total,
                'currency'       => 'USDT',
                'status'         => 'pending',
                'source'         => 'usdt_bep20',
                'payment_method' => 'usdt_bep20',
                'wallet_address' => (string) $pay['address'],
                'created_at'     => date('Y-m-d H:i:s'),
            ]);
            $purchaseId = (int) $this->db->pdo->lastInsertId();
        } catch (\Throwable $e) {
            error_log('SilverQueen createInvoice: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Could not raise the invoice.'];
        }

        return [
            'ok'          => true,
            'purchase_id' => $purchaseId,
            'product'     => (string) $product['name'],
            'units'       => $units,
            'amount'      => $total,
            'currency'    => 'USDT',
            'payment'     => $pay,
            'status'      => 'pending',
        ];
    }

    /**
     * The buyer says they've sent the USDT and hands over the transaction hash.
     * The invoice moves to awaiting_confirmation — still granting nothing — and
     * joins the admin review queue. The hash is UNIQUE across purchases, so the
     * same transfer cannot be claimed against two invoices.
     */
    public function submitPayment(int $userId, int $purchaseId, string $txHash): array
    {
        $txHash = trim($txHash);
        // BEP20 hashes are 0x + 64 hex. Reject anything else rather than queueing junk.
        if (!preg_match('/^0x[a-fA-F0-9]{64}$/', $txHash)) {
            return ['ok' => false, 'error' => 'That does not look like a BEP20 transaction hash (0x followed by 64 characters).'];
        }

        $inv = $this->db->get('sq_purchases', '*', ['id' => $purchaseId, 'user_id' => $userId]);
        if (!is_array($inv)) return ['ok' => false, 'error' => 'Invoice not found.'];
        if (!in_array((string) $inv['status'], self::OPEN_STATUSES, true)) {
            return ['ok' => false, 'error' => 'This invoice is no longer open.'];
        }

        if ($this->db->count('sq_purchases', ['tx_hash' => $txHash, 'id[!]' => $purchaseId]) > 0) {
            return ['ok' => false, 'error' => 'That transaction hash has already been submitted for another order.'];
        }

        try {
            $this->db->update('sq_purchases', [
                'tx_hash' => $txHash,
                'paid_at' => date('Y-m-d H:i:s'),
                'status'  => 'awaiting_confirmation',
                'rejection_reason' => null,
            ], ['id' => $purchaseId, 'user_id' => $userId]);
        } catch (\Throwable $e) {
            // Unique-key collision beat the count() check above.
            error_log('SilverQueen submitPayment: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'That transaction hash has already been submitted.'];
        }

        // Check the chain straight away. A transfer that already has enough
        // confirmations completes the order here, with no human in the loop.
        $verdict = $this->autoVerify($purchaseId);

        return [
            'ok'          => true,
            'purchase_id' => $purchaseId,
            'status'      => $verdict['status'] ?? 'awaiting_confirmation',
            'verify'      => $verdict['verify'] ?? null,
            'message'     => $verdict['message'] ?? 'Payment submitted. Your order unlocks once we verify the transfer on-chain.',
        ];
    }

    /**
     * Ask the public BSC nodes what really happened to an invoice's TxHash, record
     * the answer, and act on it:
     *
     *   confirmed  -> complete the order (allocation + referral overrides)
     *   failed     -> reject; the transaction reverted
     *   not_found  -> reject, but only after a grace period, since a freshly
     *                 broadcast hash can be briefly unknown to the node we asked
     *   mismatch   -> reject; it didn't pay us, or paid short
     *   pending    -> leave it queued and look again next sweep
     *   unavailable-> decide nothing; the nodes were unreachable
     *
     * Never rejects on an inconclusive answer, so an RPC outage can only ever delay
     * an order, never cancel a real payment.
     */
    public function autoVerify(int $purchaseId): array
    {
        $inv = $this->db->get('sq_purchases', '*', ['id' => $purchaseId]);
        if (!is_array($inv) || !in_array((string) $inv['status'], self::OPEN_STATUSES, true)) {
            return ['status' => is_array($inv) ? (string) $inv['status'] : 'unknown', 'verify' => null,
                    'message' => 'Nothing to verify.'];
        }
        $txHash = (string) ($inv['tx_hash'] ?? '');
        if ($txHash === '') {
            return ['status' => (string) $inv['status'], 'verify' => null, 'message' => 'No transaction hash submitted yet.'];
        }

        $verifier = new UsdtBep20Verifier();
        $res = $verifier->verify($txHash, (string) $inv['wallet_address'], (float) $inv['total']);

        // Always record what we saw, even when the answer is inconclusive.
        try {
            $this->db->update('sq_purchases', [
                'chain_from'        => $res['from'],
                'chain_amount'      => $res['amount'],
                'confirmations'     => (int) $res['confirmations'],
                'verify_verdict'    => (string) $res['verdict'],
                'verify_note'       => substr((string) $res['note'], 0, 255),
                'verify_checked_at' => date('Y-m-d H:i:s'),
            ], ['id' => $purchaseId]);
        } catch (\Throwable $e) {
            error_log('SilverQueen autoVerify record: ' . $e->getMessage());
        }

        switch ($res['verdict']) {
            case 'confirmed':
                $done = $this->confirmPurchase($purchaseId, self::SYSTEM_VERIFIER_ID);
                return !empty($done['ok'])
                    ? ['status' => 'completed', 'verify' => $res, 'confirmed' => $done,
                       'message' => 'Payment verified on-chain — your order is active.']
                    : ['status' => 'awaiting_confirmation', 'verify' => $res,
                       'message' => 'Payment verified, but the order could not be completed. Support has been notified.'];

            case 'failed':
            case 'mismatch':
                $this->rejectPurchase($purchaseId, self::SYSTEM_VERIFIER_ID, (string) $res['note']);
                return ['status' => 'rejected', 'verify' => $res, 'message' => (string) $res['note']];

            case 'not_found':
                // Give a just-broadcast transaction time to propagate before calling it fake.
                $submitted = strtotime((string) ($inv['paid_at'] ?? 'now'));
                if ($submitted !== false && (time() - $submitted) > self::UNKNOWN_TX_GRACE_SECS) {
                    $this->rejectPurchase($purchaseId, self::SYSTEM_VERIFIER_ID,
                        'No such transaction on BNB Smart Chain.');
                    return ['status' => 'rejected', 'verify' => $res, 'message' => 'No such transaction on BNB Smart Chain.'];
                }
                return ['status' => 'awaiting_confirmation', 'verify' => $res,
                        'message' => 'We cannot see that transaction yet — still looking.'];

            case 'pending':
            default:
                return ['status' => 'awaiting_confirmation', 'verify' => $res,
                        'message' => (string) $res['note']];
        }
    }

    /**
     * Sweep every submitted invoice that is still waiting. Run from cron; the
     * per-invoice work is a couple of RPC calls, so a small batch keeps the sweep
     * well inside a one-minute tick.
     */
    public function verifyPending(int $limit = 25): array
    {
        $rows = $this->db->select('sq_purchases', ['id'], [
            'status'   => 'awaiting_confirmation',
            'tx_hash[!]' => null,
            'ORDER'    => ['verify_checked_at' => 'ASC'],
            'LIMIT'    => $limit,
        ]);
        if (!is_array($rows)) $rows = [];

        $out = ['checked' => 0, 'completed' => 0, 'rejected' => 0, 'waiting' => 0];
        foreach ($rows as $r) {
            $res = $this->autoVerify((int) $r['id']);
            $out['checked']++;
            if (($res['status'] ?? '') === 'completed')      $out['completed']++;
            elseif (($res['status'] ?? '') === 'rejected')   $out['rejected']++;
            else                                            $out['waiting']++;
        }
        return $out;
    }

    /**
     * Admin verified the transfer: complete the order. This is the only path that
     * creates an SQB allocation or pays a referral override, so nothing is ever
     * granted against unpaid funds. Re-confirming a completed invoice is a no-op.
     */
    public function confirmPurchase(int $purchaseId, int $adminId): array
    {
        $inv = $this->db->get('sq_purchases', '*', ['id' => $purchaseId]);
        if (!is_array($inv)) return ['ok' => false, 'error' => 'Invoice not found.'];
        if ((string) $inv['status'] === 'completed') {
            return ['ok' => false, 'error' => 'This order is already completed.'];
        }
        if (!in_array((string) $inv['status'], self::OPEN_STATUSES, true)) {
            return ['ok' => false, 'error' => 'This invoice is not open.'];
        }

        $userId  = (int) $inv['user_id'];
        $total   = (float) $inv['total'];
        $units   = (int) $inv['units'];
        $product = $this->product((string) $inv['product_code']);
        $isEngine = $product && $product['kind'] === 'engine';
        $pdo = $this->db->pdo;

        try {
            $pdo->beginTransaction();

            // Guarded UPDATE: only an open row flips, so two admins clicking at once
            // cannot both create an allocation.
            $this->db->update('sq_purchases', [
                'status'       => 'completed',
                'confirmed_at' => date('Y-m-d H:i:s'),
                'confirmed_by' => $adminId,
            ], ['id' => $purchaseId, 'status' => self::OPEN_STATUSES]);

            $fresh = $this->db->get('sq_purchases', 'status', ['id' => $purchaseId]);
            if ((string) $fresh !== 'completed') {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'This invoice was just handled by someone else.'];
            }

            $allocationId = null;
            if ($isEngine) {
                $now  = time();
                $term = (int) ($product['term_days'] ?: self::TERM_DAYS);
                $rate = (float) ($product['daily_rate'] ?: self::DAILY_RATE);
                $this->db->insert('sq_allocations', [
                    'user_id'     => $userId,
                    'purchase_id' => $purchaseId,
                    'units'       => $units,
                    'principal'   => $total,
                    'daily_rate'  => $rate,
                    'term_days'   => $term,
                    'status'      => 'active',
                    // The 365-day clock starts when the payment is verified, not when
                    // the invoice was raised, so a slow confirmation costs no yield.
                    'start_at'    => date('Y-m-d H:i:s', $now),
                    'matures_at'  => date('Y-m-d H:i:s', $now + $term * self::DAY_SECS),
                    'created_at'  => date('Y-m-d H:i:s', $now),
                ]);
                $allocationId = (int) $pdo->lastInsertId();
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('SilverQueen confirmPurchase: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Could not confirm the payment.'];
        }

        // Settled outside the transaction so a payout problem can never roll back a
        // confirmation the buyer already paid for.
        $paid = $this->payCommissions($purchaseId, $userId, $total);

        return [
            'ok'            => true,
            'purchase_id'   => $purchaseId,
            'allocation_id' => $allocationId,
            'user_id'       => $userId,
            'product'       => $product['name'] ?? (string) $inv['product_code'],
            'amount'        => $total,
            'qualified'     => $this->isQualified($userId),
            'commissions'   => $paid,
        ];
    }

    /** Admin could not find the transfer: bounce the invoice back with a reason. */
    public function rejectPurchase(int $purchaseId, int $adminId, string $reason = ''): array
    {
        $inv = $this->db->get('sq_purchases', '*', ['id' => $purchaseId]);
        if (!is_array($inv)) return ['ok' => false, 'error' => 'Invoice not found.'];
        if (!in_array((string) $inv['status'], self::OPEN_STATUSES, true)) {
            return ['ok' => false, 'error' => 'This invoice is not open.'];
        }
        $reason = trim($reason) !== '' ? substr(trim($reason), 0, 255) : 'Transfer could not be verified on-chain.';

        try {
            $this->db->update('sq_purchases', [
                'status'           => 'rejected',
                'rejection_reason' => $reason,
                'confirmed_by'     => $adminId,
                'confirmed_at'     => date('Y-m-d H:i:s'),
                // Free the hash so a corrected one can be submitted on a new invoice.
                'tx_hash'          => null,
            ], ['id' => $purchaseId, 'status' => self::OPEN_STATUSES]);
        } catch (\Throwable $e) {
            error_log('SilverQueen rejectPurchase: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Could not reject the invoice.'];
        }
        return ['ok' => true, 'purchase_id' => $purchaseId, 'reason' => $reason];
    }

    /** The buyer walks away from an unpaid invoice. Only theirs, only if unpaid. */
    public function cancelInvoice(int $userId, int $purchaseId): array
    {
        $inv = $this->db->get('sq_purchases', '*', ['id' => $purchaseId, 'user_id' => $userId]);
        if (!is_array($inv)) return ['ok' => false, 'error' => 'Invoice not found.'];
        if ((string) $inv['status'] !== 'pending') {
            return ['ok' => false, 'error' => 'Only an unpaid invoice can be cancelled.'];
        }
        $this->db->update('sq_purchases', ['status' => 'cancelled'], ['id' => $purchaseId, 'user_id' => $userId, 'status' => 'pending']);
        return ['ok' => true, 'purchase_id' => $purchaseId];
    }

    /** Whether an invoice belongs to this member — the ownership check for re-checks. */
    public function ownsInvoice(int $userId, int $purchaseId): bool
    {
        return $this->db->count('sq_purchases', ['id' => $purchaseId, 'user_id' => $userId]) > 0;
    }

    /** This member's open invoices — what they still owe or are waiting on. */
    public function openInvoices(int $userId): array
    {
        $rows = $this->db->select('sq_purchases', '*', [
            'user_id' => $userId, 'status' => self::OPEN_STATUSES, 'ORDER' => ['id' => 'DESC'],
        ]);
        return is_array($rows) ? $rows : [];
    }

    /** Recently rejected invoices, so the buyer sees why and can retry. */
    public function rejectedInvoices(int $userId, int $limit = 5): array
    {
        $rows = $this->db->select('sq_purchases', '*', [
            'user_id' => $userId, 'status' => 'rejected', 'ORDER' => ['id' => 'DESC'], 'LIMIT' => $limit,
        ]);
        return is_array($rows) ? $rows : [];
    }

    /** The admin verification queue: paid invoices waiting on an on-chain check. */
    public function reviewQueue(int $limit = 50): array
    {
        $rows = $this->db->select('sq_purchases', '*', [
            'status' => 'awaiting_confirmation', 'ORDER' => ['paid_at' => 'ASC'], 'LIMIT' => $limit,
        ]);
        if (!is_array($rows)) $rows = [];
        $names = $this->displayNames(array_column($rows, 'user_id'));
        foreach ($rows as &$r) $r['member'] = $names[(int) $r['user_id']] ?? ('Member #' . $r['user_id']);
        unset($r);
        return $rows;
    }

    // ---------------------------------------------------------------- accrual

    /**
     * Bring a member fully up to date: accrue every whole elapsed day on their
     * allocations, then run the wallet's 24h compounding cycle. Safe to call on
     * every request — it only writes when a 24h boundary has actually been crossed.
     */
    public function sync(int $userId): array
    {
        $accrued    = $this->accrueUser($userId);
        $compounded = $this->compoundWallet($userId);
        return ['accrued' => $accrued, 'compounded' => $compounded];
    }

    /** Accrue all active allocations for one member. Returns the amount credited. */
    public function accrueUser(int $userId): float
    {
        $rows = $this->db->select('sq_allocations', '*', ['user_id' => $userId, 'status' => 'active']);
        if (!is_array($rows)) return 0.0;
        $total = 0.0;
        foreach ($rows as $a) $total += $this->accrueAllocation($a);
        return round($total, 8);
    }

    /**
     * Accrue every active allocation platform-wide. This is what the cron worker
     * calls; it deliberately does not touch wallets, since compounding is per-member
     * and is settled by compoundAllWallets().
     */
    public function accrueAll(int $limit = 5000): array
    {
        $rows = $this->db->select('sq_allocations', '*', ['status' => 'active', 'LIMIT' => $limit]);
        if (!is_array($rows)) $rows = [];
        $total = 0.0; $touched = 0;
        foreach ($rows as $a) {
            $amt = $this->accrueAllocation($a);
            if ($amt > 0) { $total += $amt; $touched++; }
        }
        return ['allocations' => count($rows), 'credited' => $touched, 'amount' => round($total, 8)];
    }

    /**
     * One allocation's catch-up. Day N settles at start_at + N*24h, so the first
     * payout lands a full day after purchase (Day 2 of ownership) and nothing is
     * paid for Day 1. Each day is written to sq_accruals under a UNIQUE key, so a
     * double run is a no-op rather than a double payment.
     */
    private function accrueAllocation(array $a): float
    {
        $allocId   = (int) $a['id'];
        $startTs   = strtotime((string) $a['start_at']);
        $term      = (int) $a['term_days'];
        $done      = (int) $a['days_accrued'];
        $principal = (float) $a['principal'];
        $rate      = (float) $a['daily_rate'];

        if ($startTs === false || $term <= 0 || $principal <= 0) return 0.0;

        $elapsed = (int) floor((time() - $startTs) / self::DAY_SECS);
        $due     = min($elapsed, $term);
        if ($due <= $done) {
            // Nothing new — but a matured allocation still needs closing out.
            if ($done >= $term && $a['status'] === 'active') {
                $this->db->update('sq_allocations', ['status' => 'completed'], ['id' => $allocId]);
            }
            return 0.0;
        }

        $perDay = round($principal * $rate, 8);
        $credited = 0.0;
        for ($n = $done + 1; $n <= $due; $n++) {
            try {
                $this->db->insert('sq_accruals', [
                    'allocation_id' => $allocId,
                    'user_id'       => (int) $a['user_id'],
                    'day_index'     => $n,
                    'amount'        => $perDay,
                    'principal'     => $principal,
                    'accrued_for'   => date('Y-m-d H:i:s', $startTs + $n * self::DAY_SECS),
                    'created_at'    => date('Y-m-d H:i:s'),
                ]);
                $credited += $perDay;
            } catch (\Throwable $e) {
                // Duplicate key = another worker already settled this day. Skip it.
                continue;
            }
        }

        if ($credited > 0) {
            $this->db->update('sq_allocations', [
                'days_accrued' => $due,
                'accrued[+]'   => round($credited, 8),
                'status'       => $due >= $term ? 'completed' : 'active',
            ], ['id' => $allocId]);
        }
        return round($credited, 8);
    }

    // ----------------------------------------------------------------- wallet

    /** The member's wallet row, created on first touch. */
    public function wallet(int $userId): array
    {
        $w = $this->db->get('sq_wallets', '*', ['user_id' => $userId]);
        if (!is_array($w)) {
            $this->db->insert('sq_wallets', ['user_id' => $userId, 'balance' => 0, 'created_at' => date('Y-m-d H:i:s')]);
            $w = $this->db->get('sq_wallets', '*', ['user_id' => $userId]) ?: [
                'user_id' => $userId, 'balance' => 0, 'total_claimed' => 0,
                'total_compounded' => 0, 'total_commission' => 0, 'last_compound_at' => null,
            ];
        }
        return $w;
    }

    /**
     * Transfer to Wallet — move everything accrued on the member's allocations into
     * the internal wallet. Once there it starts compounding, so claiming is what
     * puts yield to work rather than just realising it.
     */
    public function claim(int $userId): array
    {
        $rows = $this->db->select('sq_allocations', ['id', 'accrued'], ['user_id' => $userId, 'accrued[>]' => 0]);
        if (!is_array($rows) || !$rows) return ['ok' => false, 'error' => 'Nothing to transfer yet.'];

        $amount = 0.0;
        foreach ($rows as $r) $amount += (float) $r['accrued'];
        $amount = round($amount, 8);
        if ($amount <= 0) return ['ok' => false, 'error' => 'Nothing to transfer yet.'];

        $pdo = $this->db->pdo;
        try {
            $pdo->beginTransaction();

            foreach ($rows as $r) {
                $this->db->update('sq_allocations', [
                    'accrued'          => 0,
                    'claimed_total[+]' => round((float) $r['accrued'], 8),
                ], ['id' => (int) $r['id'], 'accrued[>]' => 0]);
            }

            $wallet  = $this->wallet($userId);
            $balance = round((float) $wallet['balance'] + $amount, 8);

            $update = ['balance' => $balance, 'total_claimed[+]' => $amount];
            // First money in starts the 24h compounding clock.
            if (empty($wallet['last_compound_at'])) $update['last_compound_at'] = date('Y-m-d H:i:s');
            $this->db->update('sq_wallets', $update, ['user_id' => $userId]);

            $this->db->insert('sq_wallet_txns', [
                'user_id' => $userId, 'type' => 'yield_claim', 'amount' => $amount,
                'balance_after' => $balance, 'note' => 'Yield transferred from ' . count($rows) . ' allocation(s)',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('SilverQueen claim: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Could not transfer the yield.'];
        }
        return ['ok' => true, 'amount' => $amount, 'balance' => $balance];
    }

    /**
     * The 24-hour recalculation on wallet-resident assets. Every whole cycle since
     * last_compound_at re-rates the balance at DAILY_RATE, so yield left in the
     * wallet compounds onto the base rather than sitting idle. The anchor advances
     * by whole cycles only, so the partial day is never lost or double-counted.
     */
    public function compoundWallet(int $userId): float
    {
        $w       = $this->wallet($userId);
        $balance = (float) $w['balance'];
        $anchor  = !empty($w['last_compound_at']) ? strtotime((string) $w['last_compound_at']) : null;

        if ($balance <= 0) {
            // An empty wallet has nothing to re-rate; re-anchor so the next deposit
            // gets a full 24h cycle instead of instant credit for idle time.
            if ($anchor !== null) $this->db->update('sq_wallets', ['last_compound_at' => null], ['user_id' => $userId]);
            return 0.0;
        }
        if ($anchor === null || $anchor === false) {
            $this->db->update('sq_wallets', ['last_compound_at' => date('Y-m-d H:i:s')], ['user_id' => $userId]);
            return 0.0;
        }

        $cycles = (int) floor((time() - $anchor) / self::DAY_SECS);
        if ($cycles <= 0) return 0.0;
        $cycles = min($cycles, self::MAX_COMPOUND_CYCLES);

        $interest = round($balance * (pow(1 + self::DAILY_RATE, $cycles) - 1), 8);
        if ($interest <= 0) return 0.0;
        $newBalance = round($balance + $interest, 8);

        $pdo = $this->db->pdo;
        try {
            $pdo->beginTransaction();
            $this->db->update('sq_wallets', [
                'balance'             => $newBalance,
                'total_compounded[+]' => $interest,
                'last_compound_at'    => date('Y-m-d H:i:s', $anchor + $cycles * self::DAY_SECS),
            ], ['user_id' => $userId]);
            $this->db->insert('sq_wallet_txns', [
                'user_id' => $userId, 'type' => 'compound', 'amount' => $interest,
                'balance_after' => $newBalance,
                'note' => $cycles . ' × 24h recalculation @ ' . rtrim(rtrim(number_format(self::DAILY_RATE * 100, 3), '0'), '.') . '%/day',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('SilverQueen compoundWallet: ' . $e->getMessage());
            return 0.0;
        }
        return $interest;
    }

    /** Compound every funded wallet — the cron worker's second half. */
    public function compoundAllWallets(int $limit = 5000): array
    {
        $ids = $this->db->select('sq_wallets', 'user_id', ['balance[>]' => 0, 'LIMIT' => $limit]);
        if (!is_array($ids)) $ids = [];
        $total = 0.0; $touched = 0;
        foreach ($ids as $uid) {
            $amt = $this->compoundWallet((int) $uid);
            if ($amt > 0) { $total += $amt; $touched++; }
        }
        return ['wallets' => count($ids), 'compounded' => $touched, 'amount' => round($total, 8)];
    }

    /** Recent wallet ledger rows, newest first. */
    public function transactions(int $userId, int $limit = 20): array
    {
        $rows = $this->db->select('sq_wallet_txns', '*', [
            'user_id' => $userId, 'ORDER' => ['id' => 'DESC'], 'LIMIT' => $limit,
        ]);
        return is_array($rows) ? $rows : [];
    }

    // -------------------------------------------------------- AntFun referrals

    /**
     * Enrol a member into the AntFun tree. This hierarchy is deliberately separate
     * from users.referrer_id: someone's SilverQueen sponsor need not be — and usually
     * isn't — whoever referred them to the platform. Enrolment happens once; the
     * sponsor is fixed from then on.
     */
    public function enroll(int $userId, ?string $sponsorCode = null): array
    {
        $existing = $this->db->get('sq_referrals', '*', ['user_id' => $userId]);
        if (is_array($existing)) return $existing;

        $sponsor   = $sponsorCode ? $this->resolveSponsor($sponsorCode, $userId) : null;
        $sponsorId = $sponsor['user_id'] ?? null;
        $depth     = $sponsor ? (int) $sponsor['depth'] + 1 : 0;
        $path      = $sponsor ? rtrim((string) $sponsor['path'], '/') . '/' . $sponsor['user_id'] . '/' : '/';

        $row = [
            'user_id'    => $userId,
            'sponsor_id' => $sponsorId,
            'depth'      => $depth,
            'path'       => $path,
            'code'       => $this->mintCode($userId),
            'created_at' => date('Y-m-d H:i:s'),
        ];
        try {
            $this->db->insert('sq_referrals', $row);
        } catch (\Throwable $e) {
            // Raced with a concurrent enrolment — the winner's row is authoritative.
            $won = $this->db->get('sq_referrals', '*', ['user_id' => $userId]);
            if (is_array($won)) return $won;
            error_log('SilverQueen enroll: ' . $e->getMessage());
        }
        return $row;
    }

    /**
     * Resolve an invite code to its owner's tree row. Refuses self-sponsorship and
     * any sponsor already sitting in the joiner's own downline, which is what keeps
     * the tree acyclic.
     */
    private function resolveSponsor(string $code, int $joinerId): ?array
    {
        $code = trim($code);
        if ($code === '') return null;
        $row = $this->db->get('sq_referrals', '*', ['code' => $code]);
        if (!is_array($row)) return null;
        if ((int) $row['user_id'] === $joinerId) return null;
        if (strpos((string) $row['path'], '/' . $joinerId . '/') !== false) return null;
        return $row;
    }

    /** A stable, unique invite code for this member (public_id when there is one). */
    private function mintCode(int $userId): string
    {
        $base = '';
        try {
            $pid = $this->db->get('users', 'public_id', ['id' => $userId]);
            if (!empty($pid)) $base = preg_replace('/[^A-Za-z0-9]/', '', (string) $pid);
        } catch (\Throwable $e) {}
        if ($base === '') $base = 'SQ' . strtoupper(base_convert((string) $userId, 10, 36));

        $code = $base;
        for ($i = 2; $this->db->count('sq_referrals', ['code' => $code]) > 0 && $i < 50; $i++) {
            $code = $base . $i;
        }
        return substr($code, 0, 32);
    }

    /** This member's tree row, enrolling them if they haven't been seen before. */
    public function referralRow(int $userId): array
    {
        $r = $this->db->get('sq_referrals', '*', ['user_id' => $userId]);
        return is_array($r) ? $r : $this->enroll($userId);
    }

    /**
     * The member's 2-level downline with per-member volume, for the tree visualiser.
     * Level 1 = direct recruits, level 2 = their recruits. Nothing below level 2 is
     * tracked because nothing below level 2 pays.
     */
    public function downline(int $userId): array
    {
        $out = [1 => [], 2 => []];
        $l1  = $this->db->select('sq_referrals', ['user_id', 'created_at'], ['sponsor_id' => $userId, 'ORDER' => ['created_at' => 'ASC']]);
        if (!is_array($l1)) $l1 = [];

        $l1Ids = array_map(static fn($r) => (int) $r['user_id'], $l1);
        $l2 = $l1Ids ? $this->db->select('sq_referrals', ['user_id', 'sponsor_id', 'created_at'], ['sponsor_id' => $l1Ids, 'ORDER' => ['created_at' => 'ASC']]) : [];
        if (!is_array($l2)) $l2 = [];

        $names = $this->displayNames(array_merge($l1Ids, array_map(static fn($r) => (int) $r['user_id'], $l2)));

        foreach ($l1 as $r) {
            $uid = (int) $r['user_id'];
            $out[1][] = [
                'user_id' => $uid, 'name' => $names[$uid] ?? ('Member #' . $uid),
                'joined' => $r['created_at'], 'volume' => $this->purchaseVolume($uid),
                'children' => 0,
            ];
        }
        foreach ($l2 as $r) {
            $uid = (int) $r['user_id'];
            $out[2][] = [
                'user_id' => $uid, 'name' => $names[$uid] ?? ('Member #' . $uid),
                'sponsor_id' => (int) $r['sponsor_id'],
                'joined' => $r['created_at'], 'volume' => $this->purchaseVolume($uid),
            ];
            foreach ($out[1] as &$p) if ($p['user_id'] === (int) $r['sponsor_id']) $p['children']++;
            unset($p);
        }
        return $out;
    }

    /** Completed purchase volume for one member — the base commissions are paid on. */
    public function purchaseVolume(int $userId): float
    {
        $sum = $this->db->sum('sq_purchases', 'total', ['user_id' => $userId, 'status' => 'completed']);
        return round((float) $sum, 8);
    }

    private function displayNames(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) return [];
        $rows = $this->db->select('users', ['id', 'username', 'fullname'], ['id' => $ids]);
        if (!is_array($rows)) return [];
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['id']] = trim((string) ($r['fullname'] ?: $r['username'] ?: ''))
                ?: ('Member #' . (int) $r['id']);
        }
        return $out;
    }

    /**
     * Pay the unilevel override on a purchase: 15% to the direct sponsor, 5% to the
     * sponsor's sponsor, credited straight into their SilverQueen wallets. The
     * UNIQUE(purchase_id, level) key means a retry can never pay the same override twice.
     */
    public function payCommissions(int $purchaseId, int $buyerId, float $amount): array
    {
        if ($amount <= 0) return [];
        $paid    = [];
        $current = $this->referralRow($buyerId);

        foreach (self::LEVEL_RATES as $level => $rate) {
            $sponsorId = (int) ($current['sponsor_id'] ?? 0);
            if ($sponsorId <= 0) break;

            $commission = round($amount * $rate, 8);
            if ($commission > 0) {
                try {
                    $this->db->insert('sq_commissions', [
                        'earner_id' => $sponsorId, 'source_user_id' => $buyerId,
                        'purchase_id' => $purchaseId, 'level' => $level,
                        'rate' => $rate, 'amount' => $commission,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                    $this->creditWallet($sponsorId, $commission, 'commission',
                        'Level ' . $level . ' override (' . round($rate * 100) . '%)', $purchaseId);
                    $paid[] = ['user_id' => $sponsorId, 'level' => $level, 'amount' => $commission];
                } catch (\Throwable $e) {
                    // Duplicate = already paid on an earlier attempt. Nothing to do.
                }
            }

            $next = $this->db->get('sq_referrals', '*', ['user_id' => $sponsorId]);
            if (!is_array($next)) break;
            $current = $next;
        }
        return $paid;
    }

    /** Credit the wallet and write the ledger row, starting the compounding clock if idle. */
    private function creditWallet(int $userId, float $amount, string $type, string $note, ?int $refId = null): void
    {
        if ($amount <= 0) return;
        $pdo = $this->db->pdo;
        $inOuter = $pdo->inTransaction();
        try {
            if (!$inOuter) $pdo->beginTransaction();

            $w       = $this->wallet($userId);
            $balance = round((float) $w['balance'] + $amount, 8);

            $update = ['balance' => $balance];
            if ($type === 'commission') $update['total_commission[+]'] = $amount;
            if (empty($w['last_compound_at'])) $update['last_compound_at'] = date('Y-m-d H:i:s');
            $this->db->update('sq_wallets', $update, ['user_id' => $userId]);

            $this->db->insert('sq_wallet_txns', [
                'user_id' => $userId, 'type' => $type, 'amount' => $amount,
                'balance_after' => $balance, 'note' => $note, 'ref_id' => $refId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            if (!$inOuter) $pdo->commit();
        } catch (\Throwable $e) {
            if (!$inOuter && $pdo->inTransaction()) $pdo->rollBack();
            error_log('SilverQueen creditWallet: ' . $e->getMessage());
        }
    }

    /** What this member has earned from the tree, split by level. */
    public function commissionSummary(int $userId): array
    {
        $out = ['total' => 0.0, 'levels' => [1 => 0.0, 2 => 0.0], 'count' => 0];
        foreach ([1, 2] as $lv) {
            $sum = (float) $this->db->sum('sq_commissions', 'amount', ['earner_id' => $userId, 'level' => $lv]);
            $out['levels'][$lv] = round($sum, 8);
            $out['total'] += $sum;
        }
        $out['total'] = round($out['total'], 8);
        $out['count'] = (int) $this->db->count('sq_commissions', ['earner_id' => $userId]);
        return $out;
    }

    // ------------------------------------------------------------- dashboards

    /**
     * Everything the standard dashboard renders: holdings, live yield rate, what is
     * claimable right now, and when the next 24h boundary lands.
     */
    public function memberSnapshot(int $userId): array
    {
        $allocations = $this->db->select('sq_allocations', '*', ['user_id' => $userId, 'ORDER' => ['id' => 'DESC']]);
        if (!is_array($allocations)) $allocations = [];

        $principal = 0.0; $pending = 0.0; $claimed = 0.0; $daily = 0.0; $units = 0; $active = 0;
        $nextAccrualTs = null;

        foreach ($allocations as $a) {
            $principal += (float) $a['principal'];
            $pending   += (float) $a['accrued'];
            $claimed   += (float) $a['claimed_total'];
            $units     += (int) $a['units'];
            if ($a['status'] === 'active') {
                $active++;
                $daily += (float) $a['principal'] * (float) $a['daily_rate'];
                $startTs = strtotime((string) $a['start_at']);
                $next    = $startTs + ((int) $a['days_accrued'] + 1) * self::DAY_SECS;
                if ($nextAccrualTs === null || $next < $nextAccrualTs) $nextAccrualTs = $next;
            }
        }

        $wallet     = $this->wallet($userId);
        $balance    = (float) $wallet['balance'];
        $anchor     = !empty($wallet['last_compound_at']) ? strtotime((string) $wallet['last_compound_at']) : null;
        $nextCompound = ($anchor && $balance > 0) ? $anchor + self::DAY_SECS : null;

        return [
            'qualified'        => $this->isQualified($userId),
            'owned_cards'      => $this->ownedCards($userId),
            'allocations'      => $allocations,
            'active_count'     => $active,
            'units'            => $units,
            'principal'        => round($principal, 8),
            'pending_yield'    => round($pending, 8),
            'claimed_total'    => round($claimed, 8),
            'daily_yield'      => round($daily, 8),
            'daily_rate'       => self::DAILY_RATE,
            'term_days'        => self::TERM_DAYS,
            'wallet'           => $wallet,
            'wallet_daily'     => round($balance * self::DAILY_RATE, 8),
            'next_accrual_at'  => $nextAccrualTs ? date('c', $nextAccrualTs) : null,
            'next_compound_at' => $nextCompound ? date('c', $nextCompound) : null,
            'transactions'     => $this->transactions($userId, 15),
            'commissions'      => $this->commissionSummary($userId),
            'referral'         => $this->referralRow($userId),
            'downline'         => $this->downline($userId),
            'payment'          => $this->paymentConfig(),
            'open_invoices'    => $this->openInvoices($userId),
            'rejected'         => $this->rejectedInvoices($userId),
        ];
    }

    /**
     * Elevated view: system-wide pools and the raw parameters the simulation runs on.
     * Only ever rendered for admins — it exposes total liquidity and per-member concentration.
     */
    public function adminSnapshot(): array
    {
        $sum = fn(string $t, string $c, array $w = []) => round((float) $this->db->sum($t, $c, $w ?: null), 8);

        $poolPrincipal = $sum('sq_allocations', 'principal', ['status' => 'active']);
        $walletFloat   = $sum('sq_wallets', 'balance');
        $unclaimed     = $sum('sq_allocations', 'accrued');
        $grossSales    = $sum('sq_purchases', 'total', ['status' => 'completed']);
        $commissions   = $sum('sq_commissions', 'amount');

        $top = $this->db->query(
            "SELECT a.user_id, SUM(a.principal) AS principal, SUM(a.units) AS units
               FROM sq_allocations a WHERE a.status = 'active'
              GROUP BY a.user_id ORDER BY principal DESC LIMIT 10"
        )->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        $names = $this->displayNames(array_column($top, 'user_id'));
        foreach ($top as &$t) $t['name'] = $names[(int) $t['user_id']] ?? ('Member #' . $t['user_id']);
        unset($t);

        $byProduct = $this->db->query(
            "SELECT product_code, COUNT(*) AS orders, SUM(units) AS units, SUM(total) AS revenue
               FROM sq_purchases WHERE status = 'completed'
              GROUP BY product_code ORDER BY revenue DESC"
        )->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $lastAccrual = $this->db->get('sq_accruals', ['created_at', 'accrued_for'], ['ORDER' => ['id' => 'DESC']]);
        $workerStamp = @filemtime(($this->storagePath()) . '/silverqueen_worker.stamp') ?: null;

        return [
            'members'          => (int) $this->db->count('sq_referrals', []),
            'qualified'        => $this->qualifiedCount(),
            'active_allocs'    => (int) $this->db->count('sq_allocations', ['status' => 'active']),
            'matured_allocs'   => (int) $this->db->count('sq_allocations', ['status' => 'completed']),
            'pool_principal'   => $poolPrincipal,
            'pool_daily_cost'  => round($poolPrincipal * self::DAILY_RATE, 8),
            'wallet_float'     => $walletFloat,
            'wallet_daily_cost'=> round($walletFloat * self::DAILY_RATE, 8),
            'unclaimed_yield'  => $unclaimed,
            'total_claimed'    => $sum('sq_wallets', 'total_claimed'),
            'total_compounded' => $sum('sq_wallets', 'total_compounded'),
            'gross_sales'      => $grossSales,
            'commissions_paid' => $commissions,
            'net_inflow'       => round($grossSales - $commissions, 8),
            'liability'        => round($walletFloat + $unclaimed, 8),
            'coverage_pct'     => $grossSales > 0 ? round((($walletFloat + $unclaimed) / $grossSales) * 100, 2) : 0.0,
            'accrual_rows'     => (int) $this->db->count('sq_accruals', []),
            'last_accrual'     => is_array($lastAccrual) ? $lastAccrual : null,
            'worker_last_run'  => $workerStamp ? date('c', $workerStamp) : null,
            'top_holders'      => $top,
            'by_product'       => $byProduct,
            'review_queue'     => $this->reviewQueue(),
            'awaiting_value'   => $sum('sq_purchases', 'total', ['status' => 'awaiting_confirmation']),
            'pending_value'    => $sum('sq_purchases', 'total', ['status' => 'pending']),
            'payment'          => $this->paymentConfig(),
            'verifier'         => [
                'min_confirmations' => (new UsdtBep20Verifier())->minConfirmations(),
                'usdt_contract'     => (new UsdtBep20Verifier())->contract(),
                'chain'             => 'BNB Smart Chain',
                'source'            => 'public JSON-RPC (keyless)',
            ],
            'params'           => [
                'daily_rate'          => self::DAILY_RATE,
                'term_days'           => self::TERM_DAYS,
                'day_seconds'         => self::DAY_SECS,
                'level_rates'         => self::LEVEL_RATES,
                'required_cards'      => self::REQUIRED_CARDS,
                'max_compound_cycles' => self::MAX_COMPOUND_CYCLES,
                'server_time'         => date('c'),
            ],
        ];
    }

    /** Members holding all three cards — the SQB-eligible population. */
    private function qualifiedCount(): int
    {
        $n = count(self::REQUIRED_CARDS);
        $in = "'" . implode("','", self::REQUIRED_CARDS) . "'";
        $row = $this->db->query(
            "SELECT COUNT(*) AS c FROM (
                SELECT user_id FROM sq_purchases
                 WHERE status = 'completed' AND product_code IN ($in)
                 GROUP BY user_id HAVING COUNT(DISTINCT product_code) = $n
             ) q"
        )->fetch(\PDO::FETCH_ASSOC);
        return (int) ($row['c'] ?? 0);
    }

    private function storagePath(): string
    {
        return defined('STORAGE_PATH') ? STORAGE_PATH : sys_get_temp_dir();
    }

    /** Touched by the cron worker so the admin panel can show when it last ran. */
    public function stampWorkerRun(): void
    {
        $dir = $this->storagePath();
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        @touch($dir . '/silverqueen_worker.stamp');
    }
}
