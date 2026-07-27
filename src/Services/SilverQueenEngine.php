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
                currency VARCHAR(10) NOT NULL DEFAULT 'USD',
                status ENUM('pending','completed','cancelled') NOT NULL DEFAULT 'completed',
                source VARCHAR(30) NOT NULL DEFAULT 'simulated',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_sq_purchases_user (user_id, status),
                KEY idx_sq_purchases_code (user_id, product_code, status),
                KEY idx_sq_purchases_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

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
                'currency' => 'USD', 'daily_rate' => $rate, 'term_days' => $term,
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

    // --------------------------------------------------------------- purchase

    /**
     * Record a purchase, allocate SQB units if applicable, and pay the 2-level
     * unilevel commissions. Returns ['ok' => bool, ...].
     *
     * Payment is simulated: the row lands as 'completed'. Wiring a real gateway
     * (the Academy's PayMongo flow, say) means creating the row as 'pending' here
     * and calling completePurchase() from the webhook instead.
     */
    public function purchase(int $userId, string $code, int $units = 1): array
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
        }
        if ($kind === 'engine' && !$this->isQualified($userId)) {
            return ['ok' => false, 'error' => 'SQB engines unlock once you hold all three membership cards.'];
        }

        $unitPrice = (float) $product['price'];
        $total     = round($unitPrice * $units, 8);
        $pdo       = $this->db->pdo;

        try {
            $pdo->beginTransaction();

            $this->db->insert('sq_purchases', [
                'user_id'      => $userId,
                'product_id'   => (int) $product['id'],
                'product_code' => $code,
                'units'        => $units,
                'unit_price'   => $unitPrice,
                'total'        => $total,
                'currency'     => (string) $product['currency'],
                'status'       => 'completed',
                'source'       => 'simulated',
                'created_at'   => date('Y-m-d H:i:s'),
            ]);
            $purchaseId = (int) $pdo->lastInsertId();

            $allocationId = null;
            if ($kind === 'engine') {
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
                    'start_at'    => date('Y-m-d H:i:s', $now),
                    'matures_at'  => date('Y-m-d H:i:s', $now + $term * self::DAY_SECS),
                    'created_at'  => date('Y-m-d H:i:s', $now),
                ]);
                $allocationId = (int) $pdo->lastInsertId();
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('SilverQueen purchase: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Could not complete the purchase.'];
        }

        // Commissions are settled outside the purchase transaction so a payout
        // problem can never roll back a purchase the member already made.
        $paid = $this->payCommissions($purchaseId, $userId, $total);

        return [
            'ok'            => true,
            'purchase_id'   => $purchaseId,
            'allocation_id' => $allocationId,
            'product'       => $product['name'],
            'units'         => $units,
            'total'         => $total,
            'qualified'     => $this->isQualified($userId),
            'commissions'   => $paid,
        ];
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
